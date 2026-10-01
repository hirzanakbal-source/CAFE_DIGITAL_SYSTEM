<?php
session_start();
require __DIR__ . '/db.php';

if (!isset($_SESSION['customer_id']) && !isset($_SESSION['guest'])) {
    header('Location: welcome.php');
    exit;
}

$isGuest = isset($_SESSION['guest']) && $_SESSION['guest'] === true;
$action  = $_GET['action'] ?? '';

if ($isGuest) {
    // ===== GUEST: Update SESSION cart =====
    $menu_id = intval($_GET['menu_id'] ?? 0);

    if ($menu_id > 0 && isset($_SESSION['guest_cart'])) {
        foreach ($_SESSION['guest_cart'] as $key => &$item) {
            if ($item['menu_id'] == $menu_id) {
                if ($action === 'increase') {
                    $item['quantity']++;
                } elseif ($action === 'decrease') {
                    $item['quantity']--;
                    if ($item['quantity'] <= 0) {
                        unset($_SESSION['guest_cart'][$key]);
                    }
                } elseif ($action === 'remove') {
                    unset($_SESSION['guest_cart'][$key]);
                }
                break;
            }
        }
        // Re-index array
        $_SESSION['guest_cart'] = array_values($_SESSION['guest_cart']);
    }

} else {
    // ===== LOGGED IN: Update DATABASE cart =====
    $cart_id = intval($_GET['cart_id'] ?? 0);

    if ($cart_id > 0) {
        try {
            if ($action === 'increase') {
                $stmt = $pdo->prepare("
                    UPDATE cart
                    SET quantity = quantity + 1
                    WHERE cart_id = ?
                ");
                $stmt->execute([$cart_id]);

            } elseif ($action === 'decrease') {
                                $stmt = $pdo->prepare("
                    SELECT quantity FROM cart
                    WHERE cart_id = ?
                ");
                $stmt->execute([$cart_id]);
                $current = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($current && $current['quantity'] > 1) {
                    $stmt = $pdo->prepare("
                        UPDATE cart
                        SET quantity = quantity - 1
                        WHERE cart_id = ?
                    ");
                    $stmt->execute([$cart_id]);
                } else {
                    $stmt = $pdo->prepare("
                        DELETE FROM cart
                        WHERE cart_id = ?
                    ");
                    $stmt->execute([$cart_id]);
                }

            } elseif ($action === 'remove') {
                $stmt = $pdo->prepare("
                    DELETE FROM cart
                    WHERE cart_id = ?
                ");
                $stmt->execute([$cart_id]);
            }

        } catch (PDOException $e) {
            // Handle error silently
        }
    }
}

header('Location: cart.php');
exit;