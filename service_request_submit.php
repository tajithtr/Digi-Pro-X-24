<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_country = strtolower(trim($_SESSION['user_country'] ?? ''));
$is_sri_lanka = ($user_country === 'sri lanka' || $user_country === 'lk' || $user_country === 'srilanka' || $user_country === 'sl');
if (!$is_sri_lanka) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$service_id = (int)($_POST['service_id'] ?? 0);

$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$customer_name = $first_name . ' ' . $last_name;
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$customer_note = trim($_POST['customer_note'] ?? '');

$location_address = trim($address . ', ' . $city);
if (empty($location_address) || $location_address === ',') {
    $location_address = "No address provided.";
}

// Generate unique token
$token_number = '#SR-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Insert into service_requests table
$stmt = $pdo->prepare("INSERT INTO service_requests (user_id, token_number, customer_name, phone_number, location_address, customer_note, service_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$user_id, $token_number, $customer_name, $phone, $location_address, $customer_note, $service_id]);

$_SESSION['success'] = "Request Submitted! Your Service Token Number is: " . $token_number . ". Our team will call you shortly to schedule an appointment.";
header("Location: my_orders.php");
exit;
?>
