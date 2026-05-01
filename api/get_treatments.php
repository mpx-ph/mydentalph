<?php
// api/get_treatments.php — list tbl_treatments for the authenticated patient user
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/request_context.inc.php';

header('Content-Type: application/json; charset=utf-8');
api_send_no_cache_headers();

$userId = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
$tenantId = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : '';
$statusFilter = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
$treatmentId = isset($_GET['treatment_id']) ? trim((string) $_GET['treatment_id']) : '';

if ($userId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

$allowedStatuses = ['active', 'completed', 'cancelled'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status (use active, completed, or cancelled)']);
    exit;
}

try {
    $tenantId = api_resolve_tenant_id($pdo, $userId, $tenantId);
    if ($tenantId === null) {
        echo json_encode(['success' => false, 'message' => 'Missing tenant context for this user']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT patient_id FROM tbl_patients WHERE (owner_user_id = ? OR linked_user_id = ?) AND tenant_id = ? LIMIT 1'
    );
    $stmt->execute([$userId, $userId, $tenantId]);
    $patRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patRow) {
        echo json_encode(['success' => true, 'patient_id' => null, 'treatments' => []]);
        exit;
    }

    $patientId = (string) $patRow['patient_id'];

    $sql = '
        SELECT
            id,
            tenant_id,
            treatment_id,
            patient_id,
            primary_service_id,
            primary_service_name,
            total_cost,
            amount_paid,
            remaining_balance,
            duration_months,
            months_paid,
            months_left,
            status,
            started_at,
            completed_at,
            created_by,
            created_at,
            updated_at
        FROM tbl_treatments
        WHERE tenant_id = ? AND patient_id = ?
    ';
    $bind = [$tenantId, $patientId];

    if ($statusFilter !== '') {
        $sql .= ' AND status = ? ';
        $bind[] = $statusFilter;
    }
    if ($treatmentId !== '') {
        $sql .= ' AND treatment_id = ? ';
        $bind[] = $treatmentId;
    }

    $sql .= ' ORDER BY started_at DESC, id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    $treatments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize INT unix `started_at` / etc. for mobile (JSON numbers were shown as raw digits; tiny values = 1970 bugs).
    $tzPh = new DateTimeZone('Asia/Manila');
    foreach ($treatments as &$tr) {
        foreach (['started_at', 'completed_at', 'created_at', 'updated_at'] as $k) {
            if (!array_key_exists($k, $tr)) {
                continue;
            }
            $v = $tr[$k];
            if (!is_numeric($v)) {
                continue;
            }
            $ts = (int) $v;
            if ($ts >= 946684800) {
                $dt = (new DateTimeImmutable('@' . $ts))->setTimezone($tzPh);
                $tr[$k] = $dt->format('Y-m-d H:i:s');
            } elseif ($ts > 0 && $ts < 946684800) {
                $tr[$k] = null;
            }
        }
    }
    unset($tr);

    echo json_encode([
        'success' => true,
        'patient_id' => $patientId,
        'treatments' => $treatments,
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
