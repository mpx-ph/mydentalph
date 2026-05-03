<?php
/**
 * Staff portal: Treatment Progress modal JSON (delegates to shared resolver).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/treatment_progress_modal_lib.php';

header('Content-Type: application/json; charset=utf-8');

if ((string) ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use GET.']);
    exit;
}

$tenantId = requireClinicTenantId();
if (!isLoggedIn(['manager', 'doctor', 'staff', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff login required.']);
    exit;
}

$patientId = trim((string) ($_GET['patient_id'] ?? ''));
$treatmentId = trim((string) ($_GET['treatment_id'] ?? ''));
if ($patientId === '' || $treatmentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'patient_id and treatment_id are required.']);
    exit;
}

try {
    $pdo = getDBConnection();
    $payload = treatment_progress_modal_resolve_payload($pdo, (string) $tenantId, $patientId, $treatmentId);
    echo json_encode([
        'success' => true,
        'message' => 'Treatment progress loaded.',
        'data' => $payload,
    ]);
} catch (RuntimeException $e) {
    $code = (int) $e->getCode();
    if ($code === 404) {
        http_response_code(404);
    } elseif ($code === 503) {
        http_response_code(503);
    } else {
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('treatment_progress_modal endpoint: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load treatment progress.']);
}
