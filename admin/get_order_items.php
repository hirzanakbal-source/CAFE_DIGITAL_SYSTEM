<?php
require __DIR__ . '/../db.php';
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
$order_id = intval($_GET['order_id'] ?? 0);
try {
    $stmt = $pdo->prepare("
        SELECT om.quantity,
               m.menu_name,
               om.unit_price,
               (om.quantity * om.unit_price) AS subtotal
        FROM order_menu om
        LEFT JOIN menu m ON om.menu_id = m.menu_id
        WHERE om.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStmt = $pdo->prepare("
         SELECT total_price FROM `order` WHERE order_id = ?
    ");
    $totalStmt->execute([$order_id]);
    $total = $totalStmt->fetchColumn();

    echo json_encode([
        'items' => $items,
        'total' => $total,
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>