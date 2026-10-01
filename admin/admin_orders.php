<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name  = $_SESSION['admin_name'] ?? $_SESSION['name'] ?? 'Admin';
$message     = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id  = intval($_POST['order_id']);
    $newStatus = $_POST['status'] ?? '';
    $allowed   = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];
    if (in_array($newStatus, $allowed)) {
        try {
            $pdo->prepare("UPDATE `order` SET status = ? WHERE order_id = ?")->execute([$newStatus, $order_id]);
            $message     = 'Order #' . $order_id . ' updated to ' . $newStatus;
            $messageType = 'success';
        } catch (PDOException $e) {
            $message     = 'Failed: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
$filterStatus = $_GET['status'] ?? 'all';
$allowed      = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

if ($filterStatus !== 'all' && in_array($filterStatus, $allowed)) {
    $stmt = $pdo->prepare("
        SELECT o.order_id, c.full_name AS customer_name, o.created_at, o.total_price, o.status
        FROM `order` o JOIN customer c ON o.customer_id = c.customer_id
        WHERE o.status = ? ORDER BY o.created_at DESC
    ");
    $stmt->execute([$filterStatus]);
} else {
        $stmt = $pdo->query("
        SELECT o.order_id, c.full_name AS customer_name, o.created_at, o.total_price, o.status
        FROM `order` o JOIN customer c ON o.customer_id = c.customer_id
        ORDER BY o.created_at DESC
    ");
}
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$counts = [];
try {
        foreach ($pdo->query("SELECT status, COUNT(*) as cnt FROM `order` GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['status']] = $row['cnt'];
    }
} catch (PDOException $e) { $counts = []; }

function icon($name, $size = 18) {
    $paths = [
        'coffee'    => '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',
        'home'      => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'cart'      => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'tag'       => '<path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'bars'      => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'clock'     => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'flame'     => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'check'     => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'clipcheck' => '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 14l2 2 4-4"/>',
        'xcircle'   => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        'x'         => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'box'      => '<path d="M21 8V21H3V8"/><path d="M1 3h22v5H1z"/><line x1="10" y1="12" x2="14" y2="12"/>',
        ];
    if (!isset($paths[$name])) return '';
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths[$name].'</svg>';
}

function statusIcon($status, $size = 14) {
    return match($status) {
        'Pending'   => icon('clock', $size),
        'Preparing' => icon('flame', $size),
        'Ready'     => icon('check', $size),
        'Completed' => icon('clipcheck', $size),
        'Cancelled' => icon('xcircle', $size),
        default     => '',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Orders — Cafe Digital</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
  --sidebar-bg: #0f1629; --sidebar-accent: #3b82f6; --sidebar-text: #94a3b8;
  --sidebar-hover: rgba(255,255,255,0.06); --body-bg: #f1f5f9; --card-bg: #ffffff;
  --border: #e2e8f0; --text-primary: #0f172a; --text-secondary: #64748b; --text-muted: #94a3b8;
  --blue: #3b82f6; --green: #10b981; --orange: #f59e0b; --red: #ef4444;
  --teal: #14b8a6; --purple: #8b5cf6; --sidebar-width: 240px; --radius: 12px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--body-bg); color: var(--text-primary); min-height: 100vh; display: flex; }

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ─── SIDEBAR ─── */
.sidebar {
  position: fixed; top: 0; left: 0;
  width: var(--sidebar-width); height: 100vh;
  background: var(--sidebar-bg); display: flex; flex-direction: column;
  z-index: 200; overflow-y: auto;
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.sidebar-brand { padding: 28px 22px 22px; border-bottom: 1px solid rgba(255,255,255,0.07); }
.brand-icon { width: 38px; height: 38px; background: var(--sidebar-accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; margin-bottom: 10px; }
.sidebar-brand h2 { font-size: 0.95rem; font-weight: 700; color: #fff; letter-spacing: 0.01em; }
.sidebar-brand p  { font-size: 0.72rem; color: var(--sidebar-text); margin-top: 2px; letter-spacing: 0.03em; }
.sidebar-nav { padding: 18px 0; flex: 1; }
.nav-section-label { font-size: 0.65rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.1em; padding: 14px 22px 6px; }
.nav-link { display: flex; align-items: center; gap: 11px; padding: 10px 22px; color: var(--sidebar-text); text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; position: relative; }
.nav-link:hover { background: var(--sidebar-hover); color: #fff; }
.nav-link.active { background: rgba(59,130,246,0.15); color: var(--sidebar-accent); }
.nav-link.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--sidebar-accent); border-radius: 0 3px 3px 0; }
.nav-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: rgba(255,255,255,0.04); transition: background 0.2s; }
.nav-link.active .nav-icon { background: rgba(59,130,246,0.2); }
.nav-link:hover  .nav-icon { background: rgba(255,255,255,0.08); }
.sidebar-footer { padding: 16px 22px; border-top: 1px solid rgba(255,255,255,0.07); }
.logout-btn { display: flex; align-items: center; gap: 10px; color: #f87171; text-decoration: none; font-size: 0.85rem; font-weight: 500; padding: 9px 12px; border-radius: 8px; transition: background 0.2s; }
.logout-btn:hover { background: rgba(239,68,68,0.1); }

/* ─── OVERLAY ─── */
.sidebar-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.5); z-index: 199;
  opacity: 0; pointer-events: none;
  transition: opacity 0.3s ease;
  backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px);
}
.sidebar-overlay.visible { opacity: 1; pointer-events: auto; }

/* ─── MAIN ─── */
.main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* ─── TOPBAR ─── */
.topbar {
  background: #fff; border-bottom: 1px solid var(--border);
  padding: 14px 30px; display: flex; align-items: center;
  justify-content: space-between; gap: 16px; flex-wrap: wrap;
  position: sticky; top: 0; z-index: 210;
}
.topbar-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.topbar-title h1 { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); }
.topbar-title p  { font-size: 0.75rem; color: var(--text-secondary); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.date-pill { display: flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid var(--border); padding: 6px 13px; border-radius: 99px; font-size: 0.75rem; color: var(--text-secondary); font-weight: 500; }
.admin-pill { display: flex; align-items: center; gap: 9px; background: #f8fafc; border: 1px solid var(--border); padding: 6px 14px 6px 7px; border-radius: 99px; }
.admin-avatar { width: 28px; height: 28px; background: var(--sidebar-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.78rem; font-weight: 700; }
.admin-pill span { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); }

/* ─── HAMBURGER ─── */
.hamburger {
  display: none; flex-direction: column; justify-content: center; align-items: center;
  gap: 5px; width: 36px; height: 36px; border: 1px solid var(--border);
  border-radius: 9px; background: #f8fafc; cursor: pointer;
  flex-shrink: 0; transition: background 0.2s, border-color 0.2s; padding: 0;
}
.hamburger:hover { background: #f1f5f9; border-color: #cbd5e1; }
.hamburger span { display: block; width: 16px; height: 2px; background: var(--text-primary); border-radius: 2px; transition: transform 0.3s ease, opacity 0.3s ease, width 0.3s ease; transform-origin: center; }
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; width: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ─── CONTENT ─── */
.content { padding: 26px 30px; flex: 1; }

.message { display: flex; align-items: center; gap: 10px; border-radius: var(--radius); padding: 11px 16px; margin-bottom: 18px; font-size: 0.82rem; font-weight: 600; animation: fadeUp 0.3s ease both; border: 1px solid transparent; }
.message.success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.message.error   { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }

.status-counts { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 18px; }
.count-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; animation: fadeUp 0.4s ease both; }
.count-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 9px; }
.count-chip { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; padding: 3px 8px; border-radius: 99px; }
.chip-pending   { background: #fffbeb; color: #92400e; }
.chip-preparing { background: #eff6ff; color: #1d4ed8; }
.chip-ready     { background: #f0fdf4; color: #15803d; }
.chip-completed { background: #f0fdfa; color: #0f766e; }
.chip-cancelled { background: #fff1f2; color: #be123c; }
.count-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.ci-pending   { background: #fffbeb; color: #92400e; } .ci-preparing { background: #eff6ff; color: #1d4ed8; }
.ci-ready     { background: #f0fdf4; color: #15803d; } .ci-completed { background: #f0fdfa; color: #0f766e; }
.ci-cancelled { background: #fff1f2; color: #be123c; }
.count-num { font-family: 'Space Mono', monospace; font-size: 1.35rem; font-weight: 700; color: var(--text-primary); line-height: 1.1; }
.count-label { font-size: 0.72rem; color: var(--text-muted); margin-top: 3px; font-weight: 500; }

.filter-tabs { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-tab { display: inline-flex; align-items: center; gap: 6px; padding: 7px 13px; border-radius: 99px; text-decoration: none; font-size: 0.76rem; font-weight: 700; border: 1px solid var(--border); color: var(--text-secondary); background: #fff; transition: all 0.2s ease; }
.filter-tab:hover { border-color: #bfdbfe; color: var(--blue); background: #eff6ff; }
.filter-tab.active { background: var(--blue); border-color: var(--blue); color: #fff; }

.card { background: #fff; border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
th { text-align: left; padding: 11px 13px; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em; border-bottom: 1px solid var(--border); background: #f8fafc; }
td { padding: 12px 13px; font-size: 0.82rem; color: var(--text-primary); border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f8fafc; }

.status-badge { display: inline-block; padding: 4px 10px; border-radius: 99px; font-size: 0.68rem; font-weight: 700; }
.status-Pending   { background: #fffbeb; color: #92400e; }
.status-Preparing { background: #eff6ff; color: #1d4ed8; }
.status-Ready     { background: #f0fdf4; color: #15803d; }
.status-Completed { background: #f0fdfa; color: #0f766e; }
.status-Cancelled { background: #fff1f2; color: #be123c; }

.status-form { display: flex; align-items: center; gap: 8px; }
.status-select { padding: 7px 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.78rem; color: var(--text-primary); cursor: pointer; background: #fff; font-family: inherit; }
.status-select:focus { border-color: #bfdbfe; outline: none; box-shadow: 0 0 0 3px #eff6ff; }
.update-btn { background: var(--blue); color: #fff; border: none; padding: 7px 11px; border-radius: 8px; font-size: 0.76rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.update-btn:hover { background: #2563eb; }
.view-items-btn { display: inline-flex; align-items: center; gap: 5px; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 6px 10px; border-radius: 8px; font-size: 0.74rem; font-weight: 700; cursor: pointer; transition: all 0.2s; }
.view-items-btn:hover { background: #dbeafe; }

/* ─── MODAL ─── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.show { display: flex; }
.modal-box { background: #fff; border-radius: 14px; max-width: 520px; width: 100%; max-height: 80vh; overflow-y: auto; border: 1px solid var(--border); animation: fadeUp 0.25s ease; box-shadow: 0 20px 60px rgba(2,6,23,0.25); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 15px 17px; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: #fff; }
.modal-header h3 { font-size: 0.9rem; font-weight: 700; }
.modal-close-btn { background: #f8fafc; border: 1px solid var(--border); width: 30px; height: 30px; border-radius: 50%; cursor: pointer; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; }
.modal-close-btn:hover { background: #eff6ff; color: var(--blue); }
.modal-items { padding: 10px 17px 16px; }
.order-item-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.82rem; }
.order-item-row:last-child { border-bottom: none; }
.order-total-row { display: flex; justify-content: space-between; padding: 13px 0 0; font-weight: 700; font-size: 0.9rem; border-top: 1px solid var(--border); margin-top: 10px; }

.footer { padding: 14px 30px; border-top: 1px solid var(--border); text-align: center; font-size: 0.72rem; color: var(--text-muted); }

/* ─── RESPONSIVE ─── */
@media (max-width: 1200px) { .status-counts { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 900px)  { .status-counts { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); box-shadow: none; }
  .sidebar.open { transform: translateX(0); box-shadow: 8px 0 32px rgba(0,0,0,0.25); }
  .sidebar-overlay { display: block; }
  .main { margin-left: 0; }
  body.sidebar-open { overflow: hidden; }
  .hamburger { display: flex; }
  .content { padding: 16px; }
  .topbar, .footer { padding: 12px 16px; }
  .date-pill { display: none; }
}
</style>
</head>
<body>

<!-- ─── OVERLAY ─── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ─── SIDEBAR ─── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><?= icon('coffee', 20) ?></div>
    <h2>Cafe Digital</h2>
    <p>Admin Panel</p>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a href="admin_dashboard.php" class="nav-link"><span class="nav-icon"><?= icon('home') ?></span> Dashboard</a>
    <div class="nav-section-label">Catalogue</div>
    <a href="admin_menu_add.php"  class="nav-link"><span class="nav-icon"><?= icon('plus') ?></span> Add Menu</a>
        <a href="admin_menu_list.php" class="nav-link">
      <span class="nav-icon"><?= icon('file') ?></span> View / Edit Menu
    </a>
    <a href="inventory.php" class="nav-link">
      <span class="nav-icon"><?= icon('box') ?></span> Inventory
    </a>

    <div class="nav-section-label">Operations</div>
    <a href="admin_orders.php"  class="nav-link active"><span class="nav-icon"><?= icon('cart') ?></span> Manage Orders</a>
    <a href="admin_coupons.php" class="nav-link"><span class="nav-icon"><?= icon('tag') ?></span> Manage Coupons</a>
    <div class="nav-section-label">Analytics</div>
    <a href="admin_reports.php" class="nav-link"><span class="nav-icon"><?= icon('bars') ?></span> Sales Report</a>
  </nav>
  <div class="sidebar-footer">
    <a href="admin_logout.php" class="logout-btn"><?= icon('logout') ?> Logout</a>
  </div>
</aside>

<!-- ─── MAIN ─── -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <div class="topbar-title">
        <h1>Manage Orders</h1>
        <p>Track order progress and update statuses quickly.</p>
      </div>
    </div>
    <div class="topbar-right">
      <div class="date-pill"><?= icon('calendar', 14) ?> <?= date('D, d M Y') ?></div>
      <div class="admin-pill">
        <div class="admin-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
        <span><?= htmlspecialchars($admin_name) ?></span>
      </div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <?php if ($message): ?>
      <div class="message <?= $messageType ?>"><?= icon($messageType === 'success' ? 'check' : 'xcircle', 16) ?> <?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- STATUS COUNTS -->
    <div class="status-counts">
      <div class="count-card">
        <div class="count-top"><span class="count-chip chip-pending">Pending</span><span class="count-icon ci-pending"><?= icon('clock', 16) ?></span></div>
        <div class="count-num"><?= $counts['Pending'] ?? 0 ?></div>
        <div class="count-label">Awaiting action</div>
      </div>
      <div class="count-card">
        <div class="count-top"><span class="count-chip chip-preparing">Preparing</span><span class="count-icon ci-preparing"><?= icon('flame', 16) ?></span></div>
        <div class="count-num"><?= $counts['Preparing'] ?? 0 ?></div>
        <div class="count-label">In kitchen</div>
      </div>
      <div class="count-card">
        <div class="count-top"><span class="count-chip chip-ready">Ready</span><span class="count-icon ci-ready"><?= icon('check', 16) ?></span></div>
        <div class="count-num"><?= $counts['Ready'] ?? 0 ?></div>
        <div class="count-label">Ready to serve</div>
      </div>
      <div class="count-card">
        <div class="count-top"><span class="count-chip chip-completed">Completed</span><span class="count-icon ci-completed"><?= icon('clipcheck', 16) ?></span></div>
        <div class="count-num"><?= $counts['Completed'] ?? 0 ?></div>
        <div class="count-label">Finished orders</div>
      </div>
      <div class="count-card">
        <div class="count-top"><span class="count-chip chip-cancelled">Cancelled</span><span class="count-icon ci-cancelled"><?= icon('xcircle', 16) ?></span></div>
        <div class="count-num"><?= $counts['Cancelled'] ?? 0 ?></div>
        <div class="count-label">Cancelled orders</div>
      </div>
    </div>

    <!-- FILTER TABS -->
    <div class="filter-tabs">
      <a href="?status=all"       class="filter-tab <?= $filterStatus === 'all'       ? 'active' : '' ?>">All Orders</a>
      <a href="?status=Pending"   class="filter-tab <?= $filterStatus === 'Pending'   ? 'active' : '' ?>"><?= icon('clock', 13) ?> Pending</a>
      <a href="?status=Preparing" class="filter-tab <?= $filterStatus === 'Preparing' ? 'active' : '' ?>"><?= icon('flame', 13) ?> Preparing</a>
      <a href="?status=Ready"     class="filter-tab <?= $filterStatus === 'Ready'     ? 'active' : '' ?>"><?= icon('check', 13) ?> Ready</a>
      <a href="?status=Completed" class="filter-tab <?= $filterStatus === 'Completed' ? 'active' : '' ?>"><?= icon('clipcheck', 13) ?> Completed</a>
      <a href="?status=Cancelled" class="filter-tab <?= $filterStatus === 'Cancelled' ? 'active' : '' ?>"><?= icon('xcircle', 13) ?> Cancelled</a>
    </div>

    <!-- ORDERS TABLE -->
    <div class="card">
      <table>
        <tr>
          <th>Order ID</th><th>Customer</th><th>Date</th>
          <th>Total</th><th>Items</th><th>Status</th><th>Update</th>
        </tr>
        <?php if ($orders): ?>
          <?php foreach ($orders as $order): ?>
          <tr>
            <td><b>#<?= $order['order_id'] ?></b></td>
            <td><?= htmlspecialchars($order['customer_name']) ?></td>
            <td><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></td>
<td style="font-family:'Space Mono',monospace;">RM <?= number_format($order['total_price'], 2) ?></td>
            <td><button class="view-items-btn" onclick="viewItems(<?= $order['order_id'] ?>)"><?= icon('search', 12) ?> View Items</button></td>
            <td><span class="status-badge status-<?= $order['status'] ?>"><?= htmlspecialchars($order['status']) ?></span></td>
            <td>
              <form method="post" class="status-form">
                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                <select name="status" class="status-select">
                  <option value="Pending"   <?= $order['status'] === 'Pending'   ? 'selected' : '' ?>>Pending</option>
                  <option value="Preparing" <?= $order['status'] === 'Preparing' ? 'selected' : '' ?>>Preparing</option>
                  <option value="Ready"     <?= $order['status'] === 'Ready'     ? 'selected' : '' ?>>Ready</option>
                  <option value="Completed" <?= $order['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                  <option value="Cancelled" <?= $order['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <button type="submit" class="update-btn">Update</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:30px;">No orders found.</td></tr>
        <?php endif; ?>
      </table>
    </div>

  </div><!-- /content -->

  <div class="footer">Cafe Digital Admin Panel &nbsp;|&nbsp; © <?= date('Y') ?></div>
</div>

<!-- ORDER ITEMS MODAL -->
<div class="modal-overlay" id="itemsModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalOrderTitle">Order Items</h3>
      <button type="button" class="modal-close-btn" onclick="closeModal()"><?= icon('x', 15) ?></button>
    </div>
    <div id="modalItemsContent" class="modal-items">
      <p style="text-align:center;color:#94a3b8;">Loading...</p>
    </div>
  </div>
</div>

<script>
// ─── SIDEBAR TOGGLE ───────────────────────────────────────────
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('sidebarOverlay');
const hamburger = document.getElementById('hamburger');

function openSidebar() {
  sidebar.classList.add('open');
  overlay.classList.add('visible');
  hamburger.classList.add('open');
  hamburger.setAttribute('aria-expanded', 'true');
  document.body.classList.add('sidebar-open');
}

function closeSidebar() {
  sidebar.classList.remove('open');
  overlay.classList.remove('visible');
  hamburger.classList.remove('open');
  hamburger.setAttribute('aria-expanded', 'false');
  document.body.classList.remove('sidebar-open');
}

hamburger.addEventListener('click', () => {
  sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
});

overlay.addEventListener('click', closeSidebar);

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    if (document.getElementById('itemsModal').classList.contains('show')) closeModal();
    else closeSidebar();
  }
});

sidebar.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
});

// ─── ORDER ITEMS MODAL ────────────────────────────────────────
function viewItems(orderId) {
  document.getElementById('modalOrderTitle').textContent = 'Order #' + orderId + ' Items';
  document.getElementById('modalItemsContent').innerHTML = '<p style="text-align:center;color:#94a3b8;">Loading...</p>';
  document.getElementById('itemsModal').classList.add('show');

  fetch('get_order_items.php?order_id=' + orderId)
    .then(res => res.json())
    .then(data => {
      if (data.items && data.items.length > 0) {
        let html = '';
        data.items.forEach(item => {
          html += `<div class="order-item-row"><span>${item.menu_name} x${item.quantity}</span><span>RM ${parseFloat(item.subtotal).toFixed(2)}</span></div>`;
        });
        html += `<div class="order-total-row"><span>Total</span><span>RM ${parseFloat(data.total).toFixed(2)}</span></div>`;
        document.getElementById('modalItemsContent').innerHTML = html;
      } else {
        document.getElementById('modalItemsContent').innerHTML = '<p style="text-align:center;color:#94a3b8;">No items found.</p>';
      }
    })
    .catch(() => {
      document.getElementById('modalItemsContent').innerHTML = '<p style="text-align:center;color:#be123c;">Error loading items.</p>';
    });
}

function closeModal() {
  document.getElementById('itemsModal').classList.remove('show');
}

document.getElementById('itemsModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>