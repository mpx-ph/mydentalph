<?php
/**
 * Create User API Endpoint (for Admin creation)
 * Similar to register.php but creates admin users
 */

// Set error handler to return JSON
set_error_handler(function($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'PHP Error: ' . $message,
            'data' => []
        ]);
        exit;
    }
});

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

// Handle both FormData (multipart/form-data) and JSON
$input = [];
if (!empty($_POST)) {
    // FormData was sent
    $input = $_POST;
} else {
    // Try to read JSON from input
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $input = json_decode($rawInput, true) ?? [];
    }
}

$data = [
    'first_name' => sanitize($input['first_name'] ?? ''),
    'last_name' => sanitize($input['last_name'] ?? ''),
    'email' => sanitize($input['email'] ?? ''),
    'username' => sanitize($input['username'] ?? ''),
    'password' => $input['password'] ?? '',
    'mobile' => sanitize($input['mobile'] ?? ''),
    'user_type' => sanitize($input['user_type'] ?? 'admin') // Default to admin
];

// Get database connection (with proper error handling)
try {
    $pdo = getDBConnection();
} catch (PDOException $e) {
    error_log('Create User - Database Connection Error: ' . $e->getMessage());
    jsonResponse(false, 'Database connection failed: ' . $e->getMessage());
} catch (Exception $e) {
    error_log('Create User - Connection Error: ' . $e->getMessage());
    jsonResponse(false, 'Connection error: ' . $e->getMessage());
}

// Validate required fields (mobile is optional)
$required = ['first_name', 'last_name', 'email', 'username', 'password'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        jsonResponse(false, ucfirst(str_replace('_', ' ', $field)) . ' is required.');
    }
}

// Validate email format
if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Invalid email format.');
}

// Validate user_type - allow admin, client, manager, doctor, staff
$allowedUserTypes = ['admin', 'client', 'manager', 'doctor', 'staff'];
// Normalize 'doctors' to 'doctor' for consistency
if (strtolower($data['user_type']) === 'doctors') {
    $data['user_type'] = 'doctor';
}
if (!in_array(strtolower($data['user_type']), $allowedUserTypes)) {
    $data['user_type'] = 'admin'; // Default to admin if invalid
}

// Check if email exists
$stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE email = ?");
$stmt->execute([$data['email']]);
if ($stmt->fetch()) {
    jsonResponse(false, 'Email already registered.');
}

// Check if username exists
$stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ?");
$stmt->execute([$data['username']]);
if ($stmt->fetch()) {
    jsonResponse(false, 'Username already taken.');
}

// Validate password strength
if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
    jsonResponse(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
}

// Hash password
$hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

// Generate user_id
$userId = generateUserId($data['user_type']);

// Insert user (without first_name, last_name, contact_number - these go in patients table)
try {
    // Check if email verification is required for this user type
    $skipVerificationTypes = ['manager', 'doctor', 'staff', 'admin'];
    $requiresVerification = !in_array(strtolower($data['user_type']), $skipVerificationTypes);
    
    // Schema: tbl_users has user_id, password_hash, role, full_name (no email_verified_at)
    $role = (strtolower($data['user_type']) === 'doctor') ? 'dentist' : (($data['user_type'] === 'admin') ? 'manager' : $data['user_type']);
    $fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: $data['username'];
    $tenantId = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : (function_exists('requireClinicTenantId') ? requireClinicTenantId() : null);
    if (!$tenantId) {
        jsonResponse(false, 'Tenant context required.');
    }
    $stmt = $pdo->prepare("
        INSERT INTO tbl_users (tenant_id, user_id, email, username, full_name, password_hash, role, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ");
    
    $stmt->execute([
        $tenantId,
        $userId,
        $data['email'],
        $data['username'],
        $fullName,
        $hashedPassword,
        $role,
    ]);
    
    $insertId = $userId;
    
    // Create profile based on user_type
    // ONLY clients get patient records
    // Admin, staff, doctor, manager get staff records
    try {
        if (strtolower($data['user_type']) === 'client') {
            // Create patient profile for clients only
            // Generate patient_id using thread-safe function
            require_once __DIR__ . '/../includes/functions.php';
            $patientDisplayId = generatePatientId();
            
            // Create patient profile (owner_user_id = linked_user_id = user_id for self profile)
            $stmt = $pdo->prepare("
                INSERT INTO patients (
                    patient_id, owner_user_id, linked_user_id, first_name, last_name, contact_number, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $patientDisplayId,
                $userId, // owner_user_id
                $userId, // linked_user_id (self profile)
                $data['first_name'],
                $data['last_name'],
                !empty($data['mobile']) ? $data['mobile'] : (!empty($data['contact_number']) ? $data['contact_number'] : null)
            ]);
        } else {
            // Create staff profile for admin, staff, doctor, manager
            $year = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM staffs WHERE staff_id LIKE ?");
            $stmt->execute(["S-{$year}-%"]);
            $result = $stmt->fetch();
            $sequence = str_pad((int)$result['count'] + 1, 5, '0', STR_PAD_LEFT);
            $staffDisplayId = "S-{$year}-{$sequence}";
            
            // Create staff profile
            $stmt = $pdo->prepare("
                INSERT INTO staffs (
                    staff_id, user_id, first_name, last_name, contact_number, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $staffDisplayId,
                $userId,
                $data['first_name'],
                $data['last_name'],
                !empty($data['mobile']) ? $data['mobile'] : (!empty($data['contact_number']) ? $data['contact_number'] : null)
            ]);
        }
    } catch (Exception $e) {
        // Log error but don't fail user creation if profile creation fails
        error_log('Auto-create profile error: ' . $e->getMessage());
    }
    
    jsonResponse(true, 'User created successfully!', ['user_id' => $userId]);
    
} catch (PDOException $e) {
    error_log('Create User PDO Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Show the actual error message for debugging
    $errorMsg = 'Database error: ' . $e->getMessage();
    
    // Provide more helpful messages for common errors
    if (strpos($e->getMessage(), 'SQLSTATE[HY000] [1045]') !== false) {
        $errorMsg = 'Database authentication failed. Please check your database credentials.';
    } elseif (strpos($e->getMessage(), 'SQLSTATE[HY000] [1049]') !== false) {
        $errorMsg = 'Database not found. Please check your database name.';
    } elseif (strpos($e->getMessage(), 'SQLSTATE[HY000] [2002]') !== false) {
        $errorMsg = 'Cannot connect to database server. Please check your database host.';
    }
    
    jsonResponse(false, $errorMsg);
} catch (Exception $e) {
    error_log('Create User Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    jsonResponse(false, 'Error: ' . $e->getMessage());
}
