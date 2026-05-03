<?php
/**
 * Profile API Endpoint
 * Handles profile data retrieval and updates for logged-in clients
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Require client authentication
if (!isLoggedIn('client')) {
    jsonResponse(false, 'Unauthorized. Please log in.');
}

// Route based on method and action
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'patients') {
            getPatientList();
        } else if ($action === 'check_completeness') {
            checkProfileCompleteness();
        } else {
            getProfile();
        }
        break;
    case 'PUT':
        updateProfile();
        break;
    case 'POST':
        if ($action === 'dependent') {
            createDependent();
        } else if ($action === 'upload_photo') {
            uploadPhoto();
        } else {
            jsonResponse(false, 'Invalid action.');
        }
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Resolve client `patient_id` to DB primary key `patients.id`.
 * Accepts numeric id OR business display id (e.g. P-2026-00022 from list_dependents.php).
 */
function profileResolveOwnedPatientId(PDO $pdo, string $ownerUserId, $raw): ?int {
    $raw = trim((string) ($raw ?? ''));
    if ($raw === '') {
        return null;
    }
    if (ctype_digit($raw)) {
        $id = (int) $raw;
        if ($id <= 0) {
            return null;
        }
        $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND owner_user_id = ? LIMIT 1");
        $stmt->execute([$id, $ownerUserId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE patient_id = ? AND owner_user_id = ? LIMIT 1");
    $stmt->execute([$raw, $ownerUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int) $row['id'] : null;
}

/**
 * Get profile data
 * Returns data for a specific patient profile (by patient_id) or default self profile
 */
function getProfile() {
    global $pdo;
    
    $userId = getCurrentUserId(); // This is users.id (int)
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    try {
        // Get user's user_id (varchar) from users table
        $stmt = $pdo->prepare("SELECT user_id, username, email FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        
        $userUserId = $user['user_id'];
        
        // Query param may be numeric id OR display patient_id (e.g. P-2026-00022 from the mobile app).
        $rawPatientKey = isset($_GET['patient_id']) ? trim((string) $_GET['patient_id']) : '';
        
        if ($rawPatientKey !== '') {
            $resolvedId = profileResolveOwnedPatientId($pdo, $userUserId, $rawPatientKey);
            if ($resolvedId === null) {
                jsonResponse(false, 'Patient profile not found or access denied.');
            }
            $stmt = $pdo->prepare("
                SELECT * FROM patients 
                WHERE id = ? AND owner_user_id = ?
            ");
            $stmt->execute([$resolvedId, $userUserId]);
            $patient = $stmt->fetch();
            if (!$patient) {
                jsonResponse(false, 'Patient profile not found or access denied.');
            }
        } else {
            // Get self patient profile (linked_user_id = user_id)
            $stmt = $pdo->prepare("
                SELECT * FROM patients 
                WHERE linked_user_id = ? AND owner_user_id = ?
            ");
            $stmt->execute([$userUserId, $userUserId]);
            $patient = $stmt->fetch();
        }
        
        if ($patient) {
            // Email lives on the patient row (tbl_patients / patients); never substitute tbl_users.
            $isSelf = ($patient['linked_user_id'] === $userUserId);
            $patientEmail = $patient['email'] ?? '';
            if ($patientEmail === null) {
                $patientEmail = '';
            }
            $payload = [
                'patient_id' => $patient['id'],
                'patient_display_id' => $patient['patient_id'],
                'owner_user_id' => $patient['owner_user_id'],
                'linked_user_id' => $patient['linked_user_id'],
                'first_name' => $patient['first_name'] ?? '',
                'last_name' => $patient['last_name'] ?? '',
                'email' => $patientEmail,
                'contact_number' => $patient['contact_number'] ?? '',
                'date_of_birth' => $patient['date_of_birth'] ?? '',
                'gender' => $patient['gender'] ?? '',
                'blood_type' => $patient['blood_type'] ?? '',
                'house_street' => $patient['house_street'] ?? '',
                'barangay' => $patient['barangay'] ?? '',
                'city_municipality' => $patient['city_municipality'] ?? '',
                'province' => $patient['province'] ?? '',
                'profile_image' => $patient['profile_image'] ?? '',
                'is_self' => $isSelf,
                'source' => 'patients'
            ];
            // Login username only applies to the account-linked patient row.
            $payload['username'] = $isSelf ? $user['username'] : '';
            jsonResponse(true, 'Profile retrieved successfully.', $payload);
        } else {
            // No patient record yet - return minimal data
            jsonResponse(true, 'Profile retrieved successfully.', [
                'user_id' => $userUserId,
                'first_name' => '',
                'last_name' => '',
                'email' => $user['email'] ?? '',
                'contact_number' => '',
                'date_of_birth' => '',
                'gender' => '',
                'blood_type' => '',
                'house_street' => '',
                'barangay' => '',
                'city_municipality' => '',
                'province' => '',
                'profile_image' => '',
                'username' => $user['username'],
                'is_self' => true,
                'source' => 'users'
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Get Profile Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve profile.');
    }
}

/**
 * Update profile
 * Handles both personal details (patients table) and password (users table)
 */
function updateProfile() {
    global $pdo;
    
    $userId = getCurrentUserId(); // This is users.id (int)
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Determine update type
    if (isset($input['update_type']) && $input['update_type'] === 'password') {
        updatePassword($userId, $input);
    } else {
        updatePersonalDetails($userId, $input);
    }
}

/**
 * Update personal details in patients table
 */
function updatePersonalDetails($userId, $input) {
    global $pdo;
    
    try {
        // Get user's user_id and user_type from users table
        $stmt = $pdo->prepare("SELECT user_id, role FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        
        $userUserId = $user['user_id']; // This is the user_id (varchar)
        $userType = function_exists('_authRoleToUserType') ? _authRoleToUserType($user['role'] ?? 'client') : ($user['role'] ?? 'client');
        
        // CRITICAL: Only clients can create/update patient records
        // Admin, staff, doctor, manager must use staffs table, not patients table
        if (strtolower($userType) !== 'client') {
            jsonResponse(false, 'Only client accounts can manage patient profiles. Staff accounts use a different profile system.');
        }
        
        // patient_id in JSON may be numeric id OR display id (P-2026-…)
        $rawPatientRef = isset($input['patient_id']) ? trim((string) $input['patient_id']) : '';
        $patientId = null;
        if ($rawPatientRef !== '') {
            $patientId = profileResolveOwnedPatientId($pdo, $userUserId, $rawPatientRef);
            if ($patientId === null) {
                jsonResponse(false, 'Patient profile not found or access denied.');
            }
        }
        
        // Extract and sanitize patient data (no first_name/last_name - they're in users table)
        // Note: date_of_birth cannot be updated after registration
        $patientData = [
            'gender' => sanitize($input['gender'] ?? ''),
            'blood_type' => sanitize($input['blood_type'] ?? ''),
            'house_street' => sanitize($input['house_street'] ?? ''),
            'barangay' => sanitize($input['barangay'] ?? ''),
            'city_municipality' => sanitize($input['city_municipality'] ?? $input['city'] ?? ''),
            'province' => sanitize($input['province'] ?? '')
        ];
        
        // Extract account data (only for self profile)
        $accountData = [
            'email' => sanitize($input['email'] ?? ''),
            'username' => sanitize($input['username'] ?? ''),
            'contact_number' => sanitize($input['contact_number'] ?? ''),
            'first_name' => sanitize($input['first_name'] ?? ''),
            'last_name' => sanitize($input['last_name'] ?? '')
        ];
        
        // Validation - first_name and last_name required for self profile
        $isSelfProfile = false;
        if ($patientId) {
            $stmt = $pdo->prepare("SELECT linked_user_id FROM patients WHERE id = ?");
            $stmt->execute([$patientId]);
            $patientCheck = $stmt->fetch();
            $isSelfProfile = ($patientCheck && $patientCheck['linked_user_id'] === $userUserId);
        } else {
            $isSelfProfile = true; // Creating/updating self profile
        }
        
        if ($isSelfProfile) {
            if (empty($accountData['first_name'])) {
                jsonResponse(false, 'First name is required.');
            }
            
            if (empty($accountData['last_name'])) {
                jsonResponse(false, 'Last name is required.');
            }
        }
        
        // Patient-specific validations (optional for dependents, required for self)
        if ($isSelfProfile) {
            if (empty($patientData['gender'])) {
                jsonResponse(false, 'Gender is required.');
            }
            
            if (empty($patientData['house_street'])) {
                jsonResponse(false, 'Street address is required.');
            }
            
            if (empty($patientData['barangay'])) {
                jsonResponse(false, 'Barangay is required.');
            }
            
            if (empty($patientData['city_municipality'])) {
                jsonResponse(false, 'City/Municipality is required.');
            }
            
            if (empty($patientData['province'])) {
                jsonResponse(false, 'Province is required.');
            }
        }
        
        // If patient_id is provided, update that specific patient profile
        if ($patientId) {
            // Verify ownership
            $stmt = $pdo->prepare("SELECT owner_user_id, linked_user_id FROM patients WHERE id = ?");
            $stmt->execute([$patientId]);
            $existingPatient = $stmt->fetch();
            
            if (!$existingPatient || $existingPatient['owner_user_id'] !== $userUserId) {
                jsonResponse(false, 'Access denied. You can only update patient profiles you own.');
            }
            
            $isSelf = ($existingPatient['linked_user_id'] === $userUserId);
            
            // Update patient record (first_name, last_name, contact_number are now in patients table)
            // Note: date_of_birth cannot be updated after registration
            $updateFields = [];
            $updateParams = [];
            
            // Update patient-specific fields (always editable for all patients)
            if (isset($patientData['gender'])) {
                $updateFields[] = "gender = ?";
                $updateParams[] = $patientData['gender'] ?: null;
            }
            if (isset($patientData['blood_type'])) {
                $updateFields[] = "blood_type = ?";
                $updateParams[] = $patientData['blood_type'] ?: null;
            }
            if (isset($patientData['house_street'])) {
                $updateFields[] = "house_street = ?";
                $updateParams[] = $patientData['house_street'] ?: null;
            }
            if (isset($patientData['barangay'])) {
                $updateFields[] = "barangay = ?";
                $updateParams[] = $patientData['barangay'] ?: null;
            }
            if (isset($patientData['city_municipality'])) {
                $updateFields[] = "city_municipality = ?";
                $updateParams[] = $patientData['city_municipality'] ?: null;
            }
            if (isset($patientData['province'])) {
                $updateFields[] = "province = ?";
                $updateParams[] = $patientData['province'] ?: null;
            }
            
            // first_name, last_name, and contact_number can only be updated for self profile
            // For dependents, contact_number is locked (belongs to owner)
            if ($isSelf) {
                if (isset($accountData['first_name'])) {
                    $updateFields[] = "first_name = ?";
                    $updateParams[] = $accountData['first_name'];
                }
                if (isset($accountData['last_name'])) {
                    $updateFields[] = "last_name = ?";
                    $updateParams[] = $accountData['last_name'];
                }
                if (isset($accountData['contact_number'])) {
                    $updateFields[] = "contact_number = ?";
                    $updateParams[] = $accountData['contact_number'] ?: null;
                }
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = NOW()";
                $updateParams[] = $patientId;
                
                $stmt = $pdo->prepare("
                    UPDATE patients SET " . implode(', ', $updateFields) . " WHERE id = ?
                ");
                $stmt->execute($updateParams);
            }
            
            // If this is the self profile, also update account info in users table (email and username)
            if ($isSelf) {
                if (empty($accountData['email'])) {
                    jsonResponse(false, 'Email is required.');
                } elseif (!filter_var($accountData['email'], FILTER_VALIDATE_EMAIL)) {
                    jsonResponse(false, 'Invalid email format.');
                }
                
                // Check if email already exists for another user
                $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE email = ? AND user_id != ?");
                $stmt->execute([$accountData['email'], $userId]);
                if ($stmt->fetch()) {
                    jsonResponse(false, 'Email already registered to another account.');
                }
                
                // Check if username is provided and validate it
                $updateFields = [];
                $updateParams = [];
                
                if (!empty($accountData['username'])) {
                    // Check if username already exists for another user
                    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ? AND user_id != ?");
                    $stmt->execute([$accountData['username'], $userId]);
                    if ($stmt->fetch()) {
                        jsonResponse(false, 'Username already taken.');
                    }
                    $updateFields[] = "username = ?";
                    $updateParams[] = $accountData['username'];
                }
                
                $updateFields[] = "email = ?";
                $updateParams[] = $accountData['email'];
                $updateParams[] = $userId;
                
                $stmt = $pdo->prepare("
                    UPDATE tbl_users SET
                        " . implode(', ', $updateFields) . ",
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute($updateParams);
            }
            
            // Update session user_name if first_name or last_name was updated for self profile
            if ($isSelf && (isset($accountData['first_name']) || isset($accountData['last_name']))) {
                // Get updated name from database
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE id = ?");
                $stmt->execute([$patientId]);
                $patient = $stmt->fetch();
                if ($patient) {
                    $_SESSION['user_name'] = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
                }
            }
            
            jsonResponse(true, 'Profile updated successfully.');
        } else {
            // No patient_id - get or create self patient profile
            $stmt = $pdo->prepare("SELECT id FROM patients WHERE linked_user_id = ? AND owner_user_id = ?");
            $stmt->execute([$userUserId, $userUserId]);
            $existingPatient = $stmt->fetch();
            
            if ($existingPatient) {
                // UPDATE existing self patient record (first_name, last_name, contact_number are now in patients table)
                // Note: date_of_birth cannot be updated after registration
                $updateFields = [];
                $updateParams = [];
                
                // Update patient-specific fields
                if (isset($patientData['gender'])) {
                    $updateFields[] = "gender = ?";
                    $updateParams[] = $patientData['gender'] ?: null;
                }
                if (isset($patientData['blood_type'])) {
                    $updateFields[] = "blood_type = ?";
                    $updateParams[] = $patientData['blood_type'] ?: null;
                }
                if (isset($patientData['house_street'])) {
                    $updateFields[] = "house_street = ?";
                    $updateParams[] = $patientData['house_street'] ?: null;
                }
                if (isset($patientData['barangay'])) {
                    $updateFields[] = "barangay = ?";
                    $updateParams[] = $patientData['barangay'] ?: null;
                }
                if (isset($patientData['city_municipality'])) {
                    $updateFields[] = "city_municipality = ?";
                    $updateParams[] = $patientData['city_municipality'] ?: null;
                }
                if (isset($patientData['province'])) {
                    $updateFields[] = "province = ?";
                    $updateParams[] = $patientData['province'] ?: null;
                }
                
                // Update first_name, last_name, contact_number in patients table
                if (isset($accountData['first_name'])) {
                    $updateFields[] = "first_name = ?";
                    $updateParams[] = $accountData['first_name'];
                }
                if (isset($accountData['last_name'])) {
                    $updateFields[] = "last_name = ?";
                    $updateParams[] = $accountData['last_name'];
                }
                if (isset($accountData['contact_number'])) {
                    $updateFields[] = "contact_number = ?";
                    $updateParams[] = $accountData['contact_number'];
                }
                
                if (!empty($updateFields)) {
                    $updateFields[] = "updated_at = NOW()";
                    $updateParams[] = $existingPatient['id'];
                    
                    $stmt = $pdo->prepare("
                        UPDATE patients SET " . implode(', ', $updateFields) . " WHERE id = ?
                    ");
                    $stmt->execute($updateParams);
                }
                
                // Also update account info in users table (email and username)
                if (empty($accountData['email'])) {
                    jsonResponse(false, 'Email is required.');
                } elseif (!filter_var($accountData['email'], FILTER_VALIDATE_EMAIL)) {
                    jsonResponse(false, 'Invalid email format.');
                }
                
                // Check if email already exists for another user
                $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE email = ? AND user_id != ?");
                $stmt->execute([$accountData['email'], $userId]);
                if ($stmt->fetch()) {
                    jsonResponse(false, 'Email already registered to another account.');
                }
                
                // Check if username is provided and validate it
                $updateFields = [];
                $updateParams = [];
                
                if (!empty($accountData['username'])) {
                    // Check if username already exists for another user
                    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ? AND user_id != ?");
                    $stmt->execute([$accountData['username'], $userId]);
                    if ($stmt->fetch()) {
                        jsonResponse(false, 'Username already taken.');
                    }
                    $updateFields[] = "username = ?";
                    $updateParams[] = $accountData['username'];
                }
                
                $updateFields[] = "email = ?";
                $updateParams[] = $accountData['email'];
                $updateParams[] = $userId;
                
                $stmt = $pdo->prepare("
                    UPDATE tbl_users SET
                        " . implode(', ', $updateFields) . ",
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute($updateParams);
                
                // Update session user_name if first_name or last_name was updated
                if (isset($accountData['first_name']) || isset($accountData['last_name'])) {
                    // Get updated name from database
                    $stmt = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE id = ?");
                    $stmt->execute([$existingPatient['id']]);
                    $patient = $stmt->fetch();
                    if ($patient) {
                        $_SESSION['user_name'] = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
                    }
                }
                
                jsonResponse(true, 'Profile updated successfully.');
            } else {
                // INSERT new self patient record
                if (empty($accountData['email'])) {
                    jsonResponse(false, 'Email is required.');
                } elseif (!filter_var($accountData['email'], FILTER_VALIDATE_EMAIL)) {
                    jsonResponse(false, 'Invalid email format.');
                }
                
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM tbl_users WHERE email = ?");
                $stmt->execute([$accountData['email']]);
                if ($stmt->fetch()) {
                    jsonResponse(false, 'Email already registered.');
                }
                
                // Update users table with account info (email only)
                $stmt = $pdo->prepare("
                    UPDATE tbl_users SET
                        email = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $accountData['email'],
                    $userId
                ]);
                
                // Generate patient_id display code using thread-safe function
                $patientDisplayId = generatePatientId();
                
                $stmt = $pdo->prepare("
                    INSERT INTO patients (
                        patient_id, owner_user_id, linked_user_id, first_name, last_name, contact_number,
                        date_of_birth, gender, blood_type, house_street, barangay, city_municipality, province, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $patientDisplayId,
                    $userUserId,
                    $userUserId, // Self profile
                    $accountData['first_name'] ?? '',
                    $accountData['last_name'] ?? '',
                    $accountData['contact_number'] ?? null,
                    $patientData['date_of_birth'] ?: null,
                    $patientData['gender'] ?: null,
                    $patientData['blood_type'] ?: null,
                    $patientData['house_street'] ?: null,
                    $patientData['barangay'] ?: null,
                    $patientData['city_municipality'] ?: null,
                    $patientData['province'] ?: null
                ]);
                
                // Update session user_name if first_name or last_name was provided
                if (isset($accountData['first_name']) || isset($accountData['last_name'])) {
                    $_SESSION['user_name'] = trim(($accountData['first_name'] ?? '') . ' ' . ($accountData['last_name'] ?? ''));
                }
                
                jsonResponse(true, 'Profile saved successfully.');
            }
        }
        
    } catch (Exception $e) {
        error_log('Update Profile Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update profile.');
    }
}

/**
 * Update password in users table
 */
function updatePassword($userId, $input) {
    global $pdo;
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    // Validation
    if (empty($currentPassword)) {
        jsonResponse(false, 'Current password is required.');
    }
    
    if (empty($newPassword)) {
        jsonResponse(false, 'New password is required.');
    }
    
    if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
        jsonResponse(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
    }
    
    if ($newPassword !== $confirmPassword) {
        jsonResponse(false, 'New password and confirm password do not match.');
    }
    
    try {
        // Get current password hash
        $stmt = $pdo->prepare("SELECT password_hash FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        
        // Verify current password
        if (!password_verify($currentPassword, $user['password_hash'] ?? '')) {
            jsonResponse(false, 'Current password is incorrect.');
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE tbl_users SET password_hash = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$hashedPassword, $userId]);
        
        jsonResponse(true, 'Password updated successfully.');
        
    } catch (Exception $e) {
        error_log('Update Password Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update password.');
    }
}

/**
 * Get list of patient profiles owned by logged-in user
 */
function getPatientList() {
    global $pdo;
    
    $userId = getCurrentUserId();
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    try {
        // Get user's user_id (varchar)
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        
        $userUserId = $user['user_id'];
        
        // Get all patient profiles owned by this user (first_name, last_name are now in patients table)
        $stmt = $pdo->prepare("
            SELECT 
                p.id,
                p.patient_id,
                p.owner_user_id,
                p.linked_user_id,
                p.first_name,
                p.last_name,
                p.contact_number,
                p.date_of_birth,
                p.gender,
                CASE 
                    WHEN p.linked_user_id = ? THEN 1 
                    ELSE 0 
                END as is_self
            FROM patients p
            WHERE p.owner_user_id = ?
            ORDER BY is_self DESC, p.first_name ASC, p.last_name ASC
        ");
        $stmt->execute([$userUserId, $userUserId]);
        $patients = $stmt->fetchAll();
        
        jsonResponse(true, 'Patient list retrieved successfully.', [
            'patients' => $patients
        ]);
        
    } catch (Exception $e) {
        error_log('Get Patient List Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve patient list.');
    }
}

/**
 * Create a new dependent patient profile
 */
function createDependent() {
    global $pdo;
    
    $userId = getCurrentUserId();
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Start transaction for data consistency
    $pdo->beginTransaction();
    
    try {
        // Get user's user_id (varchar)
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $pdo->rollBack();
            jsonResponse(false, 'User not found.');
        }
        
        $userUserId = $user['user_id'];
        
        // Get owner's contact_number from their patient record (self profile)
        $stmt = $pdo->prepare("
            SELECT contact_number 
            FROM patients 
            WHERE owner_user_id = ? AND linked_user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$userUserId, $userUserId]);
        $ownerPatient = $stmt->fetch();
        $ownerContactNumber = $ownerPatient['contact_number'] ?? null;
        
        // Extract and sanitize data
        $data = [
            'first_name' => sanitize($input['first_name'] ?? ''),
            'last_name' => sanitize($input['last_name'] ?? ''),
            'date_of_birth' => sanitize($input['date_of_birth'] ?? ''),
            'gender' => sanitize($input['gender'] ?? ''),
            'blood_type' => sanitize($input['blood_type'] ?? ''),
            'house_street' => sanitize($input['house_street'] ?? ''),
            'barangay' => sanitize($input['barangay'] ?? ''),
            'city_municipality' => sanitize($input['city_municipality'] ?? ''),
            'province' => sanitize($input['province'] ?? ''),
            'profile_image' => sanitize($input['profile_image'] ?? '')
        ];
        
        // Validation
        if (empty($data['first_name'])) {
            $pdo->rollBack();
            jsonResponse(false, 'First name is required.');
        }
        
        if (empty($data['last_name'])) {
            $pdo->rollBack();
            jsonResponse(false, 'Last name is required.');
        }
        
        if (empty($data['date_of_birth'])) {
            $pdo->rollBack();
            jsonResponse(false, 'Date of birth is required.');
        }
        
        if (empty($data['gender'])) {
            $pdo->rollBack();
            jsonResponse(false, 'Gender is required.');
        }
        
        if (empty($data['house_street'])) {
            $pdo->rollBack();
            jsonResponse(false, 'Street address is required.');
        }
        
        if (empty($data['barangay'])) {
            $pdo->rollBack();
            jsonResponse(false, 'Barangay is required.');
        }
        
        if (empty($data['city_municipality'])) {
            $pdo->rollBack();
            jsonResponse(false, 'City/Municipality is required.');
        }
        
        if (empty($data['province'])) {
            $pdo->rollBack();
            jsonResponse(false, 'Province is required.');
        }
        
        // Generate patient_id display code using thread-safe function
        $patientDisplayId = generatePatientId();
        
        // Insert new dependent patient profile (no user account needed - just patient record)
        // linked_user_id is NULL for dependents since they don't have user accounts
        // contact_number is inherited from the owner (parent/guardian)
        // Match the exact column order from api/patients.php
        $stmt = $pdo->prepare("
            INSERT INTO patients (
                patient_id, owner_user_id, linked_user_id, first_name, last_name, contact_number,
                date_of_birth, gender, blood_type, house_street, barangay, city_municipality, province, profile_image, created_at
            ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $patientDisplayId,
            $userUserId, // owner_user_id = logged-in user (parent/guardian)
            $data['first_name'], // first_name (stored directly in patients table)
            $data['last_name'], // last_name (stored directly in patients table)
            $ownerContactNumber, // contact_number from owner (parent/guardian)
            $data['date_of_birth'],
            $data['gender'],
            $data['blood_type'] ?: null,
            $data['house_street'],
            $data['barangay'],
            $data['city_municipality'],
            $data['province'],
            $data['profile_image'] ?: null
        ]);
        
        $patientId = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        jsonResponse(true, 'Dependent profile created successfully.', [
            'patient_id' => $patientId,
            'patient_display_id' => $patientDisplayId
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Create Dependent PDO Error: ' . $e->getMessage());
        error_log('Create Dependent Error Code: ' . $e->getCode());
        error_log('Create Dependent Error Trace: ' . $e->getTraceAsString());
        
        // Check for specific error types
        $errorCode = $e->getCode();
        if ($errorCode == 23000) { // Integrity constraint violation
            $errorMessage = 'A record with this information already exists. Please check and try again.';
        } else {
            $errorMessage = 'Failed to create dependent profile. Error: ' . $e->getMessage();
        }
        
        jsonResponse(false, $errorMessage);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Create Dependent Error: ' . $e->getMessage());
        error_log('Create Dependent Error Trace: ' . $e->getTraceAsString());
        jsonResponse(false, 'Failed to create dependent profile. Error: ' . $e->getMessage());
    }
}

/**
 * Upload profile photo
 * Handles photo upload for patient profiles
 */
function uploadPhoto() {
    global $pdo;
    
    $userId = getCurrentUserId(); // This is users.id (int)
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['photo'])) {
        jsonResponse(false, 'No photo data provided.');
    }
    
    try {
        // Get user's user_id and user_type from users table
        $stmt = $pdo->prepare("SELECT user_id, role FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        
        $userUserId = $user['user_id']; // This is the user_id (varchar)
        $userType = function_exists('_authRoleToUserType') ? _authRoleToUserType($user['role'] ?? 'client') : ($user['role'] ?? 'client');
        
        // CRITICAL: Only clients can upload patient profile photos
        if (strtolower($userType) !== 'client') {
            jsonResponse(false, 'Only client accounts can upload patient profile photos.');
        }
        
        $rawPatientRef = isset($input['patient_id']) ? trim((string) $input['patient_id']) : '';
        $patientId = null;
        if ($rawPatientRef !== '') {
            $patientId = profileResolveOwnedPatientId($pdo, $userUserId, $rawPatientRef);
            if ($patientId === null) {
                jsonResponse(false, 'Patient profile not found or access denied.');
            }
        }
        
        // Save the photo using saveBase64Image function
        require_once __DIR__ . '/../includes/functions.php';
        $photoResult = saveBase64Image($input['photo'], 'uploads/patients/', 'patient_');
        
        if (!$photoResult['success']) {
            jsonResponse(false, !empty($photoResult['message']) ? $photoResult['message'] : 'Failed to upload photo.');
        }
        
        $profileImagePath = $photoResult['filepath'];
        
        // Determine which patient profile to update
        if ($patientId) {
            // Update specific patient profile (must be owned by logged-in user)
            $stmt = $pdo->prepare("SELECT owner_user_id FROM patients WHERE id = ?");
            $stmt->execute([$patientId]);
            $existingPatient = $stmt->fetch();
            
            if (!$existingPatient || $existingPatient['owner_user_id'] !== $userUserId) {
                jsonResponse(false, 'Access denied. You can only update patient profiles you own.');
            }
            
            // Delete old photo if it exists
            $stmt = $pdo->prepare("SELECT profile_image FROM patients WHERE id = ?");
            $stmt->execute([$patientId]);
            $oldPatient = $stmt->fetch();
            if ($oldPatient && !empty($oldPatient['profile_image'])) {
                $oldImagePath = ROOT_PATH . $oldPatient['profile_image'];
                if (file_exists($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            }
            
            // Update patient record with new photo
            $stmt = $pdo->prepare("
                UPDATE patients SET
                    profile_image = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$profileImagePath, $patientId]);
        } else {
            // Update or create self patient profile
            $stmt = $pdo->prepare("SELECT id, profile_image FROM patients WHERE linked_user_id = ? AND owner_user_id = ?");
            $stmt->execute([$userUserId, $userUserId]);
            $existingPatient = $stmt->fetch();
            
            if ($existingPatient) {
                // Delete old photo if it exists
                if (!empty($existingPatient['profile_image'])) {
                    $oldImagePath = ROOT_PATH . $existingPatient['profile_image'];
                    if (file_exists($oldImagePath)) {
                        @unlink($oldImagePath);
                    }
                }
                
                // Update existing self patient record
                $stmt = $pdo->prepare("
                    UPDATE patients SET
                        profile_image = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                $stmt->execute([$profileImagePath, $existingPatient['id']]);
            } else {
                // Create new self patient record with photo
                // Note: This should rarely happen as profile should exist, but handle it anyway
                // Generate patient_id using thread-safe function
                $patientDisplayId = generatePatientId();
                
                $stmt = $pdo->prepare("
                    INSERT INTO patients (
                        patient_id, owner_user_id, linked_user_id, profile_image, created_at
                    ) VALUES (?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $patientDisplayId,
                    $userUserId,
                    $userUserId,
                    $profileImagePath
                ]);
            }
        }
        
        jsonResponse(true, 'Photo uploaded successfully.', [
            'profile_image' => $profileImagePath
        ]);
        
    } catch (Exception $e) {
        error_log('Upload Photo Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to upload photo.');
    }
}

/**
 * Check if user's profile is complete
 * Returns list of missing required fields
 */
function checkProfileCompleteness() {
    global $pdo;
    
    $userId = getCurrentUserId(); // This is users.id (int)
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    try {
        // Get user's user_id and user_type from users table
        $stmt = $pdo->prepare("SELECT user_id, email, role FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        
        $userUserId = $user['user_id'];
        $userType = function_exists('_authRoleToUserType') ? _authRoleToUserType($user['role'] ?? 'client') : ($user['role'] ?? 'client');
        
        // CRITICAL: Only clients have patient profiles
        // For non-clients, check staffs table instead
        if (strtolower($userType) !== 'client') {
            // For non-clients, check staffs table
            $stmt = $pdo->prepare("SELECT * FROM staffs WHERE user_id = ?");
            $stmt->execute([$userUserId]);
            $staff = $stmt->fetch();
            
            $missingFields = [];
            $isComplete = true;
            
            if (empty($user['email'])) {
                $missingFields[] = 'Email Address';
                $isComplete = false;
            }
            
            if (!$staff) {
                $missingFields[] = 'Staff Profile';
                $isComplete = false;
            } else {
                if (empty($staff['first_name'])) $missingFields[] = 'First Name';
                if (empty($staff['last_name'])) $missingFields[] = 'Last Name';
                if (empty($staff['contact_number'])) $missingFields[] = 'Contact Number';
            }
            
            jsonResponse(true, $isComplete ? 'Profile is complete.' : 'Profile is incomplete.', [
                'is_complete' => $isComplete,
                'missing_fields' => $missingFields
            ]);
            return;
        }
        
        // Get self patient profile (first_name, last_name, contact_number are now in patients table)
        $stmt = $pdo->prepare("
            SELECT * FROM patients 
            WHERE linked_user_id = ? AND owner_user_id = ?
        ");
        $stmt->execute([$userUserId, $userUserId]);
        $patient = $stmt->fetch();
        
        $missingFields = [];
        $isComplete = true;
        
        // Check required user account fields (email only - first_name, last_name, contact_number are in patients)
        if (empty($user['email'])) {
            $missingFields[] = 'Email Address';
            $isComplete = false;
        }
        
        // Check if patient record exists
        if (!$patient) {
            // Patient record doesn't exist - profile is incomplete
            $missingFields[] = 'First Name';
            $missingFields[] = 'Last Name';
            $missingFields[] = 'Contact Number';
            $missingFields[] = 'Gender';
            $missingFields[] = 'Street Address';
            $missingFields[] = 'Barangay';
            $missingFields[] = 'City/Municipality';
            $missingFields[] = 'Province';
            $isComplete = false;
        } else {
            // Check required patient fields (first_name, last_name, contact_number are now here)
            if (empty($patient['first_name'])) {
                $missingFields[] = 'First Name';
                $isComplete = false;
            }
            
            if (empty($patient['last_name'])) {
                $missingFields[] = 'Last Name';
                $isComplete = false;
            }
            
            if (empty($patient['contact_number'])) {
                $missingFields[] = 'Contact Number';
                $isComplete = false;
            }
            
            if (empty($patient['gender'])) {
                $missingFields[] = 'Gender';
                $isComplete = false;
            }
            
            if (empty($patient['house_street'])) {
                $missingFields[] = 'Street Address';
                $isComplete = false;
            }
            
            if (empty($patient['barangay'])) {
                $missingFields[] = 'Barangay';
                $isComplete = false;
            }
            
            if (empty($patient['city_municipality'])) {
                $missingFields[] = 'City/Municipality';
                $isComplete = false;
            }
            
            if (empty($patient['province'])) {
                $missingFields[] = 'Province';
                $isComplete = false;
            }
        }
        
        jsonResponse(true, $isComplete ? 'Profile is complete.' : 'Profile is incomplete.', [
            'is_complete' => $isComplete,
            'missing_fields' => $missingFields,
            'message' => $isComplete 
                ? 'Your profile is complete. You can book appointments.' 
                : 'Please complete your profile before booking an appointment. Missing: ' . implode(', ', $missingFields)
        ]);
        
    } catch (Exception $e) {
        error_log('Check Profile Completeness Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to check profile completeness.');
    }
}
