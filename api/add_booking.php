<?php
// api/add_booking.php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "message" => "POST required"]));
}

// Get POST data (either raw or from $_POST)
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$user_id = $input['user_id'] ?? null;
$tenant_id = $input['tenant_id'] ?? 'TNT_00025';
$dentist_id = $input['dentist_id'] ?? 1; // Default to Dr. Sarah
$appointment_date = $input['appointment_date'] ?? null;
$appointment_time = $input['appointment_time'] ?? null;
$services_json = $input['services'] ?? '[]';
$total_amount = $input['total_amount'] ?? 0;
$payment_amount = $input['payment_amount'] ?? 0;
$payment_method = $input['payment_method'] ?? 'gcash';
$reference_number = $input['reference_number'] ?? null;

if (!$user_id || !$appointment_date || !$appointment_time) {
    die(json_encode(["status" => "error", "message" => "Missing required fields"]));
}

// Map User ID to Patient ID
try {
    $stmt = $pdo->prepare("SELECT patient_id, first_name, last_name FROM tbl_patients WHERE owner_user_id = ? OR linked_user_id = ? LIMIT 1");
    $stmt->execute([$user_id, $user_id]);
    $patRow = $stmt->fetch();
    
    if (!$patRow) {
        throw new Exception("Patient profile not found for this user. Please complete your registration.");
    }
    $patient_id = $patRow['patient_id'];

    // Generate highly unique booking_id (10 chars + BK prefix)
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

    // 1. Insert Appointment
    $stmt = $pdo->prepare("INSERT INTO tbl_appointments 
        (tenant_id, dentist_id, booking_id, patient_id, appointment_date, appointment_time, status, total_treatment_cost, visit_type, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, 'pre_book', ?)");
    $stmt->execute([$tenant_id, $dentist_id, $booking_id, $patient_id, $appointment_date, $appointment_time, $total_amount, $user_id]);
    $appointment_id = $pdo->lastInsertId();

    // 2. Insert Services Breakdown
    $services = is_array($services_json) ? $services_json : json_decode($services_json, true);
    foreach ($services as $srv) {
        $stmt = $pdo->prepare("INSERT INTO tbl_appointment_services 
            (tenant_id, booking_id, appointment_id, service_id, service_name, price) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $booking_id, $appointment_id, $srv['id'], $srv['name'], $srv['price']]);
    }

    // 3. Insert Payment
    $payment_id = 'PAY-' . strtoupper(substr(md5(microtime(true) . $booking_id . mt_rand()), 0, 10));
    // Set status to 'completed' only if paid in full, 'pending' if partial/downpayment
    $final_status = ($payment_amount >= $total_amount) ? 'completed' : 'pending';
    $payment_type = ($payment_amount < $total_amount) ? 'downpayment' : 'fullpayment';
    
    $stmt = $pdo->prepare("INSERT INTO tbl_payments 
        (tenant_id, payment_id, patient_id, booking_id, amount, payment_method, reference_number, status, created_by, payment_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $payment_id, $patient_id, $booking_id, $payment_amount, $payment_method, $reference_number, $final_status, $user_id, $payment_type]);

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Booking successful",
        "booking_id" => $booking_id
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
