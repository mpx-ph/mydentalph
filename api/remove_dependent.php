<?php
// remove_dependent.php — mobile: delete a dependent tbl_patients row (guardian-owned, no linked login)

if (ob_get_level() === 0) {
    ob_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/profile_common.inc.php';
require_once __DIR__ . '/request_context.inc.php';
api_send_no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json_exit(false, 'POST required');
}

$raw   = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$userId    = isset($input['user_id']) ? trim((string) $input['user_id']) : '';
$tenantId  = isset($input['tenant_id']) ? trim((string) $input['tenant_id']) : '';
$patientId = isset($input['patient_id']) ? trim((string) $input['patient_id']) : '';

if ($userId === '') {
    api_json_exit(false, 'Missing user_id');
}
if ($patientId === '') {
    api_json_exit(false, 'Missing patient_id');
}

try {
    $tenantId = api_resolve_tenant_id($pdo, $userId, $tenantId);
    if ($tenantId === null) {
        api_json_exit(false, 'Missing tenant context for this user');
    }

    $user = api_profile_fetch_user($pdo, $userId, $tenantId);
    if (!$user) {
        api_json_exit(false, 'User not found for this tenant');
    }
    if (strtolower((string) ($user['role'] ?? '')) !== 'client') {
        api_json_exit(false, 'Only patient accounts can remove dependents');
    }

    $st = $pdo->prepare(
        'SELECT id FROM tbl_patients
         WHERE tenant_id = ? AND patient_id = ? AND owner_user_id = ? AND linked_user_id IS NULL
         LIMIT 1'
    );
    $st->execute([$tenantId, $patientId, $userId]);
    $found = $st->fetch(PDO::FETCH_ASSOC);
    if (!$found) {
        api_json_exit(false, 'Dependent not found or cannot be removed.');
    }

    $internalId = (int) ($found['id'] ?? 0);
    if ($internalId <= 0) {
        api_json_exit(false, 'Invalid dependent record.');
    }

    $del = $pdo->prepare(
        'DELETE FROM tbl_patients
         WHERE id = ? AND tenant_id = ? AND owner_user_id = ? AND linked_user_id IS NULL'
    );
    $del->execute([$internalId, $tenantId, $userId]);

    if ($del->rowCount() === 0) {
        api_json_exit(false, 'Could not remove dependent.');
    }

    api_json_exit(true, 'Dependent removed.');
} catch (PDOException $e) {
    $msg = $e->getMessage();
    $code = (string) $e->getCode();
    if ($code === '23000' || stripos($msg, 'Integrity constraint') !== false || stripos($msg, 'foreign key') !== false) {
        api_json_exit(false, 'This dependent has clinic records linked and cannot be deleted.');
    }
    api_json_exit(false, 'Error: ' . $msg);
} catch (Throwable $e) {
    api_json_exit(false, 'Error: ' . $e->getMessage());
}
