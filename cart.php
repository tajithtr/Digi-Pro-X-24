<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

$cart_items = $_SESSION['cart'] ?? [];
$cart_count = array_sum($cart_items);

$products = [];
$subtotal = 0;
$discount = 0;
$delivery_charge = 0;

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
        
        $product = $productsById[$p_id];
        $stock = (int)$product['stock'];
        if ($qty > $stock) {
            $qty = $stock;
            $_SESSION['cart'][$cart_key] = $qty; // Update session with capped value
        }
        
        $row = $product;
        $row['qty'] = $qty;
        $row['cart_key'] = $cart_key; // Save cart key to row
        
        $original_lkr = $row['price'];
        $discount_pct = $row['discount_percent'] ?? 0;
        $lkr_price = $original_lkr;
        
        // Flash sale check
        $is_flash_sale = false;
        if (!empty($row['flash_sale_price']) && !empty($row['flash_sale_start']) && !empty($row['flash_sale_end'])) {
            $now = new DateTime();
            $start = new DateTime($row['flash_sale_start']);
            $end = new DateTime($row['flash_sale_end']);
            if ($now >= $start && $now < $end) {
                $is_flash_sale = true;
                $lkr_price = $row['flash_sale_price'];
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
        
        if (!empty($variant_img)) {
            $row['image'] = $variant_img;
        }
        if (!empty($variant_names)) {
            $row['name'] .= ' (' . implode(', ', $variant_names) . ')';
        }
        
        $item_shipping = isset($row['shipping_fee']) ? (float)$row['shipping_fee'] : 450.00;
        $delivery_charge += $item_shipping * $qty;
        
        $row['lkr_price'] = $lkr_price;
        $row['total'] = $lkr_price * $qty;
        $subtotal += $row['total'];
        $products[] = $row;
    }
}

$discount_msg = '';
if (isset($_POST['discount_code'])) {
    $discount_code = trim($_POST['discount_code']);
    if ($discount_code === 'NEON10' || $discount_code === 'FREE500') {
        $_SESSION['discount_code'] = $discount_code;
    } elseif (empty($discount_code)) {
        unset($_SESSION['discount_code']);
    } else {
        $discount_msg = '❌ Invalid discount code.';
        unset($_SESSION['discount_code']);
    }
}

$discount_code = $_SESSION['discount_code'] ?? '';
$discount = 0;

if ($discount_code === 'NEON10') {
    $discount = $subtotal * 0.10;
    if (empty($discount_msg)) $discount_msg = '✨ 10% Discount Applied!';
} elseif ($discount_code === 'FREE500') {
    $discount = 500;
    if (empty($discount_msg)) $discount_msg = '✨ Rs. 500 Off Applied!';
}

$delivery_charge = !empty($cart_items) ? $delivery_charge : 0;
$_SESSION['shipping_fee'] = $delivery_charge;
$total = $subtotal - $discount + $delivery_charge;
if ($total < 0) $total = 0;

$_SESSION['checkout_total'] = $total;

$user_shipping_fee = null;
$user_country_name = '';
$has_user_shipping = false;
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_country'])) {
    $raw_country = trim($_SESSION['user_country']);
    $normalized_code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $raw_country));
    if ($normalized_code === 'lk' || $normalized_code === 'lka' || $normalized_code === 'srilanka' || $normalized_code === 'sl') {
        $normalized_code = 'LK';
        $user_country_name = 'Sri Lanka';
    } else {
        $normalized_code = strtoupper($normalized_code);
        $country_map = [
            'US' => 'United States', 'GB' => 'United Kingdom', 'AU' => 'Australia', 'CA' => 'Canada',
            'LK' => 'Sri Lanka', 'IN' => 'India', 'AE' => 'United Arab Emirates', 'SG' => 'Singapore',
            'NZ' => 'New Zealand', 'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy',
            'ES' => 'Spain', 'NL' => 'Netherlands', 'SE' => 'Sweden', 'CH' => 'Switzerland',
            'JP' => 'Japan', 'ZA' => 'South Africa', 'MY' => 'Malaysia', 'MV' => 'Maldives',
            'QA' => 'Qatar', 'SA' => 'Saudi Arabia', 'AF' => 'Afghanistan', 'AL' => 'Albania',
            'DZ' => 'Algeria', 'AD' => 'Andorra', 'AO' => 'Angola', 'AG' => 'Antigua & Barbuda',
            'AR' => 'Argentina', 'AM' => 'Armenia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados',
            'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin',
            'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia & Herzegovina', 'BW' => 'Botswana',
            'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
            'BI' => 'Burundi', 'CV' => 'Cape Verde', 'KH' => 'Cambodia', 'CM' => 'Cameroon',
            'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
            'KM' => 'Comoros', 'CG' => 'Congo - Brazzaville', 'CD' => 'Congo - Kinshasa', 'CR' => 'Costa Rica',
            'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czechia',
            'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
            'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia',
            'FJ' => 'Fiji', 'FI' => 'Finland', 'GA' => 'Gabon', 'GM' => 'Gambia',
            'GE' => 'Georgia', 'GH' => 'Ghana', 'GR' => 'Greece', 'GD' => 'Grenada',
            'GT' => 'Guatemala', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana',
            'HT' => 'Haiti', 'HN' => 'Honduras', 'HU' => 'Hungary', 'IS' => 'Iceland',
            'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland',
            'IL' => 'Israel', 'JM' => 'Jamaica', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan',
            'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'North Korea', 'KR' => 'South Korea',
            'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia',
            'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya',
            'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MG' => 'Madagascar',
            'MW' => 'Malawi', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands',
            'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia',
            'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
            'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
            'NR' => 'Nauru', 'NP' => 'Nepal', 'NI' => 'Nicaragua', 'NE' => 'Niger',
            'NG' => 'Nigeria', 'MK' => 'North Macedonia', 'NO' => 'Norway', 'OM' => 'Oman',
            'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama',
            'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
            'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'RU' => 'Russia',
            'RW' => 'Rwanda', 'KN' => 'St. Kitts & Nevis', 'LC' => 'St. Lucia', 'VC' => 'St. Vincent & Grenadines',
            'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome & Principe', 'SN' => 'Senegal',
            'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SK' => 'Slovakia',
            'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'SD' => 'Sudan',
            'SR' => 'Suriname', 'SY' => 'Syria', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo',
            'TO' => 'Tonga', 'TT' => 'Trinidad & Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
            'TM' => 'Turkmenistan', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
            'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City',
            'VE' => 'Venezuela', 'VN' => 'Vietnam', 'YE' => 'Yemen', 'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        ];
        $user_country_name = $country_map[$normalized_code] ?? $normalized_code;
    }
    try {
        $sum_user_shipping_fee = 0.00;
        $has_user_shipping = !empty($products);
        
        $rateStmt = $pdo->prepare("SELECT fee FROM product_shipping_rates WHERE product_id = ? AND country_code = ? LIMIT 1");
        foreach ($products as $p) {
            $rateStmt->execute([$p['id'], $normalized_code]);
            $fee_val = $rateStmt->fetchColumn();
            if ($fee_val !== false) {
                $item_shipping = (float)$fee_val;
            } else {
                $item_shipping = isset($p['shipping_fee']) ? (float)$p['shipping_fee'] : 450.00;
            }
            $sum_user_shipping_fee += $item_shipping * $p['qty'];
        }
        $user_shipping_fee = $sum_user_shipping_fee;
        $delivery_charge = $user_shipping_fee;
        $_SESSION['shipping_fee'] = $delivery_charge;
        $total = $subtotal - $discount + $delivery_charge;
        if ($total < 0) $total = 0;
        $_SESSION['checkout_total'] = $total;
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <title>Your Cart - Digi Pro X 24</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .cart-page { padding: 120px 5% 5rem; min-height: 80vh; max-width: 1400px; margin: 0 auto; position: relative; z-index: 1; }
        
        /* Stepper */
        .checkout-stepper { display: flex; justify-content: center; align-items: center; margin-bottom: 4rem; gap: 1rem; }
        .step { display: flex; align-items: center; gap: 0.5rem; color: var(--text-muted); font-weight: 600; font-size: 1.1rem; }
        .step.active { color: var(--primary-glow); }
        .step-num { width: 30px; height: 30px; border-radius: 50%; background: rgba(255,94,0,0.06); border: 1px solid rgba(255,94,0,0.1); display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
        .step.active .step-num { background: var(--primary-glow); color: white; box-shadow: 0 4px 10px rgba(255,94,0,0.25); border-color: var(--primary-glow); }
        .step-line { width: 50px; height: 2px; background: rgba(255,94,0,0.1); }
        
        .cart-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 3rem; align-items: start; }
        
        .cart-items-container { display: flex; flex-direction: column; gap: 1.5rem; }
        .cart-item-card { display: flex; align-items: center; gap: 1.5rem; padding: 1.25rem; border-radius: 16px; transition: 0.3s; background: rgba(13, 16, 21, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 94, 0, 0.12); color: var(--text-main); }
        .cart-item-card:hover { border-color: var(--primary-glow); box-shadow: 0 4px 15px rgba(255, 94, 0, 0.08); transform: translateY(-2px); }
        .cart-item-img { width: 100px; height: 100px; border-radius: 12px; object-fit: cover; border: 1px solid rgba(255, 94, 0, 0.2); }
        .cart-item-info { flex: 1; }
        .cart-item-title { font-size: 1.15rem; font-weight: 700; margin-bottom: 0.4rem; color: #ffffff; }
        .cart-item-price { color: var(--text-muted); font-size: 1rem; font-weight: 500; }
        
        .qty-controls { display: flex; align-items: center; gap: 0.2rem; background: rgba(13, 16, 21, 0.4); padding: 0.2rem; border-radius: 50px; border: 1px solid rgba(255, 94, 0, 0.2); }
        .qty-btn { background: transparent; border: none; color: var(--text-main); width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; transition: 0.2s; font-weight: 500; }
        .qty-btn:hover { background: rgba(255, 94, 0, 0.1); color: var(--primary-glow); }
        .qty-input { width: 40px; text-align: center; background: transparent; border: none; color: var(--text-main); font-weight: 700; font-size: 1.05rem; -moz-appearance: textfield; }
        .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        
        .item-total { font-size: 1.15rem; font-weight: 800; color: var(--primary-glow); width: 130px; text-align: right; }
        
        .btn-remove { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); width: 38px; height: 38px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .btn-remove:hover { background: #ef4444; color: #ffffff; border-color: #ef4444; transform: scale(1.05); }
        
        .cart-summary { position: sticky; top: 120px; padding: 2rem; border-radius: 20px; background: rgba(13, 16, 21, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 94, 0, 0.12); box-shadow: 0 10px 30px rgba(0,0,0,0.5); color: var(--text-main); }
        .cart-summary h2 { font-size: 1.4rem; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255, 94, 0, 0.1); padding-bottom: 1rem; color: #ffffff; font-weight: 800; border-left: 3px solid var(--primary-glow); padding-left: 12px; text-transform: uppercase; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 1rem; color: var(--text-muted); font-weight: 500; }
        .summary-total { font-size: 1.4rem; font-weight: 800; color: #ffffff; margin-top: 1.2rem; border-top: 1px solid rgba(255, 94, 0, 0.1); padding-top: 1.2rem; }
        
        .discount-form { display: flex; gap: 0.5rem; margin: 1.5rem 0; }
        .discount-input { flex: 1; padding: 0.75rem 1rem; border-radius: 50px; background: rgba(13, 16, 21, 0.4); border: 1px solid rgba(255, 94, 0, 0.2); color: var(--text-main); font-family: var(--font-family); outline: none; transition: 0.2s; font-size: 0.95rem; }
        .discount-input:focus { border-color: var(--primary-glow); box-shadow: 0 0 10px rgba(255, 94, 0, 0.2); background: rgba(13, 16, 21, 0.7); }
        .btn-apply-discount { background: rgba(255, 94, 0, 0.1); color: var(--primary-glow); border: 1px solid var(--primary-glow); border-radius: 50px; padding: 0 1.5rem; font-weight: 700; cursor: pointer; transition: 0.2s; }
        .btn-apply-discount:hover { background: var(--primary-glow); color: #000000; box-shadow: 0 0 15px rgba(255, 94, 0, 0.4); }
        
        .btn-checkout { display: block; width: 100%; text-align: center; padding: 1rem; font-size: 1.15rem; margin-top: 2rem; border-radius: 50px; font-weight: 800; background: var(--primary-glow); color: #000000; text-decoration: none; transition: 0.2s; border: none; text-transform: uppercase; letter-spacing: 1px; }
        .btn-checkout:hover { background: var(--secondary-glow); box-shadow: 0 0 20px rgba(255, 94, 0, 0.4); transform: translateY(-1px); }
        
        @media(max-width: 900px) { 
            .cart-layout { grid-template-columns: 1fr; }
            .cart-item-card { flex-wrap: wrap; gap: 1rem; }
            .item-total { text-align: left; width: auto; flex: 1; }
        }
        
        .cart-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, #090a0f 0%, #050608 100%);
            z-index: -5;
        }
    </style>
</head>
<body>
    <div class="cart-bg"></div>
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
            <a href="index.php" class="btn-secondary" style="padding: 0.5rem 1.5rem; margin-right: 0.5rem;">Continue Shopping</a>
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

    <main class="cart-page">
        <!-- Back Button -->
        <div style="margin-bottom: 2rem;">
            <a href="products.php" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; color: var(--primary-glow); font-weight: 600; font-size: 1.1rem; padding: 0.5rem 1rem; border-radius: 8px; background: rgba(255,94,0,0.05); border: 1px solid rgba(255,94,0,0.1); transition: 0.3s;" onmouseover="this.style.background='rgba(255,94,0,0.1)';" onmouseout="this.style.background='rgba(255,94,0,0.05)';">
                <span style="font-size: 1.2rem;">&larr;</span> Back
            </a>
        </div>
        
        <div class="checkout-stepper">
            <div class="step active"><div class="step-num">1</div> Shopping Cart</div>
            <div class="step-line"></div>
            <div class="step"><div class="step-num">2</div> Checkout</div>
            <div class="step-line"></div>
            <div class="step"><div class="step-num">3</div> Order Complete</div>
        </div>
        
        <?php if(empty($products)): ?>
            <div class="glass-panel" style="padding: 5rem; text-align: center; border-radius: 24px; max-width: 600px; margin: 0 auto; background: rgba(13, 16, 21, 0.75); backdrop-filter: blur(10px); border: 1px solid rgba(255,94,0,0.12); box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                <div style="font-size: 5rem; margin-bottom: 1rem;">🛒</div>
                <h2 style="font-size: 2rem; margin-bottom: 1rem; color:var(--text-main);">Your cart is empty</h2>
                <p style="margin-bottom: 2.5rem; color: var(--text-muted); font-size: 1.2rem;">Looks like you haven't added any electricals or tech to your cart yet. Discover high-quality products today!</p>
                <a href="index.php" class="btn-primary neon-glow" style="padding: 1rem 3rem; font-size: 1.2rem;">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items-container">
                    <?php foreach($products as $p): ?>
                        <div class="cart-item-card glass-panel" data-id="<?php echo htmlspecialchars($p['cart_key']); ?>">
                            <img src="<?php echo htmlspecialchars($p['image']); ?>" class="cart-item-img" alt="<?php echo htmlspecialchars($p['name']); ?>">
                            <div class="cart-item-info">
                                <div class="cart-item-title"><?php echo htmlspecialchars($p['name']); ?></div>
                                <div class="cart-item-price">Rs. <?php echo number_format($p['lkr_price'], 2); ?> each</div>
                                <?php if ($p['stock'] <= 0): ?>
                                    <div style="background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; width: max-content; margin-top: 0.5rem;">Out of Stock</div>
                                <?php elseif ($p['qty'] >= $p['stock']): ?>
                                    <div style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.75rem; font-weight: 700; width: max-content; margin-top: 0.5rem;">Maximum stock reached (<?php echo $p['stock']; ?> available)</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="qty-controls">
                                <button class="qty-btn" onclick="updateQty(this, -1)">-</button>
                                <input type="number" class="qty-input" value="<?php echo $p['qty']; ?>" readonly>
                                <button class="qty-btn" onclick="updateQty(this, 1)">+</button>
                            </div>
                            
                            <div class="item-total">
                                Rs. <?php echo number_format($p['total'], 2); ?>
                            </div>
                            
                            <button class="btn-remove" title="Remove Item" onclick="removeItem(this)">
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-summary glass-panel">
                    <h2>Order Summary</h2>
                    
                    <div class="summary-row">
                        <span>Items (<?php echo $cart_count; ?>)</span>
                        <span style="color: white;">Rs. <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <?php if($discount > 0): ?>
                    <div class="summary-row" style="color: var(--primary-glow); font-weight: 600;">
                        <span>Discount Savings</span>
                        <span>- Rs. <?php echo number_format($discount, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="summary-row" style="font-weight: 600; margin-top: 1.5rem;">
                        <span>Delivery Fee</span>
                        <span style="font-size: 0.9rem; color: var(--text-muted);">Calculated at Checkout</span>
                    </div>
                    <?php if (isset($_SESSION['user_id']) && isset($has_user_shipping) && $has_user_shipping): ?>
                    <div class="shipping-fee-banner" style="display: flex; align-items: center; gap: 0.9rem; background: rgba(16, 185, 129, 0.08); border: 1.5px solid rgba(16, 185, 129, 0.3); padding: 0.75rem 1.2rem; border-radius: 14px; margin-top: 0.5rem; margin-bottom: 1rem;">
                        <div style="font-size: 1.6rem;">💵</div>
                        <div>
                            <div style="font-weight: 800; font-size: 0.92rem; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">
                                Delivery Fee: <?php echo $user_shipping_fee == 0 ? 'Free' : 'Rs. ' . number_format($user_shipping_fee, 2); ?>
                            </div>
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.7); font-weight: 600;">
                                Shipping to <?php echo htmlspecialchars($user_country_name); ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div style="font-size: 0.85rem; color: var(--primary-glow); background: rgba(255, 94, 0, 0.08); padding: 0.8rem; border-radius: 8px; border: 1px dashed rgba(255, 94, 0, 0.3); margin-top: -0.5rem; margin-bottom: 1rem; line-height: 1.5; font-weight: 500;">
                        💡 <strong>Note:</strong> Selecting or changing your shipping country on the checkout page will automatically update and calculate your final delivery fee.
                    </div>


                    <div class="summary-row" style="font-size: 0.85rem; color: #60a5fa; background: rgba(59, 130, 246, 0.1); padding: 0.6rem 0.8rem; border-radius: 10px; margin: 0.8rem 0; border: 1px solid rgba(59, 130, 246, 0.25); display: flex; justify-content: space-between; align-items: center;">
                        <span>🚚 Estimated Delivery</span>
                        <span style="font-weight: 800;">3 - 7 Business Days</span>
                    </div>

                    <div class="summary-row summary-total">
                        <span>Subtotal</span>
                        <span style="color: var(--primary-glow);">Rs. <?php echo number_format($total, 2); ?></span>
                    </div>

                    <a href="checkout.php" class="btn-primary btn-checkout neon-glow">Proceed to Checkout →</a>
                    <div style="text-align: center; margin-top: 1.5rem; color: var(--text-muted); font-size: 0.9rem;">
                        🔒 Secure 256-bit SSL Encryption
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
    function updateQty(btn, change) {
        const row = btn.closest('.cart-item-card');
        const id = row.getAttribute('data-id');
        const input = row.querySelector('.qty-input');
        let newQty = parseInt(input.value) + change;
        if(newQty < 1) newQty = 1;
        
        fetch('cart_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update&product_id=${id}&qty=${newQty}`
        }).then(() => location.reload());
    }

    function removeItem(btn) {
        const row = btn.closest('.cart-item-card');
        const id = row.getAttribute('data-id');
        
        row.style.opacity = '0';
        row.style.transform = 'scale(0.9)';
        row.style.transition = '0.3s';
        
        setTimeout(() => {
            fetch('cart_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=remove&product_id=${id}`
            }).then(res => res.json())
              .then(data => {
                  if(data.success) {
                      location.reload();
                  } else {
                      alert('Failed to remove item');
                      row.style.opacity = '1';
                      row.style.transform = 'scale(1)';
                  }
              });
        }, 300);
    }
    </script>

    <footer style="text-align: center; padding: 2rem 0; margin-top: 3rem; border-top: 1px solid rgba(255, 94, 0, 0.08); color: var(--text-muted); font-size: 0.75rem;" class="no-print">
        <p>Developed By <a href="https://fusionwavesystems.com/" target="_blank" rel="noopener noreferrer" style="color: var(--text-main); font-weight: 600; text-decoration: none; border-bottom: 1px dashed var(--primary-glow);">Fusion Wave Systems (Pvt) Ltd.</a></p>
    </footer>
    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
</body>
</html>
