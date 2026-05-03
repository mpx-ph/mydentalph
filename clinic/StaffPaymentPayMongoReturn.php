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
require_once __DIR__ . '/../paymongo_config.php';
require_once __DIR__ . '/../mail_config.php';

if (!function_exists('staff_payment_recording_to_manila_datetime')) {
    /** @see StaffPaymentRecording.php — keep naive DST handling aligned with tbl_payments storage */
    function staff_payment_recording_to_manila_datetime(string $rawValue): ?DateTimeImmutable
    {
        $raw = trim($rawValue);
        if ($raw === '') {
            return null;
        }
        $manila = new DateTimeZone('Asia/Manila');
        if (preg_match('/(?:Z|[+\-]\d{2}:\d{2}|[+\-]\d{4})$/', $raw) === 1) {
            try {
                $dt = new DateTimeImmutable($raw);
                return $dt->setTimezone($manila);
            } catch (Throwable $e) {
                return null;
            }
        }
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d',
        ];
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat('!' . $format, $raw, $manila);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }
        try {
            return new DateTimeImmutable($raw, $manila);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('staff_payment_recording_build_receipt_email_html')) {
    function staff_payment_recording_build_receipt_email_html(array $receipt): string
    {
        $clinicName = htmlspecialchars((string) ($receipt['clinic_name'] ?? 'MyDental Philippines'), ENT_QUOTES, 'UTF-8');
        $clinicLogo = htmlspecialchars((string) ($receipt['clinic_logo'] ?? ''), ENT_QUOTES, 'UTF-8');
        $patientName = htmlspecialchars((string) ($receipt['patient_name'] ?? 'Patient'), ENT_QUOTES, 'UTF-8');
        $patientId = htmlspecialchars((string) ($receipt['patient_id'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
        $reference = htmlspecialchars((string) ($receipt['reference'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $paymentId = htmlspecialchars((string) ($receipt['payment_id'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $service = htmlspecialchars((string) ($receipt['service'] ?? 'Dental treatment'), ENT_QUOTES, 'UTF-8');
        $paymentDate = htmlspecialchars((string) ($receipt['payment_date'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $paymentMethod = htmlspecialchars((string) ($receipt['payment_method'] ?? '-'), ENT_QUOTES, 'UTF-8');
        $amountPaid = htmlspecialchars((string) ($receipt['amount_paid'] ?? '₱0.00'), ENT_QUOTES, 'UTF-8');
        $remainingBalance = htmlspecialchars((string) ($receipt['remaining_balance'] ?? '₱0.00'), ENT_QUOTES, 'UTF-8');
        $servicesTotal = htmlspecialchars((string) ($receipt['services_total'] ?? '₱0.00'), ENT_QUOTES, 'UTF-8');
        $serviceItems = isset($receipt['service_items']) && is_array($receipt['service_items']) ? $receipt['service_items'] : [];
        $serviceRowsHtml = '';
        foreach ($serviceItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemName = htmlspecialchars(trim((string) ($item['name'] ?? '')), ENT_QUOTES, 'UTF-8');
            $itemAmountRaw = $item['amount'] ?? '';
            if (is_int($itemAmountRaw) || is_float($itemAmountRaw) || (is_string($itemAmountRaw) && is_numeric($itemAmountRaw))) {
                $itemAmountRaw = '₱' . number_format((float) $itemAmountRaw, 2);
            }
            $itemAmount = htmlspecialchars(trim((string) $itemAmountRaw), ENT_QUOTES, 'UTF-8');
            if ($itemName === '' && $itemAmount === '') {
                continue;
            }
            if ($itemName === '') {
                $itemName = 'Service';
            }
            if ($itemAmount === '') {
                $itemAmount = '₱0.00';
            }
            $serviceRowsHtml .= '<tr><td style="font-size:15px;line-height:21px;font-weight:600;color:#41547a;padding:0 0 8px;">' . $itemName . '</td><td align="right" style="font-size:15px;line-height:21px;font-weight:800;color:#0f172a;padding:0 0 8px;">' . $itemAmount . '</td></tr>';
        }
        if ($serviceRowsHtml === '') {
            $serviceRowsHtml = '<tr><td style="font-size:15px;line-height:21px;font-weight:600;color:#41547a;padding:0 0 8px;">Service</td><td align="right" style="font-size:15px;line-height:21px;font-weight:800;color:#0f172a;padding:0 0 8px;">' . $service . '</td></tr>';
        }

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Payment Receipt</title></head><body style="margin:0;padding:0;background:#f3f8ff;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f8ff;padding:24px 12px;"><tr><td align="center">'
            . '<table role="presentation" width="760" cellpadding="0" cellspacing="0" style="width:760px;max-width:760px;background:#ffffff;border:1px solid #dbeafe;border-radius:18px;overflow:hidden;">'
            . '<tr><td style="padding:22px 24px;background:#f8fcff;border-bottom:1px solid #dbeafe;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td width="72" valign="top" style="width:72px;padding-right:12px;">'
            . ($clinicLogo !== '' ? '<img src="' . $clinicLogo . '" alt="Clinic Logo" width="64" height="64" style="display:block;width:64px;height:64px;border-radius:14px;border:1px solid #dbeafe;object-fit:cover;background:#fff;">' : '')
            . '</td>'
            . '<td valign="top"><p style="margin:0;font-size:24px;line-height:30px;font-weight:800;color:#0f172a;">' . $clinicName . '</p>'
            . '<p style="margin:8px 0 0;font-size:13px;line-height:18px;letter-spacing:4px;font-weight:800;color:#647aa5;text-transform:uppercase;">Official Payment Receipt</p>'
            . '<p style="margin:12px 0 0;font-size:16px;line-height:22px;color:#60739a;">Thank you for your payment. Keep this as your billing record.</p></td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:18px 24px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td width="49%" valign="top" style="width:49%;border:1px solid #dbeafe;border-radius:14px;padding:12px 14px;">'
            . '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;font-weight:800;color:#647aa5;text-transform:uppercase;">Patient</p>'
            . '<p style="margin:10px 0 0;font-size:18px;line-height:24px;font-weight:800;color:#0f172a;">' . $patientName . '</p>'
            . '<p style="margin:8px 0 0;font-size:14px;line-height:18px;color:#7284a8;">ID ' . $patientId . '</p></td>'
            . '<td width="2%"></td>'
            . '<td width="49%" valign="top" style="width:49%;border:1px solid #dbeafe;border-radius:14px;padding:12px 14px;">'
            . '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;font-weight:800;color:#647aa5;text-transform:uppercase;">Transaction Ref</p>'
            . '<p style="margin:10px 0 0;font-size:18px;line-height:24px;font-weight:800;color:#0f172a;word-break:break-word;">' . $reference . '</p>'
            . '<p style="margin:8px 0 0;font-size:14px;line-height:18px;color:#7284a8;">Payment ID ' . $paymentId . '</p></td>'
            . '</tr></table></td></tr>'
            . '<tr><td style="padding:18px 24px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dbeafe;border-radius:14px;overflow:hidden;">'
            . '<tr><td style="padding:12px 14px;background:#f9fcff;border-bottom:1px solid #dbeafe;font-size:13px;line-height:18px;letter-spacing:4px;font-weight:800;color:#4f668f;text-transform:uppercase;">Payment Breakdown</td></tr>'
            . '<tr><td style="padding:12px 14px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            . $serviceRowsHtml
            . '<tr><td style="font-size:16px;line-height:22px;font-weight:800;color:#1e3a8a;padding:2px 0 0;">Total</td><td align="right" style="font-size:16px;line-height:22px;font-weight:900;color:#1e3a8a;padding:2px 0 0;">' . $servicesTotal . '</td></tr>'
            . '<tr><td height="12"></td><td></td></tr>'
            . '<tr><td style="font-size:16px;line-height:22px;font-weight:600;color:#41547a;">Payment Date</td><td align="right" style="font-size:16px;line-height:22px;font-weight:800;color:#0f172a;">' . $paymentDate . '</td></tr>'
            . '<tr><td height="12"></td><td></td></tr>'
            . '<tr><td style="font-size:16px;line-height:22px;font-weight:600;color:#41547a;">Payment Method</td><td align="right" style="font-size:16px;line-height:22px;font-weight:800;color:#0f172a;">' . $paymentMethod . '</td></tr>'
            . '</table></td></tr></table></td></tr>'
            . '<tr><td style="padding:18px 24px 24px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
            . '<td width="49%" valign="top" style="width:49%;border:1px solid #bfdbfe;border-radius:14px;background:#f0f8ff;padding:12px 14px;">'
            . '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;text-transform:uppercase;font-weight:800;color:#2382ff;">Amount Paid</p>'
            . '<p style="margin:12px 0 0;font-size:34px;line-height:38px;font-weight:800;color:#2382ff;">' . $amountPaid . '</p></td>'
            . '<td width="2%"></td>'
            . '<td width="49%" valign="top" style="width:49%;border:1px solid #fcdca7;border-radius:14px;background:#fffaf0;padding:12px 14px;">'
            . '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;text-transform:uppercase;font-weight:800;color:#b45309;">Remaining Balance</p>'
            . '<p style="margin:12px 0 0;font-size:34px;line-height:38px;font-weight:800;color:#b45309;">' . $remainingBalance . '</p></td>'
            . '</tr></table></td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}

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

        // Keep appointment lifecycle independent from payment lifecycle.
        // Successful payment updates payment/treatment/installment records only.

        $pdo->commit();
    } catch (Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }

    // Auto-send receipt email after successful PayMongo authorization.
    // Email delivery failures must not roll back a successful payment completion.
    try {
        $tenantStmt = $pdo->prepare("
            SELECT COALESCE(NULLIF(clinic_name, ''), 'MyDental Philippines') AS clinic_name
            FROM tbl_tenants
            WHERE tenant_id = ?
            LIMIT 1
        ");
        $tenantStmt->execute([$tenantId]);
        $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $clinicName = trim((string) ($tenantRow['clinic_name'] ?? 'MyDental Philippines'));
        if ($clinicName === '') {
            $clinicName = 'MyDental Philippines';
        }

        $patientStmt = $pdo->prepare("
            SELECT
                COALESCE(p.first_name, '') AS first_name,
                COALESCE(p.last_name, '') AS last_name,
                COALESCE(NULLIF(u_linked.email, ''), NULLIF(u_owner.email, ''), '') AS patient_email
            FROM tbl_patients p
            LEFT JOIN tbl_users u_linked
              ON u_linked.user_id = p.linked_user_id
            LEFT JOIN tbl_users u_owner
              ON u_owner.user_id = p.owner_user_id
            WHERE p.tenant_id = ?
              AND p.patient_id = ?
            LIMIT 1
        ");
        $patientStmt->execute([$tenantId, $patientId]);
        $patientRow = $patientStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $patientEmail = trim((string) ($patientRow['patient_email'] ?? ''));
        if ($patientEmail !== '' && !filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
            $patientEmail = '';
        }

        $recipientEmail = '';
        $paymongoSecret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
        $checkoutSessionId = trim((string) ($payRow['reference_number'] ?? ''));
        if ($paymongoSecret !== '' && $checkoutSessionId !== '') {
            $recipientEmail = staff_paymongo_get_checkout_billing_email($checkoutSessionId, $paymongoSecret);
        }
        if ($recipientEmail === '') {
            $recipientEmail = $patientEmail;
        }

        if ($recipientEmail !== '' && function_exists('send_smtp_gmail')) {
            $patientName = trim(trim((string) ($patientRow['first_name'] ?? '')) . ' ' . trim((string) ($patientRow['last_name'] ?? '')));
            if ($patientName === '') {
                $patientName = 'Patient';
            }

            $clinicLogoPath = isset($_SESSION['clinic_logo_nav']) ? trim((string) $_SESSION['clinic_logo_nav']) : '';
            if ($clinicLogoPath === '' && isset($_SESSION['clinic_logo']) && trim((string) $_SESSION['clinic_logo']) !== '') {
                $clinicLogoPath = trim((string) $_SESSION['clinic_logo']);
            }
            $clinicLogoUrl = '';
            if ($clinicLogoPath !== '') {
                if (preg_match('#^https?://#i', $clinicLogoPath)) {
                    $clinicLogoUrl = $clinicLogoPath;
                } else {
                    $assetPath = ltrim($clinicLogoPath, '/');
                    $base = defined('BASE_URL') ? trim((string) BASE_URL) : '';
                    if ($base !== '' && preg_match('#^https?://#i', $base)) {
                        $clinicLogoUrl = rtrim($base, '/') . '/' . $assetPath;
                    }
                }
            }

            $paidLabel = '₱' . number_format((float) $amount, 2);
            $remainingLabel = '₱' . number_format((float) max(0.0, $pendingBalance), 2);
            $paymentDateObj = staff_payment_recording_to_manila_datetime($paymentDateTime);
            $paymentDateLabel = $paymentDateObj instanceof DateTimeImmutable
                ? $paymentDateObj->format('F d, Y h:i A')
                : '-';
            $referenceLabel = trim((string) ($payRow['reference_number'] ?? ''));
            if ($referenceLabel === '') {
                $referenceLabel = $paymentId;
            }
            $methodRaw = strtolower(trim((string) ($payRow['payment_method'] ?? '')));
            $allowedMethods = [
                'gcash' => 'GCash',
                'cash' => 'Cash',
                'bank_transfer' => 'Bank Transfer',
                'credit_card' => 'Credit Card',
            ];
            $methodLabel = $allowedMethods[$methodRaw] ?? ucfirst(str_replace('_', ' ', $methodRaw !== '' ? $methodRaw : 'online'));
            $serviceLabel = 'Payment';
            $serviceItems = [[
                'name' => 'Payment',
                'amount' => round((float) $amount, 2),
            ]];
            $servicesTotalLabel = $paidLabel;

            $emailSubject = 'Payment Receipt - ' . $clinicName;
            $emailBodyText = "Clinic: {$clinicName}\n"
                . "Patient: {$patientName}\n"
                . "Booking ID: {$bookingId}\n"
                . "Payment ID: {$paymentId}\n"
                . "Reference: {$referenceLabel}\n"
                . "Services: {$serviceLabel}\n"
                . "Service Total: {$servicesTotalLabel}\n"
                . "Amount Paid: {$paidLabel}\n"
                . "Remaining Balance: {$remainingLabel}\n"
                . "Payment Date: {$paymentDateLabel}\n";

            $emailBodyHtml = staff_payment_recording_build_receipt_email_html([
                'clinic_name' => $clinicName,
                'clinic_logo' => $clinicLogoUrl,
                'patient_name' => $patientName,
                'patient_id' => trim((string) $patientId),
                'reference' => $referenceLabel,
                'payment_id' => (string) $paymentId,
                'service' => $serviceLabel,
                'service_items' => $serviceItems,
                'services_total' => $servicesTotalLabel,
                'payment_date' => $paymentDateLabel,
                'payment_method' => $methodLabel,
                'amount_paid' => $paidLabel,
                'remaining_balance' => $remainingLabel,
            ]);

            if (send_smtp_gmail($recipientEmail, $emailSubject, $emailBodyText, $emailBodyHtml)) {
                $_SESSION['staff_last_receipt_email'] = [
                    'tenant_id' => $tenantId,
                    'payment_id' => $paymentId,
                    'sent_at' => time(),
                ];
                $_SESSION['staff_receipt_email_success'] = '1';
            }
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
