<?php
require __DIR__ . '/../db.php';

if (isset($_SESSION['admin_id'])) {
    header("Location: admin_dashboard.php");
    exit;
}

$message     = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm']  ?? '');
    $phone    = trim($_POST['phone']    ?? '');

    if (!$name || !$email || !$password || !$confirm) {
        $message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
    } else {
        try {
            $checkStmt = $pdo->prepare("SELECT admin_id FROM admin WHERE email = ?");
            $checkStmt->execute([$email]);

                       if ($checkStmt->fetch()) {
                $message = 'This email is already registered.';
            } else {
                               $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt   = $pdo->prepare("INSERT INTO admin (admin_name, email, password, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashed, $phone ?: null]);
                $messageType = 'success';
            }
                      } catch (PDOException $e) {
    $message = 'Something went wrong. Please try again.';
}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Register — MIA COFFEE</title>
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
  padding: 24px 20px;
  position: relative;

  background-image:
    linear-gradient(135deg, rgba(26,26,46,0.88) 0%, rgba(22,33,62,0.88) 50%, rgba(15,52,96,0.88) 100%),
    url('../assets/images/background.png');
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
}

    /* ── Card ── */
    .register-card {
      background: #fff;
      border-radius: 20px;
      padding: 2.5rem 2.25rem 2rem;
      max-width: 440px;
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
      font-size: 22px; font-weight: 700;
      color: #1a1a2e; letter-spacing: -0.3px;
    }

    .brand-text span { color: #e31937; }

    .tagline-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  margin-bottom: 1.6rem;
}

    .tagline { font-size: 13px; color: #888; }

    .admin-badge {
      display: inline-flex; align-items: center; gap: 5px;
      background: #1a1a2e; color: #fff;
      font-size: 10px; font-weight: 500;
      letter-spacing: 0.8px; text-transform: uppercase;
      padding: 3px 10px; border-radius: 20px; flex-shrink: 0;
    }

    .admin-badge svg { width: 11px; height: 11px; opacity: 0.8; }

    .divider-line {
      border: none; border-top: 1px solid #f0f0f0;
      margin: 0 0 1.4rem;
    }

    /* ── Banners ── */
    .msg-banner {
      display: flex; align-items: flex-start; gap: 9px;
      border-radius: 10px; padding: 10px 13px;
      margin-bottom: 14px; font-size: 13px; font-weight: 500; line-height: 1.5;
    }

    .msg-banner svg { flex-shrink: 0; width: 16px; height: 16px; margin-top: 1px; }

    .msg-banner.error  { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
    .msg-banner.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }

    /* ── Success state ── */
    .success-box {
      text-align: center; padding: 1rem 0 0.5rem;
    }

    .success-icon-wrap {
      width: 60px; height: 60px; border-radius: 50%;
      background: #f0fdf4; border: 2px solid #bbf7d0;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 16px;
    }

    .success-icon-wrap svg { width: 28px; height: 28px; color: #15803d; }

    .success-box h3 {
      font-family: 'Playfair Display', serif;
      font-size: 20px; font-weight: 700;
      color: #1a1a2e; margin-bottom: 8px;
    }

    .success-box p {
      font-size: 13px; color: #888;
      line-height: 1.6; margin-bottom: 22px;
    }

    .go-login-btn {
      display: inline-flex; align-items: center; gap: 8px;
      background: #1a1a2e; color: #fff;
      padding: 11px 26px; border-radius: 11px;
      text-decoration: none; font-weight: 500; font-size: 14px;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    }

    .go-login-btn svg { width: 16px; height: 16px; opacity: 0.75; }

    .go-login-btn:hover {
      background: #0d0d1a; transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(26,26,46,0.3);
    }

    /* ── Fields ── */
    .field { margin-bottom: 13px; }

    .field label {
      display: flex; align-items: center; gap: 5px;
      font-size: 11px; font-weight: 500; color: #888;
      letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 6px;
    }

    .field label svg { width: 13px; height: 13px; }

    .req { color: #e31937; margin-left: 1px; }

    .optional-tag {
      font-size: 10px; color: #ccc; font-weight: 400;
      letter-spacing: 0; text-transform: none; margin-left: 3px;
    }

    .input-wrap { position: relative; }

    .input-wrap input {
      width: 100%; padding: 11px 14px;
      background: #f9f9f9; border: 1.5px solid #e8e8e8;
      border-radius: 10px; font-size: 14px;
      font-family: 'DM Sans', sans-serif; color: #1a1a2e;
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
      outline: none;
    }

    .input-wrap input:focus {
      border-color: #1a1a2e; background: #fff;
      box-shadow: 0 0 0 3px rgba(26,26,46,0.07);
    }

    .input-wrap input::placeholder { color: #bbb; }

    .input-wrap input.valid   { border-color: #16a34a; background: #f8fff9; }
    .input-wrap input.invalid { border-color: #dc2626; background: #fff8f8; }

    .field-hint {
      font-size: 11px; color: #bbb; margin-top: 4px;
      display: flex; align-items: center; gap: 4px;
    }

    .field-hint svg { width: 11px; height: 11px; flex-shrink: 0; }
    .field-hint.valid   { color: #16a34a; }
    .field-hint.invalid { color: #dc2626; }

    /* eye toggle */
    .eye-btn {
      position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; padding: 4px;
      color: #bbb; display: flex; align-items: center;
      border-radius: 6px; transition: color 0.2s;
    }

    .eye-btn:hover { color: #888; }
    .eye-btn svg { width: 16px; height: 16px; }

    /* strength bar */
    .strength-bar {
      height: 3px; border-radius: 2px; background: #eee;
      margin-top: 6px; overflow: hidden;
    }

    .strength-fill {
      height: 100%; border-radius: 2px;
      transition: width 0.3s, background 0.3s;
      width: 0%;
    }

    .strength-fill.weak   { background: #dc2626; width: 33%; }
    .strength-fill.medium { background: #f59e0b; width: 66%; }
    .strength-fill.strong { background: #16a34a; width: 100%; }

    .strength-label {
      font-size: 11px; margin-top: 3px; font-weight: 500; color: #bbb;
    }

    .strength-label.weak   { color: #dc2626; }
    .strength-label.medium { color: #f59e0b; }
    .strength-label.strong { color: #16a34a; }

    /* ── Register button ── */
    .btn-register {
      width: 100%; padding: 12px;
      background: #1a1a2e; color: #fff;
      border: none; border-radius: 11px;
      font-size: 14px; font-weight: 500;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer; margin-top: 6px;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    }

    .btn-register svg { width: 16px; height: 16px; opacity: 0.75; }

    .btn-register:hover {
      background: #0d0d1a; transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(26,26,46,0.3);
    }

    .btn-register:active { transform: translateY(0); box-shadow: none; }
    .btn-register:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

    /* ── Or divider ── */
    .or-divider {
      display: flex; align-items: center; gap: 10px;
      margin: 1.2rem 0; font-size: 12px; color: #ccc;
    }

    .or-divider::before, .or-divider::after {
      content: ''; flex: 1; height: 1px; background: #f0f0f0;
    }

    /* ── Footer ── */
    .footer-links {
      display: flex; flex-direction: column;
      align-items: center; gap: 8px; font-size: 12px;
    }

    .footer-links .login-line { color: #999; }
    .footer-links .login-line a { color: #e31937; font-weight: 500; text-decoration: none; }
    .footer-links .login-line a:hover { text-decoration: underline; }

    .footer-links .back-link { color: #bbb; text-decoration: none; transition: color 0.2s; }
    .footer-links .back-link:hover { color: #888; }

    /* ── Security note ── */
    .security-note {
      display: flex; align-items: center; justify-content: center;
      gap: 6px; margin-top: 1.25rem; font-size: 11px; color: #ccc;
    }

    .security-note svg { width: 12px; height: 12px; }
  </style>
</head>
<body>

<div class="register-card">

 <div class="brand">
  <div class="logo-frame">
    <img src="../assets/images/logo.jpg" alt="MIA Coffee logo">
  </div>
  <div class="brand-text"><span>MIA</span> COFFEE</div>
</div>

  <div class="tagline-row">
    <span class="tagline">Create your admin account.</span>
    <span class="admin-badge">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
      Administrator
    </span>
  </div>

  <hr class="divider-line">

  <?php if ($messageType === 'success'): ?>

  <!-- Success state -->
  <div class="success-box">
    <div class="success-icon-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
    </div>
    <h3>Account Created!</h3>
    <p>Your admin account has been set up successfully.<br>You can now login to the admin panel.</p>
    <a href="admin_login.php" class="go-login-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
      </svg>
      Go to Login
    </a>
  </div>

  <?php else: ?>

  <!-- Error banner -->
  <?php if ($message): ?>
  <div class="msg-banner error">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <!-- Registration form -->
  <form method="post" id="reg-form">

    <!-- Nickname -->
    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
        Nickname <span class="req">*</span>
      </label>
      <div class="input-wrap">
        <input type="text" name="name" id="name-input"
               placeholder="Your nickname"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
               autocomplete="nickname" required>
      </div>
      <div class="field-hint" id="name-hint">At least 2 characters</div>
    </div>

    <!-- Email -->
    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
        </svg>
        Email address <span class="req">*</span>
      </label>
      <div class="input-wrap">
        <input type="email" name="email" id="email-input"
               placeholder="admin@miacoffee.com"
               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
               autocomplete="email" required>
      </div>
      <div class="field-hint" id="email-hint">Enter a valid email address</div>
    </div>

    <!-- Password -->
    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        Password <span class="req">*</span>
      </label>
      <div class="input-wrap">
        <input type="password" name="password" id="pw-input"
               placeholder="Create a password"
               autocomplete="new-password" required>
        <button type="button" class="eye-btn" id="eye-btn-1" aria-label="Toggle password">
          <svg id="eye-icon-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
      <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
      <div class="strength-label" id="strength-label"></div>
    </div>

    <!-- Confirm Password -->
    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        Confirm password <span class="req">*</span>
      </label>
      <div class="input-wrap">
        <input type="password" name="confirm" id="confirm-input"
               placeholder="Re-enter your password"
               autocomplete="new-password" required>
        <button type="button" class="eye-btn" id="eye-btn-2" aria-label="Toggle confirm password">
          <svg id="eye-icon-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
      <div class="field-hint" id="confirm-hint">Re-enter your password</div>
    </div>

    <!-- Phone -->
    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.64 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6.29 6.29l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        Phone <span class="optional-tag">optional</span>
      </label>
      <div class="input-wrap">
        <input type="text" name="phone" id="phone-input"
               placeholder="+60 12-345 6789"
               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
               autocomplete="tel">
      </div>
      <div class="field-hint" id="phone-hint">Malaysian phone number</div>
    </div>

    <button type="submit" class="btn-register" id="reg-btn">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
        <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
      </svg>
      <span id="btn-label">Create Admin Account</span>
    </button>

  </form>

  <?php endif; ?>

  <div class="or-divider">or</div>

  <div class="footer-links">
    <p class="login-line">Already have an account? <a href="admin_login.php">Login here</a></p>
    <a href="../welcome.php" class="back-link">← Back to Customer Site</a>
  </div>

  <div class="security-note">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
    Secure admin registration
  </div>

</div>

<script>
  const eyeOpen   = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
  const eyeClosed = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/>';

  function makeToggle(btnId, inputId, iconId) {
    document.getElementById(btnId).addEventListener('click', () => {
      const inp = document.getElementById(inputId);
      const icon = document.getElementById(iconId);
      const hidden = inp.type === 'password';
      inp.type = hidden ? 'text' : 'password';
      icon.innerHTML = hidden ? eyeClosed : eyeOpen;
    });
  }

  makeToggle('eye-btn-1', 'pw-input',      'eye-icon-1');
  makeToggle('eye-btn-2', 'confirm-input', 'eye-icon-2');

  // Name validation
  document.getElementById('name-input').addEventListener('input', function () {
    const hint = document.getElementById('name-hint');
    const ok   = this.value.trim().length >= 2;
    this.className = ok ? 'valid' : 'invalid';
    hint.className = 'field-hint ' + (ok ? 'valid' : 'invalid');
    hint.textContent = ok ? 'Looks good!' : 'At least 2 characters required';
  });

  // Email validation
  document.getElementById('email-input').addEventListener('input', function () {
    const hint  = document.getElementById('email-hint');
    const ok    = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value.trim());
    this.className = ok ? 'valid' : 'invalid';
    hint.className = 'field-hint ' + (ok ? 'valid' : 'invalid');
    hint.textContent = ok ? 'Valid email address' : 'Enter a valid email address';
  });

  // Password strength
  document.getElementById('pw-input').addEventListener('input', function () {
    const val    = this.value;
    const fill   = document.getElementById('strength-fill');
    const label  = document.getElementById('strength-label');
    const confirm = document.getElementById('confirm-input');

    if (!val) {
      fill.className   = 'strength-fill';
      label.className  = 'strength-label';
      label.textContent = '';
      return;
    }

    let score = 0;
    if (val.length >= 6)              score++;
    if (val.length >= 10)             score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))    score++;

    if (score <= 2) {
      fill.className   = 'strength-fill weak';
      label.className  = 'strength-label weak';
      label.textContent = 'Weak password';
    } else if (score <= 3) {
      fill.className   = 'strength-fill medium';
      label.className  = 'strength-label medium';
      label.textContent = 'Medium password';
    } else {
      fill.className   = 'strength-fill strong';
      label.className  = 'strength-label strong';
      label.textContent = 'Strong password';
    }

    // Re-check confirm if already typed
    if (confirm.value) checkConfirm();
  });

  // Confirm password
  function checkConfirm() {
    const confirmInput = document.getElementById('confirm-input');
    const hint         = document.getElementById('confirm-hint');
    const pw           = document.getElementById('pw-input').value;
    const ok           = confirmInput.value === pw && pw.length > 0;
    confirmInput.className = ok ? 'valid' : 'invalid';
    hint.className = 'field-hint ' + (ok ? 'valid' : 'invalid');
    hint.textContent = ok ? 'Passwords match!' : 'Passwords do not match';
  }

  document.getElementById('confirm-input').addEventListener('input', checkConfirm);

  // Phone validation
  document.getElementById('phone-input').addEventListener('input', function () {
    const hint = document.getElementById('phone-hint');
    const val  = this.value.trim();
    if (!val) {
      this.className = '';
      hint.className = 'field-hint';
      hint.textContent = 'Malaysian phone number';
      return;
    }
    const ok = /^[0-9\-\+\s]{8,15}$/.test(val);
    this.className = ok ? 'valid' : 'invalid';
    hint.className = 'field-hint ' + (ok ? 'valid' : 'invalid');
    hint.textContent = ok ? 'Valid phone number' : 'Invalid phone number format';
  });

  // Form submit
  const regForm = document.getElementById('reg-form');
  if (regForm) {
    regForm.addEventListener('submit', function (e) {
      const name     = document.getElementById('name-input').value.trim();
      const email    = document.getElementById('email-input').value.trim();
      const password = document.getElementById('pw-input').value;
      const confirm  = document.getElementById('confirm-input').value;

      if (name.length < 2) {
        e.preventDefault();
        document.getElementById('name-input').focus(); return;
      }
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        e.preventDefault();
        document.getElementById('email-input').focus(); return;
      }
      if (password.length < 6) {
        e.preventDefault();
        document.getElementById('pw-input').focus(); return;
      }
      if (password !== confirm) {
        e.preventDefault();
        document.getElementById('confirm-input').focus(); return;
      }

      // Loading state
      const btn = document.getElementById('reg-btn');
      document.getElementById('btn-label').textContent = 'Creating account…';
      btn.disabled = true;
    });
  }

  // Auto-focus
  window.addEventListener('load', () => {
    const nameInput = document.getElementById('name-input');
    if (nameInput) nameInput.focus();
  });
</script>

</body>
</html>