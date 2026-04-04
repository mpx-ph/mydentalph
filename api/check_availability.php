<?php
// api/check_availability.php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(["status" => "error", "message" => "GET required"]));
}

$dentist_id      = $_GET['dentist_id'] ?? null;
$appointment_date = $_GET['appointment_date'] ?? null;
$appointment_time = $_GET['appointment_time'] ?? null;

if (!$dentist_id || !$appointment_date || !$appointment_time) {
    die(json_encode(["status" => "error", "message" => "Missing required fields"]));
}

try {
    // Check if the specific dentist already has a confirmed/pending appointment at this exact time
    $stmt = $pdo->prepare("SELECT id FROM tbl_appointments 
        WHERE dentist_id = ? AND appointment_date = ? AND appointment_time = ? 
        AND status NOT IN ('cancelled') LIMIT 1");
    $stmt->execute([$dentist_id, $appointment_date, $appointment_time]);
    
    $exists = $stmt->fetch();
    
    echo json_encode([
        "status"    => "success",
        "available" => !$exists,
        "message"   => $exists ? "This time slot is already reserved." : "Slot is available."
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>
