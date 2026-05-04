<?php
// api/get_services.php - Fetch services for a specific tenant

declare(strict_types=1);

header("Content-Type: application/json");

require_once '../db.php';

$tenant_id = $_GET['tenant_id'] ?? $_POST['tenant_id'] ?? null;
$tenant_id = is_string($tenant_id) ? trim($tenant_id) : '';

/** Comma-separated or single billing type filter, e.g. `included_plan` (plan-follow-up scheduling). */
$onlyTypesRaw = $_GET['only_service_type'] ?? $_POST['only_service_type'] ?? '';
$onlyTypesRaw = is_string($onlyTypesRaw) ? trim($onlyTypesRaw) : '';

if ($tenant_id === '') {
    echo json_encode([
        "success" => false,
        "message" => "Tenant ID is required.",
    ]);
    exit;
}

$onlyTypes = array_values(array_filter(array_map(static function ($p) {
    return strtolower(trim($p));
}, explode(',', $onlyTypesRaw))));
$includedPlanOnly = $onlyTypes === ['included_plan']
    || (count($onlyTypes) === 1 && $onlyTypes[0] === 'included_plan');

try {
    $svcFilterSql = '';
    if ($includedPlanOnly) {
        $svcFilterSql = " AND LOWER(TRIM(COALESCE(service_type, 'regular'))) = 'included_plan' ";
    }

    $stmt = $pdo->prepare("
        SELECT service_id,
               service_name,
               service_details,
               category,
               price,
               COALESCE(enable_installment, 0) AS enable_installment,
               COALESCE(installment_duration_months, 0) AS installment_duration_months,
               downpayment_percentage,
               installment_downpayment,
               LOWER(TRIM(COALESCE(service_type, 'regular'))) AS service_type
        FROM tbl_services
        WHERE tenant_id = ? AND status = 'active'
        {$svcFilterSql}
    ");
    $stmt->execute([$tenant_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "services" => $services,
    ]);
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage(),
    ]);
}
