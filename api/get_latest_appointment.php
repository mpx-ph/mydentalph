<?php
// api/get_latest_appointment.php
require_once '../db.php';
require_once __DIR__ . '/request_context.inc.php';

header('Content-Type: application/json');
api_send_no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(["status" => "error", "message" => "GET required"]));
}

$user_id   = $_GET['user_id']   ?? '';
$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($user_id)) {
    die(json_encode(["status" => "error", "message" => "Missing user_id"]));
}

try {
    $tenant_id = api_resolve_tenant_id($pdo, (string) $user_id, (string) $tenant_id);

    // 1. Find the patient record linked to the logged-in user
    if ($tenant_id !== null && $tenant_id !== '') {
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
        echo json_encode(["status" => "success", "appointments" => [], "message" => "No patient record found."]);
        exit;
    }

    $patient_id = $patient['patient_id'];

    // 2. Fetch the FIRST upcoming 'confirmed' appointment (SCHEDULED)
    // We look for dates greater than or equal to today, ordered by arrival time.
    $stmt = $pdo->prepare(
        "SELECT 
            a.*,
            COALESCE(a.service_type, (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)) AS display_service,
            CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
            d.specialization AS dentist_specialization,
            TRIM(CONCAT(COALESCE(pat.first_name, ''), ' ', COALESCE(pat.last_name, ''))) AS patient_name,
            pat.contact_number AS patient_contact,
            pat.date_of_birth AS patient_dob
         FROM tbl_appointments a
         LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
         LEFT JOIN tbl_patients pat ON pat.tenant_id = a.tenant_id AND pat.patient_id = a.patient_id
         WHERE a.patient_id = ? AND a.status = 'confirmed' AND a.appointment_date >= CURDATE()
         ORDER BY a.appointment_date ASC, a.appointment_time ASC
         LIMIT 1"
    );
    $stmt->execute([$patient_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["status" => "success", "appointment" => null, "message" => "No upcoming scheduled appointments."]);
        exit;
    }

    // 3. Full row + display-friendly fields (backward compatible)
    $appointment = $row;
    $appointment['service_name']   = $row['display_service'] ?: 'Appointment';
    $appointment['dentist_name']  = 'DR. ' . strtoupper($row['dentist_name'] ?? '');
    $appointment['specialization'] = strtoupper($row['dentist_specialization'] ?? 'Dentist');

    echo json_encode(["status" => "success", "appointment" => $appointment]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
