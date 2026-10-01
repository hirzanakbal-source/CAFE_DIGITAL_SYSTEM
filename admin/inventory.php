<?php
require __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name = $_SESSION['admin_name'] ?? $_SESSION['name'] ?? 'Admin';

// Make sure the ITEM table exists (auto-migrate on first load)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS item (
        item_id INT AUTO_INCREMENT PRIMARY KEY,
        item_code VARCHAR(50) NOT NULL UNIQUE,
        item_name VARCHAR(150) NOT NULL,
        item_category VARCHAR(50) NOT NULL,
        item_group VARCHAR(50) NOT NULL,
        vendor VARCHAR(120) DEFAULT NULL,
        spec VARCHAR(50) DEFAULT NULL,
        quantity INT NOT NULL DEFAULT 0,
        unit VARCHAR(30) DEFAULT NULL,
        image_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");
$itemColumns = $pdo->query("SHOW COLUMNS FROM item")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('quantity', $itemColumns, true)) {
    $pdo->exec("ALTER TABLE item ADD COLUMN quantity INT NOT NULL DEFAULT 0");
}
if (!in_array('unit', $itemColumns, true)) {
    $pdo->exec("ALTER TABLE item ADD COLUMN unit VARCHAR(30) DEFAULT NULL");
}

$uploadDir = __DIR__ . '/uploads/items/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$categories = ['Raw Ingredients', 'Merchandise', 'Finished Goods'];
$groups     = ['Dairy', 'Drinks', 'Dry Goods', 'Packaging', 'Syrups & Sauces', 'Other'];
$units      = ['Boxes', 'Bottles', 'Units', 'Packets', 'Cans', 'Bags', 'Kg', 'Liters'];

$errors  = [];
$success = '';

function handleImageUpload(array $file, ?string $existingPath, string $uploadDir): array {
    // Returns [path, error]. If no new file was chosen, keeps the existing path.
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [$existingPath, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [$existingPath, 'Image upload failed.'];
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime    = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return [$existingPath, 'Image must be JPG, PNG, or WEBP.'];
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        return [$existingPath, 'Image must be under 3MB.'];
    }

    $filename = 'item_' . uniqid() . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        return [$existingPath, 'Could not save uploaded image.'];
    }

    if ($existingPath && is_file(__DIR__ . '/' . $existingPath)) {
        @unlink(__DIR__ . '/' . $existingPath);
    }

    return ['uploads/items/' . $filename, null];
}

// Handle delete
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $delId = (int)($_POST['item_id'] ?? 0);
    if ($delId > 0) {
                $stmt = $pdo->prepare("SELECT image_path FROM item WHERE item_id = ?");
        $stmt->execute([$delId]);
        $img = $stmt->fetchColumn();

        $pdo->prepare("DELETE FROM item WHERE item_id = ?")->execute([$delId]);

        if ($img && is_file(__DIR__ . '/' . $img)) {
            @unlink(__DIR__ . '/' . $img);
        }
        header("Location: inventory.php?deleted=1");
        exit;
    }
}

// Handle save (insert or update)
if (isset($_POST['action']) && $_POST['action'] === 'save') {
    $itemId       = (int)($_POST['item_id'] ?? 0);
    $itemCode     = trim($_POST['item_code'] ?? '');
    $itemName     = trim($_POST['item_name'] ?? '');
    $itemCategory = trim($_POST['item_category'] ?? '');
    $itemGroup    = trim($_POST['item_group'] ?? '');
    $vendor       = trim($_POST['vendor'] ?? '');
    $quantity     = (int)($_POST['quantity'] ?? 0);
    $unit         = trim($_POST['unit'] ?? '');

    if ($itemCode === '')                    $errors[] = 'Item code is required.';
    if ($itemName === '')                    $errors[] = 'Item name is required.';
    if (!in_array($itemCategory, $categories, true)) $errors[] = 'Choose a valid category.';
    if (!in_array($itemGroup, $groups, true))        $errors[] = 'Choose a valid group.';
    if ($quantity < 0)                        $errors[] = 'Quantity cannot be negative.';

    $existingPath = null;
    if ($itemId > 0) {
        $stmt = $pdo->prepare("SELECT image_path FROM item WHERE item_id = ?");
        $stmt->execute([$itemId]);
        $existingPath = $stmt->fetchColumn() ?: null;
    }

    if (!$errors) {
        [$imagePath, $imgErr] = handleImageUpload($_FILES['item_image'] ?? [], $existingPath, $uploadDir);
        if ($imgErr) $errors[] = $imgErr;
    }

    if (!$errors) {
        try {
                 if ($itemId > 0) {
                    $stmt = $pdo->prepare("
                    UPDATE item SET item_code=?, item_name=?, item_category=?, item_group=?, vendor=?, quantity=?, unit=?, image_path=?
                    WHERE item_id=?
                ");
                $stmt->execute([$itemCode, $itemName, $itemCategory, $itemGroup, $vendor ?: null, $quantity, $unit ?: null, $imagePath, $itemId]);
                header("Location: inventory.php?updated=1");
            } else {
                   $stmt = $pdo->prepare("
                    INSERT INTO item (item_code, item_name, item_category, item_group, vendor, quantity, unit, image_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$itemCode, $itemName, $itemCategory, $itemGroup, $vendor ?: null, $quantity, $unit ?: null, $imagePath]);
                header("Location: inventory.php?saved=1");
            }
            exit;
        } catch (PDOException $e) {
            $errors[] = ($e->getCode() === '23000')
                ? 'That item code already exists.'
                : 'Database error: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['saved']))   $success = 'Item added to inventory.';
if (isset($_GET['updated'])) $success = 'Item updated.';
if (isset($_GET['deleted'])) $success = 'Item removed.';

// Load item for edit mode
$editItem = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM item WHERE item_id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$vendors = $pdo->query("SELECT DISTINCT vendor FROM item WHERE vendor IS NOT NULL AND vendor <> '' ORDER BY vendor")->fetchAll(PDO::FETCH_COLUMN);
$items   = $pdo->query("SELECT * FROM item ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalItems = count($items);

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
        'box'      => '<path d="M21 8V21H3V8"/><path d="M1 3h22v5H1z"/><line x1="10" y1="12" x2="14" y2="12"/>',
        'edit'     => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4Z"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'upload'   => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'search'   => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'image'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'check'    => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'warning'  => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'x'        => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
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
<title>Inventory — Cafe Digital</title>
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

.sidebar-brand { padding: 28px 22px 22px; border-bottom: 1px solid rgba(255,255,255,0.07); }
.brand-icon { width: 38px; height: 38px; background: var(--sidebar-accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; margin-bottom: 10px; }
.sidebar-brand h2 { font-size: 0.95rem; font-weight: 700; color: #fff; letter-spacing: 0.01em; }
.sidebar-brand p { font-size: 0.72rem; color: var(--sidebar-text); margin-top: 2px; letter-spacing: 0.03em; }

.sidebar-nav { padding: 18px 0; flex: 1; }
.nav-section-label { font-size: 0.65rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.1em; padding: 14px 22px 6px; }
.nav-link { display: flex; align-items: center; gap: 11px; padding: 10px 22px; color: var(--sidebar-text); text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.2s; position: relative; }
.nav-link:hover { background: var(--sidebar-hover); color: #fff; }
.nav-link.active { background: rgba(59,130,246,0.15); color: var(--sidebar-accent); }
.nav-link.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--sidebar-accent); border-radius: 0 3px 3px 0; }
.nav-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: rgba(255,255,255,0.04); transition: background 0.2s; }
.nav-link.active .nav-icon { background: rgba(59,130,246,0.2); }
.nav-link:hover .nav-icon { background: rgba(255,255,255,0.08); }

.sidebar-footer { padding: 16px 22px; border-top: 1px solid rgba(255,255,255,0.07); }
.logout-btn { display: flex; align-items: center; gap: 10px; color: #f87171; text-decoration: none; font-size: 0.85rem; font-weight: 500; padding: 9px 12px; border-radius: 8px; transition: background 0.2s; }
.logout-btn:hover { background: rgba(239,68,68,0.1); }

.sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 199; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px); }
.sidebar-overlay.visible { opacity: 1; pointer-events: auto; }

.main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

.topbar { background: #fff; border-bottom: 1px solid var(--border); padding: 14px 30px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; position: sticky; top: 0; z-index: 210; }
.hamburger { display: none; flex-direction: column; justify-content: center; align-items: center; gap: 5px; width: 36px; height: 36px; border: 1px solid var(--border); border-radius: 9px; background: #f8fafc; cursor: pointer; flex-shrink: 0; transition: background 0.2s, border-color 0.2s; padding: 0; }
.hamburger:hover { background: #f1f5f9; border-color: #cbd5e1; }
.hamburger span { display: block; width: 16px; height: 2px; background: var(--text-primary); border-radius: 2px; transition: transform 0.3s ease, opacity 0.3s ease, width 0.3s ease; transform-origin: center; }
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; width: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.topbar-left { display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1; }
.topbar-title h1 { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); }
.topbar-title p { font-size: 0.75rem; color: var(--text-secondary); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }

.admin-pill { display: flex; align-items: center; gap: 9px; background: #f8fafc; border: 1px solid var(--border); padding: 6px 14px 6px 7px; border-radius: 99px; }
.admin-avatar { width: 28px; height: 28px; background: var(--sidebar-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.78rem; font-weight: 700; }
.admin-pill span { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); }

.content { padding: 26px 30px; flex: 1; }

.alert { display: flex; align-items: center; gap: 10px; border-radius: var(--radius); padding: 11px 16px; margin-bottom: 22px; font-size: 0.82rem; font-weight: 500; animation: fadeUp 0.4s ease both; }
.alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.alert-error   { background: #fff1f2; border: 1px solid #fecdd3; color: #be123c; }
.alert svg { flex-shrink: 0; }

.section-heading { font-size: 0.82rem; font-weight: 700; color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.07em; display: flex; align-items: center; gap: 8px; margin-bottom: 14px; }
.section-heading::before { content: ''; width: 3px; height: 16px; background: var(--blue); border-radius: 2px; display: inline-block; }

.card { background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border); padding: 20px 22px; animation: fadeUp 0.4s ease 0.15s both; margin-bottom: 22px; }
.card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: 10px; }
.card-title { font-size: 0.88rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 7px; }
.card-title svg { color: var(--text-muted); }
.card-subtitle { font-size: 0.72rem; color: var(--text-muted); margin-top: 2px; }

/* --- Form --- */
.form-grid { display: grid; grid-template-columns: 220px 1fr 1fr; gap: 18px; }
.form-fields { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; align-content: start; }
.field-group.span-2 { grid-column: span 2; }
.field-label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
.field-input, .field-select {
  width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 8px;
  font-family: 'DM Sans', sans-serif; font-size: 0.85rem; color: var(--text-primary); background: #fff;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.field-input:focus, .field-select:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(59,130,246,0.12); }

.image-upload {
  border: 2px dashed var(--border); border-radius: var(--radius); height: 100%;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; padding: 16px; text-align: center; cursor: pointer; transition: border-color 0.2s, background 0.2s;
  position: relative; overflow: hidden; min-height: 200px;
}
.image-upload:hover { border-color: var(--blue); background: #f8fafc; }
.image-upload input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.image-upload img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.image-upload .upload-hint { color: var(--text-muted); }
.image-upload .upload-hint svg { color: var(--text-muted); margin-bottom: 4px; }
.image-upload .upload-hint p { font-size: 0.78rem; font-weight: 600; }
.image-upload .upload-hint span { font-size: 0.68rem; color: var(--text-muted); }

.form-actions { grid-column: 1 / -1; display: flex; gap: 10px; justify-content: flex-end; margin-top: 4px; }

.btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 20px; border-radius: 8px; font-size: 0.82rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: background 0.2s, color 0.2s, border-color 0.2s; }
.btn-primary { background: var(--blue); color: #fff; }
.btn-primary:hover { background: #2563eb; }
.btn-ghost { background: #fff; color: var(--text-secondary); border: 1px solid var(--border); }
.btn-ghost:hover { background: #f8fafc; }
.btn-sm { padding: 6px 12px; font-size: 0.75rem; }
.btn-icon { width: 32px; height: 32px; padding: 0; justify-content: center; border-radius: 8px; }
.btn-danger-ghost { background: #fff; color: var(--red); border: 1px solid #fecdd3; }
.btn-danger-ghost:hover { background: #fff1f2; }

/* --- Search --- */
.search-box { display: flex; align-items: center; gap: 8px; background: #f8fafc; border: 1px solid var(--border); border-radius: 99px; padding: 8px 14px; width: 260px; max-width: 100%; }
.search-box svg { color: var(--text-muted); flex-shrink: 0; }
.search-box input { border: none; background: transparent; outline: none; font-family: 'DM Sans', sans-serif; font-size: 0.82rem; width: 100%; color: var(--text-primary); }

/* --- Table --- */
table { width: 100%; border-collapse: collapse; }
thead th { text-align: left; padding: 8px 13px; font-size: 0.68rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.06em; background: #f8fafc; border-bottom: 1px solid var(--border); white-space: nowrap; }
tbody td { padding: 10px 13px; font-size: 0.82rem; color: var(--text-primary); border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
tbody tr:hover td { background: #f8fafc; }

.item-thumb { width: 42px; height: 42px; border-radius: 8px; object-fit: cover; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: var(--text-muted); flex-shrink: 0; }
.item-name-cell { display: flex; align-items: center; gap: 11px; }
.item-name-cell b { font-size: 0.83rem; }
.item-code-chip { font-family: 'Space Mono', monospace; font-size: 0.72rem; font-weight: 700; color: var(--blue); background: #eff6ff; padding: 2px 8px; border-radius: 5px; }

.badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 99px; font-size: 0.68rem; font-weight: 700; white-space: nowrap; }
.b-raw       { background: #f0fdf4; color: #15803d; }
.b-merch     { background: #f5f3ff; color: #6d28d9; }
.b-finished  { background: #eff6ff; color: #1d4ed8; }

.row-actions { display: flex; gap: 6px; justify-content: flex-end; }

.empty { text-align: center; padding: 30px 20px; color: var(--text-muted); }
.empty .empty-icon { display: flex; align-items: center; justify-content: center; margin-bottom: 10px; color: var(--text-muted); }
.empty p { font-size: 0.82rem; }

@media (max-width: 1024px) {
  .form-grid { grid-template-columns: 1fr; }
  .image-upload { min-height: 140px; }
}
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); box-shadow: none; }
  .sidebar.open { transform: translateX(0); box-shadow: 8px 0 32px rgba(0,0,0,0.25); }
  .sidebar-overlay { display: block; }
  .main { margin-left: 0; }
  body.sidebar-open { overflow: hidden; }
  .hamburger { display: flex; }
  .content { padding: 16px; }
  .topbar { padding: 12px 16px; }
  .form-fields { grid-template-columns: 1fr; }
  .field-group.span-2 { grid-column: span 1; }
  table { display: block; overflow-x: auto; }
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
    <a href="admin_dashboard.php" class="nav-link">
      <span class="nav-icon"><?= icon('home') ?></span> Dashboard
    </a>

    <div class="nav-section-label">Catalogue</div>
    <a href="admin_menu_add.php" class="nav-link">
      <span class="nav-icon"><?= icon('plus') ?></span> Add Menu
    </a>
    <a href="admin_menu_list.php" class="nav-link">
      <span class="nav-icon"><?= icon('file') ?></span> View / Edit Menu
    </a>
    <a href="inventory.php" class="nav-link active">
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
        <span></span><span></span><span></span>
      </button>
      <div class="topbar-title">
        <h1>Inventory</h1>
        <p>Manage cafe ingredients, beverages and stock items.</p>
      </div>
    </div>
    <div class="topbar-right">
      <div class="admin-pill">
        <div class="admin-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
        <span><?= htmlspecialchars($admin_name) ?></span>
      </div>
    </div>
  </div>

  <div class="content">

    <?php if ($success): ?>
      <div class="alert alert-success"><?= icon('check', 16) ?> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-error"><?= icon('warning', 16) ?> <?= htmlspecialchars(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <!-- Data Input Form -->
    <div class="card" id="formCard">
      <div class="card-header">
        <div>
          <div class="card-title"><?= icon($editItem ? 'edit' : 'plus', 16) ?> <?= $editItem ? 'Update Item' : 'Add New Item' ?></div>
          <div class="card-subtitle"><?= $editItem ? 'Editing ' . htmlspecialchars($editItem['item_code']) : 'Add a new ingredient, beverage or stock item' ?></div>
        </div>
      </div>

      <form method="POST" enctype="multipart/form-data" id="itemForm">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="item_id" id="item_id" value="<?= $editItem['item_id'] ?? 0 ?>">

        <div class="form-grid">

          <div class="field-group">
            <label class="field-label">Item Image</label>
            <label class="image-upload" id="imageDrop">
              <input type="file" name="item_image" id="item_image" accept="image/png,image/jpeg,image/webp">
              <img id="imagePreview" src="<?= $editItem && $editItem['image_path'] ? htmlspecialchars($editItem['image_path']) : '' ?>" style="<?= $editItem && $editItem['image_path'] ? '' : 'display:none;' ?>">
              <div class="upload-hint" id="uploadHint" style="<?= $editItem && $editItem['image_path'] ? 'display:none;' : '' ?>">
                <?= icon('upload', 22) ?>
                <p>Upload photo</p>
                <span>JPG, PNG or WEBP, max 3MB</span>
              </div>
            </label>
          </div>

          <div class="form-fields span-2" style="grid-column: span 2;">
            <div class="field-group">
              <label class="field-label">Item Code</label>
              <input class="field-input" type="text" name="item_code" id="item_code" placeholder="e.g. RM-0012" value="<?= htmlspecialchars($editItem['item_code'] ?? '') ?>" required>
            </div>
            <div class="field-group">
              <label class="field-label">Item Name</label>
              <input class="field-input" type="text" name="item_name" id="item_name" placeholder="e.g. Fresh Milk" value="<?= htmlspecialchars($editItem['item_name'] ?? '') ?>" required>
            </div>

            <div class="field-group">
              <label class="field-label">Item Category</label>
              <select class="field-select" name="item_category" id="item_category" required>
                <option value="">Select category</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= htmlspecialchars($c) ?>" <?= ($editItem['item_category'] ?? '') === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field-group">
              <label class="field-label">Item Group</label>
              <select class="field-select" name="item_group" id="item_group" required>
                <option value="">Select group</option>
                <?php foreach ($groups as $g): ?>
                  <option value="<?= htmlspecialchars($g) ?>" <?= ($editItem['item_group'] ?? '') === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="field-group">
              <label class="field-label">Vendor</label>
              <select class="field-select" name="vendor" id="vendor_select">
                <option value="">Select vendor</option>
                <?php foreach ($vendors as $v): ?>
                  <option value="<?= htmlspecialchars($v) ?>" <?= ($editItem['vendor'] ?? '') === $v ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
                <?php endforeach; ?>
                <option value="__new__">+ Add new vendor…</option>
              </select>
              <input class="field-input" type="text" name="vendor_new" id="vendor_new" placeholder="New vendor name" style="display:none; margin-top:8px;">
            </div>
            <div class="field-group">
              <label class="field-label">Quantity</label>
              <input class="field-input" type="number" name="quantity" id="quantity" min="0" step="1" placeholder="e.g. 2" value="<?= htmlspecialchars($editItem['quantity'] ?? 0) ?>">
            </div>
            <div class="field-group">
              <label class="field-label">Unit</label>
              <select class="field-select" name="unit" id="unit">
                <option value="">Select unit</option>
                <?php foreach ($units as $u): ?>
                  <option value="<?= htmlspecialchars($u) ?>" <?= ($editItem['unit'] ?? '') === $u ? 'selected' : '' ?>><?= htmlspecialchars($u) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <a href="inventory.php" class="btn btn-ghost" id="clearBtn"><?= icon('x', 15) ?> Clear</a>
            <button type="submit" class="btn btn-primary"><?= icon($editItem ? 'check' : 'plus', 15) ?> <?= $editItem ? 'Update Item' : 'Save Item' ?></button>
          </div>

        </div>
      </form>
    </div>

    <!-- Inventory Display Table -->
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title"><?= icon('box', 16) ?> All Stock Items</div>
          <div class="card-subtitle"><?= $totalItems ?> item<?= $totalItems === 1 ? '' : 's' ?> in inventory</div>
        </div>
        <div class="search-box">
          <?= icon('search', 15) ?>
          <input type="text" id="tableSearch" placeholder="Search code, name, vendor...">
        </div>
      </div>

      <?php if (!empty($items)): ?>
        <table id="itemsTable">
          <thead>
              <tr>
              <th>Item</th>
              <th>Category</th>
              <th>Group</th>
              <th>Stock</th>
              <th>Vendor</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $it):
              $cc = match($it['item_category']) {
                'Raw Ingredients' => 'b-raw',
                'Merchandise'     => 'b-merch',
                'Finished Goods'  => 'b-finished',
                default           => 'b-raw',
              };
                                         $rowText = strtolower($it['item_code'].' '.$it['item_name'].' '.$it['vendor'].' '.$it['item_category'].' '.$it['item_group'].' '.($it['unit'] ?? '')); 
            ?>
            <tr data-search="<?= htmlspecialchars($rowText) ?>">
              <td>
                <div class="item-name-cell">
                  <?php if ($it['image_path']): ?>
                    <img class="item-thumb" src="<?= htmlspecialchars($it['image_path']) ?>" alt="">
                  <?php else: ?>
                    <div class="item-thumb"><?= icon('image', 16) ?></div>
                  <?php endif; ?>
                  <div>
                    <b><?= htmlspecialchars($it['item_name']) ?></b><br>
                    <span class="item-code-chip"><?= htmlspecialchars($it['item_code']) ?></span>
                  </div>
                </div>
              </td>
              <td><span class="badge <?= $cc ?>"><?= htmlspecialchars($it['item_category']) ?></span></td>
              <td><?= htmlspecialchars($it['item_group']) ?></td>
               <td>
                <b style="font-family:'Space Mono',monospace;"><?= (int)($it['quantity'] ?? 0) ?></b>
                <span style="color:var(--text-secondary);"><?= htmlspecialchars($it['unit'] ?: '') ?></span>
              </td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($it['vendor'] ?: '—') ?></td>
              <td>
                <div class="row-actions">
                  <a href="inventory.php?edit=<?= $it['item_id'] ?>#formCard" class="btn btn-ghost btn-icon" title="Edit"><?= icon('edit', 14) ?></a>
                  <form method="POST" onsubmit="return confirm('Delete this item?');" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" value="<?= $it['item_id'] ?>">
                    <button type="submit" class="btn btn-danger-ghost btn-icon" title="Delete"><?= icon('trash', 14) ?></button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty">
          <span class="empty-icon"><?= icon('box', 34) ?></span>
          <p>No inventory items yet. Add your first item above.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <div style="padding:14px 30px;border-top:1px solid var(--border);text-align:center;font-size:0.72rem;color:var(--text-muted);">
    Cafe Digital Admin Panel &nbsp;|&nbsp; © <?= date('Y') ?>
  </div>
</div>

<script>
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('sidebarOverlay');
const hamburger = document.getElementById('hamburger');

function openSidebar() {
  sidebar.classList.add('open'); overlay.classList.add('visible');
  hamburger.classList.add('open'); hamburger.setAttribute('aria-expanded', 'true');
  document.body.classList.add('sidebar-open');
}
function closeSidebar() {
  sidebar.classList.remove('open'); overlay.classList.remove('visible');
  hamburger.classList.remove('open'); hamburger.setAttribute('aria-expanded', 'false');
  document.body.classList.remove('sidebar-open');
}
hamburger.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
overlay.addEventListener('click', closeSidebar);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });
sidebar.querySelectorAll('.nav-link').forEach(link => link.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); }));

// Image preview
const fileInput   = document.getElementById('item_image');
const imgPreview  = document.getElementById('imagePreview');
const uploadHint  = document.getElementById('uploadHint');
fileInput.addEventListener('change', () => {
  const file = fileInput.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    imgPreview.src = e.target.result;
    imgPreview.style.display = 'block';
    uploadHint.style.display = 'none';
  };
  reader.readAsDataURL(file);
});

// Vendor "add new" toggle
const vendorSelect = document.getElementById('vendor_select');
const vendorNew     = document.getElementById('vendor_new');
vendorSelect.addEventListener('change', () => {
  if (vendorSelect.value === '__new__') {
    vendorNew.style.display = 'block';
    vendorNew.focus();
  } else {
    vendorNew.style.display = 'none';
    vendorNew.value = '';
  }
});
vendorNew.addEventListener('input', () => {
  // mirror the typed vendor into the actual submitted field name
});
document.getElementById('itemForm').addEventListener('submit', () => {
  if (vendorSelect.value === '__new__' && vendorNew.value.trim() !== '') {
    vendorSelect.insertAdjacentHTML('beforeend', `<option value="${vendorNew.value.trim()}" selected></option>`);
    vendorSelect.value = vendorNew.value.trim();
  }
});

// Live table search
const searchInput = document.getElementById('tableSearch');
if (searchInput) {
  searchInput.addEventListener('input', () => {
    const q = searchInput.value.trim().toLowerCase();
    document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
      row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
  });
}
</script>
</body>
</html>