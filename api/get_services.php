<?php
// api/get_services.php - Fetch services for a specific tenant

header("Content-Type: application/json");

// Import database connection
require_once '../db.php';

// Get tenant_id from request
$tenant_id = $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? null;

if (!$tenant_id) {
    echo json_encode([
        "success" => false,
        "message" => "Tenant ID is required."
    ]);
    exit;
}

try {
    // Fetch active services for the tenant
    $stmt = $pdo->prepare("
        SELECT service_id,
               service_name,
               service_details,
               category,
               price,
               COALESCE(enable_installment, 0) AS enable_installment,
               COALESCE(installment_duration_months, 0) AS installment_duration_months
        FROM tbl_services
        WHERE tenant_id = ? AND status = 'active'
    ");
    $stmt->execute([$tenant_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "services" => $services
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false, 
        "message" => "Database error: " . $e->getMessage()
    ]);
}
