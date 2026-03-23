<?php
/**
 * Tenant bootstrap for public clinic pages.
 *
 * Resolves the current tenant based on the clinic slug in the URL.
 * Expected usage (with web server rewrite rules):
 *   /{clinic_slug}/              -> clinic/MainPageClient.php?clinic_slug={slug}
 *   /{clinic_slug}/about         -> clinic/AboutUsClient.php?clinic_slug={slug}
 *   /{clinic_slug}/contact       -> clinic/ContactUsClient.php?clinic_slug={slug}
 *   /{clinic_slug}/register      -> clinic/RegisterClient.php?clinic_slug={slug}
 *   /{clinic_slug}/services      -> clinic/ServicesClient.php?clinic_slug={slug}
 *   /{clinic_slug}/download      -> clinic/DownloadApp.php?clinic_slug={slug}
 *
 * After including this file, the following variables are available:
 *   - $currentTenantId
 *   - $currentTenantSlug
 *   - $currentTenantData (full row from tbl_tenants)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use same DB as config (getDBConnection from config/database.php) so one connection path
if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/config/config.php';
}
try {
    $pdo = getDBConnection();
} catch (Throwable $e) {
    error_log('Tenant bootstrap DB: ' . $e->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Database error. Please check your database connection.');
}

$clinic_slug = $_GET['clinic_slug'] ?? '';
$clinic_slug = strtolower(trim($clinic_slug));

// Basic slug validation – keep in sync with ProviderClinicSetup.php
if ($clinic_slug === '' || !preg_match('/^[a-z0-9\-]+$/', $clinic_slug)) {
    http_response_code(404);
    exit('Clinic not found.');
}

$stmt = $pdo->prepare("
    SELECT tenant_id, clinic_name, clinic_slug, contact_email, contact_phone, clinic_address
    FROM tbl_tenants
    WHERE clinic_slug = ? AND (subscription_status IS NULL OR subscription_status = 'active')
    LIMIT 1
");
$stmt->execute([$clinic_slug]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    http_response_code(404);
    exit('Clinic not found.');
}

$currentTenantId   = $tenant['tenant_id'];
$currentTenantSlug = $tenant['clinic_slug'];
$currentTenantData = $tenant;

// Store in session for downstream use (e.g. APIs that rely on session tenant_id)
$_SESSION['public_tenant_id']   = $currentTenantId;
$_SESSION['public_tenant_slug'] = $currentTenantSlug;

/**
 * Log one anonymous website visit per request for public (patient-facing) pages only.
 * Superadmin reports count these — not logins.
 */
$__tb_script = basename(isset($_SERVER['SCRIPT_FILENAME']) ? (string) $_SERVER['SCRIPT_FILENAME'] : (isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : ''));
$__tb_publicScripts = [
    'MainPageClient.php',
    'AboutUsClient.php',
    'ContactUsClient.php',
    'RegisterClient.php',
    'Login.php',
    'DownloadApp.php',
];
if (in_array($__tb_script, $__tb_publicScripts, true)) {
    try {
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim((string) explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = trim((string) $_SERVER['REMOTE_ADDR']);
        }
        $path = isset($_SERVER['REQUEST_URI']) ? substr((string) $_SERVER['REQUEST_URI'], 0, 512) : null;
        $stmt = $pdo->prepare('INSERT INTO tbl_website_visits (tenant_id, ip_address, visit_path) VALUES (?, ?, ?)');
        $stmt->execute([$currentTenantId, $ip !== '' ? $ip : null, $path]);
    } catch (Throwable $e) {
        // Table may not exist until migration 005 is applied; never break the page.
        error_log('tbl_website_visits insert: ' . $e->getMessage());
    }
}
unset($__tb_script, $__tb_publicScripts);

