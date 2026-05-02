<?php
/**
 * Staff: installment schedule + totals for Treatment Progress modal (tbl_treatments + tbl_installments).
 */
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
$treatmentId = trim((string) ($_GET['treatment_id'] ?? ''));
if ($patientId === '' || $treatmentId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'patient_id and treatment_id are required.']);
    exit;
}

/**
 * @return 'booked'|'book_visit'|'pending'
 */
function treatment_progress_ui_bucket(string $status): string
{
    $s = strtolower(trim($status));
    if (in_array($s, ['paid', 'completed'], true)) {
        return 'booked';
    }
    if ($s === 'book_visit') {
        return 'book_visit';
    }
    return 'pending';
}

try {
    $pdo = getDBConnection();
    $dbTables = clinic_resolve_appointment_db_tables($pdo);
    $treatmentsTable = $dbTables['treatments'];
    $appointmentsTable = $dbTables['appointments'];
    if ($treatmentsTable === null) {
        echo json_encode(['success' => false, 'message' => 'Treatments table not available.']);
        exit;
    }

    $qt = clinic_quote_identifier($treatmentsTable);
    $tStmt = $pdo->prepare("
        SELECT
            treatment_id,
            patient_id,
            COALESCE(total_cost, 0) AS total_cost,
            COALESCE(amount_paid, 0) AS amount_paid,
            COALESCE(remaining_balance, 0) AS remaining_balance,
            COALESCE(status, 'active') AS status
        FROM {$qt}
        WHERE tenant_id = ?
          AND patient_id = ?
          AND treatment_id = ?
        LIMIT 1
    ");
    $tStmt->execute([$tenantId, $patientId, $treatmentId]);
    $treatment = $tStmt->fetch(PDO::FETCH_ASSOC);
    if (!$treatment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Treatment not found for this patient.']);
        exit;
    }

    $totalCost = max(0.0, (float) ($treatment['total_cost'] ?? 0));
    $amountPaid = max(0.0, (float) ($treatment['amount_paid'] ?? 0));
    if ($totalCost > 0 && $amountPaid > $totalCost) {
        $amountPaid = $totalCost;
    }
    $progressPct = $totalCost > 0 ? min(100.0, max(0.0, ($amountPaid / $totalCost) * 100.0)) : 0.0;

    $bookingId = '';
    $appointmentDate = '';
    $appointmentTime = '';
    if ($appointmentsTable !== null) {
        $apptCols = clinic_table_columns($pdo, $appointmentsTable);
        $hasTreatmentCol = in_array('treatment_id', $apptCols, true);
        $hasCreated = in_array('created_at', $apptCols, true);
        $hasApptDate = in_array('appointment_date', $apptCols, true);
        $qa = clinic_quote_identifier($appointmentsTable);
        if ($hasCreated) {
            $orderBy = 'a.created_at DESC';
        } elseif ($hasApptDate) {
            $orderBy = 'a.appointment_date DESC';
        } else {
            $orderBy = 'a.booking_id DESC';
        }
        if ($hasTreatmentCol) {
            $bStmt = $pdo->prepare("
                SELECT a.booking_id,
                       COALESCE(a.appointment_date, '') AS appointment_date,
                       COALESCE(a.appointment_time, '') AS appointment_time
                FROM {$qa} a
                WHERE a.tenant_id = ?
                  AND a.patient_id = ?
                  AND TRIM(COALESCE(a.treatment_id, '')) = ?
                ORDER BY {$orderBy}
                LIMIT 1
            ");
            $bStmt->execute([$tenantId, $patientId, $treatmentId]);
            $br = $bStmt->fetch(PDO::FETCH_ASSOC);
            if ($br) {
                $bookingId = trim((string) ($br['booking_id'] ?? ''));
                $appointmentDate = trim((string) ($br['appointment_date'] ?? ''));
                $appointmentTime = trim((string) ($br['appointment_time'] ?? ''));
            }
        }
    }

    $steps = [];
    $installmentsTable = clinic_get_physical_table_name($pdo, 'tbl_installments')
        ?? clinic_get_physical_table_name($pdo, 'installments');

    if ($bookingId !== '' && $installmentsTable !== null) {
        $instCols = clinic_table_columns($pdo, $installmentsTable);
        $qi = clinic_quote_identifier($installmentsTable);
        $sel = ['i.installment_number', 'i.amount_due', 'i.status'];
        if (in_array('scheduled_date', $instCols, true)) {
            $sel[] = 'COALESCE(i.scheduled_date, \'\') AS scheduled_date';
        } else {
            $sel[] = '\'\' AS scheduled_date';
        }
        if (in_array('scheduled_time', $instCols, true)) {
            $sel[] = 'COALESCE(i.scheduled_time, \'\') AS scheduled_time';
        } else {
            $sel[] = '\'\' AS scheduled_time';
        }
        if (in_array('due_date', $instCols, true)) {
            $sel[] = 'COALESCE(i.due_date, \'\') AS due_date';
        } else {
            $sel[] = '\'\' AS due_date';
        }
        $sql = 'SELECT ' . implode(', ', $sel) . " FROM {$qi} i WHERE i.booking_id = ? ";
        $sql .= 'AND (i.tenant_id = ? OR i.tenant_id IS NULL OR TRIM(COALESCE(i.tenant_id, \'\')) = \'\') ';
        $sql .= 'ORDER BY i.installment_number ASC';
        $iStmt = $pdo->prepare($sql);
        $iStmt->execute([$bookingId, $tenantId]);
        $rows = $iStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $num = (int) ($row['installment_number'] ?? 0);
            $rawStatus = trim((string) ($row['status'] ?? ''));
            $bucket = treatment_progress_ui_bucket($rawStatus);
            $amountDue = round(max(0.0, (float) ($row['amount_due'] ?? 0)), 2);

            $scheduleDisplay = null;
            if ($num === 1 && $appointmentDate !== '') {
                $scheduleDisplay = treatment_progress_format_schedule_cell($appointmentDate, $appointmentTime);
            } else {
                $sd = trim((string) ($row['scheduled_date'] ?? ''));
                $st = trim((string) ($row['scheduled_time'] ?? ''));
                if ($sd !== '') {
                    $scheduleDisplay = treatment_progress_format_schedule_cell($sd, $st);
                } else {
                    $dd = trim((string) ($row['due_date'] ?? ''));
                    if ($dd !== '') {
                        $scheduleDisplay = treatment_progress_format_schedule_date_only($dd);
                    }
                }
            }

            $label = $bucket === 'booked' ? 'Booked' : ($bucket === 'book_visit' ? 'Book visit' : 'Pending');
            $action = 'dash';
            if ($bucket === 'book_visit') {
                $action = 'book';
            } elseif ($bucket === 'pending') {
                $action = 'pending_text';
            }

            $steps[] = [
                'step_code' => 'T' . $num,
                'installment_number' => $num,
                'status_bucket' => $bucket,
                'status_label' => $label,
                'amount_due' => $amountDue,
                'schedule_display' => $scheduleDisplay,
                'action' => $action,
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Treatment progress loaded.',
        'data' => [
            'booking_id' => $bookingId,
            'treatment' => [
                'treatment_id' => (string) ($treatment['treatment_id'] ?? ''),
                'total_cost' => round($totalCost, 2),
                'amount_paid' => round($amountPaid, 2),
                'progress_percentage' => round($progressPct, 2),
            ],
            'steps' => $steps,
        ],
    ]);
    exit;
} catch (Throwable $e) {
    error_log('treatment_progress_modal error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load treatment progress.']);
    exit;
}

function treatment_progress_format_schedule_cell(string $dateYmd, string $timeRaw): string
{
    $ts = strtotime($dateYmd);
    if ($ts === false) {
        return $dateYmd;
    }
    $datePart = date('M j, Y', $ts);
    $t = trim($timeRaw);
    if ($t === '') {
        return $datePart;
    }
    $hm = strlen($t) >= 5 ? substr($t, 0, 5) : $t;
    $trial = strtotime('2000-01-01 ' . $hm);
    $timePart = $trial !== false ? date('g:i A', $trial) : $hm;
    return $datePart . ' ' . $timePart;
}

function treatment_progress_format_schedule_date_only(string $dateYmd): string
{
    $ts = strtotime($dateYmd);
    if ($ts === false) {
        return $dateYmd;
    }
    return date('M j, Y', $ts);
}
