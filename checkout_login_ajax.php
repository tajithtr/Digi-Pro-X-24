<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

try {
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
            echo json_encode([
                'success' => false,
                'message' => "Account locked due to too many failed attempts. Try again in {$minutes}m {$seconds}s."
            ]);
            exit;
        }
    }

    if (!$locked && $user && password_verify($password, $user['password'])) {
        if (isset($user['force_password_change']) && $user['force_password_change'] == 1) {
            echo json_encode([
                'success' => false,
                'force_password_change' => true,
                'message' => 'You are using a temporary password. Please click "Login" in the header to sign in and update your password first.'
            ]);
            exit;
        }
        
        // Reset attempts
        $reset_stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = ?");
        $reset_stmt->execute([$user['id']]);

        session_regenerate_id(true);
        // Prevent administrative login here for safety, or allow standard redirect/handling
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        echo json_encode([
            'success' => true,
            'user' => [
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => $user['phone'] ?? '',
                'address' => $user['address'] ?? '',
                'district' => $user['district'] ?? '',
                'city' => $user['city'] ?? '',
                'zip' => $user['zip'] ?? ''
            ]
        ]);
    } else {
        if ($user && !$locked) {
            $attempts = $user['failed_attempts'] + 1;
            if ($attempts >= 5) {
                $lock_until = date('Y-m-d H:i:s', time() + 900);
                $lock_stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, lockout_until = ? WHERE id = ?");
                $lock_stmt->execute([$lock_until, $user['id']]);
                echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Account locked for 15 minutes.']);
            } else {
                $lock_stmt = $pdo->prepare("UPDATE users SET failed_attempts = ? WHERE id = ?");
                $lock_stmt->execute([$attempts, $user['id']]);
                $remaining = 5 - $attempts;
                echo json_encode(['success' => false, 'message' => "Invalid email or password. ({$remaining} attempts remaining)"]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
