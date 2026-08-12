<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
function normalize_country_code($val) {
    if (!$val) return '';
    $v = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', trim($val)));
    if ($v === 'lk' || $v === 'lka' || $v === 'srilanka' || $v === 'sl') {
        return 'LK';
    }
    return strtoupper(trim($val));
}

require_once 'config.php';

// Force login before checkout
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=checkout.php");
    exit;
}
$user_details = null;
if (isset($_SESSION['user_id'])) {
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$_SESSION['user_id']]);
    $user_details = $user_stmt->fetch(PDO::FETCH_ASSOC);
}

    $default_name = trim($user_details['name'] ?? '');
    $name_parts = explode(' ', $default_name, 2);
    $default_first_name = trim($user_details['first_name'] ?? ($name_parts[0] ?? ''));
    $default_last_name = trim($user_details['last_name'] ?? ($name_parts[1] ?? ''));
    
    $default_email = trim($user_details['email'] ?? '');
    $default_phone = trim($user_details['phone'] ?? '');
    $default_country = trim($user_details['country'] ?? '');
    
    // Address fields prefill: keep blank for new users to show placeholders
    $raw_addr1 = trim($user_details['address_line_1'] ?? ($user_details['address_line1'] ?? ''));
    $raw_addr2 = trim($user_details['address_line_2'] ?? ($user_details['address_line2'] ?? ''));
    $raw_addr = trim($user_details['address'] ?? '');
    
    // Clear dummy store address fallback if present
    if (strpos($raw_addr, 'No.161') !== false || strpos($raw_addr1, 'No.161') !== false || strpos($raw_addr2, 'Wackwella') !== false) {
        $raw_addr1 = '';
        $raw_addr2 = '';
        $raw_addr = '';
    }
    
    if ($raw_addr1 === '' && $raw_addr2 === '' && $raw_addr !== '') {
        $address_parts = explode(', ', $raw_addr, 2);
        $raw_addr1 = trim($address_parts[0] ?? '');
        $raw_addr2 = trim($address_parts[1] ?? '');
    }
    
    $default_street_address = $raw_addr1;
    $default_street_address_2 = $raw_addr2;
    
    $default_district = trim($user_details['state_province_region'] ?? ($user_details['district'] ?? ($user_details['province'] ?? '')));
    $default_city = trim($user_details['city'] ?? '');
    $default_postcode = trim($user_details['zip'] ?? '');

    // Reset dummy city/postcode if address is empty or dummy
    if ($raw_addr1 === '' && ($default_city === 'Galle' || $default_postcode === '80000')) {
        if (strpos($raw_addr, 'No.161') !== false || empty($raw_addr)) {
            $default_city = '';
            $default_district = '';
            $default_postcode = '';
        }
    }

$cart_items = $_SESSION['cart'] ?? [];
if (empty($cart_items)) {
    header('Location: index.php');
    exit;
}

$total = $_SESSION['checkout_total'] ?? 0;
$shipping_fee = $_SESSION['shipping_fee'] ?? 0.00;

$has_flash_sale_in_cart = false;
$subtotal = 0;
$delivery_charge = 0;
$checkout_products = [];

if (!empty($cart_items)) {
    // Collect unique product IDs
    $product_ids = [];
    foreach (array_keys($cart_items) as $cart_key) {
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
    
    foreach ($cart_items as $cart_key => $qty) {
        $parts = explode(':', $cart_key);
        $p_id = (int)$parts[0];
        $variant_str = isset($parts[1]) ? $parts[1] : '';
        
        if (!isset($productsById[$p_id])) {
            continue;
        }
        
        $p = $productsById[$p_id];
        
        $original_lkr = $p['price'];
        $discount_pct = $p['discount_percent'] ?? 0;
        $lkr_price = $original_lkr;
        
        // Flash sale check
        $is_flash_sale = false;
        if (!empty($p['flash_sale_price']) && !empty($p['flash_sale_start']) && !empty($p['flash_sale_end'])) {
            $now = new DateTime();
            $start = new DateTime($p['flash_sale_start']);
            $end = new DateTime($p['flash_sale_end']);
            if ($now >= $start && $now < $end) {
                $is_flash_sale = true;
                $lkr_price = $p['flash_sale_price'];
            }
        }
        
        if (!$is_flash_sale && $discount_pct > 0) {
            $lkr_price = $original_lkr * (1 - ($discount_pct / 100));
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
                    $lkr_price += (float)$vItem['price_modifier'];
                    $variant_names[] = $vItem['variant_value'];
                    if (!empty($vItem['image'])) {
                        $variant_img = $vItem['image'];
                    }
                }
            }
        }
        
        $item = $p;
        $item['qty'] = $qty;
        $item['cart_key'] = $cart_key;
        $item['lkr_price'] = $lkr_price;
        $item['total'] = $lkr_price * $qty;
        
        if (!empty($variant_img)) {
            $item['image'] = $variant_img;
        }
        if (!empty($variant_names)) {
            $item['name'] .= ' (' . implode(', ', $variant_names) . ')';
        }
        
        $item_shipping = isset($p['shipping_fee']) ? (float)$p['shipping_fee'] : 450.00;
        $delivery_charge += $item_shipping * $qty;
        
        $subtotal += $item['total'];
        $checkout_products[] = $item;
    }
}

// Apply coupon code if set in session
$discount_code = $_SESSION['discount_code'] ?? '';
$discount = 0;
if ($discount_code === 'NEON10') {
    $discount = $subtotal * 0.10;
} elseif ($discount_code === 'FREE500') {
    $discount = 500;
}

$shipping_fee = 0.00;
$total = $subtotal - $discount + $shipping_fee;
if ($total < 0) $total = 0;

$_SESSION['shipping_fee'] = $shipping_fee;
$_SESSION['checkout_total'] = $total;

$success = false;
$order_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle payment slip upload
    $payment_slip_path = null;
    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg','image/jpg','image/png','image/webp','application/pdf'];
        $file_type = mime_content_type($_FILES['payment_slip']['tmp_name']);
        $file_size = $_FILES['payment_slip']['size'];
        if (in_array($file_type, $allowed_types) && $file_size <= 10 * 1024 * 1024) {
            $ext = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
            $filename = 'slip_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            $upload_dir = __DIR__ . '/uploads/payment_slips/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $upload_dir . $filename)) {
                $payment_slip_path = 'uploads/payment_slips/' . $filename;
            }
        }
    }
    $transaction_id_input = trim($_POST['transaction_id'] ?? '');
    $transaction_id_input = $transaction_id_input !== '' ? $transaction_id_input : null;
    // Map new international fields
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $name = trim($first_name . ' ' . $last_name);
    
    $email = $_POST['email'] ?? '';
    $phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
    
    $address_line_1 = $_POST['address_line_1'] ?? '';
    $address_line_2 = $_POST['address_line_2'] ?? '';
    
    $same_as_billing = isset($_POST['same_as_billing']) || isset($_POST['same_address']);
    
    $del_first_name = $_POST['del_first_name'] ?? '';
    $del_last_name = $_POST['del_last_name'] ?? '';
    $del_phone = preg_replace('/[^0-9+]/', '', $_POST['del_phone'] ?? '');
    $del_email = $_POST['del_email'] ?? '';
    $del_address_line_1 = $_POST['del_address_line_1'] ?? '';
    $del_address_line_2 = $_POST['del_address_line_2'] ?? '';
    $del_country = $_POST['del_country'] ?? '';
    $del_city = $_POST['del_city'] ?? '';
    $del_state_province_region = $_POST['del_state_province_region'] ?? '';
    $del_postcode = $_POST['del_postcode'] ?? '';
    
    $city = !empty($_POST['city']) ? trim($_POST['city']) : '';
    $state_province_region = !empty($_POST['state_province_region']) ? trim($_POST['state_province_region']) : '';
    $country = !empty($_POST['country']) ? trim($_POST['country']) : '';
    $zip = !empty($_POST['postcode']) ? trim($_POST['postcode']) : '';
    
    if (!$same_as_billing) {
        $final_first_name = $del_first_name;
        $final_last_name = $del_last_name;
        $final_phone = $del_phone;
        $final_email = $del_email;
        $final_address_line_1 = $del_address_line_1;
        $final_address_line_2 = $del_address_line_2;
        $final_city = $del_city;
        $final_state_province_region = $del_state_province_region;
        $final_zip = $del_postcode;
        $final_country = $del_country;
    } else {
        $final_first_name = $first_name;
        $final_last_name = $last_name;
        $final_phone = $phone;
        $final_email = $email;
        $final_address_line_1 = $address_line_1;
        $final_address_line_2 = $address_line_2;
        $final_city = $city;
        $final_state_province_region = $state_province_region;
        $final_zip = $zip;
        $final_country = $country;
    }
    
    $payment_method = $_POST['payment_method'] ?? 'cod';
    $order_notes = !empty($_POST['order_notes']) ? trim($_POST['order_notes']) : null;
    
    $normalized_shipping_country = normalize_country_code($final_country);
    $is_final_sri_lanka = ($normalized_shipping_country === 'LK');

    // Securely fetch dynamic shipping rate from database based on the final selected country
    $db_shipping_fee = false;
    try {
        $sum_shipping_fee = 0.00;
        $has_rate = false;
        $can_deliver = true;
        
        $s_stmt = $pdo->prepare("SELECT fee FROM product_shipping_rates WHERE product_id = ? AND country_code = ? LIMIT 1");
        foreach ($checkout_products as $item) {
            $s_stmt->execute([$item['id'], $normalized_shipping_country]);
            $rate_val = $s_stmt->fetchColumn();
            if ($rate_val !== false) {
                $has_rate = true;
                $item_fee = (float)$rate_val;
                $sum_shipping_fee += $item_fee * $item['qty'];
            } else {
                $can_deliver = false;
                break;
            }
        }
        
        if ($can_deliver && $has_rate) {
            $db_shipping_fee = $sum_shipping_fee;
            
            // Recalculate total
            $shipping_fee = $db_shipping_fee;
            $total = $subtotal - $discount + $shipping_fee;
            if ($total < 0) $total = 0;
        } else {
            $db_shipping_fee = false;
        }
    } catch(Exception $e) {}

    if ($db_shipping_fee === false) {
        $error = "Shipping is not available for the selected country.";
    } elseif ($payment_method === 'cod' && !$is_final_sri_lanka) {
        $error = "Cash on Delivery is only available for deliveries to Sri Lanka.";
    } elseif ($final_first_name && $final_email && $final_phone && $final_address_line_1 && $payment_method) {
        try {
            $pdo->beginTransaction();

            $user_id = $_SESSION['user_id'];

            // Update ONLY address fields in users table (preserve signup fields: name, phone, email, country)
            $stmt = $pdo->prepare("UPDATE users SET address = ?, address_line_1 = ?, address_line_2 = ?, city = ?, district = ?, state_province_region = ?, zip = ? WHERE id = ?");
            $stmt->execute([$address_line_1, $address_line_1, $address_line_2, $city, $state_province_region, $state_province_region, $zip, $user_id]);

            // Determine payment_status based on method
            $payment_status_val = ($payment_method === 'cod') ? 'payment_not_required' : 'awaiting_verification';

            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_price, status, address_version, first_name, last_name, email, phone, address_line_1, address_line_2, city, state_province_region, zip, country, payment_method, payment_status, payment_slip, transaction_id, order_notes, shipping_fee) VALUES (?, ?, 'pending', 2, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $total, $final_first_name, $final_last_name, $final_email, $final_phone, $final_address_line_1, $final_address_line_2, $final_city, $final_state_province_region, $final_zip, $final_country, $payment_method, $payment_status_val, $payment_slip_path, $transaction_id_input, $order_notes, $shipping_fee]);
            $order_id = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmtReduceStock = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
            $wa_items_text = "";
            $html_items_rows = "";
            foreach ($checkout_products as $p) {
                $qty = $p['qty'];
                $lkr_price = $p['lkr_price'];
                
                $stmtItem->execute([$order_id, $p['id'], $qty, $lkr_price]);
                $stmtReduceStock->execute([$qty, $p['id']]);
                $warranty = $p['warranty'] ?? 'No Warranty';
                $wa_items_text .= "- " . $qty . "x " . $p['name'] . " [Warranty: " . $warranty . "] (Rs. " . number_format($lkr_price * $qty, 2) . ")\n";
                
                $item_total = $lkr_price * $qty;
                $html_items_rows .= "
                <tr>
                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #1e293b;'>" . htmlspecialchars($p['name']) . "</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #64748b; text-align: center;'>" . htmlspecialchars($warranty) . "</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #1e293b; text-align: right;'>Rs. " . number_format($lkr_price, 2) . "</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #1e293b; text-align: center;'>$qty</td>
                    <td style='padding: 12px; border-bottom: 1px solid #e2e8f0; font-family: Arial, sans-serif; font-size: 14px; color: #ff5e00; font-weight: bold; text-align: right;'>Rs. " . number_format($item_total, 2) . "</td>
                </tr>";
            }

            $pdo->commit();
            
            // Generate WhatsApp confirmation message link — COD only
            if ($payment_method === 'cod') {
                $wa_text = "🛍️ *New COD Order from Digi Pro X 24*\n\n";
                $wa_text .= "*Order Number:* #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "\n";
                $wa_text .= "*Customer:* " . $name . "\n";
                $wa_text .= "*Phone:* " . $phone . "\n";
                $wa_text .= "*Shipping Address:* " . $final_address_line_1 . ", " . $final_city . ", " . $final_state_province_region . " (" . $final_zip . ")\n";
                $wa_text .= "*Payment Method:* CASH ON DELIVERY\n\n";
                $wa_text .= "*Items Ordered:*\n" . $wa_items_text . "\n";
                if ($shipping_fee > 0) {
                    $wa_text .= "*Grand Total:* Rs. " . number_format($total, 2) . " (Includes Delivery Charge Rs. " . number_format($shipping_fee, 2) . ")\n\n";
                } else {
                    $wa_text .= "*Grand Total:* Rs. " . number_format($total, 2) . " (Includes Free Delivery)\n\n";
                }
                $wa_text .= "Thank you!";
                $_SESSION['wa_url'] = "https://wa.me/94706756006?text=" . urlencode($wa_text);
            } else {
                unset($_SESSION['wa_url']);
            } 
            
            // Send invoice to customer email (HTML format)
            $to = $email;
            $subject = "Order Confirmation - Digi Pro X 24 [Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "]";
            
            $email_body = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Order Invoice</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f6f9fc; font-family: Arial, sans-serif;'>
    <table width='100%' border='0' cellpadding='0' cellspacing='0' style='background-color: #f6f9fc; padding: 40px 0;'>
        <tr>
            <td align='center'>
                <table width='600' border='0' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-top: 6px solid #ff5e00;'>
                    <!-- Header -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #ffffff; border-bottom: 1px solid #f1f5f9;'>
                            <table width='100%' border='0' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td>
                                        <div style='font-size: 24px; font-weight: bold; color: #0f172a;'>
                                            Digi <span style='color: #ff5e00;'>Pro X 24</span>
                                        </div>
                                        <div style='font-size: 12px; color: #ff5e00; font-weight: bold; letter-spacing: 2px; margin-top: 4px; text-transform: uppercase;'>INVOICE</div>
                                    </td>
                                    <td align='right'>
                                        <div style='background-color: " . ($payment_method === 'cod' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(16, 185, 129, 0.1)') . "; border: 1px solid " . ($payment_method === 'cod' ? 'rgba(245, 158, 11, 0.3)' : 'rgba(16, 185, 129, 0.3)') . "; color: " . ($payment_method === 'cod' ? '#d97706' : '#10b981') . "; padding: 6px 16px; border-radius: 20px; font-weight: bold; font-size: 12px; text-transform: uppercase; display: inline-block;'>
                                            " . ($payment_method === 'cod' ? 'COD PENDING' : 'PAID') . "
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Billed To & Order Details -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #ffffff;'>
                            <table width='100%' border='0' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td width='50%' valign='top' style='font-size: 14px; color: #64748b; line-height: 1.6;'>
                                        <strong style='color: #0f172a; font-size: 12px; letter-spacing: 1px;'>BILLED TO:</strong><br>
                                        <span style='color: #1e293b; font-weight: 600;'>" . htmlspecialchars($name) . "</span><br>
                                        " . htmlspecialchars($phone) . "<br>
                                        " . htmlspecialchars($email) . "
                                    </td>
                                    <td width='50%' align='right' valign='top' style='font-size: 14px; color: #64748b; line-height: 1.6;'>
                                        <strong style='color: #0f172a; font-size: 12px; letter-spacing: 1px;'>ORDER DETAILS:</strong><br>
                                        Order ID: <span style='color: #1e293b; font-weight: 600;'>#" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "</span><br>
                                        Date: " . date('F j, Y') . "<br>
                                        Payment: " . strtoupper(str_replace('_', ' ', $payment_method)) . "
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Items Table -->
                    <tr>
                        <td style='padding: 0 40px;'>
                            <table width='100%' border='0' cellpadding='0' cellspacing='0' style='border-collapse: collapse;'>
                                <thead>
                                    <tr style='background-color: #f8fafc;'>
                                        <th align='left' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Description</th>
                                        <th align='center' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Warranty</th>
                                        <th align='right' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Unit</th>
                                        <th align='center' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Qty</th>
                                        <th align='right' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    $html_items_rows
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <!-- Totals Box -->
                    <tr>
                        <td style='padding: 30px 40px;'>
                            <table width='100%' border='0' cellpadding='0' cellspacing='0' style='background-color: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; padding: 20px;'>
                                <tr>
                                    <td style='font-size: 14px; color: #475569; font-weight: bold;'>Subtotal</td>
                                    <td align='right' style='font-size: 14px; color: #1e293b;'>Rs. " . number_format($total - $shipping_fee, 2) . "</td>
                                </tr>
                                <tr style='margin-top: 10px;'>
                                    <td style='font-size: 14px; color: #475569; font-weight: bold; padding-top: 10px;'>Shipping</td>
                                    <td align='right' style='font-size: 14px; color: #1e293b; padding-top: 10px;'>" . ($shipping_fee > 0 ? 'Rs. ' . number_format($shipping_fee, 2) : 'Free Shipping') . "</td>
                                </tr>
                                <tr>
                                    <td style='font-size: 16px; color: #0f172a; font-weight: 800; padding-top: 15px; border-top: 1px dashed #e2e8f0; margin-top: 15px;'>Grand Total</td>
                                    <td align='right' style='font-size: 20px; color: #ff5e00; font-weight: 900; padding-top: 15px; border-top: 1px dashed #e2e8f0;'>Rs. " . number_format($total, 2) . "</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Shipping Address Info -->
                    <tr>
                        <td style='padding: 0 40px 30px 40px; font-size: 14px; color: #64748b; line-height: 1.6;'>
                            <div style='border-top: 1px solid #f1f5f9; padding-top: 20px;'>
                                <strong>Shipping Address:</strong><br>
                                " . htmlspecialchars($final_address_line_1 . ', ' . $final_city . ', ' . $final_state_province_region . ' ' . $final_zip) . "
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #0f172a; color: #94a3b8; font-size: 12px; text-align: center; line-height: 1.6;'>
                            <strong>Digi Pro X 24</strong><br>
                            No.161, Wackwella Rd, Galle, Sri Lanka<br>
                            Phone: 070 6756006 | Email: digipro24@gmail.com<br><br>
                            Thank you for shopping with us!
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
            
            // Send new order notification to store owner (HTML format)
            $owner_to = "digipro24@gmail.com";
            $owner_subject = "New Order Alert - Digi Pro X 24 [Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "]";
            
            $owner_email_body = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>New Order Alert</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f6f9fc; font-family: Arial, sans-serif;'>
    <table width='100%' border='0' cellpadding='0' cellspacing='0' style='background-color: #f6f9fc; padding: 40px 0;'>
        <tr>
            <td align='center'>
                <table width='600' border='0' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-top: 6px solid #f59e0b;'>
                    <!-- Header -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #ffffff; border-bottom: 1px solid #f1f5f9;'>
                            <table width='100%' border='0' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td>
                                        <div style='font-size: 24px; font-weight: bold; color: #0f172a;'>
                                            Digi Pro <span style='color: #ff9f0a;'>Admin</span>
                                        </div>
                                        <div style='font-size: 12px; color: #f59e0b; font-weight: bold; letter-spacing: 2px; margin-top: 4px; text-transform: uppercase;'>NEW ORDER RECEIVED</div>
                                    </td>
                                    <td align='right'>
                                        <div style='background-color: " . ($payment_method === 'cod' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(16, 185, 129, 0.1)') . "; border: 1px solid " . ($payment_method === 'cod' ? 'rgba(245, 158, 11, 0.3)' : 'rgba(16, 185, 129, 0.3)') . "; color: " . ($payment_method === 'cod' ? '#d97706' : '#10b981') . "; padding: 6px 16px; border-radius: 20px; font-weight: bold; font-size: 12px; text-transform: uppercase; display: inline-block;'>
                                            " . strtoupper(str_replace('_', ' ', $payment_method)) . "
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Customer details & Order Details -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #ffffff;'>
                            <table width='100%' border='0' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td width='50%' valign='top' style='font-size: 14px; color: #64748b; line-height: 1.6;'>
                                        <strong style='color: #0f172a; font-size: 12px; letter-spacing: 1px;'>CUSTOMER DETAILS:</strong><br>
                                        <span style='color: #1e293b; font-weight: 600;'>" . htmlspecialchars($name) . "</span><br>
                                        " . htmlspecialchars($phone) . "<br>
                                        " . htmlspecialchars($email) . "
                                    </td>
                                    <td width='50%' align='right' valign='top' style='font-size: 14px; color: #64748b; line-height: 1.6;'>
                                        <strong style='color: #0f172a; font-size: 12px; letter-spacing: 1px;'>ORDER DETAILS:</strong><br>
                                        Order ID: <span style='color: #1e293b; font-weight: 600;'>#" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . "</span><br>
                                        Date: " . date('F j, Y') . "<br>
                                        Status: <span style='color: #f59e0b; font-weight: bold;'>Pending Fulfillment</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Items Table -->
                    <tr>
                        <td style='padding: 0 40px;'>
                            <table width='100%' border='0' cellpadding='0' cellspacing='0' style='border-collapse: collapse;'>
                                <thead>
                                    <tr style='background-color: #f8fafc;'>
                                        <th align='left' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Description</th>
                                        <th align='center' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Warranty</th>
                                        <th align='right' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Unit</th>
                                        <th align='center' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Qty</th>
                                        <th align='right' style='padding: 12px; border-bottom: 2px solid #e2e8f0; font-size: 12px; color: #475569; text-transform: uppercase; font-weight: bold;'>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    $html_items_rows
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <!-- Totals Box -->
                    <tr>
                        <td style='padding: 30px 40px;'>
                            <table width='100%' border='0' cellpadding='0' cellspacing='0' style='background-color: #f8fafc; border: 1px solid #f1f5f9; border-radius: 12px; padding: 20px;'>
                                <tr>
                                    <td style='font-size: 14px; color: #475569; font-weight: bold;'>Subtotal</td>
                                    <td align='right' style='font-size: 14px; color: #1e293b;'>Rs. " . number_format($total - $shipping_fee, 2) . "</td>
                                </tr>
                                <tr style='margin-top: 10px;'>
                                    <td style='font-size: 14px; color: #475569; font-weight: bold; padding-top: 10px;'>Shipping</td>
                                    <td align='right' style='font-size: 14px; color: #1e293b; padding-top: 10px;'>" . ($shipping_fee > 0 ? 'Rs. ' . number_format($shipping_fee, 2) : 'Free Shipping') . "</td>
                                </tr>
                                <tr>
                                    <td style='font-size: 16px; color: #0f172a; font-weight: 800; padding-top: 15px; border-top: 1px dashed #e2e8f0; margin-top: 15px;'>Grand Total</td>
                                    <td align='right' style='font-size: 20px; color: #f59e0b; font-weight: 900; padding-top: 15px; border-top: 1px dashed #e2e8f0;'>Rs. " . number_format($total, 2) . "</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Shipping Address Info -->
                    <tr>
                        <td style='padding: 0 40px 30px 40px; font-size: 14px; color: #64748b; line-height: 1.6;'>
                            <div style='border-top: 1px solid #f1f5f9; padding-top: 20px;'>
                                <strong>Shipping Address:</strong><br>
                                " . htmlspecialchars($final_address_line_1 . ', ' . $final_city . ', ' . $final_state_province_region . ' ' . $final_zip) . "
                            </div>
                        </td>
                    </tr>
                    <!-- Action Bar -->
                    <tr>
                        <td style='padding: 20px 40px; background-color: #f8fafc; text-align: center; border-top: 1px solid #f1f5f9;'>
                            <a href='https://digiprox24.com/admin/orders' style='background-color: #0f172a; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 14px; display: inline-block;'>View Order in Admin Dashboard</a>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #0f172a; color: #94a3b8; font-size: 12px; text-align: center; line-height: 1.6;'>
                            <strong>Digi Pro X 24 Admin Portal</strong><br>
                            This is an automated system notification. Please log in to your dashboard to fulfill this order.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";

            $headers_arr = [
                'Reply-To' => 'digipro24@gmail.com',
                'X-Mailer' => 'PHP/' . phpversion(),
                'Content-Type' => 'text/html; charset=UTF-8'
            ];

            $owner_headers = [
                'Reply-To' => 'digipro24@gmail.com',
                'X-Mailer' => 'PHP/' . phpversion(),
                'Content-Type' => 'text/html; charset=UTF-8'
            ];

            // Defer SMTP email delivery to background shutdown phase so user response renders instantly
            register_shutdown_function(function() use ($to, $subject, $email_body, $headers_arr, $owner_to, $owner_subject, $owner_email_body, $owner_headers) {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                @send_smtp_email($to, $subject, $email_body, $headers_arr);
                @send_smtp_email($owner_to, $owner_subject, $owner_email_body, $owner_headers);
            });
            
            $_SESSION['cart'] = [];
            $_SESSION['checkout_total'] = 0;
            $success = true;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Checkout failed: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <title>Checkout - Digi Pro X 24</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        html, body {
            background-color: #080b11 !important;
            overflow-x: hidden !important;
            max-width: 100vw !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .checkout-page { padding: 120px 5% 5rem; min-height: 80vh; max-width: 1400px; margin: 0 auto; position: relative; z-index: 1; }
        @media (max-width: 768px) {
            .checkout-page { padding-bottom: 150px; }
        }
        
        /* Stepper */
        .checkout-stepper { display: flex; justify-content: center; align-items: center; margin-bottom: 3rem; gap: 1.5rem; }
        .step { display: flex; align-items: center; gap: 0.6rem; color: var(--text-muted); font-weight: 700; font-size: 0.92rem; text-transform: uppercase; letter-spacing: 1px; }
        .step.completed { color: #10b981; }
        .step.active { color: var(--primary-glow); }
        .step-num { width: 26px; height: 26px; border-radius: 50%; background: transparent; border: 2px solid rgba(255, 94, 0, 0.2); display: flex; align-items: center; justify-content: center; transition: 0.3s; color: var(--text-muted); font-size: 0.85rem; font-weight: 800; }
        .step.completed .step-num { background: rgba(16,185,129,0.1); color: #10b981; border-color: #10b981; }
        .step.active .step-num { background: var(--primary-glow); color: #000000; border-color: var(--primary-glow); box-shadow: 0 4px 15px rgba(255, 94, 0, 0.4); }
        .step-line { width: 40px; height: 2px; background: rgba(255, 94, 0, 0.2); }
        .step.completed + .step-line { background: #10b981; }
        .label-short { display: none; }
        .label-full { display: inline; }
        .checkout-layout { display: grid; grid-template-columns: 1fr; max-width: 800px; margin: 0 auto; gap: 2rem; align-items: start; }
        
        .form-section { padding: 2.5rem; border-radius: 20px; background: rgba(13, 16, 21, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 94, 0, 0.12); box-shadow: 0 10px 40px rgba(0,0,0,0.5); margin-bottom: 2rem; color: var(--text-main); }
        .form-section h2 { font-size: 1.4rem; margin-bottom: 2rem; color: #ffffff; display: flex; align-items: center; gap: 0.8rem; font-weight: 800; padding-bottom: 1rem; border-bottom: 1px solid rgba(255, 94, 0, 0.1); }
        .form-section h2 span { background: rgba(255, 94, 0, 0.1); color: var(--primary-glow); width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; border: 1px solid var(--primary-glow); }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .form-group.full { grid-column: span 2; }
        .form-group label { color: var(--text-muted); font-size: 0.9rem; font-weight: 600; letter-spacing: 0.3px; }
        
        .form-input { width: 100%; padding: 0.9rem 1.1rem; border-radius: 8px; background: rgba(13, 16, 21, 0.4); border: 1px solid rgba(255, 94, 0, 0.2); color: #ffffff; font-family: var(--font-family); font-size: 1rem; transition: all 0.25s ease; }
        .form-input:focus { outline: none; border-color: var(--primary-glow); box-shadow: 0 0 10px rgba(255, 94, 0, 0.2); background: rgba(13, 16, 21, 0.7); }
        
        .payment-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .payment-method { position: relative; background: rgba(13, 16, 21, 0.4); border: 1px solid rgba(255, 94, 0, 0.2); border-radius: 12px; padding: 1.5rem 1rem; text-align: center; cursor: pointer; transition: all 0.2s ease; display: flex; flex-direction: column; align-items: center; gap: 0.6rem; }
        .checkout-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, #090a0f 0%, #050608 100%);
            z-index: -5;
        }
        /* ── Payment panel styles ── */
        .pm-panel {
            display: none;
            margin-top: 1.5rem;
            border-radius: 16px;
            padding: 1.8rem 2rem;
            animation: panelFadeIn 0.3s ease;
        }
        @keyframes panelFadeIn { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
        .pm-panel.visible { display: block; }
        .pm-panel-bank  { background: rgba(255,94,0,0.05);   border: 1px solid rgba(255,94,0,0.2); }
        .pm-panel-crypto{ background: rgba(38,161,123,0.05); border: 1px solid rgba(38,161,123,0.2); }
        .pm-panel-paypal{ background: rgba(0,121,193,0.05);  border: 1px solid rgba(0,121,193,0.2); }
        .pm-panel-cod   { background: rgba(16,185,129,0.05); border: 1px solid rgba(16,185,129,0.2); }
        .pm-panel h3 { font-size: 1.1rem; font-weight: 800; margin-bottom: 1.2rem; display:flex; align-items:center; gap:0.6rem; }
        .pm-info-box {
            background: rgba(0,0,0,0.35);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 10px;
            padding: 1rem 1.2rem;
            font-size: 0.95rem;
            color: #f1f5f9;
            line-height: 1.7;
            margin-bottom: 1rem;
            word-break: break-word;
            font-family: 'Courier New', monospace;
        }
        .pm-section-title {
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: var(--text-muted);
            margin: 1.2rem 0 0.6rem 0;
            padding-bottom: 0.4rem;
            border-bottom: 1px dashed rgba(255,255,255,0.08);
        }
        .pm-note {
            font-size: 0.83rem;
            color: var(--text-muted);
            margin-top: 0.8rem;
            line-height: 1.5;
        }
        .file-upload-zone {
            border: 2px dashed rgba(255,94,0,0.3);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: rgba(255,94,0,0.03);
            position: relative;
        }
        .file-upload-zone:hover { border-color: var(--primary-glow); background: rgba(255,94,0,0.07); }
        .file-upload-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        .file-upload-zone .upload-icon { font-size: 2rem; margin-bottom: 0.5rem; }
        .file-upload-zone .upload-text { font-size: 0.9rem; color: var(--text-muted); }
        .file-upload-zone .upload-text strong { color: var(--primary-glow); }
        .payment-method:hover { border-color: var(--primary-glow); background: rgba(255, 94, 0, 0.05); transform: translateY(-2px); }
        
        .payment-method input[type="radio"] { position: absolute; opacity: 0; }
        .payment-method .pm-icon { height: 45px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem; transition: 0.3s; filter: grayscale(1); opacity: 0.6; color: var(--text-main); }
        .payment-method .pm-name { color: var(--text-muted); font-weight: 600; transition: 0.3s; line-height: 1.4; font-size: 0.95rem; }
        
        /* Selected State */
        .payment-method.selected { border-color: var(--primary-glow); background: rgba(255, 94, 0, 0.1); box-shadow: 0 4px 15px rgba(255, 94, 0, 0.2); border-width: 2px; padding: calc(1.5rem - 1px) calc(1rem - 1px); }
        .payment-method.selected .pm-icon { filter: grayscale(0); opacity: 1; transform: scale(1.05); }
        .payment-method.selected#pm-card .pm-icon { color: var(--primary-glow); }
        .payment-method.selected#pm-koko .pm-icon { color: #0284c7; }
        .payment-method.selected#pm-payzy .pm-icon { color: #08a4db; }
        .payment-method.selected#pm-cod .pm-icon { color: #10b981; }
        .payment-method.selected .pm-name { color: var(--primary-glow); }
        
        /* Disabled State */
        .payment-method.disabled { opacity: 0.5; cursor: not-allowed; background: rgba(13, 16, 21, 0.6); }
        .payment-method.disabled:hover { transform: none !important; border-color: rgba(255, 94, 0, 0.2) !important; }
        
        .order-summary-box { display: none !important; }
        
        @keyframes spinBtn {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .btn-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spinBtn 0.6s linear infinite;
            margin-right: 8px;
            vertical-align: middle;
        }

        .iti { width: 100%; display: block; }
        .iti__flag-container { z-index: 5; }
        .iti__selected-flag { background: transparent !important; padding: 0 12px !important; border-radius: 8px 0 0 8px !important; transition: 0.3s; }
        .iti__selected-flag:hover { background: rgba(255, 94, 0, 0.1) !important; }
        .iti__country-list { background: rgba(13, 16, 21, 0.95); border: 1px solid rgba(255, 94, 0, 0.2); backdrop-filter: blur(10px); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); color: #ffffff; white-space: normal; }
        .iti__country { padding: 10px 12px; transition: 0.2s; }
        .iti__country:hover, .iti__country.iti__highlight { background: rgba(255, 94, 0, 0.15); color: var(--primary-glow); }
        .iti__divider { border-bottom: 1px solid rgba(255, 94, 0, 0.2); }
        .iti__dial-code { color: var(--text-muted); }
        
        .phone-input-wrapper input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):hover,
        .phone-input-wrapper input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):focus,
        .phone-input-wrapper input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):focus-within {
            transform: none !important;
            animation: none !important;
            translate: none !important;
            scale: none !important;
            box-shadow: none !important;
            border-color: var(--primary-glow) !important;
        }
        
        .phone-input-wrapper input {
            line-height: normal !important;
            position: relative;
            top: 0 !important;
            margin-top: 0 !important;
            transition: border-color 0.2s ease !important;
        }
        
        .ts-wrapper.single .ts-control { background: rgba(13, 16, 21, 0.6) !important; border: 1px solid rgba(255, 94, 0, 0.2) !important; border-radius: 8px !important; color: white !important; padding: 0.85rem 1.25rem !important; box-shadow: none !important; font-family: 'Outfit', sans-serif !important; font-size: 1rem !important; }
        .ts-wrapper.focus .ts-control { border-color: var(--primary-glow) !important; box-shadow: 0 0 0 3px rgba(255, 94, 0, 0.15) !important; }
        .ts-wrapper .ts-dropdown { background: rgba(13, 16, 21, 0.95) !important; border: 1px solid rgba(255, 94, 0, 0.2) !important; border-radius: 8px !important; color: white !important; font-family: 'Outfit', sans-serif !important; backdrop-filter: blur(10px); box-shadow: 0 10px 30px rgba(0,0,0,0.5) !important; }
        .ts-wrapper .ts-dropdown .option { padding: 10px 12px !important; color: white !important; }
        .ts-wrapper .ts-dropdown .option.active, .ts-wrapper .ts-dropdown .option:hover { background: rgba(255, 94, 0, 0.15) !important; color: var(--primary-glow) !important; }
        div.ts-control > input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):hover,
        div.ts-control > input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):focus {
            transform: none !important;
            box-shadow: none !important;
            border-color: transparent !important;
        }
        .ts-control input { color: white !important; }
        .ts-control .item { vertical-align: baseline !important; }
        
        

          .btn-checkout { display: block; width: 100%; text-align: center; padding: 1rem; font-size: 1.1rem; margin-top: 2rem; border-radius: 50px; font-weight: 800; border: none; cursor: pointer; background: var(--primary-glow) !important; color: #000000 !important; transition: all 0.25s ease; text-transform: uppercase; letter-spacing: 1px; }
        .btn-checkout:hover { background: var(--secondary-glow) !important; transform: translateY(-1px); box-shadow: 0 0 20px rgba(255, 94, 0, 0.4); }
        
        .error-msg { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(239, 68, 68, 0.3); display: flex; align-items: center; gap: 1rem; }
        
        .success-box { text-align: center; padding: 3rem 2rem; border-radius: 24px; border: 1px solid rgba(255, 94, 0, 0.12); box-shadow: 0 10px 40px rgba(0,0,0,0.5); max-width: 800px; margin: 0 auto; background: rgba(13, 16, 21, 0.7); backdrop-filter: blur(10px); }
        .success-icon { font-size: 5rem; margin-bottom: 1rem; color: #10b981; animation: float 3s ease-in-out infinite; }
        .success-box h1 { font-size: 2.5rem; margin-bottom: 1rem; color: #ffffff; }
        
        .receipt-card { 
            background: rgba(13, 16, 21, 0.85); 
            border: 1px solid rgba(255, 94, 0, 0.15); 
            border-top: 6px solid var(--primary-glow); 
            border-radius: 24px; 
            padding: 3.5rem; 
            text-align: left; 
            margin-top: 3rem; 
            box-shadow: 0 30px 60px rgba(0,0,0,0.4); 
            position: relative; 
            overflow: hidden; 
            color: var(--text-main); 
        }
        
        .receipt-watermark { 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            font-size: 18rem; 
            color: rgba(255, 94, 0, 0.015); 
            z-index: 0; 
            pointer-events: none; 
            font-family: 'Outfit', sans-serif; 
            font-weight: 900; 
            letter-spacing: -10px; 
            user-select: none; 
        }
        .receipt-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start; 
            gap: 2rem;
            border-bottom: 2px dashed rgba(255, 94, 0, 0.15); 
            padding-bottom: 2.5rem; 
            margin-bottom: 2.5rem; 
            position: relative; 
            z-index: 1; 
        }
        .receipt-header h2 { 
            font-size: 2.2rem; 
            margin: 0; 
            line-height: 1; 
            color: #ffffff; 
            font-weight: 800;
            display: block;
            white-space: nowrap;
        }
        .receipt-header h2 span { 
            color: var(--primary-glow); 
            display: inline;
        }
        .receipt-header h2 img {
            vertical-align: middle;
            margin-right: 0.8rem;
            margin-bottom: 6px;
        }
        .receipt-title { 
            font-size: 1.1rem; 
            letter-spacing: 3px; 
            color: var(--primary-glow); 
            font-weight: 800; 
            text-transform: uppercase; 
            margin-top: 0.6rem; 
        }
        .paid-badge { 
            background: rgba(16, 185, 129, 0.12); 
            border: 1px solid rgba(16, 185, 129, 0.35); 
            color: #10b981; 
            padding: 0.5rem 1.8rem; 
            border-radius: 30px; 
            font-weight: 800; 
            font-size: 1.1rem; 
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.15); 
            display: inline-flex; 
            align-items: center;
            gap: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .receipt-details { 
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem; 
            font-size: 0.98rem; 
            line-height: 1.8; 
            color: var(--text-muted); 
            position: relative; 
            z-index: 1; 
        }
        .receipt-details strong { 
            color: #ffffff; 
            font-size: 0.9rem; 
            letter-spacing: 1px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 0.5rem;
        }
        .text-right { text-align: right; }
        
        .receipt-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 2rem; 
            position: relative; 
            z-index: 1; 
        }
        .receipt-table th { 
            text-align: left; 
            padding: 1.2rem 1rem; 
            color: var(--primary-glow); 
            font-weight: 800; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            border-bottom: 2px solid rgba(255, 94, 0, 0.25); 
        }
        .receipt-table td { 
            padding: 1.4rem 1rem; 
            border-bottom: 1px solid rgba(255, 94, 0, 0.08);
            color: var(--text-main); 
            font-size: 1rem; 
        }
        .receipt-table tr td:first-child { 
            font-weight: 600; 
            color: #ffffff;
        }
        .receipt-table tr td:last-child { 
            font-weight: 800; 
            color: var(--primary-glow); 
            text-align: right;
        }
        
        .receipt-total-box { 
            background: linear-gradient(90deg, rgba(255, 94, 0, 0.08), rgba(255, 189, 0, 0.02)); 
            border: 1.5px solid rgba(255, 94, 0, 0.25);
            border-left: 5px solid var(--primary-glow);
            padding: 1.8rem 2.2rem; 
            border-radius: 16px; 
            margin-top: 1.5rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            position: relative; 
            z-index: 1; 
        }
        .receipt-total-label { 
            font-size: 1.1rem; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 1.5px; 
            font-weight: 800; 
        }
        .receipt-total-value { 
            font-size: 2.2rem; 
            font-weight: 900; 
            color: var(--primary-glow); 
            text-shadow: 0 0 15px rgba(255, 94, 0, 0.35);
        }
 
        .receipt-footer { 
            text-align: center; 
            font-size: 0.9rem; 
            color: var(--text-muted); 
            border-top: 1px solid rgba(255, 94, 0, 0.08); 
            padding-top: 2.5rem; 
            margin-top: 3.5rem; 
            position: relative; 
            z-index: 1; 
            line-height: 1.6;
        }
 
        @media print {
            @page { size: portrait; margin: 4mm; }
            body { background: #ffffff !important; color: #0f172a !important; margin: 0 !important; padding: 0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-size: 0.78rem !important; }
            main { padding-top: 0 !important; }
            .checkout-bg, .glass-header, .checkout-stepper, .no-print, .bg-orb, .whatsapp-float { display: none !important; }
            .checkout-page { padding: 0 !important; min-height: auto !important; margin: 0 !important; }
            .success-box { box-shadow: none !important; border: none !important; background: transparent !important; padding: 0 !important; max-width: 100% !important; margin: 0 !important; }
            .receipt-card { 
                background: #ffffff !important; 
                backdrop-filter: none !important;
                border: 1px solid rgba(15, 23, 42, 0.15) !important; 
                border-top: 5px solid #ff5e00 !important; 
                padding: 1rem 1.25rem !important; 
                margin: 0 auto !important; 
                box-shadow: none !important; 
                border-radius: 12px !important; 
                width: 100% !important; 
                color: #0f172a !important;
                page-break-inside: avoid; 
            }
            .receipt-watermark { display: none !important; }
            .receipt-header h2 { color: #0f172a !important; font-size: 1.5rem !important; }
            .receipt-header h2 span { display: none !important; }
            .receipt-header h2 img { height: 26px !important; }
            .shop-details { margin-top: 0.5rem !important; font-size: 0.75rem !important; line-height: 1.35 !important; }
            .receipt-title { color: #ff5e00 !important; font-size: 0.85rem !important; margin-top: 0.3rem !important; }
            .paid-badge { border-color: #10b981 !important; color: #10b981 !important; background: rgba(16,185,129,0.05) !important; font-size: 0.8rem !important; padding: 0.25rem 1rem !important; }
            .paid-badge.status-cod { border-color: #d97706 !important; color: #d97706 !important; background: rgba(217, 119, 6, 0.05) !important; font-size: 0.8rem !important; padding: 0.25rem 1rem !important; }
            .receipt-header { margin-bottom: 0.75rem !important; padding-bottom: 0.75rem !important; border-bottom-color: rgba(15, 23, 42, 0.1) !important; }
            .receipt-details { margin-bottom: 0.75rem !important; font-size: 0.78rem !important; color: #475569 !important; gap: 0.75rem !important; }
            .receipt-details strong { color: #0f172a !important; margin-bottom: 0.1rem !important; }
            .receipt-table { display: table !important; width: 100% !important; border-collapse: collapse !important; }
            .receipt-table thead { display: table-header-group !important; }
            .receipt-table tbody { display: table-row-group !important; }
            .receipt-table tr { display: table-row !important; border-bottom: 1px solid rgba(0,0,0,0.06) !important; }
            .receipt-table th, .receipt-table td { display: table-cell !important; text-align: left !important; padding: 0.4rem 0.3rem !important; width: auto !important; }
            .receipt-table th { color: #475569 !important; border-bottom-color: rgba(15, 23, 42, 0.1) !important; font-size: 0.7rem !important; font-weight: 700 !important; }
            .receipt-table td { font-size: 0.75rem !important; background: transparent !important; color: #0f172a !important; border: none !important; }
            .receipt-table td:nth-child(2)::before,
            .receipt-table td:nth-child(3)::before,
            .receipt-table td:nth-child(4)::before,
            .receipt-table tr td:last-child::before { content: "" !important; display: none !important; }
            .receipt-table tr td:first-child { font-size: 0.75rem !important; font-weight: normal !important; color: #0f172a !important; line-height: 1.35 !important; }
            .receipt-table tr td:last-child { font-size: 0.75rem !important; font-weight: 700 !important; color: #ff5e00 !important; text-align: right !important; }
            .receipt-table th.text-right, .receipt-table td.text-right { text-align: right !important; }
            .receipt-table th[style*="text-align: center"], .receipt-table td[style*="text-align: center"] { text-align: center !important; }
            
            .receipt-total-box { background: #f8fafc !important; border: 1px solid rgba(0,0,0,0.06) !important; padding: 0.5rem 1rem !important; margin-top: 0.75rem !important; border-radius: 8px !important; }
            .receipt-total-label { color: #475569 !important; font-size: 0.85rem !important; }
            .receipt-total-value { font-size: 1.3rem !important; color: #ff5e00 !important; text-shadow: none !important; }
            .receipt-footer { margin-top: 1rem !important; font-size: 0.7rem !important; color: #475569 !important; border-top-color: rgba(15, 23, 42, 0.1) !important; padding-top: 0.8rem !important; }
            .invoice-barcode-container { margin-top: 0.75rem !important; }
            .invoice-barcode-container div { height: 20px !important; }
            .receipt-credit { display: none !important; }
            
            /* Hide system design credit footer in print */
            .receipt-card + div, 
            .receipt-card ~ div:not(.no-print) {
                display: none !important;
            }
        }
 
        .step-nav-bar {
            grid-column: 1 / -1 !important;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px dashed rgba(255, 94, 0, 0.15);
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            gap: 1.25rem !important;
            width: 100% !important;
            max-width: 480px !important;
            margin-left: auto !important;
            margin-right: auto !important;
        }
        .btn-step-back {
            flex: 1 1 0% !important;
            width: 50% !important;
            height: 52px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.5rem !important;
            padding: 0 1rem !important;
            border-radius: 50px !important;
            font-size: 1rem !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            background: rgba(255, 255, 255, 0.04) !important;
            border: 1.5px solid rgba(255, 94, 0, 0.35) !important;
            color: #ffffff !important;
            text-decoration: none !important;
            white-space: nowrap !important;
            transition: all 0.25s ease !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
            box-sizing: border-box !important;
        }
        .btn-step-back:hover {
            background: rgba(255, 94, 0, 0.15) !important;
            border-color: #ff5e00 !important;
            color: #ff8700 !important;
            transform: translateY(-8px) !important;
            box-shadow: var(--hover-shadow) !important;
        }
        .btn-step-next {
            flex: 1 1 0% !important;
            width: 50% !important;
            height: 52px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.5rem !important;
            padding: 0 1rem !important;
            border-radius: 50px !important;
            font-size: 1rem !important;
            font-weight: 800 !important;
            cursor: pointer !important;
            background: linear-gradient(135deg, #ff5e00, #ff8700) !important;
            border: none !important;
            color: #ffffff !important;
            text-transform: uppercase !important;
            letter-spacing: 1px !important;
            white-space: nowrap !important;
            transition: all 0.25s ease !important;
            box-shadow: 0 4px 20px rgba(255, 94, 0, 0.4) !important;
            box-sizing: border-box !important;
        }
        .btn-step-next:hover {
            background: linear-gradient(135deg, #ff8700, #ff5e00) !important;
            transform: translateY(-8px) !important;
            box-shadow: var(--hover-shadow) !important;
        }

        @media(max-width: 900px) { 
            .checkout-layout { grid-template-columns: 1fr; } 
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: 1; }
            .payment-methods { grid-template-columns: 1fr; }
        }

        @media(max-width: 768px) {
            .checkout-page {
                padding: 100px 1rem 4rem !important;
                width: 100% !important;
                min-width: 0 !important;
            }
            .checkout-layout,
            .checkout-forms,
            .order-summary-box {
                width: 100% !important;
                min-width: 0 !important;
            }
            .form-section {
                padding: 1.5rem 1rem !important;
                border-radius: 16px !important;
                width: 100% !important;
            }
            .form-section h2 {
                font-size: 1.3rem !important;
            }
            .payment-methods {
                grid-template-columns: 1fr !important;
                width: 100% !important;
            }
            .payment-method {
                padding: 1.2rem 1rem !important;
                width: 100% !important;
            }
            .order-summary-box {
                padding: 1.5rem 1rem !important;
                border-radius: 16px !important;
                position: relative !important;
                top: 0 !important;
            }
            #card-details-section,
            #koko-installments-section,
            #payzy-installments-section {
                padding: 1rem !important;
            }
            

          .btn-checkout {
                font-size: 1rem !important;
                padding: 1rem !important;
                border-radius: 12px !important;
            }
            .summary-row {
                margin-bottom: 1.25rem !important;
                font-size: 1.08rem !important;
            }
            .summary-total {
                margin-top: 1.8rem !important;
                padding-top: 1.8rem !important;
                font-size: 1.8rem !important;
            }
            .step-nav-bar {
                gap: 0.5rem !important;
            }
            .btn-step-back, .btn-step-next {
                font-size: 0.8rem !important;
                padding: 0 0.5rem !important;
                letter-spacing: 0.5px !important;
            }

            /* Stepper Mobile Responsive Styling */
            .checkout-stepper {
                display: flex !important;
                justify-content: space-between !important;
                align-items: center !important;
                gap: 0.25rem !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                padding: 0.4rem 0 0.8rem !important;
                margin-bottom: 1.25rem !important;
                width: 100% !important;
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }
            .checkout-stepper::-webkit-scrollbar {
                display: none !important;
            }
            .step {
                flex-shrink: 0 !important;
                font-size: 0.68rem !important;
                font-weight: 700 !important;
                letter-spacing: 0.2px !important;
                gap: 0.3rem !important;
                padding: 0.35rem 0.55rem !important;
                background: rgba(13, 16, 21, 0.7) !important;
                border: 1px solid rgba(255, 94, 0, 0.2) !important;
                border-radius: 20px !important;
                white-space: nowrap !important;
            }
            .step.completed {
                background: rgba(16, 185, 129, 0.12) !important;
                border-color: rgba(16, 185, 129, 0.4) !important;
                color: #10b981 !important;
            }
            .step.active {
                background: rgba(255, 94, 0, 0.15) !important;
                border-color: var(--primary-glow) !important;
                color: var(--primary-glow) !important;
                box-shadow: 0 0 12px rgba(255, 94, 0, 0.3) !important;
            }
            .step-num {
                width: 18px !important;
                height: 18px !important;
                font-size: 0.65rem !important;
                flex-shrink: 0 !important;
            }
            .step-line {
                display: none !important;
            }
            .label-short {
                display: inline !important;
            }
            .label-full {
                display: none !important;
            }

            /* Invoice & Grand Total Box Mobile Responsiveness */
            .receipt-box {
                padding: 1.5rem 1rem !important;
                border-radius: 16px !important;
                width: 100% !important;
                overflow: hidden !important;
            }
            .receipt-details {
                flex-direction: column !important;
                gap: 1rem !important;
            }
            .receipt-details .text-right {
                text-align: left !important;
            }
            .receipt-total-box {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.4rem !important;
                padding: 1.2rem 1.2rem !important;
                border-left-width: 4px !important;
            }
            .receipt-total-label {
                font-size: 0.85rem !important;
                letter-spacing: 1px !important;
            }
            .receipt-total-value {
                font-size: 1.6rem !important;
                line-height: 1.25 !important;
                word-break: break-word !important;
            }
        #file-name-display { font-size: 0.82rem; color: #10b981; margin-top: 0.5rem; font-weight: 600; }
        .success-pm-badge {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.5rem 1.5rem; border-radius: 30px;
            font-weight: 800; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px;
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .success-pm-badge.badge-cod      { background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.35); color:#10b981; }
        .success-pm-badge.badge-awaiting { background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.35); color:#f59e0b; }
        .pm-action-card {
            background: rgba(13,16,21,0.5);
            border: 1px solid rgba(255,94,0,0.15);
            border-radius: 16px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="checkout-bg"></div>
    <!-- Background Animated Elements -->
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>

    <header class="glass-header">
        <a href="index.php" class="logo" style="text-decoration:none; display:flex; align-items:center; gap:0.6rem; color:var(--text-main);">
            <img src="logo.png" alt="Digi Pro X 24" style="height:36px; border-radius: 8px;">
            Digi <span>Pro X 24</span>
        </a>
        <div class="header-actions" style="display: flex; align-items: center;">
            <button id="currencyToggle" title="Switch to USD" style="background: rgba(255, 94, 0, 0.08); border: 1.5px solid var(--primary-glow); color: var(--primary-glow); padding: 0.45rem 1rem; border-radius: 8px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; margin-left: 0.8rem; box-shadow: 0 0 10px rgba(255, 94, 0, 0.1); height: 38px; white-space: nowrap;"><script>document.write(localStorage.getItem('site_currency') === 'USD' ? '🇺🇸 USD' : '🇱🇰 LKR');</script></button>
            <button id="hamburgerBtn" class="hamburger-btn" onclick="toggleMobileMenu()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
            <?php if(isset($_SESSION['user_id'])): ?>
                <?php if(($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')): ?>
                    <a href="admin/index.php" class="btn-primary" style="text-decoration:none; margin-right: 0.5rem;">Admin</a>
                <?php endif; ?>
                <a href="logout.php" class="btn-primary" style="text-decoration:none;">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn-primary" style="text-decoration:none;">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="checkout-page">
        <!-- Back Button (Only on form steps) -->
        <?php if (!$success): ?>
        <div style="margin-bottom: 2rem;" class="no-print">
            <a href="javascript:void(0)" onclick="clickPageBackButton()" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; color: var(--primary-glow); font-weight: 600; font-size: 1.1rem; padding: 0.5rem 1rem; border-radius: 8px; background: rgba(255,94,0,0.05); border: 1px solid rgba(255,94,0,0.1); transition: 0.3s;" onmouseover="this.style.background='rgba(255,94,0,0.1)';" onmouseout="this.style.background='rgba(255,94,0,0.05)';">
                <span style="font-size: 1.2rem;">&larr;</span> Back
            </a>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <?php if ($payment_method === 'payzy' && isset($_SESSION['wa_url'])): ?>
                <script>
                    setTimeout(function() {
                        window.location.href = "<?php echo $_SESSION['wa_url']; ?>";
                    }, 1000);
                </script>
            <?php endif; ?>
            <div class="checkout-stepper">
                <div class="step completed" id="step-header-customer"><div class="step-num">✓</div> <span class="step-label"><span class="label-full">Customer details</span><span class="label-short">Details</span></span></div>
                <div class="step-line"></div>
                <div class="step completed" id="step-header-info"><div class="step-num">✓</div> <span class="step-label"><span class="label-full">Additional Information</span><span class="label-short">Info</span></span></div>
                <div class="step-line"></div>
                <div class="step completed" id="step-header-payment"><div class="step-num">✓</div> <span class="step-label"><span class="label-full">Payments</span><span class="label-short">Payment</span></span></div>
                <div class="step-line"></div>
                <div class="step active" id="step-header-complete"><div class="step-num">4</div> <span class="step-label"><span class="label-full">Order Complete</span><span class="label-short">Complete</span></span></div>
            </div>
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const activeComplete = document.getElementById("step-header-complete");
                    if (activeComplete && typeof activeComplete.scrollIntoView === 'function') {
                        setTimeout(function() {
                            activeComplete.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                        }, 100);
                    }
                });
            </script>

            <!-- 4-Second Order Processing Loader Overlay -->
            <div id="order-confirm-loader" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #050608; backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); z-index: 9999999; display: flex; flex-direction: column; align-items: center; justify-content: center; transition: opacity 0.4s ease, visibility 0.4s ease; text-align: center; padding: 2rem;">
                <div style="position: relative; width: 84px; height: 84px; margin-bottom: 1.5rem;">
                    <div style="position: absolute; inset: 0; border: 3px solid rgba(255, 94, 0, 0.15); border-top-color: var(--primary-glow, #ff5e00); border-radius: 50%; animation: confirmSpin 0.9s linear infinite;"></div>
                    <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; filter: drop-shadow(0 0 10px rgba(255,94,0,0.4));">⚡</div>
                </div>
                <div style="font-size: 1.35rem; font-weight: 800; color: #ffffff; margin-bottom: 0.4rem; letter-spacing: 0.5px;">Finalizing Your Order...</div>
                <div id="loader-subtext" style="font-size: 0.92rem; color: var(--text-muted, #94a3b8); margin-bottom: 1.8rem; font-weight: 500;">Validating items & generating invoice...</div>
                <div style="width: 260px; max-width: 80vw; height: 6px; background: rgba(255, 94, 0, 0.15); border-radius: 10px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.5);">
                    <div id="loader-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #ff5e00, #ff9f0a); border-radius: 10px; transition: width 0.04s linear; box-shadow: 0 0 10px rgba(255,94,0,0.6);"></div>
                </div>
            </div>
            <style>
                @keyframes confirmSpin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
            <script>
                (function() {
                    var startTime = Date.now();
                    var duration = 4000; // Exactly 4 seconds
                    var bar = document.getElementById('loader-progress-bar');
                    var sub = document.getElementById('loader-subtext');
                    var overlay = document.getElementById('order-confirm-loader');
                    
                    var interval = setInterval(function() {
                        var elapsed = Date.now() - startTime;
                        var pct = Math.min(100, (elapsed / duration) * 100);
                        if (bar) bar.style.width = pct.toFixed(1) + '%';
                        
                        if (elapsed >= 2000 && sub && sub.textContent !== 'Order confirmed! Loading receipt...') {
                            sub.textContent = 'Order confirmed! Loading receipt...';
                        }
                        
                        if (elapsed >= duration) {
                            clearInterval(interval);
                            if (overlay) {
                                overlay.style.opacity = '0';
                                overlay.style.visibility = 'hidden';
                                setTimeout(function() {
                                    overlay.remove();
                                }, 450);
                            }
                        }
                    }, 40);
                })();
            </script>

            <div class="success-box">
                <div class="no-print">
                    <div class="success-icon">✨</div>
                    <h1>Order Confirmed!</h1>
                    <?php if ($payment_method === 'cod'): ?>
                        <p style="color: var(--text-muted); font-size: 1.2rem; line-height: 1.6; max-width: 500px; margin: 0 auto 1.5rem auto;">Thank you for your order! Your items are being prepared for shipping. Pay at delivery.</p>
                    <?php else: ?>
                        <p style="color: var(--text-muted); font-size: 1.2rem; line-height: 1.6; max-width: 500px; margin: 0 auto 1.5rem auto;">Your order has been placed. Please complete your payment verification so we can process your order.</p>
                    <?php endif; ?>
                    
            <?php if ($payment_method === 'cod'): ?>
                <div class="success-pm-badge badge-cod">
                    ✅ Payment Not Required
                </div>
                <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.6; max-width: 560px; margin: 0 auto 1.5rem;">
                    Your order has been placed successfully. Our team will prepare your order and process it shortly.
                    <strong style="color:var(--text-main);">You can pay when your order is delivered.</strong>
                </p>
                <?php if(isset($_SESSION['wa_url'])): ?>
                <div style="margin-top: 1.5rem;">
                    <a href="<?php echo $_SESSION['wa_url']; ?>" target="_blank" class="btn-primary neon-glow" style="padding: 1rem 2rem; font-size: 1.05rem; background: #25D366; border-color: #25D366; box-shadow: 0 0 15px rgba(37,211,102,0.4); display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none; width: 100%; max-width: 400px;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.792 1.451 5.485.002 9.947-4.437 9.95-9.912.002-2.653-1.03-5.148-2.906-7.027A9.873 9.873 0 0011.997 1.284C6.507 1.284 2.05 5.722 2.046 11.2c-.001 1.761.479 3.483 1.39 5.017L2.45 20.83l4.197-1.101-.001-.005-.002-.008zM17.9 14.88c-.317-.159-1.88-.928-2.197-1.044-.318-.116-.549-.174-.78.174-.23.348-.897 1.13-1.1 1.362-.202.233-.404.261-.722.102-.317-.159-1.34-.493-2.553-1.574-.944-.842-1.58-1.883-1.766-2.197-.186-.317-.02-.49.139-.647.143-.142.318-.369.477-.553.159-.184.212-.307.318-.512.106-.205.053-.385-.026-.543-.08-.159-.78-1.88-1.069-2.572-.282-.677-.568-.585-.78-.596-.202-.01-.433-.01-.664-.01a1.272 1.272 0 00-.923.43c-.317.348-1.213 1.189-1.213 2.899 0 1.71 1.242 3.36 1.416 3.59.174.23 2.446 3.736 5.925 5.24.828.358 1.474.57 1.977.73.832.264 1.588.227 2.186.138.667-.099 1.88-.769 2.146-1.477.265-.709.265-1.318.186-1.448-.08-.13-.294-.207-.611-.366z"/></svg>
                        Confirm Order via WhatsApp
                    </a>
                </div>
                <?php endif; ?>
            <?php elseif ($payment_method === 'bank_transfer'): ?>
                <div class="success-pm-badge badge-awaiting">
                    ⏳ Waiting for Payment Verification
                </div>
                <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.65; max-width: 560px; margin: 0 auto 1.5rem;">
                    Your order has been placed successfully. Please upload your bank payment slip for verification. Our team will review your payment and process your order.
                </p>
                <div class="pm-action-card">
                    <div style="font-size:1.8rem; margin-bottom:0.5rem;">🏦</div>
                    <div style="font-weight:700; color:var(--text-main); margin-bottom:0.3rem;">Upload Payment Slip</div>
                    <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">You can upload from <a href="my_orders.php" style="color:var(--primary-glow); font-weight:700;">My Orders</a> if you didn't upload during checkout.</div>
                    <?php if (!empty($payment_slip_path)): ?>
                        <div style="color:#10b981; font-weight:700;">✅ Payment slip submitted successfully.</div>
                    <?php else: ?>
                        <div style="color:#f59e0b; font-size:0.88rem;">⚠️ No slip uploaded yet. Visit My Orders to upload.</div>
                    <?php endif; ?>
                </div>
            <?php elseif ($payment_method === 'crypto'): ?>
                <div class="success-pm-badge badge-awaiting">
                    ⏳ Waiting for Payment Verification
                </div>
                <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.65; max-width: 560px; margin: 0 auto 1.5rem;">
                    Your order has been placed. Please complete your crypto payment and submit your payment confirmation. Our team will verify your payment and process your order.
                </p>
            <?php elseif ($payment_method === 'paypal'): ?>
                <div class="success-pm-badge badge-awaiting">
                    ⏳ Waiting for Payment Verification
                </div>
                <p style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.65; max-width: 560px; margin: 0 auto 1.5rem;">
                    Your order has been placed. Please complete your PayPal payment. Our team will verify and process your order.
                </p>
            <?php else: ?>
                <p style="color: var(--text-muted); font-size: 1.1rem; line-height: 1.6; max-width: 500px; margin: 0 auto 1.5rem auto;">Your order has been placed successfully.</p>
            <?php endif; ?>
                </div>

                <div class="receipt-card">
                    <div class="receipt-watermark">SE</div>
                    <div class="receipt-header">
                        <div>
                            <h2>
                                <img src="logo.png" alt="Digi Pro X 24" style="height:36px; border-radius: 8px;">
                                Digi <span>Pro X 24</span>
                            </h2>
                            <div class="receipt-title">INVOICE</div>
                            <div class="shop-details" style="margin-top: 1.5rem; color: var(--text-muted); font-size: 0.95rem; line-height: 1.6;">
                                <strong>Digi Pro X 24</strong><br>
                                No.161, Wackwella Rd, Galle, Sri Lanka<br>
                                11320, Sri Lanka<br>
                                Phone: 070 6756006<br>
                                Email: digipro24@gmail.com
                            </div>
                        </div>
                    <?php if ($payment_method === 'cod'): ?>
                            <div class="paid-badge status-cod" style="background: rgba(16, 185, 129, 0.1); border-color: rgba(16, 185, 129, 0.3); color: #10b981;">✅ COD – No Online Payment</div>
                        <?php elseif ($payment_method === 'bank_transfer'): ?>
                            <div class="paid-badge status-cod" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); color: #f59e0b;">⏳ Awaiting Bank Verification</div>
                        <?php elseif ($payment_method === 'crypto'): ?>
                            <div class="paid-badge status-cod" style="background: rgba(38, 161, 123, 0.1); border-color: rgba(38, 161, 123, 0.3); color: #26a17b;">⏳ Awaiting Crypto Verification</div>
                        <?php elseif ($payment_method === 'paypal'): ?>
                            <div class="paid-badge status-cod" style="background: rgba(0, 121, 193, 0.1); border-color: rgba(0, 121, 193, 0.3); color: #0079c1;">⏳ Awaiting PayPal Verification</div>
                        <?php else: ?>
                            <div class="paid-badge status-cod" style="background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.3); color: #f59e0b;">⏳ Awaiting Verification</div>
                        <?php endif; ?>
                    </div>
                    <div class="receipt-details">
                        <div class="detail-block">
                            <strong>BILLED TO:</strong><br><br>
                            <?php echo htmlspecialchars($name); ?><br>
                            <?php echo htmlspecialchars($phone); ?><br>
                            <span style="word-break: break-all;"><?php echo htmlspecialchars($email); ?></span>
                        </div>
                        <div class="detail-block text-right">
                            <strong>ORDER NO:</strong> #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?><br><br>
                            <strong>DATE:</strong> <?php echo date('F j, Y'); ?><br>
                            <strong>PAYMENT:</strong> <?php echo strtoupper(str_replace('_', ' ', $payment_method)); ?>
                        </div>
                    </div>
                    
                    <table class="receipt-table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th style="text-align: center;">Warranty</th>
                                <th class="text-right">Unit Price</th>
                                <th style="text-align: center;">Qty</th>
                                <th class="text-right">Total (Rs.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($checkout_products as $p): 
                                $qty = $p['qty'];
                                $lkr_price = $p['lkr_price'];
                                $item_lkr = $p['total'];
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.8rem;">
                                        <img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,94,0,0.1); flex-shrink: 0;">
                                        <span><?php echo htmlspecialchars($p['name']); ?></span>
                                    </div>
                                </td>
                                <td style="text-align: center; font-size: 0.9rem; color: #aaa;"><?php echo htmlspecialchars($p['warranty'] ?? 'No Warranty'); ?></td>
                                <td class="text-right">Rs. <?php echo number_format($lkr_price, 2); ?></td>
                                <td style="text-align: center;"><?php echo $qty; ?></td>
                                <td class="text-right">Rs. <?php echo number_format($item_lkr, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="border-top: 1px solid rgba(255, 94, 0, 0.08); border-bottom: 1px solid rgba(255, 94, 0, 0.08); padding: 1.2rem 1rem; display: flex; flex-direction: column; gap: 0.6rem; background: rgba(255, 94, 0, 0.03); margin-top: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.95rem; color: var(--text-muted); font-weight: 500;">
                            <span>Items Subtotal</span>
                            <span style="color: var(--text-main);">Rs. <?php echo number_format($total - $shipping_fee, 2); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.95rem; color: var(--text-muted); font-weight: 500;">
                            <span>Delivery Charges</span>
                            <span style="color: var(--text-main);"><?php echo $shipping_fee > 0 ? 'Rs. ' . number_format($shipping_fee, 2) : 'Free Shipping'; ?></span>
                        </div>
                    </div>
                    
                    <div class="receipt-total-box">
                        <div class="receipt-total-label">Grand Total</div>
                        <div class="receipt-total-value">Rs. <?php echo number_format($total, 2); ?></div>
                    </div>

                    <div class="receipt-footer">
                        Shipping Address: <?php echo htmlspecialchars($final_address_line_1 . ', ' . $final_city . ', ' . $final_state_province_region . ' ' . $final_zip); ?><br>
                        Thank you for shopping with Digi Pro X 24!
                        
                        <div class="invoice-barcode-container" style="margin-top: 2rem; display: flex; flex-direction: column; align-items: center; gap: 0.3rem; opacity: 0.8;">
                            <div style="display: flex; gap: 2px; height: 35px; align-items: stretch;">
                                <div style="width: 2px; background: var(--text-muted);"></div>
                                <div style="width: 1px; background: var(--text-muted);"></div>
                                <div style="width: 3px; background: var(--text-muted);"></div>
                                <div style="width: 1px; background: var(--text-muted);"></div>
                                <div style="width: 4px; background: var(--text-muted);"></div>
                                <div style="width: 2px; background: var(--text-muted);"></div>
                                <div style="width: 1px; background: var(--text-muted);"></div>
                                <div style="width: 3px; background: var(--text-muted);"></div>
                                <div style="width: 2px; background: var(--text-muted);"></div>
                                <div style="width: 4px; background: var(--text-muted);"></div>
                                <div style="width: 1px; background: var(--text-muted);"></div>
                                <div style="width: 2px; background: var(--text-muted);"></div>
                                <div style="width: 1px; background: var(--text-muted);"></div>
                                <div style="width: 3px; background: var(--text-muted);"></div>
                                <div style="width: 2px; background: var(--text-muted);"></div>
                                <div style="width: 4px; background: var(--text-muted);"></div>
                                <div style="width: 1px; background: var(--text-muted);"></div>
                                <div style="width: 3px; background: var(--text-muted);"></div>
                                <div style="width: 1px; background: var(--text-muted);"></div>
                                <div style="width: 4px; background: var(--text-muted);"></div>
                            </div>
                            <span style="font-family: 'Courier New', monospace; font-size: 0.75rem; letter-spacing: 2px; color: #64748b;">DPX24-ORDER-<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></span>
                        </div>

                        <div class="receipt-credit" style="margin-top: 2rem; border-top: 1px solid rgba(255,94,0,0.08); padding-top: 1.5rem; font-size: 0.8rem; color: #64748b;">
                            System Design By <a href="https://fusionwavesystems.com/" target="_blank" rel="noopener noreferrer" style="color: var(--text-main); letter-spacing: 0.5px; font-weight: 700; text-decoration: none; border-bottom: 1px dashed var(--text-main);">Fusion Wave Systems (Pvt) Ltd.</a>
                        </div>
                    </div>
                </div>


                <div class="no-print" style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center; width: 100%; flex-wrap: wrap;">
                    <a href="index.php" class="btn-primary neon-glow" style="padding: 1rem 2rem; font-size: 1.1rem; text-align: center; flex: 1; max-width: 250px;">Return to Storefront</a>
                    <button onclick="window.print()" class="btn-secondary neon-glow" style="padding: 1rem 2rem; font-size: 1.1rem; display:flex; align-items:center; justify-content: center; gap:0.5rem; cursor: pointer; flex: 1; max-width: 250px;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Download PDF Bill
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-stepper">
                <div class="step active" id="step-header-customer"><div class="step-num">1</div> <span class="step-label"><span class="label-full">Customer details</span><span class="label-short">Details</span></span></div>
                <div class="step-line" id="step-line-1"></div>
                <div class="step" id="step-header-info"><div class="step-num">2</div> <span class="step-label"><span class="label-full">Additional Information</span><span class="label-short">Info</span></span></div>
                <div class="step-line" id="step-line-2"></div>
                <div class="step" id="step-header-payment"><div class="step-num">3</div> <span class="step-label"><span class="label-full">Payments</span><span class="label-short">Payment</span></span></div>
                <div class="step-line" id="step-line-3"></div>
                <div class="step" id="step-header-complete"><div class="step-num">4</div> <span class="step-label"><span class="label-full">Order Complete</span><span class="label-short">Complete</span></span></div>
            </div>

            <?php if(isset($error)): ?>
                <div class="error-msg">
                    <span style="font-size: 1.5rem;">⚠️</span>
                    <div><strong>Hold up!</strong> <?php echo $error; ?></div>
                </div>
            <?php endif; ?>

            <form action="checkout.php" method="POST" enctype="multipart/form-data" class="checkout-layout" novalidate>
                <div class="checkout-forms">
                    
                    <div id="checkout-step-1-container">


                        <div class="form-section glass-panel">
                            <h2><span>1</span> Customer details</h2>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>First Name</label>
                                    <input type="text" name="first_name" id="first-name" class="form-input" placeholder="John" value="<?php echo htmlspecialchars($default_first_name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Last Name</label>
                                    <input type="text" name="last_name" id="last-name" class="form-input" placeholder="Doe" value="<?php echo htmlspecialchars($default_last_name); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Phone Number</label>
                                    <div class="phone-input-wrapper">
                                        <input type="tel" name="phone" id="phone" class="form-input" value="<?php echo htmlspecialchars($default_phone); ?>" placeholder="712345678" required style="padding-left: 50px;">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" id="email" class="form-input" placeholder="john@example.com" value="<?php echo htmlspecialchars($default_email); ?>" required>
                                </div>
                                <div class="form-group full">
                                    <label>Address Line 1</label>
                                    <input type="text" name="address_line_1" id="street-address" class="form-input" placeholder="House number and street name" value="<?php echo htmlspecialchars($default_street_address); ?>" required>
                                </div>
                                <div class="form-group full">
                                    <label>Address Line 2 (Optional)</label>
                                    <input type="text" name="address_line_2" id="street-address-2" class="form-input" placeholder="Apartment, suite, unit, etc." value="<?php echo htmlspecialchars($default_street_address_2); ?>">
                                </div>
                                <div class="form-group full">
                                    <label>Country</label>
                                    <select name="country" id="country-select" class="form-input" required>
                                        <option value="">Select a country...</option>
                                        <option value="US">United States</option>
                                        <option value="GB">United Kingdom</option>
                                        <option value="AU">Australia</option>
                                        <option value="CA">Canada</option>
                                        <option value="LK">Sri Lanka</option>
                                        <option value="IN">India</option>
                                        <option value="AE">United Arab Emirates</option>
                                        <option value="SG">Singapore</option>
                                        <option value="NZ">New Zealand</option>
                                        <option value="DE">Germany</option>
                                        <option value="FR">France</option>
                                        <option value="IT">Italy</option>
                                        <option value="ES">Spain</option>
                                        <option value="NL">Netherlands</option>
                                        <option value="SE">Sweden</option>
                                        <option value="CH">Switzerland</option>
                                        <option value="JP">Japan</option>
                                        <option value="ZA">South Africa</option>
                                        <option value="MY">Malaysia</option>
                                        <option value="MV">Maldives</option>
                                        <option value="QA">Qatar</option>
                                        <option value="SA">Saudi Arabia</option>
                                        <option value="AF">Afghanistan</option>
                                        <option value="AL">Albania</option>
                                        <option value="DZ">Algeria</option>
                                        <option value="AD">Andorra</option>
                                        <option value="AO">Angola</option>
                                        <option value="AG">Antigua & Barbuda</option>
                                        <option value="AR">Argentina</option>
                                        <option value="AM">Armenia</option>
                                        <option value="AT">Austria</option>
                                        <option value="AZ">Azerbaijan</option>
                                        <option value="BS">Bahamas</option>
                                        <option value="BH">Bahrain</option>
                                        <option value="BD">Bangladesh</option>
                                        <option value="BB">Barbados</option>
                                        <option value="BY">Belarus</option>
                                        <option value="BE">Belgium</option>
                                        <option value="BZ">Belize</option>
                                        <option value="BJ">Benin</option>
                                        <option value="BT">Bhutan</option>
                                        <option value="BO">Bolivia</option>
                                        <option value="BA">Bosnia & Herzegovina</option>
                                        <option value="BW">Botswana</option>
                                        <option value="BR">Brazil</option>
                                        <option value="BN">Brunei</option>
                                        <option value="BG">Bulgaria</option>
                                        <option value="BF">Burkina Faso</option>
                                        <option value="BI">Burundi</option>
                                        <option value="CV">Cape Verde</option>
                                        <option value="KH">Cambodia</option>
                                        <option value="CM">Cameroon</option>
                                        <option value="TD">Chad</option>
                                        <option value="CL">Chile</option>
                                        <option value="CN">China</option>
                                        <option value="CO">Colombia</option>
                                        <option value="KM">Comoros</option>
                                        <option value="CG">Congo - Brazzaville</option>
                                        <option value="CD">Congo - Kinshasa</option>
                                        <option value="CR">Costa Rica</option>
                                        <option value="HR">Croatia</option>
                                        <option value="CU">Cuba</option>
                                        <option value="CY">Cyprus</option>
                                        <option value="CZ">Czechia</option>
                                        <option value="DK">Denmark</option>
                                        <option value="DJ">Djibouti</option>
                                        <option value="DM">Dominica</option>
                                        <option value="DO">Dominican Republic</option>
                                        <option value="EC">Ecuador</option>
                                        <option value="EG">Egypt</option>
                                        <option value="SV">El Salvador</option>
                                        <option value="GQ">Equatorial Guinea</option>
                                        <option value="ER">Eritrea</option>
                                        <option value="EE">Estonia</option>
                                        <option value="SZ">Eswatini</option>
                                        <option value="ET">Ethiopia</option>
                                        <option value="FJ">Fiji</option>
                                        <option value="FI">Finland</option>
                                        <option value="GA">Gabon</option>
                                        <option value="GM">Gambia</option>
                                        <option value="GE">Georgia</option>
                                        <option value="GH">Ghana</option>
                                        <option value="GR">Greece</option>
                                        <option value="GD">Grenada</option>
                                        <option value="GT">Guatemala</option>
                                        <option value="GN">Guinea</option>
                                        <option value="GW">Guinea-Bissau</option>
                                        <option value="GY">Guyana</option>
                                        <option value="HT">Haiti</option>
                                        <option value="HN">Honduras</option>
                                        <option value="HU">Hungary</option>
                                        <option value="IS">Iceland</option>
                                        <option value="ID">Indonesia</option>
                                        <option value="IR">Iran</option>
                                        <option value="IQ">Iraq</option>
                                        <option value="IE">Ireland</option>
                                        <option value="IL">Israel</option>
                                        <option value="JM">Jamaica</option>
                                        <option value="JO">Jordan</option>
                                        <option value="KZ">Kazakhstan</option>
                                        <option value="KE">Kenya</option>
                                        <option value="KI">Kiribati</option>
                                        <option value="KP">North Korea</option>
                                        <option value="KR">South Korea</option>
                                        <option value="KW">Kuwait</option>
                                        <option value="KG">Kyrgyzstan</option>
                                        <option value="LA">Laos</option>
                                        <option value="LV">Latvia</option>
                                        <option value="LB">Lebanon</option>
                                        <option value="LS">Lesotho</option>
                                        <option value="LR">Liberia</option>
                                        <option value="LY">Libya</option>
                                        <option value="LI">Liechtenstein</option>
                                        <option value="LT">Lithuania</option>
                                        <option value="LU">Luxembourg</option>
                                        <option value="MG">Madagascar</option>
                                        <option value="MW">Malawi</option>
                                        <option value="ML">Mali</option>
                                        <option value="MT">Malta</option>
                                        <option value="MH">Marshall Islands</option>
                                        <option value="MR">Mauritania</option>
                                        <option value="MU">Mauritius</option>
                                        <option value="MX">Mexico</option>
                                        <option value="FM">Micronesia</option>
                                        <option value="MD">Moldova</option>
                                        <option value="MC">Monaco</option>
                                        <option value="MN">Mongolia</option>
                                        <option value="ME">Montenegro</option>
                                        <option value="MA">Morocco</option>
                                        <option value="MZ">Mozambique</option>
                                        <option value="MM">Myanmar</option>
                                        <option value="NA">Namibia</option>
                                        <option value="NR">Nauru</option>
                                        <option value="NP">Nepal</option>
                                        <option value="NI">Nicaragua</option>
                                        <option value="NE">Niger</option>
                                        <option value="NG">Nigeria</option>
                                        <option value="MK">North Macedonia</option>
                                        <option value="NO">Norway</option>
                                        <option value="OM">Oman</option>
                                        <option value="PK">Pakistan</option>
                                        <option value="PW">Palau</option>
                                        <option value="PS">Palestine</option>
                                        <option value="PA">Panama</option>
                                        <option value="PG">Papua New Guinea</option>
                                        <option value="PY">Paraguay</option>
                                        <option value="PE">Peru</option>
                                        <option value="PH">Philippines</option>
                                        <option value="PL">Poland</option>
                                        <option value="PT">Portugal</option>
                                        <option value="RO">Romania</option>
                                        <option value="RU">Russia</option>
                                        <option value="RW">Rwanda</option>
                                        <option value="WS">Samoa</option>
                                        <option value="SM">San Marino</option>
                                        <option value="ST">São Tomé & Príncipe</option>
                                        <option value="SN">Senegal</option>
                                        <option value="RS">Serbia</option>
                                        <option value="SC">Seychelles</option>
                                        <option value="SL">Sierra Leone</option>
                                        <option value="SK">Slovakia</option>
                                        <option value="SI">Slovenia</option>
                                        <option value="SB">Solomon Islands</option>
                                        <option value="SO">Somalia</option>
                                        <option value="SS">South Sudan</option>
                                        <option value="SD">Sudan</option>
                                        <option value="SR">Suriname</option>
                                        <option value="SY">Syria</option>
                                        <option value="TW">Taiwan</option>
                                        <option value="TJ">Tajikistan</option>
                                        <option value="TZ">Tanzania</option>
                                        <option value="TH">Thailand</option>
                                        <option value="TG">Togo</option>
                                        <option value="TO">Tonga</option>
                                        <option value="TT">Trinidad & Tobago</option>
                                        <option value="TN">Tunisia</option>
                                        <option value="TR">Turkey</option>
                                        <option value="TM">Turkmenistan</option>
                                        <option value="TV">Tuvalu</option>
                                        <option value="UG">Uganda</option>
                                        <option value="UA">Ukraine</option>
                                        <option value="UY">Uruguay</option>
                                        <option value="UZ">Uzbekistan</option>
                                        <option value="VU">Vanuatu</option>
                                        <option value="VA">Vatican City</option>
                                        <option value="VE">Venezuela</option>
                                        <option value="VN">Vietnam</option>
                                        <option value="YE">Yemen</option>
                                        <option value="ZM">Zambia</option>
                                        <option value="ZW">Zimbabwe</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" name="city" id="city" class="form-input" placeholder="City" value="<?php echo htmlspecialchars($default_city); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>State / Province / Region</label>
                                    <input type="text" name="state_province_region" id="district" class="form-input" placeholder="State / Province / Region" value="<?php echo htmlspecialchars($default_district); ?>" required>
                                </div>
                                <div class="form-group full">
                                    <label>Postcode / ZIP</label>
                                    <input type="text" name="postcode" id="postcode" class="form-input" placeholder="Postcode" value="<?php echo htmlspecialchars($default_postcode); ?>" required>
                                </div>
                                

                            </div>
                            <div class="step-nav-bar">
                                <a href="cart.php" class="btn-step-back">
                                    ⬅ Back
                                </a>
                                <button type="button" id="btn-next-step" class="btn-step-next" onclick="goToStep(2)">
                                    NEXT ➔
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="checkout-step-2-container" style="display:none;">
                    <div class="form-section glass-panel">
                        <h2><span>2</span> Additional Information</h2>

                        <div class="form-group full" style="margin-top: 0.8rem; background: rgba(255,94,0,0.03); padding: 1.1rem 1.4rem; border-radius: 14px; border: 1.5px solid rgba(255,94,0,0.18);">
                                <label style="display: inline-flex; align-items: center; gap: 0.75rem; cursor: pointer; color: var(--text-main); font-size: 1rem; font-weight: 700; user-select: none;">
                                    <input type="checkbox" name="same_as_billing" id="same-address-checkbox" checked onchange="toggleDeliveryAddress()" style="width: 20px; height: 20px; border-radius: 4px; accent-color: #ff5e00; cursor: pointer;">
                                    <span>Use shipping address as billing address</span>
                                </label>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.35rem; padding-left: 2.3rem;">Untick this checkbox if you want to deliver this order to a different recipient or shipping address.</div>
                            </div>

                            <div class="form-group full" id="delivery-address-container" style="display: none; border-top: 1px dashed rgba(255,94,0,0.25); padding-top: 1.5rem; margin-top: 1.25rem;">
                                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 1.2rem;">
                                    <div>
                                        <h3 style="font-size: 1.2rem; color: var(--primary-glow); margin: 0; font-weight: 800;">Separate Shipping / Delivery Address</h3>
                                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0.2rem 0 0 0;">Enter the recipient's details where this package should be delivered.</p>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Recipient First Name</label>
                                        <input type="text" name="del_first_name" id="del-first-name" class="form-input" placeholder="Recipient First Name">
                                    </div>
                                    <div class="form-group">
                                        <label>Recipient Last Name</label>
                                        <input type="text" name="del_last_name" id="del-last-name" class="form-input" placeholder="Recipient Last Name">
                                    </div>
                                    <div class="form-group">
                                        <label>Recipient Phone Number</label>
                                        <div class="phone-input-wrapper">
                                            <input type="tel" name="del_phone" id="del-phone" class="form-input" placeholder="712345678" style="padding-left: 50px;">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Recipient Email</label>
                                        <input type="email" name="del_email" id="del-email" class="form-input" placeholder="recipient@example.com">
                                    </div>
                                    <div class="form-group full">
                                        <label>Shipping Address Line 1</label>
                                        <input type="text" name="del_address_line_1" id="del-street-address" class="form-input" placeholder="House number and street name">
                                    </div>
                                    <div class="form-group full">
                                        <label>Shipping Address Line 2 (Optional)</label>
                                        <input type="text" name="del_address_line_2" id="del-street-address-2" class="form-input" placeholder="Apartment, suite, unit, etc.">
                                    </div>
                                    <div class="form-group full">
                                        <label>Shipping Country</label>
                                        <select name="del_country" id="del-country-select" class="form-input">
                                            <option value="">Select country...</option>
                                            <option value="LK" selected>Sri Lanka</option>
                                            <option value="US">United States</option>
                                            <option value="GB">United Kingdom</option>
                                            <option value="AU">Australia</option>
                                            <option value="CA">Canada</option>
                                            <option value="IN">India</option>
                                            <option value="AE">United Arab Emirates</option>
                                            <option value="SG">Singapore</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Shipping City</label>
                                        <input type="text" name="del_city" id="del-city" class="form-input" placeholder="City">
                                    </div>
                                    <div class="form-group">
                                        <label>Shipping State / Province / Region</label>
                                        <input type="text" name="del_state_province_region" id="del-district" class="form-input" placeholder="State / Province / Region">
                                    </div>
                                    <div class="form-group">
                                        <label>Shipping Postcode / ZIP</label>
                                        <input type="text" name="del_postcode" id="del-postcode" class="form-input" placeholder="Postcode">
                                    </div>
                                </div>
                            </div>


                        <div class="form-group full" style="margin-top: 1rem; margin-bottom: 1.5rem;">
                            <label style="font-size: 1.1rem; color: var(--primary-glow); font-weight: 800; margin-bottom: 0.5rem; display: block;">Order Notes (Optional)</label>
                            <textarea name="order_notes" id="order-notes" class="form-input" placeholder="Notes about your order, e.g. special notes for delivery." rows="3" style="resize: vertical;"></textarea>
                        </div>
                        
                        <div class="step-nav-bar">
                            <button type="button" onclick="goToStep(1)" class="btn-step-back">
                                ⬅ Back
                            </button>
                            <button type="button" id="btn-next-step-2" class="btn-step-next" onclick="goToStep(3)">
                                NEXT ➔
                            </button>
                        </div>
                    </div>
                    </div>

                    <div id="checkout-step-3-container" style="display:none;">
                    <div class="form-section glass-panel">
                        <h2><span>3</span> Payments</h2>
                                                    <div class="payment-methods" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                            <!-- Cash on Delivery -->
                                <label class="payment-method selected" id="pm-cod" onclick="selectMethod('pm-cod')">
                                    <input type="radio" name="payment_method" value="cod" checked>
                                    <div class="pm-icon">
                                        <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius:3px; padding:2px;">
                                            <rect width="38" height="24" fill="#ffffff"/>
                                            <rect x="5" y="6" width="16" height="10" rx="1" fill="#475569"/>
                                            <text x="13" y="13" fill="#ffffff" font-family="system-ui, -apple-system" font-weight="900" font-size="6" text-anchor="middle">COD</text>
                                            <path d="M21 9h5l2 3v4h-7V9z" fill="#334155"/>
                                            <circle cx="9" cy="17" r="2" fill="#0f172a"/>
                                            <circle cx="23" cy="17" r="2" fill="#0f172a"/>
                                        </svg>
                                    </div>
                                    <div class="pm-name" style="color:var(--text-main);">Cash on Delivery<br><span style="font-size:0.75rem; color:#10b981; font-weight:700;">(Pay at Doorstep)</span></div>
                                </label>

                            <!-- Crypto -->
                            <label class="payment-method" id="pm-crypto" onclick="selectMethod('pm-crypto')">
                                <input type="radio" name="payment_method" value="crypto">
                                <div class="pm-icon">
                                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius:3px; padding:2px;">
                                        <rect width="38" height="24" fill="#ffffff"/>
                        <circle cx="19" cy="12" r="9" fill="#26a17b"/>
                        <path d="M19 7c-2.4 0-4.3 0.3-4.3 0.8s1.9 0.8 4.3 0.8 4.3-0.3 4.3-0.8S21.4 7 19 7zm0.5 1.7H22v0.8h-2.5v4.5h-1v-4.5H16v-0.8h2.5v-0.1h1v0.1z" fill="#ffffff"/>
                                    </svg>
                                </div>
                                <div class="pm-name" style="color:var(--text-main);">Cryptocurrency<br><span style="font-size:0.75rem; color:#26a17b; font-weight:700;">(USDT Only)</span></div>
                            </label>

                            <!-- PayPal -->
                            <label class="payment-method" id="pm-paypal" onclick="selectMethod('pm-paypal')">
                                <input type="radio" name="payment_method" value="paypal">
                                <div class="pm-icon">
                                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius:3px; padding:2px;">
                                        <rect width="38" height="24" fill="#ffffff"/>
                                        <path d="M12.5 4h5.2c1.8 0 3.2.4 4 1.2.7.7 1 1.7.9 3-.1 1.8-.8 3.2-1.9 4.1-1 .9-2.5 1.3-4.4 1.3h-2.1l-1.3 6.4h-3.4l2.6-13c.2-1 .4-1.7.7-2 .3-.3 1-.3 1.7-.3z" fill="#003087"/>
                                        <path d="M14.5 6h5.2c1.8 0 3.2.4 4 1.2.7.7 1 1.7.9 3-.1 1.8-.8 3.2-1.9 4.1-1 .9-2.5 1.3-4.4 1.3h-2.1l-1.3 6.4h-3.4l2.6-13c.2-1 .4-1.7.7-2 .3-.3 1-.3 1.7-.3z" fill="#0079C1" opacity="0.8"/>
                                    </svg>
                                </div>
                                <div class="pm-name" style="color:var(--text-main);">PayPal<br><span style="font-size:0.75rem; color:#0079c1; font-weight:700;">(Express Checkout)</span></div>
                            </label>

                            <!-- Bank Transfer -->
                            <label class="payment-method" id="pm-bank" onclick="selectMethod('pm-bank')">
                                <input type="radio" name="payment_method" value="bank_transfer">
                                <div class="pm-icon">
                                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius:3px; padding:2px;">
                                        <rect width="38" height="24" fill="#ffffff"/>
                                        <path d="M19 4L6 9v2h26V9L19 4zm-9 9v6h3v-6h-3zm6 0v6h3v-6h-3zm6 0v6h3v-6h-3zm-14 8v1h20v-1H8z" fill="#0f172a"/>
                                    </svg>
                                </div>
                                <div class="pm-name" style="color:var(--text-main);">Bank Transfer<br><span style="font-size:0.75rem; color:var(--primary-glow); font-weight:700;">(Direct Deposit)</span></div>
                            </label>
                        </div>

                        <!-- Final Order Summary (Shows before buy button on all devices) -->
                        <div class="final-order-summary no-print" style="margin-top: 1.5rem; margin-bottom: 1.5rem; background: rgba(0,0,0,0.02); padding: 1.5rem; border-radius: 12px; border: 1px solid var(--border-light); display: block;">
                            <h3 style="font-size: 1.1rem; margin-bottom: 1rem; color: var(--text-main); border-bottom: 1px solid var(--border-light); padding-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                                Final Order Summary
                                <span style="font-size: 0.8rem; background: var(--primary-glow); color: white; padding: 2px 8px; border-radius: 20px; font-weight: 600;">Checkout</span>
                            </h3>
                            <div style="display: flex; justify-content: space-between; font-size: 0.95rem; color: var(--text-muted); margin-bottom: 0.8rem;">
                                <span>Items Subtotal</span>
                                <span>Rs. <?php echo number_format($total - $shipping_fee, 2); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.95rem; color: var(--text-muted); margin-bottom: 0.4rem;">
                                <span>Delivery Fee</span>
                                <span id="shipping-fee-display-mobile" style="color: var(--text-main); font-weight: 600;">
                                    Pending
                                </span>
                            </div>
                            <!-- Shipping unavailable note (mobile) -->
                            <div id="shipping-unavailable-note-mobile" style="display: none; align-items: flex-start; gap: 0.5rem; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 0.55rem 0.8rem; margin-bottom: 0.8rem; font-size: 0.82rem; color: #fca5a5; line-height: 1.4;">
                                <span style="font-size: 1rem; flex-shrink: 0;">🚫</span>
                                <span>Our delivery facility currently does not ship to the selected country. Please choose a supported shipping destination to proceed.</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 1.15rem; color: var(--primary-glow); font-weight: 800; margin-top: 1rem; border-top: 1px dashed var(--border-light); padding-top: 1rem;">
                                <span>Grand Total</span>
                                <span id="grand-total-display-mobile">Rs. <?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>

                        <!-- COD Panel -->
                        <div id="pm-panel-cod" class="pm-panel pm-panel-cod visible">
                            <h3 style="color:#10b981;">🚚 Cash on Delivery</h3>
                            <div style="display:flex; align-items:center; gap:1rem; padding:1rem; background:rgba(16,185,129,0.08); border-radius:12px; border:1px solid rgba(16,185,129,0.2);">
                                <div style="font-size:2rem; flex-shrink:0;">💵</div>
                                <div>
                                    <div style="font-weight:700; color:var(--text-main); margin-bottom:0.3rem;">Pay at Doorstep</div>
                                    <div style="font-size:0.88rem; color:var(--text-muted); line-height:1.5;">No online payment required. Our delivery agent will collect payment when your order arrives. Cash or mobile payments accepted at delivery.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Crypto Panel -->
                        <div id="pm-panel-crypto" class="pm-panel pm-panel-crypto">
                            <h3 style="color:#26a17b;">🪙 Cryptocurrency Payment – USDT (TRC20)</h3>
                            <div class="pm-section-title">Step 1 — Transfer Payment</div>
                            <div style="text-align:center; margin-bottom:1rem;">
                                <img src="assets/usdt_qr.jpeg" alt="USDT TRC20 QR Code" style="max-width:160px; border-radius:12px; border:2px solid rgba(38,161,123,0.3); padding:5px; background:white;">
                            </div>
                            <div class="pm-info-box"><strong>USDT Address (TRC20):</strong><br>TS96vFScwsiwML7m4MtoQzRA6ZAQU7mz5L</div>
                            <p style="font-size:0.85rem; color:var(--text-muted);">Transfer the exact order amount in USDT (Tron TRC20) to the address above.</p>

                            <div class="pm-section-title">Step 2 — Enter Transaction ID</div>
                            <div class="form-group">
                                <label style="color:var(--text-muted); font-size:0.88rem; font-weight:600;">Transaction ID / Hash</label>
                                <input type="text" name="transaction_id" id="txn-id-crypto" class="form-input" placeholder="e.g. abc123def456...">
                            </div>

                            <div class="pm-section-title">Step 3 — Upload Payment Screenshot</div>
                            <div class="file-upload-zone" id="upload-zone-crypto" onclick="document.getElementById('slip-upload-crypto').click()">
                                <div class="upload-icon">📸</div>
                                <div class="upload-text"><strong>Click to upload screenshot</strong><br>JPG, PNG, WEBP, PDF · Max 10MB</div>
                                <div id="file-name-display-crypto"></div>
                                <input type="file" name="payment_slip" id="slip-upload-crypto" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="handleFileSelect(this,'file-name-display-crypto','upload-zone-crypto')">
                            </div>
                            <p class="pm-note">💡 You can also upload your proof later from <strong>My Orders</strong> if needed.</p>
                        </div>

                        <!-- PayPal Panel -->
                        <div id="pm-panel-paypal" class="pm-panel pm-panel-paypal">
                            <h3 style="color:#0079c1;">💳 PayPal Express Checkout</h3>
                            <div class="pm-section-title">Step 1 — Send Payment</div>
                            <div class="pm-info-box" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div><strong>PayPal Email:</strong> digipro24@gmail.com</div>
                                <a href="https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=digipro24@gmail.com" target="_blank" style="background: #0079c1; color: #ffffff; padding: 6px 16px; border-radius: 6px; text-decoration: none; font-weight: 700; font-family: 'Outfit', sans-serif; font-size: 0.85rem; box-shadow: 0 4px 10px rgba(0, 121, 193, 0.3); transition: transform 0.2s ease, background 0.2s ease;" onmouseover="this.style.background='#005a91'; this.style.transform='translateY(-2px)';" onmouseout="this.style.background='#0079c1'; this.style.transform='none';">Pay</a>
                            </div>
                            <p style="font-size:0.85rem; color:var(--text-muted);">Send the exact order total to our verified PayPal account. Include your name in the payment note.</p>

                            <div class="pm-section-title">Step 2 — Enter Transaction ID</div>
                            <div class="form-group">
                                <label style="color:var(--text-muted); font-size:0.88rem; font-weight:600;">PayPal Transaction ID</label>
                                <input type="text" name="transaction_id" id="txn-id-paypal" class="form-input" placeholder="e.g. 4CD123456789A">
                            </div>

                            <div class="pm-section-title">Step 3 — Upload Payment Screenshot</div>
                            <div class="file-upload-zone" id="upload-zone-paypal" onclick="document.getElementById('slip-upload-paypal').click()">
                                <div class="upload-icon">📸</div>
                                <div class="upload-text"><strong>Click to upload screenshot</strong><br>JPG, PNG, WEBP, PDF · Max 10MB</div>
                                <div id="file-name-display-paypal"></div>
                                <input type="file" name="payment_slip" id="slip-upload-paypal" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="handleFileSelect(this,'file-name-display-paypal','upload-zone-paypal')">
                            </div>
                            <p class="pm-note">💡 You can also upload your proof later from <strong>My Orders</strong> if needed.</p>
                        </div>

                        <!-- Bank Transfer Panel -->
                        <div id="pm-panel-bank" class="pm-panel pm-panel-bank">
                            <h3 style="color:var(--primary-glow);">🏦 Direct Bank Transfer</h3>
                            <div class="pm-section-title">Step 1 — Bank Deposit Details</div>
                            <div class="pm-info-box">
                                <strong>Bank Name:</strong> Seylan Bank<br>
                                <strong>Account Name:</strong> H.M.P. Abeyrathne<br>
                                <strong>Account Number:</strong> 16012995186101<br>
                                <strong>Branch:</strong> Galle Main Street
                            </div>

                            <div class="pm-section-title">Step 2 — Upload Deposit Slip</div>
                            <div class="file-upload-zone" id="upload-zone-bank" onclick="document.getElementById('slip-upload-bank').click()">
                                <div class="upload-icon">📎</div>
                                <div class="upload-text"><strong>Click to upload your bank slip</strong><br>JPG, PNG, WEBP, PDF · Max 10MB</div>
                                <div id="file-name-display-bank"></div>
                                <input type="file" name="payment_slip" id="slip-upload-bank" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="handleFileSelect(this,'file-name-display-bank','upload-zone-bank')">
                            </div>
                            <p class="pm-note">💡 You can also upload your deposit slip later from <strong>My Orders</strong>. Your order will be held until payment is verified.</p>
                        </div>

                        <div class="step-nav-bar">
                            <button type="button" onclick="goToStep(2)" class="btn-step-back">
                                ⬅ Back
                            </button>
                            <button type="submit" id="btn-submit-order" class="btn-step-next">
                                ✓ PLACE ORDER
                            </button>
                        </div>
                    </div>
                    </div>
                </div>


                <div class="order-summary-box">
                    <h3>Order Summary</h3>
                    
                    <div style="margin-bottom: 2rem; max-height: 200px; overflow-y: auto; padding-right: 10px;">
                        <?php 
                        foreach($checkout_products as $p): 
                            $qty = $p['qty'];
                            $lkr_price = $p['lkr_price'];
                        ?>
                            <div style="display: flex; gap: 1rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px dashed rgba(255,94,0,0.08); font-size: 1rem; align-items: flex-start;">
                                <div style="width: 65%; min-width: 0; display: flex; flex-direction: column; gap: 0.25rem;">
                                    <div style="font-weight: 600; color: var(--text-main); line-height: 1.5; white-space: normal; word-break: break-word;">
                                        <?php echo htmlspecialchars($p['name']); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                                        Quantity: <strong style="color: var(--text-main);"><?php echo $qty; ?></strong>
                                    </div>
                                </div>
                                <div style="width: 35%; text-align: right; font-weight: 700; color: var(--primary-glow); font-size: 1rem; padding-top: 0.2rem; white-space: nowrap;">
                                    Rs. <?php echo number_format($lkr_price * $qty, 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span>Items Total</span>
                        <span>Rs. <?php echo number_format($total - $shipping_fee, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span id="shipping-fee-display" style="color: var(--text-main); font-weight: 600;">
                            <?php echo ($delivery_charge == 0 && !empty($checkout_products)) ? 'Free Shipping' : 'Pending'; ?>
                        </span>
                    </div>

                    <!-- Shipping unavailable note (sidebar) -->
                    <div id="shipping-unavailable-note-sidebar" style="display: none; align-items: flex-start; gap: 0.5rem; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 0.55rem 0.8rem; margin: 0.3rem 0 0.6rem; font-size: 0.82rem; color: #fca5a5; line-height: 1.4;">
                        <span style="font-size: 1rem; flex-shrink: 0;">🚫</span>
                        <span>Our delivery facility currently does not ship to the selected country. Please choose a supported shipping destination to proceed.</span>
                    </div>

                    <div class="summary-row" style="font-size: 0.85rem; color: #60a5fa; background: rgba(59, 130, 246, 0.1); padding: 0.6rem 0.8rem; border-radius: 10px; margin: 0.8rem 0; border: 1px solid rgba(59, 130, 246, 0.25); display: flex; justify-content: space-between; align-items: center;">
                        <span>🚚 Delivery Time</span>
                        <span style="font-weight: 800;">3 - 7 Business Days</span>
                    </div>
                    
                    <div class="summary-total">
                        <span>Total</span>
                        <span id="grand-total-display" style="color: var(--primary-glow);">Rs. <?php echo number_format($total, 2); ?></span>
                    </div>

                    <p style="text-align: center; margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                        By placing your order, you agree to our Terms of Service.
                    </p>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <script>
        const baseItemsTotal = <?php echo $total - $shipping_fee; ?>;
        
        function updateShippingFee() {
            const sameAddress = document.getElementById('same-address-checkbox');
            const isSameAddress = !sameAddress || sameAddress.checked;
            const countrySelect = isSameAddress ? document.getElementById('country-select') : document.getElementById('del-country-select');
            
            if (!countrySelect) return;
            const countryCode = countrySelect.value;
            if (!countryCode) return;
            
            const formData = new FormData();
            formData.append('country_code', countryCode);
            
            fetch('get_shipping_rate.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                const btnNext = document.getElementById('btn-next-step');
                const btnSubmit = document.getElementById('btn-submit-order');
                const isSubmitting = document.querySelector('form.checkout-layout')?.dataset.submitting === 'true';
                let infoDisplay = document.getElementById('shipping-info-msg');
                if (!infoDisplay) {
                    infoDisplay = document.createElement('div');
                    infoDisplay.id = 'shipping-info-msg';
                    infoDisplay.style.fontSize = '0.85rem';
                    infoDisplay.style.marginTop = '0.5rem';
                    infoDisplay.style.fontWeight = '600';
                }
                // Move the message under the currently active country selector
                countrySelect.parentNode.appendChild(infoDisplay);
                
                if (data.success) {
                    if (btnNext) btnNext.disabled = false;
                    if (!isSubmitting && btnSubmit) btnSubmit.disabled = false;
                    
                    let fee = parseFloat(data.fee);
                    const displayFee = fee > 0 ? 'Rs. ' + fee.toFixed(2) : 'Free Shipping';
                    
                    infoDisplay.style.color = 'var(--primary-glow)'; 
                    infoDisplay.innerHTML = '🚚 Delivery Fee to selected country: <strong>' + displayFee + '</strong>';
                    
                    const newTotal = baseItemsTotal + fee;
                    
                    document.getElementById('shipping-fee-display').textContent = displayFee;
                    document.getElementById('grand-total-display').textContent = 'Rs. ' + newTotal.toFixed(2);
                    
                    // Hide shipping unavailable notes
                    const noteM = document.getElementById('shipping-unavailable-note-mobile');
                    const noteS = document.getElementById('shipping-unavailable-note-sidebar');
                    if(noteM) noteM.style.display = 'none';
                    if(noteS) noteS.style.display = 'none';

                    
                    const mobShip = document.getElementById('shipping-fee-display-mobile');
                    const mobTotal = document.getElementById('grand-total-display-mobile');
                    if(mobShip) mobShip.textContent = displayFee;
                    if(mobTotal) mobTotal.textContent = 'Rs. ' + newTotal.toFixed(2);
                } else {
                    infoDisplay.style.color = '#ef4444'; // Red for error
                    infoDisplay.textContent = data.message;
                    
                    if (btnNext) btnNext.disabled = true;
                    if (btnSubmit) btnSubmit.disabled = true;
                    document.getElementById('shipping-fee-display').textContent = 'N/A';
                    document.getElementById('grand-total-display').textContent = 'N/A';
                    
                    const mobShip = document.getElementById('shipping-fee-display-mobile');
                    const mobTotal = document.getElementById('grand-total-display-mobile');
                    if(mobShip) mobShip.textContent = 'N/A';
                    if(mobTotal) mobTotal.textContent = 'N/A';

                    // Show shipping unavailable notes
                    const noteM = document.getElementById('shipping-unavailable-note-mobile');
                    const noteS = document.getElementById('shipping-unavailable-note-sidebar');
                    if(noteM) noteM.style.display = 'flex';
                    if(noteS) noteS.style.display = 'flex';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const mainCountry = document.getElementById('country-select');
            const delCountry = document.getElementById('del-country-select');
            const sameAddress = document.getElementById('same-address-checkbox');
            
            if (mainCountry) mainCountry.addEventListener('change', updateShippingFee);
            if (delCountry) delCountry.addEventListener('change', updateShippingFee);
            if (sameAddress) sameAddress.addEventListener('change', updateShippingFee);
            
            setTimeout(updateShippingFee, 100);
        });
        function goToStep(step) {
            
            const step1Container = document.getElementById('checkout-step-1-container');
            const step2Container = document.getElementById('checkout-step-2-container');
            const step3Container = document.getElementById('checkout-step-3-container');
            
            const stepHeaderCustomer = document.getElementById('step-header-customer');
            const stepHeaderInfo = document.getElementById('step-header-info');
            const stepHeaderPayment = document.getElementById('step-header-payment');
            
            const stepLine1 = document.getElementById('step-line-1');
            const stepLine2 = document.getElementById('step-line-2');
            
            const btnSubmit = document.getElementById('btn-submit-order');

            if (step === 2) {
                // Only validate if we are currently on step 1 (going forward)
                if (step1Container.style.display !== 'none') {
                    // Validate required fields in Step 1
                    const country = document.getElementById('country-select');
                    const firstName = document.getElementById('first-name');
                    const lastName = document.getElementById('last-name');
                    const streetAddress = document.getElementById('street-address');
                    const city = document.getElementById('city');
                    const stateProvince = document.getElementById('district');
                    const postcode = document.getElementById('postcode');
                    const email = document.getElementById('email');
                    const phone = document.getElementById('phone');
                    
                    if (!country.value || !firstName.reportValidity() || !lastName.reportValidity() || 
                        !streetAddress.reportValidity() || !city.reportValidity() || !stateProvince.reportValidity() || 
                        !postcode.reportValidity() || !email.reportValidity() || !phone.reportValidity()) {
                        return;
                    }
                }
                
                // Show Step 2
                step1Container.style.display = 'none';
                step2Container.style.display = 'block';
                if (step3Container) step3Container.style.display = 'none';
                
                // Update Stepper styling
                stepHeaderCustomer.classList.remove('active');
                stepHeaderCustomer.classList.add('completed');
                stepHeaderCustomer.querySelector('.step-num').innerHTML = '✓';
                
                if (stepLine1) stepLine1.style.background = '#10b981';
                
                if (stepHeaderInfo) {
                    stepHeaderInfo.classList.add('active');
                    stepHeaderInfo.classList.remove('completed');
                    stepHeaderInfo.querySelector('.step-num').innerHTML = '2';
                }
                
                if (stepLine2) stepLine2.style.background = '';
                if (stepHeaderPayment) {
                    stepHeaderPayment.classList.remove('active', 'completed');
                    stepHeaderPayment.querySelector('.step-num').innerHTML = '3';
                }
                
                if (stepHeaderInfo && typeof stepHeaderInfo.scrollIntoView === 'function') {
                    setTimeout(() => stepHeaderInfo.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' }), 50);
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else if (step === 3) {
                // Only validate if we are currently on step 2 (going forward)
                if (step2Container.style.display !== 'none') {
                    // Validate required fields in Step 2 (if separate delivery address is checked)
                    const sameAddressCheckbox = document.getElementById('same-address-checkbox');
                    const delFirstName = document.getElementById('del-first-name');
                    const delLastName = document.getElementById('del-last-name');
                    const delStreetAddress = document.getElementById('del-street-address');
                    const delCountry = document.getElementById('del-country-select');
                    const delCity = document.getElementById('del-city');
                    const delStateProvince = document.getElementById('del-district');
                    const delPostcode = document.getElementById('del-postcode');

                    let isDeliveryValid = true;
                    if (sameAddressCheckbox && !sameAddressCheckbox.checked) {
                        isDeliveryValid = delFirstName.reportValidity() && delLastName.reportValidity() && 
                                          delStreetAddress.reportValidity() && Boolean(delCountry.value) && 
                                          delCity.reportValidity() && delStateProvince.reportValidity() && 
                                          delPostcode.reportValidity();
                    }

                    if (!isDeliveryValid) {
                        return;
                    }
                }

                // Show Step 3
                step1Container.style.display = 'none';
                step2Container.style.display = 'none';
                if (step3Container) step3Container.style.display = 'block';

                stepHeaderCustomer.classList.remove('active');
                stepHeaderCustomer.classList.add('completed');
                stepHeaderCustomer.querySelector('.step-num').innerHTML = '✓';

                if (stepHeaderInfo) {
                    stepHeaderInfo.classList.remove('active');
                    stepHeaderInfo.classList.add('completed');
                    stepHeaderInfo.querySelector('.step-num').innerHTML = '✓';
                }

                if (stepLine1) stepLine1.style.background = '#10b981';
                if (stepLine2) stepLine2.style.background = '#10b981';

                if (stepHeaderPayment) {
                    stepHeaderPayment.classList.add('active');
                    stepHeaderPayment.classList.remove('completed');
                    stepHeaderPayment.querySelector('.step-num').innerHTML = '3';
                }

                if (stepHeaderPayment && typeof stepHeaderPayment.scrollIntoView === 'function') {
                    setTimeout(() => stepHeaderPayment.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' }), 50);
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                // Show Step 1
                step1Container.style.display = 'block';
                step2Container.style.display = 'none';
                if (step3Container) step3Container.style.display = 'none';
                
                // Update Stepper styling
                stepHeaderCustomer.classList.add('active');
                stepHeaderCustomer.classList.remove('completed');
                stepHeaderCustomer.querySelector('.step-num').innerHTML = '1';
                
                if (stepLine1) stepLine1.style.background = '';
                if (stepHeaderInfo) {
                    stepHeaderInfo.classList.remove('active', 'completed');
                    stepHeaderInfo.querySelector('.step-num').innerHTML = '2';
                }
                
                if (stepLine2) stepLine2.style.background = '';
                if (stepHeaderPayment) {
                    stepHeaderPayment.classList.remove('active', 'completed');
                    stepHeaderPayment.querySelector('.step-num').innerHTML = '3';
                }

                if (stepHeaderCustomer && typeof stepHeaderCustomer.scrollIntoView === 'function') {
                    setTimeout(() => stepHeaderCustomer.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' }), 50);
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        }

        function toggleInlineSignin() {
            const form = document.getElementById('inline-signin-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        function submitInlineSignin() {
            const email = document.getElementById('signin-email').value;
            const password = document.getElementById('signin-password').value;
            const errDiv = document.getElementById('signin-error');
            errDiv.style.display = 'none';

            if (!email || !password) {
                errDiv.textContent = 'Please enter both email and password.';
                errDiv.style.display = 'block';
                return;
            }

            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);

            fetch('checkout_login_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Pre-fill form fields
                    document.querySelector('input[name="name"]').value = data.user.name;
                    document.querySelector('input[name="email"]').value = data.user.email;
                    document.querySelector('input[name="phone"]').value = data.user.phone;
                    document.querySelector('input[name="address"]').value = data.user.address;
                    document.querySelector('input[name="city"]').value = data.user.city;
                    document.querySelector('input[name="zip"]').value = data.user.zip;

                                                            if (data.user.province) {
                        const provinceSelect = document.getElementById('province-select');
                        provinceSelect.value = data.user.province;
                        updateDistricts();
                        if (data.user.district) {
                            document.getElementById('district-select').value = data.user.district;
                        }
                    }

                    // Reload/Update header login status or hide the sign in banner dynamically
                    const banner = document.querySelector('.inline-signin-banner');
                    if (banner) {
                        banner.style.display = 'none';
                    }
                    
                    // Hide signup checkbox as well
                    const signupBox = document.getElementById('create-account-checkbox');
                    if (signupBox) {
                        signupBox.closest('.form-group').style.display = 'none';
                        document.getElementById('password-field-container').style.display = 'none';
                    }

                    // Update header action buttons to reflect logged in state
                    const headerActions = document.querySelector('.header-actions');
                    if (headerActions) {
                        headerActions.innerHTML = `
                            <a href="logout.php" class="btn-primary" style="text-decoration:none;">Logout</a>
                        `;
                    }
                } else {
                    errDiv.textContent = data.message || 'Login failed.';
                    errDiv.style.display = 'block';
                }
            })
            .catch(err => {
                console.error(err);
                errDiv.textContent = 'An error occurred during sign-in.';
                errDiv.style.display = 'block';
            });
        }

                        function normalizeCountryCode(val) {
            if (!val) return "";
            const v = val.toString().trim().toLowerCase().replace(/[^a-z0-9]/g, '');
            if (v === 'lk' || v === 'lka' || v === 'srilanka' || v === 'sl') {
                return 'LK';
            }
            return val.toString().trim().toUpperCase();
        }

        function updateCODVisibility() {
            const codLabel = document.getElementById('pm-cod');
            if (!codLabel) return;
            
            const accountCountry = "<?php echo htmlspecialchars($user_details['country'] ?? 'N/A'); ?>";
            const sameAddressCheckbox = document.getElementById('same-address-checkbox');
            const sameAsBilling = sameAddressCheckbox ? sameAddressCheckbox.checked : true;
            
            const mainSelect = document.getElementById('country-select');
            const delSelect = document.getElementById('del-country-select');
            
            let billingCountryVal = mainSelect ? (mainSelect.tomselect ? mainSelect.tomselect.getValue() : mainSelect.value) : "";
            let deliveryCountryVal = delSelect ? (delSelect.tomselect ? delSelect.tomselect.getValue() : delSelect.value) : "";
            
            let shippingCountryFromForm = "";
            let decisionReason = "";

            if (!sameAsBilling) {
                // When separate shipping address is enabled, Shipping Country comes ONLY from del-country-select
                shippingCountryFromForm = deliveryCountryVal ? deliveryCountryVal.trim() : "";
                decisionReason = "Separate shipping address active. Evaluated Shipping Country from #del-country-select ('" + shippingCountryFromForm + "'). Billing Country ('" + billingCountryVal + "') & Account Country ('" + accountCountry + "') IGNORED.";
            } else {
                // When same address is used, Shipping Country comes from country-select
                shippingCountryFromForm = billingCountryVal ? billingCountryVal.trim() : "";
                decisionReason = "Same address active. Evaluated Shipping Country from #country-select ('" + shippingCountryFromForm + "'). Account Country ('" + accountCountry + "') IGNORED.";
            }
            
            const normalizedShippingCode = normalizeCountryCode(shippingCountryFromForm);
            const isSriLanka = (normalizedShippingCode === 'LK');

            // Debug logs
            console.group("COD Gateway & Eligibility Debug");
            console.log("Shipping country received:", shippingCountryFromForm || "None");
            console.log("Billing country received:", billingCountryVal || "None");
            console.log("Customer account country:", accountCountry || "N/A");
            console.log("COD gateway status: ACTIVE & REGISTERED");
            console.log("COD enabled/disabled reason:", isSriLanka ? "Shipping country matches Sri Lanka -> COD AVAILABLE" : "Shipping country does not match Sri Lanka -> COD UNAVAILABLE");
            console.groupEnd();
            
            const codInput = codLabel.querySelector('input[type="radio"]');
            if (!codInput) return;

            if (isSriLanka) {
                // Enable COD
                codLabel.classList.remove('disabled');
                codLabel.style.cursor = '';
                codLabel.style.opacity = '';
                codLabel.style.borderColor = '';
                codInput.disabled = false;
                
                // Set click handler back
                codLabel.setAttribute('onclick', "selectMethod('pm-cod')");
                
                const blockedTag = codLabel.querySelector('.cod-blocked-tag');
                if (blockedTag) blockedTag.style.display = 'none';
                
                // Ensure COD is selected if no payment method is currently selected
                const anyChecked = document.querySelector('input[name="payment_method"]:checked');
                if (!anyChecked || anyChecked.disabled) {
                    selectMethod('pm-cod');
                }
                
            } else {
                // Disable COD
                codLabel.classList.add('disabled');
                codLabel.style.cursor = 'not-allowed';
                codLabel.style.opacity = '0.65';
                codLabel.style.borderColor = 'rgba(239, 68, 68, 0.15)';
                codInput.disabled = true;
                codLabel.removeAttribute('onclick');
                
                if (codInput.checked) {
                    codInput.checked = false;
                    codLabel.classList.remove('selected');
                    // Fallback to crypto if COD was selected
                    const cryptoMethod = document.getElementById('pm-crypto');
                    if (cryptoMethod) {
                        selectMethod('pm-crypto');
                    }
                }
                
                let blockedTag = codLabel.querySelector('.cod-blocked-tag');
                if (!blockedTag) {
                    blockedTag = document.createElement('span');
                    blockedTag.className = 'cod-blocked-tag';
                    blockedTag.style = 'position: absolute; top: 8px; right: 8px; font-size: 0.65rem; background: #ef4444; color: white; padding: 0.2rem 0.5rem; border-radius: 20px; font-weight: 700;';
                    blockedTag.innerText = 'Not in SL';
                    codLabel.appendChild(blockedTag);
                }
                blockedTag.style.display = 'block';
            }
        }

        function toggleDeliveryAddress() {
            const checkbox = document.getElementById('same-address-checkbox');
            const deliveryContainer = document.getElementById('delivery-address-container');
            const reqFields = ['del-first-name', 'del-last-name', 'del-phone', 'del-email', 'del-street-address', 'del-city', 'del-district', 'del-postcode'];
            
            if (checkbox && deliveryContainer) {
                if (checkbox.checked) {
                    deliveryContainer.style.display = 'none';
                    reqFields.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.required = false;
                    });
                } else {
                    deliveryContainer.style.display = 'block';
                    reqFields.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.required = true;
                    });
                }
                updateCODVisibility();
            }
        }

        function togglePasswordFields() {
            const checkbox = document.getElementById('create-account-checkbox');
            const passwordContainer = document.getElementById('password-field-container');
            const passwordInput = document.getElementById('signup-password');
            if (checkbox.checked) {
                passwordContainer.style.display = 'block';
                passwordInput.required = true;
            } else {
                passwordContainer.style.display = 'none';
                passwordInput.required = false;
            }
        }

        function selectMethod(methodId) {
            // Remove 'selected' class from all payment methods
            const methods = ['pm-cod', 'pm-crypto', 'pm-paypal', 'pm-bank'];
            methods.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.classList.remove('selected');
                    const radio = el.querySelector('input[type="radio"]');
                    if (radio && !radio.disabled) {
                        radio.checked = false;
                    }
                }
            });

            // Add 'selected' class to the clicked method and check its radio input
            const selectedEl = document.getElementById(methodId);
            if (selectedEl) {
                selectedEl.classList.add('selected');
                const radio = selectedEl.querySelector('input[type="radio"]');
                if (radio && !radio.disabled) {
                    radio.checked = true;
                }
            }

            // Show/hide pm-panel divs & disable inputs in hidden panels to prevent form submission conflict
            const panelMap = {
                'pm-cod':    'pm-panel-cod',
                'pm-crypto': 'pm-panel-crypto',
                'pm-paypal': 'pm-panel-paypal',
                'pm-bank':   'pm-panel-bank'
            };
            Object.values(panelMap).forEach(pid => {
                const p = document.getElementById(pid);
                if (p) {
                    p.classList.remove('visible');
                    p.style.display = 'none';
                    p.querySelectorAll('input[name="payment_slip"], input[name="transaction_id"]').forEach(inp => {
                        inp.disabled = true;
                    });
                }
            });
            const target = document.getElementById(panelMap[methodId]);
            if (target) {
                target.classList.add('visible');
                target.style.display = 'block';
                target.querySelectorAll('input[name="payment_slip"], input[name="transaction_id"]').forEach(inp => {
                    inp.disabled = false;
                });
            }
        }

        function handleFileSelect(input, displayId, zoneId) {
            const display = document.getElementById(displayId);
            const zone = document.getElementById(zoneId);
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                if (file.size > 10 * 1024 * 1024) {
                    alert('File too large. Maximum size is 10MB.');
                    input.value = '';
                    if (display) display.textContent = '';
                    return;
                }
                if (display) display.textContent = '✅ ' + file.name + ' (' + sizeMB + ' MB)';
                if (zone) { zone.style.borderColor = '#10b981'; zone.style.background = 'rgba(16,185,129,0.07)'; }
            }
        }

        function clickPageBackButton() {
            const step1 = document.getElementById('checkout-step-1-container');
            const step2 = document.getElementById('checkout-step-2-container');
            const step3 = document.getElementById('checkout-step-3-container');

            if (step3 && step3.style.display !== 'none') {
                goToStep(2);
            } else if (step2 && step2.style.display !== 'none') {
                goToStep(1);
            } else {
                // If on Step 1, go back to the cart page
                window.location.href = 'cart.php';
            }
        }

        </script>

    <footer style="text-align: center; padding: 2rem 0; margin-top: 3rem; border-top: 1px solid rgba(255, 94, 0, 0.08); color: var(--text-muted); font-size: 0.75rem;" class="no-print">
        <p>Developed By <a href="https://fusionwavesystems.com/" target="_blank" rel="noopener noreferrer" style="color: var(--text-main); font-weight: 600; text-decoration: none; border-bottom: 1px dashed var(--primary-glow);">Fusion Wave Systems (Pvt) Ltd.</a></p>
    </footer>
    <script src="assets/js/main.js?v=9"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
    <script>
        const phoneInputField = document.querySelector("#phone");
        const delPhoneInputField = document.querySelector("#del-phone");
        const itiOptions = {
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
            initialCountry: "lk",
            autoPlaceholder: "off",
            separateDialCode: true,
            preferredCountries: ["lk", "us", "gb", "au", "ca"]
        };
        
        const phoneInput = window.intlTelInput(phoneInputField, itiOptions);
        const delPhoneInput = window.intlTelInput(delPhoneInputField, itiOptions);
        
        // Update the hidden/underlying form value with the full international number before submitting
        document.querySelector("form").addEventListener("submit", function(e) {
            const formEl = this;
            if (formEl.dataset.submitting === 'true') {
                e.preventDefault();
                return false;
            }

            if (!phoneInput.isValidNumber()) {
                e.preventDefault();
                alert("Please enter a valid Phone Number.");
                phoneInputField.focus();
                return false;
            }
            phoneInputField.value = phoneInput.getNumber();
            
            const delPhoneContainer = document.querySelector('.delivery-details-section');
            if (delPhoneContainer && delPhoneContainer.style.display !== 'none' && delPhoneInputField.value.trim() !== '') {
                if (!delPhoneInput.isValidNumber()) {
                    e.preventDefault();
                    alert("Please enter a valid Shipping Phone Number.");
                    delPhoneInputField.focus();
                    return false;
                }
                delPhoneInputField.value = delPhoneInput.getNumber();
            }

            // Show instant loading state on Place Order button
            const btnSubmit = document.getElementById('btn-submit-order');
            if (btnSubmit) {
                formEl.dataset.submitting = 'true';
                btnSubmit.disabled = true;
                btnSubmit.style.opacity = '0.85';
                btnSubmit.style.cursor = 'wait';
                btnSubmit.innerHTML = '<span class="btn-spinner"></span> PLACING ORDER...';
            }
        });
        
        // Ensure goToStep validates the full number correctly
        const btnNextStep = document.getElementById('btn-next-step');
        if (btnNextStep) {
            btnNextStep.addEventListener('click', function(e) {
                if (phoneInputField.value && phoneInput.isValidNumber()) {
                    phoneInputField.value = phoneInput.getNumber();
                } else if (phoneInputField.value) {
                    phoneInputField.setCustomValidity("Please enter a valid phone number.");
                }
                
                if (!document.getElementById('same-address-checkbox').checked) {
                    if (delPhoneInputField.value && delPhoneInput.isValidNumber()) {
                        delPhoneInputField.value = delPhoneInput.getNumber();
                    } else if (delPhoneInputField.value) {
                        delPhoneInputField.setCustomValidity("Please enter a valid delivery phone number.");
                    }
                }
            });
            phoneInputField.addEventListener('input', function() {
                phoneInputField.setCustomValidity("");
            });
            delPhoneInputField.addEventListener('input', function() {
                delPhoneInputField.setCustomValidity("");
            });
        }
        
        // Unified Initialization for Country Select, Autofill & COD Visibility
        document.addEventListener("DOMContentLoaded", function() {
            // 1. Clone options to delivery country select
            const mainCountrySelect = document.getElementById("country-select");
            const delCountrySelect = document.getElementById("del-country-select");
            if (mainCountrySelect && delCountrySelect) {
                delCountrySelect.innerHTML = mainCountrySelect.innerHTML;
                for (let opt of delCountrySelect.options) {
                    opt.selected = (opt.value === '');
                }
                delCountrySelect.value = '';
            }

            // 2. Set default country from PHP on primary select if needed
            const defaultCountry = "<?php echo htmlspecialchars($default_country); ?>";
            if (defaultCountry && mainCountrySelect) {
                for (let option of mainCountrySelect.options) {
                    if (option.value.toLowerCase() === defaultCountry.toLowerCase() ||
                        (defaultCountry.toLowerCase() === 'sri lanka' && option.value === 'LK')) {
                        mainCountrySelect.value = option.value;
                        break;
                    }
                }
            }

            // 3. Initialize TomSelect on #country-select & #del-country-select
            if (mainCountrySelect) {
                new TomSelect("#country-select", {
                    create: false,
                    maxOptions: null,
                    placeholder: "Search for a country...",
                    onChange: function() {
                        if (typeof updateCODVisibility === 'function') updateCODVisibility();
                        if (typeof updateShippingFee === 'function') updateShippingFee();
                    }
                });
            }

            if (delCountrySelect) {
                new TomSelect("#del-country-select", {
                    create: false,
                    maxOptions: null,
                    placeholder: "Search for a country...",
                    onChange: function() {
                        if (typeof updateCODVisibility === 'function') updateCODVisibility();
                        if (typeof updateShippingFee === 'function') updateShippingFee();
                    }
                });
            }

            // 4. Load saved data from localStorage if present
            const autofillFields = ['first-name', 'last-name', 'street-address', 'street-address-2', 'city', 'district', 'postcode', 'email', 'phone'];
            const savedData = JSON.parse(localStorage.getItem('digiProCheckoutData') || '{}');
            autofillFields.forEach(id => {
                const el = document.getElementById(id);
                if (el && savedData[id]) {
                    if (el.tomselect) {
                        el.tomselect.setValue(savedData[id], true);
                    } else if (!el.value) {
                        el.value = savedData[id];
                    }
                }
            });

            // 5. Save data on form submit
            const checkoutForm = document.querySelector('form.checkout-layout');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function() {
                    const data = {};
                    autofillFields.forEach(id => {
                        const el = document.getElementById(id);
                        if (el) {
                            if (el.tomselect) {
                                data[id] = el.tomselect.getValue();
                            } else {
                                data[id] = el.value;
                            }
                        }
                    });
                    localStorage.setItem('digiProCheckoutData', JSON.stringify(data));
                });
            }

            // 6. Attach listeners to native change events
            if (mainCountrySelect) mainCountrySelect.addEventListener('change', updateCODVisibility);
            if (delCountrySelect) delCountrySelect.addEventListener('change', updateCODVisibility);
            
            const sameAddressCheckbox = document.getElementById('same-address-checkbox');
            if (sameAddressCheckbox) {
                sameAddressCheckbox.addEventListener('change', function() {
                    toggleDeliveryAddress();
                    updateCODVisibility();
                });
            }

            // 7. Initial payment method selection & immediate COD check
            selectMethod('pm-cod');

            if (typeof updateCODVisibility === 'function') {
                updateCODVisibility();
            }
        });
    </script>
</body>
</html>




