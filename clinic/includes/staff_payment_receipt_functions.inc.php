<?php

/**
 * Shared staff payment receipt helpers.
 * Loaded by StaffPaymentRecording.php and StaffPaymentPayMongoReturn.php.
 */

/**
 * Human-readable payment method for receipts, recent transactions, exports (includes mobile wallet combos).
 *
 * Mobile wallet-only rows use ENUM `check` plus `[Wallet applied: …]` in notes; combo uses `gcash` + same note tag.
 *
 * @param string|null $notes Optional tbl_payments.notes (or '') so `check` maps to MyDental Wallet when tagged.
 */
function staff_payment_recording_format_payment_method_display(?string $methodRaw, ?string $notes = null): string
{
    $key = strtolower(trim((string) $methodRaw));
    $notesStr = (string) ($notes ?? '');
    $notesTagsWallet = str_contains($notesStr, '[Wallet applied:');
    if ($key === 'check' && $notesTagsWallet) {
        return 'MyDental Wallet';
    }
    if ($key === 'gcash' && $notesTagsWallet) {
        return 'GCash + MyDental Wallet';
    }

    $map = [
        'gcash' => 'GCash',
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'credit_card' => 'Credit Card',
        'check' => 'Check',
        /** Legacy / migrated rows that stored logical codes in VARCHAR. */
        'wallet' => 'MyDental Wallet',
        'wallet_gcash' => 'GCash + MyDental Wallet',
        'wallet+gcash' => 'GCash + MyDental Wallet',
        'wallet+paymongo' => 'GCash + MyDental Wallet',
        'wallet_paymongo' => 'GCash + MyDental Wallet',
    ];
    if (isset($map[$key])) {
        return $map[$key];
    }
    if ($key === '') {
        return '-';
    }

    return ucfirst(str_replace('_', ' ', $key));
}

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

function staff_payment_recording_fetch_regular_services_for_booking(
    PDO $pdo,
    string $tenantId,
    string $bookingId,
    bool $supportsAppointmentServicesTable,
    bool $supportsAppointmentServiceTypeColumn,
    bool $supportsServiceEnableInstallmentColumn
): array {
    if (!$supportsAppointmentServicesTable || $bookingId === '') {
        return [];
    }

    $sql = '';
    if ($supportsAppointmentServiceTypeColumn) {
        $sql = "
            SELECT COALESCE(aps.service_name, '') AS service_name, COALESCE(aps.price, 0) AS price
            FROM tbl_appointment_services aps
            WHERE aps.tenant_id = ?
              AND aps.booking_id = ?
              AND LOWER(TRIM(COALESCE(aps.service_type, ''))) = 'regular'
            ORDER BY aps.id ASC
        ";
    } elseif ($supportsServiceEnableInstallmentColumn) {
        $sql = "
            SELECT COALESCE(aps.service_name, '') AS service_name, COALESCE(aps.price, 0) AS price
            FROM tbl_appointment_services aps
            INNER JOIN tbl_services sv
                ON sv.tenant_id = aps.tenant_id
               AND sv.service_id = aps.service_id
            WHERE aps.tenant_id = ?
              AND aps.booking_id = ?
              AND COALESCE(sv.enable_installment, 0) = 0
            ORDER BY aps.id ASC
        ";
    } else {
        return [];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId, $bookingId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $out = [];
    foreach ($rows as $row) {
        $serviceName = trim((string) ($row['service_name'] ?? ''));
        $serviceName = preg_replace('/\[[^\]]*\]/', '', $serviceName);
        $serviceName = trim((string) preg_replace('/\s+/', ' ', (string) $serviceName));
        if ($serviceName === '') {
            continue;
        }
        $out[] = [
            'name' => $serviceName,
            'amount' => round(max(0.0, (float) ($row['price'] ?? 0)), 2),
        ];
    }
    return $out;
}

/**
 * Comma-separated appointment line item names for receipts (all services on the booking).
 */
function staff_payment_recording_fetch_appointment_service_names_summary(
    PDO $pdo,
    string $tenantId,
    string $bookingId,
    bool $supportsAppointmentServicesTable
): string {
    $bookingId = trim($bookingId);
    if (!$supportsAppointmentServicesTable || $bookingId === '' || $tenantId === '') {
        return '';
    }
    $stmt = $pdo->prepare("
        SELECT service_name
        FROM tbl_appointment_services
        WHERE tenant_id = ?
          AND booking_id = ?
          AND TRIM(COALESCE(service_name, '')) <> ''
        ORDER BY id ASC
    ");
    $stmt->execute([$tenantId, $bookingId]);
    $seen = [];
    $names = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
        $n = trim((string) ($row['service_name'] ?? ''));
        if ($n === '') {
            continue;
        }
        $key = strtolower($n);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $names[] = $n;
    }
    return implode(', ', $names);
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

function staff_payment_recording_normalize_receipt_installment_totals(array &$items, float $targetTotal): void
{
    $targetTotal = round(max(0.0, $targetTotal), 2);
    if ($items === [] || $targetTotal <= 0.009) {
        return;
    }
    $n = count($items);
    $current = 0.0;
    foreach ($items as $it) {
        $current += round(max(0.0, (float) ($it['amount'] ?? 0)), 2);
    }
    $current = round($current, 2);
    if (abs($current - $targetTotal) <= 0.05) {
        return;
    }
    if ($n === 1) {
        $items[0]['amount'] = $targetTotal;
        return;
    }
    if ($current <= 0.009) {
        $each = round($targetTotal / $n, 2);
        $sum = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $items[$i]['amount'] = $each;
            $sum = round($sum + $each, 2);
        }
        $items[$n - 1]['amount'] = round($targetTotal - $sum, 2);
        return;
    }
    $scale = $targetTotal / $current;
    $acc = 0.0;
    for ($i = 0; $i < $n - 1; $i++) {
        $line = round(max(0.0, (float) ($items[$i]['amount'] ?? 0)) * $scale, 2);
        $items[$i]['amount'] = $line;
        $acc = round($acc + $line, 2);
    }
    $items[$n - 1]['amount'] = round($targetTotal - $acc, 2);
}

/**
 * Whether installment row #1 is a discrete downpayment (vs monthly #1) for labeling receipts.
 *
 * Uses tbl_treatments.duration_months when present: downpayment-flow schedules have one extra slot
 * (row 1 down + duration_months monthly rows numbered 2..duration+1), so max(installment_number) > duration_months.
 */
function staff_payment_recording_schedule_row1_is_discrete_downpayment(array $schedule, int $treatmentDurationMonths): bool
{
    $maxInst = 0;
    $amountByNumber = [];
    foreach ($schedule as $row) {
        if (!is_array($row)) {
            continue;
        }
        $n = (int) ($row['installment_number'] ?? 0);
        if ($n <= 0) {
            continue;
        }
        $maxInst = max($maxInst, $n);
        $amountByNumber[$n] = round(max(0.0, (float) ($row['amount_due'] ?? 0)), 2);
    }
    $treatDur = max(0, $treatmentDurationMonths);
    if ($treatDur > 0 && $maxInst > $treatDur) {
        return true;
    }
    $a1 = $amountByNumber[1] ?? 0.0;
    $a2 = $amountByNumber[2] ?? 0.0;
    if ($a1 > 0.009 && $a2 > 0.009 && abs($a1 - $a2) > 0.05) {
        return true;
    }
    return false;
}

/**
 * Map schedule installment_number to patient-facing monthly ordinal (1-based), or 0 for downpayment line.
 *
 * When row 1 is discrete downpayment, schedule slots 2+ are Monthly #1, #2, …
 */
function staff_payment_recording_schedule_num_to_monthly_display_num(int $scheduleInstNum, bool $row1IsDiscreteDown): int
{
    if ($scheduleInstNum <= 0) {
        return 0;
    }
    if ($scheduleInstNum === 1) {
        return $row1IsDiscreteDown ? 0 : 1;
    }
    return $row1IsDiscreteDown ? ($scheduleInstNum - 1) : $scheduleInstNum;
}

/**
 * Build receipt breakdown from the current payment transaction only.
 *
 * @return array{service_label:string, service_items:list<array{name:string, amount:float}>, services_total:float}
 */
function staff_payment_recording_build_transaction_breakdown(array $payment): array
{
    $amountPaid = round(max(0.0, (float) ($payment['amount'] ?? 0)), 2);
    $installmentNumber = max(0, (int) ($payment['installment_number'] ?? 0));
    $monthsPaid = max(0, (int) ($payment['months_paid'] ?? 0));
    $paymentType = strtolower(trim((string) ($payment['payment_type'] ?? '')));
    $serviceType = strtolower(trim((string) ($payment['service_type'] ?? '')));
    $treatmentDurationMonths = max(0, (int) ($payment['treatment_duration_months'] ?? 0));

    $serviceHint = trim((string) ($payment['service_description'] ?? ''));
    if ($serviceHint === '') {
        $serviceHint = trim((string) ($payment['service_list'] ?? ''));
    }
    if ($serviceHint === '') {
        $serviceHint = trim((string) ($payment['service'] ?? ''));
    }
    $serviceHint = preg_replace('/\[[^\]]*\]/', '', $serviceHint);
    $serviceHint = trim((string) preg_replace('/\s+/', ' ', (string) $serviceHint));
    $paymentNotes = trim((string) ($payment['notes'] ?? ''));
    $installmentSchedule = isset($payment['installment_schedule']) && is_array($payment['installment_schedule'])
        ? $payment['installment_schedule']
        : [];
    $row1DiscreteForReceipt = staff_payment_recording_schedule_row1_is_discrete_downpayment(
        $installmentSchedule,
        $treatmentDurationMonths
    );
    $regularServiceItems = [];
    if (isset($payment['regular_service_items']) && is_array($payment['regular_service_items'])) {
        foreach ($payment['regular_service_items'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowName = trim((string) ($row['name'] ?? ''));
            $rowName = preg_replace('/\[[^\]]*\]/', '', $rowName);
            $rowName = trim((string) preg_replace('/\s+/', ' ', (string) $rowName));
            if ($rowName === '') {
                continue;
            }
            $regularServiceItems[] = [
                'name' => $rowName,
                'amount' => round(max(0.0, (float) ($row['amount'] ?? 0)), 2),
            ];
        }
    }

    $addOnItemsFromNotes = [];
    if ($paymentNotes !== '' && preg_match('/\[Add-on Services:\s*(.*?)\]/i', $paymentNotes, $addOnMatch) === 1) {
        $rawItems = array_filter(array_map('trim', explode(';', (string) ($addOnMatch[1] ?? ''))));
        foreach ($rawItems as $rawItem) {
            $itemName = $rawItem;
            $itemAmount = null;
            if (preg_match('/^(.*?)\s*\((?:₱|PHP|Php|php)?\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)\)\s*$/u', $rawItem, $itemMatch) === 1) {
                $itemName = trim((string) ($itemMatch[1] ?? ''));
                $itemAmount = (float) str_replace(',', '', (string) ($itemMatch[2] ?? '0'));
            } elseif (preg_match('/^(.*?)\s*\(([^)]*)\)\s*$/u', $rawItem, $fallbackMatch) === 1) {
                // Fallback for legacy notes where the peso symbol may be stripped/encoded differently.
                $itemName = trim((string) ($fallbackMatch[1] ?? ''));
                $numericPart = preg_replace('/[^0-9.,]/', '', (string) ($fallbackMatch[2] ?? ''));
                if (is_string($numericPart) && $numericPart !== '') {
                    $itemAmount = (float) str_replace(',', '', $numericPart);
                }
            }
            $itemName = trim((string) preg_replace('/\s+/', ' ', $itemName));
            if ($itemName === '') {
                continue;
            }
            $addOnItemsFromNotes[] = [
                'name' => 'Add-on: ' . $itemName,
                'amount' => round(max(0.0, (float) ($itemAmount ?? 0.0)), 2),
            ];
        }
    }

    $installmentNumbersFromNotes = [];
    if ($paymentNotes !== '' && preg_match('/\[Installments:\s*(.*?)\]/i', $paymentNotes, $installmentMatch) === 1) {
        $numMatches = [];
        preg_match_all('/#\s*(\d+)/', (string) ($installmentMatch[1] ?? ''), $numMatches);
        $rawNums = isset($numMatches[1]) && is_array($numMatches[1]) ? $numMatches[1] : [];
        foreach ($rawNums as $rawNum) {
            $n = (int) $rawNum;
            if ($n > 0) {
                $installmentNumbersFromNotes[$n] = true;
            }
        }
    }

    $transactionLabel = 'Payment';
    if ($installmentNumber > 0 || $serviceType === 'installment') {
        if ($installmentNumbersFromNotes !== []) {
            $installmentItems = [];
            $installmentTotal = 0.0;
            foreach (array_keys($installmentNumbersFromNotes) as $instNum) {
                $amountForInstallment = 0.0;
                foreach ($installmentSchedule as $instRow) {
                    if ((int) ($instRow['installment_number'] ?? 0) === (int) $instNum) {
                        $amountForInstallment = round(max(0.0, (float) ($instRow['amount_due'] ?? 0)), 2);
                        break;
                    }
                }
                if ($amountForInstallment <= 0.0) {
                    continue;
                }
                $disp = staff_payment_recording_schedule_num_to_monthly_display_num((int) $instNum, $row1DiscreteForReceipt);
                if ($disp === 0) {
                    $itemLabel = 'Downpayment';
                } else {
                    $itemLabel = 'Monthly Payment #' . $disp;
                }
                $installmentItems[] = [
                    'name' => $itemLabel,
                    'amount' => $amountForInstallment,
                ];
                $installmentTotal += $amountForInstallment;
            }
            if ($installmentItems !== []) {
                if ($addOnItemsFromNotes !== []) {
                    $addOnExplicitTotal = 0.0;
                    $addOnMissingAmountCount = 0;
                    foreach ($addOnItemsFromNotes as $addOnItem) {
                        $lineAmount = (float) ($addOnItem['amount'] ?? 0);
                        if ($lineAmount <= 0.0) {
                            $addOnMissingAmountCount++;
                        }
                        $addOnExplicitTotal += $lineAmount;
                    }
                    $addOnExplicitTotal = round(max(0.0, $addOnExplicitTotal), 2);
                    staff_payment_recording_normalize_receipt_installment_totals(
                        $installmentItems,
                        round(max(0.0, $amountPaid - $addOnExplicitTotal), 2)
                    );
                    $installmentTotal = 0.0;
                    foreach ($installmentItems as $ii) {
                        $installmentTotal += (float) ($ii['amount'] ?? 0);
                    }
                    $installmentTotal = round($installmentTotal, 2);

                    $distributableAddons = round(max(0.0, $amountPaid - $installmentTotal - $addOnExplicitTotal), 2);
                    if ($addOnMissingAmountCount > 0 && $distributableAddons > 0.009) {
                        $addOnAssignable = $distributableAddons;
                        $updatedItems = [];
                        foreach ($addOnItemsFromNotes as $index => $addOnItem) {
                            $currentAmount = round(max(0.0, (float) ($addOnItem['amount'] ?? 0)), 2);
                            if ($currentAmount > 0.0) {
                                $updatedItems[] = $addOnItem;
                                continue;
                            }
                            $fillAmount = $index === (count($addOnItemsFromNotes) - 1)
                                ? $addOnAssignable
                                : round($distributableAddons / $addOnMissingAmountCount, 2);
                            $fillAmount = max(0.0, min($addOnAssignable, $fillAmount));
                            $addOnAssignable = round($addOnAssignable - $fillAmount, 2);
                            $addOnItem['amount'] = $fillAmount;
                            $updatedItems[] = $addOnItem;
                        }
                        $addOnItemsFromNotes = $updatedItems;
                    }
                    $addOnTotal = 0.0;
                    foreach ($addOnItemsFromNotes as $addOnItem) {
                        $addOnTotal += (float) ($addOnItem['amount'] ?? 0);
                    }
                    $addOnTotal = round($addOnTotal, 2);

                    $mergedItems = array_merge($installmentItems, $addOnItemsFromNotes);
                    $mergedTotal = round($installmentTotal + $addOnTotal, 2);
                    // Keep the receipt aligned with the posted amount if minor rounding drift remains.
                    if ($mergedTotal <= 0.009 || abs($mergedTotal - $amountPaid) > 0.05) {
                        $mergedTotal = $amountPaid;
                    }
                    return [
                        'service_label' => 'Installment Payments + Add-on Services',
                        'service_items' => $mergedItems,
                        'services_total' => round($mergedTotal, 2),
                    ];
                }
                staff_payment_recording_normalize_receipt_installment_totals($installmentItems, $amountPaid);
                $installmentTotal = 0.0;
                foreach ($installmentItems as $ii) {
                    $installmentTotal += (float) ($ii['amount'] ?? 0);
                }
                return [
                    'service_label' => 'Installment Payments',
                    'service_items' => $installmentItems,
                    'services_total' => round($installmentTotal, 2),
                ];
            }
        }

        $dispInst = $installmentNumber > 0
            ? staff_payment_recording_schedule_num_to_monthly_display_num($installmentNumber, $row1DiscreteForReceipt)
            : -1;
        if ($dispInst === 0 || ($dispInst < 0 && $paymentType === 'downpayment')) {
            $transactionLabel = 'Downpayment';
        } elseif ($dispInst > 0) {
            $transactionLabel = 'Monthly Payment #' . $dispInst;
        } elseif ($monthsPaid > 0) {
            $transactionLabel = 'Monthly Payment #' . $monthsPaid;
        } else {
            $transactionLabel = 'Monthly Payment';
        }
    } elseif ($serviceType === 'regular' || $serviceType === '') {
        $transactionLabel = 'Regular Services';
    }

    if ($transactionLabel === 'Add-on Services' && $addOnItemsFromNotes !== []) {
        $servicesTotal = 0.0;
        foreach ($addOnItemsFromNotes as $addOnItem) {
            $servicesTotal += (float) ($addOnItem['amount'] ?? 0);
        }
        // If legacy note values are missing/partial, keep receipt totals consistent with posted amount.
        if ($servicesTotal <= 0.009 || abs($servicesTotal - $amountPaid) > 0.05) {
            $servicesTotal = $amountPaid;
        }
        return [
            'service_label' => 'Add-on Services',
            'service_items' => $addOnItemsFromNotes,
            'services_total' => round($servicesTotal, 2),
        ];
    }

    if ($transactionLabel === 'Regular Services' && $regularServiceItems !== []) {
        $regularServicesTotal = 0.0;
        foreach ($regularServiceItems as $regularServiceItem) {
            $regularServicesTotal += (float) ($regularServiceItem['amount'] ?? 0);
        }
        if ($regularServicesTotal <= 0.009 || abs($regularServicesTotal - $amountPaid) > 0.05) {
            $regularServicesTotal = $amountPaid;
        }
        return [
            'service_label' => 'Regular Services',
            'service_items' => $regularServiceItems,
            'services_total' => round($regularServicesTotal, 2),
        ];
    }

    $serviceLabel = $serviceHint !== '' ? $serviceHint : $transactionLabel;
    if ($transactionLabel !== 'Add-on Services' && $serviceHint !== '' && $serviceHint !== $transactionLabel) {
        $serviceLabel = $transactionLabel . ' - ' . $serviceHint;
    }

    return [
        'service_label' => $serviceLabel,
        'service_items' => [[
            'name' => $transactionLabel,
            'amount' => $amountPaid,
        ]],
        'services_total' => $amountPaid,
    ];
}

function staff_payment_recording_build_receipt_email_html(array $receipt): string
{
    $clinicName = htmlspecialchars((string) ($receipt['clinic_name'] ?? 'MyDental Philippines'), ENT_QUOTES, 'UTF-8');
    $clinicLogo = htmlspecialchars((string) ($receipt['clinic_logo'] ?? ''), ENT_QUOTES, 'UTF-8');
    $patientName = htmlspecialchars((string) ($receipt['patient_name'] ?? 'Patient'), ENT_QUOTES, 'UTF-8');
    $patientId = htmlspecialchars((string) ($receipt['patient_id'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
    $reference = htmlspecialchars((string) ($receipt['reference'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $paymentId = htmlspecialchars((string) ($receipt['payment_id'] ?? '-'), ENT_QUOTES, 'UTF-8');
    $appointmentServicesLine = htmlspecialchars(trim((string) ($receipt['appointment_services'] ?? '')), ENT_QUOTES, 'UTF-8');
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
    $appointmentServicesRowHtml = '';
    if ($appointmentServicesLine !== '') {
        $appointmentServicesRowHtml = '<tr><td style="font-size:14px;line-height:20px;font-weight:700;color:#0f172a;padding:0 0 10px;">Appointment services</td><td align="right" style="font-size:14px;line-height:20px;font-weight:700;color:#0f172a;padding:0 0 10px;max-width:360px;">' . $appointmentServicesLine . '</td></tr>'
            . '<tr><td colspan="2" style="border-bottom:1px solid #e2e8f0;line-height:0;font-size:0;padding:0 0 6px">&nbsp;</td></tr>'
            . '<tr><td height="8"></td><td></td></tr>';
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
        . '<p style="margin:8px 0 0;font-size:14px;line-height:18px;color:#7284a8;">ID: ' . $patientId . '</p></td>'
        . '<td width="2%"></td>'
        . '<td width="49%" valign="top" style="width:49%;border:1px solid #dbeafe;border-radius:14px;padding:12px 14px;">'
        . '<p style="margin:0;font-size:11px;line-height:14px;letter-spacing:3px;font-weight:800;color:#647aa5;text-transform:uppercase;">Transaction Ref</p>'
        . '<p style="margin:10px 0 0;font-size:18px;line-height:24px;font-weight:800;color:#0f172a;word-break:break-word;">' . $reference . '</p>'
        . '<p style="margin:8px 0 0;font-size:14px;line-height:18px;color:#7284a8;">Payment ID: ' . $paymentId . '</p></td>'
        . '</tr></table></td></tr>'
        . '<tr><td style="padding:18px 24px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dbeafe;border-radius:14px;overflow:hidden;">'
        . '<tr><td style="padding:12px 14px;background:#f9fcff;border-bottom:1px solid #dbeafe;font-size:13px;line-height:18px;letter-spacing:4px;font-weight:800;color:#4f668f;text-transform:uppercase;">Payment Breakdown</td></tr>'
        . '<tr><td style="padding:12px 14px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . $appointmentServicesRowHtml
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
        . '<tr><td style="padding:8px 24px 22px;text-align:center;font-size:11px;color:#64748b;font-weight:600;line-height:16px;">Digitally generated receipt from ' . $clinicName . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Same receipt fields as Staff Payment Recording "Send to email" — clinic logo from DB, full payment breakdown.
 *
 * @return array{subject:string,text:string,html:string}|null
 */
function staff_payment_recording_compose_receipt_email_payload(PDO $pdo, string $tenantId, string $paymentId): ?array
{
    $tenantId = trim($tenantId);
    $paymentId = trim($paymentId);
    if ($tenantId === '' || $paymentId === '') {
        return null;
    }

    $supportsPaymentTypeColumn = false;
    $supportsAppointmentServicesTable = false;
    $appointmentServiceColumns = [];
    $supportsAppointmentServiceTypeColumn = false;
    $supportsServiceEnableInstallmentColumn = false;
    $installmentsTableName = null;
    $supportsPaymentsInstallmentNumberColumn = false;

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

    $clinicDisplayName = 'MyDental Philippines';
    $clinicLogoPath = 'DRCGLogo2.png';
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

    $receiptInstallmentNumberSelectSql = $supportsPaymentsInstallmentNumberColumn
        ? 'COALESCE(py.installment_number, 0)'
        : '0';
    $receiptPaymentTypeSelectSql = $supportsPaymentTypeColumn
        ? "COALESCE(py.payment_type, '')"
        : "''";

    $receiptSql = "
        SELECT
            py.payment_id,
            py.patient_id,
            py.booking_id,
            py.amount,
            py.payment_date,
            py.payment_method,
            py.reference_number,
            COALESCE(py.notes, '') AS notes,
            py.status,
            {$receiptInstallmentNumberSelectSql} AS installment_number,
            {$receiptPaymentTypeSelectSql} AS payment_type,
            COALESCE(t.months_paid, 0) AS months_paid,
            COALESCE(t.duration_months, 0) AS treatment_duration_months,
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
        LEFT JOIN tbl_treatments t
            ON t.tenant_id = py.tenant_id
           AND t.treatment_id = COALESCE(a.treatment_id, '')
        LEFT JOIN tbl_users u_linked
            ON u_linked.user_id = p.linked_user_id
        LEFT JOIN tbl_users u_owner
            ON u_owner.user_id = p.owner_user_id
        WHERE py.tenant_id = ?
          AND py.payment_id = ?
        LIMIT 1
    ";

    $receiptStmt = $pdo->prepare($receiptSql);
    $receiptStmt->execute([$tenantId, $paymentId]);
    $receiptRow = $receiptStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($receiptRow === []) {
        return null;
    }

    $receiptBookingId = trim((string) ($receiptRow['booking_id'] ?? ''));
    $appointmentServicesSummary = '';
    if ($receiptBookingId !== '') {
        $receiptRow['regular_service_items'] = staff_payment_recording_fetch_regular_services_for_booking(
            $pdo,
            $tenantId,
            $receiptBookingId,
            $supportsAppointmentServicesTable,
            $supportsAppointmentServiceTypeColumn,
            $supportsServiceEnableInstallmentColumn
        );
        $receiptRow['installment_schedule'] = staff_payment_recording_fetch_installments(
            $pdo,
            $installmentsTableName,
            $tenantId,
            $receiptBookingId
        );
        $appointmentServicesSummary = staff_payment_recording_fetch_appointment_service_names_summary(
            $pdo,
            $tenantId,
            $receiptBookingId,
            $supportsAppointmentServicesTable
        );
    } else {
        $receiptRow['regular_service_items'] = [];
        $receiptRow['installment_schedule'] = [];
    }

    $patientFullName = trim(trim((string) ($receiptRow['patient_first_name'] ?? '')) . ' ' . trim((string) ($receiptRow['patient_last_name'] ?? '')));
    if ($patientFullName === '') {
        $patientFullName = 'Patient';
    }

    $receiptBreakdown = staff_payment_recording_build_transaction_breakdown($receiptRow);
    $servicesLabel = (string) ($receiptBreakdown['service_label'] ?? 'Payment');
    $serviceItems = isset($receiptBreakdown['service_items']) && is_array($receiptBreakdown['service_items'])
        ? $receiptBreakdown['service_items']
        : [];
    $servicesTotalValue = (float) ($receiptBreakdown['services_total'] ?? 0);
    $servicesTotalLabel = '₱' . number_format($servicesTotalValue, 2);
    $amountPaid = (float) ($receiptRow['amount'] ?? 0);
    $isRegularAddOnReceipt = strcasecmp($servicesLabel, 'Add-on Services') === 0;
    if ($isRegularAddOnReceipt) {
        $balanceLeft = 0.0;
    } else {
        $balanceLeft = max(0, (float) ($receiptRow['total_treatment_cost'] ?? 0) - (float) ($receiptRow['booking_total_paid'] ?? 0));
    }
    $paymentDateValue = trim((string) ($receiptRow['payment_date'] ?? ''));
    $paymentDateObj = staff_payment_recording_to_manila_datetime($paymentDateValue);
    $paymentDateLabel = $paymentDateObj instanceof DateTimeImmutable
        ? $paymentDateObj->format('F d, Y h:i A')
        : '-';
    $referenceLabel = trim((string) ($receiptRow['reference_number'] ?? ''));
    if ($referenceLabel === '') {
        $referenceLabel = trim((string) ($receiptRow['payment_id'] ?? ''));
    }
    $allowedMethods = [
        'gcash' => 'GCash',
        'cash' => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'credit_card' => 'Credit Card',
    ];
    $methodLabel = $allowedMethods[strtolower(trim((string) ($receiptRow['payment_method'] ?? '')))]
        ?? ucfirst(str_replace('_', ' ', trim((string) ($receiptRow['payment_method'] ?? ''))));

    $amountPaidLabel = '₱' . number_format($amountPaid, 2);
    $balanceLeftLabel = '₱' . number_format($balanceLeft, 2);
    $appointmentServicesSummaryForEmail = trim((string) $appointmentServicesSummary);

    $emailSubject = 'Payment Receipt - ' . $clinicDisplayName;
    $emailBodyText = "Clinic: {$clinicDisplayName}\n"
        . "Patient: {$patientFullName}\n"
        . "Payment ID: " . (string) ($receiptRow['payment_id'] ?? '') . "\n"
        . "Reference: {$referenceLabel}\n"
        . (
            $appointmentServicesSummaryForEmail !== ''
                ? ("Appointment services: {$appointmentServicesSummaryForEmail}\n")
                : ''
        )
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
        'appointment_services' => $appointmentServicesSummaryForEmail,
        'service' => $servicesLabel,
        'service_items' => $serviceItems,
        'services_total' => $servicesTotalLabel,
        'payment_date' => $paymentDateLabel,
        'payment_method' => $methodLabel,
        'amount_paid' => $amountPaidLabel,
        'remaining_balance' => $balanceLeftLabel,
    ]);

    return [
        'subject' => $emailSubject,
        'text' => $emailBodyText,
        'html' => $emailBodyHtml,
    ];
}

/**
 * Persist an approved PWD/Senior discount by lowering billable amounts on the booking:
 * reduces regular (non-installment) appointment line prices and syncs tbl_appointments.total_treatment_cost
 * to the new line sum so pending, PAID/PARTIAL, receipts, and selectors match the discounted obligation.
 */
function staff_payment_recording_apply_pwd_senior_discount_to_booking_economics(
    PDO $pdo,
    string $tenantId,
    string $bookingId,
    float $discountAmount
): void {
    $tenantId = trim($tenantId);
    $bookingId = trim($bookingId);
    $discountAmount = round(max(0.0, $discountAmount), 2);
    if ($tenantId === '' || $bookingId === '' || $discountAmount <= 0.009) {
        return;
    }

    $apsTableStmt = $pdo->prepare("
        SELECT TABLE_NAME
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND LOWER(TABLE_NAME) = 'tbl_appointment_services'
        LIMIT 1
    ");
    $apsTableStmt->execute();
    $apsPhysical = $apsTableStmt->fetchColumn();
    if (!$apsPhysical || !is_string($apsPhysical)) {
        $upd = $pdo->prepare(
            'UPDATE tbl_appointments
             SET total_treatment_cost = ROUND(GREATEST(0, COALESCE(total_treatment_cost, 0) - ?), 2)
             WHERE tenant_id = ? AND booking_id = ?
             LIMIT 1'
        );
        $upd->execute([$discountAmount, $tenantId, $bookingId]);

        return;
    }

    $apsQuoted = '`' . str_replace('`', '``', $apsPhysical) . '`';

    $apsColsStmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND LOWER(TABLE_NAME) = LOWER(?)
    ");
    $apsColsStmt->execute([$apsPhysical]);
    $apsCols = array_map('strtolower', array_map('strval', $apsColsStmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    $hasApsServiceType = in_array('service_type', $apsCols, true);

    $svcEnableStmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tbl_services'
          AND COLUMN_NAME = 'enable_installment'
        LIMIT 1
    ");
    $svcEnableStmt->execute();
    $hasSvcEnableInst = (bool) $svcEnableStmt->fetchColumn();

    $enableExpr = $hasSvcEnableInst ? 'COALESCE(sv.enable_installment, 0)' : '0';

    $apsTypeExpr = $hasApsServiceType
        ? "LOWER(TRIM(COALESCE(NULLIF(aps.service_type, ''), '')))"
        : "''";

    $sql = "
        SELECT
            aps.id,
            COALESCE(aps.price, 0) AS price,
            {$apsTypeExpr} AS aps_type,
            LOWER(TRIM(COALESCE(sv.service_type, ''))) AS sv_type,
            {$enableExpr} AS enable_installment
        FROM {$apsQuoted} aps
        LEFT JOIN tbl_services sv
            ON sv.tenant_id = aps.tenant_id
           AND sv.service_id = aps.service_id
        WHERE aps.tenant_id = ?
          AND aps.booking_id = ?
        ORDER BY aps.id ASC
    ";
    $st = $pdo->prepare($sql);
    $st->execute([$tenantId, $bookingId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $regularIndices = [];
    $regularSum = 0.0;
    foreach ($rows as $i => $row) {
        $apsType = (string) ($row['aps_type'] ?? '');
        $svType = (string) ($row['sv_type'] ?? '');
        $enableInst = (int) ($row['enable_installment'] ?? 0);
        if ($apsType === 'included_plan' || $svType === 'included_plan') {
            continue;
        }
        if ($apsType === 'installment' || $svType === 'installment' || $enableInst === 1) {
            continue;
        }
        $price = (float) ($row['price'] ?? 0);
        if ($price <= 0.009) {
            continue;
        }
        $regularIndices[] = $i;
        $regularSum += $price;
    }

    if ($regularSum <= 0.009) {
        $upd = $pdo->prepare(
            'UPDATE tbl_appointments
             SET total_treatment_cost = ROUND(GREATEST(0, COALESCE(total_treatment_cost, 0) - ?), 2)
             WHERE tenant_id = ? AND booking_id = ?
             LIMIT 1'
        );
        $upd->execute([$discountAmount, $tenantId, $bookingId]);

        return;
    }

    $allocations = [];
    $allocated = 0.0;
    $n = count($regularIndices);
    foreach ($regularIndices as $j => $idx) {
        $price = (float) ($rows[$idx]['price'] ?? 0);
        if ($j === $n - 1) {
            $share = round(max(0.0, $discountAmount - $allocated), 2);
        } else {
            $share = round($discountAmount * ($price / $regularSum), 2);
            $allocated += $share;
        }
        $allocations[$idx] = min($price, $share);
    }

    $sumAlloc = 0.0;
    foreach ($regularIndices as $idx) {
        $sumAlloc += $allocations[$idx] ?? 0.0;
    }
    $lastIdx = $regularIndices[$n - 1];
    if (abs($sumAlloc - $discountAmount) > 0.02) {
        $allocations[$lastIdx] = round(
            max(0.0, (float) ($allocations[$lastIdx] ?? 0) + ($discountAmount - $sumAlloc)),
            2
        );
    }

    $updPrice = $pdo->prepare("UPDATE {$apsQuoted} SET price = ? WHERE tenant_id = ? AND id = ? LIMIT 1");
    foreach ($regularIndices as $idx) {
        $oldP = (float) ($rows[$idx]['price'] ?? 0);
        $ded = (float) ($allocations[$idx] ?? 0);
        $newP = round(max(0.0, $oldP - $ded), 2);
        $id = (int) ($rows[$idx]['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $updPrice->execute([$newP, $tenantId, $id]);
    }

    $sumStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(price), 0) AS s FROM {$apsQuoted} WHERE tenant_id = ? AND booking_id = ?"
    );
    $sumStmt->execute([$tenantId, $bookingId]);
    $lineTotal = round((float) ($sumStmt->fetchColumn() ?: 0), 2);
    $hdr = $pdo->prepare('UPDATE tbl_appointments SET total_treatment_cost = ? WHERE tenant_id = ? AND booking_id = ? LIMIT 1');
    $hdr->execute([$lineTotal, $tenantId, $bookingId]);
}
