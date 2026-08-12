<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart_count = array_sum($_SESSION['cart']);
require_once 'config.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header("Location: products.php");
    exit;
}

// Fetch product details
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.is_disabled = 0");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header("Location: products.php");
    exit;
}

if (($product['product_type'] ?? '') === 'local_service') {
    $user_logged_in = isset($_SESSION['user_id']);
    $user_country = strtolower(trim($_SESSION['user_country'] ?? ''));
    $is_sri_lanka = ($user_country === 'sri lanka' || $user_country === 'lk' || $user_country === 'srilanka' || $user_country === 'sl');
    if (!$user_logged_in || !$is_sri_lanka) {
        header("Location: index.php");
        exit;
    }
}

// Fetch product gallery
$gStmt = $pdo->prepare("SELECT * FROM product_gallery WHERE product_id = ? ORDER BY id ASC");
$gStmt->execute([$product_id]);
$gallery = $gStmt->fetchAll(PDO::FETCH_ASSOC);

// If main image is placeholder but gallery has images, promote the first gallery image
if ((empty($product['image']) || $product['image'] === 'placeholder.jpg') && count($gallery) > 0) {
    $product['image'] = $gallery[0]['image_path'];
    array_shift($gallery);
}

$is_free_shipping = is_product_free_shipping($product_id, $product['shipping_fee'] ?? 450.00, $product['product_type'] ?? 'product');

// Fetch product variants
$vStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
$vStmt->execute([$product_id]);
$variants = $vStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch related products (same category, excluding current product)
$rStmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.category_id = ? AND p.id != ? AND p.is_disabled = 0 LIMIT 4");
$rStmt->execute([$product['category_id'], $product_id]);
$related_products = $rStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch approved reviews for this product
$revStmt = $pdo->prepare("SELECT * FROM reviews WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC");
$revStmt->execute([$product_id]);
$approved_reviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

$qStmt = $pdo->prepare("SELECT * FROM product_questions WHERE product_id = ? AND is_approved = 1 ORDER BY created_at DESC");
$qStmt->execute([$product_id]);
$product_questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
$avg_rating = 0;
if (count($approved_reviews) > 0) {
    $total_stars = array_sum(array_column($approved_reviews, 'rating'));
    $avg_rating = round($total_stars / count($approved_reviews), 1);
}


// Calculate prices
$original_price_lkr = $product['price'];
$discount_percent = $product['discount_percent'] ?? 0;
$current_price_lkr = $original_price_lkr;
$is_flash_sale = false;

// Check if flash sale is active
if (!empty($product['flash_sale_price']) && !empty($product['flash_sale_start']) && !empty($product['flash_sale_end'])) {
    $now = new DateTime();
    $start = new DateTime($product['flash_sale_start']);
    $end = new DateTime($product['flash_sale_end']);
    if ($now >= $start && $now < $end) {
        $is_flash_sale = true;
        $current_price_lkr = $product['flash_sale_price'];
        $discount_percent = $original_price_lkr > 0 ? round((($original_price_lkr - $current_price_lkr) / $original_price_lkr) * 100) : 0;
    } else {
        if ($discount_percent > 0) {
            $current_price_lkr = $original_price_lkr * (1 - ($discount_percent / 100));
        }
    }
} else {
    if ($discount_percent > 0) {
        $current_price_lkr = $original_price_lkr * (1 - ($discount_percent / 100));
    }
}

// Warranty color coding logic
$w_text = strtolower($product['warranty'] ?? '');
if (strpos($w_text, 'year') !== false) {
    // Gold/Yellow for Years
    $style = ['color' => '#ffbd00', 'bg' => 'rgba(255, 189, 0, 0.1)', 'border' => 'rgba(255, 189, 0, 0.3)', 'glow' => 'rgba(255, 189, 0, 0.4)'];
} elseif (strpos($w_text, 'month') !== false) {
    // Blue/Cyan for Months
    $style = ['color' => '#06b6d4', 'bg' => 'rgba(6, 182, 212, 0.1)', 'border' => 'rgba(6, 182, 212, 0.3)', 'glow' => 'rgba(6, 182, 212, 0.4)'];
} else {
    // Default Green
    $style = ['color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.1)', 'border' => 'rgba(16, 185, 129, 0.3)', 'glow' => 'rgba(16, 185, 129, 0.4)'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="https://digiprox24.com/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy <?php echo htmlspecialchars($product['name']); ?> Online in Sri Lanka | DigiPro X24</title>
    <meta name="description" content="Buy <?php echo htmlspecialchars($product['name']); ?> online at DigiPro X24 Sri Lanka. Best price, official warranty, and fast islandwide cash on delivery.">
    <link rel="canonical" href="https://digiprox24.com/product_detail.php?id=<?php echo (int)$product['id']; ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="product">
    <meta property="og:url" content="https://digiprox24.com/product_detail.php?id=<?php echo (int)$product['id']; ?>">
    <meta property="og:title" content="Buy <?php echo htmlspecialchars($product['name']); ?> Online in Sri Lanka | DigiPro X24">
    <meta property="og:description" content="Buy <?php echo htmlspecialchars($product['name']); ?> at DigiPro X24. Best price, warranty & fast delivery in Sri Lanka.">
    <meta property="og:image" content="https://digiprox24.com/<?php echo htmlspecialchars($product['image']); ?>">
    <meta property="product:price:amount" content="<?php echo (float)$current_price_lkr; ?>">
    <meta property="product:price:currency" content="LKR">
    <meta property="og:site_name" content="DigiPro X24">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://digiprox24.com/product_detail.php?id=<?php echo (int)$product['id']; ?>">
    <meta property="twitter:title" content="Buy <?php echo htmlspecialchars($product['name']); ?> Online in Sri Lanka | DigiPro X24">
    <meta property="twitter:description" content="Buy <?php echo htmlspecialchars($product['name']); ?> at DigiPro X24. Best price, warranty & fast delivery in Sri Lanka.">
    <meta property="twitter:image" content="https://digiprox24.com/<?php echo htmlspecialchars($product['image']); ?>">

    <!-- Schema.org Product Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org/",
      "@type": "Product",
      "name": "<?php echo htmlspecialchars($product['name']); ?>",
      "image": "https://digiprox24.com/<?php echo htmlspecialchars($product['image']); ?>",
      "description": "<?php echo htmlspecialchars(substr(strip_tags($product['description'] ?? ''), 0, 200)); ?>",
      "brand": {
        "@type": "Brand",
        "name": "DigiPro X24"
      },
      "itemCondition": "https://schema.org/NewCondition",
      "offers": {
        "@type": "Offer",
        "url": "https://digiprox24.com/product_detail.php?id=<?php echo (int)$product['id']; ?>",
        "priceCurrency": "LKR",
        "price": "<?php echo (float)$current_price_lkr; ?>",
        "itemCondition": "https://schema.org/NewCondition",
        "availability": "<?php echo (int)$product['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'; ?>"
      }
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Home",
          "item": "https://digiprox24.com/"
        },
        {
          "@type": "ListItem",
          "position": 2,
          "name": "Products",
          "item": "https://digiprox24.com/products.php"
        },
        <?php if (!empty($product['category_id'])): ?>
        {
          "@type": "ListItem",
          "position": 3,
          "name": "<?php echo htmlspecialchars($product['category_name'] ?? 'Category'); ?>",
          "item": "https://digiprox24.com/products.php?category=<?php echo (int)$product['category_id']; ?>"
        },
        {
          "@type": "ListItem",
          "position": 4,
          "name": "<?php echo htmlspecialchars($product['name']); ?>",
          "item": "https://digiprox24.com/product_detail.php?id=<?php echo (int)$product['id']; ?>"
        }
        <?php else: ?>
        {
          "@type": "ListItem",
          "position": 3,
          "name": "<?php echo htmlspecialchars($product['name']); ?>",
          "item": "https://digiprox24.com/product_detail.php?id=<?php echo (int)$product['id']; ?>"
        }
        <?php endif; ?>
      ]
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .detail-page {
            padding: 80px 0 0;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .breadcrumb-wrap {
            max-width: 1600px;
            margin: 0 auto;
            padding: 1.2rem 3%;
        }

        .detail-section {
            max-width: 1280px;
            margin: 0 auto;
            background: rgba(13, 16, 21, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,94,0,0.15);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        }

        .tabs-outer {
            max-width: 1280px;
            margin: 0 auto;
            padding: 3rem 2.2rem;
        }

        .related-outer {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2.2rem 4rem;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.6rem 1.2rem;
            border-radius: 100px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .btn-back:hover {
            background: rgba(255, 94, 0, 0.1);
            border-color: var(--primary-glow);
            color: var(--primary-glow);
            box-shadow: 0 0 20px rgba(255, 94, 0, 0.2);
            transform: translateX(-4px);
        }

        .breadcrumb {
            margin-bottom: 2rem;
            font-size: 0.95rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.6rem;
            font-weight: 500;
        }

        .breadcrumb a {
            color: var(--primary-glow);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .breadcrumb a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 1px;
            bottom: -2px;
            left: 0;
            background-color: var(--primary-glow);
            transition: width 0.3s ease;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
            text-shadow: 0 0 10px rgba(255, 94, 0, 0.4);
        }

        .breadcrumb a:hover::after {
            width: 100%;
        }

        .breadcrumb .separator {
            color: rgba(255, 255, 255, 0.2);
            font-weight: 300;
            font-size: 0.9rem;
        }

        .breadcrumb .current {
            color: var(--text-main);
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .detail-container {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 2.5rem;
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            align-items: start;
            padding: 1.5rem;
        }

        @media (max-width: 1100px) {
            .detail-container {
                grid-template-columns: 1.2fr 1fr;
                gap: 1.5rem;
            }
        }

        @media (max-width: 900px) {
            .detail-container {
                grid-template-columns: minmax(0, 1fr) !important;
            }
        }

        /* Left gallery column */
        .media-gallery-col {
            padding: 1rem;
            position: sticky;
            top: 80px;
            align-self: start;
        }

        /* Right info column */
        .info-col {
            padding: 1.5rem;
            overflow-y: auto;
            background: rgba(13, 16, 21, 0.4);
            border: 1px solid rgba(255, 94, 0, 0.15);
            border-radius: 12px;
        }

        @media (max-width: 900px) {
            .detail-page {
                padding-bottom: 6rem;
            }
            .detail-section {
                margin: 0 10px 1.5rem !important;
                border-radius: 20px !important;
                border: 1px solid rgba(255,94,0,0.08) !important;
            }
            .breadcrumb-wrap {
                padding: 0.75rem 4% 0.25rem;
            }
            .media-gallery-col {
                position: relative !important;
                top: 0 !important;
                border-right: none !important;
            }
            .info-col {
                overflow: hidden !important;
            }
            .media-gallery-col,
            .info-col {
                padding: 1rem;
            }
            .tabs-outer {
                padding: 1.5rem 1rem !important;
            }
            .related-outer {
                padding: 0 1rem 3rem !important;
            }
            .reviews-outer {
                padding: 0 1rem 3rem !important;
            }
            .products-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 0.75rem !important;
            }
            .purchase-action-row {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.8rem !important;
            }
            .qty-controls {
                width: 100% !important;
                height: 52px !important;
                min-height: 52px !important;
                justify-content: center !important;
            }
            .btn-buy,
            .btn-detail-cart {
                width: 100% !important;
                height: 60px !important;
                min-height: 60px !important;
                font-size: 1.25rem !important;
            }
        }

        /* Gallery Styles */
        .media-gallery {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .main-image-container {
            position: relative;
            width: 100%;
            height: 60vh;
            min-height: 420px;
            max-height: 620px;
            border-radius: 20px;
            overflow: hidden;
            border: 2.5px solid rgba(255, 94, 0, 0.25);
            background: rgba(13, 16, 21, 0.5);
            backdrop-filter: blur(10px);
            box-shadow: 0 0 0 4px rgba(255,94,0,0.05), 0 12px 40px rgba(0,0,0,0.4);
            cursor: zoom-in;
            transition: box-shadow 0.3s;
            margin-bottom: 1.2rem;
        }

        .main-image-container:hover {
            box-shadow: 0 0 0 5px rgba(255,94,0,0.18), 0 20px 50px rgba(255,94,0,0.18);
        }

        .slide-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: var(--primary-glow);
            width: 0%;
            z-index: 20;
            transition: none;
        }

        @media (max-width: 576px) {
            .main-image-container {
                height: 300px;
                min-height: auto !important;
            }
            .thumbnail-card {
                width: 58px !important;
                height: 58px !important;
                border-radius: 8px !important;
            }
            .breadcrumb {
                font-size: 0.78rem;
                flex-wrap: wrap;
                margin-bottom: 0.4rem;
            }
            .breadcrumb-wrap {
                padding: 0.5rem 4% 0.25rem;
            }
            .detail-section {
                margin: 0 8px 1rem !important;
            }
        }

        .main-image-container img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.5s ease;
        }

        .main-image-container img:hover {
            transform: scale(1.03);
        }

        .nav-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 10;
            user-select: none;
            outline: none;
            padding: 0;
            line-height: 1;
        }

        .nav-arrow:hover {
            background: var(--primary-glow);
            border-color: transparent;
            box-shadow: 0 0 15px rgba(255, 94, 0, 0.4);
        }

        .arrow-left { left: 15px; }
        .arrow-right { right: 15px; }

        .thumbnail-row {
            display: flex;
            gap: 0.8rem;
            overflow-x: auto;
            padding-top: 0.8rem;
            padding-bottom: 0.8rem;
            margin-top: -0.8rem;
            max-width: 100%;
        }

        .thumbnail-card {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            overflow: hidden;
            border: 2.5px solid rgba(255, 94, 0, 0.15);
            cursor: pointer;
            transition: all 0.25s;
            flex-shrink: 0;
            background: rgba(13, 16, 21, 0.5);
            position: relative;
        }

        .thumbnail-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .thumbnail-card:hover img {
            transform: scale(1.08);
        }

        .thumbnail-card.active {
            border-color: #ff5e00;
            box-shadow: 0 0 0 3px rgba(255,94,0,0.2), 0 4px 14px rgba(255,94,0,0.25);
            transform: translateY(-3px);
        }

        .thumbnail-card:hover:not(.active) {
            border-color: rgba(255,94,0,0.45);
            box-shadow: 0 0 0 2px rgba(255,94,0,0.12);
            transform: translateY(-2px);
        }

        /* Slideshow dot indicators */
        .slide-dots {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 0.5rem;
        }

        .slide-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,94,0,0.2);
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            padding: 0;
        }

        .slide-dot.active {
            background: #ff5e00;
            width: 22px;
            border-radius: 4px;
        }

        /* Product Meta Details */
        .info-panel {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            background: transparent;
            border: none;
            backdrop-filter: none;
            box-shadow: none;
        }

        .info-panel .product-category {
            font-size: 0.75rem;
            letter-spacing: 1.5px;
        }

        .info-panel .product-title {
            font-size: 1.45rem;
            line-height: 1.3;
            border-bottom: 1px solid rgba(255,94,0,0.1);
            padding-bottom: 0.8rem;
        }

        .info-panel .price-section {
            background: rgba(255, 94, 0, 0.04);
            border: 1px solid rgba(255, 94, 0, 0.2);
            border-radius: 12px;
            padding: 0.7rem 1rem;
        }

        .info-panel .price-current {
            font-size: 1.6rem;
        }

        .info-panel .price-original {
            font-size: 1.05rem;
        }

        .info-panel .short-desc {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-muted);
            border-left: 3px solid #ff5e00;
            border-radius: 0 8px 8px 0;
            padding: 0.7rem 1rem;
            font-size: 0.88rem;
            line-height: 1.6;
            border-bottom: none;
        }

        .purchase-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            margin-top: 0.3rem;
            padding-top: 0.8rem;
            border-top: 1px solid rgba(255,94,0,0.1);
        }

        .purchase-inline {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            width: 100%;
        }

        .purchase-action-row {
            display: flex;
            align-items: center;
            gap: 1rem;
            width: 100%;
        }

        .cart-status-text {
            font-size: 0.82rem;
            color: #94a3b8;
            font-weight: 600;
        }

        .product-category {
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--primary-glow);
        }

        .product-title {
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1.15;
            color: var(--text-main);
        }

        @media (max-width: 576px) {
            .product-title {
                font-size: 1.6rem;
            }
        }

        @media (max-width: 430px) {
            .product-title {
                font-size: 1.35rem;
            }
            .price-current {
                font-size: 1.6rem;
            }
            .info-panel {
                gap: 0.65rem;
            }
        }

        .price-section {
            display: flex;
            align-items: baseline;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .price-current {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--primary-glow);
        }

        .price-original {
            font-size: 1.4rem;
            color: var(--text-muted);
            text-decoration: line-through;
        }

        .discount-pill {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.25);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 800;
        }

        .short-desc {
            color: var(--text-muted);
            font-size: 1.05rem;
            line-height: 1.7;
            border-bottom: 1px solid rgba(0,0,0,0.06);
            padding-bottom: 1.5rem;
        }

        .variant-selector {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .variant-selector label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .variant-select {
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.85);
            color: var(--text-main);
            font-family: inherit;
            font-weight: 600;
            outline: none;
            transition: all 0.3s;
            cursor: pointer;
            width: 100%;
        }

        .variant-select:focus {
            border-color: var(--primary-glow);
        }

        .purchase-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .qty-controls {
            display: flex;
            align-items: center;
            gap: 0.2rem;
            background: rgba(13, 16, 21, 0.6);
            border: 1px solid rgba(255, 94, 0, 0.25);
            border-radius: 12px;
            padding: 4px;
            height: 52px;
        }

        .qty-btn {
            background: none;
            border: none;
            color: var(--text-main);
            width: 38px;
            height: 38px;
            font-size: 1.2rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            border-radius: 8px;
        }

        .qty-btn:hover {
            background: var(--primary-light);
            color: var(--primary-glow);
        }

        .qty-input {
            width: 45px;
            text-align: center;
            background: none;
            border: none;
            color: var(--text-main);
            font-family: inherit;
            font-size: 1.1rem;
            font-weight: 600;
            outline: none;
        }

        .btn-buy {
            height: 64px;
            border-radius: 50px;
            border: none;
            background: #3665f3;
            color: #fff;
            font-size: 1.25rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-buy:hover {
            background: #2b50c5;
            transform: translateY(-1px);
        }

        .btn-detail-cart {
            width: 100%;
            height: 64px;
            border-radius: 50px;
            border: 1px solid #3665f3;
            background: transparent;
            color: #3665f3;
            font-size: 1.25rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-detail-cart:hover {
            background: rgba(54, 101, 243, 0.1);
        }

        .btn-disabled {
            background: rgba(0,0,0,0.06);
            color: var(--text-muted);
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Description Tabs */
        .tabs-section {
            margin-top: 5rem;
        }

        .tabs-header {
            display: flex;
            gap: 1.5rem;
            border-bottom: 1px solid var(--glass-border);
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .tab-btn {
            padding: 1rem 0.5rem;
            font-weight: 600;
            color: var(--text-muted);
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
            font-size: 1.05rem;
            white-space: nowrap;
        }

        .tab-btn.active {
            color: var(--primary-glow);
            border-bottom-color: var(--primary-glow);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.4s ease forwards;
            line-height: 1.8;
            color: var(--text-muted);
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Related Products Section */
        .related-section {
            margin-top: 6rem;
            border-top: 1px solid var(--glass-border);
            padding-top: 5rem;
        }

        .related-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 3rem;
            text-align: center;
        }

        .related-title span {
            color: var(--primary-glow);
        }

        /* Warranty Banner */
        @keyframes warrantyPulse {
            0%, 100% { box-shadow: 0 0 0 0 var(--w-glow, rgba(251,191,36,0.4)); }
            50%        { box-shadow: 0 0 0 8px transparent; }
        }

        .warranty-banner {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            border: 1.5px solid var(--w-border);
            background: var(--w-bg);
            color: var(--w-color);
            animation: warrantyPulse 2.5s ease-in-out infinite;
            position: relative;
            overflow: hidden;
        }

        .warranty-banner::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, transparent 40%, rgba(255,255,255,0.06) 50%, transparent 60%);
            animation: shimmer 3s linear infinite;
        }

        @keyframes shimmer {
            from { transform: translateX(-100%); }
            to   { transform: translateX(100%); }
        }

        .warranty-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
            line-height: 1;
        }

        .warranty-text-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
        }

        .warranty-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.7;
        }

        .warranty-value {
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        /* Payment Methods & Delivery Block */
        .payment-installment-banner {
            background: linear-gradient(135deg, rgba(255,94,0,0.07), rgba(255,189,0,0.05));
            border: 1px solid rgba(255,94,0,0.15);
            border-radius: 10px;
            padding: 0.6rem 0.9rem;
            margin-top: 0;
        }

        .installment-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .installment-icon {
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .installment-main {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .installment-sub {
            display: block;
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 0.1rem;
        }

        .delivery-row {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.55rem 0.9rem;
            background: rgba(16,185,129,0.05);
            border: 1px solid rgba(16,185,129,0.15);
            border-radius: 10px;
        }

        .delivery-icon {
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .delivery-label {
            display: block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #10b981;
            opacity: 0.8;
        }

        .delivery-dates {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 0;
        }

        .payment-methods-bar {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            padding: 0.6rem 0;
            background: transparent;
            border: none;
        }

        .pm-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
        }

        .pm-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .pm-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
        }

        .pm-logo-card {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.45rem 0.9rem 0.45rem 0.6rem;
            border-radius: 10px;
            border: 1.5px solid rgba(255,94,0,0.12);
            background: #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            font-size: 0.78rem;
            font-weight: 700;
            color: var(--text-main);
            transition: all 0.2s;
            user-select: none;
        }

        .pm-logo-card:hover {
            border-color: rgba(255,94,0,0.3);
            box-shadow: 0 3px 12px rgba(255,94,0,0.12);
            transform: translateY(-1px);
        }

        .pm-logo-card svg {
            flex-shrink: 0;
        }


        /* Fullscreen Lightbox */
        .lightbox-overlay {
            position: fixed;
            inset: 0;
            background: rgba(4, 8, 20, 0.96);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            animation: lbFadeIn 0.3s ease;
        }

        .lightbox-overlay.active {
            display: flex;
        }

        @keyframes lbFadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .lightbox-img-wrap {
            position: relative;
            max-width: 92vw;
            max-height: 88vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .lightbox-img-wrap img {
            max-width: 90vw;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 16px;
            border: 2px solid rgba(255,94,0,0.4);
            box-shadow: 0 0 80px rgba(255,94,0,0.3);
            animation: lbZoomIn 0.35s cubic-bezier(.175,.885,.32,1.275);
        }

        @keyframes lbZoomIn {
            from { transform: scale(0.85); opacity: 0; }
            to   { transform: scale(1);    opacity: 1; }
        }

        .lb-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,94,0,0.3);
            border: 1.5px solid rgba(255,94,0,0.5);
            color: #fff;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            outline: none;
            padding: 0;
            z-index: 2;
            user-select: none;
        }

        .lb-btn:hover {
            background: #ff5e00;
            box-shadow: 0 0 20px rgba(255,94,0,0.5);
        }

        .lb-prev { left: -70px; }
        .lb-next { right: -70px; }

        .lb-close {
            position: absolute;
            top: -50px;
            right: 0;
            background: rgba(239,68,68,0.3);
            border: 1.5px solid rgba(239,68,68,0.5);
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            outline: none;
            padding: 0;
        }

        .lb-close:hover {
            background: #ef4444;
        }

        .lb-counter {
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .zoom-hint {
            position: absolute;
            bottom: 14px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255,94,0,0.85);
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            z-index: 15;
            pointer-events: none;
            letter-spacing: 0.5px;
            opacity: 0.9;
        }

        /* Autoplay pause button */
        .autoplay-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            background: rgba(255,94,0,0.7);
            border: none;
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            cursor: pointer;
            z-index: 15;
            transition: 0.2s;
            outline: none;
        }

        .autoplay-btn:hover {
            background: #ff5e00;
        }


        /* Reviews Section */
        .reviews-outer {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2.2rem 3rem;
        }

        .reviews-section-title {
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .reviews-section-title span { color: var(--primary-glow); }

        .avg-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(251,191,36,0.1);
            border: 1px solid rgba(251,191,36,0.25);
            color: #fbbf24;
            padding: 0.3rem 0.9rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .review-form-card {
            background: rgba(13, 16, 21, 0.85);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 94, 0, 0.25);
            border-radius: 16px;
            padding: 1.8rem;
            margin-bottom: 2.5rem;
        }

        .review-form-card h3 {
            margin: 0 0 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .rf-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 600px) {
            .rf-row { grid-template-columns: 1fr; }
        }

        .rf-input {
            padding: 0.7rem 1rem;
            border-radius: 10px;
            border: 1.5px solid rgba(255, 94, 0, 0.25);
            background: rgba(13, 16, 21, 0.6);
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s;
            width: 100%;
            box-sizing: border-box;
        }

        .rf-input:focus { border-color: #ff5e00; }

        .rf-textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            border: 1.5px solid rgba(255, 94, 0, 0.25);
            background: rgba(13, 16, 21, 0.6);
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
            resize: vertical;
            min-height: 80px;
            transition: border-color 0.2s;
            margin-bottom: 1rem;
        }

        .rf-textarea:focus { border-color: #ff5e00; }

        /* Star rating input */
        .star-rating-input {
            display: flex;
            gap: 0.3rem;
            margin-bottom: 1rem;
        }

        .star-rating-input .star-btn {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #d1d5db;
            transition: all 0.15s;
            padding: 0;
            line-height: 1;
        }

        .star-rating-input .star-btn.active,
        .star-rating-input .star-btn:hover {
            color: #fbbf24;
            transform: scale(1.15);
        }

        .rf-submit {
            padding: 0.7rem 2rem;
            border-radius: 10px;
            border: none;
            background: #ff5e00;
            color: #fff;
            font-family: inherit;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .rf-submit:hover { background: #e02424; transform: translateY(-1px); }

        .rf-toast {
            padding: 0.8rem 1.2rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            display: none;
        }

        .rf-toast.success {
            background: rgba(16,185,129,0.1);
            border: 1px solid rgba(16,185,129,0.3);
            color: #059669;
            display: block;
        }

        .rf-toast.error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #ef4444;
            display: block;
        }

        /* Review cards list */
        .review-list {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .review-item {
            background: rgba(13, 16, 21, 0.85);
            border: 1px solid rgba(255,94,0,0.08);
            border-radius: 14px;
            padding: 1.3rem 1.5rem;
            transition: all 0.2s;
        }

        .review-item:hover {
            border-color: rgba(255,94,0,0.18);
            box-shadow: 0 4px 15px rgba(255,94,0,0.06);
        }

        .ri-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.4rem;
        }

        .ri-author {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .ri-date {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .ri-stars {
            color: #fbbf24;
            font-size: 0.95rem;
            letter-spacing: 1px;
            margin-bottom: 0.4rem;
        }

        .ri-text {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.65;
        }

        .no-reviews-msg {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Center aligned title */
        .reviews-section-title-centered {
            text-align: center;
            font-size: 2.2rem;
            font-weight: 800;
            margin-top: 4rem;
            margin-bottom: 2rem;
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
            text-transform: capitalize;
        }

        /* Summary Header Row */
        .reviews-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(13, 16, 21, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 94, 0, 0.2);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            gap: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
        }

        @media (max-width: 768px) {
            .reviews-summary-row {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }
        }

        .summary-left {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .summary-stars {
            color: #fbbf24;
            font-size: 1.6rem;
            letter-spacing: 2px;
        }

        .summary-rating-num {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .summary-rating-num a {
            color: var(--primary-glow);
            text-decoration: underline;
        }

        .summary-count {
            font-size: 0.95rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .summary-right-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
            min-width: 220px;
        }

        .btn-write-review, .btn-ask-question {
            padding: 0.75rem 2rem;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            font-family: inherit;
        }

        .btn-write-review {
            background: #ff5e00;
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 12px rgba(255, 94, 0, 0.25);
        }

        .btn-write-review:hover {
            background: #ff5e00;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 94, 0, 0.35);
        }

        .btn-ask-question {
            background: #ffffff;
            color: #ff5e00;
            border: 1.5px solid #ff5e00;
        }

        .btn-ask-question:hover {
            background: rgba(255, 94, 0, 0.05);
            transform: translateY(-2px);
        }

        /* Customer Photos Section */
        .customer-photos-section {
            margin-bottom: 2.5rem;
            background: rgba(255, 255, 255, 0.7);
            border: 1px solid rgba(255, 94, 0, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .customer-photos-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 1rem;
        }

        .photos-grid-scroll {
            display: flex;
            gap: 0.8rem;
            overflow-x: auto;
            padding-bottom: 0.5rem;
        }

        .photos-grid-scroll::-webkit-scrollbar {
            height: 6px;
        }

        .photos-grid-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 94, 0, 0.15);
            border-radius: 10px;
        }

        .photo-thumb-wrapper {
            position: relative;
            width: 75px;
            height: 75px;
            min-width: 75px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.08);
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .photo-thumb-wrapper:hover {
            transform: scale(1.08);
        }

        .photo-thumb-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-see-more {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(255, 94, 0, 0.15);
            text-align: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #ff5e00;
        }

        /* Tabs and Sorting Row */
        .reviews-tabs-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1.5px solid rgba(255, 94, 0, 0.1);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
            gap: 1.5rem;
        }

        @media (max-width: 500px) {
            .reviews-tabs-row {
                flex-direction: column;
                align-items: stretch;
            }
        }

        .reviews-tabs {
            display: flex;
            gap: 1rem;
        }

        .rev-tab {
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1.5px solid transparent;
        }

        .rev-tab.active {
            background: #eff6ff;
            color: #ff5e00;
            border-color: rgba(255, 94, 0, 0.15);
        }

        .rev-tab:not(.active) {
            background: transparent;
            color: var(--text-muted);
        }

        .reviews-sorting select {
            padding: 0.4rem 1.2rem;
            border-radius: 20px;
            border: 1.5px solid rgba(255, 94, 0, 0.15);
            background: #ffffff;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            outline: none;
            cursor: pointer;
        }

        /* Review Item Card Design */
        .ri-new-card {
            background: rgba(13, 16, 21, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 94, 0, 0.15);
            border-radius: 16px;
            padding: 1.8rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
        }

        .ri-new-card:hover {
            border-color: rgba(255, 94, 0, 0.3);
            box-shadow: 0 8px 30px rgba(255, 94, 0, 0.1);
        }

        .ri-card-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .ri-card-stars {
            color: #fbbf24;
            font-size: 1.1rem;
            letter-spacing: 1px;
        }

        .ri-card-date {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        .ri-user-row {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1rem;
        }

        .ri-user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: rgba(255, 94, 0, 0.1);
            color: var(--primary-glow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            border: 1.5px solid rgba(255, 94, 0, 0.25);
        }

        .ri-user-meta {
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }

        .ri-user-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-main);
        }

        .ri-verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
            width: max-content;
        }

        .ri-card-title {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 0.6rem;
        }

        .ri-card-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.65;
            margin-bottom: 1rem;
        }

        .ri-attached-photo {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.08);
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-top: 0.5rem;
        }

        .ri-attached-photo:hover {
            transform: scale(1.05);
        }

        .ri-attached-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* File input upload styling */
        .photo-upload-container {
            margin-bottom: 1.5rem;
        }

        .photo-upload-label {
            display: inline-flex !important;
            align-items: center;
            gap: 0.5rem;
            background: #eff6ff;
            color: #000000 !important;
            border: 1.5px dashed rgba(255, 94, 0, 0.3);
            padding: 0.6rem 1.5rem;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .photo-upload-label:hover {
            background: #dbeafe;
            border-color: #ff5e00;
        }

        .photo-upload-input {
            display: none;
        }

        .upload-preview-wrap {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-top: 0.8rem;
        }

        .upload-preview-thumb {
            width: 50px;
            height: 50px;
            border-radius: 6px;
            object-fit: cover;
            border: 1px solid rgba(0, 0, 0, 0.1);
            display: none;
        }

        .upload-preview-clear {
            display: none;
            align-items: center;
            gap: 0.3rem;
            background: rgba(239, 68, 68, 0.1) !important;
            color: #ef4444 !important;
            border: 1px solid rgba(239, 68, 68, 0.2) !important;
            padding: 0.3rem 0.8rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .upload-preview-clear:hover {
            background: rgba(239, 68, 68, 0.2) !important;
            border-color: #ef4444 !important;
        }

    </style>
</head>
<body>

    <!-- Background Orbs -->
    <div class="bg-orb orb-1"></div>
    <div class="bg-orb orb-2"></div>
    <div class="bg-orb orb-3"></div>

    <!-- Global Header -->
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
                <?php $uc = strtolower(trim($_SESSION['user_country'] ?? '')); if (isset($_SESSION['user_id']) && ($uc === 'sri lanka' || $uc === 'lk' || $uc === 'srilanka' || $uc === 'sl')): ?>
                <?php $uc = strtolower(trim($_SESSION['user_country'] ?? '')); if (isset($_SESSION['user_id']) && ($uc === 'sri lanka' || $uc === 'lk' || $uc === 'srilanka' || $uc === 'sl')): ?>
                <li><a href="services.php">Services</a></li>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="my_orders.php">My Orders</a></li>
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

    <main class="detail-page">
        <!-- Back Button -->
        <div style="margin-bottom: 0.5rem; padding: 0.5rem 4% 0;">
            <a href="<?php 
                $ref = $_SERVER['HTTP_REFERER'] ?? 'products.php';
                echo (strpos($ref, $_SERVER['HTTP_HOST'] ?? '') !== false) ? htmlspecialchars($ref) : 'products.php';
            ?>" class="btn-back">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </a>
        </div>
        
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-wrap">
            <div class="breadcrumb">
                <a href="index.php">Home</a> <span class="separator">/</span>
                <?php if (($product['product_type'] ?? '') === 'local_service'): ?>
                    <a href="services.php">Services</a> <span class="separator">/</span>
                <?php else: ?>
                    <a href="products.php">Products</a> <span class="separator">/</span>
                    <a href="products.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a> <span class="separator">/</span>
                <?php endif; ?>
                <span class="current"><?php echo htmlspecialchars($product['name']); ?></span>
            </div>
        </div>

        <div class="detail-section">
        <div class="detail-container">
            <!-- Left Column: Gallery -->
            <div class="media-gallery-col media-gallery">
                <div class="main-image-container" id="main-img-container" style="position: relative;">
                        <?php if($is_free_shipping && (!isset($product['product_type']) || $product['product_type'] !== 'local_service')): ?>
                            <div class="free-shipping-badge" style="position: absolute; top: 15px; right: 15px; background: rgba(139, 92, 246, 0.95); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 6px 14px; border-radius: 20px; font-size: 0.85rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 7; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.35); display: flex; align-items: center; gap: 0.3rem;"><span style="font-size: 1rem;">🚚</span> FREE SHIPPING</div>
                        <?php endif; ?>
                        <?php if(isset($product['is_new_arrival']) && $product['is_new_arrival']): ?>
                            <div class="new-badge" style="position: absolute; top: 15px; left: 15px; background: rgba(16, 185, 129, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 6; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);">NEW</div>
                        <?php endif; ?>
                        <?php if($discount_percent > 0): ?>
                             <div class="discount-badge" style="position: absolute; top: <?php echo (isset($product['is_new_arrival']) && $product['is_new_arrival']) ? '55px' : '15px'; ?>; left: 15px; background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow)); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 6; box-shadow: 0 4px 10px rgba(255, 94, 0, 0.35);"><?php echo $discount_percent; ?>% OFF</div>
                        <?php endif; ?>
                        <img id="main-product-img" src="<?php echo htmlspecialchars(get_product_image_url($product['image'])); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onclick="openLightbox(currentIdx)" style="cursor:zoom-in;">
                        
                        <?php if (count($gallery) > 0): ?>
                        <button class="nav-arrow arrow-left" onclick="navigateGallery(-1); event.stopPropagation();">&#10094;</button>
                        <button class="nav-arrow arrow-right" onclick="navigateGallery(1); event.stopPropagation();">&#10095;</button>
                        <button class="autoplay-btn" id="autoplay-btn" onclick="toggleAutoplay(); event.stopPropagation();" title="Pause slideshow">⏸</button>
                        <div class="slide-progress" id="slide-progress"></div>
                        <?php endif; ?>
                        
                    <div class="zoom-hint">&#128269; Click to zoom</div>
                </div>

                <?php if (count($gallery) > 0): ?>
                <div class="thumbnail-row">
                    <div class="thumbnail-card active" onclick="selectThumbnail(0, '<?php echo htmlspecialchars(get_product_image_url($product['image'])); ?>')">
                        <img src="<?php echo htmlspecialchars(get_product_image_url($product['image'])); ?>" alt="Main Photo">
                    </div>
                    <?php 
                    $thumb_idx = 1;
                    foreach ($gallery as $gImg): 
                    ?>
                        <div class="thumbnail-card" onclick="selectThumbnail(<?php echo $thumb_idx; ?>, '<?php echo htmlspecialchars($gImg['image_path']); ?>')">
                            <img src="<?php echo htmlspecialchars(get_product_image_url($gImg['image_path'])); ?>" alt="Gallery Photo">
                        </div>
                    <?php 
                        $thumb_idx++;
                    endforeach; 
                    ?>
                </div>
                <!-- Slide indicator dots -->
                <div class="slide-dots" id="slide-dots"></div>
                <?php endif; ?>
            </div>

            <!-- Right Column: Info Panel -->
            <div class="info-col">
            <div class="info-panel">
                <?php if (($product['product_type'] ?? '') !== 'local_service'): ?>
                <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                <?php endif; ?>
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <?php if (($product['product_type'] ?? '') !== 'local_service'): ?>
                <div style="margin: 0.8rem 0 1.5rem 0;">
                    <?php if ((int)$product['stock'] > 0): ?>
                        <span style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                            <span style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; display: inline-block; box-shadow: 0 0 8px #10b981;"></span> In Stock (<span id="stock-badge-qty"><?php echo (int)$product['stock']; ?></span> Available)
                        </span>
                    <?php else: ?>
                        <span style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 0.4rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                            <span style="width: 8px; height: 8px; background: #ef4444; border-radius: 50%; display: inline-block; box-shadow: 0 0 8px #ef4444;"></span> Out of Stock (0 Available)
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if(!empty($product['warranty'])): ?>
                    <?php
                        // CSS custom properties per warranty tier
                        $wCssColor  = $style['color'];
                        $wCssBg     = $style['bg'];
                        $wCssBorder = $style['border'];
                        $wCssGlow   = $style['glow'];
                    ?>
                    <div class="warranty-banner" style="--w-color:<?php echo $wCssColor;?>; --w-bg:<?php echo $wCssBg;?>; --w-border:<?php echo $wCssBorder;?>; --w-glow:<?php echo $wCssGlow;?>;">
                        <div class="warranty-icon">🛡️</div>
                        <div class="warranty-text-wrap">
                            <span class="warranty-label">Warranty</span>
                            <span class="warranty-value"><?php echo htmlspecialchars($product['warranty']); ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php 
                if (($product['product_type'] ?? '') !== 'local_service'):
                    $del_days = (int)($product['delivery_days'] ?? 3);
                    if ($del_days <= 0) $del_days = 3;
                    $del_sub = ($del_days >= 7) ? "Special Order / Out-of-Store Dispatch" : "In-Stock Store Dispatch";
                    
                    // Fetch shipping rate based on user country
                    $user_shipping_fee = null;
                    $user_country_name = '';
                    $has_user_shipping = false;
                    if (isset($_SESSION['user_id'])) {
                        $raw_country = trim($_SESSION['user_country'] ?? '');
                        if (!empty($raw_country)) {
                            $normalized_code = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $raw_country));
                            if ($normalized_code === 'lk' || $normalized_code === 'lka' || $normalized_code === 'srilanka' || $normalized_code === 'sl') {
                                $normalized_code = 'LK';
                                $user_country_name = 'Sri Lanka';
                            } else {
                                $normalized_code = strtoupper($normalized_code);
                                $country_map = [
                                    'US' => 'United States', 'GB' => 'United Kingdom', 'AU' => 'Australia', 'CA' => 'Canada',
                                    'LK' => 'Sri Lanka', 'IN' => 'India', 'AE' => 'United Arab Emirates', 'SG' => 'Singapore',
                                    'NZ' => 'New Zealand', 'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy',
                                    'ES' => 'Spain', 'NL' => 'Netherlands', 'SE' => 'Sweden', 'CH' => 'Switzerland',
                                    'JP' => 'Japan', 'ZA' => 'South Africa', 'MY' => 'Malaysia', 'MV' => 'Maldives',
                                    'QA' => 'Qatar', 'SA' => 'Saudi Arabia', 'AF' => 'Afghanistan', 'AL' => 'Albania',
                                    'DZ' => 'Algeria', 'AD' => 'Andorra', 'AO' => 'Angola', 'AG' => 'Antigua & Barbuda',
                                    'AR' => 'Argentina', 'AM' => 'Armenia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
                                    'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados',
                                    'BY' => 'Belarus', 'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin',
                                    'BT' => 'Bhutan', 'BO' => 'Bolivia', 'BA' => 'Bosnia & Herzegovina', 'BW' => 'Botswana',
                                    'BR' => 'Brazil', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso',
                                    'BI' => 'Burundi', 'CV' => 'Cape Verde', 'KH' => 'Cambodia', 'CM' => 'Cameroon',
                                    'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia',
                                    'KM' => 'Comoros', 'CG' => 'Congo - Brazzaville', 'CD' => 'Congo - Kinshasa', 'CR' => 'Costa Rica',
                                    'HR' => 'Croatia', 'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czechia',
                                    'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica', 'DO' => 'Dominican Republic',
                                    'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                                    'ER' => 'Eritrea', 'EE' => 'Estonia', 'SZ' => 'Eswatini', 'ET' => 'Ethiopia',
                                    'FJ' => 'Fiji', 'FI' => 'Finland', 'GA' => 'Gabon', 'GM' => 'Gambia',
                                    'GE' => 'Georgia', 'GH' => 'Ghana', 'GR' => 'Greece', 'GD' => 'Grenada',
                                    'GT' => 'Guatemala', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana',
                                    'HT' => 'Haiti', 'HN' => 'Honduras', 'HU' => 'Hungary', 'IS' => 'Iceland',
                                    'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland',
                                    'IL' => 'Israel', 'JM' => 'Jamaica', 'JO' => 'Jordan', 'KZ' => 'Kazakhstan',
                                    'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'North Korea', 'KR' => 'South Korea',
                                    'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia',
                                    'LB' => 'Lebanon', 'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya',
                                    'LI' => 'Liechtenstein', 'LT' => 'Lithuania', 'LU' => 'Luxembourg', 'MG' => 'Madagascar',
                                    'MW' => 'Malawi', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands',
                                    'MR' => 'Mauritania', 'MU' => 'Mauritius', 'MX' => 'Mexico', 'FM' => 'Micronesia',
                                    'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
                                    'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
                                    'NR' => 'Nauru', 'NP' => 'Nepal', 'NI' => 'Nicaragua', 'NE' => 'Niger',
                                    'NG' => 'Nigeria', 'MK' => 'North Macedonia', 'NO' => 'Norway', 'OM' => 'Oman',
                                    'PK' => 'Pakistan', 'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama',
                                    'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru', 'PH' => 'Philippines',
                                    'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'RU' => 'Russia',
                                    'RW' => 'Rwanda', 'KN' => 'St. Kitts & Nevis', 'LC' => 'St. Lucia', 'VC' => 'St. Vincent & Grenadines',
                                    'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome & Principe', 'SN' => 'Senegal',
                                    'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SK' => 'Slovakia',
                                    'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'SD' => 'Sudan',
                                    'SR' => 'Suriname', 'SY' => 'Syria', 'TW' => 'Taiwan', 'TJ' => 'Tajikistan',
                                    'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo',
                                    'TO' => 'Tonga', 'TT' => 'Trinidad & Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
                                    'TM' => 'Turkmenistan', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
                                    'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City',
                                    'VE' => 'Venezuela', 'VN' => 'Vietnam', 'YE' => 'Yemen', 'ZM' => 'Zambia',
                                    'ZW' => 'Zimbabwe'
                                ];
                                $user_country_name = $country_map[$normalized_code] ?? $normalized_code;
                            }
                            try {
                                $feeStmt = $pdo->prepare("SELECT fee FROM product_shipping_rates WHERE product_id = ? AND country_code = ? LIMIT 1");
                                $feeStmt->execute([$product_id, $normalized_code]);
                                $fee_val = $feeStmt->fetchColumn();
                                if ($fee_val !== false) {
                                    $user_shipping_fee = (float)$fee_val;
                                    $has_user_shipping = true;
                                } else {
                                    // Fallback to base product shipping fee
                                    $user_shipping_fee = isset($product['shipping_fee']) ? (float)$product['shipping_fee'] : 450.00;
                                    $has_user_shipping = true;
                                }
                            } catch (Exception $e) {}
                        }
                    }
                ?>
                <div class="delivery-banner" style="display: flex; align-items: center; gap: 0.9rem; background: rgba(255, 94, 0, 0.08); border: 1.5px solid rgba(255, 94, 0, 0.3); padding: 0.75rem 1.2rem; border-radius: 14px; margin-bottom: 0.75rem;">
                    <div style="font-size: 1.6rem;">🚚</div>
                    <div>
                        <div style="font-weight: 800; font-size: 0.92rem; color: #ff5e00; text-transform: uppercase; letter-spacing: 0.5px;">
                            Estimated Delivery: <?php echo $del_days; ?> Days (Island-wide)
                        </div>
                        <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.7); font-weight: 600;">
                            <?php echo htmlspecialchars($del_sub); ?>
                        </div>
                    </div>
                </div>

                <div class="shipping-fee-banner" style="display: flex; align-items: center; gap: 0.9rem; background: rgba(16, 185, 129, 0.08); border: 1.5px solid rgba(16, 185, 129, 0.3); padding: 0.75rem 1.2rem; border-radius: 14px; margin-bottom: 1.5rem;">
                    <div style="font-size: 1.6rem;">💵</div>
                    <div>
                        <div style="font-weight: 800; font-size: 0.92rem; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">
                            <?php if ($has_user_shipping): ?>
                                Delivery Fee: <?php echo $user_shipping_fee == 0 ? 'Free' : 'Rs. ' . number_format($user_shipping_fee, 2); ?>
                            <?php else: ?>
                                Delivery Fee: --
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.7); font-weight: 600;">
                            <?php if ($has_user_shipping): ?>
                                Shipping to <?php echo htmlspecialchars($user_country_name); ?>
                            <?php else: ?>
                                Please login to view shipping rates
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="price-section">
                    <span class="price-current" id="dynamic-price" <?php echo $is_flash_sale ? 'style="color: var(--primary-glow);"' : ''; ?>>Rs. <?php echo number_format($current_price_lkr, 2); ?></span>
                    <?php if($discount_percent > 0): ?>
                        <span class="price-original" id="dynamic-original">Rs. <?php echo number_format($original_price_lkr, 2); ?></span>
                        <span class="discount-pill" <?php echo $is_flash_sale ? 'style="background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow)); box-shadow: 0 0 10px rgba(255, 94, 0, 0.4); color: white; border: none;"' : ''; ?>><?php echo $discount_percent; ?>% OFF</span>
                    <?php endif; ?>
                </div>

                <?php if ($is_flash_sale): ?>
                <div class="flash-countdown-detail" data-endtime="<?php echo strtotime($product['flash_sale_end']) * 1000; ?>" style="background: linear-gradient(145deg, var(--primary-glow), var(--secondary-glow)); box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.4), inset 0 -2px 4px rgba(0, 0, 0, 0.3), 0 5px 15px rgba(255, 94, 0, 0.4); border-radius: 12px; padding: 1rem; margin-bottom: 2rem; border: 1px solid rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.8rem;">
                        <span style="font-size: 1.8rem; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">⚡</span>
                        <div>
                            <div style="color: #ffffff; font-weight: 800; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Flash Sale</div>
                            <div style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.9); font-weight: 600;">Offer ends soon</div>
                        </div>
                    </div>
                    <div class="timer-display" style="display: flex; gap: 0.6rem; color: #ffffff; font-weight: 800; font-size: 1.3rem; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">
                        <div class="time-block" style="display: flex; flex-direction: column; align-items: center; line-height: 1;"><span class="days">00</span><span style="font-size: 0.6rem; font-weight: 600; color: rgba(255, 255, 255, 0.85); margin-top: 2px;">DAYS</span></div>
                        <div style="color: rgba(255, 255, 255, 0.6); padding-bottom: 10px;">:</div>
                        <div class="time-block" style="display: flex; flex-direction: column; align-items: center; line-height: 1;"><span class="hours">00</span><span style="font-size: 0.6rem; font-weight: 600; color: rgba(255, 255, 255, 0.85); margin-top: 2px;">HRS</span></div>
                        <div style="color: rgba(255, 255, 255, 0.6); padding-bottom: 10px;">:</div>
                        <div class="time-block" style="display: flex; flex-direction: column; align-items: center; line-height: 1;"><span class="minutes">00</span><span style="font-size: 0.6rem; font-weight: 600; color: rgba(255, 255, 255, 0.85); margin-top: 2px;">MINS</span></div>
                        <div style="color: rgba(255, 255, 255, 0.6); padding-bottom: 10px;">:</div>
                        <div class="time-block" style="display: flex; flex-direction: column; align-items: center; line-height: 1;"><span class="seconds">00</span><span style="font-size: 0.6rem; font-weight: 600; color: rgba(255, 255, 255, 0.85); margin-top: 2px;">SECS</span></div>
                    </div>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const timer = document.querySelector('.flash-countdown-detail');
                        if (timer) {
                            function updateTimer() {
                                const now = new Date().getTime();
                                const endTime = parseInt(timer.getAttribute('data-endtime'));
                                const distance = endTime - now;
                                
                                if (distance < 0) {
                                    timer.innerHTML = '<div style="color: #ffffff; font-weight: 800; padding: 0.5rem; text-align: center; width: 100%;">Flash Sale Ended</div>';
                                    
                                    // Revert to normal price dynamically
                                    if (!window.flashReverted) {
                                        window.flashReverted = true;
                                        const stdDisc = <?php echo (int)($product['discount_percent'] ?? 0); ?>;
                                        basePrice = baseOriginalPrice * (1 - (stdDisc / 100));
                                        discountPercent = stdDisc;
                                        
                                        const dynPrice = document.getElementById('dynamic-price');
                                        if (dynPrice) dynPrice.style.color = ''; // Remove flash sale red
                                        
                                        const discPill = document.querySelector('.discount-pill');
                                        const dynOrig = document.getElementById('dynamic-original');
                                        
                                        if (stdDisc > 0) {
                                            if (discPill) {
                                                discPill.style.background = '';
                                                discPill.style.boxShadow = '';
                                                discPill.style.display = 'inline-block';
                                                discPill.innerText = stdDisc + '% OFF';
                                            }
                                            if (dynOrig) dynOrig.style.display = 'inline';
                                        } else {
                                            if (discPill) discPill.style.display = 'none';
                                            if (dynOrig) dynOrig.style.display = 'none';
                                        }
                                        
                                        if (typeof updateVariantSelection === 'function') {
                                            updateVariantSelection();
                                        }
                                    }
                                    return;
                                }
                                
                                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                                
                                timer.querySelector('.days').innerText = days.toString().padStart(2, '0');
                                timer.querySelector('.hours').innerText = hours.toString().padStart(2, '0');
                                timer.querySelector('.minutes').innerText = minutes.toString().padStart(2, '0');
                                timer.querySelector('.seconds').innerText = seconds.toString().padStart(2, '0');
                            }
                            updateTimer();
                            setInterval(updateTimer, 1000);
                        }
                    });
                </script>
                <?php endif; ?>

                <!-- Product Variants Options Selection -->
                <div class="variants-container" style="display: flex; flex-direction: column; gap: 1rem;">
                    <?php
                    // Group variants by type
                    $grouped_variants = [];
                    foreach ($variants as $v) {
                        $grouped_variants[$v['variant_type']][] = $v;
                    }

                    foreach ($grouped_variants as $type => $group):
                    ?>
                        <div class="variant-selector">
                            <label>Select <?php echo htmlspecialchars($type); ?></label>
                            <select class="variant-select" data-type="<?php echo htmlspecialchars($type); ?>" onchange="updateVariantSelection()">
                                <option value="" data-modifier="0">-- Choose <?php echo htmlspecialchars($type); ?> --</option>
                                <?php foreach ($group as $vItem): ?>
                                    <option value="<?php echo $vItem['id']; ?>" data-modifier="<?php echo $vItem['price_modifier']; ?>" data-image="<?php echo htmlspecialchars($vItem['image'] ?? ''); ?>" data-stock="<?php echo isset($vItem['stock']) && (int)$vItem['stock'] > 0 ? (int)$vItem['stock'] : (int)$product['stock']; ?>">
                                        <?php echo htmlspecialchars($vItem['variant_value']); ?>
                                        <?php if (isset($vItem['stock']) && (int)$vItem['stock'] > 0): ?>
                                            (<?php echo (int)$vItem['stock']; ?> available)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div><!-- /.variants-container -->

                <div class="purchase-wrap" style="margin-top: 1.5rem;">
                    <?php if (($product['product_type'] ?? '') === 'local_service'): ?>
                        <a href="service_checkout.php?service_id=<?php echo $product_id; ?>" style="text-decoration: none;">
                            <button class="btn-buy" style="width: 100%; margin-bottom: 0.5rem; background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);">Request a Service 🛠️</button>
                        </a>
                        <p style="text-align:center; font-size: 0.85rem; color: var(--text-muted); margin-top:0.5rem;">You will be asked for location details on the next screen.</p>
                    <?php else: ?>
                        <!-- Qty + Add to Cart -->
                        <?php 
                        $cart_qty = $_SESSION['cart'][$product_id] ?? 0;
                        $available_stock = (int)$product['stock'];
                        ?>
                        <?php if ($available_stock <= 0): ?>
                            <button class="btn-buy btn-disabled" disabled>Out of Stock</button>
                        <?php elseif ($cart_qty >= $available_stock): ?>
                            <div style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 0.6rem 1rem; border-radius: 12px; font-size: 0.85rem; font-weight: 700; width: max-content; margin-bottom: 1rem;">Maximum stock reached (<?php echo $available_stock; ?> available)</div>
                            <button class="btn-buy btn-disabled" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); opacity: 0.8; cursor: not-allowed;" disabled>Max Stock Added 🛒</button>
                        <?php else: ?>
                            <div class="purchase-inline">
                                <div class="cart-status-text" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.6rem;">
                                    <span style="color: var(--text-main); font-weight: 600;">Available Quantity:</span>
                                    <span id="available-stock-display" style="color: #10b981; font-weight: 800; font-size: 0.95rem; background: rgba(16, 185, 129, 0.1); padding: 2px 10px; border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2);"><?php echo $available_stock; ?> units</span>
                                    <?php if ($cart_qty > 0): ?>
                                        <span style="color: #94a3b8; font-size: 0.82rem;">(You have <?php echo $cart_qty; ?> in cart)</span>
                                    <?php endif; ?>
                                </div>
                                <div class="purchase-action-row" style="flex-direction: column; align-items: flex-start; gap: 1rem;">
                                    <div class="qty-controls" style="width: 100%; max-width: 150px;">
                                        <button type="button" class="qty-btn" onclick="updateQty(-1)">-</button>
                                        <input type="text" id="product-qty" value="1" readonly class="qty-input">
                                        <button type="button" class="qty-btn" onclick="updateQty(1)">+</button>
                                    </div>
                                    <button class="btn-buy" id="btn-buy-now" style="width: 100%; margin-bottom: 0.5rem;" onclick="document.getElementById('btn-add-to-cart').click();">Buy It Now</button>
                                    <button class="btn-detail-cart" id="btn-add-to-cart" style="width: 100%;">Add to Cart</button>
                                </div>
                                <div class="item-total-price-row" style="margin-top: 0.8rem; font-size: 0.95rem; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                                    <span>Items Total:</span>
                                    <span id="dynamic-total-price" style="color: var(--accent); font-size: 1.15rem; font-weight: 800;">Rs. <?php echo number_format($current_price_lkr, 2); ?></span>
                                </div>
                                <div id="qty-error-msg" style="color: #f59e0b; font-size: 0.82rem; font-weight: 700; display: none; width: 100%; margin-top: -0.2rem;"></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="payment-methods-bar" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,94,0,0.1);">
                        <div class="pm-pills" style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                            <!-- Visa -->
                            <span style="display:inline-flex; align-items:center; font-size: 0;" title="Visa">
                                <svg width="38" height="24" viewBox="0 0 38 24" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                    <text x="19" y="16" font-family="Arial Black, Impact, sans-serif" font-style="italic" font-size="11" font-weight="900" fill="#ffffff" text-anchor="middle">VISA</text>
                                </svg>
                            </span>
                            <!-- Mastercard -->
                            <span style="display:inline-flex; align-items:center; font-size: 0;" title="Mastercard">
                                <svg width="38" height="24" viewBox="0 0 38 24" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                    <circle cx="15" cy="12" r="9" fill="#EB001B"/>
                                    <circle cx="23" cy="12" r="9" fill="#F79E1B" opacity="0.85"/>
                                </svg>
                            </span>
                            <!-- American Express -->
                            <span style="display:inline-flex; align-items:center; font-size: 0;" title="American Express">
                                <svg width="38" height="24" viewBox="0 0 38 24" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                    <rect x="4" y="4" width="30" height="16" rx="2" fill="#2E77BC"/>
                                    <text x="19" y="14.5" font-family="Arial, sans-serif" font-weight="bold" font-size="7.5" fill="#fff" text-anchor="middle">AMEX</text>
                                </svg>
                            </span>
                            <!-- Crypto USDT -->
                            <span style="display:inline-flex; align-items:center; font-size: 0;" title="USDT (Tether)">
                                <svg width="38" height="24" viewBox="0 0 38 24" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                    <circle cx="19" cy="12" r="10" fill="#50AF95"/>
                                    <path d="M14.5 9.5h9v1.6h-3.6v5.4h-1.8v-5.4h-3.6v-1.6z" fill="#fff"/>
                                </svg>
                            </span>
                            <!-- PayPal -->
                            <span style="display:inline-flex; align-items:center; font-size: 0;" title="PayPal">
                                <svg width="38" height="24" viewBox="0 0 38 24" xmlns="http://www.w3.org/2000/svg" style="display:block;">
                                    <text x="19" y="15.5" font-family="Arial, sans-serif" font-style="italic" font-weight="900" font-size="10.5" fill="#ffffff" text-anchor="middle">PayPal</text>
                                </svg>
                            </span>
                        </div>
                    </div>
                </div><!-- /.purchase-wrap -->
            </div><!-- /.info-panel -->
            </div><!-- /.info-col -->
        </div><!-- /.detail-container -->
        </div><!-- /.detail-section -->

        <!-- Details tabs -->
        <div class="tabs-outer">
        <section class="tabs-section">
            <div class="tabs-header">
                <button class="tab-btn active" onclick="switchTab(event, 'tab-description')">Description</button>
                <?php if (($product['product_type'] ?? '') !== 'local_service'): ?>
                <button class="tab-btn" onclick="switchTab(event, 'tab-shipping')">Shipping & Delivery</button>
                <button class="tab-btn" onclick="switchTab(event, 'tab-warranty')">Warranty & Returns</button>
                <?php endif; ?>
            </div>

            <div id="tab-description" class="tab-content active glass-panel" style="padding: 2.5rem; border-radius: 16px;">
                <h3 style="margin-bottom: 1rem; color: var(--text-main);"><?php echo (($product['product_type'] ?? '') === 'local_service') ? 'Service Specifications' : 'Product Specifications'; ?></h3>
                <p style="white-space: pre-line;"><?php echo htmlspecialchars($product['description']); ?></p>
            </div>

            <?php if (($product['product_type'] ?? '') !== 'local_service'): ?>
            <?php
                $del_days = (int)($product['delivery_days'] ?? 3);
                $delivery_type = "Standard In-Stock Store Default";
                if ($del_days === 1) {
                    $colombo_txt = "1 to 2";
                    $outstation_txt = "2 to 3";
                    $delivery_type = "Express Next-Day Delivery";
                } elseif ($del_days === 5) {
                    $colombo_txt = "5 to 6";
                    $outstation_txt = "7 to 8";
                    $delivery_type = "Fast Courier";
                } elseif ($del_days === 7) {
                    $colombo_txt = "7 to 8";
                    $outstation_txt = "9 to 10";
                    $delivery_type = "Out of Store / Backorder / Import";
                } elseif ($del_days === 14) {
                    $colombo_txt = "14 to 15";
                    $outstation_txt = "16 to 17";
                    $delivery_type = "Extended Special Import";
                } else {
                    // Default to 3 days or any other values
                    $colombo_txt = "3 to 4";
                    $outstation_txt = "5 to 6";
                    $delivery_type = "Standard In-Stock Store Default";
                }
            ?>
            <div id="tab-shipping" class="tab-content glass-panel" style="padding: 2.5rem; border-radius: 16px;">
                <h3 style="margin-bottom: 1rem; color: var(--text-main);">Island-wide Safe Delivery</h3>
                <ul style="padding-left: 1.5rem; display: flex; flex-direction: column; gap: 0.6rem;">
                    <li><strong>Shipping Method:</strong> <?php echo $delivery_type; ?></li>
                    <li>Standard delivery time for Colombo & suburbs is <?php echo $colombo_txt; ?> working days.</li>
                    <li>Outstation deliveries take <?php echo $outstation_txt; ?> working days.</li>
                    <li>We accept Cash on Delivery (COD), PayPal, Crypto, and Bank Transfers.</li>
                </ul>
            </div>

            <div id="tab-warranty" class="tab-content glass-panel" style="padding: 2.5rem; border-radius: 16px;">
                <?php if(!empty($product['warranty'])): ?>
                    <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; padding:1rem 1.4rem; border-radius:14px; border:1.5px solid <?php echo $style['border'];?>; background:<?php echo $style['bg'];?>; color:<?php echo $style['color'];?>;">
                        <span style="font-size:2rem;">🛡️</span>
                        <div>
                            <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; opacity:0.7;">This product includes</div>
                            <div style="font-size:1.2rem; font-weight:800;"><?php echo htmlspecialchars($product['warranty']); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
                <p style="font-size: 0.9rem; color: var(--text-muted); line-height: 1.6;">
                    *Water and dust resistance were tested under controlled lab conditions. Resistance may fail due to wear and tear or over time. Damage caused by immersion in liquid, Cosmetic Damages such as wear &amp; tear, scratch mark, color fade &amp; others will not be applicable for warranty.
                </p>
            </div>
            <?php endif; ?>
        </section>
        </div><!-- /.tabs-outer -->

        <?php
        $review_count = count($approved_reviews);

        // Build real star breakdown from DB reviews only
        $db_total_stars   = 0;
        $real_star_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $reviews_with_images = [];
        foreach ($approved_reviews as $rev) {
            $db_total_stars += (float)$rev['rating'];
            $star = (int)$rev['rating'];
            if (isset($real_star_counts[$star])) {
                $real_star_counts[$star]++;
            }
            if (!empty($rev['image_path'])) {
                $reviews_with_images[] = $rev;
            }
        }

        // Real average — only from actual submitted reviews
        $disp_rating = $review_count > 0 ? round($db_total_stars / $review_count, 1) : 0;

        // Only show real customer photos — no mock images
        $disp_images = array_map(fn($r) => $r['image_path'], $reviews_with_images);
        ?>
        <div class="reviews-outer">
            <h2 class="reviews-section-title-centered">Customer Reviews</h2>
            
            <div class="reviews-summary-row">
                <div class="summary-left">
                    <?php
                        // Render stars from real average
                        $fullS = floor($disp_rating);
                        $halfS = ($disp_rating - $fullS) >= 0.3 ? 1 : 0;
                        $empS  = 5 - $fullS - $halfS;
                        $starsDisplay = $review_count > 0
                            ? str_repeat('★', $fullS) . ($halfS ? '½' : '') . str_repeat('☆', $empS)
                            : '☆☆☆☆☆';
                    ?>
                    <div class="summary-stars" style="color:#f59e0b;"><?php echo $starsDisplay; ?></div>
                    <?php if ($review_count > 0): ?>
                        <div class="summary-rating-num"><?php echo number_format($disp_rating, 1); ?> out of 5</div>
                        <div class="summary-count">Based on <?php echo $review_count; ?> <?php echo $review_count === 1 ? 'review' : 'reviews'; ?></div>
                    <?php else: ?>
                        <div class="summary-rating-num" style="font-size:1.1rem; color:var(--text-muted);">No reviews yet</div>
                        <div class="summary-count">Be the first to review this product</div>
                    <?php endif; ?>
                </div>
                <div class="summary-right-buttons">
                    <button class="btn-write-review" onclick="toggleReviewForm()">Write a review</button>
                    <button class="btn-ask-question" onclick="toggleQuestionForm()">Ask a question</button>
                </div>
            </div>

            <!-- Review Submission Form (hidden by default) -->
            <div class="review-form-card" id="review-form-container" style="display: none;">
                <h3>✍️ Write a Review</h3>
                <div id="review-toast" class="rf-toast"></div>
                <form id="review-form" onsubmit="submitReview(event)" enctype="multipart/form-data">
                    <div class="rf-row">
                        <input type="text" class="rf-input" id="rev-name" placeholder="Your Name *" required>
                        <input type="email" class="rf-input" id="rev-email" placeholder="Your Email (optional)">
                    </div>

                    <label style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.3rem; display: block;">Your Rating *</label>
                    <div class="star-rating-input" id="star-rating-input">
                        <button type="button" class="star-btn" data-value="1" onclick="setRating(1)">★</button>
                        <button type="button" class="star-btn" data-value="2" onclick="setRating(2)">★</button>
                        <button type="button" class="star-btn" data-value="3" onclick="setRating(3)">★</button>
                        <button type="button" class="star-btn" data-value="4" onclick="setRating(4)">★</button>
                        <button type="button" class="star-btn" data-value="5" onclick="setRating(5)">★</button>
                    </div>
                    <input type="hidden" id="rev-rating" value="0">

                    <div class="photo-upload-container">
                        <label class="photo-upload-label" for="rev-image">
                            📷 Upload a Photo (optional)
                        </label>
                        <input type="file" id="rev-image" name="review_image" accept="image/*" class="photo-upload-input" onchange="previewUploadImage(event)">
                        <div class="upload-preview-wrap">
                            <img id="upload-preview" class="upload-preview-thumb" src="" alt="Preview">
                            <button type="button" id="clear-upload-btn" class="upload-preview-clear" onclick="clearUploadImage()">
                                ❌ Remove Photo
                            </button>
                        </div>
                    </div>

                    <textarea class="rf-textarea" id="rev-text" placeholder="Share your experience with this product... *" required></textarea>

                    <button type="submit" class="rf-submit">Submit Review ➔</button>
                </form>
            </div>

            <!-- Question Form (hidden by default) -->
            <div class="review-form-card" id="question-form-container" style="display: none;">
                <h3>❓ Ask a Question</h3>
                <div id="question-toast" class="rf-toast"></div>
                <form id="question-form" onsubmit="submitQuestion(event)">
                    <div class="rf-row">
                        <input type="text" class="rf-input" id="q-name" placeholder="Your Name *" required>
                        <input type="email" class="rf-input" id="q-email" placeholder="Your Email *" required>
                    </div>
                    <textarea class="rf-textarea" id="q-text" placeholder="What would you like to know about this product? *" required></textarea>
                    <button type="submit" class="rf-submit" style="background: #ff5e00;">Submit Question ➔</button>
                </form>
            </div>


            <!-- Tabs and Sorting Row -->
            <div class="reviews-tabs-row">
                <div class="reviews-tabs">
                    <div class="rev-tab active" id="tab-reviews-btn" onclick="switchReviewTab('reviews')">Reviews (<?php echo $review_count; ?>)</div>
                    <div class="rev-tab" id="tab-questions-btn" onclick="switchReviewTab('questions')">Questions (<?php echo count($product_questions); ?>)</div>
                </div>
                <?php if ($review_count > 0): ?>
                <div class="reviews-sorting">
                    <select id="rev-sort-select">
                        <option value="recent">Most Recent</option>
                        <option value="highest">Highest Rating</option>
                        <option value="lowest">Lowest Rating</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Approved Reviews List -->
            <div id="reviews-list-container">
                <div class="review-list">
                    <?php if ($review_count > 0): ?>
                        <?php foreach ($approved_reviews as $rev): ?>
                            <div class="ri-new-card">
                                <div class="ri-card-top">
                                    <div class="ri-card-stars" style="color:#f59e0b;">
                                        <?php for ($s = 1; $s <= 5; $s++): ?>
                                            <?php echo $s <= $rev['rating'] ? '★' : '☆'; ?>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="ri-card-date"><?php echo date('d M Y', strtotime($rev['created_at'])); ?></div>
                                </div>
                                <div class="ri-user-row">
                                    <div class="ri-user-avatar">
                                        <?php echo strtoupper(substr($rev['reviewer_name'], 0, 1)); ?>
                                    </div>
                                    <div class="ri-user-meta">
                                        <span class="ri-user-name"><?php echo htmlspecialchars($rev['reviewer_name']); ?></span>
                                        <span class="ri-verified-badge">✓ Verified Purchase</span>
                                    </div>
                                </div>
                                <div class="ri-card-text"><?php echo nl2br(htmlspecialchars($rev['review_text'])); ?></div>
                                <?php if (!empty($rev['image_path'])): ?>
                                    <div class="ri-attached-photo" onclick="openPhotoLightbox('<?php echo htmlspecialchars($rev['image_path']); ?>')">
                                        <img src="<?php echo htmlspecialchars($rev['image_path']); ?>" alt="Attached review photo">
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($rev['admin_reply'])): ?>
                                    <div style="margin-top: 1.5rem; padding: 1rem 1.2rem; background: rgba(255, 94, 0, 0.1); border-left: 3px solid #ff5e00; border-radius: 8px;">
                                        <div style="font-weight: 700; color: #ff5e00; margin-bottom: 0.5rem; font-size: 0.9rem;">Digi Pro X 24 (Seller)</div>
                                        <div style="font-size: 0.95rem; color: rgba(255,255,255,0.9); line-height: 1.6;"><?php echo nl2br(htmlspecialchars($rev['admin_reply'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-reviews-msg" style="text-align:center; padding: 3rem 1rem; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">✍️</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem;">No reviews yet</div>
                            <div style="font-size: 0.95rem;">Be the first to share your experience with this product.</div>
                            <button class="btn-write-review" style="margin-top: 1.5rem;" onclick="toggleReviewForm()">Write the first review</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Questions Tab Container (hidden by default) -->
            <div id="questions-list-container" style="display: none;">
                <div class="review-list">
                    <?php if (!empty($product_questions) && count($product_questions) > 0): ?>
                        <?php foreach ($product_questions as $q): ?>
                            <div class="ri-new-card">
                                <div class="ri-card-top">
                                    <div class="ri-card-stars" style="color: #ff5e00; font-size: 0.9rem; font-weight: 700;">❓ PRODUCT QUESTION</div>
                                    <div class="ri-card-date"><?php echo date('d M Y', strtotime($q['created_at'])); ?></div>
                                </div>
                                <div class="ri-user-row">
                                    <div class="ri-user-avatar" style="color: #ff5e00; background: rgba(255, 94, 0, 0.1); border-color: rgba(255, 94, 0, 0.25);">
                                        <?php echo strtoupper(substr($q['customer_name'], 0, 1)); ?>
                                    </div>
                                    <div class="ri-user-meta">
                                        <span class="ri-user-name"><?php echo htmlspecialchars($q['customer_name']); ?></span>
                                    </div>
                                </div>
                                <div class="ri-card-text" style="font-weight: 600; color: #ffffff; margin-bottom:0;"><?php echo nl2br(htmlspecialchars($q['question_text'])); ?></div>
                                <?php if (!empty($q['admin_reply'])): ?>
                                    <div style="margin-top: 1.5rem; padding: 1rem 1.2rem; background: rgba(255, 94, 0, 0.1); border-left: 3px solid #ff5e00; border-radius: 8px;">
                                        <div style="font-weight: 700; color: #ff5e00; margin-bottom: 0.5rem; font-size: 0.9rem;">Digi Pro X 24 (Seller)</div>
                                        <div style="font-size: 0.95rem; color: rgba(255,255,255,0.9); line-height: 1.6;"><?php echo nl2br(htmlspecialchars($q['admin_reply'])); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-reviews-msg" style="text-align:center; padding: 3rem 1rem; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">❓</div>
                            <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.5rem;">No questions yet</div>
                            <div style="font-size: 0.95rem;">Have a question about this product? Click "Ask a question" above.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Custom Image Lightbox Modal -->
        <div class="lightbox-overlay" id="photo-lightbox-modal" onclick="closePhotoLightbox()" style="display: none; position: fixed; inset: 0; background: rgba(9, 13, 22, 0.9); z-index: 9999; align-items: center; justify-content: center;">
            <div class="lightbox-img-wrap" onclick="event.stopPropagation()" style="position: relative; max-width: 90%; max-height: 80vh;">
                <button class="lb-close" onclick="closePhotoLightbox()" style="position: absolute; top: -40px; right: 0; background: none; border: none; color: #fff; font-size: 2rem; cursor: pointer;">&#10005;</button>
                <img id="photo-lightbox-img" src="" alt="Review Photo" style="max-width: 100%; max-height: 80vh; object-fit: contain; border-radius: 12px; border: 2px solid rgba(255,255,255,0.1);">
            </div>
        </div>

        <!-- Related Products Section -->
        <?php if (count($related_products) > 0): ?>
            <div class="related-outer">
            <section class="related-section" style="margin-top:0; border-top:none; padding-top:0;">
                <h2 class="related-title">Related <span>Products</span></h2>
                <div class="product-grid">
                    <?php 
                    foreach ($related_products as $rel): 
                        $rel_orig = $rel['price'];
                        $rel_disc = $rel['discount_percent'] ?? 0;
                        $rel_curr = $rel_orig;
                        if ($rel_disc > 0) {
                            $rel_curr = $rel_orig * (1 - ($rel_disc / 100));
                        }
                    ?>
                        <div class="product-card glass-panel" style="cursor: pointer;" onclick="window.location.href='product_detail.php?id=<?php echo $rel['id']; ?>'">
                            <div class="product-image">
                                <?php if(isset($rel['shipping_fee']) && (float)$rel['shipping_fee'] == 0 && (!isset($rel['product_type']) || $rel['product_type'] !== 'local_service')): ?>
                                    <div class="free-shipping-badge" style="position: absolute; top: 15px; right: 15px; background: rgba(139, 92, 246, 0.95); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 7; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.35); display: flex; align-items: center; gap: 0.3rem;"><span style="font-size: 0.9rem;">🚚</span> FREE</div>
                                <?php endif; ?>
                                <img src="<?php echo htmlspecialchars($rel['image']); ?>" alt="<?php echo htmlspecialchars($rel['name']); ?>">
                                <?php if ($rel['stock'] > 0): ?>
                                    <div class="stock-badge-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(16, 185, 129, 0.95); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); color: #ffffff; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15); z-index: 5; letter-spacing: 0.5px;">IN STOCK</div>
                                <?php else: ?>
                                    <div class="stock-badge-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(239, 68, 68, 0.95); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); color: #ffffff; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15); z-index: 5; letter-spacing: 0.5px;">OUT OF STOCK</div>
                                    <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h3><?php echo htmlspecialchars($rel['name']); ?></h3>
                                <div class="price-row">
                                    <div class="price-col">
                                        <?php if ($rel_disc > 0): ?>
                                            <span class="original-price">Rs. <?php echo number_format($rel_orig, 2); ?></span>
                                        <?php endif; ?>
                                        <span class="price">Rs. <?php echo number_format($rel_curr, 2); ?></span>
                                    </div>
                                    <span class="btn-add-cart" onclick="event.stopPropagation(); window.location.href='product_detail.php?id=<?php echo $rel['id']; ?>'">View 🔍</span>
                                </div>
                                
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            </div>
        <?php endif; ?>
    <!-- Fullscreen Lightbox -->
    <div class="lightbox-overlay" id="lightbox-overlay" onclick="closeLightbox()">
        <div class="lightbox-img-wrap" onclick="event.stopPropagation()">
            <button class="lb-close" onclick="closeLightbox()" title="Close">&#10005;</button>
            <button class="lb-btn lb-prev" onclick="lbNavigate(-1)">&#10094;</button>
            <img id="lightbox-img" src="" alt="<?php echo htmlspecialchars($product['name']); ?>">
            <button class="lb-btn lb-next" onclick="lbNavigate(1)">&#10095;</button>
            <div class="lb-counter" id="lb-counter"></div>
        </div>
    </div>

    </main>

    <!-- Global Footer -->
    <footer class="glass-footer">
        <div class="footer-content">
            <div class="footer-brand">
                <div class="logo" style="display:flex; align-items:center; gap:0.6rem;">
                    <img src="logo.png" alt="Digi Pro X 24" style="height:36px; border-radius: 8px;">
                    Digi <span>Pro X 24</span>
                </div>
                <p>Your premier destination for high-performance Custom PCs, advanced surveillance systems, POS solutions, and premium tech utilities.</p>
                <div class="footer-contacts">
                    <div class="footer-contact-item">
                        <span class="icon">📍</span>
                        <a href="https://maps.app.goo.gl/Z1kx3yJVm6h6YCfJ9" target="_blank" rel="noopener noreferrer" style="color:inherit; text-decoration:none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-glow, #ff5e00)'" onmouseout="this.style.color='inherit'">No.161, Wackwella Rd, Galle, Sri Lanka ↗</a>
                    </div>
                    <div class="footer-contact-item">
                        <span class="icon">📞</span>
                        <span>070 6756006</span>
                    </div>
                    <div class="footer-contact-item">
                        <span class="icon">✉️</span>
                        <span>digipro24@gmail.com</span>
                    </div>
                </div>
                <div class="footer-whatsapp">
                    <a href="https://wa.me/94706756006" target="_blank" rel="noopener noreferrer">
                        <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" style="vertical-align: middle;"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.792 1.451 5.485.002 9.947-4.437 9.95-9.912.002-2.653-1.03-5.148-2.906-7.027A9.873 9.873 0 0011.997 1.284C6.507 1.284 2.05 5.722 2.046 11.2c-.001 1.761.479 3.483 1.39 5.017L2.45 20.83l4.197-1.101-.001-.005-.002-.008zm11.12-6.504c-.3-.15-1.78-.88-2.05-.98-.27-.1-.47-.15-.67.15-.2.3-.77.98-.95 1.18-.18.2-.35.23-.65.08-1.76-.88-3.15-1.53-4.4-3.67-.33-.57.33-.53.94-1.75.1-.2.05-.38-.02-.53-.07-.15-.67-1.62-.92-2.22-.24-.59-.5-.51-.68-.52-.17-.01-.37-.01-.57-.01-.2 0-.52.08-.8.38-.28.3-1.06 1.04-1.06 2.53 0 1.49 1.08 2.93 1.23 3.13.15.2 2.13 3.25 5.16 4.56.72.31 1.28.5 1.72.64.73.23 1.39.2 1.91.12.58-.09 1.78-.73 2.03-1.43.25-.7.25-1.29.18-1.42-.07-.13-.27-.2-.57-.35z"/></svg>
                        WhatsApp Us
                    </a>
                </div>
                <div class="footer-socials">
                    <a href="https://www.facebook.com/share/18m5wRA5Ct/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer" class="social-icon fb" title="Facebook">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8H7v3h2v9h4v-9h3.6l.4-3h-4V6.5c0-.8.2-1.1 1-1.1h3V1H13c-3.2 0-5 1.7-5 4.8V8z"/></svg>
                    </a>
                    <a href="javascript:void(0)" class="social-icon yt" title="YouTube" onclick="return false;">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M23.5 6.2c-.3-1.1-1.1-2-2.2-2.3C19.3 3.5 12 3.5 12 3.5s-7.3 0-9.3.4c-1.1.3-2 1.2-2.3 2.3C0 8.2 0 12 0 12s0 3.8.4 5.8c.3 1.1 1.1 2 2.2 2.3 2 2.4 9.3 2.4 9.3 2.4s7.3 0 9.3-.4c1.1-.3 2-1.2 2.3-2.3.4-2 .4-5.8.4-5.8s0-3.8-.4-5.8zm-14 9.3V8.5l6.5 3.5-6.5 3.5z"/></svg>
                    </a>
                    <a href="https://www.tiktok.com/@digiprox24?_r=1&_t=ZS-98JqfEsOal3" target="_blank" rel="noopener noreferrer" class="social-icon tt" title="TikTok">
                        <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.17-2.86-.74-3.94-1.74-.22-.2-.43-.43-.62-.67v6.62c.03 2.12-.55 4.31-2 5.92-1.54 1.72-3.89 2.67-6.2 2.5-2.61-.1-5.12-1.53-6.42-3.8-1.5-2.58-1.46-6.07.45-8.48 1.54-2 4.14-2.97 6.64-2.5v4.13c-1.31-.38-2.83-.07-3.82.81-1.04.93-1.41 2.52-.94 3.86.43 1.3 1.83 2.22 3.19 2.17 1.34-.02 2.62-1.02 2.87-2.34.1-.55.08-1.12.08-1.68V.02z"/></svg>
                    </a>
                </div>
            </div>
            <div class="footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="categories.php">Categories</a></li>
                    <li><a href="products.php">Products</a></li>
                <?php $uc = strtolower(trim($_SESSION['user_country'] ?? '')); if (isset($_SESSION['user_id']) && ($uc === 'sri lanka' || $uc === 'lk' || $uc === 'srilanka' || $uc === 'sl')): ?>
                <?php $uc = strtolower(trim($_SESSION['user_country'] ?? '')); if (isset($_SESSION['user_id']) && ($uc === 'sri lanka' || $uc === 'lk' || $uc === 'srilanka' || $uc === 'sl')): ?>
                <li><a href="services.php">Services</a></li>
                <?php endif; ?>
                <?php endif; ?>
                    <li><a href="about.php">About Us</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Policies</h4>
                <ul>
                    <li><a href="privacy">Privacy Policy</a></li>
                    <li><a href="terms">Terms &amp; Conditions</a></li>
                    <li><a href="return-policy">Return &amp; Refund Policy</a></li>
                    <li><a href="warranty">Warranty Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-bottom-left">
                <p>&copy; <?php echo date('Y'); ?> Digi Pro X 24. All rights reserved.</p>
                <p style="margin-top: 5px; font-size: 0.75rem; color: var(--text-muted);">Developed By <a href="https://fusionwavesystems.com/" target="_blank" rel="noopener noreferrer" style="color: var(--text-main); font-weight: 600; text-decoration: none; border-bottom: 1px dashed var(--primary-glow);">Fusion Wave Systems (Pvt) Ltd.</a></p>
            </div>
                        <div class="footer-payment-methods">
                <!-- Cash on Delivery -->
                <div class="payment-card" title="Cash on Delivery">
                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius:3px; padding:2px; display:block;">
                        <rect width="38" height="24" fill="#ffffff"/>
                        <rect x="5" y="6" width="16" height="10" rx="1" fill="#475569"/>
                        <text x="13" y="13" fill="#ffffff" font-family="system-ui, -apple-system" font-weight="900" font-size="6" text-anchor="middle">COD</text>
                        <path d="M21 9h5l2 3v4h-7V9z" fill="#334155"/>
                        <circle cx="9" cy="17" r="2" fill="#0f172a"/>
                        <circle cx="23" cy="17" r="2" fill="#0f172a"/>
                    </svg>
                </div>
                <!-- Crypto -->
                <div class="payment-card" title="Cryptocurrency (USDT/BTC)">
                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius:3px; padding:2px; display:block;">
                        <rect width="38" height="24" fill="#ffffff"/>
                        <circle cx="19" cy="12" r="9" fill="#26a17b"/>
                        <path d="M19 7c-2.4 0-4.3 0.3-4.3 0.8s1.9 0.8 4.3 0.8 4.3-0.3 4.3-0.8S21.4 7 19 7zm0.5 1.7H22v0.8h-2.5v4.5h-1v-4.5H16v-0.8h2.5v-0.1h1v0.1z" fill="#ffffff"/>
                    </svg>
                </div>
                <!-- PayPal -->
                <div class="payment-card" title="PayPal">
                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius:3px; padding:2px; display:block;">
                        <rect width="38" height="24" fill="#ffffff"/>
                        <path d="M12.5 4h5.2c1.8 0 3.2.4 4 1.2.7.7 1 1.7.9 3-.1 1.8-.8 3.2-1.9 4.1-1 .9-2.5 1.3-4.4 1.3h-2.1l-1.3 6.4h-3.4l2.6-13c.2-1 .4-1.7.7-2 .3-.3 1-.3 1.7-.3z" fill="#003087"/>
                        <path d="M14.5 6h5.2c1.8 0 3.2.4 4 1.2.7.7 1 1.7.9 3-.1 1.8-.8 3.2-1.9 4.1-1 .9-2.5 1.3-4.4 1.3h-2.1l-1.3 6.4h-3.4l2.6-13c.2-1 .4-1.7.7-2 .3-.3 1-.3 1.7-.3z" fill="#0079C1" opacity="0.8"/>
                    </svg>
                </div>
                <!-- Bank Transfer -->
                <div class="payment-card" title="Bank Transfer">
                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius:3px; padding:2px; display:block;">
                        <rect width="38" height="24" fill="#ffffff"/>
                        <path d="M19 4L6 9v2h26V9L19 4zm-9 9v6h3v-6h-3zm6 0v6h3v-6h-3zm6 0v6h3v-6h-3zm-14 8v1h20v-1H8z" fill="#0f172a"/>
                    </svg>
                </div>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // Gallery Navigation Logic
        const galleryImages = [
            "<?php echo addslashes(get_product_image_url($product['image'])); ?>",
            <?php foreach ($gallery as $gImg) {
                echo '"' . addslashes(get_product_image_url($gImg['image_path'])) . '",';
            } ?>
        ];
        let currentIdx = 0;
        let autoplayTimer = null;
        let autoplayRunning = true;
        const AUTOPLAY_DELAY = 4000;

        // Build slide dots
        function buildDots() {
            const dotsEl = document.getElementById('slide-dots');
            if (!dotsEl) return;
            dotsEl.innerHTML = '';
            galleryImages.forEach((_, i) => {
                const dot = document.createElement('button');
                dot.className = 'slide-dot' + (i === 0 ? ' active' : '');
                dot.setAttribute('aria-label', 'Slide ' + (i + 1));
                dot.onclick = () => { stopAutoplay(); selectThumbnail(i); startAutoplay(); };
                dotsEl.appendChild(dot);
            });
        }

        function updateDots() {
            const dots = document.querySelectorAll('.slide-dot');
            dots.forEach((d, i) => {
                if (i === currentIdx) d.classList.add('active');
                else d.classList.remove('active');
            });
        }

        function updateGalleryView(animate = true) {
            const mainImgEl = document.getElementById('main-product-img');

            if (animate) {
                mainImgEl.style.opacity = '0';
                mainImgEl.style.transform = 'scale(0.97)';
                mainImgEl.style.transition = 'opacity 0.3s, transform 0.3s';
                setTimeout(() => {
                    mainImgEl.src = galleryImages[currentIdx];
                    mainImgEl.style.opacity = '1';
                    mainImgEl.style.transform = 'scale(1)';
                }, 250);
            } else {
                mainImgEl.src = galleryImages[currentIdx];
            }

            // Highlight thumbnails
            const thumbs = document.querySelectorAll('.thumbnail-card');
            thumbs.forEach((t, i) => {
                if (i === currentIdx) t.classList.add('active');
                else t.classList.remove('active');
            });

            updateDots();
            resetProgressBar();
        }

        function selectThumbnail(idx) {
            currentIdx = idx;
            updateGalleryView();
        }

        function navigateGallery(dir) {
            currentIdx += dir;
            if (currentIdx < 0)  currentIdx = galleryImages.length - 1;
            if (currentIdx >= galleryImages.length) currentIdx = 0;
            updateGalleryView();
        }

        // ── Progress bar ──
        let progressAnim = null;
        function resetProgressBar() {
            const bar = document.getElementById('slide-progress');
            if (!bar) return;
            bar.style.transition = 'none';
            bar.style.width = '0%';
            clearTimeout(progressAnim);
            if (autoplayRunning && galleryImages.length > 1) {
                progressAnim = setTimeout(() => {
                    bar.style.transition = 'width ' + AUTOPLAY_DELAY + 'ms linear';
                    bar.style.width = '100%';
                }, 30);
            }
        }

        // ── Autoplay ──
        function startAutoplay() {
            if (galleryImages.length <= 1) return;
            autoplayRunning = true;
            const btn = document.getElementById('autoplay-btn');
            if (btn) btn.textContent = '⏸';
            resetProgressBar();
            clearInterval(autoplayTimer);
            autoplayTimer = setInterval(() => {
                currentIdx = (currentIdx + 1) % galleryImages.length;
                updateGalleryView();
            }, AUTOPLAY_DELAY);
        }

        function stopAutoplay() {
            autoplayRunning = false;
            clearInterval(autoplayTimer);
            const btn = document.getElementById('autoplay-btn');
            if (btn) btn.textContent = '▶';
            const bar = document.getElementById('slide-progress');
            if (bar) { bar.style.transition = 'none'; bar.style.width = '0%'; }
        }

        function toggleAutoplay() {
            if (autoplayRunning) stopAutoplay();
            else startAutoplay();
        }

        // ── Lightbox ──
        function openLightbox(idx) {
            currentIdx = idx;
            const overlay = document.getElementById('lightbox-overlay');
            const img = document.getElementById('lightbox-img');
            const counter = document.getElementById('lb-counter');
            img.src = galleryImages[currentIdx];
            if (counter) counter.textContent = (currentIdx + 1) + ' / ' + galleryImages.length;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
            stopAutoplay();
        }

        function closeLightbox() {
            document.getElementById('lightbox-overlay').classList.remove('active');
            document.body.style.overflow = '';
            startAutoplay();
        }

        function lbNavigate(dir) {
            currentIdx += dir;
            if (currentIdx < 0)  currentIdx = galleryImages.length - 1;
            if (currentIdx >= galleryImages.length) currentIdx = 0;
            const img = document.getElementById('lightbox-img');
            const counter = document.getElementById('lb-counter');
            img.style.opacity = '0';
            setTimeout(() => {
                img.src = galleryImages[currentIdx];
                img.style.opacity = '1';
                img.style.transition = 'opacity 0.25s';
            }, 150);
            if (counter) counter.textContent = (currentIdx + 1) + ' / ' + galleryImages.length;
            // Also update main gallery view
            updateGalleryView(false);
        }

        // Keyboard navigation
        document.addEventListener('keydown', e => {
            const lb = document.getElementById('lightbox-overlay');
            if (lb.classList.contains('active')) {
                if (e.key === 'ArrowLeft')  lbNavigate(-1);
                if (e.key === 'ArrowRight') lbNavigate(1);
                if (e.key === 'Escape')     closeLightbox();
            } else {
                if (e.key === 'ArrowLeft')  { stopAutoplay(); navigateGallery(-1); }
                if (e.key === 'ArrowRight') { stopAutoplay(); navigateGallery(1); }
            }
        });

        // ── Init ──
        document.addEventListener('DOMContentLoaded', () => {
            buildDots();
            updateGalleryView(false);
            startAutoplay();
        });

        // Quantity selector logic
        const maxQuantityLimit = <?php echo ($available_stock - $cart_qty); ?>;
        function updateQty(change) {
            const qtyInput = document.getElementById('product-qty');
            const errorMsg = document.getElementById('qty-error-msg');
            if (qtyInput) {
                let val = parseInt(qtyInput.value) + change;
                if (val < 1) {
                    val = 1;
                }
                if (val > maxQuantityLimit) {
                    val = maxQuantityLimit;
                    if (errorMsg) {
                        errorMsg.style.display = 'block';
                        errorMsg.innerText = `Only ${maxQuantityLimit} items available to add to cart`;
                    }
                } else {
                    if (errorMsg) {
                        errorMsg.style.display = 'none';
                    }
                }
                qtyInput.value = val;
                updateTotalPrice();
            }
        }

        // Variant selection & price calculation logic
        let baseOriginalPrice = <?php echo (float)$original_price_lkr; ?>;
        let basePrice = <?php echo (float)$current_price_lkr; ?>;
        let discountPercent = <?php echo (int)$discount_percent; ?>;
        let currentUnitPrice = basePrice;

        function updateTotalPrice() {
            const qtyInput = document.getElementById('product-qty');
            const totalEl = document.getElementById('dynamic-total-price');
            if (qtyInput && totalEl) {
                const qty = parseInt(qtyInput.value) || 1;
                const total = currentUnitPrice * qty;
                totalEl.innerText = 'Rs. ' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        }

        function updateVariantSelection() {
            let totalModifier = 0;
            const selects = document.querySelectorAll('.variant-select');
            let hasVariantImage = false;
            let hasVariantStock = false;
            let currentVarStock = 0;
            
            selects.forEach(select => {
                const opt = select.options[select.selectedIndex];
                if (opt && opt.value) {
                    totalModifier += parseFloat(opt.getAttribute('data-modifier')) || 0;
                    
                    // Option photo update
                    const varImg = opt.getAttribute('data-image');
                    if (varImg && varImg.trim() !== '') {
                        document.getElementById('main-product-img').src = varImg;
                        hasVariantImage = true;
                    }

                    // Variant stock update
                    const varStock = opt.getAttribute('data-stock');
                    if (varStock) {
                        currentVarStock = parseInt(varStock);
                        hasVariantStock = true;
                    }
                }
            });

            if (hasVariantImage) {
                stopAutoplay();
            } else {
                if (typeof galleryImages !== 'undefined' && galleryImages.length > 0) {
                    document.getElementById('main-product-img').src = galleryImages[typeof currentIdx !== 'undefined' ? currentIdx : 0];
                }
            }

            if (hasVariantStock) {
                const badgeQtyEl = document.getElementById('stock-badge-qty');
                if (badgeQtyEl) badgeQtyEl.innerText = currentVarStock;
                const availDisp = document.getElementById('available-stock-display');
                if (availDisp) availDisp.innerText = currentVarStock + ' units';
            } else {
                const baseStockVal = <?php echo (int)$available_stock; ?>;
                const badgeQtyEl = document.getElementById('stock-badge-qty');
                if (badgeQtyEl) badgeQtyEl.innerText = baseStockVal;
                const availDisp = document.getElementById('available-stock-display');
                if (availDisp) availDisp.innerText = baseStockVal + ' units';
            }

            // Calculate updated pricing
            const updatedOriginal = baseOriginalPrice + totalModifier;
            const updatedPrice = basePrice + totalModifier;
            currentUnitPrice = updatedPrice;

            document.getElementById('dynamic-price').innerText = 'Rs. ' + updatedPrice.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const origEl = document.getElementById('dynamic-original');
            if (origEl) {
                origEl.innerText = 'Rs. ' + updatedOriginal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            updateTotalPrice();
        }

        // Add to Cart Logic
        const addToCartBtn = document.getElementById('btn-add-to-cart');
        if (addToCartBtn) {
            addToCartBtn.onclick = () => {
                const qtyVal = parseInt(document.getElementById('product-qty').value) || 1;
                
                // Get selected variants values
                const selectedOptions = [];
                document.querySelectorAll('.variant-select').forEach(select => {
                    if (select.value) {
                        selectedOptions.push(select.value);
                    }
                });

                fetch('cart_ajax.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=add&product_id=<?php echo $product_id; ?>&qty=${qtyVal}&variants=${selectedOptions.join(',')}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (window.openCartDrawer) {
                            window.openCartDrawer();
                        } else {
                            const countEl = document.querySelector('.cart-count');
                            if (countEl) countEl.innerText = data.cart_count;
                            alert('Product successfully added to cart 🛒');
                        }
                    } else {
                        alert(data.message || 'Error adding to cart.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Could not add to cart.');
                });
            };
        }

        // Detail Specs Tab Switching
        function switchTab(evt, tabId) {
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(c => c.classList.remove('active'));

            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(b => b.classList.remove('active'));

            document.getElementById(tabId).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        // ── Review Form Logic ──
        let selectedRating = 0;

        function setRating(val) {
            selectedRating = val;
            document.getElementById('rev-rating').value = val;
            const stars = document.querySelectorAll('.star-rating-input .star-btn');
            stars.forEach((s, i) => {
                if (i < val) s.classList.add('active');
                else s.classList.remove('active');
            });
        }

        function toggleReviewForm() {
            const container = document.getElementById('review-form-container');
            const qContainer = document.getElementById('question-form-container');
            qContainer.style.display = 'none';
            if (container.style.display === 'none') {
                container.style.display = 'block';
                container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                container.style.display = 'none';
            }
        }

        function toggleQuestionForm() {
            const container = document.getElementById('question-form-container');
            const rContainer = document.getElementById('review-form-container');
            rContainer.style.display = 'none';
            if (container.style.display === 'none') {
                container.style.display = 'block';
                container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                container.style.display = 'none';
            }
        }

        function previewUploadImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const preview = document.getElementById('upload-preview');
                preview.src = reader.result;
                preview.style.display = 'block';
                const clearBtn = document.getElementById('clear-upload-btn');
                if (clearBtn) {
                    clearBtn.style.display = 'inline-flex';
                }
            }
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }

        function clearUploadImage() {
            const fileInput = document.getElementById('rev-image');
            if (fileInput) {
                fileInput.value = '';
            }
            const preview = document.getElementById('upload-preview');
            if (preview) {
                preview.src = '';
                preview.style.display = 'none';
            }
            const clearBtn = document.getElementById('clear-upload-btn');
            if (clearBtn) {
                clearBtn.style.display = 'none';
            }
        }

        function switchReviewTab(tab) {
            const rBtn = document.getElementById('tab-reviews-btn');
            const qBtn = document.getElementById('tab-questions-btn');
            const rList = document.getElementById('reviews-list-container');
            const qList = document.getElementById('questions-list-container');
            
            if (tab === 'reviews') {
                rBtn.classList.add('active');
                qBtn.classList.remove('active');
                rList.style.display = 'block';
                qList.style.display = 'none';
            } else {
                rBtn.classList.remove('active');
                qBtn.classList.add('active');
                rList.style.display = 'none';
                qList.style.display = 'block';
            }
        }

        function openPhotoLightbox(src) {
            const modal = document.getElementById('photo-lightbox-modal');
            const img = document.getElementById('photo-lightbox-img');
            img.src = src;
            modal.style.display = 'flex';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePhotoLightbox();
            }
        });

        function closePhotoLightbox() {
            const modal = document.getElementById('photo-lightbox-modal');
            modal.style.display = 'none';
        }

                        function submitQuestion(e) {
            e.preventDefault();
            const name = document.getElementById('q-name').value.trim();
            const email = document.getElementById('q-email').value.trim();
            const text = document.getElementById('q-text').value.trim();
            const toast = document.getElementById('question-toast');
            const btn = e.target.querySelector('button[type="submit"]');

            if (!name) { showToast('error', 'Please enter your name.'); return; }
            if (!text) { showToast('error', 'Please write your question.'); return; }

            const originalBtnText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Submitting...';

            const formData = new FormData();
            formData.append('product_id', '<?php echo $product_id; ?>');
            formData.append('questioner_name', name);
            formData.append('questioner_email', email);
            formData.append('question_text', text);

            fetch('submit_question.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = originalBtnText;
                if (data.success) {
                    toast.className = 'rf-toast success';
                    toast.textContent = data.message;
                    toast.style.display = 'block';
                    document.getElementById('question-form').reset();
                    setTimeout(() => {
                        toast.style.display = 'none';
                        document.getElementById('question-form-container').style.display = 'none';
                    }, 4000);
                } else {
                    toast.className = 'rf-toast error';
                    toast.textContent = data.message;
                    toast.style.display = 'block';
                    setTimeout(() => {
                        toast.style.display = 'none';
                    }, 4000);
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.textContent = originalBtnText;
                console.error(err);
                showToast('error', 'An error occurred. Please try again.');
            });
        }

        function submitReview(e) {
            e.preventDefault();
            const name = document.getElementById('rev-name').value.trim();
            const email = document.getElementById('rev-email').value.trim();
            const rating = selectedRating;
            const text = document.getElementById('rev-text').value.trim();
            const imageFile = document.getElementById('rev-image').files[0];

            if (!name) { showToast('error', 'Please enter your name.'); return; }
            if (rating < 1) { showToast('error', 'Please select a rating.'); return; }
            if (!text) { showToast('error', 'Please write your review.'); return; }

            const btn = document.querySelector('.rf-submit');
            btn.disabled = true;
            btn.textContent = 'Submitting...';

            const formData = new FormData();
            formData.append('product_id', '<?php echo $product_id; ?>');
            formData.append('reviewer_name', name);
            formData.append('reviewer_email', email);
            formData.append('rating', rating);
            formData.append('review_text', text);
            if (imageFile) {
                formData.append('review_image', imageFile);
            }

            fetch('submit_review.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.textContent = 'Submit Review ➔';
                if (data.success) {
                    showToast('success', data.message);
                    document.getElementById('review-form').reset();
                    document.getElementById('upload-preview').style.display = 'none';
                    const clearBtn = document.getElementById('clear-upload-btn');
                    if (clearBtn) clearBtn.style.display = 'none';
                    setRating(0);
                } else {
                    showToast('error', data.message);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.textContent = 'Submit Review ➔';
                showToast('error', 'Something went wrong. Please try again.');
            });
        }
function showToast(type, msg) {
            const toast = document.getElementById('review-toast');
            toast.className = 'rf-toast ' + type;
            toast.textContent = msg;
            toast.style.display = 'block';
            setTimeout(() => { 
                toast.className = 'rf-toast'; 
                toast.textContent = ''; 
                toast.style.display = 'none';
            }, 6000);
        }

    </script>
</body>
</html>
