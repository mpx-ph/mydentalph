<?php
/**
 * Clinic Template - Main configuration
 * Required by all clinictemplate pages. Compatible with PHP 5.6+.
 */

if (!defined('CONFIG_LOADED')) {
    define('CONFIG_LOADED', true);
}

// Show PHP errors when ?debug=1 (remove in production or restrict by IP)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    @ini_set('display_errors', 1);
    @ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Base URL: clinic web root (folder that contains MainPageClient.php, api/, etc.)
// Must NOT use dirname(SCRIPT_NAME) alone — requests from clinic/api/*.php would yield
// …/clinic/api/ and break redirects (e.g. MainPageClient.php → 404 on shared hosts).
$_s = isset($_SERVER) ? $_SERVER : array();
$protocol = (!empty($_s['HTTPS']) && $_s['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_s['HTTP_HOST']) ? (string) $_s['HTTP_HOST'] : 'localhost';
$basePath = '';
$clinicRootFs = @realpath(dirname(__DIR__));
$docRootFs = isset($_s['DOCUMENT_ROOT']) ? @realpath((string) $_s['DOCUMENT_ROOT']) : false;
if ($clinicRootFs && $docRootFs) {
    $c = rtrim(str_replace('\\', '/', $clinicRootFs), '/');
    $d = rtrim(str_replace('\\', '/', $docRootFs), '/');
    if (strpos($c, $d) === 0) {
        $tail = trim(substr($c, strlen($d)), '/');
        $basePath = $tail !== '' ? '/' . $tail : '';
    }
}
if ($basePath === '') {
    $script = isset($_s['SCRIPT_NAME']) ? (string) $_s['SCRIPT_NAME'] : '';
    $basePath = $script !== '' ? rtrim(dirname($script), '/\\') : '';
    if ($basePath === '' || $basePath === '\\' || $basePath === '.') {
        $basePath = '';
    } else {
        $basePath = '/' . trim(str_replace('\\', '/', $basePath), '/');
    }
    if ($basePath !== '' && preg_match('#/api$#', $basePath)) {
        $basePath = preg_replace('#/api$#', '', $basePath);
    }
}
define('BASE_URL', $protocol . '://' . $host . $basePath . '/');

// Provider (MyDental) root URL - for "Login using MyDental" SSO
define('PROVIDER_BASE_URL', $protocol . '://' . $host . '/');

// Site name
define('SITE_NAME', 'Dental Clinic');

// Root path (directory containing clinictemplate index files)
define('ROOT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);

// Pagination
define('ITEMS_PER_PAGE', 15);

// Upload limits (used by functions.php)
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Database: clinictemplate uses the project root db.php only (no separate DB config)
// Set to true in development to see actual PDO errors from API (e.g. login) instead of generic message
define('DB_DEBUG', false);

// Load database helper so getDBConnection() is always available to pages that use config
require_once __DIR__ . '/database.php';

// Prevent being embedded in other domains' iframes (avoids "Unsafe attempt to load URL from frame" when opener is an error page)
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
}
