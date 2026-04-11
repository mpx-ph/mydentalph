<?php
/**
 * Walk-in create: included from StaffWalkIn.php (action=create_walkin) or clinic/api/walkin_create.php.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $rawBody = file_get_contents('php://input');
    $input = json_decode((string) $rawBody, true);
    if (!is_array($input)) {
        $input = [];
    }

    // Slug may be only in JSON (POST query strings are sometimes dropped by hosts / proxies).
    if (!empty($input['clinic_slug']) && is_string($input['clinic_slug'])) {
        $cs = strtolower(trim($input['clinic_slug']));
        if ($cs !== '' && preg_match('/^[a-z0-9\-]+$/', $cs)) {
            $_GET['clinic_slug'] = $cs;
        }
    }

    $pdo = getDBConnection();
    if (!empty($GLOBALS['CLINIC_WALKIN_RESOLVED_TENANT_ID'])) {
        $tenantId = (string) $GLOBALS['CLINIC_WALKIN_RESOLVED_TENANT_ID'];
    } else {
        $tenantId = clinic_resolve_walkin_tenant_id($pdo);
    }

    if (empty($tenantId)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Tenant context missing. Please log in again or open this page with your clinic link.']);
        exit;
    }

    $patientId = trim((string) ($input['patient_id'] ?? ''));
    $dentistId = trim((string) ($input['dentist_id'] ?? ''));
    $appointmentDate = trim((string) ($input['appointment_date'] ?? ''));
    $appointmentTime = trim((string) ($input['appointment_time'] ?? ''));
    $notes = trim((string) ($input['notes'] ?? ''));
    $services = isset($input['services']) && is_array($input['services']) ? $input['services'] : [];
    $status = trim((string) ($input['status'] ?? 'pending'));
    $visitType = 'walk_in';

    if ($patientId === '') {
        echo json_encode(['success' => false, 'message' => 'Patient selection is required.']);
        exit;
    }
    if ($dentistId === '') {
        echo json_encode(['success' => false, 'message' => 'Assigned dentist is required.']);
        exit;
    }
    if ($appointmentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate)) {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment date.']);
        exit;
    }
    if ($appointmentTime === '' || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $appointmentTime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment time.']);
        exit;
    }
    if (empty($services)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one service.']);
        exit;
    }

    $statusAllowed = ['pending', 'confirmed', 'scheduled'];
    if (!in_array(strtolower($status), $statusAllowed, true)) {
        $status = 'pending';
    }

    $dbTables = clinic_resolve_appointment_db_tables($pdo);
    $tblApptPhys = clinic_get_physical_table_name($pdo, 'tbl_appointments');
    $legacyApptPhys = clinic_get_physical_table_name($pdo, 'appointments');
    $tblApsPhys = clinic_get_physical_table_name($pdo, 'tbl_appointment_services');
    $legacyApsPhys = clinic_get_physical_table_name($pdo, 'appointment_services');

    // Prefer tbl_* (what phpMyAdmin / StaffAppointments use) when both legacy and tbl exist.
    $appointmentsTable = $tblApptPhys ?? $legacyApptPhys;
    $appointmentServicesTable = $tblApsPhys ?? $legacyApsPhys;

    $servicesCatalogTable = $dbTables['services'];
    if ($appointmentsTable === null || $appointmentServicesTable === null || $servicesCatalogTable === null) {
        echo json_encode(['success' => false, 'message' => 'Database is missing appointment or service tables. Ensure schema/migrations are applied.']);
        exit;
    }
    $patientsTable = $dbTables['patients'];
    $dentistsTable = $dbTables['dentists'];
    $usersTable = $dbTables['users'];
    if ($patientsTable === null || $dentistsTable === null) {
        echo json_encode(['success' => false, 'message' => 'Database is missing patient or dentist tables.']);
        exit;
    }

    $mirrorAppointmentsTable = null;
    $mirrorAppointmentServicesTable = null;
    if ($tblApptPhys !== null && $legacyApptPhys !== null && $tblApptPhys !== $legacyApptPhys) {
        $mirrorAppointmentsTable = ($appointmentsTable === $tblApptPhys) ? $legacyApptPhys : $tblApptPhys;
    }
    if ($tblApsPhys !== null && $legacyApsPhys !== null && $tblApsPhys !== $legacyApsPhys) {
        $mirrorAppointmentServicesTable = ($appointmentServicesTable === $tblApsPhys) ? $legacyApsPhys : $tblApsPhys;
    }

    $quotedPatients = clinic_quote_identifier($patientsTable);
    $patientCheckStmt = $pdo->prepare("
        SELECT patient_id
        FROM {$quotedPatients}
        WHERE tenant_id = ? AND patient_id = ?
        LIMIT 1
    ");
    $patientCheckStmt->execute([$tenantId, $patientId]);
    if (!$patientCheckStmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Selected patient was not found for this clinic.']);
        exit;
    }

    $quotedDentists = clinic_quote_identifier($dentistsTable);
    $dentistCheckStmt = $pdo->prepare("
        SELECT dentist_id
        FROM {$quotedDentists}
        WHERE tenant_id = ? AND dentist_id = ?
        LIMIT 1
    ");
    $dentistCheckStmt->execute([$tenantId, $dentistId]);
    if (!$dentistCheckStmt->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => false, 'message' => 'Selected dentist was not found for this clinic.']);
        exit;
    }

    // Same calendar day as StaffAppointments.php (Asia/Manila). Using only server date() breaks
    // walk-ins when PHP default timezone is UTC — booking lands on "yesterday" vs clinic schedule.
    $clinicTz = new DateTimeZone('Asia/Manila');
    $nowClinic = new DateTimeImmutable('now', $clinicTz);
    $appointmentDate = $nowClinic->format('Y-m-d');
    $dentistIdInt = (int) $dentistId;
    $slotProbe = $pdo->prepare('
        SELECT COUNT(*) FROM ' . clinic_quote_identifier($appointmentsTable) . '
        WHERE tenant_id = ? AND dentist_id = ? AND appointment_date = ? AND appointment_time = ?
    ');
    $appointmentTime = $nowClinic->format('H:i:s');
    for ($sec = 0; $sec < 300; $sec++) {
        $try = $nowClinic->modify('+' . $sec . ' seconds');
        $tryTime = $try->format('H:i:s');
        $slotProbe->execute([$tenantId, $dentistIdInt, $appointmentDate, $tryTime]);
        if ((int) $slotProbe->fetchColumn() === 0) {
            $appointmentTime = $tryTime;
            break;
        }
    }

    $pdo->beginTransaction();

    $bookingPrefix = 'BK-' . $nowClinic->format('Y') . '-';
    $sequence = 0;
    foreach (array_unique(array_filter([$appointmentsTable, $mirrorAppointmentsTable], static function ($v) {
        return $v !== null && $v !== '';
    })) as $tName) {
        $q = clinic_quote_identifier($tName);
        $bookingStmt = $pdo->prepare("
            SELECT booking_id
            FROM {$q}
            WHERE tenant_id = ?
              AND booking_id LIKE ?
            ORDER BY booking_id DESC
            LIMIT 1
        ");
        $bookingStmt->execute([$tenantId, $bookingPrefix . '%']);
        $lastBookingId = (string) ($bookingStmt->fetchColumn() ?: '');
        if ($lastBookingId !== '') {
            $parts = explode('-', $lastBookingId);
            if (count($parts) === 3) {
                $sequence = max($sequence, (int) $parts[2]);
            }
        }
    }
    $bookingId = $bookingPrefix . str_pad((string) ($sequence + 1), 6, '0', STR_PAD_LEFT);

    $serviceNames = [];
    $serviceDescriptions = [];
    $totalCost = 0.0;
    $normalizedServices = [];

    $quotedSvc = clinic_quote_identifier($servicesCatalogTable);
    $serviceStmt = $pdo->prepare("
        SELECT service_id, service_name, category, price
        FROM {$quotedSvc}
        WHERE tenant_id = ? AND service_id = ? AND status = 'active'
        LIMIT 1
    ");

    foreach ($services as $service) {
        $serviceId = trim((string) ($service['id'] ?? $service['service_id'] ?? ''));
        if ($serviceId === '') {
            continue;
        }
        $serviceStmt->execute([$tenantId, $serviceId]);
        $serviceRow = $serviceStmt->fetch(PDO::FETCH_ASSOC);
        if (!$serviceRow) {
            continue;
        }
        $price = (float) ($serviceRow['price'] ?? 0);
        $name = trim((string) ($serviceRow['service_name'] ?? ''));
        $normalizedServices[] = [
            'service_id' => (string) ($serviceRow['service_id'] ?? ''),
            'service_name' => $name,
            'price' => $price,
        ];
        if ($name !== '') {
            $serviceNames[] = $name;
            $serviceDescriptions[] = $name . ' (₱' . number_format($price, 2) . ')';
        }
        $totalCost += $price;
    }

    if (empty($normalizedServices)) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'No valid active services were selected.']);
        exit;
    }

    $serviceType = implode(', ', array_slice($serviceNames, 0, 3));
    if (count($serviceNames) > 3) {
        $serviceType .= ' (+' . (count($serviceNames) - 3) . ' more)';
    }
    $serviceType = function_exists('mb_substr')
        ? mb_substr($serviceType, 0, 100, 'UTF-8')
        : substr($serviceType, 0, 100);
    $serviceDescription = implode('; ', $serviceDescriptions) . ' | Total: ₱' . number_format($totalCost, 2);
    $createdBy = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
    if ($createdBy === '') {
        $createdBy = null;
    }
    if ($createdBy !== null && $usersTable !== null) {
        $quotedUsers = clinic_quote_identifier($usersTable);
        $userOk = $pdo->prepare("SELECT 1 FROM {$quotedUsers} WHERE user_id = ? LIMIT 1");
        $userOk->execute([$createdBy]);
        if (!$userOk->fetchColumn()) {
            $createdBy = null;
        }
    }

    $statusForDb = strtolower((string) $status);
    if ($statusForDb === 'scheduled') {
        $statusForDb = 'confirmed';
    }

    $storageTargets = [
        ['appt' => $appointmentsTable, 'aps' => $appointmentServicesTable],
    ];
    if ($mirrorAppointmentsTable !== null && $mirrorAppointmentServicesTable !== null) {
        $storageTargets[] = ['appt' => $mirrorAppointmentsTable, 'aps' => $mirrorAppointmentServicesTable];
    }

    $dbName = '';
    try {
        $dbName = (string) ($pdo->query('SELECT DATABASE()')->fetchColumn() ?: '');
    } catch (Throwable $e) {
        $dbName = '';
    }

    $firstAppointmentId = 0;
    $written = [];

    foreach ($storageTargets as $idx => $target) {
        $apptTbl = $target['appt'];
        $apsTbl = $target['aps'];
        $quotedAppt = clinic_quote_identifier($apptTbl);

        $localStatus = $statusForDb;
        if (strtolower($apptTbl) === 'tbl_appointments') {
            $tblStatusOk = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
            if (!in_array($localStatus, $tblStatusOk, true)) {
                $localStatus = 'pending';
            }
        }

        $apptCols = clinic_table_columns($pdo, $apptTbl);
        $apptData = [
            'tenant_id' => $tenantId,
            'booking_id' => $bookingId,
            'patient_id' => $patientId,
            'appointment_date' => $appointmentDate,
            'appointment_time' => $appointmentTime,
            'service_type' => $serviceType,
            'service_description' => $serviceDescription,
            'treatment_type' => 'short_term',
            'visit_type' => $visitType,
            'status' => $localStatus,
            'notes' => $notes !== '' ? $notes : null,
            'total_treatment_cost' => $totalCost,
            'duration_months' => null,
            'target_completion_date' => null,
            'start_date' => null,
            'created_by' => $createdBy,
        ];
        if (in_array('dentist_id', $apptCols, true)) {
            $apptData['dentist_id'] = (int) $dentistId;
        }

        $insertCols = [];
        $insertPlaceholders = [];
        $insertParams = [];
        $columnOrder = [
            'tenant_id', 'dentist_id', 'booking_id', 'patient_id', 'appointment_date', 'appointment_time',
            'service_type', 'service_description', 'treatment_type', 'visit_type', 'status', 'notes',
            'total_treatment_cost', 'duration_months', 'target_completion_date', 'start_date', 'created_by',
        ];
        foreach ($columnOrder as $col) {
            if (!in_array($col, $apptCols, true) || !array_key_exists($col, $apptData)) {
                continue;
            }
            $insertCols[] = '`' . str_replace('`', '``', $col) . '`';
            $insertPlaceholders[] = '?';
            $insertParams[] = $apptData[$col];
        }
        if (in_array('created_at', $apptCols, true)) {
            $insertCols[] = '`created_at`';
            $insertPlaceholders[] = 'NOW()';
        }
        if ($insertCols === []) {
            if ($idx === 0) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Appointment table structure is not compatible with walk-in booking.']);
                exit;
            }
            error_log('Staff walk-in: skipping mirror storage — incompatible appointment columns on ' . $apptTbl);
            continue;
        }

        $insertSql = 'INSERT INTO ' . $quotedAppt . ' (' . implode(', ', $insertCols) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
        $insertAppointmentStmt = $pdo->prepare($insertSql);
        $insertAppointmentStmt->execute($insertParams);

        $appointmentId = (int) $pdo->lastInsertId();
        if ($idx === 0) {
            $firstAppointmentId = $appointmentId;
        }

        $apsCols = clinic_table_columns($pdo, $apsTbl);
        $quotedAps = clinic_quote_identifier($apsTbl);
        foreach ($normalizedServices as $serviceRow) {
            $rowData = [
                'tenant_id' => $tenantId,
                'booking_id' => $bookingId,
                'service_id' => $serviceRow['service_id'],
                'service_name' => $serviceRow['service_name'],
                'price' => $serviceRow['price'],
                'is_original' => 1,
                'added_by' => $createdBy,
            ];
            if (in_array('appointment_id', $apsCols, true)) {
                $rowData['appointment_id'] = $appointmentId > 0 ? $appointmentId : null;
            }
            $svcInsertCols = [];
            $svcPlaceholders = [];
            $svcParams = [];
            $apsOrder = ['tenant_id', 'booking_id', 'appointment_id', 'service_id', 'service_name', 'price', 'is_original', 'added_by'];
            foreach ($apsOrder as $col) {
                if (!in_array($col, $apsCols, true) || !array_key_exists($col, $rowData)) {
                    continue;
                }
                $svcInsertCols[] = '`' . str_replace('`', '``', $col) . '`';
                $svcPlaceholders[] = '?';
                $svcParams[] = $rowData[$col];
            }
            if (in_array('added_at', $apsCols, true)) {
                $svcInsertCols[] = '`added_at`';
                $svcPlaceholders[] = 'NOW()';
            }
            if ($svcInsertCols === []) {
                throw new RuntimeException('Appointment services table has no compatible columns for walk-in lines: ' . $apsTbl);
            }
            $svcSql = 'INSERT INTO ' . $quotedAps . ' (' . implode(', ', $svcInsertCols) . ') VALUES (' . implode(', ', $svcPlaceholders) . ')';
            $svcStmt = $pdo->prepare($svcSql);
            $svcStmt->execute($svcParams);
        }

        $written[] = [
            'appointments_table' => $apptTbl,
            'appointment_services_table' => $apsTbl,
            'appointment_row_id' => $appointmentId,
        ];
    }

    $verifyAppt = $pdo->prepare('SELECT COUNT(*) FROM ' . clinic_quote_identifier($appointmentsTable) . ' WHERE tenant_id = ? AND booking_id = ?');
    $verifyAppt->execute([$tenantId, $bookingId]);
    if ((int) $verifyAppt->fetchColumn() < 1) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Could not save walk-in: booking was not stored in ' . $appointmentsTable . '. Check DB permissions and table name casing.',
        ]);
        exit;
    }
    $verifySvc = $pdo->prepare('SELECT COUNT(*) FROM ' . clinic_quote_identifier($appointmentServicesTable) . ' WHERE tenant_id = ? AND booking_id = ?');
    $verifySvc->execute([$tenantId, $bookingId]);
    $svcCount = (int) $verifySvc->fetchColumn();
    if ($svcCount < count($normalizedServices)) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Could not save walk-in: service lines were not stored in ' . $appointmentServicesTable . ' (expected ' . count($normalizedServices) . ', saw ' . $svcCount . ').',
        ]);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Walk-in appointment created successfully.',
        'data' => [
            'booking_id' => $bookingId,
            'appointment_id' => $firstAppointmentId,
            'database' => $dbName,
            'written_to' => $written,
        ],
    ]);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff walk-in create error: ' . $e->getMessage());
    http_response_code(500);
    $msg = 'Unable to create walk-in appointment right now.';
    if ($e instanceof PDOException) {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);
        if ($driverCode === 1062 || $sqlState === '23000') {
            $msg = 'Schedule conflict (duplicate booking or time slot). Please try again.';
        } elseif ($driverCode === 1452) {
            $msg = 'Database rejected a link (patient, dentist, or user). Re-select patient and dentist, then try again.';
        }
    }
    if (defined('DB_DEBUG') && DB_DEBUG) {
        $msg = $e->getMessage();
    }
    echo json_encode([
        'success' => false,
        'message' => $msg,
    ]);
    exit;
}
