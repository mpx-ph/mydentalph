<?php
// db.php - InfinityFree MySQL Connection
$host = 'sql102.infinityfree.com';
$db = 'if0_41436542_mydentalph';
$user = 'if0_41436542';
$pass = 'dIdY2azmN95';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    try {
        // Force MySQL session clock to Philippine time for NOW()/CURRENT_TIMESTAMP behavior.
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        // Some hosts may block SET time_zone; app-level Manila formatting still applies.
    }
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
