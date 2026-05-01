<?php
// api/save_treatment.php — POST create/update tbl_treatments for the authenticated patient user
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/request_context.inc.php';
require_once __DIR__ . '/profile_common.inc.php';

api_send_no_cache_headers();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json_exit(false, 'POST required');
}

$raw = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$userId = isset($input['user_id']) ? trim((string) $input['user_id']) : '';
$tenantIdIn = isset($input['tenant_id']) ? trim((string) $input['tenant_id']) : '';

if ($userId === '') {
    api_json_exit(false, 'Missing user_id');
}

/** @return array{0: float, 1: float, 2: float} total, paid, remaining */
function api_treatment_normalize_money(float $total, float $paid): array
{
    $total = max(0.0, round($total, 2));
    $paid = max(0.0, round($paid, 2));
    if ($paid > $total) {
        $paid = $total;
    }
    $remaining = max(0.0, round($total - $paid, 2));
    return [$total, $paid, $remaining];
}

try {
    $tenantId = api_resolve_tenant_id($pdo, $userId, $tenantIdIn);
    if ($tenantId === null) {
        api_json_exit(false, 'Missing tenant context for this user');
    }

    $user = api_profile_fetch_user($pdo, $userId, $tenantId);
    if (!$user) {
        api_json_exit(false, 'User not found for this tenant');
    }
    if (strtolower((string) ($user['role'] ?? '')) !== 'client') {
        api_json_exit(false, 'Treatments API is only available for patient accounts');
    }

    $p = api_profile_fetch_patient($pdo, $userId, $tenantId);
    if (!$p || empty($p['patient_id'])) {
        api_json_exit(false, 'Patient profile not found. Complete registration first.');
    }
    $patientId = trim((string) $p['patient_id']);

    $incomingTid = isset($input['treatment_id']) ? trim((string) $input['treatment_id']) : '';

    $existing = null;
    if ($incomingTid !== '') {
        $st = $pdo->prepare(
            'SELECT * FROM tbl_treatments WHERE tenant_id = ? AND treatment_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $incomingTid]);
        $existing = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($existing !== null && (string) ($existing['patient_id'] ?? '') !== $patientId) {
            api_json_exit(false, 'Treatment belongs to another patient');
        }
    }

    $primaryServiceId = isset($input['primary_service_id']) ? trim((string) $input['primary_service_id']) : '';
    if ($primaryServiceId === '' && $existing === null) {
        api_json_exit(false, 'primary_service_id is required when creating a treatment');
    }
    if ($primaryServiceId !== '') {
        $svc = $pdo->prepare(
            'SELECT service_id, service_name FROM tbl_services WHERE tenant_id = ? AND service_id = ? LIMIT 1'
        );
        $svc->execute([$tenantId, $primaryServiceId]);
        $svcRow = $svc->fetch(PDO::FETCH_ASSOC);
        if (!$svcRow) {
            api_json_exit(false, 'Invalid primary_service_id for this clinic');
        }
        $svcNameDefault = (string) ($svcRow['service_name'] ?? '');
    } else {
        $svcNameDefault = '';
    }

    $totalCost = isset($input['total_cost']) ? (float) $input['total_cost'] : ($existing ? (float) $existing['total_cost'] : 0.0);
    $amountPaid = isset($input['amount_paid']) ? (float) $input['amount_paid'] : ($existing ? (float) $existing['amount_paid'] : 0.0);
    [, , $remainingComputed] = api_treatment_normalize_money($totalCost, $amountPaid);
    $remainingBalance = array_key_exists('remaining_balance', $input)
        ? max(0.0, round((float) $input['remaining_balance'], 2))
        : $remainingComputed;

    $durationMonths = isset($input['duration_months'])
        ? (int) $input['duration_months']
        : ($existing ? (int) $existing['duration_months'] : 0);
    $monthsPaid = isset($input['months_paid'])
        ? (int) $input['months_paid']
        : ($existing ? (int) $existing['months_paid'] : 0);
    $monthsLeft = isset($input['months_left'])
        ? (int) $input['months_left']
        : ($existing ? (int) $existing['months_left'] : max(0, $durationMonths - $monthsPaid));

    $status = isset($input['status']) ? strtolower(trim((string) $input['status'])) : ($existing ? (string) $existing['status'] : 'active');
    $allowedStatuses = ['active', 'completed', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        api_json_exit(false, 'Invalid status');
    }

    $startedAt = array_key_exists('started_at', $input)
        ? trim((string) $input['started_at'])
        : ($existing ? ($existing['started_at'] !== null ? (string) $existing['started_at'] : '') : '');
    $completedAt = array_key_exists('completed_at', $input)
        ? trim((string) $input['completed_at'])
        : (($existing !== null && isset($existing['completed_at']) && $existing['completed_at'] !== null)
            ? (string) $existing['completed_at']
            : null);

    if ($startedAt !== '') {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $startedAt);
        if (!$dt || $dt->format('Y-m-d') !== $startedAt) {
            api_json_exit(false, 'started_at must be YYYY-MM-DD or empty');
        }
    }
    $completedAtNormalized = null;
    if ($completedAt !== null && $completedAt !== '') {
        $dtc = DateTimeImmutable::createFromFormat('Y-m-d', $completedAt);
        if (!$dtc || $dtc->format('Y-m-d') !== $completedAt) {
            api_json_exit(false, 'completed_at must be YYYY-MM-DD or empty');
        }
        $completedAtNormalized = $completedAt;
    }

    [$totalNorm, $paidNorm, ] = api_treatment_normalize_money($totalCost, $amountPaid);

    $primaryServiceName = array_key_exists('primary_service_name', $input)
        ? trim((string) $input['primary_service_name'])
        : '';
    if ($primaryServiceId !== '') {
        if ($primaryServiceName === '') {
            $primaryServiceName = $svcNameDefault;
        }
    } else {
        $primaryServiceName = $existing !== null ? (string) ($existing['primary_service_name'] ?? '') : '';
    }

    if ($existing === null) {
        $treatmentId = $incomingTid !== '' ? $incomingTid : 'TRT-' . strtoupper(substr(md5((string) microtime(true) . $userId . mt_rand()), 0, 10));

        $dup = $pdo->prepare('SELECT 1 FROM tbl_treatments WHERE tenant_id = ? AND treatment_id = ? LIMIT 1');
        $dup->execute([$tenantId, $treatmentId]);
        if ($dup->fetchColumn()) {
            api_json_exit(false, 'treatment_id already exists; omit it to generate a new id or use save to update');
        }

        $startedParam = ($startedAt === '') ? null : $startedAt;

        $ins = $pdo->prepare(
            'INSERT INTO tbl_treatments (
                tenant_id, treatment_id, patient_id, primary_service_id, primary_service_name,
                total_cost, amount_paid, remaining_balance, duration_months, months_paid, months_left,
                status, started_at, completed_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $tenantId,
            $treatmentId,
            $patientId,
            $primaryServiceId,
            $primaryServiceName !== '' ? $primaryServiceName : null,
            $totalNorm,
            $paidNorm,
            $remainingBalance,
            $durationMonths,
            $monthsPaid,
            $monthsLeft,
            $status,
            $startedParam,
            $completedAtNormalized,
            $userId,
        ]);

        api_json_exit(true, 'Treatment created.', [
            'treatment_id' => $treatmentId,
            'patient_id' => $patientId,
        ]);
    }

    // Update: primary_service_id may be omitted to keep existing; if sent empty string, keep existing
    $updateServiceId = $primaryServiceId !== '' ? $primaryServiceId : (string) $existing['primary_service_id'];

    $svc2 = $pdo->prepare(
        'SELECT service_name FROM tbl_services WHERE tenant_id = ? AND service_id = ? LIMIT 1'
    );
    $svc2->execute([$tenantId, $updateServiceId]);
    $svc2Row = $svc2->fetch(PDO::FETCH_ASSOC);
    if (!$svc2Row) {
        api_json_exit(false, 'Invalid primary_service_id for this clinic');
    }

    $finalServiceName = $primaryServiceName;
    if ($finalServiceName === '' && ($primaryServiceId !== '' || $updateServiceId !== '')) {
        $finalServiceName = (string) ($svc2Row['service_name'] ?? '');
    }
    if ($finalServiceName === '' && isset($existing['primary_service_name'])) {
        $finalServiceName = (string) $existing['primary_service_name'];
    }

    $startedUpdate = ($startedAt === '') ? null : $startedAt;

    $upd = $pdo->prepare(
        'UPDATE tbl_treatments SET
            primary_service_id = ?,
            primary_service_name = ?,
            total_cost = ?,
            amount_paid = ?,
            remaining_balance = ?,
            duration_months = ?,
            months_paid = ?,
            months_left = ?,
            status = ?,
            started_at = ?,
            completed_at = ?
        WHERE tenant_id = ? AND treatment_id = ? AND patient_id = ?'
    );
    $upd->execute([
        $updateServiceId,
        $finalServiceName !== '' ? $finalServiceName : null,
        $totalNorm,
        $paidNorm,
        $remainingBalance,
        $durationMonths,
        $monthsPaid,
        $monthsLeft,
        $status,
        $startedUpdate,
        $completedAtNormalized,
        $tenantId,
        (string) $existing['treatment_id'],
        $patientId,
    ]);

    api_json_exit(true, 'Treatment updated.', [
        'treatment_id' => (string) $existing['treatment_id'],
        'patient_id' => $patientId,
    ]);
} catch (InvalidArgumentException $e) {
    api_json_exit(false, $e->getMessage());
} catch (Throwable $e) {
    api_json_exit(false, 'Error: ' . $e->getMessage());
}
