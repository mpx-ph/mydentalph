<?php
// api/upload_patient_file.php
// Mobile app file upload — writes to tbl_patient_files (correct mobile schema)

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);

require_once '../db.php';

$user_id         = $_POST['user_id']       ?? null;
$tenant_id       = $_POST['tenant_id']     ?? null;
$file_category   = trim($_POST['file_category'] ?? 'General');
$postpatient_raw = trim((string)($_POST['patient_id'] ?? ''));

if (!$user_id || !$tenant_id) {
    echo json_encode(["success" => false, "message" => "Missing user_id or tenant_id."]);
    exit;
}

// Validate file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "No file uploaded or upload error."]);
    exit;
}

$file = $_FILES['file'];

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(["success" => false, "message" => "File too large. Max 10MB."]);
    exit;
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

if (!in_array($mimeType, $allowed)) {
    echo json_encode(["success" => false, "message" => "Invalid file type. Only JPG, PNG, PDF allowed."]);
    exit;
}

// Resolve patient_id: optional POST (household / dependent booking) must match tenant + user access
$patientId = null;
try {
    if ($postpatient_raw !== '') {
        $stmt = $pdo->prepare("
            SELECT patient_id FROM tbl_patients
            WHERE tenant_id = ? AND patient_id = ?
              AND (owner_user_id = ? OR linked_user_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$tenant_id, $postpatient_raw, $user_id, $user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['patient_id'])) {
            $patientId = (string)$row['patient_id'];
        }
    }
    if ($patientId === null) {
        $stmt = $pdo->prepare("
            SELECT patient_id FROM tbl_patients
            WHERE linked_user_id = ? AND tenant_id = ?
            ORDER BY patient_id DESC LIMIT 1
        ");
        $stmt->execute([$user_id, $tenant_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($patient && !empty($patient['patient_id'])) {
            $patientId = (string)$patient['patient_id'];
        }
    }
} catch (PDOException $e) {
    $patientId = null;
}

// Save file to server
$uploadDir = __DIR__ . '/../uploads/patient_files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$uniqueName = 'mob_' . uniqid('', true) . '.' . $ext;
$destPath   = $uploadDir . $uniqueName;
$relPath    = 'uploads/patient_files/' . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(["success" => false, "message" => "Failed to save file."]);
    exit;
}

// Insert into tbl_patient_files
try {
    $stmt = $pdo->prepare("
        INSERT INTO tbl_patient_files
            (tenant_id, patient_id, file_name, file_path, file_type, file_size, file_category, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $tenant_id,
        $patientId,    // may be null if no patient profile yet
        $file['name'],
        $relPath,
        $mimeType,
        $file['size'],
        $file_category,
        $user_id       // uploaded_by = user_id
    ]);

    echo json_encode([
        "success"    => true,
        "message"    => "File uploaded successfully.",
        "file_name"  => $file['name'],
        "file_path"  => $relPath,
        "patient_id" => $patientId
    ]);

} catch (PDOException $e) {
    @unlink($destPath);
    echo json_encode(["success" => false, "message" => "DB error: " . $e->getMessage()]);
}
?>
