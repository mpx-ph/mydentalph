<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../clinic/includes/booking_treatment_ledger.php';
require_once __DIR__ . '/../clinic/includes/staff_installment_helpers.php';
require_once __DIR__ . '/includes/mobile_wallet_payment.inc.php';
require_once __DIR__ . '/includes/mobile_booking_confirmation_email.inc.php';

$pid = isset($_GET['pid']) ? trim((string) $_GET['pid']) : '';
/** @var string For deep link back to the app after online payment */
$bookingIdForDeepLink = '';

if ($pid !== '') {
    try {
        $tables = clinic_resolve_appointment_db_tables($pdo);
        $payPhys = $tables['payments'] ?? 'tbl_payments';
        $pq = clinic_quote_identifier((string) $payPhys);

        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM ' . $pq . ' WHERE payment_id = ? LIMIT 1 FOR UPDATE');
        $st->execute([$pid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $statusNorm = strtolower(trim((string) ($row['status'] ?? '')));
            $tenantId = trim((string) ($row['tenant_id'] ?? ''));
            $wasIncomplete = ($statusNorm !== 'completed');
            if ($wasIncomplete) {
                $upd = $pdo->prepare('UPDATE ' . $pq . ' SET status = \'completed\' WHERE payment_id = ?');
                $upd->execute([$pid]);
            }
            $st2 = $pdo->prepare('SELECT * FROM ' . $pq . ' WHERE payment_id = ? LIMIT 1');
            $st2->execute([$pid]);
            $fresh = $st2->fetch(PDO::FETCH_ASSOC) ?: $row;

            $walletApplied = mobile_wallet_parse_applied_amount_from_notes((string) ($fresh['notes'] ?? ''));
            if (
                $walletApplied > 0.009
                && $tenantId !== ''
                && !mobile_wallet_payment_debit_exists($pdo, $tenantId, $pid)
            ) {
                mobile_wallet_apply_payment_debit(
                    $pdo,
                    $tenantId,
                    trim((string) ($fresh['patient_id'] ?? '')),
                    $walletApplied,
                    $pid,
                    trim((string) ($fresh['booking_id'] ?? '')),
                    trim((string) ($fresh['created_by'] ?? '')) ?: 'system',
                );
            }

            if ($wasIncomplete && $walletApplied > 0.009) {
                $onlineRecorded = (float) ($fresh['amount'] ?? 0);
                $combinedPaid = round($onlineRecorded + $walletApplied, 2);
                if ($combinedPaid > 0.009) {
                    $updAmt = $pdo->prepare('UPDATE ' . $pq . ' SET amount = ? WHERE payment_id = ?');
                    $updAmt->execute([$combinedPaid, $pid]);
                }
            }

            $st3 = $pdo->prepare('SELECT * FROM ' . $pq . ' WHERE payment_id = ? LIMIT 1');
            $st3->execute([$pid]);
            $fresh = $st3->fetch(PDO::FETCH_ASSOC) ?: $fresh;

            booking_apply_completed_payment_to_treatment($pdo, $fresh);
            staff_installments_mark_paid_from_mobile_payment_row($pdo, $fresh);

            $bookingIdForDeepLink = trim((string) ($fresh['booking_id'] ?? ''));
            if ($wasIncomplete) {
                try {
                    mobile_try_send_booking_confirmation_email($pdo, $fresh);
                } catch (Throwable $mailEx) {
                    // Non-fatal: payment already recorded.
                }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100vh; margin: 0; background-color: #f8fafc; }
        .card { background: white; padding: 40px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; max-width: 400px; width: 90%; }
        .icon { font-size: 64px; color: #10b981; margin-bottom: 16px; }
        h1 { color: #0f172a; margin: 0 0 8px 0; font-size: 24px; }
        p { color: #64748b; margin: 0 0 24px 0; line-height: 1.5; }
        .btn { background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 16px; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✨</div>
        <h1>Payment Successful!</h1>
        <p>Your transaction has been securely processed and recorded. A confirmation email will be sent to your registered email when available.</p>
        <?php
        $backHref = 'mydentalph://app/payment-complete';
        if ($bookingIdForDeepLink !== '') {
            $backHref .= '?booking_id=' . rawurlencode($bookingIdForDeepLink);
        }
        ?>
        <a href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>" class="btn">Return to app</a>
    </div>
</body>
</html>
