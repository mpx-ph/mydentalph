<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../../api/includes/refund_requests_schema.inc.php';
require_once __DIR__ . '/../../api/includes/booking_cancel_wallet.inc.php';

header('Content-Type: application/json; charset=utf-8');

function staff_can_process_refunds(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    $t = strtolower(trim((string) ($_SESSION['user_type'] ?? '')));

    return in_array($t, ['manager', 'doctor', 'staff', 'admin'], true);
}

if (!staff_can_process_refunds()) {
    jsonResponse(false, 'Unauthorized.');
}

$pdo = getDBConnection();
$tenantId = requireClinicTenantId();

$raw = file_get_contents('php://input');
$input = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
$action = strtolower(trim((string)($input['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '')));

if ($action === 'list') {
    refund_requests_ensure_table($pdo);
    $st = $pdo->prepare(
        "SELECT rr.id, rr.appointment_id, rr.booking_id, rr.patient_id, rr.reason, rr.created_at,
                p.first_name AS patient_first_name, p.last_name AS patient_last_name,
                COALESCE((
                    SELECT SUM(py.amount) FROM tbl_payments py
                    WHERE py.tenant_id = rr.tenant_id AND py.booking_id = rr.booking_id
                      AND py.patient_id = rr.patient_id AND py.status = 'completed'
                ), 0) AS refundable_amount
         FROM tbl_refund_requests rr
         INNER JOIN tbl_patients p ON p.tenant_id = rr.tenant_id AND p.patient_id = rr.patient_id
         WHERE rr.tenant_id = ? AND rr.status = 'pending'
         ORDER BY rr.id ASC"
    );
    $st->execute([$tenantId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    jsonResponse(true, 'OK', ['requests' => $rows]);
}

$requestId = (int) ($input['request_id'] ?? $_POST['request_id'] ?? 0);
if ($requestId <= 0) {
    jsonResponse(false, 'Invalid request_id.');
}

$staffUserId = trim((string) ($_SESSION['user_id'] ?? ''));
if ($staffUserId === '') {
    jsonResponse(false, 'Session expired.');
}

if ($action === 'approve') {
    try {
        refund_requests_ensure_table($pdo);
        $pdo->beginTransaction();

        $st = $pdo->prepare(
            "SELECT * FROM tbl_refund_requests WHERE id = ? AND tenant_id = ? AND status = 'pending' LIMIT 1 FOR UPDATE"
        );
        $st->execute([$requestId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            jsonResponse(false, 'Request not found or already resolved.');
        }

        $apptId = (int) ($row['appointment_id'] ?? 0);
        $apSt = $pdo->prepare(
            'SELECT id, booking_id, status FROM tbl_appointments WHERE tenant_id = ? AND id = ? LIMIT 1 FOR UPDATE'
        );
        $apSt->execute([$tenantId, $apptId]);
        $ap = $apSt->fetch(PDO::FETCH_ASSOC);
        if (!$ap) {
            $pdo->rollBack();
            jsonResponse(false, 'Appointment not found.');
        }

        $reason = trim((string) ($row['reason'] ?? ''));
        $notesForCancel = $reason !== '' ? $reason : 'Cancel & refund approved by clinic.';

        $result = booking_perform_full_cancellation(
            $pdo,
            $tenantId,
            (string) ($row['patient_id'] ?? ''),
            $apptId,
            (string) ($row['booking_id'] ?? ''),
            (string) ($ap['status'] ?? ''),
            $notesForCancel,
            $staffUserId
        );

        $upd = $pdo->prepare(
            "UPDATE tbl_refund_requests
             SET status = 'approved', resolved_at = NOW(), resolved_by_user_id = ?
             WHERE id = ? AND tenant_id = ? AND status = 'pending'
             LIMIT 1"
        );
        $upd->execute([$staffUserId, $requestId, $tenantId]);

        $pdo->commit();

        jsonResponse(true, 'Refund approved and booking cancelled.', [
            'refunded_amount' => round((float) ($result['refunded_amount'] ?? 0), 2),
            'already_cancelled' => !empty($result['already_cancelled']),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        jsonResponse(false, $e->getMessage());
    }
}

if ($action === 'decline') {
    try {
        refund_requests_ensure_table($pdo);
        $upd = $pdo->prepare(
            "UPDATE tbl_refund_requests
             SET status = 'declined', resolved_at = NOW(), resolved_by_user_id = ?
             WHERE id = ? AND tenant_id = ? AND status = 'pending'
             LIMIT 1"
        );
        $upd->execute([$staffUserId, $requestId, $tenantId]);
        if ($upd->rowCount() < 1) {
            jsonResponse(false, 'Request not found or already resolved.');
        }
        jsonResponse(true, 'Request declined. The patient app will show the booking as still scheduled.');
    } catch (Throwable $e) {
        jsonResponse(false, $e->getMessage());
    }
}

jsonResponse(false, 'Unknown action.');
