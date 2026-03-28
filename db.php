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
    // Always expose connection globally: db.php is sometimes require_once from inside a function
    // (e.g. provider_require_approved_for_provider_portal), where a plain $pdo would be function-local only.
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
