<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/db.php';

$message     = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone    = trim($_POST['phone']    ?? '');

    if ($name && $email && $password) {
    $check = $pdo->prepare("SELECT customer_id FROM customer WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        $message = "That email is already registered. Try logging in instead.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO customer (full_name, email, password, phone) VALUES (?, ?, ?, ?)");
        try {
            $stmt->execute([$name, $email, $password_hash, $phone]);
            $message     = "Registration successful! You can now <a href='login.php'>login here</a>.";
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = "That email is already registered. Try logging in instead.";
        }
    }
} else {
    $message = "Please fill in all required fields.";
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register — MIA COFFEE</title>
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
    .register-card {
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

    /* ── Messages ── */
    .msg-banner {
      display: flex;
      align-items: flex-start;
      gap: 9px;
      border-radius: 10px;
      padding: 10px 13px;
      margin-bottom: 14px;
      font-size: 13px;
      font-weight: 500;
      line-height: 1.5;
    }

    .msg-banner svg { flex-shrink: 0; width: 16px; height: 16px; margin-top: 1px; }

    .msg-banner.error {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
    }

    .msg-banner.success {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #15803d;
    }

    .msg-banner a { color: inherit; font-weight: 700; }

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

    .optional-tag {
      font-size: 10px;
      color: #ccc;
      font-weight: 400;
      letter-spacing: 0;
      text-transform: none;
      margin-left: 4px;
    }

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

    /* password toggle */
    .eye-btn {
      position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; padding: 4px;
      color: #bbb; display: flex; align-items: center;
      border-radius: 6px; transition: color 0.2s;
    }

    .eye-btn:hover { color: #888; }
    .eye-btn svg { width: 16px; height: 16px; }

    /* ── Submit button ── */
    .btn-register {
      width: 100%; padding: 12px;
      background: #1a1a2e; color: #fff;
      border: none; border-radius: 11px;
      font-size: 14px; font-weight: 500;
      font-family: 'DM Sans', sans-serif;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      margin-top: 5px;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    }

    .btn-register svg { width: 17px; height: 17px; opacity: 0.75; }

    .btn-register:hover {
      background: #0d0d1a;
      transform: translateY(-1px);
      box-shadow: 0 6px 18px rgba(26,26,46,0.25);
    }

    .btn-register:active { transform: translateY(0); box-shadow: none; }

    /* ── Bottom links ── */
    .bottom-links {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: 1.2rem;
      font-size: 12px;
    }

    .login-link { color: #999; }
    .login-link a { color: #e31937; font-weight: 500; text-decoration: none; }
    .login-link a:hover { text-decoration: underline; }

    .back-link a { color: #bbb; text-decoration: none; transition: color 0.2s; }
    .back-link a:hover { color: #888; }
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

  <p class="tagline">Create your account to get started.</p>
  <hr class="divider-line">

  <!-- Message banner -->
  <?php if ($message): ?>
  <div class="msg-banner <?= $messageType ?>">
    <?php if ($messageType === 'error'): ?>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
    <?php else: ?>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
      </svg>
    <?php endif; ?>
    <?= $message ?>
  </div>
  <?php endif; ?>

  <!-- Registration form -->
  <form method="post">

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
        Nickname
      </label>
      <div class="input-wrap">
        <input type="text" name="name"
               placeholder="Your nickname"
               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
               autocomplete="nickname"
               required>
      </div>
    </div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
        </svg>
        Email address
      </label>
      <div class="input-wrap">
        <input type="email" name="email"
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
        <input type="password" name="password"
               id="pw-input"
               placeholder="Create a password"
               autocomplete="new-password"
               required>
        <button type="button" class="eye-btn" id="eye-btn" aria-label="Toggle password visibility">
          <svg id="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>

    <div class="field">
      <label>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.64 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6.29 6.29l.96-.96a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
        </svg>
        Phone <span class="optional-tag">optional</span>
      </label>
      <div class="input-wrap">
        <input type="text" name="phone"
               placeholder="+60 12-345 6789"
               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
      </div>
    </div>

    <button type="submit" class="btn-register">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
        <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
      </svg>
      Create Account
    </button>

  </form>

  <div class="bottom-links">
    <div class="login-link">Already have an account? <a href="login.php">Login here</a></div>
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