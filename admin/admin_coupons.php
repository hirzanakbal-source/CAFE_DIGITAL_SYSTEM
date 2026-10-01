<?php
require __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name  = $_SESSION['admin_name'] ?? $_SESSION['name'] ?? 'Admin';
$message     = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $code            = strtoupper(trim($_POST['code'] ?? ''));
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $min_order       = floatval($_POST['min_order'] ?? 0);
    $expiry_date     = $_POST['expiry_date'] ?? '';
    $is_active       = isset($_POST['is_active']) ? 1 : 0;

    if (!$code || $discount_amount <= 0 || $discount_amount > 100 || !$expiry_date) {
        $message = 'Please fill in all required fields.';
        $messageType = 'error';
    } else {
        try {
            $pdo->prepare("INSERT INTO coupon (code,discount,min_order,expiry_date,is_active) VALUES (?,?,?,?,?)")
                ->execute([$code,$discount_amount,$min_order,$expiry_date,$is_active]);
            $message = 'Coupon ' . $code . ' created successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = $e->getCode() == 23000 ? 'Coupon code already exists.' : 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $points_per_rm           = floatval($_POST['points_per_rm'] ?? 1);
    $redemption_threshold    = intval($_POST['redemption_threshold'] ?? 100);
    $reward_discount_percent = floatval($_POST['reward_discount_percent'] ?? 10);
    $reward_validity_days    = intval($_POST['reward_validity_days'] ?? 30);
    $reward_min_order        = floatval($_POST['reward_min_order'] ?? 0);
    $is_enabled              = isset($_POST['is_enabled']) ? 1 : 0;

    try {
        $pdo->prepare("
            UPDATE loyalty_settings SET
                points_per_rm = ?,
                redemption_threshold = ?,
                reward_discount_percent = ?,
                reward_validity_days = ?,
                reward_min_order = ?,
                is_enabled = ?
            WHERE setting_id = 1
        ")->execute([
            $points_per_rm, $redemption_threshold, $reward_discount_percent,
            $reward_validity_days, $reward_min_order, $is_enabled
        ]);
        $message = 'Loyalty settings updated successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $messageType = 'error';
    }
}
if (isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM coupon WHERE coupon_id = ?")->execute([intval($_GET['delete'])]);
        $message = 'Coupon deleted successfully!';
        $messageType = 'success';
    } catch (PDOException $e) { $message = 'Error: ' . $e->getMessage(); $messageType = 'error'; }
}

if (isset($_GET['toggle'])) {
    try {
        $pdo->prepare("UPDATE coupon SET is_active = IF(is_active=1,0,1) WHERE coupon_id=?")->execute([intval($_GET['toggle'])]);
        $message = 'Coupon status updated!';
        $messageType = 'success';
    } catch (PDOException $e) { $message = 'Error: ' . $e->getMessage(); $messageType = 'error'; }
}


try {
    $coupons = $pdo->query("SELECT * FROM coupon ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $coupons = []; }

try {
    $loyaltySettings = $pdo->query("SELECT * FROM loyalty_settings WHERE setting_id = 1")->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $loyaltySettings = [
        'points_per_rm' => 1, 'redemption_threshold' => 100,
        'reward_discount_percent' => 10, 'reward_validity_days' => 30,
        'reward_min_order' => 0, 'is_enabled' => 1,
    ];
}

$totalCoupons    = count($coupons);
$activeCoupons   = count(array_filter($coupons, fn($c) => $c['is_active']==1 && strtotime($c['expiry_date'])>=strtotime('today')));
$expiredCoupons  = count(array_filter($coupons, fn($c) => strtotime($c['expiry_date'])<strtotime('today')));
$inactiveCoupons = count(array_filter($coupons, fn($c) => $c['is_active']==0));

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
        'warning'  => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'ban'      => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
        'shuffle'  => '<polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/>',
        'pause'    => '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>',
        'play'     => '<polygon points="5 3 19 12 5 21 5 3"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'bulb'     => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/>',
        'box'      => '<path d="M21 8V21H3V8"/><path d="M1 3h22v5H1z"/><line x1="10" y1="12" x2="14" y2="12"/>',
        ];
    if (!isset($paths[$name])) return '';
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'.$paths[$name].'</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Coupons — Cafe Digital</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
  --sidebar-bg:#0f1629; --sidebar-accent:#3b82f6; --sidebar-text:#94a3b8;
  --sidebar-hover:rgba(255,255,255,0.06); --body-bg:#f1f5f9; --card-bg:#fff;
  --border:#e2e8f0; --text-primary:#0f172a; --text-secondary:#64748b; --text-muted:#94a3b8;
  --blue:#3b82f6; --green:#10b981; --orange:#f59e0b; --red:#ef4444;
  --purple:#8b5cf6; --teal:#14b8a6; --pink:#ec4899; --sidebar-width:240px; --radius:12px;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'DM Sans',sans-serif; background:var(--body-bg); color:var(--text-primary); min-height:100vh; display:flex; }

@keyframes fadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

/* ─── SIDEBAR ─── */
.sidebar {
  position:fixed; top:0; left:0; width:var(--sidebar-width); height:100vh;
  background:var(--sidebar-bg); display:flex; flex-direction:column;
  z-index:200; overflow-y:auto;
  transition:transform 0.3s cubic-bezier(0.4,0,0.2,1);
}
.sidebar-brand { padding:28px 22px 22px; border-bottom:1px solid rgba(255,255,255,0.07); }
.brand-icon { width:38px;height:38px;background:var(--sidebar-accent);border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;margin-bottom:10px; }
.sidebar-brand h2 { font-size:.95rem;font-weight:700;color:#fff; }
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
.topbar-title h1 { font-size:1.05rem;font-weight:700; }
.topbar-title p  { font-size:.75rem;color:var(--text-secondary);margin-top:1px; }
.topbar-right { display:flex;align-items:center;gap:10px; }
.date-pill { display:flex;align-items:center;gap:6px;background:#f8fafc;border:1px solid var(--border);padding:6px 13px;border-radius:99px;font-size:.75rem;color:var(--text-secondary);font-weight:500; }
.admin-pill { display:flex;align-items:center;gap:9px;background:#f8fafc;border:1px solid var(--border);padding:6px 14px 6px 7px;border-radius:99px; }
.admin-avatar { width:28px;height:28px;background:var(--sidebar-accent);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.78rem;font-weight:700; }
.admin-pill span { font-size:.8rem;font-weight:600; }

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

/* ─── CONTENT ─── */
.content { padding:26px 30px; flex:1; }

.message { display:flex;align-items:center;gap:10px;border-radius:var(--radius);padding:11px 16px;margin-bottom:18px;font-size:.82rem;font-weight:600;animation:fadeUp .3s ease both;border:1px solid transparent; }
.message svg { flex-shrink:0; }
.message.success { background:#f0fdf4;border-color:#bbf7d0;color:#166534; }
.message.error   { background:#fff1f2;border-color:#fecdd3;color:#9f1239; }

.stats-row { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px; }
.stat-card { background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:15px; }
.stat-top { display:flex;align-items:center;justify-content:space-between;margin-bottom:10px; }
.stat-chip { font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:3px 8px;border-radius:99px; }
.chip-blue{background:#eff6ff;color:#2563eb} .chip-green{background:#f0fdf4;color:#16a34a}
.chip-red{background:#fff1f2;color:#e11d48}  .chip-gray{background:#f8fafc;color:#64748b}
.stat-icon { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center; }
.si-blue{background:#eff6ff;color:#2563eb} .si-green{background:#f0fdf4;color:#16a34a} .si-red{background:#fff1f2;color:#e11d48} .si-gray{background:#f8fafc;color:#64748b}
.stat-num { font-family:'Space Mono',monospace;font-size:1.35rem;font-weight:700; }
.stat-label { font-size:.72rem;color:var(--text-muted);margin-top:2px; }

.two-col { display:grid;grid-template-columns:360px 1fr;gap:16px;align-items:start; }
.card { background:#fff;border:1px solid var(--border);border-radius:var(--radius);overflow:hidden; }
.card-header { display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;border-bottom:1px solid var(--border); }
.card-title-wrap { display:flex;align-items:center;gap:10px; }
.card-icon { width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#eff6ff;color:var(--blue);flex-shrink:0; }
.card-title { font-size:.85rem;font-weight:700;display:flex;align-items:center;gap:7px; }
.card-title svg { color:var(--text-muted); }
.card-subtitle { font-size:.72rem;color:var(--text-muted);margin-top:2px; }
.card-body { padding:16px; }

.form-group { margin-bottom:14px; }
.form-group label { display:block;font-weight:600;font-size:.8rem;color:var(--text-primary);margin-bottom:6px; }
.form-group label span { color:var(--red); }
.form-group input[type="text"],
.form-group input[type="number"],
.form-group input[type="date"],
.form-group select,
.form-group textarea {
  width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:10px;
  font-size:.82rem;color:var(--text-primary);background:#fff;font-family:inherit;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus {
  border-color:#bfdbfe;outline:none;box-shadow:0 0 0 3px #eff6ff;
}
.form-row { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.checkbox-group { display:flex;align-items:center;gap:9px; }
.checkbox-group input[type="checkbox"] { width:16px;height:16px;accent-color:var(--blue); }
.generate-btn { display:flex;align-items:center;gap:6px;background:#0f172a;color:#fff;border:none;padding:9px 12px;border-radius:10px;font-size:.75rem;font-weight:700;cursor:pointer;white-space:nowrap; }
.generate-btn:hover { background:#1e293b; }
.submit-btn { display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:11px;background:var(--blue);color:#fff;border:none;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;transition:background .2s; }
.submit-btn:hover { background:#2563eb; }
.code-input-wrap { display:flex;gap:8px; }
.code-input-wrap input { flex:1;text-transform:uppercase;letter-spacing:1px;font-weight:700; }

table { width:100%;border-collapse:collapse; }
th { text-align:left;padding:10px 12px;font-size:.67rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;border-bottom:1px solid var(--border);background:#f8fafc; }
td { padding:11px 12px;font-size:.8rem;color:var(--text-primary);border-bottom:1px solid #f1f5f9;vertical-align:middle; }
tr:last-child td { border-bottom:none; }

.code-display { font-family:'Space Mono',monospace;font-size:.76rem;font-weight:700;color:#2563eb;background:#eff6ff;padding:3px 8px;border-radius:6px;border:1px dashed #93c5fd;letter-spacing:1px; }
.discount-badge { display:inline-block;padding:3px 8px;border-radius:99px;font-size:.68rem;font-weight:700; }
.badge-percent{background:#eff6ff;color:#1d4ed8} .badge-fixed{background:#f5f3ff;color:#6d28d9}
.status-badge { display:inline-block;padding:3px 9px;border-radius:99px;font-size:.68rem;font-weight:700; }
.status-active{background:#f0fdf4;color:#15803d} .status-inactive{background:#fff1f2;color:#be123c} .status-expired{background:#f8fafc;color:#64748b}
.action-btns { display:flex;gap:6px;flex-wrap:wrap; }
.btn-toggle,.btn-delete { display:inline-flex;align-items:center;gap:5px;border:none;padding:6px 10px;border-radius:8px;font-size:.72rem;font-weight:700;cursor:pointer;text-decoration:none; }
.btn-toggle { background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe; }
.btn-delete { background:#fff1f2;color:#be123c;border:1px solid #fecdd3; }
.expiry-soon{color:#c2410c;font-weight:600} .expiry-expired{color:#be123c;font-weight:600} .expiry-ok{color:#64748b}

.flow-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:14px; }
.flow-step { padding:13px;border:1px solid var(--border);border-radius:10px;text-align:center;background:#f8fafc; }
.flow-step-num { width:32px;height:32px;border-radius:50%;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;margin:0 auto 8px; }
.flow-step-title { font-size:.78rem;font-weight:700;margin-bottom:4px; }
.flow-step-text { font-size:.72rem;color:var(--text-secondary); }

.empty-state { text-align:center;padding:30px;color:var(--text-muted); }
.empty-state-icon { display:flex;align-items:center;justify-content:center;margin-bottom:10px;color:var(--text-muted); }

.footer { padding:14px 30px;border-top:1px solid var(--border);text-align:center;font-size:.72rem;color:var(--text-muted); }

/* ─── RESPONSIVE ─── */
@media (max-width:1200px) { .stats-row{grid-template-columns:repeat(2,1fr)} .flow-grid{grid-template-columns:repeat(2,1fr)} }
@media (max-width:1024px) { .two-col{grid-template-columns:1fr} }
@media (max-width:768px) {
  .sidebar { transform:translateX(-100%);box-shadow:none; }
  .sidebar.open { transform:translateX(0);box-shadow:8px 0 32px rgba(0,0,0,.25); }
  .sidebar-overlay { display:block; }
  .main { margin-left:0; }
  body.sidebar-open { overflow:hidden; }
  .hamburger { display:flex; }
  .content { padding:16px; }
  .topbar,.footer { padding:12px 16px; }
  .date-pill { display:none; }
  .form-row { grid-template-columns:1fr; }
  .stats-row { grid-template-columns:1fr 1fr; }
  .flow-grid { grid-template-columns:1fr; }
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
    <a href="admin_coupons.php" class="nav-link active"><span class="nav-icon"><?= icon('tag') ?></span> Manage Coupons</a>
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
        <h1>Manage Coupons</h1>
        <p>Create and control discount campaigns for customers.</p>
      </div>
    </div>
    <div class="topbar-right">
      <div class="date-pill"><?= icon('calendar', 14) ?> <?= date('D, d M Y') ?></div>
      <div class="admin-pill">
        <div class="admin-avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
        <span><?= htmlspecialchars($admin_name) ?></span>
      </div>
    </div>
  </div>

  <div class="content">
    <?php if ($message): ?>
      <div class="message <?= $messageType ?>">
        <?= icon($messageType === 'success' ? 'check' : 'warning', 16) ?>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <div class="stats-row">
      <div class="stat-card"><div class="stat-top"><span class="stat-chip chip-blue">Total</span><span class="stat-icon si-blue"><?= icon('tag', 16) ?></span></div><div class="stat-num"><?= $totalCoupons ?></div><div class="stat-label">Total Coupons</div></div>
      <div class="stat-card"><div class="stat-top"><span class="stat-chip chip-green">Active</span><span class="stat-icon si-green"><?= icon('check', 16) ?></span></div><div class="stat-num"><?= $activeCoupons ?></div><div class="stat-label">Currently usable</div></div>
      <div class="stat-card"><div class="stat-top"><span class="stat-chip chip-red">Expired</span><span class="stat-icon si-red"><?= icon('clock', 16) ?></span></div><div class="stat-num"><?= $expiredCoupons ?></div><div class="stat-label">Past expiry date</div></div>
      <div class="stat-card"><div class="stat-top"><span class="stat-chip chip-gray">Inactive</span><span class="stat-icon si-gray"><?= icon('ban', 16) ?></span></div><div class="stat-num"><?= $inactiveCoupons ?></div><div class="stat-label">Disabled manually</div></div>
    </div>

        <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <div class="card-title-wrap">
          <span class="card-icon"><?= icon('bulb', 18) ?></span>
          <div>
            <div class="card-title">Loyalty Points Settings</div>
            <div class="card-subtitle">Control how customers earn points and unlock reward coupons.</div>
          </div>
        </div>
      </div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="update_settings">
          <div class="form-row">
            <div class="form-group">
              <label>Points per RM Spent</label>
              <input type="number" name="points_per_rm" step="0.01" min="0"
                     value="<?= htmlspecialchars($loyaltySettings['points_per_rm']) ?>">
            </div>
            <div class="form-group">
              <label>Points Needed for Reward</label>
              <input type="number" name="redemption_threshold" min="1"
                     value="<?= htmlspecialchars($loyaltySettings['redemption_threshold']) ?>">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Reward Discount (%)</label>
              <input type="number" name="reward_discount_percent" step="0.01" min="0" max="100"
                     value="<?= htmlspecialchars($loyaltySettings['reward_discount_percent']) ?>">
            </div>
            <div class="form-group">
              <label>Reward Validity (days)</label>
              <input type="number" name="reward_validity_days" min="1"
                     value="<?= htmlspecialchars($loyaltySettings['reward_validity_days']) ?>">
            </div>
          </div>
          <div class="form-group">
            <label>Minimum Order for Reward Coupon (RM)</label>
            <input type="number" name="reward_min_order" step="0.01" min="0"
                   value="<?= htmlspecialchars($loyaltySettings['reward_min_order']) ?>">
          </div>
          <div class="form-group">
            <div class="checkbox-group">
              <input type="checkbox" name="is_enabled" id="loyaltyEnabled" <?= $loyaltySettings['is_enabled'] ? 'checked' : '' ?>>
              <label for="loyaltyEnabled">Loyalty system enabled</label>
            </div>
          </div>
          <button type="submit" class="submit-btn"><?= icon('check', 16) ?> <span>Save Settings</span></button>
        </form>
      </div>
    </div>

    <div class="two-col">
  
      <!-- CREATE FORM -->
      <div class="card">
        <div class="card-header">
          <div class="card-title-wrap">
            <span class="card-icon"><?= icon('plus', 18) ?></span>
            <div>
              <div class="card-title">Create New Coupon</div>
              <div class="card-subtitle">Set code, discount, and expiry rules.</div>
            </div>
          </div>
        </div>
        <div class="card-body">
          <form method="post">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
              <label>Coupon Code <span>*</span></label>
              <div class="code-input-wrap">
                <input type="text" name="code" id="couponCode" placeholder="e.g. SAVE10" maxlength="20" required style="text-transform:uppercase;">
                <button type="button" class="generate-btn" onclick="generateCode()"><?= icon('shuffle', 14) ?> Generate</button>
              </div>
            </div>
            <div class="form-group">
  <label>Discount (%) <span>*</span></label>
<input type="number" name="discount_amount" id="discountAmount" placeholder="e.g. 10" step="0.01" min="0.01" max="100" required>
</div>
            <div class="form-row">
              <div class="form-group">
                <label>Min. Order (RM)</label>
                <input type="number" name="min_order" placeholder="e.g. 20.00" step="0.01" min="0" value="0">
              </div>
              <div class="form-group">
                <label>Expiry Date <span>*</span></label>
                <input type="date" name="expiry_date" min="<?= date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <div class="checkbox-group">
                <input type="checkbox" name="is_active" id="isActive" checked>
                <label for="isActive">Active (visible to customers)</label>
              </div>
            </div>
            <button type="submit" class="submit-btn"><?= icon('tag', 16) ?> <span>Create Coupon</span></button>
          </form>
        </div>
      </div>

      <!-- COUPON LIST -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><?= icon('file', 16) ?> All Coupons <span style="font-size:.72rem;color:#94a3b8;font-weight:500;">(<?= $totalCoupons ?> total)</span></div>
        </div>
        <div class="card-body" style="padding:0;">
          <?php if (!empty($coupons)): ?>
            <table>
              <tr><th>Code</th><th>Discount</th><th>Min Order</th><th>Expiry</th><th>Status</th><th>Actions</th></tr>
              <?php foreach ($coupons as $coupon):
                $isExpired = strtotime($coupon['expiry_date']) < strtotime('today');
                $daysLeft  = (int)((strtotime($coupon['expiry_date']) - strtotime('today')) / 86400);
                if ($isExpired)         { $expiryClass='expiry-expired'; $expiryText='Expired'; }
                elseif ($daysLeft <= 7) { $expiryClass='expiry-soon';    $expiryText=$daysLeft.'d left'; }
                else                   { $expiryClass='expiry-ok';       $expiryText=date('d M Y',strtotime($coupon['expiry_date'])); }
                if ($isExpired)              { $statusClass='status-expired';  $statusText='Expired'; }
                elseif ($coupon['is_active']) { $statusClass='status-active';   $statusText='Active'; }
                else                         { $statusClass='status-inactive'; $statusText='Inactive'; }
              ?>
              <tr>
<td>
  <span class="code-display"><?= htmlspecialchars($coupon['code']) ?></span>
</td>
<td>
  <span class="discount-badge badge-percent"><?= $coupon['discount'] ?>% OFF</span>
</td>
                <td><?= $coupon['min_order']>0 ? 'RM '.number_format($coupon['min_order'],2) : '<span style="color:#94a3b8;">None</span>' ?></td>
                <td><span class="<?= $expiryClass ?>"><?= $expiryText ?></span></td>
                <td><span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                <td>
                  <div class="action-btns">
                    <?php if (!$isExpired): ?>
                      <a href="?toggle=<?= $coupon['coupon_id'] ?>" class="btn-toggle"><?= icon($coupon['is_active'] ? 'pause' : 'play', 12) ?> <?= $coupon['is_active'] ? 'Disable' : 'Enable' ?></a>
                    <?php endif; ?>
                    <a href="?delete=<?= $coupon['coupon_id'] ?>" class="btn-delete" onclick="return confirm('Delete coupon <?= addslashes($coupon['code']) ?>?')"><?= icon('trash', 12) ?> Delete</a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </table>
          <?php else: ?>
            <div class="empty-state">
              <div class="empty-state-icon"><?= icon('tag', 36) ?></div>
              <p style="font-weight:600;">No coupons created yet.</p>
              <p style="font-size:.78rem;margin-top:5px;">Create your first coupon using the form on the left.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- HOW IT WORKS -->
    <div class="card" style="margin-top:16px;">
      <div class="card-header"><div class="card-title"><?= icon('bulb', 16) ?> How the Coupon System Works</div></div>
      <div class="card-body">
        <div class="flow-grid">
          <div class="flow-step"><div class="flow-step-num">1</div><div class="flow-step-title">Admin Creates Coupon</div><div class="flow-step-text">Create code, discount and expiry from this panel.</div></div>
          <div class="flow-step"><div class="flow-step-num">2</div><div class="flow-step-title">Customer Logs In</div><div class="flow-step-text">Available coupons can be shown on customer side.</div></div>
          <div class="flow-step"><div class="flow-step-num">3</div><div class="flow-step-title">Customer Copies Code</div><div class="flow-step-text">Customer selects a coupon to apply at checkout.</div></div>
          <div class="flow-step"><div class="flow-step-num">4</div><div class="flow-step-title">Apply in Cart</div><div class="flow-step-text">System validates rule & applies discount instantly.</div></div>
        </div>
        <div style="background:#f8fafc;border-radius:10px;padding:12px 14px;margin-top:14px;display:flex;align-items:center;justify-content:center;gap:10px;flex-wrap:wrap;font-size:.78rem;">
          <span style="background:#0f172a;color:#fff;padding:6px 11px;border-radius:99px;font-weight:700;">Admin Creates</span>
          <span style="color:#94a3b8;">→</span>
          <span style="background:#2563eb;color:#fff;padding:6px 11px;border-radius:99px;font-weight:700;">Customer Sees</span>
          <span style="color:#94a3b8;">→</span>
          <span style="background:#16a34a;color:#fff;padding:6px 11px;border-radius:99px;font-weight:700;">Code Applied</span>
          <span style="color:#94a3b8;">→</span>
          <span style="background:#7c3aed;color:#fff;padding:6px 11px;border-radius:99px;font-weight:700;">Discount Success</span>
        </div>
      </div>
    </div>

  </div>

  <div class="footer">Cafe Digital Admin Panel &nbsp;|&nbsp; © <?= date('Y') ?></div>
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
  if (e.key === 'Escape') closeSidebar();
});

sidebar.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
});

// ─── COUPON HELPERS ───────────────────────────────────────────
function generateCode() {
  const prefix = ['SAVE','CAFE','DEAL','OFF','GET','WIN'][Math.floor(Math.random()*6)];
  const chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let suffix   = '';
  for (let i = 0; i < 4; i++) suffix += chars.charAt(Math.floor(Math.random()*chars.length));
  document.getElementById('couponCode').value = prefix + suffix;
}



document.getElementById('couponCode').addEventListener('input', function() {
  this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
});
</script>
</body>
</html>