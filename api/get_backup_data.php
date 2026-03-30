<?php
// api/get_backup_data.php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(["status" => "error", "message" => "GET required"]));
}

$user_id   = $_GET['user_id']   ?? '';

if (empty($user_id)) {
    die(json_encode(["status" => "error", "message" => "Missing user_id"]));
}

try {
    // 1. Find patient record
    $stmt = $pdo->prepare(
        "SELECT patient_id, first_name, last_name FROM tbl_patients 
         WHERE owner_user_id = ? OR linked_user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$user_id, $user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode(["status" => "error", "message" => "No patient record found."]);
        exit;
    }

    $patient_id = $patient['patient_id'];
    $patient_name = $patient['first_name'] . ' ' . $patient['last_name'];

    // 2. Fetch ALL appointments with payment status
    $stmt = $pdo->prepare(
        "SELECT 
            a.booking_id,
            a.appointment_date,
            a.appointment_time,
            a.status AS appointment_status,
            a.total_treatment_cost,
            COALESCE(a.service_type, (SELECT service_name FROM tbl_appointment_services WHERE appointment_id = a.id LIMIT 1)) AS display_service,
            CONCAT(d.first_name, ' ', d.last_name) AS dentist_name,
            p.amount AS payment_amount,
            p.payment_method,
            p.reference_number,
            p.status AS payment_status
         FROM tbl_appointments a
         LEFT JOIN tbl_dentists d ON a.dentist_id = d.dentist_id
         LEFT JOIN tbl_payments p ON a.booking_id = p.booking_id
         WHERE a.patient_id = ?
         ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    );
    $stmt->execute([$patient_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Format Response
    $transactions = [];
    foreach ($rows as $row) {
        $transactions[] = [
            'booking_id'       => $row['booking_id'],
            'date'             => $row['appointment_date'],
            'time'             => $row['appointment_time'],
            'service'          => $row['display_service'] ?: 'Consultation',
            'dentist'          => 'Dr. ' . trim($row['dentist_name']),
            'apt_status'       => strtoupper($row['appointment_status'] ?? 'PENDING'),
            'total_cost'       => number_format((float)($row['total_treatment_cost'] ?? 0), 2),
            'payment_amount'   => number_format((float)($row['payment_amount'] ?? 0), 2),
            'payment_method'   => strtoupper($row['payment_method'] ?? 'N/A'),
            'payment_ref'      => $row['reference_number'] ?: 'N/A',
            'payment_status'   => strtoupper($row['payment_status'] ?? 'UNPAID')
        ];
    }

    echo json_encode([
        "status"       => "success",
        "patient_name" => $patient_name,
        "transactions" => $transactions
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>
