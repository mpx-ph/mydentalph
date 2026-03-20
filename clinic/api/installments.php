<?php
/**
 * Installments API Endpoint
 * Handles CRUD operations for installments (long-term treatment payments)
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
    case 'GET':
        getInstallments();
        break;
    case 'PUT':
        updateInstallment();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Get installments
 */
function getInstallments() {
    global $pdo, $tenantId;
    
    $userId = getCurrentUserId();
    $userType = $_SESSION['user_type'] ?? 'client';
    
    // Get query parameters
    $bookingId = isset($_GET['booking_id']) ? sanitize($_GET['booking_id']) : null;
    
    if (!$bookingId) {
        jsonResponse(false, 'Booking ID is required.');
    }
    
    try {
        // Verify user has access to this appointment
        if ($userType === 'manager') {
            // Manager can see all installments
            $stmt = $pdo->prepare("
                SELECT i.*, a.patient_id, a.booking_id, a.total_treatment_cost
                FROM installments i
                INNER JOIN appointments a ON i.booking_id = a.booking_id
                WHERE i.booking_id = ? AND i.tenant_id = ? AND a.tenant_id = ?
                ORDER BY i.installment_number ASC
            ");
            $stmt->execute([$bookingId, $tenantId, $tenantId]);
        } else {
            // Client can only see installments for their owned patient profiles
            if (!$userId) {
                jsonResponse(false, 'Authentication required.');
            }
            
            // Get user's user_id (varchar)
            $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                jsonResponse(false, 'User not found.');
            }
            
            $userUserId = $user['user_id'];
            
            // Verify appointment belongs to user
            $stmt = $pdo->prepare("
                SELECT a.booking_id
                FROM appointments a
                INNER JOIN patients p ON a.patient_id = p.patient_id
                WHERE a.booking_id = ? AND p.owner_user_id = ? AND a.tenant_id = ? AND p.tenant_id = ?
            ");
            $stmt->execute([$bookingId, $userUserId, $tenantId, $tenantId]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                jsonResponse(false, 'Unauthorized. Appointment not found.');
            }
            
            // Get installments
            $stmt = $pdo->prepare("
                SELECT i.*, a.patient_id, a.booking_id, a.total_treatment_cost
                FROM installments i
                INNER JOIN appointments a ON i.booking_id = a.booking_id
                WHERE i.booking_id = ? AND i.tenant_id = ? AND a.tenant_id = ?
                ORDER BY i.installment_number ASC
            ");
            $stmt->execute([$bookingId, $tenantId, $tenantId]);
        }
        
        $installments = $stmt->fetchAll();
        
        // Get total treatment cost from appointment
        $stmt = $pdo->prepare("SELECT total_treatment_cost FROM appointments WHERE booking_id = ? AND tenant_id = ?");
        $stmt->execute([$bookingId, $tenantId]);
        $appointment = $stmt->fetch();
        $totalTreatmentCost = $appointment ? floatval($appointment['total_treatment_cost']) : 0;
        
        jsonResponse(true, 'Installments retrieved successfully.', [
            'installments' => $installments,
            'total_treatment_cost' => $totalTreatmentCost
        ]);
        
    } catch (Exception $e) {
        error_log('Get installments error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve installments.');
    }
}

/**
 * Update installment
 */
function updateInstallment() {
    global $pdo, $tenantId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $installmentId = intval($input['id'] ?? 0);
    
    if (!$installmentId) {
        jsonResponse(false, 'Installment ID is required.');
    }
    
    // Check permissions (admin only or appointment owner)
    $userType = $_SESSION['user_type'] ?? 'client';
    $userId = getCurrentUserId();
    
    if ($userType !== 'manager' && $userType !== 'doctor' && $userType !== 'staff') {
        // Check if user owns this appointment (through patient profile ownership)
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        
        $userUserId = $user['user_id'];
        
        $stmt = $pdo->prepare("
            SELECT a.patient_id, p.owner_user_id 
            FROM installments i
            INNER JOIN appointments a ON i.booking_id = a.booking_id
            LEFT JOIN patients p ON a.patient_id = p.patient_id
            WHERE i.id = ? AND i.tenant_id = ? AND a.tenant_id = ? AND p.tenant_id = ?
        ");
        $stmt->execute([$installmentId, $tenantId, $tenantId, $tenantId]);
        $installment = $stmt->fetch();
        
        if (!$installment || $installment['owner_user_id'] !== $userUserId) {
            jsonResponse(false, 'Unauthorized.');
        }
    }
    
    // Extract updateable fields
    $updates = [];
    $params = [];
    
    if (isset($input['status'])) {
        $updates[] = "status = ?";
        $params[] = sanitize($input['status']);
    }
    
    if (isset($input['scheduled_date'])) {
        $updates[] = "scheduled_date = ?";
        $params[] = sanitize($input['scheduled_date']);
    }
    
    if (isset($input['scheduled_time'])) {
        $updates[] = "scheduled_time = ?";
        $params[] = sanitize($input['scheduled_time']);
    }
    
    if (isset($input['notes'])) {
        $updates[] = "notes = ?";
        $params[] = sanitize($input['notes']);
    }
    
    if (empty($updates)) {
        jsonResponse(false, 'No fields to update.');
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $installmentId;
    $params[] = $tenantId;
    
    try {
        $sql = "UPDATE installments SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse(true, 'Installment updated successfully.');
        
    } catch (Exception $e) {
        error_log('Update installment error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update installment.');
    }
}
