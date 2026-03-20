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

// Base URL: auto-detect from request so it works on any domain (e.g. mydental.ct.ws)
$_s = isset($_SERVER) ? $_SERVER : array();
$protocol = (!empty($_s['HTTPS']) && $_s['HTTPS'] !== 'off') ? 'https' : 'http';
$host = isset($_s['HTTP_HOST']) ? (string) $_s['HTTP_HOST'] : 'localhost';
$script = isset($_s['SCRIPT_NAME']) ? (string) $_s['SCRIPT_NAME'] : '';
$basePath = $script !== '' ? rtrim(dirname($script), '/\\') : '';
if ($basePath === '' || $basePath === '\\' || $basePath === '.') {
    $basePath = '';
} else {
    $basePath = '/' . trim(str_replace('\\', '/', $basePath), '/');
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
