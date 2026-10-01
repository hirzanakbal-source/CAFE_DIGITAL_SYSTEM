<?php
require __DIR__ . '/db.php';


// ===== CHECK SESSION =====
if (!isset($_SESSION['customer_id']) && !isset($_SESSION['guest'])) {
    header('Location: welcome.php');
    exit;
}

$isGuest     = isset($_SESSION['guest']) && $_SESSION['guest'] === true;
$cart_items  = [];
$customer_id = null;

if ($isGuest) {
    // ===== GUEST: Get cart from SESSION =====
    $cart_items = $_SESSION['guest_cart'] ?? [];
} else {
    // ===== LOGGED IN: Get cart from DATABASE =====
    $customer_id = $_SESSION['customer_id'];

      $stmt = $pdo->prepare("
        SELECT c.cart_id, c.menu_id, m.menu_name, m.price, c.quantity
        FROM cart c
        JOIN menu m ON c.menu_id = m.menu_id
        WHERE c.customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== CALCULATE TOTALS =====
$subtotal   = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$sstRate    = 0.06;
$sstAmount  = $subtotal * $sstRate;
$grandTotal = $subtotal + $sstAmount;

// ===== AVAILABLE COUPONS (for the Coupons browse popup) =====
$availableCoupons = [];
if (!$isGuest) {
    try {
        $tableCheck  = $pdo->query("SHOW TABLES LIKE 'coupon'");
        $tableExists = $tableCheck->rowCount() > 0;

             if ($tableExists) {
                $couponStmt = $pdo->prepare("
                SELECT * FROM coupon
                WHERE is_active = 1
                AND expiry_date >= CURDATE()
                AND coupon_id NOT IN (
                SELECT coupon_id FROM coupon_usage WHERE customer_id = ?
                )
                ORDER BY discount DESC
            ");
            $couponStmt->execute([$customer_id]);
            $availableCoupons = $couponStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $availableCoupons = [];
    }
}

// ===== DUMMY QR GENERATOR (visual only — not a real, scannable QR code) =====
function renderDummyQR($modules = 21, $cellSize = 6) {
    $finder = [
        [1,1,1,1,1,1,1],
        [1,0,0,0,0,0,1],
        [1,0,1,1,1,0,1],
        [1,0,1,1,1,0,1],
        [1,0,1,1,1,0,1],
        [1,0,0,0,0,0,1],
        [1,1,1,1,1,1,1],
    ];

    $grid = [];
    for ($y = 0; $y < $modules; $y++) {
        for ($x = 0; $x < $modules; $x++) {
            $grid[$y][$x] = mt_rand(0, 1);
        }
    }

    // Stamp the three QR "finder" corner squares so it looks authentic —
    // the rest stays random noise, so it can never actually decode.
    $corners = [[0, 0], [0, $modules - 7], [$modules - 7, 0]];
    foreach ($corners as [$oy, $ox]) {
        for ($y = 0; $y < 7; $y++) {
            for ($x = 0; $x < 7; $x++) {
                $grid[$oy + $y][$ox + $x] = $finder[$y][$x];
            }
        }
    }

    $px = $modules * $cellSize;
    $svg = '<svg viewBox="0 0 ' . $px . ' ' . $px . '" width="150" height="150" xmlns="http://www.w3.org/2000/svg">';
    $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
    for ($y = 0; $y < $modules; $y++) {
        for ($x = 0; $x < $modules; $x++) {
            if ($grid[$y][$x]) {
                $svg .= '<rect x="' . ($x * $cellSize) . '" y="' . ($y * $cellSize) . '" width="' . $cellSize . '" height="' . $cellSize . '" fill="#1a1a2e"></rect>';
            }
        }
    }
    $svg .= '</svg>';
    return $svg;
}

// ===== SVG ICON HELPER =====
function icon($name, $size = 20, $stroke = 'currentColor') {
    $paths = [
        'cart'            => '<circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>',
        'utensils'        => '<path d="M3 2v7c0 1.1.9 2 2 2h2a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3zm0 0v7"></path>',
        'trash'           => '<polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>',
        'ticket'          => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"></path><path d="M13 5v2M13 17v2M13 11v2"></path>',
        'clipboard'       => '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>',
        'info'            => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>',
        'credit-card'     => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>',
        'flask'           => '<path d="M9 2v6.5L4.5 18a2 2 0 0 0 1.8 2.9h11.4a2 2 0 0 0 1.8-2.9L15 8.5V2"></path><path d="M8.5 2h7"></path><path d="M7 16h10"></path>',
        'bank'            => '<line x1="3" y1="21" x2="21" y2="21"></line><line x1="5" y1="21" x2="5" y2="10"></line><line x1="9" y1="21" x2="9" y2="10"></line><line x1="15" y1="21" x2="15" y2="10"></line><line x1="19" y1="21" x2="19" y2="10"></line><polygon points="12 3 21 8 3 8"></polygon>',
        'smartphone'      => '<rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line>',
        'camera'          => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle>',
        'cash'            => '<rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="3"></circle><path d="M6 12h.01M18 12h.01"></path>',
        'receipt'         => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"></path><line x1="8" y1="7" x2="16" y2="7"></line><line x1="8" y1="11" x2="16" y2="11"></line><line x1="8" y1="15" x2="12" y2="15"></line>',
        'check-circle'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
        'alert-triangle'  => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        'arrow-left'      => '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>',
        'bookmark'        => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>',
    ];
    if (!isset($paths[$name])) return '';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . $stroke . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;flex-shrink:0;">' . $paths[$name] . '</svg>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Your Cart - MIA CAFE</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

 body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: 
      linear-gradient(rgba(244,246,249,0.9), rgba(244,246,249,0.9)),
      url('../assets/images/background.png');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
    background-repeat: no-repeat;
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

.navbar-title {
    font-size: 1.2rem;
    font-weight: 800;
    color: #222;
    display: flex;
    align-items: center;
    gap: 8px;
}

.navbar-title span { color: #080909; }

.navbar-logo {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
}

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

  .back-btn:hover {
    background: #1558b0;
    color: #fff;
  }

  .navbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .coupon-nav-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 0.78rem;
    font-weight: 700;
    border: 1px solid #1558b0;
    background: #fff;
    color: #1558b0;
    cursor: pointer;
    transition: all .2s ease;
    font-family: inherit;
    white-space: nowrap;
  }

  .coupon-nav-btn:hover { background: #eff6ff; }

  /* ===== MAIN CONTAINER ===== */
  .main-container {
    max-width: 650px;
    margin: 25px auto;
    padding: 0 15px;
  }

  /* ===== PAGE TITLE ===== */
  .page-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: #222;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .page-title span {
    background: #1558b0;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
  }

  /* ===== EMPTY CART ===== */
  .empty-cart {
    background: #fff;
    border-radius: 16px;
    padding: 50px 30px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
  }

  .empty-cart .empty-icon {
    display: flex;
    justify-content: center;
    margin-bottom: 15px;
    color: #ccc;
  }

  .empty-cart h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
  }

  .empty-cart p {
    font-size: 0.9rem;
    color: #888;
    margin-bottom: 20px;
  }

  .go-menu-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #1558b0;
    color: #fff;
    text-decoration: none;
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.95rem;
    transition: background 0.3s;
  }

  .go-menu-btn:hover { background: #10418f; }

  /* ===== CART CARD ===== */
  .cart-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 15px;
  }

  .cart-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #555;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* ===== CART ITEMS ===== */
  .cart-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #f5f5f5;
    gap: 12px;
    transition: background 0.2s;
  }

  .cart-item:last-child { border-bottom: none; }
  .cart-item:hover { background: #fafafa; }

  .item-number {
    width: 28px;
    height: 28px;
    background: #f0f0f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 700;
    color: #666;
    flex-shrink: 0;
  }

  .item-info { flex: 1; }

  .item-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #222;
    margin-bottom: 3px;
  }

  .item-price-unit {
    font-size: 0.78rem;
    color: #aaa;
  }

  .qty-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }

  .qty-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #0d47a1;
    background: #fff;
    color: #0d47a1;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    text-decoration: none;
    line-height: 1;
  }

  .qty-btn:hover {
    background: #1558b0;
    color: #fff;
  }

  .qty-num {
    font-size: 0.95rem;
    font-weight: 700;
    color: #222;
    min-width: 20px;
    text-align: center;
  }

  .item-subtotal {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1558b0;
    min-width: 70px;
    text-align: right;
    flex-shrink: 0;
  }

  .remove-btn {
    background: none;
    border: none;
    color: #ccc;
    cursor: pointer;
    padding: 4px;
    border-radius: 50%;
    transition: all 0.2s;
    flex-shrink: 0;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .remove-btn:hover {
    color: #1558b0;
    background: #fff0f0;
  }

  /* ===== ORDER SUMMARY ===== */
  .summary-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 15px;
  }

  .summary-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #555;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .summary-body { padding: 15px 20px; }

  .summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    font-size: 0.9rem;
    color: #555;
    border-bottom: 1px solid #f5f5f5;
  }

  .summary-row:last-child { border-bottom: none; }
  .summary-row .label { font-weight: 500; }
  .summary-row .value { font-weight: 600; color: #333; }

  .summary-row.sst-row .label {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .sst-badge {
    background: #fff3cd;
    color: #856404;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
    border: 1px solid #ffc107;
  }

  .summary-row.total-row {
    padding-top: 12px;
    margin-top: 5px;
    border-top: 2px solid #f0f0f0;
    border-bottom: none;
  }

  .summary-row.total-row .label {
    font-size: 1rem;
    font-weight: 800;
    color: #222;
  }

  .summary-row.total-row .value {
    font-size: 1.2rem;
    font-weight: 800;
    color: #1558b0;
  }

  .sst-info {
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-radius: 10px;
    padding: 10px 14px;
    margin-top: 12px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.78rem;
    color: #795548;
    line-height: 1.4;
  }

  /* ===== COUPON SECTION (matches cart-card / summary-card pattern) ===== */
  .coupon-section {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 15px;
  }

  .coupon-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #555;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .coupon-body { padding: 15px 20px; }

  .coupon-input-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }

  .coupon-input-row .coupon-input { min-width: 140px; }

  .coupon-input {
    flex: 1;
    padding: 10px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: border-color 0.3s;
    color: #333;
  }

  .coupon-input:focus {
    border-color: #1558b0;
    outline: none;
  }

  .coupon-apply-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #1558b0;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    transition: background 0.3s;
    white-space: nowrap;
  }

  .coupon-apply-btn:hover { background: #10418f; }

  .coupon-save-btn {
    display: flex;
    align-items: center;
    gap: 4px;
    background: #fff;
    color: #1558b0;
    border: 1px solid #1558b0;
    padding: 7px 10px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.7rem;
    cursor: pointer;
    transition: .2s;
    white-space: nowrap;
    font-family: inherit;
  }

  .coupon-save-btn:hover { background: #eff6ff; }
  .coupon-save-btn:disabled { opacity: .6; cursor: not-allowed; }

  .coupon-save-btn.saved {
    background: #28a745;
    border-color: #28a745;
    color: #fff;
  }

  /* ===== Coupon browse popup (matches menu.php, prefixed to avoid clashing with the coupon-entry card) ===== */
  .cp-overlay {
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

  .cp-overlay.show { display: flex; }

  .cp-popup {
    background: #fff;
    border-radius: 18px;
    max-width: 460px;
    width: 100%;
    box-shadow: 0 22px 65px rgba(0,0,0,0.28);
    animation: popIn 0.28s ease;
    overflow: hidden;
  }

  .cp-header {
    background: linear-gradient(135deg, #1a1a2e, #1558b0);
    padding: 22px 20px 18px;
    text-align: center;
    position: relative;
  }

  .cp-close {
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

  .cp-header h2 {
    font-size: 1.05rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 4px;
  }

  .cp-header p {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.9);
  }

  .cp-body {
    padding: 16px 16px 10px;
    max-height: 340px;
    overflow-y: auto;
  }

  .cp-subtitle {
    font-size: 0.76rem;
    color: #64748b;
    text-align: center;
    margin-bottom: 12px;
  }

  .cp-card {
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

  .cp-card:hover { background: #f8fbff; }

  .cp-discount {
    background: #eff6ff;
    color: #1558b0;
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    min-width: 72px;
    flex-shrink: 0;
    border: 1px solid #bfdbfe;
  }

  .cp-discount-amount {
    font-size: 1.02rem;
    font-weight: 900;
    line-height: 1;
  }

  .cp-discount-type {
    font-size: 0.62rem;
    font-weight: 700;
    margin-top: 2px;
    letter-spacing: .08em;
  }

  .cp-info { flex: 1; min-width: 0; }

  .cp-code {
    font-size: 0.86rem;
    font-weight: 800;
    color: #1558b0;
    letter-spacing: 1px;
    margin-bottom: 2px;
    word-break: break-all;
  }

  .cp-min { font-size: 0.7rem; color: #64748b; margin-bottom: 2px; }
  .cp-expiry { font-size: 0.68rem; color: #94a3b8; }

  .cp-actions {
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
  }

  .cp-copy-btn {
    background: #1558b0;
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

  .cp-copy-btn:hover { background: #10418f; }
  .cp-copy-btn.copied { background: #28a745; }

  .cp-save-btn {
    background: #fff;
    color: #1558b0;
    border: 1px solid #1558b0;
    padding: 7px 10px;
    border-radius: 8px;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
    white-space: nowrap;
    font-family: inherit;
  }

  .cp-save-btn:hover { background: #eff6ff; }
  .cp-save-btn:disabled { opacity: .6; cursor: not-allowed; }
  .cp-save-btn.saved { background: #28a745; border-color: #28a745; color: #fff; }

  .cp-no-coupons {
    text-align: center;
    padding: 20px 12px;
    color: #94a3b8;
    font-size: 0.78rem;
  }

  .cp-footer { padding: 0 16px 16px; }

  .cp-footer-btn {
    width: 100%;
    padding: 11px;
    background: #1a1a2e;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 0.82rem;
    font-weight: 700;
    cursor: pointer;
  }

  .cp-footer-btn:hover { background: #12121f; }

  /* ===== Square save-confirmation popup (matches menu.php) ===== */
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
    animation: popIn 0.22s ease;
  }

  .save-confirm-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #dcfce7;
    color: #16a34a;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
  }

  .save-confirm-box h4 {
    font-size: 1rem;
    font-weight: 800;
    color: #222;
    margin-bottom: 8px;
  }

  .save-confirm-box p {
    font-size: 0.78rem;
    color: #888;
    line-height: 1.5;
    margin-bottom: 16px;
  }

  .save-confirm-close {
    padding: 9px 20px;
    background: #1558b0;
    color: #fff;
    border: none;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
  }

  .save-confirm-close:hover { background: #10418f; }

  .coupon-msg {
    margin-top: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 6px;
    display: none;
    align-items: center;
    gap: 6px;
  }

  .coupon-msg.success {
    background: #d4edda;
    color: #155724;
    display: flex;
  }

  .coupon-msg.error {
    background: #f8d7da;
    color: #721c24;
    display: flex;
  }

  /* ===== PAYMENT METHOD SECTION ===== */
  .payment-section {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 15px;
  }

  .payment-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #555;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .payment-body { padding: 15px 20px; }

  /* ===== PAYMENT METHOD CARDS ===== */
  .payment-methods {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 15px;
  }

  .payment-method-card {
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 14px 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    background: #fafafa;
    position: relative;
  }

  .payment-method-card:hover {
  border-color: #1558b0;
  background: #f0f4ff;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(21,88,176,0.1);
}

.payment-method-card.selected {
  border-color: #1558b0;
  background: #eaf1ff;
  box-shadow: 0 4px 15px rgba(21,88,176,0.15);
}

  .payment-method-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
  }

  .payment-check {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #ddd;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    color: transparent;
  }

  .payment-method-card.selected .payment-check {
    background: #1558b0;
    border-color: #1558b0;
    color: #fff;
  }

  .payment-icon {
    display: flex;
    color: #555;
  }

  .payment-method-card.selected .payment-icon { color: #1558b0; }
  .payment-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: #333;
    text-align: center;
  }

  .payment-desc {
    font-size: 0.7rem;
    color: #aaa;
    text-align: center;
    line-height: 1.3;
  }

  /* ===== PAYMENT DETAIL PANEL ===== */
  .payment-detail {
    display: none;
    border-radius: 12px;
    padding: 15px;
    margin-top: 5px;
    animation: fadeIn 0.3s ease;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .payment-detail.show { display: block; }

  /* ===== ONLINE BANKING DETAIL ===== */
  .banking-detail {
    background: #f0f4ff;
    border: 1px solid #c5d5ff;
  }

  .bank-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 10px;
  }

  .bank-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    background: #fff;
    transition: all 0.2s;
    font-size: 0.82rem;
    font-weight: 600;
    color: #333;
  }

  .bank-item:hover {
    border-color: #3a5bd9;
    background: #f0f4ff;
  }

  .bank-item.selected {
    border-color: #3a5bd9;
    background: #e8eeff;
  }

  .bank-item input[type="radio"] {
    accent-color: #3a5bd9;
    width: 15px;
    height: 15px;
    flex-shrink: 0;
  }

  .bank-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .bank-logo-img {
    width: 22px;
    height: 22px;
    object-fit: contain;
    flex-shrink: 0;
    border-radius: 4px;
  }

  /* ===== E-WALLET DETAIL ===== */
  .ewallet-detail {
    background: #f0fff4;
    border: 1px solid #b2dfdb;
  }

  .ewallet-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 10px;
  }

  .ewallet-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    background: #fff;
    transition: all 0.2s;
    font-size: 0.82rem;
    font-weight: 600;
    color: #333;
  }

  .ewallet-item:hover {
    border-color: #28a745;
    background: #f0fff4;
  }

  .ewallet-item.selected {
    border-color: #28a745;
    background: #e8fff0;
  }

  .ewallet-item input[type="radio"] {
    accent-color: #28a745;
    width: 15px;
    height: 15px;
    flex-shrink: 0;
  }

  .ewallet-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .ewallet-logo-img {
    width: 22px;
    height: 22px;
    object-fit: contain;
    flex-shrink: 0;
    border-radius: 4px;
  }

  /* ===== QR PAYMENT DETAIL ===== */
  .qr-detail {
    background: #fff8f0;
    border: 1px solid #ffcc80;
    text-align: center;
  }

  .qr-code-box {
    background: #fff;
    border: 3px solid #333;
    border-radius: 12px;
    padding: 15px;
    display: inline-block;
    margin: 12px auto;
    position: relative;
  }

  .qr-label {
    font-size: 0.78rem;
    color: #888;
    margin-top: 8px;
  }

  .qr-amount {
    font-size: 1.1rem;
    font-weight: 800;
    color: #e31937;
    margin: 8px 0;
  }

  .qr-note {
    font-size: 0.75rem;
    color: #aaa;
    font-style: italic;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
  }

  /* ===== CASH DETAIL ===== */
  .cash-detail {
    background: #f9f0ff;
    border: 1px solid #ce93d8;
  }

  .cash-info-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
  }

  .cash-info-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #f0e0ff;
  }

  .cash-info-icon {
    display: flex;
    flex-shrink: 0;
    color: #9c27b0;
    margin-top: 1px;
  }

  .cash-info-text {
    font-size: 0.82rem;
    color: #555;
    line-height: 1.4;
  }

  .cash-info-text b { color: #333; }

  /* ===== DUMMY BADGE ===== */
  .dummy-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffc107;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 0.72rem;
    font-weight: 700;
    margin-bottom: 10px;
  }

  /* ===== PAYMENT DETAIL TITLE ===== */
  .detail-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* ===== PLACE ORDER BUTTON ===== */
  .place-order-btn {
  width: 100%;
  padding: 16px;
  background: #1558b0;
  color: #fff;
  border: none;
  border-radius: 14px;
  font-size: 1.05rem;
  font-weight: 800;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 5px 20px rgba(21,88,176,0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.place-order-btn:hover {
  background: #10418f;
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(21,88,176,0.5);
}
  .place-order-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
  }

  /* ===== GUEST NOTE ===== */
  .guest-note {
    background: #f0f4ff;
    border: 1px solid #c5d5ff;
    border-radius: 10px;
    padding: 12px 15px;
    margin-bottom: 15px;
    font-size: 0.82rem;
    color: #3a5bd9;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    line-height: 1.5;
  }

  .guest-note a {
    color: #e31937;
    font-weight: 700;
    text-decoration: none;
  }

  .guest-note a:hover { text-decoration: underline; }

  /* ===== PAYMENT NOTE ===== */
  .payment-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-align: center;
    font-size: 0.78rem;
    color: #aaa;
    margin-top: 10px;
  }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 480px) {
    .main-container { padding: 0 10px; }
    .cart-item { padding: 12px 14px; gap: 8px; }
    .item-name { font-size: 0.88rem; }
    .item-subtotal { min-width: 60px; font-size: 0.88rem; }
    .payment-methods { grid-template-columns: 1fr 1fr; }
    .bank-list,
    .ewallet-list { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<!-- ===== TOP NAVBAR ===== -->
<div class="top-navbar">
  <div class="navbar-title">
    <img src="../assets/images/logo.jpg" alt="MIA Coffee logo" class="navbar-logo">
    <span>MIA COFFEE</span>
  </div>
  <div class="navbar-right">
    <?php if (!$isGuest): ?>
      <button type="button" class="coupon-nav-btn" onclick="openCouponPopup()" aria-label="View coupons">
        <?= icon('ticket', 14) ?> Coupons
      </button>
    <?php endif; ?>
    <a href="menu.php" class="back-btn"><?= icon('arrow-left', 16) ?> Back to Menu</a>
  </div>
</div>

<!-- ===== MAIN CONTAINER ===== -->
<div class="main-container">

  <!-- Page Title -->
  <div class="page-title">
    <?= icon('cart', 24) ?> My Cart
    <?php if (!empty($cart_items)): ?>
      <span><?= count($cart_items) ?> items</span>
    <?php endif; ?>
  </div>

  <?php if (empty($cart_items)): ?>
  <!-- ===== EMPTY CART ===== -->
  <div class="empty-cart">
    <span class="empty-icon"><?= icon('cart', 56) ?></span>
    <h3>Your cart is empty</h3>
    <p>Looks like you haven't added anything yet.</p>
    <a href="menu.php" class="go-menu-btn"><?= icon('utensils', 16) ?> Browse Menu</a>
  </div>

  <?php else: ?>

  <!-- ===== GUEST NOTE ===== -->
  <?php if ($isGuest): ?>
  <div class="guest-note">
    <span><?= icon('info', 16) ?></span>
    <span>
      You are ordering as a <b>Guest</b>.
      <a href="login.php">Login</a> or
      <a href="register.php">Register</a>
      to save your order history and get exclusive coupons!
    </span>
  </div>
  <?php endif; ?>

  <!-- ===== CART ITEMS ===== -->
  <div class="cart-card">
    <div class="cart-card-header"><?= icon('utensils', 16) ?> Order Items</div>
    <?php
    $subtotal = 0;
    foreach ($cart_items as $index => $item):
      $itemSubtotal = $item['price'] * $item['quantity'];
      $subtotal    += $itemSubtotal;
    ?>
    <div class="cart-item">
      <div class="item-number"><?= $index + 1 ?></div>
      <div class="item-info">
        <div class="item-name">
          <?= htmlspecialchars($item['menu_name']) ?>
        </div>
        <div class="item-price-unit">
          RM <?= number_format($item['price'], 2) ?> each
        </div>
      </div>
      <div class="qty-controls">
        <?php if ($isGuest): ?>
          <a href="update_cart.php?action=decrease&menu_id=<?= $item['menu_id'] ?>"
             class="qty-btn">−</a>
        <?php else: ?>
         <a href="update_cart.php?action=decrease&cart_id=<?= $item['cart_id'] ?>" class="qty-btn">−</a>
        <?php endif; ?>
        <span class="qty-num"><?= $item['quantity'] ?></span>
        <?php if ($isGuest): ?>
          <a href="update_cart.php?action=increase&menu_id=<?= $item['menu_id'] ?>"
             class="qty-btn">+</a>
        <?php else: ?>
          <a href="update_cart.php?action=increase&cart_id=<?= $item['cart_id'] ?>"
             class="qty-btn">+</a>
        <?php endif; ?>
      </div>
      <div class="item-subtotal">
        RM <?= number_format($itemSubtotal, 2) ?>
      </div>
      <?php if ($isGuest): ?>
        <a href="update_cart.php?action=remove&menu_id=<?= $item['menu_id'] ?>"
           class="remove-btn"
           onclick="return confirm('Remove this item?')"><?= icon('trash', 18) ?></a>
      <?php else: ?>
        <a href="update_cart.php?action=remove&cart_id=<?= $item['cart_id'] ?>"
           class="remove-btn"
           onclick="return confirm('Remove this item?')"><?= icon('trash', 18) ?></a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ===== COUPON SECTION (Logged in only) — styled like other cards ===== -->
  <?php if (!$isGuest): ?>
  <div class="coupon-section">
    <div class="coupon-header">
      <?= icon('ticket', 16) ?> Have a Coupon Code?
    </div>
    <div class="coupon-body">
     
              <div class="coupon-input-row">
        <input type="text"
               class="coupon-input"
               id="couponInput"
               placeholder="Enter coupon code..."
               maxlength="20">
        <button class="coupon-apply-btn"
                onclick="applyCoupon()">
          Apply
        </button>
      </div>
      <div class="coupon-msg" id="couponMsg"></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ===== ORDER SUMMARY ===== -->
  <?php
  $sstRate    = 0.06;
  $sstAmount  = $subtotal * $sstRate;
  $grandTotal = $subtotal + $sstAmount;
  ?>
  <div class="summary-card">
    <div class="summary-header"><?= icon('clipboard', 16) ?> Order Summary</div>
    <div class="summary-body">

      <!-- Subtotal -->
      <div class="summary-row">
        <span class="label">Subtotal</span>
        <span class="value">
          RM <?= number_format($subtotal, 2) ?>
        </span>
      </div>

      <!-- SST -->
      <div class="summary-row sst-row">
        <span class="label">
          Service Tax
          <span class="sst-badge">SST 6%</span>
        </span>
        <span class="value">
          RM <?= number_format($sstAmount, 2) ?>
        </span>
      </div>

      <!-- Discount (hidden by default) -->
      <div class="summary-row"
           id="discountRow"
           style="display:none;">
        <span class="label" style="color:#28a745; display:flex; align-items:center; gap:6px;">
          <?= icon('ticket', 14, '#28a745') ?> Coupon Discount
        </span>
        <span class="value"
              style="color:#28a745;"
              id="discountValue">
          - RM 0.00
        </span>
      </div>

      <!-- Grand Total -->
      <div class="summary-row total-row">
        <span class="label">Grand Total</span>
        <span class="value" id="grandTotalDisplay">
          RM <?= number_format($grandTotal, 2) ?>
        </span>
      </div>

      <!-- SST Info -->
      <div class="sst-info">
        <span><?= icon('info', 16) ?></span>
        <span>
          Prices are subject to 6% Sales & Service Tax (SST)
          as required by Malaysian law.
        </span>
      </div>

    </div>
  </div>

  <!-- ===== PAYMENT METHOD SECTION ===== -->
  <div class="payment-section">
    <div class="payment-header">
      <?= icon('credit-card', 16) ?> Select Payment Method
    </div>
    <div class="payment-body">

      <!-- Dummy Badge -->
      <div style="text-align:center; margin-bottom:15px;">
        <span class="dummy-badge">
          <?= icon('flask', 14) ?> Demo Mode — No Real Transaction
        </span>
      </div>

      <!-- ===== PAYMENT METHOD CARDS ===== -->
      <div class="payment-methods">

        <!-- Online Banking -->
        <div class="payment-method-card"
             id="card-banking"
             onclick="selectPayment('banking')">
          <input type="radio"
                 name="payment_method"
                 value="banking"
                 id="pay-banking">
          <div class="payment-check" id="check-banking"><?= icon('check-circle', 12) ?></div>
          <div class="payment-icon"><?= icon('bank', 26) ?></div>
          <div class="payment-name">Online Banking</div>
          <div class="payment-desc">
            Maybank, CIMB, RHB & more
          </div>
        </div>

        <!-- E-Wallet -->
        <div class="payment-method-card"
             id="card-ewallet"
             onclick="selectPayment('ewallet')">
          <input type="radio"
                 name="payment_method"
                 value="ewallet"
                 id="pay-ewallet">
          <div class="payment-check" id="check-ewallet"><?= icon('check-circle', 12) ?></div>
          <div class="payment-icon"><?= icon('smartphone', 26) ?></div>
          <div class="payment-name">E-Wallet</div>
          <div class="payment-desc">
            Touch 'n Go, GrabPay & more
          </div>
        </div>

        <!-- QR Payment -->
        <div class="payment-method-card"
             id="card-qr"
             onclick="selectPayment('qr')">
          <input type="radio"
                 name="payment_method"
                 value="qr"
                 id="pay-qr">
          <div class="payment-check" id="check-qr"><?= icon('check-circle', 12) ?></div>
          <div class="payment-icon"><?= icon('camera', 26) ?></div>
          <div class="payment-name">QR Payment</div>
          <div class="payment-desc">
            Scan & Pay instantly
          </div>
        </div>

        <!-- Cash -->
        <div class="payment-method-card"
             id="card-cash"
             onclick="selectPayment('cash')">
          <input type="radio"
                 name="payment_method"
                 value="cash"
                 id="pay-cash">
          <div class="payment-check" id="check-cash"><?= icon('check-circle', 12) ?></div>
          <div class="payment-icon"><?= icon('cash', 26) ?></div>
          <div class="payment-name">Cash</div>
          <div class="payment-desc">
            Pay at counter
          </div>
        </div>

      </div>

      <!-- ===== ONLINE BANKING DETAIL ===== -->
      <div class="payment-detail banking-detail"
           id="detail-banking">
        <div class="detail-title">
          <?= icon('bank', 16) ?> Select Your Bank
        </div>
        <p style="font-size:0.78rem; color:#888; margin-bottom:5px;">
          You will be redirected to your bank's secure page.
          <b>(Demo only - no real redirect)</b>
        </p>
        <div class="bank-list">
          <label class="bank-item" id="bank-maybank">
            <input type="radio"
                   name="bank_choice"
                   value="Maybank"
                   onchange="selectBank(this)">
            <img src="https://th.bing.com/th/id/OIP.N_I5h7vs6mmHGf2BW3rquQAAAA?w=179&h=180&c=7&r=0&o=7&pid=1.7&rm=3" alt="Maybank" class="bank-logo-img">
            Maybank2u
          </label>
         <label class="bank-item" id="bank-cimb">
  <input type="radio" name="bank_choice" value="CIMB" onchange="selectBank(this)">
  <img src="/CAFE_SYSTEM/assets/images/banks/cimb-logo.webp" alt="CIMB" class="bank-logo-img">
  CIMB Clicks
</label>

<label class="bank-item" id="bank-rhb">
  <input type="radio" name="bank_choice" value="RHB" onchange="selectBank(this)">
  <img src="/CAFE_SYSTEM/assets/images/banks/rhb-logo.webp" alt="RHB" class="bank-logo-img">
  RHB Now
</label>
          <label class="bank-item" id="bank-public">
            <input type="radio"
                   name="bank_choice"
                   value="Public Bank"
                   onchange="selectBank(this)">
             <img src="/CAFE_SYSTEM/assets/images/banks/publicbank-logo.png" alt="Public Bank" class="bank-logo-img">
            Public Bank
          </label>
          <label class="bank-item" id="bank-hongleong">
            <input type="radio"
                   name="bank_choice"
                   value="Hong Leong"
                   onchange="selectBank(this)">
            <img src="https://th.bing.com/th/id/OIP.2n-tOH99zfVUtCYSHYtOxwAAAA?w=129&h=180&c=7&r=0&o=7&pid=1.7&rm=3" alt="Hong Leong" class="bank-logo-img">
            Hong Leong
          </label>
          <label class="bank-item" id="bank-ambank">
            <input type="radio"
                   name="bank_choice"
                   value="AmBank"
                   onchange="selectBank(this)">
            <img src="https://th.bing.com/th/id/OIP.PJFQGOtDu61Fxf9jrmB_hAHaFj?w=233&h=180&c=7&r=0&o=7&pid=1.7&rm=3" alt="AmBank" class="bank-logo-img">
            AmBank
          </label>
        </div>
      </div>

      <!-- ===== E-WALLET DETAIL ===== -->
      <div class="payment-detail ewallet-detail"
           id="detail-ewallet">
        <div class="detail-title">
          <?= icon('smartphone', 16) ?> Select Your E-Wallet
        </div>
        <p style="font-size:0.78rem; color:#888; margin-bottom:5px;">
          Open your e-wallet app to complete payment.
          <b>(Demo only)</b>
        </p>
        <div class="ewallet-list">
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="Touch n Go"
                   onchange="selectEwallet(this)">
            <img src="https://th.bing.com/th/id/OIP.zLXb-oYB4-psSoaqNv7hBgHaHa?w=171&h=180&c=7&r=0&o=7&pid=1.7&rm=3" alt="Touch 'n Go" class="ewallet-logo-img">
            Touch 'n Go
          </label>
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="GrabPay"
                   onchange="selectEwallet(this)">
            <img src="https://th.bing.com/th/id/OIP.LyC49aN7zgHgJNd7LtDT7QHaHa?w=174&h=180&c=7&r=0&o=7&pid=1.7&rm=3" alt="Grab" class="ewallet-logo-img">
            GrabPay
          </label>
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="Boost"
                   onchange="selectEwallet(this)">
            <img src="https://th.bing.com/th/id/OIP.A0OWFX5AQbaqVkCIPZMbXAHaFO?w=257&h=181&c=7&r=0&o=7&pid=1.7&rm=3" alt="Boost" class="ewallet-logo-img">
            Boost
          </label>
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="ShopeePay"
                   onchange="selectEwallet(this)">
            <img src="https://th.bing.com/th/id/OIP.Ynmrs4hqWfgcqNWjoaPGNAHaFj?w=241&h=181&c=7&r=0&o=7&pid=1.7&rm=3" alt="Shopeepay" class="ewallet-logo-img">
            ShopeePay
          </label>
          <label class="ewallet-item">
  <input type="radio" name="ewallet_choice" value="MAE" onchange="selectEwallet(this)">
  <img src="/CAFE_SYSTEM/assets/images/banks/mae-logo.webp" alt="MAE" class="ewallet-logo-img">
  MAE
</label>

<label class="ewallet-item">
  <input type="radio" name="ewallet_choice" value="BigPay" onchange="selectEwallet(this)">
  <img src="/CAFE_SYSTEM/assets/images/banks/bigpay-logo.webp" alt="BigPay" class="ewallet-logo-img">
  BigPay
</label>
        </div>
      </div>

      <!-- ===== QR PAYMENT DETAIL ===== -->
      <div class="payment-detail qr-detail"
           id="detail-qr">
        <div class="detail-title"
             style="justify-content:center;">
          <?= icon('camera', 16) ?> Scan QR Code to Pay
        </div>
        <p style="font-size:0.78rem; color:#888;">
          Use any banking app or e-wallet to scan.
          <b>(Demo QR - not real)</b>
        </p>

        <!-- Dummy QR Code (visual only, not a real/scannable QR) -->
        <div class="qr-code-box">
          <?= renderDummyQR() ?>
        </div>

        <div class="qr-amount" id="qrAmount">
          RM <?= number_format($grandTotal, 2) ?>
        </div>
        <div class="qr-label">
          Cafe Digital Payment
        </div>
        <div class="qr-note">
          <?= icon('alert-triangle', 13) ?> This is a dummy QR code for
          display only — it cannot be scanned or accessed.
        </div>
      </div>

      <!-- ===== CASH DETAIL ===== -->
      <div class="payment-detail cash-detail"
           id="detail-cash">
        <div class="detail-title">
          <?= icon('cash', 16) ?> Pay with Cash
        </div>
        <div class="cash-info-list">
          <div class="cash-info-item">
            <span class="cash-info-icon"><?= icon('clipboard', 16) ?></span>
            <div class="cash-info-text">
              <b>Step 1:</b> Place your order by
              clicking the button below.
            </div>
          </div>
          <div class="cash-info-item">
            <span class="cash-info-icon"><?= icon('receipt', 16) ?></span>
            <div class="cash-info-text">
              <b>Step 2:</b> Show your
              <b>Order ID</b> to the cashier
              at the counter.
            </div>
          </div>
          <div class="cash-info-item">
            <span class="cash-info-icon"><?= icon('cash', 16) ?></span>
            <div class="cash-info-text">
              <b>Step 3:</b> Pay the total amount of
              <b id="cashTotal">
                RM <?= number_format($grandTotal, 2) ?>
              </b>
              at the counter.
            </div>
          </div>
          <div class="cash-info-item">
            <span class="cash-info-icon"><?= icon('check-circle', 16) ?></span>
            <div class="cash-info-text">
              <b>Step 4:</b> Collect your receipt
              and wait for your order to be ready!
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ===== PLACE ORDER FORM ===== -->
  <form method="post"
        action="place_order.php"
        id="orderForm"
        onsubmit="return validateOrder()">

    <input type="hidden" name="subtotal"
           value="<?= $subtotal ?>">
    <input type="hidden" name="sst_amount"
           value="<?= $sstAmount ?>">
    <input type="hidden" name="grand_total"
           id="grandTotalInput"
           value="<?= $grandTotal ?>">
    <input type="hidden" name="coupon_code"
           id="couponCodeInput" value="">
    <input type="hidden" name="discount"
           id="discountInput" value="0">
    <input type="hidden" name="is_guest"
           value="<?= $isGuest ? '1' : '0' ?>">
    <input type="hidden" name="payment_method"
           id="paymentMethodInput" value="">
    <input type="hidden" name="payment_detail"
           id="paymentDetailInput" value="">
    <button type="submit"
            class="place-order-btn"
            id="placeOrderBtn"
            disabled>
      <?= icon('cart', 18) ?> Place Order —
      RM <span id="btnTotal">
        <?= number_format($grandTotal, 2) ?>
      </span>
    </button>

  </form>

  <!-- Payment Required Note -->
  <p class="payment-note">
    <?= icon('alert-triangle', 14) ?> Please select a payment method to continue
  </p>

  <?php endif; ?>

</div>

<?php if (!$isGuest): ?>
<div class="cp-overlay" id="couponOverlay" role="dialog" aria-labelledby="cpHeader" aria-hidden="true">
  <div class="cp-popup">
    <div class="cp-header">
      <button class="cp-close" onclick="closeCoupon()" aria-label="Close coupon popup">×</button>
      <h2 id="cpHeader">Available Coupons</h2>
      <p>Copy a code to apply it, or save it for later</p>
    </div>
    <div class="cp-body">
      <p class="cp-subtitle">Use copy to apply the coupon above, or save it to your account.</p>
      <?php if (!empty($availableCoupons)): ?>
        <?php foreach ($availableCoupons as $coupon):
          $cCode = htmlspecialchars($coupon['code'], ENT_QUOTES, 'UTF-8');
        ?>
        <div class="cp-card">
          <div class="cp-discount">
            <div class="cp-discount-amount"><?= number_format($coupon['discount'], 0) ?>%</div>
            <div class="cp-discount-type">OFF</div>
          </div>
          <div class="cp-info">
            <div class="cp-code"><?= $cCode ?></div>
            <?php if (!empty($coupon['min_order']) && $coupon['min_order'] > 0): ?>
              <div class="cp-min">Minimum order: RM <?= number_format($coupon['min_order'], 2) ?></div>
            <?php endif; ?>
            <div class="cp-expiry">Expires: <?= date('d M Y', strtotime($coupon['expiry_date'])) ?></div>
          </div>
         <div class="cp-actions">
 <button class="cp-copy-btn"
        onclick="copyCouponCode(this, <?= htmlspecialchars(json_encode($coupon['code']), ENT_QUOTES) ?>)"
        aria-label="Copy coupon code <?= $cCode ?>">
  Copy
</button>
</div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="cp-no-coupons">
          <p>No coupons available right now.</p>
        </div>
      <?php endif; ?>
    </div>
    <div class="cp-footer">
      <button class="cp-footer-btn" onclick="closeCoupon()" aria-label="Close">
        Close
      </button>
    </div>
  </div>
</div>
<?php endif; ?>


<script>
  // ===== BASE VALUES =====
  const baseSubtotal   = <?= $subtotal ?>;
  const sstRate        = <?= $sstRate ?>;
  let   discountAmount = 0;
  let   appliedCode    = '';
  let   selectedPayment = '';
  let   selectedDetail  = '';

  // ===== SVG ICONS FOR PROCESSING POPUP =====
  const paymentIconsSvg = {
    banking: '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#e31937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="21" x2="21" y2="21"></line><line x1="5" y1="21" x2="5" y2="10"></line><line x1="9" y1="21" x2="9" y2="10"></line><line x1="15" y1="21" x2="15" y2="10"></line><line x1="19" y1="21" x2="19" y2="10"></line><polygon points="12 3 21 8 3 8"></polygon></svg>',
    ewallet: '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#e31937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>',
    qr: '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#e31937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>',
    cash: '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#e31937" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"></rect><circle cx="12" cy="12" r="3"></circle><path d="M6 12h.01M18 12h.01"></path></svg>',
  };

  // ===== SELECT PAYMENT METHOD =====
  function selectPayment(method) {
    // ===== REMOVE ALL SELECTED =====
    const allCards = document.querySelectorAll('.payment-method-card');
    allCards.forEach(card => card.classList.remove('selected'));

    // ===== HIDE ALL DETAILS =====
    const allDetails = document.querySelectorAll('.payment-detail');
    allDetails.forEach(detail => detail.classList.remove('show'));

    // ===== SELECT CLICKED CARD =====
    const card = document.getElementById('card-' + method);
    if (card) card.classList.add('selected');

    // ===== CHECK RADIO =====
    const radio = document.getElementById('pay-' + method);
    if (radio) radio.checked = true;

    // ===== SHOW DETAIL PANEL =====
    const detail = document.getElementById('detail-' + method);
    if (detail) detail.classList.add('show');

    // ===== SET SELECTED PAYMENT =====
    selectedPayment = method;
    selectedDetail  = '';

    // ===== UPDATE HIDDEN INPUT =====
    document.getElementById('paymentMethodInput').value = method;
    document.getElementById('paymentDetailInput').value = '';

    // ===== ENABLE PLACE ORDER BUTTON =====
    // For cash & QR - enable immediately
    // For banking & ewallet - wait for sub-selection
    if (method === 'cash' || method === 'qr') {
      enableOrderBtn();
    } else {
      disableOrderBtn();
    }

    // ===== HIDE PAYMENT NOTE =====
    const note = document.querySelector('.payment-note');
    if (note) note.style.display = 'none';
  }

  // ===== SELECT BANK =====
  function selectBank(radio) {
    // Remove selected from all bank items
    document.querySelectorAll('.bank-item').forEach(item => {
      item.classList.remove('selected');
    });

    // Add selected to parent label
    if (radio.parentElement) {
      radio.parentElement.classList.add('selected');
    }

    selectedDetail = radio.value;
    document.getElementById('paymentDetailInput').value = radio.value;

    // Enable order button
    enableOrderBtn();
  }

  // ===== SELECT E-WALLET =====
  function selectEwallet(radio) {
    // Remove selected from all ewallet items
    document.querySelectorAll('.ewallet-item').forEach(item => {
      item.classList.remove('selected');
    });

    // Add selected to parent label
    if (radio.parentElement) {
      radio.parentElement.classList.add('selected');
    }

    selectedDetail = radio.value;
    document.getElementById('paymentDetailInput').value = radio.value;

    // Enable order button
    enableOrderBtn();
  }

  // ===== ENABLE ORDER BUTTON =====
  function enableOrderBtn() {
  const btn = document.getElementById('placeOrderBtn');
  if (btn) {
    btn.disabled = false;
    btn.style.background = '#1558b0';
    btn.style.cursor     = 'pointer';
  }
}

  // ===== DISABLE ORDER BUTTON =====
  function disableOrderBtn() {
    const btn = document.getElementById('placeOrderBtn');
    if (btn) {
      btn.disabled = true;
      btn.style.background = '#ccc';
      btn.style.cursor     = 'not-allowed';
    }
  }

  // ===== VALIDATE ORDER BEFORE SUBMIT =====
  function validateOrder() {
    if (!selectedPayment) {
      alert('Please select a payment method!');
      return false;
    }

    if (selectedPayment === 'banking' && !selectedDetail) {
      alert('Please select your bank!');
      return false;
    }

    if (selectedPayment === 'ewallet' && !selectedDetail) {
      alert('Please select your e-wallet!');
      return false;
    }

    // ===== SHOW PROCESSING POPUP =====
    showProcessingPopup();
    return true;
  }

  // ===== SHOW PROCESSING POPUP =====
  function showProcessingPopup() {
    const method = selectedPayment;
    let   iconSvg = paymentIconsSvg.cash;
    let   msg     = 'Processing payment...';

    if (method === 'banking') {
      iconSvg = paymentIconsSvg.banking;
      msg     = 'Connecting to ' + selectedDetail + '...';
    } else if (method === 'ewallet') {
      iconSvg = paymentIconsSvg.ewallet;
      msg     = 'Opening ' + selectedDetail + '...';
    } else if (method === 'qr') {
      iconSvg = paymentIconsSvg.qr;
      msg     = 'Verifying QR payment...';
    } else if (method === 'cash') {
      iconSvg = paymentIconsSvg.cash;
      msg     = 'Confirming cash order...';
    }

    // Create overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.7);
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
    `;

    overlay.innerHTML = `
      <div style="background:#fff; border-radius:20px;
                  padding:35px 30px; text-align:center;
                  max-width:300px; width:90%;
                  box-shadow:0 20px 60px rgba(0,0,0,0.3);
                  animation: popIn 0.3s ease;">
        <div style="display:flex; justify-content:center;
                    margin-bottom:15px;
                    animation: spin 1s linear infinite;">
          ${iconSvg}
        </div>
        <h3 style="font-size:1.1rem; font-weight:800;
                   color:#222; margin-bottom:8px;">
          ${msg}
        </h3>
        <p style="font-size:0.82rem; color:#888;
                  margin-bottom:15px; line-height:1.5;">
          Please wait while we process your order...
        </p>
        <div style="background:#f0f0f0; border-radius:10px;
                    height:6px; overflow:hidden;">
          <div style="background:#e31937; height:100%;
                      border-radius:10px;
                      animation: progress 2s ease forwards;"
               id="progressBar">
          </div>
        </div>
        <p style="font-size:0.72rem; color:#bbb;
                  margin-top:12px;">
          Demo Mode — No real payment processed
        </p>
      </div>
    `;

    document.body.appendChild(overlay);
  }

  // ===== APPLY COUPON =====
  function applyCoupon() {
    const code  = document.getElementById('couponInput')
                          .value.trim().toUpperCase();

    if (!code) {
      showCouponMsg('Please enter a coupon code.', 'error');
      return;
    }

    // Show loading
    showCouponMsg('Validating coupon...', 'success');

    fetch('validate_coupon.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'code=' + encodeURIComponent(code)
            + '&subtotal=' + encodeURIComponent(baseSubtotal),
    })
    .then(res => res.json())
    .then(data => {
      if (data.valid) {
        discountAmount = parseFloat(data.discount);
        appliedCode    = code;

        // Update totals
        updateTotals();

        // Show discount row
        document.getElementById('discountRow').style.display = 'flex';
        document.getElementById('discountValue').textContent =
          '- RM ' + discountAmount.toFixed(2);

        // Set hidden inputs
        document.getElementById('couponCodeInput').value = code;
        document.getElementById('discountInput').value   = discountAmount;

        showCouponMsg(
          'Coupon applied! You save RM '
          + discountAmount.toFixed(2),
          'success'
        );
      } else {
        discountAmount = 0;
        appliedCode    = '';
        document.getElementById('discountRow').style.display = 'none';
        document.getElementById('couponCodeInput').value     = '';
        document.getElementById('discountInput').value       = '0';
        updateTotals();
        showCouponMsg(
          (data.message || 'Invalid coupon code.'),
          'error'
        );
      }
    })
    .catch(() => {
      showCouponMsg(
        'Error validating coupon. Please try again.',
        'error'
      );
    });
  }

  // ===== COUPON BROWSE POPUP =====
  function openCouponPopup() {
    const overlay = document.getElementById('couponOverlay');
    if (overlay) {
      overlay.classList.add('show');
      overlay.setAttribute('aria-hidden', 'false');
    }
  }

  function closeCoupon() {
    const overlay = document.getElementById('couponOverlay');
    if (overlay) {
      overlay.classList.remove('show');
      overlay.setAttribute('aria-hidden', 'true');
    }
  }

  const _couponOverlayEl = document.getElementById('couponOverlay');
  if (_couponOverlayEl) {
    _couponOverlayEl.addEventListener('click', function(e) {
      if (e.target === this) closeCoupon();
    });
  }

  function copyCouponCode(btn, code) {
  const input = document.getElementById('couponInput');
  if (input) input.value = code;

  navigator.clipboard.writeText(code).catch(() => {
    // Clipboard can fail silently (permissions, insecure context) — safe to ignore,
    // the code is still filled into the input below.
  });

  const original = btn.textContent;
  btn.textContent = 'Copied!';
  btn.disabled = true;

  closeCoupon();
  showCouponMsg('Code copied! Click Apply to use it.', 'success');

  setTimeout(() => {
    btn.textContent = original;
    btn.disabled = false;
  }, 1200);
}


  // ===== UPDATE TOTALS =====
  function updateTotals() {
    const afterDiscount = Math.max(0, baseSubtotal - discountAmount);
    const sst           = afterDiscount * sstRate;
    const grand         = afterDiscount + sst;

    // Update display
    document.getElementById('grandTotalDisplay').textContent =
      'RM ' + grand.toFixed(2);

    // Update hidden input
    document.getElementById('grandTotalInput').value = grand.toFixed(2);

    // Update button total
    document.getElementById('btnTotal').textContent = grand.toFixed(2);

    // Update QR amount
    const qrAmount = document.getElementById('qrAmount');
    if (qrAmount) {
      qrAmount.textContent = 'RM ' + grand.toFixed(2);
    }

    // Update cash total
    const cashTotal = document.getElementById('cashTotal');
    if (cashTotal) {
      cashTotal.textContent = 'RM ' + grand.toFixed(2);
    }
  }

  // ===== SHOW COUPON MESSAGE =====
  function showCouponMsg(msg, type) {
    const el      = document.getElementById('couponMsg');
    el.textContent = msg;
    el.className   = 'coupon-msg ' + type;
  }

  // ===== APPLY COUPON ON ENTER KEY =====
  const couponInput = document.getElementById('couponInput');
  if (couponInput) {
    couponInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        applyCoupon();
      }
    });
  }

  // ===== CSS ANIMATIONS =====
  const style = document.createElement('style');
  style.textContent = `
    @keyframes spin {
      0%   { transform: rotate(0deg);   }
      100% { transform: rotate(360deg); }
    }
    @keyframes progress {
      0%   { width: 0%;   }
      100% { width: 100%; }
    }
    @keyframes popIn {
      from { transform: scale(0.8); opacity: 0; }
      to   { transform: scale(1);   opacity: 1; }
    }
  `;
  document.head.appendChild(style);
</script>

</body>
</html>