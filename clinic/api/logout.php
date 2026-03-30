<?php
/**
 * Logout API Endpoint
 * Redirects to appropriate login page after logout
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Get user type and client clinic slug BEFORE destroying session
$userType = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'client';
$accountKind = isset($_SESSION['account_kind']) ? trim((string) $_SESSION['account_kind']) : '';
$userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$clientClinicSlug = isset($_SESSION['public_tenant_slug']) ? trim((string) $_SESSION['public_tenant_slug']) : '';
$adminClinicSlug = isset($_SESSION['tenant_slug']) ? trim((string) $_SESSION['tenant_slug']) : '';
if ($adminClinicSlug === '' && $clientClinicSlug !== '') {
    $adminClinicSlug = $clientClinicSlug;
}

// Record logout before session is destroyed
if ($tenantId !== '') {
    writeAuditLog(
        $tenantId,
        $userId !== '' ? $userId : null,
        'LOGOUT',
        'User logged out.'
    );
}

// Perform logout (destroys session)
logout();

// Determine redirect URL based on user type
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$origin = $protocol . '://' . $host;

// Client logout: go back to tenant root if possible; staff-portal logout: go to unified clinic login
if ($userType === 'manager' || $userType === 'admin' || $userType === 'doctor' || $userType === 'staff') {
    // Always return staff-portal users to the clinic unified login page.
    if ($accountKind === 'staff') {
        $redirectUrl = $origin . '/clinic/Login.php';
    } elseif ($adminClinicSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $adminClinicSlug)) {
        $redirectUrl = $origin . '/' . rawurlencode(strtolower($adminClinicSlug)) . '/AdminLoginPage.php';
    } else {
        // Fallback to legacy /clinic path
        $redirectUrl = $origin . '/clinic/AdminLoginPage.php';
    }
} else {
    if ($clientClinicSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $clientClinicSlug)) {
        $redirectUrl = $origin . '/' . rawurlencode(strtolower($clientClinicSlug));
    } else {
        $redirectUrl = $origin . '/clinic/MainPageClient.php';
    }
}

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    // If AJAX request, return JSON with redirect info
    jsonResponse(true, 'Logged out successfully.', [
        'redirect' => $redirectUrl
    ]);
} else {
    // If direct browser request, redirect immediately
    header('Location: ' . $redirectUrl);
    exit;
}

