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

    // 2. Fetch bookings where a downpayment was made but balance still remains
    $sql = "
        SELECT 
            a.booking_id,
            a.appointment_date,
            a.total_treatment_cost,
            COALESCE(SUM(CASE WHEN p.status IN ('completed', 'pending') THEN p.amount ELSE 0 END), 0) as total_paid
        FROM tbl_appointments a
        INNER JOIN tbl_payments p ON a.booking_id = p.booking_id AND a.tenant_id = p.tenant_id
        WHERE a.patient_id = ? AND a.tenant_id = ? AND a.total_treatment_cost > 0 AND a.status != 'cancelled'
          AND p.payment_type = 'downpayment'
        GROUP BY a.id, a.booking_id, a.appointment_date, a.total_treatment_cost
        HAVING total_paid < a.total_treatment_cost
        ORDER BY a.appointment_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id, $tenant_id]);
    
    $bills = [];
    $total_unpaid_across_all = 0.0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cost = (float)$row['total_treatment_cost'];
        $paid = (float)$row['total_paid'];
        $unpaid_due = $cost - $paid;

        if ($unpaid_due > 0) {
            $total_unpaid_across_all += $unpaid_due;
            $bills[] = [
                "booking_id"       => $row['booking_id'],
                "appointment_date" => date("M d, Y", strtotime($row['appointment_date'])),
                "total_cost"       => $cost,
                "amount_paid"      => $paid,
                "unpaid_due"       => $unpaid_due
            ];
        }
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
