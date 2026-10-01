<?php
<?php
require __DIR__ . '/db.php';


if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Get cart items directly (flat CART table)
$stmt = $pdo->prepare("
    SELECT c.cart_id, m.menu_id, m.menu_name, m.price, c.quantity
    FROM cart c
    JOIN menu m ON c.menu_id = m.menu_id
    WHERE c.customer_id = ?
");
$stmt->execute([$customer_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

// Calculate total
// Calculate total
$total = 0;
foreach ($items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// ===== APPLY COUPON (if any) =====
$couponCode = trim($_POST['coupon_code'] ?? '');
$coupon_id  = null;
$discount   = 0;

if ($couponCode !== '') {
    $stmtCoupon = $pdo->prepare("
        SELECT coupon_id, discount 
        FROM coupon 
        WHERE code = ? AND is_active = 1 AND expiry_date >= CURDATE()
    ");
    $stmtCoupon->execute([$couponCode]);
    $couponRow = $stmtCoupon->fetch(PDO::FETCH_ASSOC);

    if ($couponRow) {
        // Check if this customer has already used this coupon
        $stmtUsed = $pdo->prepare("
            SELECT usage_id FROM coupon_usage 
            WHERE coupon_id = ? AND customer_id = ?
        ");
        $stmtUsed->execute([$couponRow['coupon_id'], $customer_id]);

        if ($stmtUsed->fetch()) {
            // Already used — no discount, silently ignored
            // (or set a $couponError message and show it on cart.php if you display errors there)
        } else {
            $coupon_id = $couponRow['coupon_id'];
            $discount  = $total * ($couponRow['discount'] / 100);
        }
    }
}

$discountedSubtotal = max(0, $total - $discount);

// Tax & charges
$taxRate    = 0.06;
$tax        = $discountedSubtotal * $taxRate;
$grandTotal = $discountedSubtotal + $tax;

// Insert ORDER
$stmt = $pdo->prepare("INSERT INTO `order` 
    (customer_id, status, total_price, coupon_id) 
    VALUES (?, 'Pending', ?, ?)");
$stmt->execute([$customer_id, $grandTotal, $coupon_id]);
$order_id = $pdo->lastInsertId();

// Record coupon usage (one-time-per-customer enforcement)
if ($coupon_id !== null) {
    $pdo->prepare("
        INSERT INTO coupon_usage (coupon_id, customer_id, order_id) 
        VALUES (?, ?, ?)
    ")->execute([$coupon_id, $customer_id, $order_id]);
}
// Insert ORDER_MENU records
$stmtOrderMenu = $pdo->prepare(
    "INSERT INTO order_menu (order_id, menu_id, quantity, unit_price) 
     VALUES (?, ?, ?, ?)"
);
foreach ($items as $item) {
    $stmtOrderMenu->execute([$order_id, $item['menu_id'], $item['quantity'], $item['price']]);
}

// Clear cart
// Clear cart
$pdo->prepare("DELETE FROM cart WHERE customer_id = ?")->execute([$customer_id]);

require_once __DIR__ . '/../loyalty_functions.php';
$loyaltyResult = processLoyaltyForOrder($pdo, $customer_id, $order_id, $total);

// Get customer name
$stmtCust = $pdo->prepare("SELECT * FROM customer WHERE customer_id = ?");
$stmtCust->execute([$customer_id]);
$customer = $stmtCust->fetch(PDO::FETCH_ASSOC);

// Order date & time
// Order date & time
$orderDate = date('d M Y');
$orderTime = date('h:i A');

// Order status step (0=Placed, 1=Preparing, 2=Ready, 3=Completed)
$statusMap = [
    'Pending'   => 0,
    'Preparing' => 1,
    'Ready'     => 2,
    'Completed' => 3,
];
$orderStatus = 'Pending';
$statusStep  = $statusMap[$orderStatus] ?? 0;

function icon($name, $size = 20) {
    $icons = [
        'check'     => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'receipt'   => '<path d="M4 2h16v20l-3-2-3 2-3-2-3 2-3-2-1 2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'printer'   => '<path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M4 9h16a2 2 0 0 1 2 2v5h-4M4 16H2v-5a2 2 0 0 1 2-2Z"/>',
        'utensils'  => '<path d="M3 2v7c0 1.1.9 2 2 2h1a2 2 0 0 0 2-2V2M7 2v20M17 2v20M21 15c0-4-3-4-3-8V2"/>',
        'coffee'    => '<path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/>',
        'heart'     => '<path d="M19 14c1.5-1.4 3-3.1 3-5.4A4.6 4.6 0 0 0 12 5.6 4.6 4.6 0 0 0 2 8.6c0 2.3 1.5 4 3 5.4l7 6.5Z"/>',
        'package'   => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/',
        'sparkles'  => '<path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/>',
    ];
    $paths = $icons[$name] ?? '';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Receipt - MIA COFFEE</title>
<style>
  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #f0f2f5;
    min-height: 100vh;
    padding: 30px 15px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
  }

  .receipt-wrapper {
    width: 100%;
    max-width: 420px;
  }

  /* ===== SUCCESS ANIMATION ===== */
  .success-header {
    text-align: center;
    margin-bottom: 20px;
    animation: fadeDown 0.5s ease;
  }

  @keyframes fadeDown {
    from { opacity: 0; transform: translateY(-20px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .success-icon {
    width: 70px;
    height: 70px;
    background: #28a745;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    color: #fff;
    box-shadow: 0 5px 20px rgba(40,167,69,0.4);
    animation: popIn 0.5s ease 0.2s both;
  }

  .success-icon svg { width: 34px; height: 34px; }

  @keyframes popIn {
    from { transform: scale(0); }
    to   { transform: scale(1); }
  }

  .success-header h2 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #222;
    margin-bottom: 5px;
  }

  .success-header p {
    color: #888;
    font-size: 0.9rem;
  }

  /* ===== RECEIPT CARD ===== */
  .receipt-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 5px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    animation: fadeUp 0.5s ease 0.3s both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* Receipt Top */
  .receipt-top {
    background: linear-gradient(135deg, #e31937, #b21427);
    padding: 25px 25px 35px;
    text-align: center;
    color: #fff;
    position: relative;
  }

  .cafe-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 6px;
  }

  .cafe-logo svg { width: 30px; height: 30px; }

  .cafe-name {
    font-size: 1.3rem;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 3px;
  }

  .cafe-tagline {
    font-size: 0.8rem;
    opacity: 0.85;
  }

  /* Zigzag bottom edge */
  .receipt-top::after {
    content: '';
    position: absolute;
    bottom: -12px;
    left: 0;
    right: 0;
    height: 25px;
    background: #fff;
    clip-path: polygon(
      0% 100%, 2.5% 0%, 5% 100%, 7.5% 0%, 10% 100%,
      12.5% 0%, 15% 100%, 17.5% 0%, 20% 100%,
      22.5% 0%, 25% 100%, 27.5% 0%, 30% 100%,
      32.5% 0%, 35% 100%, 37.5% 0%, 40% 100%,
      42.5% 0%, 45% 100%, 47.5% 0%, 50% 100%,
      52.5% 0%, 55% 100%, 57.5% 0%, 60% 100%,
      62.5% 0%, 65% 100%, 67.5% 0%, 70% 100%,
      72.5% 0%, 75% 100%, 77.5% 0%, 80% 100%,
      82.5% 0%, 85% 100%, 87.5% 0%, 90% 100%,
      92.5% 0%, 95% 100%, 97.5% 0%, 100% 100%
    );
  }

  /* Receipt Body */
  .receipt-body {
    padding: 30px 25px 20px;
  }

  /* Order Info */
  .order-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 2px dashed #eee;
  }

  .info-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 10px 12px;
  }

  .info-label {
    font-size: 0.72rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
  }

  .info-value {
    font-size: 0.9rem;
    font-weight: 700;
    color: #222;
  }

  .info-value.order-id {
    color: #1558b0;
    font-size: 1rem;
  }

  .info-value.status {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #f39c12;
  }

  .info-value.status svg { width: 15px; height: 15px; }

  /* ===== ORDER STATUS TIMELINE ===== */
  .timeline-section {
    margin-bottom: 22px;
    padding-bottom: 20px;
    border-bottom: 2px dashed #eee;
  }

  .timeline {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    position: relative;
  }

  .timeline-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    position: relative;
  }

  .timeline-dot {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #eee;
    color: #aaa;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2;
    transition: all 0.3s ease;
  }

  .timeline-dot svg { width: 16px; height: 16px; }

  .timeline-step.done .timeline-dot {
    background: #28a745;
    color: #fff;
  }

  .timeline-step.active .timeline-dot {
    background: #e31937;
    color: #fff;
    box-shadow: 0 0 0 4px rgba(227,25,55,0.15);
  }

  .timeline-line {
    position: absolute;
    top: 16px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: #eee;
    z-index: 1;
  }

  .timeline-step.done .timeline-line,
  .timeline-step.active .timeline-line {
    background: #28a745;
  }

  .timeline-step:last-child .timeline-line {
    display: none;
  }

  .timeline-label {
    font-size: 0.68rem;
    color: #999;
    margin-top: 6px;
    text-align: center;
    font-weight: 600;
  }

  .timeline-step.done .timeline-label,
  .timeline-step.active .timeline-label {
    color: #333;
  }

  /* Items Section */
  .items-title {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    font-weight: 700;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
  }

  .item-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
    gap: 10px;
  }

  .item-row:last-child {
    border-bottom: none;
  }

  .item-left {
    flex: 1;
  }

  .item-name {
    font-size: 0.92rem;
    font-weight: 600;
    color: #222;
    margin-bottom: 2px;
  }

  .item-qty {
    font-size: 0.78rem;
    color: #999;
  }

  .item-price {
    font-size: 0.92rem;
    font-weight: 700;
    color: #222;
    white-space: nowrap;
  }

  /* Totals Section */
  .totals-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px dashed #eee;
  }

  .total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: 0.88rem;
    color: #555;
  }

  .total-row.tax {
    color: #888;
    font-size: 0.82rem;
  }

  .total-row.grand {
    margin-top: 10px;
    padding-top: 12px;
    border-top: 2px solid #eee;
    font-size: 1.1rem;
    font-weight: 800;
    color: #222;
  }

  .total-row.grand .grand-amount {
    color: #1558b0;
    font-size: 1.2rem;
  }

  /* Receipt Bottom */
  .receipt-bottom {
    padding: 20px 25px;
    background: #fafafa;
    border-top: 2px dashed #eee;
    text-align: center;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffc107;
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 15px;
  }

  .status-dot {
    width: 8px;
    height: 8px;
    background: #ffc107;
    border-radius: 50%;
    animation: blink 1s infinite;
  }

  @keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
  }

  .thank-you {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 0.88rem;
    color: #888;
    margin-bottom: 5px;
  }

  .thank-you svg { width: 14px; height: 14px; color: #e31937; }

  .barcode {
    font-size: 2rem;
    letter-spacing: 3px;
    color: #ddd;
    margin: 10px 0;
    font-family: monospace;
  }

  .order-ref {
    font-size: 0.75rem;
    color: #bbb;
  }

  /* ===== ACTION BUTTONS ===== */
  .action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
    animation: fadeUp 0.5s ease 0.5s both;
  }

  .btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 700;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .btn svg { width: 18px; height: 18px; }

  .btn-primary {
  background: linear-gradient(135deg, #1558b0, #10418f);
  color: #fff;
  box-shadow: 0 4px 15px rgba(21,88,176,0.3);
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(21,88,176,0.4);
}
  .btn-secondary {
    background: #f0f2f5;
    color: #555;
  }

  .btn-secondary:hover {
    background: #e0e0e0;
    transform: translateY(-1px);
  }

  /* Print styles */
  @media print {
    body {
      background: #fff;
      padding: 0;
    }

    .action-buttons {
      display: none;
    }

    .receipt-card {
      box-shadow: none;
      border-radius: 0;
    }

    .success-header {
      display: none;
    }
  }

  /* Responsive */
  @media (max-width: 480px) {
    body {
      padding: 15px 10px;
    }

    .receipt-body {
      padding: 25px 18px 15px;
    }

    .order-info {
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
  }
</style>
</head>
<body>

<div class="receipt-wrapper">

  <!-- Success Header -->
  <div class="success-header">
    <div class="success-icon"><?= icon('check', 34) ?></div>
    <h2>Order Placed!</h2>
    <p>Your order has been received successfully</p>
  </div>
  <?php if (!empty($loyaltyResult['reward_coupon'])): ?>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:14px 18px;margin-bottom:16px;text-align:center;">
    <strong>🎉 You've unlocked a reward coupon!</strong><br>
    Use code <strong style="color:#1558b0;"><?= htmlspecialchars($loyaltyResult['reward_coupon']['code']) ?></strong>
    for <?= $loyaltyResult['reward_coupon']['discount_percent'] ?>% off — valid until
    <?= date('d M Y', strtotime($loyaltyResult['reward_coupon']['expiry_date'])) ?>.
  </div>
<?php endif; ?>

  <!-- Receipt Card -->
  <div class="receipt-card">

    <!-- Top Header -->
    <div class="receipt-top">
      <div class="cafe-logo"><?= icon('coffee', 30) ?></div>
      <div class="cafe-name">MIA COFFEE</div>
      <div class="cafe-tagline">Thank you for your order!</div>
    </div>

    <!-- Body -->
    <div class="receipt-body">

      <!-- Order Info Grid -->
      <div class="order-info">
        <div class="info-box">
          <div class="info-label">Order ID</div>
          <div class="info-value order-id">#<?= str_pad($order_id, 4, '0', STR_PAD_LEFT) ?></div>
        </div>

        <div class="info-box">
  <div class="info-label">Status</div>
  <div class="info-value status"><?= icon('clock', 15) ?> <?= htmlspecialchars($orderStatus) ?></div>
</div>

        <div class="info-box">
          <div class="info-label">Date</div>
          <div class="info-value"><?= $orderDate ?></div>
        </div>

        <div class="info-box">
          <div class="info-label">Time</div>
          <div class="info-value"><?= $orderTime ?></div>
        </div>

        <div class="info-box" style="grid-column: span 2;">
          <div class="info-label">Customer</div>
          <div class="info-value">
          <?= htmlspecialchars($customer['full_name'] ?? 'Customer #' . $customer_id) ?>
          </div>
        </div>
      </div>

      <!-- Order Status Timeline -->
      <div class="timeline-section">
        <div class="items-title"><?= icon('package', 15) ?> Order Progress</div>
        <div class="timeline">
          <?php
          $steps = [
              ['label' => 'Placed',    'icon' => 'check'],
              ['label' => 'Preparing', 'icon' => 'coffee'],
              ['label' => 'Ready',     'icon' => 'sparkles'],
              ['label' => 'Completed', 'icon' => 'package'],
          ];
          foreach ($steps as $i => $step):
              $state = $i < $statusStep ? 'done' : ($i === $statusStep ? 'active' : '');
          ?>
            <div class="timeline-step <?= $state ?>">
              <div class="timeline-line"></div>
              <div class="timeline-dot"><?= icon($state === 'done' ? 'check' : $step['icon'], 16) ?></div>
              <div class="timeline-label"><?= $step['label'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Items -->
      <div class="items-title"><?= icon('receipt', 15) ?> Order Items</div>

      <?php foreach ($items as $item):
        $subtotal = $item['price'] * $item['quantity'];
      ?>
        <div class="item-row">
          <div class="item-left">
            <div class="item-name">
              <?= htmlspecialchars($item['menu_name']) ?>
            </div>
            <div class="item-qty">
              <?= $item['quantity'] ?> x 
              RM <?= number_format($item['price'], 2) ?>
            </div>
          </div>
          <div class="item-price">
            RM <?= number_format($subtotal, 2) ?>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Totals -->
      <div class="totals-section">
        <div class="total-row">
          <span>Subtotal</span>
          <span>RM <?= number_format($total, 2) ?></span>
        </div>
        <div class="total-row tax">
          <span>Tax (6%)</span>
          <span>RM <?= number_format($tax, 2) ?></span>
        </div>
        <div class="total-row grand">
          <span>Total</span>
          <span class="grand-amount">
            RM <?= number_format($grandTotal, 2) ?>
          </span>
        </div>
      </div>

    </div>

    <!-- Receipt Bottom -->
    <div class="receipt-bottom">

      <!-- Status Badge -->
     <div class="status-badge">
  <span class="status-dot"></span>
  <?= $orderStatus === 'Pending' ? 'Order received' : 'Order is being prepared' ?>
</div>
      <div class="thank-you">
        <?= icon('heart', 14) ?> Thank you for dining with us!
      </div>

      <!-- Barcode Design -->
      <div class="barcode">
        ||||| |||| ||||| ||||
      </div>

      <div class="order-ref">
        Ref: CD-<?= date('Ymd') ?>-<?= str_pad($order_id, 4, '0', STR_PAD_LEFT) ?>
      </div>

    </div>

  </div>
  <!-- End Receipt Card -->

  <!-- Action Buttons -->
  <div class="action-buttons">

    <button class="btn btn-secondary" onclick="window.print()">
      <?= icon('printer', 18) ?> Print Receipt
    </button>

    <a href="menu.php" class="btn btn-primary">
      <?= icon('utensils', 18) ?> Back to Menu
    </a>

  </div>

</div>

</body>
</html>