<?php
// api/get_my_files.php
// Returns all patient files uploaded by the current user

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once '../db.php';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);

$user_id   = $_GET['user_id']   ?? null;
$tenant_id = $_GET['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    echo json_encode(["success" => false, "message" => "Missing user_id or tenant_id."]);
    exit;
}

try {
    // Resolve patient_id from patients table
    $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE linked_user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $patientId = $patient['patient_id'] ?? $user_id;

    // Fetch all non-deleted files for this patient
    $stmt = $pdo->prepare("
        SELECT id, file_name, file_path, file_type, file_size, file_category, created_at
        FROM patient_files
        WHERE patient_id = ?
          AND updated_at IS NULL
        ORDER BY created_at DESC
    ");
    $stmt->execute([$patientId]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build full URLs
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST'] . '/';

    foreach ($files as &$file) {
        $file['file_url']       = $baseUrl . $file['file_path'];
        $file['formatted_size'] = formatBytes($file['file_size']);
        $file['formatted_date'] = date('M j, Y', strtotime($file['created_at']));
    }

    echo json_encode(["success" => true, "files" => $files]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB error: " . $e->getMessage()]);
}

function formatBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>
