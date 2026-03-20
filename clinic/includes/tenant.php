<?php
/**
 * Tenant helpers for anything under /clinic.
 *
 * Rule:
 * - Every request under /clinic must operate within exactly one tenant_id.
 * - Admin/staff flows: tenant_id comes from provider session (SSO) -> $_SESSION['tenant_id']
 * - Public (slug) flows: tenant_id comes from tenant_bootstrap -> $_SESSION['public_tenant_id']
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get the tenant_id for this clinic request (admin or public).
 * @return string|null
 */
function getClinicTenantId() {
    if (!empty($_SESSION['tenant_id'])) return (string) $_SESSION['tenant_id'];
    if (!empty($_SESSION['public_tenant_id'])) return (string) $_SESSION['public_tenant_id'];
    return null;
}

/**
 * Get the clinic_slug for the current request.
 * - Public pages: comes from tenant_bootstrap via $_SESSION['public_tenant_slug']
 * - Admin pages: derive from tenant_id and cache in $_SESSION['tenant_slug']
 * @return string|null
 */
function getClinicTenantSlug() {
    if (!empty($_SESSION['tenant_slug']) && preg_match('/^[a-z0-9\-]+$/', (string) $_SESSION['tenant_slug'])) {
        return (string) $_SESSION['tenant_slug'];
    }
    if (!empty($_SESSION['public_tenant_slug']) && preg_match('/^[a-z0-9\-]+$/', (string) $_SESSION['public_tenant_slug'])) {
        return (string) $_SESSION['public_tenant_slug'];
    }

    $tenantId = !empty($_SESSION['tenant_id']) ? (string) $_SESSION['tenant_id'] : '';
    if ($tenantId === '') return null;

    try {
        if (!function_exists('getDBConnection')) {
            require_once __DIR__ . '/../config/config.php';
        }
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT clinic_slug FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $slug = isset($row['clinic_slug']) ? strtolower(trim((string) $row['clinic_slug'])) : '';
        if ($slug !== '' && preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $_SESSION['tenant_slug'] = $slug;
            return $slug;
        }
    } catch (Throwable $e) {
        error_log('getClinicTenantSlug: ' . $e->getMessage());
    }
    return null;
}

/**
 * Build a tenant-slug URL for a clinic page (Admin*.php, Dentist_Dashboard.php, etc).
 * Falls back to BASE_URL when no slug is available.
 * @param string $pageFile e.g. 'AdminDashboard.php'
 * @return string
 */
function clinicPageUrl($pageFile) {
    $pageFile = ltrim((string) $pageFile, '/');
    $slug = getClinicTenantSlug();
    if ($slug !== null && defined('PROVIDER_BASE_URL')) {
        return rtrim(PROVIDER_BASE_URL, '/') . '/' . rawurlencode($slug) . '/' . $pageFile;
    }
    if (defined('BASE_URL')) return BASE_URL . $pageFile;
    return '/' . $pageFile;
}

/**
 * Require a tenant_id in session. For API requests, return JSON 401.
 * For page requests, redirect to provider SSO to establish tenant context.
 * @return string tenant_id
 */
function requireClinicTenantId() {
    $tenantId = getClinicTenantId();
    if (!empty($tenantId)) return $tenantId;

    $isApi = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/clinic/api/') !== false)
        || (strpos($_SERVER['REQUEST_URI'] ?? '', '/clinic/api/') !== false);

    if ($isApi) {
        if (function_exists('jsonResponse')) {
            jsonResponse(false, 'Tenant context missing. Please log in again.', ['code' => 'TENANT_MISSING']);
        }
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tenant context missing. Please log in again.', 'data' => ['code' => 'TENANT_MISSING']]);
        exit;
    }

    // For pages: bounce to SSO so provider can set tenant_id in session
    if (defined('BASE_URL')) {
        header('Location: ' . BASE_URL . 'ProviderMyDentalSSO.php');
        exit;
    }
    http_response_code(401);
    exit('Tenant context missing.');
}

