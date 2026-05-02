<?php
// list_dependents.php — mobile: dependents = tbl_patients rows owned by user without a linked login

if (ob_get_level() === 0) {
    ob_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/profile_common.inc.php';
require_once __DIR__ . '/request_context.inc.php';
api_send_no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_json_exit(false, 'GET required');
}

$userId   = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
$tenantId = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : '';

if ($userId === '') {
    api_json_exit(false, 'Missing user_id');
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
        api_json_exit(false, 'Only patient accounts can list dependents');
    }

    $st = $pdo->prepare(
        "SELECT patient_id, first_name, last_name, COALESCE(email, '') AS email, date_of_birth, gender, blood_type
         FROM tbl_patients
         WHERE tenant_id = ?
           AND owner_user_id = ?
           AND linked_user_id IS NULL
         ORDER BY first_name ASC, last_name ASC, patient_id ASC"
    );
    $st->execute([$tenantId, $userId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $out  = [];
    foreach ($rows as $r) {
        $out[] = [
            'patient_id'    => (string) ($r['patient_id'] ?? ''),
            'first_name'    => (string) ($r['first_name'] ?? ''),
            'last_name'     => (string) ($r['last_name'] ?? ''),
            'email'         => (string) ($r['email'] ?? ''),
            'date_of_birth' => $r['date_of_birth'] ? (string) $r['date_of_birth'] : '',
            'gender'        => (string) ($r['gender'] ?? ''),
            'blood_type'    => (string) ($r['blood_type'] ?? ''),
        ];
    }

    api_json_exit(true, 'OK', ['dependents' => $out]);
} catch (Throwable $e) {
    api_json_exit(false, 'Error: ' . $e->getMessage());
}
