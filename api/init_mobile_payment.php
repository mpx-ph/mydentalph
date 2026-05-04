<?php

declare(strict_types=1);

// api/init_mobile_payment.php — mobile PayMongo; writes treatment ledger + linkage like staff bookings.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../clinic/includes/patient_booking_slots.php';
require_once __DIR__ . '/../clinic/includes/appointment_booking_row.php';
require_once __DIR__ . '/../clinic/includes/booking_treatment_ledger.php';
require_once __DIR__ . '/../clinic/includes/staff_installment_helpers.php';
require_once __DIR__ . '/includes/mobile_wallet_payment.inc.php';
require_once __DIR__ . '/../paymongo_config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "message" => "POST required"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$user_id = $input['user_id'] ?? null;
$tenant_id = trim((string) ($input['tenant_id'] ?? 'TNT_00025'));
$appointment_fallback_ymd = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$appointment_date = booking_normalize_mobile_date_input($input['appointment_date'] ?? null, $appointment_fallback_ymd);
if ($appointment_date === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
    die(json_encode(["status" => "error", "message" => "Missing or invalid appointment_date"]));
}

$appointment_time = $input['appointment_time'] ?? null;
$dentist_id_in = isset($input['dentist_id']) ? (int) $input['dentist_id'] : 1;
$services_json = $input['services'] ?? '[]';
$total_amount = isset($input['total_amount']) ? (float) $input['total_amount'] : 0.0;
$payment_amount = isset($input['payment_amount']) ? (float) $input['payment_amount'] : 0.0;
$wallet_amount_in = isset($input['wallet_amount']) ? (float) $input['wallet_amount'] : 0.0;
/** Patient-facing obligation after discounts (full booking). When set, downpayment vs full is based on this, not gross total_amount. */
$discounted_subtotal_in = isset($input['discounted_subtotal']) ? (float) $input['discounted_subtotal'] : 0.0;

$schedule_followup_only = !empty($input['schedule_followup_only']);
$existing_treatment_id_in = trim((string) ($input['existing_treatment_id'] ?? ''));
$plan_booking_id_in = trim((string) ($input['plan_booking_id'] ?? ''));
$installment_number_for_slot = (int) ($input['installment_number_for_slot'] ?? 0);

if ($schedule_followup_only) {
    $payment_amount = 0.0;
    $wallet_amount_in = 0.0;
    if ($existing_treatment_id_in === '' || $plan_booking_id_in === '' || $installment_number_for_slot < 1) {
        die(json_encode([
            'status' => 'error',
            'message' => 'Missing plan follow-up fields (existing_treatment_id, plan_booking_id, installment_number_for_slot).',
        ]));
    }
}

if (!$user_id || !$appointment_time) {
    die(json_encode(["status" => "error", "message" => "Missing required fields"]));
}

$paymongo_secret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
$needs_paymongo = !$schedule_followup_only && $payment_amount > 0.009;
if ($needs_paymongo && $paymongo_secret === '') {
    die(json_encode(["status" => "error", "message" => "PayMongo Secret Key not configured on the server."]));
}

try {
    /** Mobile app sends `patient_id` (holder or dependent). The old query used LIMIT 1 and always picked one arbitrary row. */
    $patient_id_in = trim((string) ($input['patient_id'] ?? ''));

    if ($patient_id_in !== '') {
        $stmt = $pdo->prepare(
            'SELECT p.patient_id, p.first_name, p.last_name, u.email, u.phone
            FROM tbl_patients p
            LEFT JOIN tbl_users u ON u.user_id = ?
            WHERE (p.owner_user_id = ? OR p.linked_user_id = ?)
              AND TRIM(p.patient_id) = ?
            LIMIT 1'
        );
        $stmt->execute([(string) $user_id, $user_id, $user_id, $patient_id_in]);
        $patRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$patRow) {
            throw new Exception('That patient is not on your account. Pick yourself or a dependent from the list.');
        }
    } else {
        $stmt = $pdo->prepare(
            'SELECT p.patient_id, p.first_name, p.last_name, u.email, u.phone
            FROM tbl_patients p
            LEFT JOIN tbl_users u ON u.user_id = ?
            WHERE p.owner_user_id = ? OR p.linked_user_id = ?
            LIMIT 1'
        );
        $stmt->execute([(string) $user_id, $user_id, $user_id]);
        $patRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$patRow) {
            throw new Exception("Patient profile not found for this user. Please complete your registration.");
        }
    }
    $patient_id = trim((string) ($patRow['patient_id'] ?? ''));
    $patient_name = trim(trim((string) ($patRow['first_name'] ?? '')) . ' ' . trim((string) ($patRow['last_name'] ?? '')));
    $user_email = !empty($patRow['email']) ? trim((string) $patRow['email']) : 'patient@mydentalph.com';
    $user_phone = trim((string) ($patRow['phone'] ?? ''));

    $dentist_id = patient_booking_resolve_mobile_dentist_choice(
        $pdo,
        (string) $tenant_id,
        (string) $appointment_date,
        (string) $appointment_time,
        $dentist_id_in
    );
    if ($dentist_id <= 0) {
        throw new Exception(
            'No dentist is available for this date and time. Please choose another slot or pick a specific dentist.'
        );
    }

    if ($schedule_followup_only) {
        mobile_api_schedule_followup_only_booking(
            $pdo,
            (string) $tenant_id,
            $user_id,
            $patient_id,
            $patient_name,
            (int) $dentist_id,
            (string) $appointment_date,
            (string) $appointment_time,
            $input,
            $services_json,
            $existing_treatment_id_in,
            $plan_booking_id_in,
            $installment_number_for_slot
        );
    }

    $booking_id = 'BK-' . strtoupper(substr(md5((string) microtime(true) . $user_id . mt_rand()), 0, 10));

    $tables = clinic_resolve_appointment_db_tables($pdo);
    $apptPhys = $tables['appointments'] ?? 'tbl_appointments';
    $apptQuoted = clinic_quote_identifier((string) $apptPhys);

    $stmt = $pdo->prepare(
        "SELECT id FROM {$apptQuoted}
         WHERE dentist_id = ? AND appointment_date = ? AND appointment_time = ?
           AND LOWER(COALESCE(status, '')) NOT IN ('cancelled')
         LIMIT 1"
    );
    $stmt->execute([(int) $dentist_id, $appointment_date, $appointment_time]);
    if ($stmt->fetch()) {
        throw new Exception("This time slot is already reserved. Please choose a different time.");
    }

    $services = is_array($services_json) ? $services_json : json_decode((string) $services_json, true);
    if (!is_array($services)) {
        $services = [];
    }

    $apptExtras = clinic_appointment_extras_for_booking($pdo, $tenant_id, $services, (string) $appointment_date);
    $apptExtras = booking_merge_extras_from_mobile_input($apptExtras, $input);
    $apptExtras = booking_ensure_long_term_schedule($apptExtras, (string) $appointment_date);

    $pdo->beginTransaction();

    $treatment_cart_total = ($discounted_subtotal_in > 0.009) ? $discounted_subtotal_in : $total_amount;

    $ledger = booking_create_treatment_row(
        $pdo,
        $tenant_id,
        $patient_id,
        (string) $user_id,
        $booking_id,
        $services,
        $treatment_cart_total,
        $apptExtras,
        (string) $appointment_date
    );
    $treatment_id_fk = trim((string) ($ledger['treatment_id'] ?? ''));
    $treatmentFkOrNull = $treatment_id_fk !== '' ? $treatment_id_fk : null;
    $installmentForAppointment = (($ledger['installment_number'] ?? 0) > 0 && $treatmentFkOrNull !== null)
        ? (int) $ledger['installment_number']
        : null;

    $notesCombined = trim((string) ($input['notes'] ?? ''));
    if ($treatmentFkOrNull !== null) {
        $notesCombined .= ($notesCombined !== '' ? "\n" : '') . 'treatment_id: ' . $treatmentFkOrNull;
    }

    $ledgerDurationMonths = (int) ($ledger['duration_months'] ?? 0);
    $durationForAppointment = isset($apptExtras['duration_months']) ? (int) $apptExtras['duration_months'] : null;
    if ($treatmentFkOrNull !== null && $ledgerDurationMonths > 0) {
        // Match tbl_treatments (catalog + mobile extras; no hard-coded month defaults).
        $durationForAppointment = $ledgerDurationMonths;
    }

    $apptVals = [
        'tenant_id' => $tenant_id,
        'dentist_id' => (int) $dentist_id,
        'booking_id' => $booking_id,
        'patient_id' => $patient_id,
        'treatment_id' => $treatmentFkOrNull,
        'appointment_date' => $appointment_date,
        'appointment_time' => $appointment_time,
        'status' => 'confirmed',
        'visit_type' => 'pre_book',
        'created_by' => (string) $user_id,
        'service_type' => $apptExtras['service_type'] ?? null,
        'service_description' => $apptExtras['service_description'] ?? null,
        'treatment_type' => $apptExtras['treatment_type'] ?? 'short_term',
        'total_treatment_cost' => $treatment_cart_total > 0 ? $treatment_cart_total : null,
        'duration_months' => $durationForAppointment,
        'target_completion_date' => $apptExtras['target_completion_date'] ?? null,
        'start_date' => $apptExtras['start_date'] ?? $appointment_date,
        'notes' => $notesCombined !== '' ? $notesCombined : null,
        'installment_number' => $installmentForAppointment,
    ];

    $apptVals = booking_coerce_appointment_date_columns($pdo, (string) $apptPhys, $apptVals);

    booking_mobile_dynamic_insert_appointment($pdo, (string) $apptPhys, $apptVals);
    $appointment_id = (int) $pdo->lastInsertId();

    $appointmentServicesPhys = $tables['appointment_services'] ?? null;
    if ($appointmentServicesPhys !== null) {
        $apsQuoted = clinic_quote_identifier((string) $appointmentServicesPhys);
        $asc = clinic_table_columns($pdo, (string) $appointmentServicesPhys);
        $ledgerRows = $ledger['enriched_rows'] ?? [];
        if ($ledgerRows === [] && $services !== []) {
            $ledgerRows = booking_enrich_mobile_service_rows($pdo, $tenant_id, $services);
        }

        foreach ($ledgerRows as $er) {
            $sid = trim((string) ($er['service_id'] ?? ''));
            $stype = !empty($er['is_installment_line']) ? 'installment' : 'regular';
            $label = $stype === 'installment' ? 'Long Term' : 'Short Term';
            $svcName = (string) ($er['service_name'] ?? '');
            $price = isset($er['price']) ? (float) $er['price'] : 0.0;

            $colParts = [];
            $ph = [];
            $bind = [];

            foreach (
                [
                    'tenant_id' => $tenant_id,
                    'booking_id' => $booking_id,
                    'appointment_id' => $appointment_id > 0 ? $appointment_id : null,
                    'treatment_id' => ($treatmentFkOrNull !== null && $stype === 'installment') ? $treatmentFkOrNull : null,
                    'service_id' => $sid !== '' ? $sid : null,
                    'service_name' => $svcName,
                    'price' => $price,
                    'service_type' => $stype,
                    'type' => $label,
                    'is_original' => 1,
                    'added_by' => (string) $user_id,
                ] as $nm => $val
            ) {
                $lc = strtolower((string) $nm);
                if (!in_array($lc, $asc, true)) {
                    continue;
                }
                $colParts[] = clinic_quote_identifier($lc);
                $ph[] = '?';
                $bind[] = $val;
            }
            if (in_array('added_at', $asc, true)) {
                $colParts[] = clinic_quote_identifier('added_at');
                $ph[] = 'NOW()';
            }
            if ($colParts !== []) {
                $sqlIns = 'INSERT INTO ' . $apsQuoted . ' (' . implode(', ', $colParts) . ') VALUES (' . implode(', ', $ph) . ')';
                $insSt = $pdo->prepare($sqlIns);
                $insSt->execute($bind);
            }
        }
    }

    $payment_id = 'PAY-' . strtoupper(substr(md5((string) microtime(true) . $booking_id . mt_rand()), 0, 10));
    $obligation_total = ($discounted_subtotal_in > 0.009) ? $discounted_subtotal_in : $total_amount;
    $paid_now_total = $wallet_amount_in + $payment_amount;
    // Full payment when wallet + online covers the discounted obligation (not gross catalog total).
    $payment_type = ($paid_now_total + 0.02 >= $obligation_total) ? 'fullpayment' : 'downpayment';
    $payNotes = isset($ledger['payment_notes'])
        ? (string) $ledger['payment_notes']
        : sprintf('Mobile booking %s (PayMongo pending).', $booking_id);
    if ($wallet_amount_in > 0.009) {
        $payNotes .= ($payNotes !== '' ? ' ' : '')
            . sprintf('[Wallet applied: ₱%s]', number_format($wallet_amount_in, 2, '.', ''));
    }

    /**
     * Persist ENUM-safe values: wallet-only → `check`, combo → `gcash` (notes carry `[Wallet applied: …]`).
     * Staff/app labels use staff_payment_recording_format_payment_method_display(..., notes).
     */
    $clientPm = strtolower(trim((string) ($input['payment_method'] ?? 'paymongo')));
    if ($clientPm === 'wallet') {
        $stored_payment_method = 'check';
    } elseif ($clientPm === 'wallet+paymongo') {
        $stored_payment_method = 'gcash';
    } else {
        $stored_payment_method = 'gcash';
    }

    $paymentsPhys = $tables['payments'] ?? 'tbl_payments';
    $ppc = clinic_table_columns($pdo, (string) $paymentsPhys);
    $pq = clinic_quote_identifier((string) $paymentsPhys);

    /** PayMongo row tracks gateway amount; wallet-only must persist total paid for staff/receipts (SUM completed.amount). */
    $payment_insert_amount = $needs_paymongo ? round($payment_amount, 2) : round($paid_now_total, 2);
    /** No redirect checkout: mark settled immediately when something was captured (wallet or zero-edge free flows stay pending). */
    $payment_insert_status = (!$needs_paymongo && $payment_insert_amount > 0.009) ? 'completed' : 'pending';

    $payColsIns = [];
    $payBind = [];
    $map = [
        'tenant_id' => $tenant_id,
        'payment_id' => $payment_id,
        'patient_id' => $patient_id,
        'booking_id' => $booking_id,
        'treatment_id' => $treatmentFkOrNull,
        'installment_number' => (($ledger['installment_number'] ?? 0) > 0) ? (int) $ledger['installment_number'] : null,
        'amount' => $payment_insert_amount,
        'payment_method' => $stored_payment_method,
        'status' => $payment_insert_status,
        'created_by' => (string) $user_id,
        'payment_type' => $payment_type,
        'notes' => $payNotes,
        'reference_number' => null,
    ];
    $phParts = [];
    foreach ($map as $c => $val) {
        $lc = strtolower((string) $c);
        if (!in_array($lc, $ppc, true)) {
            continue;
        }
        if ($lc === 'reference_number' && ($val === null || $val === '')) {
            continue;
        }
        $payColsIns[] = clinic_quote_identifier($lc);
        $phParts[] = '?';
        $payBind[] = $val;
    }
    $paymentInserted = false;
    if ($payColsIns !== []) {
        $psql = 'INSERT INTO ' . $pq . ' (' . implode(', ', $payColsIns) . ') VALUES (' . implode(', ', $phParts) . ')';
        $pst = $pdo->prepare($psql);
        $pst->execute($payBind);
        $paymentInserted = true;
    }

    if ($paymentInserted && !$needs_paymongo && $wallet_amount_in > 0.009) {
        mobile_wallet_apply_payment_debit(
            $pdo,
            $tenant_id,
            $patient_id,
            $wallet_amount_in,
            $payment_id,
            $booking_id,
            (string) $user_id,
        );
        $stFreshPay = $pdo->prepare('SELECT * FROM ' . $pq . ' WHERE payment_id = ? LIMIT 1');
        $stFreshPay->execute([$payment_id]);
        $freshPayRow = $stFreshPay->fetch(PDO::FETCH_ASSOC);
        if (is_array($freshPayRow) && $freshPayRow !== []) {
            booking_apply_completed_payment_to_treatment($pdo, $freshPayRow);
            staff_installments_mark_paid_from_mobile_payment_row($pdo, $freshPayRow);
        }
    }

    $pdo->commit();

    if (!$needs_paymongo) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Booking saved.',
            'booking_id' => $booking_id,
            'treatment_id' => $treatment_id_fk,
            'checkout_url' => '',
        ]);
        exit;
    }

    $description = $payment_type === 'downpayment'
        ? 'Downpayment for Appointment'
        : 'Full Payment for Appointment';
    $amount_in_cents = (int) round($payment_amount * 100);

    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    $baseApi = ($host !== '' ? "{$scheme}://{$host}" : 'http://mydentalph.ct.ws') . '/api';

    $paymongo_data = [
        'data' => [
            'attributes' => [
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'payment_method_types' => ['gcash', 'paymaya', 'card'],
                'description' => "$description ($booking_id) - $patient_name",
                'success_url' => $baseApi . '/payment_success.php?pid=' . rawurlencode($payment_id),
                'billing' => [
                    'name' => $patient_name,
                    'email' => $user_email,
                    'phone' => $user_phone,
                ],
                'line_items' => [
                    [
                        'currency' => 'PHP',
                        'amount' => $amount_in_cents,
                        'description' => 'Payment for Booking',
                        'name' => 'Dental Services',
                        'quantity' => 1,
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymongo_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($paymongo_secret . ':'),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $respond = static function (
        array $payload
    ): void {
        echo json_encode($payload);
        exit;
    };

    if ($curlError) {
        $respond([
            'status' => 'success',
            'message' => 'Booking saved, PayMongo checkout failed: ' . $curlError,
            'booking_id' => $booking_id,
            'treatment_id' => $treatment_id_fk,
        ]);
    }

    $responseData = json_decode((string) $response, true);

    if (
        $httpCode >= 200
        && $httpCode < 300
        && is_array($responseData)
        && isset($responseData['data']['attributes']['checkout_url'])
    ) {
        $checkout_url = $responseData['data']['attributes']['checkout_url'];
        $paymongo_reference_number = (string) $responseData['data']['id'];
        $stmt = $pdo->prepare('UPDATE ' . clinic_quote_identifier((string) $paymentsPhys)
            . ' SET reference_number = ? WHERE payment_id = ?');
        $stmt->execute([$paymongo_reference_number, $payment_id]);

        $respond([
            'status' => 'success',
            'message' => 'Booking successful, redirecting to payment...',
            'booking_id' => $booking_id,
            'treatment_id' => $treatment_id_fk,
            'checkout_url' => $checkout_url,
        ]);
    }

    $paymongoErrorMsg = (is_array($responseData) && isset($responseData['errors'][0]['detail']))
        ? (string) $responseData['errors'][0]['detail']
        : ('Unknown PayMongo Error (' . $httpCode . ')');

    $respond([
        'status' => 'success',
        'message' => 'Booking saved, checkout session failed: ' . $paymongoErrorMsg,
        'booking_id' => $booking_id,
        'treatment_id' => $treatment_id_fk,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}

/**
 * Long-term plan visit already paid via installments: create appointment under existing treatment, no PayMongo.
 */
function mobile_api_schedule_followup_only_booking(
    PDO $pdo,
    string $tenant_id,
    mixed $user_id,
    string $patient_id,
    string $patient_name,
    int $dentist_id,
    string $appointment_date,
    string $appointment_time,
    array $input,
    mixed $services_json,
    string $existing_treatment_id,
    string $plan_booking_id,
    int $installment_number_for_slot,
): void {
    $tenant_id = trim($tenant_id);
    $patient_id = trim($patient_id);
    $existing_treatment_id = trim($existing_treatment_id);
    $plan_booking_id = trim($plan_booking_id);

    $tables = clinic_resolve_appointment_db_tables($pdo);
    $apptPhys = $tables['appointments'] ?? 'tbl_appointments';
    $apptQuoted = clinic_quote_identifier((string) $apptPhys);
    $treatmentsPhys = $tables['treatments'] ?? 'tbl_treatments';

    $stmt = $pdo->prepare(
        "SELECT id FROM {$apptQuoted}
         WHERE dentist_id = ? AND appointment_date = ? AND appointment_time = ?
           AND LOWER(COALESCE(status, '')) NOT IN ('cancelled')
         LIMIT 1"
    );
    $stmt->execute([(int) $dentist_id, $appointment_date, $appointment_time]);
    if ($stmt->fetch()) {
        throw new Exception('This time slot is already reserved. Please choose a different time.');
    }

    $qt = clinic_quote_identifier((string) $treatmentsPhys);
    $tstmt = $pdo->prepare(
        "SELECT COALESCE(duration_months, 0) AS duration_months, COALESCE(total_cost, 0) AS total_cost
         FROM {$qt}
         WHERE tenant_id = ?
           AND patient_id = ?
           AND treatment_id = ?
         LIMIT 1"
    );
    $tstmt->execute([$tenant_id, $patient_id, $existing_treatment_id]);
    $tRow = $tstmt->fetch(PDO::FETCH_ASSOC);
    if (!$tRow) {
        throw new Exception('Treatment not found for this patient.');
    }

    $installPhys = clinic_get_physical_table_name($pdo, 'tbl_installments')
        ?? clinic_get_physical_table_name($pdo, 'installments')
        ?? '';
    if ($installPhys === '') {
        throw new Exception('Installments table is not available.');
    }

    /**
     * Treatment Progress may expose the latest tied appointment booking_id while tbl_installments
     * stays on the originating plan booking_id — those differ. Prefer client id when installments
     * exist under it; else fall back to server-resolved plan booking_id.
     */
    $resolvedBid = trim((string) staff_installments_resolve_plan_booking_id_for_patient_treatment(
        $pdo,
        $tenant_id,
        $patient_id,
        $existing_treatment_id,
        (string) $apptPhys,
        (string) $installPhys
    ));

    $qi = clinic_quote_identifier((string) $installPhys);
    $tenantClause = '(i.tenant_id = ? OR i.tenant_id IS NULL OR TRIM(COALESCE(i.tenant_id, \'\')) = \'\')';
    $installmentRowExists = static function (string $bid) use (
        $pdo,
        $qi,
        $tenantClause,
        $tenant_id,
        $installment_number_for_slot
    ): bool {
        $bid = trim($bid);
        if ($bid === '') {
            return false;
        }
        $st = $pdo->prepare(
            "SELECT 1 FROM {$qi} i
             WHERE LOWER(TRIM(COALESCE(i.booking_id, ''))) = LOWER(?)
               AND i.installment_number = ?
               AND {$tenantClause}
             LIMIT 1"
        );
        $st->execute([$bid, $installment_number_for_slot, $tenant_id]);

        return (bool) $st->fetchColumn();
    };

    $effectivePlanBookingId = trim((string) $plan_booking_id);
    if (!$installmentRowExists($effectivePlanBookingId) && $resolvedBid !== ''
        && strcasecmp($resolvedBid, $effectivePlanBookingId) !== 0) {
        if ($installmentRowExists($resolvedBid)) {
            $effectivePlanBookingId = $resolvedBid;
        }
    }
    if (!$installmentRowExists($effectivePlanBookingId)) {
        throw new Exception(
            'Could not locate this installment on your treatment schedule. Staff may need to link your plan booking to appointments, or reopen Treatment Progress after data is fixed.'
        );
    }

    $services = is_array($services_json) ? $services_json : json_decode((string) $services_json, true);
    if (!is_array($services)) {
        $services = [];
    }

    $durTr = max(0, (int) ($tRow['duration_months'] ?? 0));
    if ($durTr >= 1) {
        $input['treatment_type'] = 'long_term';
        if (empty($input['duration_months']) || (int) $input['duration_months'] < 1) {
            $input['duration_months'] = $durTr;
        }
    }

    $treatment_cart_total = (float) ($tRow['total_cost'] ?? 0.0);
    if ($treatment_cart_total <= 0.0) {
        $treatment_cart_total = max(0.0, (float) ($input['total_amount'] ?? 0.0));
    }

    $apptExtras = clinic_appointment_extras_for_booking($pdo, $tenant_id, $services, (string) $appointment_date);
    $apptExtras = booking_merge_extras_from_mobile_input($apptExtras, $input);
    $apptExtras = booking_ensure_long_term_schedule($apptExtras, (string) $appointment_date);

    $ledgerDurationMonths = (int) ($apptExtras['duration_months'] ?? 0);
    $durationForAppointment = isset($apptExtras['duration_months']) ? (int) $apptExtras['duration_months'] : null;
    if ($ledgerDurationMonths > 0) {
        $durationForAppointment = $ledgerDurationMonths;
    }

    $treatmentFkOrNull = $existing_treatment_id;
    $installmentForAppointment = $installment_number_for_slot > 0 ? $installment_number_for_slot : null;

    $notesCombined = trim((string) ($input['notes'] ?? ''));
    if ($treatmentFkOrNull !== '') {
        $notesCombined .= ($notesCombined !== '' ? "\n" : '') . 'treatment_id: ' . $treatmentFkOrNull;
    }
    $notesCombined .= ($notesCombined !== '' ? "\n" : '') . 'plan_follow_up_installment:' . $installment_number_for_slot;

    $booking_id = 'BK-' . strtoupper(substr(md5((string) microtime(true) . $user_id . mt_rand()), 0, 10));

    $apptVals = [
        'tenant_id' => $tenant_id,
        'dentist_id' => (int) $dentist_id,
        'booking_id' => $booking_id,
        'patient_id' => $patient_id,
        'treatment_id' => $treatmentFkOrNull,
        'appointment_date' => $appointment_date,
        'appointment_time' => $appointment_time,
        'status' => 'confirmed',
        'visit_type' => 'pre_book',
        'created_by' => (string) $user_id,
        'service_type' => $apptExtras['service_type'] ?? null,
        'service_description' => $apptExtras['service_description'] ?? null,
        'treatment_type' => $apptExtras['treatment_type'] ?? 'short_term',
        'total_treatment_cost' => $treatment_cart_total > 0 ? $treatment_cart_total : null,
        'duration_months' => $durationForAppointment,
        'target_completion_date' => $apptExtras['target_completion_date'] ?? null,
        'start_date' => $apptExtras['start_date'] ?? $appointment_date,
        'notes' => $notesCombined !== '' ? $notesCombined : null,
        'installment_number' => $installmentForAppointment,
    ];

    $apptVals = booking_coerce_appointment_date_columns($pdo, (string) $apptPhys, $apptVals);

    $pdo->beginTransaction();

    try {
        booking_mobile_dynamic_insert_appointment($pdo, (string) $apptPhys, $apptVals);
        $appointment_id = (int) $pdo->lastInsertId();

        $appointmentServicesPhys = $tables['appointment_services'] ?? null;
        if ($appointmentServicesPhys !== null) {
            $apsQuoted = clinic_quote_identifier((string) $appointmentServicesPhys);
            $asc = clinic_table_columns($pdo, (string) $appointmentServicesPhys);
            $ledgerRows = booking_enrich_mobile_service_rows($pdo, $tenant_id, $services);

            foreach ($ledgerRows as $er) {
                $sid = trim((string) ($er['service_id'] ?? ''));
                $stype = !empty($er['is_installment_line']) ? 'installment' : 'regular';
                $label = $stype === 'installment' ? 'Long Term' : 'Short Term';
                $svcName = (string) ($er['service_name'] ?? '');
                $price = isset($er['price']) ? (float) $er['price'] : 0.0;

                $colParts = [];
                $ph = [];
                $bind = [];

                foreach (
                    [
                        'tenant_id' => $tenant_id,
                        'booking_id' => $booking_id,
                        'appointment_id' => $appointment_id > 0 ? $appointment_id : null,
                        'treatment_id' => ($treatmentFkOrNull !== '' && $stype === 'installment') ? $treatmentFkOrNull : null,
                        'service_id' => $sid !== '' ? $sid : null,
                        'service_name' => $svcName,
                        'price' => $price,
                        'service_type' => $stype,
                        'type' => $label,
                        'is_original' => 1,
                        'added_by' => (string) $user_id,
                    ] as $nm => $val
                ) {
                    $lc = strtolower((string) $nm);
                    if (!in_array($lc, $asc, true)) {
                        continue;
                    }
                    $colParts[] = clinic_quote_identifier($lc);
                    $ph[] = '?';
                    $bind[] = $val;
                }
                if (in_array('added_at', $asc, true)) {
                    $colParts[] = clinic_quote_identifier('added_at');
                    $ph[] = 'NOW()';
                }
                if ($colParts !== []) {
                    $sqlIns = 'INSERT INTO ' . $apsQuoted . ' (' . implode(', ', $colParts) . ') VALUES (' . implode(', ', $ph) . ')';
                    $insSt = $pdo->prepare($sqlIns);
                    $insSt->execute($bind);
                }
            }
        }

        staff_installments_stamp_followup_slot_explicit(
            $pdo,
            $tenant_id,
            $effectivePlanBookingId,
            $installment_number_for_slot,
            $appointment_date,
            $appointment_time
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Visit scheduled (included with your plan).',
        'booking_id' => $booking_id,
        'treatment_id' => $treatmentFkOrNull,
    ]);
    exit;
}
