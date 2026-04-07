<?php
// api/get_outstanding_bills.php
require_once '../db.php';
header('Content-Type: application/json');

$user_id = $_GET['user_id'] ?? null;
$tenant_id = $_GET['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    die(json_encode(["success" => false, "message" => "Missing user_id or tenant_id"]));
}

try {
    // 1. Get patient_id for this user
    $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE (owner_user_id = ? OR linked_user_id = ?) AND tenant_id = ? LIMIT 1");
    $stmt->execute([$user_id, $user_id, $tenant_id]);
    $patRow = $stmt->fetch();
    
    if (!$patRow) {
        // Not a patient yet, so no bills
        echo json_encode(["success" => true, "bills" => []]);
        exit;
    }
    
    $patient_id = $patRow['patient_id'];

    // 2. Get all booking_ids for this patient that have a downpayment in tbl_payments
    //    Then sum all payments per booking and compute remaining balance.
    $sql = "
        SELECT 
            p.booking_id,
            a.appointment_date,
            COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
            SUM(p.amount) AS total_paid
        FROM tbl_payments p
        LEFT JOIN tbl_appointments a 
            ON a.booking_id = p.booking_id AND a.tenant_id = p.tenant_id
        WHERE p.patient_id = ?
          AND p.tenant_id = ?
          AND p.payment_type = 'downpayment'
        GROUP BY p.booking_id, a.appointment_date, a.total_treatment_cost
        ORDER BY a.appointment_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id, $tenant_id]);
    
    $bills = [];
    $total_unpaid_across_all = 0.0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cost  = (float)$row['total_treatment_cost'];
        $paid  = (float)$row['total_paid'];

        // Remaining = total cost minus what was paid (downpayment)
        // If total_treatment_cost was never set, fall back to showing paid amount as the bill
        $unpaid_due = ($cost > 0) ? max(0, $cost - $paid) : 0;

        $total_unpaid_across_all += $unpaid_due;

        $date_label = !empty($row['appointment_date'])
            ? date("M d, Y", strtotime($row['appointment_date']))
            : 'Date N/A';

        $bills[] = [
            "booking_id"       => $row['booking_id'],
            "appointment_date" => $date_label,
            "total_cost"       => $cost,
            "amount_paid"      => $paid,
            "unpaid_due"       => $unpaid_due
        ];
    }

    echo json_encode([
        "success" => true, 
        "total_unpaid_across_all" => $total_unpaid_across_all,
        "bills" => $bills
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
