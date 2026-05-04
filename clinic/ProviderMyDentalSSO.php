<?php
/**
 * MyDental SSO (clinic copy): if user is logged in on provider, set session and redirect for auto-approval.
 * Session is shared on same domain so provider login state is visible here.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/config/config.php';

provider_default_session_start();

try {

    // Provider session keys (set by ProviderLogin / ProviderTenantDashboard at root)
    $loggedIn = !empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id']);

    if (!$loggedIn) {
        $redirect = urlencode('clinictemplate/ProviderMyDentalSSO.php');
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $providerUrl = (defined('PROVIDER_BASE_URL') ? PROVIDER_BASE_URL : ($protocol . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . '/'));
        header('Location: ' . $providerUrl . 'ProviderLogin.php?redirect=' . $redirect);
        exit;
    }

    $_SESSION['mydental_sso_data'] = [
        'user_id' => $_SESSION['user_id'],
        'tenant_id' => $_SESSION['tenant_id'],
        'email' => isset($_SESSION['email']) ? $_SESSION['email'] : '',
        'created' => time()
    ];

    $adminLogin = function_exists('clinicPageUrl') ? clinicPageUrl('Login.php') : (BASE_URL . 'Login.php');
    header('Location: ' . $adminLogin . '?mydental_sso=1');
    exit;
} catch (Exception $e) {
    error_log('ProviderMyDentalSSO (clinictemplate): ' . $e->getMessage());
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $base = defined('BASE_URL') ? BASE_URL : ('https://' . $host . '/' . (dirname($script) === '/' ? '' : trim(dirname($script), '/') . '/'));
    $adminLogin = function_exists('clinicPageUrl') ? clinicPageUrl('Login.php') : ($base . 'Login.php');
    header('Location: ' . $adminLogin . '?mydental_error=1');
    exit;
}
