<?php

declare(strict_types=1);

require_once __DIR__ . '/appointment_db_tables.php';
require_once __DIR__ . '/treatment_duration_sync.php';

/**
 * Mark installment rows paid after a recorded payment.
 * Unlocking the next installment (pending → book_visit) happens only after Staff marks the visit appointment completed (see staff_installments_advance_after_visit_completed).
 *
 * @param list<array{id:int, installment_number:int}> $paidItems
 */
function staff_installments_apply_paid_with_unlocks(
    PDO $pdo,
    string $tenantId,
    string $bookingId,
    string $paymentId,
    string $installmentsTableName,
    array $paidItems
): void {
    if ($paidItems === []) {
        return;
    }
    $quoted = '`' . str_replace('`', '``', $installmentsTableName) . '`';
    $mark = $pdo->prepare("UPDATE {$quoted} i SET i.status = 'paid', i.payment_id = ? WHERE i.id = ? AND i.booking_id = ? AND (i.tenant_id = ? OR i.tenant_id IS NULL)");
    foreach ($paidItems as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $mark->execute([$paymentId, $id, $bookingId, $tenantId]);
    }
}

/**
 * When staff marks an appointment visit completed: mark the earliest open paid installment completed, then expose the next pending slot as book_visit.
 */
function staff_installments_advance_after_visit_completed(PDO $pdo, string $tenantId, string $bookingId): void
{
    $tenantId = trim($tenantId);
    $bookingId = trim($bookingId);
    if ($tenantId === '' || $bookingId === '') {
        return;
    }

    $tableName = clinic_get_physical_table_name($pdo, 'tbl_installments')
        ?? clinic_get_physical_table_name($pdo, 'installments');
    if ($tableName === null || trim((string) $tableName) === '') {
        return;
    }
    $qtab = '`' . str_replace('`', '``', (string) $tableName) . '`';

    $stampSetSql = in_array('updated_at', clinic_table_columns($pdo, (string) $tableName), true)
        ? ', i.updated_at = NOW()'
        : '';

    $tenantClause = '(i.tenant_id = ? OR i.tenant_id IS NULL OR TRIM(COALESCE(i.tenant_id, \'\')) = \'\')';

    $tryBookingIds = [$bookingId];

    try {
        $tables = clinic_resolve_appointment_db_tables($pdo);
        $apptTableName = $tables['appointments'] ?? null;
        if ($apptTableName !== null && trim((string) $apptTableName) !== '') {
            $apptCols = clinic_table_columns($pdo, (string) $apptTableName);
            if (
                in_array('patient_id', $apptCols, true)
                && in_array('treatment_id', $apptCols, true)
            ) {
                $qa = clinic_quote_identifier((string) $apptTableName);
                $lookupAppt = $pdo->prepare("
                    SELECT TRIM(COALESCE(patient_id, '')) AS patient_id,
                           TRIM(COALESCE(treatment_id, '')) AS treatment_id
                    FROM {$qa}
                    WHERE tenant_id = ?
                      AND TRIM(COALESCE(booking_id, '')) = ?
                    LIMIT 1
                ");
                $lookupAppt->execute([$tenantId, $bookingId]);
                $look = $lookupAppt->fetch(PDO::FETCH_ASSOC) ?: null;
                $pid = trim((string) ($look['patient_id'] ?? ''));
                $tid = trim((string) ($look['treatment_id'] ?? ''));
                if ($pid !== '' && $tid !== '') {
                    $planBid = staff_installments_resolve_plan_booking_id_for_patient_treatment(
                        $pdo,
                        $tenantId,
                        $pid,
                        $tid,
                        (string) $apptTableName,
                        (string) $tableName
                    );
                    if ($planBid !== '' && !in_array($planBid, $tryBookingIds, true)) {
                        $tryBookingIds[] = $planBid;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('staff_installments_advance_after_visit_completed plan booking lookup: ' . $e->getMessage());
    }

    $sel = $pdo->prepare("
        SELECT i.installment_number
        FROM {$qtab} i
        WHERE i.booking_id = ?
          AND {$tenantClause}
          AND LOWER(TRIM(COALESCE(i.status, ''))) = 'paid'
        ORDER BY i.installment_number ASC
        LIMIT 1
    ");
    $updPaid = $pdo->prepare("
        UPDATE {$qtab} i
        SET i.status = 'completed'
            {$stampSetSql}
        WHERE i.booking_id = ?
          AND i.installment_number = ?
          AND {$tenantClause}
          AND LOWER(TRIM(COALESCE(i.status, ''))) = 'paid'
        LIMIT 1
    ");
    $unlock = $pdo->prepare("
        UPDATE {$qtab} i
        SET i.status = 'book_visit'
            {$stampSetSql}
        WHERE i.booking_id = ?
          AND i.installment_number = ?
          AND {$tenantClause}
          AND LOWER(TRIM(COALESCE(i.status, ''))) = 'pending'
        LIMIT 1
    ");

    foreach ($tryBookingIds as $useBidRaw) {
        $useBid = trim((string) $useBidRaw);
        if ($useBid === '') {
            continue;
        }
        $sel->execute([$useBid, $tenantId]);
        $hit = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$hit) {
            continue;
        }

        $currentNum = (int) ($hit['installment_number'] ?? 0);
        if ($currentNum <= 0) {
            continue;
        }

        $updPaid->execute([$useBid, $currentNum, $tenantId]);

        $nextNum = $currentNum + 1;
        $unlock->execute([$useBid, $nextNum, $tenantId]);

        return;
    }
}

/**
 * Apply a payment to a treatment record, updating financial and progress fields.
 *
 * This keeps `amount_paid`, `remaining_balance`, `months_paid`, `months_left`, and `completed_at`
 * in sync for all payment types.
 */
function staff_treatments_apply_payment(
    PDO $pdo,
    string $tenantId,
    string $treatmentId,
    float $amount,
    int $monthsPaidIncrement = 0
): void {
    $tenantId = trim($tenantId);
    $treatmentId = trim($treatmentId);
    if ($tenantId === '' || $treatmentId === '' || $amount <= 0) {
        return;
    }

    try {
        clinic_reconcile_tbl_treatments_duration($pdo, $tenantId, $treatmentId);
    } catch (Throwable $e) {
        error_log('staff_treatments_apply_payment reconcile: ' . $e->getMessage());
    }

    static $treatmentsTable = null;
    if ($treatmentsTable === null) {
        try {
            $treatTblStmt = $pdo->prepare("
                SELECT TABLE_NAME
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME IN ('tbl_treatments', 'treatments')
                ORDER BY FIELD(TABLE_NAME, 'tbl_treatments', 'treatments')
                LIMIT 1
            ");
            $treatTblStmt->execute();
            $treatmentsTable = trim((string) ($treatTblStmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            $treatmentsTable = '';
        }
        if ($treatmentsTable === '') {
            return;
        }
    }

    $quotedTreat = '`' . str_replace('`', '``', $treatmentsTable) . '`';

    // Load the current snapshot so we can derive progress.
    $selSql = "
        SELECT
            total_cost,
            amount_paid,
            remaining_balance,
            duration_months,
            months_paid,
            months_left,
            status,
            started_at,
            completed_at
        FROM {$quotedTreat}
        WHERE tenant_id = ?
          AND treatment_id = ?
        LIMIT 1
    ";
    $selStmt = $pdo->prepare($selSql);
    $selStmt->execute([$tenantId, $treatmentId]);
    $row = $selStmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    $totalCost = max(0.0, (float) ($row['total_cost'] ?? 0));
    $currentPaid = max(0.0, (float) ($row['amount_paid'] ?? 0));
    $durationMonths = max(0, (int) ($row['duration_months'] ?? 0));
    $currentMonthsPaid = max(0, (int) ($row['months_paid'] ?? 0));

    $newPaid = $currentPaid + $amount;
    if ($totalCost > 0 && $newPaid > $totalCost) {
        $newPaid = $totalCost;
    }
    $newRemaining = $totalCost > 0 ? max(0.0, $totalCost - $newPaid) : 0.0;

    // Month progress must come from actual monthly installment payments only.
    $monthsPaid = $currentMonthsPaid + max(0, $monthsPaidIncrement);
    if ($monthsPaid < 0) {
        $monthsPaid = 0;
    } elseif ($durationMonths > 0 && $monthsPaid > $durationMonths) {
        $monthsPaid = $durationMonths;
    }
    $monthsLeft = $durationMonths > 0 ? max(0, $durationMonths - $monthsPaid) : 0;

    $isFullyPaid = $totalCost > 0 && $newPaid >= ($totalCost - 0.009);
    $newStatus = $isFullyPaid ? 'completed' : 'active';
    $completedAt = null;
    if ($isFullyPaid) {
        $existingCompleted = trim((string) ($row['completed_at'] ?? ''));
        $completedAt = $existingCompleted !== '' ? $existingCompleted : date('Y-m-d');
    }

    $updSql = "
        UPDATE {$quotedTreat}
        SET
            amount_paid = ?,
            remaining_balance = ?,
            months_paid = ?,
            months_left = ?,
            status = ?,
            completed_at = ?
        WHERE tenant_id = ?
          AND treatment_id = ?
        LIMIT 1
    ";
    $updStmt = $pdo->prepare($updSql);
    $updStmt->execute([
        $newPaid,
        $newRemaining,
        $monthsPaid,
        $monthsLeft,
        $newStatus,
        $completedAt,
        $tenantId,
        $treatmentId,
    ]);
}

/**
 * Whether installment.scheduled_date is unset (Treatment Progress relies on this for T2+ rows).
 */
function staff_installment_scheduled_slot_is_empty(?string $raw): bool
{
    $v = trim((string) ($raw ?? ''));
    return $v === '' || strncmp($v, '0000-00-00', 10) === 0;
}

/**
 * booking_id that owns installment rows for this treatment (usually the originating plan appointment).
 *
 * Follow-up bookings use new booking IDs; installments stay on the plan booking.
 */
function staff_installments_resolve_plan_booking_id_for_patient_treatment(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    string $treatmentId,
    string $appointmentsTableName,
    string $installmentsTableName
): string {
    $tenantId = trim($tenantId);
    $patientId = trim($patientId);
    $treatmentId = trim($treatmentId);
    $appointmentsTableName = trim($appointmentsTableName);
    $installmentsTableName = trim($installmentsTableName);
    if (
        $tenantId === ''
        || $patientId === ''
        || $treatmentId === ''
        || $appointmentsTableName === ''
        || $installmentsTableName === ''
    ) {
        return '';
    }

    $qa = clinic_quote_identifier($appointmentsTableName);
    $qi = clinic_quote_identifier($installmentsTableName);

    try {
        $sql = "
            SELECT TRIM(COALESCE(i.booking_id, '')) AS bid
            FROM {$qi} i
            INNER JOIN {$qa} a
                ON a.tenant_id = ?
                AND TRIM(COALESCE(a.booking_id, '')) <> ''
                AND TRIM(COALESCE(a.booking_id, '')) = TRIM(COALESCE(i.booking_id, ''))
            WHERE TRIM(COALESCE(i.booking_id, '')) <> ''
              AND (
                  i.tenant_id = ?
                  OR i.tenant_id IS NULL
                  OR TRIM(COALESCE(i.tenant_id, '')) = ''
              )
              AND TRIM(COALESCE(a.patient_id, '')) = ?
              AND TRIM(COALESCE(a.treatment_id, '')) = ?
            GROUP BY i.booking_id
            ORDER BY COUNT(*) DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $tenantId,
            $tenantId,
            $patientId,
            $treatmentId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? trim((string) ($row['bid'] ?? '')) : '';
    } catch (Throwable $e) {
        error_log('staff_installments_resolve_plan_booking_id_for_patient_treatment: ' . $e->getMessage());

        return '';
    }
}

/**
 * When staff saves a NEW follow-up appointment (new booking row) with a plan visit, stamp the correct
 * tbl_installments row so Treatment Progress Schedule column stays in sync with the booked date.
 *
 * Skips installment #1: that row's display comes from the plan's originating appointment record.
 *
 * @return bool true when a DB row was updated
 */
function staff_installments_stamp_next_followup_slot_for_staff_visit(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    string $treatmentId,
    string $appointmentsTableName,
    string $visitDateRaw,
    string $visitTimeRaw
): bool {
    $tenantId = trim($tenantId);
    $patientId = trim($patientId);
    $treatmentId = trim($treatmentId);
    $visitDateRaw = trim($visitDateRaw);
    $visitTimeRaw = trim($visitTimeRaw);
    if ($tenantId === '' || $patientId === '' || $treatmentId === '' || $visitDateRaw === '') {
        return false;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $visitDateRaw)) {
        return false;
    }
    $dateYmd = substr($visitDateRaw, 0, 10);

    $installmentsPhys = clinic_get_physical_table_name($pdo, 'tbl_installments')
        ?? clinic_get_physical_table_name($pdo, 'installments')
        ?? '';
    if ($installmentsPhys === '') {
        return false;
    }

    $planBookingId = staff_installments_resolve_plan_booking_id_for_patient_treatment(
        $pdo,
        $tenantId,
        $patientId,
        $treatmentId,
        $appointmentsTableName,
        (string) $installmentsPhys
    );
    if ($planBookingId === '') {
        return false;
    }

    $qi = clinic_quote_identifier((string) $installmentsPhys);
    $tenantClause = '(i.tenant_id = ? OR i.tenant_id IS NULL OR TRIM(COALESCE(i.tenant_id, \'\')) = \'\')';

    $sel = $pdo->prepare("
        SELECT i.installment_number, i.status, i.scheduled_date
        FROM {$qi} i
        WHERE i.booking_id = ?
          AND {$tenantClause}
        ORDER BY i.installment_number ASC
    ");
    $sel->execute([$planBookingId, $tenantId]);
    $rows = $sel->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $targetNumber = 0;
    foreach ($rows as $r) {
        $num = (int) ($r['installment_number'] ?? 0);
        if ($num <= 1) {
            continue;
        }
        $st = strtolower(trim((string) ($r['status'] ?? '')));
        // UI can show Paid from aggregate reconcile while installment.status is still pending.
        if (in_array($st, ['completed', 'locked'], true)) {
            continue;
        }
        if (!in_array($st, ['paid', 'book_visit', 'pending'], true)) {
            continue;
        }
        if (!staff_installment_scheduled_slot_is_empty((string) ($r['scheduled_date'] ?? ''))) {
            continue;
        }
        $priorOk = true;
        foreach ($rows as $p) {
            $pn = (int) ($p['installment_number'] ?? 0);
            if ($pn <= 0 || $pn >= $num) {
                continue;
            }
            $pst = strtolower(trim((string) ($p['status'] ?? '')));
            if ($pst !== 'completed') {
                $priorOk = false;
                break;
            }
        }
        if (!$priorOk) {
            continue;
        }
        $targetNumber = $num;
        break;
    }

    if ($targetNumber <= 0) {
        return false;
    }

    $instCols = clinic_table_columns($pdo, (string) $installmentsPhys);
    $hasScheduledDate = in_array('scheduled_date', $instCols, true);
    $hasScheduledTime = in_array('scheduled_time', $instCols, true);
    if (!$hasScheduledDate) {
        return false;
    }

    $timeVal = $visitTimeRaw;
    if ($hasScheduledTime && $timeVal !== '') {
        if (strlen($timeVal) === 5 && preg_match('/^\d{2}:\d{2}$/', $timeVal)) {
            $timeVal .= ':00';
        }
    } else {
        $timeVal = '';
    }

    $stampSet = ['i.scheduled_date = ?'];
    $params = [$dateYmd];
    if ($hasScheduledTime) {
        $stampSet[] = 'i.scheduled_time = ?';
        $params[] = $timeVal !== '' ? $timeVal : null;
    }
    if (in_array('updated_at', $instCols, true)) {
        $stampSet[] = 'i.updated_at = NOW()';
    }

    $params[] = $planBookingId;
    $params[] = $targetNumber;
    $params[] = $tenantId;

    $sql = 'UPDATE ' . $qi . ' i SET ' . implode(', ', $stampSet) . "
        WHERE i.booking_id = ?
          AND i.installment_number = ?
          AND {$tenantClause}
        LIMIT 1
    ";
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    return $upd->rowCount() > 0;
}

/**
 * Stamp scheduled_date/time for an explicit installment (patient app follow-up after paying a plan row).
 * Row #1 stays driven by the plan master appointment; no-op when installment_number &lt;= 1.
 */
function staff_installments_stamp_followup_slot_explicit(
    PDO $pdo,
    string $tenantId,
    string $planBookingId,
    int $installmentNumber,
    string $visitDateRaw,
    string $visitTimeRaw
): bool {
    $tenantId = trim($tenantId);
    $planBookingId = trim($planBookingId);
    $visitDateRaw = trim($visitDateRaw);
    $visitTimeRaw = trim($visitTimeRaw);
    if ($tenantId === '' || $planBookingId === '' || $installmentNumber <= 1) {
        return true;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $visitDateRaw)) {
        return false;
    }
    $dateYmd = substr($visitDateRaw, 0, 10);

    $installmentsPhys = clinic_get_physical_table_name($pdo, 'tbl_installments')
        ?? clinic_get_physical_table_name($pdo, 'installments')
        ?? '';
    if ($installmentsPhys === '') {
        return false;
    }

    $qi = clinic_quote_identifier((string) $installmentsPhys);
    $tenantClause = '(i.tenant_id = ? OR i.tenant_id IS NULL OR TRIM(COALESCE(i.tenant_id, \'\')) = \'\')';

    $chk = $pdo->prepare(
        "SELECT i.installment_number FROM {$qi} i
         WHERE i.booking_id = ? AND i.installment_number = ? AND {$tenantClause}
         LIMIT 1"
    );
    $chk->execute([$planBookingId, $installmentNumber, $tenantId]);
    $found = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$found) {
        return false;
    }

    $instCols = clinic_table_columns($pdo, (string) $installmentsPhys);
    $hasScheduledDate = in_array('scheduled_date', $instCols, true);
    $hasScheduledTime = in_array('scheduled_time', $instCols, true);
    if (!$hasScheduledDate) {
        return false;
    }

    $timeVal = $visitTimeRaw;
    if ($hasScheduledTime && $timeVal !== '') {
        if (strlen($timeVal) === 5 && preg_match('/^\d{2}:\d{2}$/', $timeVal)) {
            $timeVal .= ':00';
        }
    } else {
        $timeVal = '';
    }

    $stampSet = ['i.scheduled_date = ?'];
    $params = [$dateYmd];
    if ($hasScheduledTime) {
        $stampSet[] = 'i.scheduled_time = ?';
        $params[] = $timeVal !== '' ? $timeVal : null;
    }
    if (in_array('updated_at', $instCols, true)) {
        $stampSet[] = 'i.updated_at = NOW()';
    }

    $params[] = $planBookingId;
    $params[] = $installmentNumber;
    $params[] = $tenantId;

    $sql = 'UPDATE ' . $qi . ' i SET ' . implode(', ', $stampSet) . "
        WHERE i.booking_id = ?
          AND i.installment_number = ?
          AND {$tenantClause}
        LIMIT 1
    ";
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    return $upd->rowCount() > 0;
}

/**
 * Patient PayMongo completion: mark tbl_installments row paid when payment row carries plan context.
 */
function staff_installments_mark_paid_from_mobile_payment_row(PDO $pdo, array $paymentRow): void
{
    $tenantId = trim((string) ($paymentRow['tenant_id'] ?? ''));
    $bookingId = trim((string) ($paymentRow['booking_id'] ?? ''));
    $paymentId = trim((string) ($paymentRow['payment_id'] ?? ''));
    $installmentNumber = (int) ($paymentRow['installment_number'] ?? 0);
    if ($tenantId === '' || $bookingId === '' || $paymentId === '' || $installmentNumber < 1) {
        return;
    }

    $installmentsPhys = clinic_get_physical_table_name($pdo, 'tbl_installments')
        ?? clinic_get_physical_table_name($pdo, 'installments')
        ?? '';
    if ($installmentsPhys === '') {
        return;
    }

    $qi = '`' . str_replace('`', '``', (string) $installmentsPhys) . '`';
    $tenantClause = '(i.tenant_id = ? OR i.tenant_id IS NULL OR TRIM(COALESCE(i.tenant_id, \'\')) = \'\')';

    $sel = $pdo->prepare("
        SELECT i.id AS id
        FROM {$qi} i
        WHERE i.booking_id = ?
          AND i.installment_number = ?
          AND {$tenantClause}
        LIMIT 1
    ");
    $sel->execute([$bookingId, $installmentNumber, $tenantId]);
    $id = $sel->fetchColumn();
    if (!$id || (int) $id <= 0) {
        return;
    }

    staff_installments_apply_paid_with_unlocks(
        $pdo,
        $tenantId,
        $bookingId,
        $paymentId,
        (string) $installmentsPhys,
        [['id' => (int) $id, 'installment_number' => $installmentNumber]]
    );
}
