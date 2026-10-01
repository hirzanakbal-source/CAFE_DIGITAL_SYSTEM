<?php
require __DIR__ . '/db.php';

// ===== CHECK SESSION =====
if (!isset($_SESSION['customer_id']) && !isset($_SESSION['guest'])) {
    header('Location: welcome.php');
    exit;
}

$isGuest    = isset($_SESSION['guest']) && $_SESSION['guest'] === true;
$menu_id    = intval($_POST['menu_id'] ?? 0);
$isAjax     = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// ===== VALIDATE QUANTITY (defaults to 1, capped 1–99) =====
$quantity = intval($_POST['quantity'] ?? 1);
if ($quantity < 1) $quantity = 1;
if ($quantity > 99) $quantity = 99;

function respondError($message, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    header('Location: menu.php');
    exit;
}

if ($menu_id <= 0) {
    respondError('Invalid item.', $isAjax);
}

// ===== SERVER-SIDE AVAILABILITY CHECK (applies to guest AND logged-in) =====
try {
    $checkStmt = $pdo->prepare("SELECT menu_id, menu_name, price, availability FROM menu WHERE menu_id = ?");
    $checkStmt->execute([$menu_id]);
    $menuItem = $checkStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    respondError('Could not verify item.', $isAjax);
}

if (!$menuItem) {
    respondError('Item not found.', $isAjax);
}

if (empty($menuItem['availability'])) {
    respondError($menuItem['menu_name'] . ' is currently sold out.', $isAjax);
}

if ($isGuest) {
    // ===== GUEST: ADD TO SESSION CART =====
    try {
        if (!isset($_SESSION['guest_cart'])) {
            $_SESSION['guest_cart'] = [];
        }

        $found = false;
        foreach ($_SESSION['guest_cart'] as &$cartItem) {
            if ($cartItem['menu_id'] == $menu_id) {
                $cartItem['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        unset($cartItem);

        if (!$found) {
            $_SESSION['guest_cart'][] = [
                'menu_id'   => $menuItem['menu_id'],
                'menu_name' => $menuItem['menu_name'],
                'price'     => $menuItem['price'],
                'quantity'  => $quantity,
            ];
        }
    } catch (Exception $e) {
        respondError('Cart error. Please try again.', $isAjax);
    }
} else {
    // ===== LOGGED IN: ADD TO DATABASE CART =====
    $customer_id = $_SESSION['customer_id'];
    try {
               $stmt = $pdo->prepare("SELECT cart_id, quantity FROM cart WHERE customer_id = ? AND menu_id = ?");
        $stmt->execute([$customer_id, $menu_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + ? WHERE cart_id = ?");
            $stmt->execute([$quantity, $existing['cart_id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cart (customer_id, menu_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$customer_id, $menu_id, $quantity]);
        }
    } catch (PDOException $e) {
        respondError('Cart error. Please try again.', $isAjax);
    }
}

// ===== RESPOND =====
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

header('Location: menu.php');
exit;