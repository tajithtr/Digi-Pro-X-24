<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'superadmin')) {
    file_put_contents(__DIR__ . '/../debug_log.txt', date('Y-m-d H:i:s') . " - [admin/index.php] Redirecting to login. User ID: " . ($_SESSION['user_id'] ?? 'NOT_SET') . ", Role: " . ($_SESSION['role'] ?? 'NOT_SET') . ", Cookie: " . ($_COOKIE[session_name()] ?? 'NO_COOKIE') . "\n", FILE_APPEND);
    header("Location: ../login.php");
    exit;
}

require_once '../config.php';

// Fetch counts
$prodCount = $pdo->query("SELECT COUNT(*) FROM products WHERE product_type != 'local_service'")->fetchColumn();
$serviceCount = $pdo->query("SELECT COUNT(*) FROM products WHERE product_type = 'local_service'")->fetchColumn();
$catCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$customerCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('admin', 'superadmin')")->fetchColumn();
$orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$msgCount = 0;
$unreadMsgCount = 0;
try {
    $msgCount = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();
    $unreadMsgCount = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'")->fetchColumn();
} catch (PDOException $e) {}

// Fetch recent products
$stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.product_type != 'local_service' ORDER BY p.id DESC LIMIT 5");
$recentProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent services
$stmtService = $pdo->query("SELECT * FROM products WHERE product_type = 'local_service' ORDER BY id DESC LIMIT 5");
$recentServices = $stmtService->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Digi Pro X 24</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
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
                    <span style="color: var(--text-muted); font-weight:600; font-size:0.9rem;">Overview</span>
                    <h1>Dashboard</h1>
                </div>
            </div>
            <div class="header-user-badge">
                Logged in as: <span style="color: var(--primary-glow, #3b82f6); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <div class="stats-grid">
            <a href="products.php" class="stat-card">
                <div class="stat-info">
                    <h3>Total Products</h3>
                    <p><?php echo $prodCount; ?></p>
                    <span style="font-size: 0.8rem; color: var(--primary-glow, #3b82f6); font-weight: 600;">Manage Products ➔</span>
                </div>
                <div class="stat-icon">🛍️</div>
            </a>
            <a href="categories.php" class="stat-card">
                <div class="stat-info">
                    <h3>Categories</h3>
                    <p><?php echo $catCount; ?></p>
                    <span style="font-size: 0.8rem; color: var(--primary-glow, #3b82f6); font-weight: 600;">Manage Categories ➔</span>
                </div>
                <div class="stat-icon">📁</div>
            </a>
            <a href="users.php?tab=customer" class="stat-card">
                <div class="stat-info">
                    <h3>Registered Users</h3>
                    <p><?php echo $customerCount; ?></p>
                    <span style="font-size: 0.8rem; color: var(--primary-glow, #3b82f6); font-weight: 600;">Manage Users ➔</span>
                </div>
                <div class="stat-icon">👥</div>
            </a>
            <a href="users.php?tab=admin" class="stat-card">
                <div class="stat-info">
                    <h3>Admin Users</h3>
                    <p><?php echo $adminCount; ?></p>
                    <span style="font-size: 0.8rem; color: var(--primary-glow, #3b82f6); font-weight: 600;">Manage Admins ➔</span>
                </div>
                <div class="stat-icon">🛡️</div>
            </a>
            <a href="orders.php" class="stat-card">
                <div class="stat-info">
                    <h3>Total Orders</h3>
                    <p><?php echo $orderCount; ?></p>
                    <span style="font-size: 0.8rem; color: var(--primary-glow, #3b82f6); font-weight: 600;">Manage Orders ➔</span>
                </div>
                <div class="stat-icon">📦</div>
            </a>
            <a href="messages.php" class="stat-card">
                <div class="stat-info">
                    <h3>Contact Messages</h3>
                    <p><?php echo $msgCount; ?> <?php if($unreadMsgCount > 0): ?><span style="font-size:0.8rem; color:#dc2626;">(<?php echo $unreadMsgCount; ?> new)</span><?php endif; ?></p>
                    <span style="font-size: 0.8rem; color: var(--primary-glow, #3b82f6); font-weight: 600;">View Messages ➔</span>
                </div>
                <div class="stat-icon">💬</div>
            </a>
        </div>

        <div class="dashboard-section">
            <div class="section-title">
                <span>Recent Products Added</span>
                <a href="products.php" class="btn-small btn-add" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none;">Manage Products ➔</a>
            </div>
            <div class="admin-table-wrapper">
                <table class="admin-table responsive-cards">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price (LKR)</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentProducts)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-muted);">No products found in database.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentProducts as $p): ?>
                                <tr>
                                    <td class="td-id">#<?php echo $p['id']; ?></td>
                                    <td class="td-info" style="font-weight: 600;"><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td class="td-category"><span class="badge"><?php echo htmlspecialchars($p['category_name']); ?></span></td>
                                    <td class="td-price">LKR <?php echo number_format($p['price'], 2); ?></td>
                                    <td class="td-stock"><?php echo $p['stock']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-section" style="margin-top: 2rem;">
            <div class="section-title">
                <span>Recent Services Added</span>
                <a href="services.php" class="btn-small btn-add" style="padding: 0.5rem 1rem; font-size: 0.85rem; text-decoration: none;">Manage Services ➔</a>
            </div>
            <div class="admin-table-wrapper">
                <table class="admin-table responsive-cards">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service Name</th>
                            <th>Price (LKR)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentServices)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: var(--text-muted);">No services found in database.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentServices as $s): ?>
                                <tr>
                                    <td class="td-id">#<?php echo $s['id']; ?></td>
                                    <td class="td-info" style="font-weight: 600;"><?php echo htmlspecialchars($s['name']); ?></td>
                                    <td class="td-price" style="font-weight: 700; color: #ea580c;">LKR <?php echo number_format($s['price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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