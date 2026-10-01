<?php
// db.php - Database connection file
date_default_timezone_set('Asia/Kuala_Lumpur');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db = 'cafe_digital_system';
$user = 'root';
$pass = ''; // your DB password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("DB Connection failed: " . $e->getMessage());
    die("A system error occurred. Please try again later.");
}