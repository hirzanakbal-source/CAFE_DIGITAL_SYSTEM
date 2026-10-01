<?php
require __DIR__ . '/db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ===== ONLY FOR LOGGED IN USERS =====
if (!isset($_SESSION['customer_id'])) {
    echo json_encode([
        'valid'   => false,
        'message' => 'Please login to use coupons.',
    ]);
    exit;
}

header('Content-Type: application/json');

$code     = strtoupper(trim($_POST['code']     ?? ''));
$subtotal = floatval($_POST['subtotal']         ?? 0);

if (empty($code)) {
    echo json_encode([
        'valid'   => false,
        'message' => 'Please enter a coupon code.',
    ]);
    exit;
}

try {
    // ===== CHECK IF COUPON TABLE EXISTS =====
    $tableCheck  = $pdo->query("SHOW TABLES LIKE 'coupon'");
    $tableExists = $tableCheck->rowCount() > 0;

    if (!$tableExists) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Coupon system not available.',
        ]);
        exit;
    }

    // ===== FIND COUPON =====
    $stmt = $pdo->prepare("
        SELECT *
        FROM coupon
        WHERE code = ?
        AND is_active = 1
        AND expiry_date >= CURDATE()
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Invalid or expired coupon code.',
        ]);
        exit;
    }
        $used = $pdo->prepare("SELECT 1 FROM coupon_usage WHERE coupon_id = ? AND customer_id = ?");
    $used->execute([$coupon['coupon_id'], $_SESSION['customer_id']]);
    if ($used->fetch()) {
        echo json_encode([
            'valid'   => false,
            'message' => 'You have already used this coupon.',
        ]);
        exit;
    }
    // ===== CHECK MINIMUM ORDER =====
    if (!empty($coupon['min_order']) && $subtotal < $coupon['min_order']) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Minimum order of RM '
                       . number_format($coupon['min_order'], 2)
                       . ' required for this coupon.',
        ]);
        exit;
    }

    // ===== CALCULATE DISCOUNT (your COUPON table stores a percentage in `discount`) =====
    $discount = $subtotal * ($coupon['discount'] / 100);

    // Discount cannot exceed subtotal
    $discount = min($discount, $subtotal);

    echo json_encode([
        'valid'    => true,
        'discount' => round($discount, 2),
        'message'  => 'Coupon applied successfully!',
        'type'     => 'percent',
        'amount'   => $coupon['discount'],
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'valid'   => false,
        'message' => 'Error validating coupon.',
    ]);
}
?>