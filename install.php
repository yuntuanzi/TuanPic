<?php
define('DB_FILE', 'photos.db');
define('PIC_DIR', 'pic');
define('THUMB_DIR', 'thumbs');

if (file_exists(DB_FILE)) {
    die('相册系统已经安装。如需重新安装，请先删除 '.DB_FILE);
}

try {
    $db = new PDO('sqlite:'.DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("CREATE TABLE IF NOT EXISTS images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        path TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL,
        filename TEXT NOT NULL,
        filesize INTEGER NOT NULL,
        width INTEGER,
        height INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_modified INTEGER NOT NULL
    )");
    
    $db->exec("CREATE TABLE IF NOT EXISTS thumbnails (
        image_id INTEGER NOT NULL,
        width INTEGER NOT NULL,
        height INTEGER NOT NULL,
        path TEXT NOT NULL UNIQUE,
        FOREIGN KEY (image_id) REFERENCES images(id),
        PRIMARY KEY (image_id, width, height)
    )");
    
    if (!file_exists(THUMB_DIR)) {
        mkdir(THUMB_DIR, 0755, true);
    }
    
    echo "安装成功！数据库已创建。";
    
} catch (PDOException $e) {
    unlink(DB_FILE);
    die("安装失败: " . $e->getMessage());
}
?>