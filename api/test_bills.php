<?php
require_once '../db.php';
header('Content-Type: application/json');

$patient_id = 'P-2026-00013';
$tenant_id = 'TNT_00025';

$sql = "
    SELECT 
        p.booking_id,
        a.appointment_date,
        a.status as appt_status,
        COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
        SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) AS total_paid_completed,
        MAX(CASE WHEN p.payment_type = 'downpayment' THEN p.amount ELSE 0 END) AS amount_to_calculate,
        SUM(CASE WHEN p.payment_type = 'downpayment' THEN 1 ELSE 0 END) as down_count
    FROM tbl_payments p
    LEFT JOIN tbl_appointments a 
        ON a.booking_id = p.booking_id AND a.tenant_id = p.tenant_id
    WHERE p.patient_id = ?
      AND p.tenant_id = ?
    GROUP BY p.booking_id, a.appointment_date, a.total_treatment_cost, a.status
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$patient_id, $tenant_id]);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
