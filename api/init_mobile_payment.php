<?php

declare(strict_types=1);

// api/init_mobile_payment.php — mobile PayMongo; writes treatment ledger + linkage like staff bookings.
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../clinic/includes/appointment_booking_row.php';
require_once __DIR__ . '/../clinic/includes/booking_treatment_ledger.php';
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
$dentist_id = $input['dentist_id'] ?? 1;
$appointment_date = $input['appointment_date'] ?? null;
$appointment_time = $input['appointment_time'] ?? null;
$services_json = $input['services'] ?? '[]';
$total_amount = isset($input['total_amount']) ? (float) $input['total_amount'] : 0.0;
$payment_amount = isset($input['payment_amount']) ? (float) $input['payment_amount'] : 0.0;

if (!$user_id || !$appointment_date || !$appointment_time) {
    die(json_encode(["status" => "error", "message" => "Missing required fields"]));
}

$paymongo_secret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
if ($paymongo_secret === '') {
    die(json_encode(["status" => "error", "message" => "PayMongo Secret Key not configured on the server."]));
}

try {
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
    $patient_id = trim((string) ($patRow['patient_id'] ?? ''));
    $patient_name = trim(trim((string) ($patRow['first_name'] ?? '')) . ' ' . trim((string) ($patRow['last_name'] ?? '')));
    $user_email = !empty($patRow['email']) ? trim((string) $patRow['email']) : 'patient@mydentalph.com';
    $user_phone = trim((string) ($patRow['phone'] ?? ''));

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

    $ledger = booking_create_treatment_row(
        $pdo,
        $tenant_id,
        $patient_id,
        (string) $user_id,
        $booking_id,
        $services,
        $total_amount,
        $apptExtras
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
        'total_treatment_cost' => $total_amount > 0 ? $total_amount : null,
        'duration_months' => $apptExtras['duration_months'] ?? null,
        'target_completion_date' => $apptExtras['target_completion_date'] ?? null,
        'start_date' => $apptExtras['start_date'] ?? $appointment_date,
        'notes' => $notesCombined !== '' ? $notesCombined : null,
        'installment_number' => $installmentForAppointment,
    ];

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
    $payment_type = ($payment_amount < $total_amount) ? 'downpayment' : 'fullpayment';
    $payNotes = isset($ledger['payment_notes'])
        ? (string) $ledger['payment_notes']
        : sprintf('Mobile booking %s (PayMongo pending).', $booking_id);

    $paymentsPhys = $tables['payments'] ?? 'tbl_payments';
    $ppc = clinic_table_columns($pdo, (string) $paymentsPhys);
    $pq = clinic_quote_identifier((string) $paymentsPhys);

    $payColsIns = [];
    $payBind = [];
    $map = [
        'tenant_id' => $tenant_id,
        'payment_id' => $payment_id,
        'patient_id' => $patient_id,
        'booking_id' => $booking_id,
        'treatment_id' => $treatmentFkOrNull,
        'installment_number' => (($ledger['installment_number'] ?? 0) > 0) ? (int) $ledger['installment_number'] : null,
        'amount' => $payment_amount,
        'payment_method' => 'gcash',
        'status' => 'pending',
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
    if ($payColsIns !== []) {
        $psql = 'INSERT INTO ' . $pq . ' (' . implode(', ', $payColsIns) . ') VALUES (' . implode(', ', $phParts) . ')';
        $pst = $pdo->prepare($psql);
        $pst->execute($payBind);
    }

    $pdo->commit();

    $description = ($payment_amount < $total_amount) ? 'Downpayment for Appointment' : 'Full Payment for Appointment';
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
