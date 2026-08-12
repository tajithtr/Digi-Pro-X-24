<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$country_code = isset($_POST['country_code']) ? trim($_POST['country_code']) : '';

if (empty($country_code)) {
    echo json_encode(['success' => false, 'message' => 'Country code is required.']);
    exit;
}

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();

// Normalize the country code
$normalized_code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $country_code));
if ($normalized_code === 'lk' || $normalized_code === 'lka' || $normalized_code === 'srilanka' || $normalized_code === 'sl') {
    $normalized_code = 'LK';
} else {
    $normalized_code = strtoupper($normalized_code);
}

// Calculate delivery charge from cart
$delivery_charge = 0;
$has_items = false;
$cart_items = $_SESSION['cart'] ?? [];
if (!empty($cart_items)) {
    $product_ids = [];
    foreach (array_keys($cart_items) as $cart_key) {
        $parts = explode(':', $cart_key);
        $product_ids[] = (int)$parts[0];
    }
    $product_ids = array_unique($product_ids);
    if (!empty($product_ids)) {
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt = $pdo->prepare("SELECT id, shipping_fee FROM products WHERE id IN ($placeholders)");
        $stmt->execute($product_ids);
        $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($dbProducts as $p) {
            $has_items = true;
            $qty = 0;
            foreach ($cart_items as $cart_key => $cart_qty) {
                $parts = explode(':', $cart_key);
                if ((int)$parts[0] === (int)$p['id']) {
                    $qty += (int)$cart_qty;
                }
            }
            $item_shipping = isset($p['shipping_fee']) ? (float)$p['shipping_fee'] : 450.00;
            $delivery_charge += $item_shipping * $qty;
        }
    }
}

try {
    $sum_shipping_fee = 0.00;
    $has_rate = false;
    $can_deliver = true;
    
    if (!empty($product_ids)) {
        $s_stmt = $pdo->prepare("SELECT fee FROM product_shipping_rates WHERE product_id = ? AND country_code = ? LIMIT 1");
        foreach ($dbProducts as $p) {
            $qty = 0;
            foreach ($cart_items as $cart_key => $cart_qty) {
                $parts = explode(':', $cart_key);
                if ((int)$parts[0] === (int)$p['id']) {
                    $qty += (int)$cart_qty;
                }
            }
            $s_stmt->execute([$p['id'], $normalized_code]);
            $rate_val = $s_stmt->fetchColumn();
            if ($rate_val !== false) {
                $has_rate = true;
                $item_fee = (float)$rate_val;
                $sum_shipping_fee += $item_fee * $qty;
            } else {
                $can_deliver = false;
                break;
            }
        }
    } else {
        $can_deliver = false;
    }

    if ($can_deliver && $has_rate) {
        $final_fee = $sum_shipping_fee;
        echo json_encode([
            'success' => true,
            'fee' => $final_fee,
            'country_code' => $normalized_code
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Delivery is not available for this country.'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching shipping rates.'
    ]);
}
?>
