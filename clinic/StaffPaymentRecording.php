<?php
$staff_nav_active = 'payments';
require_once __DIR__ . '/config/config.php';

/**
 * @return list<array{id:int, installment_number:int, amount_due:float, status:string}>
 */
function staff_payment_recording_fetch_installments(PDO $pdo, ?string $installmentsTableName, string $tenantId, string $bookingId): array
{
    if ($installmentsTableName === null || $bookingId === '') {
        return [];
    }
    $quoted = '`' . str_replace('`', '``', $installmentsTableName) . '`';
    $sql = "
        SELECT id, installment_number, amount_due, status
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
        ];
    }
    return $out;
}

function staff_payment_recording_installment_is_paid(string $status): bool
{
    $s = strtolower(trim($status));
    return $s === 'paid' || $s === 'completed';
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
if (isset($_GET['payment_success']) && $_GET['payment_success'] === '1') {
    $paymentSuccess = 'Payment recorded successfully.';
}
if (isset($_GET['paymongo_error']) && $_GET['paymongo_error'] === '1') {
    $paymentError = 'Could not confirm the online payment. If money was debited, contact support with the booking reference and time of payment.';
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
$availableServices = [];
$supportsPaymentTypeColumn = false;
$supportsAppointmentUpdatedAtColumn = false;
$supportsAppointmentServicesTable = false;
$appointmentServiceColumns = [];
$installmentsTableName = null;
$supportsServiceEnableInstallmentColumn = false;
$supportsPaymentsInstallmentNumberColumn = false;
$formSelectedBookingId = trim((string) ($_POST['selected_booking_id'] ?? ''));
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

        $appointmentUpdatedAtColumnStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_appointments'
              AND COLUMN_NAME = 'updated_at'
            LIMIT 1
        ");
        $appointmentUpdatedAtColumnStmt->execute();
        $supportsAppointmentUpdatedAtColumn = (bool) $appointmentUpdatedAtColumnStmt->fetchColumn();

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
        $patientQuery = trim((string) ($_POST['patient_query'] ?? ''));
        $selectedBookingId = trim((string) ($_POST['selected_booking_id'] ?? ''));
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
                    a.patient_id,
                    COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
                    COALESCE(a.service_description, '') AS service_description,
                    COALESCE(SUM(CASE WHEN py.status = 'completed' THEN py.amount ELSE 0 END), 0) AS total_paid
                FROM tbl_appointments a
                LEFT JOIN tbl_payments py
                    ON py.tenant_id = a.tenant_id
                   AND py.booking_id = a.booking_id
                WHERE a.tenant_id = ?
                  AND a.booking_id = ?
                GROUP BY a.booking_id, a.patient_id, a.total_treatment_cost, a.service_description
                LIMIT 1
            ";
            $bookingStmt = $pdo->prepare($bookingSql);
            $bookingStmt->execute([$tenantId, $selectedBookingId]);
            $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC);
            $patientId = trim((string) ($bookingRow['patient_id'] ?? ''));
            $totalCost = (float) ($bookingRow['total_treatment_cost'] ?? 0);
            $totalPaid = (float) ($bookingRow['total_paid'] ?? 0);
            $pendingBalance = max(0, $totalCost - $totalPaid);

            if ($patientId === '') {
                $paymentError = 'Selected transaction was not found.';
            } elseif ($pendingBalance <= 0) {
                $paymentError = 'Selected transaction is already fully paid.';
            } else {
                try {
                    $scheduleRows = staff_payment_recording_fetch_installments($pdo, $installmentsTableName, $tenantId, $selectedBookingId);
                    $postedInstallFlow = trim((string) ($_POST['installment_flow'] ?? 'regular'));
                    $postedPayMode = trim((string) ($_POST['installment_pay_mode'] ?? 'full'));
                    $postedSlotCount = max(1, (int) ($_POST['installment_slot_count'] ?? 1));
                    $runSchedulePayment = ($postedInstallFlow === 'schedule' && $scheduleRows !== []);

                    if ($runSchedulePayment && !empty($additionalServiceIds)) {
                        throw new RuntimeException('Additional services cannot be combined with installment schedule payments. Uncheck extra services or use a non-schedule payment.');
                    }

                    if (!$runSchedulePayment && !empty($additionalServiceIds)) {
                        if (!$supportsAppointmentServicesTable) {
                            throw new RuntimeException('Additional services are not available in this deployment yet.');
                        }

                        $placeholders = implode(',', array_fill(0, count($additionalServiceIds), '?'));
                        $servicesSql = "
                            SELECT service_id, service_name, price
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

                        $insertColumns = ['tenant_id', 'booking_id', 'service_id', 'service_name', 'price'];
                        $insertValues = ['?', '?', '?', '?', '?'];
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
                            if ($serviceId === '' || isset($existingLookup[$serviceId])) {
                                continue;
                            }
                            $serviceName = trim((string) ($service['service_name'] ?? 'Additional Service'));
                            $servicePrice = (float) ($service['price'] ?? 0);
                            $insertServiceStmt->execute([
                                $tenantId,
                                $selectedBookingId,
                                $serviceId,
                                $serviceName,
                                $servicePrice,
                            ]);
                            $addedCost += $servicePrice;
                            $addedServiceLabels[] = $serviceName . ' (P' . number_format($servicePrice, 2) . ')';
                        }

                        if ($addedCost > 0) {
                            $totalCost += $addedCost;
                            $pendingBalance += $addedCost;
                            $serviceDescription = trim((string) ($bookingRow['service_description'] ?? ''));
                            $addedServiceNote = '[ADDED AT PAYMENT] ' . implode('; ', $addedServiceLabels);
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

                        $unpaid = [];
                        foreach ($scheduleRows as $sr) {
                            if (!staff_payment_recording_installment_is_paid($sr['status'])) {
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
                                'installment_finalize' => [
                                    'installments_table' => $installmentsTableName,
                                    'paid_items' => $finalizeItems,
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

                        $nextAppointmentStatus = ($pendingAfter <= 0.009) ? 'completed' : 'confirmed';
                        $updateAppointmentSql = "
                            UPDATE tbl_appointments
                            SET status = ?" . ($supportsAppointmentUpdatedAtColumn ? ", updated_at = NOW()" : "") . "
                            WHERE tenant_id = ?
                              AND booking_id = ?
                              AND status = 'pending'
                        ";
                        $updateAppointmentStmt = $pdo->prepare($updateAppointmentSql);
                        $updateAppointmentStmt->execute([$nextAppointmentStatus, $tenantId, $selectedBookingId]);

                        $paymentSuccess = 'Payment recorded successfully.';
                        $selectedMethod = '';
                        $selectedMethodForUi = '';
                        $formSelectedBookingId = '';
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

                    $nextAppointmentStatus = ($amount + 0.009 >= $pendingBalance) ? 'completed' : 'confirmed';
                    $updateAppointmentSql = "
                        UPDATE tbl_appointments
                        SET status = ?" . ($supportsAppointmentUpdatedAtColumn ? ", updated_at = NOW()" : "") . "
                        WHERE tenant_id = ?
                          AND booking_id = ?
                          AND status = 'pending'
                    ";
                    $updateAppointmentStmt = $pdo->prepare($updateAppointmentSql);
                    $updateAppointmentStmt->execute([$nextAppointmentStatus, $tenantId, $selectedBookingId]);

                    $paymentSuccess = 'Payment recorded successfully.';
                    // Reset the modal form after successful submission.
                    $selectedMethod = '';
                    $selectedMethodForUi = '';
                    $formSelectedBookingId = '';
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

    if ($tenantId !== '') {
        $today = date('Y-m-d');

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM tbl_payments WHERE tenant_id = ? AND status = 'completed'");
        $stmt->execute([$tenantId]);
        $summaryTotalRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0);

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS today_revenue FROM tbl_payments WHERE tenant_id = ? AND DATE(payment_date) = ? AND status = 'completed'");
        $stmt->execute([$tenantId, $today]);
        $summaryTodayRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['today_revenue'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total_payments FROM tbl_payments WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $summaryTotalPayments = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_payments'] ?? 0);

        $recentSql = "
            SELECT
                py.payment_id,
                py.patient_id,
                py.amount,
                py.payment_date,
                py.payment_method,
                py.status,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name
            FROM tbl_payments py
            LEFT JOIN tbl_patients p
                ON p.tenant_id = py.tenant_id
               AND p.patient_id = py.patient_id
            WHERE py.tenant_id = ?
            ORDER BY py.payment_date DESC, py.id DESC
            LIMIT 20
        ";
        $recentStmt = $pdo->prepare($recentSql);
        $recentStmt->execute([$tenantId]);
        $recentPayments = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
        if ($supportsAppointmentServicesTable && $supportsServiceEnableInstallmentColumn) {
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

        $transactionsSql = "
            SELECT
                a.booking_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                a.service_type,
                {$installmentPlanSelectSql},
                COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
                COALESCE(SUM(CASE WHEN py.status = 'completed' THEN py.amount ELSE 0 END), 0) AS total_paid,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name
            FROM tbl_appointments a
            LEFT JOIN tbl_payments py
                ON py.tenant_id = a.tenant_id
               AND py.booking_id = a.booking_id
            LEFT JOIN tbl_patients p
                ON p.tenant_id = a.tenant_id
               AND p.patient_id = a.patient_id
            WHERE a.tenant_id = ?
              AND COALESCE(a.total_treatment_cost, 0) > 0
              AND LOWER(COALESCE(a.status, '')) <> 'cancelled'
            GROUP BY
                a.booking_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                a.service_type,
                a.total_treatment_cost,
                p.first_name,
                p.last_name
            HAVING (COALESCE(a.total_treatment_cost, 0) - COALESCE(SUM(CASE WHEN py.status = 'completed' THEN py.amount ELSE 0 END), 0)) > 0.009
            ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC
            LIMIT 300
        ";
        $transactionsStmt = $pdo->prepare($transactionsSql);
        $transactionsStmt->execute([$tenantId]);
        $transactionCandidates = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
        foreach ($transactionCandidates as $ic => $candRow) {
            $b = trim((string) ($candRow['booking_id'] ?? ''));
            $transactionCandidates[$ic]['installment_schedule'] = $scheduleByBooking[$b] ?? [];
        }

        $servicesStmt = $pdo->prepare("
            SELECT service_id, service_name, category, price
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
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision - Payment Recording</title>
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
<?php if ($paymentSuccess !== ''): ?>
<section>
<div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-5 py-3 text-sm font-semibold">
<?php echo htmlspecialchars($paymentSuccess, ENT_QUOTES, 'UTF-8'); ?>
</div>
</section>
<?php endif; ?>
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
    $dateLabel = $paymentDateRaw !== '' ? date('M d, Y', strtotime($paymentDateRaw)) : '-';
    $timeLabel = $paymentDateRaw !== '' ? date('h:i A', strtotime($paymentDateRaw)) : '-';
    $methodKey = strtolower(trim((string) ($payment['payment_method'] ?? 'cash')));
    $methodLabel = $allowedMethods[$methodKey] ?? ucfirst(str_replace('_', ' ', $methodKey));
    $statusKey = strtolower(trim((string) ($payment['status'] ?? 'pending')));
    $statusLabel = ucfirst(str_replace('_', ' ', $statusKey));
    $statusClasses = 'bg-amber-50 text-amber-600';
    $dotClass = 'bg-amber-500';
    if ($statusKey === 'completed') {
        $statusClasses = 'bg-emerald-50 text-emerald-600';
        $dotClass = 'bg-emerald-500';
    } elseif ($statusKey === 'cancelled') {
        $statusClasses = 'bg-slate-100 text-slate-600';
        $dotClass = 'bg-slate-500';
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
<span class="inline-flex items-center gap-1.5 px-3 py-1 <?php echo $statusClasses; ?> text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full <?php echo $dotClass; ?>"></span>
<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
</span>
</td>
<td class="px-8 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 hover:bg-primary/10 rounded-lg text-primary transition-colors" title="<?php echo htmlspecialchars((string) ($payment['payment_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" type="button">
<span class="material-symbols-outlined text-sm">visibility</span>
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
<div class="fixed inset-0 z-50 hidden items-center justify-center p-6" id="transaction-modal" role="dialog" aria-modal="true" aria-labelledby="transaction-modal-title">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" id="transaction-modal-overlay"></div>
<div class="relative z-10 w-full max-w-4xl">
<div class="glass-form bg-white p-10 rounded-[2.5rem] shadow-2xl shadow-primary/20 max-h-[88vh] overflow-y-auto no-scrollbar">
<?php if ($paymentError !== ''): ?>
<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 text-red-700 px-5 py-3 text-sm font-semibold">
<?php echo htmlspecialchars($paymentError, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
<div class="flex justify-between items-start mb-10 border-b border-primary/10 pb-6">
<div>
<h3 class="text-3xl font-black font-headline text-slate-900" id="transaction-modal-title">Record New Payment</h3>
<p class="text-xs text-primary font-bold uppercase tracking-[0.2em] mt-1">Submit digital transaction receipt</p>
</div>
<button class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors" id="close-transaction-modal" type="button">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form class="space-y-10" method="post">
<div class="space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Patient Identification</label>
<div class="relative group">
<input name="selected_booking_id" id="selected_booking_id_input" type="hidden" value="<?php echo htmlspecialchars($formSelectedBookingId, ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="patient_query" id="patient_query_input" type="hidden" value="<?php echo htmlspecialchars($formPatientQuery, ENT_QUOTES, 'UTF-8'); ?>"/>
<button id="open-transaction-selector-modal" type="button" class="w-full px-6 py-4 form-input-styled rounded-2xl text-left text-base font-semibold outline-none inline-flex items-center justify-between gap-3">
<span class="inline-flex items-center gap-3 min-w-0">
<span class="material-symbols-outlined text-slate-400">person_search</span>
<span id="selected_transaction_label" class="truncate"><?php echo htmlspecialchars($formPatientQuery !== '' ? $formPatientQuery : 'Select appointment transaction with pending balance', ENT_QUOTES, 'UTF-8'); ?></span>
</span>
<span class="material-symbols-outlined text-slate-500">keyboard_arrow_down</span>
</button>
</div>
<p class="text-[11px] font-semibold text-slate-500 ml-1">Only appointments with pending balance are listed.</p>
</div>
<input name="installment_flow" id="installment_flow_input" type="hidden" value="<?php echo htmlspecialchars($formInstallmentFlow !== '' ? $formInstallmentFlow : 'regular', ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="installment_pay_mode" id="installment_pay_mode_input" type="hidden" value="<?php echo htmlspecialchars($formInstallmentPayMode !== '' ? $formInstallmentPayMode : 'full', ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="installment_slot_count" id="installment_slot_count_input" type="hidden" value="<?php echo (int) max(1, $formInstallmentSlotCount); ?>"/>
<div class="hidden rounded-2xl border border-primary/25 bg-gradient-to-br from-primary/[0.06] to-slate-50/80 p-6 space-y-4" id="installment-plan-panel">
<div class="flex items-center justify-between gap-3 flex-wrap">
<div>
<p class="text-[11px] font-black uppercase tracking-widest text-primary">Installment plan</p>
<h4 class="text-lg font-black text-slate-900 mt-1">Payment progress</h4>
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
<div class="border-t border-slate-200/80 pt-4 space-y-3">
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
<span class="min-w-0"><span class="block text-sm font-extrabold text-slate-900">Down + months ahead</span><span class="block text-xs text-slate-500 mt-0.5">Pay down payment plus one or more monthly installments.</span></span>
</label>
<label class="installment-option-card flex items-start gap-3 p-4 rounded-2xl border-2 border-slate-100 bg-white/80 cursor-pointer hover:border-primary/40 transition-colors has-[:checked]:border-primary has-[:checked]:bg-primary/5" id="inst_opt_monthly_wrap">
<input type="radio" name="installment_pay_mode_ui" value="monthly" class="mt-1 text-primary focus:ring-primary/30" id="inst_opt_monthly"/>
<span class="min-w-0"><span class="block text-sm font-extrabold text-slate-900">Monthly payment</span><span class="block text-xs text-slate-500 mt-0.5">Pay one or more upcoming monthly installments (after down is paid).</span></span>
</label>
</div>
<div class="flex flex-col sm:flex-row sm:items-center gap-3 pt-1" id="installment_slot_row">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 sm:min-w-[10rem]">Installments to pay</label>
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
<input class="w-full pl-12 pr-6 py-4 form-input-styled rounded-2xl text-xl font-black outline-none" min="0.01" name="amount" placeholder="0.00" required step="0.01" type="number" value="<?php echo htmlspecialchars($formAmount, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
</div>
<div class="hidden md:block h-12 w-px bg-slate-200 mt-6"></div>
<div class="flex-1 w-full space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Transaction Date</label>
<div class="relative group">
<input class="w-full px-6 py-4 form-input-styled rounded-2xl text-base font-semibold outline-none" max="<?php echo date('Y-m-d'); ?>" name="payment_date" required type="date" value="<?php echo htmlspecialchars($formPaymentDate, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
</div>
</div>
<div class="space-y-4" id="additional-services-section">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Additional Services</label>
<div class="rounded-2xl border border-slate-200 bg-white/70 p-4 space-y-3">
<p class="text-[11px] font-semibold text-slate-500">Optional: add on-the-spot services. Selected services are added to treatment cost and reflected in schedule services.</p>
<div class="max-h-52 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/40 p-2 space-y-1.5">
<?php if (empty($availableServices)): ?>
<p class="px-2 py-2 text-sm font-semibold text-slate-500">No active services available.</p>
<?php else: ?>
<?php foreach ($availableServices as $service): ?>
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
<label class="flex items-center justify-between gap-3 p-2.5 rounded-lg hover:bg-white border border-transparent hover:border-slate-200">
<span class="flex items-start gap-3 min-w-0">
<input class="additional-service-checkbox mt-1 rounded border-slate-300 text-primary focus:ring-primary/30" data-service-price="<?php echo htmlspecialchars((string) $servicePrice, ENT_QUOTES, 'UTF-8'); ?>" name="additional_service_ids[]" type="checkbox" value="<?php echo htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isChecked ? ' checked' : ''; ?>/>
<span class="min-w-0">
<span class="block text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="block text-xs text-slate-500"><?php echo htmlspecialchars($serviceCategory, ENT_QUOTES, 'UTF-8'); ?></span>
</span>
</span>
<span class="text-sm font-black text-primary">₱<?php echo htmlspecialchars(number_format($servicePrice, 2), ENT_QUOTES, 'UTF-8'); ?></span>
</label>
<?php endforeach; ?>
<?php endif; ?>
</div>
<p id="additional_services_total_hint" class="text-xs font-bold text-slate-600">Added services total: ₱0.00</p>
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
        const hasServerError = <?php echo $paymentError !== '' ? 'true' : 'false'; ?>;
        const transactionCandidates = <?php echo json_encode($transactionCandidates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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
        const patientQueryInput = document.getElementById('patient_query_input');
        const selectedTransactionLabel = document.getElementById('selected_transaction_label');
        const amountInput = document.querySelector('input[name="amount"]');
        const additionalServiceCheckboxes = document.querySelectorAll('.additional-service-checkbox');
        const additionalServicesTotalHint = document.getElementById('additional_services_total_hint');
        const installmentPlanPanel = document.getElementById('installment-plan-panel');
        const installmentFlowInput = document.getElementById('installment_flow_input');
        const installmentPayModeInput = document.getElementById('installment_pay_mode_input');
        const installmentSlotCountInput = document.getElementById('installment_slot_count_input');
        const installmentProgressBar = document.getElementById('installment_progress_bar');
        const installmentProgressPctLabel = document.getElementById('installment_progress_pct_label');
        const installmentProgressPaidLine = document.getElementById('installment_progress_paid_line');
        const installmentProgressRemainLine = document.getElementById('installment_progress_remain_line');
        const installmentProgressHint = document.getElementById('installment_progress_hint');
        const installmentSlotRow = document.getElementById('installment_slot_row');
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
        let selectedTransaction = null;

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

        function refreshInstallmentPaymentUi() {
            if (!selectedTransaction) {
                if (installmentPlanPanel) {
                    installmentPlanPanel.classList.add('hidden');
                }
                if (installmentFlowInput) {
                    installmentFlowInput.value = 'regular';
                }
                if (amountInput) {
                    amountInput.removeAttribute('readonly');
                }
                if (additionalServicesSection) {
                    additionalServicesSection.classList.remove('opacity-50', 'pointer-events-none');
                }
                return;
            }
            const sched = getScheduleList(selectedTransaction);
            const hasSchedule = sched.length > 0;
            if (installmentFlowInput) {
                installmentFlowInput.value = hasSchedule ? 'schedule' : 'regular';
            }
            if (!installmentPlanPanel) {
                return;
            }
            if (!hasSchedule) {
                installmentPlanPanel.classList.add('hidden');
                if (amountInput) {
                    amountInput.removeAttribute('readonly');
                }
                if (additionalServicesSection) {
                    additionalServicesSection.classList.remove('opacity-50', 'pointer-events-none');
                }
                return;
            }

            installmentPlanPanel.classList.remove('hidden');
            if (instOptFull && !document.querySelector('input[name="installment_pay_mode_ui"]:checked')) {
                instOptFull.checked = true;
            }
            if (additionalServicesSection) {
                additionalServicesSection.classList.add('opacity-50', 'pointer-events-none');
            }
            if (amountInput) {
                amountInput.setAttribute('readonly', 'readonly');
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

            let downLabel = '';
            const inst1 = sched.find((r) => Number(r.installment_number) === 1);
            if (inst1 && !installmentStatusPaid(inst1.status)) {
                downLabel = 'Down payment (installment 1) is pending.';
            } else if (inst1 && installmentStatusPaid(inst1.status)) {
                downLabel = 'Down payment (installment 1) is paid.';
            }
            const unpaidSched = sched.filter((r) => !installmentStatusPaid(r.status));
            const settled = sched.length - unpaidSched.length;
            if (installmentProgressHint) {
                installmentProgressHint.textContent = downLabel
                    ? (downLabel + ' ' + settled + ' of ' + sched.length + ' installment line(s) settled.')
                    : (settled + ' of ' + sched.length + ' installment line(s) settled.');
            }

            const firstUnpaid = unpaidSched.length ? unpaidSched[0] : null;
            const fn = firstUnpaid ? Number(firstUnpaid.installment_number) : 0;

            if (instOptDownWrap) {
                instOptDownWrap.classList.toggle('opacity-40', fn !== 1);
                instOptDownWrap.classList.toggle('pointer-events-none', fn !== 1);
            }
            if (instOptCombinedWrap) {
                instOptCombinedWrap.classList.toggle('opacity-40', fn !== 1 || unpaidSched.length < 2);
                instOptCombinedWrap.classList.toggle('pointer-events-none', fn !== 1 || unpaidSched.length < 2);
            }
            if (instOptMonthlyWrap) {
                instOptMonthlyWrap.classList.toggle('opacity-40', fn < 2);
                instOptMonthlyWrap.classList.toggle('pointer-events-none', fn < 2);
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
                slotCount = Math.max(2, parseInt(String(installmentSlotStepper ? installmentSlotStepper.value : '2'), 10) || 2);
                slotCount = Math.min(maxSlots, Math.max(2, slotCount));
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
                        installmentSlotStepper.min = '2';
                        installmentSlotStepper.max = String(Math.max(2, maxSlots));
                        if (installmentSlotRangeHint) {
                            installmentSlotRangeHint.textContent = '(2 – ' + maxSlots + ' installments)';
                        }
                        installmentSlotStepper.value = String(Math.min(maxSlots, Math.max(2, slotCount)));
                    } else {
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
            if (amountInput) {
                amountInput.value = sum.toFixed(2);
            }
        }

        const normalizeTransactions = transactionCandidates.map((item) => {
            const totalCost = Number(item.total_treatment_cost || 0);
            const totalPaid = Number(item.total_paid || 0);
            const pendingBalance = Math.max(0, totalCost - totalPaid);
            const firstName = String(item.patient_first_name || '').trim();
            const lastName = String(item.patient_last_name || '').trim();
            const patientName = (firstName + ' ' + lastName).trim() || 'Unknown Patient';
            const label = patientName + ' | Booking ' + (item.booking_id || '-') + ' | Pending ₱' + pendingBalance.toFixed(2);
            const rawPlan = item.is_installment_plan;
            const isInstallmentPlan = rawPlan === true || rawPlan === 1 || rawPlan === '1';
            return {
                booking_id: String(item.booking_id || ''),
                patient_id: String(item.patient_id || ''),
                patient_name: patientName,
                service_type: String(item.service_type || '-'),
                appointment_date: String(item.appointment_date || ''),
                appointment_time: String(item.appointment_time || ''),
                total_cost: totalCost,
                total_paid: totalPaid,
                pending_balance: pendingBalance,
                is_installment_plan: isInstallmentPlan,
                installment_schedule: Array.isArray(item.installment_schedule) ? item.installment_schedule : [],
                label: label
            };
        }).filter((item) => item.pending_balance > 0);

        function filterTransactionsByType(list) {
            if (transactionTypeFilter === 'installment') {
                return list.filter((item) => item.is_installment_plan);
            }
            return list.filter((item) => !item.is_installment_plan);
        }

        function filterTransactionsByKeyword(list, keyword) {
            if (!keyword) {
                return list;
            }
            return list.filter((item) => {
                return [
                    item.patient_name,
                    item.patient_id,
                    item.booking_id,
                    item.service_type
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
            transactionTypeFilter = mode === 'installment' ? 'installment' : 'regular';
            if (transactionTypeToggle) {
                transactionTypeToggle.setAttribute('data-active', transactionTypeFilter);
            }
            if (transactionTypeRegularBtn) {
                transactionTypeRegularBtn.setAttribute('aria-pressed', transactionTypeFilter === 'regular' ? 'true' : 'false');
            }
            if (transactionTypeInstallmentBtn) {
                transactionTypeInstallmentBtn.setAttribute('aria-pressed', transactionTypeFilter === 'installment' ? 'true' : 'false');
            }
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

        function renderTransactionRows(list) {
            if (!selectorList || !selectorEmpty) return;
            if (!list.length) {
                selectorList.innerHTML = '';
                selectorEmpty.classList.remove('hidden');
                return;
            }

            selectorEmpty.classList.add('hidden');
            selectorList.innerHTML = list.map((item) => {
                return '' +
                    '<div class="py-3 px-1 sm:px-2">' +
                        '<div class="rounded-2xl border border-slate-200 p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">' +
                            '<div class="min-w-0">' +
                                '<p class="text-sm font-extrabold text-slate-900 truncate">' + escapeHtml(item.patient_name) + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Patient ID: ' + escapeHtml(item.patient_id) + ' | Booking ID: ' + escapeHtml(item.booking_id) + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Service: ' + escapeHtml(item.service_type) + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Date: ' + escapeHtml(item.appointment_date || '-') + ' ' + escapeHtml(item.appointment_time || '') + '</p>' +
                                '<p class="text-xs font-semibold text-slate-700 mt-1">Total: ₱' + item.total_cost.toFixed(2) + ' | Paid: ₱' + item.total_paid.toFixed(2) + ' | Pending: ₱' + item.pending_balance.toFixed(2) + '</p>' +
                            '</div>' +
                            '<button type="button" data-action="select-transaction" data-booking-id="' + escapeHtml(item.booking_id) + '" class="shrink-0 px-4 py-2.5 rounded-xl bg-primary text-white text-xs font-black uppercase tracking-widest hover:bg-primary/90 transition-colors">Select</button>' +
                        '</div>' +
                    '</div>';
            }).join('');
        }

        function getAdditionalServicesTotal() {
            let total = 0;
            additionalServiceCheckboxes.forEach((checkbox) => {
                if (!checkbox.checked) {
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
                additionalServicesTotalHint.textContent = 'Added services total: ₱' + servicesTotal.toFixed(2);
            }
            if (selectedTransaction && amountInput) {
                if (getScheduleList(selectedTransaction).length > 0) {
                    refreshInstallmentPaymentUi();
                    return;
                }
                const basePending = Number(selectedTransaction.pending_balance || 0);
                amountInput.value = Math.max(0, basePending + servicesTotal).toFixed(2);
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

        const openModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        };

        const closeModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        };

        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        if (overlay) {
            overlay.addEventListener('click', closeModal);
        }
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        if (hasServerError) {
            openModal();
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
                const bookingId = String(btn.getAttribute('data-booking-id') || '');
                const selected = normalizeTransactions.find((item) => item.booking_id === bookingId);
                if (!selected) {
                    return;
                }
                if (selectedBookingIdInput) {
                    selectedBookingIdInput.value = selected.booking_id;
                }
                if (patientQueryInput) {
                    patientQueryInput.value = selected.label;
                }
                if (selectedTransactionLabel) {
                    selectedTransactionLabel.textContent = selected.label;
                }
                if (amountInput) {
                    selectedTransaction = selected;
                    syncAmountWithAdditionalServices();
                    refreshInstallmentPaymentUi();
                }
                closeSelectorModal();
            });
        }
        additionalServiceCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', syncAmountWithAdditionalServices);
        });
        document.querySelectorAll('input[name="installment_pay_mode_ui"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                refreshInstallmentPaymentUi();
            });
        });
        if (installmentSlotStepper) {
            installmentSlotStepper.addEventListener('input', () => {
                refreshInstallmentPaymentUi();
            });
        }
        syncAmountWithAdditionalServices();
        refreshInstallmentPaymentUi();

        const hiddenInput = document.getElementById('payment_method_input');
        const cards = document.querySelectorAll('.payment-card[data-method]');
        cards.forEach((card) => {
            card.addEventListener('click', () => {
                cards.forEach((other) => other.classList.remove('active'));
                card.classList.add('active');
                if (hiddenInput) {
                    hiddenInput.value = card.getAttribute('data-method') || '';
                }
            });
        });
    })();
</script>
</body></html>