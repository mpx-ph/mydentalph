<?php
/**
 * Walk-in booking API — same auth + tenant rules as clinic/api/patients.php.
 * POST JSON body. Session cookie must be sent (fetch credentials: 'include').
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointment_db_tables.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST with JSON body.']);
    exit;
}

// Same tenant source as other staff APIs (session), not a guessed slug.
$tenantId = requireClinicTenantId();

if (!isLoggedIn(['manager', 'doctor', 'staff', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff login required to create walk-in appointments.']);
    exit;
}

define('CLINIC_WALKIN_RESOLVED_TENANT_ID', $tenantId);

require __DIR__ . '/../includes/staff_walkin_create_handler.php';
