<?php
/**
 * Reports API Endpoint
 * Handles report generation and analytics
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
        getReport();
        break;
    case 'POST':
        generateReport();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Get report
 */
function getReport() {
    global $pdo, $tenantId;
    
    // Require manager authentication
    if (!isLoggedIn('manager')) {
        jsonResponse(false, 'Unauthorized. Manager access required.');
    }
    
    $reportType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
    $dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : null;
    
    if (empty($reportType)) {
        jsonResponse(false, 'Report type is required.');
    }
    
    try {
        switch ($reportType) {
            case 'appointments':
                getAppointmentsReport($dateFrom, $dateTo);
                break;
            case 'payments':
                getPaymentsReport($dateFrom, $dateTo);
                break;
            case 'patients':
                getPatientsReport();
                break;
            case 'dashboard':
                getDashboardStats();
                break;
            default:
                jsonResponse(false, 'Invalid report type.');
        }
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to generate report.');
    }
}

/**
 * Get appointments report
 */
function getAppointmentsReport($dateFrom, $dateTo) {
    global $pdo, $tenantId;
    
    $sql = "
        SELECT 
            DATE(a.appointment_date) as date,
            a.status,
            COUNT(*) as count
        FROM appointments a
        WHERE a.tenant_id = ?
    ";
    
    $params = [$tenantId];
    
    if ($dateFrom) {
        $sql .= " AND DATE(a.appointment_date) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $sql .= " AND DATE(a.appointment_date) <= ?";
        $params[] = $dateTo;
    }
    
    $sql .= " GROUP BY DATE(a.appointment_date), a.status ORDER BY date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Get summary
    $summarySql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments
        WHERE tenant_id = ?
    ";
    
    $summaryParams = [$tenantId];
    if ($dateFrom) {
        $summarySql .= " AND DATE(appointment_date) >= ?";
        $summaryParams[] = $dateFrom;
    }
    if ($dateTo) {
        $summarySql .= " AND DATE(appointment_date) <= ?";
        $summaryParams[] = $dateTo;
    }
    
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryParams);
    $summary = $summaryStmt->fetch();
    
    jsonResponse(true, 'Appointments report generated successfully.', [
        'data' => $data,
        'summary' => $summary
    ]);
}

/**
 * Get payments report
 */
function getPaymentsReport($dateFrom, $dateTo) {
    global $pdo, $tenantId;
    
    $sql = "
        SELECT 
            DATE(p.payment_date) as date,
            p.payment_method,
            COUNT(*) as count,
            SUM(p.amount) as total_amount
        FROM payments p
        WHERE p.tenant_id = ?
    ";
    
    $params = [$tenantId];
    
    if ($dateFrom) {
        $sql .= " AND DATE(p.payment_date) >= ?";
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $sql .= " AND DATE(p.payment_date) <= ?";
        $params[] = $dateTo;
    }
    
    $sql .= " GROUP BY DATE(p.payment_date), p.payment_method ORDER BY date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Get summary
    $summarySql = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(amount) as total_revenue,
            AVG(amount) as average_amount,
            SUM(CASE WHEN payment_method = 'cash' THEN amount ELSE 0 END) as cash_total,
            SUM(CASE WHEN payment_method = 'credit_card' THEN amount ELSE 0 END) as credit_card_total,
            SUM(CASE WHEN payment_method = 'gcash' THEN amount ELSE 0 END) as gcash_total
        FROM payments
        WHERE status = 'paid' AND tenant_id = ?
    ";
    
    $summaryParams = [$tenantId];
    if ($dateFrom) {
        $summarySql .= " AND DATE(payment_date) >= ?";
        $summaryParams[] = $dateFrom;
    }
    if ($dateTo) {
        $summarySql .= " AND DATE(payment_date) <= ?";
        $summaryParams[] = $dateTo;
    }
    
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($summaryParams);
    $summary = $summaryStmt->fetch();
    
    jsonResponse(true, 'Payments report generated successfully.', [
        'data' => $data,
        'summary' => $summary
    ]);
}

/**
 * Get patients report
 */
function getPatientsReport() {
    global $pdo, $tenantId;
    
    $sql = "
        SELECT 
            COUNT(*) as total_patients,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            COUNT(DISTINCT DATE(created_at)) as new_patients_days,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_last_30_days,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_last_7_days
        FROM patients
        WHERE tenant_id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $summary = $stmt->fetch();
    
    // Get patients by month
    $monthlySql = "
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM patients
        WHERE tenant_id = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ";
    
    $monthlyStmt = $pdo->prepare($monthlySql);
    $monthlyStmt->execute([$tenantId]);
    $monthly = $monthlyStmt->fetchAll();
    
    jsonResponse(true, 'Patients report generated successfully.', [
        'summary' => $summary,
        'monthly' => $monthly
    ]);
}

/**
 * Get dashboard statistics
 */
function getDashboardStats() {
    global $pdo, $tenantId;
    
    // Today's appointments
    $todaySql = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM appointments
        WHERE tenant_id = ?
          AND DATE(appointment_date) = CURDATE()
    ";
    $todayStmt = $pdo->prepare($todaySql);
    $todayStmt->execute([$tenantId]);
    $today = $todayStmt->fetch();
    
    // This month's revenue
    $revenueSql = "
        SELECT 
            SUM(amount) as total,
            COUNT(*) as transactions
        FROM payments
        WHERE status = 'paid' 
          AND tenant_id = ?
          AND MONTH(payment_date) = MONTH(CURDATE())
          AND YEAR(payment_date) = YEAR(CURDATE())
    ";
    $revenueStmt = $pdo->prepare($revenueSql);
    $revenueStmt->execute([$tenantId]);
    $revenue = $revenueStmt->fetch();
    
    // Total patients
    $patientsSql = "
        SELECT COUNT(*) as total
        FROM patients
        WHERE status = 'active' AND tenant_id = ?
    ";
    $patientsStmt = $pdo->prepare($patientsSql);
    $patientsStmt->execute([$tenantId]);
    $patients = $patientsStmt->fetch();
    
    // Pending appointments
    $pendingSql = "
        SELECT COUNT(*) as total
        FROM appointments
        WHERE status = 'pending' AND tenant_id = ?
    ";
    $pendingStmt = $pdo->prepare($pendingSql);
    $pendingStmt->execute([$tenantId]);
    $pending = $pendingStmt->fetch();
    
    jsonResponse(true, 'Dashboard statistics retrieved successfully.', [
        'today_appointments' => $today,
        'monthly_revenue' => $revenue,
        'total_patients' => $patients,
        'pending_appointments' => $pending
    ]);
}

/**
 * Generate and save report
 */
function generateReport() {
    global $pdo;
    
    // Require manager authentication
    if (!isLoggedIn('manager')) {
        jsonResponse(false, 'Unauthorized. Manager access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $reportType = sanitize($input['report_type'] ?? '');
    $title = sanitize($input['title'] ?? '');
    $description = sanitize($input['description'] ?? '');
    $data = json_encode($input['data'] ?? []);
    
    if (empty($reportType) || empty($title)) {
        jsonResponse(false, 'Report type and title are required.');
    }
    
    try {
        $createdBy = getCurrentUserId();
        
        $stmt = $pdo->prepare("
            INSERT INTO reports (
                report_type, title, description, data, generated_by, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $reportType,
            $title,
            $description ?: null,
            $data,
            $createdBy
        ]);
        
        $reportId = $pdo->lastInsertId();
        
        jsonResponse(true, 'Report generated and saved successfully.', ['report_id' => $reportId]);
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to generate report.');
    }
}

