<?php
/**
 * Appointments API Endpoint
 * Handles CRUD operations for appointments
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/availability.php';
require_once __DIR__ . '/../includes/appointment_db_tables.php';
require_once __DIR__ . '/../includes/appointment_reminder_service.php';

header('Content-Type: application/json');

// Ensure this endpoint never returns an empty body on fatal errors.
register_shutdown_function(static function () {
    $lastError = error_get_last();
    if ($lastError === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($lastError['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(500);
    }
    $fatalMessage = isset($lastError['message']) ? trim((string) $lastError['message']) : '';
    $fatalFile = isset($lastError['file']) ? basename((string) $lastError['file']) : 'unknown';
    $fatalLine = isset($lastError['line']) ? (int) $lastError['line'] : 0;
    $displayMessage = 'Failed to create appointment. Please try again.';
    if ($fatalMessage !== '') {
        $displayMessage = 'Failed to create appointment. ' . $fatalMessage . ' (' . $fatalFile . ':' . $fatalLine . ')';
    }
    echo json_encode([
        'success' => false,
        'message' => $displayMessage
    ]);
});

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();
$tenantId = requireClinicTenantId();
$resolvedTables = clinic_resolve_appointment_db_tables($pdo);
$appointmentsTableName = $resolvedTables['appointments'] ?? 'appointments';
$patientsTableName = $resolvedTables['patients'] ?? (clinic_get_physical_table_name($pdo, 'tbl_patients') ?? 'patients');
$usersTableName = $resolvedTables['users'] ?? (clinic_get_physical_table_name($pdo, 'tbl_users') ?? 'tbl_users');
$installmentsTableName = clinic_get_physical_table_name($pdo, 'tbl_installments')
    ?? clinic_get_physical_table_name($pdo, 'installments')
    ?? 'installments';
$dentistsTableName = $resolvedTables['dentists']
    ?? clinic_get_physical_table_name($pdo, 'tbl_dentists')
    ?? clinic_get_physical_table_name($pdo, 'dentists')
    ?? 'tbl_dentists';
$treatmentsTableName = clinic_get_physical_table_name($pdo, 'tbl_treatments')
    ?? clinic_get_physical_table_name($pdo, 'treatments')
    ?? '';

// Route based on method and action
$action = isset($_GET['action']) ? sanitize($_GET['action']) : null;

try {
    switch ($method) {
        case 'POST':
            if ($action === 'add_services') {
                addServicesToAppointment();
            } else {
                createAppointment();
            }
            break;
        case 'GET':
            if ($action === 'auto_cancel_pending') {
                autoCancelPendingBookings();
                jsonResponse(true, 'Auto-cancellation check completed.');
            } else {
                // Auto-cancel old pending bookings before fetching appointments
                autoCancelPendingBookings();
                getAppointments();
            }
            break;
        case 'PUT':
            updateAppointment();
            break;
        case 'DELETE':
            deleteAppointment();
            break;
        default:
            jsonResponse(false, 'Invalid request method.');
    }
} catch (Throwable $routeError) {
    error_log('Appointments API uncaught error: ' . $routeError->getMessage());
    jsonResponse(false, 'Appointment API error: ' . $routeError->getMessage());
}

/**
 * Generate unique booking ID
 * Format: BK-YYYY-XXXXXX where XXXXXX is a 6-digit sequential number
 * @return string Booking ID
 */
function generateBookingId() {
    global $pdo, $tenantId, $appointmentsTableName;
    
    $prefix = 'BK';
    $year = date('Y');
    $pattern = $prefix . '-' . $year . '-%';
    
    $quotedAppointmentsTable = clinic_quote_identifier((string) $appointmentsTableName);

    // Find the last booking_id with this prefix and year
    $stmt = $pdo->prepare("
        SELECT booking_id 
        FROM {$quotedAppointmentsTable}
        WHERE booking_id LIKE ?
          AND tenant_id = ?
        ORDER BY booking_id DESC 
        LIMIT 1
    ");
    $stmt->execute([$pattern, $tenantId]);
    $lastBookingId = $stmt->fetchColumn();
    
    // Extract sequence number from last booking_id, or start at 000001
    if ($lastBookingId) {
        $parts = explode('-', $lastBookingId);
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
    $bookingId = $prefix . '-' . $year . '-' . $formattedSequence;
    
    // Double-check uniqueness
    $stmt = $pdo->prepare("SELECT booking_id FROM {$quotedAppointmentsTable} WHERE booking_id = ? AND tenant_id = ?");
    $stmt->execute([$bookingId, $tenantId]);
    if ($stmt->fetchColumn()) {
        // If somehow it exists, increment and try again
        $sequence++;
        $formattedSequence = str_pad($sequence, 6, '0', STR_PAD_LEFT);
        $bookingId = $prefix . '-' . $year . '-' . $formattedSequence;
    }
    
    return $bookingId;
}

/**
 * Generate unique treatment ID.
 * Format: TRT-YYYY-XXXXXX
 */
function generateTreatmentId(): string {
    global $pdo, $tenantId, $treatmentsTableName;

    if (trim((string) $treatmentsTableName) === '') {
        return '';
    }

    $prefix = 'TRT-' . date('Y') . '-';
    $quotedTreatmentsTable = clinic_quote_identifier((string) $treatmentsTableName);

    $stmt = $pdo->prepare("
        SELECT treatment_id
        FROM {$quotedTreatmentsTable}
        WHERE tenant_id = ?
          AND treatment_id LIKE ?
        ORDER BY treatment_id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $prefix . '%']);
    $lastTreatmentId = (string) ($stmt->fetchColumn() ?: '');

    $sequence = 1;
    if ($lastTreatmentId !== '') {
        $parts = explode('-', $lastTreatmentId);
        if (count($parts) === 3) {
            $sequence = ((int) $parts[2]) + 1;
        }
    }

    return $prefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
}

/**
 * Read ENUM values for a table column.
 *
 * @param PDO $pdo
 * @param string $tableName
 * @param string $columnName
 * @return array<int, string>
 */
function getEnumValues(PDO $pdo, string $tableName, string $columnName): array {
    try {
        $stmt = $pdo->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName, $columnName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $columnType = isset($row['COLUMN_TYPE']) ? (string) $row['COLUMN_TYPE'] : '';
        if ($columnType === '' || stripos($columnType, 'enum(') !== 0) {
            return [];
        }
        if (!preg_match_all("/'([^']+)'/", $columnType, $matches) || empty($matches[1])) {
            return [];
        }
        return array_values(array_map(static function ($value) {
            return strtolower(trim((string) $value));
        }, $matches[1]));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Read the SQL column type for a table column.
 *
 * @param PDO $pdo
 * @param string $tableName
 * @param string $columnName
 * @return string
 */
function getColumnType(PDO $pdo, string $tableName, string $columnName): string {
    try {
        $stmt = $pdo->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName, $columnName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return isset($row['COLUMN_TYPE']) ? strtolower(trim((string) $row['COLUMN_TYPE'])) : '';
    } catch (Throwable $e) {
        return '';
    }
}

function clinic_time_to_minutes(string $timeValue): ?int {
    $timeValue = trim($timeValue);
    if ($timeValue === '') return null;
    $formats = ['H:i:s', 'H:i'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $timeValue);
        if ($dt instanceof DateTime) {
            return ((int) $dt->format('H') * 60) + (int) $dt->format('i');
        }
    }
    return null;
}

function clinic_compute_requested_duration_minutes(array $normalizedServiceRows): int {
    $sum = 0;
    foreach ($normalizedServiceRows as $row) {
        if (!is_array($row)) continue;
        $duration = max(0, (int) ($row['service_duration'] ?? 0));
        $buffer = max(0, (int) ($row['buffer_time'] ?? 0));
        $sum += ($duration + $buffer);
    }
    return $sum > 0 ? $sum : 60;
}

function clinic_fetch_existing_appointment_duration_minutes(
    PDO $pdo,
    string $tenantId,
    string $bookingId,
    int $appointmentId,
    ?string $appointmentServicesTableName,
    ?string $servicesCatalogTableName
): int {
    if ($appointmentServicesTableName === null || $servicesCatalogTableName === null) {
        return 60;
    }
    $apsCols = clinic_table_columns($pdo, $appointmentServicesTableName);
    if (!in_array('service_id', $apsCols, true)) {
        return 60;
    }
    $hasAppointmentId = in_array('appointment_id', $apsCols, true);
    $hasBookingId = in_array('booking_id', $apsCols, true);
    if (!$hasAppointmentId && !$hasBookingId) {
        return 60;
    }

    $quotedAps = clinic_quote_identifier($appointmentServicesTableName);
    $quotedSvc = clinic_quote_identifier($servicesCatalogTableName);
    if ($hasAppointmentId && $appointmentId > 0) {
        $whereSql = 'aps.tenant_id = ? AND aps.appointment_id = ?';
        $lookupKey = $appointmentId;
    } elseif ($hasBookingId && trim($bookingId) !== '') {
        $whereSql = 'aps.tenant_id = ? AND aps.booking_id = ?';
        $lookupKey = trim($bookingId);
    } else {
        return 60;
    }

    $schedJoinSql = '';
    $schedTimeClause = '';
    $appointmentsPhys = clinic_resolve_appointment_db_tables($pdo)['appointments'] ?? null;
    if ($appointmentsPhys !== null && in_array('added_at', $apsCols, true)) {
        $apptCols = clinic_table_columns($pdo, (string) $appointmentsPhys);
        if (
            in_array('created_at', $apptCols, true)
            && in_array('id', $apptCols, true)
            && (
                ($hasAppointmentId && $appointmentId > 0)
                || ($hasBookingId && trim($bookingId) !== '')
            )
        ) {
            $qAppt = clinic_quote_identifier((string) $appointmentsPhys);
            if ($appointmentId > 0 && $hasAppointmentId) {
                $schedJoinSql = "
                    INNER JOIN {$qAppt} ap_sa ON ap_sa.tenant_id = aps.tenant_id AND ap_sa.id = aps.appointment_id
                ";
            } else {
                $schedJoinSql = "
                    INNER JOIN {$qAppt} ap_sa ON ap_sa.tenant_id = aps.tenant_id AND ap_sa.booking_id = aps.booking_id
                ";
            }
            $schedTimeClause = "
                AND (
                    aps.added_at IS NULL
                    OR ap_sa.created_at IS NULL
                    OR aps.added_at <= DATE_ADD(ap_sa.created_at, INTERVAL 10 MINUTE)
                )
            ";
        }
    }

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(COALESCE(sv.service_duration, 0) + COALESCE(sv.buffer_time, 0)), 0) AS total_minutes
        FROM {$quotedAps} aps
        LEFT JOIN {$quotedSvc} sv
          ON sv.tenant_id = aps.tenant_id
         AND sv.service_id = aps.service_id
        {$schedJoinSql}
        WHERE {$whereSql}
        {$schedTimeClause}
    ");
    $stmt->execute([$tenantId, $lookupKey]);
    $minutes = (int) ($stmt->fetchColumn() ?? 0);
    return $minutes > 0 ? $minutes : 60;
}

/** @return list<string> */
function clinic_fallback_names_from_truncated_service_type(string $serviceTypeTruncatedSummary): array
{
    $clean = preg_replace('/\s*\(\+\d+\s+more\)/i', '', trim($serviceTypeTruncatedSummary)) ?? '';
    if ($clean === '') {
        return [];
    }
    $parts = array_map(static function ($part) {
        return trim((string) $part);
    }, explode(',', $clean));

    return array_values(array_unique(array_filter($parts, static function ($part) {
        return $part !== '';
    })));
}

/**
 * Readable service names attached to appointment line items (appointment_services rows).
 *
 * @return list<string>
 */
function clinic_collect_appointment_line_service_display_names(
    PDO $pdo,
    string $tenantId,
    string $bookingId,
    int $appointmentId,
    ?string $appointmentServicesTableName,
    ?string $servicesCatalogTableName
): array {
    if ($appointmentServicesTableName === null) {
        return [];
    }
    $apsCols = clinic_table_columns($pdo, (string) $appointmentServicesTableName);
    if (!in_array('tenant_id', $apsCols, true) || !in_array('service_id', $apsCols, true)) {
        return [];
    }
    $orParts = [];
    $params = [$tenantId];
    if (in_array('appointment_id', $apsCols, true) && $appointmentId > 0) {
        $orParts[] = 'aps.appointment_id = ?';
        $params[] = $appointmentId;
    }
    if (in_array('booking_id', $apsCols, true) && trim($bookingId) !== '') {
        $orParts[] = 'aps.booking_id = ?';
        $params[] = $bookingId;
    }
    if ($orParts === []) {
        return [];
    }

    $quotedAps = clinic_quote_identifier((string) $appointmentServicesTableName);
    $whereOr = '(' . implode(' OR ', $orParts) . ')';

    $svcColsOk = false;
    $quotedSvc = null;
    if ($servicesCatalogTableName !== null) {
        $svcCols = clinic_table_columns($pdo, (string) $servicesCatalogTableName);
        $svcColsOk = in_array('tenant_id', $svcCols, true)
            && in_array('service_id', $svcCols, true)
            && in_array('service_name', $svcCols, true);
        if ($svcColsOk) {
            $quotedSvc = clinic_quote_identifier((string) $servicesCatalogTableName);
        }
    }

    $hasApsSvcName = in_array('service_name', $apsCols, true);

    $nameExprSql = '';
    $joinSql = '';
    if ($hasApsSvcName && $svcColsOk && $quotedSvc !== null) {
        $joinSql = "LEFT JOIN {$quotedSvc} srv
            ON srv.tenant_id = aps.tenant_id
           AND srv.service_id = aps.service_id ";
        $nameExprSql = "
            TRIM(COALESCE(
                NULLIF(TRIM(COALESCE(aps.service_name, '')), ''),
                NULLIF(TRIM(COALESCE(srv.service_name, '')), '')
            ))
        ";
    } elseif ($svcColsOk && $quotedSvc !== null) {
        $joinSql = "LEFT JOIN {$quotedSvc} srv
            ON srv.tenant_id = aps.tenant_id
           AND srv.service_id = aps.service_id ";
        $nameExprSql = "NULLIF(TRIM(COALESCE(srv.service_name, '')), '')";
    } elseif ($hasApsSvcName) {
        $nameExprSql = "NULLIF(TRIM(COALESCE(aps.service_name, '')), '')";
    } else {
        return [];
    }

    $schedJoinSql = '';
    $schedTimeClause = '';
    $appointmentsPhys = clinic_resolve_appointment_db_tables($pdo)['appointments'] ?? null;
    if (
        $appointmentsPhys !== null
        && in_array('added_at', $apsCols, true)
    ) {
        $apptCols = clinic_table_columns($pdo, (string) $appointmentsPhys);
        if (
            in_array('created_at', $apptCols, true)
            && in_array('id', $apptCols, true)
            && (
                (in_array('appointment_id', $apsCols, true) && $appointmentId > 0)
                || (in_array('booking_id', $apsCols, true) && trim($bookingId) !== '')
            )
        ) {
            $qAppt = clinic_quote_identifier((string) $appointmentsPhys);
            if (in_array('appointment_id', $apsCols, true) && $appointmentId > 0) {
                $schedJoinSql = "
                    INNER JOIN {$qAppt} ap_sa ON ap_sa.tenant_id = aps.tenant_id AND ap_sa.id = aps.appointment_id
                ";
            } else {
                $schedJoinSql = "
                    INNER JOIN {$qAppt} ap_sa ON ap_sa.tenant_id = aps.tenant_id AND ap_sa.booking_id = aps.booking_id
                ";
            }
            $schedTimeClause = "
              AND (
                  aps.added_at IS NULL
                  OR ap_sa.created_at IS NULL
                  OR aps.added_at <= DATE_ADD(ap_sa.created_at, INTERVAL 10 MINUTE)
              )
            ";
        }
    }

    $sql = "
        SELECT DISTINCT {$nameExprSql} AS n
        FROM {$quotedAps} aps
        {$joinSql}
        {$schedJoinSql}
        WHERE aps.tenant_id = ?
          AND {$whereOr}
          {$schedTimeClause}
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = [];
    while ($nameRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $n = trim((string) ($nameRow['n'] ?? ''));
        if ($n !== '') {
            $out[] = $n;
        }
    }

    return array_values(array_unique($out));
}

/**
 * @param array<string, mixed> $bookingRow
 */
function clinic_staff_booking_augment_combined_services(PDO $pdo, array &$bookingRow, string $tenantId, ?string $appointmentServicesTableName, ?string $servicesCatalogTableName): void
{
    $bookingId = trim((string) ($bookingRow['booking_id'] ?? ''));
    $appointmentId = (int) ($bookingRow['id'] ?? 0);
    if ($appointmentServicesTableName !== null && $servicesCatalogTableName !== null) {
        $bookingRow['combined_duration_minutes'] = clinic_fetch_existing_appointment_duration_minutes(
            $pdo,
            $tenantId,
            $bookingId,
            $appointmentId,
            $appointmentServicesTableName,
            $servicesCatalogTableName
        );
        $bookingRow['appointment_service_names'] = clinic_collect_appointment_line_service_display_names(
            $pdo,
            $tenantId,
            $bookingId,
            $appointmentId,
            $appointmentServicesTableName,
            $servicesCatalogTableName
        );
    } else {
        $bookingRow['combined_duration_minutes'] = null;
        $bookingRow['appointment_service_names'] = [];
    }
    if (!is_array($bookingRow['appointment_service_names']) || $bookingRow['appointment_service_names'] === []) {
        $bookingRow['appointment_service_names'] = clinic_fallback_names_from_truncated_service_type(
            (string) ($bookingRow['service_type'] ?? '')
        );
    }
}

/**
 * Create installments for long-term treatment
 * @param PDO $pdo Database connection
 * @param string $bookingId Booking ID
 * @param float $totalCost Total treatment cost
 * @param int $durationMonths Treatment duration in months
 * @param string $startDate Start date (Y-m-d format)
 * @param array|null $orthodonticsRequirements Orthodontics payment requirements
 */
function createInstallments($pdo, $bookingId, $totalCost, $durationMonths, $startDate, $orthodonticsRequirements = null) {
    global $installmentsTableName;
    $paymentOption = isset($orthodonticsRequirements['payment_option']) ? $orthodonticsRequirements['payment_option'] : 'installment';
    $downpaymentAmount = isset($orthodonticsRequirements['downpayment_amount']) ? floatval($orthodonticsRequirements['downpayment_amount']) : 0;
    
    $installments = [];
    $remainingAmount = $totalCost;
    
    if ($paymentOption === 'downpayment' && $downpaymentAmount > 0) {
        // First installment is the downpayment
        $installments[] = [
            'number' => 1,
            'amount' => $downpaymentAmount,
            'status' => 'pending'
        ];
        $remainingAmount -= $downpaymentAmount;
        $installmentCount = $durationMonths - 1; // Remaining installments
    } else {
        // Full payment or installment plan - divide total by duration
        $installmentCount = $durationMonths;
    }
    
    // Calculate monthly installment amount
    if ($installmentCount > 0) {
        $monthlyAmount = $remainingAmount / $installmentCount;
        
        // Create remaining installments
        $startNumber = ($paymentOption === 'downpayment' && $downpaymentAmount > 0) ? 2 : 1;
        for ($i = 0; $i < $installmentCount; $i++) {
            $installmentNumber = $startNumber + $i;
            
            // For the last installment, add any rounding difference
            if ($i === $installmentCount - 1) {
                $amount = $remainingAmount - ($monthlyAmount * ($installmentCount - 1));
            } else {
                $amount = $monthlyAmount;
            }
            
            // Determine initial status
            // First installment (after downpayment if applicable) can be 'book_visit' if downpayment was paid
            // Otherwise, all start as 'pending'
            $status = 'pending';
            if ($paymentOption === 'downpayment' && $downpaymentAmount > 0 && $installmentNumber === 2) {
                // After downpayment, next installment can be booked
                $status = 'book_visit';
            }
            
            $installments[] = [
                'number' => $installmentNumber,
                'amount' => round($amount, 2),
                'status' => $status
            ];
        }
    }
    
    // Insert installments into database
    foreach ($installments as $installment) {
        $quotedInstallmentsTable = clinic_quote_identifier((string) $installmentsTableName);
        $stmt = $pdo->prepare("
            INSERT INTO {$quotedInstallmentsTable} (
                booking_id,
                installment_number,
                amount_due,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $bookingId,
            $installment['number'],
            $installment['amount'],
            $installment['status']
        ]);
    }
}

/**
 * Create new appointment
 */
function createAppointment() {
    global $pdo, $tenantId, $appointmentsTableName, $patientsTableName, $usersTableName, $installmentsTableName, $resolvedTables, $treatmentsTableName;
    $quotedPatientsTable = clinic_quote_identifier((string) $patientsTableName);
    $quotedUsersTable = clinic_quote_identifier((string) $usersTableName);
    $quotedAppointmentsTable = clinic_quote_identifier((string) $appointmentsTableName);
    $quotedInstallmentsTable = clinic_quote_identifier((string) $installmentsTableName);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extract and sanitize data
    $data = [
        'patient_id' => isset($input['patient_id']) ? sanitize($input['patient_id']) : null,
        'dentist_id' => isset($input['dentist_id']) ? sanitize($input['dentist_id']) : null,
        'dentist_user_id' => isset($input['dentist_user_id']) ? sanitize($input['dentist_user_id']) : null,
        'booking_source' => isset($input['booking_source']) ? sanitize($input['booking_source']) : '',
        'appointment_date' => sanitize($input['appointment_date'] ?? ''),
        'appointment_time' => sanitize($input['appointment_time'] ?? ''),
        'procedure' => sanitize($input['procedure'] ?? ''), // Legacy support
        'services' => isset($input['services']) && is_array($input['services']) ? $input['services'] : [],
        'service_categories' => isset($input['service_categories']) && is_array($input['service_categories']) ? $input['service_categories'] : [],
        'insurance' => sanitize($input['insurance'] ?? ''),
        'notes' => sanitize($input['notes'] ?? ''),
        'orthodontics_requirements' => isset($input['orthodontics_requirements']) ? $input['orthodontics_requirements'] : null,
        'visit_type' => isset($input['visit_type']) ? sanitize($input['visit_type']) : 'pre_book',
        'status' => isset($input['status']) ? sanitize($input['status']) : 'pending'
    ];
    $isStaffSetAppointmentBooking = strtolower(trim((string) ($data['booking_source'] ?? ''))) === 'staff_set_appointments';
    $appointmentServicesTableName = $resolvedTables['appointment_services']
        ?? clinic_get_physical_table_name($pdo, 'tbl_appointment_services')
        ?? clinic_get_physical_table_name($pdo, 'appointment_services');
    $servicesCatalogTableName = $resolvedTables['services']
        ?? clinic_get_physical_table_name($pdo, 'tbl_services')
        ?? clinic_get_physical_table_name($pdo, 'services');
    
    // Validation
    $errors = [];
    $availabilityDentistUserId = '';
    
    // Validate patient_id is provided
    if (empty($data['patient_id'])) {
        $errors[] = 'Patient selection is required.';
    } else {
        // Check if user is staff/admin (can create appointments for any patient)
        $userType = $_SESSION['user_type'] ?? 'client';
        $isStaffOrAdmin = in_array($userType, ['manager', 'doctor', 'staff']);
        
        // Verify the patient exists
        $userId = getCurrentUserId();
        if ($userId) {
            // For staff/admin, just verify patient exists
            if ($isStaffOrAdmin) {
                // Trim and validate patient_id
                $patientId = trim($data['patient_id']);
                if (empty($patientId)) {
                    $errors[] = 'Patient ID cannot be empty.';
                } else {
                    $stmt = $pdo->prepare("SELECT patient_id, id FROM {$quotedPatientsTable} WHERE patient_id = ? AND tenant_id = ?");
                    $stmt->execute([$patientId, $tenantId]);
                    $patient = $stmt->fetch();
                    
                    if (!$patient) {
                        // Log for debugging
                        error_log("Patient validation failed - patient_id: '" . $patientId . "', user_type: " . $userType . ", user_id: " . $userId);
                        $errors[] = 'Invalid patient selected. Please ensure the patient exists in the system.';
                    }
                }
            } else {
                // For clients, verify the patient belongs to the logged-in user
                $stmt = $pdo->prepare("SELECT user_id FROM {$quotedUsersTable} WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $stmt = $pdo->prepare("SELECT patient_id FROM {$quotedPatientsTable} WHERE patient_id = ? AND owner_user_id = ? AND tenant_id = ?");
                    $stmt->execute([$data['patient_id'], $user['user_id'], $tenantId]);
                    $patient = $stmt->fetch();
                    
                    if (!$patient) {
                        $errors[] = 'Invalid patient selected.';
                    }
                }
            }
        } else {
            $errors[] = 'Authentication required.';
        }
    }
    
    if (empty($data['appointment_date'])) {
        $errors[] = 'Appointment date is required.';
    } elseif (!validateDate($data['appointment_date'], 'Y-m-d')) {
        $errors[] = 'Invalid date format.';
    } else {
        $appointmentDate = new DateTime($data['appointment_date']);
        
        // Check if the date is a Sunday (0 = Sunday)
        $dayOfWeek = (int)$appointmentDate->format('w');
        if ($dayOfWeek === 0) {
            $errors[] = 'Sunday is unavailable. Please select another day.';
        }
        
        // For walk-ins, allow current date/time. For pre-booked appointments, check if date is in the past
        if ($data['visit_type'] !== 'walk_in') {
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            if ($appointmentDate < $today) {
                $errors[] = 'Appointment date cannot be in the past.';
            }
        }
    }
    
    if (empty($data['appointment_time'])) {
        $errors[] = 'Appointment time is required.';
    }

    // Optional provider availability enforcement for booking pages that pass dentist info.
    if (empty($errors) && (!empty($data['dentist_user_id']) || !empty($data['dentist_id']))) {
        try {
            $dentistUserId = trim((string) ($data['dentist_user_id'] ?? ''));
            if ($dentistUserId === '' && !empty($data['dentist_id'])) {
                $dentistsTable = clinic_get_physical_table_name($pdo, 'tbl_dentists')
                    ?? clinic_get_physical_table_name($pdo, 'dentists');
                if ($dentistsTable !== null) {
                    $quotedDentistsTable = '`' . str_replace('`', '``', $dentistsTable) . '`';
                    $dentistLookupStmt = $pdo->prepare("
                        SELECT user_id
                        FROM {$quotedDentistsTable}
                        WHERE tenant_id = ?
                          AND dentist_id = ?
                        LIMIT 1
                    ");
                    $dentistLookupStmt->execute([$tenantId, trim((string) $data['dentist_id'])]);
                    $dentistLookup = $dentistLookupStmt->fetch(PDO::FETCH_ASSOC);
                    $dentistUserId = trim((string) ($dentistLookup['user_id'] ?? ''));
                }
            }
            if ($dentistUserId === '') {
                $errors[] = 'Selected staff/dentist is not available at this time';
            } else {
                $availabilityDentistUserId = $dentistUserId;
                $slotStart = $data['appointment_date'] . ' ' . $data['appointment_time'];
                try {
                    $slotEnd = (new DateTime($slotStart))->modify('+1 hour')->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $slotEnd = '';
                }
                if ($slotEnd === '' || !isUserAvailable($dentistUserId, $slotStart, $slotEnd)) {
                    $errors[] = 'Selected staff/dentist is not available at this time';
                }
            }
        } catch (Throwable $availabilityError) {
            error_log('Availability pre-check error: ' . $availabilityError->getMessage());
            $errors[] = 'Selected staff/dentist is not available at this time';
        }
    }
    
    // Validate services (new format) or procedure (legacy format)
    // Skip validation for follow-up appointments
    $isFollowup = isset($input['is_followup']) && $input['is_followup'] === true;
    $followupBookingId = isset($input['followup_booking_id']) ? sanitize($input['followup_booking_id']) : null;
    $followupInstallmentNumber = isset($input['followup_installment_number']) ? intval($input['followup_installment_number']) : null;
    
    if (!$isFollowup && empty($data['services']) && empty($data['procedure'])) {
        $errors[] = 'Please select at least one service or procedure.';
    }
    
    // Validate follow-up appointment data
    if ($isFollowup) {
        if (empty($followupBookingId)) {
            $errors[] = 'Follow-up booking ID is required.';
        }
        if (empty($followupInstallmentNumber)) {
            $errors[] = 'Follow-up installment number is required.';
        }
    }
    
    if (!empty($errors)) {
        jsonResponse(false, implode(' ', $errors));
    }
    
    try {
        $pdo->beginTransaction();

        // Atomic re-check before any write to reduce double-booking races.
        if ($availabilityDentistUserId !== '' && !empty($data['appointment_date']) && !empty($data['appointment_time'])) {
            $slotStartAtomic = $data['appointment_date'] . ' ' . $data['appointment_time'];
            try {
                $slotEndAtomic = (new DateTime($slotStartAtomic))->modify('+1 hour')->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                $slotEndAtomic = '';
            }
            if ($slotEndAtomic === '') {
                $pdo->rollBack();
                jsonResponse(false, 'Selected staff/dentist is not available at this time');
            }
            $atomicCheck = clinic_assert_user_available_atomic($pdo, $tenantId, $availabilityDentistUserId, $slotStartAtomic, $slotEndAtomic);
            if (empty($atomicCheck['available'])) {
                $pdo->rollBack();
                jsonResponse(false, 'Selected staff/dentist is not available at this time');
            }
        }
        
        // Handle follow-up appointments differently
        if ($isFollowup && $followupBookingId && $followupInstallmentNumber) {
            // Verify the installment exists and belongs to the user
            $userId = getCurrentUserId();
            if ($userId) {
                $stmt = $pdo->prepare("SELECT user_id FROM {$quotedUsersTable} WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Verify appointment belongs to user
                        $stmt = $pdo->prepare("
                        SELECT a.booking_id, a.patient_id
                        FROM {$quotedAppointmentsTable} a
                        INNER JOIN {$quotedPatientsTable} p ON a.patient_id = p.patient_id
                        WHERE a.booking_id = ? AND p.owner_user_id = ? AND a.tenant_id = ? AND p.tenant_id = ?
                    ");
                    $stmt->execute([$followupBookingId, $user['user_id'], $tenantId, $tenantId]);
                    $appointment = $stmt->fetch();
                    
                    if (!$appointment) {
                        $pdo->rollBack();
                        jsonResponse(false, 'Unauthorized. Follow-up booking not found.');
                    }
                    
                    // Verify installment exists and get amount
                    $stmt = $pdo->prepare("
                        SELECT id, status, amount_due 
                        FROM installments 
                        WHERE booking_id = ? AND installment_number = ?
                    ");
                    $stmt->execute([$followupBookingId, $followupInstallmentNumber]);
                    $installment = $stmt->fetch();
                    
                    if (!$installment) {
                        $pdo->rollBack();
                        jsonResponse(false, 'Installment not found.');
                    }
                    
                    $installmentAmount = floatval($installment['amount_due'] || 0);
                    $isFullyPaid = ($installmentAmount == 0);
                    
                    // Validate date: must be after previous treatment and at least 28 days apart
                    $previousDate = null;
                    if ($followupInstallmentNumber == 2) {
                        // For Treatment 2, get date from appointments table (Treatment 1)
                        $stmt = $pdo->prepare("SELECT appointment_date FROM {$quotedAppointmentsTable} WHERE booking_id = ? AND tenant_id = ?");
                        $stmt->execute([$followupBookingId, $tenantId]);
                        $appointment = $stmt->fetch();
                        if ($appointment && $appointment['appointment_date']) {
                            $previousDate = new DateTime($appointment['appointment_date']);
                        }
                    } else {
                        // For Treatment 3+, get date from previous installment
                        $stmt = $pdo->prepare("
                            SELECT scheduled_date 
                            FROM {$quotedInstallmentsTable}
                            WHERE booking_id = ? AND installment_number = ?
                        ");
                        $stmt->execute([$followupBookingId, $followupInstallmentNumber - 1]);
                        $previousInstallment = $stmt->fetch();
                        if ($previousInstallment && $previousInstallment['scheduled_date']) {
                            $previousDate = new DateTime($previousInstallment['scheduled_date']);
                        }
                    }
                    
                    if ($previousDate) {
                        $selectedDate = new DateTime($data['appointment_date']);
                        
                        // Check if date is earlier than or equal to previous treatment
                        if ($selectedDate <= $previousDate) {
                            $pdo->rollBack();
                            jsonResponse(false, 'Next treatment must be scheduled after your previous appointment date.');
                        }
                        
                        // Check if at least 28 days apart
                        $interval = $previousDate->diff($selectedDate);
                        $daysDiff = (int)$interval->format('%a');
                        
                        if ($daysDiff < 28) {
                            $pdo->rollBack();
                            jsonResponse(false, 'Next treatment must be scheduled at least 28 days after your previous appointment.');
                        }
                    }
                    
                    // Update installment with scheduled date and time
                    $stmt = $pdo->prepare("
                        UPDATE {$quotedInstallmentsTable}
                        SET scheduled_date = ?, 
                            scheduled_time = ?,
                            status = CASE 
                                WHEN status = 'book_visit' THEN 'pending'
                                WHEN status = 'paid' THEN 'pending'
                                ELSE status
                            END,
                            updated_at = NOW()
                        WHERE booking_id = ? AND installment_number = ?
                    ");
                    $stmt->execute([
                        $data['appointment_date'],
                        $data['appointment_time'],
                        $followupBookingId,
                        $followupInstallmentNumber
                    ]);
                    
                    $pdo->commit();
                    
                    jsonResponse(true, 'Follow-up appointment scheduled successfully. Our team will contact you to confirm.', [
                        'booking_id' => $followupBookingId,
                        'installment_number' => $followupInstallmentNumber,
                        'appointment_date' => $data['appointment_date'],
                        'appointment_time' => $data['appointment_time'],
                        'installment_amount' => $installmentAmount,
                        'is_fully_paid' => $isFullyPaid
                    ]);
                    return;
                }
            }
            
            $pdo->rollBack();
            jsonResponse(false, 'Authentication required for follow-up appointments.');
            return;
        }
        
        // Get user ID if logged in
        $createdBy = getCurrentUserId();
        $createdByUserId = null;
        if ($createdBy) {
            $stmt = $pdo->prepare("SELECT user_id FROM {$quotedUsersTable} WHERE user_id = ?");
            $stmt->execute([$createdBy]);
            $user = $stmt->fetch();
            if ($user) {
                $createdByUserId = $user['user_id'];
            }
        }
        
        // Generate booking ID
        $bookingId = generateBookingId();
        
        $resolvedTreatmentId = trim((string) ($data['treatment_id'] ?? ''));
        $pendingInstallmentTreatmentData = null;

        // Retry loop for potential duplicates (shouldn't happen with new format, but safety check)
        $maxRetries = 5;
        $retryCount = 0;
        
        while ($retryCount < $maxRetries) {
            try {
                // Format service information
                $serviceType = '';
                $serviceDescription = '';
                $totalPrice = 0;
                $normalizedServiceRows = [];
                
                // Check if any service is from Orthodontics category
                $hasOrthodontics = false;
                if (!empty($data['services']) && is_array($data['services'])) {
                    foreach ($data['services'] as $service) {
                        $category = isset($service['category']) ? strtolower($service['category']) : '';
                        if ($category === 'orthodontics') {
                            $hasOrthodontics = true;
                            break;
                        }
                    }
                }
                
                // Determine treatment type
                $treatmentType = $hasOrthodontics ? 'long_term' : 'short_term';
                
                if (!empty($data['services']) && is_array($data['services'])) {
                    // New format: Multiple services selected.
                    // For StaffSetAppointments bookings, normalize service rows so we can persist
                    // appointment line items (same behavior pattern used by walk-ins).
                        $serviceCatalogStmt = null;
                    if ($isStaffSetAppointmentBooking && $appointmentServicesTableName !== null && $servicesCatalogTableName !== null) {
                        $quotedServicesCatalogTable = clinic_quote_identifier((string) $servicesCatalogTableName);
                        $serviceCatalogStmt = $pdo->prepare("
                            SELECT service_id, service_name, price, enable_installment, service_type, installment_duration_months, service_duration, buffer_time
                            FROM {$quotedServicesCatalogTable}
                            WHERE tenant_id = ?
                              AND service_id = ?
                            LIMIT 1
                        ");
                    }
                    $serviceNames = [];
                    foreach ($data['services'] as $service) {
                        if (!is_array($service)) {
                            continue;
                        }
                        $serviceId = trim((string) ($service['id'] ?? $service['service_id'] ?? ''));
                        $rowName = trim((string) ($service['name'] ?? $service['service_name'] ?? ''));
                        $rowPrice = isset($service['price']) ? (float) $service['price'] : 0.0;
                        $inputServiceType = strtolower(trim((string) ($service['service_type'] ?? '')));
                        $isIncludedPlanRow = $inputServiceType === 'included_plan';
                        $isInstallmentRow = false;
                        if ($serviceCatalogStmt !== null && $serviceId !== '') {
                            $serviceCatalogStmt->execute([$tenantId, $serviceId]);
                            $serviceCatalogRow = $serviceCatalogStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                            if (is_array($serviceCatalogRow)) {
                                $catalogName = trim((string) ($serviceCatalogRow['service_name'] ?? ''));
                                if ($catalogName !== '') {
                                    $rowName = $catalogName;
                                }
                                $catalogServiceType = strtolower(trim((string) ($serviceCatalogRow['service_type'] ?? '')));
                                if ($catalogServiceType === 'included_plan') {
                                    $isIncludedPlanRow = true;
                                }
                                $isInstallmentRow = !$isIncludedPlanRow
                                    && (!empty($serviceCatalogRow['enable_installment']) || $catalogServiceType === 'installment');
                                $catalogPrice = isset($serviceCatalogRow['price']) ? (float) $serviceCatalogRow['price'] : $rowPrice;
                                $rowPrice = $isIncludedPlanRow ? 0.0 : $catalogPrice;
                            }
                        }
                        if ($rowName === '') {
                            continue;
                        }
                        $serviceNames[] = $rowName;
                        $totalPrice += $rowPrice;
                        $normalizedServiceRows[] = [
                            'service_id' => $serviceId,
                            'service_name' => $rowName,
                            'price' => $rowPrice,
                            'service_type' => $isIncludedPlanRow ? 'included_plan' : ($isInstallmentRow ? 'installment' : 'regular'),
                            'installment_duration_months' => isset($serviceCatalogRow['installment_duration_months'])
                                ? (int) $serviceCatalogRow['installment_duration_months']
                                : 0,
                            'service_duration' => isset($serviceCatalogRow['service_duration'])
                                ? (int) $serviceCatalogRow['service_duration']
                                : 0,
                            'buffer_time' => isset($serviceCatalogRow['buffer_time'])
                                ? (int) $serviceCatalogRow['buffer_time']
                                : 0,
                        ];
                    }
                    
                    $serviceType = implode(', ', array_slice($serviceNames, 0, 3)); // First 3 services
                    if (count($serviceNames) > 3) {
                        $serviceType .= ' (+' . (count($serviceNames) - 3) . ' more)';
                    }
                    
                    // Create detailed description with prices
                    $serviceDetails = [];
                    foreach ($normalizedServiceRows as $serviceRow) {
                        $serviceDetails[] = (string) ($serviceRow['service_name'] ?? '')
                            . ' (₱' . number_format((float) ($serviceRow['price'] ?? 0), 2) . ')';
                    }
                    
                    $serviceDescription = implode('; ', $serviceDetails);
                    if (!empty($data['service_categories'])) {
                        $serviceDescription .= ' | Categories: ' . implode(', ', $data['service_categories']);
                    }
                    if (!empty($data['insurance']) && $data['insurance'] !== 'none') {
                        $serviceDescription .= ' | Insurance: ' . $data['insurance'];
                    }
                    $serviceDescription .= ' | Total: ₱' . number_format($totalPrice, 2);
                } else {
                    // Legacy format: Single procedure
                    $serviceType = $data['procedure'];
                    $serviceDescription = $data['procedure'];
                    if (!empty($data['insurance']) && $data['insurance'] !== 'none') {
                        $serviceDescription .= ' (Insurance: ' . $data['insurance'] . ')';
                    }
                }

                $catalogMaxInstallmentDuration = 0;
                foreach ($normalizedServiceRows as $_catRow) {
                    $catalogMaxInstallmentDuration = max(
                        $catalogMaxInstallmentDuration,
                        (int) ($_catRow['installment_duration_months'] ?? 0)
                    );
                }
                
                // Prepare treatment cost and long-term treatment fields
                // Always set total_treatment_cost when there's a price, regardless of treatment type
                $totalTreatmentCost = ($totalPrice > 0) ? $totalPrice : null;
                $durationMonths = null;
                $targetCompletionDate = null;
                $startDate = null;
                
                if ($treatmentType === 'long_term' && $catalogMaxInstallmentDuration > 0) {
                    // Align with tbl_services.installment_duration_months (same source as tbl_treatments rows below).
                    $durationMonths = $catalogMaxInstallmentDuration;
                    $startDate = $data['appointment_date'];
                    $startDateObj = new DateTime((string) $startDate);
                    $targetCompletionDateObj = clone $startDateObj;
                    $targetCompletionDateObj->modify('+' . (int) $durationMonths . ' months');
                    $targetCompletionDate = $targetCompletionDateObj->format('Y-m-d');
                }

                $primaryInstallmentServiceRow = null;
                foreach ($normalizedServiceRows as $serviceRow) {
                    if (strtolower(trim((string) ($serviceRow['service_type'] ?? 'regular'))) === 'installment') {
                        $primaryInstallmentServiceRow = $serviceRow;
                        break;
                    }
                }
                $hasInstallmentService = $primaryInstallmentServiceRow !== null;
                if ($isStaffSetAppointmentBooking && $hasInstallmentService && $resolvedTreatmentId === '' && $treatmentsTableName !== '') {
                    $resolvedTreatmentId = generateTreatmentId();
                    if ($resolvedTreatmentId !== '') {
                        $primaryDur = max(0, (int) ($primaryInstallmentServiceRow['installment_duration_months'] ?? 0));
                        $durationFromService = $catalogMaxInstallmentDuration > 0
                            ? $catalogMaxInstallmentDuration
                            : $primaryDur;
                        $pendingInstallmentTreatmentData = [
                            'tenant_id' => $tenantId,
                            'treatment_id' => $resolvedTreatmentId,
                            'patient_id' => (string) $data['patient_id'],
                            'primary_service_id' => trim((string) ($primaryInstallmentServiceRow['service_id'] ?? '')),
                            'primary_service_name' => trim((string) ($primaryInstallmentServiceRow['service_name'] ?? '')),
                            'total_cost' => (float) ($primaryInstallmentServiceRow['price'] ?? 0),
                            'amount_paid' => 0.0,
                            'remaining_balance' => (float) ($primaryInstallmentServiceRow['price'] ?? 0),
                            'duration_months' => $durationFromService,
                            'months_paid' => 0,
                            'months_left' => $durationFromService,
                            'status' => 'active',
                            'started_at' => (string) ($data['appointment_date'] ?? date('Y-m-d')),
                            'created_by' => $createdByUserId,
                        ];
                    }
                }
                
                // Insert appointment with dynamic column support across table variants.
                $apptCols = clinic_table_columns($pdo, (string) $appointmentsTableName);
                $statusForInsert = strtolower(trim((string) ($data['status'] ?? 'pending')));
                if ($statusForInsert === '' || $statusForInsert === 'scheduled' || $statusForInsert === 'confirmed') {
                    $statusForInsert = 'pending';
                }
                $visitTypeForInsert = strtolower(trim((string) ($data['visit_type'] ?? 'pre_book')));
                if ($visitTypeForInsert === 'walkin' || $visitTypeForInsert === 'walk-in') {
                    $visitTypeForInsert = 'walk_in';
                } elseif ($visitTypeForInsert !== 'walk_in') {
                    $visitTypeForInsert = 'pre_book';
                }
                if (in_array('visit_type', $apptCols, true)) {
                    $visitTypeEnumValues = getEnumValues($pdo, (string) $appointmentsTableName, 'visit_type');
                    if ($visitTypeEnumValues !== [] && !in_array($visitTypeForInsert, $visitTypeEnumValues, true)) {
                        if (in_array('pre_book', $visitTypeEnumValues, true)) {
                            $visitTypeForInsert = 'pre_book';
                        } elseif (in_array('scheduled', $visitTypeEnumValues, true)) {
                            $visitTypeForInsert = 'scheduled';
                        } elseif (in_array('appointment', $visitTypeEnumValues, true)) {
                            $visitTypeForInsert = 'appointment';
                        } else {
                            $visitTypeForInsert = (string) $visitTypeEnumValues[0];
                        }
                    }
                }
                $normalizedDentistId = $data['dentist_id'] !== null ? trim((string) $data['dentist_id']) : '';
                if ($normalizedDentistId !== '') {
                    $dentistIdColumnType = getColumnType($pdo, (string) $appointmentsTableName, 'dentist_id');
                    if ($dentistIdColumnType !== '' && preg_match('/^(tinyint|smallint|mediumint|int|bigint)\b/', $dentistIdColumnType)) {
                        if (!ctype_digit($normalizedDentistId)) {
                            $resolvedNumericDentistId = '';
                            $dentistsTableForId = clinic_get_physical_table_name($pdo, 'tbl_dentists')
                                ?? clinic_get_physical_table_name($pdo, 'dentists');
                            if ($dentistsTableForId !== null) {
                                $quotedDentistsTableForId = clinic_quote_identifier((string) $dentistsTableForId);
                                if (!empty($data['dentist_user_id'])) {
                                    $resolveByUserStmt = $pdo->prepare("
                                        SELECT dentist_id
                                        FROM {$quotedDentistsTableForId}
                                        WHERE tenant_id = ?
                                          AND user_id = ?
                                        LIMIT 1
                                    ");
                                    $resolveByUserStmt->execute([$tenantId, trim((string) $data['dentist_user_id'])]);
                                    $resolveByUser = $resolveByUserStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                                    $resolvedNumericDentistId = trim((string) ($resolveByUser['dentist_id'] ?? ''));
                                }
                            }
                            if ($resolvedNumericDentistId !== '' && ctype_digit($resolvedNumericDentistId)) {
                                $normalizedDentistId = $resolvedNumericDentistId;
                            } else {
                                throw new Exception('Please select a valid assigned dentist before scheduling.');
                            }
                        }
                    }
                }

                $requestedStartMinutes = clinic_time_to_minutes((string) ($data['appointment_time'] ?? ''));
                $requestedDurationMinutes = clinic_compute_requested_duration_minutes($normalizedServiceRows);
                if ($normalizedDentistId !== '' && $requestedStartMinutes !== null) {
                    $requestedEndMinutes = $requestedStartMinutes + max(1, $requestedDurationMinutes);
                    $conflictStmt = $pdo->prepare("
                        SELECT id, booking_id, appointment_time
                        FROM {$quotedAppointmentsTable}
                        WHERE tenant_id = ?
                          AND dentist_id = ?
                          AND appointment_date = ?
                          AND LOWER(COALESCE(status, 'pending')) NOT IN ('cancelled', 'no_show', 'completed')
                    ");
                    $conflictStmt->execute([$tenantId, $normalizedDentistId, $data['appointment_date']]);
                    $conflictRows = $conflictStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    foreach ($conflictRows as $conflictRow) {
                        $existingStartMinutes = clinic_time_to_minutes((string) ($conflictRow['appointment_time'] ?? ''));
                        if ($existingStartMinutes === null) {
                            continue;
                        }
                        $existingDurationMinutes = clinic_fetch_existing_appointment_duration_minutes(
                            $pdo,
                            $tenantId,
                            (string) ($conflictRow['booking_id'] ?? ''),
                            (int) ($conflictRow['id'] ?? 0),
                            $appointmentServicesTableName,
                            $servicesCatalogTableName
                        );
                        $existingEndMinutes = $existingStartMinutes + max(1, $existingDurationMinutes);
                        $isOverlap = $requestedStartMinutes < $existingEndMinutes && $existingStartMinutes < $requestedEndMinutes;
                        if ($isOverlap) {
                            $pdo->rollBack();
                            jsonResponse(false, 'There is already an existing appointment scheduled for this dentist at the selected time. Please choose a different time slot.');
                        }
                    }
                }

                $apptData = [
                    'tenant_id' => $tenantId,
                    'booking_id' => $bookingId,
                    'patient_id' => $data['patient_id'],
                    'treatment_id' => $resolvedTreatmentId !== '' ? $resolvedTreatmentId : null,
                    'dentist_id' => $normalizedDentistId !== '' ? $normalizedDentistId : null,
                    'appointment_date' => $data['appointment_date'],
                    'appointment_time' => $data['appointment_time'],
                    'service_type' => $serviceType,
                    'service_description' => $serviceDescription,
                    'treatment_type' => $treatmentType,
                    'visit_type' => $visitTypeForInsert,
                    'status' => $statusForInsert,
                    'notes' => $data['notes'],
                    'total_treatment_cost' => $totalTreatmentCost,
                    'duration_months' => $durationMonths,
                    'target_completion_date' => $targetCompletionDate,
                    'start_date' => $startDate,
                    'created_by' => $createdByUserId,
                ];
                $columnOrder = [
                    'tenant_id', 'dentist_id', 'booking_id', 'patient_id', 'treatment_id',
                    'appointment_date', 'appointment_time', 'service_type', 'service_description',
                    'treatment_type', 'visit_type', 'status', 'notes', 'total_treatment_cost',
                    'duration_months', 'target_completion_date', 'start_date', 'created_by'
                ];
                $insertCols = [];
                $insertPlaceholders = [];
                $insertParams = [];
                foreach ($columnOrder as $col) {
                    if (!in_array($col, $apptCols, true) || !array_key_exists($col, $apptData)) {
                        continue;
                    }
                    $insertCols[] = clinic_quote_identifier($col);
                    $insertPlaceholders[] = '?';
                    $insertParams[] = $apptData[$col];
                }
                if (in_array('created_at', $apptCols, true)) {
                    $insertCols[] = clinic_quote_identifier('created_at');
                    $insertPlaceholders[] = 'NOW()';
                }
                if ($insertCols === []) {
                    throw new Exception('Appointments table has no compatible columns for insert.');
                }
                $insertSql = 'INSERT INTO ' . $quotedAppointmentsTable
                    . ' (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute($insertParams);
                $appointmentRowId = (int) $pdo->lastInsertId();

                if (
                    $isStaffSetAppointmentBooking
                    && $visitTypeForInsert === 'pre_book'
                    && $appointmentServicesTableName !== null
                    && $normalizedServiceRows !== []
                ) {
                    $appointmentServiceColumns = clinic_table_columns($pdo, (string) $appointmentServicesTableName);
                    $quotedAppointmentServicesTable = clinic_quote_identifier((string) $appointmentServicesTableName);
                    $lineCols = ['tenant_id', 'booking_id', 'service_id', 'service_name', 'price'];
                    $lineVals = ['?', '?', '?', '?', '?'];
                    if (in_array('appointment_id', $appointmentServiceColumns, true)) {
                        $lineCols[] = 'appointment_id';
                        $lineVals[] = '?';
                    }
                    if (in_array('treatment_id', $appointmentServiceColumns, true)) {
                        $lineCols[] = 'treatment_id';
                        $lineVals[] = '?';
                    }
                    if (in_array('service_type', $appointmentServiceColumns, true)) {
                        $lineCols[] = 'service_type';
                        $lineVals[] = '?';
                    }
                    if (in_array('type', $appointmentServiceColumns, true)) {
                        $lineCols[] = 'type';
                        $lineVals[] = '?';
                    }
                    if (in_array('is_original', $appointmentServiceColumns, true)) {
                        $lineCols[] = 'is_original';
                        $lineVals[] = '?';
                    }
                    if (in_array('added_by', $appointmentServiceColumns, true)) {
                        $lineCols[] = 'added_by';
                        $lineVals[] = '?';
                    }
                    if (in_array('added_at', $appointmentServiceColumns, true)) {
                        $lineCols[] = 'added_at';
                        $lineVals[] = 'NOW()';
                    }
                    $lineInsertSql = 'INSERT INTO ' . $quotedAppointmentServicesTable
                        . ' (' . implode(', ', array_map('clinic_quote_identifier', $lineCols)) . ')'
                        . ' VALUES (' . implode(', ', $lineVals) . ')';
                    $lineInsertStmt = $pdo->prepare($lineInsertSql);
                    foreach ($normalizedServiceRows as $serviceRow) {
                        $lineServiceType = strtolower(trim((string) ($serviceRow['service_type'] ?? 'regular')));
                        $isInstallmentLine = $lineServiceType === 'installment';
                        $lineParams = [
                            $tenantId,
                            $bookingId,
                            trim((string) ($serviceRow['service_id'] ?? '')),
                            trim((string) ($serviceRow['service_name'] ?? '')),
                            (float) ($serviceRow['price'] ?? 0),
                        ];
                        if (in_array('appointment_id', $appointmentServiceColumns, true)) {
                            $lineParams[] = $appointmentRowId > 0 ? $appointmentRowId : null;
                        }
                        if (in_array('treatment_id', $appointmentServiceColumns, true)) {
                            $lineParams[] = $isInstallmentLine
                                ? ($resolvedTreatmentId !== '' ? $resolvedTreatmentId : null)
                                : null;
                        }
                        if (in_array('service_type', $appointmentServiceColumns, true)) {
                            $lineParams[] = $isInstallmentLine
                                ? 'installment'
                                : ($lineServiceType === 'included_plan' ? 'included_plan' : 'regular');
                        }
                        if (in_array('type', $appointmentServiceColumns, true)) {
                            $lineParams[] = $isInstallmentLine
                                ? 'Long Term'
                                : 'Short Term';
                        }
                        if (in_array('is_original', $appointmentServiceColumns, true)) {
                            $lineParams[] = 1;
                        }
                        if (in_array('added_by', $appointmentServiceColumns, true)) {
                            $lineParams[] = $createdByUserId;
                        }
                        $lineInsertStmt->execute($lineParams);
                    }
                }
                
                // Success - break out of retry loop
                break;
                
            } catch (PDOException $e) {
                // Check if it's a duplicate key error (code 23000)
                if ($e->getCode() == '23000' && strpos($e->getMessage(), 'booking_id') !== false) {
                    $retryCount++;
                    if ($retryCount >= $maxRetries) {
                        throw new Exception('Failed to generate unique booking ID after multiple attempts.');
                    }
                    // Wait a tiny bit and retry (in case of race condition)
                    usleep(100000); // 0.1 seconds
                } elseif ($e->getCode() == '23000' && (
                    stripos($e->getMessage(), 'unique_dentist_schedule') !== false
                    || stripos($e->getMessage(), 'dentist_id') !== false && stripos($e->getMessage(), 'appointment_date') !== false
                )) {
                    throw new Exception('The selected dentist already has a booking at that date/time. Please pick a different time.');
                } elseif ($e->getCode() == '23000' && (
                    stripos($e->getMessage(), 'foreign key') !== false
                    || stripos($e->getMessage(), 'cannot add or update a child row') !== false
                )) {
                    throw new Exception('One or more selected booking details are invalid. Please reselect patient, dentist, and services.');
                } else {
                    // Different error - rethrow
                    throw $e;
                }
            }
        }
        
        $appointmentId = $pdo->lastInsertId();

        if (
            $pendingInstallmentTreatmentData !== null
            && $resolvedTreatmentId !== ''
            && $treatmentsTableName !== ''
        ) {
            $quotedTreatmentsTable = clinic_quote_identifier((string) $treatmentsTableName);
            $treatCols = clinic_table_columns($pdo, (string) $treatmentsTableName);

            $existsStmt = $pdo->prepare("
                SELECT 1
                FROM {$quotedTreatmentsTable}
                WHERE tenant_id = ?
                  AND treatment_id = ?
                LIMIT 1
            ");
            $existsStmt->execute([$tenantId, $resolvedTreatmentId]);
            $alreadyExists = (bool) $existsStmt->fetchColumn();

            if (!$alreadyExists) {
                $insertTreatCols = [];
                $insertTreatVals = [];
                $insertTreatParams = [];
                $treatOrder = [
                    'tenant_id', 'treatment_id', 'patient_id',
                    'primary_service_id', 'primary_service_name',
                    'total_cost', 'amount_paid', 'remaining_balance',
                    'duration_months', 'months_paid', 'months_left',
                    'status', 'started_at', 'created_by'
                ];
                foreach ($treatOrder as $col) {
                    if (!in_array($col, $treatCols, true) || !array_key_exists($col, $pendingInstallmentTreatmentData)) {
                        continue;
                    }
                    $insertTreatCols[] = clinic_quote_identifier($col);
                    $insertTreatVals[] = '?';
                    $insertTreatParams[] = $pendingInstallmentTreatmentData[$col];
                }
                if (in_array('created_at', $treatCols, true)) {
                    $insertTreatCols[] = clinic_quote_identifier('created_at');
                    $insertTreatVals[] = 'NOW()';
                }
                if ($insertTreatCols !== []) {
                    $insertTreatSql = 'INSERT INTO ' . $quotedTreatmentsTable
                        . ' (' . implode(', ', $insertTreatCols) . ') VALUES (' . implode(', ', $insertTreatVals) . ')';
                    $insertTreatStmt = $pdo->prepare($insertTreatSql);
                    $insertTreatStmt->execute($insertTreatParams);
                }
            }
        }
        
        // Create installments for long-term treatments ONLY if payment option is 'downpayment'
        // Full payment long-term treatments should NOT have installments
        if ($treatmentType === 'long_term' && $totalTreatmentCost > 0 && $durationMonths !== null && (int) $durationMonths > 0) {
            $paymentOption = isset($data['orthodontics_requirements']['payment_option']) 
                ? $data['orthodontics_requirements']['payment_option'] 
                : null;
            
            // Only create installments if payment option is 'downpayment'
            if ($paymentOption === 'downpayment') {
                createInstallments($pdo, $bookingId, $totalTreatmentCost, (int) $durationMonths, $data['appointment_date'], $data['orthodontics_requirements']);
            }
            // If payment_option is 'full', no installments are created, but treatment_type remains 'long_term'
        }
        
        $pdo->commit();

        // Fallback trigger: process due reminders right after create so same-day
        // bookings are still handled if cron is delayed on some environments.
        if ($visitTypeForInsert !== 'walk_in' && function_exists('send_scheduled_appointment_reminders')) {
            try {
                $reminderRun = send_scheduled_appointment_reminders($pdo, (string) $tenantId);
                error_log('Appointment create reminder run: ' . json_encode([
                    'booking_id' => $bookingId,
                    'tenant_id' => $tenantId,
                    'ok' => (bool) ($reminderRun['ok'] ?? false),
                    'sent' => $reminderRun['sent'] ?? [],
                    'errors' => $reminderRun['errors'] ?? [],
                ], JSON_UNESCAPED_SLASHES));
            } catch (Throwable $reminderError) {
                error_log('Appointment create reminder fallback failed. booking=' . $bookingId . ' error=' . $reminderError->getMessage());
            }
        }
        
        jsonResponse(true, 'Appointment request submitted successfully. Our team will contact you to confirm.', [
            'appointment_id' => $appointmentId,
            'booking_id' => $bookingId,
            'patient_id' => $data['patient_id'],
            'treatment_type' => $treatmentType
        ]);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Appointment creation error: ' . $e->getMessage());
        $safeMessage = trim((string) $e->getMessage());
        if ($safeMessage === '' || stripos($safeMessage, 'sqlstate') !== false) {
            $safeMessage = 'Failed to create appointment. Please try again.';
        }
        jsonResponse(false, $safeMessage);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Appointment creation fatal error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to create appointment. Please try again.');
    }
}


/**
 * Calculate pending balance for a booking
 * @param string $bookingId Booking ID
 * @param PDO $pdo Database connection
 * @return array ['total_cost' => float, 'total_paid' => float, 'pending_balance' => float]
 */
function calculateBookingBalance($bookingId, $pdo) {
    global $appointmentsTableName;
    // Get total treatment cost from appointment
    $tenantId = getClinicTenantId();
    $quotedAppointmentsTable = clinic_quote_identifier((string) $appointmentsTableName);
    $stmt = $pdo->prepare("SELECT total_treatment_cost FROM {$quotedAppointmentsTable} WHERE booking_id = ? AND tenant_id = ?");
    $stmt->execute([$bookingId, $tenantId]);
    $appointment = $stmt->fetch();
    $totalCost = $appointment ? floatval($appointment['total_treatment_cost'] ?? 0) : 0;
    
    // If no total cost, return zero balance
    if ($totalCost <= 0) {
        return [
            'total_cost' => 0,
            'total_paid' => 0,
            'pending_balance' => 0
        ];
    }
    
    // Calculate total paid (sum of all completed payments for this booking)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_paid
        FROM payments
        WHERE booking_id = ? AND status = 'completed' AND tenant_id = ?
    ");
    $stmt->execute([$bookingId, $tenantId]);
    $result = $stmt->fetch();
    $totalPaid = floatval($result['total_paid'] ?? 0);
    
    $pendingBalance = max(0, $totalCost - $totalPaid);
    
    return [
        'total_cost' => $totalCost,
        'total_paid' => $totalPaid,
        'pending_balance' => $pendingBalance
    ];
}

/**
 * Compute final appointment status without payment coupling.
 * @param array $appointment Appointment data
 * @param PDO $pdo Database connection (unused, kept for compatibility)
 * @param int|null $installmentNumber Installment number (unused, kept for compatibility)
 * @return string Final status (PENDING, IN_PROGRESS, COMPLETED, CANCELLED, NO_SHOW)
 */
function computeAppointmentStatus($appointment, $pdo, $installmentNumber = null) {
    $appointmentStatus = strtolower(trim((string) ($appointment['status'] ?? 'pending')));
    if ($appointmentStatus === 'scheduled' || $appointmentStatus === 'confirmed') {
        $appointmentStatus = 'pending';
    }
    if (!in_array($appointmentStatus, ['pending', 'in_progress', 'completed', 'cancelled', 'no_show'], true)) {
        $appointmentStatus = 'pending';
    }
    if ($appointmentStatus === 'no_show') {
        return 'NO_SHOW';
    }
    return strtoupper($appointmentStatus);
}

/**
 * Get appointments
 */
function getAppointments() {
    global $pdo, $tenantId, $appointmentsTableName, $patientsTableName, $usersTableName, $installmentsTableName, $dentistsTableName;
    $quotedAppointmentsTable = clinic_quote_identifier((string) $appointmentsTableName);
    $quotedPatientsTable = clinic_quote_identifier((string) $patientsTableName);
    $quotedUsersTable = clinic_quote_identifier((string) $usersTableName);
    $quotedInstallmentsTable = clinic_quote_identifier((string) $installmentsTableName);
    $quotedDentistsTable = clinic_quote_identifier((string) $dentistsTableName);
    
    $userId = getCurrentUserId();
    $userType = $_SESSION['user_type'] ?? 'client';
    
    // Get query parameters
    $patientId = isset($_GET['patient_id']) ? sanitize($_GET['patient_id']) : null;
    $bookingId = isset($_GET['booking_id']) ? sanitize($_GET['booking_id']) : null;
    $status = isset($_GET['status']) ? sanitize($_GET['status']) : null;
    $date = isset($_GET['date']) ? sanitize($_GET['date']) : null;
    
    
    try {
        // Special handling for date-based queries (Daily Schedule)
        if ($date && ($userType === 'manager' || $userType === 'doctor' || $userType === 'staff')) {
            // Fetch all bookings for the selected date:
            // 1. Main appointments with appointment_date = date
            // 2. Follow-up visits (installments) with scheduled_date = date
            
            $allBookings = [];
            
            // Fetch main appointments for this date
            $sql = "
                SELECT a.*, 
                       p.first_name,
                       p.last_name,
                       p.contact_number,
                       p.patient_id as patient_display_id,
                       TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))) AS dentist_name,
                       u.email,
                       u2.email as created_by_email
                FROM {$quotedAppointmentsTable} a
                LEFT JOIN {$quotedPatientsTable} p ON a.patient_id = p.patient_id
                LEFT JOIN {$quotedDentistsTable} d
                    ON d.tenant_id = a.tenant_id
                   AND TRIM(CAST(d.dentist_id AS CHAR)) = TRIM(CAST(a.dentist_id AS CHAR))
                LEFT JOIN {$quotedUsersTable} u ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id
                LEFT JOIN {$quotedUsersTable} u2 ON a.created_by = u2.user_id
                WHERE a.appointment_date = ?
                  AND a.tenant_id = ?
            ";
            
            $params = [$date, $tenantId];
            
            if ($patientId) {
                $sql .= " AND a.patient_id = ?";
                $params[] = $patientId;
            }
            
            if ($bookingId) {
                $sql .= " AND a.booking_id = ?";
                $params[] = $bookingId;
            }
            
            $sql .= " ORDER BY a.appointment_time ASC";
            
            $stmt = $pdo->prepare($sql);
            if (!$stmt) {
                $errorInfo = $pdo->errorInfo();
                error_log("PDO prepare error: " . print_r($errorInfo, true));
                throw new Exception("Failed to prepare query: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            $stmt->execute($params);
            if ($stmt->errorCode() !== '00000') {
                $errorInfo = $stmt->errorInfo();
                error_log("PDO execute error: " . print_r($errorInfo, true));
                throw new Exception("Query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            $mainAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resolvedLineTablesStaff = clinic_resolve_appointment_db_tables($pdo);
            $appointmentServicesTableForAgg = $resolvedLineTablesStaff['appointment_services'] ?? null;
            $servicesCatalogTableForAgg = $resolvedLineTablesStaff['services'] ?? null;

            foreach ($mainAppointments as &$aptAugment) {
                clinic_staff_booking_augment_combined_services(
                    $pdo,
                    $aptAugment,
                    $tenantId,
                    $appointmentServicesTableForAgg,
                    $servicesCatalogTableForAgg
                );
            }
            unset($aptAugment);
            
            // Add main appointments to results
            foreach ($mainAppointments as $apt) {
                try {
                    $finalStatus = computeAppointmentStatus($apt, $pdo, null);
                    $apt['final_status'] = $finalStatus;
                    
                    // Calculate balance information
                    $balance = calculateBookingBalance($apt['booking_id'], $pdo);
                    $apt['total_cost'] = $balance['total_cost'];
                    $apt['total_paid'] = $balance['total_paid'];
                    $apt['pending_balance'] = $balance['pending_balance'];
                } catch (Exception $e) {
                    error_log('Error computing status for appointment ' . ($apt['booking_id'] ?? 'unknown') . ': ' . $e->getMessage());
                    error_log('Appointment data: ' . print_r($apt, true));
                    // Default to PENDING if status computation fails
                    $apt['final_status'] = 'PENDING';
                    $apt['total_cost'] = 0;
                    $apt['total_paid'] = 0;
                    $apt['pending_balance'] = 0;
                } catch (PDOException $e) {
                    error_log('PDO Error computing status: ' . $e->getMessage());
                    $apt['final_status'] = 'PENDING';
                    $apt['total_cost'] = 0;
                    $apt['total_paid'] = 0;
                    $apt['pending_balance'] = 0;
                }
                $apt['is_installment_visit'] = false;
                $allBookings[] = $apt;
            }
            
            // Fetch follow-up visits (installments) for this date
            $sql = "
                SELECT a.*, 
                       p.first_name,
                       p.last_name,
                       p.contact_number,
                       p.patient_id as patient_display_id,
                       TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))) AS dentist_name,
                       u.email,
                       u2.email as created_by_email,
                       i.installment_number,
                       i.scheduled_date as installment_scheduled_date,
                       i.scheduled_time as installment_scheduled_time,
                       i.status as installment_status
                FROM {$quotedInstallmentsTable} i
                INNER JOIN {$quotedAppointmentsTable} a ON i.booking_id = a.booking_id
                LEFT JOIN {$quotedPatientsTable} p ON a.patient_id = p.patient_id
                LEFT JOIN {$quotedDentistsTable} d
                    ON d.tenant_id = a.tenant_id
                   AND TRIM(CAST(d.dentist_id AS CHAR)) = TRIM(CAST(a.dentist_id AS CHAR))
                LEFT JOIN {$quotedUsersTable} u ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id
                LEFT JOIN {$quotedUsersTable} u2 ON a.created_by = u2.user_id
                WHERE i.scheduled_date = ?
                  AND i.tenant_id = ?
                  AND a.tenant_id = ?
            ";
            
            $params = [$date, $tenantId, $tenantId];
            
            if ($patientId) {
                $sql .= " AND a.patient_id = ?";
                $params[] = $patientId;
            }
            
            if ($bookingId) {
                $sql .= " AND i.booking_id = ?";
                $params[] = $bookingId;
            }
            
            $sql .= " ORDER BY i.scheduled_time ASC";
            
            $stmt = $pdo->prepare($sql);
            if (!$stmt) {
                $errorInfo = $pdo->errorInfo();
                error_log("PDO prepare error: " . print_r($errorInfo, true));
                throw new Exception("Failed to prepare installment query: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            $stmt->execute($params);
            if ($stmt->errorCode() !== '00000') {
                $errorInfo = $stmt->errorInfo();
                error_log("PDO execute error: " . print_r($errorInfo, true));
                throw new Exception("Installment query execution failed: " . ($errorInfo[2] ?? 'Unknown error'));
            }
            
            $installmentVisits = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($installmentVisits as &$visitAugment) {
                clinic_staff_booking_augment_combined_services(
                    $pdo,
                    $visitAugment,
                    $tenantId,
                    $appointmentServicesTableForAgg,
                    $servicesCatalogTableForAgg
                );
            }
            unset($visitAugment);
            
            // Add installment visits to results
            foreach ($installmentVisits as $visit) {
                try {
                    $finalStatus = computeAppointmentStatus($visit, $pdo, $visit['installment_number']);
                    $visit['final_status'] = $finalStatus;
                    
                    // Calculate balance information
                    $balance = calculateBookingBalance($visit['booking_id'], $pdo);
                    $visit['total_cost'] = $balance['total_cost'];
                    $visit['total_paid'] = $balance['total_paid'];
                    $visit['pending_balance'] = $balance['pending_balance'];
                } catch (Exception $e) {
                    error_log('Error computing status for installment visit ' . ($visit['booking_id'] ?? 'unknown') . ': ' . $e->getMessage());
                    $visit['final_status'] = 'PENDING'; // Default fallback
                    $visit['total_cost'] = 0;
                    $visit['total_paid'] = 0;
                    $visit['pending_balance'] = 0;
                } catch (PDOException $e) {
                    error_log('PDO Error computing status for installment: ' . $e->getMessage());
                    $visit['final_status'] = 'PENDING';
                    $visit['total_cost'] = 0;
                    $visit['total_paid'] = 0;
                    $visit['pending_balance'] = 0;
                }
                $visit['is_installment_visit'] = true;
                // Use installment scheduled time/date for display
                $visit['appointment_time'] = $visit['installment_scheduled_time'];
                $visit['appointment_date'] = $visit['installment_scheduled_date'];
                $allBookings[] = $visit;
            }
            
            // Sort all bookings by time
            usort($allBookings, function($a, $b) {
                $timeA = $a['appointment_time'] ?? '00:00:00';
                $timeB = $b['appointment_time'] ?? '00:00:00';
                return strcmp($timeA, $timeB);
            });
            
            // Always return appointments array, even if empty
            jsonResponse(true, 'Appointments retrieved successfully.', [
                'appointments' => $allBookings
            ]);
            return;
        }
        
        // If date is provided but user is not admin, fall through to standard query
        // This ensures date filtering still works for non-admin users
        
        // Standard query (non-date-specific or client view)
        if ($userType === 'manager' || $userType === 'doctor' || $userType === 'staff') {
            // Admin can see all appointments
            $sql = "
                SELECT a.*, 
                       p.first_name,
                       p.last_name,
                       p.contact_number,
                       p.patient_id as patient_display_id,
                       TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))) AS dentist_name,
                       u.email,
                       u2.email as created_by_email
                FROM {$quotedAppointmentsTable} a
                LEFT JOIN {$quotedPatientsTable} p ON a.patient_id = p.patient_id
                LEFT JOIN {$quotedDentistsTable} d
                    ON d.tenant_id = a.tenant_id
                   AND TRIM(CAST(d.dentist_id AS CHAR)) = TRIM(CAST(a.dentist_id AS CHAR))
                LEFT JOIN {$quotedUsersTable} u ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id
                LEFT JOIN {$quotedUsersTable} u2 ON a.created_by = u2.user_id
                WHERE a.tenant_id = ?
            ";
            
            $params = [$tenantId];
            
            if ($patientId) {
                $sql .= " AND a.patient_id = ?";
                $params[] = $patientId;
            }
            
            if ($bookingId) {
                $sql .= " AND a.booking_id = ?";
                $params[] = $bookingId;
            }
            
            if ($status) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }
            
            if ($date) {
                $sql .= " AND a.appointment_date = ?";
                $params[] = $date;
            }
            
            $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
        } else {
            // Client can only see appointments for their owned patient profiles
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
            
            // Get all patient profiles owned by this user
            $sql = "
                SELECT a.*, 
                       p.first_name,
                       p.last_name,
                       p.contact_number,
                       p.patient_id as patient_display_id,
                       TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))) AS dentist_name,
                       u.email
                FROM {$quotedAppointmentsTable} a
                INNER JOIN {$quotedPatientsTable} p ON a.patient_id = p.patient_id
                LEFT JOIN {$quotedDentistsTable} d
                    ON d.tenant_id = a.tenant_id
                   AND TRIM(CAST(d.dentist_id AS CHAR)) = TRIM(CAST(a.dentist_id AS CHAR))
                LEFT JOIN {$quotedUsersTable} u ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id
                WHERE p.owner_user_id = ?
                  AND a.tenant_id = ?
                  AND p.tenant_id = ?
            ";
            
            $params = [$userUserId, $tenantId, $tenantId];
            
            if ($patientId) {
                $sql .= " AND a.patient_id = ?";
                $params[] = $patientId;
            }
            
            if ($bookingId) {
                $sql .= " AND a.booking_id = ?";
                $params[] = $bookingId;
            }
            
            if ($status) {
                $sql .= " AND a.status = ?";
                $params[] = $status;
            }
            
            if ($date) {
                $sql .= " AND a.appointment_date = ?";
                $params[] = $date;
            }
            
            $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Compute final status for each appointment
        foreach ($appointments as &$apt) {
            $apt['final_status'] = computeAppointmentStatus($apt, $pdo, null);
            
            // Calculate balance information
            try {
                $balance = calculateBookingBalance($apt['booking_id'], $pdo);
                $apt['total_cost'] = $balance['total_cost'];
                $apt['total_paid'] = $balance['total_paid'];
                $apt['pending_balance'] = $balance['pending_balance'];
            } catch (Exception $e) {
                error_log('Error calculating balance for appointment ' . ($apt['booking_id'] ?? 'unknown') . ': ' . $e->getMessage());
                $apt['total_cost'] = 0;
                $apt['total_paid'] = 0;
                $apt['pending_balance'] = 0;
            }
        }
        
        jsonResponse(true, 'Appointments retrieved successfully.', [
            'appointments' => $appointments
        ]);
        
    } catch (PDOException $e) {
        error_log('Get appointments PDO error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        error_log('PDO Error Info: ' . print_r($e->errorInfo ?? [], true));
        // Always show error details for debugging
        jsonResponse(false, 'Database error: ' . $e->getMessage());
    } catch (Exception $e) {
        error_log('Get appointments error: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        // Always show error details for debugging
        jsonResponse(false, 'Error: ' . $e->getMessage());
    }
}

/**
 * Update appointment
 */
function updateAppointment() {
    global $pdo, $tenantId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $appointmentId = intval($input['id'] ?? 0);
    
    if (!$appointmentId) {
        jsonResponse(false, 'Appointment ID is required.');
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
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.patient_id
            WHERE a.id = ? AND a.tenant_id = ? AND p.tenant_id = ?
        ");
        $stmt->execute([$appointmentId, $tenantId, $tenantId]);
        $appointment = $stmt->fetch();
        
        if (!$appointment || $appointment['owner_user_id'] !== $userUserId) {
            jsonResponse(false, 'Unauthorized.');
        }
        
        // If client is trying to cancel, check if appointment has payments
        if (isset($input['status']) && strtolower($input['status']) === 'cancelled') {
            // Get booking_id from appointment
            $stmt = $pdo->prepare("SELECT booking_id FROM appointments WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$appointmentId, $tenantId]);
            $apptData = $stmt->fetch();
            
            if ($apptData && $apptData['booking_id']) {
                // Check if there are any completed payments for this booking
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM payments 
                    WHERE booking_id = ? 
                    AND status = 'completed'
                    AND tenant_id = ?
                ");
                $stmt->execute([$apptData['booking_id'], $tenantId]);
                $paymentCount = $stmt->fetchColumn();
                
                if ($paymentCount > 0) {
                    jsonResponse(false, 'Paid appointments cannot be cancelled online. Refunds or cancellations must be processed physically at the clinic.');
                }
            }
        }
    }
    
    // Extract updateable fields
    $updates = [];
    $params = [];
    
    if (isset($input['appointment_date'])) {
        $updates[] = "appointment_date = ?";
        $params[] = sanitize($input['appointment_date']);
    }
    
    if (isset($input['appointment_time'])) {
        $updates[] = "appointment_time = ?";
        $params[] = sanitize($input['appointment_time']);
    }
    
    if (isset($input['status'])) {
        $updates[] = "status = ?";
        $params[] = sanitize($input['status']);
    }
    
    if (isset($input['notes'])) {
        $updates[] = "notes = ?";
        $params[] = sanitize($input['notes']);
    }
    
    if (empty($updates)) {
        jsonResponse(false, 'No fields to update.');
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $appointmentId;
    
    try {
        $sql = "UPDATE appointments SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $params[] = $tenantId;
        $stmt->execute($params);
        
        jsonResponse(true, 'Appointment updated successfully.');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to update appointment.');
    }
}

/**
 * Delete appointment
 */
function deleteAppointment() {
    global $pdo, $tenantId;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $appointmentId = intval($input['id'] ?? 0);
    
    if (!$appointmentId) {
        jsonResponse(false, 'Appointment ID is required.');
    }
    
    // Only admin/doctor can delete
    $userType = $_SESSION['user_type'] ?? 'client';
    if ($userType !== 'manager' && $userType !== 'doctor' && $userType !== 'staff') {
        jsonResponse(false, 'Unauthorized. Only administrators, doctors, and staff can delete appointments.');
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$appointmentId, $tenantId]);
        
        jsonResponse(true, 'Appointment deleted successfully.');
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to delete appointment.');
    }
}

/**
 * Add extra services to an existing appointment
 * Only admin/staff can add services
 */
function addServicesToAppointment() {
    global $pdo, $tenantId;
    
    // Require admin/doctor authentication
    $userType = $_SESSION['user_type'] ?? 'client';
    if ($userType !== 'manager' && $userType !== 'doctor' && $userType !== 'staff') {
        jsonResponse(false, 'Unauthorized. Only administrators, doctors, and staff can add services to appointments.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $bookingId = isset($input['booking_id']) ? sanitize($input['booking_id']) : null;
    $services = isset($input['services']) && is_array($input['services']) ? $input['services'] : [];
    
    if (empty($bookingId)) {
        jsonResponse(false, 'Booking ID is required.');
    }
    
    if (empty($services)) {
        jsonResponse(false, 'At least one service must be selected.');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verify appointment exists and is not completed/cancelled
        $stmt = $pdo->prepare("
            SELECT id, booking_id, patient_id, service_type, service_description, 
                   total_treatment_cost, treatment_type, status
            FROM appointments 
            WHERE booking_id = ? AND tenant_id = ?
        ");
        $stmt->execute([$bookingId, $tenantId]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            $pdo->rollBack();
            jsonResponse(false, 'Appointment not found.');
        }
        
        // Don't allow adding services to completed or cancelled appointments
        if (in_array(strtolower($appointment['status']), ['completed', 'cancelled'])) {
            $pdo->rollBack();
            jsonResponse(false, 'Cannot add services to completed or cancelled appointments.');
        }
        
        // Get current user ID
        $userId = getCurrentUserId();
        $addedByUserId = null;
        if ($userId) {
            $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            if ($user) {
                $addedByUserId = $user['user_id'];
            }
        }
        
        // Get service details and calculate new total
        $newServices = [];
        $additionalCost = 0;
        $serviceNames = [];
        
        foreach ($services as $serviceId) {
            $serviceId = sanitize($serviceId);
            
            // Get service details
            $stmt = $pdo->prepare("SELECT service_id, service_name, price FROM services WHERE service_id = ? AND status = 'active' AND tenant_id = ?");
            $stmt->execute([$serviceId, $tenantId]);
            $service = $stmt->fetch();
            
            if (!$service) {
                $pdo->rollBack();
                jsonResponse(false, "Service with ID {$serviceId} not found or is inactive.");
            }
            
            // Check if service already exists for this appointment
            $stmt = $pdo->prepare("
                SELECT id FROM appointment_services 
                WHERE booking_id = ? AND service_id = ? AND tenant_id = ?
            ");
            $stmt->execute([$bookingId, $serviceId, $tenantId]);
            if ($stmt->fetch()) {
                // Service already added, skip
                continue;
            }
            
            $price = floatval($service['price']);
            $additionalCost += $price;
            $serviceNames[] = $service['service_name'] . ' (₱' . number_format($price, 2) . ')';
            
            // Insert into appointment_services table
            $stmt = $pdo->prepare("
                INSERT INTO appointment_services (
                    tenant_id,
                    booking_id, service_id, service_name, price, is_original, added_by, added_at
                ) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())
            ");
            $stmt->execute([
                $tenantId,
                $bookingId,
                $service['service_id'],
                $service['service_name'],
                $price,
                $addedByUserId
            ]);
            
            $newServices[] = [
                'service_id' => $service['service_id'],
                'service_name' => $service['service_name'],
                'price' => $price
            ];
        }
        
        if (empty($newServices)) {
            $pdo->rollBack();
            jsonResponse(false, 'No new services were added. Some services may already be included in this appointment.');
        }
        
        // Calculate new total cost
        $currentTotal = floatval($appointment['total_treatment_cost'] ?? 0);
        
        // If current total is 0, try to extract from service_description
        if ($currentTotal == 0 && !empty($appointment['service_description'])) {
            $totalMatch = preg_match('/Total:\s*₱?\s*([\d,]+(?:\.\d{2})?)/i', $appointment['service_description'], $matches);
            if ($totalMatch && isset($matches[1])) {
                $currentTotal = floatval(str_replace(',', '', $matches[1]));
            } else {
                // Try parsing individual service prices
                $pricePattern = '/\(₱?\s*([\d,]+(?:\.\d{2})?)\)/';
                preg_match_all($pricePattern, $appointment['service_description'], $priceMatches);
                if (!empty($priceMatches[1])) {
                    foreach ($priceMatches[1] as $priceStr) {
                        $currentTotal += floatval(str_replace(',', '', $priceStr));
                    }
                }
            }
        }
        
        $newTotal = $currentTotal + $additionalCost;
        
        // Update service_description to include new services
        $currentDescription = $appointment['service_description'] ?? '';
        $newServicesText = implode('; ', $serviceNames);
        
        if (!empty($currentDescription)) {
            // Remove old total if it exists
            $currentDescription = preg_replace('/\s*\|\s*Total:\s*₱?[\d,]+(?:\.\d{2})?/i', '', $currentDescription);
            $updatedDescription = $currentDescription . '; [ADDED] ' . $newServicesText . ' | Total: ₱' . number_format($newTotal, 2);
        } else {
            $updatedDescription = '[ADDED] ' . $newServicesText . ' | Total: ₱' . number_format($newTotal, 2);
        }
        
        // Update appointments table
        $stmt = $pdo->prepare("
            UPDATE appointments 
            SET total_treatment_cost = ?,
                service_description = ?,
                updated_at = NOW()
            WHERE booking_id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $newTotal,
            $updatedDescription,
            $bookingId,
            $tenantId
        ]);
        
        $pdo->commit();
        
        jsonResponse(true, 'Services added successfully. Total cost updated.', [
            'booking_id' => $bookingId,
            'added_services' => $newServices,
            'additional_cost' => $additionalCost,
            'new_total' => $newTotal,
            'previous_total' => $currentTotal
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('Add services to appointment error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to add services: ' . $e->getMessage());
    }
}

/**
 * Automatically cancel pending bookings that are older than 5 hours
 * This function checks for appointments with status 'pending' that were created more than 5 hours ago
 * and updates their status to 'cancelled'
 */
function autoCancelPendingBookings() {
    global $pdo, $tenantId;
    
    try {
        // Calculate the timestamp 5 hours ago
        $fiveHoursAgo = date('Y-m-d H:i:s', strtotime('-5 hours'));
        
        // Find all pending appointments created more than 5 hours ago
        // Only cancel if they don't have any completed payments
        $stmt = $pdo->prepare("
            SELECT a.id, a.booking_id, a.patient_id
            FROM appointments a
            WHERE a.status = 'pending'
            AND a.created_at < ?
            AND a.tenant_id = ?
            AND NOT EXISTS (
                SELECT 1 
                FROM payments p 
                WHERE p.booking_id = a.booking_id 
                AND p.status = 'completed'
                AND p.tenant_id = ?
            )
        ");
        $stmt->execute([$fiveHoursAgo, $tenantId, $tenantId]);
        $pendingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pendingBookings)) {
            // No bookings to cancel
            return;
        }
        
        $cancelledCount = 0;
        $pdo->beginTransaction();
        
        foreach ($pendingBookings as $booking) {
            // Update appointment status to cancelled
            $updateStmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled', 
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $updateStmt->execute([$booking['id'], $tenantId]);
            $cancelledCount++;
        }
        
        $pdo->commit();
        
        // Log the cancellation (optional, for debugging)
        if ($cancelledCount > 0) {
            error_log("Auto-cancelled {$cancelledCount} pending booking(s) older than 5 hours");
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Auto-cancel pending bookings error: ' . $e->getMessage());
        // Don't throw error - this is a background process
    }
}

