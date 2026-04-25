<?php
// api/get_payments.php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(["success" => false, "message" => "GET required"]));
}

$tenant_id = $_GET['tenant_id'] ?? null;
$user_id = $_GET['user_id'] ?? null;
$payment_id = $_GET['payment_id'] ?? null;
$booking_id = $_GET['booking_id'] ?? null;
$patient_id = $_GET['patient_id'] ?? null;
$status = $_GET['status'] ?? null;

if (!$tenant_id || !$user_id) {
    die(json_encode(["success" => false, "message" => "Missing tenant_id or user_id"]));
}

try {
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

    $sql = "
        SELECT p.*
        FROM tbl_payments p
        WHERE p.tenant_id = ?
          AND (
                p.created_by = ?
                OR p.patient_id = ?
              )
    ";
    $params = [$tenant_id, $user_id, $patientRow['patient_id'] ?? null];

    if (!empty($payment_id)) {
        $sql .= " AND p.payment_id = ? ";
        $params[] = $payment_id;
    }
    if (!empty($booking_id)) {
        $sql .= " AND p.booking_id = ? ";
        $params[] = $booking_id;
    }
    if (!empty($patient_id)) {
        $sql .= " AND p.patient_id = ? ";
        $params[] = $patient_id;
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

