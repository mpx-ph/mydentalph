<?php

declare(strict_types=1);

/**
 * Mark installment rows paid and unlock the next pending installment (due), matching clinic/api/payments.php behavior.
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
    $columnStmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $columnStmt->execute([$installmentsTableName]);
    $columns = array_map('strtolower', array_map('strval', $columnStmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    $supportsLastPaymentDate = in_array('last_payment_date', $columns, true);
    $supportsDueDate = in_array('due_date', $columns, true);

    $markSql = "UPDATE {$quoted} i SET i.status = 'paid', i.payment_id = ?";
    if ($supportsLastPaymentDate) {
        $markSql .= ", i.last_payment_date = CURDATE()";
    }
    $markSql .= " WHERE i.id = ? AND i.booking_id = ? AND (i.tenant_id = ? OR i.tenant_id IS NULL)";
    $mark = $pdo->prepare($markSql);
    foreach ($paidItems as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $mark->execute([$paymentId, $id, $bookingId, $tenantId]);
    }
    $unlockSql = "
        UPDATE {$quoted} i
        SET i.status = 'due'"
        . ($supportsDueDate ? ", i.due_date = COALESCE(i.due_date, DATE_ADD(CURDATE(), INTERVAL 30 DAY))" : "") . "
        WHERE i.booking_id = ?
          AND i.installment_number = ?
          AND LOWER(COALESCE(i.status, '')) = 'pending'
          AND (i.tenant_id = ? OR i.tenant_id IS NULL)
    ";
    $unlock = $pdo->prepare($unlockSql);
    foreach ($paidItems as $row) {
        $num = (int) ($row['installment_number'] ?? 0);
        if ($num <= 0) {
            continue;
        }
        $nextNum = $num + 1;
        $unlock->execute([$bookingId, $nextNum, $tenantId]);
    }
}
