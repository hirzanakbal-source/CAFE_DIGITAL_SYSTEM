<?php
require __DIR__ . '/db.php';

$message     = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $message = 'Please fill in all fields.';
    } else {
        try {
               $stmt = $pdo->prepare("
                SELECT * FROM customer
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer && password_verify($password, $customer['password'])) {
                session_regenerate_id(true);

                $_SESSION['customer_id'] = $customer['customer_id'];
                $_SESSION['name']        = $customer['full_name'];
                $_SESSION['email']       = $customer['email'];

                unset($_SESSION['guest']);
                unset($_SESSION['guest_cart']);
                unset($_SESSION['coupon_shown']);
                unset($_SESSION['admin_id']);
                unset($_SESSION['admin_name']);

                header('Location: menu.php');
                exit;
            } else {
                $message = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $message = 'Something went wrong. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — MIA COFFEE</title>
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
    .login-card {
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
      margin: 0 0 1.4rem;
    }

    /* ── Error banner ── */
    .error-banner {
      display: flex;
      align-items: center;
      gap: 9px;
      background: #fef2f2;
      border: 1px solid #fecaca;
      border-radius: 10px;
      padding: 10px 13px;
      margin-bottom: 14px;
      font-size: 13px;
      font-weight: 500;
      color: #b91c1c;
    }

    .error-banner svg { flex-shrink: 0; width: 16px; height: 16px; }

    /* ── Fields ── */
    .field { margin-bottom: 14px; }

    .field label {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 500;
      color: #888;
      letter-spacing: 0.5px;
      text-transform: uppercase;
      margin-bottom: 6px;
    }

    .field label svg { width: 13px; height: 13px; }

    .input-wrap { position: relative; }

    .input-wrap input {
      width: 100%;
      padding: 11px 14px;
      background: #f9f9f9;
      border: 1.5px solid #e8e8e8;
      border-radius: 10px;
      font-size: 14px;
      font-family: 'DM Sans', sans-serif;
      color: #1a1a2e;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
      outline: none;
    }

    .input-wrap input:focus {
      border-color: #1a1a2e;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(26,26,46,0.07);
    }

    .input-wrap input::placeholder { color: #bbb; }

    .eye-btn {
      position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; padding: 4px;
      color: #bbb; display: flex; align-items: center;
      border-radius: 6px; transition: color 0.2s;
    }

    .eye-btn:hover { color: #888; }
    .eye-btn svg { width: 16px; height: 16px; }

    .forgot {
      text-align: right;
      font-size: 12px;
      margin-top: 5px;
      margin-bottom: 18px;
    }

    .forgot a {
      color: #aaa; text-decoration: none; transition: color 0.2s;
    }

    .forgot a:hover { color: #e31937; }

    /* ── Primary button ── */
    .btn-login {
      width: 100%; padding: 12px;
      background: #1a1a2e; color: #fff;
      border: none; border-radius: 11px;
      font-size: 14px; font-weight: 500;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      letter-spacing: 0.2px;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
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
      margin: 1.2rem 0;
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

    /* ── Bottom links ── */
    .bottom-links {
      display: flex; align-items: center; justify-content: space-between;
      margin-top: 1.2rem;
      font-size: 12px;
    }

    .register-link { color: #999; }
    .register-link a { color: #e31937; font-weight: 500; text-decoration: none; }
    .register-link a:hover { text-decoration: underline; }

    .back-link a { color: #bbb; text-decoration: none; transition: color 0.2s; }
    .back-link a:hover { color: #888; }
  </style>
</head>
<body>

<div class="login-card">

  <!-- Brand -->
  <div class="brand">
    <div class="logo-frame">
      <img src="../assets/images/logo.jpg" alt="MIA Coffee logo">
    </div>
    <div class="brand-text"><span>MIA</span> COFFEE</div>
  </div>

  <p class="tagline">Welcome back — please login to continue.</p>
  <hr class="divider-line">

  <!-- Error banner (PHP-driven) -->
  <?php if ($message): ?>
  <div class="error-banner">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <!-- Login form -->
  <form method="post" id="login-form">

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
        </svg>
        Email address
      </label>
      <div class="input-wrap">
        <input type="email"
               name="email"
               placeholder="you@example.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               autocomplete="email"
               required>
      </div>
    </div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        Password
      </label>
      <div class="input-wrap">
        <input type="password"
               name="password"
               id="pw-input"
               placeholder="Enter your password"
               autocomplete="current-password"
               required>
        <button type="button" class="eye-btn" id="eye-btn" aria-label="Toggle password visibility">
          <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="forgot">
      <a href="#">Forgot password?</a>
    </div>

    <button type="submit" class="btn-login">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="8" cy="15" r="4"/><path d="M12 11l8-8"/><path d="M18 6l2 2"/><path d="M15 9l2 2"/>
      </svg>
      Login
    </button>

  </form>

  <div class="or-divider">or</div>

  <a href="guest_login.php" class="btn-guest">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
    </svg>
    Continue as Guest
  </a>

  <div class="bottom-links">
    <div class="register-link">No account? <a href="register.php">Register here</a></div>
    <div class="back-link"><a href="welcome.php">← Back</a></div>
  </div>

</div>

<script>
  const pwInput = document.getElementById('pw-input');
  const eyeBtn  = document.getElementById('eye-btn');
  const eyeIcon = document.getElementById('eye-icon');

  const eyeOpen   = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
  const eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';

  eyeBtn.addEventListener('click', () => {
    const hidden = pwInput.type === 'password';
    pwInput.type = hidden ? 'text' : 'password';
    eyeIcon.innerHTML = hidden ? eyeClosed : eyeOpen;
  });
</script>

</body>
</html>