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

    $sel = $pdo->prepare("
        SELECT i.installment_number
        FROM {$qtab} i
        WHERE i.booking_id = ?
          AND {$tenantClause}
          AND LOWER(TRIM(COALESCE(i.status, ''))) = 'paid'
        ORDER BY i.installment_number ASC
        LIMIT 1
    ");
    $sel->execute([$bookingId, $tenantId]);
    $hit = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$hit) {
        return;
    }

    $currentNum = (int) ($hit['installment_number'] ?? 0);
    if ($currentNum <= 0) {
        return;
    }

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
    $updPaid->execute([$bookingId, $currentNum, $tenantId]);

    $nextNum = $currentNum + 1;
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
    $unlock->execute([$bookingId, $nextNum, $tenantId]);
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
