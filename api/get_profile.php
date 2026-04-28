<?php
// get_profile.php — full "My Profile" for mobile (tbl_users + tbl_patients)

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
        api_json_exit(false, 'Profile is only available for patient accounts');
    }

    $p = api_profile_fetch_patient($pdo, $userId, $tenantId);

    $patImg    = $p ? trim((string) ($p['profile_image'] ?? '')) : '';
    $phone     = trim((string) ($user['phone'] ?? ''));
    $contact   = $p ? trim((string) ($p['contact_number'] ?? '')) : '';
    if ($contact === '') {
        $contact = $phone;
    }

    $uCreated = (string) ($user['created_at'] ?? '');
    $uUpd     = (string) ($user['updated_at'] ?? '');
    $pUpd     = $p ? (string) ($p['updated_at'] ?? '') : '';

    $pObj = $p
        ? [
            'first_name'      => (string) ($p['first_name'] ?? ''),
            'last_name'       => (string) ($p['last_name'] ?? ''),
            'date_of_birth'   => $p['date_of_birth'] ? (string) $p['date_of_birth'] : '',
            'gender'          => (string) ($p['gender'] ?? ''),
            'contact_number'  => $contact,
            'blood_type'      => (string) ($p['blood_type'] ?? ''),
        ]
        : [
            'first_name'      => '',
            'last_name'       => '',
            'date_of_birth'   => '',
            'gender'          => '',
            'contact_number'  => $phone,
            'blood_type'      => '',
        ];

    // DB columns only (tbl_patients). If house_street wrongly contains a stringified JSON object, split it for the app.
    $addr = api_profile_resolve_address_for_api($p);

    $profile = [
        'user_id'            => (string) $user['user_id'],
        'patient_id'         => $p && !empty($p['patient_id']) ? (string) $p['patient_id'] : null,
        'tenant_id'          => (string) $user['tenant_id'],
        'email'              => (string) ($user['email'] ?? ''),
        'username'           => (string) ($user['username'] ?? ''),
        'full_name'          => (string) ($user['full_name'] ?? ''),
        // Backward-compatible alias; now sourced from tbl_patients.profile_image only.
        'user_photo'         => $patImg,
        'patient_profile_image' => $patImg,
        'profile_image'      => $patImg,
        'personal'           => $pObj,
        'address'            => $addr,
        'account'            => [
            'email'                 => (string) ($user['email'] ?? ''),
            'username'              => (string) ($user['username'] ?? ''),
            'phone'                 => $phone,
            'registration_date'     => $uCreated,
            'last_profile_update'   => api_profile_last_update($uUpd, $p ? $pUpd : null),
        ],
    ];

    api_json_exit(true, 'Profile loaded.', ['profile' => $profile]);
} catch (Throwable $e) {
    api_json_exit(false, 'Error: ' . $e->getMessage());
}
