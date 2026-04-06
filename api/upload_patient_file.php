<?php
// api/upload_patient_file.php
// Handles file uploads from the mobile app to tbl_patient_files

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

require_once '../db.php';

// --- Validate inputs ---
$user_id      = $_POST['user_id']       ?? null;
$patient_id   = $_POST['patient_id']    ?? $_POST['user_id'] ?? null;
$tenant_id    = $_POST['tenant_id']     ?? null;
$file_category = $_POST['file_category'] ?? 'General'; // e.g., X-Ray, Prescription, General

if (!$user_id || !$tenant_id) {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["success" => false, "message" => "No file uploaded or upload error."]);
    exit;
}

$file     = $_FILES['file'];
$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$fileSize = $file['size'];

// --- Allowed types ---
$allowed = ['jpg', 'jpeg', 'png', 'pdf'];
if (!in_array($ext, $allowed)) {
    echo json_encode(["success" => false, "message" => "Invalid file type. Only JPG, PNG, PDF allowed."]);
    exit;
}

// --- Max 10MB ---
if ($fileSize > 10 * 1024 * 1024) {
    echo json_encode(["success" => false, "message" => "File too large. Max 10MB allowed."]);
    exit;
}

// --- Determine MIME type for file_type column ---
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'pdf'  => 'application/pdf',
];
$fileType = $mimeMap[$ext] ?? 'application/octet-stream';

// --- Save file to server ---
$uploadDir = '../uploads/patient_files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$uniqueName = uniqid('pf_', true) . '.' . $ext;
$destPath   = $uploadDir . $uniqueName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(["success" => false, "message" => "Failed to save file to server."]);
    exit;
}

$filePath = 'uploads/patient_files/' . $uniqueName;

// --- Insert into tbl_patient_files (matching real schema) ---
try {
    $stmt = $pdo->prepare("
        INSERT INTO tbl_patient_files 
            (tenant_id, patient_id, file_name, file_path, file_type, file_size, file_category, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $tenant_id,
        $patient_id,
        $origName,
        $filePath,
        $fileType,
        $fileSize,
        $file_category,
        $user_id
    ]);

    echo json_encode([
        "success"   => true,
        "message"   => "File uploaded successfully.",
        "file_path" => $filePath,
        "file_name" => $origName
    ]);
} catch (PDOException $e) {
    // Clean up if DB insert fails
    @unlink($destPath);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?>
