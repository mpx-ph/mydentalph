<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/appointment_db_tables.php';

header('Content-Type: application/json; charset=utf-8');

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
          AND LOWER(COALESCE(t.status, 'active')) = 'active'
        ORDER BY t.started_at DESC, t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $patientId]);
    $treatment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$treatment) {
        echo json_encode([
            'success' => true,
            'message' => 'No active installment treatment found.',
            'data' => ['has_active_treatment' => false],
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

    $paymentsTotalPaid = null;
    if ($paymentsTable !== null) {
        $paymentsCols = clinic_table_columns($pdo, $paymentsTable);
        $appointmentsCols = $appointmentsTable !== null ? clinic_table_columns($pdo, $appointmentsTable) : [];
        $paymentsHasTreatmentId = in_array('treatment_id', $paymentsCols, true);
        $paymentsHasBookingId = in_array('booking_id', $paymentsCols, true);
        $appointmentsHasTreatmentId = in_array('treatment_id', $appointmentsCols, true);
        $appointmentsHasBookingId = in_array('booking_id', $appointmentsCols, true);
        $canJoinByBooking = $qa !== null && $paymentsHasBookingId && $appointmentsHasBookingId && $appointmentsHasTreatmentId;
        $treatmentId = (string) ($treatment['treatment_id'] ?? '');

        if ($treatmentId !== '' && $qp !== null) {
            if ($paymentsHasTreatmentId && $canJoinByBooking) {
                $paidStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(py.amount), 0) AS total_paid
                    FROM {$qp} py
                    WHERE py.tenant_id = ?
                      AND LOWER(COALESCE(py.status, '')) = 'completed'
                      AND (
                            py.treatment_id = ?
                            OR (
                                COALESCE(py.treatment_id, '') = ''
                                AND EXISTS (
                                    SELECT 1
                                    FROM {$qa} a
                                    WHERE a.tenant_id = py.tenant_id
                                      AND a.booking_id = py.booking_id
                                      AND a.treatment_id = ?
                                )
                            )
                      )
                ");
                $paidStmt->execute([$tenantId, $treatmentId, $treatmentId]);
                $paymentsTotalPaid = (float) ($paidStmt->fetchColumn() ?? 0);
            } elseif ($paymentsHasTreatmentId) {
                $paidStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(py.amount), 0) AS total_paid
                    FROM {$qp} py
                    WHERE py.tenant_id = ?
                      AND LOWER(COALESCE(py.status, '')) = 'completed'
                      AND py.treatment_id = ?
                ");
                $paidStmt->execute([$tenantId, $treatmentId]);
                $paymentsTotalPaid = (float) ($paidStmt->fetchColumn() ?? 0);
            } elseif ($canJoinByBooking) {
                $paidStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(py.amount), 0) AS total_paid
                    FROM {$qp} py
                    INNER JOIN {$qa} a
                      ON a.tenant_id = py.tenant_id
                     AND a.booking_id = py.booking_id
                    WHERE py.tenant_id = ?
                      AND LOWER(COALESCE(py.status, '')) = 'completed'
                      AND a.treatment_id = ?
                ");
                $paidStmt->execute([$tenantId, $treatmentId]);
                $paymentsTotalPaid = (float) ($paidStmt->fetchColumn() ?? 0);
            }
        }
    }

    $computedMonthsLeft = null;
    $computedInstallmentTotalAmount = null;
    $computedInstallmentPaidAmount = null;
    $computedInstallmentTotalSlots = null;
    $computedInstallmentSettledSlots = null;
    if ($installmentsTable !== null) {
        $installmentsCols = clinic_table_columns($pdo, $installmentsTable);
        $installmentsHasTreatmentId = in_array('treatment_id', $installmentsCols, true);
        $installmentsHasBookingId = in_array('booking_id', $installmentsCols, true);
        $installmentsHasStatus = in_array('status', $installmentsCols, true);
        $installmentsHasAmountDue = in_array('amount_due', $installmentsCols, true);
        $appointmentsColsForInstallments = $appointmentsTable !== null ? clinic_table_columns($pdo, $appointmentsTable) : [];
        $appointmentsHasTreatmentIdForInstallments = in_array('treatment_id', $appointmentsColsForInstallments, true);
        $appointmentsHasBookingIdForInstallments = in_array('booking_id', $appointmentsColsForInstallments, true);
        $canJoinInstallmentsByBooking = $qa !== null
            && $installmentsHasBookingId
            && $appointmentsHasBookingIdForInstallments
            && $appointmentsHasTreatmentIdForInstallments;
        $qi = clinic_quote_identifier($installmentsTable);
        $treatmentId = (string) ($treatment['treatment_id'] ?? '');

        if ($treatmentId !== '' && $installmentsHasStatus) {
            $slotAmountExpr = $installmentsHasAmountDue ? 'MAX(COALESCE(i.amount_due, 0))' : '0';
            if ($installmentsHasTreatmentId && $canJoinInstallmentsByBooking) {
                $monthsStmt = $pdo->prepare("
                    SELECT
                        COUNT(*) AS total_slots,
                        COALESCE(SUM(slot_group.slot_settled), 0) AS settled_slots,
                        COALESCE(SUM(slot_group.slot_amount), 0) AS total_amount,
                        COALESCE(SUM(CASE WHEN slot_group.slot_settled = 1 THEN slot_group.slot_amount ELSE 0 END), 0) AS settled_amount
                    FROM (
                        SELECT
                            COALESCE(NULLIF(CAST(i.installment_number AS CHAR), ''), CONCAT('row-', i.id)) AS slot_key,
                            MAX(CASE WHEN LOWER(COALESCE(i.status, '')) IN ('paid','completed') THEN 1 ELSE 0 END) AS slot_settled,
                            {$slotAmountExpr} AS slot_amount
                        FROM {$qi} i
                        WHERE i.tenant_id = ?
                          AND (
                                i.treatment_id = ?
                                OR (
                                    COALESCE(i.treatment_id, '') = ''
                                    AND EXISTS (
                                        SELECT 1
                                        FROM {$qa} a
                                        WHERE a.tenant_id = i.tenant_id
                                          AND a.booking_id = i.booking_id
                                          AND a.treatment_id = ?
                                    )
                                )
                          )
                        GROUP BY slot_key
                    ) AS slot_group
                ");
                $monthsStmt->execute([$tenantId, $treatmentId, $treatmentId]);
            } elseif ($installmentsHasTreatmentId) {
                $monthsStmt = $pdo->prepare("
                    SELECT
                        COUNT(*) AS total_slots,
                        COALESCE(SUM(slot_group.slot_settled), 0) AS settled_slots,
                        COALESCE(SUM(slot_group.slot_amount), 0) AS total_amount,
                        COALESCE(SUM(CASE WHEN slot_group.slot_settled = 1 THEN slot_group.slot_amount ELSE 0 END), 0) AS settled_amount
                    FROM (
                        SELECT
                            COALESCE(NULLIF(CAST(i.installment_number AS CHAR), ''), CONCAT('row-', i.id)) AS slot_key,
                            MAX(CASE WHEN LOWER(COALESCE(i.status, '')) IN ('paid','completed') THEN 1 ELSE 0 END) AS slot_settled,
                            {$slotAmountExpr} AS slot_amount
                        FROM {$qi} i
                        WHERE i.tenant_id = ?
                          AND i.treatment_id = ?
                        GROUP BY slot_key
                    ) AS slot_group
                ");
                $monthsStmt->execute([$tenantId, $treatmentId]);
            } elseif ($canJoinInstallmentsByBooking) {
                $monthsStmt = $pdo->prepare("
                    SELECT
                        COUNT(*) AS total_slots,
                        COALESCE(SUM(slot_group.slot_settled), 0) AS settled_slots,
                        COALESCE(SUM(slot_group.slot_amount), 0) AS total_amount,
                        COALESCE(SUM(CASE WHEN slot_group.slot_settled = 1 THEN slot_group.slot_amount ELSE 0 END), 0) AS settled_amount
                    FROM (
                        SELECT
                            COALESCE(NULLIF(CAST(i.installment_number AS CHAR), ''), CONCAT('row-', i.id)) AS slot_key,
                            MAX(CASE WHEN LOWER(COALESCE(i.status, '')) IN ('paid','completed') THEN 1 ELSE 0 END) AS slot_settled,
                            {$slotAmountExpr} AS slot_amount
                        FROM {$qi} i
                        INNER JOIN {$qa} a
                          ON a.tenant_id = i.tenant_id
                         AND a.booking_id = i.booking_id
                        WHERE i.tenant_id = ?
                          AND a.treatment_id = ?
                        GROUP BY slot_key
                    ) AS slot_group
                ");
                $monthsStmt->execute([$tenantId, $treatmentId]);
            } else {
                $monthsStmt = null;
            }

            if (isset($monthsStmt) && $monthsStmt !== null) {
                $rows = $monthsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $totalSlots = (int) ($rows['total_slots'] ?? 0);
                $settledSlots = (int) ($rows['settled_slots'] ?? 0);
                $totalAmount = (float) ($rows['total_amount'] ?? 0);
                $settledAmount = (float) ($rows['settled_amount'] ?? 0);
                if ($totalSlots > 0) {
                    $computedMonthsLeft = max(0, $totalSlots - $settledSlots);
                    $computedInstallmentTotalSlots = $totalSlots;
                    $computedInstallmentSettledSlots = max(0, min($totalSlots, $settledSlots));
                }
                if ($totalAmount > 0) {
                    $computedInstallmentTotalAmount = max(0.0, $totalAmount);
                    $computedInstallmentPaidAmount = max(0.0, min($computedInstallmentTotalAmount, $settledAmount));
                }
            }
        }
    }

    $rawTotalCost = (float) ($treatment['total_cost'] ?? 0);
    $rawAmountPaid = $paymentsTotalPaid !== null
        ? $paymentsTotalPaid
        : (float) ($treatment['amount_paid'] ?? 0);
    $totalCost = $computedInstallmentTotalAmount !== null && $computedInstallmentTotalAmount > 0
        ? (float) $computedInstallmentTotalAmount
        : $rawTotalCost;
    $amountPaid = $computedInstallmentPaidAmount !== null && $computedInstallmentPaidAmount > 0
        ? (float) $computedInstallmentPaidAmount
        : $rawAmountPaid;
    if ($amountPaid > $totalCost && $totalCost > 0) {
        $amountPaid = $totalCost;
    }
    $remainingBalance = max(0.0, $totalCost - $amountPaid);
    $storedRemainingBalance = max(0.0, (float) ($treatment['remaining_balance'] ?? 0));
    if ($computedInstallmentTotalAmount !== null && $computedInstallmentTotalAmount > 0) {
        $remainingBalance = max($remainingBalance, $storedRemainingBalance);
        if ($remainingBalance > $totalCost) {
            $remainingBalance = $totalCost;
        }
        $amountPaid = max(0.0, $totalCost - $remainingBalance);
    }
    if ($totalCost > 0 && $remainingBalance < 0) {
        $remainingBalance = 0.0;
    }
    $progressPct = $totalCost > 0 ? min(100.0, max(0.0, ($amountPaid / $totalCost) * 100.0)) : 0.0;

    echo json_encode([
        'success' => true,
        'message' => 'Treatment context loaded.',
        'data' => [
            'has_active_treatment' => true,
            'treatment' => [
                'treatment_id' => (string) ($treatment['treatment_id'] ?? ''),
                'patient_id' => (string) ($treatment['patient_id'] ?? ''),
                'status' => (string) ($treatment['status'] ?? 'active'),
                'total_cost' => round($totalCost, 2),
                'amount_paid' => round($amountPaid, 2),
                'remaining_balance' => round($remainingBalance, 2),
                'duration_months' => (int) ($treatment['duration_months'] ?? 0),
                'months_paid' => (int) ($treatment['months_paid'] ?? 0),
                'months_left' => $computedMonthsLeft !== null
                    ? (int) $computedMonthsLeft
                    : (int) ($treatment['months_left'] ?? 0),
                'installment_total_slots' => $computedInstallmentTotalSlots !== null ? (int) $computedInstallmentTotalSlots : null,
                'installment_settled_slots' => $computedInstallmentSettledSlots !== null ? (int) $computedInstallmentSettledSlots : null,
                'installment_total_amount' => $computedInstallmentTotalAmount !== null ? round((float) $computedInstallmentTotalAmount, 2) : null,
                'installment_paid_amount' => $computedInstallmentPaidAmount !== null ? round((float) $computedInstallmentPaidAmount, 2) : null,
                'progress_percentage' => round($progressPct, 2),
                'started_at' => (string) ($treatment['started_at'] ?? ''),
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
