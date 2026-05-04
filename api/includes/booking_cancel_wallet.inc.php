<?php

/**
 * Cancel an appointment and credit refundable completed payments to the patient's wallet.
 *
 * @return array{refunded_amount: float, already_cancelled: bool}
 *
 * @throws Exception
 */
function booking_perform_full_cancellation(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    int $resolvedAppointmentId,
    string $resolvedBookingId,
    string $currentStatusRaw,
    string $notes,
    string $actingUserId
): array {
    $currentStatus = strtoupper(trim((string) $currentStatusRaw));
    $finalStatus = 'cancelled';

    if ($currentStatus === 'CANCELLED' || strtolower($currentStatusRaw) === 'cancelled') {
        return ['refunded_amount' => 0.0, 'already_cancelled' => true];
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0) AS refundable_amount
         FROM tbl_payments
         WHERE tenant_id = ?
           AND booking_id = ?
           AND patient_id = ?
           AND status = \'completed\'
         FOR UPDATE'
    );
    $stmt->execute([$tenantId, $resolvedBookingId, $patientId]);
    $refundableAmount = (float) ($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare(
        'UPDATE tbl_appointments
         SET status = ?, notes = ?
         WHERE tenant_id = ?
           AND id = ?
           AND patient_id = ?
         LIMIT 1'
    );
    $stmt->execute([$finalStatus, $notes, $tenantId, $resolvedAppointmentId, $patientId]);

    if ($stmt->rowCount() < 1) {
        throw new Exception('Failed to cancel booking');
    }

    if ($refundableAmount > 0) {
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
            throw new Exception('Wallet account not found after creation');
        }

        $balanceBefore = (float) ($wallet['balance'] ?? 0);
        $balanceAfter = $balanceBefore + $refundableAmount;

        $stmt = $pdo->prepare(
            'UPDATE tbl_wallet_accounts SET balance = ? WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$balanceAfter, $wallet['id']]);

        $walletTxnId = booking_cancel_generate_wallet_transaction_id();
        $stmt = $pdo->prepare(
            "INSERT INTO tbl_wallet_transactions (
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
            ) VALUES (?, ?, ?, 'refund_credit', 'credit', ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $tenantId,
            $walletId,
            $walletTxnId,
            $refundableAmount,
            $balanceBefore,
            $balanceAfter,
            $resolvedBookingId,
            $resolvedBookingId,
            $notes !== '' ? $notes : 'Refund from cancelled booking',
            $actingUserId,
        ]);

        $stmt = $pdo->prepare(
            "UPDATE tbl_payments
             SET status = 'refunded'
             WHERE tenant_id = ?
               AND booking_id = ?
               AND patient_id = ?
               AND status = 'completed'"
        );
        $stmt->execute([$tenantId, $resolvedBookingId, $patientId]);
    }

    return ['refunded_amount' => $refundableAmount, 'already_cancelled' => false];
}

function booking_cancel_generate_wallet_id_local(): string
{
    return 'WAL-' . date('Ymd') . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function booking_cancel_ensure_wallet_account_local(PDO $pdo, string $tenantId, string $patientId): string
{
    $stmt = $pdo->prepare(
        'SELECT wallet_id FROM tbl_wallet_accounts WHERE tenant_id = ? AND patient_id = ? LIMIT 1'
    );
    $stmt->execute([$tenantId, $patientId]);
    $existingWalletId = $stmt->fetchColumn();
    if ($existingWalletId) {
        return (string) $existingWalletId;
    }

    $maxAttempts = 8;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $walletId = booking_cancel_generate_wallet_id_local();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO tbl_wallet_accounts (
                    tenant_id, wallet_id, patient_id, balance, status, created_at, updated_at
                ) VALUES (?, ?, ?, 0.00, 'active', NOW(), NOW())"
            );
            $stmt->execute([$tenantId, $walletId, $patientId]);

            return $walletId;
        } catch (PDOException $e) {
            $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
            if ($driverCode === 1062 && $attempt < $maxAttempts) {
                continue;
            }
            throw $e;
        }
    }

    throw new Exception('Failed to generate a unique wallet account ID.');
}

function booking_cancel_generate_wallet_transaction_id(): string
{
    return 'WTX-' . strtoupper(substr(md5((string) microtime(true) . mt_rand()), 0, 12));
}
