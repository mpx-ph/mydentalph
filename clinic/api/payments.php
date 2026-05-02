<?php
/**
 * Payments API Endpoint
 * Handles CRUD operations for payments
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/availability.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();
$tenantId = requireClinicTenantId();

// Route based on method
switch ($method) {
    case 'POST':
        createPayment();
        break;
    case 'GET':
        getPayments();
        break;
    case 'PUT':
        updatePayment();
        break;
    case 'DELETE':
        deletePayment();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Generate unique payment ID
 * Format: PY-YYYY-XXXXXX where XXXXXX is a 6-digit sequential number
 * @return string Payment ID
 */
function generatePaymentId() {
    global $pdo, $tenantId;
    
    $prefix = 'PY';
    $year = date('Y');
    $pattern = $prefix . '-' . $year . '-%';
    
    // Find the last payment_id with this prefix and year
    $stmt = $pdo->prepare("
        SELECT payment_id 
        FROM payments 
        WHERE payment_id LIKE ? AND tenant_id = ?
        ORDER BY payment_id DESC 
        LIMIT 1
    ");
    $stmt->execute([$pattern, $tenantId]);
    $lastPaymentId = $stmt->fetchColumn();
    
    // Extract sequence number from last payment_id, or start at 000001
    if ($lastPaymentId) {
        $parts = explode('-', $lastPaymentId);
        if (count($parts) === 3) {
            $sequence = intval($parts[2]);
            $sequence++;
        } else {
            $sequence = 1;
        }
    } else {
        $sequence = 1;
    }
    
    // Format as 6-digit sequence (000001, 000002, etc.)
    $formattedSequence = str_pad($sequence, 6, '0', STR_PAD_LEFT);
    $paymentId = $prefix . '-' . $year . '-' . $formattedSequence;
    
    return $paymentId;
}

/**
 * Create new payment
 */
function createPayment() {
    global $pdo, $tenantId;
    
    // Require authentication (admin or client)
    if (!isLoggedIn()) {
        jsonResponse(false, 'Unauthorized. Please log in.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extract patient_id (varchar) from input
    $patientId = sanitize($input['patient_id'] ?? '');
    
    // Extract and sanitize data
    $data = [
        'patient_id' => $patientId,
        'booking_id' => sanitize($input['booking_id'] ?? ''),
        'amount' => isset($input['amount']) ? floatval($input['amount']) : 0,
        'payment_method' => sanitize($input['payment_method'] ?? 'cash'),
        'payment_date' => sanitize($input['payment_date'] ?? date('Y-m-d H:i:s')),
        'reference_number' => sanitize($input['reference_number'] ?? ''),
        'notes' => sanitize($input['notes'] ?? ''),
        'status' => sanitize($input['status'] ?? 'completed')
    ];
    
    // Validation
    if (empty($data['patient_id'])) {
        jsonResponse(false, 'Patient ID is required.');
    }
    
    // If user is a client, verify patient belongs to them
    $userType = $_SESSION['user_type'] ?? 'client';
    if ($userType === 'client') {
        $userId = getCurrentUserId();
        if ($userId) {
            $userStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            if ($user) {
                $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ? AND owner_user_id = ?");
                $stmt->execute([$data['patient_id'], $user['user_id']]);
                if (!$stmt->fetch()) {
                    jsonResponse(false, 'Invalid patient selected.');
                }
            }
        }
    }
    
    if ($data['amount'] <= 0) {
        jsonResponse(false, 'Payment amount must be greater than zero.');
    }
    
    $validMethods = ['cash', 'credit_card', 'debit_card', 'gcash', 'paymaya', 'bank_transfer', 'check', 'bank'];
    if (!in_array($data['payment_method'], $validMethods)) {
        jsonResponse(false, 'Invalid payment method.');
    }
    
    // Verify booking exists if provided (same tenant)
    if (!empty($data['booking_id'])) {
        $stmt = $pdo->prepare("SELECT booking_id FROM appointments WHERE booking_id = ? AND tenant_id = ?");
        $stmt->execute([$data['booking_id'], $tenantId]);
        if (!$stmt->fetch()) {
            jsonResponse(false, 'Booking not found.');
        }
    }
    
    // Verify patient exists
    $stmt = $pdo->prepare("SELECT patient_id FROM patients WHERE patient_id = ?");
    $stmt->execute([$data['patient_id']]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'Patient not found.');
    }
    
    $availabilitySafetyWarning = null;
    try {
        $pdo->beginTransaction();
        
        $createdBy = getCurrentUserId();
        
        // Get user_id (varchar) for created_by
        $createdByUserId = null;
        if ($createdBy) {
            $userStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
            $userStmt->execute([$createdBy]);
            $user = $userStmt->fetch();
            if ($user) {
                $createdByUserId = $user['user_id'];
            }
        }
        
        // Check if booking has installments (for long-term treatments with downpayment)
        $installmentNumber = null;
        $installmentId = null;
        $bookingTreatmentId = '';
        $treatmentRemainingBalance = null;
        if (!empty($data['booking_id'])) {
            $bookingStmt = $pdo->prepare("
                SELECT booking_id, COALESCE(treatment_id, '') AS treatment_id
                FROM appointments
                WHERE booking_id = ?
                  AND tenant_id = ?
                LIMIT 1
            ");
            $bookingStmt->execute([$data['booking_id'], $tenantId]);
            $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$bookingRow) {
                $pdo->rollBack();
                jsonResponse(false, 'Booking not found.');
            }
            $bookingTreatmentId = trim((string) ($bookingRow['treatment_id'] ?? ''));

            // Read-only safety check: if provider schedule is now incompatible with the booked slot,
            // keep payment flow working but return a warning for downstream UI/ops visibility.
            try {
                $apptProviderStmt = $pdo->prepare("
                    SELECT a.appointment_date, a.appointment_time, d.user_id
                    FROM appointments a
                    LEFT JOIN tbl_dentists d
                      ON d.tenant_id = a.tenant_id
                     AND d.dentist_id = a.dentist_id
                    WHERE a.tenant_id = ?
                      AND a.booking_id = ?
                    LIMIT 1
                ");
                $apptProviderStmt->execute([$tenantId, $data['booking_id']]);
                $apptProviderRow = $apptProviderStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($apptProviderRow) {
                    $dentistUserId = trim((string) ($apptProviderRow['user_id'] ?? ''));
                    $apptDate = trim((string) ($apptProviderRow['appointment_date'] ?? ''));
                    $apptTime = trim((string) ($apptProviderRow['appointment_time'] ?? ''));
                    if ($dentistUserId !== '' && $apptDate !== '' && $apptTime !== '') {
                        $slotStart = $apptDate . ' ' . $apptTime;
                        $slotEnd = (new DateTime($slotStart))->modify('+1 hour')->format('Y-m-d H:i:s');
                        if (!isUserAvailable($dentistUserId, $slotStart, $slotEnd)) {
                            $availabilitySafetyWarning = 'Assigned staff/dentist is currently unavailable for this booking timeslot.';
                        }
                    }
                }
            } catch (Throwable $availabilityProbeError) {
                // Never interrupt payment flow for optional safety read.
                $availabilitySafetyWarning = null;
            }

            if ($bookingTreatmentId !== '') {
                $treatStmt = $pdo->prepare("
                    SELECT COALESCE(remaining_balance, 0) AS remaining_balance
                    FROM tbl_treatments
                    WHERE tenant_id = ?
                      AND treatment_id = ?
                    LIMIT 1
                ");
                $treatStmt->execute([$tenantId, $bookingTreatmentId]);
                $treatRow = $treatStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($treatRow) {
                    $treatmentRemainingBalance = max(0.0, (float) ($treatRow['remaining_balance'] ?? 0));
                }
            }

            // Check if there are installments for this booking
            $stmt = $pdo->prepare("
                SELECT id, installment_number, amount_due, status 
                FROM installments 
                WHERE booking_id = ? 
                AND tenant_id = ?
                AND status IN ('pending', 'book_visit')
                ORDER BY installment_number ASC 
                LIMIT 1
            ");
            $stmt->execute([$data['booking_id'], $tenantId]);
            $installment = $stmt->fetch();
            
            if ($installment) {
                // This is a payment for an installment-based booking
                $installmentNumber = intval($installment['installment_number']);
                $installmentId = intval($installment['id']);
                
                // Verify the payment amount matches the installment amount (with small tolerance for rounding)
                $installmentAmount = floatval($installment['amount_due']);
                $paymentAmount = $data['amount'];
                $tolerance = 0.01; // Allow 1 cent difference for rounding
                
                if (abs($paymentAmount - $installmentAmount) > $tolerance) {
                    $pdo->rollBack();
                    jsonResponse(false, 'Payment amount does not match the installment amount due (₱' . number_format($installmentAmount, 2) . ').');
                }
            }

            if ($treatmentRemainingBalance !== null) {
                $paymentAmount = (float) $data['amount'];
                if ($paymentAmount - $treatmentRemainingBalance > 0.01) {
                    $pdo->rollBack();
                    jsonResponse(false, 'Payment amount exceeds the remaining balance for this treatment (₱' . number_format($treatmentRemainingBalance, 2) . ').');
                }
            }
        }
        
        // Generate unique payment_id
        $paymentId = generatePaymentId();
        
        // Retry loop for potential duplicates (shouldn't happen with new format, but safety check)
        $maxRetries = 5;
        $retryCount = 0;
        $inserted = false;
        
        while ($retryCount < $maxRetries && !$inserted) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO payments (
                        tenant_id,
                        payment_id, patient_id, booking_id, installment_number, amount, payment_method, payment_date,
                        reference_number, notes, status, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $tenantId,
                    $paymentId,
                    $data['patient_id'],
                    $data['booking_id'] ?: null,
                    $installmentNumber,
                    $data['amount'],
                    $data['payment_method'],
                    $data['payment_date'],
                    $data['reference_number'] ?: null,
                    $data['notes'] ?: null,
                    $data['status'],
                    $createdByUserId
                ]);
                
                // Success - mark as inserted
                $inserted = true;
                
            } catch (PDOException $e) {
                // Check if it's a duplicate key error
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    $retryCount++;
                    if ($retryCount < $maxRetries) {
                        // Regenerate payment_id and retry
                        $paymentId = generatePaymentId();
                        continue;
                    } else {
                        throw new Exception('Failed to generate unique payment ID after multiple attempts.');
                    }
                } else {
                    // Different error - rethrow
                    throw $e;
                }
            }
        }
        
        if (!$inserted) {
            throw new Exception('Failed to insert payment after multiple attempts.');
        }
        
        // Update installment record if this is an installment payment
        if ($installmentId && $installmentNumber) {
            $stmt = $pdo->prepare("
                UPDATE installments 
                SET status = 'paid', 
                    payment_id = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$paymentId, $installmentId, $tenantId]);
        }

        // Keep payment lifecycle independent from appointment lifecycle.
        // Recording a payment must not mutate appointment status.
        
        $pdo->commit();
        
        jsonResponse(true, 'Payment recorded successfully.', [
            'payment_id' => $paymentId,
            'installment_number' => $installmentNumber,
            'availability_warning' => $availabilitySafetyWarning
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Payment creation error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to record payment. Please try again.');
    }
}

/**
 * Get payments
 */
function getPayments() {
    global $pdo;
    
    // Require authentication (admin or client)
    // Check if user is logged in as either admin or client
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        jsonResponse(false, 'Unauthorized. Please log in.');
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        jsonResponse(false, 'Session expired. Please log in again.');
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Verify user type is valid (manager, staff, doctor, or client)
    if (!in_array($_SESSION['user_type'], ['manager', 'staff', 'doctor', 'client'])) {
        jsonResponse(false, 'Unauthorized. Invalid user type.');
    }
    
    $userType = $_SESSION['user_type'] ?? 'client';
    
    // Get query parameters
    $paymentId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $patientId = isset($_GET['patient_id']) ? sanitize($_GET['patient_id']) : null;
    
    $bookingId = isset($_GET['booking_id']) ? sanitize($_GET['booking_id']) : null;
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : null;
    $dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : null;
    $stats = isset($_GET['stats']) ? filter_var($_GET['stats'], FILTER_VALIDATE_BOOLEAN) : false;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    try {
        // Return statistics if requested
        if ($stats) {
            return getPaymentStatistics();
        }
        
        if ($paymentId) {
            // Get single payment
            // first_name, last_name, contact_number are now in patients table
            $stmt = $pdo->prepare("
                SELECT p.id, p.payment_id, p.patient_id, p.booking_id, p.amount, p.payment_method, p.payment_date,
                       p.reference_number, p.notes, p.status, p.created_by, p.created_at, p.updated_at,
                       pt.first_name as patient_first_name, pt.last_name as patient_last_name,
                       pt.contact_number as patient_mobile,
                       pt.patient_id as patient_display_id,
                       u_patient.email as patient_email,
                       a.appointment_date, a.appointment_time, a.service_type,
                       CONCAT(pt_creator.first_name, ' ', pt_creator.last_name) as created_by_name
                FROM payments p
                LEFT JOIN patients pt ON p.patient_id = pt.patient_id
                LEFT JOIN tbl_users u_patient ON pt.linked_user_id = u_patient.user_id AND pt.owner_user_id = u_patient.user_id
                LEFT JOIN appointments a ON p.booking_id = a.booking_id
                LEFT JOIN patients pt_creator ON (pt_creator.linked_user_id = p.created_by AND pt_creator.owner_user_id = p.created_by)
                WHERE p.id = ?
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();
            
            if (!$payment) {
                jsonResponse(false, 'Payment not found.');
            }
            
            jsonResponse(true, 'Payment retrieved successfully.', ['payment' => $payment]);
        } else {
            // Get list of payments
            // first_name, last_name, contact_number are now in patients table
            $sql = "
                SELECT p.id, p.payment_id, p.patient_id, p.booking_id, p.amount, p.payment_method, p.payment_date,
                       p.reference_number, p.notes, p.status, p.created_by, p.created_at, p.updated_at,
                       pt.first_name as patient_first_name, pt.last_name as patient_last_name,
                       pt.contact_number as patient_mobile,
                       pt.patient_id as patient_display_id,
                       u_patient.email as patient_email,
                       a.appointment_date, a.service_type,
                       CONCAT(pt_creator.first_name, ' ', pt_creator.last_name) as created_by_name
                FROM payments p
                LEFT JOIN patients pt ON p.patient_id = pt.patient_id
                LEFT JOIN tbl_users u_patient ON pt.linked_user_id = u_patient.user_id AND pt.owner_user_id = u_patient.user_id
                LEFT JOIN appointments a ON p.booking_id = a.booking_id
                LEFT JOIN patients pt_creator ON (pt_creator.linked_user_id = p.created_by AND pt_creator.owner_user_id = p.created_by)
                WHERE 1=1
            ";
            
            $params = [];
            
            // For clients, filter by their owned patients only
            if ($userType === 'client') {
                $userId = getCurrentUserId();
                if ($userId) {
                    $userStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
                    $userStmt->execute([$userId]);
                    $user = $userStmt->fetch();
                    if ($user) {
                        $sql .= " AND pt.owner_user_id = ?";
                        $params[] = $user['user_id'];
                    }
                }
            }
            
            if ($patientId) {
                $sql .= " AND p.patient_id = ?";
                $params[] = $patientId;
            }
            
            if ($bookingId) {
                $sql .= " AND p.booking_id = ?";
                $params[] = $bookingId;
            }
            
            if ($status) {
                $sql .= " AND p.status = ?";
                $params[] = $status;
            }
            
            if ($dateFrom) {
                $sql .= " AND DATE(p.payment_date) >= ?";
                $params[] = $dateFrom;
            }
            
            if ($dateTo) {
                $sql .= " AND DATE(p.payment_date) <= ?";
                $params[] = $dateTo;
            }
            
            // Build simplified count query (only from payments table with necessary JOINs for filtering)
            $countSql = "SELECT COUNT(*) as total FROM payments p";
            $countParams = [];
            
            // Apply WHERE conditions for count
            if ($userType === 'client') {
                $userId = getCurrentUserId();
                if ($userId) {
                    $userStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
                    $userStmt->execute([$userId]);
                    $user = $userStmt->fetch();
                    if ($user) {
                        $countSql .= " LEFT JOIN patients pt ON p.patient_id = pt.patient_id";
                        $countSql .= " WHERE pt.owner_user_id = ?";
                        $countParams[] = $user['user_id'];
                    } else {
                        $countSql .= " WHERE 1=0"; // No access
                    }
                } else {
                    $countSql .= " WHERE 1=0"; // No access
                }
            } else {
                $countSql .= " WHERE 1=1";
            }
            
            if ($patientId) {
                $countSql .= " AND p.patient_id = ?";
                $countParams[] = $patientId;
            }
            
            if ($bookingId) {
                $countSql .= " AND p.booking_id = ?";
                $countParams[] = $bookingId;
            }
            
            if ($status) {
                $countSql .= " AND p.status = ?";
                $countParams[] = $status;
            }
            
            if ($dateFrom) {
                $countSql .= " AND DATE(p.payment_date) >= ?";
                $countParams[] = $dateFrom;
            }
            
            if ($dateTo) {
                $countSql .= " AND DATE(p.payment_date) <= ?";
                $countParams[] = $dateTo;
            }
            
            // Get total count
            try {
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($countParams);
                $total = $countStmt->fetch()['total'];
            } catch (Exception $e) {
                error_log('Count query error: ' . $e->getMessage());
                error_log('Count SQL: ' . $countSql);
                error_log('Count params: ' . print_r($countParams, true));
                throw $e; // Re-throw to be caught by outer catch
            }
            
            // Add ORDER BY and pagination to main query
            $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";
            
            // Calculate totals (before pagination)
            $totalSql = "
                SELECT SUM(p.amount) as total_amount, COUNT(*) as count
                FROM payments p
                LEFT JOIN patients pt ON p.patient_id = pt.patient_id
                LEFT JOIN tbl_users u_patient ON pt.linked_user_id = u_patient.user_id AND pt.owner_user_id = u_patient.user_id
                LEFT JOIN appointments a ON p.booking_id = a.booking_id
                LEFT JOIN tbl_users u ON p.created_by = u.user_id
                WHERE 1=1
            ";
            
            // Apply same WHERE conditions
            $totalParams = [];
            if ($userType === 'client') {
                $userId = getCurrentUserId();
                if ($userId) {
                    $userStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
                    $userStmt->execute([$userId]);
                    $user = $userStmt->fetch();
                    if ($user) {
                        $totalSql .= " AND pt.owner_user_id = ?";
                        $totalParams[] = $user['user_id'];
                    }
                }
            }
            
            if ($patientId) {
                $totalSql .= " AND p.patient_id = ?";
                $totalParams[] = $patientId;
            }
            
            if ($bookingId) {
                $totalSql .= " AND p.booking_id = ?";
                $totalParams[] = $bookingId;
            }
            
            if ($status) {
                $totalSql .= " AND p.status = ?";
                $totalParams[] = $status;
            }
            
            if ($dateFrom) {
                $totalSql .= " AND DATE(p.payment_date) >= ?";
                $totalParams[] = $dateFrom;
            }
            
            if ($dateTo) {
                $totalSql .= " AND DATE(p.payment_date) <= ?";
                $totalParams[] = $dateTo;
            }
            
            $totalStmt = $pdo->prepare($totalSql);
            $totalStmt->execute($totalParams);
            $totals = $totalStmt->fetch();
            
            // Get payments with pagination
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $payments = $stmt->fetchAll();
            
            jsonResponse(true, 'Payments retrieved successfully.', [
                'payments' => $payments,
                'totals' => [
                    'total_amount' => floatval($totals['total_amount'] ?? 0),
                    'count' => intval($totals['count'] ?? 0)
                ],
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Payments API Error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        jsonResponse(false, 'Failed to retrieve payments: ' . $e->getMessage());
    }
}

/**
 * Update payment
 */
function updatePayment() {
    global $pdo;
    
    // Require manager/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager or Staff access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $paymentId = intval($input['id'] ?? 0);
    
    if (!$paymentId) {
        jsonResponse(false, 'Payment ID is required.');
    }
    
    // Check if payment exists
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE id = ?");
    $stmt->execute([$paymentId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'Payment not found.');
    }
    
    // Extract updateable fields
    $updates = [];
    $params = [];
    
    $fields = ['amount', 'payment_method', 'payment_date', 'reference_number', 'notes', 'status'];
    
    foreach ($fields as $field) {
        if (isset($input[$field])) {
            if ($field === 'amount') {
                $updates[] = "{$field} = ?";
                $params[] = floatval($input[$field]);
            } else {
                $updates[] = "{$field} = ?";
                $params[] = sanitize($input[$field]);
            }
        }
    }
    
    if (empty($updates)) {
        jsonResponse(false, 'No fields to update.');
    }
    
    // Validate amount if being updated
    if (isset($input['amount']) && floatval($input['amount']) <= 0) {
        jsonResponse(false, 'Payment amount must be greater than zero.');
    }
    
    // Validate payment method if being updated
    if (isset($input['payment_method'])) {
        $validMethods = ['cash', 'credit_card', 'debit_card', 'gcash', 'paymaya', 'bank_transfer', 'check'];
        if (!in_array($input['payment_method'], $validMethods)) {
            jsonResponse(false, 'Invalid payment method.');
        }
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $paymentId;
    
    try {
        $sql = "UPDATE payments SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        jsonResponse(true, 'Payment updated successfully.');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to update payment.');
    }
}

/**
 * Delete payment
 */
function deletePayment() {
    global $pdo;
    
    // Require manager/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager or Staff access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $paymentId = intval($input['id'] ?? 0);
    
    if (!$paymentId) {
        jsonResponse(false, 'Payment ID is required.');
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        
        jsonResponse(true, 'Payment deleted successfully.');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to delete payment.');
    }
}

/**
 * Get payment statistics
 * Returns: Total Revenue, Today's Revenue, Total Payments Count
 */
function getPaymentStatistics() {
    global $pdo;
    
    // Require authentication (admin or client)
    // Check if user is logged in as either admin or client
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        jsonResponse(false, 'Unauthorized. Please log in.');
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        jsonResponse(false, 'Session expired. Please log in again.');
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Verify user type is valid (manager, staff, doctor, or client)
    if (!in_array($_SESSION['user_type'], ['manager', 'staff', 'doctor', 'client'])) {
        jsonResponse(false, 'Unauthorized. Invalid user type.');
    }
    
    try {
        // Get today's date in the server timezone (Asia/Manila)
        $today = date('Y-m-d');
        
        // Base SQL for filtering (considering user type)
        $userType = $_SESSION['user_type'] ?? 'client';
        $baseWhere = "WHERE 1=1";
        $params = [];
        
        // For clients, filter by their owned patients only
        if ($userType === 'client') {
            $userId = getCurrentUserId();
            if ($userId) {
                $userStmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch();
                if ($user) {
                    $baseWhere .= " AND pt.owner_user_id = ?";
                    $params[] = $user['user_id'];
                }
            }
        }
        
        // Total Revenue (sum of all payments)
        $totalRevenueSql = "
            SELECT COALESCE(SUM(p.amount), 0) as total_revenue
            FROM payments p
            LEFT JOIN patients pt ON p.patient_id = pt.patient_id
            {$baseWhere}
        ";
        $stmt = $pdo->prepare($totalRevenueSql);
        $stmt->execute($params);
        $totalRevenue = floatval($stmt->fetch()['total_revenue']);
        
        // Today's Revenue (sum of payments made today)
        $todayRevenueSql = "
            SELECT COALESCE(SUM(p.amount), 0) as today_revenue
            FROM payments p
            LEFT JOIN patients pt ON p.patient_id = pt.patient_id
            {$baseWhere} AND DATE(p.payment_date) = ?
        ";
        $todayParams = array_merge($params, [$today]);
        $stmt = $pdo->prepare($todayRevenueSql);
        $stmt->execute($todayParams);
        $todayRevenue = floatval($stmt->fetch()['today_revenue']);
        
        // Total Payments Count
        $totalPaymentsSql = "
            SELECT COUNT(*) as total_payments
            FROM payments p
            LEFT JOIN patients pt ON p.patient_id = pt.patient_id
            {$baseWhere}
        ";
        $stmt = $pdo->prepare($totalPaymentsSql);
        $stmt->execute($params);
        $totalPayments = intval($stmt->fetch()['total_payments']);
        
        jsonResponse(true, 'Statistics retrieved successfully.', [
            'total_revenue' => $totalRevenue,
            'today_revenue' => $todayRevenue,
            'total_payments' => $totalPayments
        ]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to retrieve statistics.');
    }
}

