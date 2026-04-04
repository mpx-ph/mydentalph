<?php
// api/get_dentists.php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(["status" => "error", "message" => "GET required"]));
}

$tenant_id = $_GET['tenant_id'] ?? '';

if (empty($tenant_id)) {
    die(json_encode(["status" => "error", "message" => "Missing tenant_id"]));
}

try {
    $stmt = $pdo->prepare(
        "SELECT dentist_id, first_name, last_name, specialization 
         FROM tbl_dentists 
         WHERE tenant_id = ? AND is_active = 1"
    );
    $stmt->execute([$tenant_id]);
    $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // In case there is no is_active column or the query fails due to it, let's gracefully fall back
    if ($dentists === false || empty($dentists)) {
        $stmt = $pdo->prepare(
            "SELECT dentist_id, first_name, last_name, specialization 
             FROM tbl_dentists 
             WHERE tenant_id = ?"
        );
        $stmt->execute([$tenant_id]);
        $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "status" => "success",
        "dentists" => $dentists
    ]);

} catch (Exception $e) {
    // If is_active doesn't exist, PDO throws an exception. We catch it and retry without is_active.
    try {
        $stmt = $pdo->prepare(
            "SELECT dentist_id, first_name, last_name, specialization 
             FROM tbl_dentists 
             WHERE tenant_id = ?"
        );
        $stmt->execute([$tenant_id]);
        $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "dentists" => $dentists]);
    } catch (Exception $ex) {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $ex->getMessage()]);
    }
}
?>
