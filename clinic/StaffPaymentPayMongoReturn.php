<?php
/**
 * PayMongo checkout return URL — marks staff-recorded pending payment completed and updates appointment.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/staff_installment_helpers.php';
require_once __DIR__ . '/includes/appointment_db_tables.php';
require_once __DIR__ . '/includes/staff_payment_receipt_functions.inc.php';
require_once __DIR__ . '/../paymongo_config.php';
require_once __DIR__ . '/../mail_config.php';

if (!function_exists('staff_paymongo_get_checkout_billing_email')) {
    /**
     * Email entered on PayMongo Hosted Checkout (Customer Information) is returned on the session resource.
     */
    function staff_paymongo_get_checkout_billing_email(string $checkoutSessionId, string $secret): string
    {
        $checkoutSessionId = trim($checkoutSessionId);
        if ($checkoutSessionId === '' || $secret === '' || strpos($secret, 'YOUR_') !== false) {
            return '';
        }
        if (strncmp($checkoutSessionId, 'cs_', 3) !== 0) {
            return '';
        }
        $endpoint = 'https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($checkoutSessionId);
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return '';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Basic ' . base64_encode($secret . ':'),
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            return '';
        }
        $email = trim((string) ($data['data']['attributes']['billing']['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return '';
    }
}

if (isset($_SESSION['user_role']) && strtolower(trim((string) $_SESSION['user_role'])) === 'dentist') {
    header('Location: StaffDashboard.php');
    exit;
}

$currentTenantSlug = '';
if (isset($_GET['clinic_slug'])) {
    $slug = strtolower(trim((string) $_GET['clinic_slug']));
    if ($slug !== '' && preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $currentTenantSlug = $slug;
    }
}

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$paymentId = trim((string) ($_GET['pid'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$redirectBack = 'StaffPaymentRecording.php'
    . ($currentTenantSlug !== '' ? '?clinic_slug=' . urlencode($currentTenantSlug) : '');

if ($tenantId === '' || $paymentId === '' || $token === '') {
    header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
    exit;
}

$stash = $_SESSION['staff_paymongo_checkout'] ?? null;
unset($_SESSION['staff_paymongo_checkout']);

if (
    !is_array($stash)
    || !isset($stash['token'], $stash['payment_id'], $stash['tenant_id'])
    || !hash_equals((string) $stash['token'], $token)
    || (string) $stash['payment_id'] !== $paymentId
    || (string) $stash['tenant_id'] !== $tenantId
) {
    header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
    exit;
}

try {
    $pdo = getDBConnection();

    $payStmt = $pdo->prepare("
        SELECT payment_id, patient_id, booking_id, amount, status, payment_method, reference_number
        FROM tbl_payments
        WHERE tenant_id = ?
          AND payment_id = ?
        LIMIT 1
    ");
    $payStmt->execute([$tenantId, $paymentId]);
    $payRow = $payStmt->fetch(PDO::FETCH_ASSOC);
    if (!$payRow || strtolower(trim((string) ($payRow['status'] ?? ''))) !== 'pending') {
        header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
        exit;
    }

    $bookingId = trim((string) ($payRow['booking_id'] ?? ''));
    $patientId = trim((string) ($payRow['patient_id'] ?? ''));
    $amount = (float) ($payRow['amount'] ?? 0);
    if ($bookingId === '' || $amount <= 0) {
        header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
        exit;
    }

    $bookingSql = "
        SELECT
            COALESCE(MAX(a.id), 0) AS appointment_id,
            a.booking_id,
            COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
            COALESCE(SUM(CASE WHEN py.status = 'completed' THEN py.amount ELSE 0 END), 0) AS total_paid,
            COALESCE(a.treatment_id, '') AS treatment_id
        FROM tbl_appointments a
        LEFT JOIN tbl_payments py
            ON py.tenant_id = a.tenant_id
           AND py.booking_id = a.booking_id
        WHERE a.tenant_id = ?
          AND a.booking_id = ?
        GROUP BY a.booking_id, a.total_treatment_cost, a.treatment_id
        LIMIT 1
    ";
    $bookingStmt = $pdo->prepare($bookingSql);
    $bookingStmt->execute([$tenantId, $bookingId]);
    $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$bookingRow) {
        header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
        exit;
    }

    $totalCost = (float) ($bookingRow['total_treatment_cost'] ?? 0);
    $totalPaid = (float) ($bookingRow['total_paid'] ?? 0);
    $pendingBalance = max(0, $totalCost - $totalPaid);
    $appointmentId = (int) ($bookingRow['appointment_id'] ?? 0);
    $bookingTreatmentId = trim((string) ($bookingRow['treatment_id'] ?? ''));

    $storedDate = trim((string) ($stash['payment_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $storedDate)) {
        $storedDate = date('Y-m-d');
    }
    $paymentDateTime = $storedDate . ' ' . date('H:i:s');

    $pdo->beginTransaction();
    try {
        $updPay = $pdo->prepare("
            UPDATE tbl_payments
            SET status = 'completed',
                payment_date = ?
            WHERE tenant_id = ?
              AND payment_id = ?
              AND status = 'pending'
            LIMIT 1
        ");
        $updPay->execute([$paymentDateTime, $tenantId, $paymentId]);
        if ($updPay->rowCount() === 0) {
            throw new RuntimeException('Payment was already updated.');
        }

        $bookingStmt->execute([$tenantId, $bookingId]);
        $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        if ($bookingRow) {
            $totalCost = (float) ($bookingRow['total_treatment_cost'] ?? 0);
            $totalPaid = (float) ($bookingRow['total_paid'] ?? 0);
            $pendingBalance = max(0, $totalCost - $totalPaid);
            $bookingTreatmentId = trim((string) ($bookingRow['treatment_id'] ?? $bookingTreatmentId));
        }

        $selectedTransactionType = strtolower(trim((string) ($stash['selected_transaction_type'] ?? '')));
        if ($selectedTransactionType !== 'regular' && $selectedTransactionType !== 'installment') {
            $selectedTransactionType = is_array($stash['installment_finalize'] ?? null) ? 'installment' : 'regular';
        }

        // Apply to treatment progress only for installment transactions.
        if ($selectedTransactionType === 'installment' && $bookingTreatmentId !== '' && $amount > 0) {
            $monthsPaidIncrement = 0;
            $finalize = $stash['installment_finalize'] ?? null;
            if (is_array($finalize)) {
                $monthsPaidIncrement = max(0, (int) ($finalize['months_paid_increment'] ?? 0));
            }
            staff_treatments_apply_payment(
                $pdo,
                $tenantId,
                $bookingTreatmentId,
                (float) $amount,
                $monthsPaidIncrement
            );
        }

        $finalize = $stash['installment_finalize'] ?? null;
        if (is_array($finalize) && !empty($finalize['installments_table']) && !empty($finalize['paid_items']) && is_array($finalize['paid_items'])) {
            staff_installments_apply_paid_with_unlocks(
                $pdo,
                $tenantId,
                $bookingId,
                $paymentId,
                (string) $finalize['installments_table'],
                $finalize['paid_items']
            );
        }

        $pwdWants = !empty($stash['use_pwd_senior_discount']);
        $pwdAmtStash = round((float) ($stash['pwd_discount_amount'] ?? 0), 2);
        if (
            $pwdWants
            && $pwdAmtStash > 0.009
            && $selectedTransactionType === 'regular'
        ) {
            staff_payment_recording_apply_pwd_senior_discount_to_booking_economics(
                $pdo,
                $tenantId,
                $bookingId,
                $pwdAmtStash
            );
        }

        // Keep appointment lifecycle independent from payment lifecycle.
        // Successful payment updates payment/treatment/installment records only.

        $pdo->commit();
    } catch (Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }

    // Auto-send receipt email after successful PayMongo authorization (same payload as Staff Payment Recording).
    // Email delivery failures must not roll back a successful payment completion.
    try {
        $recipientEmail = '';
        $paymongoSecret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
        $checkoutSessionId = trim((string) ($payRow['reference_number'] ?? ''));
        if ($paymongoSecret !== '' && $checkoutSessionId !== '') {
            $recipientEmail = staff_paymongo_get_checkout_billing_email($checkoutSessionId, $paymongoSecret);
        }
        if ($recipientEmail === '') {
            $patientStmt = $pdo->prepare("
                SELECT COALESCE(NULLIF(u_linked.email, ''), NULLIF(u_owner.email, ''), '') AS patient_email
                FROM tbl_patients p
                LEFT JOIN tbl_users u_linked ON u_linked.user_id = p.linked_user_id
                LEFT JOIN tbl_users u_owner ON u_owner.user_id = p.owner_user_id
                WHERE p.tenant_id = ? AND p.patient_id = ?
                LIMIT 1
            ");
            $patientStmt->execute([$tenantId, $patientId]);
            $patientRow = $patientStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $recipientEmail = trim((string) ($patientRow['patient_email'] ?? ''));
            if ($recipientEmail !== '' && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $recipientEmail = '';
            }
        }

        $receiptPayload = staff_payment_recording_compose_receipt_email_payload($pdo, $tenantId, $paymentId);
        if (
            $receiptPayload !== null
            && $recipientEmail !== ''
            && function_exists('send_smtp_gmail')
            && send_smtp_gmail(
                $recipientEmail,
                $receiptPayload['subject'],
                $receiptPayload['text'],
                $receiptPayload['html']
            )
        ) {
            $_SESSION['staff_last_receipt_email'] = [
                'tenant_id' => $tenantId,
                'payment_id' => $paymentId,
                'sent_at' => time(),
            ];
            $_SESSION['staff_receipt_email_success'] = '1';
        }
    } catch (Throwable $mailErr) {
        error_log('StaffPaymentPayMongoReturn receipt email auto-send error: ' . $mailErr->getMessage());
    }

    header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'payment_success=1');
    exit;
} catch (Throwable $e) {
    error_log('StaffPaymentPayMongoReturn: ' . $e->getMessage());
    header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
    exit;
}
