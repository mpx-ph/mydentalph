<?php
// api/get_my_files.php
// Returns all files uploaded by the logged-in user
// Uses uploaded_by (always reliable) as the primary key — no strict tenant filter

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);

require_once '../db.php';

$user_id   = $_GET['user_id']   ?? null;
$tenant_id = $_GET['tenant_id'] ?? null;

if (!$user_id) {
    echo json_encode(["success" => false, "message" => "Missing user_id."]);
    exit;
}

try {
    // Primary query: find all files this user ever uploaded (any tenant)
    // uploaded_by is ALWAYS set to user_id so this is the most reliable anchor
    $stmt = $pdo->prepare("
        SELECT id, tenant_id, patient_id, file_name, file_path,
               file_type, file_size, file_category, created_at
        FROM tbl_patient_files
        WHERE uploaded_by = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If tenant_id provided but nothing found, also try patient_id match
    if (empty($files) && $tenant_id) {
        // Try resolving patient_id
        try {
            $stmt2 = $pdo->prepare("
                SELECT patient_id FROM tbl_patients
                WHERE linked_user_id = ?
                ORDER BY patient_id DESC LIMIT 1
            ");
            $stmt2->execute([$user_id]);
            $pat = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($pat && !empty($pat['patient_id'])) {
                $stmt3 = $pdo->prepare("
                    SELECT id, tenant_id, patient_id, file_name, file_path,
                           file_type, file_size, file_category, created_at
                    FROM tbl_patient_files
                    WHERE patient_id = ?
                    ORDER BY created_at DESC
                ");
                $stmt3->execute([$pat['patient_id']]);
                $files = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {}
    }

    // Build file URLs
    $scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'];
    $baseUrl = $scheme . '://' . $host . '/';

    foreach ($files as &$file) {
        $path = ltrim($file['file_path'], '/');
        $file['file_url']       = $baseUrl . $path;
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
