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

$user_logged_in = isset($_SESSION['user_id']);
$user_country = strtolower(trim($_SESSION['user_country'] ?? ''));
$is_sri_lanka = ($user_country === 'sri lanka' || $user_country === 'lk' || $user_country === 'srilanka' || $user_country === 'sl');

if (!$user_logged_in || !$is_sri_lanka) {
    header("Location: index.php");
    exit;
}


$catSql = "SELECT * FROM categories";
if (!$user_logged_in || !$is_sri_lanka) {
    $catSql .= " WHERE type != 'service'";
}
$catStmt = $pdo->query($catSql);
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

$selected_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_disabled = 0 AND p.product_type = 'local_service'";
$params = [];

if ($selected_category > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $selected_category;
}

if ($search_query !== '') {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_cat_name = "All Products";
if ($selected_category > 0) {
    foreach($categories as $cat) {
        if($cat['id'] == $selected_category) {
            $current_cat_name = $cat['name'];
            break;
        }
    }
}
if ($search_query !== '') {
    $current_cat_name = "Search Results for '" . htmlspecialchars($search_query) . "'";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="https://digiprox24.com/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Computer Repair & CCTV Installation Services Sri Lanka | DigiPro X24</title>
    <meta name="description" content="Professional computer repair, laptop repair, PC maintenance & CCTV camera installation services in Sri Lanka by expert technicians at DigiPro X24.">
    <link rel="canonical" href="https://digiprox24.com/services.php">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://digiprox24.com/services.php">
    <meta property="og:title" content="Computer Repair & CCTV Installation Services Sri Lanka | DigiPro X24">
    <meta property="og:description" content="Expert laptop & PC repairs, hardware troubleshooting, CCTV camera installation and IT support services in Sri Lanka by DigiPro X24.">
    <meta property="og:image" content="https://digiprox24.com/logo.png">
    <meta property="og:site_name" content="DigiPro X24">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://digiprox24.com/services.php">
    <meta property="twitter:title" content="Computer Repair & CCTV Installation Services Sri Lanka | DigiPro X24">
    <meta property="twitter:description" content="Expert laptop & PC repairs, hardware troubleshooting, CCTV camera installation and IT support services in Sri Lanka by DigiPro X24.">
    <meta property="twitter:image" content="https://digiprox24.com/logo.png">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "serviceType": "Computer Repair and CCTV Installation Services",
      "name": "DigiPro X24 Technical & Security Services",
      "provider": {
        "@type": "LocalBusiness",
        "name": "DigiPro X24",
        "telephone": "+94706756006",
        "email": "digipro24@gmail.com",
        "address": {
          "@type": "PostalAddress",
          "streetAddress": "No.161, Wackwella Rd",
          "addressLocality": "Galle",
          "addressCountry": "LK"
        }
      },
      "areaServed": {
        "@type": "Country",
        "name": "Sri Lanka"
      },
      "description": "Comprehensive IT support, computer repair, laptop diagnostic, PC maintenance, and CCTV security camera installation services in Sri Lanka."
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
          "name": "Services",
          "item": "https://digiprox24.com/services.php"
        }
      ]
    }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .products-page {
            padding: 120px 4% 5rem;
            min-height: 80vh;
            max-width: 1600px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .shop-container {
            padding: 2rem 0;
            gap: 2rem;
        }

        .products-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 3rem;
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 1.5rem;
        }

        .products-header-row h1 {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .products-header-row h1 span {
            color: var(--primary-glow);
        }

        .search-form {
            display: flex;
            gap: 0.5rem;
            width: 100%;
            max-width: 450px;
            margin-left: auto;
        }

        .search-input {
            flex: 1;
            padding: 0.8rem 1.2rem;
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            background: rgba(255, 255, 255, 0.8);
            font-family: inherit;
            color: var(--text-main);
            outline: none;
            transition: all 0.3s;
        }

        .search-input:focus {
            border-color: var(--primary-glow);
            box-shadow: 0 0 12px rgba(255, 94, 0, 0.08);
        }

        .search-btn {
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            border: none;
            background: var(--primary-glow);
            color: white;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .search-btn:hover {
            opacity: 0.9;
        }

        @media (max-width: 1024px) and (min-width: 769px) {
            .products-header-row h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .products-page {
                padding: 90px 4% 6rem !important;
            }
            .products-header-row {
                margin-bottom: 1.5rem;
                gap: 1rem;
            }
            .products-header-row h1 {
                font-size: 1.8rem;
            }
            .search-form {
                max-width: 100%;
                width: 100%;
            }
            .search-input {
                font-size: 0.95rem;
            }
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
                <li><a href="index.php">Home</a></li>
                <li><a href="categories.php">Categories</a></li>
                <li><a href="products.php">Products</a></li>
                <?php $uc = strtolower(trim($_SESSION['user_country'] ?? '')); if (isset($_SESSION['user_id']) && ($uc === 'sri lanka' || $uc === 'lk' || $uc === 'srilanka' || $uc === 'sl')): ?>
                <li><a href="services.php" class="active">Services</a></li>
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
                <a href="login" class="btn-primary" style="text-decoration:none;">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="products-page">
        <div class="products-header-row">
            <h1>Our <span>Services</span></h1>
            <form action="services.php" method="GET" class="search-form">
                <?php if($selected_category > 0): ?>
                    <input type="hidden" name="category" value="<?php echo $selected_category; ?>">
                <?php endif; ?>
                <input type="text" name="search" class="search-input" placeholder="Search services..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit" class="search-btn">Search</button>
            </form>
        </div>

        <div class="shop-container">
                        <aside class="sidebar glass-panel">
                <h3 class="sidebar-title">Our Services</h3>
                <ul class="category-list">
                    <li>
                        <a href="services.php" class="<?php echo $selected_category == 0 ? 'active' : ''; ?>">
                            All Services
                        </a>
                    </li>
                    <?php 
                    $svcStmt = $pdo->query("SELECT * FROM products WHERE product_type = 'local_service' AND is_disabled = 0");
                    $services_list = $svcStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach($services_list as $service): ?>
                    <li>
                        <a href="product_detail.php?id=<?php echo $service['id']; ?>">
                            <?php echo htmlspecialchars($service['name']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </aside>

            <section class="products-content">
                <?php if(count($products) === 0): ?>
                    <div class="glass-panel" style="padding: 4rem; text-align: center; border-radius: 20px;">
                        <span style="font-size: 3rem; display: block; margin-bottom: 1rem;">🔍</span>
                        <h3 style="color: var(--text-main); margin-bottom: 0.5rem;">No Services Found</h3>
                        <p style="color: var(--text-muted);">We couldn't find any services matching your selection. Try checking other categories or query terms.</p>
                        <a href="services.php" class="btn-primary" style="display: inline-block; margin-top: 1.5rem; padding: 0.8rem 2rem;">View All Services</a>
                    </div>
                <?php else: ?>
                    <div class="product-grid services-grid">
                        <?php foreach($products as $product): 
                            $original_price_lkr = $product['price'];
                            $discount_percent = $product['discount_percent'] ?? 0;
                            $current_price_lkr = $original_price_lkr;
                            if ($discount_percent > 0) {
                                $current_price_lkr = $original_price_lkr * (1 - ($discount_percent / 100));
                            }
                        ?>
                        <div class="product-card glass-panel" data-discount="<?php echo $discount_percent; ?>" 
                             onclick="window.location.href='product_detail.php?id=<?php echo $product['id']; ?>'">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars(get_product_image_url($product['image'])); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">

                                <?php if($product['product_type'] === 'local_service'): ?>
                                    <div class="new-badge" style="position: absolute; top: 15px; left: 15px; background: rgba(59, 130, 246, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 6; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.25);">SERVICE</div>
                                <?php else: ?>
                                    <?php if($discount_percent > 0): ?>
                                        <div class="discount-badge" <?php echo (isset($product['is_new_arrival']) && $product['is_new_arrival']) ? 'style="top: 55px;"' : ''; ?>><?php echo $discount_percent; ?>% OFF</div>
                                    <?php endif; ?>
                                    <?php if(isset($product['is_new_arrival']) && $product['is_new_arrival']): ?>
                                        <div class="new-badge" style="position: absolute; top: 15px; left: 15px; background: rgba(16, 185, 129, 0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800; border: 1px solid rgba(255, 255, 255, 0.2); z-index: 6; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.25);">NEW</div>
                                    <?php endif; ?>
                                    <?php if ($product['stock'] > 0): ?>
                                        <div class="stock-badge-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(16, 185, 129, 0.95); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); color: #ffffff; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15); z-index: 5; letter-spacing: 0.5px;">IN STOCK</div>
                                    <?php else: ?>
                                        <div class="stock-badge-overlay" style="position: absolute; bottom: 12px; right: 12px; background: rgba(239, 68, 68, 0.95); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); color: #ffffff; padding: 4px 10px; border-radius: 6px; font-size: 0.72rem; font-weight: 800; border: 1px solid rgba(255,255,255,0.15); z-index: 5; letter-spacing: 0.5px;">OUT OF STOCK</div>
                                    <?php endif; ?>
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
                                <div class="price-row">
                                    <div class="price-col">
                                        <?php if($discount_percent > 0): ?>
                                            <span class="original-price">Rs. <?php echo number_format($original_price_lkr, 2); ?></span>
                                        <?php endif; ?>
                                        <span class="price">Rs. <?php echo number_format($current_price_lkr, 2); ?></span>
                                    </div>
                                    <?php if ($product['product_type'] === 'local_service'): ?>
                                        <button class="btn-primary" onclick="event.stopPropagation(); window.location.href='product_detail.php?id=<?php echo $product['id']; ?>'" style="border-radius: 12px; padding: 0.6rem 1.2rem; font-weight: 600; font-size: 0.9rem;">Request</button>
                                    <?php else: ?>
                                        <?php if ($product['stock'] > 0): ?>
                                            <button class="btn-add-cart" data-id="<?php echo $product['id']; ?>" onclick="event.stopPropagation()">Add 🛒</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
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
                <li><a href="services.php" class="active">Services</a></li>
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

    <!-- Quick View Modal -->
    <div class="modal-overlay" id="quickViewModal" onclick="closeQuickView()">
        <div class="modal-card" onclick="event.stopPropagation()">
            <div class="modal-close" onclick="closeQuickView()">✕</div>
            <div class="modal-image" style="display: flex; flex-direction: column; position: relative;">
                <div style="position: relative; width: 100%; height: 300px; border-radius: 16px; overflow: hidden;">
                    <img id="qv-image" src="" alt="Service Quick View" style="width: 100%; height: 100%; object-fit: cover;">
                    
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

    <script src="assets/js/main.js?v=9"></script>
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
    </script>
</body>
</html>
