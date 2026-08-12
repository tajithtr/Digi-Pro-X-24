<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

$redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? 'index.php');
// Sanitize redirect to prevent open redirect (only allow local alphanumeric + dots + underscores)
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $redirect)) {
    $redirect = 'index.php';
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin') {
        header("Location: admin/index.php");
    } else {
        header("Location: " . $redirect);
    }
    exit;
}

$action = $_GET['action'] ?? ($_GET['tab'] ?? 'login');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh the page and try again.';
        $_POST['form_type'] = 'invalid';
    }
    $form_type = $_POST['form_type'] ?? 'login';
    
    if ($form_type === 'register') {
        $action = 'register';
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $country = trim($_POST['country'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($name) || empty($email) || empty($phone) || empty($country) || empty($password)) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email address already exists. Please Sign In.';
                $action = 'login';
            } else {
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $insert_stmt = $pdo->prepare("INSERT INTO users (name, email, phone, country, password, role) VALUES (?, ?, ?, ?, ?, 'customer')");
                    $insert_stmt->execute([$name, $email, $phone, $country, $hashed_password]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Log user in automatically
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['name'] = $name;
                    $_SESSION['role'] = 'customer';
                    $_SESSION['user_country'] = $country;
                    
                    header("Location: " . $redirect);
                    exit;
                } catch (PDOException $e) {
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
    } else {
        // Form type is Login
        $action = 'login';
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        if ($email !== '' && $password !== '') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $locked = false;
            if ($user && !empty($user['lockout_until'])) {
                $lockout_time = new DateTime($user['lockout_until']);
                $now = new DateTime();
                if ($now < $lockout_time) {
                    $locked = true;
                    $diff = $lockout_time->diff($now);
                    $minutes = $diff->i + ($diff->h * 60);
                    $seconds = $diff->s;
                    $error = "This account is temporarily locked. Please try again in {$minutes}m {$seconds}s.";
                }
            }

            if (!$locked && $user && password_verify($password, $user['password'])) {
                // Clear any lockouts/failed attempts on success
                $reset_stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = ?");
                $reset_stmt->execute([$user['id']]);

                session_regenerate_id(true);
                
                if (isset($user['force_password_change']) && $user['force_password_change'] == 1) {
                    $_SESSION['temp_password_user_id'] = $user['id'];
                    header("Location: change_password.php");
                    exit;
                }
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_country'] = $user['country'];
                
                if ($user['role'] === 'admin' || $user['role'] === 'superadmin') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: " . $redirect);
                }
                exit;
            } elseif (!$locked) {
                if ($user) {
                    $attempts = ($user['failed_attempts'] ?? 0) + 1;
                    if ($attempts >= 5) {
                        $lock_until = date('Y-m-d H:i:s', time() + 900); // 15 mins lockout
                        $lock_stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = ? WHERE id = ?");
                        $lock_stmt->execute([$lock_until, $user['id']]);
                        $error = 'Too many failed login attempts. Your account has been locked for 15 minutes.';
                    } else {
                        $lock_stmt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                        $lock_stmt->execute([$attempts, $user['id']]);
                        $remaining = 5 - $attempts;
                        $error = "Invalid email or password. ({$remaining} attempts remaining)";
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        } else {
            $error = 'Please fill in all fields.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <title><?php echo $action === 'register' ? 'Sign Up' : 'Login'; ?> - Digi Pro X 24</title>
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: radial-gradient(circle at 50% 50%, #0f172a 0%, #020617 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            padding: 2rem 1.5rem;
        }
        
        .bg-orb {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 94, 0, 0.15) 0%, rgba(255, 94, 0, 0) 70%);
            border-radius: 50%;
            top: -100px;
            right: -100px;
            z-index: 1;
            pointer-events: none;
        }
        
        .bg-orb-2 {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0) 75%);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
            z-index: 1;
            pointer-events: none;
        }

        .auth-card {
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1.5px solid rgba(255, 94, 0, 0.35);
            border-radius: 20px;
            padding: 2.5rem 2.2rem;
            width: 100%;
            max-width: 460px;
            z-index: 10;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .auth-logo img {
            height: 46px;
            border-radius: 8px;
            margin-bottom: 0.6rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .auth-logo h2 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #ffffff;
            margin: 0;
        }

        .auth-logo h2 span {
            background: linear-gradient(135deg, #ff5e00, #ffbd00);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-tabs {
            display: flex;
            background: rgba(2, 6, 23, 0.6);
            border: 1px solid rgba(255, 94, 0, 0.2);
            border-radius: 30px;
            padding: 4px;
            margin-bottom: 1.8rem;
        }

        .auth-tab-btn {
            flex: 1;
            padding: 0.7rem 0;
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.95rem;
            font-weight: 700;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .auth-tab-btn.active {
            background: var(--primary-glow, #ff5e00);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(255, 94, 0, 0.4);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.45rem;
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.88rem;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            padding: 0.85rem 1.1rem;
            background: rgba(13, 16, 21, 0.8);
            border: 1.5px solid rgba(255, 94, 0, 0.25);
            border-radius: 10px;
            color: #ffffff;
            font-size: 0.95rem;
            transition: all 0.3s;
            box-sizing: border-box;
            font-family: inherit;
        }

        .form-input:focus {
            outline: none;
            border-color: #ff5e00;
            box-shadow: 0 0 15px rgba(255, 94, 0, 0.35);
            background: rgba(15, 23, 42, 0.9);
        }

        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, #ff5e00, #ff8c00);
            border: none;
            border-radius: 10px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 0.8rem;
            box-shadow: 0 4px 18px rgba(255, 94, 0, 0.4);
            font-family: inherit;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 22px rgba(255, 94, 0, 0.6);
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 0.8rem 1.2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
            font-weight: 600;
        }

        .switch-mode-text {
            text-align: center;
            margin-top: 1.4rem;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        .switch-mode-text a {
            color: #ff5e00;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .switch-mode-text a:hover {
            text-decoration: underline;
        }

        .back-to-site {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.88rem;
            transition: color 0.3s;
        }

        .back-to-site:hover {
            color: #ffffff;
        }
        /* TomSelect Dark Theme */
        .ts-wrapper.single .ts-control { background: rgba(13, 16, 21, 0.8) !important; border: 1.5px solid rgba(255, 94, 0, 0.25) !important; border-radius: 10px !important; color: white !important; padding: 0.85rem 1.1rem !important; box-shadow: none !important; font-family: 'Outfit', sans-serif !important; font-size: 0.95rem !important; height: auto; }
        .ts-wrapper.focus .ts-control { border-color: #ff5e00 !important; box-shadow: 0 0 15px rgba(255, 94, 0, 0.35) !important; background: rgba(15, 23, 42, 0.9) !important; }
        .ts-wrapper .ts-dropdown { background: rgba(15, 23, 42, 0.95) !important; border: 1px solid rgba(255, 94, 0, 0.2) !important; border-radius: 10px !important; color: white !important; font-family: 'Outfit', sans-serif !important; backdrop-filter: blur(15px); box-shadow: 0 10px 40px rgba(0,0,0,0.6) !important; }
        .ts-wrapper .ts-dropdown .option { padding: 10px 15px !important; color: rgba(255,255,255,0.85) !important; }
        .ts-wrapper .ts-dropdown .option.active, .ts-wrapper .ts-dropdown .option:hover { background: rgba(255, 94, 0, 0.15) !important; color: #ff5e00 !important; }
        div.ts-control > input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):hover,
        div.ts-control > input:not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="checkbox"]):not([type="radio"]):focus {
            transform: none !important;
            box-shadow: none !important;
            border-color: transparent !important;
        }
        .ts-control input { color: white !important; }
        .ts-control .item { vertical-align: baseline !important; }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb-2"></div>

    <div class="auth-card">
        <div class="auth-logo">
            <a href="index.php" style="text-decoration: none;">
                <img src="logo.png" alt="Digi Pro X 24">
                <h2>Digi <span>Pro X 24</span></h2>
            </a>
        </div>

        <!-- Auth Tabs Switcher -->
        <div class="auth-tabs">
            <button type="button" id="tab-login" class="auth-tab-btn <?php echo $action !== 'register' ? 'active' : ''; ?>" onclick="switchTab('login')">Sign In</button>
            <button type="button" id="tab-register" class="auth-tab-btn <?php echo $action === 'register' ? 'active' : ''; ?>" onclick="switchTab('register')">Sign Up</button>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Sign In Form -->
        <div id="form-login-box" style="display: <?php echo $action !== 'register' ? 'block' : 'none'; ?>;">
            <form action="login.php?action=login" method="POST">
                <input type="hidden" name="form_type" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-input" placeholder="john@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                        <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
                        <a href="forgot_password.php" style="color: #ff5e00; font-size: 0.82rem; text-decoration: none; font-weight: 600;">Forgot Password?</a>
                    </div>
                    <div style="position: relative;">
                        <input type="password" name="password" id="password" class="form-input" placeholder="••••••••" required style="padding-right: 3rem;">
                        <button type="button" id="togglePassword" style="position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 1.1rem; outline: none;">
                            👁️
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Sign In ➔</button>
            </form>
            <div class="switch-mode-text">
                Don't have an account? <a onclick="switchTab('register')">Sign Up Now</a>
            </div>
        </div>

        <!-- Sign Up / Register Form -->
        <div id="form-register-box" style="display: <?php echo $action === 'register' ? 'block' : 'none'; ?>;">
            <form action="login.php?action=register" method="POST">
                <input type="hidden" name="form_type" value="register">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                <div class="form-group">
                    <label class="form-label" for="reg_name">Full Name</label>
                    <input type="text" name="name" id="reg_name" class="form-input" placeholder="John Doe" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reg_email">Email Address</label>
                    <input type="email" name="email" id="reg_email" class="form-input" placeholder="john@example.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reg_phone">Phone Number</label>
                    <input type="tel" name="phone" id="reg_phone" class="form-input" placeholder="+94 77 123 4567" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reg_country">Country</label>
                    <select name="country" id="reg_country" class="form-input" required>
<option value="" disabled selected>Select your country</option>
<option value="US">United States</option>
<option value="GB">United Kingdom</option>
<option value="AU">Australia</option>
<option value="CA">Canada</option>
<option value="LK">Sri Lanka</option>
<option value="IN">India</option>
<option value="AE">United Arab Emirates</option>
<option value="SG">Singapore</option>
<option value="NZ">New Zealand</option>
<option value="DE">Germany</option>
<option value="FR">France</option>
<option value="IT">Italy</option>
<option value="ES">Spain</option>
<option value="NL">Netherlands</option>
<option value="SE">Sweden</option>
<option value="CH">Switzerland</option>
<option value="JP">Japan</option>
<option value="ZA">South Africa</option>
<option value="MY">Malaysia</option>
<option value="MV">Maldives</option>
<option value="QA">Qatar</option>
<option value="SA">Saudi Arabia</option>
<option value="AF">Afghanistan</option>
<option value="AL">Albania</option>
<option value="DZ">Algeria</option>
<option value="AD">Andorra</option>
<option value="AO">Angola</option>
<option value="AG">Antigua & Barbuda</option>
<option value="AR">Argentina</option>
<option value="AM">Armenia</option>
<option value="AT">Austria</option>
<option value="AZ">Azerbaijan</option>
<option value="BS">Bahamas</option>
<option value="BH">Bahrain</option>
<option value="BD">Bangladesh</option>
<option value="BB">Barbados</option>
<option value="BY">Belarus</option>
<option value="BE">Belgium</option>
<option value="BZ">Belize</option>
<option value="BJ">Benin</option>
<option value="BT">Bhutan</option>
<option value="BO">Bolivia</option>
<option value="BA">Bosnia & Herzegovina</option>
<option value="BW">Botswana</option>
<option value="BR">Brazil</option>
<option value="BN">Brunei</option>
<option value="BG">Bulgaria</option>
<option value="BF">Burkina Faso</option>
<option value="BI">Burundi</option>
<option value="CV">Cape Verde</option>
<option value="KH">Cambodia</option>
<option value="CM">Cameroon</option>
<option value="TD">Chad</option>
<option value="CL">Chile</option>
<option value="CN">China</option>
<option value="CO">Colombia</option>
<option value="KM">Comoros</option>
<option value="CG">Congo - Brazzaville</option>
<option value="CD">Congo - Kinshasa</option>
<option value="CR">Costa Rica</option>
<option value="HR">Croatia</option>
<option value="CU">Cuba</option>
<option value="CY">Cyprus</option>
<option value="CZ">Czechia</option>
<option value="DK">Denmark</option>
<option value="DJ">Djibouti</option>
<option value="DM">Dominica</option>
<option value="DO">Dominican Republic</option>
<option value="EC">Ecuador</option>
<option value="EG">Egypt</option>
<option value="SV">El Salvador</option>
<option value="GQ">Equatorial Guinea</option>
<option value="ER">Eritrea</option>
<option value="EE">Estonia</option>
<option value="SZ">Eswatini</option>
<option value="ET">Ethiopia</option>
<option value="FJ">Fiji</option>
<option value="FI">Finland</option>
<option value="GA">Gabon</option>
<option value="GM">Gambia</option>
<option value="GE">Georgia</option>
<option value="GH">Ghana</option>
<option value="GR">Greece</option>
<option value="GD">Grenada</option>
<option value="GT">Guatemala</option>
<option value="GN">Guinea</option>
<option value="GW">Guinea-Bissau</option>
<option value="GY">Guyana</option>
<option value="HT">Haiti</option>
<option value="HN">Honduras</option>
<option value="HU">Hungary</option>
<option value="IS">Iceland</option>
<option value="ID">Indonesia</option>
<option value="IR">Iran</option>
<option value="IQ">Iraq</option>
<option value="IE">Ireland</option>
<option value="IL">Israel</option>
<option value="JM">Jamaica</option>
<option value="JO">Jordan</option>
<option value="KZ">Kazakhstan</option>
<option value="KE">Kenya</option>
<option value="KI">Kiribati</option>
<option value="KP">North Korea</option>
<option value="KR">South Korea</option>
<option value="KW">Kuwait</option>
<option value="KG">Kyrgyzstan</option>
<option value="LA">Laos</option>
<option value="LV">Latvia</option>
<option value="LB">Lebanon</option>
<option value="LS">Lesotho</option>
<option value="LR">Liberia</option>
<option value="LY">Libya</option>
<option value="LI">Liechtenstein</option>
<option value="LT">Lithuania</option>
<option value="LU">Luxembourg</option>
<option value="MG">Madagascar</option>
<option value="MW">Malawi</option>
<option value="ML">Mali</option>
<option value="MT">Malta</option>
<option value="MH">Marshall Islands</option>
<option value="MR">Mauritania</option>
<option value="MU">Mauritius</option>
<option value="MX">Mexico</option>
<option value="FM">Micronesia</option>
<option value="MD">Moldova</option>
<option value="MC">Monaco</option>
<option value="MN">Mongolia</option>
<option value="ME">Montenegro</option>
<option value="MA">Morocco</option>
<option value="MZ">Mozambique</option>
<option value="MM">Myanmar</option>
<option value="NA">Namibia</option>
<option value="NR">Nauru</option>
<option value="NP">Nepal</option>
<option value="NI">Nicaragua</option>
<option value="NE">Niger</option>
<option value="NG">Nigeria</option>
<option value="MK">North Macedonia</option>
<option value="NO">Norway</option>
<option value="OM">Oman</option>
<option value="PK">Pakistan</option>
<option value="PW">Palau</option>
<option value="PS">Palestine</option>
<option value="PA">Panama</option>
<option value="PG">Papua New Guinea</option>
<option value="PY">Paraguay</option>
<option value="PE">Peru</option>
<option value="PH">Philippines</option>
<option value="PL">Poland</option>
<option value="PT">Portugal</option>
<option value="RO">Romania</option>
<option value="RU">Russia</option>
<option value="RW">Rwanda</option>
<option value="WS">Samoa</option>
<option value="SM">San Marino</option>
<option value="ST">São Tomé & Príncipe</option>
<option value="SN">Senegal</option>
<option value="RS">Serbia</option>
<option value="SC">Seychelles</option>
<option value="SL">Sierra Leone</option>
<option value="SK">Slovakia</option>
<option value="SI">Slovenia</option>
<option value="SB">Solomon Islands</option>
<option value="SO">Somalia</option>
<option value="SS">South Sudan</option>
<option value="SD">Sudan</option>
<option value="SR">Suriname</option>
<option value="SY">Syria</option>
<option value="TW">Taiwan</option>
<option value="TJ">Tajikistan</option>
<option value="TZ">Tanzania</option>
<option value="TH">Thailand</option>
<option value="TG">Togo</option>
<option value="TO">Tonga</option>
<option value="TT">Trinidad & Tobago</option>
<option value="TN">Tunisia</option>
<option value="TR">Turkey</option>
<option value="TM">Turkmenistan</option>
<option value="TV">Tuvalu</option>
<option value="UG">Uganda</option>
<option value="UA">Ukraine</option>
<option value="UY">Uruguay</option>
<option value="UZ">Uzbekistan</option>
<option value="VU">Vanuatu</option>
<option value="VA">Vatican City</option>
<option value="VE">Venezuela</option>
<option value="VN">Vietnam</option>
<option value="YE">Yemen</option>
<option value="ZM">Zambia</option>
<option value="ZW">Zimbabwe</option>
</select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reg_password">Password (Min 6 characters)</label>
                    <div style="position: relative;">
                        <input type="password" name="password" id="reg_password" class="form-input" placeholder="••••••••" required minlength="6" style="padding-right: 3rem;">
                        <button type="button" id="toggleRegPassword" style="position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 1.1rem; outline: none;">
                            👁️
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reg_confirm_password">Confirm Password</label>
                    <div style="position: relative;">
                        <input type="password" name="confirm_password" id="reg_confirm_password" class="form-input" placeholder="••••••••" required minlength="6" style="padding-right: 3rem;">
                        <button type="button" id="toggleConfirmPassword" style="position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: rgba(255,255,255,0.4); cursor: pointer; font-size: 1.1rem; outline: none;">
                            👁️
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Create Account ➔</button>
            </form>
            <div class="switch-mode-text">
                Already have an account? <a onclick="switchTab('login')">Sign In</a>
            </div>
        </div>

        <a href="index.php" class="back-to-site">← Return to Storefront</a>
    </div>

    <script>
        function switchTab(tab) {
            const loginBox = document.getElementById('form-login-box');
            const regBox = document.getElementById('form-register-box');
            const loginBtn = document.getElementById('tab-login');
            const regBtn = document.getElementById('tab-register');

            if (tab === 'register') {
                loginBox.style.display = 'none';
                regBox.style.display = 'block';
                loginBtn.classList.remove('active');
                regBtn.classList.add('active');
                history.replaceState(null, null, '?action=register');
            } else {
                regBox.style.display = 'none';
                loginBox.style.display = 'block';
                regBtn.classList.remove('active');
                loginBtn.classList.add('active');
                history.replaceState(null, null, '?action=login');
            }
        }

        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('password');

        if (togglePassword && passwordField) {
            togglePassword.addEventListener('click', function () {
                const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordField.setAttribute('type', type);
                this.textContent = type === 'text' ? '👁️' : '👁️';
            });
        }

        const toggleRegPassword = document.getElementById('toggleRegPassword');
        const regPasswordField = document.getElementById('reg_password');

        if (toggleRegPassword && regPasswordField) {
            toggleRegPassword.addEventListener('click', function () {
                const type = regPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                regPasswordField.setAttribute('type', type);
            });
        }

        const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
        const confirmPasswordField = document.getElementById('reg_confirm_password');

        if (toggleConfirmPassword && confirmPasswordField) {
            toggleConfirmPassword.addEventListener('click', function () {
                const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordField.setAttribute('type', type);
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var countrySelect = document.getElementById("reg_country");
            if (countrySelect) {
                new TomSelect("#reg_country", {
                    placeholder: "Search for a country...",
                    create: false,
                    dropdownParent: "body"
                });
            }
        });
    </script>
</body>
</html>
