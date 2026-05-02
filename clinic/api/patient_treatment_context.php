<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/appointment_db_tables.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Target end date from tbl_treatments: started_at + duration_months (calendar months).
 */
/**
 * Booking row used by Treatment Progress (latest appointment touching this treatment_id).
 *
 * @return array{booking_id:string,appointment_date:string}
 */
function patient_treatment_resolve_booking_row(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    string $treatmentId,
    ?string $appointmentsTable
): array {
    if ($appointmentsTable === null || $treatmentId === '') {
        return ['booking_id' => '', 'appointment_date' => ''];
    }
    $apptCols = clinic_table_columns($pdo, $appointmentsTable);
    if (!in_array('treatment_id', $apptCols, true)) {
        return ['booking_id' => '', 'appointment_date' => ''];
    }
    $hasCreated = in_array('created_at', $apptCols, true);
    $hasApptDate = in_array('appointment_date', $apptCols, true);
    $qa = clinic_quote_identifier($appointmentsTable);
    $orderBy = $hasCreated
        ? 'a.created_at DESC'
        : ($hasApptDate ? 'a.appointment_date DESC' : 'a.booking_id DESC');

    $bStmt = $pdo->prepare("
        SELECT a.booking_id
        FROM {$qa} a
        WHERE a.tenant_id = ?
          AND a.patient_id = ?
          AND TRIM(COALESCE(a.treatment_id, '')) = ?
        ORDER BY {$orderBy}
        LIMIT 1
    ");
    $bStmt->execute([$tenantId, $patientId, $treatmentId]);
    $br = $bStmt->fetch(PDO::FETCH_ASSOC);

    return [
        'booking_id' => $br ? trim((string) ($br['booking_id'] ?? '')) : '',
        'appointment_date' => '',
    ];
}

function patient_treatment_installment_open_count(
    PDO $pdo,
    string $tenantId,
    string $bookingId,
    ?string $installmentsTable
): int {
    if ($installmentsTable === null || $bookingId === '') {
        return 0;
    }
    $quoted = clinic_quote_identifier($installmentsTable);
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM {$quoted} i
        WHERE i.booking_id = ?
          AND (
              i.tenant_id = ?
              OR i.tenant_id IS NULL
              OR TRIM(COALESCE(i.tenant_id, '')) = ''
          )
          AND LOWER(TRIM(COALESCE(i.status, ''))) NOT IN ('paid', 'completed')
    ");
    $stmt->execute([$bookingId, $tenantId]);

    return (int) $stmt->fetchColumn();
}

/**
 * True when ledger is fully settled and persisted installment slots (if any) are all paid/completed.
 *
 * Mirrors Treatment Progress reconciliation: unpaid rows disqualify closure even if totals look settled.
 *
 * @param array<string,mixed> $treatmentRow
 */
function patient_treatment_plan_sidebar_finished(
    PDO $pdo,
    string $tenantId,
    array $treatmentRow,
    string $patientId,
    ?string $appointmentsTable,
    ?string $installmentsTable
): bool {
    $eps = 0.05;
    $totalCost = max(0.0, (float) ($treatmentRow['total_cost'] ?? 0));
    $amountPaid = max(0.0, (float) ($treatmentRow['amount_paid'] ?? 0));
    $remainingBalance = max(0.0, (float) ($treatmentRow['remaining_balance'] ?? 0));
    if ($totalCost <= 0.009) {
        return false;
    }
    if ($amountPaid > $totalCost) {
        $amountPaid = $totalCost;
    }
    $financiallySettled = $remainingBalance <= $eps && ($amountPaid + $eps >= $totalCost);

    $treatmentId = trim((string) ($treatmentRow['treatment_id'] ?? ''));

    if (!$financiallySettled) {
        return false;
    }

    $booking = patient_treatment_resolve_booking_row($pdo, $tenantId, $patientId, $treatmentId, $appointmentsTable);
    $bookingId = $booking['booking_id'];

    if ($bookingId !== '' && $installmentsTable !== null) {
        $qi = clinic_quote_identifier($installmentsTable);
        $cntStmt = $pdo->prepare("
            SELECT COUNT(*) FROM {$qi} i
            WHERE i.booking_id = ?
              AND (
                  i.tenant_id = ?
                  OR i.tenant_id IS NULL
                  OR TRIM(COALESCE(i.tenant_id, '')) = ''
              )
        ");
        $cntStmt->execute([$bookingId, $tenantId]);
        $rowCnt = (int) $cntStmt->fetchColumn();
        if ($rowCnt > 0 && patient_treatment_installment_open_count($pdo, $tenantId, $bookingId, $installmentsTable) > 0) {
            return false;
        }
    }

    return true;
}

/**
 * Persist closed treatment row when installment plan is satisfied (eligible for next plan).
 */
function patient_treatment_mark_plan_completed(
    PDO $pdo,
    string $quotedTreatmentsTable,
    string $tenantId,
    string $patientId,
    string $treatmentId
): void {
    $treatmentId = trim($treatmentId);
    if ($treatmentId === '') {
        return;
    }
    $today = date('Y-m-d');
    $bareName = trim($quotedTreatmentsTable, '`');
    $cols = clinic_table_columns($pdo, $bareName);
    $sets = ["status = 'completed'"];
    $paramsPostSet = [];

    if (in_array('completed_at', $cols, true)) {
        $sets[] = 'completed_at = COALESCE(NULLIF(TRIM(COALESCE(completed_at, \'\')), \'\'), ?)';
        $paramsPostSet[] = $today;
    }
    if (in_array('remaining_balance', $cols, true)) {
        $sets[] = 'remaining_balance = 0';
    }
    if (in_array('months_left', $cols, true)) {
        $sets[] = 'months_left = 0';
    }
    if (in_array('amount_paid', $cols, true) && in_array('total_cost', $cols, true)) {
        $sets[] = 'amount_paid = CASE WHEN COALESCE(total_cost, 0) > COALESCE(amount_paid, 0) THEN COALESCE(total_cost, 0) ELSE COALESCE(amount_paid, 0) END';
    }

    $sql = '
        UPDATE ' . $quotedTreatmentsTable . '
        SET ' . implode(', ', $sets) . '
        WHERE tenant_id = ?
          AND patient_id = ?
          AND treatment_id = ?
          AND LOWER(TRIM(COALESCE(status, \'\'))) <> \'completed\'
        LIMIT 1
    ';
    try {
        $params = array_merge($paramsPostSet, [$tenantId, $patientId, $treatmentId]);
        $upd = $pdo->prepare($sql);
        $upd->execute($params);
    } catch (Throwable $e) {
        error_log('patient_treatment_mark_plan_completed: ' . $e->getMessage());
    }
}

function patient_treatment_compute_target_completion_date(?string $startedAt, int $durationMonths): ?string
{
    $startedAt = $startedAt !== null ? trim($startedAt) : '';
    if ($startedAt === '' || $durationMonths <= 0) {
        return null;
    }
    try {
        $tz = new DateTimeZone('Asia/Manila');
        $start = new DateTimeImmutable($startedAt, $tz);
    } catch (Throwable $e) {
        try {
            $start = new DateTimeImmutable($startedAt);
        } catch (Throwable $e2) {
            return null;
        }
    }
    try {
        return $start->modify('+' . $durationMonths . ' months')->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use GET.']);
    exit;
}

$tenantId = requireClinicTenantId();
if (!isLoggedIn(['manager', 'doctor', 'staff', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff login required.']);
    exit;
}

$patientId = trim((string) ($_GET['patient_id'] ?? ''));
if ($patientId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'patient_id is required.']);
    exit;
}

try {
    $pdo = getDBConnection();
    $dbTables = clinic_resolve_appointment_db_tables($pdo);
    $treatmentsTable = $dbTables['treatments'];
    $servicesTable = $dbTables['services'];
    $paymentsTable = $dbTables['payments'];
    $appointmentsTable = $dbTables['appointments'];
    $installmentsTable = clinic_get_physical_table_name($pdo, 'tbl_installments')
        ?? clinic_get_physical_table_name($pdo, 'installments');
    if ($treatmentsTable === null || $servicesTable === null) {
        echo json_encode([
            'success' => true,
            'message' => 'No treatment table available.',
            'data' => ['has_active_treatment' => false],
        ]);
        exit;
    }

    $qt = clinic_quote_identifier($treatmentsTable);
    $qs = clinic_quote_identifier($servicesTable);
    $qp = $paymentsTable !== null ? clinic_quote_identifier($paymentsTable) : null;
    $qa = $appointmentsTable !== null ? clinic_quote_identifier($appointmentsTable) : null;
    $hasActiveAppointmentToday = false;
    if ($qa !== null) {
        $clinicTz = new DateTimeZone('Asia/Manila');
        $todayDate = (new DateTimeImmutable('now', $clinicTz))->format('Y-m-d');
        $activeAppointmentStmt = $pdo->prepare("
            SELECT 1
            FROM {$qa}
            WHERE tenant_id = ?
              AND patient_id = ?
              AND appointment_date = ?
              AND LOWER(COALESCE(status, 'pending')) IN ('pending', 'confirmed', 'scheduled')
            LIMIT 1
        ");
        $activeAppointmentStmt->execute([$tenantId, $patientId, $todayDate]);
        $hasActiveAppointmentToday = (bool) $activeAppointmentStmt->fetchColumn();
    }

    $stmt = $pdo->prepare("
        SELECT
            t.treatment_id,
            t.patient_id,
            t.primary_service_id,
            t.primary_service_name,
            COALESCE(t.total_cost, 0) AS total_cost,
            COALESCE(t.amount_paid, 0) AS amount_paid,
            COALESCE(t.remaining_balance, 0) AS remaining_balance,
            COALESCE(t.duration_months, 0) AS duration_months,
            COALESCE(t.months_paid, 0) AS months_paid,
            COALESCE(t.months_left, 0) AS months_left,
            COALESCE(t.status, 'active') AS status,
            t.started_at
        FROM {$qt} t
        WHERE t.tenant_id = ?
          AND t.patient_id = ?
          AND COALESCE(t.total_cost, 0) > 0
          AND LOWER(TRIM(COALESCE(t.status, ''))) NOT IN ('completed', 'cancelled')
        ORDER BY t.started_at DESC, t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $patientId]);
    $treatment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$treatment) {
        echo json_encode([
            'success' => true,
            'message' => 'No active installment treatment found.',
            'data' => [
                'has_active_treatment' => false,
                'has_active_appointment_today' => $hasActiveAppointmentToday,
                'eligible_for_new_treatment_plan' => true,
                'treatment_plan_closed_reason' => null,
            ],
        ]);
        exit;
    }

    if (patient_treatment_plan_sidebar_finished($pdo, $tenantId, $treatment, $patientId, $appointmentsTable, $installmentsTable)) {
        patient_treatment_mark_plan_completed($pdo, $qt, $tenantId, $patientId, (string) ($treatment['treatment_id'] ?? ''));
        echo json_encode([
            'success' => true,
            'message' => 'Installment treatment plan has been completed.',
            'data' => [
                'has_active_treatment' => false,
                'has_active_appointment_today' => $hasActiveAppointmentToday,
                'eligible_for_new_treatment_plan' => true,
                'treatment_plan_closed_reason' => 'fully_settled_installments_complete',
                'completed_treatment_id' => (string) ($treatment['treatment_id'] ?? ''),
            ],
        ]);
        exit;
    }

    $primaryServiceId = (string) ($treatment['primary_service_id'] ?? '');
    $serviceStmt = $pdo->prepare("
        SELECT service_id, service_name, category, price, enable_installment,
               installment_duration_months, installment_downpayment
        FROM {$qs}
        WHERE tenant_id = ? AND service_id = ?
        LIMIT 1
    ");
    $serviceStmt->execute([$tenantId, $primaryServiceId]);
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // Authoritative source for walk-in treatment progress cards:
    // use the persisted treatment snapshot only (tbl_treatments).
    $totalCost = max(0.0, (float) ($treatment['total_cost'] ?? 0));
    $amountPaid = max(0.0, (float) ($treatment['amount_paid'] ?? 0));
    $remainingBalance = max(0.0, (float) ($treatment['remaining_balance'] ?? 0));
    if ($totalCost > 0 && $amountPaid > $totalCost) {
        $amountPaid = $totalCost;
    }
    if ($totalCost > 0 && $remainingBalance > $totalCost) {
        $remainingBalance = $totalCost;
    }
    $progressPct = $totalCost > 0 ? min(100.0, max(0.0, ($amountPaid / $totalCost) * 100.0)) : 0.0;

    $durationMonths = (int) ($treatment['duration_months'] ?? 0);
    $targetCompletionYmd = patient_treatment_compute_target_completion_date(
        (string) ($treatment['started_at'] ?? ''),
        $durationMonths
    );
    $snapshotServiceName = trim((string) ($treatment['primary_service_name'] ?? ''));

    echo json_encode([
        'success' => true,
        'message' => 'Treatment context loaded.',
        'data' => [
            'has_active_treatment' => true,
            'has_active_appointment_today' => $hasActiveAppointmentToday,
            'eligible_for_new_treatment_plan' => false,
            'treatment_plan_closed_reason' => null,
            'treatment' => [
                'treatment_id' => (string) ($treatment['treatment_id'] ?? ''),
                'patient_id' => (string) ($treatment['patient_id'] ?? ''),
                'status' => (string) ($treatment['status'] ?? 'active'),
                'total_cost' => round($totalCost, 2),
                'amount_paid' => round($amountPaid, 2),
                'remaining_balance' => round($remainingBalance, 2),
                'duration_months' => $durationMonths,
                'months_paid' => (int) ($treatment['months_paid'] ?? 0),
                'months_left' => (int) ($treatment['months_left'] ?? 0),
                'installment_total_slots' => null,
                'installment_settled_slots' => null,
                'installment_total_amount' => null,
                'installment_paid_amount' => null,
                'progress_percentage' => round($progressPct, 2),
                'started_at' => (string) ($treatment['started_at'] ?? ''),
                /** Snapshot from tbl_treatments (authoritative label at creation). */
                'primary_service_name' => $snapshotServiceName,
                /** Y-m-d from started_at + duration_months, matching ledger semantics. */
                'target_completion_date' => $targetCompletionYmd,
                'primary_service' => $service,
            ],
        ],
    ]);
    exit;
} catch (Throwable $e) {
    error_log('patient_treatment_context error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load treatment context.',
    ]);
    exit;
}
