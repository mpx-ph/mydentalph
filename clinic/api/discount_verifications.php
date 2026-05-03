<?php
/**
 * Discount verification applications (list, create, review).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDBConnection();
$tenantId = requireClinicTenantId();

function discount_staff_can_access(): bool {
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    return isLoggedIn('manager') || isLoggedIn('staff') || isLoggedIn('doctor');
}

if (!discount_staff_can_access()) {
    jsonResponse(false, 'Unauthorized.');
}

function proof_upload_relative_url(string $tenantId, int $verificationId, string $dataUrlOrBase64): ?string {
    $raw = null;
    $ext = 'jpg';
    if (preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,#i', $dataUrlOrBase64, $m)) {
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $b64 = preg_replace('#^data:image/[^;]+;base64,#', '', $dataUrlOrBase64);
        $raw = base64_decode($b64, true);
    } else {
        $raw = base64_decode($dataUrlOrBase64, true);
    }
    if ($raw === false || $raw === '') {
        return null;
    }
    $max = defined('MAX_FILE_SIZE') ? (int) MAX_FILE_SIZE : (5 * 1024 * 1024);
    if (strlen($raw) > $max) {
        return null;
    }

    $safeTenant = preg_replace('/[^A-Za-z0-9_\-]/', '_', $tenantId);
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'discount_verifications' . DIRECTORY_SEPARATOR . $safeTenant;
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }
    }

    $name = 'dv_' . $verificationId . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $full = $dir . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($full, $raw) === false) {
        return null;
    }

    return 'uploads/discount_verifications/' . $safeTenant . '/' . $name;
}

function proof_public_url(?string $relativePath): string {
    if ($relativePath === null || trim($relativePath) === '') {
        return '';
    }
    $p = str_replace('\\', '/', $relativePath);
    return rtrim(BASE_URL, '/') . '/' . ltrim($p, '/');
}

/**
 * @return array<string,mixed>
 */
function mapVerificationRow(array $row): array {
    $proofUrl = proof_public_url(isset($row['proof_image_path']) ? (string) $row['proof_image_path'] : '');

    return [
        'id' => (string) (int) $row['discount_verification_id'],
        'programId' => (string) (int) $row['discount_program_id'],
        'programName' => (string) $row['program_name_snapshot'],
        'reqUploadProof' => !empty($row['req_upload_proof']),
        'reqNotes' => !empty($row['req_notes']),
        'patientName' => (string) $row['patient_name'],
        'patientRef' => $row['patient_ref'] !== null ? (string) $row['patient_ref'] : '',
        'idNumber' => $row['id_number'] !== null ? (string) $row['id_number'] : '',
        'proofImageUrl' => $proofUrl,
        'applicationNotes' => $row['application_notes'] !== null ? (string) $row['application_notes'] : '',
        'dateApplied' => (string) $row['date_applied'],
        'staffAssigned' => isset($row['staff_assigned_name']) && $row['staff_assigned_name'] !== null
            ? (string) $row['staff_assigned_name']
            : '',
        'staffAssignedUserId' => $row['staff_assigned_user_id'] !== null ? (string) $row['staff_assigned_user_id'] : '',
        'status' => (string) $row['status'],
        'approvedBy' => isset($row['approved_by_name']) && $row['approved_by_name'] !== null
            ? (string) $row['approved_by_name']
            : '',
        'approvedByUserId' => $row['approved_by_user_id'] !== null ? (string) $row['approved_by_user_id'] : '',
        'remarks' => $row['remarks'] !== null ? (string) $row['remarks'] : '',
        'programValidTo' => isset($row['program_valid_to']) && $row['program_valid_to'] !== null
            ? (string) $row['program_valid_to']
            : '',
        'programMinSpend' => isset($row['program_min_spend']) ? (float) $row['program_min_spend'] : 0.0,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($method) {
        case 'GET':
            handleDiscountVerificationsGet($pdo, $tenantId);
            break;
        case 'POST':
            handleDiscountVerificationsPost($pdo, $tenantId);
            break;
        case 'PATCH':
            handleDiscountVerificationsPatch($pdo, $tenantId);
            break;
        default:
            jsonResponse(false, 'Method not allowed.');
    }
} catch (Throwable $e) {
    error_log('discount_verifications: ' . $e->getMessage());
    jsonResponse(false, 'Server error.');
}

function handleDiscountVerificationsGet(PDO $pdo, string $tenantId): void {
    $reqFilter = trim((string) ($_GET['requirements'] ?? 'all'));
    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    $patientQ = trim((string) ($_GET['patient'] ?? ''));

    $sql = '
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
    $params = [$tenantId];

    if ($reqFilter === 'proof') {
        $sql .= ' AND v.req_upload_proof = 1';
    } elseif ($reqFilter === 'notes') {
        $sql .= ' AND v.req_notes = 1';
    } elseif ($reqFilter === 'both') {
        $sql .= ' AND v.req_upload_proof = 1 AND v.req_notes = 1';
    }

    if ($patientQ !== '') {
        $sql .= ' AND v.patient_name LIKE ?';
        $params[] = '%' . $patientQ . '%';
    }

    if ($dateFrom !== '') {
        $sql .= ' AND v.date_applied >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $sql .= ' AND v.date_applied <= ?';
        $params[] = $dateTo;
    }

    $sql .= ' ORDER BY v.date_applied DESC, v.discount_verification_id DESC LIMIT 500';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $row) {
        $out[] = mapVerificationRow($row);
    }

    $approversStmt = $pdo->prepare(
        'SELECT DISTINCT ab.user_id, ab.full_name
         FROM tbl_discount_verifications v
         INNER JOIN tbl_users ab ON ab.user_id = v.approved_by_user_id
         WHERE v.tenant_id = ? AND v.approved_by_user_id IS NOT NULL
         ORDER BY ab.full_name ASC'
    );
    $approversStmt->execute([$tenantId]);
    $approvers = $approversStmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(true, 'OK', [
        'verifications' => $out,
        'approvers' => array_map(static function ($r) {
            return [
                'user_id' => (string) $r['user_id'],
                'full_name' => (string) $r['full_name'],
            ];
        }, $approvers),
    ]);
}

function handleDiscountVerificationsPost(PDO $pdo, string $tenantId): void {
    $userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
    if ($userId === '') {
        jsonResponse(false, 'Session user missing.');
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, 'Invalid JSON.');
    }

    $programId = isset($input['discount_program_id']) ? (int) $input['discount_program_id'] : 0;
    if ($programId <= 0) {
        jsonResponse(false, 'Invalid discount program.');
    }

    $pStmt = $pdo->prepare(
        'SELECT * FROM tbl_discount_programs WHERE discount_program_id = ? AND tenant_id = ? LIMIT 1'
    );
    $pStmt->execute([$programId, $tenantId]);
    $prog = $pStmt->fetch(PDO::FETCH_ASSOC);
    if (!$prog) {
        jsonResponse(false, 'Discount program not found.');
    }
    if (empty($prog['enabled'])) {
        jsonResponse(false, 'This discount program is disabled.');
    }

    $patientName = trim((string) ($input['patient_name'] ?? ''));
    if ($patientName === '') {
        jsonResponse(false, 'Patient name is required.');
    }

    $patientRef = trim((string) ($input['patient_ref'] ?? ''));
    $idNumber = trim((string) ($input['id_number'] ?? ''));
    $applicationNotes = trim((string) ($input['application_notes'] ?? ''));
    $dateApplied = trim((string) ($input['date_applied'] ?? ''));
    if ($dateApplied === '') {
        $dateApplied = date('Y-m-d');
    }

    $proofIn = isset($input['proof_image_base64']) ? (string) $input['proof_image_base64'] : '';

    $reqProof = !empty($prog['req_upload_proof']);
    $reqNotes = !empty($prog['req_notes']);

    if ($reqProof && $proofIn === '') {
        jsonResponse(false, 'Proof image is required for this program.');
    }
    if ($reqNotes && $applicationNotes === '') {
        jsonResponse(false, 'Notes are required for this program.');
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
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, \'pending\')'
        );
        $ins->execute([
            $tenantId,
            $programId,
            $snapshotName,
            !empty($prog['req_upload_proof']) ? 1 : 0,
            !empty($prog['req_notes']) ? 1 : 0,
            $patientName,
            $patientRef !== '' ? $patientRef : null,
            $idNumber !== '' ? $idNumber : null,
            $applicationNotes !== '' ? $applicationNotes : null,
            $dateApplied,
            $userId,
        ]);
        $vid = (int) $pdo->lastInsertId();

        $relPath = null;
        if ($proofIn !== '') {
            $relPath = proof_upload_relative_url($tenantId, $vid, $proofIn);
            if ($relPath === null) {
                $pdo->rollBack();
                jsonResponse(false, 'Could not save proof image. Check format and size.');
            }
            $up = $pdo->prepare(
                'UPDATE tbl_discount_verifications SET proof_image_path = ? WHERE discount_verification_id = ? AND tenant_id = ?'
            );
            $up->execute([$relPath, $vid, $tenantId]);
        }

        $pdo->commit();

        $sel = $pdo->prepare(
            'SELECT v.*,
                p.valid_to AS program_valid_to,
                p.min_spend AS program_min_spend,
                sa.full_name AS staff_assigned_name,
                ab.full_name AS approved_by_name
             FROM tbl_discount_verifications v
             INNER JOIN tbl_discount_programs p
                ON p.discount_program_id = v.discount_program_id AND p.tenant_id = v.tenant_id
             LEFT JOIN tbl_users sa ON sa.user_id = v.staff_assigned_user_id
             LEFT JOIN tbl_users ab ON ab.user_id = v.approved_by_user_id
             WHERE v.discount_verification_id = ? AND v.tenant_id = ? LIMIT 1'
        );
        $sel->execute([$vid, $tenantId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            jsonResponse(false, 'Failed to load verification.');
        }
        jsonResponse(true, 'Created.', ['verification' => mapVerificationRow($row)]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleDiscountVerificationsPatch(PDO $pdo, string $tenantId): void {
    $userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
    if ($userId === '') {
        jsonResponse(false, 'Session user missing.');
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, 'Invalid JSON.');
    }

    $vid = isset($input['id']) ? (int) $input['id'] : 0;
    if ($vid <= 0) {
        jsonResponse(false, 'Invalid verification id.');
    }

    $action = strtolower(trim((string) ($input['action'] ?? '')));
    if (!in_array($action, ['approve', 'reject', 'request_info'], true)) {
        jsonResponse(false, 'Invalid action.');
    }

    $remarks = trim((string) ($input['remarks'] ?? ''));

    $stmt = $pdo->prepare(
        'SELECT * FROM tbl_discount_verifications WHERE discount_verification_id = ? AND tenant_id = ? LIMIT 1'
    );
    $stmt->execute([$vid, $tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(false, 'Record not found.');
    }
    if ($row['status'] !== 'pending') {
        jsonResponse(false, 'Only pending records can be updated.');
    }

    if ($action === 'reject' && $remarks === '') {
        jsonResponse(false, 'Remarks are required when rejecting.');
    }

    if ($action === 'approve') {
        $upd = $pdo->prepare(
            'UPDATE tbl_discount_verifications SET
                status = \'approved\',
                approved_by_user_id = ?,
                remarks = ?
             WHERE discount_verification_id = ? AND tenant_id = ?'
        );
        $upd->execute([
            $userId,
            $remarks !== '' ? $remarks : 'Approved.',
            $vid,
            $tenantId,
        ]);
    } elseif ($action === 'reject') {
        $upd = $pdo->prepare(
            'UPDATE tbl_discount_verifications SET
                status = \'rejected\',
                approved_by_user_id = ?,
                remarks = ?
             WHERE discount_verification_id = ? AND tenant_id = ?'
        );
        $upd->execute([$userId, $remarks, $vid, $tenantId]);
    } else {
        $note = $remarks !== '' ? $remarks : 'Additional information requested.';
        $upd = $pdo->prepare(
            'UPDATE tbl_discount_verifications SET
                remarks = ?
             WHERE discount_verification_id = ? AND tenant_id = ?'
        );
        $upd->execute([$note, $vid, $tenantId]);
    }

    $sel = $pdo->prepare(
        'SELECT v.*,
            p.valid_to AS program_valid_to,
            p.min_spend AS program_min_spend,
            sa.full_name AS staff_assigned_name,
            ab.full_name AS approved_by_name
         FROM tbl_discount_verifications v
         INNER JOIN tbl_discount_programs p
            ON p.discount_program_id = v.discount_program_id AND p.tenant_id = v.tenant_id
         LEFT JOIN tbl_users sa ON sa.user_id = v.staff_assigned_user_id
         LEFT JOIN tbl_users ab ON ab.user_id = v.approved_by_user_id
         WHERE v.discount_verification_id = ? AND v.tenant_id = ? LIMIT 1'
    );
    $sel->execute([$vid, $tenantId]);
    $out = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$out || !is_array($out)) {
        jsonResponse(false, 'Failed to reload record.');
    }
    jsonResponse(true, 'Updated.', ['verification' => mapVerificationRow($out)]);
}
