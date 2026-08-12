<?php
require_once 'config.php';
header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
    $stmt->execute([$product_id]);
    $variants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $gStmt = $pdo->prepare("SELECT * FROM product_gallery WHERE product_id = ? ORDER BY id ASC");
    $gStmt->execute([$product_id]);
    $gallery = $gStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true, 
        'variants' => $variants,
        'gallery' => $gallery
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
}
?>
