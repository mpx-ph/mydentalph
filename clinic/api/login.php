<?php
/**
 * Login API Endpoint
 */

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Handle CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$debug = false;
try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Invalid JSON data: ' . json_last_error_msg());
    }
    
    $debug = !empty($input['debug']);
    $email = sanitize($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $userType = sanitize($input['user_type'] ?? 'client');
    $clinicSlug = isset($input['clinic_slug']) ? trim((string) $input['clinic_slug']) : '';

    if (empty($email) || empty($password)) {
        jsonResponse(false, 'Email and password are required.');
    }

    // If tenant context is missing but clinic_slug was sent, resolve and set session (e.g. direct API call or new tab)
    if (empty(getClinicTenantId()) && $clinicSlug !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($clinicSlug))) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT tenant_id, clinic_slug
            FROM tbl_tenants
            WHERE clinic_slug = ? AND (subscription_status IS NULL OR subscription_status = 'active')
            LIMIT 1
        ");
        $stmt->execute([strtolower($clinicSlug)]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            $_SESSION['public_tenant_id'] = $tenant['tenant_id'];
            $_SESSION['public_tenant_slug'] = $tenant['clinic_slug'];
        }
    }

    $result = loginUser($email, $password, $userType);

    if ($result['success']) {
        $slugOut = $clinicSlug !== '' ? strtolower($clinicSlug) : (isset($_SESSION['public_tenant_slug']) ? strtolower((string) $_SESSION['public_tenant_slug']) : '');
        $portal = isset($result['portal']) ? (string) $result['portal'] : 'patient';
        $redirectUrl = '';
        if ($portal === 'staff') {
            // Per requirement: `role=staff` goes to AdminDashboard.php; other staff-type users keep StaffDashboard.
            $role = strtolower(trim((string) ($result['user']['role'] ?? $result['user']['user_type'] ?? '')));
            $targetPage = ($role === 'staff') ? 'AdminDashboard.php' : 'StaffDashboard.php';

            if (defined('PROVIDER_BASE_URL') && $slugOut !== '' && preg_match('/^[a-z0-9\-]+$/', $slugOut)) {
                $redirectUrl = rtrim(PROVIDER_BASE_URL, '/') . '/' . rawurlencode($slugOut) . '/' . $targetPage;
            } elseif ($slugOut !== '' && preg_match('/^[a-z0-9\-]+$/', $slugOut)) {
                $redirectUrl = BASE_URL . $targetPage . '?clinic_slug=' . rawurlencode($slugOut);
            } else {
                $redirectUrl = BASE_URL . $targetPage;
            }
        } else {
            $redirectUrl = ($slugOut !== '' && preg_match('/^[a-z0-9\-]+$/', $slugOut))
                ? (BASE_URL . 'MainPageClient.php?clinic_slug=' . rawurlencode($slugOut))
                : (BASE_URL . 'MainPageClient.php');
        }

        jsonResponse(true, $result['message'], [
            'user' => [
                'id' => $result['user']['id'],
                'name' => trim($result['user']['first_name'] . ' ' . $result['user']['last_name']),
                'email' => $result['user']['email'],
                'type' => $result['user']['user_type'],
            ],
            'portal' => $portal,
            'redirect_url' => $redirectUrl,
        ]);
    } else {
        jsonResponse(false, $result['message']);
    }
} catch (PDOException $e) {
    // Database errors (including connection errors)
    error_log('Login API PDO Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    $showDetail = $debug || (defined('DB_DEBUG') && DB_DEBUG);
    $errorMsg = $showDetail
        ? ('Database error: ' . $e->getMessage())
        : 'Database error. Please check your database connection.';
    jsonResponse(false, $errorMsg);
} catch (Exception $e) {
    // Other errors
    error_log('Login API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    $showDetail = $debug || (ini_get('display_errors'));
    $errorMessage = $showDetail ? $e->getMessage() : 'An error occurred. Please try again.';
    jsonResponse(false, $errorMessage);
}

