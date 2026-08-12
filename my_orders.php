<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// Fetch all orders for this user
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all service requests for this user
$stmtSR = $pdo->prepare("SELECT sr.*, p.name as service_name, p.image FROM service_requests sr LEFT JOIN products p ON sr.service_id = p.id WHERE sr.user_id = ? ORDER BY sr.id DESC");
$stmtSR->execute([$user_id]);
$service_requests = $stmtSR->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <title>My Orders | Digi Pro X 24</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .orders-page {
            padding: 120px 5% 5rem;
            min-height: 80vh;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .page-header-row {
            margin-bottom: 3rem;
            text-align: center;
        }

        .page-header-row h1 {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--text-main);
        }
<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cart_count = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;

// Fetch all orders for this user
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all service requests for this user
$stmtSR = $pdo->prepare("SELECT sr.*, p.name as service_name, p.image FROM service_requests sr LEFT JOIN products p ON sr.service_id = p.id WHERE sr.user_id = ? ORDER BY sr.id DESC");
$stmtSR->execute([$user_id]);
$service_requests = $stmtSR->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <title>My Orders | Digi Pro X 24</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .orders-page {
            padding: 120px 5% 5rem;
            min-height: 80vh;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .page-header-row {
            margin-bottom: 3rem;
            text-align: center;
        }

        .page-header-row h1 {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .page-header-row h1 span {
            background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .order-card {
            background: rgba(13, 16, 21, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 94, 0, 0.18);
            border-radius: 24px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            transition: all 0.3s ease;
        }

        .order-card:hover {
            border-color: rgba(255, 94, 0, 0.35);
            transform: translateY(-3px);
            box-shadow: 0 15px 45px rgba(255, 94, 0, 0.12);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dashed rgba(255, 94, 0, 0.18);
            padding-bottom: 1.25rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .order-meta-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem 2.5rem;
            flex: 1;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .meta-val {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-main);
        }

        .order-id-highlight {
            color: var(--primary-glow);
            font-family: monospace;
            font-size: 1.1rem;
        }

        .order-status {
            padding: 0.45rem 1.3rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .status-completed,
        .status-delivered {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.35);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.15);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.35);
            box-shadow: 0 0 15px rgba(245, 158, 11, 0.15);
        }

        .status-processing {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.35);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.15);
        }

        .status-shipped {
            background: rgba(168, 85, 247, 0.15);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.35);
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.15);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.35);
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.15);
        }

        /* Order Tracker Steps Bar */
        .order-tracker-box {
            background: rgba(2, 6, 23, 0.7);
            border: 1px solid rgba(255, 94, 0, 0.2);
            border-radius: 18px;
            padding: 1.25rem 1.5rem 1.4rem 1.5rem;
            margin: 1.25rem 0 1.6rem 0;
            box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.4);
        }

        .tracker-steps {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .tracker-step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            z-index: 2;
            flex: 1;
        }

        .tracker-icon-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(15, 23, 42, 0.95);
            border: 2px solid rgba(255, 255, 255, 0.15);
            color: rgba(255, 255, 255, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            transition: all 0.3s ease;
        }

        .tracker-step-item.step-1.active .tracker-icon-circle {
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.25);
            color: #ffffff;
            box-shadow: 0 0 18px rgba(245, 158, 11, 0.5);
        }

        .tracker-step-item.step-2.active .tracker-icon-circle {
            border-color: #3b82f6;
            background: rgba(59, 130, 246, 0.25);
            color: #ffffff;
            box-shadow: 0 0 18px rgba(59, 130, 246, 0.5);
        }

        .tracker-step-item.step-3.active .tracker-icon-circle {
            border-color: #a855f7;
            background: rgba(168, 85, 247, 0.25);
            color: #ffffff;
            box-shadow: 0 0 18px rgba(168, 85, 247, 0.5);
        }

        .tracker-step-item.step-4.active .tracker-icon-circle,
        .tracker-step-item.completed .tracker-icon-circle {
            border-color: #10b981;
            background: rgba(16, 185, 129, 0.25);
            color: #10b981;
            box-shadow: 0 0 18px rgba(16, 185, 129, 0.4);
            font-weight: 800;
        }

        .tracker-step-title {
            font-size: 0.78rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.45);
            text-align: center;
            white-space: nowrap;
        }

        .tracker-step-item.step-1.active .tracker-step-title { color: #f59e0b; }
        .tracker-step-item.step-2.active .tracker-step-title { color: #60a5fa; }
        .tracker-step-item.step-3.active .tracker-step-title { color: #c084fc; }
        .tracker-step-item.step-4.active .tracker-step-title,
        .tracker-step-item.completed .tracker-step-title { color: #10b981; }

        .tracker-line {
            flex: 1;
            height: 3.5px;
            background: rgba(255, 255, 255, 0.1);
            margin: 0 -12px 1.5rem -12px;
            z-index: 1;
            transition: all 0.4s ease;
        }

        .tracker-line.active {
            background: linear-gradient(90deg, #ff5e00, #10b981);
            box-shadow: 0 0 8px rgba(255, 94, 0, 0.4);
        }

        .order-items-list {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .order-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: nowrap;
            padding: 0.85rem 1.2rem;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 16px;
            border: 1px solid rgba(255, 94, 0, 0.1);
            transition: all 0.25s ease;
        }

        .order-item-row:hover {
            border-color: rgba(255, 94, 0, 0.25);
            background: rgba(255, 255, 255, 0.04);
            transform: translateX(4px);
        }

        .item-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
            min-width: 250px;
        }

        .item-thumb {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            object-fit: cover;
            border: 1.5px solid rgba(255, 94, 0, 0.25);
            background: rgba(13, 16, 21, 0.6);
            flex-shrink: 0;
        }

        .item-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .item-name {
            font-weight: 700;
            font-size: 1.05rem;
            color: var(--text-main);
        }

        .item-price-qty {
            font-size: 0.88rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .item-total-price {
            font-weight: 800;
            font-size: 1.15rem;
            color: var(--primary-glow);
        }

        .order-footer-details {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 2rem;
            border-top: 1px solid rgba(255, 94, 0, 0.12);
            padding-top: 1.5rem;
            margin-top: 0.5rem;
        }

        .shipping-address-block {
            font-size: 0.92rem;
            color: var(--text-muted);
            line-height: 1.65;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 94, 0, 0.1);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
        }

        .shipping-address-block strong {
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-bottom: 0.6rem;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .order-pricing-summary {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            padding: 1.25rem 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 94, 0, 0.15);
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.92rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .price-row.grand-total {
            border-top: 1px dashed rgba(255, 94, 0, 0.2);
            padding-top: 0.75rem;
            margin-top: 0.2rem;
            font-weight: 800;
            color: var(--text-main);
            font-size: 1.2rem;
        }

        .price-row.grand-total span:last-child {
            color: var(--primary-glow);
            font-size: 1.3rem;
        }

        @media(max-width: 768px) {
            .orders-page {
                padding: 90px 4% 10rem;
            }

            .page-header-row {
                margin-bottom: 2rem;
            }

            .page-header-row h1 {
                font-size: 2rem;
            }

            .page-header-row p {
                font-size: 0.9rem;
            }

            .order-card {
                padding: 1.25rem;
                border-radius: 18px;
                margin-bottom: 1.25rem;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.25rem;
                padding-bottom: 1rem;
                margin-bottom: 1rem;
            }

            .order-meta-info {
                grid-template-columns: 1fr 1fr;
                gap: 0.85rem 1.5rem;
                width: 100%;
                order: 2;
            }

            .order-status {
                align-self: flex-start;
                font-size: 0.78rem;
                padding: 0.4rem 1rem;
                order: 1;
            }

            .payment-proof-bar {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .payment-proof-bar .proof-bar-btn {
                width: 100%;
                justify-content: center;
            }
            .payment-proof-bar .proof-bar-icon {
                margin: 0 auto;
            }
            .payment-proof-bar .proof-bar-text {
                text-align: center;
                min-width: unset;
            }

            .meta-label {
                font-size: 0.68rem;
            }

            .meta-val {
                font-size: 0.9rem;
            }

            .order-tracker-box {
                padding: 1rem;
                margin: 1rem 0 1.25rem;
            }

            .tracker-icon-circle {
                width: 34px;
                height: 34px;
                font-size: 0.85rem;
            }

            .tracker-step-title {
                font-size: 0.65rem;
            }

            .order-items-list {
                gap: 0.75rem;
            }

            .order-item-row {
                flex-direction: row;
                align-items: center;
                padding: 0.7rem 0.85rem;
                gap: 0.75rem;
            }

            .item-info {
                min-width: 0;
                flex: 1;
                gap: 0.75rem;
            }

            .item-thumb {
                width: 52px;
                height: 52px;
                border-radius: 10px;
            }

            .item-name {
                font-size: 0.9rem;
                line-height: 1.3;
                word-break: break-word;
            }

            .item-price-qty {
                font-size: 0.78rem;
            }

            .item-total-price {
                font-size: 1rem;
                white-space: nowrap;
            }

            .order-footer-details {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding-top: 1rem;
            }

            .shipping-address-block,
            .order-pricing-summary {
                padding: 1rem;
                font-size: 0.88rem;
            }

            .price-row.grand-total {
                font-size: 1rem;
            }

            .price-row.grand-total span:last-child {
                font-size: 1.1rem;
            }
        }

        @media(max-width: 430px) {
            .order-meta-info {
                grid-template-columns: 1fr;
            }

            .tracker-icon-circle {
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
            }

            .tracker-step-title {
                font-size: 0.6rem;
            }
        }
        .tab-btn {
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .tab-btn.active {
            background: rgba(255, 94, 0, 0.15);
            border: 1px solid var(--primary-glow);
            color: #fff;
            box-shadow: 0 4px 15px rgba(255, 94, 0, 0.2);
        }
        .tab-btn.inactive {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid transparent;
            color: var(--text-muted);
        }
        .tab-btn.inactive:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .tab-btn {
            padding: 0.8rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .tab-btn.active {
            background: rgba(255, 94, 0, 0.15);
            border: 1px solid var(--primary-glow);
            color: #fff;
            box-shadow: 0 4px 15px rgba(255, 94, 0, 0.2);
        }
        .tab-btn.inactive {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid transparent;
            color: var(--text-muted);
        }
        .tab-btn.inactive:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .status-verified,
        .pay-status-verified {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.35);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.1);
        }
        .status-awaiting_verification,
        .pay-status-awaiting_verification {
            background: rgba(6, 182, 212, 0.15);
            color: #06b6d4;
            border: 1px solid rgba(6, 182, 212, 0.4);
            box-shadow: 0 0 15px rgba(6, 182, 212, 0.2);
        }
        .status-rejected,
        .pay-status-rejected {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.35);
        }
        .pay-status-payment_not_required {
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.35);
        }
        .pay-status-pending {
            background: rgba(168, 85, 247, 0.15);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.35);
        }
        .payment-action-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.55rem 1.2rem; border-radius: 10px;
            font-size: 0.85rem; font-weight: 700; cursor: pointer;
            border: 1.5px solid rgba(255, 94, 0, 0.4);
            background: rgba(255, 94, 0, 0.07);
            color: var(--primary-glow);
            transition: all 0.2s ease; font-family: inherit;
        }
        .payment-action-btn:hover { background: rgba(255,94,0,0.15); border-color: var(--primary-glow); }
        /* Upload Modal */
        .proof-modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.75);
            z-index: 9999; display: none; align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .proof-modal-overlay.open { display: flex; }
        .proof-modal-box {
            background: rgba(13,16,21,0.98);
            border: 1px solid rgba(255,94,0,0.25);
            border-top: 4px solid var(--primary-glow);
            border-radius: 20px; padding: 2rem;
            max-width: 480px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.7);
        }
        .proof-modal-box h3 { font-size: 1.2rem; font-weight: 800; margin-bottom: 0.4rem; color: #fff; }
        .proof-modal-box p { font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.5rem; }
        .proof-file-zone {
            border: 2px dashed rgba(255,94,0,0.3); border-radius: 12px;
            padding: 1.5rem; text-align: center; cursor: pointer;
            background: rgba(255,94,0,0.03); transition: all 0.2s; position: relative;
            margin-bottom: 1rem;
        }
        .proof-file-zone:hover { border-color: var(--primary-glow); background: rgba(255,94,0,0.08); }
        .proof-file-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
        .proof-txn-input { width:100%; padding:0.8rem 1rem; border-radius:8px; background:rgba(13,16,21,0.5); border:1px solid rgba(255,94,0,0.2); color:#fff; font-family:inherit; font-size:0.95rem; margin-bottom:1rem; }
        .proof-txn-input:focus { outline:none; border-color:var(--primary-glow); }
        .proof-submit-btn { width:100%; padding:0.9rem; border-radius:10px; background:var(--primary-glow); color:#000; font-weight:800; font-size:1rem; border:none; cursor:pointer; transition:0.2s; }
        .proof-submit-btn:hover { background: #ff8700; }
        #proof-upload-result { font-size:0.88rem; margin-top:0.75rem; text-align:center; font-weight:600; }
        /* Payment Proof Bar */
        .payment-proof-bar {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            padding: 1.2rem 1.5rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            animation: proofBarPulse 3s ease-in-out infinite;
        }
        .payment-proof-bar.proof-submitted {
            background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(16,185,129,0.03));
            border: 1.5px solid rgba(16,185,129,0.3);
        }
        .payment-proof-bar.proof-needed {
            background: rgba(245,158,11,0.05);
            border: 1px solid rgba(245,158,11,0.25);
        }
        .payment-proof-bar.proof-rejected {
            background: linear-gradient(135deg, rgba(239,68,68,0.08), rgba(239,68,68,0.03));
            border: 1.5px solid rgba(239,68,68,0.3);
        }
        @keyframes proofBarPulse {
            0%, 100% { box-shadow: 0 0 0 0 transparent; }
            50% { box-shadow: 0 0 20px rgba(245,158,11,0.08); }
        }
        .proof-bar-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem; flex-shrink: 0;
        }
        .proof-submitted .proof-bar-icon { background: rgba(16,185,129,0.15); }
        .proof-needed .proof-bar-icon { background: rgba(245,158,11,0.15); }
        .proof-rejected .proof-bar-icon { background: rgba(239,68,68,0.15); }
        .proof-bar-text { flex: 1; min-width: 200px; }
        .proof-bar-text .proof-title { font-weight: 700; font-size: 0.95rem; margin-bottom: 0.2rem; }
        .proof-bar-text .proof-desc { font-size: 0.82rem; color: var(--text-muted); line-height: 1.4; }
        .proof-bar-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.65rem 1.5rem; border-radius: 12px;
            font-size: 0.88rem; font-weight: 700; cursor: pointer;
            border: none; font-family: inherit;
            transition: all 0.25s ease;
            white-space: nowrap;
        }
        .proof-submitted .proof-bar-btn {
            background: rgba(16,185,129,0.12); color: #10b981;
            border: 1.5px solid rgba(16,185,129,0.35);
        }
        .proof-submitted .proof-bar-btn:hover { background: rgba(16,185,129,0.2); }
        .proof-needed .proof-bar-btn {
            background: transparent; color: #f59e0b;
            border: 1.5px solid #f59e0b; box-shadow: none;
        }
        .proof-needed .proof-bar-btn:hover { background: rgba(245,158,11,0.1); transform: translateY(-1px); }
        .proof-rejected .proof-bar-btn {
            background: rgba(239,68,68,0.12); color: #ef4444;
            border: 1.5px solid rgba(239,68,68,0.35);
        }
        .proof-rejected .proof-bar-btn:hover { background: rgba(239,68,68,0.2); }
        @media(max-width: 768px) {
            .payment-proof-bar {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 1.25rem 1rem;
                gap: 1rem;
            }
            .proof-bar-icon { margin: 0 auto; }
            .proof-bar-text { text-align: center; min-width: unset; }
            .proof-action-btns { justify-content: center; width: 100%; }
            .proof-bar-btn { width: auto; justify-content: center; }
        }
    </style>
</head>
<body>
    <!-- Payment Proof Upload Modal -->
    <div class="proof-modal-overlay" id="proof-modal-overlay" onclick="closeProofModal(event)">
        <div class="proof-modal-box">
            <h3>📎 Submit Payment Proof</h3>
            <p id="proof-modal-desc">Upload your payment slip or enter your transaction ID below.</p>
            <form id="proof-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="order_id" id="proof-order-id">
                <div class="proof-file-zone" id="proof-file-zone" onclick="document.getElementById('proof-file-input').click()">
                    <div style="font-size:2rem;">📁</div>
                    <div style="font-size:0.9rem; color:var(--text-muted); margin-top:0.3rem;"><strong style="color:var(--primary-glow);">Click to upload file</strong><br>JPG, PNG, WEBP, PDF · Max 10MB</div>
                    <div id="proof-file-name" style="font-size:0.8rem; color:#10b981; margin-top:0.4rem;"></div>
                    <input type="file" name="payment_slip" id="proof-file-input" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="updateProofFileName(this)">
                </div>
                <label style="font-size:0.8rem; color:var(--text-muted); font-weight:700; display:block; margin-bottom:0.4rem; text-transform:uppercase; letter-spacing:0.8px;">Transaction ID (Optional)</label>
                <input type="text" name="transaction_id" id="proof-txn-id" class="proof-txn-input" placeholder="Enter transaction ID or hash...">
                <button type="button" class="proof-submit-btn" onclick="submitProofUpload()">Submit Payment Proof</button>
                <div id="proof-upload-result"></div>
            </form>
            <button onclick="closeProofModal()" style="width:100%; margin-top:0.75rem; padding:0.7rem; border-radius:10px; background:transparent; border:1px solid rgba(255,255,255,0.1); color:var(--text-muted); cursor:pointer; font-size:0.9rem;">Cancel</button>
        </div>
    </div>

    <!-- Background Animated Elements -->
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>

    <header class="glass-header">
        <a href="index.php" class="logo" style="text-decoration:none; display:flex; align-items:center; gap:0.6rem; color:var(--text-main);">
            <img src="logo.png" alt="Digi Pro X 24" style="height:36px; border-radius: 8px;">
            Digi <span>Pro X 24</span>
        </a>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="categories.php">Categories</a></li>
                <li><a href="products.php">Products</a></li>
                <?php $uc_nav = strtolower(trim($_SESSION['user_country'] ?? '')); if (isset($_SESSION['user_id']) && ($uc_nav === 'sri lanka' || $uc_nav === 'lk' || $uc_nav === 'srilanka' || $uc_nav === 'sl')): ?>
                <li><a href="services.php">Services</a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="my_orders.php" class="active">My Orders</a></li>
                <?php endif; ?>
                <li><a href="about.php">About</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
        <div class="header-actions" style="display: flex; align-items: center;">
            <button id="currencyToggle" title="Switch to USD" style="background: rgba(255, 94, 0, 0.08); border: 1.5px solid var(--primary-glow); color: var(--primary-glow); padding: 0.45rem 1rem; border-radius: 8px; font-size: 0.95rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; margin-left: 0.8rem; box-shadow: 0 0 10px rgba(255, 94, 0, 0.1); height: 38px; white-space: nowrap;"><script>document.write(localStorage.getItem('site_currency') === 'USD' ? '🇺🇸 USD' : '🇱🇰 LKR');</script></button>
            <button id="hamburgerBtn" class="hamburger-btn" onclick="toggleMobileMenu()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg></button>
            <a href="cart.php" class="icon-btn cart-btn" style="text-decoration:none;">🛒 <span class="cart-count"><?php echo $cart_count; ?></span></a>
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

    <main class="orders-page">
        <?php if (isset($_SESSION['success'])): ?>
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #10b981; padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 2rem; font-weight: 600;">
                echo htmlspecialchars($_SESSION['success']); 
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="page-header-row">
            <h1>My <span>Dashboard</span></h1>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Track your purchases and view service tickets below</p>
        </div>

        <div class="order-tabs" style="display: flex; justify-content: center; gap: 1rem; margin-bottom: 3rem; flex-wrap: wrap;">
            <button onclick="showSection('orders')" id="tab-orders" class="tab-btn active">📦 Product Orders</button>
            <?php 
            $uc = strtolower(trim($_SESSION['user_country'] ?? ''));
            $is_sl = (isset($_SESSION['user_id']) && ($uc === 'sri lanka' || $uc === 'lk' || $uc === 'srilanka' || $uc === 'sl'));
            if ($is_sl): 
            ?>
            <button onclick="showSection('services')" id="tab-services" class="tab-btn inactive">🛠️ Service Requests</button>
            <?php endif; ?>
        </div>

        <div id="section-orders">

        <?php if (empty($orders)): ?>
            <div class="glass-panel" style="text-align: center; padding: 5rem 2rem; border-radius: 24px; border: 1px solid rgba(255,94,0,0.18);">
                <div style="font-size: 4rem; margin-bottom: 1rem;">📦</div>
                <h2 style="color: var(--text-main); margin-bottom: 0.5rem;">No Orders Placed Yet</h2>
                <p style="color: var(--text-muted); margin-bottom: 2rem;">Looks like you haven't placed any orders yet. Visit our catalog to start shopping!</p>
                <a href="products.php" class="btn-primary" style="text-decoration: none; padding: 0.9rem 2rem; border-radius: 12px; display: inline-block;">Browse Products</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): 
                $order_id = $order['id'];
                
                // Fetch items for this order
                $itemStmt = $pdo->prepare("
                    SELECT oi.*, p.name, p.image 
                    FROM order_items oi 
                    LEFT JOIN products p ON oi.product_id = p.id 
                    WHERE oi.order_id = ?
                ");
                $itemStmt->execute([$order_id]);
                $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

                // Order status styling & tracking logic
                $rawStatus = strtolower(trim($order['status'] ?? 'pending'));
                $stage = 1;
                $statusClass = 'status-pending';
                $statusIcon = '⏳';
                $statusLabel = 'Pending Confirmation';

                if ($rawStatus === 'pending') {
                    $stage = 1;
                    $statusClass = 'status-pending';
                    $statusIcon = '⏳';
                    $statusLabel = 'Pending Confirmation';
                } elseif ($rawStatus === 'processing') {
                    $stage = 2;
                    $statusClass = 'status-processing';
                    $statusIcon = '⚙️';
                    $statusLabel = 'Processing Order';
                } elseif ($rawStatus === 'shipped') {
                    $stage = 3;
                    $statusClass = 'status-shipped';
                    $statusIcon = '🚚';
                    $statusLabel = 'Shipped & On The Way';
                } elseif ($rawStatus === 'delivered' || $rawStatus === 'completed') {
                    $stage = 4;
                    $statusClass = 'status-completed';
                    $statusIcon = '✅';
                    $statusLabel = $rawStatus === 'delivered' ? 'Delivered' : 'Completed';
                } elseif ($rawStatus === 'cancelled') {
                    $stage = 0;
                    $statusClass = 'status-cancelled';
                    $statusIcon = '✕';
                    $statusLabel = 'Cancelled';
                }
            ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-meta-info">
                            <div class="meta-item">
                                <span class="meta-label">Order ID</span>
                                <span class="meta-val order-id-highlight">#<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Date Placed</span>
                                <span class="meta-val"><?php echo date('M j, Y, g:i a', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Payment Method</span>
                                <span class="meta-val"><?php echo strtoupper(str_replace('_', ' ', $order['payment_method'])); ?></span>
                            </div>
                            <?php
                            $ps = $order['payment_status'] ?? 'pending';
                            $psLabel = match($ps) {
                                'awaiting_verification' => '⏳ Awaiting Verification',
                                'verified'              => '✅ Payment Verified',
                                'rejected'              => '❌ Payment Rejected',
                                'payment_not_required'  => '💵 Pay at Delivery',
                                default                 => '⏳ Pending',
                            };
                            $psColor = match($ps) {
                                'awaiting_verification' => '#06b6d4',
                                'verified'              => '#10b981',
                                'rejected'              => '#ef4444',
                                default                 => '#f59e0b',
                            };
                            $psClass = 'status-' . str_replace(' ', '_', $ps);
                            ?>
                            <div class="meta-item">
                                <span class="meta-label">Payment Status</span>
                                <span class="meta-val" style="color: <?php echo $psColor; ?>;"><?php echo $psLabel; ?></span>
                            </div>
                        </div>
                        <span class="order-status <?php echo $statusClass; ?>">
                            <span><?php echo $statusIcon; ?></span>
                            <?php echo htmlspecialchars($statusLabel); ?>
                        </span>
                    </div>

                    <?php
                    $pm = $order['payment_method'] ?? 'cod';
                    $isManualPayment = in_array($pm, ['bank_transfer','crypto','paypal']);
                    if ($isManualPayment):
                        $hasSlip = !empty($order['payment_slip']);
                        $hasTxn  = !empty($order['transaction_id']);
                        $proofLabel = $pm === 'bank_transfer' ? '📎 Upload Bank Slip' : '📝 Submit Payment Confirmation';
                        $proofDesc  = $pm === 'bank_transfer'
                            ? 'Upload your bank deposit slip or screenshot for verification.'
                            : 'Enter your transaction ID and optionally upload a payment screenshot.';
                    ?>
                    <div class="payment-proof-bar <?php 
                        if ($ps === 'rejected') echo 'proof-rejected';
                        elseif ($ps === 'verified') echo 'proof-submitted';
                        elseif ($hasSlip || $hasTxn) echo 'proof-submitted';
                        else echo 'proof-needed';
                    ?>">
                        <div class="proof-bar-icon">
                            <?php if ($ps === 'rejected'): ?>
                                ❌
                            <?php elseif ($ps === 'verified'): ?>
                                ✅
                            <?php elseif ($hasSlip || $hasTxn): ?>
                                ⏳
                            <?php else: ?>
                                ⚠️
                            <?php endif; ?>
                        </div>
                        <div class="proof-bar-text">
                            <?php if ($ps === 'rejected'): ?>
                                <div class="proof-title" style="color:#ef4444;">Payment Rejected – Please Resubmit</div>
                                <div class="proof-desc">Your payment proof was rejected. Please re-upload a valid payment slip or correct transaction ID.</div>
                            <?php elseif ($ps === 'verified'): ?>
                                <div class="proof-title" style="color:#10b981;">Payment Verified</div>
                                <div class="proof-desc">Your payment has been successfully verified by our team. Your order is being processed.</div>
                            <?php elseif ($hasSlip || $hasTxn): ?>
                                <div class="proof-title" style="color:#10b981;">Payment Proof Submitted – Under Review</div>
                                <div class="proof-desc">
                                    Our team is reviewing your payment confirmation.
                                    <?php if ($hasTxn): ?>
                                        <br><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['transaction_id']); ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="proof-title" style="color:#f59e0b;">Payment Proof Required</div>
                                <div class="proof-desc">Your order is on hold until payment proof is submitted. Please upload your bank slip or transaction confirmation.</div>
                            <?php endif; ?>
                        </div>
                        <div class="proof-action-btns" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                            <?php if ($hasSlip): ?>
                                <a href="<?php echo htmlspecialchars($order['payment_slip']); ?>" target="_blank" class="proof-bar-btn" style="text-decoration:none; background:rgba(255,255,255,0.08); color:#fff; border:1px solid rgba(255,255,255,0.2);">🔍 View Submitted Slip</a>
                            <?php endif; ?>
                            <?php if ($ps !== 'verified'): ?>
                                <button class="proof-bar-btn" onclick="openProofModal(<?php echo $order_id; ?>, '<?php echo addslashes($proofDesc); ?>')">
                                    <?php echo ($hasSlip || $hasTxn || $ps === 'rejected') ? '🔄 Re-upload Proof' : $proofLabel; ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($stage > 0): ?>
                    <!-- Order Status Progress Tracker Bar -->
                    <div class="order-tracker-box">
                        <div class="tracker-steps">
                            <div class="tracker-step-item step-1 <?php echo $stage > 1 ? 'completed' : ($stage === 1 ? 'active' : ''); ?>">
                                <div class="tracker-icon-circle"><?php echo $stage > 1 ? '✓' : '📝'; ?></div>
                                <div class="tracker-step-title">Order Placed</div>
                            </div>
                            <div class="tracker-line <?php echo $stage >= 2 ? 'active' : ''; ?>"></div>

                            <div class="tracker-step-item step-2 <?php echo $stage > 2 ? 'completed' : ($stage === 2 ? 'active' : ''); ?>">
                                <div class="tracker-icon-circle"><?php echo $stage > 2 ? '✓' : '⚙️'; ?></div>
                                <div class="tracker-step-title">Processing</div>
                            </div>
                            <div class="tracker-line <?php echo $stage >= 3 ? 'active' : ''; ?>"></div>

                            <div class="tracker-step-item step-3 <?php echo $stage > 3 ? 'completed' : ($stage === 3 ? 'active' : ''); ?>">
                                <div class="tracker-icon-circle"><?php echo $stage > 3 ? '✓' : '🚚'; ?></div>
                                <div class="tracker-step-title">Shipped</div>
                            </div>
                            <div class="tracker-line <?php echo $stage >= 4 ? 'active' : ''; ?>"></div>

                            <div class="tracker-step-item step-4 <?php echo $stage === 4 ? 'completed active' : ''; ?>">
                                <div class="tracker-icon-circle">✅</div>
                                <div class="tracker-step-title">Delivered</div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="order-items-list">
                        <?php foreach ($items as $item): ?>
                            <div class="order-item-row">
                                <div class="item-info">
                                    <img src="<?php echo htmlspecialchars($item['image'] ?? 'placeholder.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name'] ?? 'Purchased Product'); ?>" class="item-thumb">
                                    <div class="item-details">
                                        <span class="item-name"><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?></span>
                                        <span class="item-price-qty">Rs. <?php echo number_format($item['price'], 2); ?> × <?php echo $item['quantity']; ?></span>
                                    </div>
                                </div>
                                <span class="item-total-price">Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="order-footer-details">
                        <div class="shipping-address-block">
                            <strong>📍 Shipping Details</strong>
                            <?php if (($order['address_version'] ?? 1) == 2): ?>
                                <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?><br>
                                <?php echo htmlspecialchars($order['phone']); ?><br>
                                <?php echo htmlspecialchars($order['address_line_1']); ?>
                                <?php echo !empty($order['address_line_2']) ? '<br>' . htmlspecialchars($order['address_line_2']) : ''; ?><br>
                                <?php echo htmlspecialchars($order['city'] . ', ' . $order['state_province_region']); ?><br>
                                Postal Code: <?php echo htmlspecialchars($order['zip']); ?>
                                <?php echo !empty($order['country']) ? '<br>' . htmlspecialchars($order['country']) : ''; ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars($order['address']); ?><br>
                                <?php echo htmlspecialchars($order['city'] . ', ' . $order['district']); ?><br>
                                Postal Code: <?php echo htmlspecialchars($order['zip']); ?>
                            <?php endif; ?>
                            <?php if (!empty($order['order_notes'])): ?>
                                <div style="margin-top: 0.75rem; padding: 0.6rem 0.8rem; background: rgba(255, 94, 0, 0.05); border: 1px dashed rgba(255, 94, 0, 0.3); border-radius: 8px; font-size: 0.85rem;">
                                    <strong style="color: #ff5e00;">📝 Order Notes:</strong> <?php echo htmlspecialchars($order['order_notes']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="order-pricing-summary">
                            <div class="price-row">
                                <span>Items Subtotal</span>
                                <span>Rs. <?php echo number_format($order['total_price'] - $order['shipping_fee'], 2); ?></span>
                            </div>
                            <div class="price-row">
                                <span>Delivery Fee</span>
                                <span><?php echo $order['shipping_fee'] > 0 ? 'Rs. ' . number_format($order['shipping_fee'], 2) : 'Free Shipping'; ?></span>
                            </div>
                            <div class="price-row grand-total">
                                <span>Grand Total</span>
                                <span>Rs. <?php echo number_format($order['total_price'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div> <!-- End order-card -->
            <?php endforeach; ?>
        <?php endif; ?>

        </div> <!-- End section-orders -->
        
        <?php if ($is_sl): ?>
        <div id="section-services" style="display: none;">
        <?php if (count($service_requests) > 0): ?>
            
            <?php foreach ($service_requests as $sr): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-meta-info">
                            <div class="meta-item">
                                <span class="meta-label">Token Number</span>
                                <span class="meta-val order-id-highlight"><?php echo htmlspecialchars($sr['token_number']); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Date Submitted</span>
                                <span class="meta-val"><?php echo date('M j, Y, g:i a', strtotime($sr['created_at'])); ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-label">Service</span>
                                <span class="meta-val"><?php echo htmlspecialchars($sr['service_name'] ?? 'Local Service'); ?></span>
                            </div>
                        </div>
                        <?php 
                        $sr_status_class = 'status-pending';
                        $sr_status_icon = '⏳';
                        if (strtolower($sr['status']) === 'completed') {
                            $sr_status_class = 'status-completed';
                            $sr_status_icon = '✅';
                        } elseif (strtolower($sr['status']) === 'called & scheduled') {
                            $sr_status_class = 'status-shipped';
                            $sr_status_icon = '📞';
                        }
                        ?>
                        <span class="order-status <?php echo $sr_status_class; ?>">
                            <span><?php echo $sr_status_icon; ?></span>
                            <?php echo htmlspecialchars($sr['status']); ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="glass-panel" style="text-align: center; padding: 5rem 2rem; border-radius: 24px; border: 1px solid rgba(255,94,0,0.18);">
                <div style="font-size: 4rem; margin-bottom: 1rem;">🛠️</div>
                <h2 style="color: var(--text-main); margin-bottom: 0.5rem;">No Service Requests Yet</h2>
                <p style="color: var(--text-muted); margin-bottom: 2rem;">You haven't requested any technical services.</p>
                <a href="services.php" class="btn-primary" style="text-decoration: none; padding: 0.9rem 2rem; border-radius: 12px; display: inline-block;">Browse Services</a>
            </div>
        <?php endif; ?>
        </div> <!-- End section-services -->
        <?php endif; ?>

    </main>

    <footer style="text-align: center; padding: 2rem 0; border-top: 1px solid rgba(255, 94, 0, 0.08); color: var(--text-muted); font-size: 0.75rem;" class="no-print">
        <p>Developed By <a href="https://fusionwavesystems.com/" target="_blank" rel="noopener noreferrer" style="color: var(--text-main); font-weight: 600; text-decoration: none; border-bottom: 1px dashed var(--primary-glow);">Fusion Wave Systems (Pvt) Ltd.</a></p>
    </footer>
    <script src="assets/js/main.js?v=9"></script>
    <script>
        function showSection(section) {
            document.getElementById('section-orders').style.display = (section === 'orders') ? 'block' : 'none';
            document.getElementById('section-services').style.display = (section === 'services') ? 'block' : 'none';
            
            if(section === 'orders') {
                document.getElementById('tab-orders').className = 'tab-btn active';
                let tabServices = document.getElementById('tab-services');
                if (tabServices) tabServices.className = 'tab-btn inactive';
            } else {
                let tabServices = document.getElementById('tab-services');
                if (tabServices) tabServices.className = 'tab-btn active';
                document.getElementById('tab-orders').className = 'tab-btn inactive';
            }
        }

        function openProofModal(orderId, desc) {
            document.getElementById('proof-order-id').value = orderId;
            document.getElementById('proof-modal-desc').textContent = desc || 'Upload your payment slip or enter your transaction ID below.';
            document.getElementById('proof-file-input').value = '';
            document.getElementById('proof-file-name').textContent = '';
            document.getElementById('proof-txn-id').value = '';
            document.getElementById('proof-upload-result').textContent = '';
            document.getElementById('proof-modal-overlay').classList.add('open');
        }

        function closeProofModal(e) {
            if (e && e.target !== e.currentTarget) return;
            document.getElementById('proof-modal-overlay').classList.remove('open');
        }

        function updateProofFileName(input) {
            const nameEl = document.getElementById('proof-file-name');
            if (input.files && input.files[0]) {
                const f = input.files[0];
                const mb = (f.size / 1024 / 1024).toFixed(2);
                if (f.size > 10 * 1024 * 1024) {
                    alert('File too large. Maximum 10MB.');
                    input.value = '';
                    nameEl.textContent = '';
                    return;
                }
                nameEl.textContent = '✅ ' + f.name + ' (' + mb + ' MB)';
            }
        }

        function submitProofUpload() {
            const form = document.getElementById('proof-upload-form');
            const resultEl = document.getElementById('proof-upload-result');
            const fd = new FormData(form);
            resultEl.textContent = 'Uploading...';
            resultEl.style.color = '#f59e0b';

            fetch('upload_payment_proof.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        resultEl.style.color = '#10b981';
                        resultEl.textContent = '✅ ' + data.message;
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        resultEl.style.color = '#ef4444';
                        resultEl.textContent = '❌ ' + data.message;
                    }
                })
                .catch(() => {
                    resultEl.style.color = '#ef4444';
                    resultEl.textContent = '❌ Upload failed. Please try again.';
                });
        }
    </script>
</body>
</html>