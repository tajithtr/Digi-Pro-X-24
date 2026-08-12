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

$success = '';
$error = '';

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['product_ids'])) {
    $action = $_POST['bulk_action'];
    $ids = array_map('intval', (array)$_POST['product_ids']);
    
    try {
        if ($action === 'delete') {
            $deleted_count = 0;
            $disabled_count = 0;
            
            $chkStmt = $pdo->prepare("SELECT (SELECT COUNT(*) FROM order_items WHERE product_id = ?) + (SELECT COUNT(*) FROM service_requests WHERE service_id = ?) AS total");
            foreach ($ids as $p_id) {
                $chkStmt->execute([$p_id, $p_id]);
                $has_orders = ($chkStmt->fetchColumn() > 0);
                
                if ($has_orders) {
                    $stmt = $pdo->prepare("UPDATE products SET is_disabled = 1 WHERE id = ?");
                    $stmt->execute([$p_id]);
                    $disabled_count++;
                } else {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                        $stmt->execute([$p_id]);
                        $deleted_count++;
                    } catch (PDOException $e) {
                        $stmt = $pdo->prepare("UPDATE products SET is_disabled = 1 WHERE id = ?");
                        $stmt->execute([$p_id]);
                        $disabled_count++;
                    }
                }
            }
            $msg = [];
            if ($deleted_count > 0) $msg[] = "$deleted_count product(s) deleted successfully.";
            if ($disabled_count > 0) $msg[] = "$disabled_count product(s) have customer order history, so they were disabled & archived instead to preserve order history.";
            $success = implode(" ", $msg);
        } elseif ($action === 'disable') {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE products SET is_disabled = 1 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $success = count($ids) . " products disabled.";
        } elseif ($action === 'enable') {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE products SET is_disabled = 0 WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $success = count($ids) . " products enabled.";
        }
    } catch (PDOException $e) {
        $error = "Bulk action failed: " . $e->getMessage();
    }
}

// Handle Single Delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    try {
        $chkStmt = $pdo->prepare("SELECT (SELECT COUNT(*) FROM order_items WHERE product_id = ?) + (SELECT COUNT(*) FROM service_requests WHERE service_id = ?) AS total");
        $chkStmt->execute([$delete_id, $delete_id]);
        $has_orders = ($chkStmt->fetchColumn() > 0);
        
        if ($has_orders) {
            $stmt = $pdo->prepare("UPDATE products SET is_disabled = 1 WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "This product has existing customer orders, so it cannot be permanently deleted. It has been disabled & archived instead to preserve order records.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "Product deleted successfully.";
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000' || strpos($e->getMessage(), '1451') !== false) {
            $stmt = $pdo->prepare("UPDATE products SET is_disabled = 1 WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = "This product has existing customer orders, so it cannot be permanently deleted. It has been disabled & archived instead to preserve order records.";
        } else {
            $error = "Error deleting product: " . $e->getMessage();
        }
    }
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;

// Count total products (excluding services)
$countStmt = $pdo->query("SELECT COUNT(*) FROM products WHERE product_type != 'local_service'");
$totalProducts = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

// Fetch paginated products
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.product_type != 'local_service' ORDER BY p.id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Digi Pro X 24 Admin</title>
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
                    <span style="color: var(--text-muted); font-weight:600; font-size:0.9rem;">Catalog Setup</span>
                    <h1>Manage Products</h1>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <a href="product_add.php" class="btn-add">➕ Add Product</a>
                <div class="header-user-badge">
                    Logged in as: <span style="color: var(--primary-glow, #3b82f6); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
                </div>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="msg-banner success-banner"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="msg-banner error-banner"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="products.php?page=<?php echo $page; ?>" method="POST" id="bulk-actions-form">
            <div class="hide-on-mobile" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; gap: 0.8rem; align-items: center; flex-wrap: wrap;">
                    <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Bulk Actions:</span>
                    <button type="submit" name="bulk_action" value="disable" style="background: #fef9c3; display: inline-block; transition: all 0.3s; color: #854d0e; border: 1px solid #fde047; padding: 0.5rem 1.2rem; font-size: 0.85rem; font-weight: 700; margin-right: 0; border-radius: 8px; cursor: pointer;">🚫 Disable Selected</button>
                    <button type="submit" name="bulk_action" value="enable" style="background: #dcfce7; display: inline-block; transition: all 0.3s; color: #166534; border: 1px solid #86efac; padding: 0.5rem 1.2rem; font-size: 0.85rem; font-weight: 700; margin-right: 0; border-radius: 8px; cursor: pointer;">✨ Enable Selected</button>
                    <button type="submit" name="bulk_action" value="delete" style="background: #fee2e2; display: inline-block; transition: all 0.3s; color: #dc2626; border: 1px solid #fca5a5; padding: 0.5rem 1.2rem; font-size: 0.85rem; font-weight: 700; margin-right: 0; border-radius: 8px; cursor: pointer;" onclick="return confirm('Are you sure you want to delete the selected products? All variants associated with them will also be deleted.');">🗑️ Delete Selected</button>
                </div>
            </div>

            <div class="panel-box">
                <div class="admin-table-wrapper">
                    <table class="admin-table responsive-cards">
                        <thead>
                            <tr>
                                <th style="width: 40px; text-align: center;"><input type="checkbox" id="select-all" style="width: 1.1rem; height: 1.1rem; cursor: pointer; accent-color: var(--accent-orange);"></th>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Base Price</th>
                                <th>Discount</th>
                                <th>Final Price</th>
                                <th>Stock</th>
                                <th style="text-align: right; white-space: nowrap;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2rem;">No products found in the database. Add your first product above!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): 
                                    $original_price_lkr = $p['price'];
                                    $discount = $p['discount_percent'] ?? 0;
                                    $current_price_lkr = $original_price_lkr * (1 - ($discount / 100));
                                    $imgSrc = get_product_image_url($p['image']);
                                    if (strpos($imgSrc, 'http') !== 0) $imgSrc = '../' . ltrim($imgSrc, '/');
                                ?>
                                    <tr>
                                        <td class="td-checkbox" style="text-align: center;">
                                            <input type="checkbox" name="product_ids[]" value="<?php echo $p['id']; ?>" class="product-checkbox" style="width: 1.1rem; height: 1.1rem; cursor: pointer; accent-color: var(--accent-orange);">
                                        </td>
                                        <td class="td-image">
                                            <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="product-thumb">
                                        </td>
                                        <td class="td-info">
                                            <div style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                                <?php echo htmlspecialchars($p['name']); ?>
                                                <?php if (isset($p['is_trending']) && $p['is_trending']): ?>
                                                    <span class="badge" style="background: #fef9c3; color: #854d0e; border: 1px solid #fde047; font-size: 0.7rem; padding: 0.15rem 0.5rem;">🔥 Trending</span>
                                                <?php endif; ?>
                                                <?php if (isset($p['is_new_arrival']) && $p['is_new_arrival']): ?>
                                                    <span class="badge" style="background: #dcfce7; color: #166534; border: 1px solid #86efac; font-size: 0.7rem; padding: 0.15rem 0.5rem;">✨ New</span>
                                                <?php endif; ?>
                                                <?php if (isset($p['is_disabled']) && $p['is_disabled']): ?>
                                                    <span class="badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.7rem; padding: 0.15rem 0.5rem;">🚫 Disabled</span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem; max-width: 200px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;"><?php echo htmlspecialchars($p['description']); ?></div>
                                            <?php if (!empty($p['warranty'])): ?>
                                                <div style="font-size: 0.75rem; color: #d97706; margin-top: 0.25rem; display: flex; align-items: center; gap: 0.25rem; font-weight: 500;">
                                                    <span>🛡️</span> <span><?php echo htmlspecialchars($p['warranty']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="td-category"><span class="badge"><?php echo htmlspecialchars($p['category_name']); ?></span></td>
                                        <td class="td-base-price" style="white-space: nowrap;">LKR <?php echo number_format($original_price_lkr, 2); ?></td>
                                        <td class="td-discount">
                                            <?php if ($discount > 0): ?>
                                                <span class="discount-badge" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700;"><?php echo $discount; ?>% OFF</span>
                                            <?php else: ?>
                                                <span style="color: var(--text-light); font-size: 0.85rem;">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="td-price" style="font-weight: 700; color: #ea580c;">
                                            LKR <?php echo number_format($current_price_lkr, 2); ?>
                                        </td>
                                        <td class="td-stock">
                                            <?php if ($p['stock'] > 0): ?>
                                                <?php echo $p['stock']; ?>
                                            <?php else: ?>
                                                <span style="color: #dc2626; font-weight: 700;">Out of Stock</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="td-actions action-links" style="text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end; white-space: nowrap;">
                                            <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="btn-small btn-edit">✏️ Edit</a>
                                            <a href="products.php?delete=<?php echo $p['id']; ?>" class="btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this product? All variants associated with it will also be deleted.');">🗑️ Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination" style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" style="display: inline-block; transition: all 0.3s; padding: 0.5rem 1.2rem; font-size: 0.9rem; margin-right: 0; border-radius: 8px; text-decoration: none; background: #ffffff; color: #334155; border: 1px solid #cbd5e1;">Previous</a>
                <?php endif; ?>
                
                <span style="display: flex; align-items: center; padding: 0 1rem; font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" style="display: inline-block; transition: all 0.3s; padding: 0.5rem 1.2rem; font-size: 0.9rem; margin-right: 0; border-radius: 8px; text-decoration: none; background: #ffffff; color: #334155; border: 1px solid #cbd5e1;">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const selectAll = document.getElementById('select-all');
                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('.product-checkbox');
                        checkboxes.forEach(cb => cb.checked = this.checked);
                    });
                }
            });
        </script>
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
