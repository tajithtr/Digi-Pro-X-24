<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    $name = trim($_POST['questioner_name'] ?? '');
    $email = trim($_POST['questioner_email'] ?? '');
    $text = trim($_POST['question_text'] ?? '');

    if (!$product_id || empty($name) || empty($text)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO product_questions (product_id, customer_name, email, question_text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$product_id, $name, $email, $text]);

        echo json_encode(['success' => true, 'message' => 'Thank you! Your question has been submitted successfully and will be displayed once answered.']);
    } catch (PDOException $e) {
        error_log("Submit Question Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while submitting your question. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
