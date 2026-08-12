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

// Ensure uploads directory exists
if (!is_dir('../uploads')) {
    mkdir('../uploads', 0777, true);
}

// Fetch categories for dropdown
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price']; // price in LKR (store base)
    $category_id = NULL; // Services don't need categories
    $stock = 999;
    $discount_percent = (int)$_POST['discount_percent'];
    $warranty = "";
    $shipping_fee = 0.00;
    $delivery_days = 0;
    
    
    // Handle main image upload or URL
    $main_image = 'placeholder.jpg';
    if (isset($_FILES['image']) && !empty($_FILES['image']['name'])) {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['image']['tmp_name'];
            $file_name = $_FILES['image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            if (in_array($file_ext, $allowed_exts) && is_valid_image($file_tmp, $file_name)) {
                $new_name = 'prod_' . uniqid() . '.' . $file_ext;
                $destination = '../uploads/' . $new_name;
                if (move_uploaded_file($file_tmp, $destination)) {
                    $main_image = 'uploads/' . $new_name;
                } else {
                    $error = "Failed to save cover image to server uploads directory.";
                }
            } else {
                $error = "Invalid cover image format. Supported formats: JPG, JPEG, PNG, GIF, WEBP, SVG.";
            }
        } else {
            $error = "Image upload failed with error code: " . $_FILES['image']['error'];
        }
    } elseif (!empty($_POST['image_url'])) {
        $main_image = trim($_POST['image_url']);
    }
    
    if ($name !== '' && $price > 0) {
        $is_trending = isset($_POST['is_trending']) ? 1 : 0;
        $is_disabled = isset($_POST['is_disabled']) ? 1 : 0;
        $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
        $flash_sale_price = !empty($_POST['flash_sale_price']) ? (float)$_POST['flash_sale_price'] : null;
        $flash_sale_start = !empty($_POST['flash_sale_start']) ? $_POST['flash_sale_start'] : null;
        $flash_sale_end = !empty($_POST['flash_sale_end']) ? $_POST['flash_sale_end'] : null;
        $pdo->beginTransaction();
        try {
            // Insert product
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, image, category_id, stock, discount_percent, is_trending, is_disabled, is_new_arrival, warranty, flash_sale_price, flash_sale_start, flash_sale_end, shipping_fee, delivery_days, product_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'local_service')");
            $stmt->execute([$name, $description, $price, $main_image, $category_id, $stock, $discount_percent, $is_trending, $is_disabled, $is_new_arrival, $warranty, $flash_sale_price, $flash_sale_start, $flash_sale_end, $shipping_fee, $delivery_days]);
            $productId = $pdo->lastInsertId();
            
            // Handle variants
            if (isset($_POST['variants']) && is_array($_POST['variants'])) {
                foreach ($_POST['variants'] as $key => $variant) {
                    $type = trim($variant['type']);
                    $value = trim($variant['value']);
                    $price_modifier = (float)$variant['price_modifier'];
                    $v_stock = (int)$variant['stock'];
                    
                    if ($value !== '') {
                        $variant_image = null;
                        $photo_input_name = "variant_photo_" . $key;
                        
                        // Handle variant image upload
                        if (isset($_FILES[$photo_input_name]) && $_FILES[$photo_input_name]['error'] === UPLOAD_ERR_OK) {
                            $v_file_tmp = $_FILES[$photo_input_name]['tmp_name'];
                            $v_file_name = $_FILES[$photo_input_name]['name'];
                            $v_file_ext = strtolower(pathinfo($v_file_name, PATHINFO_EXTENSION));
                            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                            if (in_array($v_file_ext, $allowed_exts) && is_valid_image($v_file_tmp, $v_file_name)) {
                                $v_new_name = 'var_' . uniqid() . '.' . $v_file_ext;
                                $v_destination = '../uploads/' . $v_new_name;
                                if (move_uploaded_file($v_file_tmp, $v_destination)) {
                                    $variant_image = 'uploads/' . $v_new_name;
                                }
                            }
                        }
                        
                        $vStmt = $pdo->prepare("INSERT INTO product_variants (product_id, variant_type, variant_value, price_modifier, image, stock) VALUES (?, ?, ?, ?, ?, ?, 'local_service')");
                        $vStmt->execute([$productId, $type, $value, $price_modifier, $variant_image, $v_stock]);
                    }
                }
            }
            
            // Handle gallery images upload
            if (isset($_FILES['gallery_images'])) {
                $gallery = $_FILES['gallery_images'];
                for ($i = 0; $i < count($gallery['name']); $i++) {
                    if ($gallery['error'][$i] === UPLOAD_ERR_OK) {
                        $g_tmp = $gallery['tmp_name'][$i];
                        $g_name = $gallery['name'][$i];
                        $g_ext = strtolower(pathinfo($g_name, PATHINFO_EXTENSION));
                        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                        if (in_array($g_ext, $allowed_exts) && is_valid_image($g_tmp, $g_name)) {
                            $g_new_name = 'gal_' . uniqid() . '_' . $i . '.' . $g_ext;
                            $g_dest = '../uploads/' . $g_new_name;
                            if (move_uploaded_file($g_tmp, $g_dest)) {
                                $g_path = 'uploads/' . $g_new_name;
                                $gStmt = $pdo->prepare("INSERT INTO product_gallery (product_id, image_path) VALUES (?, ?, 'local_service')");
                                $gStmt->execute([$productId, $g_path]);
                            }
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            // Post-commit cleanup: promote gallery image if main image is placeholder
            if ($main_image === 'placeholder.jpg') {
                $gCheck = $pdo->prepare("SELECT id, image_path FROM product_gallery WHERE product_id = ? ORDER BY id ASC LIMIT 1");
                $gCheck->execute([$productId]);
                $firstGal = $gCheck->fetch(PDO::FETCH_ASSOC);
                if ($firstGal) {
                    $pdo->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$firstGal['image_path'], $productId]);
                    $pdo->prepare("DELETE FROM product_gallery WHERE id = ?")->execute([$firstGal['id']]);
                }
            }
            
            $success = "Service added successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to Add Service: " . $e->getMessage();
        }
    } else {
        $error = "Please enter all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Service - Digi Pro X 24 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    
    

    <style>
        /* Hide physical product fields in service admin */
        label[for="stock"], input#stock,
        label[for="shipping_fee"], input#shipping_fee,
        label[for="delivery_days"], input#delivery_days,
        .variants-section, #variants-container, #add-variant-btn {
            display: none !important;
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
                    <span style="color: var(--text-muted); font-weight:600; font-size:0.9rem;">Catalog Setup</span>
                    <h1>Add New Service</h1>
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

        <form action="service_add.php" method="POST" enctype="multipart/form-data">
            <!-- Product Info Panel -->
            <div class="panel-box">
                <h2>Service Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">Service Name *</label>
                        <input type="text" name="name" id="name" class="form-input" placeholder="e.g. System Installation" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea name="description" id="description" class="form-input" placeholder="Provide a detailed description of the product..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="price">Base Price (LKR) *</label>
                        <input type="number" step="0.01" name="price" id="price" class="form-input" placeholder="e.g. 9500.00" required>
                        <span style="font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem; display:block;">Enter price in LKR (Rs.).</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="discount_price">Discounted Price (LKR)</label>
                        <input type="number" step="0.01" id="discount_price" class="form-input" placeholder="e.g. 8000.00" style="color: #059669; font-weight: 700;" value="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="discount_percent">Discount Percentage (%)</label>
                        <input type="number" name="discount_percent" id="discount_percent" class="form-input" min="0" max="100" value="0">
                    </div>
                </div>

                <div class="form-group" style="background: #f8fafc; padding: 1.2rem; border-radius: 12px; border: 1px solid var(--border-color);">
                    <label class="form-label">Service Cover Image</label>
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label" for="image" style="font-size: 0.83rem; color: var(--text-muted);">📁 Option 1: Upload Image File (JPG, PNG, SVG, WEBP, GIF)</label>
                        <input type="file" name="image" id="image" class="form-input" accept="image/*">
                    </div>
                    <div>
                        <label class="form-label" for="image_url" style="font-size: 0.83rem; color: var(--text-muted);">🌐 Option 2: Or Enter Image Web URL / Filename</label>
                        <input type="text" name="image_url" id="image_url" class="form-input" placeholder="e.g. https://example.com/photo.jpg or uploads/piano.jpg">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="gallery_images">Additional Service Photos (Gallery)</label>
                    <input type="file" name="gallery_images[]" id="gallery_images" class="form-input" accept="image/*" multiple>
                    <span style="font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem; display:block;">You can select multiple files at once.</span>
                </div>
            </div>

            <!-- Submit buttons -->
            <div style="margin-bottom: 4rem;">
                <button type="submit" class="btn-submit">Add Service</button>
                <a href="services.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
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

        function updateFinalPrice() {
            const basePrice = parseFloat(document.getElementById('price').value) || 0;
            const discount = parseFloat(document.getElementById('discount_percent').value) || 0;
            const discountPrice = basePrice * (1 - discount / 100);
            document.getElementById('discount_price').value = discountPrice.toFixed(2);
        }

        function calculateFromDiscountPrice() {
            const basePrice = parseFloat(document.getElementById('price').value) || 0;
            const discountPrice = parseFloat(document.getElementById('discount_price').value) || 0;
            if (basePrice > 0) {
                let percent = ((basePrice - discountPrice) / basePrice) * 100;
                percent = Math.max(0, Math.min(100, percent));
                document.getElementById('discount_percent').value = Math.round(percent);
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const priceInput = document.getElementById('price');
            const discountPriceInput = document.getElementById('discount_price');
            const discountInput = document.getElementById('discount_percent');
            if (priceInput && discountPriceInput && discountInput) {
                priceInput.addEventListener('input', updateFinalPrice);
                discountInput.addEventListener('input', updateFinalPrice);
                discountPriceInput.addEventListener('input', calculateFromDiscountPrice);
                discountPriceInput.addEventListener('change', updateFinalPrice);
                updateFinalPrice();
            }
        });

        menuToggle.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    </script>
</body>
</html>
