<?php
session_start();

// If already logged in, redirect to menu
if (isset($_SESSION['customer_id'])) {
    header('Location: menu.php');
    exit;
}

// If already a guest, redirect to menu
if (isset($_SESSION['guest']) && $_SESSION['guest'] === true) {
    header('Location: menu.php');
    exit;
}

// If guest button clicked
if (isset($_GET['guest'])) {
    session_unset();
    $_SESSION['guest'] = true;
    $_SESSION['name']  = 'Guest';
    header('Location: menu.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Welcome — MIA COFFEE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
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

  background-image:
    linear-gradient(135deg, rgba(26,26,46,0.88) 0%, rgba(22,33,62,0.88) 50%, rgba(15,52,96,0.88) 100%),
    url('../assets/images/background.png');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
}

    /* ── Card ── */
    .welcome-card {
      background: #fff;
      border-radius: 20px;
      padding: 2.5rem 2.25rem 2rem;
      max-width: 420px;
      width: 100%;
      box-shadow: 0 30px 70px rgba(0,0,0,0.35);
      animation: fadeUp 0.45s cubic-bezier(.22,.68,0,1.2) both;
    }

    @keyframes fadeUp {
      from { transform: translateY(28px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }

    /* ── Brand ── */
   .brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  margin-bottom: 6px;
  text-align: center;
}

.logo-frame {
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: rgba(255,255,255,0.08);
  border: 4px solid rgba(255,255,255,0.85);
  box-shadow:
    0 10px 30px rgba(0,0,0,0.35),
    0 0 0 6px rgba(255,255,255,0.06);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
  padding: 6px;
  overflow: hidden;
}

.logo-frame img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 50%;
}

   .brand-text {
  font-family: 'Playfair Display', serif;
  font-size: 22px;
  font-weight: 700;
  color: #1a1a2e;
  letter-spacing: -0.3px;
}

.brand-text span { color: #e31937; }

    .tagline {
  font-size: 13px;
  color: #888;
  margin-bottom: 1.6rem;
  text-align: center;
}

    .divider-line {
      border: none;
      border-top: 1px solid #f0f0f0;
      margin: 0 0 1.6rem;
    }

    .subtitle {
      font-size: 11px;
      font-weight: 500;
      color: #888;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-bottom: 16px;
      text-align: center;
    }

    /* ── Primary button (Login) ── */
    .btn-login {
      width: 100%; padding: 12px;
      background: #1a1a2e; color: #fff;
      border: none; border-radius: 11px;
      font-size: 14px; font-weight: 500;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      text-decoration: none;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      letter-spacing: 0.2px;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
      margin-bottom: 12px;
    }

    .btn-login svg { width: 17px; height: 17px; opacity: 0.75; }

    .btn-login:hover {
      background: #0d0d1a;
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(26,26,46,0.25);
    }

    .btn-login:active { transform: translateY(0); box-shadow: none; }

    /* ── Or divider ── */
    .or-divider {
      display: flex; align-items: center; gap: 10px;
      margin: 1rem 0;
      font-size: 12px; color: #ccc;
    }

    .or-divider::before, .or-divider::after {
      content: ''; flex: 1; height: 1px; background: #f0f0f0;
    }

    /* ── Guest button ── */
    .btn-guest {
      width: 100%; padding: 11px;
      background: #fafafa;
      border: 1.5px solid #e8e8e8;
      border-radius: 11px;
      font-size: 13px; font-weight: 500;
      font-family: 'DM Sans', sans-serif;
      color: #666;
      cursor: pointer; text-decoration: none;
      display: flex; align-items: center; justify-content: center; gap: 7px;
      transition: border-color 0.2s, color 0.2s, background 0.2s;
    }

    .btn-guest svg { width: 15px; height: 15px; }

    .btn-guest:hover {
      border-color: #e31937;
      color: #e31937;
      background: #fff5f5;
    }

    /* ── Bottom link ── */
    .bottom-links {
      text-align: center;
      margin-top: 1.2rem;
      font-size: 12px;
      color: #999;
    }

    .bottom-links a {
      color: #e31937;
      font-weight: 500;
      text-decoration: none;
    }

    .bottom-links a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<div class="welcome-card">

  <!-- Brand -->
  <div class="brand">
  <div class="logo-frame">
    <img src="../assets/images/logo.jpg" alt="MIA Coffee logo">
  </div>
  <div class="brand-text"><span>MIA</span> COFFEE</div>
</div>

  <p class="tagline">Your favourite cafe, now online.</p>
  <hr class="divider-line">

  <p class="subtitle">How would you like to continue?</p>

  <!-- Login button -->
  <a href="login.php" class="btn-login">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="8" cy="15" r="4"/><path d="M12 11l8-8"/><path d="M18 6l2 2"/><path d="M15 9l2 2"/>
    </svg>
    Login to My Account
  </a>

  <div class="or-divider">or</div>

  <!-- Guest button -->
  <a href="?guest=1" class="btn-guest">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
    </svg>
    Continue as Guest
  </a>

  <div class="bottom-links">
    Don't have an account? <a href="register.php">Register here</a>
  </div>

</div>

</body>
</html>