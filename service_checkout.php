<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'config.php';

$user_country = strtolower(trim($_SESSION['user_country'] ?? ''));
$is_sri_lanka = ($user_country === 'sri lanka' || $user_country === 'lk' || $user_country === 'srilanka' || $user_country === 'sl');
if (!$is_sri_lanka) {
    header("Location: index.php");
    exit;
}

$service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
if ($service_id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$stmtU = $pdo->prepare("SELECT name, first_name, last_name, phone, address, address_line_1, city FROM users WHERE id = ?");
$stmtU->execute([$user_id]);
$user = $stmtU->fetch(PDO::FETCH_ASSOC);

$name_parts = explode(' ', $user['name'] ?? '', 2);
$default_first_name = trim($user['first_name'] ?? ($name_parts[0] ?? ''));
$default_last_name = trim($user['last_name'] ?? ($name_parts[1] ?? ''));
$default_phone = trim($user['phone'] ?? '');

$raw_address = trim($user['address_line_1'] ?? ($user['address'] ?? ''));
if (strpos($raw_address, 'No.161') !== false) {
    $raw_address = '';
}
$default_address = $raw_address;

$default_city = trim($user['city'] ?? '');
if ($default_city === 'Galle' && empty($default_address)) {
    $default_city = '';
}

$success = false;
$token_number = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $customer_name = $first_name . ' ' . $last_name;
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $customer_note = trim($_POST['customer_note'] ?? '');

    // Update ONLY address & city in users table (preserve signup fields: name, phone, email, country)
    if (!empty($address)) {
        $upStmt = $pdo->prepare("UPDATE users SET address = ?, address_line_1 = ?, city = ? WHERE id = ?");
        $upStmt->execute([$address, $address, $city, $user_id]);
    }

    $location_address = trim($address . ', ' . $city);
    if (empty($location_address) || $location_address === ',') {
        $location_address = "No address provided.";
    }

    $token_number = '#SR-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("INSERT INTO service_requests (user_id, token_number, customer_name, phone_number, location_address, customer_note, service_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $token_number, $customer_name, $phone, $location_address, $customer_note, $service_id]);

    $wa_text = "New Service Request 🛠️\n\n";
    $wa_text .= "Token: " . $token_number . "\n";
    $wa_text .= "Service: " . $service['name'] . "\n";
    $wa_text .= "Customer: " . $customer_name . "\n";
    $wa_text .= "Phone: " . $phone . "\n";
    $wa_text .= "Address: " . $location_address . "\n";
    $wa_text .= "Note: " . $customer_note;
    $wa_url = "https://wa.me/94706756006?text=" . urlencode($wa_text);

    $success = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <title>Service Request - Digi Pro X 24</title>
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
        
        /* Stepper */
        .checkout-stepper { display: flex; justify-content: center; align-items: center; margin-bottom: 3rem; gap: 1.5rem; }
        .step { display: flex; align-items: center; gap: 0.6rem; color: var(--text-muted); font-weight: 700; font-size: 0.92rem; text-transform: uppercase; letter-spacing: 1px; }
        .step.completed { color: #10b981; }
        .step.active { color: var(--primary-glow); }
        .step-num { width: 26px; height: 26px; border-radius: 50%; background: transparent; border: 2px solid rgba(255, 94, 0, 0.2); display: flex; align-items: center; justify-content: center; transition: 0.3s; color: var(--text-muted); font-size: 0.85rem; font-weight: 800; }
        .step.completed .step-num { background: rgba(16,185,129,0.1); color: #10b981; border-color: #10b981; }
        .step.active .step-num { background: var(--primary-glow); color: #000000; border-color: var(--primary-glow); box-shadow: 0 4px 15px rgba(255, 94, 0, 0.4); }
        .step-line { width: 40px; height: 2px; background: rgba(255, 94, 0, 0.2); }
        .step.completed + .step-line { background: #10b981; }        .checkout-layout { display: grid; grid-template-columns: 1fr; max-width: 800px; margin: 0 auto; gap: 2rem; align-items: start; }
        
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
        }
        .receipt-header h2 span { 
            color: var(--primary-glow); 
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
        }
 
        .checkout-bg {
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
        <?php if ($success): ?>
            <div class="checkout-stepper">
                <div class="step completed"><div class="step-num">✓</div> Contact Details</div>
                <div class="step-line"></div>
                <div class="step active"><div class="step-num">2</div> Request Complete</div>
            </div>

            <div class="success-box">
                <div class="no-print">
                    <div class="success-icon">✨</div>
                    <h1>Service Request Sent!</h1>
                    <p style="color: var(--text-muted); font-size: 1.2rem; line-height: 1.6; max-width: 500px; margin: 0 auto;">Our technician will contact you shortly to schedule your appointment.</p>
                </div>

                <div class="receipt-card" style="margin-top: 2rem;">
                    <div class="receipt-header">
                        <div>
                            <h2 style="margin-bottom: 0.5rem;">Token Number</h2>
                            <p style="color: var(--text-muted); font-size: 0.9rem;">Keep this token for your records</p>
                        </div>
                    </div>
                    
                    <div style="text-align: center; margin: 2rem 0; padding: 2rem; background: rgba(16, 185, 129, 0.1); border: 2px dashed #10b981; border-radius: 12px;">
                        <div style="font-size: 2.5rem; font-weight: 800; color: #10b981; letter-spacing: 2px;">
                            <?php echo htmlspecialchars($token_number); ?>
                        </div>
                    </div>
                    
                    <div class="receipt-footer">
                        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Payment will be collected in-person after the service is completed.</p>
                        <div style="display: flex; flex-direction: column; gap: 1rem; align-items: stretch; width: fit-content; margin: 0 auto;" class="no-print">
                            <a href="<?php echo htmlspecialchars($wa_url ?? ''); ?>" target="_blank" class="btn-primary neon-glow" style="background: #25D366; border-color: #25D366; box-shadow: 0 0 15px rgba(37,211,102,0.4); display: flex; align-items: center; justify-content: center; gap: 0.5rem; text-decoration: none;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="white" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12.031 0C5.385 0 0 5.383 0 12.03C0 14.15 0.553 16.19 1.558 17.96L0.007 24L6.177 22.38C7.883 23.29 9.882 23.82 11.989 23.82C18.636 23.82 24.02 18.438 24.02 11.791C24.02 8.563 22.766 5.56 20.485 3.279C18.204 0.999 15.201 0 12.031 0ZM12.031 21.821C10.218 21.821 8.497 21.336 6.993 20.446L6.64 20.237L3.064 21.173L4.015 17.689L3.785 17.323C2.803 15.759 2.274 13.931 2.274 12.03C2.274 6.644 6.657 2.261 12.043 2.261C14.654 2.261 17.11 3.277 18.955 5.123C20.8 6.969 21.815 9.426 21.815 12.038C21.815 17.424 17.432 21.821 12.031 21.821ZM17.4 14.526C17.106 14.379 15.666 13.67 15.399 13.573C15.132 13.475 14.939 13.426 14.743 13.721C14.547 14.015 14 14.653 13.827 14.849C13.654 15.045 13.478 15.07 13.184 14.923C12.89 14.776 11.947 14.47 10.835 13.483C10.513 13.189 10.278 13.255 9.946 13.483C9.615 13.71 9.15 14.07 9.15 14.07C9.15 14.07 8.976 14.237 8.423 13.943C7.87 13.65 6.786 12.695 6.048 11.464C5.31 10.233 6.048 9.458 6.048 9.458C6.048 9.458 6.331 9.074 6.551 8.781C6.772 8.487 6.845 8.274 6.993 8.012C7.14 7.75 7.079 7.424 6.932 7.151C6.786 6.878 6.048 5.123 5.754 4.417C5.46 3.712 5.166 3.844 4.946 3.828C4.726 3.811 4.431 3.811 4.137 3.811C3.843 3.811 3.364 3.925 2.996 4.341C2.628 4.757 1.558 5.767 1.558 7.828C1.558 9.889 3.033 11.884 3.217 12.13C3.401 12.377 6.048 16.634 10.457 18.361C11.506 18.771 12.327 19.016 12.984 19.208C14.043 19.54 14.994 19.49 15.75 19.349C16.592 19.192 18.347 18.232 18.716 17.185C19.085 16.139 19.085 15.239 18.955 15.045C18.825 14.851 18.531 14.754 18.237 14.607L17.4 14.526Z"/>
                                </svg>
                                Send Details via WhatsApp
                            </a>
                            <a href="index.php" class="btn-primary no-print" style="text-decoration: none; display: flex; align-items: center; justify-content: center;">Return to Home</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>



        <form action="" method="POST" class="checkout-layout" id="service-form" novalidate>
            <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">
            <div class="checkout-forms">
                <div id="checkout-step-1-container">
                    <div class="form-section glass-panel">
                        <h2><span>1</span> Contact & Location Details</h2>
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
                                <input type="tel" name="phone" id="phone" class="form-input" value="<?php echo htmlspecialchars($default_phone); ?>" placeholder="0712345678" required>
                            </div>
                            <div class="form-group">
                                <label>City / Region</label>
                                <input type="text" name="city" id="city" class="form-input" placeholder="Galle" value="<?php echo htmlspecialchars($default_city); ?>" required>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Full Address</label>
                                <input type="text" name="address" id="address" class="form-input" placeholder="123 Main St, Appt 4B" value="<?php echo htmlspecialchars($default_address); ?>" required>
                            </div>
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Problem Description / Note for Technician</label>
                                <textarea name="customer_note" id="customer-note" class="form-input" rows="4" placeholder="Describe the issue or provide any special instructions..." style="resize:vertical;" required></textarea>
                            </div>
                            </div>
                        </div>
                        <div class="step-nav-bar" style="margin-top: 2rem;">
                            <a href="javascript:void(0)" onclick="window.history.back();" class="btn-step-back">
                                ⬅ Back
                            </a>
                            <button type="submit" id="btn-submit-order-bottom" class="btn-step-next" style="background: linear-gradient(135deg, #10b981, #059669); border: none; color: white;">
                                Submit Request 🛠️
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-summary-box glass-panel">
                <h2 style="margin-bottom: 1.5rem; font-size: 1.25rem;">Service Summary</h2>
                <div class="summary-items">
                    <div class="summary-item" style="display: flex; gap: 1rem; align-items: center;">
                        <div class="item-image" style="width: 60px; height: 60px; border-radius: 8px; overflow: hidden; background: #fff; flex-shrink: 0;">
                            <img src="<?php echo htmlspecialchars($service['image_url'] ?? 'assets/images/default.jpg'); ?>" alt="<?php echo htmlspecialchars($service['name'] ?? 'Technical Service'); ?>" style="width: 100%; height: 100%; object-fit: contain;">
                        </div>
                        <div class="item-details" style="flex: 1;">
                            <div class="item-name" style="font-weight: 800; color: white;"><?php echo htmlspecialchars($service['name']); ?></div>
                            <div class="item-variant" style="color: #10b981; font-size: 0.85rem; margin-top: 0.2rem;">Technical Service Request</div>
                        </div>
                    </div>
                </div>
                
                <div class="summary-row total-row" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; font-weight: 800; font-size: 1.2rem;">
                    <span style="color: white;">Base Service Fee</span>
                    <span style="color: var(--primary-glow);">Rs. <?php echo number_format($service['price'], 2); ?></span>
                </div>
                
                <div style="margin-top:1.5rem; padding: 1rem; background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; border-radius: 8px;">
                    <p style="font-size: 0.85rem; color: #94a3b8; line-height: 1.5; margin:0;">
                        Payment is collected in-person after the service is completed by our technician.
                    </p>
                </div>

                <div id="checkout-error-msg" style="color: #ef4444; font-size: 0.85rem; font-weight: 700; margin-top: 0.8rem; text-align: center; display: none;"></div>
            </div>
        </form>
        <?php endif; ?>


    </main>
    <script>
        const form = document.getElementById('service-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (form.dataset.submitting === 'true') {
                    e.preventDefault();
                    return false;
                }
                const reqs = form.querySelectorAll('[required]');
                let valid = true;
                for(let r of reqs) {
                    if(!r.value.trim()) {
                        valid = false;
                        r.style.borderColor = '#ef4444';
                    } else {
                        r.style.borderColor = 'rgba(255,255,255,0.1)';
                    }
                }
                if(!valid) {
                    e.preventDefault();
                    const err = document.getElementById('checkout-error-msg');
                    err.textContent = 'Please fill out all required fields.';
                    err.style.display = 'block';
                } else {
                    const btn = document.getElementById('btn-submit-order-bottom');
                    if (btn) {
                        form.dataset.submitting = 'true';
                        btn.disabled = true;
                        btn.style.opacity = '0.85';
                        btn.style.cursor = 'wait';
                        btn.innerHTML = '<span class="btn-spinner"></span> SUBMITTING...';
                    }
                }
            });
        }
    </script>
</body>
</html>
