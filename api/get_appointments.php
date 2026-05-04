<?php
// api/get_appointments.php
require_once '../db.php';
require_once __DIR__ . '/request_context.inc.php';
require_once __DIR__ . '/includes/refund_requests_schema.inc.php';

header('Content-Type: application/json');
api_send_no_cache_headers();

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
    refund_requests_ensure_table($pdo);

    $tenant_id = api_resolve_tenant_id($pdo, $user_id, $tenant_id);

    // 1. All patient rows for this login: account holder (linked_user_id / owner) + dependents (same owner_user_id).
    if ($tenant_id !== null && $tenant_id !== '') {
        $stmt = $pdo->prepare(
            "SELECT patient_id FROM tbl_patients 
             WHERE tenant_id = ? AND (owner_user_id = ? OR linked_user_id = ?)"
        );
        $stmt->execute([$tenant_id, $user_id, $user_id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT patient_id FROM tbl_patients 
             WHERE owner_user_id = ? OR linked_user_id = ?"
        );
        $stmt->execute([$user_id, $user_id]);
    }
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $patient_ids = array_values(array_unique(array_filter(array_map('strval', $patient_ids), static function ($v) {
        return $v !== '';
    })));

    if (empty($patient_ids)) {
        echo json_encode(["status" => "success", "appointments" => [], "message" => "No patient record found for this user."]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));

    // 2. Fetch appointments for every linked patient (guardian + dependents).
    // We use a subquery to grab the first service name from tbl_appointment_services 
    // since tbl_appointments.service_type is often null in the mobile booking flow.
    if ($tenant_id !== null && $tenant_id !== '') {
        $sql = "SELECT 
                a.*,
                (EXISTS (
                    SELECT 1 FROM tbl_reviews r
                    WHERE r.tenant_id = a.tenant_id AND r.appointment_id = a.id
                )) AS has_review,
                COALESCE(a.service_type, (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)) AS display_service,
                CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
                TRIM(CONCAT(COALESCE(pat.first_name, ''), ' ', COALESCE(pat.last_name, ''))) AS patient_name,
                pat.contact_number AS patient_contact,
                pat.date_of_birth AS patient_dob,
                (
                    SELECT rr2.status
                    FROM tbl_refund_requests rr2
                    WHERE rr2.tenant_id = a.tenant_id
                      AND rr2.appointment_id = a.id
                    ORDER BY rr2.id DESC
                    LIMIT 1
                ) AS refund_request_status
             FROM tbl_appointments a
             LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
             LEFT JOIN tbl_patients pat ON pat.tenant_id = a.tenant_id AND pat.patient_id = a.patient_id
             WHERE a.patient_id IN ($placeholders) AND a.tenant_id = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($patient_ids, [$tenant_id]));
    } else {
        $sql = "SELECT 
                a.*,
                (EXISTS (
                    SELECT 1 FROM tbl_reviews r
                    WHERE r.tenant_id = a.tenant_id AND r.appointment_id = a.id
                )) AS has_review,
                COALESCE(a.service_type, (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)) AS display_service,
                CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
                TRIM(CONCAT(COALESCE(pat.first_name, ''), ' ', COALESCE(pat.last_name, ''))) AS patient_name,
                pat.contact_number AS patient_contact,
                pat.date_of_birth AS patient_dob,
                (
                    SELECT rr2.status
                    FROM tbl_refund_requests rr2
                    WHERE rr2.tenant_id = a.tenant_id
                      AND rr2.appointment_id = a.id
                    ORDER BY rr2.id DESC
                    LIMIT 1
                ) AS refund_request_status
             FROM tbl_appointments a
             LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
             LEFT JOIN tbl_patients pat ON pat.tenant_id = a.tenant_id AND pat.patient_id = a.patient_id
             WHERE a.patient_id IN ($placeholders)
             ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($patient_ids);
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

        $rr = strtolower(trim((string)($row['refund_request_status'] ?? '')));
        if ($rr === 'pending') {
            $displayStatus = 'REFUND REQUESTED';
        }

        $item = $row;
        $item['status_code']  = $row['status'] ?? 'pending';
        $item['status']      = $displayStatus;
        $item['refund_request_status'] = $rr;
        $item['service_name'] = $row['display_service'] ?: 'Appointment';
        $item['dentist_name']  = $row['dentist_name'] ? 'DR. ' . strtoupper($row['dentist_name']) : 'CLINIC';
        $item['price']         = number_format((float)($row['total_treatment_cost'] ?? 0), 2);
        // Explicit for mobile filtering (a.* may already include patient_id).
        $item['patient_id']    = (string)($row['patient_id'] ?? '');
        $appointments[] = $item;
    }

    echo json_encode(["status" => "success", "appointments" => $appointments]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
