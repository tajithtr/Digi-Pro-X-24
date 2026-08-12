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
    header("Location: services.php");
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
    
    // Fetch existing category_id to prevent foreign key constraint failures
    $catCheck = $pdo->prepare("SELECT category_id FROM products WHERE id = ?");
    $catCheck->execute([$id]);
    $existing_cat = $catCheck->fetchColumn();
    $category_id = NULL; // Services don't need categories
    $stock = 999;
    $discount_percent = (int)$_POST['discount_percent'];
    $warranty = isset($_POST['warranty']) ? trim($_POST['warranty']) : '';
    $shipping_fee = 0.00;
    $delivery_days = 0;
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
    
    if ($name !== '' && $price > 0) {
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
            
            $success = "Service updated successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update service: " . $e->getMessage();
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
    header("Location: services.php");
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service - Digi Pro X 24 Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    
    <!-- Flatpickr Date/Time Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
            <li><a href="services.php" <?php echo (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'class="active"' : ''; ?>>🛠️ Services</a></li>
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
                    <h1>Edit Service</h1>
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

        <form action="service_edit.php?id=<?php echo $id; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($product['image']); ?>">
            <input type="hidden" name="deleted_variant_ids" id="deleted_variant_ids" value="">

            <!-- Product Info Panel -->
            <div class="panel-box">
                <h2>Service Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">Service Name *</label>
                        <input type="text" name="name" id="name" class="form-input" value="<?php echo htmlspecialchars($product['name']); ?>" required>
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
                    
                    
                    
                    
                    
                    
                                        <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 0.5rem;">
                        <label class="form-label" for="is_disabled" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; color: #dc2626;">
                            <input type="checkbox" name="is_disabled" id="is_disabled" value="1" <?php echo (int)$product['is_disabled'] === 1 ? 'checked' : ''; ?> style="width: 1.2rem; height: 1.2rem; cursor: pointer; accent-color: #dc2626;">
                            <span>Disable Service (Hide from storefront)</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" style="background: #f8fafc; padding: 1.2rem; border-radius: 12px; border: 1px solid var(--border-color);">
                    <label class="form-label">Service Cover Image</label>

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
                    <label class="form-label" for="gallery_images">Additional Service Photos (Gallery)</label>
                    <div style="margin-bottom: 1.5rem;">
                        <input type="file" name="gallery_images[]" id="gallery_images" class="form-input" accept="image/*" multiple>
                        <span style="font-size:0.8rem; color:var(--text-muted); margin-top:0.3rem; display:block;">You can select multiple files at once.</span>
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

            <!-- Submit buttons -->
            <div style="margin-bottom: 4rem;">
                <button type="submit" class="btn-submit">Save Changes</button>
                <a href="services.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>

        <script>
        const deletedGalleryIds = [];

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

        function previewCoverImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('new-cover-preview-img').src = e.target.result;
                    document.getElementById('new-cover-preview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
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
</body>
</html>
