<?php
define('DB_FILE', dirname(__DIR__) . '/photos.db');
define('PIC_DIR', dirname(__DIR__) . '/pic');
define('THUMB_DIR', dirname(__DIR__) . '/thumbs');
define('THUMB_MAX_WIDTH', 300);
define('THUMB_MAX_HEIGHT', 300);

if (!file_exists(PIC_DIR) || !is_dir(PIC_DIR)) {
    die("错误：图片目录 " . PIC_DIR . " 不存在或不是目录");
}

if (!file_exists(DB_FILE)) {
    die('数据库不存在，请先运行install.php安装系统');
}

try {
    $db = new PDO('sqlite:'.DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (!file_exists(THUMB_DIR)) {
        if (!mkdir(THUMB_DIR, 0755, true)) {
            die("无法创建缩略图目录: " . THUMB_DIR);
        }
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(PIC_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    $processed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            continue;
        }
        
        try {
            $path = $file->getPathname();
            $relativePath = substr($path, strlen(PIC_DIR) + 1);
            $filename = $file->getFilename();
            $category = basename($file->getPath());
            
            if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
                continue;
            }
            
            $stmt = $db->prepare("SELECT id, last_modified FROM images WHERE path = ?");
            $stmt->execute(["pic/$relativePath"]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $filemtime = $file->getMTime();
            if ($existing && $existing['last_modified'] >= $filemtime) {
                $skipped++;
                continue;
            }
            
            $size = getimagesize($path);
            if (!$size) {
                $errors++;
                error_log("无法读取图片尺寸: $path");
                continue;
            }
            
            list($width, $height) = $size;
            $filesize = $file->getSize();
            
            $thumbWidth = $width;
            $thumbHeight = $height;
            
            if ($width > THUMB_MAX_WIDTH || $height > THUMB_MAX_HEIGHT) {
                $ratio = min(THUMB_MAX_WIDTH / $width, THUMB_MAX_HEIGHT / $height);
                $thumbWidth = (int)round($width * $ratio);
                $thumbHeight = (int)round($height * $ratio);
            }
            
            $thumbPath = THUMB_DIR . '/' . $relativePath;
            $thumbDir = dirname($thumbPath);
            
            if (!file_exists($thumbDir)) {
                if (!mkdir($thumbDir, 0755, true)) {
                    $errors++;
                    error_log("无法创建缩略图子目录: $thumbDir");
                    continue;
                }
            }
            
            $source = null;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $source = imagecreatefromjpeg($path);
                    break;
                case 'png':
                    $source = imagecreatefrompng($path);
                    break;
                case 'gif':
                    $source = imagecreatefromgif($path);
                    break;
                case 'webp':
                    $source = imagecreatefromwebp($path);
                    break;
            }
            
            if (!$source) {
                $errors++;
                error_log("无法创建图像资源: $path");
                continue;
            }
            
            $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
            
            if ($ext === 'png' || $ext === 'gif') {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                imagefilledrectangle($thumb, 0, 0, $thumbWidth, $thumbHeight, $transparent);
            }
            
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
            
            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($thumb, $thumbPath, 85);
                    break;
                case 'png':
                    imagepng($thumb, $thumbPath, 9);
                    break;
                case 'gif':
                    imagegif($thumb, $thumbPath);
                    break;
                case 'webp':
                    imagewebp($thumb, $thumbPath, 85);
                    break;
            }
            
            imagedestroy($source);
            imagedestroy($thumb);
            
            if ($existing) {
                $stmt = $db->prepare("UPDATE images SET 
                    category = ?, 
                    filename = ?, 
                    filesize = ?, 
                    width = ?, 
                    height = ?, 
                    last_modified = ? 
                    WHERE id = ?");
                $stmt->execute([
                    $category,
                    $filename,
                    $filesize,
                    $width,
                    $height,
                    $filemtime,
                    $existing['id']
                ]);
                
                $stmt = $db->prepare("UPDATE thumbnails SET 
                    width = ?, 
                    height = ?, 
                    path = ? 
                    WHERE image_id = ?");
                $stmt->execute([
                    $thumbWidth,
                    $thumbHeight,
                    "thumbs/$relativePath",
                    $existing['id']
                ]);
            } else {
                $db->beginTransaction();
                
                $stmt = $db->prepare("INSERT INTO images 
                    (path, category, filename, filesize, width, height, last_modified) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    "pic/$relativePath",
                    $category,
                    $filename,
                    $filesize,
                    $width,
                    $height,
                    $filemtime
                ]);
                
                $imageId = $db->lastInsertId();
                
                $stmt = $db->prepare("INSERT INTO thumbnails 
                    (image_id, width, height, path) 
                    VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $imageId,
                    $thumbWidth,
                    $thumbHeight,
                    "thumbs/$relativePath"
                ]);
                
                $db->commit();
            }
            
            $processed++;
            
        } catch (Exception $e) {
            $errors++;
            error_log("处理图片 $path 时出错: " . $e->getMessage());
            continue;
        }
    }
    
    echo "处理完成: 
    - 新增/更新 {$processed} 张图片
    - 跳过 {$skipped} 张未修改的图片
    - 遇到 {$errors} 个错误";
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    die("数据库错误: " . $e->getMessage());
} catch (Exception $e) {
    die("系统错误: " . $e->getMessage());
}
?>