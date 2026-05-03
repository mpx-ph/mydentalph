<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../clinic/includes/booking_treatment_ledger.php';
require_once __DIR__ . '/../clinic/includes/staff_installment_helpers.php';

$pid = isset($_GET['pid']) ? trim((string) $_GET['pid']) : '';

if ($pid !== '') {
    try {
        $tables = clinic_resolve_appointment_db_tables($pdo);
        $payPhys = $tables['payments'] ?? 'tbl_payments';
        $pq = clinic_quote_identifier((string) $payPhys);

        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM ' . $pq . ' WHERE payment_id = ? LIMIT 1');
        $st->execute([$pid]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $statusNorm = strtolower(trim((string) ($row['status'] ?? '')));
            if ($statusNorm !== 'completed') {
                $upd = $pdo->prepare('UPDATE ' . $pq . ' SET status = \'completed\' WHERE payment_id = ?');
                $upd->execute([$pid]);
            }
            $st2 = $pdo->prepare('SELECT * FROM ' . $pq . ' WHERE payment_id = ? LIMIT 1');
            $st2->execute([$pid]);
            $fresh = $st2->fetch(PDO::FETCH_ASSOC) ?: $row;
            booking_apply_completed_payment_to_treatment($pdo, $fresh);
            staff_installments_mark_paid_from_mobile_payment_row($pdo, $fresh);
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
        <p>Your transaction has been securely processed and recorded. You can now return to your Dento Cleene app dashboard.</p>
        <a href="mydentalph://app" class="btn">Return to App Settings</a>
    </div>
</body>
</html>
