<?php
// api/cancel_appointment.php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(["status" => "error", "success" => false, "message" => "POST required"]));
}

// Accept JSON body or form-data.
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$appointment_id = $input['appointment_id'] ?? null;
$booking_id     = $input['booking_id'] ?? null;
$tenant_id      = $input['tenant_id'] ?? null;
$user_id        = $input['user_id'] ?? null;
$notes          = trim((string)($input['notes'] ?? ''));

// `status` is accepted in the payload for compatibility; we still force CANCELLED.
$final_status = 'CANCELLED';

if (!$tenant_id || !$user_id || (!$appointment_id && !$booking_id)) {
    die(json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Missing required fields: tenant_id, user_id, and appointment_id or booking_id"
    ]));
}

try {
    // Resolve patient tied to the app user for scoped cancellation.
    $stmt = $pdo->prepare("
        SELECT patient_id
        FROM tbl_patients
        WHERE tenant_id = ?
          AND (owner_user_id = ? OR linked_user_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $user_id, $user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        die(json_encode([
            "status" => "error",
            "success" => false,
            "message" => "Patient record not found for this user"
        ]));
    }

    $patient_id = $patient['patient_id'];

    if ($appointment_id) {
        $stmt = $pdo->prepare("
            UPDATE tbl_appointments
            SET status = ?, notes = ?
            WHERE tenant_id = ?
              AND id = ?
              AND patient_id = ?
            LIMIT 1
        ");
        $stmt->execute([$final_status, $notes, $tenant_id, $appointment_id, $patient_id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE tbl_appointments
            SET status = ?, notes = ?
            WHERE tenant_id = ?
              AND booking_id = ?
              AND patient_id = ?
            LIMIT 1
        ");
        $stmt->execute([$final_status, $notes, $tenant_id, $booking_id, $patient_id]);
    }

    if ($stmt->rowCount() < 1) {
        echo json_encode([
            "status" => "error",
            "success" => false,
            "message" => "Appointment not found or not allowed to cancel"
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "success" => true,
        "message" => "Booking cancelled"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}

