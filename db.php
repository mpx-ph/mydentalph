<?php
// db.php - InfinityFree MySQL Connection
$host = 'sql110.infinityfree.com';
$db = 'if0_41383818_mydental';
$user = 'if0_41383818';
$pass = 'SVA2Q2r2ZCTph';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
