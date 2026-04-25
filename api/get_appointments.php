<?php
// api/get_appointments.php
require_once '../db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    die(json_encode(["status" => "error", "message" => "GET or POST required"]));
}

$input = array_merge($_GET, $_POST);
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    }
}

$user_id   = trim((string)($input['user_id'] ?? ''));
$tenant_id = trim((string)($input['tenant_id'] ?? ''));

if ($user_id === '') {
    die(json_encode(["status" => "error", "message" => "Missing user_id"]));
}

try {
    // 1. Find the patient record linked to the logged-in user
    // We match by user_id across owner or linked fields; scope by tenant when provided.
    if ($tenant_id !== '') {
        $stmt = $pdo->prepare(
            "SELECT patient_id FROM tbl_patients 
             WHERE tenant_id = ? AND (owner_user_id = ? OR linked_user_id = ?)
             LIMIT 1"
        );
        $stmt->execute([$tenant_id, $user_id, $user_id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT patient_id FROM tbl_patients 
             WHERE owner_user_id = ? OR linked_user_id = ?
             LIMIT 1"
        );
        $stmt->execute([$user_id, $user_id]);
    }
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode(["status" => "success", "appointments" => [], "message" => "No patient record found for this user."]);
        exit;
    }

    $patient_id = $patient['patient_id'];

    // 2. Fetch appointments
    // We use a subquery to grab the first service name from tbl_appointment_services 
    // since tbl_appointments.service_type is often null in the mobile booking flow.
    if ($tenant_id !== '') {
        $stmt = $pdo->prepare(
            "SELECT 
                a.*,
                COALESCE(a.service_type, (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)) AS display_service,
                CONCAT(d.first_name, ' ', d.last_name) AS dentist_name
             FROM tbl_appointments a
             LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
             WHERE a.patient_id = ? AND a.tenant_id = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC"
        );
        $stmt->execute([$patient_id, $tenant_id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT 
                a.*,
                COALESCE(a.service_type, (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)) AS display_service,
                CONCAT(d.first_name, ' ', d.last_name) AS dentist_name
             FROM tbl_appointments a
             LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
             WHERE a.patient_id = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC"
        );
        $stmt->execute([$patient_id]);
    }
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

        $item = $row;
        $item['status_code']  = $row['status'] ?? 'pending';
        $item['status']      = $displayStatus;
        $item['service_name'] = $row['display_service'] ?: 'Appointment';
        $item['dentist_name']  = $row['dentist_name'] ? 'DR. ' . strtoupper($row['dentist_name']) : 'CLINIC';
        $item['price']         = number_format((float)($row['total_treatment_cost'] ?? 0), 2);
        $appointments[] = $item;
    }

    echo json_encode(["status" => "success", "appointments" => $appointments]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
