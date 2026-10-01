<?php
require 'db.php';

// ===== BUILD BASE URL =====
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$scriptDir = rtrim($scriptDir, '/');
$baseUrl   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
              ? 'https' : 'http')
           . '://' . $_SERVER['HTTP_HOST']
           . $scriptDir . '/';

$scriptFolder = rtrim(
    str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'])),
    '/'
);

echo "<h2>🔍 Fix Verification</h2><hr>";
echo "<b>Base URL:</b><br>";
echo htmlspecialchars($baseUrl) . "<br><br>";
echo "<b>Script Folder:</b><br>";
echo htmlspecialchars($scriptFolder) . "<br><br>";

// ===== TEST IMAGES =====
$stmt  = $pdo->query("
    SELECT menu_id, menu_name, file_path
    FROM MENU
    WHERE file_path IS NOT NULL
    AND file_path != ''
");
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($items as $item) {
    $fp  = trim($item['file_path']);
    $ext = strtolower(pathinfo($fp, PATHINFO_EXTENSION));

    echo "<div style='border:1px solid #eee; padding:10px;
          margin:10px 0; border-radius:8px;'>";
    echo "<b>" . htmlspecialchars($item['menu_name']) . "</b><br>";
    echo "File: " . htmlspecialchars($fp) . "<br>";
    echo "Extension: " . $ext . "<br>";

    $allowedExt = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowedExt)) {
        echo "<span style='color:red;'>
              ❌ NOT AN IMAGE - Upload PNG/JPG instead
              </span><br>";
    } else {
        $sp = $scriptFolder . '/' . ltrim($fp, '/');
        if (file_exists($sp)) {
            $parts   = explode('/', ltrim($fp, '/'));
            $encoded = array_map('rawurlencode', $parts);
            $url     = $baseUrl . implode('/', $encoded);

            echo "<span style='color:green;'>✅ Image found!</span><br>";
            echo "URL: <a href='" . $url . "' target='_blank'>"
               . htmlspecialchars($url) . "</a><br>";
            echo "<img src='" . $url . "'
                  style='width:100px; height:100px;
                         object-fit:cover; border-radius:8px;
                         margin-top:8px;'
                  onerror=\"this.style.display='none';
                  this.insertAdjacentHTML('afterend',
                  '<span style=color:red>❌ Image failed to load</span>')\">";
        } else {
            echo "<span style='color:red;'>
                  ❌ File not found on server
                  </span>";
        }
    }
    echo "</div>";
}
?>