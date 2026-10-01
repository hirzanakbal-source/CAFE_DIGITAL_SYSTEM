<?php
require __DIR__ . '/../db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_id    = $_SESSION['admin_id'];
$date_from   = $_POST['date_from']   ?? date('Y-m-01');
$date_to     = $_POST['date_to']     ?? date('Y-m-d');
$total_sales = $_POST['total_sales'] ?? 0;

try {
       $stmt = $pdo->prepare("
        INSERT INTO sales_report
        (admin_id, report_date, total_sales)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([
        $admin_id,
        date('Y-m-d'),
        $total_sales
    ]);

    // Redirect back with success message
    header("Location: admin_reports.php?saved=1&date_from=$date_from&date_to=$date_to");
    exit;

} catch (PDOException $e) {
    header("Location: admin_reports.php?error=1&date_from=$date_from&date_to=$date_to");
    exit;
}
?>