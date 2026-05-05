<?php
/**
 * Patient mobile: same Treatment Progress payload as staff modal (authenticated patient user).
 */
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/request_context.inc.php';
require_once __DIR__ . '/../clinic/includes/treatment_progress_modal_lib.php';

header('Content-Type: application/json; charset=utf-8');
api_send_no_cache_headers();

if ((string) ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use GET.']);
    exit;
}

$userId = trim((string) ($_GET['user_id'] ?? ''));
$treatmentId = trim((string) ($_GET['treatment_id'] ?? ''));
$tenantHint = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : '';
$patientIdHint = isset($_GET['patient_id']) ? trim((string) $_GET['patient_id']) : '';

if ($userId === '' || $treatmentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'user_id and treatment_id are required.']);
    exit;
}

/** @var PDO|null $pdo */
$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database unavailable.']);
    exit;
}

try {
    $tenantId = api_resolve_tenant_id($pdo, $userId, $tenantHint);
    if ($tenantId === null || $tenantId === '') {
        echo json_encode(['success' => false, 'message' => 'Missing tenant context for this user']);
        exit;
    }

    // Resolve patient from the treatment row (authoritative). The previous LIMIT 1 on tbl_patients
    // broke accounts with multiple profiles (e.g. guardian + dependents): wrong patient → 404.
    $tStmt = $pdo->prepare(
        'SELECT patient_id FROM tbl_treatments WHERE tenant_id = ? AND treatment_id = ? LIMIT 1'
    );
    $tStmt->execute([$tenantId, $treatmentId]);
    $treatRow = $tStmt->fetch(PDO::FETCH_ASSOC);
    if (!$treatRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Treatment not found.']);
        exit;
    }

    $patientId = trim((string) ($treatRow['patient_id'] ?? ''));
    if ($patientId === '') {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Treatment has no linked patient.']);
        exit;
    }

    if ($patientIdHint !== '' && $patientIdHint !== $patientId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'patient_id does not match this treatment.']);
        exit;
    }

    $accessStmt = $pdo->prepare(
        'SELECT 1 FROM tbl_patients
         WHERE tenant_id = ? AND patient_id = ?
           AND (owner_user_id = ? OR linked_user_id = ?)
         LIMIT 1'
    );
    $accessStmt->execute([$tenantId, $patientId, $userId, $userId]);
    if (!$accessStmt->fetchColumn()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Not allowed to view this treatment.']);
        exit;
    }

    $payload = treatment_progress_modal_resolve_payload($pdo, $tenantId, $patientId, $treatmentId);
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
    error_log('api/treatment_progress.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load treatment progress.']);
}
