<?php
// update_profile.php — save "My Profile" to tbl_users + tbl_patients

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/profile_common.inc.php';

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

if ($userId === '' || $tenantId === '') {
    api_json_exit(false, 'Missing user_id or tenant_id');
}

/**
 * @param array<string,mixed>|null $p
 * @param array<string,mixed> $input
 * @return array{fn: string, ln: string, ph: string, dob: ?string, gen: ?string, bt: string, pr: string, city: string, br: string, hs: string}
 */
function api_profile_merge_patient_row(?array $p, array $input, array $user): array
{
    $p   = $p ?? [];
    $fn  = (string) ($p['first_name'] ?? '');
    $ln  = (string) ($p['last_name'] ?? '');
    $ph  = (string) ($p['contact_number'] ?? ($user['phone'] ?? ''));
    $dob = isset($p['date_of_birth']) && $p['date_of_birth'] ? (string) $p['date_of_birth'] : null;
    $gen = isset($p['gender']) && $p['gender'] ? (string) $p['gender'] : null;
    $bt  = (string) ($p['blood_type'] ?? '');
    $pr  = (string) ($p['province'] ?? '');
    $city = (string) ($p['city_municipality'] ?? '');
    $br  = (string) ($p['barangay'] ?? '');
    $hs  = (string) ($p['house_street'] ?? '');

    if (array_key_exists('first_name', $input)) {
        $fn = trim((string) $input['first_name']);
    }
    if (array_key_exists('last_name', $input)) {
        $ln = trim((string) $input['last_name']);
    }
    if (array_key_exists('contact_number', $input)) {
        $ph = trim((string) $input['contact_number']);
    }
    if (array_key_exists('date_of_birth', $input)) {
        $dob = trim((string) $input['date_of_birth']);
        if ($dob === '') {
            $dob = null;
        } else {
            $dt = \DateTime::createFromFormat('Y-m-d', $dob);
            if (!$dt || $dt->format('Y-m-d') !== $dob) {
                throw new InvalidArgumentException('date_of_birth must be YYYY-MM-DD or empty');
            }
        }
    }
    if (array_key_exists('gender', $input)) {
        $g = api_profile_normalize_gender($input['gender']);
        if ($g === 'INVALID') {
            throw new InvalidArgumentException('Invalid gender');
        }
        if ($g === 'EMPTY') {
            $gen = null;
        } else {
            $gen = $g;
        }
    }
    if (array_key_exists('blood_type', $input)) {
        $bt = substr(trim((string) $input['blood_type']), 0, 10);
    }
    if (array_key_exists('province', $input)) {
        $pr = trim((string) $input['province']);
    }
    if (array_key_exists('city_municipality', $input) || array_key_exists('city', $input)) {
        $city = array_key_exists('city_municipality', $input)
            ? trim((string) $input['city_municipality'])
            : trim((string) $input['city']);
    }
    if (array_key_exists('barangay', $input)) {
        $br = trim((string) $input['barangay']);
    }
    if (array_key_exists('house_street', $input) || array_key_exists('street_address', $input)) {
        $hs = array_key_exists('house_street', $input)
            ? trim((string) $input['house_street'])
            : trim((string) $input['street_address']);
    }

    return ['fn' => $fn, 'ln' => $ln, 'ph' => $ph, 'dob' => $dob, 'gen' => $gen, 'bt' => $bt, 'pr' => $pr, 'city' => $city, 'br' => $br, 'hs' => $hs];
}

$patientFieldKeys = [
    'first_name', 'last_name', 'date_of_birth', 'gender', 'contact_number', 'blood_type',
    'province', 'city_municipality', 'city', 'barangay', 'house_street', 'street_address',
];
$wantsUpdate = false;
foreach ($patientFieldKeys as $k) {
    if (array_key_exists($k, $input)) {
        $wantsUpdate = true;
        break;
    }
}
if (!$wantsUpdate) {
    api_json_exit(false, 'No profile fields to update. Send at least one of: first_name, last_name, date_of_birth, gender, contact_number, blood_type, province, city_municipality, city, barangay, house_street, street_address');
}

try {
    $user = api_profile_fetch_user($pdo, $userId, $tenantId);
    if (!$user) {
        api_json_exit(false, 'User not found for this tenant');
    }
    if (strtolower((string) ($user['role'] ?? '')) !== 'client') {
        api_json_exit(false, 'Only patient accounts can update this profile');
    }

    $p  = api_profile_fetch_patient($pdo, $userId, $tenantId);
    $m  = api_profile_merge_patient_row($p, $input, $user);
    $fn = $m['fn'];
    $ln = $m['ln'];

    $full = trim($fn . ' ' . $ln);
    if ($full === '') {
        $full = (string) ($user['full_name'] ?? 'Patient');
    }

    $pdo->beginTransaction();

    if (!$p) {
        $newPid = api_profile_generate_patient_id($pdo);
        $stI = $pdo->prepare(
            "INSERT INTO tbl_patients (
                tenant_id, patient_id, owner_user_id, linked_user_id,
                first_name, last_name, contact_number, date_of_birth, gender, blood_type,
                house_street, barangay, city_municipality, province,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
        );
        $stI->execute([
            $tenantId, $newPid, $userId, $userId,
            $fn, $ln, $m['ph'], $m['dob'], $m['gen'], $m['bt'] !== '' ? $m['bt'] : null,
            $m['hs'], $m['br'], $m['city'], $m['pr'],
        ]);
    } else {
        $stU = $pdo->prepare(
            "UPDATE tbl_patients SET
                first_name = ?,
                last_name = ?,
                contact_number = ?,
                date_of_birth = ?,
                gender = ?,
                blood_type = ?,
                house_street = ?,
                barangay = ?,
                city_municipality = ?,
                province = ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?"
        );
        $stU->execute([
            $fn, $ln, $m['ph'], $m['dob'], $m['gen'], $m['bt'] !== '' ? $m['bt'] : null,
            $m['hs'], $m['br'], $m['city'], $m['pr'],
            (int) $p['id'], $tenantId,
        ]);
    }

    $stUser = $pdo->prepare('UPDATE tbl_users SET full_name = ?, phone = ?, updated_at = NOW() WHERE user_id = ? AND tenant_id = ?');
    $stUser->execute([$full, $m['ph'], $userId, $tenantId]);

    $pdo->commit();

    $pAfter = api_profile_fetch_patient($pdo, $userId, $tenantId);
    $uAfter = api_profile_fetch_user($pdo, $userId, $tenantId);
    $lastU  = (string) ($uAfter['updated_at'] ?? '');
    $lastP  = $pAfter ? (string) ($pAfter['updated_at'] ?? '') : '';

    api_json_exit(true, 'Profile updated.', [
        'user_id'               => $userId,
        'patient_id'            => $pAfter && !empty($pAfter['patient_id']) ? (string) $pAfter['patient_id'] : null,
        'last_profile_update'  => api_profile_last_update($lastU, $lastP),
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
