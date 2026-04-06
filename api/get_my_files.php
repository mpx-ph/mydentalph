<?php
// api/get_my_files.php
// Returns all files uploaded by the logged-in user from tbl_patient_files
// Ordered newest → oldest

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);

require_once '../db.php';

$user_id   = $_GET['user_id']   ?? null;
$tenant_id = $_GET['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    echo json_encode(["success" => false, "message" => "Missing user_id or tenant_id."]);
    exit;
}

try {
    // 1. Resolve patient_id from tbl_patients (linked to this user + tenant)
    $stmt = $pdo->prepare("
        SELECT patient_id FROM tbl_patients
        WHERE linked_user_id = ? AND tenant_id = ?
        ORDER BY patient_id DESC LIMIT 1
    ");
    $stmt->execute([$user_id, $tenant_id]);
    $patient   = $stmt->fetch(PDO::FETCH_ASSOC);
    $patientId = $patient['patient_id'] ?? null;

    // 2. Query files — match by uploaded_by (always set) + optionally patient_id
    if ($patientId) {
        $stmt = $pdo->prepare("
            SELECT id, file_name, file_path, file_type, file_size, file_category, created_at
            FROM tbl_patient_files
            WHERE tenant_id = ?
              AND (patient_id = ? OR uploaded_by = ?)
            ORDER BY created_at DESC
        ");
        $stmt->execute([$tenant_id, $patientId, $user_id]);
    } else {
        // Fallback: no patient profile yet — match by uploaded_by only
        $stmt = $pdo->prepare("
            SELECT id, file_name, file_path, file_type, file_size, file_category, created_at
            FROM tbl_patient_files
            WHERE tenant_id = ?
              AND uploaded_by = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$tenant_id, $user_id]);
    }

    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build full URLs
    $scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'];
    $baseUrl = $scheme . '://' . $host . '/';

    foreach ($files as &$file) {
        $file['file_url']       = $baseUrl . ltrim($file['file_path'], '/');
        $file['formatted_size'] = formatBytes((int)($file['file_size'] ?? 0));
        $file['formatted_date'] = date('M j, Y', strtotime($file['created_at']));
    }

    echo json_encode([
        "success" => true,
        "count"   => count($files),
        "files"   => $files
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB error: " . $e->getMessage()]);
}

function formatBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024,    1) . ' KB';
    return $bytes . ' B';
}
?>
