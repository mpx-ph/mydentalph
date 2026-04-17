<?php
$staff_nav_active = 'payments';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/../mail_config.php';
require_once __DIR__ . '/includes/appointment_db_tables.php';
require_once __DIR__ . '/includes/staff_installment_helpers.php';

// Ensure all generated timestamps in this page use Philippine Standard Time.
date_default_timezone_set('Asia/Manila');

/**
 * Parse a stored payment datetime and normalize it to Philippine Standard Time.
 *
 * Stored payment datetimes are treated as UTC when no explicit timezone is present.
 */
function staff_payment_recording_to_manila_datetime(string $rawValue): ?DateTimeImmutable
{
    $raw = trim($rawValue);
    if ($raw === '') {
        return null;
    }

    $utc = new DateTimeZone('UTC');
    $manila = new DateTimeZone('Asia/Manila');

    // If the timestamp already has timezone information, respect it.
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
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $raw, $utc);
        if ($dt instanceof DateTimeImmutable) {
            return $dt->setTimezone($manila);
        }
    }

    try {
        $dt = new DateTimeImmutable($raw, $utc);
        return $dt->setTimezone($manila);
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @return list<array{id:int, installment_number:int, amount_due:float, status:string, due_date:string}>
 */
function staff_payment_recording_fetch_installments(PDO $pdo, ?string $installmentsTableName, string $tenantId, string $bookingId): array
{
    if ($installmentsTableName === null || $bookingId === '') {
        return [];
    }
    $instColumns = staff_payment_recording_installments_table_columns($pdo, $installmentsTableName);
    $hasDueDateColumn = in_array('due_date', $instColumns, true);
    $quoted = '`' . str_replace('`', '``', $installmentsTableName) . '`';
    $sql = "
        SELECT id, installment_number, amount_due, status,
               " . ($hasDueDateColumn ? "COALESCE(i.due_date, '')" : "''") . " AS due_date
        FROM {$quoted} i
        WHERE i.booking_id = ?
          AND (
              i.tenant_id = ?
              OR i.tenant_id IS NULL
              OR TRIM(COALESCE(i.tenant_id, '')) = ''
          )
        ORDER BY i.installment_number ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bookingId, $tenantId]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'installment_number' => (int) ($row['installment_number'] ?? 0),
            'amount_due' => round((float) ($row['amount_due'] ?? 0), 2),
            'status' => trim((string) ($row['status'] ?? '')),
            'due_date' => trim((string) ($row['due_date'] ?? '')),
        ];
    }
    return $out;
}

function staff_payment_recording_installment_is_paid(string $status): bool
{
    $s = strtolower(trim($status));
    return $s === 'paid' || $s === 'completed';
}

/**
 * Incrementally sync a treatment's financial fields when a payment is recorded.
 *
 * This should only be called for successful payments (e.g. tbl_payments.status = 'completed' or 'paid')
 * and not for pending, cancelled, or failed payments.
 */
function staff_payment_recording_apply_payment_to_treatment(
    PDO $pdo,
    string $tenantId,
    string $treatmentId,
    float $amount,
    int $monthsPaidIncrement = 0
): void
{
    // Delegate to shared helper so all payment entry points keep treatment progress in sync.
    staff_treatments_apply_payment($pdo, $tenantId, $treatmentId, $amount, $monthsPaidIncrement);
}

function staff_payment_recording_financial_status(
    float $totalCost,
    float $totalPaid,
    string $appointmentDate = '',
    bool $isInstallmentPlan = false,
    array $installmentSchedule = []
): string
{
    $today = date('Y-m-d');
    $remaining = max(0, round($totalCost - $totalPaid, 2));
    if ($remaining <= 0.009) {
        return 'PAID';
    }
    if ($isInstallmentPlan && $installmentSchedule !== []) {
        $hasOverdueUnpaidInstallment = false;
        $allInstallmentsPaid = true;
        foreach ($installmentSchedule as $installment) {
            $instStatus = trim((string) ($installment['status'] ?? ''));
            $isInstallmentPaid = staff_payment_recording_installment_is_paid($instStatus);
            if ($isInstallmentPaid) {
                continue;
            }
            $allInstallmentsPaid = false;
            $dueDate = trim((string) ($installment['due_date'] ?? ''));
            if ($dueDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) && $dueDate < $today) {
                $hasOverdueUnpaidInstallment = true;
            }
        }
        if ($allInstallmentsPaid) {
            return 'PAID';
        }
        if ($totalPaid > 0) {
            return $hasOverdueUnpaidInstallment ? 'OVERDUE' : 'PARTIAL';
        }
        return $hasOverdueUnpaidInstallment ? 'OVERDUE' : 'UNPAID';
    }
    if ($totalPaid > 0) {
        if ($appointmentDate !== '' && $appointmentDate < $today) {
            return 'OVERDUE';
        }
        return 'PARTIAL';
    }
    if ($appointmentDate !== '' && $appointmentDate < $today) {
        return 'OVERDUE';
    }
    return 'UNPAID';
}

function staff_payment_recording_normalize_service_category(string $category): string
{
    $raw = strtolower(trim($category));
    if ($raw !== '') {
        $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;
    }
    if ($raw === '') {
        return '';
    }
    if (str_contains($raw, 'crowns') && str_contains($raw, 'bridges')) {
        return 'crowns_and_bridges';
    }
    if (str_contains($raw, 'oral') && str_contains($raw, 'surgery')) {
        return 'oral_surgery';
    }
    if (str_contains($raw, 'orthodont')) {
        return 'orthodontics';
    }
    if (str_contains($raw, 'pediatric')) {
        return 'pediatric_dentistry';
    }
    if (str_contains($raw, 'cosmetic')) {
        return 'cosmetic_dentistry';
    }
    if (str_contains($raw, 'restorative')) {
        return 'restorative_dentistry';
    }
    if (str_contains($raw, 'general')) {
        return 'general_dentistry';
    }
    if (str_contains($raw, 'specialized') || str_contains($raw, 'specialised')) {
        return 'specialized_and_others';
    }
    return '';
}

function staff_payment_recording_disallowed_combination_message(array $categories): string
{
    $set = [];
    foreach ($categories as $category) {
        $normalized = staff_payment_recording_normalize_service_category((string) $category);
        if ($normalized !== '') {
            $set[$normalized] = true;
        }
    }
    $has = static function (string $category) use ($set): bool {
        return isset($set[$category]);
    };
    if ($has('oral_surgery') && $has('crowns_and_bridges')) {
        return 'Oral Surgery and Crowns and Bridges cannot be combined in one payment update because healing must occur first before crown placement. Please schedule these services separately if needed.';
    }
    if ($has('orthodontics') && $has('crowns_and_bridges')) {
        return 'Orthodontics and Crowns and Bridges cannot be combined because permanent bridges should not be placed while teeth are still moving. Please schedule these services separately if needed.';
    }
    if ($has('orthodontics') && $has('cosmetic_dentistry')) {
        return 'Orthodontics and Cosmetic Dentistry cannot be combined because cosmetic procedures like veneers should be done after alignment is complete. Please schedule these services separately if needed.';
    }
    if ($has('pediatric_dentistry') && $has('cosmetic_dentistry')) {
        return 'Pediatric Dentistry and Cosmetic Dentistry cannot be combined because major cosmetic procedures are not appropriate for pediatric patients. Please schedule these services separately if needed.';
    }
    return '';
}

/**
 * @return array{regular_downpayment_percentage: float, long_term_min_downpayment: float}
 */
function staff_payment_recording_load_payment_settings(PDO $pdo, string $tenantId): array
{
    static $cache = [];
    if (isset($cache[$tenantId])) {
        return $cache[$tenantId];
    }
    try {
        $stmt = $pdo->prepare('SELECT regular_downpayment_percentage, long_term_min_downpayment FROM tbl_payment_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = false;
    }
    if (!$row) {
        $cache[$tenantId] = ['regular_downpayment_percentage' => 20.0, 'long_term_min_downpayment' => 500.0];
    } else {
        $cache[$tenantId] = [
            'regular_downpayment_percentage' => (float) $row['regular_downpayment_percentage'],
            'long_term_min_downpayment' => (float) $row['long_term_min_downpayment'],
        ];
    }
    return $cache[$tenantId];
}

function staff_payment_recording_effective_installment_downpayment_amount(array $ps, float $price, $storedDown): float
{
    $base = ($storedDown !== null && $storedDown !== '')
        ? (float) $storedDown
        : (float) $ps['long_term_min_downpayment'];
    $base = max(0.0, $base);
    if ($price > 0 && $base > $price) {
        return round($price, 2);
    }
    return round($base, 2);
}

/**
 * Build a deterministic fallback installment plan from the stored treatment snapshot.
 * This never uses mutable payment settings, so existing treatment math stays stable.
 *
 * @return array{duration_months:int, months_paid:int, total_cost:float, remaining_balance:float, downpayment_amount:float, monthly_amount:float}
 */
function staff_payment_recording_plan_from_treatment_snapshot(array $treatment): array
{
    $durationMonths = max(1, (int) ($treatment['duration_months'] ?? 0));
    $monthsPaidRaw = (int) ($treatment['months_paid'] ?? 0);
    $monthsPaid = max(0, min($durationMonths, $monthsPaidRaw));
    $totalCost = max(0.0, round((float) ($treatment['total_cost'] ?? 0), 2));
    $remainingBalanceRaw = round((float) ($treatment['remaining_balance'] ?? 0), 2);
    $remainingBalance = max(0.0, min($totalCost, $remainingBalanceRaw));
    $amountPaid = max(0.0, round($totalCost - $remainingBalance, 2));

    $monthlyAmount = 0.0;
    if ($durationMonths > 1) {
        $monthlyAmount = round($totalCost / $durationMonths, 2);
    } elseif ($durationMonths === 1) {
        $monthlyAmount = $totalCost;
    }

    $downpaymentAmount = 0.0;
    if ($monthsPaid > 0 && $durationMonths > 1) {
        $monthlyPortion = round($remainingBalance / max(1, $durationMonths - $monthsPaid), 2);
        $derivedDown = round($amountPaid - ($monthlyPortion * max(0, $monthsPaid - 1)), 2);
        if ($derivedDown > 0.009) {
            $downpaymentAmount = min($totalCost, max(0.0, $derivedDown));
            $monthlyAmount = round(($totalCost - $downpaymentAmount) / max(1, $durationMonths - 1), 2);
        }
    }

    return [
        'duration_months' => $durationMonths,
        'months_paid' => $monthsPaid,
        'total_cost' => $totalCost,
        'remaining_balance' => $remainingBalance,
        'downpayment_amount' => round($downpaymentAmount, 2),
        'monthly_amount' => round(max(0.0, $monthlyAmount), 2),
    ];
}

function staff_payment_recording_send_receipt_email(string $toEmail, string $subject, string $bodyText, string $bodyHtml): bool
{
    if (!function_exists('send_smtp_gmail')) {
        return false;
    }
    return send_smtp_gmail($toEmail, $subject, $bodyText, $bodyHtml);
}

function staff_payment_recording_absolute_asset_url(string $rawPath): string
{
    $value = trim($rawPath);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }
    $path = ltrim($value, '/');
    $base = defined('BASE_URL') ? trim((string) BASE_URL) : '';
    if ($base !== '' && preg_match('#^https?://#i', $base)) {
        return rtrim($base, '/') . '/' . $path;
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $value;
    }
    if ($base !== '' && $base !== '/') {
        return $scheme . '://' . $host . '/' . trim($base, '/') . '/' . $path;
    }
    return $scheme . '://' . $host . '/' . $path;
}

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
        $itemAmount = htmlspecialchars(trim((string) ($item['amount'] ?? '')), ENT_QUOTES, 'UTF-8');
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

/**
 * @return list<array{number:int, amount:float, status:string}>
 */
function staff_payment_recording_build_installments_plan(float $totalCost, int $durationMonths, string $paymentOption, float $downpaymentAmount): array
{
    $installments = [];
    $remainingAmount = $totalCost;

    if ($paymentOption === 'downpayment' && $downpaymentAmount > 0) {
        $installments[] = [
            'number' => 1,
            'amount' => round($downpaymentAmount, 2),
            'status' => 'pending',
        ];
        $remainingAmount -= $downpaymentAmount;
        // Duration months are monthly installments after downpayment.
        $installmentCount = $durationMonths;
    } else {
        $installmentCount = $durationMonths;
    }

    if ($installmentCount > 0) {
        $monthlyAmount = $remainingAmount / $installmentCount;
        $startNumber = ($paymentOption === 'downpayment' && $downpaymentAmount > 0) ? 2 : 1;
        for ($i = 0; $i < $installmentCount; $i++) {
            $installmentNumber = $startNumber + $i;
            if ($i === $installmentCount - 1) {
                $amount = $remainingAmount - ($monthlyAmount * ($installmentCount - 1));
            } else {
                $amount = $monthlyAmount;
            }
            $status = 'pending';
            if ($paymentOption === 'downpayment' && $downpaymentAmount > 0 && $installmentNumber === 2) {
                $status = 'book_visit';
            }
            $installments[] = [
                'number' => $installmentNumber,
                'amount' => round($amount, 2),
                'status' => $status,
            ];
        }
    }

    return $installments;
}

/**
 * @return list<string>
 */
function staff_payment_recording_installments_table_columns(PDO $pdo, string $tableName): array
{
    static $cache = [];
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$tableName]);
    $cache[$tableName] = array_map('strtolower', array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    return $cache[$tableName];
}

/**
 * Create missing installment rows for a booking that is installment-priced via tbl_appointment_services + tbl_services
 * but has no rows yet (mirrors clinic/api/appointments.php createInstallments).
 */
function staff_payment_recording_ensure_installment_schedule(
    PDO $pdo,
    ?string $installmentsTableName,
    string $tenantId,
    string $bookingId,
    bool $supportsAppointmentServicesTable,
    bool $supportsServiceEnableInstallmentColumn
): bool {
    if ($installmentsTableName === null || $bookingId === '' || $tenantId === '') {
        return false;
    }
    if (!$supportsAppointmentServicesTable || !$supportsServiceEnableInstallmentColumn) {
        return false;
    }

    $quoted = '`' . str_replace('`', '``', $installmentsTableName) . '`';
    $pdo->beginTransaction();
    try {
        $cntStmt = $pdo->prepare("
            SELECT COUNT(*) FROM {$quoted} i
            WHERE i.booking_id = ?
              AND (
                  i.tenant_id = ?
                  OR i.tenant_id IS NULL
                  OR TRIM(COALESCE(i.tenant_id, '')) = ''
              )
        ");
        $cntStmt->execute([$bookingId, $tenantId]);
        if ((int) $cntStmt->fetchColumn() > 0) {
            $existingStmt = $pdo->prepare("
                SELECT id, status
                FROM {$quoted} i
                WHERE i.booking_id = ?
                  AND (
                      i.tenant_id = ?
                      OR i.tenant_id IS NULL
                      OR TRIM(COALESCE(i.tenant_id, '')) = ''
                  )
                ORDER BY i.installment_number ASC
            ");
            $existingStmt->execute([$bookingId, $tenantId]);
            $existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $hasSettledInstallment = false;
            foreach ($existingRows as $existingRow) {
                if (staff_payment_recording_installment_is_paid((string) ($existingRow['status'] ?? ''))) {
                    $hasSettledInstallment = true;
                    break;
                }
            }
            if ($hasSettledInstallment) {
                $pdo->commit();
                return true;
            }

            // All rows are still unsettled; regenerate schedule to match current service settings.
            $deleteStmt = $pdo->prepare("
                DELETE FROM {$quoted}
                WHERE booking_id = ?
                  AND (
                      tenant_id = ?
                      OR tenant_id IS NULL
                      OR TRIM(COALESCE(tenant_id, '')) = ''
                  )
            ");
            $deleteStmt->execute([$bookingId, $tenantId]);
        }

        $appointmentTreatmentId = '';
        $primaryInstallmentServiceId = '';
        $apptTreatmentStmt = $pdo->prepare("
            SELECT COALESCE(a.treatment_id, '') AS treatment_id
            FROM tbl_appointments a
            WHERE a.tenant_id = ?
              AND a.booking_id = ?
            LIMIT 1
        ");
        $apptTreatmentStmt->execute([$tenantId, $bookingId]);
        $apptTreatmentRow = $apptTreatmentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $appointmentTreatmentId = trim((string) ($apptTreatmentRow['treatment_id'] ?? ''));
        if ($appointmentTreatmentId !== '') {
            $primarySvcStmt = $pdo->prepare("
                SELECT COALESCE(t.primary_service_id, '') AS primary_service_id
                FROM tbl_treatments t
                WHERE t.tenant_id = ?
                  AND t.treatment_id = ?
                LIMIT 1
            ");
            $primarySvcStmt->execute([$tenantId, $appointmentTreatmentId]);
            $primarySvcRow = $primarySvcStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $primaryInstallmentServiceId = trim((string) ($primarySvcRow['primary_service_id'] ?? ''));
        }

        $plan = [];
        $planTreatmentId = '';
        $svcRow = null;

        $svcStmt = $pdo->prepare("
            SELECT
                sv.service_id,
                sv.installment_downpayment,
                sv.installment_duration_months,
                sv.price,
                COALESCE(NULLIF(TRIM(aps.service_type), ''), 'installment') AS normalized_service_type
            FROM tbl_appointment_services aps
            INNER JOIN tbl_services sv
                ON sv.tenant_id = aps.tenant_id
               AND sv.service_id = aps.service_id
            WHERE aps.tenant_id = ?
              AND aps.booking_id = ?
              AND COALESCE(sv.enable_installment, 0) = 1
            ORDER BY
                CASE
                    WHEN ? <> '' AND sv.service_id = ? THEN 0
                    WHEN LOWER(COALESCE(NULLIF(TRIM(aps.service_type), ''), 'installment')) = 'installment' THEN 1
                    ELSE 2
                END ASC,
                sv.price DESC
            LIMIT 1
        ");
        $svcStmt->execute([$tenantId, $bookingId, $primaryInstallmentServiceId, $primaryInstallmentServiceId]);
        $svcRow = $svcStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $paymentSettings = staff_payment_recording_load_payment_settings($pdo, $tenantId);

        if ($appointmentTreatmentId !== '') {
            $treatStmt = $pdo->prepare("
                SELECT treatment_id, total_cost, remaining_balance, duration_months, months_paid
                FROM tbl_treatments
                WHERE tenant_id = ?
                  AND treatment_id = ?
                LIMIT 1
            ");
            $treatStmt->execute([$tenantId, $appointmentTreatmentId]);
            $treatmentRow = $treatStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($treatmentRow) {
                $snapshot = staff_payment_recording_plan_from_treatment_snapshot($treatmentRow);
                $durationMonths = (int) $snapshot['duration_months'];
                $monthsPaid = (int) $snapshot['months_paid'];
                $totalCost = (float) $snapshot['total_cost'];
                $downpaymentAmount = (float) $snapshot['downpayment_amount'];
                $monthlyAmount = (float) $snapshot['monthly_amount'];

                // Service-level installment downpayment is authoritative when configured.
                if ($svcRow) {
                    $serviceDownRaw = $svcRow['installment_downpayment'] ?? null;
                    $effectiveDown = staff_payment_recording_effective_installment_downpayment_amount(
                        $paymentSettings,
                        $totalCost,
                        $serviceDownRaw
                    );
                    $downpaymentAmount = $effectiveDown;
                    if ($durationMonths > 1 && $downpaymentAmount > 0.009) {
                        $monthlyAmount = round(($totalCost - $downpaymentAmount) / max(1, $durationMonths), 2);
                    }
                }

                if ($durationMonths > 0 && $totalCost > 0.009) {
                    $useDownpaymentFlow = ($durationMonths > 1 && $downpaymentAmount > 0.009);
                    $plan = staff_payment_recording_build_installments_plan(
                        $totalCost,
                        $durationMonths,
                        $useDownpaymentFlow ? 'downpayment' : 'installment',
                        $useDownpaymentFlow ? $downpaymentAmount : 0.0
                    );
                    if ($plan !== []) {
                        foreach ($plan as $idx => $slot) {
                            $slotNo = (int) ($slot['number'] ?? 0);
                            $plan[$idx]['status'] = ($slotNo > 0 && $slotNo <= $monthsPaid) ? 'paid' : 'pending';
                        }
                        if ($monthsPaid > 0 && $monthsPaid < count($plan)) {
                            $plan[$monthsPaid]['status'] = 'book_visit';
                        }
                        if ($useDownpaymentFlow && $monthsPaid <= 0 && isset($plan[1])) {
                            $plan[1]['status'] = 'book_visit';
                        }
                    }
                    $planTreatmentId = $appointmentTreatmentId;
                }
            }
        }

        if ($plan === []) {
            if (!$svcRow) {
                $pdo->commit();
                return false;
            }

            $apptStmt = $pdo->prepare('
                SELECT COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost
                FROM tbl_appointments a
                WHERE a.tenant_id = ?
                  AND a.booking_id = ?
                LIMIT 1
            ');
            $apptStmt->execute([$tenantId, $bookingId]);
            $apptRow = $apptStmt->fetch(PDO::FETCH_ASSOC);
            $totalCost = $apptRow ? (float) ($apptRow['total_treatment_cost'] ?? 0) : 0.0;
            if ($totalCost <= 0.009) {
                $pdo->commit();
                return false;
            }

            $storedDown = $svcRow['installment_downpayment'] ?? null;
            $effDown = staff_payment_recording_effective_installment_downpayment_amount(
                $paymentSettings,
                $totalCost,
                $storedDown
            );

            $rawDur = $svcRow['installment_duration_months'] ?? null;
            $durationMonths = ($rawDur === null || $rawDur === '') ? 12 : (int) $rawDur;
            $durationMonths = max(1, $durationMonths);

            $paymentOption = ($durationMonths > 1 && $effDown > 0.009) ? 'downpayment' : 'installment';
            $downForPlan = ($paymentOption === 'downpayment') ? $effDown : 0.0;

            $plan = staff_payment_recording_build_installments_plan($totalCost, $durationMonths, $paymentOption, $downForPlan);
            if ($plan === []) {
                $pdo->commit();
                return false;
            }
        }

        $cols = staff_payment_recording_installments_table_columns($pdo, $installmentsTableName);
        $hasTenant = in_array('tenant_id', $cols, true);
        $hasCreatedAt = in_array('created_at', $cols, true);
        $hasTreatmentId = in_array('treatment_id', $cols, true);

        foreach ($plan as $inst) {
            $fields = [];
            $placeholders = [];
            $params = [];
            if ($hasTenant) {
                $fields[] = 'tenant_id';
                $placeholders[] = '?';
                $params[] = $tenantId;
            }
            $fields[] = 'booking_id';
            $placeholders[] = '?';
            $params[] = $bookingId;
            if ($hasTreatmentId) {
                $fields[] = 'treatment_id';
                $placeholders[] = '?';
                $params[] = ($planTreatmentId !== '') ? $planTreatmentId : null;
            }
            $fields[] = 'installment_number';
            $placeholders[] = '?';
            $params[] = (int) $inst['number'];
            $fields[] = 'amount_due';
            $placeholders[] = '?';
            $params[] = (float) $inst['amount'];
            $fields[] = 'status';
            $placeholders[] = '?';
            $params[] = (string) $inst['status'];
            if ($hasCreatedAt) {
                $fields[] = 'created_at';
                $placeholders[] = 'NOW()';
            }
            $sql = 'INSERT INTO ' . $quoted . ' (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $ins = $pdo->prepare($sql);
            $ins->execute($params);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('staff_payment_recording_ensure_installment_schedule: ' . $e->getMessage());
        return false;
    }
}

// Dentist role restriction: redirect to dashboard
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['user_role']) && strtolower(trim((string) $_SESSION['user_role'])) === 'dentist') {
    header('Location: StaffDashboard.php');
    exit;
}
if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
$paymentSuccess = '';
$paymentError = '';
$receiptEmailSuccess = '';
$clinicDisplayName = isset($_SESSION['clinic_name']) ? trim((string) $_SESSION['clinic_name']) : '';
$clinicLogoPath = isset($_SESSION['clinic_logo_nav']) ? trim((string) $_SESSION['clinic_logo_nav']) : '';
if ($clinicDisplayName === '') {
    $clinicDisplayName = 'MyDental Philippines';
}
if ($clinicLogoPath === '') {
    $clinicLogoPath = 'DRCGLogo2.png';
}
$clinicLogoUrl = staff_payment_recording_absolute_asset_url($clinicLogoPath);
if (isset($_GET['payment_success']) && $_GET['payment_success'] === '1') {
    $paymentSuccess = 'Payment recorded successfully.';
}
if (isset($_GET['paymongo_error']) && $_GET['paymongo_error'] === '1') {
    $paymentError = 'Could not confirm the online payment. If money was debited, contact support with the booking reference and time of payment.';
}
if (isset($_SESSION['staff_receipt_email_success']) && $_SESSION['staff_receipt_email_success'] === '1') {
    $receiptEmailSuccess = 'The receipt has been sent to the patient’s email.';
    unset($_SESSION['staff_receipt_email_success']);
}
$allowedMethods = [
    'gcash' => 'GCash',
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'credit_card' => 'Credit Card',
];
$selectedMethod = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedMethod = strtolower(trim((string) ($_POST['payment_method'] ?? '')));
    if ($selectedMethod !== '' && !isset($allowedMethods[$selectedMethod])) {
        $selectedMethod = '__invalid__';
    }
}
$selectedMethodForUi = ($selectedMethod === '__invalid__') ? '' : $selectedMethod;

$summaryTotalRevenue = 0.0;
$summaryTodayRevenue = 0.0;
$summaryTotalPayments = 0;
$recentPayments = [];
$transactionCandidates = [];
$transactionDebugRows = [];
$availableServices = [];
$supportsPaymentTypeColumn = false;
$supportsAppointmentVisitTypeColumn = false;
$supportsAppointmentServicesTable = false;
$appointmentServiceColumns = [];
$supportsAppointmentServiceTypeColumn = false;
$supportsAppointmentServiceAppointmentIdColumn = false;
$installmentsTableName = null;
$supportsServiceEnableInstallmentColumn = false;
$supportsPaymentsInstallmentNumberColumn = false;
$formSelectedBookingId = trim((string) ($_POST['selected_booking_id'] ?? ''));
$formSelectedTransactionType = strtolower(trim((string) ($_POST['selected_transaction_type'] ?? 'regular')));
$formSelectedTreatmentId = trim((string) ($_POST['selected_treatment_id'] ?? ''));
if (!in_array($formSelectedTransactionType, ['regular', 'installment'], true)) {
    $formSelectedTransactionType = 'regular';
}
$formInstallmentFlow = trim((string) ($_POST['installment_flow'] ?? 'regular'));
$formInstallmentPayMode = trim((string) ($_POST['installment_pay_mode'] ?? 'full'));
$formInstallmentSlotCount = (int) ($_POST['installment_slot_count'] ?? 1);
if ($formInstallmentSlotCount < 1) {
    $formInstallmentSlotCount = 1;
}
$formPatientQuery = trim((string) ($_POST['patient_query'] ?? ''));
$formAmount = trim((string) ($_POST['amount'] ?? ''));
$formPaymentDate = trim((string) ($_POST['payment_date'] ?? date('Y-m-d')));
$formNotes = trim((string) ($_POST['notes'] ?? ''));
$formServiceIds = [];
if (isset($_POST['additional_service_ids']) && is_array($_POST['additional_service_ids'])) {
    foreach ($_POST['additional_service_ids'] as $serviceIdValue) {
        $serviceIdValue = trim((string) $serviceIdValue);
        if ($serviceIdValue !== '') {
            $formServiceIds[] = $serviceIdValue;
        }
    }
    $formServiceIds = array_values(array_unique($formServiceIds));
}

try {
    $pdo = getDBConnection();

    if ($tenantId === '' && $currentTenantSlug !== '') {
        $tenantStmt = $pdo->prepare('SELECT tenant_id FROM tbl_tenants WHERE clinic_slug = ? LIMIT 1');
        $tenantStmt->execute([$currentTenantSlug]);
        $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenantRow && isset($tenantRow['tenant_id'])) {
            $tenantId = (string) $tenantRow['tenant_id'];
        }
    }

    if ($tenantId !== '') {
        $tenantProfileStmt = $pdo->prepare('SELECT clinic_name FROM tbl_tenants WHERE tenant_id = ? LIMIT 1');
        $tenantProfileStmt->execute([$tenantId]);
        $tenantProfile = $tenantProfileStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $tenantClinicName = trim((string) ($tenantProfile['clinic_name'] ?? ''));
        if ($tenantClinicName !== '') {
            $clinicDisplayName = $tenantClinicName;
        }
        try {
            $tenantLogoStmt = $pdo->prepare("
                SELECT option_value
                FROM clinic_customization_tenant
                WHERE tenant_id = ?
                  AND option_key IN ('logo_nav', 'logo', 'clinic_logo', 'logo_email')
                ORDER BY FIELD(option_key, 'logo_nav', 'clinic_logo', 'logo_email', 'logo')
                LIMIT 1
            ");
            $tenantLogoStmt->execute([$tenantId]);
            $tenantLogoValue = trim((string) ($tenantLogoStmt->fetchColumn() ?: ''));
            if ($tenantLogoValue !== '') {
                $clinicLogoPath = $tenantLogoValue;
            } elseif (isset($_SESSION['clinic_logo']) && trim((string) $_SESSION['clinic_logo']) !== '') {
                $clinicLogoPath = trim((string) $_SESSION['clinic_logo']);
            }
        } catch (Throwable $logoErr) {
            if (isset($_SESSION['clinic_logo']) && trim((string) $_SESSION['clinic_logo']) !== '') {
                $clinicLogoPath = trim((string) $_SESSION['clinic_logo']);
            }
        }
        $clinicLogoUrl = staff_payment_recording_absolute_asset_url($clinicLogoPath);
    }

    if ($tenantId !== '' && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['paymongo_cancel']) && (string) $_GET['paymongo_cancel'] === '1') {
        $cxPid = trim((string) ($_GET['pid'] ?? ''));
        $cxToken = trim((string) ($_GET['token'] ?? ''));
        if ($cxPid !== '' && $cxToken !== '' && isset($_SESSION['staff_paymongo_checkout']) && is_array($_SESSION['staff_paymongo_checkout'])) {
            $st = $_SESSION['staff_paymongo_checkout'];
            if (($st['payment_id'] ?? '') === $cxPid && isset($st['token']) && hash_equals((string) $st['token'], $cxToken) && ($st['tenant_id'] ?? '') === $tenantId) {
                $cancelStmt = $pdo->prepare("UPDATE tbl_payments SET status = 'cancelled' WHERE tenant_id = ? AND payment_id = ? AND status = 'pending' LIMIT 1");
                $cancelStmt->execute([$tenantId, $cxPid]);
                $paymentError = 'Online checkout was cancelled; no payment was recorded.';
                unset($_SESSION['staff_paymongo_checkout']);
            }
        }
    }

    if ($tenantId !== '') {
        $paymentTypeColumnStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_payments'
              AND COLUMN_NAME = 'payment_type'
            LIMIT 1
        ");
        $paymentTypeColumnStmt->execute();
        $supportsPaymentTypeColumn = (bool) $paymentTypeColumnStmt->fetchColumn();

        $appointmentVisitTypeColumnStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_appointments'
              AND COLUMN_NAME = 'visit_type'
            LIMIT 1
        ");
        $appointmentVisitTypeColumnStmt->execute();
        $supportsAppointmentVisitTypeColumn = (bool) $appointmentVisitTypeColumnStmt->fetchColumn();

        $appointmentServicesTableStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_appointment_services'
            LIMIT 1
        ");
        $appointmentServicesTableStmt->execute();
        $supportsAppointmentServicesTable = (bool) $appointmentServicesTableStmt->fetchColumn();

        if ($supportsAppointmentServicesTable) {
            $appointmentServicesColumnsStmt = $pdo->prepare("
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'tbl_appointment_services'
            ");
            $appointmentServicesColumnsStmt->execute();
            $appointmentServiceColumns = array_map('strval', $appointmentServicesColumnsStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $supportsAppointmentServiceTypeColumn = in_array('service_type', $appointmentServiceColumns, true);
            $supportsAppointmentServiceAppointmentIdColumn = in_array('appointment_id', $appointmentServiceColumns, true);
        }

        foreach (['tbl_installments', 'installments'] as $installmentsCandidate) {
            $instTableStmt = $pdo->prepare("
                SELECT 1
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                LIMIT 1
            ");
            $instTableStmt->execute([$installmentsCandidate]);
            if ((bool) $instTableStmt->fetchColumn()) {
                $installmentsTableName = $installmentsCandidate;
                break;
            }
        }

        $serviceInstallmentColStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_services'
              AND COLUMN_NAME = 'enable_installment'
            LIMIT 1
        ");
        $serviceInstallmentColStmt->execute();
        $supportsServiceEnableInstallmentColumn = (bool) $serviceInstallmentColStmt->fetchColumn();

        $paymentsInstColStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_payments'
              AND COLUMN_NAME = 'installment_number'
            LIMIT 1
        ");
        $paymentsInstColStmt->execute();
        $supportsPaymentsInstallmentNumberColumn = (bool) $paymentsInstColStmt->fetchColumn();
    }

    if ($tenantId !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $postAction = trim((string) ($_POST['action'] ?? 'record_payment'));
        if ($postAction === 'send_receipt_email') {
            $receiptPaymentId = trim((string) ($_POST['receipt_payment_id'] ?? ''));
            if ($receiptPaymentId === '') {
                $paymentError = 'Missing payment receipt reference.';
            } else {
                $recentReceiptState = isset($_SESSION['staff_last_receipt_email']) && is_array($_SESSION['staff_last_receipt_email'])
                    ? $_SESSION['staff_last_receipt_email']
                    : [];
                $recentPaymentId = trim((string) ($recentReceiptState['payment_id'] ?? ''));
                $recentTimestamp = (int) ($recentReceiptState['sent_at'] ?? 0);
                if ($recentPaymentId === $receiptPaymentId && $recentTimestamp > 0 && (time() - $recentTimestamp) <= 45) {
                    $receiptEmailSuccess = 'The receipt has been sent to the patient’s email.';
                } else {
                $receiptSql = "
                    SELECT
                        py.payment_id,
                        py.patient_id,
                        py.booking_id,
                        py.amount,
                        py.payment_date,
                        py.payment_method,
                        py.reference_number,
                        py.status,
                        COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
                        COALESCE((
                            SELECT SUM(py2.amount)
                            FROM tbl_payments py2
                            WHERE py2.tenant_id = py.tenant_id
                              AND py2.booking_id = py.booking_id
                              AND py2.status IN ('completed', 'paid')
                        ), 0) AS booking_total_paid,
                        COALESCE(a.service_type, '') AS service_type,
                        COALESCE(a.service_description, '') AS service_description,
                        COALESCE(p.first_name, '') AS patient_first_name,
                        COALESCE(p.last_name, '') AS patient_last_name,
                        COALESCE(NULLIF(u_linked.email, ''), NULLIF(u_owner.email, ''), '') AS patient_email
                    FROM tbl_payments py
                    LEFT JOIN tbl_appointments a
                        ON a.tenant_id = py.tenant_id
                       AND a.booking_id = py.booking_id
                    LEFT JOIN tbl_patients p
                        ON p.tenant_id = py.tenant_id
                       AND p.patient_id = py.patient_id
                    LEFT JOIN tbl_users u_linked
                        ON u_linked.user_id = p.linked_user_id
                    LEFT JOIN tbl_users u_owner
                        ON u_owner.user_id = p.owner_user_id
                    WHERE py.tenant_id = ?
                      AND py.payment_id = ?
                    LIMIT 1
                ";
                $receiptStmt = $pdo->prepare($receiptSql);
                $receiptStmt->execute([$tenantId, $receiptPaymentId]);
                $receiptRow = $receiptStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $patientEmail = trim((string) ($receiptRow['patient_email'] ?? ''));
                if ($patientEmail !== '' && !filter_var($patientEmail, FILTER_VALIDATE_EMAIL)) {
                    $patientEmail = '';
                }
                if ($receiptRow === []) {
                    $paymentError = 'Payment record was not found.';
                } elseif ($patientEmail === '') {
                    $paymentError = 'Patient has no registered email address.';
                } else {
                    $patientFullName = trim(trim((string) ($receiptRow['patient_first_name'] ?? '')) . ' ' . trim((string) ($receiptRow['patient_last_name'] ?? '')));
                    if ($patientFullName === '') {
                        $patientFullName = 'Patient';
                    }
                    $servicesLabel = '';
                    $serviceItems = [];
                    $servicesTotalValue = 0.0;
                    $receiptBookingId = trim((string) ($receiptRow['booking_id'] ?? ''));
                    if ($receiptBookingId !== '') {
                        $receiptServicesStmt = $pdo->prepare("
                            SELECT service_name, price
                            FROM tbl_appointment_services
                            WHERE tenant_id = ?
                              AND booking_id = ?
                            ORDER BY id ASC
                        ");
                        $receiptServicesStmt->execute([$tenantId, $receiptBookingId]);
                        $receiptServicesRows = $receiptServicesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        foreach ($receiptServicesRows as $serviceRow) {
                            $serviceName = trim((string) ($serviceRow['service_name'] ?? ''));
                            $serviceName = preg_replace('/\[[^\]]*\]/', '', $serviceName);
                            $serviceName = trim((string) preg_replace('/\s+/', ' ', (string) $serviceName));
                            if ($serviceName === '') {
                                continue;
                            }
                            $serviceAmount = (float) ($serviceRow['price'] ?? 0);
                            $servicesTotalValue += $serviceAmount;
                            $serviceItems[] = [
                                'name' => $serviceName,
                                'amount' => '₱' . number_format($serviceAmount, 2),
                            ];
                        }
                    }
                    if ($serviceItems !== []) {
                        $serviceSummaryParts = [];
                        foreach ($serviceItems as $item) {
                            $serviceSummaryParts[] = $item['name'] . ' (' . $item['amount'] . ')';
                        }
                        $servicesLabel = implode('; ', $serviceSummaryParts);
                    }
                    if ($servicesLabel === '') {
                        $servicesLabel = trim((string) ($receiptRow['service_description'] ?? ''));
                        if ($servicesLabel === '') {
                            $servicesLabel = trim((string) ($receiptRow['service_type'] ?? ''));
                        }
                        $servicesLabel = preg_replace('/\[[^\]]*\]/', '', $servicesLabel);
                        $servicesLabel = trim((string) preg_replace('/\s+/', ' ', (string) $servicesLabel));
                    }
                    if ($servicesLabel === '') {
                        $servicesLabel = 'Dental treatment';
                    }
                    $servicesTotalLabel = '₱' . number_format($servicesTotalValue, 2);
                    if ($serviceItems === []) {
                        $servicesTotalLabel = '₱' . number_format((float) ($receiptRow['total_treatment_cost'] ?? 0), 2);
                    }
                    $amountPaid = (float) ($receiptRow['amount'] ?? 0);
                    $balanceLeft = max(0, (float) ($receiptRow['total_treatment_cost'] ?? 0) - (float) ($receiptRow['booking_total_paid'] ?? 0));
                    $paymentDateValue = trim((string) ($receiptRow['payment_date'] ?? ''));
                    $paymentDateObj = staff_payment_recording_to_manila_datetime($paymentDateValue);
                    $paymentDateLabel = $paymentDateObj instanceof DateTimeImmutable
                        ? $paymentDateObj->format('F d, Y h:i A')
                        : '-';
                    $referenceLabel = trim((string) ($receiptRow['reference_number'] ?? ''));
                    if ($referenceLabel === '') {
                        $referenceLabel = trim((string) ($receiptRow['payment_id'] ?? ''));
                    }
                    $emailSubject = 'Payment Receipt - ' . $clinicDisplayName;
                    $amountPaidLabel = '₱' . number_format($amountPaid, 2);
                    $balanceLeftLabel = '₱' . number_format($balanceLeft, 2);
                    $emailBodyText = "Clinic: {$clinicDisplayName}\n"
                        . "Patient: {$patientFullName}\n"
                        . "Payment ID: " . (string) ($receiptRow['payment_id'] ?? '') . "\n"
                        . "Reference: {$referenceLabel}\n"
                        . "Services: {$servicesLabel}\n"
                        . "Service Total: {$servicesTotalLabel}\n"
                        . "Amount Paid: {$amountPaidLabel}\n"
                        . "Remaining Balance: {$balanceLeftLabel}\n"
                        . "Payment Date: {$paymentDateLabel}\n";
                    $emailBodyHtml = staff_payment_recording_build_receipt_email_html([
                        'clinic_name' => $clinicDisplayName,
                        'clinic_logo' => $clinicLogoUrl,
                        'patient_name' => $patientFullName,
                        'patient_id' => trim((string) ($receiptRow['patient_id'] ?? 'N/A')),
                        'reference' => $referenceLabel,
                        'payment_id' => (string) ($receiptRow['payment_id'] ?? ''),
                        'service' => $servicesLabel,
                        'service_items' => $serviceItems,
                        'services_total' => $servicesTotalLabel,
                        'payment_date' => $paymentDateLabel,
                        'payment_method' => $allowedMethods[strtolower(trim((string) ($receiptRow['payment_method'] ?? '')))] ?? ucfirst(str_replace('_', ' ', trim((string) ($receiptRow['payment_method'] ?? '')))),
                        'amount_paid' => $amountPaidLabel,
                        'remaining_balance' => $balanceLeftLabel,
                    ]);
                    if (staff_payment_recording_send_receipt_email($patientEmail, $emailSubject, $emailBodyText, $emailBodyHtml)) {
                        $_SESSION['staff_last_receipt_email'] = [
                            'payment_id' => $receiptPaymentId,
                            'sent_at' => time(),
                        ];
                        $_SESSION['staff_receipt_email_success'] = '1';
                        $redirectUrl = $_SERVER['REQUEST_URI'] ?? 'StaffPaymentRecording.php';
                        if (!is_string($redirectUrl) || trim($redirectUrl) === '') {
                            $redirectUrl = 'StaffPaymentRecording.php';
                        }
                        header('Location: ' . $redirectUrl);
                        exit;
                    } else {
                        $smtpReason = '';
                        if (isset($GLOBALS['smtp_last_error'])) {
                            $smtpReason = trim((string) $GLOBALS['smtp_last_error']);
                        }
                        $paymentError = 'Failed to send the receipt email. Please try again.'
                            . ($smtpReason !== '' ? (' SMTP error: ' . $smtpReason) : '');
                    }
                }
            }
            }
        } else {
        $patientQuery = trim((string) ($_POST['patient_query'] ?? ''));
        $selectedBookingId = trim((string) ($_POST['selected_booking_id'] ?? ''));
        $selectedTransactionType = strtolower(trim((string) ($_POST['selected_transaction_type'] ?? 'regular')));
        $selectedTreatmentId = trim((string) ($_POST['selected_treatment_id'] ?? ''));
        if (!in_array($selectedTransactionType, ['regular', 'installment'], true)) {
            $selectedTransactionType = 'regular';
        }
        $amount = (float) ($_POST['amount'] ?? 0);
        $paymentDate = trim((string) ($_POST['payment_date'] ?? date('Y-m-d')));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $method = strtolower(trim((string) ($_POST['payment_method'] ?? '')));
        $additionalServiceIds = [];
        if (isset($_POST['additional_service_ids']) && is_array($_POST['additional_service_ids'])) {
            foreach ($_POST['additional_service_ids'] as $serviceIdValue) {
                $serviceIdValue = trim((string) $serviceIdValue);
                if ($serviceIdValue !== '') {
                    $additionalServiceIds[] = $serviceIdValue;
                }
            }
            $additionalServiceIds = array_values(array_unique($additionalServiceIds));
        }

        if ($method === '' || !isset($allowedMethods[$method])) {
            $paymentError = 'Please select a payment method.';
        } elseif ($selectedBookingId === '') {
            $paymentError = 'Please select a pending appointment transaction first.';
        } elseif ($amount <= 0) {
            $paymentError = 'Please enter a valid payment amount.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $paymentError = 'Please provide a valid payment date.';
        } else {
            $bookingSql = "
                SELECT
                    a.booking_id,
                    COALESCE(MAX(a.id), 0) AS appointment_id,
                    a.patient_id,
                    COALESCE(a.treatment_id, '') AS treatment_id,
                    COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
                    COALESCE(a.service_description, '') AS service_description,
                    COALESCE(SUM(CASE WHEN py.status IN ('completed', 'paid') THEN py.amount ELSE 0 END), 0) AS total_paid
                FROM tbl_appointments a
                LEFT JOIN tbl_payments py
                    ON py.tenant_id = a.tenant_id
                   AND py.booking_id = a.booking_id
                WHERE a.tenant_id = ?
                  AND a.booking_id = ?
                GROUP BY a.booking_id, a.patient_id, a.treatment_id, a.total_treatment_cost, a.service_description
                LIMIT 1
            ";
            $bookingStmt = $pdo->prepare($bookingSql);
            $bookingStmt->execute([$tenantId, $selectedBookingId]);
            $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC);
            $patientId = trim((string) ($bookingRow['patient_id'] ?? ''));
            $bookingTreatmentId = trim((string) ($bookingRow['treatment_id'] ?? ''));
            $totalCost = (float) ($bookingRow['total_treatment_cost'] ?? 0);
            $totalPaid = (float) ($bookingRow['total_paid'] ?? 0);
            $pendingBalance = 0.0;
            if ($selectedTransactionType === 'installment' && $selectedTreatmentId !== '') {
                $treatmentStmt = $pdo->prepare("
                    SELECT
                        patient_id,
                        COALESCE(total_cost, 0) AS total_cost,
                        COALESCE(amount_paid, 0) AS amount_paid,
                        COALESCE(remaining_balance, 0) AS remaining_balance
                    FROM tbl_treatments
                    WHERE tenant_id = ?
                      AND treatment_id = ?
                    LIMIT 1
                ");
                $treatmentStmt->execute([$tenantId, $selectedTreatmentId]);
                $treatmentRow = $treatmentStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($treatmentRow !== null) {
                    $patientId = trim((string) ($treatmentRow['patient_id'] ?? $patientId));
                    $bookingTreatmentId = $selectedTreatmentId;
                    $totalCost = max(0, (float) ($treatmentRow['total_cost'] ?? 0));
                    $totalPaid = max(0, (float) ($treatmentRow['amount_paid'] ?? 0));
                    $pendingBalance = max(0, (float) ($treatmentRow['remaining_balance'] ?? 0));
                }
            } else {
                $serviceTypeTotals = [
                    'regular' => 0.0,
                    'installment' => 0.0,
                ];
                if ($supportsAppointmentServicesTable) {
                    $serviceTotalsStmt = $pdo->prepare("
                        SELECT
                            COALESCE(NULLIF(TRIM(aps.service_type), ''), 'installment') AS normalized_service_type,
                            COALESCE(SUM(COALESCE(aps.price, 0)), 0) AS total_cost
                        FROM tbl_appointment_services aps
                        WHERE aps.tenant_id = ?
                          AND aps.booking_id = ?
                        GROUP BY COALESCE(NULLIF(TRIM(aps.service_type), ''), 'installment')
                    ");
                    $serviceTotalsStmt->execute([$tenantId, $selectedBookingId]);
                    foreach ($serviceTotalsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $serviceTotalRow) {
                        $normalizedType = strtolower(trim((string) ($serviceTotalRow['normalized_service_type'] ?? '')));
                        if ($normalizedType !== 'regular' && $normalizedType !== 'installment') {
                            $normalizedType = 'installment';
                        }
                        $serviceTypeTotals[$normalizedType] += (float) ($serviceTotalRow['total_cost'] ?? 0);
                    }
                }
                $hasInstallmentEntry = $serviceTypeTotals['installment'] > 0.009 || $bookingTreatmentId !== '';
                $hasRegularEntry = $serviceTypeTotals['regular'] > 0.009 || !$hasInstallmentEntry;
                $regularCost = $serviceTypeTotals['regular'] > 0.009
                    ? (float) $serviceTypeTotals['regular']
                    : ($hasInstallmentEntry ? 0.0 : $totalCost);
                $installmentCost = $serviceTypeTotals['installment'] > 0.009
                    ? (float) $serviceTypeTotals['installment']
                    : ($hasInstallmentEntry ? $totalCost : 0.0);
                if ($selectedTransactionType === 'regular' && !$hasRegularEntry) {
                    $regularCost = 0.0;
                }
                if ($selectedTransactionType === 'installment' && !$hasInstallmentEntry) {
                    $installmentCost = 0.0;
                }
                $explicitRegularPaid = 0.0;
                $explicitInstallmentPaid = 0.0;
                if ($supportsPaymentsInstallmentNumberColumn) {
                    $splitPaidStmt = $pdo->prepare("
                        SELECT
                            COALESCE(SUM(CASE WHEN status IN ('completed', 'paid') AND COALESCE(installment_number, 0) <= 0 THEN amount ELSE 0 END), 0) AS regular_paid_amount,
                            COALESCE(SUM(CASE WHEN status IN ('completed', 'paid') AND COALESCE(installment_number, 0) > 0 THEN amount ELSE 0 END), 0) AS installment_paid_amount
                        FROM tbl_payments
                        WHERE tenant_id = ?
                          AND booking_id = ?
                    ");
                    $splitPaidStmt->execute([$tenantId, $selectedBookingId]);
                    $splitPaidRow = $splitPaidStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $explicitRegularPaid = (float) ($splitPaidRow['regular_paid_amount'] ?? 0);
                    $explicitInstallmentPaid = (float) ($splitPaidRow['installment_paid_amount'] ?? 0);
                }
                $installmentPaidBySchedule = 0.0;
                $scheduleRowsForSplit = staff_payment_recording_fetch_installments($pdo, $installmentsTableName, $tenantId, $selectedBookingId);
                if ($scheduleRowsForSplit !== []) {
                    foreach ($scheduleRowsForSplit as $instRow) {
                        if (staff_payment_recording_installment_is_paid((string) ($instRow['status'] ?? ''))) {
                            $installmentPaidBySchedule += (float) ($instRow['amount_due'] ?? 0);
                        }
                    }
                }
                $installmentPaidResolved = $explicitInstallmentPaid > 0
                    ? $explicitInstallmentPaid
                    : ($installmentPaidBySchedule > 0
                        ? $installmentPaidBySchedule
                        : ($hasInstallmentEntry ? $totalPaid : 0.0));
                $installmentPaidResolved = max(0.0, min($installmentCost, $installmentPaidResolved));
                $regularPaidRaw = $explicitRegularPaid > 0
                    ? $explicitRegularPaid
                    : ($totalPaid - $installmentPaidResolved);
                // Protect regular-service pending balance from tiny split/rounding drift.
                // Example: 0.01-0.05 residual from installment math should not mark regular rows partially paid.
                if ($regularPaidRaw > 0 && $regularPaidRaw < 0.05) {
                    $regularPaidRaw = 0.0;
                }
                $regularPaid = max(0, min($regularCost, $regularPaidRaw));
                $installmentPaid = max(0, min($installmentCost, $installmentPaidResolved));
                if ($selectedTransactionType === 'installment') {
                    $totalCost = $installmentCost;
                    $totalPaid = $installmentPaid;
                } else {
                    $totalCost = $regularCost;
                    $totalPaid = $regularPaid;
                }
                $pendingBalance = max(0, $totalCost - $totalPaid);
            }

            if ($patientId === '') {
                $paymentError = 'Selected transaction was not found.';
            } elseif ($pendingBalance <= 0) {
                $paymentError = 'Selected transaction is already fully paid.';
            } else {
                try {
                    staff_payment_recording_ensure_installment_schedule(
                        $pdo,
                        $installmentsTableName,
                        $tenantId,
                        $selectedBookingId,
                        $supportsAppointmentServicesTable,
                        $supportsServiceEnableInstallmentColumn
                    );
                    $scheduleRows = staff_payment_recording_fetch_installments($pdo, $installmentsTableName, $tenantId, $selectedBookingId);
                    $postedInstallFlow = trim((string) ($_POST['installment_flow'] ?? 'regular'));
                    $postedPayMode = trim((string) ($_POST['installment_pay_mode'] ?? 'full'));
                    $postedSlotCount = max(1, (int) ($_POST['installment_slot_count'] ?? 1));
                    $runSchedulePayment = (
                        $selectedTransactionType === 'installment'
                        && $postedInstallFlow === 'schedule'
                        && $scheduleRows !== []
                    );

                    if (!empty($additionalServiceIds)) {
                        if (!$supportsAppointmentServicesTable) {
                            throw new RuntimeException('Additional services are not available in this deployment yet.');
                        }

                        $existingServicesStmt = $pdo->prepare("
                            SELECT service_id
                            FROM tbl_appointment_services
                            WHERE tenant_id = ?
                              AND booking_id = ?
                        ");
                        $existingServicesStmt->execute([$tenantId, $selectedBookingId]);
                        $existingLookup = [];
                        foreach (($existingServicesStmt->fetchAll(PDO::FETCH_COLUMN) ?: []) as $existingServiceId) {
                            $existingLookup[(string) $existingServiceId] = true;
                        }

                        $duplicateChosen = [];
                        foreach ($additionalServiceIds as $chosenId) {
                            $chosenId = (string) $chosenId;
                            if ($chosenId !== '' && isset($existingLookup[$chosenId])) {
                                $duplicateChosen[] = $chosenId;
                            }
                        }
                        if ($duplicateChosen !== []) {
                            throw new RuntimeException('One or more selected services are already on this appointment. Remove duplicates before posting: ' . implode(', ', array_unique($duplicateChosen)) . '.');
                        }

                        $placeholders = implode(',', array_fill(0, count($additionalServiceIds), '?'));
                        $hasActiveInstallmentTreatment = (
                            $selectedTransactionType === 'installment'
                            || $bookingTreatmentId !== ''
                            || ($serviceTypeTotals['installment'] ?? 0) > 0.009
                        );
                        $selectedServiceTypeSql = "'regular' AS normalized_service_type";
                        if ($supportsServiceEnableInstallmentColumn) {
                            $selectedServiceTypeSql = "CASE WHEN COALESCE(enable_installment, 0) = 1 THEN 'installment' ELSE 'regular' END AS normalized_service_type";
                        }
                        $servicesSql = "
                            SELECT service_id, service_name, category, price, {$selectedServiceTypeSql}
                            FROM tbl_services
                            WHERE tenant_id = ?
                              AND status = 'active'
                              AND service_id IN ($placeholders)
                        ";
                        $servicesStmt = $pdo->prepare($servicesSql);
                        $servicesStmt->execute(array_merge([$tenantId], $additionalServiceIds));
                        $servicesToAdd = $servicesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        if (empty($servicesToAdd)) {
                            throw new RuntimeException('No valid additional services were selected.');
                        }
                        if ($hasActiveInstallmentTreatment) {
                            $blockedInstallmentSelections = [];
                            foreach ($servicesToAdd as $serviceToValidate) {
                                $svcType = strtolower(trim((string) ($serviceToValidate['normalized_service_type'] ?? 'regular')));
                                if ($svcType !== 'regular') {
                                    $blockedInstallmentSelections[] = trim((string) ($serviceToValidate['service_name'] ?? 'Installment service'));
                                }
                            }
                            if ($blockedInstallmentSelections !== []) {
                                throw new RuntimeException(
                                    'This patient has an active installment treatment. Only Regular Services can be added in Additional Services. Remove these installment services: '
                                    . implode(', ', array_unique($blockedInstallmentSelections)) . '.'
                                );
                            }
                        }
                        $appointmentCategoriesStmt = $pdo->prepare("
                            SELECT COALESCE(NULLIF(sv.category, ''), '') AS category
                            FROM tbl_appointment_services aps
                            LEFT JOIN tbl_services sv
                              ON sv.tenant_id = aps.tenant_id
                             AND sv.service_id = aps.service_id
                            WHERE aps.tenant_id = ?
                              AND aps.booking_id = ?
                        ");
                        $appointmentCategoriesStmt->execute([$tenantId, $selectedBookingId]);
                        $combinedCategories = $appointmentCategoriesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
                        foreach ($servicesToAdd as $serviceToAdd) {
                            $combinedCategories[] = (string) ($serviceToAdd['category'] ?? '');
                        }
                        $blockedCombinationMessage = staff_payment_recording_disallowed_combination_message($combinedCategories);
                        if ($blockedCombinationMessage !== '') {
                            throw new RuntimeException($blockedCombinationMessage);
                        }

                        $appointmentRowId = (int) ($bookingRow['appointment_id'] ?? 0);
                        $insertColumns = ['tenant_id', 'booking_id', 'service_id', 'service_name', 'price'];
                        $insertValues = ['?', '?', '?', '?', '?'];
                        if (in_array('appointment_id', $appointmentServiceColumns, true)) {
                            $insertColumns[] = 'appointment_id';
                            $insertValues[] = '?';
                        }
                        if (in_array('treatment_id', $appointmentServiceColumns, true)) {
                            $insertColumns[] = 'treatment_id';
                            $insertValues[] = '?';
                        }
                        if ($supportsAppointmentServiceTypeColumn) {
                            $insertColumns[] = 'service_type';
                            $insertValues[] = '?';
                        }
                        if (in_array('is_original', $appointmentServiceColumns, true)) {
                            $insertColumns[] = 'is_original';
                            $insertValues[] = '0';
                        }
                        if (in_array('added_at', $appointmentServiceColumns, true)) {
                            $insertColumns[] = 'added_at';
                            $insertValues[] = 'NOW()';
                        }
                        $insertServiceSql = "
                            INSERT INTO tbl_appointment_services (" . implode(', ', $insertColumns) . ")
                            VALUES (" . implode(', ', $insertValues) . ")
                        ";
                        $insertServiceStmt = $pdo->prepare($insertServiceSql);

                        $addedServiceLabels = [];
                        $addedCost = 0.0;
                        foreach ($servicesToAdd as $service) {
                            $serviceId = trim((string) ($service['service_id'] ?? ''));
                            if ($serviceId === '') {
                                continue;
                            }
                            $serviceName = trim((string) ($service['service_name'] ?? 'Additional Service'));
                            $servicePrice = (float) ($service['price'] ?? 0);
                            $insertParams = [
                                $tenantId,
                                $selectedBookingId,
                                $serviceId,
                                $serviceName,
                                $servicePrice,
                            ];
                            if (in_array('appointment_id', $appointmentServiceColumns, true)) {
                                $insertParams[] = $appointmentRowId > 0 ? $appointmentRowId : null;
                            }
                            if (in_array('treatment_id', $appointmentServiceColumns, true)) {
                                $insertParams[] = null;
                            }
                            if ($supportsAppointmentServiceTypeColumn) {
                                $insertParams[] = 'regular';
                            }
                            $insertServiceStmt->execute($insertParams);
                            $existingLookup[$serviceId] = true;
                            $addedCost += $servicePrice;
                            $addedServiceLabels[] = $serviceName . ' (₱' . number_format($servicePrice, 2) . ')';
                        }

                        if ($addedCost > 0) {
                            $totalCost += $addedCost;
                            $pendingBalance += $addedCost;
                            $serviceDescription = trim((string) ($bookingRow['service_description'] ?? ''));
                            $addedServiceNote = implode('; ', $addedServiceLabels);
                            $newServiceDescription = $serviceDescription !== '' ? ($serviceDescription . '; ' . $addedServiceNote) : $addedServiceNote;
                            $updateAppointmentCostStmt = $pdo->prepare("
                                UPDATE tbl_appointments
                                SET total_treatment_cost = ?,
                                    service_description = ?
                                WHERE tenant_id = ?
                                  AND booking_id = ?
                                LIMIT 1
                            ");
                            $updateAppointmentCostStmt->execute([$totalCost, $newServiceDescription, $tenantId, $selectedBookingId]);
                        }
                    }

                    if ($runSchedulePayment) {
                        require_once __DIR__ . '/includes/staff_installment_helpers.php';

                        $inst1Row = null;
                        foreach ($scheduleRows as $sr) {
                            if ((int) ($sr['installment_number'] ?? 0) === 1) {
                                $inst1Row = $sr;
                                break;
                            }
                        }
                        $downpaymentCoveredByAmount = false;
                        if (is_array($inst1Row)) {
                            $inst1AmountDue = round((float) ($inst1Row['amount_due'] ?? 0), 2);
                            $downpaymentCoveredByAmount = $inst1AmountDue > 0
                                && ((float) $totalPaid + 0.009 >= $inst1AmountDue);
                        }

                        $unpaid = [];
                        foreach ($scheduleRows as $sr) {
                            $isPaid = staff_payment_recording_installment_is_paid((string) ($sr['status'] ?? ''));
                            $isDownpaymentSlot = ((int) ($sr['installment_number'] ?? 0) === 1);
                            if (!$isPaid && $isDownpaymentSlot && $downpaymentCoveredByAmount) {
                                $isPaid = true;
                            }
                            if (!$isPaid) {
                                $unpaid[] = $sr;
                            }
                        }
                        if ($unpaid === []) {
                            throw new RuntimeException('All installments for this booking are already paid.');
                        }
                        $firstUnpaid = $unpaid[0];
                        $fn = (int) $firstUnpaid['installment_number'];
                        $mode = $postedPayMode;
                        $allowedModes = ['full', 'down', 'monthly', 'combined'];
                        if (!in_array($mode, $allowedModes, true)) {
                            $mode = 'full';
                        }

                        $slotCount = 0;
                        if ($mode === 'full') {
                            $slotCount = count($unpaid);
                        } elseif ($mode === 'down') {
                            if ($fn !== 1) {
                                throw new RuntimeException('Down payment is already completed. Choose Monthly payment or Pay in full.');
                            }
                            $slotCount = 1;
                        } elseif ($mode === 'monthly') {
                            if ($fn < 2) {
                                throw new RuntimeException('Pay the down payment before monthly installments.');
                            }
                            $slotCount = min(count($unpaid), max(1, $postedSlotCount));
                        } else {
                            if ($fn !== 1) {
                                throw new RuntimeException('Combined down + monthly is only available when installment 1 (down payment) is still due.');
                            }
                            $slotCount = min(count($unpaid), max(2, $postedSlotCount));
                        }

                        $toPay = array_slice($unpaid, 0, $slotCount);
                        if (count($toPay) !== $slotCount) {
                            throw new RuntimeException('Not enough pending installments for this payment selection.');
                        }

                        $expected = 0.0;
                        foreach ($toPay as $tp) {
                            $expected += (float) $tp['amount_due'];
                        }
                        $expected = round($expected, 2);
                        if (abs($amount - $expected) > 0.05) {
                            throw new RuntimeException('Payment amount must be ₱' . number_format($expected, 2) . ' for the selected installment option.');
                        }

                        $instLabels = [];
                        foreach ($toPay as $tp) {
                            $instLabels[] = '#' . (int) $tp['installment_number'];
                        }
                        $noteExtra = '[Installments: ' . implode(', ', $instLabels) . ']';
                        $composedNotes = trim(($notes !== '' ? ($notes . ' ') : '') . $noteExtra);

                        $usePayMongo = in_array($method, ['gcash', 'bank_transfer', 'credit_card'], true);
                        // For non-online payments, use 'completed' which matches the schema for tbl_payments.status.
                        $recordStatus = $usePayMongo ? 'pending' : 'completed';

                        if ($mode === 'full') {
                            $paymentType = 'fullpayment';
                        } elseif ($mode === 'down') {
                            $paymentType = 'downpayment';
                        } else {
                            $paymentType = 'balancepayment';
                        }

                        $paymentId = 'PAY-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                        $firstInstNum = (int) ($toPay[0]['installment_number'] ?? 0);

                        if ($supportsPaymentsInstallmentNumberColumn && $supportsPaymentTypeColumn) {
                            $insertSql = "
                                INSERT INTO tbl_payments (
                                    tenant_id,
                                    payment_id,
                                    patient_id,
                                    booking_id,
                                    installment_number,
                                    amount,
                                    payment_method,
                                    payment_date,
                                    notes,
                                    status,
                                    created_by,
                                    payment_type
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ";
                            $insertParams = [
                                $tenantId,
                                $paymentId,
                                $patientId,
                                $selectedBookingId,
                                $firstInstNum > 0 ? $firstInstNum : null,
                                $amount,
                                $method,
                                $paymentDate . ' ' . date('H:i:s'),
                                $composedNotes !== '' ? $composedNotes : null,
                                $recordStatus,
                                $userId !== '' ? $userId : null,
                                $paymentType,
                            ];
                        } elseif ($supportsPaymentsInstallmentNumberColumn) {
                            $insertSql = "
                                INSERT INTO tbl_payments (
                                    tenant_id,
                                    payment_id,
                                    patient_id,
                                    booking_id,
                                    installment_number,
                                    amount,
                                    payment_method,
                                    payment_date,
                                    notes,
                                    status,
                                    created_by
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ";
                            $insertParams = [
                                $tenantId,
                                $paymentId,
                                $patientId,
                                $selectedBookingId,
                                $firstInstNum > 0 ? $firstInstNum : null,
                                $amount,
                                $method,
                                $paymentDate . ' ' . date('H:i:s'),
                                $composedNotes !== '' ? $composedNotes : null,
                                $recordStatus,
                                $userId !== '' ? $userId : null,
                            ];
                        } elseif ($supportsPaymentTypeColumn) {
                            $insertSql = "
                                INSERT INTO tbl_payments (
                                    tenant_id,
                                    payment_id,
                                    patient_id,
                                    booking_id,
                                    amount,
                                    payment_method,
                                    payment_date,
                                    notes,
                                    status,
                                    created_by,
                                    payment_type
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ";
                            $insertParams = [
                                $tenantId,
                                $paymentId,
                                $patientId,
                                $selectedBookingId,
                                $amount,
                                $method,
                                $paymentDate . ' ' . date('H:i:s'),
                                $composedNotes !== '' ? $composedNotes : null,
                                $recordStatus,
                                $userId !== '' ? $userId : null,
                                $mode === 'full' ? 'fullpayment' : 'downpayment',
                            ];
                        } else {
                            $insertSql = "
                                INSERT INTO tbl_payments (
                                    tenant_id,
                                    payment_id,
                                    patient_id,
                                    booking_id,
                                    amount,
                                    payment_method,
                                    payment_date,
                                    notes,
                                    status,
                                    created_by
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ";
                            $insertParams = [
                                $tenantId,
                                $paymentId,
                                $patientId,
                                $selectedBookingId,
                                $amount,
                                $method,
                                $paymentDate . ' ' . date('H:i:s'),
                                $composedNotes !== '' ? $composedNotes : null,
                                $recordStatus,
                                $userId !== '' ? $userId : null,
                            ];
                        }

                        $insertStmt = $pdo->prepare($insertSql);
                        $insertStmt->execute($insertParams);

                        $finalizeItems = [];
                        foreach ($toPay as $tp) {
                            $finalizeItems[] = [
                                'id' => (int) $tp['id'],
                                'installment_number' => (int) $tp['installment_number'],
                            ];
                        }
                        $monthsPaidIncrement = 0;
                        foreach ($toPay as $tp) {
                            $installmentNumber = (int) ($tp['installment_number'] ?? 0);
                            if ($installmentNumber > 1) {
                                $monthsPaidIncrement++;
                            }
                        }

                        // Apply to treatment progress only for installment transactions.
                        if (
                            strtolower((string) $recordStatus) === 'completed'
                            && $selectedTransactionType === 'installment'
                        ) {
                            staff_payment_recording_apply_payment_to_treatment(
                                $pdo,
                                $tenantId,
                                $bookingTreatmentId,
                                (float) $amount,
                                $monthsPaidIncrement
                            );
                        }

                        if ($usePayMongo) {
                            require_once dirname(__DIR__) . '/paymongo_config.php';
                            $secret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
                            if ($secret === '' || strpos($secret, 'YOUR_') !== false) {
                                $del = $pdo->prepare('DELETE FROM tbl_payments WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                                $del->execute([$tenantId, $paymentId, 'pending']);
                                throw new RuntimeException('PayMongo API key is not configured.');
                            }

                            $paymongoTypeMap = [
                                'gcash' => 'gcash',
                                'bank_transfer' => 'paymaya',
                                'credit_card' => 'card',
                            ];
                            $pmType = $paymongoTypeMap[$method] ?? 'gcash';
                            $amountCentavos = (int) round($amount * 100);
                            if ($amountCentavos < 100) {
                                $del = $pdo->prepare('DELETE FROM tbl_payments WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                                $del->execute([$tenantId, $paymentId, 'pending']);
                                throw new RuntimeException('Amount is too small for online checkout (minimum ₱1.00).');
                            }

                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }
                            $returnToken = bin2hex(random_bytes(24));
                            $_SESSION['staff_paymongo_checkout'] = [
                                'token' => $returnToken,
                                'payment_id' => $paymentId,
                                'tenant_id' => $tenantId,
                                'payment_date' => $paymentDate,
                                'booking_id' => $selectedBookingId,
                                'selected_transaction_type' => $selectedTransactionType,
                                'installment_finalize' => [
                                    'installments_table' => $installmentsTableName,
                                    'paid_items' => $finalizeItems,
                                    'months_paid_increment' => $monthsPaidIncrement,
                                ],
                            ];

                            $patStmt = $pdo->prepare("SELECT first_name, last_name FROM tbl_patients WHERE tenant_id = ? AND patient_id = ? LIMIT 1");
                            $patStmt->execute([$tenantId, $patientId]);
                            $patRow = $patStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                            $billingName = trim(trim((string) ($patRow['first_name'] ?? '')) . ' ' . trim((string) ($patRow['last_name'] ?? '')));
                            if ($billingName === '') {
                                $billingName = 'Patient';
                            }
                            $billingEmail = 'patient+' . preg_replace('/[^a-z0-9]/i', '', $tenantId . $patientId) . '@billing.local';

                            $successUrl = BASE_URL . 'StaffPaymentPayMongoReturn.php?pid=' . rawurlencode($paymentId) . '&token=' . rawurlencode($returnToken);
                            $cancelUrl = BASE_URL . 'StaffPaymentRecording.php?paymongo_cancel=1&pid=' . rawurlencode($paymentId) . '&token=' . rawurlencode($returnToken);
                            if ($currentTenantSlug !== '') {
                                $successUrl .= '&clinic_slug=' . rawurlencode($currentTenantSlug);
                                $cancelUrl .= '&clinic_slug=' . rawurlencode($currentTenantSlug);
                            }

                            $payload = [
                                'data' => [
                                    'attributes' => [
                                        'billing' => [
                                            'name' => $billingName,
                                            'email' => $billingEmail,
                                        ],
                                        'send_email_receipt' => false,
                                        'show_description' => true,
                                        'show_line_items' => true,
                                        'description' => 'Installment payment (booking ' . $selectedBookingId . ')',
                                        'line_items' => [[
                                            'currency' => 'PHP',
                                            'amount' => $amountCentavos,
                                            'name' => 'Dental installment payment',
                                            'quantity' => 1,
                                        ]],
                                        'payment_method_types' => [$pmType],
                                        'success_url' => $successUrl,
                                        'cancel_url' => $cancelUrl,
                                        'reference_number' => $paymentId,
                                        'metadata' => [
                                            'source' => 'staff_payment_recording',
                                            'tenant_id' => (string) $tenantId,
                                            'booking_id' => (string) $selectedBookingId,
                                            'payment_id' => (string) $paymentId,
                                        ],
                                    ],
                                ],
                            ];

                            $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_POST, true);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
                            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                                'Content-Type: application/json',
                                'Accept: application/json',
                                'Authorization: Basic ' . base64_encode($secret . ':'),
                            ]);
                            $response = curl_exec($ch);
                            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            $curlError = curl_error($ch);
                            curl_close($ch);

                            if ($curlError !== '') {
                                $del = $pdo->prepare('DELETE FROM tbl_payments WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                                $del->execute([$tenantId, $paymentId, 'pending']);
                                throw new RuntimeException('Could not reach PayMongo: ' . $curlError);
                            }

                            $responseData = json_decode((string) $response, true);
                            $checkoutUrl = is_array($responseData) ? ($responseData['data']['attributes']['checkout_url'] ?? null) : null;
                            $sessionId = is_array($responseData) ? ($responseData['data']['id'] ?? null) : null;

                            if ($httpCode >= 200 && $httpCode < 300 && $checkoutUrl && $sessionId) {
                                $refStmt = $pdo->prepare('UPDATE tbl_payments SET reference_number = ? WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                                $refStmt->execute([(string) $sessionId, $tenantId, $paymentId, 'pending']);
                                header('Location: ' . $checkoutUrl);
                                exit;
                            }

                            $del = $pdo->prepare('DELETE FROM tbl_payments WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                            $del->execute([$tenantId, $paymentId, 'pending']);
                            $apiError = '';
                            if (is_array($responseData) && isset($responseData['errors'][0])) {
                                $apiError = (string) ($responseData['errors'][0]['detail'] ?? $responseData['errors'][0]['title'] ?? '');
                            }
                            throw new RuntimeException($apiError !== '' ? ('PayMongo: ' . $apiError) : 'PayMongo did not return a checkout URL.');
                        }

                        staff_installments_apply_paid_with_unlocks(
                            $pdo,
                            $tenantId,
                            $selectedBookingId,
                            $paymentId,
                            (string) $installmentsTableName,
                            $finalizeItems
                        );

                        $bookingStmt->execute([$tenantId, $selectedBookingId]);
                        $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC);
                        $totalCostAfter = (float) ($bookingRow['total_treatment_cost'] ?? 0);
                        $totalPaidAfter = (float) ($bookingRow['total_paid'] ?? 0);
                        $pendingAfter = max(0, $totalCostAfter - $totalPaidAfter);

                        $paymentSuccess = 'Payment recorded successfully.';
                        $selectedMethod = '';
                        $selectedMethodForUi = '';
                        $formSelectedBookingId = '';
                        $formSelectedTransactionType = 'regular';
                        $formSelectedTreatmentId = '';
                        $formPatientQuery = '';
                        $formAmount = '';
                        $formPaymentDate = date('Y-m-d');
                        $formNotes = '';
                        $formServiceIds = [];
                        $formInstallmentFlow = 'regular';
                        $formInstallmentPayMode = 'full';
                        $formInstallmentSlotCount = 1;
                    } else {

                    if ($amount > $pendingBalance) {
                        throw new RuntimeException('Payment amount exceeds the pending balance of ₱' . number_format($pendingBalance, 2) . '.');
                    }

                    $usePayMongo = in_array($method, ['gcash', 'bank_transfer', 'credit_card'], true);
                    // For non-online payments, use 'completed' which matches the schema for tbl_payments.status.
                    $recordStatus = $usePayMongo ? 'pending' : 'completed';

                    $paymentId = 'PAY-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                    // Keep compatibility with deployments where payment_type enum only allows downpayment/fullpayment.
                    $paymentType = ($amount + 0.009 >= $pendingBalance) ? 'fullpayment' : 'downpayment';

                    if ($supportsPaymentTypeColumn) {
                        $insertSql = "
                            INSERT INTO tbl_payments (
                                tenant_id,
                                payment_id,
                                patient_id,
                                booking_id,
                                amount,
                                payment_method,
                                payment_date,
                                notes,
                                status,
                                created_by,
                                payment_type
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ";
                        $insertParams = [
                            $tenantId,
                            $paymentId,
                            $patientId,
                            $selectedBookingId,
                            $amount,
                            $method,
                            $paymentDate . ' ' . date('H:i:s'),
                            $notes !== '' ? $notes : null,
                            $recordStatus,
                            $userId !== '' ? $userId : null,
                            $paymentType,
                        ];
                    } else {
                        $insertSql = "
                            INSERT INTO tbl_payments (
                                tenant_id,
                                payment_id,
                                patient_id,
                                booking_id,
                                amount,
                                payment_method,
                                payment_date,
                                notes,
                                status,
                                created_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ";
                        $insertParams = [
                            $tenantId,
                            $paymentId,
                            $patientId,
                            $selectedBookingId,
                            $amount,
                            $method,
                            $paymentDate . ' ' . date('H:i:s'),
                            $notes !== '' ? $notes : null,
                            $recordStatus,
                            $userId !== '' ? $userId : null,
                        ];
                    }

                    $insertStmt = $pdo->prepare($insertSql);
                    $insertStmt->execute($insertParams);

                    // Apply to treatment progress only for installment transactions.
                    if (
                        strtolower((string) $recordStatus) === 'completed'
                        && $selectedTransactionType === 'installment'
                    ) {
                        staff_payment_recording_apply_payment_to_treatment(
                            $pdo,
                            $tenantId,
                            $bookingTreatmentId,
                            (float) $amount
                        );
                    }

                    if ($usePayMongo) {
                        require_once dirname(__DIR__) . '/paymongo_config.php';
                        $secret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
                        if ($secret === '' || strpos($secret, 'YOUR_') !== false) {
                            $del = $pdo->prepare('DELETE FROM tbl_payments WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                            $del->execute([$tenantId, $paymentId, 'pending']);
                            throw new RuntimeException('PayMongo API key is not configured.');
                        }

                        $paymongoTypeMap = [
                            'gcash' => 'gcash',
                            'bank_transfer' => 'paymaya',
                            'credit_card' => 'card',
                        ];
                        $pmType = $paymongoTypeMap[$method] ?? 'gcash';
                        $amountCentavos = (int) round($amount * 100);
                        if ($amountCentavos < 100) {
                            $del = $pdo->prepare('DELETE FROM tbl_payments WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                            $del->execute([$tenantId, $paymentId, 'pending']);
                            throw new RuntimeException('Amount is too small for online checkout (minimum ₱1.00).');
                        }

                        if (session_status() === PHP_SESSION_NONE) {
                            session_start();
                        }
                        $returnToken = bin2hex(random_bytes(24));
                        $_SESSION['staff_paymongo_checkout'] = [
                            'token' => $returnToken,
                            'payment_id' => $paymentId,
                            'tenant_id' => $tenantId,
                            'payment_date' => $paymentDate,
                            'booking_id' => $selectedBookingId,
                            'selected_transaction_type' => $selectedTransactionType,
                        ];

                        $patStmt = $pdo->prepare("SELECT first_name, last_name FROM tbl_patients WHERE tenant_id = ? AND patient_id = ? LIMIT 1");
                        $patStmt->execute([$tenantId, $patientId]);
                        $patRow = $patStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                        $billingName = trim(trim((string) ($patRow['first_name'] ?? '')) . ' ' . trim((string) ($patRow['last_name'] ?? '')));
                        if ($billingName === '') {
                            $billingName = 'Patient';
                        }
                        $billingEmail = 'patient+' . preg_replace('/[^a-z0-9]/i', '', $tenantId . $patientId) . '@billing.local';

                        $successUrl = BASE_URL . 'StaffPaymentPayMongoReturn.php?pid=' . rawurlencode($paymentId) . '&token=' . rawurlencode($returnToken);
                        $cancelUrl = BASE_URL . 'StaffPaymentRecording.php?paymongo_cancel=1&pid=' . rawurlencode($paymentId) . '&token=' . rawurlencode($returnToken);
                        if ($currentTenantSlug !== '') {
                            $successUrl .= '&clinic_slug=' . rawurlencode($currentTenantSlug);
                            $cancelUrl .= '&clinic_slug=' . rawurlencode($currentTenantSlug);
                        }

                        $payload = [
                            'data' => [
                                'attributes' => [
                                    'billing' => [
                                        'name' => $billingName,
                                        'email' => $billingEmail,
                                    ],
                                    'send_email_receipt' => false,
                                    'show_description' => true,
                                    'show_line_items' => true,
                                    'description' => 'Clinic payment (booking ' . $selectedBookingId . ')',
                                    'line_items' => [[
                                        'currency' => 'PHP',
                                        'amount' => $amountCentavos,
                                        'name' => 'Dental payment',
                                        'quantity' => 1,
                                    ]],
                                    'payment_method_types' => [$pmType],
                                    'success_url' => $successUrl,
                                    'cancel_url' => $cancelUrl,
                                    'reference_number' => $paymentId,
                                    'metadata' => [
                                        'source' => 'staff_payment_recording',
                                        'tenant_id' => (string) $tenantId,
                                        'booking_id' => (string) $selectedBookingId,
                                        'payment_id' => (string) $paymentId,
                                    ],
                                ],
                            ],
                        ];

                        $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Content-Type: application/json',
                            'Accept: application/json',
                            'Authorization: Basic ' . base64_encode($secret . ':'),
                        ]);
                        $response = curl_exec($ch);
                        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        $curlError = curl_error($ch);
                        curl_close($ch);

                        if ($curlError !== '') {
                            $del = $pdo->prepare('DELETE FROM tbl_payments WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                            $del->execute([$tenantId, $paymentId, 'pending']);
                            throw new RuntimeException('Could not reach PayMongo: ' . $curlError);
                        }

                        $responseData = json_decode((string) $response, true);
                        $checkoutUrl = is_array($responseData) ? ($responseData['data']['attributes']['checkout_url'] ?? null) : null;
                        $sessionId = is_array($responseData) ? ($responseData['data']['id'] ?? null) : null;

                        if ($httpCode >= 200 && $httpCode < 300 && $checkoutUrl && $sessionId) {
                            $refStmt = $pdo->prepare('UPDATE tbl_payments SET reference_number = ? WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                            $refStmt->execute([(string) $sessionId, $tenantId, $paymentId, 'pending']);
                            header('Location: ' . $checkoutUrl);
                            exit;
                        }

                        $del = $pdo->prepare('DELETE FROM tbl_payments WHERE tenant_id = ? AND payment_id = ? AND status = ?');
                        $del->execute([$tenantId, $paymentId, 'pending']);
                        $apiError = '';
                        if (is_array($responseData) && isset($responseData['errors'][0])) {
                            $apiError = (string) ($responseData['errors'][0]['detail'] ?? $responseData['errors'][0]['title'] ?? '');
                        }
                        throw new RuntimeException($apiError !== '' ? ('PayMongo: ' . $apiError) : 'PayMongo did not return a checkout URL.');
                    }

                    $paymentSuccess = 'Payment recorded successfully.';
                    // Reset the modal form after successful submission.
                    $selectedMethod = '';
                    $selectedMethodForUi = '';
                    $formSelectedBookingId = '';
                    $formSelectedTransactionType = 'regular';
                    $formSelectedTreatmentId = '';
                    $formPatientQuery = '';
                    $formAmount = '';
                    $formPaymentDate = date('Y-m-d');
                    $formNotes = '';
                    $formServiceIds = [];
                    $formInstallmentFlow = 'regular';
                    $formInstallmentPayMode = 'full';
                    $formInstallmentSlotCount = 1;
                    }
                } catch (Throwable $postError) {
                    error_log('Staff payment record submit error: ' . $postError->getMessage());
                    $postMessage = trim((string) $postError->getMessage());
                    $paymentError = $postMessage !== '' ? $postMessage : 'Unable to record payment right now. Please try again.';
                }
            }
        }
        }
    }

    if ($tenantId !== '') {
        $today = date('Y-m-d');

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM tbl_payments WHERE tenant_id = ? AND status IN ('completed', 'paid')");
        $stmt->execute([$tenantId]);
        $summaryTotalRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0);

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS today_revenue FROM tbl_payments WHERE tenant_id = ? AND DATE(payment_date) = ? AND status IN ('completed', 'paid')");
        $stmt->execute([$tenantId, $today]);
        $summaryTodayRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['today_revenue'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total_payments FROM tbl_payments WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $summaryTotalPayments = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_payments'] ?? 0);

        $recentInstallmentPlanSqlParts = [];
        if ($installmentsTableName !== null) {
            $quotedInstallmentsTable = '`' . str_replace('`', '``', $installmentsTableName) . '`';
            $recentInstallmentPlanSqlParts[] = "
                EXISTS (
                    SELECT 1
                    FROM {$quotedInstallmentsTable} i
                    WHERE i.booking_id = a.booking_id
                      AND (
                          i.tenant_id = a.tenant_id
                          OR i.tenant_id IS NULL
                          OR TRIM(COALESCE(i.tenant_id, '')) = ''
                      )
                )
            ";
        }
        $recentInstallmentPlanSqlParts[] = "
            (
                TRIM(COALESCE(a.treatment_id, '')) <> ''
                OR LOWER(TRIM(COALESCE(a.service_type, ''))) = 'installment'
            )
        ";
        if ($supportsAppointmentServicesTable && $supportsAppointmentServiceTypeColumn) {
            $recentInstallmentPlanSqlParts[] = "
                EXISTS (
                    SELECT 1
                    FROM tbl_appointment_services aps
                    WHERE aps.tenant_id = a.tenant_id
                      AND aps.booking_id = a.booking_id
                      AND COALESCE(NULLIF(TRIM(aps.service_type), ''), 'installment') = 'installment'
                )
            ";
        } elseif ($supportsAppointmentServicesTable && $supportsServiceEnableInstallmentColumn) {
            $recentInstallmentPlanSqlParts[] = "
                EXISTS (
                    SELECT 1
                    FROM tbl_appointment_services aps
                    INNER JOIN tbl_services sv
                        ON sv.tenant_id = aps.tenant_id
                       AND sv.service_id = aps.service_id
                    WHERE aps.tenant_id = a.tenant_id
                      AND aps.booking_id = a.booking_id
                      AND COALESCE(sv.enable_installment, 0) = 1
                )
            ";
        }
        $recentInstallmentPlanSelectSql = empty($recentInstallmentPlanSqlParts)
            ? '0 AS is_installment_plan'
            : '( ' . implode(' OR ', $recentInstallmentPlanSqlParts) . ' ) AS is_installment_plan';

        $recentServiceSelectSql = "COALESCE(a.service_type, '')";
        $recentServiceJoinSql = '';
        if ($supportsAppointmentServicesTable) {
            $recentServiceSelectSql = "COALESCE(aps.service_list, COALESCE(a.service_type, ''))";
            $recentServiceJoinSql = "
                LEFT JOIN (
                    SELECT booking_id, GROUP_CONCAT(service_name ORDER BY service_name SEPARATOR ', ') AS service_list
                    FROM tbl_appointment_services
                    WHERE tenant_id = ?
                    GROUP BY booking_id
                ) aps
                    ON aps.booking_id = py.booking_id
            ";
        }

        $recentInstallmentNumberSelectSql = $supportsPaymentsInstallmentNumberColumn
            ? 'COALESCE(py.installment_number, 0)'
            : '0';

        $recentSql = "
            SELECT
                py.payment_id,
                py.patient_id,
                py.booking_id,
                py.amount,
                py.payment_date,
                py.payment_method,
                py.reference_number,
                py.status,
                {$recentInstallmentNumberSelectSql} AS installment_number,
                {$recentInstallmentPlanSelectSql},
                COALESCE(a.appointment_date, '') AS appointment_date,
                COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
                COALESCE((
                    SELECT SUM(py2.amount)
                    FROM tbl_payments py2
                    WHERE py2.tenant_id = py.tenant_id
                      AND py2.booking_id = py.booking_id
                      AND py2.status IN ('completed', 'paid')
                ), 0) AS booking_total_paid,
                {$recentServiceSelectSql} AS service_list,
                COALESCE(NULLIF(u_linked.email, ''), NULLIF(u_owner.email, ''), '') AS patient_email,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name
            FROM tbl_payments py
            LEFT JOIN tbl_patients p
                ON p.tenant_id = py.tenant_id
               AND p.patient_id = py.patient_id
            LEFT JOIN tbl_users u_linked
                ON u_linked.user_id = p.linked_user_id
            LEFT JOIN tbl_users u_owner
                ON u_owner.user_id = p.owner_user_id
            LEFT JOIN tbl_appointments a
                ON a.tenant_id = py.tenant_id
               AND a.booking_id = py.booking_id
            {$recentServiceJoinSql}
            WHERE py.tenant_id = ?
            ORDER BY py.id DESC, py.payment_date DESC
            LIMIT 20
        ";
        $recentStmt = $pdo->prepare($recentSql);
        $recentParams = $supportsAppointmentServicesTable ? [$tenantId, $tenantId] : [$tenantId];
        $recentStmt->execute($recentParams);
        $recentPayments = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($installmentsTableName !== null && $recentPayments !== []) {
            $recentScheduleByBooking = [];
            foreach ($recentPayments as $idx => $paymentRow) {
                $isInstallmentPlan = !empty($paymentRow['is_installment_plan']);
                $bookingId = trim((string) ($paymentRow['booking_id'] ?? ''));
                if (!$isInstallmentPlan || $bookingId === '') {
                    $recentPayments[$idx]['installment_schedule'] = [];
                    continue;
                }
                if (!isset($recentScheduleByBooking[$bookingId])) {
                    $recentScheduleByBooking[$bookingId] = staff_payment_recording_fetch_installments(
                        $pdo,
                        $installmentsTableName,
                        $tenantId,
                        $bookingId
                    );
                }
                $recentPayments[$idx]['installment_schedule'] = $recentScheduleByBooking[$bookingId];
            }
        }

        $installmentPlanSqlParts = [];
        if ($installmentsTableName !== null) {
            $quotedInstallmentsTable = '`' . str_replace('`', '``', $installmentsTableName) . '`';
            // Installment rows are often inserted without tenant_id (see clinic/api/appointments.php);
            // match by booking_id and accept NULL/empty tenant_id on the installment side.
            $installmentPlanSqlParts[] = "
                EXISTS (
                    SELECT 1
                    FROM {$quotedInstallmentsTable} i
                    WHERE i.booking_id = a.booking_id
                      AND (
                          i.tenant_id = a.tenant_id
                          OR i.tenant_id IS NULL
                          OR TRIM(COALESCE(i.tenant_id, '')) = ''
                      )
                )
            ";
        }
        $installmentPlanSqlParts[] = "
            (
                TRIM(COALESCE(a.treatment_id, '')) <> ''
                OR LOWER(TRIM(COALESCE(a.service_type, ''))) = 'installment'
            )
        ";
        if ($supportsAppointmentServicesTable && $supportsAppointmentServiceTypeColumn) {
            $installmentPlanSqlParts[] = "
                EXISTS (
                    SELECT 1
                    FROM tbl_appointment_services aps
                    WHERE aps.tenant_id = a.tenant_id
                      AND aps.booking_id = a.booking_id
                      AND COALESCE(NULLIF(TRIM(aps.service_type), ''), 'installment') = 'installment'
                )
            ";
        } elseif ($supportsAppointmentServicesTable && $supportsServiceEnableInstallmentColumn) {
            $installmentPlanSqlParts[] = "
                EXISTS (
                    SELECT 1
                    FROM tbl_appointment_services aps
                    INNER JOIN tbl_services sv
                        ON sv.tenant_id = aps.tenant_id
                       AND sv.service_id = aps.service_id
                    WHERE aps.tenant_id = a.tenant_id
                      AND aps.booking_id = a.booking_id
                      AND COALESCE(sv.enable_installment, 0) = 1
                )
            ";
        }
        if (empty($installmentPlanSqlParts)) {
            $installmentPlanSelectSql = '0 AS is_installment_plan';
        } else {
            $installmentPlanSelectSql = '( ' . implode(' OR ', $installmentPlanSqlParts) . ' ) AS is_installment_plan';
        }
        $transactionTypeSelectSql = empty($installmentPlanSqlParts)
            ? "'regular' AS transaction_type"
            : "CASE WHEN ( " . implode(' OR ', $installmentPlanSqlParts) . " ) THEN 'installment' ELSE 'regular' END AS transaction_type";

        $visitTypeSelectSql = $supportsAppointmentVisitTypeColumn
            ? "COALESCE(a.visit_type, '') AS visit_type,"
            : "'' AS visit_type,";
        $visitTypeGroupSql = $supportsAppointmentVisitTypeColumn ? "a.visit_type," : '';

        if ($supportsAppointmentServicesTable) {
            $installmentTreatmentIdSql = "COALESCE(NULLIF(aps.treatment_id, ''), NULLIF(a.treatment_id, ''), '')";
            $appointmentServicesJoinSql = $supportsAppointmentServiceAppointmentIdColumn
                ? "
                    LEFT JOIN tbl_appointment_services aps
                        ON aps.tenant_id = a.tenant_id
                       AND aps.booking_id = a.booking_id
                       AND (
                            aps.appointment_id IS NULL
                            OR aps.appointment_id = 0
                            OR aps.appointment_id = a.id
                       )
                "
                : "
                    LEFT JOIN tbl_appointment_services aps
                        ON aps.tenant_id = a.tenant_id
                       AND aps.booking_id = a.booking_id
                ";
            $normalizedServiceTypeSql = $supportsAppointmentServiceTypeColumn
                ? "CASE
                        WHEN LOWER(TRIM(COALESCE(NULLIF(aps.service_type, ''), NULLIF(a.service_type, ''), ''))) = 'regular' THEN 'regular'
                        WHEN LOWER(TRIM(COALESCE(NULLIF(aps.service_type, ''), NULLIF(a.service_type, ''), ''))) = 'installment' THEN 'installment'
                        WHEN TRIM(COALESCE(NULLIF(aps.treatment_id, ''), NULLIF(a.treatment_id, ''), '')) <> '' THEN 'installment'
                        ELSE 'regular'
                    END"
                : ($supportsServiceEnableInstallmentColumn
                    ? "CASE
                            WHEN COALESCE(sv.enable_installment, 0) = 1 THEN 'installment'
                            WHEN TRIM(COALESCE(a.treatment_id, '')) <> '' THEN 'installment'
                            WHEN LOWER(TRIM(COALESCE(a.service_type, ''))) = 'installment' THEN 'installment'
                            ELSE 'regular'
                        END"
                    : "'installment'");
            $transactionsSql = "
                SELECT
                    a.booking_id,
                    COALESCE(aps.appointment_id, a.id, 0) AS appointment_id,
                    CASE
                        WHEN {$normalizedServiceTypeSql} = 'installment'
                            THEN {$installmentTreatmentIdSql}
                        ELSE ''
                    END AS treatment_id,
                    a.patient_id,
                    a.appointment_date,
                    a.appointment_time,
                    {$normalizedServiceTypeSql} AS service_type,
                    {$visitTypeSelectSql}
                    CASE WHEN {$normalizedServiceTypeSql} = 'installment' THEN 1 ELSE 0 END AS is_installment_plan,
                    CASE WHEN {$normalizedServiceTypeSql} = 'installment' THEN 'installment' ELSE 'regular' END AS transaction_type,
                    CASE
                        WHEN {$normalizedServiceTypeSql} = 'installment'
                            THEN COALESCE(NULLIF(a.total_treatment_cost, 0), COALESCE(SUM(aps.price), 0))
                        ELSE COALESCE(SUM(aps.price), 0)
                    END AS total_treatment_cost,
                    COALESCE(py.total_paid, 0) AS total_paid,
                    COALESCE(t.total_cost, 0) AS treatment_total_cost,
                    COALESCE(t.amount_paid, 0) AS treatment_amount_paid,
                    COALESCE(t.remaining_balance, 0) AS treatment_remaining_balance,
                    p.first_name AS patient_first_name,
                    p.last_name AS patient_last_name
                FROM tbl_appointments a
                {$appointmentServicesJoinSql}
                LEFT JOIN tbl_services sv
                    ON sv.tenant_id = aps.tenant_id
                   AND sv.service_id = aps.service_id
                LEFT JOIN (
                    SELECT tenant_id, booking_id, COALESCE(SUM(amount), 0) AS total_paid
                    FROM tbl_payments
                    WHERE status IN ('completed', 'paid')
                    GROUP BY tenant_id, booking_id
                ) py
                    ON py.tenant_id = a.tenant_id
                   AND py.booking_id = a.booking_id
                LEFT JOIN tbl_treatments t
                    ON t.tenant_id = a.tenant_id
                   AND t.treatment_id = {$installmentTreatmentIdSql}
                LEFT JOIN tbl_patients p
                    ON p.tenant_id = a.tenant_id
                   AND p.patient_id = a.patient_id
                WHERE a.tenant_id = ?
                  AND LOWER(COALESCE(a.status, '')) <> 'cancelled'
                GROUP BY
                    a.booking_id,
                    COALESCE(aps.appointment_id, a.id, 0),
                    CASE
                        WHEN {$normalizedServiceTypeSql} = 'installment'
                            THEN {$installmentTreatmentIdSql}
                        ELSE ''
                    END,
                    a.patient_id,
                    a.appointment_date,
                    a.appointment_time,
                    {$normalizedServiceTypeSql},
                    {$visitTypeGroupSql}
                    a.total_treatment_cost,
                    t.total_cost,
                    t.amount_paid,
                    t.remaining_balance,
                    p.first_name,
                    p.last_name
                ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC
                LIMIT 300
            ";
        } else {
            $transactionsSql = "
                SELECT
                    a.booking_id,
                    COALESCE(a.id, 0) AS appointment_id,
                    COALESCE(a.treatment_id, '') AS treatment_id,
                    a.patient_id,
                    a.appointment_date,
                    a.appointment_time,
                    a.service_type,
                    {$visitTypeSelectSql}
                    {$installmentPlanSelectSql},
                    {$transactionTypeSelectSql},
                    COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
                    COALESCE(py.total_paid, 0) AS total_paid,
                    COALESCE(t.total_cost, 0) AS treatment_total_cost,
                    COALESCE(t.amount_paid, 0) AS treatment_amount_paid,
                    COALESCE(t.remaining_balance, 0) AS treatment_remaining_balance,
                    p.first_name AS patient_first_name,
                    p.last_name AS patient_last_name
                FROM tbl_appointments a
                LEFT JOIN (
                    SELECT tenant_id, booking_id, COALESCE(SUM(amount), 0) AS total_paid
                    FROM tbl_payments
                    WHERE status IN ('completed', 'paid')
                    GROUP BY tenant_id, booking_id
                ) py
                    ON py.tenant_id = a.tenant_id
                   AND py.booking_id = a.booking_id
                LEFT JOIN tbl_treatments t
                    ON t.tenant_id = a.tenant_id
                   AND t.treatment_id = COALESCE(a.treatment_id, '')
                LEFT JOIN tbl_patients p
                    ON p.tenant_id = a.tenant_id
                   AND p.patient_id = a.patient_id
                WHERE a.tenant_id = ?
                  AND LOWER(COALESCE(a.status, '')) <> 'cancelled'
                GROUP BY
                    a.booking_id,
                    a.id,
                    a.treatment_id,
                    a.patient_id,
                    a.appointment_date,
                    a.appointment_time,
                    a.service_type,
                    {$visitTypeGroupSql}
                    a.total_treatment_cost,
                    t.total_cost,
                    t.amount_paid,
                    t.remaining_balance,
                    p.first_name,
                    p.last_name
                ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC
                LIMIT 300
            ";
        }
        $transactionsStmt = $pdo->prepare($transactionsSql);
        $transactionsStmt->execute([$tenantId]);
        $transactionCandidates = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $paymentSplitByBooking = [];
        if ($transactionCandidates !== []) {
            $bookingIdsForSplit = [];
            foreach ($transactionCandidates as $candRow) {
                $splitBookingId = trim((string) ($candRow['booking_id'] ?? ''));
                if ($splitBookingId !== '') {
                    $bookingIdsForSplit[$splitBookingId] = true;
                }
            }
            $bookingIdListForSplit = array_keys($bookingIdsForSplit);
            if ($bookingIdListForSplit !== []) {
                $splitPlaceholders = implode(',', array_fill(0, count($bookingIdListForSplit), '?'));
                if ($supportsPaymentsInstallmentNumberColumn) {
                    $splitSql = "
                        SELECT
                            booking_id,
                            COALESCE(SUM(CASE WHEN status IN ('completed', 'paid') THEN amount ELSE 0 END), 0) AS total_paid_amount,
                            COALESCE(SUM(CASE WHEN status IN ('completed', 'paid') AND COALESCE(installment_number, 0) > 0 THEN amount ELSE 0 END), 0) AS installment_paid_amount,
                            COALESCE(SUM(CASE WHEN status IN ('completed', 'paid') AND COALESCE(installment_number, 0) <= 0 THEN amount ELSE 0 END), 0) AS regular_paid_amount
                        FROM tbl_payments
                        WHERE tenant_id = ?
                          AND booking_id IN ({$splitPlaceholders})
                        GROUP BY booking_id
                    ";
                } else {
                    $splitSql = "
                        SELECT
                            booking_id,
                            COALESCE(SUM(CASE WHEN status IN ('completed', 'paid') THEN amount ELSE 0 END), 0) AS total_paid_amount,
                            0 AS installment_paid_amount,
                            COALESCE(SUM(CASE WHEN status IN ('completed', 'paid') THEN amount ELSE 0 END), 0) AS regular_paid_amount
                        FROM tbl_payments
                        WHERE tenant_id = ?
                          AND booking_id IN ({$splitPlaceholders})
                        GROUP BY booking_id
                    ";
                }
                $splitStmt = $pdo->prepare($splitSql);
                $splitStmt->execute(array_merge([$tenantId], $bookingIdListForSplit));
                foreach ($splitStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $splitRow) {
                    $splitBookingId = trim((string) ($splitRow['booking_id'] ?? ''));
                    if ($splitBookingId === '') {
                        continue;
                    }
                    $paymentSplitByBooking[$splitBookingId] = [
                        'total_paid_amount' => round((float) ($splitRow['total_paid_amount'] ?? 0), 2),
                        'installment_paid_amount' => round((float) ($splitRow['installment_paid_amount'] ?? 0), 2),
                        'regular_paid_amount' => round((float) ($splitRow['regular_paid_amount'] ?? 0), 2),
                    ];
                }
            }
        }

        // Installment rows must be authoritative from tbl_treatments to avoid
        // mismatched patient/treatment pairings from appointment-side joins.
        $installmentTreatmentsById = [];
        $latestAppointmentByTreatmentId = [];
        $treatmentsTableExistsStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_treatments'
            LIMIT 1
        ");
        $treatmentsTableExistsStmt->execute();
        $supportsTreatmentsTable = (bool) $treatmentsTableExistsStmt->fetchColumn();
        if ($supportsTreatmentsTable) {
            $treatmentsSql = "
                SELECT
                    t.treatment_id,
                    t.patient_id,
                    COALESCE(t.total_cost, 0) AS total_cost,
                    COALESCE(t.amount_paid, 0) AS amount_paid,
                    COALESCE(t.remaining_balance, 0) AS remaining_balance,
                    COALESCE(t.primary_service_name, '') AS primary_service_name,
                    p.first_name AS patient_first_name,
                    p.last_name AS patient_last_name
                FROM tbl_treatments t
                LEFT JOIN tbl_patients p
                    ON p.tenant_id = t.tenant_id
                   AND p.patient_id = t.patient_id
                WHERE t.tenant_id = ?
                  AND TRIM(COALESCE(t.treatment_id, '')) <> ''
                  AND LOWER(COALESCE(t.status, 'active')) = 'active'
                  AND COALESCE(t.remaining_balance, 0) > 0.009
            ";
            $treatmentsStmt = $pdo->prepare($treatmentsSql);
            $treatmentsStmt->execute([$tenantId]);
            foreach ($treatmentsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $tr) {
                $tid = trim((string) ($tr['treatment_id'] ?? ''));
                if ($tid === '') {
                    continue;
                }
                $installmentTreatmentsById[$tid] = $tr;
            }

            if ($installmentTreatmentsById !== []) {
                $latestApptSql = "
                    SELECT
                        a.treatment_id,
                        a.booking_id,
                        a.id AS appointment_id,
                        a.appointment_date,
                        a.appointment_time,
                        " . ($supportsAppointmentVisitTypeColumn ? "COALESCE(a.visit_type, '')" : "''") . " AS visit_type
                    FROM tbl_appointments a
                    INNER JOIN (
                        SELECT treatment_id, MAX(created_at) AS max_created_at
                        FROM tbl_appointments
                        WHERE tenant_id = ?
                          AND TRIM(COALESCE(treatment_id, '')) <> ''
                          AND LOWER(COALESCE(status, '')) <> 'cancelled'
                        GROUP BY treatment_id
                    ) latest
                      ON latest.treatment_id = a.treatment_id
                     AND latest.max_created_at = a.created_at
                    WHERE a.tenant_id = ?
                      AND TRIM(COALESCE(a.treatment_id, '')) <> ''
                ";
                $latestApptStmt = $pdo->prepare($latestApptSql);
                $latestApptStmt->execute([$tenantId, $tenantId]);
                foreach ($latestApptStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ar) {
                    $tid = trim((string) ($ar['treatment_id'] ?? ''));
                    if ($tid === '' || isset($latestAppointmentByTreatmentId[$tid])) {
                        continue;
                    }
                    $latestAppointmentByTreatmentId[$tid] = $ar;
                }
            }
        }

        if ($installmentTreatmentsById !== []) {
            $seenInstallmentTreatments = [];
            foreach ($transactionCandidates as $idx => $candRow) {
                $tid = trim((string) ($candRow['treatment_id'] ?? ''));
                if ($tid === '' || !isset($installmentTreatmentsById[$tid])) {
                    continue;
                }
                $tr = $installmentTreatmentsById[$tid];
                $latestAppt = $latestAppointmentByTreatmentId[$tid] ?? null;
                $transactionCandidates[$idx]['patient_id'] = (string) ($tr['patient_id'] ?? ($candRow['patient_id'] ?? ''));
                $transactionCandidates[$idx]['patient_first_name'] = (string) ($tr['patient_first_name'] ?? ($candRow['patient_first_name'] ?? ''));
                $transactionCandidates[$idx]['patient_last_name'] = (string) ($tr['patient_last_name'] ?? ($candRow['patient_last_name'] ?? ''));
                $transactionCandidates[$idx]['total_treatment_cost'] = (float) ($tr['total_cost'] ?? ($candRow['total_treatment_cost'] ?? 0));
                $transactionCandidates[$idx]['total_paid'] = (float) ($tr['amount_paid'] ?? ($candRow['total_paid'] ?? 0));
                $transactionCandidates[$idx]['treatment_total_cost'] = (float) ($tr['total_cost'] ?? 0);
                $transactionCandidates[$idx]['treatment_amount_paid'] = (float) ($tr['amount_paid'] ?? 0);
                $transactionCandidates[$idx]['treatment_remaining_balance'] = (float) ($tr['remaining_balance'] ?? 0);
                if ($latestAppt !== null) {
                    $transactionCandidates[$idx]['booking_id'] = trim((string) ($latestAppt['booking_id'] ?? ($candRow['booking_id'] ?? '')));
                    $transactionCandidates[$idx]['appointment_id'] = (int) ($latestAppt['appointment_id'] ?? ($candRow['appointment_id'] ?? 0));
                    $transactionCandidates[$idx]['appointment_date'] = (string) ($latestAppt['appointment_date'] ?? ($candRow['appointment_date'] ?? ''));
                    $transactionCandidates[$idx]['appointment_time'] = (string) ($latestAppt['appointment_time'] ?? ($candRow['appointment_time'] ?? ''));
                    $transactionCandidates[$idx]['visit_type'] = (string) ($latestAppt['visit_type'] ?? ($candRow['visit_type'] ?? ''));
                }
                $seenInstallmentTreatments[$tid] = true;
            }

            foreach ($installmentTreatmentsById as $tid => $tr) {
                if (isset($seenInstallmentTreatments[$tid])) {
                    continue;
                }
                $latestAppt = $latestAppointmentByTreatmentId[$tid] ?? null;
                if ($latestAppt === null) {
                    continue;
                }
                $transactionCandidates[] = [
                    'booking_id' => trim((string) ($latestAppt['booking_id'] ?? '')),
                    'appointment_id' => (int) ($latestAppt['appointment_id'] ?? 0),
                    'treatment_id' => $tid,
                    'patient_id' => (string) ($tr['patient_id'] ?? ''),
                    'appointment_date' => (string) ($latestAppt['appointment_date'] ?? ''),
                    'appointment_time' => (string) ($latestAppt['appointment_time'] ?? ''),
                    'service_type' => 'installment',
                    'visit_type' => (string) ($latestAppt['visit_type'] ?? ''),
                    'is_installment_plan' => 1,
                    'transaction_type' => 'installment',
                    'total_treatment_cost' => (float) ($tr['total_cost'] ?? 0),
                    'total_paid' => (float) ($tr['amount_paid'] ?? 0),
                    'treatment_total_cost' => (float) ($tr['total_cost'] ?? 0),
                    'treatment_amount_paid' => (float) ($tr['amount_paid'] ?? 0),
                    'treatment_remaining_balance' => (float) ($tr['remaining_balance'] ?? 0),
                    'patient_first_name' => (string) ($tr['patient_first_name'] ?? ''),
                    'patient_last_name' => (string) ($tr['patient_last_name'] ?? ''),
                ];
            }
        }

        $scheduleByBooking = [];
        if ($installmentsTableName !== null && $transactionCandidates !== []) {
            $allBookingIds = [];
            foreach ($transactionCandidates as $candRow) {
                $bid = trim((string) ($candRow['booking_id'] ?? ''));
                if ($bid !== '') {
                    $allBookingIds[$bid] = true;
                }
            }
            $idList = array_keys($allBookingIds);
            if ($idList !== []) {
                $quotedInstTable = '`' . str_replace('`', '``', $installmentsTableName) . '`';
                $placeholders = implode(',', array_fill(0, count($idList), '?'));
                $instFetchSql = "
                    SELECT booking_id, id, installment_number, amount_due, status
                    FROM {$quotedInstTable} i
                    WHERE i.booking_id IN ($placeholders)
                      AND (
                          i.tenant_id = ?
                          OR i.tenant_id IS NULL
                          OR TRIM(COALESCE(i.tenant_id, '')) = ''
                      )
                    ORDER BY i.booking_id ASC, i.installment_number ASC
                ";
                $instFetchStmt = $pdo->prepare($instFetchSql);
                $instFetchStmt->execute(array_merge($idList, [$tenantId]));
                foreach ($instFetchStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $ir) {
                    $b = trim((string) ($ir['booking_id'] ?? ''));
                    if ($b === '') {
                        continue;
                    }
                    if (!isset($scheduleByBooking[$b])) {
                        $scheduleByBooking[$b] = [];
                    }
                    $scheduleByBooking[$b][] = [
                        'id' => (int) ($ir['id'] ?? 0),
                        'installment_number' => (int) ($ir['installment_number'] ?? 0),
                        'amount_due' => round((float) ($ir['amount_due'] ?? 0), 2),
                        'status' => trim((string) ($ir['status'] ?? '')),
                    ];
                }
            }
        }
        if ($installmentsTableName !== null && $transactionCandidates !== []) {
            foreach ($transactionCandidates as $candRow) {
                $bid = trim((string) ($candRow['booking_id'] ?? ''));
                if ($bid === '' || empty($candRow['is_installment_plan'])) {
                    continue;
                }
                staff_payment_recording_ensure_installment_schedule(
                    $pdo,
                    $installmentsTableName,
                    $tenantId,
                    $bid,
                    $supportsAppointmentServicesTable,
                    $supportsServiceEnableInstallmentColumn
                );
                $scheduleByBooking[$bid] = staff_payment_recording_fetch_installments($pdo, $installmentsTableName, $tenantId, $bid);
            }
        }
        $bookedServicesByBucket = [];
        if ($supportsAppointmentServicesTable && $transactionCandidates !== []) {
            $bookedBidKeys = [];
            foreach ($transactionCandidates as $candRow) {
                $bb = trim((string) ($candRow['booking_id'] ?? ''));
                if ($bb !== '') {
                    $bookedBidKeys[$bb] = true;
                }
            }
            $bookedIdList = array_keys($bookedBidKeys);
            if ($bookedIdList !== []) {
                $bookedPh = implode(',', array_fill(0, count($bookedIdList), '?'));
                $bookedServiceTypeSelectSql = $supportsAppointmentServiceTypeColumn
                    ? "CASE
                            WHEN LOWER(TRIM(COALESCE(aps.service_type, ''))) = 'regular' THEN 'regular'
                            WHEN LOWER(TRIM(COALESCE(aps.service_type, ''))) = 'installment' THEN 'installment'
                            WHEN TRIM(COALESCE(aps.treatment_id, '')) <> '' THEN 'installment'
                            ELSE 'regular'
                        END"
                    : "'installment'";
                $bookedSql = "
                    SELECT
                        aps.booking_id,
                        COALESCE(aps.appointment_id, a2.id, 0) AS appointment_id,
                        aps.service_id,
                        aps.service_name,
                        aps.price,
                        {$bookedServiceTypeSelectSql} AS service_type,
                        COALESCE(NULLIF(sv.category, ''), '') AS category
                    FROM tbl_appointment_services aps
                    LEFT JOIN tbl_appointments a2
                      ON a2.tenant_id = aps.tenant_id
                     AND a2.booking_id = aps.booking_id
                     AND (
                        aps.appointment_id IS NULL
                        OR aps.appointment_id = 0
                        OR a2.id = aps.appointment_id
                     )
                    LEFT JOIN tbl_services sv
                      ON sv.tenant_id = aps.tenant_id
                     AND sv.service_id = aps.service_id
                    WHERE aps.tenant_id = ?
                      AND aps.booking_id IN ({$bookedPh})
                    ORDER BY aps.booking_id ASC, aps.service_name ASC
                ";
                $bookedStmt = $pdo->prepare($bookedSql);
                $bookedStmt->execute(array_merge([$tenantId], $bookedIdList));
                foreach ($bookedStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $brow) {
                    $bb = trim((string) ($brow['booking_id'] ?? ''));
                    if ($bb === '') {
                        continue;
                    }
                    $apptId = (int) ($brow['appointment_id'] ?? 0);
                    $stype = strtolower(trim((string) ($brow['service_type'] ?? 'installment')));
                    if ($stype !== 'regular') {
                        $stype = 'installment';
                    }
                    $bucketKey = $bb . '::' . $apptId . '::' . $stype;
                    if (!isset($bookedServicesByBucket[$bucketKey])) {
                        $bookedServicesByBucket[$bucketKey] = [];
                    }
                    $bookedServicesByBucket[$bucketKey][] = [
                        'service_id' => trim((string) ($brow['service_id'] ?? '')),
                        'service_name' => trim((string) ($brow['service_name'] ?? '')),
                        'service_type' => $stype,
                        'category' => trim((string) ($brow['category'] ?? '')),
                        'price' => round((float) ($brow['price'] ?? 0), 2),
                    ];
                }
            }
        }
        $primaryInstallmentServiceByTreatment = [];
        if ($transactionCandidates !== []) {
            $treatmentIds = [];
            foreach ($transactionCandidates as $candRow) {
                $tid = trim((string) ($candRow['treatment_id'] ?? ''));
                if ($tid !== '') {
                    $treatmentIds[$tid] = true;
                }
            }
            $treatmentIdList = array_keys($treatmentIds);
            if ($treatmentIdList !== []) {
                $treatTblStmt = $pdo->prepare("
                    SELECT TABLE_NAME
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME IN ('tbl_treatments', 'treatments')
                    ORDER BY FIELD(TABLE_NAME, 'tbl_treatments', 'treatments')
                    LIMIT 1
                ");
                $treatTblStmt->execute();
                $treatTableName = trim((string) ($treatTblStmt->fetchColumn() ?: ''));
                if ($treatTableName !== '') {
                    $quotedTreat = '`' . str_replace('`', '``', $treatTableName) . '`';
                    $treatPh = implode(',', array_fill(0, count($treatmentIdList), '?'));
                    $treatSql = "
                        SELECT
                            treatment_id,
                            COALESCE(primary_service_id, '') AS primary_service_id,
                            remaining_balance
                        FROM {$quotedTreat}
                        WHERE tenant_id = ?
                          AND treatment_id IN ({$treatPh})
                    ";
                    $treatStmt = $pdo->prepare($treatSql);
                    $treatStmt->execute(array_merge([$tenantId], $treatmentIdList));
                    foreach ($treatStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $tr) {
                        $tid = trim((string) ($tr['treatment_id'] ?? ''));
                        if ($tid === '') {
                            continue;
                        }
                        $primaryInstallmentServiceByTreatment[$tid] = [
                            'primary_service_id' => trim((string) ($tr['primary_service_id'] ?? '')),
                            'remaining_balance' => isset($tr['remaining_balance']) ? round((float) $tr['remaining_balance'], 2) : null,
                        ];
                    }
                }
            }
        }

        foreach ($transactionCandidates as $ic => $candRow) {
            $b = trim((string) ($candRow['booking_id'] ?? ''));
            $appointmentId = (int) ($candRow['appointment_id'] ?? 0);
            $tid = trim((string) ($candRow['treatment_id'] ?? ''));
            $stype = strtolower(trim((string) ($candRow['service_type'] ?? '')));
            if ($stype !== 'regular') {
                $stype = 'installment';
            }
            $bucketKey = $b . '::' . $appointmentId . '::' . $stype;
            $transactionCandidates[$ic]['installment_schedule'] = $scheduleByBooking[$b] ?? [];
            $transactionCandidates[$ic]['booked_services'] = $bookedServicesByBucket[$bucketKey] ?? [];
            $primaryInstallmentMeta = $tid !== '' ? ($primaryInstallmentServiceByTreatment[$tid] ?? null) : null;
            $transactionCandidates[$ic]['primary_installment_service_id'] = is_array($primaryInstallmentMeta)
                ? trim((string) ($primaryInstallmentMeta['primary_service_id'] ?? ''))
                : '';
            $splitMeta = $paymentSplitByBooking[$b] ?? null;
            $transactionCandidates[$ic]['regular_paid_amount'] = is_array($splitMeta)
                ? (float) ($splitMeta['regular_paid_amount'] ?? 0)
                : 0.0;
            $transactionCandidates[$ic]['installment_paid_amount'] = is_array($splitMeta)
                ? (float) ($splitMeta['installment_paid_amount'] ?? 0)
                : 0.0;
            $transactionCandidates[$ic]['payment_total_paid_amount'] = is_array($splitMeta)
                ? (float) ($splitMeta['total_paid_amount'] ?? 0)
                : (float) ($candRow['total_paid'] ?? 0);
            $transactionCandidates[$ic]['treatment_remaining_balance'] = is_array($primaryInstallmentMeta)
                ? (isset($primaryInstallmentMeta['remaining_balance']) ? (float) $primaryInstallmentMeta['remaining_balance'] : null)
                : null;
        }
        if ($transactionCandidates !== []) {
            $collapsedByTreatment = [];
            $collapsedKeyOrder = [];
            foreach ($transactionCandidates as $candRow) {
                $candidateType = strtolower(trim((string) ($candRow['service_type'] ?? '')));
                $isInstallment = $candidateType === 'installment';
                $treatmentId = trim((string) ($candRow['treatment_id'] ?? ''));
                if ($isInstallment && $treatmentId !== '') {
                    $groupKey = 'treatment:' . $treatmentId;
                    $currentPaid = (float) ($candRow['total_paid'] ?? 0);
                    $currentScheduleCount = count((array) ($candRow['installment_schedule'] ?? []));
                    if (!isset($collapsedByTreatment[$groupKey])) {
                        $collapsedByTreatment[$groupKey] = $candRow;
                        $collapsedKeyOrder[] = $groupKey;
                        continue;
                    }
                    $existing = $collapsedByTreatment[$groupKey];
                    $existingPaid = (float) ($existing['total_paid'] ?? 0);
                    $existingScheduleCount = count((array) ($existing['installment_schedule'] ?? []));
                    if ($currentPaid > $existingPaid || ($currentPaid === $existingPaid && $currentScheduleCount > $existingScheduleCount)) {
                        $collapsedByTreatment[$groupKey] = $candRow;
                    }
                    continue;
                }
                $appointmentId = (int) ($candRow['appointment_id'] ?? 0);
                $bookingId = trim((string) ($candRow['booking_id'] ?? ''));
                $regularGroupKey = 'regular:' . $bookingId . ':' . $appointmentId;
                if (!isset($collapsedByTreatment[$regularGroupKey])) {
                    $collapsedByTreatment[$regularGroupKey] = $candRow;
                    $collapsedKeyOrder[] = $regularGroupKey;
                } else {
                    $collapsedByTreatment[$regularGroupKey]['total_treatment_cost'] = round(
                        (float) ($collapsedByTreatment[$regularGroupKey]['total_treatment_cost'] ?? 0)
                        + (float) ($candRow['total_treatment_cost'] ?? 0),
                        2
                    );
                    $mergedServices = array_merge(
                        (array) ($collapsedByTreatment[$regularGroupKey]['booked_services'] ?? []),
                        (array) ($candRow['booked_services'] ?? [])
                    );
                    $collapsedByTreatment[$regularGroupKey]['booked_services'] = $mergedServices;
                }
            }
            $collapsedRows = [];
            foreach ($collapsedKeyOrder as $key) {
                if (isset($collapsedByTreatment[$key])) {
                    $collapsedRows[] = $collapsedByTreatment[$key];
                    unset($collapsedByTreatment[$key]);
                }
            }
            foreach ($collapsedByTreatment as $row) {
                if (is_array($row)) {
                    $collapsedRows[] = $row;
                }
            }
            if ($collapsedRows !== []) {
                $transactionCandidates = $collapsedRows;
            }
        }
        foreach ($transactionCandidates as $candRow) {
            $transactionDebugRows[] = [
                'service_type' => strtolower(trim((string) ($candRow['service_type'] ?? ''))),
                'booking_id' => trim((string) ($candRow['booking_id'] ?? '')),
                'appointment_id' => (int) ($candRow['appointment_id'] ?? 0),
                'treatment_id' => trim((string) ($candRow['treatment_id'] ?? '')),
            ];
        }

        $serviceTypeSelectSql = "'regular' AS normalized_service_type";
        if ($supportsServiceEnableInstallmentColumn) {
            $serviceTypeSelectSql = "CASE WHEN COALESCE(enable_installment, 0) = 1 THEN 'installment' ELSE 'regular' END AS normalized_service_type";
        }
        $servicesStmt = $pdo->prepare("
            SELECT service_id, service_name, category, price, {$serviceTypeSelectSql}
            FROM tbl_services
            WHERE tenant_id = ?
              AND status = 'active'
            ORDER BY service_name ASC
        ");
        $servicesStmt->execute([$tenantId]);
        $availableServices = $servicesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="payment-transactions-' . date('Ymd-His') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Payment ID', 'Patient ID', 'Patient Name', 'Amount', 'Payment Date', 'Method', 'Status']);
            foreach ($recentPayments as $row) {
                $fullName = trim(((string) ($row['patient_first_name'] ?? '')) . ' ' . ((string) ($row['patient_last_name'] ?? '')));
                fputcsv($output, [
                    (string) ($row['payment_id'] ?? ''),
                    (string) ($row['patient_id'] ?? ''),
                    $fullName !== '' ? $fullName : 'Unknown Patient',
                    number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                    (string) ($row['payment_date'] ?? ''),
                    (string) ($row['payment_method'] ?? ''),
                    (string) ($row['status'] ?? ''),
                ]);
            }
            fclose($output);
            exit;
        }
    }
} catch (Throwable $e) {
    error_log('Staff payment recording error: ' . $e->getMessage());
    if ($paymentError === '') {
        $paymentError = 'Unable to load payment data right now. Please try again.';
    }
}

$inlinePaymentError = $paymentError;
$serverValidationPopupMessage = '';
if ($paymentError === 'Please select a payment method.') {
    $serverValidationPopupMessage = 'No payment method selected';
    $inlinePaymentError = '';
} elseif ($paymentError === 'Please select a pending appointment transaction first.') {
    $serverValidationPopupMessage = 'No patient selected';
    $inlinePaymentError = '';
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Staff - Payment Recording</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background": "#f8fafc",
                        "surface": "#ffffff",
                        "on-background": "#101922",
                        "on-surface-variant": "#404752",
                        "surface-container-low": "#edf4ff",
                        "outline-variant": "#cbd5e1"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem"
                    },
                },
            },
        }
    </script>
<style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .elevated-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .glass-form {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            background-image: radial-gradient(circle at top right, rgba(43, 139, 235, 0.05), transparent);
        }
        .form-input-styled {
            border: 2px solid transparent;
            background: rgba(241, 245, 249, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-input-styled:focus {
            border-color: #2b8beb;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(43, 139, 235, 0.1);
        }
        .payment-card {
            transition: all 0.2s ease;
        }
        .payment-card:hover {
            transform: translateY(-2px);
        }
        .payment-card.active {
            background: #2b8beb;
            color: white;
            border-color: #2b8beb;
            box-shadow: 0 8px 16px -4px rgba(43, 139, 235, 0.4);
        }
        .txn-type-toggle-track {
            position: relative;
            display: flex;
            width: 100%;
            max-width: 28rem;
            margin-left: auto;
            margin-right: auto;
            padding: 0.35rem;
            border-radius: 1rem;
            background: rgba(241, 245, 249, 0.95);
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.06);
        }
        .txn-type-toggle-pill {
            position: absolute;
            top: 0.35rem;
            bottom: 0.35rem;
            left: 0.35rem;
            width: calc(50% - 0.4375rem);
            border-radius: 0.75rem;
            background: #fff;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.06);
            transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1);
            pointer-events: none;
        }
        .txn-type-toggle-track[data-active="installment"] .txn-type-toggle-pill {
            transform: translateX(calc(100% + 0.35rem));
        }
        .txn-type-toggle-btn {
            position: relative;
            z-index: 1;
            flex: 1;
            padding: 0.65rem 0.5rem;
            border-radius: 0.75rem;
            font-size: 0.65rem;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            transition: color 0.2s ease;
        }
        .txn-type-toggle-track[data-active="regular"] .txn-type-toggle-btn[data-txn-type="regular"],
        .txn-type-toggle-track[data-active="installment"] .txn-type-toggle-btn[data-txn-type="installment"] {
            color: #0f172a;
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<!-- SideNavBar Component -->
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> PAYMENT RECORDING
            </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Payment <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Recording</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Record and track all clinic payment transactions
                    </p>
</div>
<button class="px-6 py-3 bg-primary text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all" id="open-transaction-modal" type="button">
                    New Transaction
                </button>
</div>
</section>
<!-- Summary Cards -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<!-- Total Revenue -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">+12.5%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">₱<?php echo number_format($summaryTotalRevenue, 2); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Revenue</p>
</div>
</div>
<!-- Today's Revenue -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">today</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Today</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">₱<?php echo number_format($summaryTodayRevenue, 2); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Today's Revenue</p>
</div>
</div>
<!-- Total Payments -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">receipt_long</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Lifetime</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo number_format($summaryTotalPayments); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Payments</p>
</div>
</div>
</section>
<!-- Recent Transactions Section -->
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="p-8 border-b border-slate-100 flex justify-between items-center bg-white">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">Recent Transactions</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Latest daily transaction log</p>
</div>
<div class="flex gap-3">
<button class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">filter_list</span> Filter
                    </button>
<a class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2" href="?export=csv<?php echo $currentTenantSlug !== '' ? '&clinic_slug=' . urlencode($currentTenantSlug) : ''; ?>">
<span class="material-symbols-outlined text-sm">download</span> Export CSV
                    </a>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Name</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Amount</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date &amp; Time</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Method</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<?php if (empty($recentPayments)): ?>
<tr>
<td class="px-8 py-7 text-sm font-semibold text-slate-500" colspan="6">No payment transactions yet.</td>
</tr>
<?php else: ?>
<?php foreach ($recentPayments as $payment): ?>
<?php
    $patientFirst = trim((string) ($payment['patient_first_name'] ?? ''));
    $patientLast = trim((string) ($payment['patient_last_name'] ?? ''));
    $patientName = trim($patientFirst . ' ' . $patientLast);
    if ($patientName === '') {
        $patientName = 'Unknown Patient';
    }
    $patientIdLabel = trim((string) ($payment['patient_id'] ?? ''));
    $initials = strtoupper(substr($patientFirst !== '' ? $patientFirst : $patientName, 0, 1) . substr($patientLast !== '' ? $patientLast : 'X', 0, 1));
    $amountLabel = '₱' . number_format((float) ($payment['amount'] ?? 0), 2);
    $paymentDateRaw = trim((string) ($payment['payment_date'] ?? ''));
    $paymentDateObj = staff_payment_recording_to_manila_datetime($paymentDateRaw);
    $dateLabel = $paymentDateObj instanceof DateTimeImmutable ? $paymentDateObj->format('M d, Y') : '-';
    $timeLabel = $paymentDateObj instanceof DateTimeImmutable ? $paymentDateObj->format('h:i A') : '-';
    $methodKey = strtolower(trim((string) ($payment['payment_method'] ?? 'cash')));
    $methodLabel = $allowedMethods[$methodKey] ?? ucfirst(str_replace('_', ' ', $methodKey));
    $isBookingInstallmentPlan = !empty($payment['is_installment_plan']);
    $installmentNumber = (int) ($payment['installment_number'] ?? 0);
    $paymentLifecycleStatus = strtolower(trim((string) ($payment['status'] ?? '')));
    $isCompletedPayment = in_array($paymentLifecycleStatus, ['completed', 'paid'], true);
    $isExplicitInstallmentPayment = $installmentNumber > 0;
    // A completed regular add-on payment under an installment booking is fully settled immediately.
    // Do not downgrade it to PARTIAL using booking-level installment remaining balance.
    if ($isCompletedPayment && $isBookingInstallmentPlan && !$isExplicitInstallmentPayment) {
        $financialStatus = 'PAID';
    } else {
        $financialStatus = staff_payment_recording_financial_status(
            (float) ($payment['total_treatment_cost'] ?? 0),
            (float) ($payment['booking_total_paid'] ?? 0),
            trim((string) ($payment['appointment_date'] ?? '')),
            $isBookingInstallmentPlan,
            (array) ($payment['installment_schedule'] ?? [])
        );
    }
    $serviceLabel = trim((string) ($payment['service_list'] ?? ''));
    if ($serviceLabel === '') {
        $serviceLabel = 'Dental treatment';
    }
    $remainingBalance = max(0, (float) ($payment['total_treatment_cost'] ?? 0) - (float) ($payment['booking_total_paid'] ?? 0));
    $referenceLabel = trim((string) ($payment['reference_number'] ?? ''));
    if ($referenceLabel === '') {
        $referenceLabel = trim((string) ($payment['payment_id'] ?? ''));
    }
    $patientEmail = trim((string) ($payment['patient_email'] ?? ''));
    $receiptServiceItems = [];
    $receiptServicesTotal = 0.0;
    if (isset($pdo) && $pdo instanceof PDO) {
        static $receiptServicesByBookingStmt = null;
        if (!($receiptServicesByBookingStmt instanceof PDOStatement)) {
            $receiptServicesByBookingStmt = $pdo->prepare("
                SELECT service_name, price
                FROM tbl_appointment_services
                WHERE tenant_id = ?
                  AND booking_id = ?
                ORDER BY id ASC
            ");
        }
        if ($receiptServicesByBookingStmt) {
            $receiptServicesByBookingStmt->execute([$tenantId, (string) ($payment['booking_id'] ?? '')]);
            $receiptServiceRows = $receiptServicesByBookingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($receiptServiceRows as $receiptServiceRow) {
                $itemName = trim((string) ($receiptServiceRow['service_name'] ?? ''));
                $itemName = preg_replace('/\[[^\]]*\]/', '', $itemName);
                $itemName = trim((string) preg_replace('/\s+/', ' ', (string) $itemName));
                if ($itemName === '') {
                    continue;
                }
                $itemAmount = (float) ($receiptServiceRow['price'] ?? 0);
                $receiptServicesTotal += $itemAmount;
                $receiptServiceItems[] = [
                    'name' => $itemName,
                    'amount' => round($itemAmount, 2),
                ];
            }
        }
    }
    if ($receiptServiceItems === []) {
        $receiptServicesTotal = (float) ($payment['total_treatment_cost'] ?? 0);
    }
    $receiptPayload = [
        'payment_id' => (string) ($payment['payment_id'] ?? ''),
        'patient_name' => $patientName,
        'patient_id' => $patientIdLabel,
        'patient_email' => $patientEmail,
        'service' => $serviceLabel,
        'service_items' => $receiptServiceItems,
        'services_total' => round($receiptServicesTotal, 2),
        'amount_paid' => round((float) ($payment['amount'] ?? 0), 2),
        'remaining_balance' => round($remainingBalance, 2),
        'payment_date' => $paymentDateObj instanceof DateTimeImmutable
            ? $paymentDateObj->format('Y-m-d H:i:s')
            : $paymentDateRaw,
        'payment_method' => $methodLabel,
        'reference_number' => $referenceLabel,
        'booking_id' => (string) ($payment['booking_id'] ?? ''),
        'clinic_name' => $clinicDisplayName,
        'clinic_logo' => $clinicLogoUrl,
    ];
    $statusClasses = 'bg-rose-50 text-rose-700 border border-rose-200';
    if ($financialStatus === 'PAID') {
        $statusClasses = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    } elseif ($financialStatus === 'PARTIAL') {
        $statusClasses = 'bg-amber-50 text-amber-700 border border-amber-200';
    } elseif ($financialStatus === 'UNPAID') {
        $statusClasses = 'bg-slate-100 text-slate-700 border border-slate-200';
    }
?>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-black text-xs"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5"><?php echo $patientIdLabel !== '' ? 'ID: ' . htmlspecialchars($patientIdLabel, ENT_QUOTES, 'UTF-8') : 'ID: N/A'; ?></p>
</div>
</div>
</td>
<td class="px-6 py-5">
<p class="text-sm font-extrabold text-slate-900"><?php echo htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-6 py-5">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-slate-500 text-sm">payments</span>
<span class="text-[10px] font-bold text-slate-600 uppercase tracking-wider"><?php echo htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center justify-center px-3 py-1 <?php echo htmlspecialchars($statusClasses, ENT_QUOTES, 'UTF-8'); ?> text-[10px] font-black rounded-full uppercase tracking-widest">
<?php echo htmlspecialchars($financialStatus, ENT_QUOTES, 'UTF-8'); ?>
</span>
</td>
<td class="px-8 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 hover:bg-primary/10 rounded-lg text-primary transition-colors" title="<?php echo htmlspecialchars((string) ($payment['payment_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" type="button">
<span class="material-symbols-outlined text-sm">visibility</span>
</button>
<button
    class="p-2 hover:bg-primary/10 rounded-lg text-primary transition-colors"
    title="View receipt"
    type="button"
    data-action="open-receipt"
    data-receipt='<?php echo htmlspecialchars(json_encode($receiptPayload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>'
>
<span class="material-symbols-outlined text-sm">receipt_long</span>
</button>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
<p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing <?php echo number_format(count($recentPayments)); ?> of <?php echo number_format($summaryTotalPayments); ?> recent entries</p>
<div class="flex gap-2">
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</button>
<button class="w-8 h-8 rounded-lg bg-primary text-white text-xs font-black">1</button>
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</button>
</div>
</div>
</section>
</div>
</main>
<form id="receipt-email-form" method="post" class="hidden">
<input type="hidden" name="action" value="send_receipt_email"/>
<input type="hidden" name="receipt_payment_id" id="receipt_payment_id_input" value=""/>
</form>
<div class="fixed inset-0 z-[70] hidden items-center justify-center p-6" id="receipt-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-modal-title">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" id="receipt-modal-overlay"></div>
<div class="relative z-10 w-full max-w-2xl">
<div class="glass-form bg-white rounded-[2rem] shadow-2xl shadow-primary/20 max-h-[90vh] overflow-hidden flex flex-col">
<div class="px-7 py-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-primary/[0.09] via-white to-sky-500/[0.10]">
<div>
<p class="text-[10px] font-black uppercase tracking-[0.24em] text-primary/80">Transactions</p>
<h3 class="text-xl font-black font-headline text-slate-900 mt-1" id="receipt-modal-title">Payment Receipt</h3>
</div>
<button class="w-9 h-9 rounded-lg bg-white/90 border border-slate-200 hover:bg-slate-100 text-slate-500 inline-flex items-center justify-center transition-colors" id="close-receipt-modal" type="button">
<span class="material-symbols-outlined text-lg">close</span>
</button>
</div>
<div class="px-7 py-6 overflow-y-auto no-scrollbar" id="receipt-modal-body">
<div class="relative border border-slate-200 rounded-3xl p-0 bg-white overflow-hidden shadow-lg shadow-slate-900/5" id="receipt-content">
<div class="absolute top-0 right-0 w-44 h-44 bg-gradient-to-br from-primary/25 to-sky-400/0 blur-2xl pointer-events-none"></div>
<div class="relative px-6 pt-6 pb-5 border-b border-slate-200/80 bg-gradient-to-r from-white via-slate-50/70 to-primary/[0.06]">
<div class="flex items-start gap-4">
<img src="<?php echo htmlspecialchars($clinicLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Clinic Logo" class="w-16 h-16 rounded-2xl object-cover border border-slate-200 bg-white shadow-sm" id="receipt-clinic-logo"/>
<div>
<p class="text-xl font-black text-slate-900 leading-tight" id="receipt-clinic-name"><?php echo htmlspecialchars($clinicDisplayName, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[11px] uppercase tracking-[0.22em] font-black text-slate-500 mt-1">Official Payment Receipt</p>
<p class="text-[11px] text-slate-500 mt-3">Thank you for your payment. Keep this as your billing record.</p>
</div>
</div>
<div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3">
<div class="rounded-2xl bg-white border border-slate-200 px-4 py-3 shadow-sm shadow-slate-900/5">
<p class="text-[10px] uppercase tracking-widest text-slate-500 font-black">Patient</p>
<p class="text-sm font-bold text-slate-900 mt-1" id="receipt-patient-name">-</p>
<p class="text-[11px] text-slate-500 mt-1" id="receipt-patient-meta">ID: -</p>
</div>
<div class="rounded-2xl bg-white border border-slate-200 px-4 py-3 shadow-sm shadow-slate-900/5">
<p class="text-[10px] uppercase tracking-widest text-slate-500 font-black">Transaction Ref</p>
<p class="text-sm font-bold text-slate-900 mt-1 break-all whitespace-normal leading-snug" id="receipt-reference">-</p>
<p class="text-[11px] text-slate-500 mt-1 break-all whitespace-normal leading-snug" id="receipt-payment-id">Payment ID: -</p>
</div>
</div>
</div>
<div class="relative px-6 py-5 bg-white">
<div class="rounded-2xl border border-slate-200 overflow-hidden">
<div class="px-4 py-3 bg-slate-50/90 border-b border-slate-200">
<p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-600">Payment Breakdown</p>
</div>
<div class="px-4 py-4 space-y-3">
<div id="receipt-services-breakdown" class="space-y-2">
<div class="flex items-start justify-between gap-4 text-sm"><span class="font-semibold text-slate-600">Service</span><span class="font-bold text-slate-900 text-right max-w-[60%]">-</span></div>
</div>
<div class="flex items-center justify-between text-sm border-t border-slate-200 pt-2"><span class="font-extrabold text-primary">Total</span><span class="font-extrabold text-primary text-right" id="receipt-services-total">₱0.00</span></div>
<div class="flex items-center justify-between text-sm"><span class="font-semibold text-slate-600">Payment Date</span><span class="font-bold text-slate-900 text-right" id="receipt-payment-date">-</span></div>
<div class="flex items-center justify-between text-sm"><span class="font-semibold text-slate-600">Payment Method</span><span class="font-bold text-slate-900 text-right" id="receipt-payment-method">-</span></div>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
<div class="rounded-2xl border border-primary/20 bg-primary/[0.06] p-4">
<p class="text-[10px] uppercase tracking-[0.2em] text-primary font-black">Amount Paid</p>
<p class="text-2xl font-extrabold text-primary mt-2" id="receipt-amount-paid">₱0.00</p>
</div>
<div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4">
<p class="text-[10px] uppercase tracking-[0.2em] text-amber-700 font-black">Remaining Balance</p>
<p class="text-2xl font-extrabold text-amber-700 mt-2" id="receipt-remaining-balance">₱0.00</p>
</div>
</div>
</div>
<div class="px-6 py-3 border-t border-dashed border-slate-200 bg-slate-50/70">
<p class="text-[11px] font-semibold text-slate-500 text-center">Digitally generated receipt from <?php echo htmlspecialchars($clinicDisplayName, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
</div>
</div>
<div class="px-7 py-5 border-t border-slate-100 bg-white flex items-center justify-end gap-3">
<button type="button" id="receipt-send-email-btn" class="px-5 py-2.5 rounded-xl border border-primary/30 text-primary text-[11px] font-black uppercase tracking-widest hover:bg-primary/10 transition-colors inline-flex items-center gap-2">
<span class="material-symbols-outlined text-base">mail</span> Send to Email
</button>
<button type="button" id="receipt-print-btn" class="px-5 py-2.5 rounded-xl bg-primary text-white text-[11px] font-black uppercase tracking-widest hover:bg-primary/90 transition-colors inline-flex items-center gap-2 shadow-lg shadow-primary/20">
<span class="material-symbols-outlined text-base">print</span> Print Now
</button>
</div>
</div>
</div>
</div>
<div class="fixed inset-0 z-[80] hidden items-center justify-center p-6" id="receipt-email-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="receipt-email-confirm-title">
<div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" id="receipt-email-confirm-overlay"></div>
<div class="relative z-10 w-full max-w-md rounded-2xl bg-white border border-emerald-100 shadow-2xl p-6 text-center">
<h4 id="receipt-email-confirm-title" class="text-lg font-black text-slate-900">Receipt Sent</h4>
<p class="text-sm text-slate-600 mt-3">The receipt has been sent to the patient’s email.</p>
<button type="button" id="receipt-email-confirm-close" class="mt-5 px-5 py-2.5 rounded-xl bg-emerald-500 text-white text-[11px] font-black uppercase tracking-widest hover:bg-emerald-600 transition-colors">Close</button>
</div>
</div>
<div class="fixed inset-0 z-50 hidden items-center justify-center p-6" id="transaction-modal" role="dialog" aria-modal="true" aria-labelledby="transaction-modal-title">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" id="transaction-modal-overlay"></div>
<div class="relative z-10 w-full max-w-4xl">
<div class="glass-form bg-white p-8 rounded-[2.5rem] shadow-2xl shadow-primary/20 max-h-[88vh] overflow-y-auto no-scrollbar">
<?php if ($inlinePaymentError !== ''): ?>
<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 text-red-700 px-5 py-3 text-sm font-semibold">
<?php echo htmlspecialchars($inlinePaymentError, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
<div class="flex justify-between items-start mb-5 border-b border-primary/10 pb-4">
<div>
<h3 class="text-3xl font-black font-headline text-slate-900" id="transaction-modal-title">Record New Payment</h3>
<p class="text-xs text-primary font-bold uppercase tracking-[0.2em] mt-1">Submit digital transaction receipt</p>
</div>
<button class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors" id="close-transaction-modal" type="button">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form class="space-y-6" method="post">
<input type="hidden" name="action" value="record_payment"/>
<div class="space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Patient Identification</label>
<div class="flex gap-2 items-stretch">
<div class="relative group flex-1 min-w-0">
<input name="selected_booking_id" id="selected_booking_id_input" type="hidden" value="<?php echo htmlspecialchars($formSelectedBookingId, ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="selected_transaction_type" id="selected_transaction_type_input" type="hidden" value="<?php echo htmlspecialchars($formSelectedTransactionType, ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="selected_treatment_id" id="selected_treatment_id_input" type="hidden" value="<?php echo htmlspecialchars($formSelectedTreatmentId, ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="patient_query" id="patient_query_input" type="hidden" value="<?php echo htmlspecialchars($formPatientQuery, ENT_QUOTES, 'UTF-8'); ?>"/>
<button id="open-transaction-selector-modal" type="button" class="group w-full min-h-[3.25rem] px-5 py-3.5 rounded-2xl text-left text-base font-bold outline-none inline-flex items-center justify-between gap-3 border-2 border-primary/35 bg-gradient-to-br from-primary/[0.12] via-white to-sky-500/[0.08] text-slate-800 shadow-md shadow-primary/10 hover:border-primary hover:from-primary/[0.18] hover:shadow-lg hover:shadow-primary/15 focus-visible:ring-4 focus-visible:ring-primary/25 focus-visible:border-primary transition-all active:scale-[0.99]" aria-label="Select patient appointment">
<span class="inline-flex items-center gap-3 min-w-0">
<span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-inner shadow-black/10 group-hover:bg-primary/95">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">person_search</span>
</span>
<span class="min-w-0 flex flex-col gap-0.5">
<span class="text-[10px] font-black uppercase tracking-widest text-primary">Select patient</span>
<span id="selected_transaction_label" class="truncate text-[15px] font-extrabold text-slate-900"><?php echo htmlspecialchars($formPatientQuery !== '' ? $formPatientQuery : 'Tap to choose appointment with pending balance', ENT_QUOTES, 'UTF-8'); ?></span>
</span>
</span>
<span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/80 border border-primary/20 text-primary group-hover:bg-primary group-hover:text-white group-hover:border-primary transition-colors">
<span class="material-symbols-outlined text-xl">keyboard_arrow_down</span>
</span>
</button>
</div>
<button class="hidden shrink-0 w-12 rounded-2xl border-2 border-slate-200 bg-slate-50 text-slate-500 hover:border-red-200 hover:bg-red-50 hover:text-red-600 transition-colors inline-flex items-center justify-center" id="clear-selected-booking-btn" type="button" title="Clear appointment selection" aria-label="Clear appointment selection">
<span class="material-symbols-outlined text-[22px]">close</span>
</button>
</div>
<div class="hidden rounded-2xl border border-slate-200 bg-slate-50/90 p-4 space-y-2" id="selected-appointment-detail-panel">
<p class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-0.5">Booked services (this appointment)</p>
<ul class="text-sm font-semibold text-slate-800 space-y-1.5 list-none pl-0" id="selected-appointment-services-list"></ul>
<p class="text-xs font-semibold text-slate-600 leading-relaxed hidden" id="selected-appointment-service-summary"></p>
</div>
<p class="text-[11px] font-semibold text-slate-500 ml-1">Only appointments with pending balance are listed.</p>
</div>
<input name="installment_flow" id="installment_flow_input" type="hidden" value="<?php echo htmlspecialchars($formInstallmentFlow !== '' ? $formInstallmentFlow : 'regular', ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="installment_pay_mode" id="installment_pay_mode_input" type="hidden" value="<?php echo htmlspecialchars($formInstallmentPayMode !== '' ? $formInstallmentPayMode : 'full', ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="installment_slot_count" id="installment_slot_count_input" type="hidden" value="<?php echo (int) max(1, $formInstallmentSlotCount); ?>"/>
<div class="hidden rounded-2xl border border-primary/25 bg-gradient-to-br from-primary/[0.06] to-slate-50/80 p-6 space-y-4" id="record-payment-status-panel">
<div class="flex items-center justify-between gap-3 flex-wrap">
<div>
<p class="text-[11px] font-black uppercase tracking-widest text-primary">Payment status</p>
<h4 class="text-lg font-black text-slate-900 mt-1">Progress</h4>
</div>
<span class="text-xs font-bold text-slate-500" id="installment_progress_pct_label"></span>
</div>
<div class="space-y-2">
<div class="h-3 rounded-full bg-slate-200/90 overflow-hidden shadow-inner">
<div class="h-full rounded-full bg-gradient-to-r from-primary to-sky-400 transition-all duration-500 ease-out" id="installment_progress_bar" style="width:0%"></div>
</div>
<div class="flex justify-between text-[11px] font-bold text-slate-600">
<span id="installment_progress_paid_line"></span>
<span id="installment_progress_remain_line"></span>
</div>
<p class="text-[11px] font-semibold text-slate-500" id="installment_progress_hint"></p>
</div>
<p class="hidden rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-xs font-semibold text-amber-900 leading-relaxed" id="installment-flag-only-note">
This booking is installment-priced, but no installment schedule rows exist in the database yet. Record payments against the treatment balance below (same as a standard balance payment). When schedule lines are added, detailed down/monthly options will appear here.
</p>
<div class="hidden border-t border-slate-200/80 pt-4 space-y-3" id="installment-advanced-options">
<p class="text-[11px] font-black uppercase tracking-widest text-slate-500">Payment option</p>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
<label class="installment-option-card flex items-start gap-3 p-4 rounded-2xl border-2 border-slate-100 bg-white/80 cursor-pointer hover:border-primary/40 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5">
<input type="radio" name="installment_pay_mode_ui" value="full" class="mt-1 text-primary focus:ring-primary/30" id="inst_opt_full"/>
<span class="min-w-0"><span class="block text-sm font-extrabold text-slate-900">Full payment</span><span class="block text-xs text-slate-500 mt-0.5">Pay all remaining installments now.</span></span>
</label>
<label class="installment-option-card flex items-start gap-3 p-4 rounded-2xl border-2 border-slate-100 bg-white/80 cursor-pointer hover:border-primary/40 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5" id="inst_opt_down_wrap">
<input type="radio" name="installment_pay_mode_ui" value="down" class="mt-1 text-primary focus:ring-primary/30" id="inst_opt_down"/>
<span class="min-w-0"><span class="block text-sm font-extrabold text-slate-900">Down payment</span><span class="block text-xs text-slate-500 mt-0.5">Pay only the required down (installment 1).</span></span>
</label>
<label class="installment-option-card flex items-start gap-3 p-4 rounded-2xl border-2 border-slate-100 bg-white/80 cursor-pointer hover:border-primary/40 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5" id="inst_opt_combined_wrap">
<input type="radio" name="installment_pay_mode_ui" value="combined" class="mt-1 text-primary focus:ring-primary/30" id="inst_opt_combined"/>
<span class="min-w-0"><span class="block text-sm font-extrabold text-slate-900">Down + months ahead</span><span class="block text-xs text-slate-500 mt-0.5">Pay down payment plus month 1 or more months ahead.</span></span>
</label>
<label class="installment-option-card flex items-start gap-3 p-4 rounded-2xl border-2 border-slate-100 bg-white/80 cursor-pointer hover:border-primary/40 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5" id="inst_opt_monthly_wrap">
<input type="radio" name="installment_pay_mode_ui" value="monthly" class="mt-1 text-primary focus:ring-primary/30" id="inst_opt_monthly"/>
<span class="min-w-0"><span class="block text-sm font-extrabold text-slate-900">Monthly payment</span><span class="block text-xs text-slate-500 mt-0.5">Pay one or more upcoming monthly installments (after down is paid).</span></span>
</label>
</div>
<div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-1" id="installment_slot_row">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 sm:min-w-[10rem]" id="installment_slot_label">Installments to pay</label>
<div class="flex items-center gap-2">
<input type="number" min="1" step="1" id="installment_slot_stepper" class="w-24 px-3 py-2 rounded-xl border border-slate-200 text-sm font-bold text-slate-800 bg-white"/>
<span class="text-xs font-semibold text-slate-500" id="installment_slot_range_hint"></span>
</div>
</div>
</div>
</div>
<div class="flex flex-col md:flex-row gap-8 items-center">
<div class="flex-1 w-full space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Payment Amount</label>
<div class="relative group">
<span class="absolute left-5 top-1/2 -translate-y-1/2 text-lg font-extrabold text-slate-500 group-focus-within:text-primary transition-colors">₱</span>
<input class="w-full pl-12 pr-6 py-4 rounded-2xl text-xl font-black outline-none border-2 border-transparent bg-slate-100/90 text-slate-800 cursor-default tabular-nums" min="0.01" name="amount" placeholder="0.00" readonly="readonly" required step="0.01" type="number" value="<?php echo htmlspecialchars($formAmount, ENT_QUOTES, 'UTF-8'); ?>" title="Amount is set from the appointment and installment rules" aria-readonly="true"/>
</div>
</div>
<div class="hidden md:block h-12 w-px bg-slate-200 mt-6"></div>
<div class="flex-1 w-full space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Transaction Date</label>
<div class="relative group">
<input class="w-full px-6 py-4 form-input-styled rounded-2xl text-base font-semibold outline-none cursor-default bg-slate-100/90" max="<?php echo date('Y-m-d'); ?>" name="payment_date" required type="date" value="<?php echo htmlspecialchars($formPaymentDate, ENT_QUOTES, 'UTF-8'); ?>" readonly aria-readonly="true" title="Transaction date is set automatically to today"/>
</div>
</div>
</div>
<div class="hidden space-y-4" id="additional-services-section">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Additional Services (Regular Add-ons)</label>
<div class="rounded-2xl border border-slate-200 bg-white/70 p-4 space-y-3">
<p class="text-[11px] font-semibold text-slate-500">Choose only Regular Services here for same-visit add-ons. Installment Services are for ongoing treatment plans and are not billable as add-ons during an active installment treatment.</p>
<p class="text-[11px] font-semibold text-slate-500 hidden" id="additional_services_installment_note">Active installment treatment detected: Installment Services are locked. You may only select Regular Services add-ons.</p>
<div class="max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/40 p-2 space-y-2">
<?php if (empty($availableServices)): ?>
<p class="px-2 py-2 text-sm font-semibold text-slate-500">No active services available.</p>
<?php else: ?>
<?php
    $installmentServices = [];
    $regularServices = [];
    foreach ($availableServices as $service) {
        $normalizedServiceType = strtolower(trim((string) ($service['normalized_service_type'] ?? 'regular')));
        if ($normalizedServiceType !== 'installment') {
            $normalizedServiceType = 'regular';
            $regularServices[] = $service;
            continue;
        }
        $installmentServices[] = $service;
    }
?>
<div class="rounded-lg border border-rose-100 bg-rose-50/70 p-2.5 space-y-1.5" id="additional_services_installment_group">
<div class="flex items-center justify-between gap-3 px-1">
<p class="text-[11px] font-black uppercase tracking-widest text-rose-700">Installment Services</p>
<span class="text-[10px] font-bold text-rose-600 hidden" id="additional_services_installment_locked_badge">Locked for active installment treatment</span>
</div>
<p class="px-1 text-[11px] font-semibold text-rose-700/90">For ongoing treatment plans only.</p>
<?php if (empty($installmentServices)): ?>
<p class="px-2 py-1 text-xs font-semibold text-rose-600/80">No installment services in catalog.</p>
<?php else: ?>
<?php foreach ($installmentServices as $service): ?>
<?php
    $serviceId = trim((string) ($service['service_id'] ?? ''));
    if ($serviceId === '') {
        continue;
    }
    $serviceName = (string) ($service['service_name'] ?? 'Service');
    $serviceCategory = (string) ($service['category'] ?? 'General');
    $servicePrice = (float) ($service['price'] ?? 0);
    $isChecked = in_array($serviceId, $formServiceIds, true);
?>
<label class="additional-service-option flex items-center justify-between gap-3 p-2.5 rounded-lg border border-transparent hover:border-rose-200">
<span class="flex items-start gap-3 min-w-0">
<input class="additional-service-checkbox mt-1 rounded border-slate-300 text-primary focus:ring-primary/30" data-service-price="<?php echo htmlspecialchars((string) $servicePrice, ENT_QUOTES, 'UTF-8'); ?>" data-service-category="<?php echo htmlspecialchars($serviceCategory, ENT_QUOTES, 'UTF-8'); ?>" data-service-type="installment" name="additional_service_ids[]" type="checkbox" value="<?php echo htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isChecked ? ' checked' : ''; ?>/>
<span class="min-w-0">
<span class="block text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="block text-xs text-slate-500"><?php echo htmlspecialchars($serviceCategory, ENT_QUOTES, 'UTF-8'); ?></span>
</span>
</span>
<span class="text-sm font-black text-rose-700">₱<?php echo htmlspecialchars(number_format($servicePrice, 2), ENT_QUOTES, 'UTF-8'); ?></span>
</label>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="rounded-lg border border-emerald-100 bg-emerald-50/70 p-2.5 space-y-1.5" id="additional_services_regular_group">
<p class="px-1 text-[11px] font-black uppercase tracking-widest text-emerald-700">Regular Services</p>
<p class="px-1 text-[11px] font-semibold text-emerald-700/90">Selectable add-ons for this payment.</p>
<?php if (empty($regularServices)): ?>
<p class="px-2 py-1 text-xs font-semibold text-emerald-700/80">No regular services in catalog.</p>
<?php else: ?>
<?php foreach ($regularServices as $service): ?>
<?php
    $serviceId = trim((string) ($service['service_id'] ?? ''));
    if ($serviceId === '') {
        continue;
    }
    $serviceName = (string) ($service['service_name'] ?? 'Service');
    $serviceCategory = (string) ($service['category'] ?? 'General');
    $servicePrice = (float) ($service['price'] ?? 0);
    $isChecked = in_array($serviceId, $formServiceIds, true);
?>
<label class="additional-service-option flex items-center justify-between gap-3 p-2.5 rounded-lg border border-transparent hover:border-emerald-200 hover:bg-white">
<span class="flex items-start gap-3 min-w-0">
<input class="additional-service-checkbox mt-1 rounded border-slate-300 text-primary focus:ring-primary/30" data-service-price="<?php echo htmlspecialchars((string) $servicePrice, ENT_QUOTES, 'UTF-8'); ?>" data-service-category="<?php echo htmlspecialchars($serviceCategory, ENT_QUOTES, 'UTF-8'); ?>" data-service-type="regular" name="additional_service_ids[]" type="checkbox" value="<?php echo htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isChecked ? ' checked' : ''; ?>/>
<span class="min-w-0">
<span class="block text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="block text-xs text-slate-500"><?php echo htmlspecialchars($serviceCategory, ENT_QUOTES, 'UTF-8'); ?></span>
</span>
</span>
<span class="text-sm font-black text-emerald-700">₱<?php echo htmlspecialchars(number_format($servicePrice, 2), ENT_QUOTES, 'UTF-8'); ?></span>
</label>
<?php endforeach; ?>
<?php endif; ?>
</div>
<?php endif; ?>
</div>
<p id="additional_services_total_hint" class="text-xs font-bold text-slate-600">Selected regular services total: ₱0.00</p>
</div>
</div>
<div class="space-y-4">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Payment Method</label>
<input id="payment_method_input" name="payment_method" type="hidden" value="<?php echo htmlspecialchars($selectedMethodForUi, ENT_QUOTES, 'UTF-8'); ?>"/>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn <?php echo $selectedMethodForUi === 'gcash' ? 'active' : ''; ?>" data-method="gcash" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">account_balance_wallet</span>
<span class="text-[11px] font-black uppercase tracking-widest">GCash</span>
</button>
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 <?php echo $selectedMethodForUi === 'cash' ? 'active' : ''; ?>" data-method="cash" type="button">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">payments</span>
<span class="text-[11px] font-black uppercase tracking-widest">Cash</span>
</button>
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn <?php echo $selectedMethodForUi === 'bank_transfer' ? 'active' : ''; ?>" data-method="bank_transfer" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">account_balance</span>
<span class="text-[11px] font-black uppercase tracking-widest">Bank</span>
</button>
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn <?php echo $selectedMethodForUi === 'credit_card' ? 'active' : ''; ?>" data-method="credit_card" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">credit_card</span>
<span class="text-[11px] font-black uppercase tracking-widest">Card</span>
</button>
</div>
</div>
<div class="space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Additional Notes</label>
<textarea class="w-full px-6 py-4 form-input-styled rounded-2xl text-sm font-medium outline-none resize-none" name="notes" placeholder="Describe the treatment or specific billing details..." rows="3"><?php echo htmlspecialchars($formNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>
<div class="pt-4">
<button class="w-full py-5 bg-primary text-white font-black text-sm uppercase tracking-[0.3em] rounded-2xl shadow-2xl shadow-primary/40 hover:shadow-primary/60 hover:-translate-y-1 active:translate-y-0 active:scale-[0.99] transition-all flex items-center justify-center gap-4 relative overflow-hidden group" type="submit">
<span class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></span>
<span class="material-symbols-outlined text-2xl relative" style="font-variation-settings: 'FILL' 1;">verified</span>
<span class="relative">Confirm &amp; Post Payment</span>
</button>
</div>
</form>
</div>
</div>
</div>
<div class="fixed inset-0 z-[90] hidden items-center justify-center p-6" id="payment-validation-modal" role="dialog" aria-modal="true" aria-labelledby="payment-validation-title">
<div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" id="payment-validation-overlay"></div>
<div class="relative z-10 w-full max-w-md rounded-2xl bg-white border border-rose-100 shadow-2xl p-6 text-center">
<h4 id="payment-validation-title" class="text-lg font-black text-slate-900">Validation Required</h4>
<p class="text-sm text-slate-600 mt-3" id="payment-validation-message">Please review your input.</p>
<button type="button" id="payment-validation-close" class="mt-5 px-5 py-2.5 rounded-xl bg-primary text-white text-[11px] font-black uppercase tracking-widest hover:bg-primary/90 transition-colors">OK</button>
</div>
</div>
<div class="fixed inset-0 z-[60] hidden items-center justify-center p-6" id="transaction-selector-modal" role="dialog" aria-modal="true" aria-labelledby="transaction-selector-title">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" id="transaction-selector-overlay"></div>
<div class="relative z-10 w-full max-w-5xl">
<div class="bg-white p-6 rounded-3xl shadow-2xl border border-slate-200 max-h-[88vh] overflow-hidden flex flex-col">
<div class="flex items-center justify-between gap-4 pb-4 border-b border-slate-100">
<div>
<h3 class="text-xl font-black text-slate-900" id="transaction-selector-title">Select Pending Transaction</h3>
<p class="text-[11px] text-slate-500 font-bold uppercase tracking-widest mt-1">Appointments with unpaid balance</p>
</div>
<button class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 inline-flex items-center justify-center" id="close-transaction-selector-modal" type="button">
<span class="material-symbols-outlined text-[18px]">close</span>
</button>
</div>
<div class="pb-4 border-b border-slate-100">
<div class="txn-type-toggle-track mb-4" data-active="regular" id="transaction-type-toggle" role="group" aria-label="Filter by payment type">
<span class="txn-type-toggle-pill" aria-hidden="true"></span>
<button type="button" class="txn-type-toggle-btn" data-txn-type="regular" id="transaction-type-regular-btn" aria-pressed="true">Regular Services</button>
<button type="button" class="txn-type-toggle-btn" data-txn-type="installment" id="transaction-type-installment-btn" aria-pressed="false">Installment Plans</button>
</div>
<div class="py-1">
<input id="transaction_selector_search" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium outline-none focus:border-primary focus:bg-white focus:shadow-[0_0_0_4px_rgba(43,139,235,0.1)]" placeholder="Search patient name, patient ID, booking ID, or service"/>
</div>
</div>
<div id="transaction_selector_list" class="overflow-y-auto divide-y divide-slate-100 min-h-[14rem]"></div>
<div id="transaction_selector_empty" class="hidden py-10 text-center text-sm font-semibold text-slate-500">No pending transactions found.</div>
</div>
</div>
</div>
<script>
    (function () {
        const modal = document.getElementById('transaction-modal');
        const openBtn = document.getElementById('open-transaction-modal');
        const closeBtn = document.getElementById('close-transaction-modal');
        const overlay = document.getElementById('transaction-modal-overlay');
        const hasServerError = <?php echo $inlinePaymentError !== '' ? 'true' : 'false'; ?>;
        const serverValidationPopupMessage = <?php echo json_encode($serverValidationPopupMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const receiptModal = document.getElementById('receipt-modal');
        const receiptModalOverlay = document.getElementById('receipt-modal-overlay');
        const closeReceiptModalBtn = document.getElementById('close-receipt-modal');
        const receiptPrintBtn = document.getElementById('receipt-print-btn');
        const receiptSendEmailBtn = document.getElementById('receipt-send-email-btn');
        const receiptEmailForm = document.getElementById('receipt-email-form');
        const receiptPaymentIdInput = document.getElementById('receipt_payment_id_input');
        const receiptEmailConfirmModal = document.getElementById('receipt-email-confirm-modal');
        const receiptEmailConfirmOverlay = document.getElementById('receipt-email-confirm-overlay');
        const receiptEmailConfirmClose = document.getElementById('receipt-email-confirm-close');
        let activeReceiptData = null;
        let isSendingReceiptEmail = false;
        const transactionCandidates = <?php echo json_encode($transactionCandidates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const transactionDebugRows = <?php echo json_encode($transactionDebugRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const openSelectorBtn = document.getElementById('open-transaction-selector-modal');
        const selectorModal = document.getElementById('transaction-selector-modal');
        const selectorOverlay = document.getElementById('transaction-selector-overlay');
        const closeSelectorBtn = document.getElementById('close-transaction-selector-modal');
        const selectorSearchInput = document.getElementById('transaction_selector_search');
        const selectorList = document.getElementById('transaction_selector_list');
        const selectorEmpty = document.getElementById('transaction_selector_empty');
        const transactionTypeToggle = document.getElementById('transaction-type-toggle');
        const transactionTypeRegularBtn = document.getElementById('transaction-type-regular-btn');
        const transactionTypeInstallmentBtn = document.getElementById('transaction-type-installment-btn');
        let transactionTypeFilter = 'regular';
        const selectedBookingIdInput = document.getElementById('selected_booking_id_input');
        const selectedTransactionTypeInput = document.getElementById('selected_transaction_type_input');
        const selectedTreatmentIdInput = document.getElementById('selected_treatment_id_input');
        const patientQueryInput = document.getElementById('patient_query_input');
        const selectedTransactionLabel = document.getElementById('selected_transaction_label');
        const transactionForm = modal ? modal.querySelector('form[method="post"]') : null;
        const amountInput = document.querySelector('input[name="amount"]');
        const additionalServiceCheckboxes = document.querySelectorAll('.additional-service-checkbox');
        const additionalServicesTotalHint = document.getElementById('additional_services_total_hint');
        const recordPaymentStatusPanel = document.getElementById('record-payment-status-panel');
        const installmentAdvancedOptions = document.getElementById('installment-advanced-options');
        const installmentFlagOnlyNote = document.getElementById('installment-flag-only-note');
        const installmentFlowInput = document.getElementById('installment_flow_input');
        const installmentPayModeInput = document.getElementById('installment_pay_mode_input');
        const installmentSlotCountInput = document.getElementById('installment_slot_count_input');
        const installmentProgressBar = document.getElementById('installment_progress_bar');
        const installmentProgressPctLabel = document.getElementById('installment_progress_pct_label');
        const installmentProgressPaidLine = document.getElementById('installment_progress_paid_line');
        const installmentProgressRemainLine = document.getElementById('installment_progress_remain_line');
        const installmentProgressHint = document.getElementById('installment_progress_hint');
        const installmentSlotRow = document.getElementById('installment_slot_row');
        const installmentSlotLabel = document.getElementById('installment_slot_label');
        const installmentSlotStepper = document.getElementById('installment_slot_stepper');
        const installmentSlotRangeHint = document.getElementById('installment_slot_range_hint');
        const instOptFull = document.getElementById('inst_opt_full');
        const instOptDown = document.getElementById('inst_opt_down');
        const instOptCombined = document.getElementById('inst_opt_combined');
        const instOptMonthly = document.getElementById('inst_opt_monthly');
        const instOptDownWrap = document.getElementById('inst_opt_down_wrap');
        const instOptCombinedWrap = document.getElementById('inst_opt_combined_wrap');
        const instOptMonthlyWrap = document.getElementById('inst_opt_monthly_wrap');
        const additionalServicesSection = document.getElementById('additional-services-section');
        const additionalServicesInstallmentNote = document.getElementById('additional_services_installment_note');
        const additionalServicesInstallmentLockedBadge = document.getElementById('additional_services_installment_locked_badge');
        const additionalServicesInstallmentGroup = document.getElementById('additional_services_installment_group');
        const clearBookingBtn = document.getElementById('clear-selected-booking-btn');
        const selectedAppointmentDetailPanel = document.getElementById('selected-appointment-detail-panel');
        const selectedAppointmentServicesList = document.getElementById('selected-appointment-services-list');
        const selectedAppointmentServiceSummary = document.getElementById('selected-appointment-service-summary');
        const paymentValidationModal = document.getElementById('payment-validation-modal');
        const paymentValidationOverlay = document.getElementById('payment-validation-overlay');
        const paymentValidationClose = document.getElementById('payment-validation-close');
        const paymentValidationMessage = document.getElementById('payment-validation-message');
        const paymentMethodInput = document.getElementById('payment_method_input');
        const paymentMethodCards = document.querySelectorAll('.payment-card[data-method]');
        const defaultPickerLabel = 'Tap to choose appointment with pending balance';
        let selectedTransaction = null;

        function getTodayYmd() {
            const d = new Date();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return String(d.getFullYear()) + '-' + m + '-' + day;
        }

        function resetRecordPaymentForm() {
            closeSelectorModal();
            selectedTransaction = null;
            if (selectedBookingIdInput) {
                selectedBookingIdInput.value = '';
            }
            if (selectedTreatmentIdInput) {
                selectedTreatmentIdInput.value = '';
            }
            if (selectedTransactionTypeInput) {
                selectedTransactionTypeInput.value = 'regular';
            }
            if (patientQueryInput) {
                patientQueryInput.value = '';
            }
            if (selectedTransactionLabel) {
                selectedTransactionLabel.textContent = defaultPickerLabel;
            }
            additionalServiceCheckboxes.forEach((checkbox) => {
                checkbox.checked = false;
                checkbox.disabled = false;
                checkbox.removeAttribute('aria-disabled');
                const label = checkbox.closest('label.additional-service-option');
                if (label) {
                    label.classList.remove('opacity-50', 'cursor-not-allowed');
                    label.removeAttribute('title');
                }
            });
            if (additionalServicesInstallmentLockedBadge) {
                additionalServicesInstallmentLockedBadge.classList.add('hidden');
            }
            if (additionalServicesInstallmentNote) {
                additionalServicesInstallmentNote.classList.add('hidden');
            }
            if (additionalServicesInstallmentGroup) {
                additionalServicesInstallmentGroup.classList.remove('opacity-70');
            }
            if (modal) {
                const payDate = modal.querySelector('input[name="payment_date"]');
                if (payDate) {
                    payDate.value = getTodayYmd();
                }
                const notesEl = modal.querySelector('textarea[name="notes"]');
                if (notesEl) {
                    notesEl.value = '';
                }
            }
            if (amountInput) {
                amountInput.value = '0.00';
                amountInput.setAttribute('readonly', 'readonly');
            }
            if (installmentFlowInput) {
                installmentFlowInput.value = 'regular';
            }
            if (installmentPayModeInput) {
                installmentPayModeInput.value = 'full';
            }
            if (installmentSlotCountInput) {
                installmentSlotCountInput.value = '1';
            }
            if (instOptFull) {
                instOptFull.checked = true;
            }
            if (instOptDown) {
                instOptDown.checked = false;
                instOptDown.disabled = false;
            }
            if (instOptCombined) {
                instOptCombined.checked = false;
                instOptCombined.disabled = false;
            }
            if (instOptMonthly) {
                instOptMonthly.checked = false;
                instOptMonthly.disabled = false;
            }
            if (instOptDownWrap) {
                instOptDownWrap.classList.remove('opacity-40');
            }
            if (instOptCombinedWrap) {
                instOptCombinedWrap.classList.remove('opacity-40');
            }
            if (instOptMonthlyWrap) {
                instOptMonthlyWrap.classList.remove('opacity-40');
            }
            if (installmentSlotStepper) {
                installmentSlotStepper.value = '';
            }
            transactionTypeFilter = 'regular';
            syncMainAndSelectorFilter('regular');
            const methodHidden = document.getElementById('payment_method_input');
            if (methodHidden) {
                methodHidden.value = '';
            }
            document.querySelectorAll('#transaction-modal .payment-card[data-method]').forEach((card) => {
                card.classList.remove('active');
            });
            renderSelectedAppointmentServices(null);
            updateClearBookingButtonVisibility();
            updateAdditionalServicesVisibility();
            refreshInstallmentPaymentUi();
            syncAmountWithAdditionalServices();
        }

        function installmentStatusPaid(status) {
            const s = String(status || '').toLowerCase();
            return s === 'paid' || s === 'completed';
        }

        function getScheduleList(tx) {
            const raw = tx && tx.installment_schedule;
            if (!raw || !Array.isArray(raw)) {
                return [];
            }
            return raw;
        }

        function isInstallmentPlanBooking(tx) {
            if (!tx) {
                return false;
            }
            const v = tx.is_installment_plan;
            return v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true';
        }

        function hasActiveInstallmentTreatment(tx) {
            if (!tx) {
                return false;
            }
            const txType = String(tx.transaction_type || '').toLowerCase().trim();
            const treatmentId = String(tx.treatment_id || '').trim();
            return txType === 'installment' || treatmentId !== '' || isInstallmentPlanBooking(tx);
        }

        function getAdditionalServiceType(checkbox) {
            if (!checkbox) {
                return 'regular';
            }
            const rawType = String(checkbox.getAttribute('data-service-type') || '').toLowerCase().trim();
            return rawType === 'installment' ? 'installment' : 'regular';
        }

        function updateClearBookingButtonVisibility() {
            const hasBooking = !!(selectedBookingIdInput && String(selectedBookingIdInput.value || '').trim() !== '');
            if (clearBookingBtn) {
                clearBookingBtn.classList.toggle('hidden', !hasBooking);
            }
        }

        function updateAdditionalServicesVisibility() {
            if (!additionalServicesSection) {
                return;
            }
            const hasBooking = !!(selectedTransaction && String(selectedTransaction.booking_id || '').trim() !== '');
            additionalServicesSection.classList.toggle('hidden', !hasBooking);
        }

        function renderSelectedAppointmentServices(tx) {
            if (!selectedAppointmentDetailPanel || !selectedAppointmentServicesList || !selectedAppointmentServiceSummary) {
                return;
            }
            if (!tx) {
                selectedAppointmentDetailPanel.classList.add('hidden');
                selectedAppointmentServicesList.innerHTML = '';
                selectedAppointmentServiceSummary.textContent = '';
                selectedAppointmentServiceSummary.classList.add('hidden');
                return;
            }
            const booked = Array.isArray(tx.booked_services) ? tx.booked_services : [];
            if (booked.length) {
                selectedAppointmentServicesList.innerHTML = booked.map((s) => {
                    const name = escapeHtml(s.service_name || 'Service');
                    const pr = Number(s.price || 0).toFixed(2);
                    return '<li class="flex justify-between gap-3 border-b border-slate-200/80 pb-1.5 last:border-0 last:pb-0"><span class="min-w-0">' + name + '</span><span class="shrink-0 text-primary font-black">₱' + pr + '</span></li>';
                }).join('');
                selectedAppointmentServiceSummary.textContent = '';
                selectedAppointmentServiceSummary.classList.add('hidden');
            } else {
                selectedAppointmentServicesList.innerHTML = '';
                const st = escapeHtml(tx.service_type || '-');
                selectedAppointmentServiceSummary.innerHTML = 'Appointment line: <span class="font-bold text-slate-800">' + st + '</span>';
                selectedAppointmentServiceSummary.classList.remove('hidden');
            }
            selectedAppointmentDetailPanel.classList.remove('hidden');
        }

        function updateAdditionalServiceEligibility() {
            const ids = selectedTransaction && Array.isArray(selectedTransaction.booked_service_ids)
                ? selectedTransaction.booked_service_ids
                : [];
            const bookedSet = new Set(ids.map((x) => String(x)));
            const installmentTreatmentActive = hasActiveInstallmentTreatment(selectedTransaction);
            if (additionalServicesInstallmentLockedBadge) {
                additionalServicesInstallmentLockedBadge.classList.toggle('hidden', !installmentTreatmentActive);
            }
            if (additionalServicesInstallmentNote) {
                additionalServicesInstallmentNote.classList.toggle('hidden', !installmentTreatmentActive);
            }
            if (additionalServicesInstallmentGroup) {
                additionalServicesInstallmentGroup.classList.toggle('opacity-70', installmentTreatmentActive);
            }
            additionalServiceCheckboxes.forEach((checkbox) => {
                const label = checkbox.closest('label.additional-service-option');
                const id = String(checkbox.value || '');
                const serviceType = getAdditionalServiceType(checkbox);
                if (bookedSet.has(id)) {
                    checkbox.checked = false;
                    checkbox.disabled = true;
                    checkbox.setAttribute('aria-disabled', 'true');
                    if (label) {
                        label.classList.add('opacity-50', 'cursor-not-allowed');
                        label.setAttribute('title', 'Already on this appointment');
                    }
                } else if (installmentTreatmentActive && serviceType === 'installment') {
                    checkbox.checked = false;
                    checkbox.disabled = true;
                    checkbox.setAttribute('aria-disabled', 'true');
                    if (label) {
                        label.classList.add('opacity-50', 'cursor-not-allowed');
                        label.setAttribute('title', 'Locked while installment treatment is active');
                    }
                } else {
                    checkbox.disabled = false;
                    checkbox.removeAttribute('aria-disabled');
                    if (label) {
                        label.classList.remove('opacity-50', 'cursor-not-allowed');
                        label.removeAttribute('title');
                    }
                }
            });
        }

        function clearSelectedBooking() {
            resetRecordPaymentForm();
        }

        function syncMainAndSelectorFilter(mode) {
            const m = mode === 'installment' ? 'installment' : 'regular';
            transactionTypeFilter = m;
            if (transactionTypeToggle) {
                transactionTypeToggle.setAttribute('data-active', m);
            }
            if (transactionTypeRegularBtn) {
                transactionTypeRegularBtn.setAttribute('aria-pressed', m === 'regular' ? 'true' : 'false');
            }
            if (transactionTypeInstallmentBtn) {
                transactionTypeInstallmentBtn.setAttribute('aria-pressed', m === 'installment' ? 'true' : 'false');
            }
        }

        function refreshInstallmentPaymentUi() {
            if (!selectedTransaction) {
                if (recordPaymentStatusPanel) {
                    recordPaymentStatusPanel.classList.add('hidden');
                }
                if (installmentAdvancedOptions) {
                    installmentAdvancedOptions.classList.add('hidden');
                }
                if (installmentFlagOnlyNote) {
                    installmentFlagOnlyNote.classList.add('hidden');
                }
                if (installmentFlowInput) {
                    installmentFlowInput.value = 'regular';
                }
                if (amountInput) {
                    amountInput.setAttribute('readonly', 'readonly');
                }
                if (instOptDown) {
                    instOptDown.disabled = false;
                }
                if (instOptCombined) {
                    instOptCombined.disabled = false;
                }
                if (instOptMonthly) {
                    instOptMonthly.disabled = false;
                }
                if (instOptDownWrap) {
                    instOptDownWrap.classList.remove('opacity-40');
                }
                if (instOptCombinedWrap) {
                    instOptCombinedWrap.classList.remove('opacity-40');
                }
                if (instOptMonthlyWrap) {
                    instOptMonthlyWrap.classList.remove('opacity-40');
                }
                if (additionalServicesSection) {
                    additionalServicesSection.classList.remove('opacity-50', 'pointer-events-none');
                }
                if (installmentSlotLabel) {
                    installmentSlotLabel.textContent = 'Installments to pay';
                }
                return;
            }
            const sched = getScheduleList(selectedTransaction);
            const hasSchedule = sched.length > 0;
            const planFlag = isInstallmentPlanBooking(selectedTransaction);

            if (recordPaymentStatusPanel) {
                recordPaymentStatusPanel.classList.remove('hidden');
            }

            if (installmentFlowInput) {
                installmentFlowInput.value = hasSchedule ? 'schedule' : 'regular';
            }

            const totalCost = Number(selectedTransaction.total_cost || 0);
            const totalPaid = Number(selectedTransaction.total_paid || 0);
            const pending = Math.max(0, totalCost - totalPaid);
            const pct = totalCost > 0 ? Math.min(100, Math.round((totalPaid / totalCost) * 1000) / 10) : 0;
            if (installmentProgressBar) {
                installmentProgressBar.style.width = pct + '%';
            }
            if (installmentProgressPctLabel) {
                installmentProgressPctLabel.textContent = pct + '% paid';
            }
            if (installmentProgressPaidLine) {
                installmentProgressPaidLine.textContent = 'Paid ₱' + totalPaid.toFixed(2);
            }
            if (installmentProgressRemainLine) {
                installmentProgressRemainLine.textContent = 'Remaining ₱' + pending.toFixed(2);
            }
            if (installmentProgressHint) {
                if (hasSchedule) {
                    const inst1 = sched.find((r) => Number(r.installment_number) === 1);
                    const inst1AmountDue = inst1 ? Number(inst1.amount_due || 0) : 0;
                    const downpaymentCoveredByAmount = !!(inst1 && totalPaid + 0.009 >= inst1AmountDue);
                    const inst1Paid = !!(inst1 && (installmentStatusPaid(inst1.status) || downpaymentCoveredByAmount));
                    let downLabel = '';
                    if (inst1 && !inst1Paid) {
                        downLabel = 'Down payment (installment 1) is pending.';
                    } else if (inst1 && inst1Paid) {
                        downLabel = 'Down payment (installment 1) is paid.';
                    }
                    const unpaidSched = sched.filter((r) => !installmentStatusPaid(r.status));
                    const settled = sched.length - unpaidSched.length;
                    const hasSeparateDownpayment = !!(inst1 && sched.length > 1);
                    const monthlyTotal = hasSeparateDownpayment ? Math.max(0, sched.length - 1) : sched.length;
                    const monthlySettled = hasSeparateDownpayment
                        ? Math.max(0, settled - (inst1Paid ? 1 : 0))
                        : settled;
                    installmentProgressHint.textContent = downLabel
                        ? (downLabel + ' ' + monthlySettled + ' of ' + monthlyTotal + ' monthly installment(s) settled.')
                        : (monthlySettled + ' of ' + monthlyTotal + ' monthly installment(s) settled.');
                } else if (planFlag) {
                    installmentProgressHint.textContent = 'Installment-priced treatment — pay toward the balance below.';
                } else {
                    installmentProgressHint.textContent = 'Amount collected toward this appointment\'s treatment cost.';
                }
            }

            if (hasSchedule) {
                if (installmentAdvancedOptions) {
                    installmentAdvancedOptions.classList.remove('hidden');
                }
                if (installmentFlagOnlyNote) {
                    installmentFlagOnlyNote.classList.add('hidden');
                }
                if (additionalServicesSection) {
                    additionalServicesSection.classList.remove('opacity-50', 'pointer-events-none');
                }
            } else {
                if (installmentAdvancedOptions) {
                    installmentAdvancedOptions.classList.add('hidden');
                }
                if (installmentFlagOnlyNote) {
                    if (planFlag) {
                        installmentFlagOnlyNote.classList.remove('hidden');
                    } else {
                        installmentFlagOnlyNote.classList.add('hidden');
                    }
                }
                if (additionalServicesSection) {
                    additionalServicesSection.classList.remove('opacity-50', 'pointer-events-none');
                }
            }

            if (!hasSchedule) {
                return;
            }

            if (instOptFull && !document.querySelector('input[name="installment_pay_mode_ui"]:checked')) {
                instOptFull.checked = true;
            }

            const inst1 = sched.find((r) => Number(r.installment_number) === 1);
            const inst1AmountDue = inst1 ? Number(inst1.amount_due || 0) : 0;
            const downpaymentCoveredByAmount = !!(inst1 && totalPaid + 0.009 >= inst1AmountDue);
            const inst1Paid = !!(inst1 && (installmentStatusPaid(inst1.status) || downpaymentCoveredByAmount));
            const unpaidSched = sched.filter((r) => {
                if (installmentStatusPaid(r.status)) {
                    return false;
                }
                if (Number(r.installment_number) === 1 && inst1Paid) {
                    return false;
                }
                return true;
            });
            const firstUnpaid = unpaidSched.length ? unpaidSched[0] : null;
            const fn = firstUnpaid ? Number(firstUnpaid.installment_number) : 0;

            const downOk = fn === 1;
            const combinedOk = fn === 1 && unpaidSched.length >= 2;
            const monthlyOk = fn >= 2;
            if (instOptDown) {
                instOptDown.disabled = !downOk;
            }
            if (instOptCombined) {
                instOptCombined.disabled = !combinedOk;
            }
            if (instOptMonthly) {
                instOptMonthly.disabled = !monthlyOk;
            }
            if (instOptDownWrap) {
                instOptDownWrap.classList.toggle('opacity-40', !downOk);
            }
            if (instOptCombinedWrap) {
                instOptCombinedWrap.classList.toggle('opacity-40', !combinedOk);
            }
            if (instOptMonthlyWrap) {
                instOptMonthlyWrap.classList.toggle('opacity-40', !monthlyOk);
            }

            const modeUi = document.querySelector('input[name="installment_pay_mode_ui"]:checked');
            let mode = modeUi ? modeUi.value : 'full';
            if (fn === 1 && mode === 'monthly') {
                mode = 'full';
                if (instOptFull) {
                    instOptFull.checked = true;
                }
            }
            if (fn !== 1 && mode === 'down') {
                mode = unpaidSched.length > 1 ? 'monthly' : 'full';
                if (mode === 'full' && instOptFull) {
                    instOptFull.checked = true;
                }
                if (mode === 'monthly' && instOptMonthly) {
                    instOptMonthly.checked = true;
                }
            }
            if (fn !== 1 && mode === 'combined') {
                mode = 'monthly';
                if (instOptMonthly) {
                    instOptMonthly.checked = true;
                }
            }
            if ((fn !== 1 || unpaidSched.length < 2) && mode === 'combined') {
                mode = 'full';
                if (instOptFull) {
                    instOptFull.checked = true;
                }
            }

            if (installmentPayModeInput) {
                installmentPayModeInput.value = mode;
            }

            let slotCount = 1;
            const maxSlots = unpaidSched.length;
            if (mode === 'full') {
                slotCount = maxSlots;
            } else if (mode === 'down') {
                slotCount = 1;
            } else if (mode === 'combined') {
                const monthsAheadMax = Math.max(1, maxSlots - 1);
                const monthsAheadRaw = parseInt(String(installmentSlotStepper ? installmentSlotStepper.value : '1'), 10) || 1;
                const monthsAhead = Math.min(monthsAheadMax, Math.max(1, monthsAheadRaw));
                slotCount = Math.min(maxSlots, Math.max(2, monthsAhead + 1));
            } else if (mode === 'monthly') {
                slotCount = Math.max(1, parseInt(String(installmentSlotStepper ? installmentSlotStepper.value : '1'), 10) || 1);
                slotCount = Math.min(maxSlots, Math.max(1, slotCount));
            }

            if (installmentSlotCountInput) {
                installmentSlotCountInput.value = String(slotCount);
            }
            if (installmentSlotStepper) {
                if (mode === 'full' || mode === 'down') {
                    installmentSlotStepper.classList.add('hidden');
                    if (installmentSlotRow) {
                        installmentSlotRow.classList.add('hidden');
                    }
                } else {
                    installmentSlotStepper.classList.remove('hidden');
                    if (installmentSlotRow) {
                        installmentSlotRow.classList.remove('hidden');
                    }
                    if (mode === 'combined') {
                        const monthsAheadMax = Math.max(1, maxSlots - 1);
                        installmentSlotStepper.min = '1';
                        installmentSlotStepper.max = String(monthsAheadMax);
                        if (installmentSlotRangeHint) {
                            installmentSlotRangeHint.textContent = '(1 – ' + monthsAheadMax + ' months ahead)';
                        }
                        if (installmentSlotLabel) {
                            installmentSlotLabel.textContent = 'Months ahead to pay';
                        }
                        installmentSlotStepper.value = String(Math.min(monthsAheadMax, Math.max(1, slotCount - 1)));
                    } else {
                        if (installmentSlotLabel) {
                            installmentSlotLabel.textContent = 'Installments to pay';
                        }
                        installmentSlotStepper.min = '1';
                        installmentSlotStepper.max = String(maxSlots);
                        if (installmentSlotRangeHint) {
                            installmentSlotRangeHint.textContent = '(1 – ' + maxSlots + ' installments)';
                        }
                        installmentSlotStepper.value = String(Math.min(maxSlots, Math.max(1, slotCount)));
                    }
                }
            }

            let sum = 0;
            for (let i = 0; i < Math.min(slotCount, unpaidSched.length); i += 1) {
                sum += Number(unpaidSched[i].amount_due || 0);
            }
            sum = Math.round(sum * 100) / 100;
            if (slotCount >= unpaidSched.length && unpaidSched.length > 0) {
                // Avoid cumulative rounding drift when all remaining installments are selected.
                sum = pending;
            }
            if (mode === 'full') {
                // For full payment, display the persisted remaining balance.
                sum = pending;
            }
            if (amountInput) {
                amountInput.value = sum.toFixed(2);
            }
        }

        const normalizeTransactions = transactionCandidates.flatMap((item) => {
            const totalCost = Number(item.total_treatment_cost || 0);
            const totalPaid = Number(item.total_paid || 0);
            const firstName = String(item.patient_first_name || '').trim();
            const lastName = String(item.patient_last_name || '').trim();
            const patientName = (firstName + ' ' + lastName).trim() || 'Unknown Patient';
            const rawPlan = item.is_installment_plan;
            const isInstallmentPlan = rawPlan === true || rawPlan === 1 || rawPlan === '1' || String(rawPlan) === '1';
            const rawTreatmentTotalCost = Number(item.treatment_total_cost);
            const rawTreatmentAmountPaid = Number(item.treatment_amount_paid);
            const rawTreatmentRemainingBalance = Number(item.treatment_remaining_balance);
            const hasTreatmentTotalCost = Number.isFinite(rawTreatmentTotalCost) && rawTreatmentTotalCost >= 0;
            const hasTreatmentAmountPaid = Number.isFinite(rawTreatmentAmountPaid) && rawTreatmentAmountPaid >= 0;
            const hasTreatmentRemainingBalance = Number.isFinite(rawTreatmentRemainingBalance) && rawTreatmentRemainingBalance >= 0;
            const effectiveInstallmentTotalCost = hasTreatmentTotalCost && rawTreatmentTotalCost > 0
                ? rawTreatmentTotalCost
                : totalCost;
            const effectiveInstallmentPaid = hasTreatmentAmountPaid
                ? Math.max(0, Math.min(effectiveInstallmentTotalCost > 0 ? effectiveInstallmentTotalCost : rawTreatmentAmountPaid, rawTreatmentAmountPaid))
                : totalPaid;
            const effectiveInstallmentPending = hasTreatmentRemainingBalance
                ? Math.max(0, rawTreatmentRemainingBalance)
                : Math.max(0, effectiveInstallmentTotalCost - effectiveInstallmentPaid);
            const pendingBalance = isInstallmentPlan
                ? effectiveInstallmentPending
                : Math.max(0, totalCost - totalPaid);
            const treatmentId = String(item.treatment_id || '').trim();
            const recordRef = (isInstallmentPlan && treatmentId !== '')
                ? ('Treatment ' + treatmentId)
                : ('Booking ' + (item.booking_id || '-'));
            const label = patientName + ' | ' + recordRef + ' | Pending ₱' + pendingBalance.toFixed(2);
            const bookedServicesRaw = Array.isArray(item.booked_services) ? item.booked_services : [];
            const booked_service_ids = bookedServicesRaw
                .map((s) => String((s && s.service_id) || '').trim())
                .filter((id) => id !== '');
            const primaryInstallmentServiceId = String(item.primary_installment_service_id || '').trim();
            const bookedInstallment = bookedServicesRaw.filter((s) => {
                let serviceType = String((s && s.service_type) || '').toLowerCase().trim();
                if (!serviceType) {
                    serviceType = 'installment';
                }
                if (serviceType === 'installment') {
                    return true;
                }
                const sid = String((s && s.service_id) || '').trim();
                return primaryInstallmentServiceId !== '' && sid === primaryInstallmentServiceId;
            });
            const bookedRegular = bookedServicesRaw.filter((s) => {
                const sid = String((s && s.service_id) || '').trim();
                let serviceType = String((s && s.service_type) || '').toLowerCase().trim();
                if (!serviceType) {
                    serviceType = 'installment';
                }
                if (serviceType === 'installment') {
                    return false;
                }
                if (primaryInstallmentServiceId !== '' && sid === primaryInstallmentServiceId) {
                    return false;
                }
                return true;
            });
            const regularCostByLines = bookedRegular.reduce((sum, s) => sum + Number((s && s.price) || 0), 0);
            const normalizedServiceType = String(item.service_type || '').toLowerCase().trim();
            const hasInstallmentEntry =
                isInstallmentPlan ||
                bookedInstallment.length > 0 ||
                treatmentId !== '' ||
                normalizedServiceType === 'installment';
            const hasRegularEntry = bookedRegular.length > 0 || !hasInstallmentEntry;
            const installmentScheduleRaw = Array.isArray(item.installment_schedule) ? item.installment_schedule : [];
            const installmentTotalBySchedule = installmentScheduleRaw.reduce((sum, r) => sum + Number((r && r.amount_due) || 0), 0);
            const installmentPaidBySchedule = installmentScheduleRaw.reduce((sum, r) => {
                const s = String((r && r.status) || '').toLowerCase();
                if (s === 'paid' || s === 'completed') {
                    return sum + Number((r && r.amount_due) || 0);
                }
                return sum;
            }, 0);
            const rawRegularPaidAmount = Number(item.regular_paid_amount || 0);
            const rawInstallmentPaidAmount = Number(item.installment_paid_amount || 0);
            const hasExplicitRegularPaid = Number.isFinite(rawRegularPaidAmount) && rawRegularPaidAmount > 0;
            const hasExplicitInstallmentPaid = Number.isFinite(rawInstallmentPaidAmount) && rawInstallmentPaidAmount > 0;
            const installmentPaidResolved = hasTreatmentAmountPaid
                ? effectiveInstallmentPaid
                : (hasExplicitInstallmentPaid
                    ? rawInstallmentPaidAmount
                    : (installmentPaidBySchedule > 0 ? installmentPaidBySchedule : (hasInstallmentEntry ? totalPaid : 0)));
            const baseRow = {
                booking_id: String(item.booking_id || ''),
                appointment_id: Number(item.appointment_id || 0),
                treatment_id: treatmentId,
                patient_id: String(item.patient_id || ''),
                patient_name: patientName,
                service_type: String(item.service_type || '-'),
                visit_type: String(item.visit_type || ''),
                appointment_date: String(item.appointment_date || ''),
                appointment_time: String(item.appointment_time || ''),
                total_cost: totalCost,
                total_paid: totalPaid,
                pending_balance: pendingBalance,
                is_installment_plan: isInstallmentPlan,
                installment_schedule: Array.isArray(item.installment_schedule) ? item.installment_schedule : [],
                primary_installment_service_id: primaryInstallmentServiceId,
                label: label
            };
            const rows = [];
            if (hasRegularEntry) {
                const regularCost = regularCostByLines > 0 ? regularCostByLines : totalCost;
                let regularPaidRaw = hasExplicitRegularPaid ? rawRegularPaidAmount : (totalPaid - installmentPaidResolved);
                // Keep UI aligned with backend: ignore tiny rounding drift in mixed plan splits.
                if (regularPaidRaw > 0 && regularPaidRaw < 0.05) {
                    regularPaidRaw = 0;
                }
                const regularPaid = Math.max(0, Math.min(regularCost, regularPaidRaw));
                rows.push({
                    ...baseRow,
                    transaction_key: baseRow.booking_id + '::' + String(baseRow.appointment_id || 0) + '::regular',
                    transaction_type: 'regular',
                    is_installment_plan: false,
                    installment_schedule: [],
                    total_cost: regularCost,
                    total_paid: regularPaid,
                    pending_balance: Math.max(0, regularCost - regularPaid),
                    booked_services: bookedRegular.length ? bookedRegular : bookedServicesRaw,
                    booked_service_ids: (bookedRegular.length ? bookedRegular : bookedServicesRaw)
                        .map((s) => String((s && s.service_id) || '').trim())
                        .filter((id) => id !== '')
                });
            }
            if (hasInstallmentEntry) {
                const installmentTotal = effectiveInstallmentTotalCost > 0 ? effectiveInstallmentTotalCost : installmentTotalBySchedule;
                const installmentPaid = Math.max(0, Math.min(installmentTotal > 0 ? installmentTotal : installmentPaidResolved, installmentPaidResolved));
                const installmentRemainingBalance = hasTreatmentRemainingBalance
                    ? effectiveInstallmentPending
                    : Math.max(0, installmentTotal - installmentPaid);
                rows.push({
                    ...baseRow,
                    transaction_key: (baseRow.treatment_id ? ('treatment:' + baseRow.treatment_id) : ('booking:' + baseRow.booking_id)) + '::installment',
                    transaction_type: 'installment',
                    is_installment_plan: true,
                    total_cost: installmentTotal,
                    total_paid: installmentPaid,
                    pending_balance: installmentRemainingBalance,
                    treatment_remaining_balance: hasTreatmentRemainingBalance ? effectiveInstallmentPending : null,
                    booked_services: bookedInstallment.length ? bookedInstallment : bookedServicesRaw,
                    booked_service_ids: (bookedInstallment.length ? bookedInstallment : bookedServicesRaw)
                        .map((s) => String((s && s.service_id) || '').trim())
                        .filter((id) => id !== '')
                });
            }
            return rows;
        }).filter((item) => {
            if (item.transaction_type === 'installment') {
                const remaining = Number(item.pending_balance || 0);
                return remaining > 0.009;
            }
            return Number(item.pending_balance || 0) > 0.009;
        });

        if (Array.isArray(transactionDebugRows) && transactionDebugRows.length) {
            console.table(transactionDebugRows.map((row) => ({
                service_type: String(row.service_type || ''),
                appointment_id: String(row.appointment_id || ''),
                treatment_id: String(row.treatment_id || ''),
                booking_id: String(row.booking_id || '')
            })));
        }

        function getRecordTypeMeta(item) {
            const rawType = String(item && item.visit_type ? item.visit_type : '').toLowerCase();
            if (rawType === 'walk_in' || rawType === 'walk-in' || rawType === 'walkin') {
                return {
                    label: 'Walk-in',
                    cls: 'bg-amber-100 text-amber-700 border border-amber-200'
                };
            }
            return {
                label: 'Booking',
                cls: 'bg-sky-100 text-sky-700 border border-sky-200'
            };
        }

        function filterTransactionsByType(list) {
            if (transactionTypeFilter === 'installment') {
                return list.filter((item) => String(item.transaction_type || '') === 'installment');
            }
            return list.filter((item) => String(item.transaction_type || '') === 'regular');
        }

        function filterTransactionsByKeyword(list, keyword) {
            if (!keyword) {
                return list;
            }
            return list.filter((item) => {
                const bookedNames = (item.booked_services || []).map((s) => (s && s.service_name) ? String(s.service_name) : '').join(' ');
                return [
                    item.patient_name,
                    item.patient_id,
                    item.booking_id,
                    item.treatment_id,
                    item.service_type,
                    bookedNames
                ].join(' ').toLowerCase().indexOf(keyword) !== -1;
            });
        }

        function refreshTransactionSelectorList() {
            const keyword = String(selectorSearchInput ? selectorSearchInput.value : '').trim().toLowerCase();
            const typeFiltered = filterTransactionsByType(normalizeTransactions);
            const finalList = filterTransactionsByKeyword(typeFiltered, keyword);
            if (!finalList.length && selectorEmpty) {
                if (!keyword) {
                    selectorEmpty.textContent = transactionTypeFilter === 'installment'
                        ? 'No installment plans with a pending balance.'
                        : 'No regular service appointments with a pending balance.';
                } else {
                    selectorEmpty.textContent = 'No transactions match your search.';
                }
            }
            renderTransactionRows(finalList);
        }

        function setTransactionTypeFilter(mode) {
            syncMainAndSelectorFilter(mode === 'installment' ? 'installment' : 'regular');
            refreshTransactionSelectorList();
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizeClinicalCategory(category) {
            const raw = String(category || '').toLowerCase();
            if (raw.indexOf('crowns') !== -1 && raw.indexOf('bridges') !== -1) return 'crowns_and_bridges';
            if (raw.indexOf('oral') !== -1 && raw.indexOf('surgery') !== -1) return 'oral_surgery';
            if (raw.indexOf('orthodont') !== -1) return 'orthodontics';
            if (raw.indexOf('pediatric') !== -1) return 'pediatric_dentistry';
            if (raw.indexOf('cosmetic') !== -1) return 'cosmetic_dentistry';
            if (raw.indexOf('restorative') !== -1) return 'restorative_dentistry';
            if (raw.indexOf('general') !== -1) return 'general_dentistry';
            if (raw.indexOf('specialized') !== -1 || raw.indexOf('specialised') !== -1) return 'specialized_and_others';
            return '';
        }

        function findBlockedCategoryCombination(categories) {
            const normalized = new Set();
            categories.forEach((category) => {
                const mapped = normalizeClinicalCategory(category);
                if (mapped) normalized.add(mapped);
            });
            const blockedRules = [
                {
                    pair: ['oral_surgery', 'crowns_and_bridges'],
                    message: 'Oral Surgery and Crowns and Bridges cannot be combined because healing must occur first before crown placement.'
                },
                {
                    pair: ['orthodontics', 'crowns_and_bridges'],
                    message: 'Orthodontics and Crowns and Bridges cannot be combined because permanent bridges should not be placed while teeth are still moving.'
                },
                {
                    pair: ['orthodontics', 'cosmetic_dentistry'],
                    message: 'Orthodontics and Cosmetic Dentistry cannot be combined because cosmetic procedures like veneers should be done after alignment is complete.'
                },
                {
                    pair: ['pediatric_dentistry', 'cosmetic_dentistry'],
                    message: 'Pediatric Dentistry and Cosmetic Dentistry cannot be combined because major cosmetic procedures are not appropriate for pediatric patients.'
                }
            ];
            for (let i = 0; i < blockedRules.length; i += 1) {
                const rule = blockedRules[i];
                if (normalized.has(rule.pair[0]) && normalized.has(rule.pair[1])) {
                    return rule.message + ' Please schedule these services separately if needed.';
                }
            }
            return '';
        }

        function validateSelectedAdditionalServices() {
            if (!selectedTransaction) {
                return { valid: true, message: '' };
            }
            if (hasActiveInstallmentTreatment(selectedTransaction)) {
                let hasInstallmentSelection = false;
                additionalServiceCheckboxes.forEach((checkbox) => {
                    if (checkbox.checked && !checkbox.disabled && getAdditionalServiceType(checkbox) === 'installment') {
                        hasInstallmentSelection = true;
                    }
                });
                if (hasInstallmentSelection) {
                    return { valid: false, message: 'This patient has an active installment treatment. Only Regular Services can be selected as Additional Services.' };
                }
            }
            const categories = [];
            const booked = Array.isArray(selectedTransaction.booked_services) ? selectedTransaction.booked_services : [];
            booked.forEach((service) => {
                categories.push(service && service.category ? service.category : '');
            });
            additionalServiceCheckboxes.forEach((checkbox) => {
                if (!checkbox.checked) {
                    return;
                }
                categories.push(checkbox.getAttribute('data-service-category') || '');
            });
            const blockedMessage = findBlockedCategoryCombination(categories);
            if (blockedMessage) {
                return { valid: false, message: blockedMessage };
            }
            return { valid: true, message: '' };
        }

        function renderTransactionRows(list) {
            if (!selectorList || !selectorEmpty) return;
            if (!list.length) {
                selectorList.innerHTML = '';
                selectorEmpty.classList.remove('hidden');
                return;
            }

            selectorEmpty.classList.add('hidden');
            selectorList.innerHTML = list.map((item) => {
                const svcLine = (item.booked_services && item.booked_services.length)
                    ? item.booked_services.map((s) => escapeHtml(s.service_name || 'Service')).join(', ')
                    : escapeHtml(item.service_type);
                const typeMeta = getRecordTypeMeta(item);
                return '' +
                    '<div class="py-3 px-1 sm:px-2">' +
                        '<div class="rounded-2xl border border-slate-200 p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">' +
                            '<div class="min-w-0">' +
                                '<p class="text-sm font-extrabold text-slate-900 truncate">' + escapeHtml(item.patient_name) + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Patient ID: ' + escapeHtml(item.patient_id) + ' | Appointment ID: ' + escapeHtml(item.appointment_id || '-') + ' | ' + (item.is_installment_plan && item.treatment_id ? ('Treatment ID: ' + escapeHtml(item.treatment_id)) : ('Booking ID: ' + escapeHtml(item.booking_id))) + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Services: ' + svcLine + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Date: ' + escapeHtml(item.appointment_date || '-') + ' ' + escapeHtml(item.appointment_time || '') + '</p>' +
                                '<p class="text-xs font-semibold text-slate-700 mt-1">Total: ₱' + item.total_cost.toFixed(2) + ' | Paid: ₱' + item.total_paid.toFixed(2) + ' | Pending: ₱' + item.pending_balance.toFixed(2) + '</p>' +
                            '</div>' +
                            '<div class="shrink-0 flex items-center gap-2">' +
                                '<span class="inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider ' + typeMeta.cls + '">' + escapeHtml(typeMeta.label) + '</span>' +
                                '<button type="button" data-action="select-transaction" data-transaction-key="' + escapeHtml(item.transaction_key || (item.booking_id + "::" + item.transaction_type)) + '" class="px-4 py-2.5 rounded-xl bg-primary text-white text-xs font-black uppercase tracking-widest hover:bg-primary/90 transition-colors">Select</button>' +
                            '</div>' +
                        '</div>' +
                    '</div>';
            }).join('');
        }

        function getAdditionalServicesTotal() {
            let total = 0;
            additionalServiceCheckboxes.forEach((checkbox) => {
                if (!checkbox.checked || checkbox.disabled) {
                    return;
                }
                if (getAdditionalServiceType(checkbox) !== 'regular') {
                    return;
                }
                const servicePrice = Number(checkbox.getAttribute('data-service-price') || 0);
                if (Number.isFinite(servicePrice)) {
                    total += servicePrice;
                }
            });
            return total;
        }

        function syncAmountWithAdditionalServices() {
            const servicesTotal = getAdditionalServicesTotal();
            if (additionalServicesTotalHint) {
                additionalServicesTotalHint.textContent = 'Selected regular services total: ₱' + servicesTotal.toFixed(2);
            }
            if (selectedTransaction && amountInput) {
                const hasSchedule = getScheduleList(selectedTransaction).length > 0;
                if (hasSchedule) {
                    refreshInstallmentPaymentUi();
                }
                const baseAmount = hasSchedule
                    ? Number(amountInput.value || 0)
                    : Number(selectedTransaction.pending_balance || 0);
                amountInput.value = Math.max(0, baseAmount + servicesTotal).toFixed(2);
            }
        }

        function openSelectorModal() {
            if (!selectorModal) return;
            selectorModal.classList.remove('hidden');
            selectorModal.classList.add('flex');
            if (selectorSearchInput) {
                selectorSearchInput.value = '';
            }
            refreshTransactionSelectorList();
        }

        function closeSelectorModal() {
            if (!selectorModal) return;
            selectorModal.classList.add('hidden');
            selectorModal.classList.remove('flex');
        }

        function showValidationPopup(message) {
            if (!paymentValidationModal || !paymentValidationMessage) {
                return;
            }
            paymentValidationMessage.textContent = String(message || 'Please review your input.');
            paymentValidationModal.classList.remove('hidden');
            paymentValidationModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeValidationPopup() {
            if (!paymentValidationModal) {
                return;
            }
            paymentValidationModal.classList.add('hidden');
            paymentValidationModal.classList.remove('flex');
            if (
                (!modal || modal.classList.contains('hidden'))
                && (!receiptModal || receiptModal.classList.contains('hidden'))
                && (!selectorModal || selectorModal.classList.contains('hidden'))
                && (!receiptEmailConfirmModal || receiptEmailConfirmModal.classList.contains('hidden'))
            ) {
                document.body.classList.remove('overflow-hidden');
            }
        }

        const openModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            syncMainAndSelectorFilter(transactionTypeFilter);
        };

        const closeModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
            if (!hasServerError) {
                resetRecordPaymentForm();
            } else {
                closeSelectorModal();
            }
        };

        function formatPeso(value) {
            const amount = Number(value || 0);
            if (!Number.isFinite(amount)) {
                return '₱0.00';
            }
            return '₱' + amount.toFixed(2);
        }

        function formatReceiptDate(value) {
            const dateValue = String(value || '').trim();
            if (!dateValue) {
                return '-';
            }
            const parsed = new Date(dateValue.replace(' ', 'T'));
            if (Number.isNaN(parsed.getTime())) {
                return dateValue;
            }
            return parsed.toLocaleString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true,
                timeZone: 'Asia/Manila'
            });
        }

        function buildReceiptHtmlMarkup(receipt) {
            const clinicLogo = escapeHtml(receipt.clinic_logo || '');
            const clinicName = escapeHtml(receipt.clinic_name || '');
            const patientName = escapeHtml(receipt.patient_name || '-');
            const patientId = escapeHtml(receipt.patient_id || 'N/A');
            const reference = escapeHtml(receipt.reference_number || '-');
            const paymentId = escapeHtml(receipt.payment_id || '-');
            const service = escapeHtml(receipt.service || 'Dental treatment');
            const paymentDate = escapeHtml(formatReceiptDate(receipt.payment_date));
            const paymentMethod = escapeHtml(receipt.payment_method || '-');
            const amountPaid = escapeHtml(formatPeso(receipt.amount_paid));
            const remainingBalance = escapeHtml(formatPeso(receipt.remaining_balance));
            const serviceItems = Array.isArray(receipt.service_items) ? receipt.service_items : [];
            const servicesTotal = escapeHtml(formatPeso(receipt.services_total || 0));
            let serviceRowsMarkup = '';
            if (serviceItems.length) {
                serviceRowsMarkup = serviceItems.map((item) => {
                    const itemName = escapeHtml((item && item.name) ? String(item.name) : 'Service');
                    const itemAmount = escapeHtml(formatPeso(item && item.amount ? item.amount : 0));
                    return '<tr><td style="font-size:15px;line-height:21px;font-weight:600;color:#41547a;padding:0 0 8px;">' + itemName + '</td><td align="right" style="font-size:15px;line-height:21px;font-weight:800;color:#0f172a;padding:0 0 8px;">' + itemAmount + '</td></tr>';
                }).join('');
            } else {
                serviceRowsMarkup = '<tr><td style="font-size:15px;line-height:21px;font-weight:600;color:#41547a;padding:0 0 8px;">Service</td><td align="right" style="font-size:15px;line-height:21px;font-weight:800;color:#0f172a;padding:0 0 8px;">' + service + '</td></tr>';
            }

            return '' +
                '<div style="max-width:760px;margin:0 auto;background:#fff;border:1px solid #dbeafe;border-radius:18px;overflow:hidden;font-family:Arial,sans-serif;color:#0f172a;">' +
                    '<div style="padding:22px 24px;background:#f8fcff;border-bottom:1px solid #dbeafe;">' +
                        '<div style="display:flex;align-items:flex-start;gap:12px;">' +
                            '<img src="' + clinicLogo + '" alt="Clinic Logo" style="width:64px;height:64px;border-radius:14px;border:1px solid #dbeafe;object-fit:cover;background:#fff;">' +
                            '<div>' +
                                '<div style="font-size:24px;line-height:30px;font-weight:800;">' + clinicName + '</div>' +
                                '<div style="margin-top:8px;font-size:13px;line-height:18px;letter-spacing:4px;font-weight:800;color:#647aa5;text-transform:uppercase;">Official Payment Receipt</div>' +
                                '<div style="margin-top:12px;font-size:16px;line-height:22px;color:#60739a;">Thank you for your payment. Keep this as your billing record.</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div style="padding:18px 24px 0;">' +
                        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>' +
                            '<td width="49%" valign="top" style="width:49%;border:1px solid #dbeafe;border-radius:14px;padding:12px 14px;">' +
                                '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;font-weight:800;color:#647aa5;text-transform:uppercase;">Patient</p>' +
                                '<p style="margin:10px 0 0;font-size:18px;line-height:24px;font-weight:800;color:#0f172a;">' + patientName + '</p>' +
                                '<p style="margin:8px 0 0;font-size:14px;line-height:18px;color:#7284a8;">ID ' + patientId + '</p>' +
                            '</td>' +
                            '<td width="2%"></td>' +
                            '<td width="49%" valign="top" style="width:49%;border:1px solid #dbeafe;border-radius:14px;padding:12px 14px;">' +
                                '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;font-weight:800;color:#647aa5;text-transform:uppercase;">Transaction Ref</p>' +
                                '<p style="margin:10px 0 0;font-size:18px;line-height:24px;font-weight:800;color:#0f172a;word-break:break-word;overflow-wrap:anywhere;">' + reference + '</p>' +
                                '<p style="margin:8px 0 0;font-size:14px;line-height:18px;color:#7284a8;word-break:break-word;overflow-wrap:anywhere;">Payment ID ' + paymentId + '</p>' +
                            '</td>' +
                        '</tr></table>' +
                    '</div>' +
                    '<div style="padding:18px 24px 0;">' +
                        '<div style="border:1px solid #dbeafe;border-radius:14px;overflow:hidden;">' +
                            '<div style="padding:12px 14px;background:#f9fcff;border-bottom:1px solid #dbeafe;font-size:13px;line-height:18px;letter-spacing:4px;font-weight:800;color:#4f668f;text-transform:uppercase;">Payment Breakdown</div>' +
                            '<div style="padding:12px 14px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">' +
                                serviceRowsMarkup +
                                '<tr><td style="font-size:16px;line-height:22px;font-weight:800;color:#1e3a8a;padding:2px 0 0;">Total</td><td align="right" style="font-size:16px;line-height:22px;font-weight:900;color:#1e3a8a;padding:2px 0 0;">' + servicesTotal + '</td></tr>' +
                                '<tr><td height="12"></td><td></td></tr>' +
                                '<tr><td style="font-size:16px;line-height:22px;font-weight:600;color:#41547a;">Payment Date</td><td align="right" style="font-size:16px;line-height:22px;font-weight:800;color:#0f172a;">' + paymentDate + '</td></tr>' +
                                '<tr><td height="12"></td><td></td></tr>' +
                                '<tr><td style="font-size:16px;line-height:22px;font-weight:600;color:#41547a;">Payment Method</td><td align="right" style="font-size:16px;line-height:22px;font-weight:800;color:#0f172a;">' + paymentMethod + '</td></tr>' +
                            '</table></div>' +
                        '</div>' +
                    '</div>' +
                    '<div style="padding:18px 24px 24px;">' +
                        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>' +
                            '<td width="49%" valign="top" style="width:49%;border:1px solid #bfdbfe;border-radius:14px;background:#f0f8ff;padding:12px 14px;">' +
                                '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;text-transform:uppercase;font-weight:800;color:#2382ff;">Amount Paid</p>' +
                                '<p style="margin:12px 0 0;font-size:34px;line-height:38px;font-weight:800;color:#2382ff;">' + amountPaid + '</p>' +
                            '</td>' +
                            '<td width="2%"></td>' +
                            '<td width="49%" valign="top" style="width:49%;border:1px solid #fcdca7;border-radius:14px;background:#fffaf0;padding:12px 14px;">' +
                                '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;text-transform:uppercase;font-weight:800;color:#b45309;">Remaining Balance</p>' +
                                '<p style="margin:12px 0 0;font-size:34px;line-height:38px;font-weight:800;color:#b45309;">' + remainingBalance + '</p>' +
                            '</td>' +
                        '</tr></table>' +
                    '</div>' +
                '</div>';
        }

        function setReceiptText(id, value) {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = value;
            }
        }

        function renderReceiptServiceBreakdown(receipt) {
            const container = document.getElementById('receipt-services-breakdown');
            const totalEl = document.getElementById('receipt-services-total');
            if (!container || !totalEl) {
                return;
            }
            const serviceItems = Array.isArray(receipt && receipt.service_items) ? receipt.service_items : [];
            if (!serviceItems.length) {
                const fallbackService = escapeHtml((receipt && receipt.service) ? receipt.service : 'Dental treatment');
                container.innerHTML = '<div class="flex items-start justify-between gap-4 text-sm"><span class="font-semibold text-slate-600">Service</span><span class="font-bold text-slate-900 text-right max-w-[60%] break-words">' + fallbackService + '</span></div>';
                totalEl.textContent = formatPeso((receipt && receipt.services_total) ? receipt.services_total : 0);
                return;
            }
            container.innerHTML = serviceItems.map((item) => {
                const itemName = escapeHtml((item && item.name) ? String(item.name) : 'Service');
                const itemAmount = escapeHtml(formatPeso(item && item.amount ? item.amount : 0));
                return '<div class="flex items-start justify-between gap-4 text-sm"><span class="font-semibold text-slate-600">' + itemName + '</span><span class="font-bold text-slate-900 text-right">' + itemAmount + '</span></div>';
            }).join('');
            totalEl.textContent = formatPeso((receipt && receipt.services_total) ? receipt.services_total : 0);
        }

        function openReceiptModal(receipt) {
            if (!receiptModal || !receipt) {
                return;
            }
            activeReceiptData = receipt;
            setReceiptText('receipt-clinic-name', receipt.clinic_name || '<?php echo htmlspecialchars($clinicDisplayName, ENT_QUOTES, 'UTF-8'); ?>');
            setReceiptText('receipt-patient-name', receipt.patient_name || '-');
            setReceiptText('receipt-patient-meta', 'ID: ' + (receipt.patient_id || 'N/A'));
            setReceiptText('receipt-reference', receipt.reference_number || '-');
            setReceiptText('receipt-payment-id', 'Payment ID: ' + (receipt.payment_id || '-'));
            renderReceiptServiceBreakdown(receipt);
            setReceiptText('receipt-amount-paid', formatPeso(receipt.amount_paid));
            setReceiptText('receipt-remaining-balance', formatPeso(receipt.remaining_balance));
            setReceiptText('receipt-payment-date', formatReceiptDate(receipt.payment_date));
            setReceiptText('receipt-payment-method', receipt.payment_method || '-');
            const logoImg = document.getElementById('receipt-clinic-logo');
            if (logoImg && receipt.clinic_logo) {
                logoImg.src = receipt.clinic_logo;
            }
            receiptModal.classList.remove('hidden');
            receiptModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeReceiptModal() {
            if (!receiptModal) {
                return;
            }
            receiptModal.classList.add('hidden');
            receiptModal.classList.remove('flex');
            if (!modal || modal.classList.contains('hidden')) {
                document.body.classList.remove('overflow-hidden');
            }
        }

        function openReceiptPrintView() {
            if (!activeReceiptData) {
                return;
            }
            const printWin = window.open('', '_blank', 'width=900,height=700');
            if (!printWin) {
                return;
            }
            const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Payment Receipt</title><style>' +
                'body{margin:0;padding:24px;background:#f3f8ff;-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                '@media print{body{padding:0;background:#fff;} .print-wrap{margin:0;max-width:100%;}}' +
                '</style></head><body><div class="print-wrap">' + buildReceiptHtmlMarkup(activeReceiptData) + '</div></body></html>';
            printWin.document.open();
            printWin.document.write(html);
            printWin.document.close();
            printWin.focus();
            printWin.print();
        }

        function closeReceiptEmailConfirmation() {
            if (!receiptEmailConfirmModal) {
                return;
            }
            receiptEmailConfirmModal.classList.add('hidden');
            receiptEmailConfirmModal.classList.remove('flex');
        }

        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        if (overlay) {
            overlay.addEventListener('click', closeModal);
        }
        if (closeReceiptModalBtn) {
            closeReceiptModalBtn.addEventListener('click', closeReceiptModal);
        }
        if (receiptModalOverlay) {
            receiptModalOverlay.addEventListener('click', closeReceiptModal);
        }
        if (receiptPrintBtn) {
            receiptPrintBtn.addEventListener('click', openReceiptPrintView);
        }
        if (receiptSendEmailBtn) {
            receiptSendEmailBtn.addEventListener('click', () => {
                if (!activeReceiptData || !receiptEmailForm || !receiptPaymentIdInput || isSendingReceiptEmail) {
                    return;
                }
                isSendingReceiptEmail = true;
                receiptPaymentIdInput.value = String(activeReceiptData.payment_id || '');
                receiptSendEmailBtn.disabled = true;
                receiptSendEmailBtn.classList.add('opacity-70', 'cursor-not-allowed');
                receiptSendEmailBtn.innerHTML = '<span class="material-symbols-outlined text-base">hourglass_top</span> Sending...';
                receiptEmailForm.submit();
            });
        }
        if (receiptEmailConfirmClose) {
            receiptEmailConfirmClose.addEventListener('click', closeReceiptEmailConfirmation);
        }
        if (receiptEmailConfirmOverlay) {
            receiptEmailConfirmOverlay.addEventListener('click', closeReceiptEmailConfirmation);
        }
        if (paymentValidationClose) {
            paymentValidationClose.addEventListener('click', closeValidationPopup);
        }
        if (paymentValidationOverlay) {
            paymentValidationOverlay.addEventListener('click', closeValidationPopup);
        }
        document.querySelectorAll('button[data-action="open-receipt"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const payload = String(btn.getAttribute('data-receipt') || '');
                if (!payload) {
                    return;
                }
                try {
                    openReceiptModal(JSON.parse(payload));
                } catch (err) {
                }
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (receiptEmailConfirmModal && !receiptEmailConfirmModal.classList.contains('hidden')) {
                    closeReceiptEmailConfirmation();
                    return;
                }
                if (paymentValidationModal && !paymentValidationModal.classList.contains('hidden')) {
                    closeValidationPopup();
                    return;
                }
                if (receiptModal && !receiptModal.classList.contains('hidden')) {
                    closeReceiptModal();
                    return;
                }
                if (selectorModal && !selectorModal.classList.contains('hidden')) {
                    closeSelectorModal();
                    return;
                }
                closeModal();
            }
        });
        const hasReceiptEmailSuccess = <?php echo $receiptEmailSuccess !== '' ? 'true' : 'false'; ?>;
        if (hasReceiptEmailSuccess && receiptEmailConfirmModal) {
            receiptEmailConfirmModal.classList.remove('hidden');
            receiptEmailConfirmModal.classList.add('flex');
            try {
                const u = new URL(window.location.href);
                window.history.replaceState({}, '', u.pathname + u.search + u.hash);
            } catch (e) {
            }
        }
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                resetRecordPaymentForm();
            }
        });
        if (hasServerError) {
            openModal();
        }
        if (serverValidationPopupMessage) {
            openModal();
            showValidationPopup(serverValidationPopupMessage);
        }

        if (openSelectorBtn) {
            openSelectorBtn.addEventListener('click', openSelectorModal);
        }
        if (closeSelectorBtn) {
            closeSelectorBtn.addEventListener('click', closeSelectorModal);
        }
        if (selectorOverlay) {
            selectorOverlay.addEventListener('click', closeSelectorModal);
        }
        if (selectorSearchInput) {
            selectorSearchInput.addEventListener('input', () => {
                refreshTransactionSelectorList();
            });
        }
        if (transactionTypeRegularBtn) {
            transactionTypeRegularBtn.addEventListener('click', () => setTransactionTypeFilter('regular'));
        }
        if (transactionTypeInstallmentBtn) {
            transactionTypeInstallmentBtn.addEventListener('click', () => setTransactionTypeFilter('installment'));
        }
        if (selectorList) {
            selectorList.addEventListener('click', (event) => {
                const btn = event.target.closest('button[data-action="select-transaction"]');
                if (!btn) {
                    return;
                }
                const transactionKey = String(btn.getAttribute('data-transaction-key') || '');
                const selected = normalizeTransactions.find((item) => String(item.transaction_key || '') === transactionKey);
                if (!selected) {
                    return;
                }
                if (selectedBookingIdInput) {
                    selectedBookingIdInput.value = selected.booking_id;
                }
                if (selectedTreatmentIdInput) {
                    selectedTreatmentIdInput.value = String(selected.treatment_id || '').trim();
                }
                if (selectedTransactionTypeInput) {
                    selectedTransactionTypeInput.value = selected.is_installment_plan ? 'installment' : 'regular';
                }
                if (patientQueryInput) {
                    patientQueryInput.value = selected.label;
                }
                if (selectedTransactionLabel) {
                    selectedTransactionLabel.textContent = selected.label;
                }
                selectedTransaction = selected;
                updateAdditionalServicesVisibility();
                if (amountInput) {
                    if (selected.is_installment_plan) {
                        syncMainAndSelectorFilter('installment');
                    } else {
                        syncMainAndSelectorFilter('regular');
                    }
                    refreshInstallmentPaymentUi();
                    syncAmountWithAdditionalServices();
                }
                updateAdditionalServiceEligibility();
                renderSelectedAppointmentServices(selected);
                updateClearBookingButtonVisibility();
                closeSelectorModal();
            });
        }
        additionalServiceCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const validation = validateSelectedAdditionalServices();
                if (!validation.valid) {
                    checkbox.checked = false;
                    showValidationPopup(validation.message);
                }
                syncAmountWithAdditionalServices();
            });
        });
        if (transactionForm) {
            transactionForm.addEventListener('submit', (event) => {
                const bookingIdValue = String(selectedBookingIdInput ? selectedBookingIdInput.value : '').trim();
                const paymentMethodValue = String(paymentMethodInput ? paymentMethodInput.value : '').trim();
                if (!bookingIdValue && !paymentMethodValue) {
                    event.preventDefault();
                    showValidationPopup('Please select a patient and a payment method');
                    return;
                }
                if (!bookingIdValue) {
                    event.preventDefault();
                    showValidationPopup('No patient selected');
                    return;
                }
                if (!paymentMethodValue) {
                    event.preventDefault();
                    showValidationPopup('No payment method selected');
                    return;
                }
                const validation = validateSelectedAdditionalServices();
                if (!validation.valid) {
                    event.preventDefault();
                    showValidationPopup(validation.message);
                }
            });
        }
        document.querySelectorAll('input[name="installment_pay_mode_ui"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                refreshInstallmentPaymentUi();
                syncAmountWithAdditionalServices();
            });
        });
        if (installmentSlotStepper) {
            installmentSlotStepper.addEventListener('input', () => {
                refreshInstallmentPaymentUi();
                syncAmountWithAdditionalServices();
            });
        }
        if (clearBookingBtn) {
            clearBookingBtn.addEventListener('click', clearSelectedBooking);
        }
        refreshInstallmentPaymentUi();
        syncAmountWithAdditionalServices();
        updateAdditionalServicesVisibility();

        (function restoreSelectionAfterPost() {
            const bid = String(selectedBookingIdInput ? selectedBookingIdInput.value : '').trim();
            const tid = String(selectedTreatmentIdInput ? selectedTreatmentIdInput.value : '').trim();
            if (!bid) {
                updateClearBookingButtonVisibility();
                return;
            }
            const postedType = String(selectedTransactionTypeInput ? selectedTransactionTypeInput.value : '').toLowerCase().trim();
            const wantedType = postedType === 'installment' ? 'installment' : 'regular';
            const pre = (wantedType === 'installment' && tid !== ''
                ? normalizeTransactions.find((x) => String(x.treatment_id || '').trim() === tid && String(x.transaction_type || '') === 'installment')
                : null)
                || normalizeTransactions.find((x) => x.booking_id === bid && String(x.transaction_type || '') === wantedType)
                || normalizeTransactions.find((x) => x.booking_id === bid);
            if (pre) {
                selectedTransaction = pre;
                if (selectedTreatmentIdInput) {
                    selectedTreatmentIdInput.value = String(pre.treatment_id || '').trim();
                }
                if (selectedTransactionTypeInput) {
                    selectedTransactionTypeInput.value = pre.is_installment_plan ? 'installment' : 'regular';
                }
                updateAdditionalServicesVisibility();
                if (pre.is_installment_plan) {
                    syncMainAndSelectorFilter('installment');
                } else {
                    syncMainAndSelectorFilter('regular');
                }
                refreshInstallmentPaymentUi();
                syncAmountWithAdditionalServices();
                updateAdditionalServiceEligibility();
                renderSelectedAppointmentServices(pre);
            }
            updateAdditionalServicesVisibility();
            updateClearBookingButtonVisibility();
        })();

        paymentMethodCards.forEach((card) => {
            card.addEventListener('click', () => {
                paymentMethodCards.forEach((other) => other.classList.remove('active'));
                card.classList.add('active');
                if (paymentMethodInput) {
                    paymentMethodInput.value = card.getAttribute('data-method') || '';
                }
            });
        });
    })();
</script>
<div id="payment-success-toast" class="fixed top-24 right-6 md:right-10 z-[200] max-w-md w-[min(100%-3rem,28rem)] flex justify-end transition-all duration-300 ease-out opacity-0 translate-y-3 scale-[0.98] pointer-events-none" aria-hidden="true" role="status" aria-live="polite">
<div class="pointer-events-auto rounded-2xl border border-emerald-200/90 bg-white/95 backdrop-blur-md shadow-2xl shadow-slate-900/10 px-4 py-3.5 flex items-start gap-3">
<span class="material-symbols-outlined text-emerald-600 shrink-0 text-2xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
<p class="text-sm font-semibold text-slate-800 leading-snug flex-1 pt-0.5" id="payment-success-toast-msg"></p>
<button type="button" class="shrink-0 rounded-lg p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" id="payment-success-toast-close" aria-label="Dismiss notification">
<span class="material-symbols-outlined text-lg">close</span>
</button>
</div>
</div>
<script>
(function () {
    function stripSuccessQuery() {
        try {
            const u = new URL(window.location.href);
            if (u.searchParams.get('payment_success') === '1') {
                u.searchParams.delete('payment_success');
                window.history.replaceState({}, '', u.pathname + u.search + u.hash);
            }
        } catch (e) {
        }
    }
    const toast = document.getElementById('payment-success-toast');
    const msgEl = document.getElementById('payment-success-toast-msg');
    const closeBtn = document.getElementById('payment-success-toast-close');
    const message = <?php echo json_encode($paymentSuccess !== '' ? $paymentSuccess : '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    if (!toast || !msgEl) {
        stripSuccessQuery();
        return;
    }
    if (!message) {
        stripSuccessQuery();
        return;
    }
    let hideTimer = null;
    function hideToast() {
        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }
        toast.setAttribute('aria-hidden', 'true');
        toast.classList.remove('opacity-100', 'translate-y-0', 'scale-100');
        toast.classList.add('opacity-0', 'translate-y-3', 'scale-[0.98]', 'pointer-events-none');
    }
    function showToast() {
        msgEl.textContent = message;
        toast.setAttribute('aria-hidden', 'false');
        toast.classList.remove('opacity-0', 'translate-y-3', 'scale-[0.98]', 'pointer-events-none');
        toast.classList.add('opacity-100', 'translate-y-0', 'scale-100');
        stripSuccessQuery();
        hideTimer = window.setTimeout(hideToast, 4800);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', hideToast);
    }
    window.requestAnimationFrame(function () {
        window.requestAnimationFrame(showToast);
    });
})();
</script>
</body></html>