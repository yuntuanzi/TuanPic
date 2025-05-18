<?php
define('DB_FILE', 'photos.db');
define('PIC_DIR', 'pic');
define('THUMB_DIR', 'thumbs');
define('DEFAULT_THUMB_WIDTH', 300);
define('DEFAULT_THUMB_HEIGHT', 200);
define('PER_PAGE', 20);

if (!file_exists(DB_FILE)) {
    die('请先运行 install.php 安装相册系统');
}

try {
    $db = new PDO('sqlite:'.DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

function get_categories($db) {
    $categories = ['推荐'];
    
    $stmt = $db->query(
        "SELECT DISTINCT category FROM images WHERE category != '' ORDER BY category"
    );
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['category'] ?? '';
    }
    
    return $categories;
}

$current_category = $_GET['category'] ?? '推荐';
$categories = get_categories($db);
$page = intval($_GET['page'] ?? 1);

if ($current_category === '推荐') {
    $stmt = $db->prepare(
        "SELECT i.*, t.path as thumb_path 
         FROM images i
         LEFT JOIN thumbnails t ON i.id = t.image_id AND t.width = :width AND t.height = :height
         ORDER BY i.created_at DESC
         LIMIT :limit OFFSET :offset"
    );
} else {
    $stmt = $db->prepare(
        "SELECT i.*, t.path as thumb_path 
         FROM images i
         LEFT JOIN thumbnails t ON i.id = t.image_id AND t.width = :width AND t.height = :height
         WHERE i.category = :category
         ORDER BY i.created_at DESC
         LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':category', $current_category);
}

$stmt->bindValue(':width', DEFAULT_THUMB_WIDTH, PDO::PARAM_INT);
$stmt->bindValue(':height', DEFAULT_THUMB_HEIGHT, PDO::PARAM_INT);
$stmt->bindValue(':limit', PER_PAGE, PDO::PARAM_INT);
$stmt->bindValue(':offset', ($page - 1) * PER_PAGE, PDO::PARAM_INT);
$stmt->execute();

$images = $stmt->fetchAll(PDO::FETCH_ASSOC);
$has_more = count($images) === PER_PAGE;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>团团的相册集 | 我的秘密基地</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .lazy {
            background: #f5f5f5;
            min-height: 200px;
        }
        .lazy.loaded {
            background: transparent;
        }
    </style>
    <style>
        .loader {
            --background: linear-gradient(135deg, #23C4F8, #275EFE);
            --shadow: rgba(39, 94, 254, 0.28);
            --text: #6C7486;
            --page: rgba(255, 255, 255, 0.36);
            --page-fold: rgba(255, 255, 255, 0.52);
            --duration: 3s;
            width: 200px;
            height: 140px;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
        .loader:before, .loader:after {
            --r: -6deg;
            content: "";
            position: absolute;
            bottom: 8px;
            width: 120px;
            top: 80%;
            box-shadow: 0 16px 12px var(--shadow);
            transform: rotate(var(--r));
        }
        .loader:before {
            left: 4px;
        }
        .loader:after {
            --r: 6deg;
            right: 4px;
        }
        .loader div {
            width: 100%;
            height: 100%;
            border-radius: 13px;
            position: relative;
            z-index: 1;
            perspective: 600px;
            box-shadow: 0 4px 6px var(--shadow);
            background-image: var(--background);
        }
        .loader div ul {
            margin: 0;
            padding: 0;
            list-style: none;
            position: relative;
        }
        .loader div ul li {
            --r: 180deg;
            --o: 0;
            --c: var(--page);
            position: absolute;
            top: 10px;
            left: 10px;
            transform-origin: 100% 50%;
            color: var(--c);
            opacity: var(--o);
            transform: rotateY(var(--r));
            -webkit-animation: var(--duration) ease infinite;
            animation: var(--duration) ease infinite;
        }
        .loader div ul li:nth-child(2) {
            --c: var(--page-fold);
            -webkit-animation-name: page-2;
            animation-name: page-2;
        }
        .loader div ul li:nth-child(3) {
            --c: var(--page-fold);
            -webkit-animation-name: page-3;
            animation-name: page-3;
        }
        .loader div ul li:nth-child(4) {
            --c: var(--page-fold);
            -webkit-animation-name: page-4;
            animation-name: page-4;
        }
        .loader div ul li:nth-child(5) {
            --c: var(--page-fold);
            -webkit-animation-name: page-5;
            animation-name: page-5;
        }
        .loader div ul li svg {
            width: 90px;
            height: 120px;
            display: block;
        }
        .loader div ul li:first-child {
            --r: 0deg;
            --o: 1;
        }
        .loader div ul li:last-child {
            --o: 1;
        }
        .loader span {
            display: block;
            left: 0;
            right: 0;
            top: 100%;
            margin-top: 20px;
            text-align: center;
            color: var(--text);
        }
        @keyframes page-2 {
            0% {
                transform: rotateY(180deg);
                opacity: 0;
            }
            20% {
                opacity: 1;
            }
            35%, 100% {
                opacity: 0;
            }
            50%, 100% {
                transform: rotateY(0deg);
            }
        }
        @keyframes page-3 {
            15% {
                transform: rotateY(180deg);
                opacity: 0;
            }
            35% {
                opacity: 1;
            }
            50%, 100% {
                opacity: 0;
            }
            65%, 100% {
                transform: rotateY(0deg);
            }
        }
        @keyframes page-4 {
            30% {
                transform: rotateY(180deg);
                opacity: 0;
            }
            50% {
                opacity: 1;
            }
            65%, 100% {
                opacity: 0;
            }
            80%, 100% {
                transform: rotateY(0deg);
            }
        }
        @keyframes page-5 {
            45% {
                transform: rotateY(180deg);
                opacity: 0;
            }
            65% {
                opacity: 1;
            }
            80%, 100% {
                opacity: 0;
            }
            95%, 100% {
                transform: rotateY(0deg);
            }
        }
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: white;
            z-index: 9998;
            pointer-events: all;
        }
    </style>
</head>
<body>
<div class="overlay" id="overlay"></div>
<header>
    <div class="decor-circle"></div>
    <div class="decor-circle"></div>
    <div class="decor-circle"></div><div class="decor-circle"></div><div class="decor-circle"></div>
    <div class="header-content container">
        <h1 class="site-title">团团的相册集</h1>
        <p class="site-subtitle">欢迎来到我的秘密基地</p>
    </div>
</header>
    
<nav class="category-nav">
    <div class="container">
        <div class="categories">
            <?php foreach ($categories as $index => $category): ?>
                <a href="?category=<?= urlencode($category ?? '') ?>" 
                   class="category-btn <?= ($category ?? '') === $current_category ? 'active' : '' ?> animate-fade-in delay-<?= $index % 5 + 1 ?>">
                    <?= htmlspecialchars($category ?? '') ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>
    
<main class="gallery-container">
    <div class="container">
        <?php if (empty($images)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-images"></i>
                </div>
                <h3 class="empty-text">暂无照片</h3>
            </div>
        <?php else: ?>
            <div class="gallery-grid" id="galleryGrid">
                <?php foreach ($images as $index => $image): ?>
                    <div class="gallery-item animate-fade-in delay-<?= $index % 5 + 1 ?>">
                        <img src="<?= htmlspecialchars($image['thumb_path'] ?? '') ?>" 
                             data-src="<?= htmlspecialchars($image['path'] ?? '') ?>"
                             alt="<?= htmlspecialchars($image['filename'] ?? '') ?>" 
                             class="gallery-img lazy"
                             data-full="<?= htmlspecialchars($image['path'] ?? '') ?>"
                             data-category="<?= htmlspecialchars($image['category'] ?? '') ?>"
                             data-title="<?= htmlspecialchars($image['filename'] ?? '') ?>">
                             
                        <div class="gallery-overlay">
                            <div class="image-title"><?= htmlspecialchars($image['filename'] ?? '') ?></div>
                            <div class="image-category">
                                <i class="fas fa-folder"></i>
                                <?= htmlspecialchars($image['category'] ?? '') ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($has_more): ?>
                <div class="load-more" id="loadMoreIndicator" data-page="<?= $page ?>">加载更多图片...</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
    
<div class="modal-overlay" id="imageModal">
    <div class="modal-content">
        <div class="modal-image-container">
            <img src="" alt="" class="modal-image" id="modalImage">
            <div class="close-modal" id="closeModal">
                <i class="fas fa-times"></i>
            </div>
        </div>
        <div class="modal-sidebar">
            <h2 class="modal-title" id="modalTitle"></h2>
            <div class="modal-category" id="modalCategory"></div>
            
            <div class="modal-actions">
                <button class="modal-btn" id="downloadBtn">
                    <i class="fas fa-download"></i>
                    <span>下载原图</span>
                </button>
                <button class="modal-btn" id="copyLinkBtn">
                    <i class="fas fa-link"></i>
                    <span>复制链接</span>
                </button>
                <button class="modal-btn" id="shareBtn">
                    <i class="fas fa-share-alt"></i>
                    <span>分享</span>
                </button>
            </div>
            
            <h3 class="recommendations-title">其他推荐图片</h3>
            <div class="recommendations-grid" id="recommendationsGrid">
            </div>
            <div class="modal-copyright">
                &copy; <?= date('Y') ?> 摘星团团. 保留所有权利.<br>未经许可禁止转载
            </div>
        </div>
    </div>
</div>
    
<footer>
    <div class="container">
        <div class="footer-content">
            <div class="footer-column footer-about">
                <h3>关于本站</h3>
                <p>欢迎来到团团的相册~</p>
            </div>
            
            <div class="footer-column">
                <h3>导航</h3>
                <ul class="footer-links">
                    <li><a href="#"><i class="fas fa-home"></i> 首页</a></li>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3>照片分类</h3>
                <ul class="footer-links">
                    <?php foreach (array_slice($categories, 0, 6) as $category): ?>
                        <li><a href="?category=<?= urlencode($category ?? '') ?>"><i class="fas fa-folder"></i> <?= htmlspecialchars($category ?? '') ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="footer-column">
                <h3>联系我</h3>
                <ul class="footer-links">
                    <li><a href="mailto:ccssna@qq.com"><i class="fas fa-envelope"></i> 电子邮件：ccssna@qq.com</a></li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            &copy; <?= date('Y') ?> 摘星团团. 保留所有权利.
        </div>
    </div>
</footer>

<div class="loader" id="loader">
    <div>
        <ul>
            <li>
                <svg fill="currentColor" viewBox="0 0 90 120">
                    <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                </svg>
            </li>
            <li>
                <svg fill="currentColor" viewBox="0 0 90 120">
                    <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                </svg>
            </li>
            <li>
                <svg fill="currentColor" viewBox="0 0 90 120">
                    <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                </svg>
            </li>
            <li>
                <svg fill="currentColor" viewBox="0 0 90 120">
                    <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                </svg>
            </li>
            <li>
                <svg fill="currentColor" viewBox="0 0 90 120">
                    <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                </svg>
            </li>
            <li>
                <svg fill="currentColor" viewBox="0 0 90 120">
                    <path d="M90,0 L90,120 L11,120 C4.92486775,120 0,115.075132 0,109 L0,11 C0,4.92486775 4.92486775,0 11,0 L90,0 Z M71.5,81 L18.5,81 C17.1192881,81 16,82.1192881 16,83.5 C16,84.8254834 17.0315359,85.9100387 18.3356243,85.9946823 L18.5,86 L71.5,86 C72.8807119,86 74,84.8807119 74,83.5 C74,82.1745166 72.9684641,81.0899613 71.6643757,81.0053177 L71.5,81 Z M71.5,57 L18.5,57 C17.1192881,57 16,58.1192881 16,59.5 C16,60.8254834 17.0315359,61.9100387 18.3356243,61.9946823 L18.5,62 L71.5,62 C72.8807119,62 74,60.8807119 74,59.5 C74,58.1192881 72.8807119,57 71.5,57 Z M71.5,33 L18.5,33 C17.1192881,33 16,34.1192881 16,35.5 C16,36.8254834 17.0315359,37.9100387 18.3356243,37.9946823 L18.5,38 L71.5,38 C72.8807119,38 74,36.8807119 74,35.5 C74,34.1192881 72.8807119,33 71.5,33 Z"></path>
                </svg>
            </li>
        </ul>
    </div>
    <span>图片加载需要亿点点时间~</span>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            document.getElementById('loader').style.display = 'none';
            document.getElementById('overlay').style.display = 'none';
            document.getElementById('content').style.display = 'block';
        }, 3000);
    });
</script>
<script>
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalTitle = document.getElementById('modalTitle');
    const modalCategory = document.getElementById('modalCategory');
    const closeModal = document.getElementById('closeModal');
    const downloadBtn = document.getElementById('downloadBtn');
    const copyLinkBtn = document.getElementById('copyLinkBtn');
    const shareBtn = document.getElementById('shareBtn');
    const recommendationsGrid = document.getElementById('recommendationsGrid');
    const galleryGrid = document.getElementById('galleryGrid');
    const loadMoreIndicator = document.getElementById('loadMoreIndicator');
    
    let currentImage = null;
    let isLoading = false;
    let hasMore = <?= $has_more ? 'true' : 'false' ?>;
    let currentPage = <?= $page ?>;
    
    const lazyLoadObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                if (img.dataset.src) {
                    img.src = img.dataset.src;
                    img.classList.add('loaded');
                    img.removeAttribute('data-src');
                    lazyLoadObserver.unobserve(img);
                }
            }
        });
    }, { rootMargin: '200px' });
    
    document.querySelectorAll('.gallery-img.lazy').forEach(img => {
        lazyLoadObserver.observe(img);
    });
    
    document.querySelectorAll('.gallery-item').forEach(item => {
        item.addEventListener('click', function() {
            const img = this.querySelector('.gallery-img');
            currentImage = {
                src: img.getAttribute('data-full') || img.src,
                title: img.getAttribute('data-title') || '',
                category: img.getAttribute('data-category') || ''
            };
            
            openModal(currentImage);
        });
    });
    
    function openModal(image) {
        currentImage = image;
        modalImage.src = image.src;
        modalTitle.textContent = image.title;
        modalCategory.innerHTML = `<i class="fas fa-folder"></i> ${image.category}`;
        loadRecommendations();
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    closeModal.addEventListener('click', function() {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    });
    
    downloadBtn.addEventListener('click', function() {
        if (currentImage) {
            const link = document.createElement('a');
            link.href = currentImage.src;
            link.download = (currentImage.title || 'image') + '.' + (currentImage.src.split('.').pop() || 'jpg');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });
    
    copyLinkBtn.addEventListener('click', function() {
        if (currentImage) {
            const url = window.location.protocol + '//' + window.location.host + '/' + (currentImage.src || '');
            
            navigator.clipboard.writeText(url)
                .then(() => {
                    const originalText = copyLinkBtn.querySelector('span').textContent;
                    copyLinkBtn.querySelector('span').textContent = '已复制';
                    
                    setTimeout(() => {
                        copyLinkBtn.querySelector('span').textContent = originalText;
                    }, 2000);
                })
                .catch(err => {
                    console.error('复制失败: ', err);
                });
        }
    });
    
    shareBtn.addEventListener('click', function() {
        if (navigator.share && currentImage) {
            const url = window.location.protocol + '//' + window.location.host + '/' + (currentImage.src || '');
            
            navigator.share({
                title: currentImage.title || '图片分享',
                text: '快看看这张照片: ' + (currentImage.title || ''),
                url: url
            })
            .catch(err => {
                console.error('分享失败:', err);
            });
        } else {
            alert('您的浏览器不支持原生分享功能，请手动复制链接分享。');
        }
    });
    
    async function loadRecommendations() {
        recommendationsGrid.innerHTML = '<div class="loading-spinner"></div>';
        
        try {
            const response = await fetch(`api/recommendations.php?category=${encodeURIComponent(currentImage?.category || '')}&current=${encodeURIComponent(currentImage?.src || '')}`);
            const result = await response.json();
            
            if (!result.success) throw new Error(result.error || '未知错误');
            
            recommendationsGrid.innerHTML = '';
            
            result.data.forEach(image => {
                const item = document.createElement('div');
                item.className = 'recommendation-item';
                item.innerHTML = `
                    <img src="${image.thumb_path || image.path || ''}" 
                         class="recommendation-img lazy"
                         data-src="${image.path || ''}"
                         data-full="${image.path || ''}"
                         data-category="${image.category || ''}"
                         data-title="${image.filename || ''}">
                `;
                
                item.addEventListener('click', () => {
                    openModal({
                        src: image.path || '',
                        title: image.filename || '',
                        category: image.category || ''
                    });
                });
                
                recommendationsGrid.appendChild(item);
                lazyLoadObserver.observe(item.querySelector('img'));
            });
            
        } catch (error) {
            console.error('加载推荐失败:', error);
            recommendationsGrid.innerHTML = '<div class="error-message">加载推荐失败</div>';
        }
    }
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in');
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.gallery-item').forEach(item => {
        observer.observe(item);
    });
    
    if (loadMoreIndicator) {
        const scrollObserver = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !isLoading && hasMore) {
                loadMoreImages();
            }
        }, { threshold: 0.1 });
        
        scrollObserver.observe(loadMoreIndicator);
    }
    
    async function loadMoreImages() {
        isLoading = true;
        currentPage++;
        loadMoreIndicator.textContent = '加载中...';
        
        try {
            const response = await fetch(`api/images.php?category=<?= urlencode($current_category) ?>&page=${currentPage}`);
            const result = await response.json();
            
            if (!result.success) throw new Error(result.error || '未知错误');
            
            if (result.data.length === 0) {
                hasMore = false;
                loadMoreIndicator.textContent = '没有更多图片了';
                return;
            }
            
            result.data.forEach(image => {
                const item = document.createElement('div');
                item.className = 'gallery-item';
                item.innerHTML = `
                    <img src="${image.thumb_path || image.path || ''}" 
                         data-src="${image.path || ''}"
                         alt="${image.filename || ''}" 
                         class="gallery-img lazy"
                         data-full="${image.path || ''}"
                         data-category="${image.category || ''}"
                         data-title="${image.filename || ''}">
                    <div class="gallery-overlay">
                        <div class="image-title">${image.filename || ''}</div>
                        <div class="image-category">
                            <i class="fas fa-folder"></i>
                            ${image.category || ''}
                        </div>
                    </div>
                `;
                
                item.addEventListener('click', () => {
                    openModal({
                        src: image.path || '',
                        title: image.filename || '',
                        category: image.category || ''
                    });
                });
                
                galleryGrid.appendChild(item);
                observer.observe(item);
                lazyLoadObserver.observe(item.querySelector('img'));
            });
            
            hasMore = result.hasMore;
            loadMoreIndicator.textContent = hasMore ? '加载更多图片...' : '没有更多图片了';
            
        } catch (error) {
            console.error('加载更多图片失败:', error);
            loadMoreIndicator.textContent = '加载失败，点击重试';
            loadMoreIndicator.onclick = loadMoreImages;
            currentPage--;
        } finally {
            isLoading = false;
        }
    }

    document.addEventListener('keydown', function(e) {
        if (!modal.classList.contains('active')) return;
        
        if (e.key === 'Escape') {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        } else if (e.key === 'ArrowLeft') {
            navigateImage(-1);
        } else if (e.key === 'ArrowRight') {
            navigateImage(1);
        }
    });

    function navigateImage(direction) {
        const allImages = Array.from(document.querySelectorAll('.gallery-img'));
        const currentIndex = allImages.findIndex(img => 
            (img.getAttribute('data-full') || img.src) === currentImage?.src
        );
        
        if (currentIndex !== -1) {
            let newIndex = currentIndex + direction;
            
            if (newIndex < 0) newIndex = allImages.length - 1;
            if (newIndex >= allImages.length) newIndex = 0;
            
            const newImg = allImages[newIndex];
            openModal({
                src: newImg.getAttribute('data-full') || newImg.src || '',
                title: newImg.getAttribute('data-title') || '',
                category: newImg.getAttribute('data-category') || ''
            });
        }
    }
</script>
</body>
</html>