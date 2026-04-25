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

    // 2. Identify all bookings for this patient that have a downpayment record
    //    We sum ALL 'completed' payments (downpayment + remaining balance) for the actual balance calculation.
    $sql = "
        SELECT 
            p.booking_id,
            a.appointment_date,
            COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
            SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) AS total_paid_completed,
            MAX(p.amount) AS amount_to_calculate,
            MAX(a.id) AS id,
            MAX(a.tenant_id) AS tenant_id,
            MAX(a.dentist_id) AS dentist_id,
            MAX(a.patient_id) AS patient_id,
            MAX(a.appointment_time) AS appointment_time,
            MAX(a.service_type) AS service_type,
            MAX(a.service_description) AS service_description,
            MAX(a.insurance) AS insurance,
            MAX(a.treatment_type) AS treatment_type,
            MAX(a.visit_type) AS visit_type,
            MAX(a.status) AS status,
            MAX(a.notes) AS notes,
            MAX(a.duration_months) AS duration_months,
            MAX(a.target_completion_date) AS target_completion_date,
            MAX(a.start_date) AS start_date,
            MAX(a.created_by) AS created_by,
            MAX(a.created_at) AS created_at
        FROM tbl_payments p
        LEFT JOIN tbl_appointments a 
            ON a.booking_id = p.booking_id AND a.tenant_id = p.tenant_id
        WHERE (p.patient_id = ? OR p.created_by = ?)
          AND p.tenant_id = ?
          AND (a.status IS NULL OR a.status != 'cancelled')
        GROUP BY p.booking_id, a.appointment_date, a.total_treatment_cost
        ORDER BY a.appointment_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id, $user_id, $tenant_id]);

    $bills = [];
    $total_unpaid_across_all = 0.0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cost = (float) $row['total_treatment_cost'];
        $paid = (float) $row['total_paid_completed'];
        $base_amount = (float) $row['amount_to_calculate'];

        // Logic:
        // 1. If total cost is not stored, we assume the total was exactly double the downpayment
        $actual_cost = ($cost > 0) ? $cost : ($base_amount * 2);
        
        $unpaid_due = max(0, $actual_cost - $paid);

        // However, if they have absolutely NO completed payments yet (i.e. even the downpayment is pending),
        // we show the downpayment amount as due right now.
        if ($paid == 0) {
            $unpaid_due = $base_amount;
        }

        // Only show bills that still have a balance remaining 
        if ($unpaid_due <= 0)
            continue;

        $total_unpaid_across_all += $unpaid_due;

        $date_label = !empty($row['appointment_date'])
            ? date("M d, Y", strtotime($row['appointment_date']))
            : 'Date N/A';

        $bills[] = array_merge(
            $row,
            [
                "appointment_date_label" => $date_label,
                "total_cost" => ($cost > 0) ? $cost : ($base_amount * 2), // estimate full cost if not stored
                "amount_paid" => $paid,
                "unpaid_due" => $unpaid_due
            ]
        );
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
