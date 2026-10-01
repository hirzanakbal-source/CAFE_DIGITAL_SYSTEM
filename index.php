<?php
// ─────────────────────────────────────────
//  MIA COFFEE — Root Router
//  Serves admin or customer app based on host
// ─────────────────────────────────────────

// --- Configuration ---
define('ADMIN_HOST',    'admin.miacoffee.shop');
define('ADMIN_PATH',   __DIR__ . '/admin/index.php');
define('CUSTOMER_PATH',__DIR__ . '/customer/index.php');

// --- Routing logic ---
$host = $_SERVER['HTTP_HOST'] ?? '';

// Strip port if present (e.g. localhost:8080 → localhost)
$host = strtolower(explode(':', $host)[0]);

if ($host === ADMIN_HOST) {

    // ── Admin route ──────────────────────────────
    if (!file_exists(ADMIN_PATH)) {
        http_response_code(503);
        exit('Admin app not found. Please check your server configuration.');
    }
    include ADMIN_PATH;

} else {

    // ── Customer route (default) ─────────────────
    if (!file_exists(CUSTOMER_PATH)) {
        serveSplash();
    } else {
        include CUSTOMER_PATH;
    }

}

function serveSplash(): void
{
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>MIA COFFEE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;

      /* Background photo + dark navy overlay so text stays readable */
      background-image:
        linear-gradient(135deg, rgba(26,26,46,0.88) 0%, rgba(22,33,62,0.88) 50%, rgba(15,52,96,0.88) 100%),
        url('assets/images/background.png');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
    }

    .card {
      text-align: center;
      max-width: 360px;
      width: 100%;
      animation: fadeUp 0.5s cubic-bezier(.22,.68,0,1.2) both;
    }
    @keyframes fadeUp { from { transform: translateY(24px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    /* ── Logo frame ───────────────────────────── */
    .logo-frame {
      width: 160px;
      height: 160px;
      border-radius: 50%;
      background: rgba(255,255,255,0.08);
      border: 4px solid rgba(255,255,255,0.85);
      box-shadow:
        0 10px 30px rgba(0,0,0,0.45),
        0 0 0 8px rgba(255,255,255,0.06);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
      padding: 8px;
      overflow: hidden;
    }
    .logo-frame img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .brand-name { font-family: 'Playfair Display', serif; font-size: 32px; font-weight: 700; color: #fff; margin-bottom: 8px; }
    .brand-name span { color: #e31937; }
    .tagline { font-size: 13px; color: rgba(255,255,255,0.55); margin-bottom: 2.5rem; }

    .btns { display: flex; flex-direction: column; gap: 12px; }
    .btn {
      display: flex; align-items: center; gap: 14px;
      padding: 15px 20px;
      border-radius: 14px;
      text-decoration: none;
      transition: transform 0.15s, opacity 0.15s, box-shadow 0.15s;
      backdrop-filter: blur(4px);
    }
    .btn:hover { transform: translateY(-2px); opacity: 0.92; }
    .btn i { font-size: 22px; }
    .btn-text { text-align: left; flex: 1; }
    .btn-label { font-size: 14px; font-weight: 500; line-height: 1; }
    .btn-hint { font-size: 11px; opacity: 0.65; margin-top: 3px; }
    .btn-arrow { font-size: 16px; opacity: 0.5; }

    .btn-customer {
      background: #e31937;
      color: #fff;
      box-shadow: 0 6px 18px rgba(227,25,55,0.35);
    }
    .btn-admin {
      background: rgba(255,255,255,0.08);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.18);
    }
  </style>
</head>
<body>
<div class="card">
  <div class="logo-frame">
    <img src="assets/images/logo.jpg" alt="MIA Coffee logo">
  </div>
  <div class="brand-name"><span>MIA</span> COFFEE</div>
  <p class="tagline">Your favourite coffee, one click away.</p>
  <div class="btns">
    <a href="customer/login.php" class="btn btn-customer">
      <i class="ti ti-cup"></i>
      <div class="btn-text">
        <div class="btn-label">Customer login</div>
        <div class="btn-hint">Order your coffee</div>
      </div>
      <i class="ti ti-arrow-right btn-arrow"></i>
    </a>
    <a href="admin/admin_login.php" class="btn btn-admin">
      <i class="ti ti-shield-lock"></i>
      <div class="btn-text">
        <div class="btn-label">Admin panel</div>
        <div class="btn-hint">Staff access only</div>
      </div>
      <i class="ti ti-arrow-right btn-arrow"></i>
    </a>
  </div>
</div>
</body>
</html>
<?php
}