<?php
// api/init_mobile_payment.php
require_once '../db.php';
require_once '../paymongo_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "message" => "POST required"]));
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input)
    $input = $_POST;

$user_id = $input['user_id'] ?? null;
$tenant_id = $input['tenant_id'] ?? 'TNT_00025';
$dentist_id = $input['dentist_id'] ?? 1;
$appointment_date = $input['appointment_date'] ?? null;
$appointment_time = $input['appointment_time'] ?? null;
$services_json = $input['services'] ?? '[]';
$total_amount = $input['total_amount'] ?? 0;
$payment_amount = $input['payment_amount'] ?? 0;

if (!$user_id || !$appointment_date || !$appointment_time) {
    die(json_encode(["status" => "error", "message" => "Missing required fields"]));
}

// Ensure secret key is available
$paymongo_secret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
if (empty($paymongo_secret)) {
    die(json_encode(["status" => "error", "message" => "PayMongo Secret Key not configured on the server."]));
}

try {
    // 1. Get Patient Profile
    $stmt = $pdo->prepare("SELECT patient_id, first_name, last_name FROM tbl_patients WHERE owner_user_id = ? OR linked_user_id = ? LIMIT 1");
    $stmt->execute([$user_id, $user_id]);
    $patRow = $stmt->fetch();

    if (!$patRow) {
        throw new Exception("Patient profile not found for this user. Please complete your registration.");
    }
    $patient_id = $patRow['patient_id'];
    $patient_name = trim($patRow['first_name'] . ' ' . $patRow['last_name']);

    // Generate highly unique booking_id
    $booking_id = 'BK-' . strtoupper(substr(md5(microtime(true) . $user_id . mt_rand()), 0, 10));

    // Check Availability (Double-booking validation)
    $stmt = $pdo->prepare("SELECT id FROM tbl_appointments 
        WHERE dentist_id = ? AND appointment_date = ? AND appointment_time = ? 
        AND status NOT IN ('cancelled') LIMIT 1");
    $stmt->execute([$dentist_id, $appointment_date, $appointment_time]);
    if ($stmt->fetch()) {
        throw new Exception("This time slot is already reserved. Please choose a different time.");
    }

    $pdo->beginTransaction();

    // 2. Insert Appointment
    // Insert as confirmed so it shows up in their bookings list, but the payment remains pending.
    $stmt = $pdo->prepare("INSERT INTO tbl_appointments 
        (tenant_id, dentist_id, booking_id, patient_id, appointment_date, appointment_time, status, total_treatment_cost, visit_type, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, 'pre_book', ?)");
    $stmt->execute([$tenant_id, $dentist_id, $booking_id, $patient_id, $appointment_date, $appointment_time, $total_amount, $user_id]);
    $appointment_id = $pdo->lastInsertId();

    // 3. Insert Services Breakdown
    $services = is_array($services_json) ? $services_json : json_decode($services_json, true);
    $paymongo_line_items = [];

    foreach ($services as $srv) {
        $stmt = $pdo->prepare("INSERT INTO tbl_appointment_services 
            (tenant_id, booking_id, appointment_id, service_id, service_name, price) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $booking_id, $appointment_id, $srv['id'], $srv['name'], $srv['price']]);

        // If paying in full, we can list individual services. If downpayment, we group it.
        // For simplicity, we create one line item for the total payment amount requested now.
    }

    // Prepare PayMongo line items
    $description = ($payment_amount < $total_amount) ? "Downpayment for Appointment" : "Full Payment for Appointment";

    // Paymongo amount is in centavos (multiply PHP amount by 100)
    $amount_in_cents = (int) round($payment_amount * 100);

    // 4. Insert Payment record as 'pending'
    $payment_id = 'PAY-' . strtoupper(substr(md5(microtime(true) . $booking_id . mt_rand()), 0, 10));

    $stmt = $pdo->prepare("INSERT INTO tbl_payments 
        (tenant_id, payment_id, patient_id, booking_id, amount, payment_method, status, created_by) 
        VALUES (?, ?, ?, ?, ?, 'gcash', 'pending', ?)");
    $stmt->execute([$tenant_id, $payment_id, $patient_id, $booking_id, $payment_amount, $user_id]);

    $pdo->commit();

    // 5. Create PayMongo Checkout Session
    // We use checkout_sessions instead of links to force specific payment methods (GCash, Maya, Card) and avoid QRPh.
    $paymongo_data = [
        'data' => [
            'attributes' => [
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'payment_method_types' => ['gcash', 'paymaya', 'card'],
                'description' => "$description ($booking_id) - $patient_name",
                'line_items' => [
                    [
                        'currency' => 'PHP',
                        'amount' => $amount_in_cents,
                        'description' => 'Payment for Booking',
                        'name' => 'Dental Services',
                        'quantity' => 1
                    ]
                ]
            ]
        ]
    ];

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymongo_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($paymongo_secret . ':')
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode([
            "status" => "success", // Booking created successfully, but payment link failed
            "message" => "Booking successful, but failed to generate payment link: " . $curlError,
            "booking_id" => $booking_id
        ]);
        exit;
    }

    $responseData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['data']['attributes']['checkout_url'])) {
        $checkout_url = $responseData['data']['attributes']['checkout_url'];
        // For checkout sessions, we use the session ID as the reference
        $paymongo_reference_number = $responseData['data']['id'];

        // Optionally update the payment record with PayMongo's session ID
        $stmt = $pdo->prepare("UPDATE tbl_payments SET reference_number = ? WHERE payment_id = ?");
        $stmt->execute([$paymongo_reference_number, $payment_id]);

        echo json_encode([
            "status" => "success",
            "message" => "Booking successful, redirecting to payment...",
            "booking_id" => $booking_id,
            "checkout_url" => $checkout_url
        ]);
    } else {
        // Paymongo API returned an error, but the booking is still saved.
        $paymongoErrorMsg = isset($responseData['errors'][0]['detail']) ? $responseData['errors'][0]['detail'] : "Unknown PayMongo Error ($httpCode)";
        echo json_encode([
            "status" => "success",
            "message" => "Booking successful, but failed to generate payment link: " . $paymongoErrorMsg,
            "booking_id" => $booking_id
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
?>