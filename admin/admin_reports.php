<?php
require __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? $_SESSION['name'] ?? 'Admin';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

try {
    $totalSales = $pdo->prepare("SELECT COALESCE(SUM(total_price), 0) FROM `order` WHERE status = 'Completed' AND DATE(created_at) BETWEEN ? AND ?");
    $totalSales->execute([$dateFrom, $dateTo]);
    $totalSalesAmt = $totalSales->fetchColumn();

    $totalOrders = $pdo->prepare("SELECT COUNT(*) FROM `order` WHERE DATE(created_at) BETWEEN ? AND ?");
    $totalOrders->execute([$dateFrom, $dateTo]);
    $totalOrdersCount = $totalOrders->fetchColumn();

    $completedOrders = $pdo->prepare("SELECT COUNT(*) FROM `order` WHERE status = 'Completed' AND DATE(created_at) BETWEEN ? AND ?");
    $completedOrders->execute([$dateFrom, $dateTo]);
    $completedCount = $completedOrders->fetchColumn();

    $cancelledOrders = $pdo->prepare("SELECT COUNT(*) FROM `order` WHERE status = 'Cancelled' AND DATE(created_at) BETWEEN ? AND ?");
    $cancelledOrders->execute([$dateFrom, $dateTo]);
    $cancelledCount = $cancelledOrders->fetchColumn();

    $avgOrder = $completedCount > 0 ? $totalSalesAmt / $completedCount : 0;

    $dailySales = $pdo->prepare("SELECT DATE(created_at) as sale_date, COUNT(*) as order_count, SUM(total_price) as daily_total FROM `order` WHERE status = 'Completed' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY sale_date ASC");
    $dailySales->execute([$dateFrom, $dateTo]);
    $dailyData = $dailySales->fetchAll(PDO::FETCH_ASSOC);

    $topItems = $pdo->prepare("SELECT m.menu_name, SUM(om.quantity) as total_qty, SUM(om.quantity * m.price) as total_revenue FROM order_menu om JOIN menu m ON om.menu_id = m.menu_id JOIN `order` o ON om.order_id = o.order_id WHERE o.status = 'Completed' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY m.menu_id, m.menu_name ORDER BY total_qty DESC LIMIT 5");
    $topItems->execute([$dateFrom, $dateTo]);
    $topItemsData = $topItems->fetchAll(PDO::FETCH_ASSOC);

    $categoryData = $pdo->prepare("SELECT m.category, SUM(om.quantity) as total_qty, SUM(om.quantity * m.price) as total_revenue FROM order_menu om JOIN menu m ON om.menu_id = m.menu_id JOIN `order` o ON om.order_id = o.order_id WHERE o.status = 'Completed' AND DATE(o.created_at) BETWEEN ? AND ? GROUP BY m.category ORDER BY total_revenue DESC");
    $categoryData->execute([$dateFrom, $dateTo]);
    $categoryStats = $categoryData->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$chartLabels  = json_encode(array_map(fn($d) => date('d M', strtotime($d['sale_date'])), $dailyData));
$chartRevenue = json_encode(array_map(fn($d) => round($d['daily_total'], 2), $dailyData));
$chartOrders  = json_encode(array_map(fn($d) => (int)$d['order_count'], $dailyData));

function icon($name, $size = 18) {
    $paths = [
        'coffee'   => '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',
        'home'     => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'cart'     => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'tag'      => '<path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'bars'     => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'wallet'   => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'save'     => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>',
        'trophy'   => '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0Z"/><path d="M17 5h3a2 2 0 0 1-2 4h-1"/><path d="M7 5H4a2 2 0 0 0 2 4h1"/>',
        'printer'  => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'trending' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'utensils' => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
        'cake'     => '<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 21h16"/><path d="M12 3v4"/><path d="M7 11V9a5 5 0 0 1 10 0v2"/>',
        'folder'   => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
    ];
    if (!isset($paths[$name])) return '';
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths[$name].'</svg>';
}

function categoryIcon($cat, $size = 14) {
    return match(strtolower($cat ?? '')) {
        'food'      => icon('utensils', $size),
        'beverages' => icon('coffee', $size),
        'dessert'   => icon('cake', $size),
        default     => icon('utensils', $size),
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sales Report — Cafe Digital</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root {
  --sidebar-bg:#0f1629; --sidebar-accent:#3b82f6; --sidebar-text:#94a3b8;
  --sidebar-hover:rgba(255,255,255,0.06); --body-bg:#f1f5f9; --card-bg:#fff;
  --border:#e2e8f0; --text-primary:#0f172a; --text-secondary:#64748b; --text-muted:#94a3b8;
  --blue:#3b82f6; --green:#10b981; --orange:#f59e0b; --red:#ef4444; --purple:#8b5cf6;
  --sidebar-width:240px; --radius:12px;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'DM Sans',sans-serif; background:var(--body-bg); color:var(--text-primary); min-height:100vh; display:flex; }

/* ─── SIDEBAR ─── */
.sidebar {
  position:fixed; top:0; left:0; width:var(--sidebar-width); height:100vh;
  background:var(--sidebar-bg); display:flex; flex-direction:column;
  z-index:200; overflow-y:auto;
  transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);
}
.sidebar-brand { padding:28px 22px 22px; border-bottom:1px solid rgba(255,255,255,0.07); }
.brand-icon { width:38px;height:38px;background:var(--sidebar-accent);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;margin-bottom:10px; }
.sidebar-brand h2 { font-size:.95rem;font-weight:700;color:#fff;letter-spacing:.01em; }
.sidebar-brand p  { font-size:.72rem;color:var(--sidebar-text);margin-top:2px; }
.sidebar-nav { padding:18px 0; flex:1; }
.nav-section-label { font-size:.65rem;font-weight:600;color:#475569;text-transform:uppercase;letter-spacing:.1em;padding:14px 22px 6px; }
.nav-link { display:flex;align-items:center;gap:11px;padding:10px 22px;color:var(--sidebar-text);text-decoration:none;font-size:.85rem;font-weight:500;transition:all .2s;position:relative; }
.nav-link:hover { background:var(--sidebar-hover);color:#fff; }
.nav-link.active { background:rgba(59,130,246,.15);color:var(--sidebar-accent); }
.nav-link.active::before { content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--sidebar-accent);border-radius:0 3px 3px 0; }
.nav-icon { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:rgba(255,255,255,.04);transition:background .2s; }
.nav-link.active .nav-icon { background:rgba(59,130,246,.2); }
.nav-link:hover  .nav-icon { background:rgba(255,255,255,.08); }
.sidebar-footer { padding:16px 22px;border-top:1px solid rgba(255,255,255,.07); }
.logout-btn { display:flex;align-items:center;gap:10px;color:#f87171;text-decoration:none;font-size:.85rem;font-weight:500;padding:9px 12px;border-radius:8px;transition:background .2s; }
.logout-btn:hover { background:rgba(239,68,68,.1); }

/* ─── OVERLAY ─── */
.sidebar-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.5);
  z-index:199; opacity:0; pointer-events:none;
  transition:opacity .3s ease; backdrop-filter:blur(2px); -webkit-backdrop-filter:blur(2px);
}
.sidebar-overlay.visible { opacity:1; pointer-events:auto; }

/* ─── MAIN ─── */
.main { margin-left:var(--sidebar-width); flex:1; display:flex; flex-direction:column; min-height:100vh; }

/* ─── TOPBAR ─── */
.topbar {
  background:#fff; border-bottom:1px solid var(--border);
  padding:14px 30px; display:flex; align-items:center;
  justify-content:space-between; gap:16px; flex-wrap:wrap;
  position:sticky; top:0; z-index:210;
}
.topbar-left { display:flex; align-items:center; gap:12px; flex:1; min-width:0; }
.topbar-title h1 { font-size:1.05rem;font-weight:700;color:var(--text-primary); }
.topbar-title p  { font-size:.75rem;color:var(--text-secondary);margin-top:1px; }
.topbar-right { display:flex;align-items:center;gap:10px; }

/* ─── HAMBURGER ─── */
.hamburger {
  display:none; flex-direction:column; justify-content:center; align-items:center;
  gap:5px; width:36px; height:36px; border:1px solid var(--border);
  border-radius:9px; background:#f8fafc; cursor:pointer;
  flex-shrink:0; transition:background .2s,border-color .2s; padding:0;
}
.hamburger:hover { background:#f1f5f9;border-color:#cbd5e1; }
.hamburger span { display:block;width:16px;height:2px;background:var(--text-primary);border-radius:2px;transition:transform .3s ease,opacity .3s ease,width .3s ease;transform-origin:center; }
.hamburger.open span:nth-child(1) { transform:translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity:0;width:0; }
.hamburger.open span:nth-child(3) { transform:translateY(-7px) rotate(-45deg); }

/* ─── FILTER BAR ─── */
.filter-bar { background:#fff;border-bottom:1px solid var(--border);padding:12px 30px;display:flex;align-items:center;gap:14px;flex-wrap:wrap; }
.filter-group { display:flex;align-items:center;gap:8px; }
.filter-label { font-size:.75rem;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;white-space:nowrap; }
.filter-input { padding:7px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:.82rem;font-family:'DM Sans',sans-serif;color:var(--text-primary);background:#f8fafc;transition:border-color .2s;outline:none; }
.filter-input:focus { border-color:var(--blue);background:#fff; }

.btn { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:.82rem;font-weight:600;font-family:'DM Sans',sans-serif;cursor:pointer;border:none;text-decoration:none;transition:all .2s; }
.btn-primary { background:var(--blue);color:#fff; }
.btn-primary:hover { background:#2563eb; }
.btn-success { background:var(--green);color:#fff; }
.btn-success:hover { background:#059669; }
.btn-ghost { background:transparent;color:var(--text-secondary);border:1.5px solid var(--border); }
.btn-ghost:hover { background:#f8fafc;color:var(--text-primary); }

/* ─── CONTENT ─── */
.content { padding:28px 30px; flex:1; }

.period-badge { display:inline-flex;align-items:center;gap:7px;background:#eff6ff;color:var(--blue);font-size:.78rem;font-weight:600;padding:5px 13px;border-radius:20px;border:1px solid #bfdbfe;margin-bottom:22px; }

.stat-row { display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px; }
.stat-card { background:var(--card-bg);border-radius:var(--radius);padding:20px;border:1px solid var(--border);position:relative;overflow:hidden;transition:box-shadow .2s,transform .2s; }
.stat-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.07);transform:translateY(-2px); }
.stat-card::before { content:'';position:absolute;top:0;left:0;right:0;height:3px; }
.stat-card.blue::before   { background:var(--blue); }
.stat-card.green::before  { background:var(--green); }
.stat-card.orange::before { background:var(--orange); }
.stat-card.purple::before { background:var(--purple); }
.stat-top { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px; }
.stat-chip { font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:3px 9px;border-radius:20px; }
.stat-chip.blue{background:#eff6ff;color:var(--blue)} .stat-chip.green{background:#f0fdf4;color:var(--green)}
.stat-chip.orange{background:#fffbeb;color:var(--orange)} .stat-chip.purple{background:#f5f3ff;color:var(--purple)}
.stat-icon-circle { width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center; }
.stat-icon-circle.blue{background:#eff6ff;color:var(--blue)} .stat-icon-circle.green{background:#f0fdf4;color:var(--green)}
.stat-icon-circle.orange{background:#fffbeb;color:var(--orange)} .stat-icon-circle.purple{background:#f5f3ff;color:var(--purple)}
.stat-value { font-family:'Space Mono',monospace;font-size:1.55rem;font-weight:700;color:var(--text-primary);line-height:1.1; }
.stat-label { font-size:.75rem;color:var(--text-muted);margin-top:4px;font-weight:500; }

.chart-card { background:var(--card-bg);border-radius:var(--radius);border:1px solid var(--border);padding:22px 24px;margin-bottom:20px; }
.card-header { display:flex;align-items:center;justify-content:space-between;margin-bottom:20px; }
.card-title { font-size:.88rem;font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:7px; }
.card-title svg { color:var(--text-muted);flex-shrink:0; }
.card-subtitle { font-size:.72rem;color:var(--text-muted);margin-top:2px; }
.chart-legend { display:flex;gap:16px; }
.legend-item { display:flex;align-items:center;gap:6px;font-size:.75rem;color:var(--text-secondary);font-weight:500; }
.legend-dot { width:8px;height:8px;border-radius:50%; }

.two-col { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px; }

table { width:100%;border-collapse:collapse; }
thead th { text-align:left;padding:9px 14px;font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;background:#f8fafc;border-bottom:1px solid var(--border); }
tbody td { padding:11px 14px;font-size:.83rem;color:var(--text-primary);border-bottom:1px solid #f1f5f9; }
tbody tr:last-child td { border-bottom:none; }
tbody tr:hover td { background:#f8fafc; }
.tfoot-row td { background:#f1f5f9!important;font-weight:700;border-top:2px solid var(--border);border-bottom:none!important; }

.rank { width:24px;height:24px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700; }
.rank-1{background:#fef3c7;color:#92400e} .rank-2{background:#f1f5f9;color:#475569}
.rank-3{background:#fef9c3;color:#a16207} .rank-4,.rank-5{background:#f1f5f9;color:#94a3b8}

.cat-wrap { margin-bottom:18px; }
.cat-top { display:flex;justify-content:space-between;align-items:center;margin-bottom:6px; }
.cat-name { font-size:.82rem;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:5px; }
.cat-value { font-family:'Space Mono',monospace;font-size:.78rem;font-weight:700;color:var(--text-primary); }
.bar-track { height:7px;background:#f1f5f9;border-radius:99px;overflow:hidden; }
.bar-fill { height:100%;border-radius:99px;background:var(--blue);transition:width .6s cubic-bezier(.4,0,.2,1); }
.cat-name svg { flex-shrink:0;color:var(--text-muted); }
.cat-qty { font-size:.7rem;color:var(--text-muted);margin-top:3px; }

.perf-bar-track { height:5px;background:#f1f5f9;border-radius:99px;overflow:hidden;width:120px; }
.perf-bar-fill { height:100%;border-radius:99px;background:linear-gradient(90deg,var(--blue),#818cf8); }

.save-card { background:linear-gradient(135deg,#eff6ff 0%,#f0fdf4 100%);border:1px solid #bfdbfe;border-radius:var(--radius);padding:22px 24px;display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px; }
.period-badge svg { flex-shrink:0; }
.save-info h3 { font-size:.9rem;font-weight:700;color:var(--text-primary);margin-bottom:3px;display:flex;align-items:center;gap:7px; }
.save-info p  { font-size:.78rem;color:var(--text-secondary); }

.page-footer { padding:16px 30px;border-top:1px solid var(--border);text-align:center;font-size:.72rem;color:var(--text-muted); }

/* ─── PRINT ─── */
@media print {
  .sidebar,.topbar,.filter-bar,.save-card,.no-print { display:none!important; }
  .main { margin-left:0; }
  .content { padding:20px; }
}

/* ─── RESPONSIVE ─── */
@media (max-width:1100px) { .stat-row{grid-template-columns:repeat(2,1fr)} .two-col{grid-template-columns:1fr} }
@media (max-width:768px) {
  .sidebar { transform:translateX(-100%);box-shadow:none; }
  .sidebar.open { transform:translateX(0);box-shadow:8px 0 32px rgba(0,0,0,.25); }
  .sidebar-overlay { display:block; }
  .main { margin-left:0; }
  body.sidebar-open { overflow:hidden; }
  .hamburger { display:flex; }
  .content { padding:18px; }
  .topbar { padding:12px 16px; }
  .filter-bar { padding:12px 16px; }
  .page-footer { padding:12px 16px; }
  .stat-row { grid-template-columns:repeat(2,1fr); }
  .date-pill { display:none; }
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
    <a href="admin_orders.php"  class="nav-link"><span class="nav-icon"><?= icon('cart') ?></span> Manage Orders</a>
    <a href="admin_coupons.php" class="nav-link"><span class="nav-icon"><?= icon('tag') ?></span> Manage Coupons</a>
    <div class="nav-section-label">Analytics</div>
    <a href="admin_reports.php" class="nav-link active"><span class="nav-icon"><?= icon('bars') ?></span> Sales Report</a>
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
        <h1>Sales Report</h1>
        <p>Sales Analytic System — Cafe Digital</p>
      </div>
    </div>
    <div class="topbar-right">
      <button class="btn btn-success no-print" onclick="window.print()"><?= icon('printer', 14) ?> Print Report</button>
    </div>
  </div>

  <!-- FILTER BAR -->
  <form method="get" class="filter-bar no-print">
    <div class="filter-group">
      <span class="filter-label">From</span>
      <input type="date" name="date_from" class="filter-input" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="filter-group">
      <span class="filter-label">To</span>
      <input type="date" name="date_to" class="filter-input" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <button type="submit" class="btn btn-primary"><?= icon('search', 14) ?> Filter</button>
    <a href="admin_reports.php" class="btn btn-ghost">Reset</a>
  </form>

  <!-- CONTENT -->
  <div class="content">

    <div class="period-badge">
      <?= icon('calendar', 14) ?> Report Period:&nbsp;
      <b><?= date('d M Y', strtotime($dateFrom)) ?></b>
      &nbsp;—&nbsp;
      <b><?= date('d M Y', strtotime($dateTo)) ?></b>
    </div>

    <!-- STAT CARDS -->
    <div class="stat-row">
      <div class="stat-card blue">
        <div class="stat-top"><span class="stat-chip blue">Revenue</span><span class="stat-icon-circle blue"><?= icon('wallet') ?></span></div>
        <div class="stat-value">RM <?= number_format($totalSalesAmt, 2) ?></div>
        <div class="stat-label">Total Sales (Completed)</div>
      </div>
      <div class="stat-card green">
        <div class="stat-top"><span class="stat-chip green">Orders</span><span class="stat-icon-circle green"><?= icon('cart') ?></span></div>
        <div class="stat-value"><?= $totalOrdersCount ?></div>
        <div class="stat-label">Total Orders (All Status)</div>
      </div>
      <div class="stat-card orange">
        <div class="stat-top"><span class="stat-chip orange">Completed</span><span class="stat-icon-circle orange"><?= icon('check') ?></span></div>
        <div class="stat-value"><?= $completedCount ?></div>
        <div class="stat-label">Completed Orders</div>
      </div>
      <div class="stat-card purple">
        <div class="stat-top"><span class="stat-chip purple">Avg Value</span><span class="stat-icon-circle purple"><?= icon('trending') ?></span></div>
        <div class="stat-value">RM <?= number_format($avgOrder, 2) ?></div>
        <div class="stat-label">Avg Order Value</div>
      </div>
    </div>

    <!-- REVENUE CHART -->
    <div class="chart-card">
      <div class="card-header">
        <div>
          <div class="card-title"><?= icon('trending', 16) ?> Daily Revenue Trend</div>
          <div class="card-subtitle">Completed orders revenue over the selected period</div>
        </div>
        <div class="chart-legend">
          <div class="legend-item"><div class="legend-dot" style="background:#3b82f6"></div>Revenue (RM)</div>
          <div class="legend-item"><div class="legend-dot" style="background:#10b981"></div>Orders</div>
        </div>
      </div>
      <canvas id="revenueChart" height="90"></canvas>
    </div>

    <!-- TWO COL -->
    <div class="two-col">
      <div class="chart-card" style="margin-bottom:0">
        <div class="card-header">
          <div><div class="card-title"><?= icon('trophy', 16) ?> Top Selling Items</div><div class="card-subtitle">By quantity sold in period</div></div>
        </div>
        <?php if ($topItemsData): ?>
          <table>
            <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Revenue</th></tr></thead>
            <tbody>
              <?php foreach ($topItemsData as $i => $item): ?>
              <tr>
                <td><span class="rank rank-<?= $i+1 ?>"><?= $i+1 ?></span></td>
                <td><b><?= htmlspecialchars($item['menu_name']) ?></b></td>
                <td><?= $item['total_qty'] ?> pcs</td>
                <td><b>RM <?= number_format($item['total_revenue'], 2) ?></b></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p style="text-align:center;color:var(--text-muted);padding:30px 0;font-size:.85rem;">No data found.</p>
        <?php endif; ?>
      </div>

      <div class="chart-card" style="margin-bottom:0">
        <div class="card-header">
          <div><div class="card-title"><?= icon('folder', 16) ?> Sales by Category</div><div class="card-subtitle">Revenue breakdown by menu category</div></div>
        </div>
        <?php if ($categoryStats):
          $maxRev = max(array_column($categoryStats, 'total_revenue'));
          foreach ($categoryStats as $cat):
            $pct = $maxRev > 0 ? ($cat['total_revenue'] / $maxRev) * 100 : 0;
        ?>
          <div class="cat-wrap">
            <div class="cat-top">
              <div class="cat-name"><?= categoryIcon($cat['category'], 14) ?> <?= ucfirst($cat['category']) ?></div>
              <div class="cat-value">RM <?= number_format($cat['total_revenue'], 2) ?></div>
            </div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
            <div class="cat-qty"><?= $cat['total_qty'] ?> items sold</div>
          </div>
        <?php endforeach; else: ?>
          <p style="text-align:center;color:var(--text-muted);padding:30px 0;font-size:.85rem;">No data found.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- DAILY BREAKDOWN -->
    <div class="chart-card" style="margin-top:20px">
      <div class="card-header">
        <div><div class="card-title"><?= icon('calendar', 16) ?> Daily Sales Breakdown</div><div class="card-subtitle">Per-day order count and revenue</div></div>
      </div>
      <?php if ($dailyData):
        $maxDaily = max(array_column($dailyData, 'daily_total'));
      ?>
        <table>
          <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Avg / Order</th><th>Performance</th></tr></thead>
          <tbody>
            <?php foreach ($dailyData as $day):
              $dayPct = $maxDaily > 0 ? ($day['daily_total'] / $maxDaily) * 100 : 0;
              $avgPer = $day['order_count'] > 0 ? $day['daily_total'] / $day['order_count'] : 0;
            ?>
            <tr>
              <td><b><?= date('d M Y', strtotime($day['sale_date'])) ?></b><div style="font-size:.7rem;color:var(--text-muted);"><?= date('l', strtotime($day['sale_date'])) ?></div></td>
              <td><?= $day['order_count'] ?></td>
              <td><b>RM <?= number_format($day['daily_total'], 2) ?></b></td>
              <td>RM <?= number_format($avgPer, 2) ?></td>
              <td><div class="perf-bar-track"><div class="perf-bar-fill" style="width:<?= $dayPct ?>%"></div></div></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="tfoot-row">
              <td><b>TOTAL</b></td>
              <td><b><?= array_sum(array_column($dailyData,'order_count')) ?> orders</b></td>
              <td><b>RM <?= number_format(array_sum(array_column($dailyData,'daily_total')),2) ?></b></td>
              <td>—</td><td>—</td>
            </tr>
          </tfoot>
        </table>
      <?php else: ?>
        <p style="text-align:center;color:var(--text-muted);padding:30px 0;font-size:.85rem;">No sales data found for this period.</p>
      <?php endif; ?>
    </div>

    <!-- SAVE CARD -->
    <div class="save-card no-print">
      <div class="save-info">
        <h3><?= icon('save', 16) ?> Save Report</h3>
        <p>Archive this report to the database for future reference.</p>
      </div>
      <form method="post" action="save_report.php">
        <input type="hidden" name="date_from"   value="<?= $dateFrom ?>">
        <input type="hidden" name="date_to"     value="<?= $dateTo ?>">
        <input type="hidden" name="total_sales" value="<?= $totalSalesAmt ?>">
        <button type="submit" class="btn btn-success"><?= icon('save', 14) ?> Save Report</button>
      </form>
    </div>

  </div><!-- /content -->

  <div class="page-footer">
    Cafe Digital Admin Panel &nbsp;|&nbsp; © <?= date('Y') ?> &nbsp;|&nbsp;
    Report generated: <?= date('d M Y, H:i') ?>
  </div>
</div>

<!-- ─── CHART JS ─── -->
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
  if (e.key === 'Escape') closeSidebar();
});

sidebar.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
});

// ─── CHART ────────────────────────────────────────────────────
const labels  = <?= $chartLabels  ?: '[]' ?>;
const revenue = <?= $chartRevenue ?: '[]' ?>;
const orders  = <?= $chartOrders  ?: '[]' ?>;

if (labels.length > 0) {
  const ctx = document.getElementById('revenueChart').getContext('2d');
  const gradBlue = ctx.createLinearGradient(0,0,0,260);
  gradBlue.addColorStop(0,'rgba(59,130,246,0.18)');
  gradBlue.addColorStop(1,'rgba(59,130,246,0)');
  const gradGreen = ctx.createLinearGradient(0,0,0,260);
  gradGreen.addColorStop(0,'rgba(16,185,129,0.15)');
  gradGreen.addColorStop(1,'rgba(16,185,129,0)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        { label:'Revenue (RM)', data:revenue, borderColor:'#3b82f6', backgroundColor:gradBlue, borderWidth:2.5, pointBackgroundColor:'#3b82f6', pointBorderColor:'#fff', pointBorderWidth:2, pointRadius:4, tension:0.4, fill:true, yAxisID:'y' },
        { label:'Orders', data:orders, borderColor:'#10b981', backgroundColor:gradGreen, borderWidth:2, pointBackgroundColor:'#10b981', pointBorderColor:'#fff', pointBorderWidth:2, pointRadius:4, tension:0.4, fill:true, yAxisID:'y1' }
      ]
    },
    options: {
      responsive:true,
      interaction:{ mode:'index', intersect:false },
      plugins: {
        legend:{ display:false },
        tooltip:{ backgroundColor:'#0f172a', titleColor:'#94a3b8', bodyColor:'#f1f5f9', padding:12, cornerRadius:8, callbacks:{ label: ctx => ctx.datasetIndex===0 ? ` RM ${ctx.parsed.y.toFixed(2)}` : ` ${ctx.parsed.y} orders` } }
      },
      scales: {
        x: { grid:{color:'#f1f5f9'}, ticks:{color:'#94a3b8',font:{size:11,family:'DM Sans'}} },
        y: { position:'left', grid:{color:'#f1f5f9'}, ticks:{color:'#94a3b8',font:{size:11,family:'DM Sans'},callback:v=>'RM '+v.toLocaleString()} },
        y1:{ position:'right', grid:{drawOnChartArea:false}, ticks:{color:'#10b981',font:{size:11}} }
      }
    }
  });
}
</script>
</body>
</html>