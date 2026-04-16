<?php

declare(strict_types=1);

/**
 * Mark installment rows paid and unlock the next pending installment (book_visit), matching clinic/api/payments.php behavior.
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
    $unlock = $pdo->prepare("
        UPDATE {$quoted} i
        SET i.status = 'book_visit'
        WHERE i.booking_id = ?
          AND i.installment_number = ?
          AND LOWER(COALESCE(i.status, '')) = 'pending'
          AND (i.tenant_id = ? OR i.tenant_id IS NULL)
    ");
    foreach ($paidItems as $row) {
        $num = (int) ($row['installment_number'] ?? 0);
        if ($num <= 0) {
            continue;
        }
        $nextNum = $num + 1;
        $unlock->execute([$bookingId, $nextNum, $tenantId]);
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
    float $amount
): void {
    $tenantId = trim($tenantId);
    $treatmentId = trim($treatmentId);
    if ($tenantId === '' || $treatmentId === '' || $amount <= 0) {
        return;
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

    $newPaid = $currentPaid + $amount;
    if ($totalCost > 0 && $newPaid > $totalCost) {
        $newPaid = $totalCost;
    }
    $newRemaining = $totalCost > 0 ? max(0.0, $totalCost - $newPaid) : 0.0;

    // Derive simple month progress proportional to financial progress.
    $monthsPaid = 0;
    if ($durationMonths > 0 && $totalCost > 0) {
        $ratio = $newPaid / $totalCost;
        if ($ratio < 0) {
            $ratio = 0.0;
        } elseif ($ratio > 1) {
            $ratio = 1.0;
        }
        $monthsPaid = (int) floor($ratio * $durationMonths + 1e-6);
        if ($monthsPaid < 0) {
            $monthsPaid = 0;
        } elseif ($monthsPaid > $durationMonths) {
            $monthsPaid = $durationMonths;
        }
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
