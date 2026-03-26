<?php
/**
 * MyDental SSO: if user is logged in on provider (ProviderTenantDashboard / ProviderLogin),
 * store token in session and redirect to clinic AdminLoginPage for auto-approval.
 * Uses session only (no file write) to avoid permission issues on shared hosting.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/provider_auth.php';
provider_require_approved_for_provider_portal();

// Not logged in as provider/tenant -> send to provider login, then back here
if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    $redirect = urlencode('ProviderMyDentalSSO.php');
    header('Location: ProviderLogin.php?redirect=' . $redirect);
    exit;
}

// Store SSO data in session (same domain/path so clinic request will have this session)
$_SESSION['mydental_sso_data'] = [
    'user_id' => $_SESSION['user_id'],
    'tenant_id' => $_SESSION['tenant_id'],
    'email' => $_SESSION['email'] ?? '',
    'created' => time()
];

// Redirect to clinic admin login
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$clinicBase = '/clinictemplate/';
$clinicLoginUrl = $protocol . '://' . $host . $clinicBase . 'AdminLoginPage.php?mydental_sso=1';
header('Location: ' . $clinicLoginUrl);
exit;
