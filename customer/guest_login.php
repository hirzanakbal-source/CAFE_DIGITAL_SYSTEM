<?php
session_start();

// ===== SET GUEST SESSION =====
$_SESSION['guest']      = true;
$_SESSION['name']       = 'Guest';
$_SESSION['guest_cart'] = $_SESSION['guest_cart'] ?? [];

// ===== REMOVE CUSTOMER SESSION =====
unset($_SESSION['customer_id']);
unset($_SESSION['email']);
unset($_SESSION['coupon_shown']);

// ===== REDIRECT TO MENU =====
header('Location: menu.php');
exit;
?>
