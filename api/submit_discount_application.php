<?php
// submit_discount_application.php — patient submits a discount verification request (same tbl as staff portal)

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
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

$raw = (string) file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    api_json_exit(false, 'Invalid JSON');
}

$userId = trim((string) ($input['user_id'] ?? ''));
$tenantId = trim((string) ($input['tenant_id'] ?? ''));
$programId = isset($input['discount_program_id']) ? (int) $input['discount_program_id'] : 0;

if ($userId === '') {
    api_json_exit(false, 'Missing user_id');
}
if ($programId <= 0) {
    api_json_exit(false, 'Invalid discount program');
}

function patient_proof_upload_relative(string $tenantId, int $verificationId, string $dataUrlOrBase64): ?string
{
    $rawBin = null;
    $ext = 'jpg';
    if (preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,#i', $dataUrlOrBase64, $m)) {
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $b64 = preg_replace('#^data:image/[^;]+;base64,#', '', $dataUrlOrBase64);
        $rawBin = base64_decode($b64, true);
    } else {
        $rawBin = base64_decode($dataUrlOrBase64, true);
    }
    if ($rawBin === false || $rawBin === '') {
        return null;
    }
    $max = 5 * 1024 * 1024;
    if (strlen($rawBin) > $max) {
        return null;
    }

    $safeTenant = preg_replace('/[^A-Za-z0-9_\-]/', '_', $tenantId);
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'clinic' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'discount_verifications' . DIRECTORY_SEPARATOR . $safeTenant;
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
    }

    $name = 'dv_' . $verificationId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $full = $dir . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($full, $rawBin) === false) {
        return null;
    }

    return 'uploads/discount_verifications/' . $safeTenant . '/' . $name;
}

function submit_map_verification(array $row): array
{
    $rel = isset($row['proof_image_path']) ? (string) $row['proof_image_path'] : '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    $proofUrl = '';
    if ($rel !== '' && $host !== '') {
        $proofUrl = $scheme . '://' . $host . '/clinic/' . ltrim(str_replace('\\', '/', $rel), '/');
    }

    return [
        'id' => (string) (int) $row['discount_verification_id'],
        'programId' => (string) (int) $row['discount_program_id'],
        'programName' => (string) $row['program_name_snapshot'],
        'status' => (string) $row['status'],
        'dateApplied' => (string) $row['date_applied'],
        'proofImageUrl' => $proofUrl,
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
        api_json_exit(false, 'Only patient accounts may submit applications');
    }

    $patient = api_profile_fetch_patient($pdo, $userId, $tenantId);
    if (!$patient || empty($patient['patient_id'])) {
        api_json_exit(false, 'Complete your patient profile before applying for a discount.');
    }

    $patientId = trim((string) $patient['patient_id']);
    $fn = trim((string) ($patient['first_name'] ?? ''));
    $ln = trim((string) ($patient['last_name'] ?? ''));
    $patientName = trim($fn . ' ' . $ln);
    if ($patientName === '') {
        $patientName = trim((string) ($user['full_name'] ?? ''));
    }
    if ($patientName === '') {
        api_json_exit(false, 'Patient name is missing from profile.');
    }

    $pStmt = $pdo->prepare(
        'SELECT * FROM tbl_discount_programs WHERE discount_program_id = ? AND tenant_id = ? LIMIT 1'
    );
    $pStmt->execute([$programId, $tenantId]);
    $prog = $pStmt->fetch(PDO::FETCH_ASSOC);
    if (!$prog) {
        api_json_exit(false, 'Discount program not found.');
    }
    if (empty($prog['enabled'])) {
        api_json_exit(false, 'This discount program is not available.');
    }

    $vf = $prog['valid_from'];
    $vt = $prog['valid_to'];
    if ($vf !== null && (string) $vf !== '' && (string) $vf > date('Y-m-d')) {
        api_json_exit(false, 'This program is not yet active.');
    }
    if ($vt !== null && (string) $vt !== '' && (string) $vt < date('Y-m-d')) {
        api_json_exit(false, 'This program has expired.');
    }

    $applicationNotes = trim((string) ($input['application_notes'] ?? ''));
    $idNumber = trim((string) ($input['id_number'] ?? ''));
    $dateApplied = trim((string) ($input['date_applied'] ?? ''));
    if ($dateApplied === '') {
        $dateApplied = date('Y-m-d');
    }

    $proofIn = isset($input['proof_image_base64']) ? (string) $input['proof_image_base64'] : '';

    $reqProof = !empty($prog['req_upload_proof']);
    $reqNotes = !empty($prog['req_notes']);

    if ($reqProof && $proofIn === '') {
        api_json_exit(false, 'Proof image is required for this program.');
    }
    if ($reqNotes && $applicationNotes === '') {
        api_json_exit(false, 'Notes are required for this program.');
    }

    $snapshotName = (string) $prog['name'];

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO tbl_discount_verifications (
                tenant_id, discount_program_id, program_name_snapshot,
                req_upload_proof, req_notes,
                patient_name, patient_ref, id_number, proof_image_path, application_notes,
                date_applied, staff_assigned_user_id, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, NULL, \'pending\')'
        );
        $ins->execute([
            $tenantId,
            $programId,
            $snapshotName,
            !empty($prog['req_upload_proof']) ? 1 : 0,
            !empty($prog['req_notes']) ? 1 : 0,
            $patientName,
            $patientId,
            $idNumber !== '' ? $idNumber : null,
            $applicationNotes !== '' ? $applicationNotes : null,
            $dateApplied,
        ]);
        $vid = (int) $pdo->lastInsertId();

        if ($proofIn !== '') {
            $relPath = patient_proof_upload_relative($tenantId, $vid, $proofIn);
            if ($relPath === null) {
                $pdo->rollBack();
                api_json_exit(false, 'Could not save proof image. Check format and size (max 5 MB).');
            }
            $up = $pdo->prepare(
                'UPDATE tbl_discount_verifications SET proof_image_path = ? WHERE discount_verification_id = ? AND tenant_id = ?'
            );
            $up->execute([$relPath, $vid, $tenantId]);
        }

        $pdo->commit();

        $sel = $pdo->prepare(
            'SELECT v.* FROM tbl_discount_verifications v WHERE v.discount_verification_id = ? AND v.tenant_id = ? LIMIT 1'
        );
        $sel->execute([$vid, $tenantId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            api_json_exit(false, 'Failed to load verification.');
        }
        api_json_exit(true, 'Application submitted.', ['verification' => submit_map_verification($row)]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Throwable $e) {
    api_json_exit(false, 'Error: ' . $e->getMessage());
}
