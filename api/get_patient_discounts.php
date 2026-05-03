<?php
// get_patient_discounts.php — discount programs + this patient's verification history (mobile)

declare(strict_types=1);

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

$userId = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
$tenantId = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : '';

if ($userId === '') {
    api_json_exit(false, 'Missing user_id');
}

/**
 * @return list<string>
 */
function patient_discount_program_service_ids(PDO $pdo, string $tenantId, int $programId): array
{
    $svcStmt = $pdo->prepare(
        'SELECT service_id FROM tbl_discount_program_services WHERE discount_program_id = ? AND tenant_id = ? ORDER BY service_id'
    );
    $svcStmt->execute([$programId, $tenantId]);
    $serviceIds = [];
    while ($s = $svcStmt->fetch(PDO::FETCH_ASSOC)) {
        $serviceIds[] = (string) $s['service_id'];
    }
    return $serviceIds;
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function map_patient_program_row(PDO $pdo, string $tenantId, array $row): array
{
    $ageMin = null;
    $ageMax = null;
    if (array_key_exists('age_min', $row) && $row['age_min'] !== null && $row['age_min'] !== '') {
        $ageMin = (int) $row['age_min'];
    }
    if (array_key_exists('age_max', $row) && $row['age_max'] !== null && $row['age_max'] !== '') {
        $ageMax = (int) $row['age_max'];
    }

    $pid = (int) $row['discount_program_id'];

    return [
        'id' => (string) $pid,
        'name' => (string) $row['name'],
        'discountType' => (string) $row['discount_type'],
        'value' => (float) $row['value'],
        'minSpend' => (float) $row['min_spend'],
        'ageMin' => $ageMin,
        'ageMax' => $ageMax,
        'reqUploadProof' => !empty($row['req_upload_proof']),
        'reqNotes' => !empty($row['req_notes']),
        'validFrom' => $row['valid_from'] !== null ? (string) $row['valid_from'] : '',
        'validTo' => $row['valid_to'] !== null ? (string) $row['valid_to'] : '',
        'serviceScope' => (string) $row['service_scope'],
        'stacking' => (string) $row['stacking'],
        'serviceIds' => patient_discount_program_service_ids($pdo, $tenantId, $pid),
    ];
}

/**
 * Build public URL for proof files stored under clinic/uploads (same layout as staff portal).
 */
function patient_discount_proof_url(?string $relativePath): string
{
    if ($relativePath === null || trim($relativePath) === '') {
        return '';
    }
    $p = str_replace('\\', '/', $relativePath);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    if ($host === '') {
        return $p;
    }
    $clinicPrefix = '/clinic';
    return $scheme . '://' . $host . $clinicPrefix . '/' . ltrim($p, '/');
}

/**
 * @param array<string,mixed> $row
 * @return array<string,mixed>
 */
function map_patient_verification_row(array $row): array
{
    $rel = isset($row['proof_image_path']) ? (string) $row['proof_image_path'] : '';

    return [
        'id' => (string) (int) $row['discount_verification_id'],
        'programId' => (string) (int) $row['discount_program_id'],
        'programName' => (string) $row['program_name_snapshot'],
        'patientName' => (string) $row['patient_name'],
        'idNumber' => $row['id_number'] !== null ? (string) $row['id_number'] : '',
        'proofImageUrl' => patient_discount_proof_url($rel !== '' ? $rel : null),
        'applicationNotes' => $row['application_notes'] !== null ? (string) $row['application_notes'] : '',
        'dateApplied' => (string) $row['date_applied'],
        'staffAssigned' => isset($row['staff_assigned_name']) && $row['staff_assigned_name'] !== null
            ? (string) $row['staff_assigned_name']
            : '',
        'status' => (string) $row['status'],
        'approvedBy' => isset($row['approved_by_name']) && $row['approved_by_name'] !== null
            ? (string) $row['approved_by_name']
            : '',
        'remarks' => $row['remarks'] !== null ? (string) $row['remarks'] : '',
        'programValidTo' => isset($row['program_valid_to']) && $row['program_valid_to'] !== null
            ? (string) $row['program_valid_to']
            : '',
        'programMinSpend' => isset($row['program_min_spend']) ? (float) $row['program_min_spend'] : 0.0,
    ];
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
        api_json_exit(false, 'Discounts are only available for patient accounts');
    }

    $patient = api_profile_fetch_patient($pdo, $userId, $tenantId);
    $patientId = $patient && !empty($patient['patient_id']) ? trim((string) $patient['patient_id']) : '';
    $fn = $patient ? trim((string) ($patient['first_name'] ?? '')) : '';
    $ln = $patient ? trim((string) ($patient['last_name'] ?? '')) : '';
    $displayName = trim($fn . ' ' . $ln);
    if ($displayName === '') {
        $displayName = trim((string) ($user['full_name'] ?? ''));
    }

    $progStmt = $pdo->prepare(
        'SELECT * FROM tbl_discount_programs
         WHERE tenant_id = ?
           AND enabled = 1
           AND (valid_from IS NULL OR valid_from <= CURDATE())
           AND (valid_to IS NULL OR valid_to >= CURDATE())
         ORDER BY name ASC'
    );
    $progStmt->execute([$tenantId]);
    $programRows = $progStmt->fetchAll(PDO::FETCH_ASSOC);
    $programs = [];
    foreach ($programRows as $row) {
        $programs[] = map_patient_program_row($pdo, $tenantId, $row);
    }

    $verifications = [];
    $verifiedLabels = [];

    if ($patientId !== '' || $displayName !== '') {
        $vSql = '
            SELECT v.*,
                p.valid_to AS program_valid_to,
                p.min_spend AS program_min_spend,
                sa.full_name AS staff_assigned_name,
                ab.full_name AS approved_by_name
            FROM tbl_discount_verifications v
            INNER JOIN tbl_discount_programs p
                ON p.discount_program_id = v.discount_program_id AND p.tenant_id = v.tenant_id
            LEFT JOIN tbl_users sa ON sa.user_id = v.staff_assigned_user_id
            LEFT JOIN tbl_users ab ON ab.user_id = v.approved_by_user_id
            WHERE v.tenant_id = ?
        ';
        $vParams = [$tenantId];

        if ($patientId !== '') {
            if ($displayName !== '') {
                $vSql .= '
                  AND (
                        v.patient_ref = ?
                     OR (
                          (v.patient_ref IS NULL OR TRIM(v.patient_ref) = \'\')
                          AND LOWER(TRIM(v.patient_name)) = LOWER(?)
                        )
                  )
                ';
                $vParams[] = $patientId;
                $vParams[] = $displayName;
            } else {
                $vSql .= ' AND v.patient_ref = ? ';
                $vParams[] = $patientId;
            }
        } else {
            $vSql .= ' AND LOWER(TRIM(v.patient_name)) = LOWER(?) ';
            $vParams[] = $displayName;
        }

        $vSql .= ' ORDER BY v.date_applied DESC, v.discount_verification_id DESC LIMIT 100';

        $vStmt = $pdo->prepare($vSql);
        $vStmt->execute($vParams);

        $vRows = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($vRows as $row) {
            $mapped = map_patient_verification_row($row);
            $verifications[] = $mapped;
            if (($mapped['status'] ?? '') === 'approved') {
                $pn = trim((string) ($mapped['programName'] ?? ''));
                if ($pn !== '') {
                    $verifiedLabels[$pn] = true;
                }
            }
        }
    }

    $verifiedList = array_keys($verifiedLabels);
    sort($verifiedList);

    api_json_exit(true, 'OK', [
        'programs' => $programs,
        'verifications' => $verifications,
        'verified_discount_labels' => $verifiedList,
        'patient_id' => $patientId !== '' ? $patientId : null,
    ]);
} catch (Throwable $e) {
    api_json_exit(false, 'Error: ' . $e->getMessage());
}
