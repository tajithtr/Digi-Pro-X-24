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

$submitted = false;
$error = '';

if (isset($_SESSION['contact_success'])) {
    $submitted = true;
    unset($_SESSION['contact_success']);
}

if (isset($_SESSION['contact_error'])) {
    $error = $_SESSION['contact_error'];
    unset($_SESSION['contact_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate Limiting
    $rate_limit_key = 'contact_rate_limit_' . str_replace('.', '_', $_SERVER['REMOTE_ADDR']);
    $current_time = time();
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = [];
    }
    $_SESSION[$rate_limit_key] = array_filter($_SESSION[$rate_limit_key], function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 3600;
    });
    if (count($_SESSION[$rate_limit_key]) >= 3) {
        $_SESSION['contact_error'] = "You have sent too many messages. Please try again later.";
        header("Location: contact.php");
        exit;
    }
    $_SESSION[$rate_limit_key][] = $current_time;

    // CSRF Check
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['contact_error'] = "Invalid CSRF token. Please try again.";
        header("Location: contact.php");
        exit;
    }

    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    if ($name !== '' && $email !== '' && $message !== '') {
        $dbSaved = false;
        try {
            $stmtMsg = $pdo->prepare("INSERT INTO contact_messages (name, email, message, status) VALUES (?, ?, ?, 'unread')");
            $stmtMsg->execute([$name, $email, $message]);
            $dbSaved = true;
        } catch (PDOException $e) {
            $dbSaved = false;
        }

        $to = "digipro24@gmail.com";
        $subject = "New Contact Inquiry from " . $name;
        
        $email_body = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>New Inquiry</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f6f9fc; font-family: Arial, sans-serif;'>
    <table width='100%' border='0' cellpadding='0' cellspacing='0' style='background-color: #f6f9fc; padding: 40px 0;'>
        <tr>
            <td align='center'>
                <table width='550' border='0' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-top: 6px solid #3b82f6;'>
                    <!-- Header -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #ffffff; border-bottom: 1px solid #f1f5f9; text-align: center;'>
                            <div style='font-size: 24px; font-weight: bold; color: #0f172a;'>
                                Digi Pro <span style='color: #ff5e00;'>Support</span>
                            </div>
                            <div style='font-size: 12px; color: #64748b; font-weight: bold; letter-spacing: 2px; margin-top: 4px; text-transform: uppercase;'>NEW INQUIRY RECEIVED</div>
                        </td>
                    </tr>
                    <!-- Body -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #ffffff;'>
                            <p style='font-size: 16px; color: #1e293b; line-height: 1.6; margin-top: 0;'>Hello Admin,</p>
                            <p style='font-size: 14px; color: #475569; line-height: 1.6;'>You have received a new message from the contact form on Digi Pro X 24:</p>
                            
                            <!-- Sender Info Card -->
                            <table width='100%' border='0' cellpadding='0' cellspacing='0' style='background-color: #f8fafc; border: 6px solid var(--primary-glow); border-radius: 8px; padding: 20px; margin: 20px 0;'>
                                <tr>
                                    <td style='font-size: 14px; color: #64748b; line-height: 1.8;'>
                                        <strong style='color: #0f172a;'>Sender Name:</strong> " . htmlspecialchars($name) . "<br>
                                        <strong style='color: #0f172a;'>Email Address:</strong> <a href='mailto:" . htmlspecialchars($email) . "' style='color: #3b82f6; text-decoration: none;'>" . htmlspecialchars($email) . "</a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Message Card -->
                            <div style='border-left: 4px solid #3b82f6; background-color: #f8fafc; padding: 15px 20px; margin: 20px 0; border-radius: 0 12px 12px 0;'>
                                <strong style='color: #0f172a; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;'>Message:</strong>
                                <p style='font-size: 14px; color: #334155; line-height: 1.6; font-style: italic; margin-top: 8px; margin-bottom: 0;'>\"" . nl2br(htmlspecialchars($message)) . "\"</p>
                            </div>
                            
                            <!-- Reply Button -->
                            <div style='text-align: center; margin: 30px 0 10px 0;'>
                                <a href='mailto:" . htmlspecialchars($email) . "?subject=RE: " . urlencode($subject) . "' style='background: linear-gradient(135deg, #3b82f6, #ff5e00); color: #ffffff; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 14px; display: inline-block; box-shadow: 0 4px 10px rgba(59,130,246,0.25);'>Reply to " . htmlspecialchars($name) . "</a>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td style='padding: 30px 40px; background-color: #0f172a; color: #94a3b8; font-size: 12px; text-align: center; line-height: 1.6;'>
                            <strong>Digi Pro X 24 Support Portal</strong><br>
                            This is an automated notification. To reply, click the button above to email the customer directly.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
        
        $headers_arr = [
            'Reply-To' => $email,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/html; charset=UTF-8'
        ];
        
        $emailSent = @send_smtp_email($to, $subject, $email_body, $headers_arr);
        
        if ($dbSaved || $emailSent) {
            $_SESSION['contact_success'] = true;
            header("Location: contact.php");
            exit;
        } else {
            $_SESSION['contact_error'] = "Failed to send your message. Please try again later.";
            header("Location: contact.php");
            exit;
        }
    } else {
        $_SESSION['contact_error'] = "Please fill in all required fields.";
        header("Location: contact.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="https://digiprox24.com/logo.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | DigiPro X24 - Computer Shop & Services Galle, Sri Lanka</title>
    <meta name="description" content="Contact DigiPro X24 in Galle, Sri Lanka for computer sales, laptop repair, CCTV installation inquiries and customer support.">
    <link rel="canonical" href="https://digiprox24.com/contact.php">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://digiprox24.com/contact.php">
    <meta property="og:title" content="Contact Us | DigiPro X24 - Computer Shop & Services Galle, Sri Lanka">
    <meta property="og:description" content="Contact DigiPro X24 in Galle, Sri Lanka for computer sales, laptop repair, CCTV installation inquiries and customer support.">
    <meta property="og:image" content="https://digiprox24.com/logo.png">
    <meta property="og:site_name" content="DigiPro X24">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://digiprox24.com/contact.php">
    <meta property="twitter:title" content="Contact Us | DigiPro X24 - Computer Shop & Services Galle, Sri Lanka">
    <meta property="twitter:description" content="Contact DigiPro X24 in Galle, Sri Lanka for computer sales, laptop repair, CCTV installation inquiries and customer support.">
    <meta property="twitter:image" content="https://digiprox24.com/logo.png">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ContactPage",
      "name": "Contact DigiPro X24",
      "url": "https://digiprox24.com/contact.php",
      "description": "Customer support and store location details for DigiPro X24 Sri Lanka."
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
      "url": "https://digiprox24.com/",
      "telephone": "+94706756006",
      "email": "digipro24@gmail.com",
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
        .contact-page {
            padding: 120px 5% 5rem;
            min-height: 80vh;
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .contact-hero {
            text-align: center;
            margin-bottom: 4rem;
        }

        .contact-hero h1 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 1rem;
        }

        .contact-hero h1 span {
            color: var(--primary-glow);
            background: linear-gradient(135deg, var(--primary-glow), var(--secondary-glow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .contact-hero p {
            color: var(--text-muted);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.7;
        }

        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 3rem;
            align-items: start;
        }

        .info-panel h2 {
            font-size: 1.8rem;
            margin-bottom: 2rem;
            color: var(--text-main);
            font-weight: 800;
        }

        .info-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        .info-item {
            display: flex;
            gap: 1.25rem;
            align-items: flex-start;
        }

        .info-icon {
            font-size: 1.5rem;
            background: rgba(255, 94, 0, 0.1);
            color: var(--primary-glow);
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1.5px solid rgba(255, 94, 0, 0.35);
        }

        .info-text h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            color: var(--text-main);
        }

        .info-text p {
            color: var(--text-muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .form-panel {
            padding: 2.5rem;
            border-radius: 20px;
        }

        .form-panel h2 {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 1.75rem;
            color: var(--text-main);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .form-input {
            width: 100%;
            padding: 0.8rem 1rem;
            border-radius: 12px;
            border: 1.5px solid rgba(255, 94, 0, 0.3);
            background: rgba(255, 255, 255, 0.08);
            font-family: inherit;
            color: var(--text-main);
            outline: none;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .form-input:focus {
            border-color: var(--primary-glow);
            box-shadow: 0 0 12px rgba(255, 94, 0, 0.2);
            background: rgba(255, 255, 255, 0.12);
        }

        textarea.form-input {
            min-height: 140px;
            resize: vertical;
        }

        .map-container {
            margin-top: 3rem;
            padding: 1.25rem;
            border-radius: 20px;
        }

        .map-container h2 {
            font-size: 1.6rem;
            margin-bottom: 1.25rem;
            color: var(--text-main);
            text-align: center;
            font-weight: 800;
        }

        .map-container iframe {
            border: 1.5px solid rgba(255, 94, 0, 0.35);
            border-radius: 12px;
        }

        .success-alert {
            background: rgba(16, 185, 129, 0.12);
            border: 1.5px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        /* Tablet */
        @media (max-width: 1024px) and (min-width: 769px) {
            .contact-container {
                gap: 2rem;
            }
        }

        /* Mobile */
        @media (max-width: 768px) {
            .contact-page {
                padding: 90px 4% 6rem;
            }
            .contact-hero {
                margin-bottom: 2rem;
            }
            .contact-hero h1 {
                font-size: 2rem;
            }
            .contact-hero p {
                font-size: 0.95rem;
                padding: 0 0.25rem;
            }
            .contact-container {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            .info-panel {
                padding: 1.5rem;
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid rgba(255, 94, 0, 0.12);
            }
            .info-panel h2 {
                font-size: 1.5rem;
                margin-bottom: 1.5rem;
            }
            .info-list {
                gap: 1.4rem;
            }
            .info-icon {
                width: 42px;
                height: 42px;
                font-size: 1.3rem;
                border-radius: 10px;
            }
            .info-text h3 {
                font-size: 0.95rem;
            }
            .form-panel {
                padding: 1.5rem;
                border-radius: 16px;
            }
            .form-panel h2 {
                font-size: 1.5rem;
                margin-bottom: 1.25rem;
            }
            .map-container {
                margin-top: 2rem;
                padding: 1rem;
            }
            .map-container h2 {
                font-size: 1.35rem;
            }
            .map-container iframe {
                height: 280px !important;
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
                <li><a href="contact.php" class="active">Contact</a></li>
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

    <main class="contact-page">
        <section class="contact-hero">
            <h1>Get in <span>Touch</span></h1>
            <p>Have questions about a product or need help with an order? Send us a message or reach out directly.</p>
        </section>

        <div class="contact-container">
            <div class="info-panel">
                <h2>Contact Information</h2>
                <ul class="info-list">
                    <li class="info-item">
                        <div class="info-icon">📍</div>
                        <div class="info-text">
                            <h3>Store Location</h3>
                            <p><a href="https://maps.app.goo.gl/Z1kx3yJVm6h6YCfJ9" target="_blank" rel="noopener noreferrer" style="color:var(--primary-glow); font-weight:600; text-decoration:underline; font-size: 1.05rem;">No.161, Wackwella Rd, Galle, Sri Lanka ↗</a></p>
                        </div>
                    </li>
                    <li class="info-item">
                        <div class="info-icon">📞</div>
                        <div class="info-text">
                            <h3>Call Us</h3>
                            <p>070 6756006<br>Mon - Sat: 9 AM - 6 PM</p>
                        </div>
                    </li>
                    <li class="info-item">
                        <div class="info-icon">✉️</div>
                        <div class="info-text">
                            <h3>Email Us</h3>
                            <p>digipro24@gmail.com<br>We reply within 24 hours</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="form-panel glass-panel">
                <h2>Send Message</h2>
                <?php if ($submitted): ?>
                    <div class="success-alert" style="background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1.5px solid rgba(16, 185, 129, 0.4); padding: 1.2rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.8rem; box-shadow: 0 4px 20px rgba(16, 185, 129, 0.2);">
                        <span style="font-size: 1.5rem;">✨</span>
                        <div>
                            <strong style="display: block; font-size: 1rem; color: #ffffff; margin-bottom: 0.2rem;">Message Sent Successfully!</strong>
                            <span style="font-size: 0.9rem; color: rgba(255,255,255,0.85);">Thank you for contacting Digi Pro X 24. Our support team has received your message and will respond shortly.</span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                    <div class="error-alert" style="background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1.5px solid rgba(239, 68, 68, 0.4); padding: 1.2rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.8rem; box-shadow: 0 4px 20px rgba(239, 68, 68, 0.2);">
                        <span style="font-size: 1.5rem;">⚠️</span>
                        <div>
                            <strong style="display: block; font-size: 1rem; color: #ffffff; margin-bottom: 0.2rem;">Unable to Send Message</strong>
                            <span style="font-size: 0.9rem; color: rgba(255,255,255,0.85);"><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
                <form action="contact.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                    <div class="form-group">
                        <label>Your Name</label>
                        <input type="text" name="name" class="form-input" placeholder="John Doe" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-input" placeholder="john@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Your Message</label>
                        <textarea name="message" class="form-input" placeholder="Type your query here..." required></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem;">Send Message</button>
                </form>
            </div>
        </div>

        <div class="map-container glass-panel">
            <h2>Find Us on the Map</h2>
            <iframe 
                src="https://maps.google.com/maps?q=6.04025,80.215194&t=&z=17&ie=UTF8&iwloc=&output=embed" 
                width="100%" 
                height="420" 
                style="display: block;" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
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
                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius: 8px; padding:2px; display:block;">
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
                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius: 8px; padding:2px; display:block;">
                        <rect width="38" height="24" fill="#ffffff"/>
                        <circle cx="19" cy="12" r="9" fill="#26a17b"/>
                        <path d="M19 7c-2.4 0-4.3 0.3-4.3 0.8s1.9 0.8 4.3 0.8 4.3-0.3 4.3-0.8S21.4 7 19 7zm0.5 1.7H22v0.8h-2.5v4.5h-1v-4.5H16v-0.8h2.5v-0.1h1v0.1z" fill="#ffffff"/>
                    </svg>
                </div>
                <!-- PayPal -->
                <div class="payment-card" title="PayPal">
                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius: 8px; padding:2px; display:block;">
                        <rect width="38" height="24" fill="#ffffff"/>
                        <path d="M12.5 4h5.2c1.8 0 3.2.4 4 1.2.7.7 1 1.7.9 3-.1 1.8-.8 3.2-1.9 4.1-1 .9-2.5 1.3-4.4 1.3h-2.1l-1.3 6.4h-3.4l2.6-13c.2-1 .4-1.7.7-2 .3-.3 1-.3 1.7-.3z" fill="#003087"/>
                        <path d="M14.5 6h5.2c1.8 0 3.2.4 4 1.2.7.7 1 1.7.9 3-.1 1.8-.8 3.2-1.9 4.1-1 .9-2.5 1.3-4.4 1.3h-2.1l-1.3 6.4h-3.4l2.6-13c.2-1 .4-1.7.7-2 .3-.3 1-.3 1.7-.3z" fill="#0079C1" opacity="0.8"/>
                    </svg>
                </div>
                <!-- Bank Transfer -->
                <div class="payment-card" title="Bank Transfer">
                    <svg viewBox="0 0 38 24" width="38" height="24" xmlns="http://www.w3.org/2000/svg" style="background:#ffffff; border-radius: 8px; padding:2px; display:block;">
                        <rect width="38" height="24" fill="#ffffff"/>
                        <path d="M19 4L6 9v2h26V9L19 4zm-9 9v6h3v-6h-3zm6 0v6h3v-6h-3zm6 0v6h3v-6h-3zm-14 8v1h20v-1H8z" fill="#0f172a"/>
                    </svg>
                </div>
            </div>
            </div>
        </div>
    </footer>
    <script src="assets/js/main.js?v=9"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.pathname);
            }
            const successAlert = document.querySelector('.success-alert');
            if (successAlert) {
                successAlert.style.transition = 'opacity 0.6s ease, transform 0.6s ease, margin 0.6s ease, padding 0.6s ease';
                setTimeout(function() {
                    successAlert.style.opacity = '0';
                    successAlert.style.transform = 'translateY(-12px)';
                    setTimeout(function() {
                        successAlert.style.display = 'none';
                    }, 600);
                }, 4000);
            }
        });
    </script>
</body>
</html>
