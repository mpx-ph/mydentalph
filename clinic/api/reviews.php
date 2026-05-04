<?php
/**
 * Reviews API Endpoint
 * Handles CRUD operations for appointment reviews
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

// Route based on method and action
$action = isset($_GET['action']) ? sanitize($_GET['action']) : null;

switch ($method) {
    case 'POST':
        createReview();
        break;
    case 'GET':
        getReviews();
        break;
    case 'PUT':
        updateReview();
        break;
    case 'DELETE':
        deleteReview();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Generate unique review ID
 * Format: RV-YYYY-XXXXXX where XXXXXX is a 6-digit sequential number
 * @return string Review ID
 */
function generateReviewId() {
    global $pdo, $tenantId;
    
    $prefix = 'RV';
    $year = date('Y');
    
    // Get the last review ID for this year
    $stmt = $pdo->prepare("SELECT review_id FROM tbl_reviews WHERE review_id LIKE ? AND tenant_id = ? ORDER BY review_id DESC LIMIT 1");
    $pattern = $prefix . '-' . $year . '-%';
    $stmt->execute([$pattern, $tenantId]);
    $lastReview = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastReview) {
        // Extract the number part and increment
        $parts = explode('-', $lastReview['review_id']);
        $lastNumber = intval($parts[2] ?? 0);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . '-' . $year . '-' . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
}

/**
 * Create a new review
 */
function createReview() {
    global $pdo, $tenantId;
    
    // Require authentication
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Unauthorized. Please login.');
    }
    
    // Get current user's patient_id
    $userIdInt = getCurrentUserId();
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([$userIdInt, $tenantId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !isset($user['user_id'])) {
        jsonResponse(false, 'User not found. Please login again.');
    }
    
    $userUserId = $user['user_id'];
    
    // Get patient_id from patients table
    $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE owner_user_id = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$userUserId, $tenantId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient || !isset($patient['patient_id'])) {
        jsonResponse(false, 'Patient profile not found.');
    }
    
    $patientId = $patient['patient_id'];
    
    // Get input data
    $appointmentId = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : 0;
    $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    // Validation
    if ($appointmentId <= 0) {
        jsonResponse(false, 'Invalid appointment ID.');
    }
    
    if ($rating < 1 || $rating > 5) {
        jsonResponse(false, 'Rating must be between 1 and 5.');
    }
    
    // Verify appointment exists and belongs to this patient
    $stmt = $pdo->prepare("
        SELECT a.id, a.booking_id, a.patient_id, a.status 
        FROM tbl_appointments a 
        WHERE a.id = ? AND a.patient_id = ? AND a.tenant_id = ?
    ");
    $stmt->execute([$appointmentId, $patientId, $tenantId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        jsonResponse(false, 'Appointment not found or does not belong to you.');
    }
    
    // Check if review already exists for this appointment
    $stmt = $pdo->prepare("SELECT id FROM tbl_reviews WHERE appointment_id = ? AND tenant_id = ?");
    $stmt->execute([$appointmentId, $tenantId]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'You have already reviewed this appointment.');
    }
    
    // Generate review ID
    $reviewId = generateReviewId();
    
    // Insert review
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_reviews (tenant_id, review_id, appointment_id, booking_id, patient_id, rating, comment)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tenantId,
            $reviewId,
            $appointmentId,
            $appointment['booking_id'],
            $patientId,
            $rating,
            $comment
        ]);
        
        jsonResponse(true, 'Review submitted successfully.', [
            'review_id' => $reviewId,
            'appointment_id' => $appointmentId
        ]);
    } catch (PDOException $e) {
        error_log('Error creating review: ' . $e->getMessage());
        jsonResponse(false, 'Failed to submit review. Please try again.');
    }
}

/**
 * Get reviews
 */
function getReviews() {
    global $pdo, $tenantId;
    
    // Require authentication
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Unauthorized. Please login.');
    }
    
    $userType = $_SESSION['user_type'] ?? 'client';
    
    // Get query parameters
    $reviewId = isset($_GET['id']) ? sanitize($_GET['id']) : null;
    $appointmentId = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : null;
    $bookingId = isset($_GET['booking_id']) ? sanitize($_GET['booking_id']) : null;
    $patientId = isset($_GET['patient_id']) ? sanitize($_GET['patient_id']) : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 50;
    $offset = ($page - 1) * $limit;
    
    try {
        if ($reviewId) {
            // Get single review
            $sql = "
                SELECT r.*,
                       p.first_name, p.last_name,
                       a.booking_id, a.appointment_date, a.appointment_time
                FROM tbl_reviews r
                LEFT JOIN tbl_patients p ON r.patient_id = p.patient_id AND p.tenant_id = r.tenant_id
                LEFT JOIN tbl_appointments a ON r.appointment_id = a.id AND a.tenant_id = r.tenant_id
                WHERE r.review_id = ? AND r.tenant_id = ?
            ";
            
            if ($userType !== 'manager' && $userType !== 'doctor' && $userType !== 'staff') {
                // Clients can only see their own reviews
                $userIdInt = getCurrentUserId();
                $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
                $stmt->execute([$userIdInt, $tenantId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $userUserId = $user['user_id'] ?? null;
                
                $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE owner_user_id = ? AND tenant_id = ? LIMIT 1");
                $stmt->execute([$userUserId, $tenantId]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                $patientId = $patient['patient_id'] ?? null;
                
                if ($patientId) {
                    $sql .= " AND r.patient_id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$reviewId, $tenantId, $patientId]);
                } else {
                    jsonResponse(false, 'Patient profile not found.');
                }
            } else {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$reviewId, $tenantId]);
            }
            
            $review = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$review) {
                jsonResponse(false, 'Review not found.');
            }
            
            jsonResponse(true, 'Review retrieved successfully.', ['review' => $review]);
        } else {
            // Get list of reviews
            $sql = "
                SELECT r.*,
                       p.first_name, p.last_name,
                       a.booking_id, a.appointment_date, a.appointment_time,
                       GROUP_CONCAT(DISTINCT s.service_name SEPARATOR ', ') as service_names
                FROM tbl_reviews r
                LEFT JOIN tbl_patients p ON r.patient_id = p.patient_id AND p.tenant_id = r.tenant_id
                LEFT JOIN tbl_appointments a ON r.appointment_id = a.id AND a.tenant_id = r.tenant_id
                LEFT JOIN tbl_appointment_services aps ON a.id = aps.appointment_id AND aps.tenant_id = r.tenant_id
                LEFT JOIN tbl_services s ON aps.service_id = s.service_id AND s.tenant_id = r.tenant_id
                WHERE r.tenant_id = ?
            ";
            
            $params = [$tenantId];
            
            if ($appointmentId) {
                $sql .= " AND r.appointment_id = ?";
                $params[] = $appointmentId;
            }
            
            if ($bookingId) {
                $sql .= " AND r.booking_id = ?";
                $params[] = $bookingId;
            }
            
            if ($patientId) {
                $sql .= " AND r.patient_id = ?";
                $params[] = $patientId;
            }
            
            if ($userType !== 'manager' && $userType !== 'doctor' && $userType !== 'staff') {
                // Clients can only see their own reviews
                $userIdInt = getCurrentUserId();
                $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
                $stmt->execute([$userIdInt, $tenantId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                $userUserId = $user['user_id'] ?? null;
                
                $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE owner_user_id = ? AND tenant_id = ? LIMIT 1");
                $stmt->execute([$userUserId, $tenantId]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                $patientId = $patient['patient_id'] ?? null;
                
                if ($patientId) {
                    $sql .= " AND r.patient_id = ?";
                    $params[] = $patientId;
                } else {
                    jsonResponse(true, 'No reviews found.', ['reviews' => [], 'pagination' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'pages' => 0]]);
                }
            }
            
            $sql .= " GROUP BY r.id ORDER BY r.created_at DESC";
            
            // Get total count
            $countSql = "SELECT COUNT(DISTINCT r.id) as total FROM (" . $sql . ") as count_query";
            $countStmt = $pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Add pagination
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(true, 'Reviews retrieved successfully.', [
                'reviews' => $reviews,
                'pagination' => [
                    'total' => intval($total),
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
    } catch (PDOException $e) {
        error_log('Error getting reviews: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve reviews.');
    }
}

/**
 * Update a review
 */
function updateReview() {
    global $pdo, $tenantId;
    
    // Require authentication
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Unauthorized. Please login.');
    }
    
    // Parse PUT data
    parse_str(file_get_contents('php://input'), $putData);
    
    $reviewId = isset($putData['review_id']) ? sanitize($putData['review_id']) : null;
    $rating = isset($putData['rating']) ? intval($putData['rating']) : 0;
    $comment = isset($putData['comment']) ? trim($putData['comment']) : '';
    
    if (!$reviewId) {
        jsonResponse(false, 'Review ID is required.');
    }
    
    if ($rating < 1 || $rating > 5) {
        jsonResponse(false, 'Rating must be between 1 and 5.');
    }
    
    // Verify review exists and belongs to user (unless admin)
    $userType = $_SESSION['user_type'] ?? 'client';
    
    if ($userType !== 'manager' && $userType !== 'doctor' && $userType !== 'staff') {
        $userIdInt = getCurrentUserId();
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$userIdInt, $tenantId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userUserId = $user['user_id'] ?? null;
        
        $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE owner_user_id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$userUserId, $tenantId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        $patientId = $patient['patient_id'] ?? null;
        
        $stmt = $pdo->prepare("SELECT patient_id FROM tbl_reviews WHERE review_id = ? AND tenant_id = ?");
        $stmt->execute([$reviewId, $tenantId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$review || $review['patient_id'] !== $patientId) {
            jsonResponse(false, 'Review not found or you do not have permission to update it.');
        }
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE tbl_reviews 
            SET rating = ?, comment = ?
            WHERE review_id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([$rating, $comment, $reviewId, $tenantId]);
        
        jsonResponse(true, 'Review updated successfully.');
    } catch (PDOException $e) {
        error_log('Error updating review: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update review.');
    }
}

/**
 * Delete a review
 */
function deleteReview() {
    global $pdo, $tenantId;
    
    // Require authentication
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Unauthorized. Please login.');
    }
    
    $reviewId = isset($_GET['id']) ? sanitize($_GET['id']) : null;
    
    if (!$reviewId) {
        jsonResponse(false, 'Review ID is required.');
    }
    
    // Verify review exists and belongs to user (unless admin)
    $userType = $_SESSION['user_type'] ?? 'client';
    
    if ($userType !== 'manager' && $userType !== 'doctor' && $userType !== 'staff') {
        $userIdInt = getCurrentUserId();
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$userIdInt, $tenantId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userUserId = $user['user_id'] ?? null;
        
        $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE owner_user_id = ? AND tenant_id = ? LIMIT 1");
        $stmt->execute([$userUserId, $tenantId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        $patientId = $patient['patient_id'] ?? null;
        
        $stmt = $pdo->prepare("SELECT patient_id FROM tbl_reviews WHERE review_id = ? AND tenant_id = ?");
        $stmt->execute([$reviewId, $tenantId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$review || $review['patient_id'] !== $patientId) {
            jsonResponse(false, 'Review not found or you do not have permission to delete it.');
        }
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM tbl_reviews WHERE review_id = ? AND tenant_id = ?");
        $stmt->execute([$reviewId, $tenantId]);
        
        jsonResponse(true, 'Review deleted successfully.');
    } catch (PDOException $e) {
        error_log('Error deleting review: ' . $e->getMessage());
        jsonResponse(false, 'Failed to delete review.');
    }
}
