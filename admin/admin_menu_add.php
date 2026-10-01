<?php
// ===== CORRECT DB PATH FOR ADMIN FOLDER =====
require __DIR__ . '/../db.php';

// Check if admin logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name  = $_SESSION['admin_name'] ?? 'Admin';
$message     = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_name    = trim($_POST['menu_name']   ?? '');
    $description  = trim($_POST['description'] ?? '');
    $price        = $_POST['price']             ?? '';
    $category     = $_POST['category']          ?? '';
    $availability = isset($_POST['availability']) ? 1 : 0;
    $best_seller  = isset($_POST['best_seller'])  ? 1 : 0;
    $file_path    = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['file']['tmp_name'];
        $fileName      = $_FILES['file']['name'];
        $fileSize      = $_FILES['file']['size'];
        $fileNameCmps  = explode('.', $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize       = 5 * 1024 * 1024;

        if (!in_array($fileExtension, $allowedExtensions)) {
            $message     = 'Only image files allowed: JPG, JPEG, PNG, GIF, WEBP';
            $messageType = 'error';
        } elseif ($fileSize > $maxFileSize) {
            $message     = 'File size must be less than 5MB.';
            $messageType = 'error';
        } else {
            $uploadDir = __DIR__ . '/../uploads/menu_files/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $newFileName = 'menu_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath    = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $file_path = 'uploads/menu_files/' . $newFileName;
            } else {
                $message     = 'Error uploading file. Check folder permissions.';
                $messageType = 'error';
            }
        }
    }

    if ($menu_name && $price && $category && !$message) {
        try {
            $subcategory = ($category === 'beverages') ? trim($_POST['subcategory'] ?? '') : null;
            $stmt = $pdo->prepare("
                INSERT INTO menu (menu_name, description, price, category, subcategory, availability, best_seller, file_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$menu_name, $description, $price, $category, $subcategory, $availability, $best_seller, $file_path]);
            $message     = 'Menu item "' . htmlspecialchars($menu_name) . '" added successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message     = 'Failed to add menu item: ' . $e->getMessage();
            $messageType = 'error';
            if ($file_path) {
                $fullPath = __DIR__ . '/../' . $file_path;
                if (file_exists($fullPath)) unlink($fullPath);
            }
        }
    } elseif (!$message) {
        $message     = 'Please fill in all required fields.';
        $messageType = 'error';
    }
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
        'utensils' => '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'warning'  => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'camera'   => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'star'     => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'ruler'    => '<polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>',
        'palette'  => '<path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/><circle cx="6.5" cy="11.5" r="1.5"/><circle cx="9.5" cy="7.5" r="1.5"/><circle cx="14.5" cy="7.5" r="1.5"/><circle cx="17.5" cy="11.5" r="1.5"/>',
        'bulb'     => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/>',
        'x'        => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
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
<title>Add Menu Item — Cafe Digital</title>
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

/* ─── SIDEBAR ─── */
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

.nav-link.active .nav-icon { background: rgba(59,130,246,0.2); }
.nav-link:hover  .nav-icon { background: rgba(255,255,255,0.08); }

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

/* ─── OVERLAY ─── */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 199;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
}

.sidebar-overlay.visible {
  opacity: 1;
  pointer-events: auto;
}

/* ─── MAIN ─── */
.main {
  margin-left: var(--sidebar-width);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* ─── TOPBAR ─── */
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

.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

/* ─── HAMBURGER ─── */
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

.topbar-title h1 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
}

.topbar-title p {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin-top: 1px;
}

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

.admin-pill span {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-primary);
}

/* ─── CONTENT ─── */
.content { padding: 26px 30px; flex: 1; }

/* ─── MESSAGE ─── */
.message {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: var(--radius);
  padding: 11px 16px;
  margin-bottom: 18px;
  font-size: 0.82rem;
  font-weight: 600;
  animation: fadeUp 0.3s ease both;
  border: 1px solid transparent;
}

.message svg { flex-shrink: 0; }
.message.success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.message.error   { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }

/* ─── PAGE GRID ─── */
.page-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 18px;
  align-items: start;
}

/* ─── CARD ─── */
.card {
  background: var(--card-bg);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  animation: fadeUp 0.4s ease both;
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 16px 18px;
  border-bottom: 1px solid var(--border);
  background: #fff;
}

.card-title-wrap { display: flex; align-items: center; gap: 10px; }

.card-emoji {
  width: 34px; height: 34px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  background: #eff6ff;
  color: var(--blue);
}

.card-title { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 7px; }
.card-title svg { color: var(--text-muted); }
.card-subtitle { font-size: 0.73rem; color: var(--text-muted); margin-top: 2px; }
.card-body { padding: 18px; }

/* ─── FORM ─── */
.form-group { margin-bottom: 16px; }

.form-group label {
  display: block;
  font-weight: 600;
  font-size: 0.82rem;
  color: var(--text-primary);
  margin-bottom: 6px;
}

.form-group label span.required { color: var(--red); }

.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 0.84rem;
  color: var(--text-primary);
  transition: border-color 0.2s, box-shadow 0.2s;
  background: #fff;
  font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  border-color: #bfdbfe;
  outline: none;
  box-shadow: 0 0 0 3px #eff6ff;
}

.form-group textarea { resize: vertical; min-height: 95px; }

.price-input-wrap {
  display: flex;
  align-items: center;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: #fff;
}

.price-input-wrap:focus-within {
  border-color: #bfdbfe;
  box-shadow: 0 0 0 3px #eff6ff;
}

.price-prefix {
  padding: 10px 12px;
  background: #f8fafc;
  color: var(--text-secondary);
  font-weight: 700;
  font-size: 0.83rem;
  border-right: 1px solid var(--border);
  white-space: nowrap;
}

.price-input-wrap input {
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  flex: 1;
}

.checkbox-group {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.checkbox-group:hover { border-color: #bfdbfe; background: #f8fafc; }

.checkbox-group input[type="checkbox"] {
  width: 16px; height: 16px;
  accent-color: var(--blue);
  cursor: pointer;
  flex-shrink: 0;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: var(--text-secondary);
  font-weight: 500;
  cursor: pointer;
  user-select: none;
}

.checkbox-label svg { color: var(--text-muted); }

.upload-area {
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  padding: 24px 18px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: #f8fafc;
  position: relative;
}

.upload-area:hover, .upload-area.dragover {
  border-color: #93c5fd;
  background: #eff6ff;
}

.upload-icon { display: flex; align-items: center; justify-content: center; margin-bottom: 8px; color: var(--text-muted); }
.upload-text { font-size: 0.82rem; color: var(--text-primary); font-weight: 600; margin-bottom: 3px; }
.upload-subtext { font-size: 0.72rem; color: var(--text-muted); }

.upload-area input[type="file"] {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  opacity: 0;
  cursor: pointer;
}

.image-preview {
  display: none;
  margin-top: 12px;
  text-align: center;
  padding: 14px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: #fff;
}

.image-preview img {
  max-width: 100%;
  max-height: 200px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  object-fit: cover;
  box-shadow: 0 3px 10px rgba(0,0,0,0.06);
}

.preview-name { font-size: 0.74rem; color: var(--text-secondary); margin-top: 8px; }

.remove-image {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  margin-top: 7px;
  color: #dc2626;
  font-size: 0.76rem;
  cursor: pointer;
  font-weight: 600;
  background: none;
  border: none;
  padding: 0;
}

.remove-image:hover { text-decoration: underline; }

.submit-btn {
  width: 100%;
  padding: 12px;
  background: var(--blue);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.86rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.submit-btn:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(59,130,246,0.25);
}

.submit-btn:active { transform: translateY(0); }

/* ─── RIGHT PANEL ─── */
.right-panel { display: flex; flex-direction: column; gap: 16px; }
.info-card .card-header { padding: 14px 16px; }
.info-card .card-body   { padding: 14px 16px; }

.guide-list { display: flex; flex-direction: column; gap: 9px; }

.guide-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.guide-item.tip { background: #fffbeb; border-color: #fde68a; }

.guide-icon {
  width: 30px; height: 30px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  background: #fff;
  color: var(--text-secondary);
}

.guide-item.tip .guide-icon { background: #fef3c7; color: #92400e; }

.guide-item-title { font-size: 0.79rem; font-weight: 700; color: var(--text-primary); }
.guide-item-sub   { font-size: 0.72rem; color: var(--text-secondary); }
.guide-item.tip .guide-item-title,
.guide-item.tip .guide-item-sub { color: #92400e; }

.footer {
  padding: 14px 30px;
  border-top: 1px solid var(--border);
  text-align: center;
  font-size: 0.72rem;
  color: var(--text-muted);
}

/* ─── RESPONSIVE ─── */
@media (max-width: 1200px) {
  .page-grid { grid-template-columns: 1fr 320px; }
}

@media (max-width: 1024px) {
  .page-grid { grid-template-columns: 1fr; }
  .right-panel { display: grid; grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    box-shadow: none;
  }

  .sidebar.open {
    transform: translateX(0);
    box-shadow: 8px 0 32px rgba(0,0,0,0.25);
  }

  .sidebar-overlay { display: block; }

  .main { margin-left: 0; }

  body.sidebar-open { overflow: hidden; }

  .hamburger { display: flex; }

  .content { padding: 16px; }
  .topbar  { padding: 12px 16px; }
  .footer  { padding: 12px 16px; }

  .right-panel { grid-template-columns: 1fr; }

  .date-pill { display: none; }
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
    <a href="admin_dashboard.php" class="nav-link">
      <span class="nav-icon"><?= icon('home') ?></span> Dashboard
    </a>

    <div class="nav-section-label">Catalogue</div>
    <a href="admin_menu_add.php" class="nav-link active">
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

<!-- ─── MAIN ─── -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
      <div class="topbar-title">
        <h1>Add Menu Item</h1>
        <p>Create a new food, beverage, or dessert listing.</p>
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

  <!-- CONTENT -->
  <div class="content">

    <?php if ($message): ?>
      <div class="message <?= $messageType ?>">
        <?= icon($messageType === 'success' ? 'check' : 'warning', 16) ?>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <div class="page-grid">

      <!-- LEFT: MAIN FORM -->
      <div class="card">
        <div class="card-header">
          <div class="card-title-wrap">
            <span class="card-emoji"><?= icon('utensils', 18) ?></span>
            <div>
              <div class="card-title">New Menu Item</div>
              <div class="card-subtitle">Fill in required details to publish this item.</div>
            </div>
          </div>
        </div>

        <div class="card-body">
          <form method="post" enctype="multipart/form-data" id="menuForm">

            <div class="form-group">
              <label>Menu Name <span class="required">*</span></label>
              <input type="text" name="menu_name" placeholder="e.g. Nasi Lemak Special" required>
            </div>

            <div class="form-group">
              <label>Description</label>
              <textarea name="description" placeholder="Describe the menu item, ingredients, etc..."></textarea>
            </div>

            <div class="form-group">
              <label>Price <span class="required">*</span></label>
              <div class="price-input-wrap">
                <span class="price-prefix">RM</span>
                <input type="number" name="price" step="0.01" min="0" placeholder="0.00" required>
              </div>
            </div>

            <div class="form-group">
              <label>Category <span class="required">*</span></label>
              <select name="category" id="categorySelect" required onchange="updateCategoryPreview(this.value)">
                <option value="">-- Select Category --</option>
                <option value="food">Food</option>
                <option value="beverages">Beverages</option>
                <option value="dessert">Dessert</option>
              </select>
            </div>

            <div class="form-group" id="subcategoryGroup" style="display:none;">
              <label>Beverage Type <span class="required">*</span></label>
              <select name="subcategory" id="subcategorySelect">
                <option value="">-- Select Type --</option>
                <option value="coffee">Coffee</option>
                <option value="latte">Latte</option>
                <option value="sparkling tea">Sparkling Tea</option>
                <option value="ice crush series">Ice Crush Series</option>
                <option value="al-hadad shake">Al-Hadad Shake</option>
              </select>
            </div>

            <div class="form-group">
              <label>Availability</label>
              <div class="checkbox-group" onclick="toggleCheck('availability')">
                <input type="checkbox" name="availability" id="availability" checked>
                <span class="checkbox-label"><?= icon('check', 14) ?> Available for order</span>
              </div>
            </div>

            <div class="form-group">
              <label>Best Seller</label>
              <div class="checkbox-group" onclick="toggleCheck('best_seller')">
                <input type="checkbox" name="best_seller" id="best_seller">
                <span class="checkbox-label"><?= icon('star', 14) ?> Mark as Best Seller</span>
              </div>
            </div>

            <div class="form-group">
              <label>Menu Image (Optional)</label>
              <div class="upload-area" id="uploadArea">
                <span class="upload-icon"><?= icon('camera', 30) ?></span>
                <div class="upload-text">Click or drag & drop image here</div>
                <div class="upload-subtext">JPG, JPEG, PNG, GIF, WEBP • Max 5MB</div>
                <input type="file" name="file" id="fileInput" accept="image/*">
              </div>

              <div class="image-preview" id="imagePreview">
                <img id="previewImg" src="" alt="Preview">
                <div class="preview-name" id="previewName"></div>
                <button type="button" class="remove-image" onclick="removeImage()"><?= icon('x', 12) ?> Remove Image</button>
              </div>
            </div>

            <button type="submit" class="submit-btn">
              <?= icon('plus', 16) ?>
              <span>Add Menu Item</span>
            </button>

          </form>
        </div>
      </div>

      <!-- RIGHT: INFO PANEL -->
      <div class="right-panel">

        <div class="card info-card">
          <div class="card-header">
            <div class="card-title"><?= icon('camera', 16) ?> Image Guide</div>
          </div>
          <div class="card-body">
            <div class="guide-list">
              <div class="guide-item">
                <span class="guide-icon"><?= icon('check', 16) ?></span>
                <div>
                  <div class="guide-item-title">Recommended</div>
                  <div class="guide-item-sub">Square image, 500x500px+, JPG/PNG</div>
                </div>
              </div>
              <div class="guide-item">
                <span class="guide-icon"><?= icon('ruler', 16) ?></span>
                <div>
                  <div class="guide-item-title">Max File Size</div>
                  <div class="guide-item-sub">5MB per image</div>
                </div>
              </div>
              <div class="guide-item">
                <span class="guide-icon"><?= icon('palette', 16) ?></span>
                <div>
                  <div class="guide-item-title">Formats Allowed</div>
                  <div class="guide-item-sub">JPG, JPEG, PNG, GIF, WEBP</div>
                </div>
              </div>
              <div class="guide-item tip">
                <span class="guide-icon"><?= icon('bulb', 16) ?></span>
                <div>
                  <div class="guide-item-title">Pro Tip</div>
                  <div class="guide-item-sub">Use food photography with good lighting for best results</div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div><!-- /content -->

  <div class="footer">
    Cafe Digital Admin Panel &nbsp;|&nbsp; © <?= date('Y') ?>
  </div>
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
  if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
});

sidebar.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeSidebar();
  });
});

// ─── FILE UPLOAD ──────────────────────────────────────────────
const fileInput    = document.getElementById('fileInput');
const uploadArea   = document.getElementById('uploadArea');
const imagePreview = document.getElementById('imagePreview');
const previewImg   = document.getElementById('previewImg');
const previewName  = document.getElementById('previewName');

fileInput.addEventListener('change', function () {
  const file = this.files[0];
  if (file) showPreview(file);
});

uploadArea.addEventListener('dragover', function (e) {
  e.preventDefault();
  this.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', function () {
  this.classList.remove('dragover');
});

uploadArea.addEventListener('drop', function (e) {
  e.preventDefault();
  this.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    showPreview(file);
  }
});

function showPreview(file) {
  const allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
  if (!allowed.includes(file.type)) {
    alert('Only image files are allowed: JPG, JPEG, PNG, GIF, WEBP');
    fileInput.value = '';
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    alert('File size must be less than 5MB. Your file: ' + (file.size/1024/1024).toFixed(2) + 'MB');
    fileInput.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = function (e) {
    previewImg.src          = e.target.result;
    previewName.textContent = file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
    imagePreview.style.display = 'block';
    uploadArea.style.display   = 'none';
  };
  reader.readAsDataURL(file);
}

function removeImage() {
  fileInput.value            = '';
  previewImg.src             = '';
  previewName.textContent    = '';
  imagePreview.style.display = 'none';
  uploadArea.style.display   = 'block';
}

// ─── CHECKBOX / CATEGORY ─────────────────────────────────────
function toggleCheck(id) {
  const cb = document.getElementById(id);
  cb.checked = !cb.checked;
}

function updateCategoryPreview(value) {
  const subGroup = document.getElementById('subcategoryGroup');
  if (value === 'beverages') {
    subGroup.style.display = 'block';
    document.getElementById('subcategorySelect').required = true;
  } else {
    subGroup.style.display = 'none';
    document.getElementById('subcategorySelect').required = false;
    document.getElementById('subcategorySelect').value = '';
  }
}
</script>
</body>
</html>