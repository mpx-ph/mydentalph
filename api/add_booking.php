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

// Map User ID to Patient ID (usually same or linked)
// For this demo, we'll assume a direct match or fetch first patient_id for that user
try {
    $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE owner_user_id = ? OR linked_user_id = ? LIMIT 1");
    $stmt->execute([$user_id, $user_id]);
    $patRow = $stmt->fetch();
    $patient_id = $patRow ? $patRow['patient_id'] : 'PAT_NEW_' . rand(1000, 9999);

    // 1. Extract Primary Service for the main Appointment table (Dashboard views)
    $services = is_array($services_json) ? $services_json : json_decode($services_json, true);
    $primary_service = $services[0] ?? ['id' => null, 'name' => '-', 'details' => '-'];
    $service_id_main = $primary_service['id'];
    $service_name_main = $primary_service['name'];
    $service_desc_main = $primary_service['details'] ?? '-';

    $pdo->beginTransaction();

    // 1. Insert Appointment (Including Primary Service for Dashboard Compat)
    $stmt = $pdo->prepare("INSERT INTO tbl_appointments 
        (tenant_id, dentist_id, booking_id, patient_id, appointment_date, appointment_time, status, 
         total_treatment_cost, visit_type, created_by, service_id, service_name, service_description) 
        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, 'pre_book', ?, ?, ?, ?)");
    $stmt->execute([
        $tenant_id, $dentist_id, $booking_id, $patient_id, $appointment_date, $appointment_time, 
        $total_amount, $user_id, $service_id_main, $service_name_main, $service_desc_main
    ]);
    $appointment_id = $pdo->lastInsertId();

    // 2. Insert Services Breakdown (For detailed reporting)
    $services = is_array($services_json) ? $services_json : json_decode($services_json, true);
    foreach ($services as $srv) {
        $stmt = $pdo->prepare("INSERT INTO tbl_appointment_services 
            (tenant_id, booking_id, appointment_id, service_id, service_name, price) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$tenant_id, $booking_id, $appointment_id, $srv['id'], $srv['name'], $srv['price']]);
    }

    // 3. Insert Payment
    $payment_id = 'PAY-' . rand(100000, 999999);
    // Set status to 'completed' only if paid in full, 'pending' if partial/downpayment
    $final_status = ($payment_amount >= $total_amount) ? 'completed' : 'pending';
    
    $stmt = $pdo->prepare("INSERT INTO tbl_payments 
        (tenant_id, payment_id, patient_id, booking_id, amount, payment_method, reference_number, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $payment_id, $patient_id, $booking_id, $payment_amount, $payment_method, $reference_number, $final_status, $user_id]);

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
