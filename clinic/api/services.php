<?php
/**
 * Services API Endpoint
 * Handles CRUD operations for dental services
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
        createService();
        break;
    case 'GET':
        getServices();
        break;
    case 'PUT':
        updateService();
        break;
    case 'DELETE':
        deleteService();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Generate unique service_id
 * Format: SRV-{YEAR}-{5-digit-sequence}
 * @return string Generated service_id
 */
function generateServiceId() {
    global $pdo, $tenantId;
    
    $prefix = 'SRV';
    $year = date('Y');
    $pattern = $prefix . '-' . $year . '-%';
    
    // Find the last service_id with this prefix and year
    $stmt = $pdo->prepare("
        SELECT service_id 
        FROM services 
        WHERE service_id LIKE ?
          AND tenant_id = ?
        ORDER BY service_id DESC 
        LIMIT 1
    ");
    $stmt->execute([$pattern, $tenantId]);
    $lastServiceId = $stmt->fetchColumn();
    
    // Extract sequence number from last service_id, or start at 00001
    if ($lastServiceId) {
        $parts = explode('-', $lastServiceId);
        $sequence = intval(end($parts));
        $sequence++;
    } else {
        $sequence = 1;
    }
    
    // Format as 5-digit sequence (00001, 00002, etc.)
    $formattedSequence = str_pad($sequence, 5, '0', STR_PAD_LEFT);
    $serviceId = $prefix . '-' . $year . '-' . $formattedSequence;
    
    // Double-check uniqueness
    $stmt = $pdo->prepare("SELECT service_id FROM services WHERE service_id = ? AND tenant_id = ?");
    $stmt->execute([$serviceId, $tenantId]);
    if ($stmt->fetchColumn()) {
        $sequence++;
        $formattedSequence = str_pad($sequence, 5, '0', STR_PAD_LEFT);
        $serviceId = $prefix . '-' . $year . '-' . $formattedSequence;
    }
    
    return $serviceId;
}

/**
 * Create new service
 */
function createService() {
    global $pdo, $tenantId;
    
    // Require manager/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager or Staff access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extract and sanitize data
    $data = [
        'service_id' => generateServiceId(),
        'service_name' => sanitize($input['service_name'] ?? ''),
        'service_details' => sanitize($input['service_details'] ?? ''),
        'category' => sanitize($input['category'] ?? ''),
        'price' => isset($input['price']) ? floatval($input['price']) : 0.00,
        'status' => sanitize($input['status'] ?? 'active')
    ];
    
    // Validation
    if (empty($data['service_name'])) {
        jsonResponse(false, 'Service name is required.');
    }
    
    if (empty($data['category'])) {
        jsonResponse(false, 'Category is required.');
    }
    
    // Validate category
    $validCategories = [
        'General Dentistry',
        'Restorative Dentistry',
        'Oral Surgery',
        'Crowns and Bridges',
        'Cosmetic Dentistry',
        'Pediatric Dentistry',
        'Orthodontics',
        'Specialized and Others'
    ];
    
    if (!in_array($data['category'], $validCategories)) {
        jsonResponse(false, 'Invalid category selected.');
    }
    
    if ($data['price'] < 0) {
        jsonResponse(false, 'Price cannot be negative.');
    }
    
    if (!in_array($data['status'], ['active', 'inactive'])) {
        $data['status'] = 'active';
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO services (
                tenant_id, service_id, service_name, service_details, category, price, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $tenantId,
            $data['service_id'],
            $data['service_name'],
            $data['service_details'],
            $data['category'],
            $data['price'],
            $data['status']
        ]);
        
        $serviceDbId = $pdo->lastInsertId();
        
        // Get the created service
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$serviceDbId, $tenantId]);
        $service = $stmt->fetch();
        
        jsonResponse(true, 'Service created successfully.', ['service' => $service]);
        
    } catch (Exception $e) {
        error_log('Service creation error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to create service: ' . $e->getMessage());
    }
}

/**
 * Get services
 */
function getServices() {
    global $pdo, $tenantId;
    
    // Get query parameters
    $serviceId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $serviceIdCode = isset($_GET['service_id']) ? sanitize($_GET['service_id']) : null;
    $search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
    $category = isset($_GET['category']) ? sanitize($_GET['category']) : null;
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : null;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(10000, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    try {
        if ($serviceId || $serviceIdCode) {
            // Get single service
            if ($serviceId) {
                $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$serviceId, $tenantId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ? AND tenant_id = ?");
                $stmt->execute([$serviceIdCode, $tenantId]);
            }
            
            $service = $stmt->fetch();
            
            if (!$service) {
                jsonResponse(false, 'Service not found.');
            }
            
            jsonResponse(true, 'Service retrieved successfully.', ['service' => $service]);
        } else {
            // Get list of services
            $whereConditions = [];
            $params = [];
            $whereConditions[] = "tenant_id = ?";
            $params[] = $tenantId;
            
            if (!empty($search)) {
                $whereConditions[] = "(service_name LIKE ? OR service_details LIKE ?)";
                $searchParam = '%' . $search . '%';
                $params[] = $searchParam;
                $params[] = $searchParam;
            }
            
            if ($category) {
                $whereConditions[] = "category = ?";
                $params[] = $category;
            }
            
            if ($status) {
                $whereConditions[] = "status = ?";
                $params[] = $status;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM services $whereClause");
            $countStmt->execute($params);
            $totalItems = $countStmt->fetchColumn();
            
            // Get paginated results
            $sql = "SELECT * FROM services $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $services = $stmt->fetchAll();
            
            jsonResponse(true, 'Services retrieved successfully.', [
                'services' => $services,
                'total' => intval($totalItems),
                'page' => $page,
                'limit' => $limit
            ]);
        }
        
    } catch (Exception $e) {
        error_log('Get services error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve services.');
    }
}

/**
 * Update service
 */
function updateService() {
    global $pdo, $tenantId;
    
    // Require manager/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager or Staff access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $serviceId = isset($input['id']) ? intval($input['id']) : null;
    $serviceIdCode = isset($input['service_id']) ? sanitize($input['service_id']) : null;
    
    if (!$serviceId && !$serviceIdCode) {
        jsonResponse(false, 'Service ID is required.');
    }
    
    // Get existing service
    if ($serviceId) {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$serviceId, $tenantId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ? AND tenant_id = ?");
        $stmt->execute([$serviceIdCode, $tenantId]);
    }
    
    $existing = $stmt->fetch();
    
    if (!$existing) {
        jsonResponse(false, 'Service not found.');
    }
    
    // Extract and sanitize update data
    $data = [
        'service_name' => isset($input['service_name']) ? sanitize($input['service_name']) : $existing['service_name'],
        'service_details' => isset($input['service_details']) ? sanitize($input['service_details']) : $existing['service_details'],
        'category' => isset($input['category']) ? sanitize($input['category']) : $existing['category'],
        'price' => isset($input['price']) ? floatval($input['price']) : floatval($existing['price']),
        'status' => isset($input['status']) ? sanitize($input['status']) : $existing['status']
    ];
    
    // Validation
    if (empty($data['service_name'])) {
        jsonResponse(false, 'Service name is required.');
    }
    
    if (empty($data['category'])) {
        jsonResponse(false, 'Category is required.');
    }
    
    // Validate category
    $validCategories = [
        'General Dentistry',
        'Restorative Dentistry',
        'Oral Surgery',
        'Crowns and Bridges',
        'Cosmetic Dentistry',
        'Pediatric Dentistry',
        'Orthodontics',
        'Specialized and Others'
    ];
    
    if (!in_array($data['category'], $validCategories)) {
        jsonResponse(false, 'Invalid category selected.');
    }
    
    if ($data['price'] < 0) {
        jsonResponse(false, 'Price cannot be negative.');
    }
    
    if (!in_array($data['status'], ['active', 'inactive'])) {
        $data['status'] = $existing['status'];
    }
    
    try {
        $updateId = $serviceId ?: $existing['id'];
        
        $stmt = $pdo->prepare("
            UPDATE services 
            SET service_name = ?, 
                service_details = ?, 
                category = ?, 
                price = ?, 
                status = ?,
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([
            $data['service_name'],
            $data['service_details'],
            $data['category'],
            $data['price'],
            $data['status'],
            $updateId,
            $tenantId
        ]);
        
        // Get updated service
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$updateId, $tenantId]);
        $service = $stmt->fetch();
        
        jsonResponse(true, 'Service updated successfully.', ['service' => $service]);
        
    } catch (Exception $e) {
        error_log('Service update error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update service: ' . $e->getMessage());
    }
}

/**
 * Delete service (soft delete by setting status to inactive)
 */
function deleteService() {
    global $pdo, $tenantId;
    
    // Require manager/staff authentication
    if (!isLoggedIn('manager') && !isLoggedIn('staff')) {
        jsonResponse(false, 'Unauthorized. Manager or Staff access required.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $serviceId = isset($input['id']) ? intval($input['id']) : null;
    $serviceIdCode = isset($input['service_id']) ? sanitize($input['service_id']) : null;
    
    if (!$serviceId && !$serviceIdCode) {
        jsonResponse(false, 'Service ID is required.');
    }
    
    // Get existing service
    if ($serviceId) {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$serviceId, $tenantId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM services WHERE service_id = ? AND tenant_id = ?");
        $stmt->execute([$serviceIdCode, $tenantId]);
    }
    
    $existing = $stmt->fetch();
    
    if (!$existing) {
        jsonResponse(false, 'Service not found.');
    }
    
    try {
        $updateId = $serviceId ?: $existing['id'];
        
        // Soft delete by setting status to inactive
        $stmt = $pdo->prepare("
            UPDATE services 
            SET status = 'inactive',
                updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([$updateId, $tenantId]);
        
        jsonResponse(true, 'Service deactivated successfully.');
        
    } catch (Exception $e) {
        error_log('Service delete error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to delete service: ' . $e->getMessage());
    }
}
