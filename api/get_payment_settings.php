<?php

declare(strict_types=1);

/**
 * Public read of tbl_payment_settings for mobile booking/checkout (downpayment rules).
 * Mirrors clinic StaffPaymentSetting.php / tbl_payment_settings.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'GET required']);
    exit;
}

$tenant_id = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : '';

if ($tenant_id === '') {
    echo json_encode(['success' => false, 'message' => 'tenant_id is required']);
    exit;
}

try {
    $regular = 20.0;
    $long_term_min = 500.0;

    $stmt = $pdo->prepare(
        'SELECT regular_downpayment_percentage, long_term_min_downpayment
         FROM tbl_payment_settings
         WHERE tenant_id = ?
         LIMIT 1'
    );
    $stmt->execute([$tenant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $regular = (float) $row['regular_downpayment_percentage'];
        $long_term_min = (float) $row['long_term_min_downpayment'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'regular_downpayment_percentage' => round(max(0.0, min(100.0, $regular)), 4),
            'long_term_min_downpayment' => round(max(0.0, $long_term_min), 2),
        ],
    ]);
} catch (Throwable $e) {
    error_log('get_payment_settings.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load payment settings.',
    ]);
}
