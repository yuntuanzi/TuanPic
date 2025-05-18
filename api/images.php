<?php
header('Content-Type: application/json');
define('DB_FILE', '../photos.db');

try {
    $db = new PDO('sqlite:'.DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $category = $_GET['category'] ?? '推荐';
    $page = intval($_GET['page'] ?? 1);
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    if ($category === '推荐') {
        $stmt = $db->prepare(
            "SELECT i.*, t.path as thumb_path 
             FROM images i
             LEFT JOIN thumbnails t ON i.id = t.image_id AND t.width = 300 AND t.height = 200
             ORDER BY i.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
    } else {
        $stmt = $db->prepare(
            "SELECT i.*, t.path as thumb_path 
             FROM images i
             LEFT JOIN thumbnails t ON i.id = t.image_id AND t.width = 300 AND t.height = 200
             WHERE i.category = :category
             ORDER BY i.created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':category', $category);
    }
    
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $images,
        'hasMore' => count($images) === $perPage
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>