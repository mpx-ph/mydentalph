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
