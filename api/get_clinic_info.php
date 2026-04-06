<?php
// api/get_clinic_info.php
// Returns the tenant_id and clinic info for the current API server.
// The mobile app calls this once on startup to know which clinic it belongs to.

header("Content-Type: application/json");
require_once '../db.php';

// The clinic tenant ID is stored in a setting or can be resolved from the domain.
// For a white-label app, the tenant_id is fixed per deployment.
// We read it from clinic_customization which holds the active clinic config.

try {
    // Get the primary active tenant for this installation
    $stmt = $pdo->prepare("
        SELECT t.tenant_id, t.clinic_name, t.subdomain
        FROM tbl_tenants t
        WHERE t.is_active = 1
        ORDER BY t.id ASC
        LIMIT 1
    ");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($tenant) {
        echo json_encode([
            "success"     => true,
            "tenant_id"   => $tenant['tenant_id'],
            "clinic_name" => $tenant['clinic_name'],
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "No active clinic found."]);
    }
} catch (PDOException $e) {
    // Fallback: try without is_active column (older schema)
    try {
        $stmt = $pdo->prepare("
            SELECT tenant_id, clinic_name 
            FROM tbl_tenants 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute();
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tenant) {
            echo json_encode([
                "success"     => true,
                "tenant_id"   => $tenant['tenant_id'],
                "clinic_name" => $tenant['clinic_name'],
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "No clinic found."]);
        }
    } catch (PDOException $e2) {
        echo json_encode(["success" => false, "message" => "DB error: " . $e2->getMessage()]);
    }
}
?>
