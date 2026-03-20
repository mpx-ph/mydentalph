<?php
/**
 * Admin/Staff Profile API Endpoint
 * Handles profile data retrieval and updates for logged-in admin/staff/doctor users
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

// Require admin/staff/doctor authentication
$userType = $_SESSION['user_type'] ?? '';
if (!in_array($userType, ['admin', 'staff', 'doctor', 'manager'])) {
    jsonResponse(false, 'Unauthorized. Admin, Staff, Doctor, or Manager access required.');
}

// Route based on method and action
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        getProfile();
        break;
    case 'PUT':
        updateProfile();
        break;
    case 'POST':
        if ($action === 'upload_photo') {
            uploadPhoto();
        } else {
            jsonResponse(false, 'Invalid action.');
        }
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Get profile data for logged-in admin/staff/doctor
 */
function getProfile() {
    global $pdo;
    
    $userId = getCurrentUserId(); // schema: session holds user_id (string)
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    try {
        // Get user from tbl_users (PK is user_id)
        $stmt = $pdo->prepare("SELECT user_id, username, email FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        
        $userUserId = $user['user_id'];
        
        // Get staff profile from tbl_staffs (schema)
        $stmt = $pdo->prepare("SELECT * FROM tbl_staffs WHERE user_id = ?");
        $stmt->execute([$userUserId]);
        $staff = $stmt->fetch();
        
        if ($staff) {
            // Staff record exists - return staff data with user account info
            jsonResponse(true, 'Profile retrieved successfully.', [
                'staff_id' => $staff['id'],
                'staff_display_id' => $staff['staff_id'],
                'first_name' => $staff['first_name'] ?? '',
                'last_name' => $staff['last_name'] ?? '',
                'contact_number' => $staff['contact_number'] ?? '',
                'date_of_birth' => $staff['date_of_birth'] ?? '',
                'gender' => $staff['gender'] ?? '',
                'house_street' => $staff['house_street'] ?? '',
                'barangay' => $staff['barangay'] ?? '',
                'city_municipality' => $staff['city_municipality'] ?? '',
                'province' => $staff['province'] ?? '',
                'profile_image' => $staff['profile_image'] ?? '',
                'employee_id' => $staff['employee_id'] ?? '',
                'department' => $staff['department'] ?? '',
                'position' => $staff['position'] ?? '',
                'hire_date' => $staff['hire_date'] ?? '',
                // Account info from users table
                'username' => $user['username'],
                'email' => $user['email'],
                'source' => 'staffs'
            ]);
        } else {
            // No staff record yet - return minimal data from users table
            jsonResponse(true, 'Profile retrieved successfully.', [
                'user_id' => $userUserId,
                'first_name' => '',
                'last_name' => '',
                'email' => $user['email'] ?? '',
                'contact_number' => '',
                'date_of_birth' => '',
                'gender' => '',
                'house_street' => '',
                'barangay' => '',
                'city_municipality' => '',
                'province' => '',
                'profile_image' => '',
                'employee_id' => '',
                'department' => '',
                'position' => '',
                'hire_date' => '',
                'username' => $user['username'],
                'source' => 'users'
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Get Admin Profile Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve profile.');
    }
}

/**
 * Update profile
 * Handles both personal details (staffs table) and password (users table)
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
 * Update personal details in staffs table
 */
function updatePersonalDetails($userId, $input) {
    global $pdo;
    
    try {
        // Schema: session/userId is user_id (string); verify user exists
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'User not found.');
        }
        $userUserId = $userId;
        
        // Extract and sanitize staff data
        $staffData = [
            'first_name' => sanitize($input['first_name'] ?? ''),
            'last_name' => sanitize($input['last_name'] ?? ''),
            'contact_number' => sanitize($input['contact_number'] ?? ''),
            'gender' => sanitize($input['gender'] ?? ''),
            'house_street' => sanitize($input['house_street'] ?? ''),
            'barangay' => sanitize($input['barangay'] ?? ''),
            'city_municipality' => sanitize($input['city_municipality'] ?? ''),
            'province' => sanitize($input['province'] ?? '')
        ];
        
        // Extract account data
        $accountData = [
            'email' => sanitize($input['email'] ?? '')
        ];
        
        // Validation
        if (empty($staffData['first_name'])) {
            jsonResponse(false, 'First name is required.');
        }
        
        if (empty($staffData['last_name'])) {
            jsonResponse(false, 'Last name is required.');
        }
        
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
        
        // Check if staff record exists
        $stmt = $pdo->prepare("SELECT id FROM tbl_staffs WHERE user_id = ?");
        $stmt->execute([$userUserId]);
        $existingStaff = $stmt->fetch();
        
        if ($existingStaff) {
            // UPDATE existing staff record
            $updateFields = [];
            $updateParams = [];
            
            if (isset($staffData['first_name'])) {
                $updateFields[] = "first_name = ?";
                $updateParams[] = $staffData['first_name'];
            }
            if (isset($staffData['last_name'])) {
                $updateFields[] = "last_name = ?";
                $updateParams[] = $staffData['last_name'];
            }
            if (isset($staffData['contact_number'])) {
                $updateFields[] = "contact_number = ?";
                $updateParams[] = $staffData['contact_number'] ?: null;
            }
            if (isset($staffData['gender'])) {
                $updateFields[] = "gender = ?";
                $updateParams[] = $staffData['gender'] ?: null;
            }
            if (isset($staffData['house_street'])) {
                $updateFields[] = "house_street = ?";
                $updateParams[] = $staffData['house_street'] ?: null;
            }
            if (isset($staffData['barangay'])) {
                $updateFields[] = "barangay = ?";
                $updateParams[] = $staffData['barangay'] ?: null;
            }
            if (isset($staffData['city_municipality'])) {
                $updateFields[] = "city_municipality = ?";
                $updateParams[] = $staffData['city_municipality'] ?: null;
            }
            if (isset($staffData['province'])) {
                $updateFields[] = "province = ?";
                $updateParams[] = $staffData['province'] ?: null;
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = "updated_at = NOW()";
                $updateParams[] = $existingStaff['id'];
                
                $stmt = $pdo->prepare("
                    UPDATE tbl_staffs SET " . implode(', ', $updateFields) . " WHERE id = ?
                ");
                $stmt->execute($updateParams);
            }
            
            // Also update account info in users table (email only)
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
            
            jsonResponse(true, 'Profile updated successfully.');
        } else {
            // INSERT new staff record
            // Generate staff_id display code
            $year = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_staffs WHERE staff_id LIKE ?");
            $stmt->execute(["S-{$year}-%"]);
            $result = $stmt->fetch();
            $sequence = str_pad((int)$result['count'] + 1, 5, '0', STR_PAD_LEFT);
            $staffDisplayId = "S-{$year}-{$sequence}";
            
            $stmt = $pdo->prepare("
                INSERT INTO tbl_staffs (
                    staff_id, user_id, first_name, last_name, contact_number,
                    gender, house_street, barangay, city_municipality, province, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $staffDisplayId,
                $userUserId,
                $staffData['first_name'],
                $staffData['last_name'],
                $staffData['contact_number'] ?: null,
                $staffData['gender'] ?: null,
                $staffData['house_street'] ?: null,
                $staffData['barangay'] ?: null,
                $staffData['city_municipality'] ?: null,
                $staffData['province'] ?: null
            ]);
            
            // Also update account info in users table (email only)
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
            
            jsonResponse(true, 'Profile saved successfully.');
        }
        
    } catch (Exception $e) {
        error_log('Update Admin Profile Error: ' . $e->getMessage());
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
        // Get current password hash (schema: password_hash)
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
 * Upload profile photo
 * Handles photo upload for admin/staff/doctor profiles
 */
function uploadPhoto() {
    global $pdo;
    
    $userId = getCurrentUserId(); // schema: session holds user_id (string)
    
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['photo'])) {
        jsonResponse(false, 'No photo data provided.');
    }
    
    try {
        $userUserId = $userId;
        
        // Save the photo using saveBase64Image function
        $photoResult = saveBase64Image($input['photo'], 'uploads/staffs/', 'staff_');
        
        if (!$photoResult['success']) {
            jsonResponse(false, !empty($photoResult['message']) ? $photoResult['message'] : 'Failed to upload photo.');
        }
        
        $profileImagePath = $photoResult['filepath'];
        
        // Check if staff record exists
        $stmt = $pdo->prepare("SELECT id FROM tbl_staffs WHERE user_id = ?");
        $stmt->execute([$userUserId]);
        $existingStaff = $stmt->fetch();
        
        if ($existingStaff) {
            // Update existing staff record with profile image
            $stmt = $pdo->prepare("UPDATE tbl_staffs SET profile_image = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$profileImagePath, $existingStaff['id']]);
        } else {
            // Create a minimal staff record if it doesn't exist
            $year = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_staffs WHERE staff_id LIKE ?");
            $stmt->execute(["S-{$year}-%"]);
            $result = $stmt->fetch();
            $sequence = str_pad((int)$result['count'] + 1, 5, '0', STR_PAD_LEFT);
            $staffDisplayId = "S-{$year}-{$sequence}";
            
            $stmt = $pdo->prepare("
                INSERT INTO tbl_staffs (
                    staff_id, user_id, profile_image, created_at
                ) VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$staffDisplayId, $userUserId, $profileImagePath]);
        }
        
        jsonResponse(true, 'Photo uploaded successfully.', [
            'profile_image' => $profileImagePath
        ]);
        
    } catch (Exception $e) {
        error_log('Upload Photo Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to upload photo.');
    }
}
