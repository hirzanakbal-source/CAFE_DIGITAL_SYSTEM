<?php
require 'db.php';
session_start();

header('Content-Type: application/json');

// ===== ONLY LOGGED-IN CUSTOMERS CAN SAVE COUPONS =====
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to save coupons.']);
    exit;
}

$customer_id = $_SESSION['customer_id'];
$code = strtoupper(trim($_POST['code'] ?? ''));

if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'No coupon code provided.']);
    exit;
}

// ===== CONFIRM COUPON EXISTS =====
$stmt = $pdo->prepare("SELECT * FROM COUPON WHERE code = ?");
$stmt->execute([$code]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    echo json_encode(['success' => false, 'message' => 'This coupon code does not exist.']);
    exit;
}

// ===== CHECK IF ALREADY SAVED =====
$checkStmt = $pdo->prepare("SELECT saved_coupon_id FROM SAVED_COUPON WHERE customer_id = ? AND coupon_code = ?");
$checkStmt->execute([$customer_id, $code]);
$alreadySaved = (bool) $checkStmt->fetch();

// ===== SAVE (UPDATE TIMESTAMP IF ALREADY SAVED) =====
try {
    $stmt = $pdo->prepare("
        INSERT INTO SAVED_COUPON (customer_id, coupon_code)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE saved_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$customer_id, $code]);

    echo json_encode([
        'success'   => true,
        'message'   => $alreadySaved ? 'You already saved this coupon.' : 'Coupon saved to your account.',
        'duplicate' => $alreadySaved,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Could not save coupon. Please try again.']);
}