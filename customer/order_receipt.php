<?php
require __DIR__ . '/db.php';

// ===== CHECK SESSION =====
if (!isset($_SESSION['customer_id'])) {
    header('Location: welcome.php');
    exit;
}

$customer_id = $_SESSION['customer_id'];
$order_id    = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header('Location: my_orders.php');
    exit;
}

// ===== FETCH ORDER (only if it belongs to this customer) =====
$stmt = $pdo->prepare("
    SELECT o.order_id, o.customer_id, o.total_price, o.status, o.coupon_id, o.created_at,
           c.full_name, c.phone,
           cp.code AS coupon_code
    FROM `order` o
    JOIN customer c ON c.customer_id = o.customer_id
    LEFT JOIN coupon cp ON cp.coupon_id = o.coupon_id
    WHERE o.order_id = ? AND o.customer_id = ?
");
$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: my_orders.php');
    exit;
}

// ===== FETCH ORDER ITEMS =====
$stmt = $pdo->prepare("
    SELECT om.order_menu_id, om.quantity, om.unit_price, m.menu_name
    FROM order_menu om
    JOIN menu m ON m.menu_id = om.menu_id
    WHERE om.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== TOTALS =====
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['unit_price'] * $item['quantity'];
}
$sstRate   = 0.06;
$sstAmount = $subtotal * $sstRate;
// total_price is treated as the final grand total already stored at checkout
$grandTotal = (float)$order['total_price'];
$discount   = max(0, ($subtotal + $sstAmount) - $grandTotal);

// ===== ORDER STATUS -> TIMELINE STEP =====
$status = strtolower($order['status']);
$isCancelled = $status === 'cancelled';

// Order status step (0=Placed, 1=Preparing, 2=Ready, 3=Completed)
$statusMap = [
    'Pending'   => 1,
    'Preparing' => 2,
    'Ready'     => 3,
    'Completed' => 4,
];
$orderStatus = $order['status'];
$statusStep  = $statusMap[$orderStatus] ?? 0;
$statusLabels = ['Placed', 'Preparing', 'Ready', 'Completed'];

// ===== SVG ICON HELPER (same set used across the site) =====
function icon($name, $size = 20, $stroke = 'currentColor') {
    $paths = [
        'check-circle'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
        'clock'           => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
        'clipboard'       => '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>',
        'utensils'        => '<path d="M3 2v7c0 1.1.9 2 2 2h2a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"></path>',
        'bag'             => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path>',
        'receipt'         => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"></path><line x1="8" y1="7" x2="16" y2="7"></line><line x1="8" y1="11" x2="16" y2="11"></line><line x1="8" y1="15" x2="12" y2="15"></line>',
        'printer'         => '<polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect>',
        'arrow-left'      => '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>',
        'ticket'          => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"></path><path d="M13 5v2M13 17v2M13 11v2"></path>',
        'alert-triangle'  => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        'x-circle'        => '<circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line>',
        'user'            => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'hash'            => '<line x1="4" y1="9" x2="20" y2="9"></line><line x1="4" y1="15" x2="20" y2="15"></line><line x1="10" y1="3" x2="8" y2="21"></line><line x1="16" y1="3" x2="14" y2="21"></line>',
        'calendar'        => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
    ];
    if (!isset($paths[$name])) return '';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . $stroke . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;flex-shrink:0;">' . $paths[$name] . '</svg>';
}
function toMalaysiaTime($datetime) {
    $dt = new DateTime($datetime, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('Asia/Kuala_Lumpur'));
    return $dt;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order #<?= (int)$order['order_id'] ?> - MIA COFFEE</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f6f9;
    min-height: 100vh;
    padding-bottom: 40px;
  }

  /* ===== TOP NAVBAR ===== */
  .top-navbar {
    background: #fff;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f0f0f0;
    position: sticky;
    top: 0;
    z-index: 200;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
  }

  .navbar-title { font-size: 1.2rem; font-weight: 800; color: #222; }

  .back-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: 2px solid #0d47a1;
    color: #0d47a1;
    border-radius: 50px;
    padding: 6px 16px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
  }
  .back-btn:hover { background: #1558b0; color: #fff; }

  .print-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #1558b0;
    border: none;
    color: #fff;
    border-radius: 50px;
    padding: 8px 18px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.3s ease;
  }
  .print-btn:hover { background: #10418f; }

  .main-container { max-width: 560px; margin: 25px auto; padding: 0 15px; }

  /* ===== SUCCESS / STATUS HEADER ===== */
  .status-header {
    text-align: center;
    padding: 30px 20px 25px;
  }

  .status-icon-wrap {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
    animation: popScale 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  .status-icon-wrap.ok       { background: #dcfce7; color: #16a34a; }
  .status-icon-wrap.pending  { background: #fff3cd; color: #b45309; }
  .status-icon-wrap.cancel   { background: #fee2e2; color: #dc2626; }

  @keyframes popScale {
    0%   { transform: scale(0);   opacity: 0; }
    100% { transform: scale(1);   opacity: 1; }
  }

  .status-title { font-size: 1.3rem; font-weight: 800; color: #222; margin-bottom: 6px; }
  .status-sub    { font-size: 0.85rem; color: #888; }

  /* ===== TIMELINE ===== */
  .timeline-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    padding: 24px 20px 20px;
    margin-bottom: 15px;
  }

  .timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
  }

  .timeline::before {
    content: '';
    position: absolute;
    top: 16px;
    left: 8%;
    right: 8%;
    height: 3px;
    background: #eee;
    z-index: 1;
  }

  .timeline-fill {
    position: absolute;
    top: 16px;
    left: 8%;
    height: 3px;
    background: #1558b0;
    z-index: 2;
    transition: width 0.4s ease;
  }

  .timeline-step {
    position: relative;
    z-index: 3;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    flex: 1;
  }

  .step-dot {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #eee;
    color: #aaa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    border: 3px solid #f4f6f9;
    transition: all 0.3s ease;
  }

  .timeline-step.done .step-dot { background: #1558b0; color: #fff; }
  .timeline-step.current .step-dot {
    background: #e31937;
    color: #fff;
    box-shadow: 0 0 0 4px rgba(227,25,55,0.15);
  }

  .step-label {
    font-size: 0.68rem;
    font-weight: 700;
    color: #999;
    text-align: center;
    max-width: 70px;
  }
  .timeline-step.done .step-label,
  .timeline-step.current .step-label { color: #333; }

  .cancelled-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fee2e2;
    color: #b91c1c;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 0.85rem;
    font-weight: 700;
  }

  /* ===== RECEIPT CARD (zigzag edges) ===== */
  .receipt-card {
    background: #fff;
    border-radius: 16px 16px 0 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    padding: 20px;
    margin-bottom: 0;
    position: relative;
  }

  .receipt-zigzag {
    height: 14px;
    background:
      linear-gradient(135deg, #f4f6f9 50%, transparent 50%) 0 0,
      linear-gradient(-135deg, #f4f6f9 50%, transparent 50%) 0 0;
    background-size: 16px 16px;
    background-repeat: repeat-x;
    margin-bottom: 15px;
  }

  .receipt-info-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.82rem;
    color: #666;
    padding: 4px 0;
  }
  .receipt-info-row b { color: #222; }

  .receipt-divider {
    border-top: 1px dashed #ddd;
    margin: 14px 0;
  }

  .receipt-item-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 6px 0;
    font-size: 0.85rem;
  }
  .receipt-item-name { color: #333; font-weight: 600; flex: 1; padding-right: 10px; }
  .receipt-item-qty { color: #999; font-weight: 500; }
  .receipt-item-price { color: #222; font-weight: 700; white-space: nowrap; }

  .receipt-summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.85rem;
    color: #666;
    padding: 4px 0;
  }
  .receipt-summary-row.discount { color: #16a34a; }

  .receipt-total-row {
    display: flex;
    justify-content: space-between;
    padding-top: 10px;
    margin-top: 8px;
    border-top: 2px solid #f0f0f0;
    font-size: 1.05rem;
    font-weight: 800;
    color: #222;
  }
  .receipt-total-row .value { color: #1558b0; }

  .receipt-card-bottom {
    background: #fff;
    border-radius: 0 0 16px 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    padding: 0 20px 20px;
    margin-bottom: 15px;
  }

  .receipt-zigzag-bottom {
    height: 14px;
    background:
      linear-gradient(45deg, #f4f6f9 50%, transparent 50%) 0 100%,
      linear-gradient(-45deg, #f4f6f9 50%, transparent 50%) 0 100%;
    background-size: 16px 16px;
    background-repeat: repeat-x;
    background-position: bottom;
    margin-top: -1px;
  }

  .thank-you {
    text-align: center;
    font-size: 0.8rem;
    color: #aaa;
    margin-top: 4px;
  }

  @media print {
    .top-navbar, .timeline-card, .status-header, .print-btn { display: none !important; }
    body { background: #fff; padding: 0; }
    .main-container { margin: 0; max-width: 100%; }
  }
</style>
</head>
<body>

<div class="top-navbar">
  <a href="my_orders.php" class="back-btn"><?= icon('arrow-left', 16) ?> My Orders</a>
  <div class="navbar-title">MIA COFFEE</div>
  <button class="print-btn" onclick="window.print()"><?= icon('printer', 16) ?> Print</button>
</div>

<div class="main-container">

  <!-- ===== STATUS HEADER ===== -->
  <div class="status-header">
    <?php if ($isCancelled): ?>
      <div class="status-icon-wrap cancel"><?= icon('x-circle', 34) ?></div>
      <div class="status-title">Order Cancelled</div>
      <div class="status-sub">This order was cancelled.</div>
    <?php elseif ($statusStep >= 4): ?>
      <div class="status-icon-wrap ok"><?= icon('check-circle', 34) ?></div>
      <div class="status-title">Order Completed</div>
      <div class="status-sub">Thanks for ordering with MIA Coffee!</div>
    <?php else: ?>
      <div class="status-icon-wrap pending"><?= icon('clock', 34) ?></div>
      <div class="status-title">Order Confirmed</div>
      <div class="status-sub">We're getting your order ready.</div>
    <?php endif; ?>
  </div>

  <!-- ===== TIMELINE ===== -->
  <div class="timeline-card">
    <?php if ($isCancelled): ?>
      <div class="cancelled-banner">
        <?= icon('alert-triangle', 18) ?> This order was cancelled and will not be processed further.
      </div>
    <?php else: ?>
      <?php
        $fillPercent = match($statusStep) {
            1 => 0,
            2 => 33,
            3 => 66,
            4 => 100,
            default => 0,
        };
      ?>
      <div class="timeline">
        <div class="timeline-fill" style="width: calc((84% * <?= $fillPercent ?>) / 100);"></div>
        <?php foreach ($statusLabels as $i => $label):
          $stepNum = $i + 1;
          $cls = $stepNum < $statusStep ? 'done' : ($stepNum === $statusStep ? 'current' : '');
        ?>
        <div class="timeline-step <?= $cls ?>">
          <div class="step-dot">
            <?= $stepNum < $statusStep ? icon('check-circle', 16) : $stepNum ?>
          </div>
          <div class="step-label"><?= $label ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== RECEIPT ===== -->
  <div class="receipt-card">
    <div class="receipt-info-row">
      <span><?= icon('hash', 14) ?> Order ID</span>
      <b>#<?= (int)$order['order_id'] ?></b>
    </div>
    <div class="receipt-info-row">
      <span><?= icon('user', 14) ?> Customer</span>
      <b><?= htmlspecialchars($order['full_name']) ?></b>
    </div>
    <div class="receipt-info-row">
      <span><?= icon('calendar', 14) ?> Date</span>
      <b><?= toMalaysiaTime($order['created_at'])->format('d M Y, g:i A') ?></b>
    </div>

    <div class="receipt-zigzag"></div>

    <?php foreach ($items as $item): ?>
    <div class="receipt-item-row">
      <div class="receipt-item-name">
        <?= htmlspecialchars($item['menu_name']) ?>
        <span class="receipt-item-qty">&times;<?= (int)$item['quantity'] ?></span>
      </div>
      <div class="receipt-item-price">
        RM <?= number_format($item['unit_price'] * $item['quantity'], 2) ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="receipt-divider"></div>

    <div class="receipt-summary-row">
      <span>Subtotal</span>
      <span>RM <?= number_format($subtotal, 2) ?></span>
    </div>
    <div class="receipt-summary-row">
      <span>SST (6%)</span>
      <span>RM <?= number_format($sstAmount, 2) ?></span>
    </div>
       <?php if ($discount > 0): ?>
    <div class="receipt-summary-row discount">
      <span><?= icon('ticket', 13) ?> Coupon Discount<?= !empty($order['coupon_code']) ? ' (' . htmlspecialchars($order['coupon_code']) . ')' : '' ?></span>
      <span>- RM <?= number_format($discount, 2) ?></span>
    </div>
    <?php endif; ?>
    <div class="receipt-total-row">
      <span>Total Paid</span>
      <span class="value">RM <?= number_format($grandTotal, 2) ?></span>
    </div>
  </div>
  <div class="receipt-card-bottom">
    <div class="receipt-zigzag-bottom"></div>
    <div class="thank-you">Thank you for choosing MIA Coffee ☕</div>
  </div>

</div>

</body>
</html>