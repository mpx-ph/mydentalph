<?php
/**
 * Admin Patient Registration API Endpoint
 * Creates user account and complete patient profile in one transaction
 * Follows same registration flow as register.php but saves complete patient details
 */

// Suppress error display for API (return JSON instead)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any errors
ob_start();

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    // Clear any output that might have been generated
    ob_clean();
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method.');
    }
    
    // Require admin authentication
    requireAdmin();
    
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
    
    // Extract all data including complete patient details
    $data = [
        'first_name' => sanitize($input['first_name'] ?? ''),
        'middle_name' => sanitize($input['middle_name'] ?? ''),
        'last_name' => sanitize($input['last_name'] ?? ''),
        'email' => sanitize($input['email'] ?? ''),
        'username' => sanitize($input['username'] ?? ''),
        'password' => $input['password'] ?? '',
        'confirm_password' => $input['confirm_password'] ?? '',
        'mobile' => sanitize($input['mobile'] ?? $input['phone_number'] ?? ''),
        'date_of_birth' => sanitize($input['date_of_birth'] ?? ''),
        'gender' => sanitize($input['gender'] ?? ''),
        'blood_type' => sanitize($input['blood_type'] ?? ''),
        'province' => sanitize($input['province'] ?? ''),
        'city_municipality' => sanitize($input['city_municipality'] ?? $input['city'] ?? ''),
        'barangay' => sanitize($input['barangay'] ?? ''),
        'house_street' => sanitize($input['house_street'] ?? $input['street'] ?? '')
    ];
    
    $result = registerPatientWithFullDetails($data);
    
    if ($result['success']) {
        jsonResponse(true, $result['message'], [
            'user_id' => $result['user_id'],
            'patient_id' => $result['patient_id'] ?? null,
            'email' => $result['email'] ?? $data['email']
        ]);
    } else {
        jsonResponse(false, $result['message']);
    }
    
} catch (Throwable $e) {
    // Clear any output
    ob_clean();
    
    // Log the error
    error_log('Admin Patient Registration API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return JSON error
    header('Content-Type: application/json');
    jsonResponse(false, 'An error occurred during registration. Please try again.');
}

/**
 * Register patient with complete details (admin-created)
 * Similar to registerUser but saves complete patient profile
 * @param array $data
 * @return array ['success' => bool, 'message' => string, 'user_id' => string|null, 'patient_id' => string|null]
 */
function registerPatientWithFullDetails($data) {
    $pdo = getDBConnection();
    
    // Validate required fields
    $required = ['first_name', 'last_name', 'email', 'username', 'password', 'date_of_birth', 'blood_type'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return [
                'success' => false,
                'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.',
                'user_id' => null,
                'patient_id' => null
            ];
        }
    }
    
    // Validate password confirmation
    if ($data['password'] !== $data['confirm_password']) {
        return [
            'success' => false,
            'message' => 'Passwords do not match.',
            'user_id' => null,
            'patient_id' => null
        ];
    }
    
    // Validate date of birth format
    if (!empty($data['date_of_birth'])) {
        $birthDate = new DateTime($data['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        
        // Note: Admin can register patients of any age (no 18+ restriction)
        // But we still validate the date format
        if ($birthDate > $today) {
            return [
                'success' => false,
                'message' => 'Date of birth cannot be in the future.',
                'user_id' => null,
                'patient_id' => null
            ];
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email format.',
            'user_id' => null,
            'patient_id' => null
        ];
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => 'Email already registered.',
            'user_id' => null,
            'patient_id' => null
        ];
    }
    
    // Check if username exists
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => 'Username already taken.',
            'user_id' => null,
            'patient_id' => null
        ];
    }
    
    // Validate password strength
    if (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
        return [
            'success' => false,
            'message' => 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.',
            'user_id' => null,
            'patient_id' => null
        ];
    }
    
    // Hash password
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Generate user_id
    $userId = generateUserId('client');
    
    // Admin-created accounts: auto-verify and activate (no email verification needed)
    $emailVerifiedAt = date('Y-m-d H:i:s');
    $initialStatus = 'active';
    
    // Insert user and patient in a transaction to ensure atomicity
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert user (schema: tenant_id, user_id, full_name, password_hash, role; no email_verified_at)
        $tenantId = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : (function_exists('requireClinicTenantId') ? requireClinicTenantId() : null);
        if (!$tenantId) {
            throw new Exception('Tenant context required.');
        }
        $fullName = trim($data['first_name'] . (!empty($data['middle_name']) ? ' ' . $data['middle_name'] : '') . ' ' . $data['last_name']);
        $stmt = $pdo->prepare("
            INSERT INTO tbl_users (
                tenant_id, user_id, email, username, full_name, password_hash, role, status, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, 'client', ?, NOW())
        ");
        
        $stmt->execute([
            $tenantId,
            $userId,
            $data['email'],
            $data['username'],
            $fullName,
            $hashedPassword,
            $initialStatus
        ]);
        
        // Generate patient_id display code using thread-safe function
        $patientDisplayId = generatePatientId();
        
        // Create complete patient profile with all details
        // Note: middle_name is not in the schema, so we'll combine it with first_name if needed
        $firstName = trim($data['first_name'] . (!empty($data['middle_name']) ? ' ' . $data['middle_name'] : ''));
        
        $stmt = $pdo->prepare("
            INSERT INTO patients (
                patient_id, owner_user_id, linked_user_id, first_name, last_name, 
                contact_number, date_of_birth, gender, blood_type,
                house_street, barangay, city_municipality, province, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $patientDisplayId,
            $userId, // owner_user_id
            $userId, // linked_user_id (self profile)
            $firstName,
            $data['last_name'],
            !empty($data['mobile']) ? $data['mobile'] : null,
            !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
            !empty($data['gender']) ? $data['gender'] : null,
            !empty($data['blood_type']) ? $data['blood_type'] : null,
            !empty($data['house_street']) ? $data['house_street'] : null,
            !empty($data['barangay']) ? $data['barangay'] : null,
            !empty($data['city_municipality']) ? $data['city_municipality'] : null,
            !empty($data['province']) ? $data['province'] : null
        ]);

        // Every client account gets a wallet account at creation.
        ensureWalletAccount($pdo, $tenantId, $patientDisplayId);
        
        $patientId = $pdo->lastInsertId();
        
        // Commit transaction if both inserts succeed
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Patient registered successfully! Account is active and ready to use.',
            'user_id' => $userId,
            'patient_id' => $patientDisplayId,
            'email' => $data['email']
        ];
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log('Admin Patient Registration Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        
        return [
            'success' => false,
            'message' => 'Failed to register patient: ' . $e->getMessage(),
            'user_id' => null,
            'patient_id' => null
        ];
    }
}
