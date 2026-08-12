<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

$cart = $_SESSION['cart'] ?? [];
$items = [];
$subtotal = 0;
$delivery_charge = 0;

if (!empty($cart)) {
    // Collect unique product ids
    $product_ids = [];
    foreach (array_keys($cart) as $cart_key) {
        $parts = explode(':', $cart_key);
        $product_ids[] = (int)$parts[0];
    }
    $product_ids = array_unique($product_ids);
    
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $dbProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $productsById = [];
    foreach ($dbProducts as $p) {
        $productsById[$p['id']] = $p;
    }

    $normalized_code = '';
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_country'])) {
        $raw_country = trim($_SESSION['user_country']);
        $normalized_code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $raw_country));
        if ($normalized_code === 'lk' || $normalized_code === 'lka' || $normalized_code === 'srilanka' || $normalized_code === 'sl') {
            $normalized_code = 'LK';
        } else {
            $normalized_code = strtoupper($normalized_code);
        }
    }
    
    foreach ($cart as $cart_key => $qty) {
        $parts = explode(':', $cart_key);
        $p_id = (int)$parts[0];
        $variant_str = isset($parts[1]) ? $parts[1] : '';
        
        if (!isset($productsById[$p_id])) {
            continue;
        }
        
        $product = $productsById[$p_id];
        
        // Calculate dynamic price
        $price = (float)$product['price'];
        if ((int)$product['discount_percent'] > 0) {
            $price = $price * (1 - ((int)$product['discount_percent'] / 100));
        }
        
        // Check flash sale
        $has_flash = !empty($product['flash_sale_price']) && !empty($product['flash_sale_start']) && !empty($product['flash_sale_end']);
        if ($has_flash) {
            $now = date('Y-m-d H:i:s');
            if ($now >= $product['flash_sale_start'] && $now <= $product['flash_sale_end']) {
                $price = (float)$product['flash_sale_price'];
            }
        }
        
        // Add variant modifiers
        $variant_names = [];
        $variant_img = '';
        if (!empty($variant_str)) {
            $v_ids = array_filter(array_map('intval', explode(',', $variant_str)));
            if (!empty($v_ids)) {
                $v_placeholders = implode(',', array_fill(0, count($v_ids), '?'));
                $vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE id IN ($v_placeholders)");
                $vStmt->execute($v_ids);
                $variants_db = $vStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($variants_db as $vItem) {
                    $price += (float)$vItem['price_modifier'];
                    $variant_names[] = $vItem['variant_value'];
                    if (!empty($vItem['image'])) {
                        $variant_img = $vItem['image'];
                    }
                }
            }
        }
        
        $itemTotal = $price * $qty;
        $subtotal += $itemTotal;

        // Calculate item shipping fee
        $item_shipping = isset($product['shipping_fee']) ? (float)$product['shipping_fee'] : 450.00;
        if (!empty($normalized_code)) {
            try {
                $rateStmt = $pdo->prepare("SELECT fee FROM product_shipping_rates WHERE product_id = ? AND country_code = ? LIMIT 1");
                $rateStmt->execute([$p_id, $normalized_code]);
                $fee_val = $rateStmt->fetchColumn();
                if ($fee_val !== false) {
                    $item_shipping = (float)$fee_val;
                }
            } catch (Exception $e) {}
        }
        $delivery_charge += $item_shipping * $qty;
        
        // Construct image path
        $imgPath = !empty($variant_img) ? $variant_img : $product['image'];
        
        // Construct display name with variants
        $displayName = $product['name'];
        if (!empty($variant_names)) {
            $displayName .= ' (' . implode(', ', $variant_names) . ')';
        }
        
        $items[] = [
            'id' => $cart_key,
            'name' => $displayName,
            'image' => $imgPath,
            'price' => $price,
            'qty' => $qty,
            'shipping_fee' => $item_shipping * $qty,
            'single_shipping_fee' => $item_shipping,
            'total' => $itemTotal
        ];
    }
}

$discount_code = $_SESSION['discount_code'] ?? '';
$discount = 0;
if ($discount_code === 'NEON10') {
    $discount = $subtotal * 0.10;
} elseif ($discount_code === 'FREE500') {
    $discount = 500;
}

$delivery_charge = !empty($cart) ? $delivery_charge : 0;
$total = $subtotal - $discount + $delivery_charge;
if ($total < 0) $total = 0;

$_SESSION['shipping_fee'] = $delivery_charge;
$_SESSION['checkout_total'] = $total;

echo json_encode([
    'success' => true,
    'items' => $items,
    'items_subtotal' => $subtotal,
    'delivery_fee' => $delivery_charge,
    'discount' => $discount,
    'subtotal' => $total,
    'cart_count' => array_sum($cart)
]);
