<?php
/**
 * Dedicated walk-in booking API (POST JSON only).
 * Does not rely on ?action=create_walkin on StaffWalkIn.php — some hosts strip query strings on POST.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/appointment_db_tables.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST with JSON body.']);
    exit;
}

require __DIR__ . '/../includes/staff_walkin_create_handler.php';
