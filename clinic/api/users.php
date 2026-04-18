<?php
/**
 * Users API Endpoint
 * Handles CRUD operations for user accounts
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();
$tenantId = requireClinicTenantId();

// Route based on method
switch ($method) {
    case 'POST':
        createUser();
        break;
    case 'GET':
        getUsers();
        break;
    case 'PUT':
        updateUser();
        break;
    case 'DELETE':
        deleteUser();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Create new user
 */
function createUser() {
    global $pdo, $tenantId;
    
    // Require manager authentication
    if (!isLoggedIn('manager')) {
        jsonResponse(false, 'Unauthorized. Manager access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extract and sanitize data
    $data = [
        'first_name' => sanitize($input['first_name'] ?? ''),
        'last_name' => sanitize($input['last_name'] ?? ''),
        'email' => sanitize($input['email'] ?? ''),
        'username' => sanitize($input['username'] ?? ''),
        'password' => $input['password'] ?? '',
        'mobile' => sanitize($input['mobile'] ?? $input['contact_number'] ?? ''),
        'contact_number' => sanitize($input['contact_number'] ?? $input['mobile'] ?? ''),
        'user_type' => sanitize($input['user_type'] ?? 'client'),
        'status' => sanitize($input['status'] ?? 'active')
    ];
    
    // Validation
    if (empty($data['first_name'])) {
        jsonResponse(false, 'First name is required.');
    }
    
    if (empty($data['last_name'])) {
        jsonResponse(false, 'Last name is required.');
    }
    
    if (empty($data['email'])) {
        jsonResponse(false, 'Email is required.');
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Invalid email format.');
    }
    
    if (empty($data['username'])) {
        jsonResponse(false, 'Username is required.');
    }
    
    if (empty($data['password'])) {
        jsonResponse(false, 'Password is required.');
    } elseif (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
        jsonResponse(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
    }
    
    // Validate user_type - allow admin, client, manager, doctor, staff
    $allowedUserTypes = ['admin', 'client', 'manager', 'doctor', 'staff'];
    // Normalize 'doctors' to 'doctor' for consistency
    if (strtolower($data['user_type']) === 'doctors') {
        $data['user_type'] = 'doctor';
    }
    if (!in_array(strtolower($data['user_type']), $allowedUserTypes)) {
        jsonResponse(false, 'Invalid user type.');
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
    
    try {
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Generate user_id
        require_once __DIR__ . '/../includes/functions.php';
        $userId = generateUserId($data['user_type']);
        
        // Check if email verification is required for this user type
        $skipVerificationTypes = ['manager', 'doctor', 'staff', 'admin'];
        $requiresVerification = !in_array(strtolower($data['user_type']), $skipVerificationTypes);
        
        // Set email_verified_at for manager, doctors, staff, admin
        $emailVerifiedAt = null;
        if (!$requiresVerification) {
            $emailVerifiedAt = date('Y-m-d H:i:s');
        }
        
        // Schema: tbl_users has user_id, password_hash, role, full_name (no id, no email_verified_at)
        $role = function_exists('_authUserTypeToRole') ? _authUserTypeToRole($data['user_type']) : $data['user_type'];
        $fullName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: $data['username'];
        $stmt = $pdo->prepare("
            INSERT INTO tbl_users (
                tenant_id, user_id, email, username, full_name, password_hash, role, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $tenantId,
            $userId,
            $data['email'],
            $data['username'],
            $fullName,
            $hashedPassword,
            $role,
            $data['status'],
        ]);
        
        $insertId = $userId; // PK is user_id
        
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
                    INSERT INTO tbl_patients (
                        tenant_id, patient_id, owner_user_id, linked_user_id, first_name, last_name, contact_number, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $tenantId,
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
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_staffs WHERE tenant_id = ? AND staff_id LIKE ?");
                $stmt->execute([$tenantId, "S-{$year}-%"]);
                $result = $stmt->fetch();
                $sequence = str_pad((int)$result['count'] + 1, 5, '0', STR_PAD_LEFT);
                $staffDisplayId = "S-{$year}-{$sequence}";
                
                // Create staff profile
                $stmt = $pdo->prepare("
                    INSERT INTO tbl_staffs (
                        tenant_id, staff_id, user_id, first_name, last_name, contact_number, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $tenantId,
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
        
        jsonResponse(true, 'User created successfully.', ['user_id' => $userId]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to create user. Please try again.');
    }
}

/**
 * Get users
 */
function getUsers() {
    global $pdo, $tenantId;
    
    // Require authentication - allow manager, staff, and doctor roles
    // Staff and doctors can only fetch client users for messaging purposes
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        jsonResponse(false, 'Unauthorized. Please login.');
    }
    
    $currentUserType = $_SESSION['user_type'];
    $allowedTypes = ['manager', 'staff', 'doctor', 'admin'];
    
    if (!in_array($currentUserType, $allowedTypes)) {
        jsonResponse(false, 'Unauthorized. Access denied.');
    }
    
    // Staff and doctors can only fetch client users (for messaging)
    // Managers and admins can fetch all user types
    $isRestricted = in_array($currentUserType, ['staff', 'doctor']);
    
    // Get query parameters
    $userId = isset($_GET['id']) ? sanitize((string) $_GET['id']) : null;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $userType = isset($_GET['user_type']) ? sanitize($_GET['user_type']) : null;
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    try {
        if ($userId) {
            // Get single user (without password) - join with patients or staffs to get first_name, last_name, contact_number, profile_image
            $sql = "
                SELECT u.user_id as id, u.user_id, u.email, u.username,
                       CASE WHEN u.role = 'dentist' THEN 'doctor' WHEN u.role = 'tenant_owner' THEN 'manager' ELSE u.role END as user_type,
                       u.status, u.last_login, u.created_at, u.updated_at,
                       COALESCE(p.first_name, s.first_name) as first_name,
                       COALESCE(p.last_name, s.last_name) as last_name,
                       COALESCE(p.contact_number, s.contact_number) as contact_number,
                       COALESCE(p.profile_image, s.profile_image) as profile_image
                FROM tbl_users u
                LEFT JOIN tbl_patients p ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id AND p.tenant_id = u.tenant_id AND u.role = 'client'
                LEFT JOIN tbl_staffs s ON s.user_id = u.user_id AND s.tenant_id = u.tenant_id AND u.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                WHERE u.user_id = ? AND u.tenant_id = ?
            ";
            
            if ($isRestricted) {
                $sql .= " AND u.role = 'client'";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId, $tenantId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, 'User not found.');
            }
            
            jsonResponse(true, 'User retrieved successfully.', ['user' => $user]);
        } else {
            // Get list of users - join with patients or staffs to get first_name, last_name, contact_number, profile_image
            $sql = "
                SELECT u.user_id as id, u.user_id, u.email, u.username,
                       CASE WHEN u.role = 'dentist' THEN 'doctor' WHEN u.role = 'tenant_owner' THEN 'manager' ELSE u.role END as user_type,
                       u.status, u.last_login, u.created_at, u.updated_at,
                       COALESCE(p.first_name, s.first_name) as first_name,
                       COALESCE(p.last_name, s.last_name) as last_name,
                       COALESCE(p.contact_number, s.contact_number) as contact_number,
                       COALESCE(p.profile_image, s.profile_image) as profile_image,
                       (SELECT COUNT(*) FROM tbl_appointments a WHERE a.created_by = u.user_id AND a.tenant_id = u.tenant_id) as appointment_count
                FROM tbl_users u
                LEFT JOIN tbl_patients p ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id AND p.tenant_id = u.tenant_id AND u.role = 'client'
                LEFT JOIN tbl_staffs s ON s.user_id = u.user_id AND s.tenant_id = u.tenant_id AND u.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                WHERE u.tenant_id = ?
            ";
            
            $params = [$tenantId];
            
            if ($search) {
                $sql .= " AND (COALESCE(p.first_name, s.first_name) LIKE ? OR COALESCE(p.last_name, s.last_name) LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if ($isRestricted) {
                $sql .= " AND u.role = 'client'";
            } elseif ($userType) {
                $roleFilter = ($userType === 'doctor') ? 'dentist' : (($userType === 'admin') ? 'manager' : $userType);
                $sql .= " AND u.role = ?";
                $params[] = $roleFilter;
            }
            
            if ($status) {
                $sql .= " AND u.status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY u.created_at DESC";
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_query";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get users with pagination (inject validated ints to avoid LIMIT bind issues)
            $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll();
            
            jsonResponse(true, 'Users retrieved successfully.', [
                'users' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        
    } catch (PDOException $e) {
        error_log('Get Users PDO Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        $errorMsg = defined('DB_DEBUG') && DB_DEBUG 
            ? 'Database error: ' . $e->getMessage() 
            : 'Database error. Please check your database connection.';
        jsonResponse(false, $errorMsg);
    } catch (Exception $e) {
        error_log('Get Users Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        $errorMessage = (ini_get('display_errors')) ? $e->getMessage() : 'Failed to retrieve users.';
        jsonResponse(false, $errorMessage);
    }
}

/**
 * Update user
 */
function updateUser() {
    global $pdo, $tenantId;
    
    // Require manager authentication
    if (!isLoggedIn('manager')) {
        jsonResponse(false, 'Unauthorized. Manager access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = sanitize((string) ($input['id'] ?? ''));
    
    if ($userId === '') {
        jsonResponse(false, 'User ID is required.');
    }
    
    // Prevent updating own account to inactive
    $currentUserId = getCurrentUserId();
    if ((string) $userId === (string) $currentUserId && isset($input['status']) && $input['status'] === 'inactive') {
        jsonResponse(false, 'You cannot deactivate your own account.');
    }
    
    // Check if user exists (same tenant); schema PK is user_id
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([$userId, $tenantId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'User not found.');
    }
    
    $userUserId = $userId;
    $stmt = $pdo->prepare("SELECT role, email FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([$userId, $tenantId]);
    $user = $stmt->fetch();
    $profileLookupEmail = strtolower(trim((string) ($user['email'] ?? '')));
    $currentUserType = ($user && function_exists('_authRoleToUserType')) ? _authRoleToUserType($user['role'] ?? 'client') : ($user['role'] ?? 'client');
    // If user_type is being updated, use the new value, otherwise use current
    $targetUserType = isset($input['user_type']) ? $input['user_type'] : $currentUserType;
    
    // Extract updateable fields for users table
    $userUpdates = [];
    $userParams = [];
    
    // Schema: tbl_users has role (not user_type), password_hash (not password)
    $userFieldsMap = ['email' => 'email', 'username' => 'username', 'user_type' => 'role', 'status' => 'status'];
    foreach ($userFieldsMap as $inputKey => $dbCol) {
        if (isset($input[$inputKey]) && $input[$inputKey] !== null) {
            $val = $input[$inputKey];
            if ($inputKey === 'user_type' && function_exists('_authUserTypeToRole')) {
                $val = _authUserTypeToRole($val);
            }
            $userUpdates[] = "{$dbCol} = ?";
            $userParams[] = sanitize($val);
        }
    }
    
    if (isset($input['password']) && !empty($input['password'])) {
        if (strlen($input['password']) < PASSWORD_MIN_LENGTH) {
            jsonResponse(false, 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
        }
        $userUpdates[] = "password_hash = ?";
        $userParams[] = password_hash($input['password'], PASSWORD_DEFAULT);
    }
    
    // Extract fields for profile table (first_name, last_name, contact_number)
    // These go to patients table for clients, staffs table for non-clients
    $profileUpdates = [];
    $profileParams = [];
    
    if (isset($input['first_name']) && $input['first_name'] !== null) {
        $profileUpdates[] = "first_name = ?";
        $profileParams[] = sanitize($input['first_name']);
    }
    
    if (isset($input['last_name']) && $input['last_name'] !== null) {
        $profileUpdates[] = "last_name = ?";
        $profileParams[] = sanitize($input['last_name']);
    }
    
    // Handle both 'mobile' and 'contact_number' input names for backward compatibility
    $contactNumber = isset($input['contact_number']) ? $input['contact_number'] : (isset($input['mobile']) ? $input['mobile'] : null);
    if ($contactNumber !== null) {
        $profileUpdates[] = "contact_number = ?";
        $profileParams[] = sanitize($contactNumber);
    }
    
    if (empty($userUpdates) && empty($profileUpdates)) {
        jsonResponse(false, 'No fields to update.');
    }
    
    // Validate email if being updated
    if (isset($input['email'])) {
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Invalid email format.');
        }
        
        // Check if email already exists for another user (same tenant)
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE email = ? AND user_id != ? AND tenant_id = ?");
        $stmt->execute([$input['email'], $userId, $tenantId]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'Email already registered to another user.');
        }
    }
    
    // Validate username if being updated
    if (isset($input['username'])) {
        // Check if username already exists for another user
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ? AND user_id != ? AND tenant_id = ?");
        $stmt->execute([$input['username'], $userId, $tenantId]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'Username already taken.');
        }
    }
    
    // Validate user_type if being updated
    $allowedUserTypes = ['admin', 'client', 'manager', 'doctor', 'staff'];
    if (isset($input['user_type'])) {
        // Normalize 'doctors' to 'doctor' for consistency
        if (strtolower($input['user_type']) === 'doctors') {
            $input['user_type'] = 'doctor';
        }
        if (!in_array(strtolower($input['user_type']), $allowedUserTypes)) {
            jsonResponse(false, 'Invalid user type.');
        }
    }
    
    try {
        // Update users table
        if (!empty($userUpdates)) {
            $userUpdates[] = "updated_at = NOW()";
            $userParams[] = $userId;
            $sql = "UPDATE tbl_users SET " . implode(', ', $userUpdates) . " WHERE user_id = ? AND tenant_id = ?";
            $stmt = $pdo->prepare($sql);
            $userParams[] = $tenantId;
            $stmt->execute($userParams);
            
            // If user_type changed, update targetUserType
            if (isset($input['user_type'])) {
                $targetUserType = $input['user_type'];
            }
        }
        
        // Update profile table based on user_type
        // Clients → tbl_patients; dentists → tbl_dentists; other roles → tbl_staffs
        if (!empty($profileUpdates)) {
            if (strtolower($targetUserType) === 'client') {
                // Update/create patient profile for clients only
                $stmt = $pdo->prepare("SELECT id FROM tbl_patients WHERE linked_user_id = ? AND owner_user_id = ? AND tenant_id = ?");
                $stmt->execute([$userUserId, $userUserId, $tenantId]);
                $patient = $stmt->fetch();
                
                if ($patient) {
                    // Update existing patient profile
                    $profileUpdates[] = "updated_at = NOW()";
                    $profileParams[] = $patient['id'];
                    $sql = "UPDATE tbl_patients SET " . implode(', ', $profileUpdates) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($profileParams);
                } else {
                    // Create patient profile if it doesn't exist (only for clients)
                    // Generate patient_id using thread-safe function
                    $patientDisplayId = generatePatientId();
                    
                    $first_name = isset($input['first_name']) ? sanitize($input['first_name']) : '';
                    $last_name = isset($input['last_name']) ? sanitize($input['last_name']) : '';
                    $contact_number = $contactNumber ? sanitize($contactNumber) : null;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tbl_patients (
                            tenant_id, patient_id, owner_user_id, linked_user_id, first_name, last_name, contact_number, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $tenantId,
                        $patientDisplayId,
                        $userUserId,
                        $userUserId,
                        $first_name,
                        $last_name,
                        $contact_number
                    ]);
                }
            } elseif (strtolower($targetUserType) === 'doctor') {
                $stmt = $pdo->prepare("
                    SELECT dentist_id FROM tbl_dentists
                    WHERE tenant_id = ? AND LOWER(TRIM(COALESCE(email, ''))) = ?
                    ORDER BY dentist_id ASC
                    LIMIT 1
                ");
                $stmt->execute([$tenantId, $profileLookupEmail]);
                $dent = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$dent) {
                    jsonResponse(false, 'Dentist profile not found for this user.');
                }
                $dentistSets = $profileUpdates;
                $dentistParams = $profileParams;
                $dentistSets[] = 'email = ?';
                $stmt = $pdo->prepare("SELECT email FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
                $stmt->execute([$userUserId, $tenantId]);
                $dentistParams[] = trim((string) $stmt->fetchColumn());
                $dentistParams[] = (int) $dent['dentist_id'];
                $dentistParams[] = $tenantId;
                $sql = 'UPDATE tbl_dentists SET ' . implode(', ', $dentistSets) . ' WHERE dentist_id = ? AND tenant_id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($dentistParams);
            } else {
                // Update/create staff profile for non-clients (not dentists)
                $stmt = $pdo->prepare("SELECT id FROM tbl_staffs WHERE user_id = ? AND tenant_id = ?");
                $stmt->execute([$userUserId, $tenantId]);
                $staff = $stmt->fetch();
                
                if ($staff) {
                    // Update existing staff profile
                    $profileUpdates[] = "updated_at = NOW()";
                    $profileParams[] = $staff['id'];
                    $sql = "UPDATE tbl_staffs SET " . implode(', ', $profileUpdates) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($profileParams);
                } else {
                    // Create staff profile if it doesn't exist (only for non-clients)
                    $year = date('Y');
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_staffs WHERE tenant_id = ? AND staff_id LIKE ?");
                    $stmt->execute([$tenantId, "S-{$year}-%"]);
                    $result = $stmt->fetch();
                    $sequence = str_pad((int)$result['count'] + 1, 5, '0', STR_PAD_LEFT);
                    $staffDisplayId = "S-{$year}-{$sequence}";
                    
                    $first_name = isset($input['first_name']) ? sanitize($input['first_name']) : '';
                    $last_name = isset($input['last_name']) ? sanitize($input['last_name']) : '';
                    $contact_number = $contactNumber ? sanitize($contactNumber) : null;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO tbl_staffs (
                            tenant_id, staff_id, user_id, first_name, last_name, contact_number, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $tenantId,
                        $staffDisplayId,
                        $userUserId,
                        $first_name,
                        $last_name,
                        $contact_number
                    ]);
                }
            }
        }

        // Dentist directory email must follow tbl_users (e.g. manager changes email only)
        if ($user && strtolower((string) ($user['role'] ?? '')) === 'dentist') {
            $stmt = $pdo->prepare("SELECT email FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$userUserId, $tenantId]);
            $syncEmail = trim((string) $stmt->fetchColumn());
            if ($syncEmail !== '') {
                $stmt = $pdo->prepare("
                    SELECT dentist_id FROM tbl_dentists
                    WHERE tenant_id = ? AND LOWER(TRIM(COALESCE(email, ''))) = ?
                    ORDER BY dentist_id ASC
                    LIMIT 1
                ");
                $stmt->execute([$tenantId, $profileLookupEmail]);
                $dentSync = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($dentSync) {
                    $pdo->prepare('UPDATE tbl_dentists SET email = ? WHERE dentist_id = ? AND tenant_id = ?')
                        ->execute([$syncEmail, (int) $dentSync['dentist_id'], $tenantId]);
                }
            }
        }
        
        jsonResponse(true, 'User updated successfully.');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to update user.');
    }
}

/**
 * Delete user (soft delete by setting status to inactive)
 */
function deleteUser() {
    global $pdo, $tenantId;
    
    // Require manager authentication
    if (!isLoggedIn('manager')) {
        jsonResponse(false, 'Unauthorized. Manager access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = sanitize((string) ($input['id'] ?? ''));
    
    if ($userId === '') {
        jsonResponse(false, 'User ID is required.');
    }
    
    // Prevent deleting own account
    $currentUserId = getCurrentUserId();
    if ((string) $userId === (string) $currentUserId) {
        jsonResponse(false, 'You cannot delete your own account.');
    }
    
    // Check if user has related records (as owner or linked)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_patients WHERE (owner_user_id = ? OR linked_user_id = ?) AND tenant_id = ?");
    $stmt->execute([$userId, $userId, $tenantId]);
    $patientCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_appointments WHERE created_by = ? AND tenant_id = ?");
    $stmt->execute([$userId, $tenantId]);
    $appointmentCount = $stmt->fetch()['count'];
    
    if ($patientCount > 0 || $appointmentCount > 0) {
        // Soft delete - set status to inactive
        try {
            $stmt = $pdo->prepare("UPDATE tbl_users SET status = 'inactive', updated_at = NOW() WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            
            jsonResponse(true, 'User deactivated successfully.');
        } catch (Exception $e) {
            jsonResponse(false, 'Failed to deactivate user.');
        }
    } else {
        // Hard delete if no related records
        try {
            $stmt = $pdo->prepare("DELETE FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
            $stmt->execute([$userId, $tenantId]);
            
            jsonResponse(true, 'User deleted successfully.');
        } catch (Exception $e) {
            jsonResponse(false, 'Failed to delete user.');
        }
    }
}

