<?php
// api/upload_patient_file.php
// Mobile app upload endpoint — uses the same patient_files table as the web portal

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// Suppress warnings for clean JSON
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);

require_once '../db.php'; // Provides $pdo

// --- Required inputs ---
$user_id       = $_POST['user_id']       ?? null;
$tenant_id     = $_POST['tenant_id']     ?? null;
$file_category = $_POST['file_category'] ?? 'General';

if (!$user_id || !$tenant_id) {
    echo json_encode(["success" => false, "message" => "Missing user_id or tenant_id."]);
    exit;
}

// --- Validate file ---
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "No file uploaded or upload error."]);
    exit;
}

$file = $_FILES['file'];

// Max 10MB
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(["success" => false, "message" => "File too large. Max 10MB allowed."]);
    exit;
}

// Allowed types
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

if (!in_array($mimeType, $allowed)) {
    echo json_encode(["success" => false, "message" => "Invalid file type. Only JPG, PNG, PDF allowed."]);
    exit;
}

// --- Resolve the patient_id (varchar like P-2024-00001) from patients table ---
// The web portal stores files linked to patients.patient_id, NOT to tbl_users.user_id
try {
    $stmt = $pdo->prepare("
        SELECT patient_id 
        FROM patients 
        WHERE linked_user_id = ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient || empty($patient['patient_id'])) {
        // Patient profile not yet created — use user_id as fallback
        // This ensures the record is still saved and can be manually linked later
        $patientId = $user_id;
    } else {
        $patientId = $patient['patient_id'];
    }
} catch (PDOException $e) {
    // If patients table doesn't exist yet, fallback gracefully
    $patientId = $user_id;
}

// --- Save file to /uploads/patient_files/ ---
$uploadDir = __DIR__ . '/../uploads/patient_files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$uniqueName = uniqid('mob_', true) . '.' . $ext;
$destPath   = $uploadDir . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(["success" => false, "message" => "Failed to save file to server."]);
    exit;
}

$relativePath = 'uploads/patient_files/' . $uniqueName;

// --- Insert into patient_files (same table used by the web portal) ---
try {
    $stmt = $pdo->prepare("
        INSERT INTO patient_files 
            (patient_id, file_name, file_path, file_type, file_size, file_category, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $patientId,        // patients.patient_id (varchar)
        $file['name'],     // original filename
        $relativePath,     // relative path to file
        $mimeType,         // MIME type
        $file['size'],     // file size in bytes
        $file_category,    // e.g. "General", "X-Ray", "Prescription"
        $user_id           // uploaded_by = user_id
    ]);

    echo json_encode([
        "success"     => true,
        "message"     => "File uploaded successfully.",
        "file_path"   => $relativePath,
        "file_name"   => $file['name'],
        "patient_id"  => $patientId
    ]);

} catch (PDOException $e) {
    // Clean up orphan file if DB insert fails
    @unlink($destPath);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
