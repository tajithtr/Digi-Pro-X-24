<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Grant immediate access to change password
            $_SESSION['temp_password_user_id'] = $user['id'];
            header("Location: change_password.php");
            exit;
        } else {
            $error = 'No account found with that email address.';
        }
    } else {
        $error = 'Please enter your email address.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,follow">
    <title>Forgot Password - Digi Pro X 24</title>
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
            overflow: hidden;
            padding: 1.5rem;
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

        .login-card {
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 6px solid var(--primary-glow);
            border-radius: 8px;
            padding: 3rem 2.5rem;
            width: 100%;
            max-width: 440px;
            z-index: 10;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: -1.5px;
            left: -1.5px;
            right: -1.5px;
            bottom: -1.5px;
            background: linear-gradient(135deg, rgba(255, 94, 0, 0.4), transparent, rgba(147, 51, 234, 0.3));
            border-radius: 8px;
            z-index: -1;
            pointer-events: none;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo img {
            height: 48px;
            border-radius: 8px;
            margin-bottom: 0.8rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .login-logo h2 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #ffffff;
        }

        .login-logo h2 span {
            background: linear-gradient(135deg, var(--primary-glow, #3b82f6), var(--secondary-glow, #9333ea));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            padding: 0.9rem 1.2rem;
            background: rgba(15, 23, 42, 0.6);
            border: 6px solid var(--primary-glow);
            border-radius: 8px;
            color: #ffffff;
            font-size: 0.95rem;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-glow, #3b82f6);
            box-shadow: 0 0 15px rgba(255, 94, 0, 0.3);
            background: rgba(15, 23, 42, 0.8);
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-glow, #3b82f6), var(--secondary-glow, #9333ea));
            border: 6px solid var(--primary-glow);
            border-radius: 8px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(255, 94, 0, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 94, 0, 0.5);
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.15);
            border: 6px solid var(--primary-glow);
            color: #f87171;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .success-msg {
            background: rgba(16, 185, 129, 0.15);
            border: 6px solid var(--primary-glow);
            color: #34d399;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }

        .back-to-site {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            color: rgba(255, 255, 255, 0.5);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
        }

        .back-to-site:hover {
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="bg-orb"></div>
    <div class="bg-orb-2"></div>

    <div class="login-card">
        <div class="login-logo">
            <img src="logo.png" alt="Digi Pro X 24">
            <h2>Digi <span>Pro X 24</span></h2>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success !== ''): ?>
            <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form action="forgot_password.php" method="POST">
            <div class="form-group">
                <label class="form-label" for="email">Enter Email Address</label>
                <input type="email" name="email" id="email" class="form-input" placeholder="john@example.com" required>
            </div>
            <button type="submit" class="btn-submit">Continue to Reset Password ➔</button>
        </form>

        <a href="login.php" class="back-to-site">← Back to Login</a>
    </div>
</body>
</html>
