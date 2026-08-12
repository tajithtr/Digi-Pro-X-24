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

function getCategoryImages($categoryName) {
    $name = strtolower(trim($categoryName));
    if (strpos($name, 'audio') !== false || strpos($name, 'speaker') !== false || strpos($name, 'headphone') !== false || strpos($name, 'headset') !== false) {
        return 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=600&q=80';
    } elseif (strpos($name, 'power') !== false || strpos($name, 'cable') !== false || strpos($name, 'battery') !== false || strpos($name, 'batteries') !== false || strpos($name, 'charger') !== false) {
        return 'https://images.unsplash.com/photo-1609592424109-dd77894d4d5e?auto=format&fit=crop&w=600&q=80';
    } elseif (strpos($name, 'gadget') !== false || strpos($name, 'accessor') !== false || strpos($name, 'holder') !== false || strpos($name, 'keyboard') !== false || strpos($name, 'mouse') !== false) {
        return 'https://images.unsplash.com/photo-1527864550417-7fd91fc51a46?auto=format&fit=crop&w=600&q=80';
    }
    return 'https://images.unsplash.com/photo-1498049794561-7780e7231661?auto=format&fit=crop&w=600&q=80';
}

// Redirect category filtering or searching requests to the products page
if (isset($_GET['category']) || isset($_GET['search'])) {
    $target = 'products.php';
    $query_parts = [];
    if (isset($_GET['category'])) {
        $query_parts[] = 'category=' . (int)$_GET['category'];
    }
    if (isset($_GET['search'])) {
        $query_parts[] = 'search=' . urlencode($_GET['search']);
    }
    if (!empty($query_parts)) {
        $target .= '?' . implode('&', $query_parts);
    }
    header("Location: $target");
    exit;
}

$user_logged_in = isset($_SESSION['user_id']);
$user_country = strtolower(trim($_SESSION['user_country'] ?? ''));
$is_sri_lanka = ($user_country === 'sri lanka' || $user_country === 'lk' || $user_country === 'srilanka' || $user_country === 'sl');

$catSql = "SELECT * FROM categories";
if (!$user_logged_in || !$is_sri_lanka) {
    $catSql .= " WHERE type != 'service'";
}
$catStmt = $pdo->query($catSql);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$service_filter = " AND p.product_type != 'local_service'";

$stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_disabled = 0 AND p.is_trending = 1" . $service_filter . " ORDER BY p.id DESC LIMIT 24");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($products)) {
    $stmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_disabled = 0" . $service_filter . " ORDER BY p.id DESC LIMIT 8");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$local_services = [];
if ($user_logged_in && $is_sri_lanka) {
    $svcStmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_disabled = 0 AND p.product_type = 'local_service' ORDER BY p.id DESC LIMIT 8");
    $local_services = $svcStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch approved reviews for the homepage
$revStmt = $pdo->query("SELECT r.*, p.name as product_name FROM reviews r LEFT JOIN products p ON r.product_id = p.id WHERE r.is_approved = 1 ORDER BY r.created_at DESC LIMIT 6");
$homeReviews = $revStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Dynamic Review Summary (Base target 4.8 + database reviews) ──
// Base ratings yielding 4.8 average:
// 1560 × 5★ + 260 × 4★ + 40 × 3★ + 7 × 2★ + 2 × 1★ = 1,869 reviews
// Sum: 7800 + 1040 + 120 + 14 + 2 = 8976
// Avg: 8976 / 1869 ≈ 4.8025
$baseBreakdown = [
    5 => 1560,
    4 => 260,
    3 => 40,
    2 => 7,
    1 => 2,
];

// Fetch counts of approved reviews from the database
$dbCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
try {
    $dbCountsStmt = $pdo->query("SELECT rating, COUNT(*) as count FROM reviews WHERE is_approved = 1 GROUP BY rating");
    while ($row = $dbCountsStmt->fetch(PDO::FETCH_ASSOC)) {
        $rVal = (int)$row['rating'];
        if ($rVal >= 1 && $rVal <= 5) {
            $dbCounts[$rVal] = (int)$row['count'];
        }
    }
} catch (PDOException $e) {
    // Fallback if table query fails
}

$starBreakdown = [];
$totalReviews = 0;
$totalPoints = 0;

for ($s = 1; $s <= 5; $s++) {
    $count = $baseBreakdown[$s] + $dbCounts[$s];
    $starBreakdown[$s] = [
        'count' => $count,
        'pct' => 0
    ];
    $totalReviews += $count;
    $totalPoints += $count * $s;
}

$avgRating = $totalReviews > 0 ? round($totalPoints / $totalReviews, 1) : 4.8;

// Calculate percentages dynamically
for ($s = 1; $s <= 5; $s++) {
    $starBreakdown[$s]['pct'] = $totalReviews > 0 ? round(($starBreakdown[$s]['count'] / $totalReviews) * 100) : 0;
}

// Fetch flash sale products
$flashStmt = $pdo->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_disabled = 0 AND p.flash_sale_start <= NOW() AND p.flash_sale_end > NOW() ORDER BY p.flash_sale_end ASC LIMIT 12");
$flash_products = $flashStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="https://digiprox24.com/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>DigiPro X24 | Online Computer Store, Laptops & Tech Shop Sri Lanka</title>
    <meta name="description" content="DigiPro X24 is Sri Lanka's leading online computer store. Shop laptops, desktop PCs, gaming accessories, SSDs, RAM & CCTV with islandwide cash on delivery and computer repair services.">
    <meta name="author" content="DigiPro X24">
    <link rel="canonical" href="https://digiprox24.com/">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://digiprox24.com/">
    <meta property="og:title" content="DigiPro X24 | Online Computer Store & Tech Shop Sri Lanka">
    <meta property="og:description" content="Shop premium computers, laptops, gaming PCs, components, and CCTV cameras with islandwide cash on delivery in Sri Lanka.">
    <meta property="og:image" content="https://digiprox24.com/logo.png">
    <meta property="og:site_name" content="DigiPro X24">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://digiprox24.com/">
    <meta property="twitter:title" content="DigiPro X24 | Online Computer Store & Tech Shop Sri Lanka">
    <meta property="twitter:description" content="Shop premium computers, laptops, gaming PCs, components, and CCTV cameras with islandwide cash on delivery in Sri Lanka.">
    <meta property="twitter:image" content="https://digiprox24.com/logo.png">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "DigiPro X24",
      "url": "https://digiprox24.com/",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://digiprox24.com/products.php?search={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "DigiPro X24",
      "url": "https://digiprox24.com/",
      "logo": {
        "@type": "ImageObject",
        "url": "https://digiprox24.com/logo.png"
      },
      "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "+94706756006",
        "contactType": "customer service",
        "email": "digipro24@gmail.com"
      }
    }
    </script>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "DigiPro X24",
      "image": "https://digiprox24.com/logo.png",
      "logo": {
        "@type": "ImageObject",
        "url": "https://digiprox24.com/logo.png"
      },
      "@id": "https://digiprox24.com/#localbusiness",
      "url": "https://digiprox24.com/",
      "telephone": "+94706756006",
      "email": "digipro24@gmail.com",
      "priceRange": "LKR",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "No.161, Wackwella Rd",
        "addressLocality": "Galle",
        "addressRegion": "Southern Province",
        "postalCode": "80000",
        "addressCountry": "LK"
      },
      "openingHoursSpecification": {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": [
          "Monday",
          "Tuesday",
          "Wednesday",
          "Thursday",
          "Friday",
          "Saturday"
        ],
        "opens": "09:00",
        "closes": "18:00"
      }
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        /* Review cards on homepage */
        .home-reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }
        .review-item {
            background: rgba(13, 16, 21, 0.85);
            border: 1px solid rgba(255,94,0,0.08);
            border-radius: 14px;
            padding: 1.5rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .review-item:hover {
            border-color: rgba(255,94,0,0.18);
            box-shadow: 0 8px 25px rgba(255,94,0,0.08);
            transform: translateY(-3px);
        }
        .ri-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-size: 1rem;
            letter-spacing: 1px;
        }
        .ri-product {
            font-size: 0.8rem;
            color: var(--primary-glow);
            font-weight: 600;
        }
        .ri-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-top: 0.5rem;
            font-style: italic;
        }

        .category-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            max-width: 1200px;
            margin: 0 auto 1.5rem auto;
            padding: 0 1.5rem;
        }
        .category-card-clean {
            display: flex;
            flex-direction: column;
            text-decoration: none;
            overflow: hidden;
            cursor: pointer;
        }

        .category-card-img {
            width: 100%;
            height: 250px;
            border-radius: 20px;
            overflow: hidden;
            background: rgba(15, 23, 42, 0.4);
            border: 2px solid var(--primary-glow);
            box-shadow: 0 0 15px rgba(255, 94, 0, 0.15);
            margin-bottom: 1rem;
        }

        .category-card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .category-card-clean:hover .category-card-img,
        .category-card-clean:active .category-card-img {
            border-color: #ff9f0a;
            box-shadow: 0 0 25px rgba(255, 94, 0, 0.4);
        }

        .category-card-clean:hover .category-card-img img,
        .category-card-clean:active .category-card-img img {
            transform: scale(1.08);
        }

        .category-hover-text {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            color: #fff;
            text-align: center;
            padding: 10px 0;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            transform: translateY(100%);
            transition: transform 0.3s ease;
            z-index: 10;
        }
        
        @media (max-width: 768px) {
            .category-hover-text {
                display: none !important;
            }
        }

        .category-card-clean:hover .category-hover-text,
        .category-card-clean:active .category-hover-text {
            transform: translateY(0);
        }

        .category-card-info-wrap {
            text-align: center;
            width: 100%;
        }

        .category-card-title {
            font-size: 1.4rem !important;
            color: #ffffff;
            font-weight: 800;
            margin: 0 0 0.4rem 0;
            transition: color 0.3s ease;
            min-height: 3.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .category-card-clean:hover .category-card-title,
        .category-card-clean:active .category-card-title {
            color: var(--primary-glow) !important;
        }

        .category-card-link {
            font-size: 0.95rem;
            color: var(--primary-glow);
            font-weight: 600;
            display: inline-block;
            transition: transform 0.3s ease;
        }

        .category-card-clean:hover .category-card-link,
        .category-card-clean:active .category-card-link {
            transform: translateX(5px);
        }
        .promo-banner {
            background: var(--surface-color);
            border: 1px solid var(--glass-border);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }
        .promo-banner:hover {
            transform: translateY(-8px);
            border-color: rgba(255, 94, 0, 0.25);
            box-shadow: var(--hover-shadow);
        }
        .promo-banner:hover .promo-bg-icon {
            transform: scale(1.1) rotate(-20deg) !important;
            opacity: 0.09 !important;
        }
        .carousel-track-wrapper {
            position: relative;
            overflow: hidden;
            width: 100%;
            padding: 1.5rem 0;
        }
        .carousel-track {
            display: flex;
            gap: 1.5rem;
            width: max-content;
            animation: scrollMarquee 15s linear infinite;
        }
        .carousel-track:hover {
            animation-play-state: paused;
        }
        .carousel-card {
            width: 280px;
            flex-shrink: 0;
            margin: 0 !important;
            border: 2.5px solid #ff5e00 !important;
            box-shadow: 0 0 15px rgba(255, 94, 0, 0.3) !important;
            border-radius: 24px !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        .carousel-card:hover,
        .carousel-card:active {
            border-color: #ff9f0a !important;
            box-shadow: 0 0 25px rgba(96, 165, 250, 0.6) !important;
            transform: scale(1.03) !important;
        }
        @keyframes scrollMarquee {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-25%);
            }
        }
        @media (max-width: 768px) {
            .carousel-track-wrapper {
                overflow: hidden;
                padding: 1.5rem 1rem 2rem 1rem;
            }
            .carousel-track-wrapper::before,
            .carousel-track-wrapper::after {
                display: none; /* Remove fade edges on mobile */
            }
            .carousel-track {
                width: max-content;
                gap: 1.2rem;
                animation: scrollMarquee 15s linear infinite !important;
            }
            .carousel-card {
                width: 75vw;
                max-width: 320px;
            }
        }
        .hero {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-height: 80vh !important;
            width: 100% !important;
            padding: 140px 1.5rem 120px 1.5rem !important;
            background: #050608 url('hero.jpg') center center / cover no-repeat !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
            position: relative !important;
            box-shadow: none !important;
            margin-top: -80px !important;
            overflow: hidden;
        }
        .hero-glass-card {
            max-width: 680px;
            width: 100%;
            padding: 2.5rem 3rem;
            border-radius: 28px;
            background: rgba(15, 23, 42, 0.55) !important;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15) !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
            text-align: center;
            z-index: 2;
            animation: fadeInSlideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards, floatCard 6s ease-in-out infinite 0.8s, glowPulse 4s ease-in-out infinite;
            margin-bottom: 0;
            box-sizing: border-box;
        }
        .hero-badge {
            display: inline-block;
            padding: 0.4rem 1.1rem;
            background: rgba(255, 94, 0, 0.25);
            border: 1px solid rgba(255, 94, 0, 0.35);
            color: #ff9f0a;
            font-size: 0.8rem;
            font-weight: 700;
            border-radius: 30px;
            margin-bottom: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .hero-glass-card h1 {
            font-size: 3.6rem !important;
            line-height: 1.25 !important;
            margin: 0 !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4) !important;
        }
        .hero-glass-card h1 span {
            background: linear-gradient(135deg, #ff9f0a, #ffbd00) !important;
            -webkit-background-clip: text !important;
            -webkit-text-fill-color: transparent !important;
            display: inline-block !important;
        }
        .hero-glass-card p {
            font-size: 1.05rem !important;
            color: rgba(255, 255, 255, 0.9) !important;
            margin-bottom: 1.8rem !important;
            line-height: 1.5 !important;
            text-shadow: none !important;
        }
        .hero-buttons {
            display: flex;
            gap: 1.2rem;
            justify-content: center !important;
        }
        .promo-grid-section {
            max-width: 1240px;
            margin: 0 auto 2.5rem auto !important;
            padding: 0 1.5rem;
            position: relative !important;
            z-index: 10 !important;
        }
        .animate-1 { animation: fadeInUpElement 0.6s ease-out forwards; opacity: 0; }
        .animate-2 { animation: fadeInUpElement 0.6s ease-out 0.2s forwards; opacity: 0; }
        .animate-3 { animation: fadeInUpElement 0.6s ease-out 0.4s forwards; opacity: 0; }
        .animate-4 { animation: fadeInUpElement 0.6s ease-out 0.6s forwards; opacity: 0; }

        @keyframes fadeInUpElement {
            0% { opacity: 0; transform: translateY(15px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes floatCard {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
        }
        @keyframes glowPulse {
            0% { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 15px rgba(255, 94, 0, 0.15); border-color: rgba(255, 255, 255, 0.15) !important; }
            50% { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 25px rgba(255, 94, 0, 0.3); border-color: rgba(96, 165, 250, 0.35) !important; }
            100% { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 15px rgba(255, 94, 0, 0.15); border-color: rgba(255, 255, 255, 0.15) !important; }
        }
        @keyframes fadeInSlideUp {
            0% { opacity: 0; transform: translateY(30px) scale(0.98); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translate(-50%, 0); }
            40% { transform: translate(-50%, -10px); }
            60% { transform: translate(-50%, -5px); }
        }
        .hero-top-banner {
            position: absolute;
            top: 100px;
            left: 0;
            width: 100%;
            overflow: hidden;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 0.6rem 0;
            z-index: 5;
        }
        .ticker-wrap {
            display: flex;
            width: 100%;
            overflow: hidden;
        }
        .ticker-text {
            display: inline-block;
            white-space: nowrap;
            font-size: 0.9rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #ffffff;
            animation: tickerAnimation 25s linear infinite;
        }
        .ticker-text span {
            color: #ff9f0a;
            text-shadow: 0 0 10px rgba(96, 165, 250, 0.4);
        }
        @keyframes tickerAnimation {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-33.333%, 0, 0); }
        }
        @media (max-width: 1024px) {
            .hero {
                background-size: cover !important;
                background-position: center center !important;
                width: 100% !important;
                min-height: 60vh !important;
                height: auto !important;
                margin-top: 75px !important;
                padding: 6rem 1.5rem !important;
            }
            .hero-top-banner,
            .hero div[style*="bounce"] {
                display: none !important;
            }
            .promo-grid-section {
                margin: 0 auto 2rem auto !important;
            }
        }
        @media (max-width: 768px) {
            main {
                padding-top: 0 !important;
            }
            .hero {
                background-size: cover !important;
                background-position: center center !important;
                width: 100% !important;
                min-height: 520px !important;
                height: auto !important;
                margin-top: 60px !important;
                padding: 4rem 1rem 3rem 1rem !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .hero-top-banner,
            .hero div[style*="bounce"] {
                display: none !important;
            }
            .hero-glass-card {
                display: block !important;
                width: 100% !important;
                max-width: 480px !important;
                padding: 2.2rem 1.5rem !important;
                border-radius: 20px !important;
                margin: 0 auto !important;
            }
            .hero-glass-card h1 {
                font-size: 2.2rem !important;
            }
            .hero-glass-card p {
                font-size: 0.95rem !important;
            }
            .hero-buttons {
                flex-direction: column !important;
                gap: 0.8rem !important;
            }
            .hero-buttons a {
                width: 100% !important;
            }
            .promo-grid-section {
                margin: 0 auto 1.5rem auto !important;
            }
        }
        /* ── Full Banner Background with Text Overlay Styles ── */
        /* ── Full-Width Edge-to-Edge Hero Banner Carousel ── */
        .hero-banner-section {
            width: 100%;
            max-width: 100%;
            margin: 0 0 2.5rem 0;
            padding: 0;
            position: relative;
            z-index: 10;
        }

        .banner-slider-box {
            position: relative;
            width: 100%;
            height: 420px;
            min-height: 420px;
            overflow: hidden;
            border-top: 1.5px solid rgba(255, 94, 0, 0.3);
            border-bottom: 1.5px solid rgba(255, 94, 0, 0.3);
            border-left: none;
            border-right: none;
            border-radius: 0;
            box-shadow: 0 15px 45px rgba(0, 0, 0, 0.7), 0 0 30px rgba(255, 94, 0, 0.12);
            background: #0d1015;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .banner-slider-box:hover {
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.98), 0 0 25px rgba(255, 94, 0, 0.25), 0 0 50px rgba(0, 0, 0, 0.8);
            border-top-color: rgba(255, 94, 0, 0.6);
            border-bottom-color: rgba(255, 94, 0, 0.6);
        }

        .banner-slider-track {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 420px;
        }

        .banner-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            transform: scale(0.98);
            transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
            height: 100%;
            min-height: 420px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            padding: 3rem 6rem;
            background-size: cover;
            background-position: center;
            z-index: 1;
        }

        .banner-slide.active {
            opacity: 1;
            visibility: visible;
            transform: scale(1);
            z-index: 2;
        }

        /* Banner backgrounds using your images with dark overlay */
        .slide-flash-bg {
            background: linear-gradient(90deg, rgba(5, 6, 8, 0.7) 0%, rgba(5, 6, 8, 0.3) 50%, rgba(5, 6, 8, 0.1) 100%), url('Banner1.jpg') center/cover no-repeat;
        }

        .slide-delivery-bg {
            background: linear-gradient(90deg, rgba(5, 6, 8, 0.7) 0%, rgba(5, 6, 8, 0.3) 50%, rgba(5, 6, 8, 0.1) 100%), url('Banner2.jpg') center/cover no-repeat;
        }

        .slide-organic-bg {
            background: linear-gradient(90deg, rgba(5, 6, 8, 0.7) 0%, rgba(5, 6, 8, 0.3) 50%, rgba(5, 6, 8, 0.1) 100%), url('Banner3.jpg?v=2') center/cover no-repeat;
        }

        .slide-tech-bg {
            background: linear-gradient(90deg, rgba(5, 6, 8, 0.7) 0%, rgba(5, 6, 8, 0.3) 50%, rgba(5, 6, 8, 0.1) 100%), url('Banner4.jpg?v=2') center/cover no-repeat;
        }

        .banner-slide-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            z-index: 5;
        }

        .banner-tag {
            display: inline-block;
            padding: 0.4rem 1.2rem;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 1rem;
        }

        .tag-flash {
            background: rgba(255, 94, 0, 0.25);
            color: #ff5e00;
            border: 1px solid rgba(255, 94, 0, 0.4);
        }

        .tag-delivery {
            background: rgba(255, 189, 0, 0.2);
            color: #ffbd00;
            border: 1px solid rgba(255, 189, 0, 0.4);
        }

        .tag-organic {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.4);
        }

        .tag-tech {
            background: rgba(56, 189, 248, 0.2);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.4);
        }

        .banner-title {
            font-size: 3rem;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.15;
            margin: 0 0 0.5rem 0;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.9));
            text-shadow: none;
        }

        .banner-subtitle {
            font-size: 1.1rem;
            color: #e2e8f0;
            line-height: 1.6;
            margin: 0 0 1.5rem 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.8);
            max-width: 90%;
        }

        .banner-btn {
            display: inline-flex;
            align-items: center;
            padding: 0.9rem 2.2rem;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.25s ease;
            box-shadow: 0 6px 20px rgba(0,0,0,0.4);
        }

        .banner-btn:hover,
        .banner-btn:active {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.6);
        }

        .btn-orange {
            background: linear-gradient(135deg, #ff5e00, #e02424);
            color: #ffffff;
        }

        .btn-gold {
            background: linear-gradient(135deg, #ffbd00, #f59e0b);
            color: #050608;
        }

        .btn-green {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #ffffff;
        }

        .btn-cyan {
            background: linear-gradient(135deg, #38bdf8, #0284c7);
            color: #ffffff;
        }

        /* Controls */
        .banner-arrow-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(5, 6, 8, 0.8);
            border: 1.5px solid rgba(255, 94, 0, 0.4);
            color: #ffffff;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 20;
            transition: all 0.25s ease;
            outline: none;
        }

        .banner-arrow-btn:hover {
            background: #ff5e00;
            border-color: #ff5e00;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 0 15px rgba(255, 94, 0, 0.6);
        }

        .nav-arrow-left { left: 14px; }
        .nav-arrow-right { right: 14px; }

        .banner-dots-wrapper {
            position: absolute;
            bottom: 18px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 20;
        }

        .banner-dot-item {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.35);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
        }

        .banner-dot-item.active {
            background: #ff5e00;
            width: 28px;
            border-radius: 6px;
            box-shadow: 0 0 12px rgba(255, 94, 0, 0.6);
        }

        .home-search-container {
            max-width: 1240px;
            margin: 100px auto 1.5rem auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: center;
            position: relative;
            z-index: 15;
        }

        @media (max-width: 1024px) {
            .banner-slide {
                padding: 2.5rem 4.5rem 2.5rem 4.5rem;
            }
            .banner-title {
                font-size: 2.2rem;
            }
            .banner-subtitle {
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .home-search-container {
                margin: 75px auto 1.5rem auto;
                padding: 0 1rem;
            }
            .home-search-container input {
                padding: 0.8rem 3.5rem 0.8rem 1.2rem !important;
                font-size: 0.95rem !important;
            }
            .home-search-container button {
                width: 36px !important;
                height: 36px !important;
                right: 6px !important;
                font-size: 1rem !important;
            }
            .hero-banner-section {
                margin: 0 0 1.8rem 0;
            }
            .banner-slider-box,
            .banner-slider-track,
            .banner-slide {
                height: 380px;
                min-height: 380px;
            }
            .banner-slide {
                padding: 2rem 42px 2rem 42px;
            }
            .banner-title {
                font-size: 1.65rem;
                line-height: 1.25;
            }
            .banner-subtitle {
                font-size: 0.9rem;
                max-width: 100%;
                margin-bottom: 1.2rem;
            }
            .banner-arrow-btn {
                top: 50%;
                transform: translateY(-50%);
                width: 30px;
                height: 30px;
                font-size: 0.85rem;
                background: rgba(13, 16, 21, 0.85);
            }
            .banner-arrow-btn:hover {
                transform: translateY(-50%) scale(1.1);
            }
            .nav-arrow-left {
                left: 6px;
                right: auto;
            }
            .nav-arrow-right {
                right: 6px;
                left: auto;
            }
            .banner-btn {
                padding: 0.7rem 1.5rem;
                font-size: 0.85rem;
            }
        }

        /* ── Official Delivery & Payment Partners Marquee ── */
        .partners-marquee-section {
            max-width: 1240px;
            margin: 2.5rem auto 3.5rem auto;
            padding: 0 1.5rem;
            position: relative;
            z-index: 10;
        }

        .partners-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .partners-tag {
            background: rgba(255, 94, 0, 0.12);
            color: var(--primary-glow);
            border: 1px solid rgba(255, 94, 0, 0.3);
            padding: 0.35rem 1.1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 0.4rem;
        }

        .partners-header h3 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            margin: 0;
        }

        .partners-slider-wrapper {
            position: relative;
            width: 100%;
            overflow: hidden;
            background: rgba(13, 16, 21, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1.5px solid rgba(255, 94, 0, 0.25);
            border-radius: 20px;
            padding: 1.1rem 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(255, 94, 0, 0.05);
        }

        .partners-slider-wrapper::before,
        .partners-slider-wrapper::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 70px;
            z-index: 5;
            pointer-events: none;
        }

        .partners-slider-wrapper::before {
            left: 0;
            background: linear-gradient(90deg, #050608 0%, rgba(5, 6, 8, 0) 100%);
        }

        .partners-slider-wrapper::after {
            right: 0;
            background: linear-gradient(270deg, #050608 0%, rgba(5, 6, 8, 0) 100%);
        }

        .partners-track {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            width: max-content;
            animation: scrollPartners 26s linear infinite;
        }

        .partners-slider-wrapper:hover .partners-track {
            animation-play-state: paused;
        }

        @keyframes scrollPartners {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        .partner-badge {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.6rem 1.3rem;
            border-radius: 30px;
            transition: all 0.3s ease;
            white-space: nowrap;
            cursor: pointer;
        }

        .partner-badge:hover {
            background: rgba(255, 94, 0, 0.15);
            border-color: #ff5e00;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 94, 0, 0.3);
        }

        .partner-icon {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .partner-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
        }

        .partner-sub {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.55);
            font-weight: 500;
        }
        /* High-Performance Scroll Reveal */
        .reveal-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1), transform 0.7s cubic-bezier(0.16, 1, 0.3, 1);
            will-change: opacity, transform;
        }
        .reveal-up.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

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
                <li><a href="index.php" class="active">Home</a></li>
                <li><a href="categories.php">Categories</a></li>
                <li><a href="products.php">Products</a></li>
                <?php $uc = strtolower(trim($_SESSION['user_country'] ?? '')); if (isset($_SESSION['user_id']) && ($uc === 'sri lanka' || $uc === 'lk' || $uc === 'srilanka' || $uc === 'sl')): ?>
                <li><a href="services.php">Services</a></li>
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

    <main>
        <!-- Top Search Bar Container (Standard Spacing below Nav Bar and above Carousel) -->
        <div class="home-search-container">
            <form action="products.php" method="GET" style="position: relative; width: 100%; max-width: 800px;">
                <input type="text" name="search" style="width: 100%; padding: 1rem 4.5rem 1rem 2rem; border-radius: 30px; border: 2px solid var(--primary-glow); background: rgba(13, 16, 21, 0.92); backdrop-filter: blur(10px); color: #ffffff; font-size: 1.05rem; outline: none; transition: all 0.3s; box-shadow: 0 4px 20px rgba(255, 94, 0, 0.2);" placeholder="Search for products, tech gadgets..." required>
                <button type="submit" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; border-radius: 50%; border: none; background: var(--primary-glow); color: #ffffff; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; transition: all 0.25s ease;" title="Search">
                    🔍
                </button>
            </form>
        </div>

        <!-- Full-Width Image Hero Banner Carousel Section -->
        <section class="hero-banner-section" style="margin: 0 0 2.5rem 0;">
            <div class="banner-slider-box" id="std-banner-slider">
                <div class="banner-slider-track" id="std-banner-track">
                    
                    <!-- Banner Slide 1: Flash Sale -->
                    <div class="banner-slide slide-flash-bg active">
                        <div class="banner-slide-content">
                            <span class="banner-tag tag-flash">⚡ MEGA FLASH SALE</span>
                            <h1 class="banner-title" style="background: linear-gradient(135deg, #ff7e5f 0%, #feb47b 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Up to 70% OFF On Premium Tech</h1>
                            <p class="banner-subtitle" style="color: #e2e8f0;">Explore top wireless audio, smartwatches &amp; tech accessories with fast island-wide delivery.</p>
                            <a href="#flash-deals" class="banner-btn btn-orange">Shop Flash Deals ➔</a>
                        </div>
                    </div>

                    <!-- Banner Slide 2: Island Wide Free Shipping -->
                    <div class="banner-slide slide-delivery-bg">
                        <div class="banner-slide-content">
                            <span class="banner-tag tag-delivery">🚚 SPECIAL SHIPPING OFFER</span>
                            <h2 class="banner-title" style="background: linear-gradient(135deg, #f6d365 0%, #fda085 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">FREE Island-Wide Shipping</h2>
                            <p class="banner-subtitle" style="color: #e2e8f0;">Spend <strong>Rs. 5,000 or more</strong> and get 100% FREE delivery directly to your doorstep!</p>
                            <a href="products.php" class="banner-btn btn-gold">Start Shopping Now ➔</a>
                        </div>
                    </div>

                    <!-- Banner Slide 3: Technical Services -->
                    <div class="banner-slide slide-organic-bg">
                        <div class="banner-slide-content">
                            <span class="banner-tag tag-tech">🛠️ EXPERT TECHNICAL SERVICE</span>
                            <h2 class="banner-title" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Professional IT &amp; Hardware Repairs</h2>
                            <p class="banner-subtitle" style="color: #e2e8f0;">Fast, reliable, and expert diagnostic services for all your premium electronics and smart devices.</p>
                            <a href="#reviews" class="banner-btn btn-cyan">See Customer Reviews ➔</a>
                        </div>
                    </div>

                    <!-- Banner Slide 4: Smart Tech & Electronics -->
                    <div class="banner-slide slide-tech-bg">
                        <div class="banner-slide-content">
                            <span class="banner-tag tag-tech">🖥️ PREMIUM PC HARDWARE</span>
                            <h2 class="banner-title" style="background: linear-gradient(135deg, #00f2fe 0%, #4facfe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">High-Performance Components</h2>
                            <p class="banner-subtitle" style="color: #e2e8f0;">Build your dream workstation or gaming rig with our elite PC hardware.</p>
                            <a href="products.php?category=8" class="banner-btn btn-cyan">Shop Hardware ➔</a>
                        </div>
                    </div>

                </div><!-- /.banner-slider-track -->

                <!-- Carousel Navigation Arrows -->
                <button class="banner-arrow-btn nav-arrow-left" id="std-banner-prev" aria-label="Previous Slide">❮</button>
                <button class="banner-arrow-btn nav-arrow-right" id="std-banner-next" aria-label="Next Slide">❯</button>

                <!-- Carousel Pagination Dots -->
                <div class="banner-dots-wrapper" id="std-banner-dots">
                    <button class="banner-dot-item active" data-idx="0" aria-label="Slide 1"></button>
                    <button class="banner-dot-item" data-idx="1" aria-label="Slide 2"></button>
                    <button class="banner-dot-item" data-idx="2" aria-label="Slide 3"></button>
                    <button class="banner-dot-item" data-idx="3" aria-label="Slide 4"></button>
                </div>
            </div><!-- /.banner-slider-box -->
        </section>



        <?php if (!empty($flash_products)): ?>
        <!-- Flash Deals Section -->
        <section class="flash-deals reveal-up" id="flash-deals" style="max-width: 1200px; margin: 4rem auto 2rem auto; position: relative; overflow: hidden;">
            <div class="section-header" style="text-align: center; margin-bottom: 3rem; padding: 0 1.5rem;">
                <h2 style="font-size: 2.2rem; color: var(--text-main);">Mega <span style="color: var(--primary-glow); background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Flash Deals</span></h2>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 1.05rem;">Hurry up! These offers end soon.</p>
            </div>
            
            <div class="product-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 2rem;">
                <?php foreach($flash_products as $product): 
                    $original_price_lkr = $product['price'];
                    $flash_price_lkr = $product['flash_sale_price'];
                    $discount_percent = $original_price_lkr > 0 ? round((($original_price_lkr - $flash_price_lkr) / $original_price_lkr) * 100) : 0;
                ?>
                <div class="product-card glass-panel" data-discount="<?php echo $discount_percent; ?>" 
                     onclick="window.location.href='product_detail.php?id=<?php echo $product['id']; ?>'" style="border: 2px solid rgba(255, 94, 0, 0.3); box-shadow: 0 0 20px rgba(255, 94, 0, 0.1);">
                    <div class="product-image">
                        <?php if(is_product_free_shipping($product['id'], $product['shipping_fee'] ?? 450.00, $product['product_type'] ?? 'product')): ?>
                            <div class="free-shipping-badge" style="position: absolute; top: 15px; right: 15px; background: rgba(139, 92, 246, 0.95); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 7; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.35); display: flex; align-items: center; gap: 0.3rem;"><span style="font-size: 0.9rem;">🚚</span> FREE</div>
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars(get_product_image_url($product['image'])); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="discount-badge" style="background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow)); box-shadow: 0 2px 8px rgba(255, 94, 0, 0.4);"><?php echo $discount_percent; ?>% OFF</div>
                        <?php if ($product['stock'] > 0): ?>
                            <div class="stock-badge-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(16, 185, 129, 0.95); color: #ffffff; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15); z-index: 5; letter-spacing: 0.5px;">IN STOCK</div>
                        <?php else: ?>
                            <div class="stock-badge-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(239, 68, 68, 0.95); color: #ffffff; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15); z-index: 5; letter-spacing: 0.5px;">OUT OF STOCK</div>
                                    <?php endif; ?>
                            </div>
                            <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <div class="price-row" style="margin-bottom: 0.75rem;">
                            <div class="price-col">
                                <span class="original-price" style="font-size: 0.95rem;">Rs. <?php echo number_format($original_price_lkr, 2); ?></span>
                                <span class="price" style="color: var(--primary-glow); font-size: 1.4rem;">Rs. <?php echo number_format($flash_price_lkr, 2); ?></span>
                            </div>
                            <?php if ($product['stock'] > 0): ?>
                                <button class="btn-add-cart" data-id="<?php echo $product['id']; ?>" onclick="event.stopPropagation()">Add 🛒</button>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flash-countdown" data-endtime="<?php echo strtotime($product['flash_sale_end']) * 1000; ?>" style="background: linear-gradient(145deg, var(--primary-glow), var(--secondary-glow)); box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.4), inset 0 -2px 4px rgba(0, 0, 0, 0.3), 0 5px 15px rgba(255, 94, 0, 0.4); border-radius: 12px; padding: 0.8rem; text-align: center; border: 1px solid rgba(255, 255, 255, 0.2);">
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.9); margin-bottom: 0.4rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Ends in</div>
                            <div class="timer-display" style="display: flex; justify-content: center; align-items: center; gap: 0.4rem; color: #ffffff; font-weight: 800; font-size: 1.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">
                                <div class="time-block" style="display: flex; flex-direction: column; align-items: center; line-height: 1;"><span class="days">00</span><span style="font-size: 0.6rem; font-weight: 600; color: rgba(255, 255, 255, 0.85); margin-top: 2px;">DAYS</span></div>
                                <div style="color: rgba(255, 255, 255, 0.6); padding-bottom: 8px;">:</div>
                                <div class="time-block" style="display: flex; flex-direction: column; align-items: center; line-height: 1;"><span class="hours">00</span><span style="font-size: 0.6rem; font-weight: 600; color: rgba(255, 255, 255, 0.85); margin-top: 2px;">HRS</span></div>
                                <div style="color: rgba(255, 255, 255, 0.6); padding-bottom: 8px;">:</div>
                                <div class="time-block" style="display: flex; flex-direction: column; align-items: center; line-height: 1;"><span class="minutes">00</span><span style="font-size: 0.6rem; font-weight: 600; color: rgba(255, 255, 255, 0.85); margin-top: 2px;">MINS</span></div>
                                <div style="color: rgba(255, 255, 255, 0.6); padding-bottom: 8px;">:</div>
                                <div class="time-block" style="display: flex; flex-direction: column; align-items: center; line-height: 1;"><span class="seconds">00</span><span style="font-size: 0.6rem; font-weight: 600; color: rgba(255, 255, 255, 0.85); margin-top: 2px;">SECS</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const flashSection = document.getElementById('flash-deals');
                    const flashTimers = document.querySelectorAll('.flash-countdown');
                    if (!flashTimers.length) return;

                    let sectionVisible = false;
                    let lastTick = 0;
                    let rafId = null;

                    // Pause timer DOM updates when flash-deals section is off-screen
                    const visObs = new IntersectionObserver(entries => {
                        sectionVisible = entries[0].isIntersecting;
                        if (sectionVisible && !rafId) scheduleTick();
                    }, { threshold: 0 });
                    if (flashSection) visObs.observe(flashSection);

                    function updateTimers() {
                        const now = Date.now();
                        flashTimers.forEach(timer => {
                            const endTime = parseInt(timer.getAttribute('data-endtime'));
                            const distance = endTime - now;
                            if (distance < 0) {
                                if (!timer.dataset.ended) {
                                    timer.innerHTML = '<div style="color:var(--primary-glow);font-weight:800;padding:0.5rem;">Sale Ended</div>';
                                    timer.dataset.ended = '1';
                                }
                                return;
                            }
                            const days    = Math.floor(distance / 86400000);
                            const hours   = Math.floor((distance % 86400000) / 3600000);
                            const minutes = Math.floor((distance % 3600000) / 60000);
                            const seconds = Math.floor((distance % 60000) / 1000);
                            timer.querySelector('.days').textContent    = String(days).padStart(2,'0');
                            timer.querySelector('.hours').textContent   = String(hours).padStart(2,'0');
                            timer.querySelector('.minutes').textContent = String(minutes).padStart(2,'0');
                            timer.querySelector('.seconds').textContent = String(seconds).padStart(2,'0');
                        });
                    }

                    function scheduleTick() {
                        rafId = requestAnimationFrame(ts => {
                            rafId = null;
                            if (!sectionVisible) return; // stop updating when off-screen
                            if (ts - lastTick >= 1000) {
                                lastTick = ts;
                                updateTimers();
                            }
                            scheduleTick();
                        });
                    }

                    // Run once immediately to set correct values
                    updateTimers();
                    sectionVisible = true;
                    scheduleTick();
                });
            </script>
        </section>
        <?php endif; ?>


        <!-- Categories Showcase Section -->
        <section id="categories" class="home-categories reveal-up" style="padding: 5rem 0 2rem 0; position: relative; overflow: hidden;">
            <div class="section-header" style="text-align: center; margin-bottom: 3rem; padding: 0 1.5rem;">
                <h2 style="font-size: 2.2rem; color: var(--text-main);">Shop by <span style="color: var(--primary-glow); background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Category</span></h2>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 1.05rem;">Select a category to browse our curated collection of appliances and gear.</p>
            </div>

            <div class="carousel-track-wrapper">
                <div class="carousel-track">
                    <?php for($i = 0; $i < 4; $i++): ?>
                        <?php foreach($categories as $category): 
                            $cat_image = get_product_image_url($category['image']);
                            $is_service_cat = (($category['type'] ?? 'product') === 'service');
                        ?>
                            <a href="products.php?category=<?php echo $category['id']; ?>" class="category-card-clean carousel-card">
                                <div class="category-card-img" style="position: relative;">
                                    <div class="category-hover-text">
                                        <?php echo $is_service_cat ? 'Book a Service' : 'Browse Products'; ?>
                                    </div>
                                    <img src="<?php echo htmlspecialchars($cat_image); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                                </div>
                                <div class="category-card-info-wrap">
                                    <h3 class="category-card-title"><?php echo htmlspecialchars($category['name']); ?></h3>
                                    <span class="category-card-link">
                                        <?php echo $is_service_cat ? 'View Services' : 'Shop Now'; ?> <span class="arrow">➔</span>
                                    </span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endfor; ?>
                </div>
            </div>

            <div style="text-align: center; margin-top: 3.5rem; padding: 0 1.5rem;">
                <a href="products.php" class="banner-btn btn-orange" style="font-size: 1.05rem; font-weight: 600; text-decoration: none;">Explore More Categories ➔</a>
            </div>
        </section>

        <!-- Featured Products Section -->
        <section class="featured-products reveal-up" style="max-width: 1200px; margin: 4rem auto 6rem auto; padding: 0 1.5rem;">
            <div class="section-header" style="text-align: center; margin-bottom: 3.5rem;">
                <h2 style="font-size: 2.2rem; color: var(--text-main);">Trending <span style="color: var(--primary-glow); background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Products</span></h2>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 1.05rem;">Handpicked high-performance electronics and appliances for you.</p>
            </div>
            
            <div class="product-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 2rem;">
                <?php foreach($products as $product): 
                    $original_price_lkr = $product['price'];
                    $discount_percent = $product['discount_percent'] ?? 0;
                    $current_price_lkr = $original_price_lkr;
                    if ($discount_percent > 0) {
                        $current_price_lkr = $original_price_lkr * (1 - ($discount_percent / 100));
                    }
                    $is_flash_sale = (isset($product['flash_sale_price']) && $product['flash_sale_price'] > 0 && strtotime($product['flash_sale_start']) <= time() && strtotime($product['flash_sale_end']) > time());
                    $has_top_badge = $is_flash_sale || (isset($product['is_new_arrival']) && $product['is_new_arrival']);
                ?>
                <div class="product-card glass-panel" data-discount="<?php echo $discount_percent; ?>" 
                     onclick="window.location.href='product_detail.php?id=<?php echo $product['id']; ?>'">
                    <div class="product-image">
                        <?php if(is_product_free_shipping($product['id'], $product['shipping_fee'] ?? 450.00, $product['product_type'] ?? 'product')): ?>
                            <div class="free-shipping-badge" style="position: absolute; top: 15px; right: 15px; background: rgba(139, 92, 246, 0.95); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 7; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.35); display: flex; align-items: center; gap: 0.3rem;"><span style="font-size: 0.9rem;">🚚</span> FREE</div>
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars(get_product_image_url($product['image'])); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        
                        <?php if($is_flash_sale): ?>
                             <div class="new-badge" style="position: absolute; top: 15px; left: 15px; background: rgba(255, 94, 0, 0.95); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 6; box-shadow: 0 4px 10px rgba(255, 94, 0, 0.35);">FLASH DEAL</div>
                        <?php elseif(isset($product['is_new_arrival']) && $product['is_new_arrival']): ?>
                             <div class="new-badge" style="position: absolute; top: 15px; left: 15px; background: rgba(16, 185, 129, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 6; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);">NEW</div>
                        <?php endif; ?>

                        <?php if($discount_percent > 0): ?>
                             <div class="discount-badge" <?php echo $has_top_badge ? 'style="top: 55px;"' : ''; ?>><?php echo $discount_percent; ?>% OFF</div>
                        <?php endif; ?>
                        <?php if ($product['stock'] > 0): ?>
                            <div class="stock-badge-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(16, 185, 129, 0.95); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); color: #ffffff; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15); z-index: 5; letter-spacing: 0.5px;">IN STOCK</div>
                        <?php else: ?>
                            <div class="stock-badge-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(239, 68, 68, 0.95); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); color: #ffffff; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15); z-index: 5; letter-spacing: 0.5px;">OUT OF STOCK</div>
                                    <?php endif; ?>
                            </div>
                            <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                         <?php if(!empty($product['warranty'])): 
                              $style = [
                                  'color' => '#00f2fe',
                                  'bg' => 'rgba(0, 242, 254, 0.1)',
                                  'border' => 'rgba(0, 242, 254, 0.3)',
                                  'shadow' => 'inset 0 0 4px rgba(0, 242, 254, 0.2), 0 0 8px rgba(0, 242, 254, 0.15)'
                              ];
                              $wLower = strtolower($product['warranty']);
                              if (strpos($wLower, '1 year') !== false || strpos($wLower, '1year') !== false) {
                                  $style['color'] = '#fbbf24';
                                  $style['bg'] = 'rgba(251, 191, 36, 0.1)';
                                  $style['border'] = 'rgba(251, 191, 36, 0.35)';
                                  $style['shadow'] = 'inset 0 0 4px rgba(251, 191, 36, 0.2), 0 0 8px rgba(251, 191, 36, 0.15)';
                              } elseif (strpos($wLower, '2 year') !== false || strpos($wLower, '3 year') !== false || strpos($wLower, '5 year') !== false) {
                                  $style['color'] = '#34d399';
                                  $style['bg'] = 'rgba(52, 211, 153, 0.1)';
                                  $style['border'] = 'rgba(52, 211, 153, 0.35)';
                                  $style['shadow'] = 'inset 0 0 4px rgba(52, 211, 153, 0.2), 0 0 8px rgba(52, 211, 153, 0.15)';
                              }
                          ?>
                              <div class="warranty-tag" style="font-size: 0.72rem; color: <?php echo $style['color']; ?>; background: <?php echo $style['bg']; ?>; border: 1px solid <?php echo $style['border']; ?>; padding: 4px 10px; border-radius: 20px; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 0.6rem; font-weight: 700; width: max-content; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: <?php echo $style['shadow']; ?>;">
                                  <span style="font-size: 0.8rem;">🛡️</span> <span><?php echo htmlspecialchars($product['warranty']); ?></span>
                              </div>
                          <?php endif; ?>
                        <p class="description"><?php echo htmlspecialchars(substr($product['description'], 0, 60)) . '...'; ?></p>
                        <div class="price-row">
                            <div class="price-col">
                                <?php if($discount_percent > 0): ?>
                                    <span class="original-price">Rs. <?php echo number_format($original_price_lkr, 2); ?></span>
                                <?php endif; ?>
                                <span class="price">Rs. <?php echo number_format($current_price_lkr, 2); ?></span>
                            </div>
                            <?php if ($product['stock'] > 0): ?>
                                <button class="btn-add-cart" data-id="<?php echo $product['id']; ?>" onclick="event.stopPropagation()">Add 🛒</button>
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 4.5rem;">
                <a href="products.php" class="btn-primary neon-glow" style="padding: 1.2rem 3.5rem; font-size: 1.1rem; border-radius: 30px; display: inline-block; text-decoration: none;">
                    View All Products ➔
                </a>
            </div>
        </section>

        <?php if (!empty($local_services)): ?>
        <section class="local-services reveal-up" style="max-width: 1200px; margin: 4rem auto 2rem auto; position: relative; overflow: hidden;">
            <div class="section-header" style="text-align: center; margin-bottom: 3rem; padding: 0 1.5rem;">
                <h2 style="font-size: 2.2rem; color: var(--text-main);">Technical &amp; <span style="color: var(--primary-glow); background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Installation Services</span></h2>
                <p style="color: var(--text-muted); margin-top: 0.5rem; font-size: 1.05rem;">Professional local services for Sri Lankan customers.</p>
            </div>
            
            <div class="product-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 2rem;">
                <?php foreach($local_services as $product): ?>
                <div class="product-card glass-panel" 
                     onclick="window.location.href='product_detail.php?id=<?php echo $product['id']; ?>'" style="border: 2px solid rgba(16, 185, 129, 0.3); box-shadow: 0 0 20px rgba(16, 185, 129, 0.1);">
                    <div class="product-image">
                        <img src="<?php echo htmlspecialchars(get_product_image_url($product['image'])); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="new-badge" style="position: absolute; top: 15px; left: 15px; background: rgba(59, 130, 246, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 6; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);">SERVICE</div>
                    </div>
                    <div class="product-info">
                        <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                        <p class="description"><?php echo htmlspecialchars(substr($product['description'], 0, 60)) . '...'; ?></p>
                        <div style="margin-bottom: 1rem; text-align: left;">
                            <span class="price" style="font-size: 1.4rem; color: var(--primary-glow); font-weight: 800;">Rs. <?php echo number_format($product['price'], 2); ?></span>
                        </div>
                        <div class="price-row" style="justify-content: center;">
                            <button class="btn-primary" onclick="event.stopPropagation(); window.location.href='product_detail.php?id=<?php echo $product['id']; ?>'" style="width: 100%; border-radius: 12px; padding: 0.8rem; font-weight: 600;">Request Service</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Brands Slideshow -->
        <section class="brands-marquee reveal-up">
            <div class="marquee-content">
                <img src="https://upload.wikimedia.org/wikipedia/commons/f/fa/Apple_logo_black.svg" class="brand-logo" alt="Apple">
                <img src="https://upload.wikimedia.org/wikipedia/commons/b/b4/Samsung_wordmark.svg" class="brand-logo" alt="Samsung">
                <svg class="brand-logo" alt="Lenovo" viewBox="0 0 160 45" fill="currentColor"><text x="80" y="35" font-size="42" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">Lenovo</text></svg>
                <img src="https://upload.wikimedia.org/wikipedia/commons/2/2e/ASUS_Logo.svg" class="brand-logo" alt="Asus">
                <img src="https://upload.wikimedia.org/wikipedia/commons/0/00/Acer_2011.svg" class="brand-logo" alt="Acer">
                <svg class="brand-logo" alt="HP" viewBox="0 0 80 45" fill="currentColor"><text x="40" y="35" font-size="46" font-family="Arial, sans-serif" font-weight="900" font-style="italic" text-anchor="middle">hp</text></svg>
                <img src="https://upload.wikimedia.org/wikipedia/commons/0/0e/Intel_logo_%282020%2C_light_blue%29.svg" class="brand-logo" alt="Intel">
                <img src="https://upload.wikimedia.org/wikipedia/commons/8/8d/Canon_logo.svg" class="brand-logo" alt="Canon">
                <img src="https://upload.wikimedia.org/wikipedia/commons/5/59/Epson_logo.svg" class="brand-logo" alt="Epson">
                <svg class="brand-logo" alt="SanDisk" viewBox="0 0 170 45" fill="currentColor"><text x="85" y="35" font-size="38" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">SanDisk</text></svg>
                <svg class="brand-logo" alt="WD" viewBox="0 0 80 45" fill="currentColor"><text x="40" y="35" font-size="44" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">WD</text></svg>
                <svg class="brand-logo" alt="Hikvision" viewBox="0 0 210 45" fill="currentColor"><text x="105" y="35" font-size="36" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">HIKVISION</text></svg>
                <svg class="brand-logo" alt="Ezviz" viewBox="0 0 120 45" fill="currentColor"><text x="60" y="35" font-size="38" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">EZVIZ</text></svg>
                <svg class="brand-logo" alt="Gigabyte" viewBox="0 0 200 45" fill="currentColor"><text x="100" y="35" font-size="36" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">GIGABYTE</text></svg>
                <!-- Duplicate for seamless loop -->
                <img src="https://upload.wikimedia.org/wikipedia/commons/f/fa/Apple_logo_black.svg" class="brand-logo" alt="Apple">
                <img src="https://upload.wikimedia.org/wikipedia/commons/b/b4/Samsung_wordmark.svg" class="brand-logo" alt="Samsung">
                <svg class="brand-logo" alt="Lenovo" viewBox="0 0 160 45" fill="currentColor"><text x="80" y="35" font-size="42" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">Lenovo</text></svg>
                <img src="https://upload.wikimedia.org/wikipedia/commons/2/2e/ASUS_Logo.svg" class="brand-logo" alt="Asus">
                <img src="https://upload.wikimedia.org/wikipedia/commons/0/00/Acer_2011.svg" class="brand-logo" alt="Acer">
                <svg class="brand-logo" alt="HP" viewBox="0 0 80 45" fill="currentColor"><text x="40" y="35" font-size="46" font-family="Arial, sans-serif" font-weight="900" font-style="italic" text-anchor="middle">hp</text></svg>
                <img src="https://upload.wikimedia.org/wikipedia/commons/0/0e/Intel_logo_%282020%2C_light_blue%29.svg" class="brand-logo" alt="Intel">
                <img src="https://upload.wikimedia.org/wikipedia/commons/8/8d/Canon_logo.svg" class="brand-logo" alt="Canon">
                <img src="https://upload.wikimedia.org/wikipedia/commons/5/59/Epson_logo.svg" class="brand-logo" alt="Epson">
                <svg class="brand-logo" alt="SanDisk" viewBox="0 0 170 45" fill="currentColor"><text x="85" y="35" font-size="38" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">SanDisk</text></svg>
                <svg class="brand-logo" alt="WD" viewBox="0 0 80 45" fill="currentColor"><text x="40" y="35" font-size="44" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">WD</text></svg>
                <svg class="brand-logo" alt="Hikvision" viewBox="0 0 210 45" fill="currentColor"><text x="105" y="35" font-size="36" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">HIKVISION</text></svg>
                <svg class="brand-logo" alt="Ezviz" viewBox="0 0 120 45" fill="currentColor"><text x="60" y="35" font-size="38" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">EZVIZ</text></svg>
                <svg class="brand-logo" alt="Gigabyte" viewBox="0 0 200 45" fill="currentColor"><text x="100" y="35" font-size="36" font-family="Arial, sans-serif" font-weight="900" text-anchor="middle">GIGABYTE</text></svg>
            </div>
        </section>



        <!-- Trust & Reviews Section -->
        <section class="trust-section reveal-up" id="reviews">
            <div class="review-summary glass-panel">
                <h2>Customers rate Digi Pro X&nbsp;24</h2>

                <?php
                    // Render filled/half/empty stars
                    $fullStars  = floor($avgRating);
                    $halfStar   = ($avgRating - $fullStars) >= 0.3 ? 1 : 0;
                    $emptyStars = 5 - $fullStars - $halfStar;
                ?>
                <div class="rating-large">
                    <?php echo number_format($avgRating, 1); ?>
                    <span class="star">★</span>
                    <span class="review-count">(<?php echo number_format($totalReviews); ?> reviews)</span>
                </div>

                <!-- Star breakdown bars -->
                <div class="star-breakdown">
                    <?php for ($s = 5; $s >= 1; $s--): ?>
                    <div class="star-bar-row">
                        <span class="star-label"><?php echo $s; ?>★</span>
                        <div class="star-bar-track">
                            <div class="star-bar-fill" style="width:<?php echo $starBreakdown[$s]['pct']; ?>%"></div>
                        </div>
                        <span class="star-bar-count"><?php echo $starBreakdown[$s]['count']; ?></span>
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="review-tags">
                    <span>Price</span>
                    <span>Quality</span>
                    <span>Battery</span>
                    <span>Service</span>
                    <span>Sound</span>
                </div>
                <div class="ai-summary-note">📊 Live rating — based on <?php echo number_format($totalReviews); ?> verified customer reviews</div>

                <a href="products" class="banner-btn btn-orange">Browse products &amp; leave a review →</a>
            </div>

            <div class="reviews-marquee">
                <div class="reviews-track">
                    <?php 
                    $displayReviews = $homeReviews;
                    // If no approved reviews yet, use the static ones as fallback
                    if (empty($displayReviews)) {
                        $displayReviews = [
                            ['reviewer_name' => 'Aruna Perera', 'rating' => 5, 'review_text' => 'Best electronics shop in Sri Lanka! High quality products and fast delivery. My Sony headphones are perfect.', 'product_name' => 'Sony Headphones'],
                            ['reviewer_name' => 'Dilini Silva', 'rating' => 5, 'review_text' => 'The Bose headphones are amazing. Worth every rupee. Customer service was also very helpful.', 'product_name' => 'Bose Headphones'],
                            ['reviewer_name' => 'Kasun Abeysekera', 'rating' => 5, 'review_text' => 'Great service and authentic products. Highly recommended for anyone looking for genuine gadgets.', 'product_name' => 'Mechanical Keyboard'],
                            ['reviewer_name' => 'Nuwan Bandara', 'rating' => 5, 'review_text' => 'Ordered a Samsung phone and received it within 2 days. Excellent packaging and safe delivery.', 'product_name' => 'Samsung Phone']
                        ];
                    }
                    
                    // We need a duplicate of the track for seamless marquee loop
                    for ($i = 0; $i < 2; $i++):
                        foreach ($displayReviews as $rev): 
                            $stars = str_repeat('★', $rev['rating']) . str_repeat('☆', 5 - $rev['rating']);
                    ?>
                            <div class="review-box">
                                <div class="review-header">
                                    <span class="review-name"><?php echo htmlspecialchars($rev['reviewer_name']); ?></span>
                                    <span class="review-stars" style="color: #fbbf24;"><?php echo $stars; ?></span>
                                </div>
                                <p class="review-comment">"<?php echo htmlspecialchars($rev['review_text']); ?>"</p>
                                <?php if (!empty($rev['product_name'])): ?>
                                    <span style="font-size: 0.75rem; color: rgba(255,255,255,0.4); display: block; margin-top: 5px;">On: <?php echo htmlspecialchars($rev['product_name']); ?></span>
                                <?php endif; ?>
                            </div>
                    <?php 
                        endforeach; 
                    endfor; 
                    ?>
                </div>
            </div>

            <div class="features-grid">
                <div class="feature-card glass-panel">
                    <div class="feature-icon">💰</div>
                    <h3>Value for Money</h3>
                    <p>Discover unbeatable value for your hard-earned money. We offer high-quality products at prices that won't break the bank.</p>
                </div>
                <div class="feature-card glass-panel">
                    <div class="feature-icon">😊</div>
                    <h3>Customer Satisfaction</h3>
                    <p>We take immense pride in our track record of making all our customers happy. Your satisfaction is our success story.</p>
                </div>
                <div class="feature-card glass-panel">
                    <div class="feature-icon">🛡️</div>
                    <h3>Product Warranty</h3>
                    <p>Rest easy with a minimum 6-month to 1-year warranty on our products. We stand by the quality and durability of what we sell.</p>
                </div>
                <div class="feature-card glass-panel">
                    <div class="feature-icon">🚚</div>
                    <h3>Quick Delivery</h3>
                    <p>Need it ASAP? Count on our speedy delivery service to get your products to you in no time, because we know time matters.</p>
                </div>
            </div>
        </section>

        <!-- Official Delivery & Payment Partners Section -->
        <section class="partners-marquee-section">
            <div class="partners-header">
                <span class="partners-tag">🤝 TRUSTED PARTNERS</span>
                <h3>Official Logistics &amp; Payment Partners</h3>
            </div>
            <div class="partners-slider-wrapper">
                <div class="partners-track">
                    <?php
                    $partnersList = [
                        ['icon' => '🚚', 'name' => 'කුඹියෝ Delivery', 'sub' => 'Islandwide Courier'],
                        ['icon' => '✈️', 'name' => 'DHL Express', 'sub' => 'Global Priority'],
                        ['icon' => '📦', 'name' => 'DOMEX', 'sub' => 'Express Logistics'],
                        ['icon' => '💳', 'name' => 'PayPal', 'sub' => 'Secure Online'],
                        ['icon' => '🔒', 'name' => 'Visa &amp; Mastercard', 'sub' => '256-Bit SSL'],
                        ['icon' => '🏠', 'name' => 'Cash On Delivery', 'sub' => 'Doorstep Payment'],
                        ['icon' => '🚀', 'name' => 'Pronto Lanka', 'sub' => 'Fast Delivery']
                    ];
                    
                    // Loop twice for continuous infinite marquee animation
                    for ($m = 0; $m < 2; $m++):
                        foreach ($partnersList as $partner):
                    ?>
                        <div class="partner-badge">
                            <span class="partner-icon"><?php echo $partner['icon']; ?></span>
                            <div>
                                <span class="partner-name"><?php echo $partner['name']; ?></span>
                                <span class="partner-sub">• <?php echo $partner['sub']; ?></span>
                            </div>
                        </div>
                    <?php 
                        endforeach; 
                    endfor; 
                    ?>
                </div>
            </div>
        </section>

    </main>



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
                        <a href="https://maps.app.goo.gl/Z1kx3yJVm6h6YCfJ9" target="_blank" rel="noopener noreferrer" style="color:inherit; text-decoration:none; transition: color 0.2s;" onmouseover="this.style.color='var(--primary-glow, #3b82f6)'" onmouseout="this.style.color='inherit'">No.161, Wackwella Rd, Galle, Sri Lanka ↗</a>
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
                <li><a href="services.php">Services</a></li>
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
        </div>
    </footer>

    <div class="modal-overlay" id="quickViewModal" onclick="closeQuickView()">
        <div class="modal-card" onclick="event.stopPropagation()">
            <div class="modal-close" onclick="closeQuickView()">✕</div>
            <div class="modal-image" style="display: flex; flex-direction: column; position: relative;">
                <div style="position: relative; width: 100%; height: 300px; border-radius: 16px; overflow: hidden;">
                    <img id="qv-image" src="" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                    
                    <!-- Left and Right Arrows -->
                    <button id="qv-prev-btn" onclick="navigateGallery(-1)" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); background: rgba(15,23,42,0.7); border: 1px solid rgba(255,255,255,0.25); color: #fff; width: 36px; height: 36px; border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 1.2rem; cursor: pointer; transition: 0.2s; z-index: 10; padding: 0; outline: none; line-height: 1;" onmouseover="this.style.background='rgba(59,130,246,0.85)'" onmouseout="this.style.background='rgba(15,23,42,0.7)'">&#10094;</button>
                    <button id="qv-next-btn" onclick="navigateGallery(1)" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: rgba(15,23,42,0.7); border: 1px solid rgba(255,255,255,0.25); color: #fff; width: 36px; height: 36px; border-radius: 50%; display: none; align-items: center; justify-content: center; font-size: 1.2rem; cursor: pointer; transition: 0.2s; z-index: 10; padding: 0; outline: none; line-height: 1;" onmouseover="this.style.background='rgba(59,130,246,0.85)'" onmouseout="this.style.background='rgba(15,23,42,0.7)'">&#10095;</button>
                </div>
                <div id="qv-gallery-container" style="display: none; gap: 0.5rem; margin-top: 0.8rem; overflow-x: auto; padding-bottom: 0.3rem; max-width: 100%;"></div>
                <div id="qv-discount-badge" class="discount-badge" style="left:20px; top:20px; font-size:1rem; padding:8px 20px;"></div>
            </div>
            <div class="modal-info">
                <div id="qv-cat" class="modal-cat"></div>
                <h2 id="qv-title" class="modal-title"></h2>
                <div id="qv-warranty" style="display: none; font-size: 0.75rem; color: #00f2fe; background: rgba(0, 242, 254, 0.1); border: 1px solid rgba(0, 242, 254, 0.3); padding: 5px 12px; border-radius: 20px; align-items: center; gap: 6px; margin: 0.6rem 0; font-weight: 700; width: max-content; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: inset 0 0 4px rgba(0, 242, 254, 0.2), 0 0 8px rgba(0, 242, 254, 0.15);">
                    <span style="font-size: 0.85rem;">🛡️</span> <span id="qv-warranty-text"></span>
                </div>
                <div class="modal-price-row">
                    <div id="qv-price-col" class="price-col">
                        <span id="qv-original" class="modal-original"></span>
                        <span id="qv-price" class="modal-price"></span>
                    </div>
                    <div id="qv-discount-tag" class="modal-discount"></div>
                </div>
                <p id="qv-desc" class="modal-desc"></p>
                <div id="qv-variants" style="margin: 1rem 0; display:flex; flex-direction:column; gap:0.8rem;"></div>
                
                <!-- Quantity Selector -->
                <div style="margin: 1rem 0; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 1rem;">
                    <span style="font-weight: 600; font-size: 0.95rem; color: rgba(255,255,255,0.75);">Quantity</span>
                    <div style="display: flex; align-items: center; gap: 0.3rem; background: rgba(15,23,42,0.8); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 2px;">
                        <button type="button" onclick="changeQvQty(-1)" style="background: none; border: none; color: #fff; width: 28px; height: 28px; font-size: 1.1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; border-radius: 6px;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='none'">-</button>
                        <input type="number" id="qv-qty" value="1" min="1" max="99" style="width: 35px; text-align: center; background: none; border: none; color: #fff; font-family: inherit; font-size: 1rem; font-weight: 600; -moz-appearance: textfield; outline: none;" readonly>
                        <button type="button" onclick="changeQvQty(1)" style="background: none; border: none; color: #fff; width: 28px; height: 28px; font-size: 1.1rem; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; border-radius: 6px;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='none'">+</button>
                    </div>
                </div>

                <div style="margin-top:auto;">
                    <button id="qv-add-btn" class="btn-primary neon-glow" style="width:100%; padding:1.2rem; font-size:1.2rem;">Add to Cart 🛒</button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        let currentGalleryImages = [];
        let currentGalleryIndex = 0;

        function updateGalleryArrows() {
            const prevBtn = document.getElementById('qv-prev-btn');
            const nextBtn = document.getElementById('qv-next-btn');
            if (prevBtn && nextBtn) {
                if (currentGalleryImages.length > 1) {
                    prevBtn.style.display = 'flex';
                    nextBtn.style.display = 'flex';
                } else {
                    prevBtn.style.display = 'none';
                    nextBtn.style.display = 'none';
                }
            }
        }

        function selectGalleryImage(idx) {
            if (idx < 0 || idx >= currentGalleryImages.length) return;
            currentGalleryIndex = idx;
            const newImg = currentGalleryImages[idx];
            document.getElementById('qv-image').src = newImg;
            
            const galContainer = document.getElementById('qv-gallery-container');
            if (galContainer) {
                const thumbnails = galContainer.querySelectorAll('img');
                thumbnails.forEach((thumb, i) => {
                    if (i === idx) {
                        thumb.style.borderColor = 'var(--primary-glow, #3b82f6)';
                    } else {
                        thumb.style.borderColor = 'rgba(255,255,255,0.1)';
                    }
                });
            }
        }

        function navigateGallery(direction) {
            if (currentGalleryImages.length <= 1) return;
            let newIdx = currentGalleryIndex + direction;
            if (newIdx < 0) {
                newIdx = currentGalleryImages.length - 1;
            } else if (newIdx >= currentGalleryImages.length) {
                newIdx = 0;
            }
            selectGalleryImage(newIdx);
        }

        function openQuickView(p) {
            // Reset quantity to 1
            const qvQtyEl = document.getElementById('qv-qty');
            if (qvQtyEl) qvQtyEl.value = 1;

            // Check if image path is remote or local
            let mainImg = p.image;
            currentGalleryImages = [mainImg];
            currentGalleryIndex = 0;
            document.getElementById('qv-image').src = mainImg;
            document.getElementById('qv-cat').innerText = p.cat;
            document.getElementById('qv-title').innerText = p.name;
            document.getElementById('qv-desc').innerText = p.desc;
            document.getElementById('qv-price').innerText = 'Rs. ' + p.price;
            
            const original = document.getElementById('qv-original');
            const discountBadge = document.getElementById('qv-discount-badge');
            const discountTag = document.getElementById('qv-discount-tag');
            
            if(p.discount > 0) {
                original.style.display = 'block';
                original.innerText = 'Rs. ' + p.original;
                discountBadge.style.display = 'block';
                discountBadge.innerText = p.discount + '% OFF';
                discountTag.style.display = 'block';
                discountTag.innerText = '-' + p.discount + '%';
            } else {
                original.style.display = 'none';
                discountBadge.style.display = 'none';
                discountTag.style.display = 'none';
            }

            const qvWarranty = document.getElementById('qv-warranty');
            const qvWarrantyText = document.getElementById('qv-warranty-text');
            if (p.warranty && p.warranty.trim() !== '') {
                qvWarranty.style.display = 'inline-flex';
                qvWarrantyText.innerText = p.warranty;
                
                const wLower = p.warranty.toLowerCase();
                if (wLower.includes('1 year') || wLower.includes('1year')) {
                    qvWarranty.style.color = '#fbbf24';
                    qvWarranty.style.background = 'rgba(251, 191, 36, 0.1)';
                    qvWarranty.style.borderColor = 'rgba(251, 191, 36, 0.35)';
                    qvWarranty.style.boxShadow = 'inset 0 0 4px rgba(251, 191, 36, 0.2), 0 0 8px rgba(251, 191, 36, 0.15)';
                } else if (wLower.includes('2 year') || wLower.includes('3 year') || wLower.includes('5 year')) {
                    qvWarranty.style.color = '#34d399';
                    qvWarranty.style.background = 'rgba(52, 211, 153, 0.1)';
                    qvWarranty.style.borderColor = 'rgba(52, 211, 153, 0.35)';
                    qvWarranty.style.boxShadow = 'inset 0 0 4px rgba(52, 211, 153, 0.2), 0 0 8px rgba(52, 211, 153, 0.15)';
                } else {
                    qvWarranty.style.color = '#00f2fe';
                    qvWarranty.style.background = 'rgba(0, 242, 254, 0.1)';
                    qvWarranty.style.borderColor = 'rgba(0, 242, 254, 0.3)';
                    qvWarranty.style.boxShadow = 'inset 0 0 4px rgba(0, 242, 254, 0.2), 0 0 8px rgba(0, 242, 254, 0.15)';
                }
            } else {
                qvWarranty.style.display = 'none';
            }

            const addBtn = document.getElementById('qv-add-btn');
            if (parseInt(p.stock) > 0) {
                addBtn.disabled = false;
                addBtn.innerText = 'Add to Cart 🛒';
                addBtn.style.background = '';
                addBtn.style.color = '';
                addBtn.style.border = '';
                addBtn.style.opacity = '1';
                addBtn.style.cursor = 'pointer';
            } else {
                addBtn.disabled = true;
                addBtn.innerText = 'Out of Stock';
                addBtn.style.background = 'rgba(239, 68, 68, 0.15)';
                addBtn.style.color = '#f87171';
                addBtn.style.border = '1px solid rgba(239, 68, 68, 0.3)';
                addBtn.style.opacity = '0.8';
                addBtn.style.cursor = 'not-allowed';
            }

            // Load variants and gallery dynamically
            const varContainer = document.getElementById('qv-variants');
            varContainer.innerHTML = '';
            
            const galContainer = document.getElementById('qv-gallery-container');
            galContainer.innerHTML = '';
            galContainer.style.display = 'none';
            
            fetch(`fetch_variants.php?product_id=${p.id}`)
                .then(res => res.json())
                .then(data => {
                    // Render gallery images if any
                    if (data.success && data.gallery && data.gallery.length > 0) {
                        galContainer.style.display = 'flex';
                        
                        // Add main image as first thumbnail
                        const mainThumb = document.createElement('img');
                        mainThumb.src = mainImg;
                        mainThumb.style.width = '60px';
                        mainThumb.style.height = '60px';
                        mainThumb.style.objectFit = 'cover';
                        mainThumb.style.borderRadius = '8px';
                        mainThumb.style.cursor = 'pointer';
                        mainThumb.style.border = '2px solid var(--primary-glow, #3b82f6)';
                        mainThumb.style.transition = '0.2s';
                        
                        mainThumb.onclick = () => {
                            selectGalleryImage(0);
                        };
                        galContainer.appendChild(mainThumb);
                        
                        data.gallery.forEach(g => {
                            const thumb = document.createElement('img');
                            let gPath = g.image_path;
                            thumb.src = gPath;
                            thumb.style.width = '60px';
                            thumb.style.height = '60px';
                            thumb.style.objectFit = 'cover';
                            thumb.style.borderRadius = '8px';
                            thumb.style.cursor = 'pointer';
                            thumb.style.border = '2px solid rgba(255,255,255,0.1)';
                            thumb.style.transition = '0.2s';
                            
                            currentGalleryImages.push(gPath);
                            const currentIdx = currentGalleryImages.length - 1;
                            
                            thumb.onclick = () => {
                                selectGalleryImage(currentIdx);
                            };
                            galContainer.appendChild(thumb);
                        });
                        
                        updateGalleryArrows();
                    } else {
                        updateGalleryArrows();
                    }

                    if (data.success && data.variants && data.variants.length > 0) {
                        const grouped = {};
                        data.variants.forEach(v => {
                            if (!grouped[v.variant_type]) grouped[v.variant_type] = [];
                            grouped[v.variant_type].push(v);
                        });
                        
                        const baseOriginalPrice = parseFloat(p.original.replace(/,/g, ''));
                        const basePrice = parseFloat(p.price.replace(/,/g, ''));
                        const selectedVariants = {};
                        
                        Object.keys(grouped).forEach(type => {
                            const groupDiv = document.createElement('div');
                            groupDiv.style.display = 'flex';
                            groupDiv.style.flexDirection = 'column';
                            groupDiv.style.gap = '0.3rem';
                            
                            const label = document.createElement('label');
                            label.innerText = `Select ${type}`;
                            label.style.fontWeight = '600';
                            label.style.fontSize = '0.85rem';
                            label.style.color = 'rgba(255,255,255,0.6)';
                            
                            const select = document.createElement('select');
                            select.className = 'form-input';
                            select.style.padding = '0.5rem 0.8rem';
                            select.style.background = 'rgba(15,23,42,0.8)';
                            select.style.border = '1px solid rgba(255,255,255,0.1)';
                            select.style.borderRadius = '8px';
                            select.style.color = '#fff';
                            
                            const defOpt = document.createElement('option');
                            defOpt.value = '';
                            defOpt.innerText = `-- Choose ${type} --`;
                            select.appendChild(defOpt);
                            
                            grouped[type].forEach(v => {
                                const opt = document.createElement('option');
                                opt.value = v.id;
                                const modLkr = parseFloat(v.price_modifier);
                                let modText = '';
                                if (modLkr > 0) modText = ` (+Rs. ${modLkr.toFixed(2)})`;
                                else if (modLkr < 0) modText = ` (-Rs. ${Math.abs(modLkr).toFixed(2)})`;
                                opt.innerText = `${v.variant_value}${modText}`;
                                select.appendChild(opt);
                            });
                            
                            select.onchange = () => {
                                if (select.value) {
                                    selectedVariants[type] = grouped[type].find(v => v.id == select.value);
                                } else {
                                    delete selectedVariants[type];
                                }
                                
                                // Update photo if variant has one
                                let newImg = mainImg;
                                Object.values(selectedVariants).forEach(v => {
                                    if (v && v.image) {
                                        newImg = v.image;
                                    }
                                });
                                document.getElementById('qv-image').src = newImg;
                                
                                // Recalculate price
                                let modifierSum = 0;
                                Object.values(selectedVariants).forEach(v => {
                                    modifierSum += parseFloat(v.price_modifier);
                                });
                                
                                const finalOrig = baseOriginalPrice + modifierSum;
                                let finalPrice = basePrice + modifierSum;
                                if (p.discount > 0) {
                                    finalPrice = finalOrig * (1 - (p.discount / 100));
                                }
                                
                                original.innerText = 'Rs. ' + finalOrig.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                document.getElementById('qv-price').innerText = 'Rs. ' + finalPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            };
                            
                            groupDiv.appendChild(label);
                            groupDiv.appendChild(select);
                            varContainer.appendChild(groupDiv);
                        });
                    }
                });
            
            document.getElementById('qv-add-btn').onclick = () => {
                const qtyVal = parseInt(document.getElementById('qv-qty').value) || 1;
                addToCart(p.id, qtyVal);
                closeQuickView();
            };
            
            document.getElementById('quickViewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function changeQvQty(change) {
            const qtyInput = document.getElementById('qv-qty');
            if (qtyInput) {
                let val = parseInt(qtyInput.value) + change;
                if (val < 1) val = 1;
                if (val > 99) val = 99;
                qtyInput.value = val;
            }
        }
        
        function closeQuickView() {
            document.getElementById('quickViewModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Ensure the addToCart function is accessible
        function addToCart(id, qty = 1) {
            fetch('cart_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=add&product_id=${id}&qty=${qty}`
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    if (window.openCartDrawer) {
                        window.openCartDrawer();
                    } else {
                        document.querySelectorAll('.cart-count').forEach(el => el.innerText = data.cart_count);
                    }
                }
            });
        }

        // ── Immediate Auto-Working Banner Carousel JS Controller ──
        function initBannerCarousel() {
            const sliderBox = document.getElementById('std-banner-slider');
            if (!sliderBox) return;

            const slides = sliderBox.querySelectorAll('.banner-slide');
            const dots = document.querySelectorAll('.banner-dot-item');
            const prevBtn = document.getElementById('std-banner-prev');
            const nextBtn = document.getElementById('std-banner-next');

            if (!slides || slides.length === 0) return;

            let currentIndex = 0;
            const totalSlides = slides.length;
            const autoInterval = 3000; // 3 seconds auto-rotation
            let timer = null;

            function gotoSlide(index) {
                if (index < 0) index = totalSlides - 1;
                if (index >= totalSlides) index = 0;
                currentIndex = index;

                slides.forEach((slide, i) => {
                    if (i === currentIndex) {
                        slide.classList.add('active');
                    } else {
                        slide.classList.remove('active');
                    }
                });

                dots.forEach((dot, i) => {
                    if (i === currentIndex) {
                        dot.classList.add('active');
                    } else {
                        dot.classList.remove('active');
                    }
                });
            }

            function startAutoPlay() {
                stopAutoPlay();
                timer = setInterval(() => {
                    gotoSlide(currentIndex + 1);
                }, autoInterval);
            }

            function stopAutoPlay() {
                if (timer) {
                    clearInterval(timer);
                    timer = null;
                }
            }

            if (prevBtn) {
                prevBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    gotoSlide(currentIndex - 1);
                    startAutoPlay();
                });
            }

            if (nextBtn) {
                nextBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    gotoSlide(currentIndex + 1);
                    startAutoPlay();
                });
            }

            dots.forEach((dot, i) => {
                dot.addEventListener('click', (e) => {
                    e.preventDefault();
                    gotoSlide(i);
                    startAutoPlay();
                });
            });

            // Touch Swipe Support for Mobile
            let startX = 0;
            let endX = 0;
            sliderBox.addEventListener('touchstart', e => {
                startX = e.changedTouches[0].screenX;
            }, { passive: true });

            sliderBox.addEventListener('touchend', e => {
                endX = e.changedTouches[0].screenX;
                if (startX - endX > 40) {
                    gotoSlide(currentIndex + 1);
                    startAutoPlay();
                } else if (endX - startX > 40) {
                    gotoSlide(currentIndex - 1);
                    startAutoPlay();
                }
            }, { passive: true });

            sliderBox.addEventListener('mouseenter', stopAutoPlay);
            sliderBox.addEventListener('mouseleave', startAutoPlay);

            // Start auto rotation immediately
            gotoSlide(0);
            startAutoPlay();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initBannerCarousel);
        } else {
            initBannerCarousel();
        }
    </script>
    <!-- Premium Scroll Animation -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const observerOptions = {
            threshold: 0.05,
            rootMargin: "0px 0px -30px 0px"
        };
        const observer = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    obs.unobserve(entry.target);
                }
            });
        }, observerOptions);
        document.querySelectorAll('.reveal-up').forEach(el => observer.observe(el));
    });
    </script>
</body>
</html>
