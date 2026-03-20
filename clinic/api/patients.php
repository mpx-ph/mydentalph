<?php
/**
 * Patients API Endpoint
 * Handles CRUD operations for patients
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
        createPatient();
        break;
    case 'GET':
        getPatients();
        break;
    case 'PUT':
        updatePatient();
        break;
    case 'DELETE':
        deletePatient();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Create new patient
 */
function createPatient() {
    global $pdo, $tenantId;
    
    // Require manager/doctor/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('doctor') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager, Doctor, or Staff access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extract and sanitize data - match actual database schema
    // Note: patients table now has first_name, last_name, contact_number
    // Email is only in users table, so dependents won't have email
    $data = [
        'first_name' => sanitize($input['first_name'] ?? ''),
        'last_name' => sanitize($input['last_name'] ?? ''),
        'contact_number' => sanitize($input['mobile'] ?? $input['contact_number'] ?? $input['contact'] ?? ''),
        'date_of_birth' => sanitize($input['date_of_birth'] ?? $input['dob'] ?? ''),
        'gender' => sanitize($input['gender'] ?? ''),
        'house_street' => sanitize($input['house_street'] ?? $input['houseStreet'] ?? ($input['address'] ? explode(',', $input['address'])[0] : '')),
        'barangay' => sanitize($input['barangay'] ?? ''),
        'city_municipality' => sanitize($input['city_municipality'] ?? $input['city'] ?? ''),
        'province' => sanitize($input['province'] ?? ''),
        'owner_user_id' => sanitize($input['owner_user_id'] ?? ''),
        'linked_user_id' => sanitize($input['linked_user_id'] ?? null)
    ];
    
    // Handle photo upload (base64) - stored as profile_image in schema
    $profileImage = null;
    if (!empty($input['photo'])) {
        $photoResult = saveBase64Image($input['photo'], 'uploads/patients/', 'patient_');
        if ($photoResult['success']) {
            $profileImage = $photoResult['filepath'];
        } else {
            // Photo upload failed, but continue without photo
            error_log('Photo upload failed: ' . $photoResult['message']);
        }
    }
    
    // Validation
    if (empty($data['first_name'])) {
        jsonResponse(false, 'First name is required.');
    }
    
    if (empty($data['last_name'])) {
        jsonResponse(false, 'Last name is required.');
    }
    
    if (empty($data['owner_user_id'])) {
        jsonResponse(false, 'Owner user ID is required.');
    }
    
    // Validate owner_user_id exists
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([$data['owner_user_id'], $tenantId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'Owner user ID not found.');
    }
    
    // If linked_user_id is provided, validate it exists
    if (!empty($data['linked_user_id'])) {
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$data['linked_user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'Linked user ID not found.');
        }
    }
    
    try {
        // Generate patient_id display code using thread-safe function
        $patientDisplayId = generatePatientId();
        
        $stmt = $pdo->prepare("
            INSERT INTO patients (
                tenant_id,
                patient_id, owner_user_id, linked_user_id, first_name, last_name, contact_number,
                date_of_birth, gender, house_street, barangay, city_municipality, province, profile_image, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $tenantId,
            $patientDisplayId,
            $data['owner_user_id'],
            $data['linked_user_id'] ?: null,
            $data['first_name'],
            $data['last_name'],
            $data['contact_number'] ?: null,
            $data['date_of_birth'] ?: null,
            $data['gender'] ?: null,
            $data['house_street'] ?: null,
            $data['barangay'] ?: null,
            $data['city_municipality'] ?: null,
            $data['province'] ?: null,
            $profileImage
        ]);
        
        $patientId = $pdo->lastInsertId();
        
        jsonResponse(true, 'Patient created successfully.', ['patient_id' => $patientId]);
        
    } catch (Exception $e) {
        error_log('Patient creation error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to create patient: ' . $e->getMessage());
    }
}

/**
 * Get patients
 */
function getPatients() {
    global $pdo, $tenantId;
    
    // Require manager/doctor/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('doctor') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager, Doctor, or Staff access required.');
    }
    
    // Get query parameters
    $patientId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    try {
        if ($patientId) {
            // Get single patient
            $stmt = $pdo->prepare("
                SELECT p.*
                FROM patients p
                WHERE p.id = ? AND p.tenant_id = ?
            ");
            $stmt->execute([$patientId, $tenantId]);
            $patient = $stmt->fetch();
            
            if (!$patient) {
                jsonResponse(false, 'Patient not found.');
            }
            
            jsonResponse(true, 'Patient retrieved successfully.', ['patient' => $patient]);
        } else {
            // Get list of patients (first_name, last_name, contact_number are now in patients table)
            // Join with users to get email for self profiles
            $sql = "
                SELECT p.*,
                       u.email,
                       (SELECT COUNT(*) FROM appointments WHERE patient_id = p.patient_id) as appointment_count,
                       (SELECT COUNT(*) FROM payments WHERE patient_id = p.id) as payment_count
                FROM patients p
                LEFT JOIN tbl_users u ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id
                WHERE p.tenant_id = ?
            ";
            
            $params = [$tenantId];
            
            if ($search) {
                $sql .= " AND (
                    p.first_name LIKE ? OR 
                    p.last_name LIKE ? OR 
                    p.contact_number LIKE ? OR
                    p.patient_id LIKE ? OR
                    u.email LIKE ?
                )";
                $searchTerm = "%{$search}%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            
            // Get total count
            $countSql = "
                SELECT COUNT(*) as total 
                FROM patients p
                LEFT JOIN tbl_users u ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id
                WHERE p.tenant_id = ?
            ";
            $countParams = [$tenantId];
            if ($search) {
                $countSql .= " AND (
                    p.first_name LIKE ? OR 
                    p.last_name LIKE ? OR 
                    p.contact_number LIKE ? OR
                    p.patient_id LIKE ? OR
                    u.email LIKE ?
                )";
                $searchTerm = "%{$search}%";
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
                $countParams[] = $searchTerm;
            }
            
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetch()['total'];
            
            // Get patients
            $params[] = $limit;
            $params[] = $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $patients = $stmt->fetchAll();
            
            jsonResponse(true, 'Patients retrieved successfully.', [
                'patients' => $patients,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Error retrieving patients: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        jsonResponse(false, 'Failed to retrieve patients: ' . $e->getMessage());
    }
}

/**
 * Update patient
 */
function updatePatient() {
    global $pdo, $tenantId;
    
    // Require manager/doctor/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('doctor') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager, Doctor, or Staff access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $patientId = intval($input['id'] ?? 0);
    
    if (!$patientId) {
        jsonResponse(false, 'Patient ID is required.');
    }
    
    // Check if patient exists
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$patientId, $tenantId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'Patient not found.');
    }
    
    // Extract updateable fields - match actual schema
    $updates = [];
    $params = [];
    
    $fields = [
        'first_name', 'last_name', 'contact_number', 'date_of_birth', 'gender', 'blood_type',
        'house_street', 'barangay', 'city_municipality', 'province', 'owner_user_id', 'linked_user_id'
    ];
    
    // Handle photo upload separately if provided (stored as profile_image)
    $profileImage = null;
    if (isset($input['photo']) && !empty($input['photo'])) {
        $photoResult = saveBase64Image($input['photo'], 'uploads/patients/', 'patient_');
        if ($photoResult['success']) {
            $profileImage = $photoResult['filepath'];
        } else {
            error_log('Photo upload failed: ' . $photoResult['message']);
        }
    }
    
    foreach ($fields as $field) {
        if (isset($input[$field]) && $input[$field] !== null) {
            $updates[] = "{$field} = ?";
            $params[] = sanitize($input[$field]);
        }
    }
    
    // Handle profile_image separately
    if ($profileImage !== null) {
        $updates[] = "profile_image = ?";
        $params[] = $profileImage;
    }
    
    if (empty($updates)) {
        jsonResponse(false, 'No fields to update.');
    }
    
    // Validate owner_user_id if being updated
    if (isset($input['owner_user_id'])) {
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$input['owner_user_id'], $tenantId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'Owner user ID not found.');
        }
    }
    
    // Validate linked_user_id if being updated
    if (isset($input['linked_user_id']) && !empty($input['linked_user_id'])) {
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$input['linked_user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'Linked user ID not found.');
        }
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $patientId;
    
    try {
        $sql = "UPDATE patients SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $params[] = $tenantId;
        $stmt->execute($params);
        
        jsonResponse(true, 'Patient updated successfully.');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to update patient.');
    }
}

/**
 * Delete patient (soft delete by setting status to inactive)
 */
function deletePatient() {
    global $pdo, $tenantId;
    
    // Require manager/doctor/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('doctor') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager, Doctor, or Staff access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $patientId = intval($input['id'] ?? 0);
    
    if (!$patientId) {
        jsonResponse(false, 'Patient ID is required.');
    }
    
    // Check if patient has appointments or payments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $appointmentCount = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $paymentCount = $stmt->fetch()['count'];
    
    if ($appointmentCount > 0 || $paymentCount > 0) {
        // Soft delete - set status to inactive
        try {
            $stmt = $pdo->prepare("UPDATE patients SET status = 'inactive', updated_at = NOW() WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$patientId, $tenantId]);
            
            jsonResponse(true, 'Patient deactivated successfully.');
        } catch (Exception $e) {
            jsonResponse(false, 'Failed to deactivate patient.');
        }
    } else {
        // Hard delete if no related records
        try {
            $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$patientId, $tenantId]);
            
            jsonResponse(true, 'Patient deleted successfully.');
        } catch (Exception $e) {
            jsonResponse(false, 'Failed to delete patient.');
        }
    }
}

