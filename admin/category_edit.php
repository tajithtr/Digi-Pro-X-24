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

if (!isset($_GET['id'])) {
    header("Location: categories.php");
    exit;
}

$id = (int)$_GET['id'];

// Fetch category details
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header("Location: categories.php");
    exit;
}

// Handle update category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = 'product';
    
    // Handle category image upload
    $category_image = $category['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Ensure uploads folder exists
        if (!is_dir('../uploads')) {
            mkdir('../uploads', 0777, true);
        }
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = $_FILES['image']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (in_array($file_ext, $allowed_exts) && is_valid_image($file_tmp, $file_name)) {
            $new_name = 'cat_' . uniqid() . '.' . $file_ext;
            $destination = '../uploads/' . $new_name;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                // Delete old file if exists
                if ($category['image'] && strpos($category['image'], 'uploads/') !== false) {
                    $oldPath = '../' . $category['image'];
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
                $category_image = 'uploads/' . $new_name;
            }
        }
    }
    
    if ($name !== '') {
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, image = ?, type = ? WHERE id = ?");
        $stmt->execute([$name, $category_image, $type, $id]);
        $success = "Category updated successfully.";
        
        // Refresh local data
        $category['name'] = $name;
        $category['image'] = $category_image;
        $category['type'] = $type;
    } else {
        $error = "Category name cannot be empty.";
    }
}

// Fetch all categories for the right-side list
$catStmt = $pdo->query("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY c.id ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - Digi Pro X 24 Admin</title>
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
                    <h1>Edit Category</h1>
                </div>
            </div>
            <div class="header-user-badge">
                Logged in as: <span style="color: var(--primary-glow, #3b82f6); font-weight: 700;"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></span>
            </div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="msg-banner success-banner"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="msg-banner error-banner"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid-layout">
            <!-- Left Column: Edit Category Form -->
            <div class="panel-box">
                <form action="category_edit.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label" for="name">Category Name</label>
                        <input type="text" name="name" id="name" class="form-input" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Current Photo</label>
                        <?php 
                            $imgSrc = $category['image'];
                            $isPlaceholder = false;
                            if (empty($imgSrc)) {
                                $imgSrc = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><rect width="120" height="120" rx="16" fill="%23f1f5f9" stroke="%23cbd5e1" stroke-width="1"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui" font-size="12" font-weight="600" fill="%2364748b">No Photo</text></svg>';
                                $isPlaceholder = true;
                            }
                            if (!$isPlaceholder && strpos($imgSrc, 'http') === false) {
                                $imgSrc = '../' . $imgSrc;
                            }
                        ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Preview" class="curr-photo-preview" style="max-width: 150px; max-height: 150px; object-fit: contain; border-radius: 8px; margin-bottom: 1rem; display: block;">
                        
                        <label class="form-label" for="image">Upload New Photo (Optional)</label>
                        <input type="file" name="image" id="image" class="form-input" accept="image/*">
                    </div>
                    <div style="margin-top: 2rem; display: flex; gap: 0.8rem;">
                        <button type="submit" class="btn-submit" style="flex: 1; width: auto; margin: 0; display: flex; justify-content: center; align-items: center; padding: 0.75rem;">Save Changes</button>
                        <a href="categories.php" class="btn-cancel" style="flex: 1; width: auto; margin: 0; display: flex; justify-content: center; align-items: center; text-decoration: none; padding: 0.75rem;">Cancel</a>
                    </div>
                </form>
            </div>

            <!-- Right Column: Categories List -->
            <div class="panel-box">
                <h2>Categories List</h2>
                <div class="admin-table-wrapper">
                    <table class="admin-table responsive-cards">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Category Name</th>
                                <th>Type</th>
                                <th>Total Products</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-muted);">No categories found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): 
                                    $isCurrent = ($cat['id'] === $id);
                                    $catImgSrc = $cat['image'];
                                    $isCatPlaceholder = false;
                                    if (empty($catImgSrc)) {
                                        $catImgSrc = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 50 50"><rect width="50" height="50" rx="10" fill="%23f1f5f9" stroke="%23cbd5e1" stroke-width="1"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui" font-size="8" font-weight="600" fill="%2364748b">No Photo</text></svg>';
                                        $isCatPlaceholder = true;
                                    }
                                    if (!$isCatPlaceholder && strpos($catImgSrc, 'http') === false) {
                                        $catImgSrc = '../' . $catImgSrc;
                                    }
                                ?>
                                    <tr class="<?php echo $isCurrent ? 'editing-row' : ''; ?>">
                                        <td>
                                            <img src="<?php echo htmlspecialchars($catImgSrc); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="cat-thumb">
                                        </td>
                                        <td style="font-weight: 600;">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                            <?php if ($isCurrent): ?>
                                                <span class="badge" style="background: #fef9c3; color: #854d0e; border: 1px solid #fde047; font-size: 0.7rem; padding: 0.15rem 0.5rem; margin-left: 0.5rem;">Editing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (($cat['type'] ?? 'product') === 'service'): ?>
                                                <span style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">Service</span>
                                            <?php else: ?>
                                                <span style="background: #f1f5f9; color: #64748b; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">Product</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="td-count"><span class="badge"><?php echo $cat['product_count']; ?> products</span></td>
                                        <td class="action-links" style="text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end;">
                                            <?php if ($isCurrent): ?>
                                                <span class="badge" style="background: #fff7ed; color: #ea580c; border: 1px solid rgba(255, 94, 0, 0.3);">Editing Now</span>
                                            <?php else: ?>
                                                <a href="category_edit.php?id=<?php echo $cat['id']; ?>" class="btn-small btn-edit">✏️ Edit</a>
                                                <a href="categories.php?delete=<?php echo $cat['id']; ?>" class="btn-small btn-delete" onclick="return confirm('Are you sure you want to delete this category? This cannot be undone.');">🗑️ Delete</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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