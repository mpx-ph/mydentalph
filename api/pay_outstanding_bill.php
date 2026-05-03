<?php
// api/pay_outstanding_bill.php
require_once '../db.php';
require_once '../paymongo_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "message" => "POST required"]));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$user_id = $input['user_id'] ?? null;
$tenant_id = $input['tenant_id'] ?? null;
$booking_id = $input['booking_id'] ?? null;
$payment_amount = $input['payment_amount'] ?? 0;

if (!$user_id || !$tenant_id || !$booking_id || $payment_amount <= 0) {
    die(json_encode(["status" => "error", "message" => "Missing required fields or invalid amount"]));
}

$paymongo_secret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
if (empty($paymongo_secret)) {
    die(json_encode(["status" => "error", "message" => "PayMongo Secret Key not configured."]));
}

try {
    // 1. Get Patient Details for PayMongo
    $stmt = $pdo->prepare("SELECT p.patient_id, p.first_name, p.last_name, u.email, u.phone 
        FROM tbl_patients p
        LEFT JOIN tbl_users u ON u.user_id = ?
        WHERE (p.owner_user_id = ? OR p.linked_user_id = ?) AND p.tenant_id = ?
        LIMIT 1");
    $stmt->execute([$user_id, $user_id, $user_id, $tenant_id]);
    $patRow = $stmt->fetch();

    if (!$patRow) {
        throw new Exception("Patient profile not found.");
    }

    $patient_id = $patRow['patient_id'];
    $patient_name = trim($patRow['first_name'] . ' ' . $patRow['last_name']);
    $user_email = !empty($patRow['email']) ? $patRow['email'] : 'patient@mydentalph.com';
    $user_phone = !empty($patRow['phone']) ? $patRow['phone'] : '';

    // 2. Reuse an existing pending PayMongo row for this booking when present (e.g. abandoned checkout
    //    from Booking/Checkout). Otherwise a second INSERT leaves the old row stuck "pending" forever.
    $stmt = $pdo->prepare(
        "SELECT payment_id FROM tbl_payments
         WHERE tenant_id = ? AND booking_id = ? AND LOWER(TRIM(COALESCE(status, ''))) = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$tenant_id, $booking_id]);
    $existingPending = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingPending && !empty($existingPending['payment_id'])) {
        $payment_id = (string) $existingPending['payment_id'];
        $stmt = $pdo->prepare(
            "UPDATE tbl_payments
             SET amount = ?, payment_method = 'gcash', payment_type = 'fullpayment', patient_id = ?, created_by = ?
             WHERE payment_id = ?"
        );
        $stmt->execute([$payment_amount, $patient_id, $user_id, $payment_id]);

        // Drop duplicate abandoned pending sessions for the same booking so history does not show stale Pending lines.
        $stmt = $pdo->prepare(
            "UPDATE tbl_payments SET status = 'cancelled'
             WHERE tenant_id = ? AND booking_id = ? AND LOWER(TRIM(COALESCE(status, ''))) = 'pending' AND payment_id <> ?"
        );
        $stmt->execute([$tenant_id, $booking_id, $payment_id]);
    } else {
        $payment_id = 'PAY-' . strtoupper(substr(md5((string) microtime(true) . $booking_id . mt_rand()), 0, 10));
        $stmt = $pdo->prepare(
            "INSERT INTO tbl_payments
            (tenant_id, payment_id, patient_id, booking_id, amount, payment_method, status, created_by, payment_type)
            VALUES (?, ?, ?, ?, ?, 'gcash', 'pending', ?, 'fullpayment')"
        );
        $stmt->execute([$tenant_id, $payment_id, $patient_id, $booking_id, $payment_amount, $user_id]);
    }

    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    $baseApi = ($host !== '' ? "{$scheme}://{$host}" : 'http://mydentalph.ct.ws') . '/api';

    // 3. Create PayMongo Checkout Session
    $description = "Settling Outstanding Balance for $booking_id";
    $amount_in_cents = (int) round($payment_amount * 100);

    $paymongo_data = [
        'data' => [
            'attributes' => [
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'payment_method_types' => ['gcash', 'paymaya', 'card'],
                'description' => "$description - $patient_name",
                'success_url' => $baseApi . '/payment_success.php?pid=' . rawurlencode($payment_id),
                'billing' => [
                    'name' => $patient_name,
                    'email' => $user_email,
                    'phone' => $user_phone
                ],
                'line_items' => [
                    [
                        'currency' => 'PHP',
                        'amount' => $amount_in_cents,
                        'description' => 'Remaining Balance Payment',
                        'name' => 'Outstanding Bill',
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
        throw new Exception("Network error connecting to payment gateway: " . $curlError);
    }

    $responseData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['data']['attributes']['checkout_url'])) {
        $checkout_url = $responseData['data']['attributes']['checkout_url'];
        $paymongo_reference_number = $responseData['data']['id'];

        $stmt = $pdo->prepare("UPDATE tbl_payments SET reference_number = ? WHERE payment_id = ?");
        $stmt->execute([$paymongo_reference_number, $payment_id]);

        echo json_encode([
            "success" => true,
            "message" => "Payment link generated",
            "checkout_url" => $checkout_url
        ]);
    } else {
        $paymongoErrorMsg = isset($responseData['errors'][0]['detail']) ? $responseData['errors'][0]['detail'] : "Unknown Error";
        throw new Exception("PayMongo Error: " . $paymongoErrorMsg);
    }

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
