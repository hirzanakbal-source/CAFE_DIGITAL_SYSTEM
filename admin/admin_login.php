<?php
require __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$message     = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!$email || !$password) {
        $message = 'Please enter your email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['admin_name'] ?? $admin['name'] ?? 'Admin';

                unset($_SESSION['customer_id']);
                unset($_SESSION['name']);
                unset($_SESSION['email']);
                unset($_SESSION['guest']);
                unset($_SESSION['guest_cart']);

                header("Location: admin_dashboard.php");
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
  <title>Admin Login — MIA COFFEE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
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
      box-shadow: 0 30px 70px rgba(0,0,0,0.5);
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

    .tagline-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-bottom: 1.6rem;
}

    .tagline {
      font-size: 13px;
      color: #888;
    }

    .admin-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: #1a1a2e;
      color: #fff;
      font-size: 10px;
      font-weight: 500;
      letter-spacing: 0.8px;
      text-transform: uppercase;
      padding: 3px 10px;
      border-radius: 20px;
      flex-shrink: 0;
    }

    .admin-badge svg { width: 11px; height: 11px; opacity: 0.8; }

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
    .field { margin-bottom: 13px; }

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

    /* ── Remember me ── */
    .remember-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin: 6px 0 18px;
    }

    .remember-label {
      display: flex;
      align-items: center;
      gap: 7px;
      cursor: pointer;
      user-select: none;
      font-size: 12px;
      color: #777;
    }

    .remember-label input[type="checkbox"] {
      width: 15px; height: 15px;
      accent-color: #1a1a2e;
      cursor: pointer;
      margin: 0;
    }

    /* ── Login button ── */
    .btn-login {
      width: 100%; padding: 12px;
      background: #1a1a2e; color: #fff;
      border: none; border-radius: 11px;
      font-size: 14px; font-weight: 500;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    }

    .btn-login svg { width: 16px; height: 16px; opacity: 0.75; }

    .btn-login:hover {
      background: #0d0d1a;
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(26,26,46,0.3);
    }

    .btn-login:active { transform: translateY(0); box-shadow: none; }
    .btn-login:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

    /* ── Or divider ── */
    .or-divider {
      display: flex; align-items: center; gap: 10px;
      margin: 1.2rem 0;
      font-size: 12px; color: #ccc;
    }

    .or-divider::before, .or-divider::after {
      content: ''; flex: 1; height: 1px; background: #f0f0f0;
    }

    /* ── Footer links ── */
    .footer-links {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      font-size: 12px;
    }

    .footer-links .register-line { color: #999; }
    .footer-links .register-line a { color: #e31937; font-weight: 500; text-decoration: none; }
    .footer-links .register-line a:hover { text-decoration: underline; }

    .footer-links .back-link { color: #bbb; text-decoration: none; transition: color 0.2s; }
    .footer-links .back-link:hover { color: #888; }

    /* ── Security note ── */
    .security-note {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      margin-top: 1.25rem;
      font-size: 11px;
      color: #ccc;
      letter-spacing: 0.2px;
    }

    .security-note svg { width: 12px; height: 12px; }

    /* ── Toast ── */
    .toast {
      position: fixed;
      bottom: 24px; left: 50%;
      transform: translateX(-50%) translateY(80px);
      background: #1a1a2e; color: #fff;
      padding: 10px 20px;
      border-radius: 50px;
      font-size: 12px; font-weight: 500;
      box-shadow: 0 6px 20px rgba(0,0,0,0.3);
      z-index: 9999;
      transition: transform 0.35s cubic-bezier(.22,.68,0,1.2);
      white-space: nowrap;
      display: flex; align-items: center; gap: 7px;
    }

    .toast.show { transform: translateX(-50%) translateY(0); }
    .toast svg { width: 14px; height: 14px; flex-shrink: 0; }
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

  <div class="tagline-row">
    <span class="tagline">Admin panel access.</span>
    <span class="admin-badge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
      Administrator
    </span>
  </div>

  <hr class="divider-line">

  <!-- Error banner -->
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
        <input type="email" name="email" id="email-input"
               placeholder="admin@miacoffee.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               autocomplete="email" required>
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
        <input type="password" name="password" id="pw-input"
               placeholder="Enter admin password"
               autocomplete="current-password" required>
        <button type="button" class="eye-btn" id="eye-btn" aria-label="Toggle password visibility">
          <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="remember-row">
      <label class="remember-label">
        <input type="checkbox" id="remember-me">
        Remember my credentials
      </label>
    </div>

    <button type="submit" class="btn-login" id="login-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
      </svg>
      <span id="btn-label">Login to Admin Panel</span>
    </button>

  </form>

  <div class="or-divider">or</div>

  <div class="footer-links">
    <p class="register-line">Need an admin account? <a href="admin_register.php">Register here</a></p>
    <a href="../welcome.php" class="back-link">← Back to Customer Site</a>
  </div>

  <div class="security-note">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
    Secure admin access only
  </div>

</div>

<!-- Toast -->
<div class="toast" id="toast">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
  </svg>
  <span id="toast-msg"></span>
</div>

<script>
  const emailInput = document.getElementById('email-input');
  const pwInput    = document.getElementById('pw-input');
  const eyeBtn     = document.getElementById('eye-btn');
  const eyeIcon    = document.getElementById('eye-icon');
  const rememberMe = document.getElementById('remember-me');
  const loginForm  = document.getElementById('login-form');
  const loginBtn   = document.getElementById('login-btn');
  const btnLabel   = document.getElementById('btn-label');
  const toast      = document.getElementById('toast');
  const toastMsg   = document.getElementById('toast-msg');

  const eyeOpen   = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
  const eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';

  function showToast(msg, duration = 3000) {
    toastMsg.textContent = msg;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), duration);
  }

  window.addEventListener('load', () => {
    emailInput.focus();
});

  // Toggle password visibility
  eyeBtn.addEventListener('click', () => {
    const hidden = pwInput.type === 'password';
    pwInput.type = hidden ? 'text' : 'password';
    eyeIcon.innerHTML = hidden ? eyeClosed : eyeOpen;
  });

 // Save / clear credentials on submit
  loginForm.addEventListener('submit', () => {
    // Loading state
    btnLabel.textContent = 'Logging in…';
    loginBtn.disabled    = true;
  });

  
</script>

</body>
</html> 