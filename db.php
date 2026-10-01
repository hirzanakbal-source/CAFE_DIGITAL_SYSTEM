<?php
/**
 * ====================================================================
 * Cafe Digital - Database Configuration
 * ====================================================================
 */
// ===== TIMEZONE SETTINGS =====
date_default_timezone_set('Asia/Kuala_Lumpur');

// ===== DATABASE CONNECTION SETTINGS =====
$host = 'localhost';
$user = 'root';
$pass = '';
$name = 'cafe_digital_system';

// ===== CREATE DATABASE CONNECTION =====
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die('Database connection failed.');
}


// ===== SESSION CONFIGURATION =====
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
        ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// ===== ADMIN INACTIVITY TIMEOUT =====
$admin_timeout = 900; // seconds — 900 = 15 minutes

if (isset($_SESSION['admin_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $admin_timeout) {
        session_unset();
        session_destroy();
        header("Location: admin_login.php?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

?>
