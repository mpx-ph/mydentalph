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
 * Split total cost into N monthly amounts (remainder on last month).
 *
 * @return list<float>
 */
function treatment_progress_split_total_by_months(float $totalCost, int $months): array
{
    $months = max(0, $months);
    if ($months === 0) {
        return [];
    }
    $totalCents = (int) round(max(0.0, $totalCost) * 100);
    $base = intdiv($totalCents, $months);
    $rem = $totalCents % $months;
    $out = [];
    for ($i = 0; $i < $months; $i++) {
        $cents = $base + ($i === $months - 1 ? $rem : 0);
        $out[] = round($cents / 100, 2);
    }

    return $out;
}

/**
 * Split a total evenly across exactly N installments (remainder on the last slice).
 *
 * @return list<float>
 */
function treatment_progress_split_remainder_across_n(float $total, int $n): array
{
    $n = max(1, $n);
    $totalCents = (int) round(max(0.0, $total) * 100);
    $base = intdiv($totalCents, $n);
    $rem = $totalCents % $n;
    $out = [];
    for ($i = 0; $i < $n; $i++) {
        $cents = $base + ($i === $n - 1 ? $rem : 0);
        $out[] = round($cents / 100, 2);
    }

    return $out;
}

/**
 * @return array{regular_downpayment_percentage:float,long_term_min_downpayment:float}
 */
function treatment_progress_load_payment_settings(PDO $pdo, string $tenantId): array
{
    try {
        $stmt = $pdo->prepare('SELECT regular_downpayment_percentage, long_term_min_downpayment FROM tbl_payment_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = false;
    }
    if (!$row) {
        return ['regular_downpayment_percentage' => 0.0, 'long_term_min_downpayment' => 0.0];
    }

    return [
        'regular_downpayment_percentage' => (float) ($row['regular_downpayment_percentage'] ?? 0),
        'long_term_min_downpayment' => (float) ($row['long_term_min_downpayment'] ?? 0),
    ];
}

function treatment_progress_effective_installment_downpayment(array $paymentSettings, float $planTotal): float
{
    $base = max(0.0, (float) ($paymentSettings['long_term_min_downpayment'] ?? 0.0));
    if ($planTotal > 0 && $base > $planTotal) {
        return round($planTotal, 2);
    }

    return round($base, 2);
}

/**
 * @return ''|numeric-string-date 'Y-m-d'
 */
function treatment_progress_normalize_visit_date(string $input): string
{
    $t = trim($input);
    if ($t === '') {
        return '';
    }
    if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})/', $t, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    $ts = strtotime($t);

    return $ts !== false ? date('Y-m-d', $ts) : '';
}

/**
 * Mirrors StaffAppointments.php staff_appointments_resolve_status_for_ui (label + slug only for API JSON).
 *
 * @param array<string, string> $labelMap
 * @return array{code: string, label: string}
 */
function treatment_progress_staff_appointment_status_resolve(string $raw, array $labelMap): array
{
    $original = (string) $raw;
    $s = $original;
    $s = preg_replace('/\x{FEFF}|\x{200B}|\x{00AD}/u', '', (string) $s);
    $s = preg_replace('/\p{Zs}/u', ' ', (string) $s);
    $s = trim((string) $s);

    $slug = strtolower(str_replace([' ', '-'], '_', (string) $s));
    $slug = (string) preg_replace('/_+/', '_', $slug);
    if ($slug === 'inprogress' || $slug === 'ongoing') {
        $slug = 'in_progress';
    }
    if ($original !== '' && $s === '' && $slug === '') {
        $slug = 'pending';
    }
    if ($slug === '' || $slug === '0') {
        $slug = 'pending';
    } elseif (in_array($slug, ['confirmed', 'scheduled'], true)) {
        $slug = 'pending';
    }

    $known = ['pending', 'in_progress', 'completed', 'cancelled', 'no_show'];
    $label = $labelMap[$slug] ?? '';
    if (trim((string) $label) === '' && in_array($slug, $known, true)) {
        $label = $labelMap['pending'] ?? 'Pending';
    } elseif (trim((string) $label) === '' && $slug !== '') {
        $label = ucwords(str_replace('_', ' ', (string) $slug));
    }
    if (trim((string) $label) === '') {
        $label = (string) ($labelMap['pending'] ?? 'Pending');
    }

    return [
        'code' => $slug,
        'label' => $label,
    ];
}

/**
 * Buckets consumed by StaffManagePatient treatmentProgressVisitBadgeClass (StaffAppointments color parity).
 */
function treatment_progress_visit_bucket_from_staff_appointment_code(string $code): string
{
    if ($code === 'in_progress') {
        return 'in_progress';
    }
    if ($code === 'cancelled') {
        return 'cancelled';
    }
    if ($code === 'no_show') {
        return 'no_show';
    }
    if ($code === 'completed') {
        return 'completed';
    }

    return 'appt_pending';
}

/**
 * For installment rows aligned with tbl_appointments (next unpaid step or slot date equals master appointment_date),
 * mirror Staff Appointments Daily Schedule status (visit label + badge bucket).
 *
 * @param list<array<string,mixed>> $steps
 */
function treatment_progress_overlay_staff_appointment_visit_status(array &$steps, string $appointmentStatusRaw, string $masterAppointmentDate): void
{
    $appointmentStatusRaw = trim($appointmentStatusRaw);
    if ($appointmentStatusRaw === '' || $steps === []) {
        return;
    }

    $focusNum = null;
    foreach ($steps as $st) {
        $p = (($st['payment_bucket'] ?? '') === 'paid');
        $vd = (!empty($st['visit_completed']));
        if (!$p || !$vd) {
            $n = (int) ($st['installment_number'] ?? 0);
            if ($n > 0) {
                $focusNum = $n;
            }
            break;
        }
    }
    if ($focusNum === null) {
        return;
    }

    $masterYmd = treatment_progress_normalize_visit_date($masterAppointmentDate);

    $labelMap = [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No Show',
        'scheduled' => 'Pending',
        'confirmed' => 'Pending',
    ];
    $resolved = treatment_progress_staff_appointment_status_resolve($appointmentStatusRaw, $labelMap);
    foreach ($steps as &$st) {
        if (!empty($st['visit_completed'])) {
            continue;
        }
        $numSt = (int) ($st['installment_number'] ?? 0);
        $slotYmd = trim((string) ($st['visit_slot_date'] ?? ''));
        $isFocusedUnpaid = $numSt === $focusNum;
        $slotMatchesMaster = $masterYmd !== '' && $slotYmd !== '' && $slotYmd === $masterYmd;
        if (!$isFocusedUnpaid && !$slotMatchesMaster) {
            continue;
        }
        $st['visit_status'] = $resolved['label'];
        $st['visit_bucket'] = treatment_progress_visit_bucket_from_staff_appointment_code($resolved['code']);
    }
    unset($st);
}

/**
 * Align Amount column with payment recording semantics: installment #1 = down payment (when DM>1 and down is configured).
 * Subsequent rows split the remaining balance evenly (same as tbl_installments / StaffPaymentRecording plan builder).
 *
 * @param list<array<string,mixed>> $steps
 */
function treatment_progress_apply_plan_amounts_for_display(array &$steps, float $totalCost, int $durationMonthsTreatment, array $paymentSettings): void
{
    $nSteps = count($steps);
    if ($nSteps < 1 || $totalCost <= 0.009) {
        return;
    }

    usort($steps, static function (array $a, array $b): int {
        return ((int) ($a['installment_number'] ?? 0)) <=> ((int) ($b['installment_number'] ?? 0));
    });

    $dm = max(0, $durationMonthsTreatment);
    $effDown = treatment_progress_effective_installment_downpayment($paymentSettings, $totalCost);

    if ($nSteps >= 2 && $dm >= 2 && $effDown > 0.009) {
        $downAmt = round(min($effDown, $totalCost), 2);
        $row1Indexes = [];
        $monthIdx = [];
        foreach ($steps as $idx => $row) {
            $num = (int) ($row['installment_number'] ?? ($idx + 1));
            if ($num === 1) {
                $row1Indexes[] = $idx;
            } else {
                $monthIdx[] = $idx;
            }
        }
        // Legacy / odd numbering: tie downpayment to displayed first slot.
        if ($row1Indexes === [] && $nSteps >= 2) {
            $row1Indexes = [0];
            $monthIdx = [];
            for ($zi = 1; $zi < $nSteps; $zi++) {
                $monthIdx[] = $zi;
            }
        }
        foreach ($row1Indexes as $ri) {
            $steps[$ri]['amount_due'] = $downAmt;
        }
        $rem = round(max(0.0, $totalCost - $downAmt), 2);
        $mc = count($monthIdx);
        if ($mc < 1) {
            foreach ($steps as $i => $_) {
                if ((int) ($steps[$i]['installment_number'] ?? 0) <= 1) {
                    continue;
                }
                $steps[$i]['amount_due'] = 0.0;
            }

            return;
        }
        $parts = treatment_progress_split_remainder_across_n($rem, $mc);
        foreach ($monthIdx as $j => $stepIdx) {
            $steps[$stepIdx]['amount_due'] = round($parts[$j] ?? 0.0, 2);
        }

        return;
    }

    if ($dm <= 1 && $nSteps === 1) {
        $steps[0]['amount_due'] = round($totalCost, 2);

        return;
    }

    $parts = treatment_progress_split_remainder_across_n($totalCost, $nSteps);
    foreach ($steps as $i => &$step) {
        $step['amount_due'] = round($parts[$i] ?? 0.0, 2);
    }
    unset($step);
}

/**
 * Paint visit column from payment_bucket, visit_completed, schedule slot, workflow db_raw_status.
 *
 * @param array<string, mixed> $step
 */
function treatment_progress_paint_visit_for_step(array &$step): void
{
    $paid = (($step['payment_bucket'] ?? '') === 'paid');
    $phys = ($step['has_schedule_slot'] ?? false) === true;
    $vc = (($step['visit_completed'] ?? false) === true);
    $dbRaw = strtolower(trim((string) ($step['db_raw_status'] ?? '')));

    if ($vc) {
        $step['visit_status'] = 'Completed';
        $step['visit_bucket'] = 'completed';

        return;
    }

    if ($paid) {
        if ($phys) {
            $step['visit_status'] = 'Scheduled';
            $step['visit_bucket'] = 'scheduled';
        } else {
            $step['visit_status'] = 'Pending';
            $step['visit_bucket'] = 'pending';
        }

        return;
    }

    if ($phys) {
        $step['visit_status'] = 'Scheduled';
        $step['visit_bucket'] = 'scheduled';

        return;
    }

    if ($dbRaw === 'book_visit') {
        $step['visit_status'] = 'Book visit';
        $step['visit_bucket'] = 'book_visit';

        return;
    }

    if ($dbRaw === 'locked') {
        $step['visit_status'] = 'Locked';
        $step['visit_bucket'] = 'locked';

        return;
    }

    $step['visit_status'] = 'Pending';
    $step['visit_bucket'] = 'pending';
}

/**
 * One table row for the Treatment Progress modal (installment or synthetic placeholder).
 *
 * @param array{raw_status:string,amount_due:float,schedule_display:?string,visit_slot_date?:string,installment_status_db?:string} $fields
 * @return array<string, mixed>
 */
function treatment_progress_build_step_row(int $installmentNumber, array $fields): array
{
    $scheduleDisplay = $fields['schedule_display'] ?? null;
    if ($scheduleDisplay !== null && trim((string) $scheduleDisplay) === '') {
        $scheduleDisplay = null;
    }
    $visitSlotDate = treatment_progress_normalize_visit_date((string) ($fields['visit_slot_date'] ?? ''));
    $rawWorkflow = strtolower(trim((string) ($fields['raw_status'] ?? 'pending')));
    $instDb = strtolower(trim((string) ($fields['installment_status_db'] ?? $fields['raw_status'] ?? 'pending')));
    $amountDue = round(max(0.0, (float) ($fields['amount_due'] ?? 0)), 2);

    $paymentCleared = in_array($instDb, ['paid', 'completed'], true);
    $visitCompleted = ($instDb === 'completed');

    $row = [
        'step_code' => 'T' . $installmentNumber,
        'installment_number' => $installmentNumber,
        'payment_status' => $paymentCleared ? 'Paid' : 'Unpaid',
        'payment_bucket' => $paymentCleared ? 'paid' : 'unpaid',
        'visit_completed' => $visitCompleted,
        'amount_due' => $amountDue,
        'schedule_display' => $scheduleDisplay,
        'visit_slot_date' => $visitSlotDate,
        'has_schedule_slot' => ($scheduleDisplay !== null && trim((string) $scheduleDisplay) !== ''),
        /** Authoritative lowercase installment.status from DB (never stripped during reconciliation guards). */
        'installment_status_db' => $instDb,
        /** Workflow label for unpaid visit states (pending / book_visit / …); may differ during order guards. */
        'db_raw_status' => $rawWorkflow,
    ];
    treatment_progress_paint_visit_for_step($row);

    return $row;
}

/**
 * Detect downpayment + monthly schedule (row 1 = down, rows 2+ = monthlies) vs equal monthly columns.
 *
 * @param list<array<string, mixed>> $steps
 * @return bool
 */
function treatment_progress_is_discrete_downpayment_schedule(array $steps, int $treatmentDurationMonths): bool
{
    if (count($steps) < 2) {
        return false;
    }
    $maxNum = 0;
    $a1 = 0.0;
    $a2 = 0.0;
    foreach ($steps as $s) {
        $n = (int) ($s['installment_number'] ?? 0);
        if ($n > $maxNum) {
            $maxNum = $n;
        }
    }
    $treatDur = max(0, $treatmentDurationMonths);
    if ($treatDur > 0 && $maxNum > $treatDur) {
        return true;
    }
    foreach ($steps as $s) {
        $n = (int) ($s['installment_number'] ?? 0);
        $d = round(max(0.0, (float) ($s['amount_due'] ?? 0)), 2);
        if ($n === 1) {
            $a1 = $d;
        } elseif ($n === 2) {
            $a2 = $d;
        }
    }
    if ($a1 > 0.009 && $a2 > 0.009 && abs($a1 - $a2) > 0.05) {
        return true;
    }

    return false;
}

/**
 * Reconcile per-row payment status from aggregate treatment fields (full pay, months_paid, sequential amount).
 * Ensures row progression (T1 → T2 → …) and PAY button state match payment progress.
 *
 * @param list<array<string, mixed>> $steps
 * @param array<string, mixed> $treatment
 */
function treatment_progress_reconcile_payment_states(array &$steps, array $treatment): void
{
    $n = count($steps);
    if ($n === 0) {
        return;
    }
    $eps = 0.05;
    $totalCost = max(0.0, (float) ($treatment['total_cost'] ?? 0));
    $amountPaid = max(0.0, (float) ($treatment['amount_paid'] ?? 0));
    $remainingBalance = max(0.0, (float) ($treatment['remaining_balance'] ?? 0));
    if ($totalCost > 0 && $amountPaid > $totalCost) {
        $amountPaid = $totalCost;
    }
    $monthsPaidField = max(0, (int) ($treatment['months_paid'] ?? 0));
    $durationMonths = max(0, (int) ($treatment['duration_months'] ?? 0));

    $fullySettled = $totalCost > 0 && ($remainingBalance <= $eps || $amountPaid >= $totalCost - $eps);
    if ($fullySettled) {
        foreach ($steps as &$st) {
            $st['payment_status'] = 'Paid';
            $st['payment_bucket'] = 'paid';
            $st['visit_completed'] = true;
            treatment_progress_paint_visit_for_step($st);
        }
        unset($st);

        return;
    }

    $row1Discrete = treatment_progress_is_discrete_downpayment_schedule($steps, $durationMonths);
    $sequentialFlags = array_fill(0, $n, false);
    $run = $amountPaid;
    for ($i = 0; $i < $n; $i++) {
        $due = round(max(0.0, (float) ($steps[$i]['amount_due'] ?? 0)), 2);
        if ($due <= 0.009) {
            $sequentialFlags[$i] = true;

            continue;
        }
        if ($run + $eps >= $due) {
            $sequentialFlags[$i] = true;
            $run = round($run - $due, 2);
            if ($run < 0) {
                $run = 0.0;
            }
        } else {
            break;
        }
    }

    $rawPaid = [];
    for ($i = 0; $i < $n; $i++) {
        $num = (int) ($steps[$i]['installment_number'] ?? ($i + 1));
        $idb = strtolower(trim((string) ($steps[$i]['installment_status_db'] ?? '')));
        $dbPaid = in_array($idb, ['paid', 'completed'], true);
        $seqPaid = $sequentialFlags[$i];
        $monthsSlotPaid = false;
        if ($row1Discrete && $num >= 2 && $monthsPaidField > 0) {
            $monthsSlotPaid = $monthsPaidField >= ($num - 1);
        }
        $rawPaid[$i] = $dbPaid || $seqPaid || $monthsSlotPaid;
    }

    // Strict installment order: row T(k+1) cannot show PAID before row T(k).
    $finalPaid = [];
    for ($i = 0; $i < $n; $i++) {
        if ($i === 0) {
            $finalPaid[$i] = $rawPaid[$i];
        } else {
            $finalPaid[$i] = $rawPaid[$i] && $finalPaid[$i - 1];
        }
    }

    for ($i = 0; $i < $n; $i++) {
        if ($finalPaid[$i]) {
            $idb = strtolower(trim((string) ($steps[$i]['installment_status_db'] ?? '')));
            $steps[$i]['payment_status'] = 'Paid';
            $steps[$i]['payment_bucket'] = 'paid';
            $steps[$i]['visit_completed'] = ($idb === 'completed');
            treatment_progress_paint_visit_for_step($steps[$i]);
        } else {
            $num = (int) ($steps[$i]['installment_number'] ?? ($i + 1));
            $idbKeep = strtolower(trim((string) ($steps[$i]['installment_status_db'] ?? '')));
            $rawBack = strtolower((string) ($steps[$i]['db_raw_status'] ?? 'pending'));
            if (in_array($rawBack, ['paid', 'completed'], true)) {
                // Installment order guard: cannot display PAID until all prior rows are settled.
                $rawBack = 'pending';
            }
            $sched = $steps[$i]['schedule_display'] ?? null;
            $due = (float) ($steps[$i]['amount_due'] ?? 0);
            $slotPres = trim((string) ($steps[$i]['visit_slot_date'] ?? ''));
            $steps[$i] = treatment_progress_build_step_row($num, [
                'raw_status' => $rawBack,
                'installment_status_db' => $idbKeep !== '' ? $idbKeep : $rawBack,
                'amount_due' => $due,
                'schedule_display' => $sched,
                'visit_slot_date' => $slotPres,
            ]);
            $steps[$i]['payment_status'] = 'Unpaid';
            $steps[$i]['payment_bucket'] = 'unpaid';
            $steps[$i]['visit_completed'] = false;
            treatment_progress_paint_visit_for_step($steps[$i]);
        }
    }
}

/**
 * tbl_installments may mark the "next" slot as book_visit when the plan is seeded; that status is for backend/workflow.
 * In this modal, unpaid rows without a scheduled datetime should read as Pending (same as later rows), not Book visit.
 *
 * @param list<array<string,mixed>> $steps
 */
function treatment_progress_modal_flatten_book_visit_to_pending(array &$steps): void
{
    foreach ($steps as &$st) {
        if (($st['payment_bucket'] ?? '') === 'paid') {
            continue;
        }
        $sd = $st['schedule_display'] ?? null;
        $hasSchedule = $sd !== null && trim((string) $sd) !== '';
        if ($hasSchedule) {
            continue;
        }
        if ((string) ($st['visit_bucket'] ?? '') === 'book_visit') {
            $st['visit_status'] = 'Pending';
            $st['visit_bucket'] = 'pending';
        }
    }
    unset($st);
}

/**
 * Progressive unlock: prior rows must be paid; SCHEDULE only after current row paid and no visit slot yet.
 *
 * @param list<array<string,mixed>> $steps
 */
function treatment_progress_apply_progressive_action_flags(array &$steps): void
{
    $n = count($steps);
    for ($i = 0; $i < $n; $i++) {
        $priorChainGate = true;
        for ($j = 0; $j < $i; $j++) {
            $priorPaid = (($steps[$j]['payment_bucket'] ?? '') === 'paid');
            $priorVisitDone = (($steps[$j]['visit_completed'] ?? false) === true);
            if (!$priorPaid || !$priorVisitDone) {
                $priorChainGate = false;
                break;
            }
        }

        $isPaid = (($steps[$i]['payment_bucket'] ?? '') === 'paid');
        $physSlot = ($steps[$i]['has_schedule_slot'] ?? false) === true;
        $visitDoneSelf = (($steps[$i]['visit_completed'] ?? false) === true);
        $hasVisitHandled = $physSlot || $visitDoneSelf;

        // PAY: locked once this slot is paid; earlier rows must have payment + completed visit.
        $steps[$i]['pay_disabled'] = $isPaid || !$priorChainGate;

        // SCHEDULE: row 1 omits button in UI; only after payment; lock if already scheduled or visit completed; prior rows must be fully done.
        $steps[$i]['schedule_disabled'] = !$isPaid || $hasVisitHandled || !$priorChainGate;
    }
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
            COALESCE(duration_months, 0) AS duration_months,
            COALESCE(months_paid, 0) AS months_paid,
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

    $durationMonths = max(0, (int) ($treatment['duration_months'] ?? 0));
    $paymentSettings = treatment_progress_load_payment_settings($pdo, $tenantId);

    $bookingId = '';
    $appointmentDate = '';
    $appointmentTime = '';
    $appointmentRowStatus = '';
    if ($appointmentsTable !== null) {
        $apptCols = clinic_table_columns($pdo, $appointmentsTable);
        $hasTreatmentCol = in_array('treatment_id', $apptCols, true);
        $hasCreated = in_array('created_at', $apptCols, true);
        $hasApptDate = in_array('appointment_date', $apptCols, true);
        $hasStatusCol = in_array('status', $apptCols, true);
        $qa = clinic_quote_identifier($appointmentsTable);
        if ($hasCreated) {
            $orderBy = 'a.created_at DESC';
        } elseif ($hasApptDate) {
            $orderBy = 'a.appointment_date DESC';
        } else {
            $orderBy = 'a.booking_id DESC';
        }
        $statusSel = $hasStatusCol ? 'COALESCE(a.status, \'\') AS appointment_row_status' : '\'\' AS appointment_row_status';
        if ($hasTreatmentCol) {
            $bStmt = $pdo->prepare("
                SELECT a.booking_id,
                       COALESCE(a.appointment_date, '') AS appointment_date,
                       COALESCE(a.appointment_time, '') AS appointment_time,
                       {$statusSel}
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
                $appointmentRowStatus = trim((string) ($br['appointment_row_status'] ?? ''));
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
            $amountDue = round(max(0.0, (float) ($row['amount_due'] ?? 0)), 2);

            $scheduleDisplay = null;
            $visitSlotDate = '';
            if ($num === 1 && $appointmentDate !== '') {
                $scheduleDisplay = treatment_progress_format_schedule_cell($appointmentDate, $appointmentTime);
                $visitSlotDate = treatment_progress_normalize_visit_date($appointmentDate);
            } else {
                $sd = trim((string) ($row['scheduled_date'] ?? ''));
                $st = trim((string) ($row['scheduled_time'] ?? ''));
                if ($sd !== '') {
                    $scheduleDisplay = treatment_progress_format_schedule_cell($sd, $st);
                    $visitSlotDate = treatment_progress_normalize_visit_date($sd);
                } else {
                    $dd = trim((string) ($row['due_date'] ?? ''));
                    if ($dd !== '') {
                        $scheduleDisplay = treatment_progress_format_schedule_date_only($dd);
                    }
                }
            }

            $idb = strtolower(trim((string) ($row['status'] ?? '')));
            $steps[] = treatment_progress_build_step_row($num, [
                'raw_status' => $rawStatus,
                'installment_status_db' => $idb,
                'amount_due' => $amountDue,
                'schedule_display' => $scheduleDisplay,
                'visit_slot_date' => $visitSlotDate,
            ]);
        }
    }

    if ($steps === [] && $durationMonths > 0) {
        $effDownSynth = treatment_progress_effective_installment_downpayment($paymentSettings, $totalCost);
        $nth = ($durationMonths > 1 && $effDownSynth > 0.009) ? ($durationMonths + 1) : $durationMonths;
        $nth = max(1, $nth);
        $placeAmt = round($totalCost / $nth, 2);
        for ($n = 1; $n <= $nth; $n++) {
            $sched = null;
            if ($n === 1 && $appointmentDate !== '') {
                $sched = treatment_progress_format_schedule_cell($appointmentDate, $appointmentTime);
            }
            $steps[] = treatment_progress_build_step_row($n, [
                'raw_status' => 'pending',
                'installment_status_db' => 'pending',
                'amount_due' => $placeAmt,
                'schedule_display' => $sched,
                'visit_slot_date' => ($n === 1 && $appointmentDate !== '') ? treatment_progress_normalize_visit_date($appointmentDate) : '',
            ]);
        }
    }

    if ($steps !== []) {
        treatment_progress_apply_plan_amounts_for_display($steps, $totalCost, $durationMonths, $paymentSettings);
    }

    treatment_progress_reconcile_payment_states($steps, $treatment);
    treatment_progress_modal_flatten_book_visit_to_pending($steps);
    treatment_progress_overlay_staff_appointment_visit_status($steps, $appointmentRowStatus, $appointmentDate);
    treatment_progress_apply_progressive_action_flags($steps);

    echo json_encode([
        'success' => true,
        'message' => 'Treatment progress loaded.',
        'data' => [
            'booking_id' => $bookingId,
            'treatment' => [
                'treatment_id' => (string) ($treatment['treatment_id'] ?? ''),
                'total_cost' => round($totalCost, 2),
                'amount_paid' => round($amountPaid, 2),
                'remaining_balance' => round(max(0.0, (float) ($treatment['remaining_balance'] ?? 0)), 2),
                'months_paid' => (int) ($treatment['months_paid'] ?? 0),
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
