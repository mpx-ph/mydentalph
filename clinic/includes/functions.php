<?php
/**
 * General Helper Functions
 */

/**
 * Sanitize input
 * @param string|null $data
 * @return string
 */
function sanitize($data) {
    if ($data === null || $data === '') {
        return '';
    }
    return htmlspecialchars(strip_tags(trim((string)$data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize array
 * @param array $data
 * @return array
 */
function sanitizeArray($data) {
    $sanitized = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = sanitizeArray($value);
        } else {
            $sanitized[$key] = sanitize($value);
        }
    }
    return $sanitized;
}

/**
 * Next formatted dentist display id for a tenant (D-YYYY-XXXXX), same sequencing approach as staff IDs.
 */
function tenant_next_dentist_display_id(PDO $pdo, string $tenantId): string
{
    $tenantId = trim($tenantId);
    $year = date('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_dentists WHERE tenant_id = ? AND dentist_display_id LIKE ?');
    $stmt->execute([$tenantId, 'D-' . $year . '-%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $n = (int) ($row['c'] ?? 0) + 1;
    $sequence = str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    return 'D-' . $year . '-' . $sequence;
}

/**
 * Format date
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'M d, Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 * @param string $datetime
 * @return string
 */
function formatDateTime($datetime) {
    return formatDate($datetime, 'M d, Y h:i A');
}

/**
 * Format timestamp for JavaScript (ISO 8601 with timezone)
 * Converts MySQL datetime to ISO 8601 format with Asia/Manila timezone
 * @param string|null $datetime MySQL datetime string
 * @return string|null ISO 8601 formatted datetime string or null
 */
function formatTimestampForJS($datetime) {
    if (empty($datetime)) {
        return null;
    }
    
    try {
        // Create DateTime object from MySQL datetime string
        // Assume it's in Asia/Manila timezone (since MySQL timezone is set)
        $dt = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
        // Return as ISO 8601 string
        return $dt->format('c'); // 'c' format is ISO 8601 (e.g., 2024-01-15T06:27:00+08:00)
    } catch (Exception $e) {
        error_log("Error formatting timestamp: " . $e->getMessage());
        return $datetime; // Return original if conversion fails
    }
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Generate random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}

/**
 * Redirect
 * @param string $url
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * JSON response
 * @param bool $success
 * @param string $message
 * @param array $data
 */
function jsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Validate date
 * @param string $date
 * @param string $format
 * @return bool
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Get pagination info
 * @param int $currentPage
 * @param int $totalItems
 * @param int $itemsPerPage
 * @return array
 */
function getPaginationInfo($currentPage, $totalItems, $itemsPerPage = ITEMS_PER_PAGE) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;
    
    return [
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'total_items' => $totalItems,
        'items_per_page' => $itemsPerPage,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}

/**
 * Upload file
 * @param array $file
 * @param string $destination
 * @return array ['success' => bool, 'message' => string, 'filename' => string|null]
 */
function uploadFile($file, $destination = 'uploads/') {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file upload.', 'filename' => null];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error.', 'filename' => null];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large.', 'filename' => null];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Invalid file type.', 'filename' => null];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = ROOT_PATH . $destination . $filename;
    
    if (!is_dir(ROOT_PATH . $destination)) {
        mkdir(ROOT_PATH . $destination, 0755, true);
    }
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Failed to save file.', 'filename' => null];
    }
    
    return ['success' => true, 'message' => 'File uploaded successfully.', 'filename' => $filename];
}

/**
 * Generate unique user_id based on user type
 * Format: P-{YEAR}-{5-digit-sequence} for clients, A-{YEAR}-{5-digit-sequence} for admins
 * @param string $userType 'admin' or 'client'
 * @return string Generated user_id
 */
function generateUserId($userType = 'client') {
    // Ensure database connection is available
    if (!function_exists('getDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    
    $pdo = getDBConnection();
    
    // Determine prefix based on user type
    $prefix = ($userType === 'manager') ? 'M' : 'P';
    
    // Get current year
    $year = date('Y');
    
    // Build the pattern to search for existing user_ids
    $pattern = $prefix . '-' . $year . '-%';
    
    // Find the last user_id with this prefix and year
    $stmt = $pdo->prepare("
        SELECT user_id 
        FROM tbl_users 
        WHERE user_id LIKE ? 
        ORDER BY user_id DESC 
        LIMIT 1
    ");
    $stmt->execute([$pattern]);
    $lastUserId = $stmt->fetchColumn();
    
    // Extract sequence number from last user_id, or start at 00001
    if ($lastUserId) {
        // Extract the sequence part (last 5 digits)
        $parts = explode('-', $lastUserId);
        $sequence = intval(end($parts));
        $sequence++;
    } else {
        $sequence = 1;
    }
    
    // Format as 5-digit sequence (00001, 00002, etc.)
    $formattedSequence = str_pad($sequence, 5, '0', STR_PAD_LEFT);
    
    // Generate new user_id
    $userId = $prefix . '-' . $year . '-' . $formattedSequence;
    
    // Double-check uniqueness (in case of race condition)
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn()) {
        // If exists, increment and try again
        $sequence++;
        $formattedSequence = str_pad($sequence, 5, '0', STR_PAD_LEFT);
        $userId = $prefix . '-' . $year . '-' . $formattedSequence;
    }
    
    return $userId;
}

/**
 * Generate unique patient_id
 * Format: P-{YEAR}-{5-digit-sequence}
 * Thread-safe implementation that handles race conditions
 * @return string Generated patient_id
 */
function generatePatientId() {
    // Ensure database connection is available
    if (!function_exists('getDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    
    $pdo = getDBConnection();
    
    // Get current year
    $year = date('Y');
    
    // Build the pattern to search for existing patient_ids
    $pattern = 'P-' . $year . '-%';
    
    // Find the last patient_id with this prefix and year
    // Use ORDER BY patient_id DESC to get the highest sequence number
    $stmt = $pdo->prepare("
        SELECT patient_id 
        FROM tbl_patients 
        WHERE patient_id LIKE ? 
        ORDER BY patient_id DESC 
        LIMIT 1
    ");
    $stmt->execute([$pattern]);
    $lastPatientId = $stmt->fetchColumn();
    
    // Extract sequence number from last patient_id, or start at 00001
    if ($lastPatientId) {
        // Extract the sequence part (last 5 digits after the last dash)
        $parts = explode('-', $lastPatientId);
        $sequence = intval(end($parts));
        $sequence++;
    } else {
        $sequence = 1;
    }
    
    // Retry loop to handle race conditions
    $maxRetries = 10;
    $retryCount = 0;
    
    while ($retryCount < $maxRetries) {
        // Format as 5-digit sequence (00001, 00002, etc.)
        $formattedSequence = str_pad($sequence, 5, '0', STR_PAD_LEFT);
        
        // Generate new patient_id
        $patientId = 'P-' . $year . '-' . $formattedSequence;
        
        // Double-check uniqueness (in case of race condition)
        $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        if (!$stmt->fetchColumn()) {
            // Patient ID is available, return it
            return $patientId;
        }
        
        // If exists, increment and try again
        $sequence++;
        $retryCount++;
    }
    
    // If we've exhausted retries, use a timestamp-based fallback
    $timestamp = time();
    $random = mt_rand(100, 999);
    return 'P-' . $year . '-' . substr($timestamp, -5) . $random;
}

/**
 * Save base64 image to file
 * @param string $base64Data Base64 encoded image data (with or without data URI prefix)
 * @param string $destination Destination directory (relative to ROOT_PATH)
 * @param string $prefix Optional prefix for filename
 * @return array ['success' => bool, 'message' => string, 'filename' => string|null, 'filepath' => string|null]
 */
function saveBase64Image($base64Data, $destination = 'uploads/patients/', $prefix = 'patient_') {
    if (empty($base64Data)) {
        return ['success' => false, 'message' => 'No image data provided.', 'filename' => null, 'filepath' => null];
    }
    
    // Remove data URI prefix if present (e.g., "data:image/png;base64,")
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
        $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
        $imageType = $matches[1];
    } else {
        // Try to detect type from base64 data or default to jpg
        $imageType = 'jpg';
    }
    
    // Validate image type
    $allowedTypes = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
    if (!in_array(strtolower($imageType), $allowedTypes)) {
        $imageType = 'jpg'; // Default to jpg if unknown
    }
    
    // Decode base64 data
    $imageData = base64_decode($base64Data, true);
    if ($imageData === false) {
        return ['success' => false, 'message' => 'Invalid base64 data.', 'filename' => null, 'filepath' => null];
    }
    
    // Validate image data
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->buffer($imageData);
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'Invalid image type.', 'filename' => null, 'filepath' => null];
    }
    
    // Check file size
    if (strlen($imageData) > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'Image too large.', 'filename' => null, 'filepath' => null];
    }
    
    // Generate filename
    $extension = ($imageType === 'jpeg') ? 'jpg' : $imageType;
    $filename = $prefix . uniqid() . '.' . $extension;
    $filepath = ROOT_PATH . $destination . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir(ROOT_PATH . $destination)) {
        mkdir(ROOT_PATH . $destination, 0755, true);
    }
    
    // Save file
    if (!file_put_contents($filepath, $imageData)) {
        return ['success' => false, 'message' => 'Failed to save image file.', 'filename' => null, 'filepath' => null];
    }
    
    // Return relative path for database storage
    $relativePath = $destination . $filename;
    
    return ['success' => true, 'message' => 'Image saved successfully.', 'filename' => $filename, 'filepath' => $relativePath];
}

