<?php
// api/cancel_appointment.php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "success" => false, "message" => "POST required"]));
}

// Accept JSON body or form-data.
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$appointment_id = $input['appointment_id'] ?? null;
$booking_id     = $input['booking_id'] ?? null;
$tenant_id      = $input['tenant_id'] ?? null;
$user_id        = $input['user_id'] ?? null;
$notes          = trim((string)($input['notes'] ?? ''));

// `status` is accepted in the payload for compatibility; we still force CANCELLED.
$final_status = 'CANCELLED';

if (!$tenant_id || !$user_id || (!$appointment_id && !$booking_id)) {
    die(json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Missing required fields: tenant_id, user_id, and appointment_id or booking_id"
    ]));
}

function generateWalletIdLocal(): string
{
    return 'WAL-' . date('Ymd') . '-' . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function ensureWalletAccountLocal(PDO $pdo, string $tenantId, string $patientId): string
{
    $stmt = $pdo->prepare('SELECT wallet_id FROM tbl_wallet_accounts WHERE tenant_id = ? AND patient_id = ? LIMIT 1');
    $stmt->execute([$tenantId, $patientId]);
    $existingWalletId = $stmt->fetchColumn();
    if ($existingWalletId) {
        return (string) $existingWalletId;
    }

    $maxAttempts = 8;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $walletId = generateWalletIdLocal();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tbl_wallet_accounts (
                    tenant_id, wallet_id, patient_id, balance, status, created_at, updated_at
                ) VALUES (?, ?, ?, 0.00, 'active', NOW(), NOW())
            ");
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

function generateWalletTransactionId(): string
{
    return 'WTX-' . strtoupper(substr(md5((string) microtime(true) . mt_rand()), 0, 12));
}

try {
    $pdo->beginTransaction();

    // Resolve patient tied to the app user for scoped cancellation.
    $stmt = $pdo->prepare("
        SELECT patient_id
        FROM tbl_patients
        WHERE tenant_id = ?
          AND (owner_user_id = ? OR linked_user_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $user_id, $user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception('Patient record not found for this user');
    }

    $patient_id = $patient['patient_id'];

    // Resolve appointment first so we can safely compute refund by booking_id.
    if ($appointment_id) {
        $stmt = $pdo->prepare("
            SELECT id, booking_id, status
            FROM tbl_appointments
            WHERE tenant_id = ?
              AND id = ?
              AND patient_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$tenant_id, $appointment_id, $patient_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, booking_id, status
            FROM tbl_appointments
            WHERE tenant_id = ?
              AND booking_id = ?
              AND patient_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$tenant_id, $booking_id, $patient_id]);
    }

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appointment) {
        throw new Exception('Appointment not found or not allowed to cancel');
    }

    $resolvedAppointmentId = (int) $appointment['id'];
    $resolvedBookingId = (string) $appointment['booking_id'];
    $currentStatus = strtoupper((string) ($appointment['status'] ?? ''));

    // Idempotent: already cancelled means no further refund actions.
    if ($currentStatus === 'CANCELLED') {
        $pdo->commit();
        echo json_encode([
            "status" => "success",
            "success" => true,
            "message" => "Booking already cancelled",
            "refunded_amount" => "0.00"
        ]);
        exit;
    }

    // Compute refundable amount from completed payments for this booking.
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS refundable_amount
        FROM tbl_payments
        WHERE tenant_id = ?
          AND booking_id = ?
          AND patient_id = ?
          AND status = 'completed'
        FOR UPDATE
    ");
    $stmt->execute([$tenant_id, $resolvedBookingId, $patient_id]);
    $refundable_amount = (float) ($stmt->fetchColumn() ?? 0);

    // Cancel appointment and persist reason.
    $stmt = $pdo->prepare("
        UPDATE tbl_appointments
        SET status = ?, notes = ?
        WHERE tenant_id = ?
          AND id = ?
          AND patient_id = ?
        LIMIT 1
    ");
    $stmt->execute([$final_status, $notes, $tenant_id, $resolvedAppointmentId, $patient_id]);

    if ($stmt->rowCount() < 1) {
        throw new Exception('Failed to cancel booking');
    }

    if ($refundable_amount > 0) {
        $walletId = ensureWalletAccountLocal($pdo, $tenant_id, $patient_id);

        $stmt = $pdo->prepare("
            SELECT id, balance
            FROM tbl_wallet_accounts
            WHERE tenant_id = ?
              AND wallet_id = ?
              AND patient_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$tenant_id, $walletId, $patient_id]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wallet) {
            throw new Exception('Wallet account not found after creation');
        }

        $balanceBefore = (float) ($wallet['balance'] ?? 0);
        $balanceAfter = $balanceBefore + $refundable_amount;

        $stmt = $pdo->prepare("
            UPDATE tbl_wallet_accounts
            SET balance = ?
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$balanceAfter, $wallet['id']]);

        $walletTxnId = generateWalletTransactionId();
        $stmt = $pdo->prepare("
            INSERT INTO tbl_wallet_transactions (
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
            ) VALUES (?, ?, ?, 'refund_credit', 'credit', ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $tenant_id,
            $walletId,
            $walletTxnId,
            $refundable_amount,
            $balanceBefore,
            $balanceAfter,
            $resolvedBookingId,
            $resolvedBookingId,
            $notes !== '' ? $notes : 'Refund from cancelled booking',
            $user_id
        ]);

        // Mark refunded to avoid double refunds on repeated calls.
        $stmt = $pdo->prepare("
            UPDATE tbl_payments
            SET status = 'refunded'
            WHERE tenant_id = ?
              AND booking_id = ?
              AND patient_id = ?
              AND status = 'completed'
        ");
        $stmt->execute([$tenant_id, $resolvedBookingId, $patient_id]);
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "success" => true,
        "message" => "Booking cancelled",
        "refunded_amount" => number_format($refundable_amount, 2, '.', '')
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}

