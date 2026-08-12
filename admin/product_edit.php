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
    header("Location: products.php");
    exit;
}

$id = (int)$_GET['id'];

// Fetch categories for dropdown
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle update product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $category_id = (int)$_POST['category_id'];
    $stock = (int)$_POST['stock'];
    $discount_percent = (int)$_POST['discount_percent'];
    $warranty = isset($_POST['warranty']) ? trim($_POST['warranty']) : '';
    $shipping_fee = isset($_POST['shipping_fee']) ? (float)$_POST['shipping_fee'] : 450.00;
    $delivery_days = isset($_POST['delivery_days']) ? (int)$_POST['delivery_days'] : 3;
    if ($delivery_days <= 0) $delivery_days = 3;
    $current_image = $_POST['current_image'];
    
    // Handle main image upload if new
    $main_image = $current_image;
    
    // Handle delete cover image
    if (isset($_POST['delete_cover_image']) && $_POST['delete_cover_image'] === '1') {
        if ($current_image && $current_image !== 'placeholder.jpg' && strpos($current_image, 'uploads/') !== false) {
            $oldPath = '../' . $current_image;
            if (file_exists($oldPath)) { unlink($oldPath); }
        }
        $main_image = 'placeholder.jpg';
    }
    
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
                    // Delete old local cover image if changed
                    if ($current_image && $current_image !== 'placeholder.jpg' && strpos($current_image, 'uploads/') !== false) {
                        $oldPath = '../' . $current_image;
                        if (file_exists($oldPath)) { @unlink($oldPath); }
                    }
                } else {
                    $error = "Failed to save cover image to server uploads directory.";
                }
            } else {
                $error = "Invalid cover image file format or corrupted image. Supported formats: JPG, JPEG, PNG, GIF, WEBP, SVG.";
            }
        } else {
            $error = "Image upload failed with error code: " . $_FILES['image']['error'] . " (Check PHP upload file size limits).";
        }
    } elseif (!empty($_POST['image_url']) && empty($_POST['delete_cover_image'])) {
        $main_image = trim($_POST['image_url']);
    }
    
    if ($name !== '' && $price > 0 && $category_id > 0) {
        $is_trending = isset($_POST['is_trending']) ? 1 : 0;
        $is_disabled = isset($_POST['is_disabled']) ? 1 : 0;
        $is_new_arrival = isset($_POST['is_new_arrival']) ? 1 : 0;
        $flash_sale_price = !empty($_POST['flash_sale_price']) ? (float)$_POST['flash_sale_price'] : null;
        $flash_sale_start = !empty($_POST['flash_sale_start']) ? $_POST['flash_sale_start'] : null;
        $flash_sale_end = !empty($_POST['flash_sale_end']) ? $_POST['flash_sale_end'] : null;
        $pdo->beginTransaction();
        try {
            // Update product
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, image = ?, category_id = ?, stock = ?, discount_percent = ?, is_trending = ?, is_disabled = ?, is_new_arrival = ?, warranty = ?, flash_sale_price = ?, flash_sale_start = ?, flash_sale_end = ?, shipping_fee = ?, delivery_days = ? WHERE id = ?");
            $stmt->execute([$name, $description, $price, $main_image, $category_id, $stock, $discount_percent, $is_trending, $is_disabled, $is_new_arrival, $warranty, $flash_sale_price, $flash_sale_start, $flash_sale_end, $shipping_fee, $delivery_days, $id]);

            // Update product-specific shipping rates
            $pdo->prepare("DELETE FROM product_shipping_rates WHERE product_id = ?")->execute([$id]);
            if (isset($_POST['shipping_rates']) && is_array($_POST['shipping_rates'])) {
                $shipStmt = $pdo->prepare("INSERT INTO product_shipping_rates (product_id, country_code, fee) VALUES (?, ?, ?)");
                foreach ($_POST['shipping_rates'] as $cc => $fee) {
                    $cc = strtoupper(trim($cc));
                    $fee = (float)$fee;
                    if ($cc !== '' && $fee >= 0) {
                        $shipStmt->execute([$id, $cc, $fee]);
                    }
                }
            }
            
            // Delete removed variants
            if (!empty($_POST['deleted_variant_ids'])) {
                $deletedIds = explode(',', $_POST['deleted_variant_ids']);
                foreach ($deletedIds as $delId) {
                    $delId = (int)$delId;
                    if ($delId > 0) {
                        // Get photo path to delete local file
                        $vImgStmt = $pdo->prepare("SELECT image FROM product_variants WHERE id = ?");
                        $vImgStmt->execute([$delId]);
                        $vImg = $vImgStmt->fetchColumn();
                        
                        $delStmt = $pdo->prepare("DELETE FROM product_variants WHERE id = ?");
                        $delStmt->execute([$delId]);
                        
                        if ($vImg && strpos($vImg, 'uploads/') !== false) {
                            $oldVPath = '../' . $vImg;
                            if (file_exists($oldVPath)) {
                                unlink($oldVPath);
                            }
                        }
                    }
                }
            }
            
            // Process variants list
            if (isset($_POST['variants']) && is_array($_POST['variants'])) {
                foreach ($_POST['variants'] as $key => $variant) {
                    $v_id = isset($variant['id']) ? (int)$variant['id'] : 0;
                    $type = trim($variant['type']);
                    $value = trim($variant['value']);
                    $price_modifier = (float)$variant['price_modifier'];
                    $v_stock = (int)$variant['stock'];
                    
                    if ($value !== '') {
                        $variant_image = isset($variant['current_image']) ? $variant['current_image'] : null;
                        
                        // Handle variant image upload
                        $photo_input_name = "variant_photo_" . $key;
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
                                    // Delete old variant photo
                                    $oldVarImg = isset($variant['current_image']) ? $variant['current_image'] : null;
                                    if ($oldVarImg && strpos($oldVarImg, 'uploads/') !== false) {
                                        $oldVPath = '../' . $oldVarImg;
                                        if (file_exists($oldVPath)) {
                                            unlink($oldVPath);
                                        }
                                    }
                                }
                            }
                        }
                        
                        if ($v_id > 0) {
                            // Update existing
                            $vStmt = $pdo->prepare("UPDATE product_variants SET variant_type = ?, variant_value = ?, price_modifier = ?, image = ?, stock = ? WHERE id = ?");
                            $vStmt->execute([$type, $value, $price_modifier, $variant_image, $v_stock, $v_id]);
                        } else {
                            // Insert new
                            $vStmt = $pdo->prepare("INSERT INTO product_variants (product_id, variant_type, variant_value, price_modifier, image, stock) VALUES (?, ?, ?, ?, ?, ?)");
                            $vStmt->execute([$id, $type, $value, $price_modifier, $variant_image, $v_stock]);
                        }
                    }
                }
            }
            
            // Delete removed gallery images
            if (!empty($_POST['deleted_gallery_ids'])) {
                $deletedGalIds = explode(',', $_POST['deleted_gallery_ids']);
                foreach ($deletedGalIds as $gId) {
                    $gId = (int)$gId;
                    if ($gId > 0) {
                        $gImgStmt = $pdo->prepare("SELECT image_path FROM product_gallery WHERE id = ?");
                        $gImgStmt->execute([$gId]);
                        $gImg = $gImgStmt->fetchColumn();
                        
                        $delGStmt = $pdo->prepare("DELETE FROM product_gallery WHERE id = ?");
                        $delGStmt->execute([$gId]);
                        
                        if ($gImg && strpos($gImg, 'uploads/') !== false) {
                            $oldGPath = '../' . $gImg;
                            if (file_exists($oldGPath)) {
                                @unlink($oldGPath);
                            }
                        }
                    }
                }
            }
            
            // Handle new gallery images upload
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
                                $gStmt = $pdo->prepare("INSERT INTO product_gallery (product_id, image_path) VALUES (?, ?)");
                                $gStmt->execute([$id, $g_path]);
                            }
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            // Post-commit cleanup: promote gallery image if main image is placeholder
            if ($main_image === 'placeholder.jpg') {
                $gCheck = $pdo->prepare("SELECT id, image_path FROM product_gallery WHERE product_id = ? ORDER BY id ASC LIMIT 1");
                $gCheck->execute([$id]);
                $firstGal = $gCheck->fetch(PDO::FETCH_ASSOC);
                if ($firstGal) {
                    $pdo->prepare("UPDATE products SET image = ? WHERE id = ?")->execute([$firstGal['image_path'], $id]);
                    $pdo->prepare("DELETE FROM product_gallery WHERE id = ?")->execute([$firstGal['id']]);
                }
            }
            
            $success = "Product updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update product: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

// Fetch product details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit;
}

// Fetch current variants
$vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
$vStmt->execute([$id]);
$variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current gallery images
$gStmt = $pdo->prepare("SELECT * FROM product_gallery WHERE product_id = ? ORDER BY id ASC");
$gStmt->execute([$id]);
$gallery_images = $gStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current product-specific shipping rates
$pRatesStmt = $pdo->prepare("SELECT country_code, fee FROM product_shipping_rates WHERE product_id = ? ORDER BY country_code ASC");
$pRatesStmt->execute([$id]);
$pRates = $pRatesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Digi Pro X 24 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    
    <!-- Flatpickr Date/Time Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        @media (max-width: 768px) {
            .shipping-rate-add-row {
                flex-direction: column !important;
                align-items: stretch !important;
            }
            .shipping-rate-add-row > div {
                width: 100% !important;
                flex: none !important;
            }
            .shipping-rate-add-row button {
                width: 100% !important;
                margin-top: 0.5rem;
            }
        }
        .btn-rate-edit {
            padding: 6px 16px;
            background: #fff8f2;
            border: 1px solid #ffdec2;
            border-radius: 10px;
            color: #ff5e00;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-rate-edit:hover {
            background: #ff5e00 !important;
            border-color: #ff5e00 !important;
            color: #ffffff !important;
        }
        .btn-rate-delete {
            padding: 6px 16px;
            background: #fff1f1;
            border: 1px solid #ffd1d1;
            border-radius: 10px;
            color: #dc2626;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-rate-delete:hover {
            background: #dc2626 !important;
            border-color: #dc2626 !important;
            color: #ffffff !important;
        }
    </style>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
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
                    <h1>Edit Product</h1>
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

        <form action="product_edit.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($product['image']); ?>">
            <input type="hidden" name="deleted_variant_ids" id="deleted_variant_ids" value="">

            <!-- Product Info Panel -->
            <div class="panel-box">
                <h2>Product Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">Product Name *</label>
                        <input type="text" name="name" id="name" class="form-input" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="category_id">Category *</label>
                        <select name="category_id" id="category_id" class="form-input" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <textarea name="description" id="description" class="form-input"><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="price">Base Price (LKR) *</label>
                        <input type="number" step="0.01" name="price" id="price" class="form-input" value="<?php echo htmlspecialchars($product['price']); ?>" required>
                        <span style="font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem; display:block;">Enter price in LKR (Rs.).</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="discount_price">Discounted Price (LKR)</label>
                        <input type="number" step="0.01" id="discount_price" class="form-input" placeholder="e.g. 8000.00" style="color: #059669; font-weight: 700;" value="0.00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="discount_percent">Discount Percentage (%)</label>
                        <input type="number" name="discount_percent" id="discount_percent" class="form-input" min="0" max="100" value="<?php echo (int)$product['discount_percent']; ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="stock">Stock Quantity</label>
                        <input type="number" name="stock" id="stock" class="form-input" min="0" value="<?php echo $product['stock']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="warranty">Warranty Period</label>
                        <input type="text" name="warranty" id="warranty" class="form-input" value="<?php echo htmlspecialchars($product['warranty'] ?? ''); ?>" placeholder="e.g. 1 Year Store Warranty">
                    </div>
                    <?php $cur_del_days = (int)($product['delivery_days'] ?? 3); ?>
                    <div class="form-group">
                        <label class="form-label" for="delivery_days">Estimated Delivery Time (Days) *</label>
                        <select name="delivery_days" id="delivery_days" class="form-input" required style="background: #ffffff; color: #0f172a;">
                            <option value="3" <?php echo $cur_del_days === 3 ? 'selected' : ''; ?>>🚚 3 Days (Standard In-Stock Store Default)</option>
                            <option value="7" <?php echo $cur_del_days === 7 ? 'selected' : ''; ?>>📦 7 Days (Out of Store / Backorder / Import)</option>
                            <option value="5" <?php echo $cur_del_days === 5 ? 'selected' : ''; ?>>⚡ 5 Days (Fast Courier)</option>
                            <option value="1" <?php echo $cur_del_days === 1 ? 'selected' : ''; ?>>🚀 1 Day (Express Next-Day Delivery)</option>
                            <option value="14" <?php echo $cur_del_days === 14 ? 'selected' : ''; ?>>🌐 14 Days (Extended Special Import)</option>
                        </select>
                        <span style="font-size:0.78rem; color:var(--text-muted); margin-top:0.3rem; display:block;">Default is 3 Days. Out of store takes 7 Days.</span>
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 0.5rem;">
                        <label class="form-label" for="is_trending" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="is_trending" id="is_trending" value="1" <?php echo (int)$product['is_trending'] === 1 ? 'checked' : ''; ?> style="width: 1.2rem; height: 1.2rem; cursor: pointer; accent-color: var(--accent-orange);">
                            <span>Add as Trending Product</span>
                        </label>
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 0.5rem;">
                        <label class="form-label" for="is_new_arrival" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none;">
                            <input type="checkbox" name="is_new_arrival" id="is_new_arrival" value="1" <?php echo (int)$product['is_new_arrival'] === 1 ? 'checked' : ''; ?> style="width: 1.2rem; height: 1.2rem; cursor: pointer; accent-color: var(--accent-orange);">
                            <span>Add as New Arrival</span>
                        </label>
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 0.5rem;">
                        <label class="form-label" for="is_disabled" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; color: #dc2626;">
                            <input type="checkbox" name="is_disabled" id="is_disabled" value="1" <?php echo (int)$product['is_disabled'] === 1 ? 'checked' : ''; ?> style="width: 1.2rem; height: 1.2rem; cursor: pointer; accent-color: #dc2626;">
                            <span>Disable Product (Hide from storefront)</span>
                        </label>
                    </div>
                </div>

                <!-- Flash Sale Optional Block -->
                <?php $has_flash = !empty($product['flash_sale_price']) && !empty($product['flash_sale_start']); ?>
                <div class="form-group" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <label class="form-label" style="display: flex; align-items: center; gap: 0.8rem; cursor: pointer; user-select: none; margin-bottom: 1rem;">
                        <input type="checkbox" id="enable_flash_sale" name="enable_flash_sale" value="1" <?php echo $has_flash ? 'checked' : ''; ?> style="width: 1.3rem; height: 1.3rem; cursor: pointer; accent-color: var(--accent-orange);">
                        <h3 style="color: #dc2626; margin: 0; font-size: 1.1rem;">⚡ Enable Flash Sale (Optional)</h3>
                    </label>
                    <div class="form-row" id="flash_sale_fields" style="display: <?php echo $has_flash ? 'grid' : 'none'; ?>;">
                        <div class="form-group">
                            <label class="form-label" for="flash_sale_price">Flash Sale Price (LKR)</label>
                            <input type="number" step="0.01" name="flash_sale_price" id="flash_sale_price" class="form-input" value="<?php echo htmlspecialchars($product['flash_sale_price'] ?? ''); ?>" placeholder="e.g. 7500.00">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="flash_sale_start">Flash Sale Start Time</label>
                            <input type="text" name="flash_sale_start" id="flash_sale_start" class="form-input datetime-picker" placeholder="Select start date & time" value="<?php echo !empty($product['flash_sale_start']) ? date('Y-m-d H:i:s', strtotime($product['flash_sale_start'])) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="flash_sale_end">Flash Sale End Time</label>
                            <input type="text" name="flash_sale_end" id="flash_sale_end" class="form-input datetime-picker" placeholder="Select end date & time" value="<?php echo !empty($product['flash_sale_end']) ? date('Y-m-d H:i:s', strtotime($product['flash_sale_end'])) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Cover Image</label>

                    <?php 
                        $coverSrc = $product['image'];
                        if ($coverSrc && $coverSrc !== 'placeholder.jpg' && strpos($coverSrc, 'http') === false) {
                            $coverSrc = '../' . $coverSrc;
                        }
                        $hasCoverImage = !empty($product['image']) && $product['image'] !== 'placeholder.jpg';
                    ?>

                    <!-- Current image preview -->
                    <?php if ($hasCoverImage): ?>
                    <div id="current-cover-wrap" style="margin-bottom: 1rem;">
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.6rem;">Current cover image</p>
                        <div style="position: relative; width: 100px; height: 100px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color);">
                            <img id="current-cover-thumb" src="<?php echo htmlspecialchars($coverSrc); ?>" alt="Current Cover"
                                 style="width: 100%; height: 100%; object-fit: cover;">
                            <button type="button" onclick="triggerDeleteCover()" style="position: absolute; top: 4px; right: 4px; background: rgba(239, 68, 68, 0.9); border: none; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem; font-weight: bold; line-height: 1; transition: 0.2s;" title="Remove Cover Photo">&times;</button>
                        </div>
                    </div>
                    <?php endif; ?>
                    <input type="hidden" name="delete_cover_image" id="delete_cover_image" value="0">

                    <!-- Upload new image or enter URL -->
                    <div id="new-cover-upload-wrap">
                        <div style="margin-bottom: 0.8rem;">
                            <label class="form-label" for="cover_image_input" style="font-size: 0.83rem; color: var(--text-muted);">📁 Option 1: Upload Image File (JPG, PNG, SVG, WEBP, GIF)</label>
                            <input type="file" name="image" id="cover_image_input" class="form-input" accept="image/*" onchange="previewCoverImage(this)">
                        </div>
                        <div>
                            <label class="form-label" for="image_url" style="font-size: 0.83rem; color: var(--text-muted);">🌐 Option 2: Or Enter Image Web URL / Filename</label>
                            <input type="text" name="image_url" id="image_url" class="form-input" placeholder="e.g. https://example.com/photo.jpg or uploads/piano.jpg" value="<?php echo htmlspecialchars($product['image'] ?? ''); ?>">
                        </div>
                        <!-- Live preview of newly selected file -->
                        <div id="new-cover-preview" style="display:none; margin-top: 0.8rem; position: relative; width: 120px;">
                            <img id="new-cover-preview-img" src="" alt="New cover preview"
                                 style="width: 120px; height: 120px; object-fit: cover; border-radius: 12px; border: 2px solid #2563eb;">
                            <span style="position:absolute; top:-6px; right:-6px; background:#2563eb; color:#fff; font-size:0.65rem; font-weight:700; padding: 2px 6px; border-radius:8px;">NEW</span>
                            <button type="button" onclick="clearNewCover()" title="Remove selection"
                                    style="position:absolute; bottom:-6px; right:-6px; background:rgba(239,68,68,0.9); border:none; color:#fff; border-radius:50%; width:20px; height:20px; cursor:pointer; font-size:0.8rem; display:flex; align-items:center; justify-content:center; font-weight:bold;">×</button>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                    <label class="form-label">Product Photo Gallery (Multiple Photos)</label>
                    <div style="margin-bottom: 1.5rem;">
                        <input type="file" name="gallery_images[]" id="gallery_images" class="form-input" accept="image/*" multiple>
                        <span style="font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem; display:block;">Upload additional photos for this product. You can select multiple files at once.</span>
                    </div>
                    
                    <?php if (!empty($gallery_images)): ?>
                        <div class="gallery-thumbs-container" style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem;">
                            <?php foreach ($gallery_images as $gImg): 
                                $gSrc = $gImg['image_path'];
                                if (strpos($gSrc, 'http') === false) {
                                    $gSrc = '../' . $gSrc;
                                }
                            ?>
                                <div class="gallery-thumb-wrapper" data-id="<?php echo $gImg['id']; ?>" style="position: relative; width: 100px; height: 100px; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color);">
                                    <img src="<?php echo htmlspecialchars($gSrc); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <button type="button" onclick="removeGalleryImage(this, <?php echo $gImg['id']; ?>)" style="position: absolute; top: 4px; right: 4px; background: rgba(239, 68, 68, 0.9); border: none; color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 0.8rem; font-weight: bold; line-height: 1; transition: 0.2s;" title="Remove Photo">&times;</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="deleted_gallery_ids" id="deleted_gallery_ids" value="">
                </div>
            </div>

            <!-- Country-Specific Shipping Rates Panel -->
            <div class="panel-box" style="margin-top: 2rem;">
                <h2>Country-Specific Shipping Rates</h2>
                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem;">
                    Configure custom shipping rates for specific countries. If a country is not configured, the default shipping fee (Rs. 450.00) will apply.
                </p>

                <div id="shipping-rates-container" style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 1.5rem;">
                    <!-- Dynamically added rates will appear here -->
                </div>

                <div class="shipping-rate-add-row" style="display: flex; gap: 1rem; align-items: flex-end; background: #f8fafc; padding: 1rem; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 1rem;">
                    <div style="flex: 1;">
                        <label class="form-label" style="font-size: 0.8rem; margin-bottom: 4px;">Country</label>
                        <select id="rate-country-select" class="form-input" style="background: #ffffff; color: #0f172a;">
                            <option value="">Select a country...</option>
                            <?php
                            $country_list = [
                                'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AD' => 'Andorra', 'AO' => 'Angola', 
                                'AG' => 'Antigua & Barbuda', 'AR' => 'Argentina', 'AM' => 'Armenia', 'AU' => 'Australia', 'AT' => 'Austria', 
                                'AZ' => 'Azerbaijan', 'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 
                                'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BT' => 'Bhutan', 
                                'BO' => 'Bolivia', 'BA' => 'Bosnia & Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil', 'BN' => 'Brunei', 
                                'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CV' => 'Cape Verde', 'KH' => 'Cambodia', 
                                'CM' => 'Cameroon', 'CA' => 'Canada', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 
                                'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo - Brazzaville', 'CD' => 'Congo - Kinshasa', 'CR' => 'Costa Rica', 
                                'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czechia', 'DK' => 'Denmark', 
                                'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 
                                'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini', 
                                'ET' => 'Ethiopia', 'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GA' => 'Gabon', 
                                'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece', 
                                'GD' => 'Grenada', 'GT' => 'Guatemala', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 
                                'HT' => 'Haiti', 'HN' => 'Honduras', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 
                                'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel', 
                                'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan', 
                                'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'North Korea', 'KR' => 'South Korea', 'KW' => 'Kuwait', 
                                'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon', 'LS' => 'Lesotho', 
                                'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 
                                'MG' => 'Madagascar', 'MW' => 'Malawi', 'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 
                                'MT' => 'Malta', 'MH' => 'Marshall Islands', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MX' => 'Mexico', 
                                'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro', 
                                'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia', 'NR' => 'Nauru', 
                                'NP' => 'Nepal', 'NL' => 'Netherlands', 'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 
                                'NG' => 'Nigeria', 'MK' => 'North Macedonia', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 
                                'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 
                                'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal', 'QA' => 'Qatar', 
                                'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda', 'KN' => 'St. Kitts & Nevis', 'LC' => 'St. Lucia', 
                                'VC' => 'St. Vincent & Grenadines', 'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome & Principe', 
                                'SA' => 'Saudi Arabia', 'SN' => 'Senegal', 'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 
                                'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 
                                'ZA' => 'South Africa', 'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 
                                'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan', 
                                'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo', 'TO' => 'Tonga', 
                                'TT' => 'Trinidad & Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey', 'TM' => 'Turkmenistan', 'TV' => 'Tuvalu', 
                                'UG' => 'Uganda', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 
                                'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City', 'VE' => 'Venezuela', 
                                'VN' => 'Vietnam', 'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
                            ];
                            foreach ($country_list as $code => $cname) {
                                echo '<option value="' . $code . '">' . htmlspecialchars($cname) . ' (' . $code . ')</option>';
                            }
                            ?>
                            <option value="CUSTOM">Custom Code...</option>
                        </select>
                        <input type="text" id="custom-country-code" class="form-input" placeholder="Enter 2-letter Code (e.g. MY)" style="display: none; margin-top: 8px; text-transform: uppercase;" maxlength="2">
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                            <label class="form-label" style="font-size: 0.8rem; margin: 0;">Delivery Fee (Rs.)</label>
                            <label style="display: flex; align-items: center; gap: 0.3rem; cursor: pointer; font-size: 0.75rem; font-weight: 600; color: #0f172a; margin: 0; user-select: none;">
                                <input type="checkbox" id="rate-free-chk" style="width: 1rem; height: 1rem; cursor: pointer; accent-color: var(--primary-glow);" onchange="const f = document.getElementById('rate-fee-input'); f.value = this.checked ? '0.00' : ''; f.disabled = this.checked;">
                                <span>Free Shipping</span>
                            </label>
                        </div>
                        <input type="number" step="0.01" id="rate-fee-input" class="form-input" placeholder="e.g. 450.00" min="0">
                    </div>
                    <div>
                        <button type="button" class="btn-primary" onclick="addProductShippingRateRow()" style="padding: 10px 20px; background: #ff5e00; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer;">Add Rate</button>
                    </div>
                </div>
            </div>

            <!-- Variants Checkbox Toggle -->
            <div style="margin-bottom: 2rem; background: #ffffff; border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <label class="form-label" style="display: flex; align-items: center; gap: 0.8rem; cursor: pointer; user-select: none; margin: 0;">
                    <input type="checkbox" id="enable_variants" name="enable_variants" value="1" <?php echo !empty($variants) ? 'checked' : ''; ?> style="width: 1.3rem; height: 1.3rem; cursor: pointer; accent-color: var(--accent-orange);">
                    <span style="font-weight: 600; font-size: 1.1rem; color: var(--text-main);">This product has variants (e.g. Colors, Sizes, Wattage)</span>
                </label>
            </div>

            <!-- Variants Panel Wrapper -->
            <div id="variants-section-wrapper" style="display: <?php echo !empty($variants) ? 'block' : 'none'; ?>;">
                <!-- Variants Panel -->
                <div class="panel-box">
                    <h2>Product Variants (Options)</h2>
                    <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem;">
                        Configure dynamic variables such as Colors, Wattage, or Sizes. Each variant option can have its own price modifier (positive/negative) and a unique variant photo.
                    </p>

                    <div id="variants-container">
                    <?php 
                    $vIndex = 0;
                    foreach ($variants as $v): 
                    ?>
                        <div class="variant-row" id="variant-row-<?php echo $vIndex; ?>">
                            <!-- Hidden ID input for updating -->
                            <input type="hidden" name="variants[<?php echo $vIndex; ?>][id]" value="<?php echo $v['id']; ?>">
                            <input type="hidden" name="variants[<?php echo $vIndex; ?>][current_image]" value="<?php echo htmlspecialchars($v['image'] ?? ''); ?>">
                            
                            <div class="variant-col">
                                <label class="form-label">Type</label>
                                <select name="variants[<?php echo $vIndex; ?>][type]" class="form-input" required>
                                    <option value="Color" <?php echo $v['variant_type'] === 'Color' ? 'selected' : ''; ?>>Color</option>
                                    <option value="Watt" <?php echo $v['variant_type'] === 'Watt' ? 'selected' : ''; ?>>Watt</option>
                                    <option value="Size" <?php echo $v['variant_type'] === 'Size' ? 'selected' : ''; ?>>Size</option>
                                    <option value="Other" <?php echo $v['variant_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="variant-col">
                                <label class="form-label">Value</label>
                                <input type="text" name="variants[<?php echo $vIndex; ?>][value]" class="form-input" value="<?php echo htmlspecialchars($v['variant_value']); ?>" required>
                            </div>
                            <div class="variant-col">
                                <label class="form-label">Price Mod (LKR)</label>
                                <input type="number" step="0.01" name="variants[<?php echo $vIndex; ?>][price_modifier]" class="form-input price-modifier-input" value="<?php echo htmlspecialchars($v['price_modifier']); ?>" required oninput="updateAllVariantPrices()">
                            </div>
                            <div class="variant-col">
                                <label class="form-label">Variant Price (LKR)</label>
                                <input type="number" step="0.01" class="form-input variant-final-price" style="background: rgba(255,255,255,0.05); color: #ffbd00; font-weight: 700;" value="0.00" oninput="updatePriceModFromFinal(this)">
                            </div>
                            <div class="variant-col">
                                <label class="form-label">Stock</label>
                                <input type="number" name="variants[<?php echo $vIndex; ?>][stock]" class="form-input" value="<?php echo $v['stock']; ?>" required>
                            </div>
                            <div class="variant-col">
                                <label class="form-label">Photo</label>
                                <input type="file" name="variant_photo_<?php echo $vIndex; ?>" class="form-input" accept="image/*">
                                <?php if ($v['image']): 
                                    $vSrc = $v['image'];
                                    if (strpos($vSrc, 'http') === false) {
                                        $vSrc = '../' . $vSrc;
                                    }
                                ?>
                                    <img src="<?php echo htmlspecialchars($vSrc); ?>" class="variant-thumb">
                                <?php endif; ?>
                            </div>
                            <div class="variant-col" style="display:flex; align-items:flex-end;">
                                <button type="button" onclick="removeVariantRow(<?php echo $vIndex; ?>, <?php echo $v['id']; ?>)" class="btn-remove-row">Remove</button>
                            </div>
                        </div>
                    <?php 
                        $vIndex++;
                    endforeach; 
                    ?>
                    </div>

                    <button type="button" class="btn-add-var" onclick="addVariantRow()">➕ Add Variant Variable</button>
                </div>
            </div>

            <!-- Submit buttons -->
            <div style="margin-bottom: 4rem;">
                <button type="submit" class="btn-submit">Save Changes</button>
                <a href="products.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        let variantIndex = <?php echo $vIndex; ?>;
        const deletedIds = [];
        const deletedGalleryIds = [];

        function addVariantRow() {
            const container = document.getElementById('variants-container');
            const row = document.createElement('div');
            row.className = 'variant-row';
            row.id = `variant-row-${variantIndex}`;
            
            const isReq = document.getElementById('enable_variants').checked ? 'required' : '';
            
            row.innerHTML = `
                <div class="variant-col">
                    <label class="form-label">Type</label>
                    <select name="variants[${variantIndex}][type]" class="form-input" ${isReq}>
                        <option value="Color">Color</option>
                        <option value="Watt">Watt</option>
                        <option value="Size">Size</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="variant-col">
                    <label class="form-label">Value</label>
                    <input type="text" name="variants[${variantIndex}][value]" class="form-input" placeholder="e.g. Black, 10W, L" ${isReq}>
                </div>
                <div class="variant-col">
                    <label class="form-label">Price Mod (LKR)</label>
                    <input type="number" step="0.01" name="variants[${variantIndex}][price_modifier]" class="form-input price-modifier-input" value="0.00" ${isReq} oninput="updateAllVariantPrices()">
                </div>
                <div class="variant-col">
                    <label class="form-label">Variant Price (LKR)</label>
                    <input type="number" step="0.01" class="form-input variant-final-price" style="background: rgba(255,255,255,0.05); color: #ffbd00; font-weight: 700;" value="0.00" oninput="updatePriceModFromFinal(this)">
                </div>
                <div class="variant-col">
                    <label class="form-label">Stock</label>
                    <input type="number" name="variants[${variantIndex}][stock]" class="form-input" value="10" ${isReq}>
                </div>
                <div class="variant-col">
                    <label class="form-label">Photo *</label>
                    <input type="file" name="variant_photo_${variantIndex}" class="form-input" accept="image/*">
                </div>
                <div class="variant-col" style="display:flex; align-items:flex-end;">
                    <button type="button" onclick="removeVariantRow(${variantIndex}, 0)" class="btn-remove-row">Remove</button>
                </div>
            `;
            container.appendChild(row);
            variantIndex++;
            updateAllVariantPrices();
        }

        function removeVariantRow(index, dbId) {
            const row = document.getElementById(`variant-row-${index}`);
            if (row) {
                row.remove();
                if (dbId > 0) {
                    deletedIds.push(dbId);
                    document.getElementById('deleted_variant_ids').value = deletedIds.join(',');
                }
            }
        }

        function removeGalleryImage(btn, id) {
            if (confirm("Are you sure you want to delete this gallery photo?")) {
                const wrapper = btn.closest('.gallery-thumb-wrapper');
                if (wrapper) {
                    wrapper.remove();
                    deletedGalleryIds.push(id);
                    document.getElementById('deleted_gallery_ids').value = deletedGalleryIds.join(',');
                }
            }
        }

        function updateFinalPrice() {
            const basePrice = parseFloat(document.getElementById('price').value) || 0;
            const discount = parseFloat(document.getElementById('discount_percent').value) || 0;
            const discountPrice = basePrice * (1 - discount / 100);
            document.getElementById('discount_price').value = discountPrice.toFixed(2);
            updateAllVariantPrices();
        }

        function calculateFromDiscountPrice() {
            const basePrice = parseFloat(document.getElementById('price').value) || 0;
            const discountPrice = parseFloat(document.getElementById('discount_price').value) || 0;
            if (basePrice > 0) {
                let percent = ((basePrice - discountPrice) / basePrice) * 100;
                percent = Math.max(0, Math.min(100, percent));
                document.getElementById('discount_percent').value = Math.round(percent);
            }
            updateAllVariantPrices();
        }

        function updateAllVariantPrices() {
            const discountPrice = parseFloat(document.getElementById('discount_price').value) || 0;
            document.querySelectorAll('.variant-row').forEach(row => {
                const modInput = row.querySelector('.price-modifier-input');
                const finalPriceInput = row.querySelector('.variant-final-price');
                if (modInput && finalPriceInput) {
                    const modifier = parseFloat(modInput.value) || 0;
                    finalPriceInput.value = (discountPrice + modifier).toFixed(2);
                }
            });
        }

        function updatePriceModFromFinal(input) {
            const row = input.closest('.variant-row');
            const discountPrice = parseFloat(document.getElementById('discount_price').value) || 0;
            const finalPrice = parseFloat(input.value) || 0;
            const modInput = row.querySelector('.price-modifier-input');
            if (modInput) {
                modInput.value = (finalPrice - discountPrice).toFixed(2);
            }
        }

        // Setup variants toggle listeners
        document.addEventListener('DOMContentLoaded', () => {
            // Price inputs listener for dynamic calculations
            const priceInput = document.getElementById('price');
            const discountPriceInput = document.getElementById('discount_price');
            const discountInput = document.getElementById('discount_percent');
            priceInput.addEventListener('input', updateFinalPrice);
            discountInput.addEventListener('input', updateFinalPrice);
            discountPriceInput.addEventListener('input', calculateFromDiscountPrice);
            
            // Calculate initial final prices
            updateFinalPrice();

            const enableToggle = document.getElementById('enable_variants');
            const wrapper = document.getElementById('variants-section-wrapper');
            
            enableToggle.addEventListener('change', () => {
                if (enableToggle.checked) {
                    wrapper.style.display = 'block';
                    wrapper.querySelectorAll('select, input[type="text"], input[type="number"]').forEach(el => {
                        if (el.name.includes('[type]') || el.name.includes('[value]') || el.name.includes('[price_modifier]') || el.name.includes('[stock]')) {
                            el.required = true;
                        }
                    });
                    if (document.getElementById('variants-container').children.length === 0) {
                        addVariantRow();
                    }
                } else {
                    wrapper.style.display = 'none';
                    wrapper.querySelectorAll('select, input').forEach(el => {
                        el.required = false;
                    });
                }
            });

            // Flash Sale Toggle
            const flashToggle = document.getElementById('enable_flash_sale');
            const flashFields = document.getElementById('flash_sale_fields');
            
            // Initialize Flatpickr
            const fpStart = flatpickr("#flash_sale_start", {
                enableTime: true,
                dateFormat: "Y-m-d H:i:00",
                altInput: true,
                altFormat: "d/m/Y h:i K",
                time_24hr: false
            });
            const fpEnd = flatpickr("#flash_sale_end", {
                enableTime: true,
                dateFormat: "Y-m-d H:i:00",
                altInput: true,
                altFormat: "d/m/Y h:i K",
                time_24hr: false
            });

            flashToggle.addEventListener('change', () => {
                flashFields.style.display = flashToggle.checked ? 'grid' : 'none';
                if (!flashToggle.checked) {
                    document.getElementById('flash_sale_price').value = '';
                    fpStart.clear();
                    fpEnd.clear();
                }
            });

            // Toggle custom country input
            const selectEl = document.getElementById('rate-country-select');
            const customInput = document.getElementById('custom-country-code');
            if (selectEl) {
                selectEl.addEventListener('change', function() {
                    if (this.value === 'CUSTOM') {
                        customInput.style.display = 'block';
                    } else {
                        customInput.style.display = 'none';
                    }
                });
                
                new TomSelect("#rate-country-select", {
                    create: false,
                    maxOptions: null,
                    placeholder: "Search for a country..."
                });
            }

            // Prepopulate existing product-specific shipping rates
            <?php foreach ($pRates as $pr): ?>
            addProductShippingRateRow('<?php echo $gr_cc = $pr['country_code']; ?>', '<?php echo $gr_fee = $pr['fee']; ?>');
            <?php endforeach; ?>
        });

        function addProductShippingRateRow(country = '', fee = '') {
            const select = document.getElementById('rate-country-select');
            const customCodeInput = document.getElementById('custom-country-code');
            const feeInput = document.getElementById('rate-fee-input');

            let finalCountry = country || select.value;
            if (finalCountry === 'CUSTOM') {
                finalCountry = customCodeInput.value.trim().toUpperCase();
            }
            let finalFee = fee !== '' ? fee : feeInput.value.trim();

            if (!finalCountry || finalCountry.length !== 2) {
                alert('Please select or enter a valid 2-letter country code.');
                return;
            }
            if (finalFee === '' || parseFloat(finalFee) < 0) {
                alert('Please enter a valid delivery fee.');
                return;
            }

            let exists = false;
            document.querySelectorAll('.shipping-rate-row').forEach(row => {
                if (row.getAttribute('data-country') === finalCountry) {
                    exists = true;
                }
            });
            if (exists) {
                alert('Shipping rate for ' + finalCountry + ' is already added.');
                return;
            }

            const container = document.getElementById('shipping-rates-container');
            const div = document.createElement('div');
            div.className = 'shipping-rate-row';
            div.setAttribute('data-country', finalCountry);
            div.style = 'display: flex; justify-content: space-between; align-items: center; background: #ffffff; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 0.5rem;';
            div.innerHTML = `
                <div style="font-weight: 600; color: #0f172a;">
                    <span>🌍 ${finalCountry}</span>
                    <span style="margin-left: 20px; color: #ff5e00;">${parseFloat(finalFee) == 0 ? 'Free Shipping' : 'Rs. ' + parseFloat(finalFee).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2})}</span>
                </div>
                <input type="hidden" name="shipping_rates[${finalCountry}]" value="${parseFloat(finalFee).toFixed(2)}">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" onclick="editProductShippingRateRow('${finalCountry}', '${parseFloat(finalFee).toFixed(2)}', this)" class="btn-rate-edit">✏️ Edit</button>
                    <button type="button" onclick="this.closest('.shipping-rate-row').remove()" class="btn-rate-delete">🗑️ Delete</button>
                </div>
            `;
            container.appendChild(div);

            if (!country) {
                if (select.tomselect) {
                    select.tomselect.setValue('', true);
                } else {
                    select.value = '';
                }
                const freeChk = document.getElementById('rate-free-chk');
                if (freeChk) {
                    freeChk.checked = false;
                }
                feeInput.disabled = false;
                customCodeInput.style.display = 'none';
                customCodeInput.value = '';
                feeInput.value = '';
            }
        }

        function editProductShippingRateRow(country, fee, btn) {
            const select = document.getElementById('rate-country-select');
            const customCodeInput = document.getElementById('custom-country-code');
            const feeInput = document.getElementById('rate-fee-input');

            if (select.tomselect) {
                select.tomselect.setValue(country, true);
            } else {
                select.value = country;
            }

            if (select.value !== country) {
                if (select.tomselect) {
                    select.tomselect.setValue('CUSTOM', true);
                } else {
                    select.value = 'CUSTOM';
                }
                customCodeInput.style.display = 'block';
                customCodeInput.value = country;
            } else {
                customCodeInput.style.display = 'none';
                customCodeInput.value = '';
            }

            feeInput.value = fee;
            const freeChk = document.getElementById('rate-free-chk');
            if (freeChk) {
                if (parseFloat(fee) === 0) {
                    freeChk.checked = true;
                    feeInput.disabled = true;
                } else {
                    freeChk.checked = false;
                    feeInput.disabled = false;
                }
            }
            btn.closest('.shipping-rate-row').remove();
        }
        // Cover Image helpers
        function previewCoverImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('new-cover-preview-img').src = e.target.result;
                    document.getElementById('new-cover-preview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
                // Reset delete if user selects a new image
                const delInput = document.getElementById('delete_cover_image');
                if (delInput) {
                    delInput.value = '0';
                }
            }
        }

        function clearNewCover() {
            const input = document.getElementById('cover_image_input');
            if (input) input.value = '';
            document.getElementById('new-cover-preview').style.display = 'none';
            document.getElementById('new-cover-preview-img').src = '';
        }

        function triggerDeleteCover() {
            if (confirm("Are you sure you want to remove the cover photo?")) {
                const thumb = document.getElementById('current-cover-thumb');
                const hiddenInput = document.getElementById('delete_cover_image');
                if (thumb) thumb.src = '../placeholder.jpg?v=' + Date.now();
                if (hiddenInput) hiddenInput.value = '1';
                clearNewCover();
            }
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
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
</body>
</html>
