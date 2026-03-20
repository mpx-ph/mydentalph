<?php
/**
 * Database for clinic template - uses project root db.php only.
 * Exposes getDBConnection() so the rest of clinictemplate can use the same $pdo.
 */

$possiblePaths = [
    dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'db.php',  // project root from clinic/config
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'db.php',
    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db.php',           // clinic/db.php fallback
];
foreach ($possiblePaths as $dbPath) {
    if (is_file($dbPath)) {
        require_once $dbPath;
        break;
    }
}

/**
 * Get PDO database connection (the one from db.php)
 * @return PDO
 */
function getDBConnection() {
    global $pdo;
    if (empty($pdo)) {
        $fallbacks = [
            dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'db.php',  // project root
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db.php',  // clinic/db.php
        ];
        foreach ($fallbacks as $fallback) {
            if (is_file($fallback)) {
                require_once $fallback;
                break;
            }
        }
    }
    if (empty($pdo)) {
        throw new RuntimeException('Database not available. Ensure db.php exists at project root and connection succeeds.');
    }
    return $pdo;
}
