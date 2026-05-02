<?php
// add_dependent_patient.php — mobile: create a dependent as a new tbl_patients row (no login account)

// Capture stray output from includes/warnings so the client always gets pure JSON.
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

$userId   = isset($input['user_id']) ? trim((string) $input['user_id']) : '';
$tenantId = isset($input['tenant_id']) ? trim((string) $input['tenant_id']) : '';

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
        api_json_exit(false, 'Only patient accounts can add dependents');
    }

    $guardianPatient = api_profile_fetch_patient($pdo, $userId, $tenantId);
    $contactFallback = $guardianPatient ? trim((string) ($guardianPatient['contact_number'] ?? '')) : '';
    if ($contactFallback === '') {
        $contactFallback = trim((string) ($user['phone'] ?? ''));
    }
    $contactIn = trim((string) ($input['contact_number'] ?? $input['phone'] ?? ''));
    $contact   = $contactIn !== '' ? $contactIn : $contactFallback;

    $emailIn = trim((string) ($input['email'] ?? ''));
    if (strtolower($emailIn) === 'null') {
        $emailIn = '';
    }
    $emailFallback = trim((string) ($user['email'] ?? ''));
    if (strtolower($emailFallback) === 'null') {
        $emailFallback = '';
    }
    $email = $emailIn !== '' ? $emailIn : $emailFallback;

    $fnRaw = trim((string) ($input['first_name'] ?? ''));
    $mn    = trim((string) ($input['middle_name'] ?? ''));
    $ln    = trim((string) ($input['last_name'] ?? ''));
    $dob   = trim((string) ($input['date_of_birth'] ?? ''));
    $genIn = $input['gender'] ?? $input['sex'] ?? '';
    $bt    = trim((string) ($input['blood_type'] ?? ''));
    $pr    = trim((string) ($input['province'] ?? ''));
    $city  = trim((string) ($input['city_municipality'] ?? $input['city'] ?? ''));
    $br    = trim((string) ($input['barangay'] ?? ''));
    $hs    = array_key_exists('house_street', $input)
        ? trim((string) $input['house_street'])
        : trim((string) ($input['street_address'] ?? ''));

    $fn = $fnRaw;
    if ($mn !== '') {
        $fn = trim($fnRaw . ' ' . $mn);
    }

    if ($fn === '' || $ln === '') {
        api_json_exit(false, 'First name and last name are required.');
    }
    if ($dob === '') {
        api_json_exit(false, 'Date of birth is required.');
    }
    $dt = \DateTime::createFromFormat('Y-m-d', $dob);
    if (!$dt || $dt->format('Y-m-d') !== $dob) {
        api_json_exit(false, 'date_of_birth must be YYYY-MM-DD');
    }
    if ($dt > new \DateTime('today')) {
        api_json_exit(false, 'Date of birth cannot be in the future.');
    }

    $g = api_profile_normalize_gender($genIn);
    if ($g === null || $g === 'INVALID') {
        api_json_exit(false, 'Invalid gender');
    }
    if ($g === 'EMPTY') {
        api_json_exit(false, 'Gender is required.');
    }
    $gen = $g;

    if ($pr === '' || $city === '' || $br === '' || $hs === '') {
        api_json_exit(false, 'Province, city/municipality, barangay, and street address are required.');
    }

    api_profile_refuse_address_json_blob('province', $pr);
    api_profile_refuse_address_json_blob('city_municipality', $city);
    api_profile_refuse_address_json_blob('barangay', $br);
    api_profile_refuse_address_json_blob('house_street', $hs);

    $newPid = api_profile_generate_patient_id($pdo);

    $pdo->beginTransaction();
    $stI = $pdo->prepare(
        "INSERT INTO tbl_patients (
            tenant_id, patient_id, owner_user_id, linked_user_id,
            first_name, last_name, contact_number, email, date_of_birth, gender, blood_type,
            house_street, barangay, city_municipality, province,
            created_at, updated_at
        ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    $stI->execute([
        $tenantId,
        $newPid,
        $userId,
        $fn,
        $ln,
        $contact !== '' ? $contact : null,
        $email !== '' ? substr($email, 0, 255) : null,
        $dob,
        $gen,
        $bt !== '' ? substr($bt, 0, 10) : null,
        $hs,
        $br,
        $city,
        $pr,
    ]);
    $pdo->commit();

    api_json_exit(true, 'Dependent saved.', [
        'patient_id' => $newPid,
    ]);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_json_exit(false, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_json_exit(false, 'Error: ' . $e->getMessage());
}
