<?php
// api/get_payments.php
require_once '../db.php';
require_once __DIR__ . '/request_context.inc.php';
header('Content-Type: application/json');
api_send_no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(["success" => false, "message" => "GET required"]));
}

$tenant_id = trim((string) ($_GET['tenant_id'] ?? ''));
$user_id = trim((string) ($_GET['user_id'] ?? ''));
$payment_id = $_GET['payment_id'] ?? null;
$booking_id = $_GET['booking_id'] ?? null;
$filter_patient_id = isset($_GET['patient_id']) ? trim((string) $_GET['patient_id']) : null;
$status = $_GET['status'] ?? null;

if ($user_id === '') {
    die(json_encode(["success" => false, "message" => "Missing user_id"]));
}

try {
    $tenant_id = api_resolve_tenant_id($pdo, $user_id, $tenant_id);
    if ($tenant_id === null) {
        die(json_encode(["success" => false, "message" => "Missing tenant context for this user"]));
    }

    // Resolve patient access for this user.
    $stmt = $pdo->prepare(
        "SELECT patient_id
         FROM tbl_patients
         WHERE (owner_user_id = ? OR linked_user_id = ?)
           AND tenant_id = ?
         LIMIT 1"
    );
    $stmt->execute([$user_id, $user_id, $tenant_id]);
    $patientRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $resolved_patient_id = isset($patientRow['patient_id']) ? (string) $patientRow['patient_id'] : null;

    // Avoid `patient_id = NULL` (never matches in SQL); allow payments tied by created_by when no profile exists.
    $accessOr = 'p.created_by = ?';
    $params = [$tenant_id];
    if ($resolved_patient_id !== null && $resolved_patient_id !== '') {
        $accessOr = '(p.created_by = ? OR p.patient_id = ?)';
        $params[] = $user_id;
        $params[] = $resolved_patient_id;
    } else {
        $params[] = $user_id;
    }

    $sql = "
        SELECT
            p.*,
            a.appointment_date AS appt_date,
            a.service_type AS appt_service_type,
            a.service_description AS appt_service_description,
            TRIM(CONCAT(COALESCE(dent.first_name, ''), ' ', COALESCE(dent.last_name, ''))) AS dentist_display_name
        FROM tbl_payments p
        LEFT JOIN tbl_appointments a
            ON a.booking_id = p.booking_id AND a.tenant_id = p.tenant_id
        LEFT JOIN tbl_dentists dent
            ON dent.dentist_id = a.dentist_id AND dent.tenant_id = a.tenant_id
        WHERE p.tenant_id = ?
          AND ($accessOr)
    ";

    if (!empty($payment_id)) {
        $sql .= " AND p.payment_id = ? ";
        $params[] = $payment_id;
    }
    if (!empty($booking_id)) {
        $sql .= " AND p.booking_id = ? ";
        $params[] = $booking_id;
    }
    if ($filter_patient_id !== null && $filter_patient_id !== '') {
        $sql .= " AND p.patient_id = ? ";
        $params[] = $filter_patient_id;
    }
    if (!empty($status)) {
        $sql .= " AND p.status = ? ";
        $params[] = $status;
    }

    $sql .= " ORDER BY p.payment_date DESC, p.id DESC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "count" => count($payments),
        "payments" => $payments
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

