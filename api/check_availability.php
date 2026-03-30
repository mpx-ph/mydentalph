<?php
// api/check_availability.php
// Check if a time slot is already taken before proceeding to checkout
require_once '../db.php';
header('Content-Type: application/json');

// Get data
$dentist_id = $_GET['dentist_id'] ?? $_POST['dentist_id'] ?? null;
$date = $_GET['appointment_date'] ?? $_POST['appointment_date'] ?? null;
$time = $_GET['appointment_time'] ?? $_POST['appointment_time'] ?? null;
$tenant_id = $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? 'TNT_00025';

// For JSON POSTs
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $dentist_id = $input['dentist_id'] ?? $dentist_id;
    $date = $input['appointment_date'] ?? $date;
    $time = $input['appointment_time'] ?? $time;
    $tenant_id = $input['tenant_id'] ?? $tenant_id;
}

if (!$dentist_id || !$date || !$time) {
    die(json_encode(["status" => "error", "message" => "Missing parameters"]));
}

try {
    $stmt = $pdo->prepare("SELECT id FROM tbl_appointments 
        WHERE dentist_id = ? AND appointment_date = ? AND appointment_time = ? 
        AND status NOT IN ('cancelled') LIMIT 1");
    $stmt->execute([$dentist_id, $date, $time]);
    
    if ($stmt->fetch()) {
        echo json_encode([
            "status" => "taken",
            "available" => false,
            "message" => "This time has been taken"
        ]);
    } else {
        echo json_encode([
            "status" => "success",
            "available" => true,
            "message" => "Slot is available"
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
