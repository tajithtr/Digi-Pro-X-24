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

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$reviewer_name = isset($_POST['reviewer_name']) ? trim($_POST['reviewer_name']) : '';
$reviewer_email = isset($_POST['reviewer_email']) ? trim($_POST['reviewer_email']) : '';
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
$image_path = null;

if ($product_id <= 0 || empty($reviewer_name) || $rating < 1 || $rating > 5 || empty($review_text)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

// Handle image upload
if (isset($_FILES['review_image']) && $_FILES['review_image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['review_image']['tmp_name'];
    $fileName = $_FILES['review_image']['name'];
    $fileSize = $_FILES['review_image']['size'];
    $fileType = $_FILES['review_image']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));
    
    $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg', 'webp');
    if (in_array($fileExtension, $allowedfileExtensions)) {
        if (!is_valid_image($fileTmpPath, $fileName)) {
            echo json_encode(['success' => false, 'message' => 'Upload failed. The uploaded file is not a valid image.']);
            exit;
        }
        // Create unique name
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        
        $uploadFileDir = 'uploads/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }
        $dest_path = $uploadFileDir . $newFileName;
        
        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $image_path = $dest_path;
        } else {
            echo json_encode(['success' => false, 'message' => 'There was an error moving the uploaded file.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions)]);
        exit;
    }
}

try {
    $stmt = $pdo->prepare("INSERT INTO reviews (product_id, reviewer_name, email, rating, review_text, image_path, is_approved) VALUES (?, ?, ?, ?, ?, ?, 1)");
    $stmt->execute([$product_id, $reviewer_name, $reviewer_email, $rating, $review_text, $image_path]);
    
    echo json_encode(['success' => true, 'message' => 'Thank you! Your review has been submitted successfully.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred while submitting your review.']);
}
?>
