<?php
/**
 * Discount programs CRUD (staff portal — tenant scoped).
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($method) {
        case 'GET':
            handleDiscountProgramsGet($pdo, $tenantId);
            break;
        case 'POST':
            handleDiscountProgramsPost($pdo, $tenantId);
            break;
        case 'PUT':
            handleDiscountProgramsPut($pdo, $tenantId);
            break;
        case 'DELETE':
            handleDiscountProgramsDelete($pdo, $tenantId);
            break;
        default:
            jsonResponse(false, 'Method not allowed.');
    }
} catch (Throwable $e) {
    error_log('discount_programs: ' . $e->getMessage());
    jsonResponse(false, 'Server error.');
}

/**
 * @return array<string,mixed>
 */
function mapProgramRow(PDO $pdo, string $tenantId, array $row): array {
    $id = (int) $row['discount_program_id'];
    $svcStmt = $pdo->prepare(
        'SELECT service_id FROM tbl_discount_program_services WHERE discount_program_id = ? AND tenant_id = ? ORDER BY service_id'
    );
    $svcStmt->execute([$id, $tenantId]);
    $serviceIds = [];
    while ($s = $svcStmt->fetch(PDO::FETCH_ASSOC)) {
        $serviceIds[] = (string) $s['service_id'];
    }

    return [
        'id' => (string) $id,
        'name' => (string) $row['name'],
        'discountType' => (string) $row['discount_type'],
        'value' => (float) $row['value'],
        'minSpend' => (float) $row['min_spend'],
        'reqUploadProof' => !empty($row['req_upload_proof']),
        'reqNotes' => !empty($row['req_notes']),
        'enabled' => !empty($row['enabled']),
        'validFrom' => $row['valid_from'] !== null ? (string) $row['valid_from'] : '',
        'validTo' => $row['valid_to'] !== null ? (string) $row['valid_to'] : '',
        'serviceScope' => (string) $row['service_scope'],
        'serviceIds' => $serviceIds,
        'stacking' => (string) $row['stacking'],
    ];
}

function handleDiscountProgramsGet(PDO $pdo, string $tenantId): void {
    $stmt = $pdo->prepare(
        'SELECT * FROM tbl_discount_programs WHERE tenant_id = ? ORDER BY name ASC'
    );
    $stmt->execute([$tenantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $out[] = mapProgramRow($pdo, $tenantId, $row);
    }
    jsonResponse(true, 'OK', ['programs' => $out]);
}

function handleDiscountProgramsPost(PDO $pdo, string $tenantId): void {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, 'Invalid JSON.');
    }

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        jsonResponse(false, 'Name is required.');
    }

    $discountType = strtolower(trim((string) ($input['discountType'] ?? 'percentage')));
    if (!in_array($discountType, ['percentage', 'fixed'], true)) {
        jsonResponse(false, 'Invalid discount type.');
    }

    $value = isset($input['value']) ? (float) $input['value'] : 0.0;
    if ($value < 0) {
        jsonResponse(false, 'Invalid value.');
    }

    $minSpend = isset($input['minSpend']) ? (float) $input['minSpend'] : 0.0;
    if ($minSpend < 0) {
        $minSpend = 0.0;
    }

    $scope = strtolower(trim((string) ($input['serviceScope'] ?? 'all')));
    if (!in_array($scope, ['all', 'selected'], true)) {
        $scope = 'all';
    }

    $stacking = strtolower(trim((string) ($input['stacking'] ?? 'no')));
    if (!in_array($stacking, ['yes', 'no'], true)) {
        $stacking = 'no';
    }

    $serviceIds = $input['serviceIds'] ?? [];
    if (!is_array($serviceIds)) {
        $serviceIds = [];
    }
    $serviceIds = array_values(array_unique(array_map('strval', $serviceIds)));

    $validFrom = trim((string) ($input['validFrom'] ?? ''));
    $validTo = trim((string) ($input['validTo'] ?? ''));
    $vf = $validFrom !== '' ? $validFrom : null;
    $vt = $validTo !== '' ? $validTo : null;

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO tbl_discount_programs (
                tenant_id, name, discount_type, value, min_spend,
                req_upload_proof, req_notes, enabled, valid_from, valid_to,
                service_scope, stacking
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $tenantId,
            $name,
            $discountType,
            round($value, 2),
            round($minSpend, 2),
            !empty($input['reqUploadProof']) ? 1 : 0,
            !empty($input['reqNotes']) ? 1 : 0,
            !empty($input['enabled']) ? 1 : 0,
            $vf,
            $vt,
            $scope,
            $stacking,
        ]);
        $newId = (int) $pdo->lastInsertId();

        if ($scope === 'selected' && $serviceIds !== []) {
            insertProgramServices($pdo, $tenantId, $newId, $serviceIds);
        }

        $pdo->commit();

        $sel = $pdo->prepare('SELECT * FROM tbl_discount_programs WHERE discount_program_id = ? AND tenant_id = ? LIMIT 1');
        $sel->execute([$newId, $tenantId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            jsonResponse(false, 'Failed to load created program.');
        }
        jsonResponse(true, 'Created.', ['program' => mapProgramRow($pdo, $tenantId, $row)]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function insertProgramServices(PDO $pdo, string $tenantId, int $programId, array $serviceIds): void {
    $check = $pdo->prepare('SELECT 1 FROM tbl_services WHERE tenant_id = ? AND service_id = ? LIMIT 1');
    $ins = $pdo->prepare(
        'INSERT INTO tbl_discount_program_services (discount_program_id, tenant_id, service_id) VALUES (?, ?, ?)'
    );
    foreach ($serviceIds as $sid) {
        $sid = trim((string) $sid);
        if ($sid === '') {
            continue;
        }
        $check->execute([$tenantId, $sid]);
        if (!$check->fetchColumn()) {
            continue;
        }
        $ins->execute([$programId, $tenantId, $sid]);
    }
}

function deleteProgramServices(PDO $pdo, string $tenantId, int $programId): void {
    $del = $pdo->prepare('DELETE FROM tbl_discount_program_services WHERE discount_program_id = ? AND tenant_id = ?');
    $del->execute([$programId, $tenantId]);
}

function handleDiscountProgramsPut(PDO $pdo, string $tenantId): void {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, 'Invalid JSON.');
    }

    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if ($id <= 0) {
        jsonResponse(false, 'Invalid program id.');
    }

    $chk = $pdo->prepare('SELECT 1 FROM tbl_discount_programs WHERE discount_program_id = ? AND tenant_id = ? LIMIT 1');
    $chk->execute([$id, $tenantId]);
    if (!$chk->fetchColumn()) {
        jsonResponse(false, 'Program not found.');
    }

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        jsonResponse(false, 'Name is required.');
    }

    $discountType = strtolower(trim((string) ($input['discountType'] ?? 'percentage')));
    if (!in_array($discountType, ['percentage', 'fixed'], true)) {
        jsonResponse(false, 'Invalid discount type.');
    }

    $value = isset($input['value']) ? (float) $input['value'] : 0.0;
    $minSpend = isset($input['minSpend']) ? (float) $input['minSpend'] : 0.0;
    if ($minSpend < 0) {
        $minSpend = 0.0;
    }

    $scope = strtolower(trim((string) ($input['serviceScope'] ?? 'all')));
    if (!in_array($scope, ['all', 'selected'], true)) {
        $scope = 'all';
    }

    $stacking = strtolower(trim((string) ($input['stacking'] ?? 'no')));
    if (!in_array($stacking, ['yes', 'no'], true)) {
        $stacking = 'no';
    }

    $serviceIds = $input['serviceIds'] ?? [];
    if (!is_array($serviceIds)) {
        $serviceIds = [];
    }
    $serviceIds = array_values(array_unique(array_map('strval', $serviceIds)));

    $validFrom = trim((string) ($input['validFrom'] ?? ''));
    $validTo = trim((string) ($input['validTo'] ?? ''));
    $vf = $validFrom !== '' ? $validFrom : null;
    $vt = $validTo !== '' ? $validTo : null;

    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare(
            'UPDATE tbl_discount_programs SET
                name = ?, discount_type = ?, value = ?, min_spend = ?,
                req_upload_proof = ?, req_notes = ?, enabled = ?,
                valid_from = ?, valid_to = ?, service_scope = ?, stacking = ?
             WHERE discount_program_id = ? AND tenant_id = ?'
        );
        $upd->execute([
            $name,
            $discountType,
            round($value, 2),
            round($minSpend, 2),
            !empty($input['reqUploadProof']) ? 1 : 0,
            !empty($input['reqNotes']) ? 1 : 0,
            !empty($input['enabled']) ? 1 : 0,
            $vf,
            $vt,
            $scope,
            $stacking,
            $id,
            $tenantId,
        ]);

        deleteProgramServices($pdo, $tenantId, $id);
        if ($scope === 'selected' && $serviceIds !== []) {
            insertProgramServices($pdo, $tenantId, $id, $serviceIds);
        }

        $pdo->commit();

        $sel = $pdo->prepare('SELECT * FROM tbl_discount_programs WHERE discount_program_id = ? AND tenant_id = ? LIMIT 1');
        $sel->execute([$id, $tenantId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            jsonResponse(false, 'Failed to load program.');
        }
        jsonResponse(true, 'Updated.', ['program' => mapProgramRow($pdo, $tenantId, $row)]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleDiscountProgramsDelete(PDO $pdo, string $tenantId): void {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        jsonResponse(false, 'Invalid program id.');
    }

    $cntStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM tbl_discount_verifications WHERE discount_program_id = ? AND tenant_id = ?'
    );
    $cntStmt->execute([$id, $tenantId]);
    if ((int) $cntStmt->fetchColumn() > 0) {
        jsonResponse(false, 'Cannot delete: verification records exist for this program.');
    }

    $delJunction = $pdo->prepare(
        'DELETE FROM tbl_discount_program_services WHERE discount_program_id = ? AND tenant_id = ?'
    );
    $delJunction->execute([$id, $tenantId]);

    $del = $pdo->prepare('DELETE FROM tbl_discount_programs WHERE discount_program_id = ? AND tenant_id = ?');
    $del->execute([$id, $tenantId]);
    if ($del->rowCount() === 0) {
        jsonResponse(false, 'Program not found.');
    }
    jsonResponse(true, 'Deleted.');
}
