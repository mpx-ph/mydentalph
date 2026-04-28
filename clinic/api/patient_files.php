<?php
/**
 * Patient Files API Endpoint
 * Handles file upload, retrieval, and deletion for patient files
 */

// Suppress warnings/notices for clean JSON output
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/appointment_db_tables.php';

header('Content-Type: application/json');

$patientFilesTableName = null;
$patientsTableName = null;

register_shutdown_function(static function () {
    $lastError = error_get_last();
    if ($lastError === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($lastError['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    $message = trim((string) ($lastError['message'] ?? ''));
    echo json_encode([
        'success' => false,
        'message' => $message !== '' ? ('Fatal error: ' . $message) : 'Fatal error while processing patient files.'
    ]);
});

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $pdo = getDBConnection();
    $resolveTable = static function (PDO $db, string $primary, string $secondary): ?string {
        if (function_exists('clinic_get_physical_table_name')) {
            return clinic_get_physical_table_name($db, $primary)
                ?? clinic_get_physical_table_name($db, $secondary);
        }
        return null;
    };
    $patientFilesTableName = $resolveTable($pdo, 'patient_files', 'tbl_patient_files')
        ?? 'patient_files';
    $patientsTableName = $resolveTable($pdo, 'patients', 'tbl_patients')
        ?? 'patients';

    // Require authentication (client, manager, doctor, or staff)
    if (!isLoggedIn('client') && !isLoggedIn('manager') && !isLoggedIn('doctor') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Please log in.');
    }

    // Route based on method
    switch ($method) {
        case 'POST':
            uploadPatientFile();
            break;
        case 'GET':
            // Check if this is a verification status request
            if (isset($_GET['verification_status']) && $_GET['verification_status'] == '1') {
                getVerificationStatus();
            } else {
                getFiles();
            }
            break;
        case 'DELETE':
            deleteFile();
            break;
        default:
            jsonResponse(false, 'Invalid request method.');
    }
} catch (PDOException $e) {
    error_log('Database error in patient_files.php: ' . $e->getMessage());
    jsonResponse(false, 'Database error: ' . ($e->getMessage()));
} catch (Exception $e) {
    error_log('Error in patient_files.php: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred: ' . $e->getMessage());
}

/**
 * Upload patient file
 */
function uploadPatientFile() {
    global $pdo, $patientFilesTableName, $patientsTableName;
    $quotedPatientFilesTable = clinic_quote_identifier((string) $patientFilesTableName);
    $quotedPatientsTable = clinic_quote_identifier((string) $patientsTableName);
    
    $userId = getCurrentUserId(); // users.id (int)
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    // Get user's user_id (varchar)
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(false, 'User not found.');
    }
    
    $userUserId = $user['user_id']; // user_id (varchar)
    $userType = $_SESSION['user_type'] ?? 'client';
    
    // Check if patient_id (varchar) is provided directly (for manager/doctor/staff)
    $patientIdParam = isset($_POST['patient_id']) ? sanitize($_POST['patient_id']) : null;
    if (($userType === 'manager' || $userType === 'doctor' || $userType === 'staff') && !empty($patientIdParam)) {
        $patientId = trim((string) $patientIdParam);
    } else {
        $patientId = null;
    }

    // Check if patient_db_id (int id) is provided in POST data
    $patientDbId = isset($_POST['patient_db_id']) ? intval($_POST['patient_db_id']) : null;
    
    if ($patientId !== null && $patientId !== '') {
        // Staff-provided patient_id accepted as-is after basic existence check
        $stmt = $pdo->prepare("
            SELECT patient_id FROM {$quotedPatientsTable}
            WHERE patient_id = ?
            LIMIT 1
        ");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();
        if (!$patient || !$patient['patient_id']) {
            jsonResponse(false, 'Patient profile not found.');
        }
    } elseif ($patientDbId) {
        if ($userType === 'manager' || $userType === 'doctor' || $userType === 'staff') {
            $stmt = $pdo->prepare("
                SELECT patient_id FROM {$quotedPatientsTable}
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$patientDbId]);
            $patient = $stmt->fetch();
            if (!$patient || !$patient['patient_id']) {
                jsonResponse(false, 'Patient profile not found.');
            }
            $patientId = $patient['patient_id'];
        } else {
        // Use provided patient_db_id (int id) and verify it belongs to the user
        $stmt = $pdo->prepare("
            SELECT patient_id FROM {$quotedPatientsTable}
            WHERE id = ? AND owner_user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$patientDbId, $userUserId]);
        $patient = $stmt->fetch();
        
        if (!$patient || !$patient['patient_id']) {
            jsonResponse(false, 'Patient profile not found or access denied.');
        }
        
        $patientId = $patient['patient_id']; // This is the varchar patient_id from patients table
        }
    } else {
        // Fallback: Get patient_id (varchar) from patients table - use self profile (linked_user_id = user_id)
        $stmt = $pdo->prepare("
            SELECT patient_id FROM {$quotedPatientsTable}
            WHERE linked_user_id = ? AND owner_user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$userUserId, $userUserId]);
        $patient = $stmt->fetch();
        
        if (!$patient || !$patient['patient_id']) {
            jsonResponse(false, 'Patient profile not found. Please complete your profile first.');
        }
        
        $patientId = $patient['patient_id']; // This is the varchar patient_id from patients table
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'No file uploaded or upload error occurred.');
    }
    
    $file = $_FILES['file'];
    
    // Validate file size (max 10MB)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        jsonResponse(false, 'File size exceeds maximum limit of 10MB.');
    }
    
    // Validate file type (allow images, PDFs, and common document types)
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(false, 'Invalid file type. Allowed types: Images, PDF, Word, Excel.');
    }
    
    // Create upload directory if it doesn't exist
    $uploadDir = ROOT_PATH . 'uploads/patient_files/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('file_', true) . '.' . $extension;
    $filepath = $uploadDir . $filename;
    $relativePath = 'uploads/patient_files/' . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        jsonResponse(false, 'Failed to save file.');
    }
    
    // Get file category and description from POST data (optional)
    $fileCategory = sanitize($_POST['file_category'] ?? 'General');
    $description = !empty($_POST['description']) ? sanitize($_POST['description']) : null;
    
    // Insert file record into database
    // Using patients.patient_id (varchar) to identify the patient
    try {
        $stmt = $pdo->prepare("
            INSERT INTO {$quotedPatientFilesTable}
            (patient_id, file_name, file_path, file_type, file_size, file_category, description, uploaded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $patientId, // This is patients.patient_id (varchar like "P-2024-00001")
            $file['name'], // Original filename
            $relativePath,
            $mimeType,
            $file['size'],
            $fileCategory,
            $description,
            $userUserId
        ]);
        
        $fileId = $pdo->lastInsertId();
        
        // Get the created file record
        $stmt = $pdo->prepare("SELECT * FROM {$quotedPatientFilesTable} WHERE id = ?");
        $stmt->execute([$fileId]);
        $fileRecord = $stmt->fetch();
        
        jsonResponse(true, 'File uploaded successfully.', ['file' => $fileRecord]);
        
    } catch (Exception $e) {
        // Delete uploaded file if database insert fails
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        error_log('File upload error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to save file record: ' . $e->getMessage());
    }
}

/**
 * Get patient files
 */
function getFiles() {
    global $pdo, $patientFilesTableName, $patientsTableName;
    $quotedPatientFilesTable = clinic_quote_identifier((string) $patientFilesTableName);
    $quotedPatientsTable = clinic_quote_identifier((string) $patientsTableName);
    
    $userId = getCurrentUserId(); // users.id (int)
    $userType = $_SESSION['user_type'] ?? 'client';
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    // Get user's user_id (varchar)
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(false, 'User not found.');
    }
    
    $userUserId = $user['user_id']; // user_id (varchar)
    
    // Check if patient_id (varchar) is provided directly (for admin/doctor/staff)
    $patientIdParam = isset($_GET['patient_id']) ? sanitize($_GET['patient_id']) : null;
    
    // Check if patient_db_id (int id) is provided in GET data
    $patientDbId = isset($_GET['patient_db_id']) ? intval($_GET['patient_db_id']) : null;
    
    $patientId = null;
    
    // Manager/Doctor/Staff can access files by patient_id directly
    if (($userType === 'manager' || $userType === 'doctor' || $userType === 'staff') && $patientIdParam) {
        $patientId = trim($patientIdParam);
    } elseif ($patientDbId) {
        // For clients or when patient_db_id is provided, verify ownership
        if ($userType === 'client') {
            // Use provided patient_db_id (int id) and verify it belongs to the user
            $stmt = $pdo->prepare("
                SELECT patient_id FROM {$quotedPatientsTable}
                WHERE id = ? AND owner_user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$patientDbId, $userUserId]);
            $patient = $stmt->fetch();
            
            if (!$patient || !$patient['patient_id']) {
                jsonResponse(false, 'Patient profile not found or access denied.');
            }
            
            $patientId = trim($patient['patient_id']);
        } else {
            // Admin/Doctor/Staff can access by patient_db_id without ownership check
            $stmt = $pdo->prepare("
                SELECT patient_id FROM {$quotedPatientsTable}
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$patientDbId]);
            $patient = $stmt->fetch();
            
            if (!$patient || !$patient['patient_id']) {
                jsonResponse(false, 'Patient profile not found.');
            }
            
            $patientId = trim($patient['patient_id']);
        }
    } else {
        // Fallback: Get patient_id (varchar) from patients table - use self profile (linked_user_id = user_id)
        // Only for clients
        if ($userType === 'client') {
            $stmt = $pdo->prepare("
                SELECT patient_id FROM {$quotedPatientsTable}
                WHERE linked_user_id = ? AND owner_user_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmt->execute([$userUserId, $userUserId]);
            $patient = $stmt->fetch();
            
            if (!$patient || !$patient['patient_id']) {
                jsonResponse(true, 'No patient profile found.', ['files' => []]);
            }
            
            $patientId = trim($patient['patient_id']);
        } else {
            jsonResponse(false, 'Patient ID is required.');
        }
    }
    
    if (empty($patientId)) {
        jsonResponse(true, 'No patient profile found.', ['files' => []]);
    }
    
    // Get search parameter
    $search = sanitize($_GET['search'] ?? '');
    
    try {
        // Build query - use updated_at for soft delete (since deleted_at doesn't exist)
        // Files that are soft-deleted will have updated_at set (via UPDATE)
        // Non-deleted files will have updated_at IS NULL (since no updates are made to files)
        if ($search) {
            $sql = "
                SELECT * FROM {$quotedPatientFilesTable}
                WHERE patient_id = ?
                AND updated_at IS NULL
                AND (file_name LIKE ? OR file_category LIKE ?)
                ORDER BY created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $searchTerm = '%' . $search . '%';
            $stmt->execute([$patientId, $searchTerm, $searchTerm]);
        } else {
            $sql = "
                SELECT * FROM {$quotedPatientFilesTable}
                WHERE patient_id = ?
                AND updated_at IS NULL
                ORDER BY created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$patientId]);
        }
        
        $files = $stmt->fetchAll();
        
        // Debug logging (remove in production if needed)
        error_log("Patient Files Query - patient_id: " . $patientId . ", files found: " . count($files));
        
        // Format file data
        foreach ($files as &$file) {
            $file['file_url'] = BASE_URL . $file['file_path'];
            $file['formatted_size'] = formatFileSize($file['file_size']);
            $file['formatted_date'] = date('M j, Y', strtotime($file['created_at']));
        }
        
        // Add cache headers for better performance
        header('Cache-Control: private, max-age=60'); // Cache for 1 minute
        
        jsonResponse(true, 'Files retrieved successfully.', ['files' => $files]);
        
    } catch (Exception $e) {
        error_log('Get files error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve files: ' . $e->getMessage());
    }
}

/**
 * Delete patient file
 */
function deleteFile() {
    global $pdo, $patientFilesTableName, $patientsTableName;
    $quotedPatientFilesTable = clinic_quote_identifier((string) $patientFilesTableName);
    $quotedPatientsTable = clinic_quote_identifier((string) $patientsTableName);
    
    $userId = getCurrentUserId(); // users.id (int)
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    // Get file ID from request
    $input = json_decode(file_get_contents('php://input'), true);
    $fileId = isset($input['file_id']) ? intval($input['file_id']) : 0;
    
    if (!$fileId) {
        jsonResponse(false, 'File ID is required.');
    }
    
    // Get user's user_id (varchar)
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(false, 'User not found.');
    }
    
    $userUserId = $user['user_id']; // user_id (varchar)
    
    // Verify file belongs to user's patient profile
    $stmt = $pdo->prepare("
        SELECT pf.* FROM {$quotedPatientFilesTable} pf
        INNER JOIN {$quotedPatientsTable} p ON pf.patient_id = p.patient_id
        WHERE pf.id = ? AND p.owner_user_id = ?
    ");
    $stmt->execute([$fileId, $userUserId]);
    $file = $stmt->fetch();
    
    if (!$file) {
        jsonResponse(false, 'File not found or access denied.');
    }
    
    try {
        // Soft delete: Update updated_at timestamp instead of deleting the record
        // Since no updates are made to files, setting updated_at marks it as deleted
        // Non-deleted files will have updated_at IS NULL
        $stmt = $pdo->prepare("UPDATE {$quotedPatientFilesTable} SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$fileId]);
        
        // Note: Physical file is kept for potential recovery/audit purposes
        // If you want to delete the physical file as well, uncomment the following:
        // $filepath = ROOT_PATH . $file['file_path'];
        // if (file_exists($filepath)) {
        //     unlink($filepath);
        // }
        
        jsonResponse(true, 'File deleted successfully.');
        
    } catch (Exception $e) {
        error_log('Delete file error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to delete file: ' . $e->getMessage());
    }
}

/**
 * Get verification status for a patient
 */
function getVerificationStatus() {
    global $pdo, $patientFilesTableName, $patientsTableName;
    $quotedPatientFilesTable = clinic_quote_identifier((string) $patientFilesTableName);
    $quotedPatientsTable = clinic_quote_identifier((string) $patientsTableName);
    
    $userId = getCurrentUserId();
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    // Get user's user_id (varchar)
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(false, 'User not found.');
    }
    
    $userUserId = $user['user_id'];
    
    // Get patient_id from query parameter or use self profile
    $patientIdParam = isset($_GET['patient_id']) ? sanitize($_GET['patient_id']) : null;
    $patientDbId = isset($_GET['patient_db_id']) ? intval($_GET['patient_db_id']) : null;
    
    $patientId = null;
    
    if ($patientIdParam) {
        // Check if it's a varchar patient_id (starts with 'P-') or integer ID
        if (preg_match('/^P-\d{4}-/', $patientIdParam)) {
            // It's already a varchar patient_id
            $patientId = trim($patientIdParam);
            error_log("Verification status: Using varchar patient_id directly: " . $patientId);
        } else {
            // It's likely an integer ID, convert it
            error_log("Verification status: Converting integer ID to varchar: " . $patientIdParam);
            $stmt = $pdo->prepare("SELECT patient_id FROM {$quotedPatientsTable} WHERE id = ? AND owner_user_id = ?");
            $stmt->execute([intval($patientIdParam), $userUserId]);
            $patient = $stmt->fetch();
            if ($patient && $patient['patient_id']) {
                $patientId = trim($patient['patient_id']);
                error_log("Verification status: Converted to varchar patient_id: " . $patientId);
            } else {
                error_log("Verification status: Failed to convert patient_id - patient not found");
            }
        }
    } elseif ($patientDbId) {
        // Get patient_id (varchar) from patient_db_id
        $stmt = $pdo->prepare("SELECT patient_id FROM {$quotedPatientsTable} WHERE id = ? AND owner_user_id = ?");
        $stmt->execute([$patientDbId, $userUserId]);
        $patient = $stmt->fetch();
        
        if ($patient && $patient['patient_id']) {
            $patientId = trim($patient['patient_id']);
        }
    } else {
        // Use self profile
        $stmt = $pdo->prepare("
            SELECT patient_id FROM {$quotedPatientsTable}
            WHERE linked_user_id = ? AND owner_user_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$userUserId, $userUserId]);
        $patient = $stmt->fetch();
        
        if ($patient && $patient['patient_id']) {
            $patientId = trim($patient['patient_id']);
        }
    }
    
    if (empty($patientId)) {
        jsonResponse(true, 'No patient profile found.', ['verification_status' => ['is_verified' => false, 'type' => null]]);
    }
    
    // Check for verification files (PWD or SC)
    try {
        // Use updated_at for soft delete check (since deleted_at doesn't exist)
        // Non-deleted files will have updated_at IS NULL
        
        // Debug: Check all files for this patient to see what's in the database
        error_log("Verification check - Querying with patient_id: " . $patientId);
        $debugSql = "SELECT id, patient_id, description, file_category, created_at FROM {$quotedPatientFilesTable} WHERE patient_id = ? AND updated_at IS NULL ORDER BY created_at DESC";
        $debugStmt = $pdo->prepare($debugSql);
        $debugStmt->execute([$patientId]);
        $allFiles = $debugStmt->fetchAll();
        error_log("Verification check - Patient ID: " . $patientId . ", Files found: " . count($allFiles));
        if (count($allFiles) > 0) {
            error_log("Files for patient: " . json_encode($allFiles));
            // Check if any files have PWD or SC in description
            foreach ($allFiles as $file) {
                error_log("File ID " . $file['id'] . ": description='" . ($file['description'] ?? 'NULL') . "', file_category='" . ($file['file_category'] ?? 'NULL') . "'");
            }
        } else {
            // Check if patient_id exists in patients table
            $checkPatientStmt = $pdo->prepare("SELECT id, patient_id FROM {$quotedPatientsTable} WHERE patient_id = ? OR id = ?");
            $checkPatientStmt->execute([$patientId, intval($patientId)]);
            $patientCheck = $checkPatientStmt->fetchAll();
            error_log("Patient lookup result: " . json_encode($patientCheck));
        }
        
        // Check for verification files (PWD or SC) - patient is automatically verified if file exists
        $sql = "
            SELECT description, created_at 
            FROM {$quotedPatientFilesTable}
            WHERE patient_id = ? 
            AND description IN ('PWD', 'SC')
            AND updated_at IS NULL
            ORDER BY created_at DESC LIMIT 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$patientId]);
        $verificationFile = $stmt->fetch();
        
        if ($verificationFile) {
            // Patient is automatically verified when they submit their ID
            $verificationType = $verificationFile['description'];
            error_log("Verification found: " . $verificationType . " for patient " . $patientId);
            jsonResponse(true, 'Verification status retrieved.', [
                'verification_status' => [
                    'is_verified' => true, // Automatically verified upon submission
                    'type' => $verificationType,
                    'verified_date' => $verificationFile['created_at']
                ]
            ]);
        } else {
            error_log("No verification file found for patient " . $patientId);
            jsonResponse(true, 'No verification found.', [
                'verification_status' => [
                    'is_verified' => false,
                    'type' => null,
                    'debug_patient_id' => $patientId,
                    'debug_files_count' => count($allFiles)
                ]
            ]);
        }
    } catch (Exception $e) {
        error_log('Get verification status error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve verification status: ' . $e->getMessage());
    }
}

/**
 * Format file size to human-readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
