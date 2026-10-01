<?php
require __DIR__ . '/../db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['guest'])) {
    header('Location: welcome.php');
    exit;
}

$isGuest      = isset($_SESSION['guest']) && $_SESSION['guest'] === true;
$customerName = $_SESSION['name'] ?? 'Guest';

$cartCount = 0;
if (!$isGuest) {
    $customer_id = $_SESSION['customer_id'];
    try {
        $cartStmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0) as total
            FROM cart
            WHERE customer_id = ?
        ");
        $cartStmt->execute([$customer_id]);
        $cartData  = $cartStmt->fetch(PDO::FETCH_ASSOC);
        $cartCount = (int)($cartData['total'] ?? 0);
    } catch (PDOException $e) {
        $cartCount = 0;
    }
} else {
    $cartCount = isset($_SESSION['guest_cart'])
                 ? array_sum(array_column($_SESSION['guest_cart'], 'quantity'))
                 : 0;
}

$coupons         = [];
$showCouponPopup = false;

if (!$isGuest) {
    try {
        $tableCheck  = $pdo->query("SHOW TABLES LIKE 'coupon'");
        $tableExists = $tableCheck->rowCount() > 0;

        if ($tableExists) {
                      $couponStmt = $pdo->prepare("
    SELECT * FROM coupon
    WHERE is_active = 1
    AND expiry_date >= CURDATE()
    AND (customer_id IS NULL OR customer_id = ?)
    AND coupon_id NOT IN (
        SELECT coupon_id FROM coupon_usage WHERE customer_id = ?
    )
    ORDER BY discount DESC
");
$couponStmt->execute([$customer_id, $customer_id]);

            $coupons = $couponStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $coupons = [];
    }

    if (!isset($_SESSION['coupon_shown'])) {
        $showCouponPopup          = true;
        $_SESSION['coupon_shown'] = true;
    }
}

try {
       $stmt = $pdo->query("
        SELECT * FROM menu
        ORDER BY FIELD(LOWER(category), 'food', 'beverages', 'dessert') = 0,
                 FIELD(LOWER(category), 'food', 'beverages', 'dessert'),
                 FIELD(LOWER(subcategory), 'coffee', 'latte', 'sparkling tea', 'ice crush series', 'al-hadad shake') = 0,
                 FIELD(LOWER(subcategory), 'coffee', 'latte', 'sparkling tea', 'ice crush series', 'al-hadad shake'),
                 subcategory, availability DESC, menu_name
    ");

    $menu_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $menu_items = [];
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$scriptDir = rtrim($scriptDir, '/');
$baseUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
           . '://' . $_SERVER['HTTP_HOST']
           . $scriptDir . '/';

$scriptFolder = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'])), '/');

function getImageUrl($filePath, $baseUrl, $scriptFolder) {
    if (empty(trim($filePath))) return '';
    $filePath = ltrim(str_replace('\\', '/', trim($filePath)), '/');

    if (strpos($filePath, '..') !== false || strpos($filePath, '//') !== false) return '';

    $ext        = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowedExt)) return '';

    $projectRoot = dirname($scriptFolder);
    $serverPath  = $projectRoot . '/' . $filePath;
    $fallbackPath = __DIR__ . '/../' . $filePath;

    if ((!file_exists($serverPath) || !is_file($serverPath))
        && (!file_exists($fallbackPath) || !is_file($fallbackPath))) {
        return '';
    }

    $baseUrlParent = dirname(rtrim($baseUrl, '/')) . '/';
    return $baseUrlParent . $filePath;
}

function sanitizeDataAttribute($value) {
    return htmlspecialchars(strtolower($value), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';">
<title>MIA COFFEE - Menu</title>
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
    --success: #16a34a;
    --danger: #dc2626;
  }

   * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: "Inter", "Segoe UI", Roboto, Arial, sans-serif;
    background: 
      linear-gradient(rgba(241,245,249,0.9), rgba(241,245,249,0.9)),
      url('../assets/images/background.png');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    background-repeat: no-repeat;
    color: var(--text);
    padding-bottom: 110px;
  }

  .top-navbar {
    background: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 200;
    box-shadow: 0 2px 12px rgba(15,23,42,0.06);
  }

  .navbar-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1.15rem;
  font-weight: 800;
  letter-spacing: .02em;
  color: var(--text);
}

.navbar-logo {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  object-fit: cover;
  flex-shrink: 0;
}

  .navbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .welcome-text {
    font-size: 0.8rem;
    color: var(--muted);
    font-weight: 500;
  }

  .logout-btn,
  .login-nav-btn {
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 0.78rem;
    font-weight: 700;
    text-decoration: none;
    transition: all .2s ease;
    border: 1px solid transparent;
  }

  .logout-btn {
    border-color: var(--primary);
    color: var(--primary);
    background: #fff;
  }

  .logout-btn:hover {
    background: var(--primary);
    color: #fff;
  }

  .login-nav-btn {
    background: var(--primary);
    color: #fff;
  }

  .login-nav-btn:hover { background: var(--primary-dark); }

.profile-dropdown {
  position: relative;
}

.profile-trigger {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: 6px 12px 6px 14px;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--text);
  cursor: pointer;
  font-family: inherit;
  transition: all .2s;
}

.profile-trigger:hover { border-color: var(--primary); }

.profile-trigger .caret {
  font-size: 0.6rem;
  color: var(--muted);
  transition: transform .2s;
}

.profile-dropdown.open .profile-trigger .caret { transform: rotate(180deg); }

.profile-menu {
  display: none;
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  min-width: 190px;
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: 0 12px 28px rgba(15,23,42,0.12);
  overflow: hidden;
  z-index: 250;
}

.profile-dropdown.open .profile-menu { display: block; }

.profile-menu-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 10px 14px;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--text);
  text-decoration: none;
  background: none;
  border: none;
  width: 100%;
  text-align: left;
  cursor: pointer;
  font-family: inherit;
  transition: background .15s;
}

.profile-menu-item:hover { background: var(--soft); }

.profile-menu-divider {
  height: 1px;
  background: var(--border);
  margin: 4px 0;
}

.profile-menu-item.logout-item { color: var(--danger); }
  .coupon-nav-btn {
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 0.78rem;
    font-weight: 700;
    border: 1px solid var(--primary);
    background: #fff;
    color: var(--primary);
    cursor: pointer;
    transition: all .2s ease;
    font-family: inherit;
    white-space: nowrap;
  }

  .coupon-nav-btn:hover { background: #eff6ff; }

  .guest-banner {
    background: #0f172a;
    color: #e2e8f0;
    padding: 10px 16px;
    text-align: center;
    font-size: 0.8rem;
  }

  .guest-banner a {
    color: #93c5fd;
    font-weight: 700;
    text-decoration: none;
  }

  .search-wrap {
    background: #fff;
    padding: 10px 15px;
    border-bottom: 1px solid var(--border);
  }

  .search-input {
    width: 100%;
    max-width: 720px;
    margin: 0 auto;
    display: block;
    padding: 11px 15px;
    border: 1px solid var(--border);
    border-radius: 999px;
    font-size: 0.9rem;
    background: var(--soft);
    color: var(--text);
    transition: all .2s;
  }

  .search-input:focus {
    outline: none;
    border-color: #93c5fd;
    background: #fff;
    box-shadow: 0 0 0 3px #dbeafe;
  }
/* ==================== Product Detail Modal ==================== */


/* Header */


  .category-wrapper {
    background: #fff;
    padding: 10px 0;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 63px;
    z-index: 120;
  }

  .category-scroll {
    display: flex;
    justify-content: center;
    overflow-x: auto;
    gap: 8px;
    padding: 0 12px;
    scrollbar-width: none;
  }
  .category-scroll::-webkit-scrollbar { display: none; }

  .category-btn {
    padding: 9px 16px;
    border: 1px solid var(--border);
    border-radius: 999px;
    background: #fff;
    cursor: pointer;
    white-space: nowrap;
    transition: all .2s;
    min-width: 90px;
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--muted);
    user-select: none;
    font-family: inherit;
  }

  .category-btn:hover {
    border-color: #5a97dd;
    color: var(--primary);
    transform: translateY(-1px);
  }

  .category-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    box-shadow: 0 4px 14px rgba(37,99,235,0.28);
  }
  .subcategory-wrapper {
    background: #f8fafc;
    border-bottom: 1px solid var(--border);
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.3s ease, padding 0.3s ease;
    position: sticky;
    top: 113px;
    z-index: 119;
  }

  .subcategory-wrapper.show {
    max-height: 60px;
    padding: 8px 0;
  }

  .subcategory-scroll {
    display: flex;
    justify-content: center;
    overflow-x: auto;
    gap: 6px;
    padding: 0 12px;
    scrollbar-width: none;
  }
  .subcategory-scroll::-webkit-scrollbar { display: none; }

  .subcategory-btn {
    padding: 6px 14px;
    border: 1px solid #bfdbfe;
    border-radius: 999px;
    background: #eff6ff;
    cursor: pointer;
    white-space: nowrap;
    transition: all .2s;
    font-size: 0.74rem;
    font-weight: 700;
    color: #1d4ed8;
    user-select: none;
    font-family: inherit;
  }

  .subcategory-btn:hover {
    background: #dbeafe;
    transform: translateY(-1px);
  }

  .subcategory-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
    box-shadow: 0 3px 10px rgba(37,99,235,0.25);
  }
  .menu-container {
    max-width: 760px;
    margin: 0 auto;
    padding: 16px;
  }

  .section-header {
    display: flex;
    align-items: center;
    gap: 9px;
    margin-bottom: 14px;
  }

  .section-header h2 {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--text);
  }

  .item-count {
    background: #e2e8f0;
    color: #475569;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
  }

  .no-items {
    text-align: center;
    padding: 46px 20px;
    color: #94a3b8;
    display: none;
  }

  .menu-item {
    display: flex;
    align-items: center;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 12px;
    margin-bottom: 12px;
    box-shadow: 0 2px 10px rgba(15,23,42,0.04);
    transition: all .2s;
    gap: 13px;
  }

  .menu-item:hover {
    box-shadow: 0 8px 20px rgba(15,23,42,0.09);
    transform: translateY(-2px);
  }

  .menu-item.hidden,
.subcat-header.hidden { display: none; }

.subcat-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 12px 0;
}
.subcat-header:first-child { margin-top: 0; }
.subcat-header span {
  font-size: 0.82rem;
  font-weight: 800;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: var(--text);
  white-space: nowrap;
}
.subcat-header::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
}

  .menu-image {
    width: 86px;
    height: 86px;
    object-fit: cover;
    border-radius: 12px;
    display: block;
    border: 1px solid var(--border);
    background: #fff;
  }

  .img-placeholder {
    width: 86px;
    height: 86px;
    background: #f8fafc;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    color: #94a3b8;
    border: 1px solid var(--border);
    text-transform: uppercase;
    letter-spacing: .08em;
    font-weight: 700;
  }

  .menu-info { flex: 1; min-width: 0; }

  .menu-title {
    font-weight: 700;
    font-size: 0.97rem;
    color: var(--text);
    margin-bottom: 4px;
  }

  .best-seller-badge {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
    font-size: 0.66rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    display: inline-flex;
    margin-bottom: 6px;
  }
  .menu-item.sold-out { opacity: .6; }

.sold-out-badge {
  background: #fef2f2;
  color: #b91c1c;
  border: 1px solid #fecaca;
  font-size: 0.66rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 999px;
  display: inline-flex;
  margin-bottom: 6px;
}

button.add-btn:disabled {
  background: #cbd5e1;
  cursor: not-allowed;
}
button.add-btn:disabled:hover { background: #cbd5e1; transform: none; }

  .menu-desc {
    font-size: 0.78rem;
    color: var(--muted);
    margin-bottom: 6px;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 320px;
  }

  .more-link {
    color: var(--primary);
    cursor: pointer;
    font-weight: 700;
    font-size: 0.74rem;
  }

  .menu-category-tag {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: var(--soft);
    color: #334155;
  }

  .menu-right {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 7px;
    min-width: 94px;
  }

  .price {
    font-weight: 800;
    font-size: 0.98rem;
    color: var(--primary-dark);
    white-space: nowrap;
  }

  .note { font-size: 0.68rem; color: #94a3b8; }

  button.add-btn {
    background: var(--primary);
    border: none;
    color: #fff;
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all .2s;
    font-family: inherit;
    white-space: nowrap;
  }

  button.add-btn:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
  }

  .floating-cart {
    position: fixed;
    bottom: 18px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--primary);
    color: #fff;
    text-decoration: none;
    border-radius: 999px;
    padding: 13px 24px;
    display: flex;
    align-items: center;
    gap: 9px;
    font-weight: 700;
    font-size: 0.86rem;
    box-shadow: 0 8px 24px rgba(37,99,235,0.35);
    z-index: 999;
    transition: all .2s;
    min-width: 200px;
    justify-content: center;
  }

  .floating-cart:hover {
    background: var(--primary-dark);
    transform: translateX(-50%) translateY(-2px);
  }

  .floating-cart-badge {
    background: #fff;
    color: var(--primary);
    font-size: 0.72rem;
    font-weight: 800;
    min-width: 22px;
    height: 22px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
  }

  .toast {
    position: fixed;
    bottom: 84px;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: #0f172a;
    color: #fff;
    padding: 10px 18px;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    box-shadow: 0 6px 20px rgba(0,0,0,0.25);
    z-index: 9999;
    transition: transform 0.35s ease;
    pointer-events: none;
  }

  .toast.show { transform: translateX(-50%) translateY(0); }

  .modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(15,23,42,0.55);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .modal-overlay.show { display: flex; }

  .modal-box {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 14px 44px rgba(0,0,0,0.22);
    animation: slideUp 0.25s ease;
    border: 1px solid var(--border);
  }

  @keyframes slideUp {
    from { transform: translateY(24px); opacity: 0; }
    to   { transform: translateY(0); opacity: 1; }
  }

  .modal-box h4 {
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
  }

  .modal-box p {
    font-size: 0.85rem;
    color: #475569;
    line-height: 1.55;
    margin-bottom: 15px;
    word-break: break-word;
  }

  .modal-close {
    width: 100%;
    padding: 10px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 0.84rem;
    cursor: pointer;
  }

  .modal-close:hover { background: var(--primary-dark); }

  .coupon-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.55);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .coupon-overlay.show { display: flex; }

  .coupon-popup {
    background: #fff;
    border-radius: 18px;
    max-width: 460px;
    width: 100%;
    box-shadow: 0 22px 65px rgba(0,0,0,0.28);
    animation: popUp 0.28s ease;
    overflow: hidden;
    border: 1px solid var(--border);
  }

  @keyframes popUp {
    from { transform: scale(.92); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
  }

  .coupon-header {
    background: linear-gradient(135deg, #1e3a8a, #2563eb);
    padding: 22px 20px 18px;
    text-align: center;
    position: relative;
  }

  .coupon-close {
    position: absolute;
    top: 10px; right: 10px;
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    width: 28px; height: 28px;
    border-radius: 50%;
    font-size: 0.9rem;
    cursor: pointer;
  }

  .coupon-header h2 {
    font-size: 1.05rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 4px;
  }

  .coupon-header p {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.9);
  }

  .coupon-body {
    padding: 16px 16px 10px;
    max-height: 340px;
    overflow-y: auto;
  }

  .coupon-subtitle {
    font-size: 0.76rem;
    color: #64748b;
    text-align: center;
    margin-bottom: 12px;
  }

  .coupon-card {
    background: #fff;
    border: 1px dashed #93c5fd;
    border-radius: 12px;
    padding: 12px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: .2s;
  }

  .coupon-card:hover { background: #f8fbff; }

  .coupon-discount {
    background: #eff6ff;
    color: #1e40af;
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    min-width: 72px;
    flex-shrink: 0;
    border: 1px solid #bfdbfe;
  }

  .coupon-discount-amount {
    font-size: 1.02rem;
    font-weight: 900;
    line-height: 1;
  }

  .coupon-discount-type {
    font-size: 0.62rem;
    font-weight: 700;
    margin-top: 2px;
    letter-spacing: .08em;
  }

  .coupon-info { flex: 1; min-width: 0; }

  .coupon-code {
    font-size: 0.86rem;
    font-weight: 800;
    color: #1d4ed8;
    letter-spacing: 1px;
    margin-bottom: 2px;
    word-break: break-all;
  }

  .coupon-desc { font-size: 0.72rem; color: #64748b; margin-bottom: 3px; }
  .coupon-min { font-size: 0.7rem; color: #64748b; margin-bottom: 2px; }
  .coupon-expiry { font-size: 0.68rem; color: #94a3b8; }

  .coupon-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
  }

  .copy-btn {
    background: var(--primary);
    color: #fff;
    border: none;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
    white-space: nowrap;
    font-family: inherit;
  }

  .copy-btn:hover { background: var(--primary-dark); }
  .copy-btn.copied { background: var(--success); }

  .save-btn {
    background: #fff;
    color: var(--primary);
    border: 1px solid var(--primary);
    padding: 7px 10px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
    white-space: nowrap;
    font-family: inherit;
  }

  .save-btn:hover { background: #eff6ff; }
  .save-btn:disabled { opacity: .6; cursor: not-allowed; }
  .save-btn.saved { background: var(--success); border-color: var(--success); color: #fff; }

  .no-coupons {
    text-align: center;
    padding: 20px 12px;
    color: #94a3b8;
    font-size: 0.78rem;
  }

  .coupon-footer { padding: 0 16px 16px; }

  .coupon-footer-btn {
    width: 100%;
    padding: 11px;
    background: #0f172a;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
  }

  .coupon-footer-btn:hover { background: #1e293b; }

  /* Square save-confirmation popup */
  .save-confirm-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.55);
    z-index: 100000;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .save-confirm-overlay.show { display: flex; }

  .save-confirm-box {
    background: #fff;
    border-radius: 20px;
    width: 260px;
    height: 260px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.25);
    animation: popUp 0.22s ease;
    border: 1px solid var(--border);
  }

  .save-confirm-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #dcfce7;
    color: var(--success);
    font-size: 1.4rem;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
  }

  .save-confirm-box h4 {
    font-size: 1rem;
    font-weight: 800;
    color: var(--text);
    margin-bottom: 8px;
  }

  .save-confirm-box p {
    font-size: 0.78rem;
    color: var(--muted);
    line-height: 1.5;
    margin-bottom: 16px;
  }

  .save-confirm-close {
    padding: 9px 20px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
  }
  .save-confirm-close:hover { background: var(--primary-dark); }

 
  .product-modal-overlay.show { display: flex; }


  @keyframes popUp {
    from { transform: scale(.92); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
  }

 
  .product-modal-close {
    background: rgba(0,0,0,0.15);
    border: none;
    color: #fff;
    width: 28px; height: 28px;
    border-radius: 50%;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .product-modal-close:hover { background: rgba(0,0,0,0.25); }

  
  
  .product-modal-placeholder {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid var(--border);
    font-size: 0.75rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: .08em;
    font-weight: 700;
  }
  
 
  .product-modal-desc {
    font-size: 0.88rem;
    color: var(--muted);
    line-height: 1.5;
    margin-bottom: 12px;
  }
  .product-modal-price {
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--primary-dark);
    margin-top: auto; /* push to bottom */
  }
  .product-modal-note {
    font-size: 0.72rem;
    color: #94a3b8;
    margin-top: 4px;
  }
  .product-modal-add-btn {
    width: 100%;
    padding: 12px;
    background: var(--primary);
    border: none;
    color: #fff;
    font-weight: 700;
    font-size: 0.9rem;
    border-radius: 999px;
    cursor: pointer;
    transition: all .2s;
  }
  .product-modal-add-btn:hover { background: var(--primary-dark); }
  .product-modal-add-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
  }

  @media (max-width: 540px) {
    .welcome-text { display: none; }
    .menu-item { gap: 10px; padding: 10px; }
    .menu-image, .img-placeholder { width: 74px; height: 74px; }
    .menu-desc { max-width: 180px; }
    .floating-cart {
      min-width: 170px;
      padding: 11px 18px;
      font-size: .8rem;
    }
   /* Product Detail Modal */
/* ===== Product Detail Modal ===== */
.product-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,0.6);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 20px;
  backdrop-filter: blur(4px);
}
.product-modal-overlay.show { display: flex; }

.product-modal-box {
  background: #fff;
  border-radius: 24px;
  width: 100%;
  max-width: 380px;
  max-height: 88vh;
  overflow-y: auto;
  box-shadow: 0 24px 64px rgba(15,23,42,0.28);
  position: relative;
  display: flex;
  flex-direction: column;
  animation: popUp 0.25s ease;
}

@keyframes popUp {
  from { transform: scale(.93); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}

.product-modal-close {
  position: absolute;
  top: 14px; right: 14px;
  width: 32px; height: 32px;
  border-radius: 50%;
  background: rgba(15,23,42,0.08);
  border: none;
  font-size: 1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text);
  transition: background .2s;
  z-index: 10;
}
.product-modal-close:hover { background: rgba(15,23,42,0.16); }

/* Badge row */
.product-modal-badges {
  display: flex;
  justify-content: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 20px 20px 0;
}

/* Title */
.product-modal-title {
  font-size: 1.3rem;
  font-weight: 800;
  color: var(--text);
  text-align: center;
  padding: 10px 24px 0;
  line-height: 1.3;
}

/* Image */
.product-modal-img-wrap {
  padding: 16px 24px;
  display: flex;
  justify-content: center;
}
.product-modal-img {
  width: 100%;
  max-width: 260px;
  height: 220px;
  object-fit: cover;
  border-radius: 18px;
  display: block;
  box-shadow: 0 8px 24px rgba(15,23,42,0.12);
}
.product-modal-img-placeholder {
  width: 260px;
  height: 220px;
  background: #f1f5f9;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3.5rem;
}

/* Description */
.product-modal-desc {
  font-size: 0.85rem;
  color: var(--muted);
  text-align: center;
  line-height: 1.6;
  padding: 0 24px 16px;
}

/* Footer: Add to Cart + qty + price */
.product-modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 20px 20px;
  border-top: 1px solid var(--border);
  flex-wrap: wrap;
}

.product-modal-add-btn {
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 12px 20px;
  border-radius: 999px;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  transition: all .2s;
  font-family: inherit;
  white-space: nowrap;
}
.product-modal-add-btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
.product-modal-add-btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }

/* Quantity stepper */
.qty-stepper {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 6px 14px;
}
.qty-btn {
  background: none;
  border: none;
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--text);
  cursor: pointer;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: background .15s;
  font-family: inherit;
}
.qty-btn:hover { background: #e2e8f0; }
.qty-value {
  font-size: 0.95rem;
  font-weight: 700;
  min-width: 18px;
  text-align: center;
  color: var(--text);
}

/* Price block */
.product-modal-price-block {
  text-align: right;
  flex-shrink: 0;
}
.product-modal-price-block .price-amount {
  font-size: 1.2rem;
  font-weight: 900;
  color: var(--primary-dark);
  line-height: 1;
}
.product-modal-price-block .price-tax {
  font-size: 0.7rem;
  color: #94a3b8;
  margin-top: 2px;
}
  }
</style>
</head>
<body>

<div class="top-navbar">
  <div class="navbar-title">
    <img src="../assets/images/logo.jpg" alt="MIA Coffee logo" class="navbar-logo">
    <span>MIA COFFEE</span>
  </div>
  <div class="navbar-right">
    <?php if ($isGuest): ?>
      <a href="login.php" class="login-nav-btn">Login</a>
    <?php else: ?>
      <div class="profile-dropdown" id="profileDropdown">
        <button type="button" class="profile-trigger" onclick="toggleProfileMenu()" aria-haspopup="true" aria-expanded="false">
          <span><?= htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="caret">▼</span>
        </button>
       <div class="profile-menu" role="menu" aria-hidden="true">
  <a href="my_orders.php" class="profile-menu-item" role="menuitem">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
    My Orders
  </a>
  <a href="cart.php" class="profile-menu-item" role="menuitem">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
    View Cart
  </a>
  <div class="profile-menu-divider"></div>
  <a href="logout.php" class="profile-menu-item logout-item" role="menuitem">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Logout
  </a>
</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($isGuest): ?>
<div class="guest-banner">
  Browsing as guest.
  <a href="login.php">Login</a> or <a href="register.php">Register</a> to unlock member coupons.
</div>
<?php endif; ?>

<div class="search-wrap">
  <input type="text"
         class="search-input"
         id="searchInput"
         placeholder="Search menu"
         oninput="searchMenu(this.value)">
</div>

<div class="category-wrapper">
  <div class="category-scroll">
    <button class="category-btn active" data-category="all" type="button">All</button>
    <button class="category-btn" data-category="food" type="button">Food</button>
    <button class="category-btn" data-category="beverages" type="button">Beverages</button>
    <button class="category-btn" data-category="dessert" type="button">Dessert</button>
  </div>
</div>
<div class="subcategory-wrapper" id="subcategoryWrapper">
  <div class="subcategory-scroll">
    <button class="subcategory-btn active" data-sub="all" type="button">All Beverages</button>
    <button class="subcategory-btn" data-sub="coffee" type="button">Coffee</button>
    <button class="subcategory-btn" data-sub="latte" type="button">Latte</button>
    <button class="subcategory-btn" data-sub="sparkling tea" type="button">Sparkling Tea</button>
    <button class="subcategory-btn" data-sub="ice crush series" type="button">Ice Crush Series</button>
    <button class="subcategory-btn" data-sub="al-hadad shake" type="button">Al-Hadad Shake</button>
  </div>
</div>
<div class="menu-container">
  <div class="section-header">
    <h2 id="sectionTitle">All Items</h2>
    <span class="item-count" id="itemCount"><?= count($menu_items) ?> items</span>
  </div>

  <div id="menuList">
    <?php if (!empty($menu_items)): $lastGroup = ''; ?>
      <?php foreach ($menu_items as $item):
       $isSoldOut = empty($item['availability']);
        $isBestSeller = !empty($item['best_seller']) && $item['best_seller'] == 1;
        $description  = htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $shortDesc    = mb_strlen($description) > 60 ? mb_substr($description, 0, 60) . '...' : $description;
        $itemCategory = strtolower(trim($item['category'] ?? ''));
        $menuName     = htmlspecialchars($item['menu_name'], ENT_QUOTES, 'UTF-8');

        $filePath = trim($item['file_path'] ?? '');
        $imgUrl   = getImageUrl($filePath, $baseUrl, $scriptFolder);

        if ($imgUrl) {
          $imgUrlSafe = htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8');
          $imageHTML = '
            <img src="' . $imgUrlSafe . '"
                 alt="' . $menuName . '"
                 class="menu-image"
                 loading="lazy"
                 onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\';">
            <div class="img-placeholder" style="display:none;">Image</div>
          ';
        } else {
          $imageHTML = '<div class="img-placeholder">Image</div>';
        }

         $tagLabel = match($itemCategory) {
          'food'      => 'Food',
          'beverages' => 'Beverages',
          'dessert'   => 'Dessert',
          default     => ucfirst($itemCategory),
        };

        $subRaw     = trim($item['subcategory'] ?? '');
        $groupLabel = $subRaw !== '' ? ucwords(strtolower($subRaw), ' -') : $tagLabel;
        $groupKey   = $itemCategory . '|' . strtolower($subRaw);
      ?>
    
    <?php if ($groupKey !== $lastGroup): $lastGroup = $groupKey; ?>
    <div class="subcat-header"><span><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
  <?php endif; ?>

    
    <div class="menu-item<?= $isSoldOut ? ' sold-out' : '' ?>"
     data-category="<?= sanitizeDataAttribute($itemCategory) ?>"
     data-subcategory="<?= sanitizeDataAttribute($item['subcategory'] ?? '') ?>"
     data-name="<?= sanitizeDataAttribute($item['menu_name']) ?>"
     data-id="<?= (int)$item['menu_id'] ?>"
     data-price="<?= number_format($item['price'], 2) ?>"
     data-description="<?= htmlspecialchars($item['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
     data-img="<?= $imgUrl ? htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8') : '' ?>"
     data-bestseller="<?= $isBestSeller ? '1' : '0' ?>"
     data-available="<?= $isSoldOut ? '0' : '1' ?>"
     data-isguest="<?= $isGuest ? '1' : '0' ?>"
     data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
     style="cursor:pointer;">
<div class="menu-image-wrap"><?= $imageHTML ?></div>

        <div class="menu-info">
          <div class="menu-title"><?= $menuName ?></div>

         <?php if ($isBestSeller): ?>
       <span class="best-seller-badge">Best Seller</span>
            <?php endif; ?>
            <?php if ($isSoldOut): ?>
      <span class="sold-out-badge">Sold Out</span>
            <?php endif; ?>
            <?php if (!empty($description)): ?>
          <div class="menu-desc">
            <?= $shortDesc ?>
            <?php if (mb_strlen($description) > 60): ?>
              <span class="more-link"
                    role="button"
                    tabindex="0"
                    onclick="openProductModal(this)"
                    onkeypress="if(event.key==='Enter') openProductModal(this)">more</span>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <span class="menu-category-tag"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>

     <div class="menu-right">
          <div class="price">RM <?= number_format($item['price'], 2) ?></div>
          <div class="note">+ tax</div>
          <form method="post" action="add_to_cart.php" onsubmit="handlePMCart(event, this)">
            <input type="hidden" name="menu_id" value="<?= htmlspecialchars($item['menu_id'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="quantity" value="1">
            <input type="hidden" name="is_guest" value="<?= $isGuest ? '1' : '0' ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
           <button type="submit" class="add-btn" <?= $isSoldOut ? 'disabled' : '' ?> aria-label="Add <?= $menuName ?> to cart">
        <?= $isSoldOut ? 'Sold Out' : 'Add' ?>
       </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center; padding:50px 20px; color:#94a3b8;">
        <p style="font-size:0.95rem; font-weight:600;">No menu items available.</p>
      </div>
    <?php endif; ?>
  </div>

  <div class="no-items" id="noItems">
    <p>No items found.</p>
  </div>
</div>

<a href="cart.php" class="floating-cart" aria-label="View shopping cart">
  <span>View Cart</span>
  <span class="floating-cart-badge" id="floatingBadge"><?= $cartCount ?></span>
</a>

          
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<div class="modal-overlay" id="descModal" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
  <div class="modal-box">
    <h4 id="modalTitle"></h4>
    <p id="modalDesc"></p>
    <button class="modal-close" onclick="closeDescModal()" aria-label="Close modal">Close</button>
  </div>
</div>

<?php if (!$isGuest): ?>
<div class="coupon-overlay" id="couponOverlay" role="dialog" aria-labelledby="couponHeader" aria-hidden="true">
  <div class="coupon-popup">
    <div class="coupon-header">
      <button class="coupon-close" onclick="closeCoupon()" aria-label="Close coupon popup">×</button>
      <h2 id="couponHeader">Welcome, <?= htmlspecialchars(explode(' ', $customerName)[0], ENT_QUOTES, 'UTF-8') ?></h2>
      <p>Your available coupons</p>
    </div>
    <div class="coupon-body">
      <p class="coupon-subtitle">Use copy to apply coupon at checkout, or save it for later.</p>
      <?php if (!empty($coupons)): ?>
        <?php foreach ($coupons as $coupon):
          $couponCode = htmlspecialchars($coupon['code'], ENT_QUOTES, 'UTF-8');
         $couponDesc = '';
        ?>
        <div class="coupon-card">
          <div class="coupon-discount">
            <div class="coupon-discount-amount"><?= number_format($coupon['discount'], 0) ?>%</div>
            <div class="coupon-discount-type">OFF</div>
          </div>
          <div class="coupon-info">
            <div class="coupon-code"><?= $couponCode ?></div>
            <div class="coupon-desc"><?= $couponDesc ?></div>
            <?php if (!empty($coupon['min_order']) && $coupon['min_order'] > 0): ?>
              <div class="coupon-min">Minimum order: RM <?= number_format($coupon['min_order'], 2) ?></div>
            <?php endif; ?>
            <div class="coupon-expiry">Expires: <?= date('d M Y', strtotime($coupon['expiry_date'])) ?></div>
          </div>
                   <div class="coupon-actions">
            <button class="copy-btn"
                    onclick="copyCode(this, <?= json_encode($coupon['code']) ?>)"
                    aria-label="Copy coupon code <?= $couponCode ?>">
              Copy
            </button>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="no-coupons">
          <p>No coupons available right now.</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="coupon-footer">
      <button class="coupon-footer-btn" onclick="closeCoupon()" aria-label="Close and start ordering">
        Continue
      </button>
    </div>
  </div>
</div>
<?php endif; ?>



   
  </div>
</div>
<script>
<?php if (!$isGuest && $showCouponPopup): ?>
document.addEventListener('DOMContentLoaded', function() {
  var overlay = document.getElementById('couponOverlay');
  if (overlay) overlay.classList.add('show');
});
<?php endif; ?>

function closeCoupon() {
  var overlay = document.getElementById('couponOverlay');
  if (overlay) {
    overlay.classList.remove('show');
    overlay.setAttribute('aria-hidden', 'true');
  }
}

function openCouponPopup() {
  var overlay = document.getElementById('couponOverlay');
  if (overlay) {
    overlay.classList.add('show');
    overlay.setAttribute('aria-hidden', 'false');
  }
}

function toggleProfileMenu() {
  var dropdown = document.getElementById('profileDropdown');
  var menu = dropdown.querySelector('.profile-menu');
  var trigger = dropdown.querySelector('.profile-trigger');
  var isOpen = dropdown.classList.toggle('open');
  menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
  trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

document.addEventListener('click', function(e) {
  var dropdown = document.getElementById('profileDropdown');
  if (dropdown && !dropdown.contains(e.target)) {
    dropdown.classList.remove('open');
    var menu = dropdown.querySelector('.profile-menu');
    if (menu) menu.setAttribute('aria-hidden', 'true');
  }
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    var dropdown = document.getElementById('profileDropdown');
    if (dropdown) dropdown.classList.remove('open');
  }
});
/* ===== Category / Subcategory / Search filtering ===== */
var categoryButtons    = document.querySelectorAll('.category-btn');
var subcategoryButtons = document.querySelectorAll('.subcategory-btn');
var subcategoryWrapper = document.getElementById('subcategoryWrapper');
var menuItemEls        = document.querySelectorAll('.menu-item');
var sectionTitleEl     = document.getElementById('sectionTitle');
var itemCountEl        = document.getElementById('itemCount');
var noItemsEl          = document.getElementById('noItems');
var menuListEl         = document.getElementById('menuList');

var currentCategory    = 'all';
var currentSubcategory = 'all';
var currentSearch      = '';

var categoryLabels = { all: 'All Items', food: 'Food', beverages: 'Beverages', dessert: 'Dessert' };

function applyMenuFilters() {
  var visibleCount = 0;

  menuItemEls.forEach(function(item) {
    var matchesCategory = currentCategory === 'all' || item.dataset.category === currentCategory;
    var matchesSubcategory = currentCategory !== 'beverages'
      || currentSubcategory === 'all'
      || item.dataset.subcategory === currentSubcategory;
    var haystack = (item.dataset.name || '') + ' ' + (item.dataset.description || '');
    var matchesSearch = currentSearch === '' || haystack.toLowerCase().indexOf(currentSearch) !== -1;

    var visible = matchesCategory && matchesSubcategory && matchesSearch;
    item.classList.toggle('hidden', !visible);
    if (visible) visibleCount++;
  });

  document.querySelectorAll('.subcat-header').forEach(function(h) {
    var el = h.nextElementSibling, any = false;
    while (el && el.classList.contains('menu-item')) {
      if (!el.classList.contains('hidden')) { any = true; break; }
      el = el.nextElementSibling;
    }
    var singleSub = currentCategory === 'beverages' && currentSubcategory !== 'all';
    h.classList.toggle('hidden', !any || singleSub);
  });

  if (itemCountEl) itemCountEl.textContent = visibleCount + (visibleCount === 1 ? ' item' : ' items');
  if (sectionTitleEl) sectionTitleEl.textContent = categoryLabels[currentCategory] || 'All Items';
  if (noItemsEl) noItemsEl.style.display = visibleCount === 0 ? 'block' : 'none';
  if (menuListEl) menuListEl.style.display = visibleCount === 0 ? 'none' : 'block';
}

categoryButtons.forEach(function(btn) {
  btn.addEventListener('click', function() {
    categoryButtons.forEach(function(b) { b.classList.remove('active'); });
    this.classList.add('active');
    currentCategory = this.dataset.category;

    if (currentCategory === 'beverages') {
      if (subcategoryWrapper) subcategoryWrapper.classList.add('show');
    } else {
      if (subcategoryWrapper) subcategoryWrapper.classList.remove('show');
      currentSubcategory = 'all';
      subcategoryButtons.forEach(function(b) { b.classList.remove('active'); });
      var allSubBtn = document.querySelector('.subcategory-btn[data-sub="all"]');
      if (allSubBtn) allSubBtn.classList.add('active');
    }
    applyMenuFilters();
  });
});

subcategoryButtons.forEach(function(btn) {
  btn.addEventListener('click', function() {
    subcategoryButtons.forEach(function(b) { b.classList.remove('active'); });
    this.classList.add('active');
    currentSubcategory = this.dataset.sub;
    applyMenuFilters();
  });
});

function searchMenu(value) {
  currentSearch = (value || '').toLowerCase().trim();
  applyMenuFilters();
}

function closeDescModal() {
  var modal = document.getElementById('descModal');
  if (modal) {
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
  }
}

function showToast(msg) {
  var toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(window._toastTimer);
  window._toastTimer = setTimeout(function() { toast.classList.remove('show'); }, 2200);
}

/* openPM is defined further down the page (product detail modal script).
   This alias exists because the "more" link uses onclick="openProductModal(this)". */
function openProductModal(el) {
  if (typeof openPM === 'function') openPM(el);
}

function copyCode(btn, code) {
  navigator.clipboard.writeText(code).then(function() {
    btn.textContent = 'Copied!';
    btn.classList.add('copied');
    setTimeout(function() {
      btn.textContent = 'Copy';
      btn.classList.remove('copied');
    }, 1500);
  });
}

function safeToast(msg) {
  if (typeof showToast === 'function') { showToast(msg); }
}

function saveCoupon(btn, couponId, code) {
  if (btn.disabled) return;
  btn.disabled = true;
  var original = btn.textContent;
  btn.textContent = '...';

  fetch('save_coupon.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'coupon_id=' + encodeURIComponent(couponId)
        + '&csrf_token=' + encodeURIComponent('<?= $csrfToken ?>')
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    btn.disabled = false;
    if (data.success) {
      btn.textContent = 'Saved';
      btn.classList.add('saved');
      openSaveConfirm(code, !!data.duplicate);
    } else {
      btn.textContent = original;
      safeToast(data.message || 'Could not save coupon.');
    }
  })
  .catch(function() {
    btn.disabled = false;
    btn.textContent = original;
    safeToast('Could not save coupon — please try again.');
  });
}

function openSaveConfirm(code, alreadySaved) {
  document.getElementById('saveConfirmText').textContent = alreadySaved
    ? 'You already saved ' + code + ' — find it under My Coupons.'
    : code + ' is saved. Find it under My Coupons before it expires.';
  var overlay = document.getElementById('saveConfirmOverlay');
  overlay.classList.add('show');
  overlay.setAttribute('aria-hidden', 'false');
}

function closeSaveConfirm() {
  var overlay = document.getElementById('saveConfirmOverlay');
  overlay.classList.remove('show');
  overlay.setAttribute('aria-hidden', 'true');
}

document.getElementById('saveConfirmOverlay').addEventListener('click', function(e) {
  if (e.target === this) closeSaveConfirm();
});
</script>



  <!-- ===== Product Detail Modal — drop-in replacement ===== -->
<style>
.product-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(15,23,42,0.55);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.product-modal-overlay.show { display: flex; }

.pm-box {
  background: #fff;
  border-radius: 24px;
  width: 100%;
  max-width: 380px;
  max-height: 90vh;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  display: flex;
  flex-direction: column;
  position: relative;
  animation: pmPop 0.22s ease;
}
@keyframes pmPop {
  from { transform: scale(.93); opacity: 0; }
  to   { transform: scale(1);   opacity: 1; }
}

/* Close */
.pm-close {
  position: absolute;
  top: 12px; right: 12px;
  width: 32px; height: 32px;
  border-radius: 50%;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  font-size: 1rem;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  color: #64748b;
  z-index: 10;
  font-family: inherit;
  transition: background .15s;
}
.pm-close:hover { background: #e2e8f0; }

/* Badges */
.pm-badges {
  display: flex;
  justify-content: center;
  gap: 6px;
  flex-wrap: wrap;
  padding: 20px 20px 0;
}
.pm-tag.pm-soldout { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

.pm-tag {
  font-size: 0.7rem;
  font-weight: 700;
  padding: 4px 11px;
  border-radius: 999px;
  border: 1px solid #e2e8f0;
  background: #f8fafc;
  color: #334155;
}
.pm-bestseller {
  background: #fffbeb;
  color: #92400e;
  border-color: #fde68a;
}

/* Title */
.pm-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  text-align: center;
  padding: 10px 28px 0;
  line-height: 1.3;
}

/* Image */
.pm-img-wrap {
  padding: 16px 24px;
  display: flex;
  justify-content: center;
}
.pm-img {
  width: 100%;
  max-width: 260px;
  height: 210px;
  object-fit: cover;
  border-radius: 18px;
  display: block;
  border: 1px solid #e2e8f0;
}
.pm-img-placeholder {
  width: 260px;
  height: 210px;
  background: #f8fafc;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 3.5rem;
  border: 1px solid #e2e8f0;
}

/* Description */
.pm-desc {
  font-size: 0.85rem;
  color: #64748b;
  text-align: center;
  line-height: 1.6;
  padding: 0 24px 16px;
}

/* Footer */
.pm-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 14px 18px 20px;
  border-top: 1px solid #e2e8f0;
  flex-wrap: wrap;
}

/* Add to Cart btn */
.pm-add-btn {
  background: #1f4ed8;
  color: #fff;
  border: none;
  padding: 11px 20px;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  transition: background .2s, transform .15s;
  font-family: inherit;
}
.pm-add-btn:hover { background: #1e40af; transform: translateY(-1px); }
.pm-add-btn:disabled { opacity: .65; cursor: not-allowed; transform: none; }

/* Qty stepper */
.pm-qty {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #f1f5f9;
  border-radius: 999px;
  padding: 6px 14px;
  border: 1px solid #e2e8f0;
}
.pm-qty-btn {
  background: none;
  border: none;
  font-size: 1.15rem;
  font-weight: 700;
  color: #0f172a;
  cursor: pointer;
  width: 24px; height: 24px;
  display: flex; align-items: center; justify-content: center;
  border-radius: 50%;
  transition: background .12s;
  font-family: inherit;
  line-height: 1;
}
.pm-qty-btn:hover { background: #e2e8f0; }
.pm-qty-val {
  font-size: 0.95rem;
  font-weight: 800;
  min-width: 18px;
  text-align: center;
  color: #0f172a;
}

/* Price */
.pm-price-block { text-align: right; flex-shrink: 0; }
.pm-price-amount {
  font-size: 1.2rem;
  font-weight: 900;
  color: #1e40af;
  line-height: 1;
}
.pm-price-tax { font-size: 0.68rem; color: #94a3b8; margin-top: 2px; }

@media (max-width: 400px) {
  .pm-footer { justify-content: center; }
  .pm-title { font-size: 1.1rem; }
}
</style>

<div class="product-modal-overlay" id="productModal"
     role="dialog" aria-labelledby="pmTitle" aria-hidden="true">
  <div class="pm-box">

    <button class="pm-close" onclick="closePM()" aria-label="Close">✕</button>

    <div class="pm-badges" id="pmBadges"></div>
    <div class="pm-title" id="pmTitle"></div>
    <div class="pm-img-wrap" id="pmImgWrap"></div>
    <div class="pm-desc" id="pmDesc"></div>

    <div class="pm-footer">
      <form method="post" action="add_to_cart.php" onsubmit="handlePMCart(event,this)">
        <input type="hidden" name="menu_id"    id="pmMenuId">
        <input type="hidden" name="quantity"   id="pmQtyInput" value="1">
        <input type="hidden" name="is_guest"   id="pmIsGuest">
        <input type="hidden" name="csrf_token" id="pmCsrf">
        <button type="submit" class="pm-add-btn" id="pmAddBtn">Add to cart</button>
      </form>

      <div class="pm-qty">
        <button type="button" class="pm-qty-btn" onclick="pmChangeQty(-1)" aria-label="Decrease">−</button>
        <span class="pm-qty-val" id="pmQtyDisplay">1</span>
        <button type="button" class="pm-qty-btn" onclick="pmChangeQty(1)" aria-label="Increase">+</button>
      </div>

      <div class="pm-price-block">
        <div class="pm-price-amount">RM <span id="pmPrice">0.00</span></div>
        <div class="pm-price-tax">+ tax</div>
      </div>
    </div>

  </div>
</div>

<script>
var _pmBasePrice = 0;

function openPM(el) {
  var card = el.closest ? el.closest('.menu-item') : el;
  if (!card) return;

  var name       = card.dataset.name        || '';
  var desc       = card.dataset.description || '';
  var price      = parseFloat(card.dataset.price) || 0;
  var imgUrl     = card.dataset.img         || '';
  var menuId     = card.dataset.id;
  var isGuest    = card.dataset.isguest;
  var csrf       = card.dataset.csrf;
  var category   = card.dataset.category   || '';
  var bestSeller = card.dataset.bestseller === '1';
  var isAvailable = card.dataset.available !== '0';

  _pmBasePrice = price;

  document.getElementById('pmQtyInput').value       = '1';
  document.getElementById('pmQtyDisplay').textContent = '1';
  document.getElementById('pmPrice').textContent    = price.toFixed(2);

  var imgWrap = document.getElementById('pmImgWrap');
  if (imgUrl) {
    imgWrap.innerHTML = '<img src="' + imgUrl + '" alt="' + name + '" class="pm-img" '
      + 'onerror="this.style.display=\'none\';this.insertAdjacentHTML(\'afterend\','
      + '\'<div class=\\\'pm-img-placeholder\\\'>&#9749;</div>\');">';
  } else {
    var emoji = category === 'food' ? '&#127857;' : category === 'dessert' ? '&#127856;' : '&#9749;';
    imgWrap.innerHTML = '<div class="pm-img-placeholder">' + emoji + '</div>';
  }

  var catLabel = category === 'food' ? 'Food'
               : category === 'dessert' ? 'Dessert'
               : 'Beverages';
  document.getElementById('pmBadges').innerHTML =
    '<span class="pm-tag">' + catLabel + '</span>'
    + (bestSeller ? '<span class="pm-tag pm-bestseller">Best Seller</span>' : '')
    + (isAvailable ? '' : '<span class="pm-tag pm-soldout">Sold Out</span>');

  document.getElementById('pmTitle').textContent   = name;
  document.getElementById('pmDesc').textContent    = desc || 'No description available.';
  document.getElementById('pmMenuId').value        = menuId;
  document.getElementById('pmIsGuest').value       = isGuest;
  document.getElementById('pmCsrf').value          = csrf;

  var addBtn = document.getElementById('pmAddBtn');
  addBtn.disabled = !isAvailable;
  addBtn.textContent = isAvailable ? 'Add to cart' : 'Sold Out';
  addBtn.style.background = '';

  var modal = document.getElementById('productModal');
  modal.classList.add('show');
  modal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}

function closePM() {
  var modal = document.getElementById('productModal');
  modal.classList.remove('show');
  modal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  var btn = document.getElementById('pmAddBtn');
  btn.textContent = 'Add to cart';
  btn.style.background = '';
  btn.disabled = false;
}

function pmChangeQty(delta) {
  var input   = document.getElementById('pmQtyInput');
  var display = document.getElementById('pmQtyDisplay');
  var priceEl = document.getElementById('pmPrice');
  var qty = Math.min(99, Math.max(1, parseInt(input.value || 1) + delta));
  input.value          = qty;
  display.textContent  = qty;
  priceEl.textContent  = (_pmBasePrice * qty).toFixed(2);
}

function handlePMCart(e, form) {
  e.preventDefault();
  var btn = document.getElementById('pmAddBtn');
  if (btn.disabled) return;

  btn.textContent = '...';
  btn.disabled = true;

  fetch('add_to_cart.php', {
    method: 'POST',
    body: new FormData(form),
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!data.success) {
      btn.textContent = 'Sold Out';
      btn.style.background = '#dc2626';
      showToast(data.message || 'Could not add item.');
      return;
    }
    btn.textContent = '✓ Added!';
    btn.style.background = '#16a34a';
    var qty = parseInt(document.getElementById('pmQtyInput').value || 1);
    var badge = document.getElementById('floatingBadge');
    if (badge) badge.textContent = parseInt(badge.textContent || 0) + qty;
    showToast('Item added to cart');
    setTimeout(function() { closePM(); }, 1100);
  })
  .catch(function() {
    btn.textContent = 'Retry';
    btn.style.background = '#dc2626';
    btn.disabled = false;
    showToast('Could not add item — please try again.');
  });
}

/* Wire up card clicks */
document.querySelectorAll('.menu-item').forEach(function(card) {
  card.addEventListener('click', function(e) {
    if (e.target.closest('form') || e.target.closest('.add-btn')) return;
    openPM(this);
  });
});

/* Wire up "more" links */
document.querySelectorAll('.more-link').forEach(function(link) {
  link.addEventListener('click', function(e) { e.preventDefault(); openPM(this); });
  link.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); openPM(this); }
  });
});

/* Backdrop + Escape */
document.getElementById('productModal').addEventListener('click', function(e) {
  if (e.target === this) closePM();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closePM();
});
</script>
</body>
</html>