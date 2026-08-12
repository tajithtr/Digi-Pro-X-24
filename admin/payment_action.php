<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$action   = $_POST['action'] ?? '';

if (!$order_id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, payment_method, status FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'verified', status = IF(status = 'pending', 'processing', status) WHERE id = ?");
    $stmt->execute([$order_id]);
    echo json_encode(['success' => true, 'message' => 'Payment approved. Order moved to processing.', 'new_status' => 'verified']);
} else {
    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'rejected' WHERE id = ?");
    $stmt->execute([$order_id]);
    echo json_encode(['success' => true, 'message' => 'Payment rejected.', 'new_status' => 'rejected']);
}
