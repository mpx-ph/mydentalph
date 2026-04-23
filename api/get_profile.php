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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_json_exit(false, 'GET required');
}

$userId   = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
$tenantId = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : '';

if ($userId === '' || $tenantId === '') {
    api_json_exit(false, 'Missing user_id or tenant_id');
}

try {
    $user = api_profile_fetch_user($pdo, $userId, $tenantId);
    if (!$user) {
        api_json_exit(false, 'User not found for this tenant');
    }
    if (strtolower((string) ($user['role'] ?? '')) !== 'client') {
        api_json_exit(false, 'Profile is only available for patient accounts');
    }

    $p = api_profile_fetch_patient($pdo, $userId, $tenantId);

    $userPhoto = trim((string) ($user['photo'] ?? ''));
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

    $hs = $p ? (string) ($p['house_street'] ?? '') : '';
    $addr = $p
        ? [
            'province'            => (string) ($p['province'] ?? ''),
            'city_municipality'   => (string) ($p['city_municipality'] ?? ''),
            'barangay'            => (string) ($p['barangay'] ?? ''),
            'house_street'        => $hs,
            'street_address'      => $hs,
        ]
        : [
            'province'            => '',
            'city_municipality'  => '',
            'barangay'            => '',
            'house_street'        => '',
            'street_address'     => '',
        ];

    $profile = [
        'user_id'            => (string) $user['user_id'],
        'patient_id'         => $p && !empty($p['patient_id']) ? (string) $p['patient_id'] : null,
        'tenant_id'          => (string) $user['tenant_id'],
        'email'              => (string) ($user['email'] ?? ''),
        'username'           => (string) ($user['username'] ?? ''),
        'full_name'          => (string) ($user['full_name'] ?? ''),
        'user_photo'         => $userPhoto,
        'patient_profile_image' => $patImg,
        'profile_image'      => $patImg !== '' ? $patImg : $userPhoto,
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
