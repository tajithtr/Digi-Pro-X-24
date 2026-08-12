<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? '';
$raw_id = $_POST['product_id'] ?? '';
$parts = explode(':', $raw_id);
$product_id = isset($parts[0]) ? (int)$parts[0] : 0;

if ($action === 'add' && $product_id > 0) {
    $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 1;
    if ($qty < 1) $qty = 1;
    
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $stock = $stmt->fetchColumn();
    
    if ($stock === false) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    $stock = (int)$stock;
    if ($stock <= 0) {
        echo json_encode(['success' => false, 'message' => 'This product is out of stock']);
        exit;
    }

    // Process variant IDs
    $variants = isset($_POST['variants']) ? trim($_POST['variants']) : '';
    $variant_arr = array_filter(array_map('intval', explode(',', $variants)));
    sort($variant_arr);
    $variant_str = implode(',', $variant_arr);
    
    $cart_key = $variant_str !== '' ? "$product_id:$variant_str" : (string)$product_id;
    
    $current_qty = $_SESSION['cart'][$cart_key] ?? 0;
    $new_qty = $current_qty + $qty;
    if ($new_qty > $stock) {
        $new_qty = $stock;
    }
    
    $_SESSION['cart'][$cart_key] = $new_qty;
    
    $cart_count = array_sum($_SESSION['cart']);
    echo json_encode(['success' => true, 'cart_count' => $cart_count]);
    exit;
}

// For changing quantities in the cart page/drawer
if ($action === 'update' && !empty($raw_id)) {
    $qty = isset($_POST['qty']) ? (int)$_POST['qty'] : 0;
    if ($qty > 0) {
        $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $stock = (int)$stmt->fetchColumn();
        
        if ($qty > $stock) {
            $qty = $stock;
        }
        $_SESSION['cart'][$raw_id] = $qty;
    } else {
        unset($_SESSION['cart'][$raw_id]);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'remove' && !empty($raw_id)) {
    unset($_SESSION['cart'][$raw_id]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
