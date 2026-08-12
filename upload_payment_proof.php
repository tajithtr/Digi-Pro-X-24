<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
$transaction_id = trim($_POST['transaction_id'] ?? '');

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid order.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, payment_method, payment_slip, transaction_id FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

$updates = [];
$params  = [];

if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
    $file_type = mime_content_type($_FILES['payment_slip']['tmp_name']);
    $file_size = $_FILES['payment_slip']['size'];
    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, WEBP, PDF.']);
        exit;
    }
    if ($file_size > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum 10MB.']);
        exit;
    }
    $ext      = strtolower(pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION));
    $filename = 'slip_order' . $order_id . '_' . time() . '.' . $ext;
    $upload_dir = __DIR__ . '/uploads/payment_slips/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
    if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $upload_dir . $filename)) {
        $updates[] = 'payment_slip = ?';
        $params[]  = 'uploads/payment_slips/' . $filename;
        $updates[] = 'payment_status = ?';
        $params[]  = 'awaiting_verification';
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
        exit;
    }
}

if ($transaction_id !== '') {
    $updates[] = 'transaction_id = ?';
    $params[]  = $transaction_id;
    if (!in_array('payment_status = ?', $updates)) {
        $updates[] = 'payment_status = ?';
        $params[]  = 'awaiting_verification';
    }
}

if (empty($updates)) {
    echo json_encode(['success' => false, 'message' => 'Nothing to update.']);
    exit;
}

$params[] = $order_id;
$params[] = $_SESSION['user_id'];
$sql  = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success' => true, 'message' => 'Payment proof submitted. Our team will verify shortly.']);
