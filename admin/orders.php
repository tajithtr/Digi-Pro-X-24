<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    header("Location: ../login.php");
    exit;
}

require_once '../config.php';

// Handle order status update, delete, or bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'bulk_action' && isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
            $order_ids = array_map('intval', $_POST['order_ids']);
            if (!empty($order_ids)) {
                $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                $bulk_act = $_POST['bulk_act'] ?? '';
                if ($bulk_act === 'delete') {
                    // Delete items
                    $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id IN ($placeholders)");
                    $stmt->execute($order_ids);
                    // Delete orders
                    $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
                    $stmt->execute($order_ids);
                } elseif (in_array($bulk_act, ['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'])) {
                    // Update status
                    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id IN ($placeholders)");
                    $stmt->execute(array_merge([$bulk_act], $order_ids));
                }
            }
            header("Location: orders.php");
            exit;
        } elseif (isset($_POST['order_id'])) {
            $order_id = (int)$_POST['order_id'];
            if ($_POST['action'] === 'update_status') {
                $status = $_POST['status'] ?? 'pending';
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->execute([$status, $order_id]);
            } elseif ($_POST['action'] === 'upload_admin_slip' && isset($_FILES['admin_slip']) && $_FILES['admin_slip']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
                $file_type = mime_content_type($_FILES['admin_slip']['tmp_name']);
                $file_size = $_FILES['admin_slip']['size'];
                if (in_array($file_type, $allowed_types) && $file_size <= 10 * 1024 * 1024) {
                    $ext = strtolower(pathinfo($_FILES['admin_slip']['name'], PATHINFO_EXTENSION));
                    $filename = 'slip_order' . $order_id . '_' . time() . '.' . $ext;
                    $upload_dir = __DIR__ . '/../uploads/payment_slips/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
                    if (move_uploaded_file($_FILES['admin_slip']['tmp_name'], $upload_dir . $filename)) {
                        $slip_path = 'uploads/payment_slips/' . $filename;
                        $stmt = $pdo->prepare("UPDATE orders SET payment_slip = ?, payment_status = 'awaiting_verification' WHERE id = ?");
                        $stmt->execute([$slip_path, $order_id]);
                    }
                }
            } elseif ($_POST['action'] === 'delete') {
                // Delete order items first
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
                $stmt->execute([$order_id]);
                // Delete order
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
            }
            header("Location: orders.php");
            exit;
        }
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if ($statusFilter !== 'all' && !in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

$paymentFilter = $_GET['payment'] ?? 'all';
$validPayments = ['cod', 'bank_transfer', 'crypto', 'paypal', 'payzy'];
if ($paymentFilter !== 'all' && !in_array($paymentFilter, $validPayments)) {
    $paymentFilter = 'all';
}

$sortFilter = $_GET['sort'] ?? 'desc';
if ($sortFilter !== 'asc' && $sortFilter !== 'desc') {
    $sortFilter = 'desc';
}
$orderDirection = ($sortFilter === 'asc') ? 'ASC' : 'DESC';

$searchQuery = trim($_GET['search'] ?? '');

$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "o.status = ?";
    $params[] = $statusFilter;
}

if ($paymentFilter !== 'all') {
    $whereConditions[] = "LOWER(o.payment_method) = ?";
    $params[] = $paymentFilter;
}

if ($searchQuery !== '') {
    $whereConditions[] = "(o.id LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchWildcard = '%' . $searchQuery . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Fetch dynamic counts for STATUSES (respecting payment filter)
$sQuery = "SELECT status, COUNT(*) as cnt FROM orders";
$sParams = [];
if ($paymentFilter !== 'all') {
    $sQuery .= " WHERE LOWER(payment_method) = ?";
    $sParams[] = $paymentFilter;
}
$sQuery .= " GROUP BY status";
$sStmt = $pdo->prepare($sQuery);
$sStmt->execute($sParams);
$statusData = $sStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pendingCount = $statusData['pending'] ?? 0;
$processingCount = $statusData['processing'] ?? 0;
$shippedCount = $statusData['shipped'] ?? 0;
$deliveredCount = $statusData['delivered'] ?? 0;
$cancelledCount = $statusData['cancelled'] ?? 0;
$totalCount = array_sum($statusData);

// Fetch dynamic counts for PAYMENTS (respecting status filter)
$pQuery = "SELECT LOWER(payment_method) as pay_method, COUNT(*) as cnt FROM orders";
$pParams = [];
if ($statusFilter !== 'all') {
    $pQuery .= " WHERE status = ?";
    $pParams[] = $statusFilter;
}
$pQuery .= " GROUP BY LOWER(payment_method)";
$pStmt = $pdo->prepare($pQuery);
$pStmt->execute($pParams);
$paymentData = $pStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$codCount = $paymentData['cod'] ?? 0;
$bankTransferCount = $paymentData['bank_transfer'] ?? 0;
$cryptoCount = $paymentData['crypto'] ?? 0;
$paypalCount = $paymentData['paypal'] ?? 0;
$payzyCount = $paymentData['payzy'] ?? 0;
$totalPaymentCount = array_sum($paymentData);

// Fetch filtered orders with user name/details
$stmt = $pdo->prepare("
    SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    $whereClause
    ORDER BY o.id $orderDirection
");
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch order items helper to render items inside modal/accordion dynamically via JS
$itemsStmt = $pdo->query("
    SELECT oi.*, p.name as product_name, p.image as product_image, p.warranty
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
");
$all_order_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group items by order_id
$order_items_map = [];
foreach ($all_order_items as $item) {
    $order_items_map[$item['order_id']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Digi Pro X 24 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .msg-header-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 0.6rem 1.4rem;
            border-radius: 10px;
            background: #ffffff;
            color: #475569;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid #cbd5e1;
            transition: all 0.3s ease;
        }
        .filter-btn.active, .filter-btn:hover {
            background: var(--accent-orange);
            color: #ffffff;
            border-color: var(--accent-orange);
            box-shadow: 0 4px 15px rgba(255, 94, 0, 0.25);
        }
        
        
        
        
        .filter-group select {
            flex: 1;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            border: 1.5px solid #cbd5e1;
            background: #f8fafc;
            font-weight: 600;
            cursor: pointer;
            color: #1e293b;
            min-width: 0;
            width: 100%;
        }
        
        /* New Admin Orders List Layout */
        .admin-orders-wrapper {
            background: transparent;
        }
        .admin-orders-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .order-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }
        .order-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .order-card-desktop {
            display: grid;
            grid-template-columns: 40px minmax(200px, 1.5fr) minmax(220px, 1.5fr) 1fr 1fr;
            align-items: stretch;
            padding: 0;
        }
        .oc-col {
            padding: 1.25rem 1rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            border-right: 1px solid #f1f5f9;
        }
        .oc-col:last-child { border-right: none; }
        .oc-checkbox { align-items: center; justify-content: center; padding-left: 1.25rem; }
        .oc-info .oc-id { font-weight: 800; color: #0f172a; font-size: 1.05rem; margin-bottom: 0.2rem; }
        .oc-info .oc-date { font-size: 0.8rem; color: #64748b; margin-bottom: 0.6rem; }
        .oc-info .oc-customer { font-size: 0.85rem; color: #334155; line-height: 1.4; }
        .oc-fin-total { font-weight: 800; color: #ea580c; font-size: 1.1rem; margin-top:0.3rem;}
        .oc-actions { flex-direction: column; gap: 0.5rem; align-items: stretch; justify-content: center; }
        
        .admin-orders-header {
            display: grid;
            grid-template-columns: 40px minmax(200px, 1.5fr) minmax(220px, 1.5fr) 1fr 1fr;
            padding: 0.8rem 0;
            font-size: 0.8rem;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        .admin-orders-header > div { padding: 0 1rem; }
        .admin-orders-header .oc-checkbox { padding-left: 1.25rem; }
        
        @media (max-width: 900px) {
            .admin-orders-header { display: none; }
            .order-card-desktop { display: flex; flex-direction: column; padding: 1rem; }
            .oc-col { border-right: none; border-bottom: 1px dashed #e2e8f0; padding: 1rem 0; }
            .oc-col:last-child { border-bottom: none; }
            .oc-checkbox { padding: 0 0 0.5rem 0; align-items: flex-start; border-bottom: none; }
            .oc-actions { padding-top: 1rem; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <a href="../index.php" class="sidebar-brand">
            Digi Pro X 24 <br><span>Admin Panel</span>
        </a>
        <div class="sidebar-footer">
            <a href="../index.php" style="text-align: center;">🌐 View Site</a>
            <a href="../logout.php" style="text-align: center;">🚪 Logout</a>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'class="active"' : ''; ?>>📊 Admin Dashboard</a></li>
            <li><a href="categories.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'categories.php') ? 'class="active"' : ''; ?>>📁 Categories</a></li>
            <li><a href="products.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'class="active"' : ''; ?>>🛍️ Products</a></li>
            <li><a href="services.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'services.php') ? 'class="active"' : ''; ?>>🛠️ Services</a></li>
            <li><a href="reviews.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'reviews.php') ? 'class="active"' : ''; ?>>⭐ Q & A Reviews <?php if(isset($sidebar_rev) && $sidebar_rev > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_rev; ?></span><?php endif; ?></a></li>
            <li><a href="orders.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'class="active"' : ''; ?>>📦 Orders</a></li>
            <li><a href="service_requests.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'service_requests.php') ? 'class="active"' : ''; ?>>📋 Service Requests <?php if(isset($sidebar_sr) && $sidebar_sr > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_sr; ?></span><?php endif; ?></a></li>
            <li><a href="messages.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'messages.php') ? 'class="active"' : ''; ?>>💬 Messages <?php if(isset($sidebar_msg) && $sidebar_msg > 0): ?><span style="background:#ef4444; color:#fff; padding:2px 8px; border-radius:12px; font-size:0.75rem; margin-left:5px;"><?php echo $sidebar_msg; ?></span><?php endif; ?></a></li>
            <li><a href="users.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php') ? 'class="active"' : ''; ?>>👥 Users</a></li>
            <li><a href="change_password.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'change_password.php') ? 'class="active"' : ''; ?>>🔒 Change Password</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <button class="sidebar-toggle" id="menu-toggle">☰</button>
                <div>
                    <span style="color: var(--text-muted); font-weight:600; font-size:0.9rem;">Management</span>
                    <h1>Customer Orders</h1>
                </div>
            </div>
            <div class="header-user-badge">
                Logged in as: <span style="color: var(--primary-glow, #3b82f6); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <div class="dashboard-section">
            <!-- Filter Dropdowns -->
            <div class="filter-container">
                                <div class="filter-group">
                    <label>Filter by Status:</label>
                    <select class="status-select" onchange="window.location.href='orders.php?search=<?php echo urlencode($searchQuery); ?>&payment=<?php echo $paymentFilter; ?>&sort=<?php echo $sortFilter; ?>&status=' + this.value">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses (<?php echo $totalCount; ?>)</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>⏳ Pending (<?php echo $pendingCount; ?>)</option>
                        <option value="processing" <?php echo $statusFilter === 'processing' ? 'selected' : ''; ?>>⚙️ Processing (<?php echo $processingCount; ?>)</option>
                        <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>🚚 Shipped (<?php echo $shippedCount; ?>)</option>
                        <option value="delivered" <?php echo $statusFilter === 'delivered' ? 'selected' : ''; ?>>📦 Delivered (<?php echo $deliveredCount; ?>)</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>✕ Cancelled (<?php echo $cancelledCount; ?>)</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Filter by Payment:</label>
                    <select class="status-select" onchange="window.location.href='orders.php?search=<?php echo urlencode($searchQuery); ?>&status=<?php echo $statusFilter; ?>&sort=<?php echo $sortFilter; ?>&payment=' + this.value">
                        <option value="all" <?php echo $paymentFilter === 'all' ? 'selected' : ''; ?>>All Payments (<?php echo $totalPaymentCount; ?>)</option>
                        <option value="cod" <?php echo $paymentFilter === 'cod' ? 'selected' : ''; ?>>💵 COD (<?php echo $codCount; ?>)</option>
                        <option value="bank_transfer" <?php echo $paymentFilter === 'bank_transfer' ? 'selected' : ''; ?>>🏦 Bank Transfer (<?php echo $bankTransferCount; ?>)</option>
                        <option value="crypto" <?php echo $paymentFilter === 'crypto' ? 'selected' : ''; ?>>₿ Crypto (<?php echo $cryptoCount; ?>)</option>
                        <?php if ($paypalCount > 0): ?><option value="paypal" <?php echo $paymentFilter === 'paypal' ? 'selected' : ''; ?>>💳 PayPal (<?php echo $paypalCount; ?>)</option><?php endif; ?>
                        <?php if ($payzyCount > 0): ?><option value="payzy" <?php echo $paymentFilter === 'payzy' ? 'selected' : ''; ?>>📱 Payzy (<?php echo $payzyCount; ?>)</option><?php endif; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Sort By:</label>
                    <select class="status-select" onchange="window.location.href='orders.php?search=<?php echo urlencode($searchQuery); ?>&status=<?php echo urlencode($statusFilter); ?>&payment=<?php echo urlencode($paymentFilter); ?>&sort=' + this.value">
                        <option value="desc" <?php echo $sortFilter === 'desc' ? 'selected' : ''; ?>>🆕 Newest to Oldest (Default)</option>
                        <option value="asc" <?php echo $sortFilter === 'asc' ? 'selected' : ''; ?>>📅 Oldest to Newest</option>
                    </select>
                </div>

                <div class="filter-group" style="flex: 1; min-width: 200px;">
                    <label>Search:</label>
                    <form method="GET" action="orders.php" class="search-form" style="display: flex; gap: 0.5rem; width: 100%;">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                        <input type="hidden" name="payment" value="<?php echo htmlspecialchars($paymentFilter); ?>">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortFilter); ?>">
                        <input type="text" name="search" class="form-input" placeholder="Search" value="<?php echo htmlspecialchars($searchQuery); ?>" style="margin-bottom: 0; min-height: 40px; padding: 0.5rem 1rem; flex: 1;">
                        <button type="submit" class="btn-primary" style="padding: 0 1rem; margin: 0; min-height: 40px; white-space: nowrap;">🔍 Search</button>
                    </form>
                </div>
            </div>
            <form id="bulk-action-form" method="POST" action="orders.php">
                <input type="hidden" name="action" value="bulk_action">
                
                <!-- Bulk actions bar (shows only when checkboxes are ticked) -->
                <div class="bulk-action-bar" id="bulk-action-bar" style="display: none; align-items: center; justify-content: space-between; background: #eff6ff; border: 1.5px dashed #93c5fd; border-radius: 16px; padding: 1rem 1.5rem; margin-bottom: 1.8rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="font-weight: 600; font-size: 0.95rem; color: #1e293b;">
                        Selected: <span id="selected-count" style="color: var(--accent-orange); font-weight: 800;">0</span> orders
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <select name="bulk_act" class="status-select" style="min-width: 220px; padding: 0.5rem; background: #ffffff; color: #0f172a; border: 1.5px solid #cbd5e1; border-radius: 8px;" required>
                            <option value="pending" selected>⏳ Mark as Pending (Default)</option>
                            <option value="processing">⚙️ Mark as Processing</option>
                            <option value="shipped">🚚 Mark as Shipped</option>
                            <option value="delivered">📦 Mark as Delivered</option>
                            <option value="completed">✅ Mark as Completed</option>
                            <option value="cancelled">✕ Mark as Cancelled</option>
                            <option value="delete">🗑️ Delete Selected Orders</option>
                        </select>
                        <button type="submit" class="btn-small btn-view" style="padding: 0.5rem 1rem;" onclick="return confirmBulk(event)">Apply Action</button>
                    </div>
                </div>

                <div class="admin-orders-wrapper">
                    <div class="admin-orders-header">
                        <div class="oc-checkbox">
                            <label class="checkbox-container">
                                <input type="checkbox" id="select-all">
                                <span class="checkmark"></span>
                            </label>
                        </div>
                        <div>Order Details</div>
                        <div>Payment & Status</div>
                        <div>Fulfillment</div>
                        <div>Actions</div>
                    </div>
                    
                    <div class="admin-orders-list">
                        <?php if (empty($orders)): ?>
                            <div style="text-align: center; padding: 3rem; color: var(--text-muted); background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0;">No orders found.</div>
                        <?php else: ?>
                            <?php foreach ($orders as $o): ?>
                                <div class="order-card">
                                    <div class="order-card-desktop">
                                        <div class="oc-col oc-checkbox">
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="order_ids[]" value="<?php echo $o['id']; ?>" class="order-checkbox">
                                                <span class="checkmark"></span>
                                            </label>
                                        </div>
                                        
                                        <div class="oc-col oc-info">
                                            <div class="oc-id">#<?php echo str_pad($o['id'], 6, '0', STR_PAD_LEFT); ?></div>
                                            <div class="oc-date">📅 <?php echo date('M d, Y g:i A', strtotime($o['created_at'])); ?></div>
                                            <div class="oc-customer">
                                                <strong>👤 <?php echo htmlspecialchars($o['customer_name'] ?? 'Guest Customer'); ?></strong><br>
                                                📞 <span style="color: #64748b;"><?php echo htmlspecialchars($o['customer_phone'] ?? 'N/A'); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="oc-col oc-fin">
                                            <div style="display:flex; align-items:center; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                                                <span class="badge" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0;">💳 <?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $o['payment_method'] ?? 'N/A'))); ?></span>
                                                <div class="oc-fin-total">LKR <?php echo number_format($o['total_price'], 2); ?></div>
                                            </div>
                                            <div style="margin-top:0.8rem;">
                                                <?php
                                                $ps = $o['payment_status'] ?? 'pending';
                                                $psStyles = [
                                                    'verified'              => 'background:#dcfce7;color:#16a34a;border:1px solid #86efac;',
                                                    'awaiting_verification' => 'background:#ecfeff;color:#0891b2;border:1px solid #a5f3fc;',
                                                    'rejected'              => 'background:#fde2e2;color:#dc2626;border:1px solid #fca5a5;',
                                                    'payment_not_required'  => 'background:#e0f2fe;color:#0284c7;border:1px solid #7dd3fc;',
                                                ];
                                                $psLabels = [
                                                    'verified'              => '✅ Verified',
                                                    'awaiting_verification' => '⏳ Awaiting Verification',
                                                    'rejected'              => '❌ Rejected',
                                                    'payment_not_required'  => '💵 COD / Pay at Delivery',
                                                ];
                                                $psBadgeStyle = $psStyles[$ps] ?? 'background:#f1f5f9;color:#64748b;border:1px solid #cbd5e1;';
                                                $psBadgeLabel = $psLabels[$ps] ?? '⏳ Pending';
                                                ?>
                                                <span class="badge" style="<?php echo $psBadgeStyle; ?>font-size:0.75rem;padding:0.4rem 0.8rem;border-radius:6px;font-weight:700; display:inline-block; margin-bottom:0.4rem;"><?php echo $psBadgeLabel; ?></span>
                                                
                                                <?php if (!empty($o['transaction_id']) || !empty($o['payment_slip'])): ?>
                                                    <div style="display:flex; flex-direction:column; gap:0.4rem;">
                                                        <?php if (!empty($o['transaction_id'])): ?>
                                                            <div style="font-size:0.75rem; color:#475569; line-height:1.3;" title="Transaction ID">
                                                                🔑 <span style="color:#2563eb; font-family:monospace; font-weight:700; word-break:break-all;"><?php echo htmlspecialchars($o['transaction_id']); ?></span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($o['payment_slip'])): ?>
                                                            <div>
                                                                <a href="../<?php echo htmlspecialchars($o['payment_slip']); ?>" target="_blank" style="font-size:0.75rem; color:#16a34a; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem; background:#dcfce7; padding:0.3rem 0.6rem; border-radius:6px; border:1px solid #bbf7d0;">
                                                                    📎 View Uploaded Slip
                                                                </a>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="oc-col oc-status">
                                            <div style="font-size:0.75rem; font-weight:700; color:#64748b; margin-bottom:0.4rem; text-transform:uppercase;">Order Status:</div>
                                            <select class="status-select" style="width:100%; border:1.5px solid #cbd5e1; font-weight:700; padding:0.6rem; border-radius:8px;" onchange="updateSingleOrderStatus(<?php echo $o['id']; ?>, this.value)">
                                                <option value="pending" <?php echo strtolower($o['status']) === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                                                <option value="processing" <?php echo strtolower($o['status']) === 'processing' ? 'selected' : ''; ?>>⚙️ Processing</option>
                                                <option value="shipped" <?php echo strtolower($o['status']) === 'shipped' ? 'selected' : ''; ?>>🚚 Shipped</option>
                                                <option value="delivered" <?php echo strtolower($o['status']) === 'delivered' ? 'selected' : ''; ?>>📦 Delivered</option>
                                                <option value="completed" <?php echo strtolower($o['status']) === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                                                <option value="cancelled" <?php echo strtolower($o['status']) === 'cancelled' ? 'selected' : ''; ?>>✕ Cancelled</option>
                                            </select>
                                        </div>
                                        
                                        <div class="oc-col oc-actions">
                                            <div style="display:flex; gap:0.5rem; width:100%;">
                                                <button type="button" class="btn-small btn-view" style="flex:1; justify-content:center; padding:0.5rem;" data-order="<?php echo htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8'); ?>" onclick="viewOrderDetails(<?php echo $o['id']; ?>, JSON.parse(this.getAttribute('data-order')))">👁️ View</button>
                                                <button type="button" class="btn-small btn-delete" style="flex:1; justify-content:center; padding:0.5rem;" onclick="deleteSingleOrder(<?php echo $o['id']; ?>)" title="Delete Order">🗑️ Delete</button>
                                            </div>
                                            
                                            <?php $pm = $o['payment_method'] ?? 'cod'; $canVerify = in_array($pm, ['bank_transfer','crypto','paypal']) && ($ps !== 'verified'); ?>
                                            <?php if ($canVerify): ?>
                                                <div style="width:100%; height:1px; background:#e2e8f0; margin:0.4rem 0;"></div>
                                                <div style="display:flex; gap:0.5rem; width:100%;">
                                                    <button type="button" class="btn-small" style="background:#dcfce7;color:#16a34a;border:1px solid #86efac; flex:1; justify-content:center; padding:0.5rem;" onclick="paymentAction(<?php echo $o['id']; ?>,'approve')" title="Approve">✅ Approve</button>
                                                    <button type="button" class="btn-small" style="background:#fde2e2;color:#dc2626;border:1px solid #fca5a5; flex:1; justify-content:center; padding:0.5rem;" onclick="paymentAction(<?php echo $o['id']; ?>,'reject')" title="Reject">❌ Reject</button>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div id="orderModal" class="modal" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button class="close-btn" onclick="document.getElementById('orderModal').style.display='none'">&times;</button>
            <div id="modalContent">
                <!-- Dynamically populated -->
            </div>
        </div>
    </div>

    <!-- Single form helper to perform individual row actions to avoid nested HTML forms -->
    <form id="row-action-form" method="POST" style="display: none;">
        <input type="hidden" name="action" id="row-action">
        <input type="hidden" name="order_id" id="row-order-id">
        <input type="hidden" name="status" id="row-status">
    </form>

    <script>
        const orderItemsMap = <?php echo json_encode($order_items_map); ?>;

        function resolveProductImg(img) {
            if (!img || img === 'placeholder.jpg') return '../uploads/placeholder.jpg';
            if (img.startsWith('http://') || img.startsWith('https://')) return img;
            if (img.startsWith('../')) return img;
            if (img.startsWith('uploads/')) return '../' + img;
            return '../uploads/' + img;
        }

        function viewOrderDetails(orderId, orderData) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('modalContent');
            
            const items = orderItemsMap[orderId] || [];
            let itemsHtml = '';
            
            items.forEach(item => {
                const imgSrc = resolveProductImg(item.product_image);
                const warranty = item.warranty ? item.warranty : 'No Warranty';
                const total = parseFloat(item.price) * parseInt(item.quantity);
                itemsHtml += `
                    <div class="item-row">
                        <div class="item-info">
                            <img src="${imgSrc}" class="item-img" alt="${item.product_name || 'Product'}" onerror="this.onerror=null; this.src='../uploads/placeholder.jpg';">
                            <div class="item-meta">
                                <div class="item-name">${item.product_name || 'Product'}</div>
                                <div class="item-warranty">🛡️ Warranty: ${warranty}</div>
                            </div>
                        </div>
                        <div class="item-pricing">
                            <strong>LKR ${parseFloat(item.price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong> x ${item.quantity}<br>
                            <span style="color: #ea580c; font-weight: 700;">LKR ${total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                        </div>
                    </div>
                `;
            });

            const shippingFee = parseFloat(orderData.shipping_fee || 0);
            const shippingHtml = shippingFee > 0 
                ? `LKR ${shippingFee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`
                : 'Free Shipping';
            
            const subtotal = parseFloat(orderData.total_price) - shippingFee;

            content.innerHTML = `
                <div class="modal-title">Order Details: #000${orderId}</div>
                
                <div class="modal-section">
                    <div class="modal-section-title">Customer & Shipping Info</div>
                    <div class="details-grid">
                        <div>
                            <strong>Billed To:</strong><br>
                            ${(orderData.first_name || orderData.last_name) ? ((orderData.first_name||'') + ' ' + (orderData.last_name||'')).trim() : (orderData.customer_name || 'Guest Customer')}<br>
                            📞 ${orderData.phone || orderData.customer_phone || 'N/A'}<br>
                            ✉️ ${orderData.email || orderData.customer_email || 'N/A'}
                        </div>
                        <div>
                            <strong>Delivery Address:</strong><br>
                            ${orderData.address_version == '2' ? 
                                `${orderData.address_line_1 || 'N/A'}${orderData.address_line_2 ? '<br>' + orderData.address_line_2 : ''}<br>
                                 ${orderData.city || 'N/A'}, ${orderData.state_province_region || 'N/A'} ${orderData.zip || ''}<br>
                                 ${orderData.country || ''}`
                                : 
                                `${orderData.address || 'N/A'},<br>
                                 ${orderData.city || 'N/A'}, ${orderData.district || 'N/A'} (${orderData.zip || 'N/A'})`
                            }
                        </div>
                    </div>
                    ${orderData.order_notes ? `
                        <div style="margin-top: 1rem; padding: 0.9rem 1.1rem; background: rgba(255, 94, 0, 0.05); border: 1.5px dashed rgba(255, 94, 0, 0.3); border-radius: 10px;">
                            <strong style="color: #ea580c; display: flex; align-items: center; gap: 0.4rem; font-size: 0.92rem;">📝 Order Notes / Special Instructions:</strong>
                            <div style="margin-top: 0.4rem; font-size: 0.9rem; color: #1e293b; white-space: pre-wrap; line-height: 1.5; font-weight: 500;">${orderData.order_notes.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</div>
                        </div>
                    ` : ''}
                </div>

                <div class="modal-section" style="background: #f8fafc; padding: 1.2rem; border-radius: 12px; border: 1.5px solid #e2e8f0;">
                    <div class="modal-section-title" style="margin-bottom: 0.8rem;">💳 Payment & Verification Details</div>
                    <div class="details-grid">
                        <div>
                            <strong>Payment Method:</strong><br>
                            <span class="badge" style="margin-top: 0.3rem; display: inline-block;">${(orderData.payment_method || 'COD').toUpperCase().replace(/_/g, ' ')}</span>
                        </div>
                        <div>
                            <strong>Payment Status:</strong><br>
                            <span style="margin-top: 0.3rem; display: inline-block; font-weight: 700; font-size: 0.85rem;">
                                ${(orderData.payment_status === 'verified') ? '✅ Verified' : 
                                  (orderData.payment_status === 'awaiting_verification') ? '⏳ Awaiting Verification' : 
                                  (orderData.payment_status === 'rejected') ? '❌ Rejected' : 
                                  (orderData.payment_status === 'payment_not_required') ? '💵 Pay at Delivery' : '⏳ Pending'}
                            </span>
                        </div>
                    </div>
                    ${orderData.transaction_id ? `
                        <div style="margin-top: 1rem; padding: 0.8rem 1rem; background: #eff6ff; border: 1.5px solid #bfdbfe; border-radius: 8px;">
                            <strong style="color: #1e40af; font-size: 0.9rem;">🔑 Transaction ID / Reference:</strong>
                            <div style="font-family: monospace; font-size: 1rem; color: #1d4ed8; font-weight: 700; margin-top: 0.3rem; word-break: break-all;">
                                ${orderData.transaction_id.replace(/</g, "&lt;").replace(/>/g, "&gt;")}
                            </div>
                        </div>
                    ` : '<div style="margin-top: 0.8rem; font-size: 0.85rem; color: #64748b; font-style: italic;">No Transaction ID submitted.</div>'}
                    ${(function(){
                        if (orderData.payment_slip) {
                            const ext = orderData.payment_slip.split('.').pop().toLowerCase();
                            const isImg = ['jpg','jpeg','png','webp'].includes(ext);
                            return `
                                <div style="margin-top: 1rem; padding: 1rem; background: #f0fdf4; border: 1.5px solid #bbf7d0; border-radius: 10px;">
                                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.8rem;">
                                        <div>
                                            <strong style="color: #166534; font-size: 0.92rem;">📎 Uploaded Payment Slip:</strong>
                                            <div style="font-size: 0.8rem; color: #15803d; margin-top: 0.2rem;">Proof document submitted for verification</div>
                                        </div>
                                        <a href="../${orderData.payment_slip}" target="_blank" class="btn-small" style="background: #16a34a; color: #ffffff; text-decoration: none; padding: 0.5rem 1rem; font-weight: 700; border-radius: 8px;">🔍 View Full Slip</a>
                                    </div>
                                    ${isImg ? `
                                        <div style="margin-top: 0.8rem; text-align: center;">
                                            <a href="../${orderData.payment_slip}" target="_blank">
                                                <img src="../${orderData.payment_slip}" alt="Payment Slip" style="max-width: 100%; max-height: 240px; border-radius: 8px; border: 1.5px solid #86efac; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
                                            </a>
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                        } else if (orderData.payment_method !== 'cod') {
                            return `
                                <div style="margin-top: 1rem; padding: 1rem; background: #fffbeb; border: 1.5px dashed #fcd34d; border-radius: 10px;">
                                    <div style="color: #b45309; font-weight: 700; font-size: 0.9rem; margin-bottom: 0.3rem;">⚠️ No Payment Slip Uploaded</div>
                                    <div style="font-size: 0.82rem; color: #78350f; margin-bottom: 0.8rem;">Upload or attach a payment receipt/bank slip image for this order:</div>
                                    <form method="POST" action="orders.php" enctype="multipart/form-data" style="display: flex; gap: 0.6rem; flex-wrap: wrap; align-items: center;">
                                        <input type="hidden" name="action" value="upload_admin_slip">
                                        <input type="hidden" name="order_id" value="${orderId}">
                                        <input type="file" name="admin_slip" accept=".jpg,.jpeg,.png,.webp,.pdf" style="font-size: 0.82rem; background: #fff; padding: 0.3rem; border: 1px solid #fcd34d; border-radius: 6px;" required>
                                        <button type="submit" class="btn-small" style="background: #2563eb; color: #fff; padding: 0.45rem 1rem; border-radius: 6px; font-weight: 700; border: none; cursor: pointer;">📤 Attach Slip</button>
                                    </form>
                                </div>
                            `;
                        }
                        return '';
                    })()}
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">Items Ordered</div>
                    <div class="items-list">
                        ${itemsHtml ? itemsHtml : '<div style="color: var(--text-muted); text-align: center; padding: 1rem;">No items found.</div>'}
                    </div>
                </div>

                <div class="modal-section" style="border-top: 1px solid var(--border-color); padding-top: 1.5rem; margin-bottom: 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.95rem; color: var(--text-muted);">
                        <span>Subtotal</span>
                        <span style="color: var(--text-main); font-weight: 600;">LKR ${subtotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.8rem; font-size: 0.95rem; color: var(--text-muted);">
                        <span>Delivery Fee</span>
                        <span style="color: ${shippingFee > 0 ? 'var(--text-main)' : '#10b981'}; font-weight: 600;">${shippingHtml}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 1.3rem; font-weight: 800; border-top: 1px dashed var(--border-color); padding-top: 0.8rem; color: var(--text-main);">
                        <span>Grand Total</span>
                        <span style="color: #ff5e00;">LKR ${parseFloat(orderData.total_price).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    </div>
                </div>
            `;
            
            modal.style.display = 'flex';
        }

        function closeModal(e) {
            const modal = document.getElementById('orderModal');
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        }

        function updateSingleOrderStatus(orderId, status) {
            document.getElementById('row-action').value = 'update_status';
            document.getElementById('row-order-id').value = orderId;
            document.getElementById('row-status').value = status;
            document.getElementById('row-action-form').submit();
        }

        function deleteSingleOrder(orderId) {
            if (confirm('Are you sure you want to delete this order? This cannot be undone.')) {
                document.getElementById('row-action').value = 'delete';
                document.getElementById('row-order-id').value = orderId;
                document.getElementById('row-action-form').submit();
            }
        }

        // Bulk action UI and events handling
        const selectAllCheckbox = document.getElementById('select-all');
        const orderCheckboxes = document.querySelectorAll('.order-checkbox');
        const bulkActionBar = document.getElementById('bulk-action-bar');
        const selectedCountSpan = document.getElementById('selected-count');

        function toggleBulkBar() {
            const checkedCount = document.querySelectorAll('.order-checkbox:checked').length;
            if (checkedCount > 0) {
                bulkActionBar.style.display = 'flex';
                selectedCountSpan.textContent = checkedCount;
            } else {
                bulkActionBar.style.display = 'none';
            }
        }

        selectAllCheckbox.addEventListener('change', function() {
            orderCheckboxes.forEach(cb => {
                cb.checked = selectAllCheckbox.checked;
            });
            toggleBulkBar();
        });

        orderCheckboxes.forEach(cb => {
            cb.addEventListener('change', function() {
                if (!cb.checked) {
                    selectAllCheckbox.checked = false;
                } else {
                    const allChecked = Array.from(orderCheckboxes).every(c => c.checked);
                    selectAllCheckbox.checked = allChecked;
                }
                toggleBulkBar();
            });
        });

        function confirmBulk(event) {
            const bulkAction = document.getElementsByName('bulk_act')[0].value;
            if (!bulkAction) {
                alert('Please select a bulk action first.');
                event.preventDefault();
                return false;
            }
            if (bulkAction === 'delete') {
                return confirm('Are you sure you want to delete all selected orders? This cannot be undone.');
            }
            return true;
        }

        function paymentAction(orderId, action) {
            const label = action === 'approve' ? 'approve' : 'reject';
            if (!confirm('Are you sure you want to ' + label + ' this payment?')) return;
            const fd = new FormData();
            fd.append('order_id', orderId);
            fd.append('action', action);
            fetch('payment_action.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(() => alert('Request failed. Please try again.'));
        }
    </script>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <script>
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function toggleMenu() {
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                overlay.style.display = 'block';
            } else {
                overlay.style.display = 'none';
            }
        }

        menuToggle.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    </script>
</body>
</html>
