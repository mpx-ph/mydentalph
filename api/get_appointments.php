<?php
// api/get_appointments.php
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(["status" => "error", "message" => "GET required"]));
}

$user_id   = $_GET['user_id']   ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($user_id)) {
    die(json_encode(["status" => "error", "message" => "Missing user_id"]));
}

try {
    // 0. Get Clinic Name for the Header
    $clinic_name = "Denta Cleene"; // Default
    if (!empty($tenant_id)) {
        $stmt = $pdo->prepare("SELECT clinic_name FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
        $stmt->execute([$tenant_id]);
        $tenantInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tenantInfo) {
            $clinic_name = $tenantInfo['clinic_name'];
        }
    }

    // 1. Find the patient record linked to the logged-in user
    $stmt = $pdo->prepare(
        "SELECT patient_id, first_name, last_name FROM tbl_patients 
         WHERE owner_user_id = ? OR linked_user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$user_id, $user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode(["status" => "success", "appointments" => [], "clinic_name" => $clinic_name, "message" => "No patient record found."]);
        exit;
    }

    $patient_id = $patient['patient_id'];
    $patient_name = ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '');

    // 2. Fetch all their appointments
    $stmt = $pdo->prepare(
        "SELECT 
            a.id,
            a.booking_id,
            a.appointment_date,
            a.appointment_time,
            COALESCE(a.service_type, (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)) AS display_service,
            a.status,
            a.total_treatment_cost,
            CONCAT(d.first_name, ' ', d.last_name) AS dentist_name
         FROM tbl_appointments a
         LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
         WHERE a.patient_id = ?
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );
    $stmt->execute([$patient_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Status Mapping
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
            'service_name'     => $row['display_service'] ?: 'Appointment',
            'dentist_name'     => $row['dentist_name'] ? 'DR. ' . strtoupper($row['dentist_name']) : 'CLINIC',
            'appointment_date' => $row['appointment_date'],
            'appointment_time' => $row['appointment_time'],
            'status'           => $displayStatus,
            'price'            => number_format((float)($row['total_treatment_cost'] ?? 0), 2),
        ];
    }

    echo json_encode([
        "status" => "success", 
        "appointments" => $appointments,
        "clinic_name" => $clinic_name,
        "patient_name" => strtoupper($patient_name)
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
