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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="https://digiprox24.com/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return &amp; Refund Policy | DigiPro X24 - Sri Lanka</title>
    <meta name="description" content="DigiPro X24 Return & Refund Policy — Learn our return process, refund timeline, and how to exchange or report damaged items.">
    <link rel="canonical" href="https://digiprox24.com/return-policy.php">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://digiprox24.com/return-policy.php">
    <meta property="og:title" content="Return &amp; Refund Policy | DigiPro X24 - Sri Lanka">
    <meta property="og:description" content="DigiPro X24 Return & Refund Policy — Learn our return process, refund timeline, and how to exchange or report damaged items.">
    <meta property="og:image" content="https://digiprox24.com/logo.png">
    <meta property="og:site_name" content="DigiPro X24">
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://digiprox24.com/return-policy.php">
    <meta property="twitter:title" content="Return &amp; Refund Policy | DigiPro X24 - Sri Lanka">
    <meta property="twitter:description" content="DigiPro X24 Return & Refund Policy — Learn our return process, refund timeline, and how to exchange or report damaged items.">
    <meta property="twitter:image" content="https://digiprox24.com/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        .policy-page {
            padding: 120px 5% 5rem;
            min-height: 80vh;
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .policy-hero {
            text-align: center;
            margin-bottom: 3.5rem;
        }

        .policy-hero h1 {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 1rem;
        }

        .policy-hero h1 span {
            background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .policy-hero p {
            color: var(--text-muted);
            font-size: 1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .policy-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 94, 0, 0.08);
            border: 1px solid rgba(255, 94, 0, 0.2);
            color: var(--primary-glow);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .policy-body {
            padding: 2.5rem 3rem;
            border-radius: 24px;
            line-height: 1.8;
        }

        .policy-body h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-main);
            margin: 2.2rem 0 0.8rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(255, 94, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .policy-body h2:first-child {
            margin-top: 0;
        }

        .policy-body p {
            color: var(--text-muted);
            font-size: 0.97rem;
            margin-bottom: 1rem;
        }

        .policy-body ul {
            color: var(--text-muted);
            font-size: 0.97rem;
            padding-left: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .policy-body ul li::marker {
            color: var(--primary-glow);
        }

        .policy-highlight-box {
            background: rgba(255, 94, 0, 0.05);
            border-left: 4px solid var(--primary-glow);
            border-radius: 0 12px 12px 0;
            padding: 1.2rem 1.5rem;
            margin: 1.2rem 0;
        }

        .policy-highlight-box p {
            margin: 0;
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        .policy-contact-box {
            background: rgba(255, 94, 0, 0.06);
            border: 1px solid rgba(255, 94, 0, 0.15);
            border-radius: 14px;
            padding: 1.5rem 2rem;
            margin-top: 2rem;
        }

        .policy-contact-box p {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .policy-contact-box a {
            color: var(--primary-glow);
            text-decoration: none;
            font-weight: 600;
        }

        .policy-contact-box a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .policy-page {
                padding: 110px 1.5rem 6rem;
            }
            .policy-hero {
                margin-bottom: 2.5rem;
            }
            .policy-hero h1 { 
                font-size: 2.2rem; 
            }
            .policy-hero p {
                font-size: 0.95rem;
                line-height: 1.7;
                padding: 0 0.5rem;
            }
            .policy-body { 
                padding: 2rem 1.5rem; 
                border-radius: 20px;
            }
            .policy-body h2 {
                font-size: 1.25rem;
                margin-top: 1.5rem;
            }
            .policy-body p, .policy-body ul, .policy-body ol {
                font-size: 0.95rem;
                line-height: 1.7;
            }
            .policy-contact-box {
                padding: 1.5rem;
                margin-top: 1.5rem;
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

    <main class="policy-page">
        <section class="policy-hero">
            <div class="policy-badge">🔄 Refund & Return Policy</div>
            <h1>Exchange & <span>Refunds</span></h1>
            <p>Last updated: July 2026 &mdash; Please review our guidelines for product exchanges and warranty returns.</p>
        </section>

        <div class="policy-body glass-panel" style="padding: 3rem; border-radius: 24px; line-height: 1.8;">
            <p>At <strong>digiprox24.com</strong>, we want you to be completely satisfied with your purchase. If for any reason you are not happy with your items, we offer an exchange policy to ensure you find the perfect piece. Please review the following guidelines for exchanges:</p>

            <h2 style="color: var(--primary-glow); font-size: 1.4rem; border-bottom: 1px solid rgba(255,94,0,0.15); padding-bottom: 0.5rem; margin-top:2rem;">✅ Reasons Accepted for Exchanges</h2>
            <ul style="padding-left:1.5rem; color:var(--text-muted); margin-bottom:1.5rem; list-style:square;">
                <li>Wrong item sent</li>
                <li>Software damages / manufacturing defect items</li>
            </ul>

            <h2 style="color: var(--primary-glow); font-size: 1.4rem; border-bottom: 1px solid rgba(255,94,0,0.15); padding-bottom: 0.5rem; margin-top:2rem;">💰 Refund & Return Conditions</h2>
            <ul style="padding-left:1.5rem; color:var(--text-muted); margin-bottom:1.5rem; list-style:decimal;">
                <li><strong>Bill Requirement:</strong> You must have the bill to get a warranty or refund. It is mandatory.</li>
                <li><strong>Order Accuracy:</strong> In cases where there is no mistake on our part, vans/delivery charges will never be transferred/refunded. So find out exactly and place orders.</li>
                <li><strong>Physical Damage:</strong> If the goods are physically damaged, no refund or exchange will be given.</li>
                <li><strong>Reporting Period:</strong> In case of any mistake, only calls made within 5 days or maximum 7 days after the delivery of the goods will be considered for exchange.</li>
                <li><strong>No Money Refunds Policy:</strong> After the goods are sold, at the time of return of the goods, "No money refunds". But, in case of any other omission or mistake on our part, we are sensitive to money refund. Also, if we fail to provide you with a solution from the side of our company for warranty or return, we will be sensitive about giving a full refund.</li>
            </ul>

            <div style="margin-top: 3rem; border-top: 1px dashed rgba(255,94,0,0.2); padding-top: 1.5rem; text-align: center;">
                <blockquote style="font-size: 1.2rem; font-style: italic; color: var(--secondary-glow); font-weight: 700;">
                    "Rich price will make a rich life"
                </blockquote>
                <p style="margin-top: 0.5rem; font-size: 0.9rem; color: var(--text-muted);">&mdash; Thank you for choosing digiprox24.com</p>
            </div>
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
    <script src="assets/js/main.js?v=9"></script>
</body>
</html>
