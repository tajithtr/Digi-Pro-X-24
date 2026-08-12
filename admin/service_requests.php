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

// Handle delete or status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_status' && isset($_POST['request_id'])) {
            $status = $_POST['status'] ?? 'Pending Callback';
            $stmt = $pdo->prepare("UPDATE service_requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $_POST['request_id']]);
        } elseif ($_POST['action'] === 'delete' && isset($_POST['request_id'])) {
            $stmt = $pdo->prepare("DELETE FROM service_requests WHERE id = ?");
            $stmt->execute([$_POST['request_id']]);
        } elseif ($_POST['action'] === 'bulk_action' && isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
            $order_ids = array_map('intval', $_POST['order_ids']);
            if (!empty($order_ids)) {
                $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                $bulk_act = $_POST['bulk_act'] ?? '';
                if ($bulk_act === 'delete') {
                    $stmt = $pdo->prepare("DELETE FROM service_requests WHERE id IN ($placeholders)");
                    $stmt->execute($order_ids);
                } elseif (in_array($bulk_act, ['Pending Callback', 'Called & Scheduled', 'Completed', 'Cancelled'])) {
                    $stmt = $pdo->prepare("UPDATE service_requests SET status = ? WHERE id IN ($placeholders)");
                    $stmt->execute(array_merge([$bulk_act], $order_ids));
                }
            }
        }
        header("Location: service_requests.php");
        exit;
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$validStatuses = ['Pending Callback', 'Called & Scheduled', 'Completed', 'Cancelled'];
if ($statusFilter !== 'all' && !in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

$sortFilter = $_GET['sort'] ?? 'desc';
if ($sortFilter !== 'asc' && $sortFilter !== 'desc') {
    $sortFilter = 'desc';
}
$orderDirection = ($sortFilter === 'desc') ? 'DESC' : 'ASC';

$searchQuery = trim($_GET['search'] ?? '');

$whereConditions = [];
$params = [];

if ($statusFilter !== 'all') {
    $whereConditions[] = "sr.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery !== '') {
    $whereConditions[] = "(sr.token_number LIKE ? OR sr.customer_name LIKE ? OR sr.phone_number LIKE ?)";
    $searchWildcard = '%' . $searchQuery . '%';
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

// Fetch dynamic counts
$sQuery = "SELECT status, COUNT(*) as cnt FROM service_requests GROUP BY status";
$sStmt = $pdo->query($sQuery);
$statusData = $sStmt->fetchAll(PDO::FETCH_KEY_PAIR);

$pendingCount = $statusData['Pending Callback'] ?? 0;
$scheduledCount = $statusData['Called & Scheduled'] ?? 0;
$completedCount = $statusData['Completed'] ?? 0;
$cancelledCount = $statusData['Cancelled'] ?? 0;
$totalCount = array_sum($statusData);

// Fetch service requests
$stmt = $pdo->prepare("
    SELECT sr.*, p.name as service_name, p.image as service_image
    FROM service_requests sr
    LEFT JOIN products p ON sr.service_id = p.id
    $whereClause
    ORDER BY sr.id $orderDirection
");
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Requests - Digi Pro X 24 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .service-img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border-light); vertical-align: middle; margin-right: 0.5rem; }
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
                    <h1>Service Requests</h1>
                </div>
            </div>
            <div class="header-user-badge">
                Logged in as: <span style="color: var(--primary-glow, #3b82f6); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <div class="dashboard-section">
            <div class="filter-container">
                                <div class="filter-group">
                    <label>Filter by Status:</label>
                    <select class="status-select" onchange="window.location.href='service_requests.php?search=<?php echo urlencode($searchQuery); ?>&sort=<?php echo $sortFilter; ?>&status=' + this.value">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses (<?php echo $totalCount; ?>)</option>
                        <option value="Pending Callback" <?php echo $statusFilter === 'Pending Callback' ? 'selected' : ''; ?>>⏳ Pending Callback (<?php echo $pendingCount; ?>)</option>
                        <option value="Called & Scheduled" <?php echo $statusFilter === 'Called & Scheduled' ? 'selected' : ''; ?>>📅 Scheduled (<?php echo $scheduledCount; ?>)</option>
                        <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>✅ Completed (<?php echo $completedCount; ?>)</option>
                        <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>✕ Cancelled (<?php echo $cancelledCount; ?>)</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Sort By:</label>
                    <select class="status-select" onchange="window.location.href='service_requests.php?search=<?php echo urlencode($searchQuery); ?>&status=<?php echo urlencode($statusFilter); ?>&sort=' + this.value">
                        <option value="desc" <?php echo $sortFilter === 'desc' ? 'selected' : ''; ?>>🆕 Newest to Oldest</option>
                        <option value="asc" <?php echo $sortFilter === 'asc' ? 'selected' : ''; ?>>📅 Oldest to Newest</option>
                    </select>
                </div>

                <div class="filter-group" style="flex: 1; min-width: 200px;">
                    <label>Search:</label>
                    <form method="GET" action="service_requests.php" class="search-form" style="display: flex; gap: 0.5rem; width: 100%;">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortFilter); ?>">
                        <input type="text" name="search" class="form-input" placeholder="Search" value="<?php echo htmlspecialchars($searchQuery); ?>" style="margin-bottom: 0; min-height: 40px; padding: 0.5rem 1rem; flex: 1;">
                        <button type="submit" class="btn-primary" style="padding: 0 1rem; margin: 0; min-height: 40px; white-space: nowrap;">🔍 Search</button>
                    </form>
                </div>
            </div>

            <form id="bulk-action-form" method="POST" action="service_requests.php">
                <input type="hidden" name="action" value="bulk_action">
                
                <div class="bulk-action-bar" id="bulk-action-bar" style="display: none; align-items: center; justify-content: space-between; background: #eff6ff; border: 1.5px dashed #93c5fd; border-radius: 16px; padding: 1rem 1.5rem; margin-bottom: 1.8rem; flex-wrap: wrap; gap: 1rem;">
                    <div style="font-weight: 600; font-size: 0.95rem; color: #1e293b;">
                        Selected: <span id="selected-count" style="color: var(--accent-orange); font-weight: 800;">0</span> requests
                    </div>
                    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <select name="bulk_act" class="status-select" style="min-width: 220px; padding: 0.5rem; background: #ffffff; color: #0f172a; border: 1.5px solid #cbd5e1; border-radius: 8px;" required>
                            <option value="Pending Callback" selected>⏳ Mark as Pending Callback</option>
                            <option value="Called & Scheduled">📅 Mark as Scheduled</option>
                            <option value="Completed">✅ Mark as Completed</option>
                            <option value="Cancelled">✕ Mark as Cancelled</option>
                            <option value="delete">🗑️ Delete Selected</option>
                        </select>
                        <button type="submit" class="btn-small btn-view" style="padding: 0.5rem 1rem;" onclick="return confirmBulk(event)">Apply Action</button>
                    </div>
                </div>

                <div class="admin-table-wrapper">
                    <table class="admin-table responsive-table">
                        <thead>
                            <tr>
                                <th style="width: 40px; padding: 1rem 0.5rem 1rem 1.2rem;">
                                    <label class="checkbox-container">
                                        <input type="checkbox" id="select-all">
                                        <span class="checkmark"></span>
                                    </label>
                                </th>
                                <th>Token</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--text-muted);">No service requests matching your criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td class="td-checkbox" style="padding: 1.2rem 0.5rem 1.2rem 1.2rem;">
                                            <label class="checkbox-container">
                                                <input type="checkbox" name="order_ids[]" value="<?php echo $req['id']; ?>" class="order-checkbox">
                                                <span class="checkmark"></span>
                                            </label>
                                        </td>
                                        <td data-label="Token"><span style="font-weight: 700; color: #4338ca; background: #e0e7ff; padding: 2px 6px; border-radius: 4px;"><?php echo htmlspecialchars($req['token_number']); ?></span></td>
                                        <td data-label="Customer">
                                            <strong><?php echo htmlspecialchars($req['customer_name'] ?? 'Guest Customer'); ?></strong>
                                            <br>
                                            <span style="font-size:0.8rem; color: var(--text-muted);">📱 <?php echo htmlspecialchars($req['phone_number'] ?? ''); ?></span>
                                            <?php if(!empty($req['location_address'])): ?>
                                                <br><span style="font-size:0.8rem; color: var(--text-muted);">📍 <?php echo htmlspecialchars($req['location_address']); ?></span>
                                            <?php endif; ?>
                                            <?php if(!empty($req['customer_note'])): ?>
                                                <div style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(59, 130, 246, 0.1); border-left: 2px solid #3b82f6; border-radius: 4px; font-size: 0.8rem; color: #60a5fa; max-width: 250px; white-space: normal;">
                                                    <strong>Note:</strong> <?php echo nl2br(htmlspecialchars($req['customer_note'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Date"><?php echo date('M d, Y H:i', strtotime($req['created_at'])); ?></td>
                                        <td data-label="Service">
                                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                                <?php 
                                                    $img = $req['service_image'];
                                                    if (!empty($img) && strpos($img, 'http') === false) { $img = '../' . $img; }
                                                    if (empty($img)) { $img = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="%23f1f5f9"/></svg>'; }
                                                ?>
                                                <img src="<?php echo htmlspecialchars($img); ?>" alt="Service" class="service-img" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px; flex-shrink: 0;">
                                                <strong style="line-height: 1.3; font-size: 0.9rem;"><?php echo htmlspecialchars($req['service_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td data-label="Status">
                                            <select class="status-select" onchange="updateSingleRequestStatus(<?php echo $req['id']; ?>, this.value)">
                                                <option value="Pending Callback" <?php echo $req['status'] === 'Pending Callback' ? 'selected' : ''; ?>>⏳ Pending Callback</option>
                                                <option value="Called & Scheduled" <?php echo $req['status'] === 'Called & Scheduled' ? 'selected' : ''; ?>>📅 Scheduled</option>
                                                <option value="Completed" <?php echo $req['status'] === 'Completed' ? 'selected' : ''; ?>>✅ Completed</option>
                                                <option value="Cancelled" <?php echo $req['status'] === 'Cancelled' ? 'selected' : ''; ?>>✕ Cancelled</option>
                                            </select>
                                        </td>
                                        <td data-label="Actions" class="td-actions action-forms" style="display: flex; gap: 0.5rem; justify-content: flex-end; flex-wrap: wrap;">
                                            <button type="button" class="btn-small btn-view" onclick="viewRequestDetails(<?php echo $req['id']; ?>, <?php echo htmlspecialchars(json_encode($req)); ?>)">👁️ View</button>
                                            <button type="button" class="btn-small btn-delete" onclick="deleteSingleRequest(<?php echo $req['id']; ?>)" title="Delete Request">🗑️ Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden form for JS status updates & deletes -->
    <form id="single-action-form" method="POST" action="service_requests.php" style="display: none;">
        <input type="hidden" name="action" id="row-action" value="">
        <input type="hidden" name="request_id" id="row-request-id" value="">
        <input type="hidden" name="status" id="row-status" value="">
    </form>

    <!-- Request Detail Modal -->
    <div id="requestModal" class="modal" onclick="closeModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button class="close-btn" onclick="document.getElementById('requestModal').style.display='none'">&times;</button>
            <div id="modalContent">
                <!-- Dynamically populated -->
            </div>
        </div>
    </div>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <script>
        // Modal logic
        function resolveProductImg(img) {
            if (!img || img === 'placeholder.jpg') return '../uploads/placeholder.jpg';
            if (img.startsWith('http')) return img;
            return '../' + img;
        }

                function closeModal(e) {
            const modal = document.getElementById('requestModal');
            if (e && e.target === modal) {
                modal.style.display = 'none';
            }
        }

        function viewRequestDetails(reqId, reqData) {
            const modal = document.getElementById('requestModal');
            const content = document.getElementById('modalContent');
            
            let img = reqData.service_image;
            if (!img) {
                img = '../uploads/placeholder.jpg';
            } else if (!img.startsWith('http') && !img.startsWith('../')) {
                img = '../' + img;
            }

            content.innerHTML = `
                <div class="modal-title">Service Request: ${reqData.token_number}</div>
                
                <div class="modal-section">
                    <div class="modal-section-title">Customer Details</div>
                    <div class="details-grid">
                        <div>
                            <strong>Customer Info:</strong><br>
                            👤 ${reqData.customer_name || 'Guest Customer'}<br>
                            📞 ${reqData.phone_number || 'N/A'}
                        </div>
                        <div>
                            <strong>Location Address:</strong><br>
                            📍 ${reqData.location_address || 'Not Provided'}
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">Service Requested</div>
                    <div class="item-row">
                        <div class="item-info">
                            <img src="${img}" class="item-img" alt="${reqData.service_name || 'Service'}" onerror="this.onerror=null; this.src='../uploads/placeholder.jpg';">
                            <div class="item-meta">
                                <div class="item-name">${reqData.service_name || 'Service'}</div>
                                <div class="item-warranty">Status: ${reqData.status}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-section" style="border: none; padding-bottom: 0;">
                    <div class="modal-section-title">Customer Note</div>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; font-size: 0.95rem; color: var(--text-main); border: 1px dashed #cbd5e1; white-space: pre-wrap;">${reqData.customer_note || 'No additional note provided.'}</div>
                </div>
            `;
            modal.style.display = 'flex';
        }

        // Single actions
        function updateSingleRequestStatus(id, status) {
            document.getElementById('row-action').value = 'update_status';
            document.getElementById('row-request-id').value = id;
            document.getElementById('row-status').value = status;
            document.getElementById('single-action-form').submit();
        }
        function deleteSingleRequest(id) {
            if (confirm("Are you sure you want to completely delete this service request? This cannot be undone.")) {
                document.getElementById('row-action').value = 'delete';
                document.getElementById('row-request-id').value = id;
                document.getElementById('single-action-form').submit();
            }
        }

        // Bulk action UI
        const selectAllCheckbox = document.getElementById('select-all');
        const orderCheckboxes = document.querySelectorAll('.order-checkbox');
        const bulkActionBar = document.getElementById('bulk-action-bar');
        const selectedCountSpan = document.getElementById('selected-count');

        function updateBulkBar() {
            const checkedCount = document.querySelectorAll('.order-checkbox:checked').length;
            if (checkedCount > 0) {
                bulkActionBar.style.display = 'flex';
                selectedCountSpan.textContent = checkedCount;
            } else {
                bulkActionBar.style.display = 'none';
            }
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                orderCheckboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
                updateBulkBar();
            });
            orderCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = Array.from(orderCheckboxes).every(c => c.checked);
                    selectAllCheckbox.checked = allChecked;
                    updateBulkBar();
                });
            });
        }

        function confirmBulk(e) {
            const action = document.getElementsByName('bulk_act')[0].value;
            if (action === 'delete') {
                if(!confirm("Are you sure you want to delete ALL selected requests? This cannot be undone.")) {
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        }

        // Sidebar
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebar-overlay');

        function toggleMenu() {
            sidebar.classList.toggle('open');
            overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
        }
        menuToggle.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    </script>
</body>
</html>