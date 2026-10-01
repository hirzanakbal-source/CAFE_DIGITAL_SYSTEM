<?php
require __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? $_SESSION['name'] ?? 'Admin';

try {
    $totalMenu      = $pdo->query("SELECT COUNT(*) FROM menu")->fetchColumn();
    $totalOrders    = $pdo->query("SELECT COUNT(*) FROM `order`")->fetchColumn();
    $totalSales     = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM `order` WHERE status = 'Completed'")->fetchColumn();
    $pendingOrders  = $pdo->query("SELECT COUNT(*) FROM `order` WHERE status = 'Pending'")->fetchColumn();
    $totalCustomers = $pdo->query("SELECT COUNT(*) FROM customer")->fetchColumn();
    $todayOrders    = $pdo->query("SELECT COUNT(*) FROM `order` WHERE DATE(created_at) = CURDATE()")->fetchColumn();

    $tableCheck  = $pdo->query("SHOW TABLES LIKE 'coupon'");
    $tableExists = $tableCheck->rowCount() > 0;

    $totalCoupons  = 0;
    $activeCoupons = 0;
    $recentCoupons = [];

    if ($tableExists) {
        $totalCoupons  = $pdo->query("SELECT COUNT(*) FROM coupon")->fetchColumn();
        $activeCoupons = $pdo->query("SELECT COUNT(*) FROM coupon WHERE is_active = 1 AND expiry_date >= CURDATE()")->fetchColumn();
        $recentCoupons = $pdo->query("SELECT * FROM coupon ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    }

        $recentOrders = $pdo->query("
    SELECT o.order_id, COALESCE(c.full_name, 'Guest') as name,
            o.created_at, o.total_price, o.status
    FROM `order` o
    LEFT JOIN customer c ON o.customer_id = c.customer_id
    ORDER BY o.created_at DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$topItems = $pdo->query("
    SELECT m.menu_name,
           SUM(om.quantity) as total_qty,
           SUM(om.quantity * om.unit_price) as total_revenue
    FROM order_menu om
    JOIN menu m ON om.menu_id = m.menu_id
    GROUP BY om.menu_id, m.menu_name
    ORDER BY total_qty DESC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $totalMenu=$totalOrders=$totalSales=$pendingOrders=$totalCustomers=$todayOrders=0;
    $totalCoupons=$activeCoupons=0;
    $recentCoupons=$recentOrders=$topItems=[];
    $tableExists=false;
}

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
        'wallet'   => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
        'clock'    => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'utensils' => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
        'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'trophy'   => '<path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v5a5 5 0 0 1-10 0Z"/><path d="M17 5h3a2 2 0 0 1-2 4h-1"/><path d="M7 5H4a2 2 0 0 0 2 4h1"/>',
        'warning'  => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'userplus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>',
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
<title>Dashboard — Cafe Digital</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
  --sidebar-bg: #0f1629;
  --sidebar-accent: #3b82f6;
  --sidebar-text: #94a3b8;
  --sidebar-hover: rgba(255,255,255,0.06);
  --body-bg: #f1f5f9;
  --card-bg: #ffffff;
  --border: #e2e8f0;
  --text-primary: #0f172a;
  --text-secondary: #64748b;
  --text-muted: #94a3b8;
  --blue: #3b82f6;
  --green: #10b981;
  --orange: #f59e0b;
  --red: #ef4444;
  --purple: #8b5cf6;
  --teal: #14b8a6;
  --pink: #ec4899;
  --indigo: #6366f1;
  --sidebar-width: 240px;
  --radius: 12px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--body-bg);
  color: var(--text-primary);
  min-height: 100vh;
  display: flex;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

.fade-up { animation: fadeUp 0.4s ease both; }

.sidebar {
  position: fixed;
  top: 0; left: 0;
  width: var(--sidebar-width);
  height: 100vh;
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  z-index: 200;
  overflow-y: auto;
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-brand {
  padding: 28px 22px 22px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}

.brand-icon {
  width: 38px; height: 38px;
  background: var(--sidebar-accent);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  margin-bottom: 10px;
}

.sidebar-brand h2 {
  font-size: 0.95rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.01em;
}

.sidebar-brand p {
  font-size: 0.72rem;
  color: var(--sidebar-text);
  margin-top: 2px;
  letter-spacing: 0.03em;
}

.sidebar-nav { padding: 18px 0; flex: 1; }

.nav-section-label {
  font-size: 0.65rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  padding: 14px 22px 6px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 22px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  transition: all 0.2s;
  position: relative;
}

.nav-link:hover { background: var(--sidebar-hover); color: #fff; }

.nav-link.active {
  background: rgba(59,130,246,0.15);
  color: var(--sidebar-accent);
}

.nav-link.active::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: var(--sidebar-accent);
  border-radius: 0 3px 3px 0;
}

.nav-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  background: rgba(255,255,255,0.04);
  transition: background 0.2s;
}

.nav-link.active .nav-icon  { background: rgba(59,130,246,0.2); }
.nav-link:hover  .nav-icon  { background: rgba(255,255,255,0.08); }

.sidebar-footer {
  padding: 16px 22px;
  border-top: 1px solid rgba(255,255,255,0.07);
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #f87171;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  padding: 9px 12px;
  border-radius: 8px;
  transition: background 0.2s;
}

.logout-btn:hover { background: rgba(239,68,68,0.1); }

.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  z-index: 199;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
}

.sidebar-overlay.visible { opacity: 1; pointer-events: auto; }

.main {
  margin-left: var(--sidebar-width);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.topbar {
  background: #fff;
  border-bottom: 1px solid var(--border);
  padding: 14px 30px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  position: sticky;
  top: 0;
  z-index: 210;
}

.hamburger {
  display: none;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 5px;
  width: 36px; height: 36px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: #f8fafc;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s, border-color 0.2s;
  padding: 0;
}

.hamburger:hover { background: #f1f5f9; border-color: #cbd5e1; }

.hamburger span {
  display: block;
  width: 16px; height: 2px;
  background: var(--text-primary);
  border-radius: 2px;
  transition: transform 0.3s ease, opacity 0.3s ease, width 0.3s ease;
  transform-origin: center;
}

.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; width: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.topbar-left { display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1; }
.topbar-title h1 { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); }
.topbar-title p { font-size: 0.75rem; color: var(--text-secondary); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }

.date-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid var(--border);
  padding: 6px 13px;
  border-radius: 99px;
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.admin-pill {
  display: flex;
  align-items: center;
  gap: 9px;
  background: #f8fafc;
  border: 1px solid var(--border);
  padding: 6px 14px 6px 7px;
  border-radius: 99px;
}

.admin-avatar {
  width: 28px; height: 28px;
  background: var(--sidebar-accent);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 700;
}

.admin-pill span { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); }

.content { padding: 26px 30px; flex: 1; }

.alert {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: var(--radius);
  padding: 11px 16px;
  margin-bottom: 22px;
  font-size: 0.82rem;
  color: #92400e;
  font-weight: 500;
  animation: fadeUp 0.4s ease both;
}

.alert svg { flex-shrink: 0; }
.alert a { color: var(--blue); font-weight: 700; text-decoration: none; margin-left: 6px; }

.stat-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 22px;
}

.stat-card {
  background: var(--card-bg);
  border-radius: var(--radius);
  padding: 18px 20px;
  border: 1px solid var(--border);
  position: relative;
  overflow: hidden;
  transition: box-shadow 0.2s, transform 0.2s;
  animation: fadeUp 0.4s ease both;
}

.stat-card:nth-child(1) { animation-delay: 0.05s; }
.stat-card:nth-child(2) { animation-delay: 0.10s; }
.stat-card:nth-child(3) { animation-delay: 0.15s; }
.stat-card:nth-child(4) { animation-delay: 0.20s; }
.stat-card:nth-child(5) { animation-delay: 0.25s; }
.stat-card:nth-child(6) { animation-delay: 0.30s; }
.stat-card:nth-child(7) { animation-delay: 0.35s; }
.stat-card:nth-child(8) { animation-delay: 0.40s; }

.stat-card:hover { box-shadow: 0 6px 22px rgba(0,0,0,0.08); transform: translateY(-2px); }

.stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }

.sc-blue::before   { background: var(--blue); }
.sc-green::before  { background: var(--green); }
.sc-orange::before { background: var(--orange); }
.sc-red::before    { background: var(--red); }
.sc-purple::before { background: var(--purple); }
.sc-teal::before   { background: var(--teal); }
.sc-pink::before   { background: var(--pink); }
.sc-indigo::before { background: var(--indigo); }

.stat-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }

.stat-chip {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  padding: 3px 8px;
  border-radius: 99px;
}

.chip-blue   { background: #eff6ff; color: var(--blue); }
.chip-green  { background: #f0fdf4; color: var(--green); }
.chip-orange { background: #fffbeb; color: var(--orange); }
.chip-red    { background: #fff1f2; color: var(--red); }
.chip-purple { background: #f5f3ff; color: var(--purple); }
.chip-teal   { background: #f0fdfa; color: var(--teal); }
.chip-pink   { background: #fdf2f8; color: var(--pink); }
.chip-indigo { background: #eef2ff; color: var(--indigo); }

.stat-icon {
  width: 36px; height: 36px;
  border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
}

.si-blue   { background: #eff6ff; color: var(--blue); }
.si-green  { background: #f0fdf4; color: var(--green); }
.si-orange { background: #fffbeb; color: var(--orange); }
.si-red    { background: #fff1f2; color: var(--red); }
.si-purple { background: #f5f3ff; color: var(--purple); }
.si-teal   { background: #f0fdfa; color: var(--teal); }
.si-pink   { background: #fdf2f8; color: var(--pink); }
.si-indigo { background: #eef2ff; color: var(--indigo); }

.stat-value {
  font-family: 'Space Mono', monospace;
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--text-primary);
  line-height: 1.1;
}

.stat-label { font-size: 0.73rem; color: var(--text-muted); margin-top: 3px; font-weight: 500; }

.stat-sub {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.7rem;
  color: var(--green);
  font-weight: 600;
  margin-top: 2px;
}

.section-heading {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--text-primary);
  text-transform: uppercase;
  letter-spacing: 0.07em;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
}

.section-heading::before {
  content: '';
  width: 3px; height: 16px;
  background: var(--blue);
  border-radius: 2px;
  display: inline-block;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 14px;
  margin-bottom: 22px;
}

.action-card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px 18px;
  display: flex;
  align-items: center;
  gap: 13px;
  text-decoration: none;
  color: var(--text-primary);
  transition: all 0.2s;
  animation: fadeUp 0.4s ease both;
}

.action-card:nth-child(1) { animation-delay: 0.10s; }
.action-card:nth-child(2) { animation-delay: 0.15s; }
.action-card:nth-child(3) { animation-delay: 0.20s; }
.action-card:nth-child(4) { animation-delay: 0.25s; }
.action-card:nth-child(5) { animation-delay: 0.30s; }
.action-card:nth-child(6) { animation-delay: 0.35s; }

.action-card:hover {
  border-color: var(--blue);
  box-shadow: 0 4px 18px rgba(59,130,246,0.12);
  transform: translateY(-2px);
}

.action-icon {
  width: 42px; height: 42px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}

.action-info h3 { font-size: 0.85rem; font-weight: 700; color: var(--text-primary); margin-bottom: 2px; }
.action-info p { font-size: 0.72rem; color: var(--text-muted); }

.action-arrow {
  margin-left: auto;
  color: var(--text-muted);
  font-size: 1.1rem;
  transition: color 0.2s, transform 0.2s;
  flex-shrink: 0;
}

.action-card:hover .action-arrow { color: var(--blue); transform: translateX(3px); }

.card {
  background: var(--card-bg);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  padding: 20px 22px;
  animation: fadeUp 0.4s ease 0.2s both;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid var(--border);
}

.card-title {
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-primary);
  display: flex;
  align-items: center;
  gap: 7px;
}

.card-title svg { color: var(--text-muted); }
.card-subtitle { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }

.view-all {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--blue);
  text-decoration: none;
  padding: 5px 12px;
  border: 1px solid #bfdbfe;
  border-radius: 99px;
  background: #eff6ff;
  transition: all 0.2s;
}

.view-all:hover { background: var(--blue); color: #fff; border-color: var(--blue); }

.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-bottom: 18px; }

table { width: 100%; border-collapse: collapse; }

thead th {
  text-align: left;
  padding: 8px 13px;
  font-size: 0.68rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  background: #f8fafc;
  border-bottom: 1px solid var(--border);
}

tbody td {
  padding: 11px 13px;
  font-size: 0.82rem;
  color: var(--text-primary);
  border-bottom: 1px solid #f1f5f9;
  vertical-align: middle;
}

tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #f8fafc; }

.badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 9px;
  border-radius: 99px;
  font-size: 0.68rem;
  font-weight: 700;
  white-space: nowrap;
}

.b-pending   { background: #fffbeb; color: #92400e; }
.b-preparing { background: #eff6ff; color: #1d4ed8; }
.b-ready     { background: #f0fdf4; color: #15803d; }
.b-completed { background: #f0fdfa; color: #0f766e; }
.b-cancelled { background: #fff1f2; color: #be123c; }

.top-item { display: flex; align-items: center; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.top-item:last-child { border-bottom: none; }

.rank-badge {
  width: 26px; height: 26px;
  border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.75rem;
  font-family: 'Space Mono', monospace;
  font-weight: 700;
  flex-shrink: 0;
}

.rb-1 { background: #fef3c7; color: #92400e; }
.rb-2 { background: #f1f5f9; color: #475569; }
.rb-3 { background: #fff7ed; color: #9a3412; }
.rb-4, .rb-5 { background: #f8fafc; color: #94a3b8; }

.top-item-info { flex: 1; min-width: 0; }

.top-item-name {
  font-size: 0.83rem;
  font-weight: 600;
  color: var(--text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.mini-bar-track { height: 4px; background: #f1f5f9; border-radius: 99px; margin-top: 5px; overflow: hidden; }
.mini-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--blue), #818cf8); }

.top-item-stats { text-align: right; flex-shrink: 0; }
.top-item-rev { font-family: 'Space Mono', monospace; font-size: 0.78rem; font-weight: 700; color: var(--green); }
.top-item-qty { font-size: 0.68rem; color: var(--text-muted); margin-top: 1px; }

.coupon-code {
  font-family: 'Space Mono', monospace;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--blue);
  background: #eff6ff;
  padding: 3px 8px;
  border-radius: 5px;
  border: 1px dashed #93c5fd;
  letter-spacing: 1px;
}

.discount-chip { font-size: 0.68rem; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.dc-percent { background: #eff6ff; color: var(--blue); }
.dc-fixed   { background: #f5f3ff; color: var(--purple); }
.cc-active   { background: #f0fdf4; color: #15803d; }
.cc-inactive { background: #fff1f2; color: #be123c; }
.cc-expired  { background: #f8fafc; color: #94a3b8; }

.expiry-warn { display: inline-flex; align-items: center; gap: 3px; color: var(--orange); font-weight: 600; }

.empty { text-align: center; padding: 28px 20px; color: var(--text-muted); }
.empty .empty-icon { display: flex; align-items: center; justify-content: center; margin-bottom: 10px; color: var(--text-muted); }
.empty .empty-icon svg { width: 34px; height: 34px; }
.empty p { font-size: 0.82rem; margin-bottom: 12px; }

.btn-sm {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 7px 16px;
  border-radius: 8px;
  font-size: 0.78rem;
  font-weight: 600;
  text-decoration: none;
  background: var(--blue);
  color: #fff;
  transition: background 0.2s;
}

.btn-sm:hover { background: #2563eb; }

@media (max-width: 1200px) { .stat-row { grid-template-columns: repeat(4, 1fr); } }

@media (max-width: 1024px) {
  .stat-row     { grid-template-columns: repeat(2, 1fr); }
  .two-col      { grid-template-columns: 1fr; }
  .actions-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); box-shadow: none; }
  .sidebar.open { transform: translateX(0); box-shadow: 8px 0 32px rgba(0, 0, 0, 0.25); }
  .sidebar-overlay { display: block; }
  .main { margin-left: 0; }
  body.sidebar-open { overflow: hidden; }
  .hamburger { display: flex; }
  .content { padding: 16px; }
  .topbar  { padding: 12px 16px; }
  .stat-row     { grid-template-columns: repeat(2, 1fr); }
  .actions-grid { grid-template-columns: 1fr; }
  .date-pill { display: none; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><?= icon('coffee', 20) ?></div>
    <h2>Cafe Digital</h2>
    <p>Admin Panel</p>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a href="admin_dashboard.php" class="nav-link active">
      <span class="nav-icon"><?= icon('home') ?></span> Dashboard
    </a>

    <div class="nav-section-label">Catalogue</div>
    <a href="admin_menu_add.php" class="nav-link">
      <span class="nav-icon"><?= icon('plus') ?></span> Add Menu
    </a>
        <a href="admin_menu_list.php" class="nav-link">
      <span class="nav-icon"><?= icon('file') ?></span> View / Edit Menu
    </a>
    <a href="inventory.php" class="nav-link">
      <span class="nav-icon"><?= icon('box') ?></span> Inventory
    </a>

    <div class="nav-section-label">Operations</div>
    <a href="admin_orders.php" class="nav-link">
      <span class="nav-icon"><?= icon('cart') ?></span> Manage Orders
    </a>
    <a href="admin_coupons.php" class="nav-link">
      <span class="nav-icon"><?= icon('tag') ?></span> Manage Coupons
    </a>

    <div class="nav-section-label">Analytics</div>
    <a href="admin_reports.php" class="nav-link">
      <span class="nav-icon"><?= icon('bars') ?></span> Sales Report
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="admin_logout.php" class="logout-btn">
      <?= icon('logout') ?> Logout
    </a>
  </div>
</aside>

<div class="main">

  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
      <div class="topbar-title">
        <h1>Dashboard</h1>
        <p>Welcome back, <b><?= htmlspecialchars($admin_name) ?></b> — here's what's happening today.</p>
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

  <div class="content">

    <?php if ($pendingOrders > 0): ?>
    <div class="alert">
      <?= icon('warning', 16) ?>
      You have <b><?= $pendingOrders ?> pending order(s)</b> waiting for action.
      <a href="admin_orders.php">View Now →</a>
    </div>
    <?php endif; ?>

    <div class="stat-row">

      <div class="stat-card sc-blue">
        <div class="stat-top">
          <span class="stat-chip chip-blue">Revenue</span>
          <span class="stat-icon si-blue"><?= icon('wallet') ?></span>
        </div>
        <div class="stat-value" data-count="<?= $totalSales ?>" data-prefix="RM " data-decimals="2">
          RM <?= number_format($totalSales, 2) ?>
        </div>
        <div class="stat-label">Total Sales (Completed)</div>
      </div>

      <div class="stat-card sc-green">
        <div class="stat-top">
          <span class="stat-chip chip-green">Orders</span>
          <span class="stat-icon si-green"><?= icon('cart') ?></span>
        </div>
        <div class="stat-value js-count"><?= $totalOrders ?></div>
        <div class="stat-label">Total Orders</div>
        <div class="stat-sub"><?= icon('calendar', 12) ?> <?= $todayOrders ?> today</div>
      </div>

      <div class="stat-card sc-orange">
        <div class="stat-top">
          <span class="stat-chip chip-orange">Pending</span>
          <span class="stat-icon si-orange"><?= icon('clock') ?></span>
        </div>
        <div class="stat-value js-count"><?= $pendingOrders ?></div>
        <div class="stat-label">Pending Orders</div>
      </div>

      <div class="stat-card sc-red">
        <div class="stat-top">
          <span class="stat-chip chip-red">Menu</span>
          <span class="stat-icon si-red"><?= icon('utensils') ?></span>
        </div>
        <div class="stat-value js-count"><?= $totalMenu ?></div>
        <div class="stat-label">Total Menu Items</div>
      </div>

      <div class="stat-card sc-purple">
        <div class="stat-top">
          <span class="stat-chip chip-purple">Customers</span>
          <span class="stat-icon si-purple"><?= icon('users') ?></span>
        </div>
        <div class="stat-value js-count"><?= $totalCustomers ?></div>
        <div class="stat-label">Registered Customers</div>
      </div>

      <div class="stat-card sc-teal">
        <div class="stat-top">
          <span class="stat-chip chip-teal">Coupons</span>
          <span class="stat-icon si-teal"><?= icon('tag') ?></span>
        </div>
        <div class="stat-value js-count"><?= $totalCoupons ?></div>
        <div class="stat-label">Total Coupons</div>
      </div>

      <div class="stat-card sc-pink">
        <div class="stat-top">
          <span class="stat-chip chip-pink">Active</span>
          <span class="stat-icon si-pink"><?= icon('check') ?></span>
        </div>
        <div class="stat-value js-count"><?= $activeCoupons ?></div>
        <div class="stat-label">Active Coupons</div>
      </div>

      <div class="stat-card sc-indigo">
        <div class="stat-top">
          <span class="stat-chip chip-indigo">Today</span>
          <span class="stat-icon si-indigo"><?= icon('calendar') ?></span>
        </div>
        <div class="stat-value js-count"><?= $todayOrders ?></div>
        <div class="stat-label">Today's Orders</div>
      </div>

    </div>

    <div class="section-heading">Quick Actions</div>
    <div class="actions-grid">
      <a href="admin_menu_add.php" class="action-card">
        <div class="action-icon si-red"><?= icon('plus', 20) ?></div>
        <div class="action-info">
          <h3>Add Menu Item</h3>
          <p>Add food, beverages or desserts</p>
        </div>
        <span class="action-arrow">›</span>
      </a>
      <a href="admin_menu_list.php" class="action-card">
        <div class="action-icon si-blue"><?= icon('file', 20) ?></div>
        <div class="action-info">
          <h3>View / Edit Menu</h3>
          <p>Manage existing items</p>
        </div>
        <span class="action-arrow">›</span>
      </a>
      <a href="admin_orders.php" class="action-card">
        <div class="action-icon si-orange"><?= icon('cart', 20) ?></div>
        <div class="action-info">
          <h3>Manage Orders</h3>
          <p>View and update order statuses</p>
        </div>
        <span class="action-arrow">›</span>
      </a>
      <a href="admin_coupons.php" class="action-card">
        <div class="action-icon si-purple"><?= icon('tag', 20) ?></div>
        <div class="action-info">
          <h3>Manage Coupons</h3>
          <p>Create and manage discounts</p>
        </div>
        <span class="action-arrow">›</span>
      </a>
            <a href="admin_reports.php" class="action-card">
        <div class="action-icon si-green"><?= icon('bars', 20) ?></div>
        <div class="action-info">
          <h3>Sales Report</h3>
          <p>View detailed analytics</p>
        </div>
        <span class="action-arrow">›</span>
      </a>
      <a href="inventory.php" class="action-card">
        <div class="action-icon si-indigo"><?= icon('box', 20) ?></div>
        <div class="action-info">
          <h3>Inventory</h3>
          <p>Manage stock items</p>
        </div>
        <span class="action-arrow">›</span>
      </a>
      <a href="admin_register.php" class="action-card">
        <div class="action-icon si-teal"><?= icon('userplus', 20) ?></div>
        <div class="action-info">
          <h3>Add Admin</h3>
          <p>Register a new admin account</p>
        </div>
        <span class="action-arrow">›</span>
      </a>
    </div>

    <div class="two-col">

      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title"><?= icon('cart', 16) ?> Recent Orders</div>
            <div class="card-subtitle">Last 5 orders placed</div>
          </div>
          <a href="admin_orders.php" class="view-all">View All →</a>
        </div>
        <?php if (!empty($recentOrders)): ?>
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $o):
                $bc = match(strtolower($o['status'])) {
                  'pending'   => 'b-pending',
                  'preparing' => 'b-preparing',
                  'ready'     => 'b-ready',
                  'completed' => 'b-completed',
                  'cancelled' => 'b-cancelled',
                  default     => 'b-pending',
                };
              ?>
              <tr>
                <td><b style="font-family:'Space Mono',monospace;font-size:0.78rem;">#<?= $o['order_id'] ?></b></td>
                <td><?= htmlspecialchars($o['name']) ?></td>
                <td><b style="color:var(--green);font-family:'Space Mono',monospace;font-size:0.8rem;">RM <?= number_format($o['total_price'], 2) ?></b></td>
                <td><span class="badge <?= $bc ?>"><?= htmlspecialchars($o['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">
            <span class="empty-icon"><?= icon('cart', 34) ?></span>
            <p>No orders yet.</p>
            <a href="admin_orders.php" class="btn-sm">View Orders</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title"><?= icon('trophy', 16) ?> Top Selling Items</div>
            <div class="card-subtitle">By quantity sold (all time)</div>
          </div>
          <a href="admin_reports.php" class="view-all">Full Report →</a>
        </div>
        <?php if (!empty($topItems)):
          $maxQty = max(array_column($topItems, 'total_qty'));
          foreach ($topItems as $i => $item):
            $r = $i + 1;
            $rc = match($r) { 1=>'rb-1', 2=>'rb-2', 3=>'rb-3', default=>'rb-4' };
            $pct = $maxQty > 0 ? ($item['total_qty'] / $maxQty) * 100 : 0;
        ?>
          <div class="top-item">
            <div class="rank-badge <?= $rc ?>"><?= $r ?></div>
            <div class="top-item-info">
              <div class="top-item-name"><?= htmlspecialchars($item['menu_name']) ?></div>
              <div class="mini-bar-track">
                <div class="mini-bar-fill" style="width:<?= $pct ?>%"></div>
              </div>
            </div>
            <div class="top-item-stats">
              <div class="top-item-rev">RM <?= number_format($item['total_revenue'], 2) ?></div>
              <div class="top-item-qty"><?= $item['total_qty'] ?> sold</div>
            </div>
          </div>
        <?php endforeach; else: ?>
          <div class="empty">
            <span class="empty-icon"><?= icon('trophy', 34) ?></span>
            <p>No sales data yet.</p>
            <a href="admin_menu_add.php" class="btn-sm">Add Menu Items</a>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title"><?= icon('tag', 16) ?> Recent Coupons</div>
          <div class="card-subtitle">Latest coupon activity</div>
        </div>
        <a href="admin_coupons.php" class="view-all">Manage →</a>
      </div>

      <?php if (!$tableExists || empty($recentCoupons)): ?>
        <div class="empty">
          <span class="empty-icon"><?= icon('tag', 34) ?></span>
          <p><?= !$tableExists ? 'Coupon system not set up yet.' : 'No coupons created yet.' ?></p>
          <a href="admin_coupons.php" class="btn-sm"><?= icon('plus', 14) ?> <?= !$tableExists ? 'Set Up Coupons' : 'Create Coupon' ?></a>
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Code</th>
              <th>Discount</th>
              <th>Min Order</th>
              <th>Expiry</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentCoupons as $c):
              $expired  = strtotime($c['expiry_date']) < strtotime('today');
              $daysLeft = (int)((strtotime($c['expiry_date']) - strtotime('today')) / 86400);
              if ($expired)         { $cls='cc-expired';  $lbl='Expired'; }
              elseif ($c['is_active']) { $cls='cc-active';   $lbl='Active'; }
              else                  { $cls='cc-inactive'; $lbl='Inactive'; }
            ?>
            <tr>
              <td><span class="coupon-code"><?= htmlspecialchars($c['code']) ?></span></td>
              <td>
               <span class="discount-chip dc-percent"><?= number_format($c['discount'], 0) ?>% OFF</span>
              </td>
              <td style="font-size:0.8rem;color:var(--text-secondary);">
                <?= (!empty($c['min_order']) && $c['min_order'] > 0) ? 'RM '.number_format($c['min_order'],2) : '—' ?>
              </td>
              <td style="font-size:0.78rem;">
                <?php if ($expired): ?>
                  <span style="color:var(--red);">Expired</span>
                <?php elseif ($daysLeft <= 7): ?>
                  <span class="expiry-warn"><?= icon('warning', 12) ?> <?= $daysLeft ?>d left</span>
                <?php else: ?>
                  <span style="color:var(--text-secondary);"><?= date('d M Y', strtotime($c['expiry_date'])) ?></span>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <div style="margin-top:14px;text-align:right;">
          <a href="admin_coupons.php" class="btn-sm"><?= icon('plus', 14) ?> Create New Coupon</a>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <div style="padding:14px 30px;border-top:1px solid var(--border);text-align:center;font-size:0.72rem;color:var(--text-muted);">
    Cafe Digital Admin Panel &nbsp;|&nbsp; © <?= date('Y') ?>
  </div>
</div>

<script>
const sidebar     = document.getElementById('sidebar');
const overlay     = document.getElementById('sidebarOverlay');
const hamburger   = document.getElementById('hamburger');

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
  if (e.key === 'Escape' && sidebar.classList.contains('open')) {
    closeSidebar();
  }
});

sidebar.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeSidebar();
  });
});

document.querySelectorAll('.js-count').forEach(el => {
  const target = parseInt(el.textContent.trim());
  if (!target || isNaN(target)) return;
  const duration = 900;
  const step = target / (duration / 16);
  let cur = 0;
  const t = setInterval(() => {
    cur = Math.min(cur + step, target);
    el.textContent = Math.floor(cur);
    if (cur >= target) clearInterval(t);
  }, 16);
});

document.querySelectorAll('.b-pending').forEach(el => {
  const tr = el.closest('tr');
  if (tr) tr.style.background = '#fffbeb';
});
</script>
</body>
</html>