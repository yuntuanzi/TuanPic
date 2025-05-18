<?php
header('Content-Type: application/json');
define('DB_FILE', '../photos.db');

try {
    $db = new PDO('sqlite:'.DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败']);
    exit;
}

$category = $_GET['category'] ?? '';
$current = $_GET['current'] ?? '';

$stmt = $db->prepare(
    "SELECT i.*, t.path as thumb_path 
     FROM images i
     LEFT JOIN thumbnails t ON i.id = t.image_id AND t.width = 300 AND t.height = 200
     WHERE i.category = :category AND i.path != :current
     ORDER BY RANDOM()
     LIMIT 8"
);

$stmt->bindValue(':category', $category);
$stmt->bindValue(':current', $current);
$stmt->execute();

$recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $recommendations
]);