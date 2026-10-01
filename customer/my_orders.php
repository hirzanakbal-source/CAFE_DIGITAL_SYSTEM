<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/db.php';
date_default_timezone_set('Asia/Kuala_Lumpur');


if (!isset($_SESSION['customer_id'])) {
    header('Location: welcome.php');
    exit;
}



$customer_id = $_SESSION['customer_id'];
$customerName = $_SESSION['name'] ?? 'Guest';

try {
       $stmt = $pdo->prepare("
        SELECT o.order_id, o.total_price, o.status, o.created_at,
        COUNT(om.order_menu_id) AS item_count
        FROM `order` o
        LEFT JOIN order_menu om ON om.order_id = o.order_id
        WHERE o.customer_id = ?
        GROUP BY o.order_id, o.total_price, o.status, o.created_at
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $orders = [];
}

function statusStyle($status) {
    return match(strtolower($status)) {
        'completed', 'delivered' => ['bg' => '#dcfce7', 'color' => '#15803d'],
        'pending', 'processing'  => ['bg' => '#fef9c3', 'color' => '#a16207'],
        'cancelled'              => ['bg' => '#fee2e2', 'color' => '#b91c1c'],
        default                  => ['bg' => '#e2e8f0', 'color' => '#475569'],
    };
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
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>MIA COFFEE - My Orders</title>
<style>
  :root {
    --primary: #1f4ed8;
    --primary-dark: #1e40af;
    --text: #0f172a;
    --muted: #64748b;
    --bg: #f1f5f9;
    --card: #ffffff;
    --border: #e2e8f0;
    --soft: #f8fafc;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: "Inter", "Segoe UI", Roboto, Arial, sans-serif;
    background: var(--bg);
    color: var(--text);
    padding-bottom: 60px;
  }
  .top-navbar {
    background: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 12px rgba(15,23,42,0.06);
  }
  .back-link {
    color: var(--muted);
    text-decoration: none;
    font-size: 1.2rem;
    display: flex;
    align-items: center;
  }
  .navbar-title { font-size: 1.1rem; font-weight: 800; }
  .orders-container {
    max-width: 640px;
    margin: 0 auto;
    padding: 18px 16px;
  }
  .order-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 12px;
    text-decoration: none;
    color: inherit;
    display: block;
    transition: all .2s;
    box-shadow: 0 2px 10px rgba(15,23,42,0.04);
  }
  .order-card:hover {
    box-shadow: 0 8px 20px rgba(15,23,42,0.09);
    transform: translateY(-2px);
  }
  .order-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
  }
  .order-id { font-weight: 800; font-size: 0.95rem; }
  .order-status {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    text-transform: capitalize;
  }
  .order-meta {
    font-size: 0.78rem;
    color: var(--muted);
    margin-bottom: 8px;
  }
  .order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .order-total {
    font-weight: 800;
    color: var(--primary-dark);
    font-size: 0.95rem;
  }
   .order-view {
  font-size: 0.76rem;
  color: var(--primary);
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 3px;
}
  .empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
  }
  .empty-state a {
    display: inline-block;
    margin-top: 14px;
    background: var(--primary);
    color: #fff;
    text-decoration: none;
    padding: 10px 20px;
    border-radius: 999px;
    font-size: 0.85rem;
    font-weight: 700;
  }
</style>
</head>
<body>

<div class="top-navbar">
  <a href="menu.php" class="back-link" aria-label="Back to menu">←</a>
  <div class="navbar-title">My Orders</div>
</div>

<div class="orders-container">
  <?php if (!empty($orders)): ?>
    <?php foreach ($orders as $order):
      $style = statusStyle($order['status']);
    ?>
    <a href="order_receipt.php?order_id=<?= (int)$order['order_id'] ?>" class="order-card">
      <div class="order-row">
        <span class="order-id">Order #<?= (int)$order['order_id'] ?></span>
        <span class="order-status" style="background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>;">
          <?= htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8') ?>
        </span>
      </div>
      <div class="order-meta">
        <?= toMalaysiaTime($order['created_at'])->format('d M Y, g:i A') ?>
        &middot; <?= (int)$order['item_count'] ?> item<?= $order['item_count'] == 1 ? '' : 's' ?>
      </div>
      <div class="order-footer">
        <span class="order-total">RM <?= number_format($order['total_price'], 2) ?></span>
        <span class="order-view">
  View Receipt
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
</span>
      </div>
    </a>
    <?php endforeach; ?>
  <?php else: ?>
    <div class="empty-state">
      <p style="font-size:0.95rem; font-weight:600;">No orders yet.</p>
      <a href="menu.php">Start Ordering</a>
    </div>
  <?php endif; ?>
</div>

</body>
</html>