<?php

declare(strict_types=1);

require_once __DIR__ . '/booking_cancel_wallet.inc.php';

function mobile_wallet_parse_applied_amount_from_notes(string $notes): float
{
    if ($notes === '') {
        return 0.0;
    }
    if (!preg_match('/\[Wallet applied: ₱([\d,.]+)\]/u', $notes, $m)) {
        return 0.0;
    }
    $raw = str_replace(',', '', $m[1]);

    return round((float) $raw, 2);
}

function mobile_wallet_payment_debit_exists(PDO $pdo, string $tenantId, string $paymentId): bool
{
    $tenantId = trim($tenantId);
    $paymentId = trim($paymentId);
    if ($tenantId === '' || $paymentId === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT 1 FROM tbl_wallet_transactions
         WHERE tenant_id = ?
           AND source_payment_id = ?
           AND transaction_type = \'payment_debit\'
         LIMIT 1'
    );
    $stmt->execute([$tenantId, $paymentId]);

    return (bool) $stmt->fetchColumn();
}

/**
 * Debit patient wallet and append tbl_wallet_transactions (payment_debit).
 *
 * @throws Exception insufficient balance or missing wallet row after lock
 */
function mobile_wallet_apply_payment_debit(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    float $amount,
    string $paymentId,
    string $bookingId,
    string $actingUserId,
): void {
    $tenantId = trim($tenantId);
    $patientId = trim($patientId);
    $paymentId = trim($paymentId);
    $bookingId = trim($bookingId);
    $actingUserId = trim($actingUserId);
    if ($actingUserId === '') {
        $actingUserId = 'system';
    }

    $amount = round($amount, 2);
    if ($amount <= 0.009 || $tenantId === '' || $patientId === '' || $paymentId === '') {
        return;
    }

    if (mobile_wallet_payment_debit_exists($pdo, $tenantId, $paymentId)) {
        return;
    }

    $walletId = booking_cancel_ensure_wallet_account_local($pdo, $tenantId, $patientId);

    $stmt = $pdo->prepare(
        'SELECT id, balance
         FROM tbl_wallet_accounts
         WHERE tenant_id = ?
           AND wallet_id = ?
           AND patient_id = ?
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute([$tenantId, $walletId, $patientId]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$wallet) {
        throw new Exception('Wallet account not found.');
    }

    $balanceBefore = (float) ($wallet['balance'] ?? 0);
    if ($balanceBefore + 0.009 < $amount) {
        throw new Exception('Insufficient wallet balance.');
    }

    $balanceAfter = round($balanceBefore - $amount, 2);

    $stmt = $pdo->prepare(
        'UPDATE tbl_wallet_accounts SET balance = ? WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$balanceAfter, $wallet['id']]);

    $walletTxnId = booking_cancel_generate_wallet_transaction_id();
    $notes = 'Booking payment via MyDental Wallet';
    if ($bookingId !== '') {
        $notes .= ' (booking ' . $bookingId . ')';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO tbl_wallet_transactions (
            tenant_id,
            wallet_id,
            wallet_transaction_id,
            transaction_type,
            direction,
            amount,
            balance_before,
            balance_after,
            source_payment_id,
            reference_number,
            notes,
            created_by,
            created_at
        ) VALUES (?, ?, ?, \'payment_debit\', \'debit\', ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $tenantId,
        $walletId,
        $walletTxnId,
        $amount,
        $balanceBefore,
        $balanceAfter,
        $paymentId,
        $bookingId !== '' ? $bookingId : null,
        $notes,
        $actingUserId,
    ]);
}
