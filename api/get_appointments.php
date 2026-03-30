<?php
// api/get_appointments.php
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(["status" => "error", "message" => "GET required"]));
}

$user_id   = $_GET['user_id']   ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($user_id) || empty($tenant_id)) {
    die(json_encode(["status" => "error", "message" => "Missing user_id or tenant_id"]));
}

try {
    // 1. Find the patient record linked to the logged-in user
    $stmt = $pdo->prepare(
        "SELECT patient_id FROM tbl_patients 
         WHERE tenant_id = ? AND (linked_user_id = ? OR owner_user_id = ?)
         LIMIT 1"
    );
    $stmt->execute([$tenant_id, $user_id, $user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode(["status" => "success", "appointments" => [], "message" => "No patient record found for this user."]);
        exit;
    }

    $patient_id = $patient['patient_id'];

    // 2. Fetch all their appointments with dentist info and services
    $stmt = $pdo->prepare(
        "SELECT 
            a.id,
            a.booking_id,
            a.appointment_date,
            a.appointment_time,
            a.service_type,
            a.status,
            a.total_treatment_cost,
            CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
            d.specialization AS dentist_specialization
         FROM tbl_appointments a
         LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
         WHERE a.tenant_id = ? AND a.patient_id = ?
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );
    $stmt->execute([$tenant_id, $patient_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Map DB status values to display labels used by the Android app
    $statusMap = [
        'pending'   => 'PENDING',
        'confirmed' => 'SCHEDULED',
        'completed' => 'COMPLETED',
        'cancelled' => 'CANCELED',
        'no_show'   => 'CANCELED',
    ];

    $appointments = [];
    foreach ($rows as $row) {
        $dbStatus      = strtolower($row['status'] ?? 'pending');
        $displayStatus = $statusMap[$dbStatus] ?? strtoupper($dbStatus);

        $appointments[] = [
            'id'               => $row['id'],
            'booking_id'       => $row['booking_id'],
            'service_name'     => $row['service_type'] ?? 'Appointment',
            'dentist_name'     => $row['dentist_name'] ? 'DR. ' . strtoupper($row['dentist_name']) : 'CLINIC',
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'status'           => $displayStatus,
            'price'            => number_format((float)($row['total_treatment_cost'] ?? 0), 2),
        ];
    }

    echo json_encode(["status" => "success", "appointments" => $appointments]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
