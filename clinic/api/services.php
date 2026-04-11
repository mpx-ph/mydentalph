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

/**
 * @return array{regular_downpayment_percentage: float, long_term_min_downpayment: float}
 */
function loadTenantPaymentSettings(PDO $pdo, string $tenantId): array {
    static $cache = [];
    if (isset($cache[$tenantId])) {
        return $cache[$tenantId];
    }
    $stmt = $pdo->prepare('SELECT regular_downpayment_percentage, long_term_min_downpayment FROM tbl_payment_settings WHERE tenant_id = ? LIMIT 1');
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $cache[$tenantId] = ['regular_downpayment_percentage' => 20.0, 'long_term_min_downpayment' => 500.0];
    } else {
        $cache[$tenantId] = [
            'regular_downpayment_percentage' => (float) $row['regular_downpayment_percentage'],
            'long_term_min_downpayment' => (float) $row['long_term_min_downpayment'],
        ];
    }
    return $cache[$tenantId];
}

function effectiveRegularDownpaymentPercent(array $ps, ?float $stored): float {
    if ($stored !== null) {
        return round(max(0.0, min(100.0, $stored)), 4);
    }
    return round(max(0.0, min(100.0, $ps['regular_downpayment_percentage'])), 4);
}

/**
 * Stored installment downpayment null => clinic long-term minimum from Payment Settings.
 */
function effectiveInstallmentDownpaymentAmount(array $ps, float $price, $storedDown): float {
    $base = ($storedDown !== null && $storedDown !== '')
        ? (float) $storedDown
        : (float) $ps['long_term_min_downpayment'];
    $base = max(0.0, $base);
    if ($price > 0 && $base > $price) {
        return round($price, 2);
    }
    return round($base, 2);
}

/**
 * Adds effective_* and uses_global_* keys for API consumers (staff UI, billing).
 *
 * @param array<string,mixed>|false $service
 * @return array<string,mixed>|false
 */
function enrichServiceRow(PDO $pdo, string $tenantId, $service) {
    if (!$service || !is_array($service)) {
        return $service;
    }
    $ps = loadTenantPaymentSettings($pdo, $tenantId);
    $price = isset($service['price']) ? (float) $service['price'] : 0.0;
    $storedPct = $service['downpayment_percentage'] ?? null;
    $storedPctFloat = ($storedPct !== null && $storedPct !== '') ? (float) $storedPct : null;
    $enableInst = !empty($service['enable_installment']);
    $storedInst = $service['installment_downpayment'] ?? null;
    $storedInstFloat = ($storedInst !== null && $storedInst !== '') ? (float) $storedInst : null;

    $effectivePct = effectiveRegularDownpaymentPercent($ps, $storedPctFloat);
    $service['uses_global_regular_downpayment'] = !$enableInst && $storedPctFloat === null;
    $service['uses_global_installment_downpayment'] = $enableInst && $storedInstFloat === null;
    $service['effective_downpayment_percentage'] = $effectivePct;
    $service['effective_regular_downpayment_amount'] = round($price * ($effectivePct / 100.0), 2);
    if ($enableInst) {
        $effInst = effectiveInstallmentDownpaymentAmount($ps, $price, $storedInstFloat !== null ? $storedInstFloat : null);
        $service['effective_installment_downpayment'] = $effInst;
        $months = isset($service['installment_duration_months']) ? (int) $service['installment_duration_months'] : 0;
        $remaining = max(0.0, $price - $effInst);
        $service['effective_installment_monthly'] = ($months >= 1) ? round($remaining / $months, 2) : null;
    } else {
        $service['effective_installment_downpayment'] = null;
        $service['effective_installment_monthly'] = null;
    }
    return $service;
}

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
        FROM tbl_services 
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
    $stmt = $pdo->prepare("SELECT service_id FROM tbl_services WHERE service_id = ? AND tenant_id = ?");
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

    $useCustom = !empty($input['use_custom_payment']);
    $downPctRaw = $input['downpayment_percentage'] ?? null;
    $downpaymentPct = null;
    if ($downPctRaw !== null && $downPctRaw !== '') {
        $downpaymentPct = floatval($downPctRaw);
    }

    $enableInstallment = false;
    $instDown = null;
    $instMonths = null;

    if ($useCustom) {
        $enableInstallment = !empty($input['enable_installment']);
        if ($enableInstallment) {
            if (isset($input['installment_downpayment']) && $input['installment_downpayment'] !== '' && $input['installment_downpayment'] !== null) {
                $instDown = floatval($input['installment_downpayment']);
            }
            if (isset($input['installment_duration_months']) && $input['installment_duration_months'] !== '' && $input['installment_duration_months'] !== null) {
                $instMonths = intval($input['installment_duration_months']);
            }
        }
    } else {
        $enableInstallment = !empty($input['enable_installment']);
        if ($enableInstallment) {
            if (isset($input['installment_duration_months']) && $input['installment_duration_months'] !== '' && $input['installment_duration_months'] !== null) {
                $instMonths = intval($input['installment_duration_months']);
            }
            $instDown = null;
            if (isset($input['installment_downpayment']) && $input['installment_downpayment'] !== '' && $input['installment_downpayment'] !== null) {
                $instDown = floatval($input['installment_downpayment']);
            }
        } else {
            $downpaymentPct = null;
        }
    }

    $data = [
        'service_id' => generateServiceId(),
        'service_name' => sanitize($input['service_name'] ?? ''),
        'service_details' => sanitize($input['service_details'] ?? ''),
        'category' => sanitize($input['category'] ?? ''),
        'price' => isset($input['price']) ? floatval($input['price']) : 0.00,
        'downpayment_percentage' => $enableInstallment ? null : $downpaymentPct,
        'enable_installment' => $enableInstallment ? 1 : 0,
        'installment_downpayment' => $enableInstallment ? $instDown : null,
        'installment_duration_months' => $enableInstallment ? $instMonths : null,
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

    if ($useCustom && !$enableInstallment && $downpaymentPct === null) {
        jsonResponse(false, 'Enter a custom down payment percentage, or disable custom payment settings to use the clinic default.');
    }

    if (!$enableInstallment && $data['downpayment_percentage'] !== null) {
        if ($data['downpayment_percentage'] < 0 || $data['downpayment_percentage'] > 100) {
            jsonResponse(false, 'Downpayment percentage must be between 0 and 100.');
        }
    }

    $ps = loadTenantPaymentSettings($pdo, $tenantId);

    if ($enableInstallment) {
        if ($data['installment_duration_months'] === null || $data['installment_duration_months'] < 1) {
            jsonResponse(false, 'Duration must be at least 1 month when installment plan is enabled.');
        }
        if ($useCustom) {
            if ($data['installment_downpayment'] === null) {
                jsonResponse(false, 'Installment downpayment is required when using custom payment settings for an installment plan.');
            }
            if ($data['installment_downpayment'] < 0) {
                jsonResponse(false, 'Installment downpayment cannot be negative.');
            }
            if ($data['installment_downpayment'] > $data['price']) {
                jsonResponse(false, 'Installment downpayment cannot exceed the service price.');
            }
        } else {
            if ($data['installment_downpayment'] !== null) {
                if ($data['installment_downpayment'] < 0) {
                    jsonResponse(false, 'Installment downpayment cannot be negative.');
                }
                if ($data['installment_downpayment'] > $data['price']) {
                    jsonResponse(false, 'Installment downpayment cannot exceed the service price.');
                }
            } else {
                $minRequired = (float) $ps['long_term_min_downpayment'];
                if ($data['price'] > 0 && $minRequired > $data['price']) {
                    jsonResponse(false, 'Service price is lower than the long-term minimum down payment in Payment Settings. Increase the price or lower that minimum.');
                }
            }
        }
    }
    
    if (!in_array($data['status'], ['active', 'inactive'])) {
        $data['status'] = 'active';
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tbl_services (
                tenant_id, service_id, service_name, service_details, category, price,
                downpayment_percentage, enable_installment,
                installment_downpayment, installment_duration_months,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tenantId,
            $data['service_id'],
            $data['service_name'],
            $data['service_details'],
            $data['category'],
            $data['price'],
            $data['downpayment_percentage'],
            $data['enable_installment'],
            $data['installment_downpayment'],
            $data['installment_duration_months'],
            $data['status']
        ]);
        
        $serviceDbId = $pdo->lastInsertId();
        
        // Get the created service
        $stmt = $pdo->prepare("SELECT * FROM tbl_services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$serviceDbId, $tenantId]);
        $service = enrichServiceRow($pdo, $tenantId, $stmt->fetch());
        
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
                $stmt = $pdo->prepare("SELECT * FROM tbl_services WHERE id = ? AND tenant_id = ?");
                $stmt->execute([$serviceId, $tenantId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM tbl_services WHERE service_id = ? AND tenant_id = ?");
                $stmt->execute([$serviceIdCode, $tenantId]);
            }
            
            $service = enrichServiceRow($pdo, $tenantId, $stmt->fetch());
            
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
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_services $whereClause");
            $countStmt->execute($params);
            $totalItems = $countStmt->fetchColumn();
            
            // Get paginated results
            // Use validated integer literals for LIMIT/OFFSET to avoid PDO string-binding SQL errors.
            $safeLimit = (int) $limit;
            $safeOffset = (int) $offset;
            $sql = "SELECT * FROM tbl_services $whereClause ORDER BY id DESC LIMIT $safeLimit OFFSET $safeOffset";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $services = array_map(function ($row) use ($pdo, $tenantId) {
                return enrichServiceRow($pdo, $tenantId, $row);
            }, $stmt->fetchAll());
            
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
        $stmt = $pdo->prepare("SELECT * FROM tbl_services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$serviceId, $tenantId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tbl_services WHERE service_id = ? AND tenant_id = ?");
        $stmt->execute([$serviceIdCode, $tenantId]);
    }
    
    $existing = $stmt->fetch();
    
    if (!$existing) {
        jsonResponse(false, 'Service not found.');
    }

    $paymentInputPresent = is_array($input) && (
        array_key_exists('use_custom_payment', $input)
        || array_key_exists('enable_installment', $input)
        || array_key_exists('downpayment_percentage', $input)
        || array_key_exists('installment_downpayment', $input)
        || array_key_exists('installment_duration_months', $input)
    );

    $downpaymentPct = null;
    $enableInstallment = false;
    $instDown = null;
    $instMonths = null;
    $useCustom = false;

    if ($paymentInputPresent) {
        $useCustom = !empty($input['use_custom_payment']);
        $downPctRaw = $input['downpayment_percentage'] ?? null;
        if ($downPctRaw !== null && $downPctRaw !== '') {
            $downpaymentPct = floatval($downPctRaw);
        } else {
            $downpaymentPct = null;
        }

        if ($useCustom) {
            $enableInstallment = !empty($input['enable_installment']);
            if ($enableInstallment) {
                if (isset($input['installment_downpayment']) && $input['installment_downpayment'] !== '' && $input['installment_downpayment'] !== null) {
                    $instDown = floatval($input['installment_downpayment']);
                }
                if (isset($input['installment_duration_months']) && $input['installment_duration_months'] !== '' && $input['installment_duration_months'] !== null) {
                    $instMonths = intval($input['installment_duration_months']);
                }
            }
        } else {
            $enableInstallment = !empty($input['enable_installment']);
            if ($enableInstallment) {
                if (isset($input['installment_duration_months']) && $input['installment_duration_months'] !== '' && $input['installment_duration_months'] !== null) {
                    $instMonths = intval($input['installment_duration_months']);
                }
                $instDown = null;
                if (isset($input['installment_downpayment']) && $input['installment_downpayment'] !== '' && $input['installment_downpayment'] !== null) {
                    $instDown = floatval($input['installment_downpayment']);
                }
            } else {
                $downpaymentPct = null;
            }
        }
    } else {
        $downpaymentPct = isset($existing['downpayment_percentage']) && $existing['downpayment_percentage'] !== null && $existing['downpayment_percentage'] !== ''
            ? floatval($existing['downpayment_percentage'])
            : null;
        if (array_key_exists('downpayment_percentage', $input)) {
            $rawDp = $input['downpayment_percentage'];
            $downpaymentPct = ($rawDp === null || $rawDp === '') ? null : floatval($rawDp);
        }
        $enableInstallment = array_key_exists('enable_installment', $input)
            ? (!empty($input['enable_installment']))
            : !empty($existing['enable_installment']);

        $instDown = isset($existing['installment_downpayment']) && $existing['installment_downpayment'] !== null && $existing['installment_downpayment'] !== ''
            ? floatval($existing['installment_downpayment'])
            : null;
        $instMonths = isset($existing['installment_duration_months']) && $existing['installment_duration_months'] !== null && $existing['installment_duration_months'] !== ''
            ? intval($existing['installment_duration_months'])
            : null;
        if ($enableInstallment) {
            if (array_key_exists('installment_downpayment', $input)) {
                $raw = $input['installment_downpayment'];
                $instDown = ($raw === null || $raw === '') ? null : floatval($raw);
            }
            if (array_key_exists('installment_duration_months', $input)) {
                $rawM = $input['installment_duration_months'];
                $instMonths = ($rawM === null || $rawM === '') ? null : intval($rawM);
            }
        } else {
            $instDown = null;
            $instMonths = null;
        }
    }

    // Extract and sanitize update data
    $data = [
        'service_name' => isset($input['service_name']) ? sanitize($input['service_name']) : $existing['service_name'],
        'service_details' => isset($input['service_details']) ? sanitize($input['service_details']) : $existing['service_details'],
        'category' => isset($input['category']) ? sanitize($input['category']) : $existing['category'],
        'price' => isset($input['price']) ? floatval($input['price']) : floatval($existing['price']),
        'downpayment_percentage' => $enableInstallment ? null : $downpaymentPct,
        'enable_installment' => $enableInstallment ? 1 : 0,
        'installment_downpayment' => $enableInstallment ? $instDown : null,
        'installment_duration_months' => $enableInstallment ? $instMonths : null,
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

    if ($paymentInputPresent && $useCustom && !$enableInstallment && $downpaymentPct === null) {
        jsonResponse(false, 'Enter a custom down payment percentage, or disable custom payment settings to use the clinic default.');
    }

    if (!$enableInstallment && $data['downpayment_percentage'] !== null) {
        if ($data['downpayment_percentage'] < 0 || $data['downpayment_percentage'] > 100) {
            jsonResponse(false, 'Downpayment percentage must be between 0 and 100.');
        }
    }

    $ps = loadTenantPaymentSettings($pdo, $tenantId);

    if ($enableInstallment) {
        if ($data['installment_duration_months'] === null || $data['installment_duration_months'] < 1) {
            jsonResponse(false, 'Duration must be at least 1 month when installment plan is enabled.');
        }
        if ($paymentInputPresent && $useCustom) {
            if ($data['installment_downpayment'] === null) {
                jsonResponse(false, 'Installment downpayment is required when using custom payment settings for an installment plan.');
            }
            if ($data['installment_downpayment'] < 0) {
                jsonResponse(false, 'Installment downpayment cannot be negative.');
            }
            if ($data['installment_downpayment'] > $data['price']) {
                jsonResponse(false, 'Installment downpayment cannot exceed the service price.');
            }
        } elseif ($paymentInputPresent && !$useCustom) {
            if ($data['installment_downpayment'] !== null) {
                if ($data['installment_downpayment'] < 0) {
                    jsonResponse(false, 'Installment downpayment cannot be negative.');
                }
                if ($data['installment_downpayment'] > $data['price']) {
                    jsonResponse(false, 'Installment downpayment cannot exceed the service price.');
                }
            } else {
                $minRequired = (float) $ps['long_term_min_downpayment'];
                if ($data['price'] > 0 && $minRequired > $data['price']) {
                    jsonResponse(false, 'Service price is lower than the long-term minimum down payment in Payment Settings. Increase the price or lower that minimum.');
                }
            }
        } else {
            if ($data['installment_duration_months'] === null || $data['installment_duration_months'] < 1) {
                jsonResponse(false, 'Duration must be at least 1 month when installment plan is enabled.');
            }
            if ($data['installment_downpayment'] === null) {
                $minRequired = (float) $ps['long_term_min_downpayment'];
                if ($data['price'] > 0 && $minRequired > $data['price']) {
                    jsonResponse(false, 'Service price is lower than the long-term minimum down payment in Payment Settings. Increase the price or lower that minimum.');
                }
            } else {
                if ($data['installment_downpayment'] < 0) {
                    jsonResponse(false, 'Installment downpayment cannot be negative.');
                }
                if ($data['installment_downpayment'] > $data['price']) {
                    jsonResponse(false, 'Installment downpayment cannot exceed the service price.');
                }
            }
        }
    }
    
    if (!in_array($data['status'], ['active', 'inactive'])) {
        $data['status'] = $existing['status'];
    }
    
    try {
        $updateId = $serviceId ?: $existing['id'];
        
        $stmt = $pdo->prepare("
            UPDATE tbl_services 
            SET service_name = ?, 
                service_details = ?, 
                category = ?, 
                price = ?, 
                downpayment_percentage = ?,
                enable_installment = ?,
                installment_downpayment = ?,
                installment_duration_months = ?,
                status = ?
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([
            $data['service_name'],
            $data['service_details'],
            $data['category'],
            $data['price'],
            $data['downpayment_percentage'],
            $data['enable_installment'],
            $data['installment_downpayment'],
            $data['installment_duration_months'],
            $data['status'],
            $updateId,
            $tenantId
        ]);
        
        // Get updated service
        $stmt = $pdo->prepare("SELECT * FROM tbl_services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$updateId, $tenantId]);
        $service = enrichServiceRow($pdo, $tenantId, $stmt->fetch());
        
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
        $stmt = $pdo->prepare("SELECT * FROM tbl_services WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$serviceId, $tenantId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tbl_services WHERE service_id = ? AND tenant_id = ?");
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
            UPDATE tbl_services 
            SET status = 'inactive'
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->execute([$updateId, $tenantId]);
        
        jsonResponse(true, 'Service deactivated successfully.');
        
    } catch (Exception $e) {
        error_log('Service delete error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to delete service: ' . $e->getMessage());
    }
}
