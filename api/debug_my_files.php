<?php
// api/debug_my_files.php  — TEMPORARY DEBUG, delete after testing
header("Content-Type: application/json");
require_once '../db.php';

$user_id = $_GET['user_id'] ?? 'not_set';

$results = [];

// Check tbl_patient_files by uploaded_by
try {
    $stmt = $pdo->prepare("SELECT id, tenant_id, patient_id, file_name, file_path, uploaded_by, created_at FROM tbl_patient_files WHERE uploaded_by = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user_id]);
    $results['tbl_patient_files_by_uploaded_by'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $results['tbl_patient_files_error'] = $e->getMessage(); }

// Check tbl_patient_files by patient_id containing user_id
try {
    $stmt = $pdo->prepare("SELECT id, tenant_id, patient_id, file_name, uploaded_by FROM tbl_patient_files WHERE patient_id LIKE ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute(["%$user_id%"]);
    $results['tbl_patient_files_by_patient_id'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Check patient_files table (web portal table) if it exists
try {
    $stmt = $pdo->prepare("SELECT id, patient_id, file_name, file_path, uploaded_by, created_at FROM patient_files WHERE uploaded_by = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$user_id]);
    $results['patient_files_table'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $results['patient_files_table'] = 'table_not_found: ' . $e->getMessage(); }

// Show total counts
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tbl_patient_files");
    $results['tbl_patient_files_total'] = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// All tenant IDs stored in files
try {
    $stmt = $pdo->query("SELECT DISTINCT tenant_id FROM tbl_patient_files LIMIT 10");
    $results['all_tenant_ids_in_table'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

echo json_encode(["user_id_searched" => $user_id, "results" => $results], JSON_PRETTY_PRINT);
?>
