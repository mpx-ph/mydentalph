<?php
$pageTitle = 'Appointments';
$staff_nav_active = 'appointments';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/appointment_db_tables.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_GET['clinic_slug']) && !empty($_SESSION['public_tenant_slug'])) {
    $_GET['clinic_slug'] = $_SESSION['public_tenant_slug'];
}

if (empty($_GET['clinic_slug'])) {
    $reqUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $reqPath = $reqUri !== '' ? parse_url($reqUri, PHP_URL_PATH) : '';
    $scriptBase = isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : 'StaffAppointments.php';
    if (is_string($reqPath) && $reqPath !== '') {
        $segments = array_values(array_filter(explode('/', trim($reqPath, '/')), 'strlen'));
        $scriptIdx = array_search($scriptBase, $segments, true);
        if ($scriptIdx !== false && $scriptIdx > 0) {
            $slugFromPath = strtolower(trim((string) $segments[$scriptIdx - 1]));
            if ($slugFromPath !== '' && preg_match('/^[a-z0-9\-]+$/', $slugFromPath)) {
                $_GET['clinic_slug'] = $slugFromPath;
            }
        }
    }
}

$clinicSlugBoot = isset($_GET['clinic_slug']) ? trim((string) $_GET['clinic_slug']) : '';
if ($clinicSlugBoot !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($clinicSlugBoot))) {
    $_GET['clinic_slug'] = strtolower($clinicSlugBoot);
    require_once __DIR__ . '/tenant_bootstrap.php';
    if (!isset($currentTenantSlug) || trim((string) $currentTenantSlug) === '') {
        $currentTenantSlug = strtolower($clinicSlugBoot);
    }
} else {
    $currentTenantSlug = '';
}

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$manilaToday = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$selectedDate = isset($_GET['date']) ? trim((string) $_GET['date']) : $manilaToday;
if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $selectedDate)) {
    $selectedDate = $manilaToday;
}

$selectedMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : substr($selectedDate, 0, 7);
if (!preg_match('/^\d{4}\-\d{2}$/', $selectedMonth)) {
    $selectedMonth = substr($selectedDate, 0, 7);
}

$allowedStatuses = ['all', 'pending', 'in_progress', 'completed', 'cancelled', 'no_show', 'scheduled', 'confirmed'];
$selectedStatus = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : 'all';
if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = 'all';
}
if ($selectedStatus === 'scheduled' || $selectedStatus === 'confirmed') {
    $selectedStatus = 'pending';
}

$searchTerm = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$summary = [
    'in_progress' => 0,
    'cancelled' => 0,
    'pending' => 0,
];
$dailyAppointments = [];
$appointmentServicesByKey = [];
$monthCounts = [];

$monthStart = $selectedMonth . '-01';
$monthStartTs = strtotime($monthStart);
$monthEnd = date('Y-m-t', $monthStartTs ?: time());
$prevMonth = date('Y-m', strtotime('-1 month', $monthStartTs ?: time()));
$nextMonth = date('Y-m', strtotime('+1 month', $monthStartTs ?: time()));
$pageNotice = null;
$statusUpdateToastMessage = '';
$availableServices = [];

function buildAppointmentsUrl(array $overrides = []): string
{
    global $currentTenantSlug, $selectedDate, $selectedMonth, $selectedStatus, $searchTerm;
    $params = [
        'clinic_slug' => $currentTenantSlug,
        'date' => $selectedDate,
        'month' => $selectedMonth,
        'status' => $selectedStatus,
        'q' => $searchTerm,
    ];
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }
    return BASE_URL . 'StaffAppointments.php?' . http_build_query($params);
}

/**
 * Normalize a DB status string for the Daily Schedule pill + modal, strip invisible
 * characters (so labels never appear blank), and return canonical code, display label, classes.
 *
 * @param array<string, string> $labelMap
 * @return array{code: string, label: string, class: string}
 */
function staff_appointments_resolve_status_for_ui($raw, array $labelMap)
{
    $original = (string) $raw;
    $s = $original;
    $s = preg_replace('/\x{FEFF}|\x{200B}|\x{00AD}/u', '', (string) $s);
    $s = preg_replace('/\p{Zs}/u', ' ', (string) $s);
    $s = trim((string) $s);

    $slug = strtolower(str_replace([' ', '-'], '_', (string) $s));
    $slug = (string) preg_replace('/_+/', '_', $slug);
    if ($slug === 'inprogress' || $slug === 'ongoing') {
        $slug = 'in_progress';
    }
    if ($original !== '' && $s === '' && $slug === '') {
        $slug = 'pending';
    }
    if ($slug === '' || $slug === '0') {
        $slug = 'pending';
    } elseif (in_array($slug, ['confirmed', 'scheduled'], true)) {
        $slug = 'pending';
    }

    $known = ['pending', 'in_progress', 'completed', 'cancelled', 'no_show'];
    $label = $labelMap[$slug] ?? '';
    if (trim($label) === '' && in_array($slug, $known, true)) {
        $label = $labelMap['pending'] ?? 'Pending';
    } elseif (trim($label) === '' && $slug !== '') {
        $label = ucwords(str_replace('_', ' ', (string) $slug));
    }
    if (trim($label) === '') {
        $label = (string) ($labelMap['pending'] ?? 'Pending');
    }

    $class = 'bg-amber-50 text-amber-700 border border-amber-200';
    if ($slug === 'in_progress') {
        $class = 'bg-blue-50 text-blue-700 border border-blue-200';
    } elseif ($slug === 'cancelled') {
        $class = 'bg-rose-50 text-rose-700 border border-rose-200';
    } elseif ($slug === 'completed') {
        $class = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    } elseif ($slug === 'no_show') {
        $class = 'bg-slate-100 text-slate-700 border border-slate-200';
    }

    return [
        'code' => $slug,
        'label' => $label,
        'class' => $class,
    ];
}

if (isset($_SESSION['staff_appointments_notice']) && is_array($_SESSION['staff_appointments_notice'])) {
    $noticeType = (string) ($_SESSION['staff_appointments_notice']['type'] ?? '');
    $noticeMessage = (string) ($_SESSION['staff_appointments_notice']['message'] ?? '');
    if ($noticeType !== '' && $noticeMessage !== '') {
        if ($noticeType === 'success' && $noticeMessage === 'Appointment status updated successfully.') {
            $statusUpdateToastMessage = $noticeMessage;
        } else {
            $pageNotice = ['type' => $noticeType, 'message' => $noticeMessage];
        }
    }
    unset($_SESSION['staff_appointments_notice']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tenantId !== '') {
    $postAction = strtolower(trim((string) ($_POST['modal_action'] ?? '')));
    $bookingId = trim((string) ($_POST['modal_booking_id'] ?? ''));
    $notice = ['type' => 'error', 'message' => 'Unable to process request.'];

    try {
        $pdo = getDBConnection();
        $dbTables = clinic_resolve_appointment_db_tables($pdo);
        $tAppt = $dbTables['appointments'];
        $tAps = $dbTables['appointment_services'];
        $tSvc = $dbTables['services'];
        if ($tAppt === null) {
            throw new RuntimeException('Appointments table is not available.');
        }
        $qAppt = clinic_quote_identifier($tAppt);
        $qAps = $tAps !== null ? clinic_quote_identifier($tAps) : null;
        $qSvc = $tSvc !== null ? clinic_quote_identifier($tSvc) : null;

        if ($postAction === 'update_status') {
            $allowedUpdateStatuses = ['pending', 'in_progress', 'completed', 'cancelled', 'no_show'];
            $newStatus = strtolower(trim((string) ($_POST['update_status'] ?? '')));
            if ($bookingId === '' || !in_array($newStatus, $allowedUpdateStatuses, true)) {
                throw new RuntimeException('Please select a valid status.');
            }

            if ($newStatus === 'in_progress') {
                clinic_appointments_ensure_in_progress_in_status_enum($pdo, $tAppt);
            }

            $statusStmt = $pdo->prepare("
                UPDATE {$qAppt}
                SET status = ?
                WHERE tenant_id = ? AND booking_id = ?
                LIMIT 1
            ");
            $statusStmt->execute([$newStatus, $tenantId, $bookingId]);
            $vStmt = $pdo->prepare("
                SELECT status
                FROM {$qAppt}
                WHERE tenant_id = ? AND booking_id = ?
                LIMIT 1
            ");
            $vStmt->execute([$tenantId, $bookingId]);
            $vRow = $vStmt->fetch(PDO::FETCH_ASSOC);
            if (!$vRow) {
                throw new RuntimeException('Appointment not found.');
            }
            $rawStored = (string) ($vRow['status'] ?? '');
            $rawStored = preg_replace('/\x{FEFF}|\x{200B}|\x{00AD}/u', '', $rawStored);
            $rawStored = preg_replace('/\p{Zs}/u', ' ', (string) $rawStored);
            $stored = strtolower(str_replace([' ', '-'], '_', trim($rawStored)));
            if ($stored === 'inprogress') {
                $stored = 'in_progress';
            }
            if ($stored !== $newStatus) {
                throw new RuntimeException(
                    'Status could not be saved. If you selected In Progress, your database may be missing the in_progress value on the appointment status column. Run migrations/013_appointments_status_in_progress.sql.'
                );
            }
            if ($newStatus === 'completed') {
                require_once __DIR__ . '/includes/staff_installment_helpers.php';
                staff_installments_advance_after_visit_completed($pdo, $tenantId, $bookingId);
            }
            $notice = ['type' => 'success', 'message' => 'Appointment status updated successfully.'];
        } elseif ($postAction === 'add_services') {
            $serviceIds = isset($_POST['service_ids']) && is_array($_POST['service_ids']) ? array_values(array_unique($_POST['service_ids'])) : [];
            $serviceIds = array_values(array_filter(array_map('trim', $serviceIds), static function ($item) {
                return $item !== '';
            }));
            if ($bookingId === '' || empty($serviceIds)) {
                throw new RuntimeException('Please select at least one service to add.');
            }
            if ($qAps === null || $qSvc === null) {
                throw new RuntimeException('Appointment services or catalog table is not available.');
            }

            $pdo->beginTransaction();

            $aptStmt = $pdo->prepare("
                SELECT booking_id, status, total_treatment_cost, service_description
                FROM {$qAppt}
                WHERE tenant_id = ? AND booking_id = ?
                LIMIT 1
            ");
            $aptStmt->execute([$tenantId, $bookingId]);
            $appointment = $aptStmt->fetch(PDO::FETCH_ASSOC);
            if (!$appointment) {
                throw new RuntimeException('Appointment not found.');
            }

            $statusRaw = strtolower(trim((string) ($appointment['status'] ?? '')));
            if (in_array($statusRaw, ['completed', 'cancelled'], true)) {
                throw new RuntimeException('Cannot add services to completed or cancelled appointments.');
            }

            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $serviceSql = "
                SELECT service_id, service_name, price
                FROM {$qSvc}
                WHERE tenant_id = ? AND status = 'active' AND service_id IN ($placeholders)
            ";
            $serviceStmt = $pdo->prepare($serviceSql);
            $serviceStmt->execute(array_merge([$tenantId], $serviceIds));
            $servicesToAdd = $serviceStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (empty($servicesToAdd)) {
                throw new RuntimeException('Selected services were not found.');
            }

            $existingStmt = $pdo->prepare("
                SELECT service_id
                FROM {$qAps}
                WHERE tenant_id = ? AND booking_id = ?
            ");
            $existingStmt->execute([$tenantId, $bookingId]);
            $existingServiceIds = array_map('strval', $existingStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            $existingLookup = array_fill_keys($existingServiceIds, true);

            $insertStmt = $pdo->prepare("
                INSERT INTO {$qAps} (tenant_id, booking_id, service_id, service_name, price, is_original, added_at)
                VALUES (?, ?, ?, ?, ?, 0, NOW())
            ");

            $addedNames = [];
            $addedCost = 0.0;
            foreach ($servicesToAdd as $serviceRow) {
                $sid = (string) ($serviceRow['service_id'] ?? '');
                if ($sid === '' || isset($existingLookup[$sid])) {
                    continue;
                }
                $sname = (string) ($serviceRow['service_name'] ?? '');
                $sprice = (float) ($serviceRow['price'] ?? 0);
                $insertStmt->execute([$tenantId, $bookingId, $sid, $sname, $sprice]);
                $addedNames[] = $sname . ' (P' . number_format($sprice, 2) . ')';
                $addedCost += $sprice;
            }

            if ($addedCost <= 0) {
                throw new RuntimeException('No new services were added. Selected services may already be included.');
            }

            $currentTotal = (float) ($appointment['total_treatment_cost'] ?? 0);
            $newTotal = $currentTotal + $addedCost;
            $desc = trim((string) ($appointment['service_description'] ?? ''));
            $appendText = '[ADDED] ' . implode('; ', $addedNames);
            $newDescription = $desc !== '' ? ($desc . '; ' . $appendText) : $appendText;

            $updateAptStmt = $pdo->prepare("
                UPDATE {$qAppt}
                SET total_treatment_cost = ?, service_description = ?
                WHERE tenant_id = ? AND booking_id = ?
                LIMIT 1
            ");
            $updateAptStmt->execute([$newTotal, $newDescription, $tenantId, $bookingId]);

            $pdo->commit();
            $notice = ['type' => 'success', 'message' => 'Additional services were added and treatment cost was updated.'];
        }
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = trim((string) $e->getMessage());
        $notice = ['type' => 'error', 'message' => $message !== '' ? $message : 'Failed to process request.'];
    }

    $_SESSION['staff_appointments_notice'] = $notice;
    header('Location: ' . buildAppointmentsUrl());
    exit;
}

try {
    $pdo = getDBConnection();
    $dbTables = clinic_resolve_appointment_db_tables($pdo);
    $tTenants = $dbTables['tenants'];

    if ($tenantId === '' && $currentTenantSlug !== '' && $tTenants !== null) {
        $qTen = clinic_quote_identifier($tTenants);
        $tenantStmt = $pdo->prepare("SELECT tenant_id FROM {$qTen} WHERE clinic_slug = ? LIMIT 1");
        $tenantStmt->execute([$currentTenantSlug]);
        $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenantRow && isset($tenantRow['tenant_id'])) {
            $tenantId = (string) $tenantRow['tenant_id'];
        }
    }

    if ($tenantId !== '') {
        $tAppt = $dbTables['appointments'];
        $tAps = $dbTables['appointment_services'];
        $tPat = $dbTables['patients'];
        $tUsr = $dbTables['users'];
        $tPay = $dbTables['payments'];
        $tSvc = $dbTables['services'];
        $tDent = $dbTables['dentists'];

        if ($tAppt === null) {
            error_log('Staff appointments: appointments table not found.');
        } else {
            $qAppt = clinic_quote_identifier($tAppt);
            $qAps = $tAps !== null ? clinic_quote_identifier($tAps) : null;
            $qPat = $tPat !== null ? clinic_quote_identifier($tPat) : null;
            $qUsr = $tUsr !== null ? clinic_quote_identifier($tUsr) : null;
            $qPay = $tPay !== null ? clinic_quote_identifier($tPay) : null;
            $qSvc = $tSvc !== null ? clinic_quote_identifier($tSvc) : null;
            $qDent = $tDent !== null ? clinic_quote_identifier($tDent) : null;
            $apptCols = clinic_table_columns($pdo, $tAppt);
            $apsCols = $tAps !== null ? clinic_table_columns($pdo, $tAps) : [];
            $patCols = $tPat !== null ? clinic_table_columns($pdo, $tPat) : [];
            $svcCols = $tSvc !== null ? clinic_table_columns($pdo, $tSvc) : [];
            $dentCols = $tDent !== null ? clinic_table_columns($pdo, $tDent) : [];
            $apptIdCol = in_array('id', $apptCols, true) ? 'id' : (in_array('appointment_id', $apptCols, true) ? 'appointment_id' : null);
            $supportsApsAppointmentId = in_array('appointment_id', $apsCols, true) && $apptIdCol !== null;
            $apsMatchSql = $supportsApsAppointmentId
                ? 'svc.appointment_id = a.' . $apptIdCol
                : 'svc.booking_id = a.booking_id';
            $apsMatchSqlForDetails = str_replace('svc.', 'apsd.', $apsMatchSql);
            $dentistNameExpr = "'Unassigned'";
            if ($qDent !== null && in_array('dentist_id', $apptCols, true) && in_array('dentist_id', $dentCols, true)) {
                if (in_array('first_name', $dentCols, true) && in_array('last_name', $dentCols, true)) {
                    $dentistNameExpr = "NULLIF(TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))), '')";
                } elseif (in_array('full_name', $dentCols, true)) {
                    $dentistNameExpr = "NULLIF(TRIM(COALESCE(d.full_name, '')), '')";
                }
            }

            $summaryStmt = $pdo->prepare("
                SELECT
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress_count,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                    SUM(CASE WHEN status IN ('pending', 'scheduled', 'confirmed') THEN 1 ELSE 0 END) AS pending_count
                FROM {$qAppt}
                WHERE tenant_id = ? AND appointment_date = ?
            ");
            $summaryStmt->execute([$tenantId, $selectedDate]);
            $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $summary['in_progress'] = (int) ($summaryRow['in_progress_count'] ?? 0);
            $summary['cancelled'] = (int) ($summaryRow['cancelled_count'] ?? 0);
            $summary['pending'] = (int) ($summaryRow['pending_count'] ?? 0);

            $monthStmt = $pdo->prepare("
                SELECT appointment_date, COUNT(*) AS day_count
                FROM {$qAppt}
                WHERE tenant_id = ?
                  AND appointment_date BETWEEN ? AND ?
                GROUP BY appointment_date
            ");
            $monthStmt->execute([$tenantId, $monthStart, $monthEnd]);
            foreach ($monthStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $day = (string) ($row['appointment_date'] ?? '');
                if ($day !== '') {
                    $monthCounts[$day] = (int) ($row['day_count'] ?? 0);
                }
            }

            $serviceTypeExpr = $qAps !== null
                ? "(
                    SELECT COALESCE(GROUP_CONCAT(svc.service_name SEPARATOR ', '), a.service_type)
                    FROM {$qAps} svc
                    WHERE svc.tenant_id = a.tenant_id
                      AND {$apsMatchSql}
                )"
                : 'a.service_type';
            $serviceIdsExpr = $qAps !== null
                ? "(
                    SELECT COALESCE(GROUP_CONCAT(DISTINCT svc.service_id ORDER BY svc.service_id SEPARATOR ','), '')
                    FROM {$qAps} svc
                    WHERE svc.tenant_id = a.tenant_id
                      AND {$apsMatchSql}
                )"
                : "''";
            $directServiceDescriptionExpr = ($qSvc !== null && in_array('service_name', $svcCols, true) && in_array('service_details', $svcCols, true))
                ? "(
                    SELECT NULLIF(TRIM(COALESCE(svcd2.service_details, '')), '')
                    FROM {$qSvc} svcd2
                    WHERE svcd2.tenant_id = a.tenant_id
                      AND LOWER(TRIM(COALESCE(svcd2.service_name, ''))) = LOWER(TRIM(COALESCE(a.service_type, '')))
                    LIMIT 1
                )"
                : "NULL";
            $serviceDescriptionExpr = ($qAps !== null && $qSvc !== null && in_array('service_id', $apsCols, true) && in_array('service_id', $svcCols, true) && in_array('service_details', $svcCols, true))
                ? "COALESCE(
                (
                    SELECT NULLIF(
                        GROUP_CONCAT(
                            DISTINCT NULLIF(TRIM(COALESCE(svcd.service_details, '')), '')
                            SEPARATOR '; '
                        ),
                        ''
                    )
                    FROM {$qAps} apsd
                    INNER JOIN {$qSvc} svcd
                        ON svcd.tenant_id = apsd.tenant_id
                       AND svcd.service_id = apsd.service_id
                    WHERE apsd.tenant_id = a.tenant_id
                      AND {$apsMatchSqlForDetails}
                ),
                {$directServiceDescriptionExpr},
                a.service_description
            )"
                : "COALESCE({$directServiceDescriptionExpr}, a.service_description)";
            $bookingTypeJoinCatalog = '';
            $bookingTypeWhenCatalog = '';
            if ($qSvc !== null && in_array('service_id', $apsCols, true) && in_array('service_id', $svcCols, true)
                && (in_array('service_type', $svcCols, true) || in_array('treatment_type', $svcCols, true))) {
                $bookingTypeJoinCatalog = "LEFT JOIN {$qSvc} cat ON cat.tenant_id = svc.tenant_id AND cat.service_id = svc.service_id";
                if (in_array('service_type', $svcCols, true)) {
                    $bookingTypeWhenCatalog .= "
                        WHEN SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(cat.service_type), ''), 'regular')) = 'installment' THEN 1 ELSE 0 END) > 0 THEN 'Long Term'";
                }
                if (in_array('treatment_type', $svcCols, true)) {
                    $bookingTypeWhenCatalog .= "
                        WHEN SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(cat.treatment_type), ''), 'short_term')) = 'long_term' THEN 1 ELSE 0 END) > 0 THEN 'Long Term'";
                }
            }
            $bookingTypeExpr = $qAps !== null
                ? "COALESCE(
                (
                    SELECT CASE
                        WHEN SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(svc.service_type), ''), 'regular')) = 'installment' THEN 1 ELSE 0 END) > 0 THEN 'Long Term'
                        {$bookingTypeWhenCatalog}
                        WHEN LOWER(COALESCE(NULLIF(TRIM(a.treatment_type), ''), 'short_term')) = 'long_term' THEN 'Long Term'
                        ELSE 'Short Term'
                    END
                    FROM {$qAps} svc
                    {$bookingTypeJoinCatalog}
                    WHERE svc.tenant_id = a.tenant_id
                      AND {$apsMatchSql}
                ),
                (
                    CASE
                        WHEN LOWER(COALESCE(NULLIF(TRIM(a.treatment_type), ''), 'short_term')) = 'long_term' THEN 'Long Term'
                        ELSE 'Short Term'
                    END
                )
            )"
                : "(
                    CASE
                        WHEN LOWER(COALESCE(NULLIF(TRIM(a.treatment_type), ''), 'short_term')) = 'long_term' THEN 'Long Term'
                        ELSE 'Short Term'
                    END
                )";

            $totalPaidSelectSql = $qPay !== null
                ? "(
                    SELECT COALESCE(SUM(py.amount), 0)
                    FROM {$qPay} py
                    WHERE py.tenant_id = a.tenant_id
                      AND py.booking_id = a.booking_id
                      AND py.status = 'completed'
                ) AS total_paid"
                : '0 AS total_paid';

            $patientEmailExpr = in_array('email', $patCols, true)
                ? 'p.email'
                : (in_array('patient_email', $patCols, true) ? 'p.patient_email' : 'NULL');
            $patientSelectSql = $qPat !== null
                ? 'p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.patient_id AS patient_display_id,
                ' . $patientEmailExpr . ' AS patient_email'
                : 'NULL AS patient_first_name,
                NULL AS patient_last_name,
                NULL AS patient_contact_number,
                a.patient_id AS patient_display_id,
                NULL AS patient_email';

            $assignedStaffSelectSql = ($qDent !== null && in_array('dentist_id', $apptCols, true) && in_array('dentist_id', $dentCols, true))
                ? "COALESCE(
                    {$dentistNameExpr},
                    " . ($qUsr !== null ? "NULLIF(TRIM(COALESCE(u.full_name, '')), '')" : "NULL") . ",
                    " . ($qUsr !== null ? "NULLIF(TRIM(COALESCE(u.email, '')), '')" : "NULL") . ",
                    'Unassigned'
                ) AS assigned_staff_name"
                : (($qUsr !== null)
                    ? "COALESCE(NULLIF(TRIM(COALESCE(u.full_name, '')), ''), NULLIF(TRIM(COALESCE(u.email, '')), ''), 'Unassigned') AS assigned_staff_name"
                    : "'Unassigned' AS assigned_staff_name");

            $patientJoinSql = $qPat !== null
                ? "LEFT JOIN {$qPat} p
              ON p.tenant_id = a.tenant_id
             AND p.patient_id = a.patient_id"
                : '';
            $userJoinSql = $qUsr !== null
                ? "LEFT JOIN {$qUsr} u
              ON u.user_id = a.created_by"
                : '';
            $dentistJoinSql = ($qDent !== null && in_array('dentist_id', $apptCols, true) && in_array('dentist_id', $dentCols, true))
                ? "LEFT JOIN {$qDent} d
              ON d.tenant_id = a.tenant_id
             AND d.dentist_id = a.dentist_id"
                : '';

            $dailySql = "
            SELECT
                " . ($apptIdCol !== null ? "a.{$apptIdCol} AS appointment_row_id," : "NULL AS appointment_row_id,") . "
                a.booking_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                {$serviceTypeExpr} as service_type,
                {$serviceIdsExpr} AS appointment_service_ids,
                {$serviceDescriptionExpr} AS service_description,
                a.treatment_type,
                {$bookingTypeExpr} AS booking_type_label,
                a.status,
                a.notes,
                a.total_treatment_cost,
                {$totalPaidSelectSql},
                a.created_by,
                {$patientSelectSql},
                {$assignedStaffSelectSql}
            FROM {$qAppt} a
            {$patientJoinSql}
            {$userJoinSql}
            {$dentistJoinSql}
            WHERE a.tenant_id = ?
              AND a.appointment_date = ?
        ";
            $params = [$tenantId, $selectedDate];

            if ($selectedStatus !== 'all') {
                $dailySql .= " AND a.status = ?";
                $params[] = $selectedStatus;
            }

            if ($searchTerm !== '') {
                if ($qPat !== null) {
                    $dailySql .= " AND (
                a.booking_id LIKE ?
                OR a.service_type LIKE ?
                OR a.service_description LIKE ?
                OR a.patient_id LIKE ?
                OR CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) LIKE ?
            )";
                    $like = '%' . $searchTerm . '%';
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                } else {
                    $dailySql .= " AND (
                a.booking_id LIKE ?
                OR a.service_type LIKE ?
                OR a.service_description LIKE ?
                OR a.patient_id LIKE ?
            )";
                    $like = '%' . $searchTerm . '%';
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                    $params[] = $like;
                }
            }

            $dailySql .= ' ORDER BY a.appointment_time DESC, a.created_at DESC';
            $dailyStmt = $pdo->prepare($dailySql);
            $dailyStmt->execute($params);
            $dailyAppointments = $dailyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($qAps !== null && !empty($dailyAppointments)) {
                $bookingIds = [];
                $appointmentRowIds = [];
                foreach ($dailyAppointments as $row) {
                    $bookingIdVal = trim((string) ($row['booking_id'] ?? ''));
                    if ($bookingIdVal !== '') {
                        $bookingIds[$bookingIdVal] = true;
                    }
                    $appointmentRowIdVal = (int) ($row['appointment_row_id'] ?? 0);
                    if ($appointmentRowIdVal > 0) {
                        $appointmentRowIds[$appointmentRowIdVal] = true;
                    }
                }
                $bookingIdList = array_keys($bookingIds);
                $appointmentRowIdList = array_keys($appointmentRowIds);
                if (!empty($bookingIdList)) {
                    $bookingPlaceholders = implode(',', array_fill(0, count($bookingIdList), '?'));
                    $svcJoinCatalog = '';
                    $svcSelectCatalog = '';
                    if ($qSvc !== null && in_array('service_id', $apsCols, true) && in_array('service_id', $svcCols, true)
                        && (in_array('service_type', $svcCols, true) || in_array('treatment_type', $svcCols, true))) {
                        $svcJoinCatalog = "LEFT JOIN {$qSvc} cat ON cat.tenant_id = aps.tenant_id AND cat.service_id = aps.service_id";
                        if (in_array('service_type', $svcCols, true)) {
                            $svcSelectCatalog .= ', cat.service_type AS catalog_service_type';
                        }
                        if (in_array('treatment_type', $svcCols, true)) {
                            $svcSelectCatalog .= ', cat.treatment_type AS catalog_treatment_type';
                        }
                    }
                    $svcSql = "
                        SELECT aps.booking_id, aps.service_name, aps.service_type" . ($supportsApsAppointmentId ? ", aps.appointment_id" : ", NULL AS appointment_id") . "{$svcSelectCatalog}
                        FROM {$qAps} aps
                        {$svcJoinCatalog}
                        WHERE aps.tenant_id = ?
                          AND aps.booking_id IN ({$bookingPlaceholders})
                    ";
                    $svcParams = array_merge([$tenantId], $bookingIdList);
                    if ($supportsApsAppointmentId && !empty($appointmentRowIdList)) {
                        $apptPlaceholders = implode(',', array_fill(0, count($appointmentRowIdList), '?'));
                        $svcSql .= " AND (aps.appointment_id IN ({$apptPlaceholders}) OR aps.appointment_id IS NULL)";
                        $svcParams = array_merge($svcParams, $appointmentRowIdList);
                    }
                    $svcSql .= ' ORDER BY aps.id ASC';
                    $svcStmt = $pdo->prepare($svcSql);
                    $svcStmt->execute($svcParams);
                    foreach ($svcStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $svcRow) {
                        $svcBookingId = trim((string) ($svcRow['booking_id'] ?? ''));
                        if ($svcBookingId === '') {
                            continue;
                        }
                        $svcName = trim((string) ($svcRow['service_name'] ?? ''));
                        if ($svcName === '') {
                            $svcName = 'General Consultation';
                        }
                        $normalizedSvcType = strtolower(trim((string) ($svcRow['service_type'] ?? 'regular')));
                        $catalogSvcType = strtolower(trim((string) ($svcRow['catalog_service_type'] ?? '')));
                        $catalogTreatmentType = strtolower(trim((string) ($svcRow['catalog_treatment_type'] ?? '')));
                        $isLongTermLine = ($normalizedSvcType === 'installment')
                            || ($catalogSvcType === 'installment')
                            || ($catalogTreatmentType === 'long_term');
                        $svcTypeLabel = $isLongTermLine ? 'Long Term' : 'Short Term';
                        $serviceEntry = [
                            'name' => $svcName,
                            'type_label' => $svcTypeLabel,
                            'raw_type' => $isLongTermLine ? 'installment' : $normalizedSvcType,
                        ];
                        $svcAppointmentId = (int) ($svcRow['appointment_id'] ?? 0);
                        if ($supportsApsAppointmentId && $svcAppointmentId > 0) {
                            $idKey = 'id:' . $svcAppointmentId;
                            if (!isset($appointmentServicesByKey[$idKey])) {
                                $appointmentServicesByKey[$idKey] = [];
                            }
                            $appointmentServicesByKey[$idKey][] = $serviceEntry;
                        }
                        $bookingKey = 'booking:' . $svcBookingId;
                        if (!isset($appointmentServicesByKey[$bookingKey])) {
                            $appointmentServicesByKey[$bookingKey] = [];
                        }
                        $appointmentServicesByKey[$bookingKey][] = $serviceEntry;
                    }
                }
            }

            if ($qSvc !== null) {
                $servicesStmt = $pdo->prepare("
                    SELECT service_id, service_name, category, price
                    FROM {$qSvc}
                    WHERE tenant_id = ? AND status = 'active'
                    ORDER BY service_name ASC
                ");
                $servicesStmt->execute([$tenantId]);
                $availableServices = $servicesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    }
} catch (Throwable $e) {
    error_log('Staff appointments load error: ' . $e->getMessage());
}

$statusLabels = [
    'all' => 'All Statuses',
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'no_show' => 'No Show',
];
/** Human-readable label for a normalized appointment status (Daily Schedule, modal). */
$appointmentStatusText = [
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'no_show' => 'No Show',
    'scheduled' => 'Pending',
    'confirmed' => 'Pending',
];
$walkInBookingHref = BASE_URL . 'StaffWalkIn.php';
if ($currentTenantSlug !== '') {
    $walkInBookingHref .= '?' . http_build_query(['clinic_slug' => $currentTenantSlug]);
}
$setAppointmentHref = BASE_URL . 'StaffSetAppointments.php';
if ($currentTenantSlug !== '') {
    $setAppointmentHref .= '?' . http_build_query(['clinic_slug' => $currentTenantSlug]);
}
$qrCheckinApiUrl = BASE_URL . 'api/qr_checkin.php';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Appointments | Clinical Precisions</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background": "#f8fafc",
                        "surface": "#ffffff",
                        "on-background": "#101922",
                        "on-surface-variant": "#404752"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem"
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .elevated-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .staff-modal-overlay:not(.hidden) {
            animation: staff-modal-fade-in 0.25s ease forwards;
        }
        .staff-modal-panel {
            animation: staff-modal-panel-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .booking-type-option {
            transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.28s ease, border-color 0.28s ease, background-color 0.28s ease;
        }
        .booking-type-option:hover {
            transform: translateY(-3px);
            border-color: rgba(43, 139, 235, 0.5);
            box-shadow: 0 14px 28px -14px rgba(43, 139, 235, 0.45);
            background-color: rgba(239, 246, 255, 0.95);
        }
        .booking-type-option:active {
            transform: translateY(-1px) scale(0.99);
        }
        body.appointments-modal-open {
            overflow: hidden;
        }
        .appointments-page-blurred {
            filter: blur(2px);
            transition: filter 0.2s ease;
        }
        .staff-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(2px);
        }
        .booking-action-btn {
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.25s ease, background-color 0.25s ease;
        }
        .booking-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 18px 30px -18px rgba(43, 139, 235, 0.9);
        }
        .booking-action-btn:active {
            transform: translateY(0) scale(0.98);
        }
        @keyframes staff-modal-fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes staff-modal-panel-in {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main id="appointmentsPageContent" class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
    <div class="p-10 space-y-8">
        <?php if ($pageNotice): ?>
            <?php $noticeIsSuccess = $pageNotice['type'] === 'success'; ?>
            <section class="rounded-2xl border px-4 py-3 <?php echo $noticeIsSuccess ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-700'; ?>">
                <p class="text-sm font-semibold"><?php echo htmlspecialchars((string) $pageNotice['message'], ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php endif; ?>
        <section class="flex flex-col gap-4">
            <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
                <span class="w-12 h-[1.5px] bg-primary"></span> APPOINTMENT MANAGEMENT
            </div>
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Bookings <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Manager</span>
                    </h2>
                    <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                        Daily schedule with status tracking and treatment details.
                    </p>
                </div>
                <div class="shrink-0 pt-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:gap-4 lg:gap-5">
                    <div class="flex flex-col gap-2">
                        <label for="openPatientCheckInQrBtn" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-widest">
                            Check In for Patient
                        </label>
                        <button
                            type="button"
                            id="openPatientCheckInQrBtn"
                            class="booking-action-btn inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 font-bold text-sm tracking-wide shadow-lg shadow-blue-600/25 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                            aria-label="Open patient check-in QR"
                        >
                            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">qr_code_2</span>
                        </button>
                    </div>
                    <div class="flex flex-col gap-2">
                        <span class="block text-[10px] font-black uppercase tracking-widest invisible pointer-events-none select-none" aria-hidden="true">&nbsp;</span>
                        <button
                            type="button"
                            id="openBookingTypeModalBtn"
                            class="booking-action-btn inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-primary to-blue-500 hover:from-primary hover:to-blue-600 text-white px-5 py-3 font-bold text-sm tracking-wide shadow-lg shadow-primary/30"
                        >
                            <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">add_circle</span>
                            Add New Booking
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="elevated-card p-6 rounded-3xl">
            <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <input type="hidden" name="clinic_slug" value="<?php echo htmlspecialchars($currentTenantSlug, ENT_QUOTES, 'UTF-8'); ?>"/>
                <input type="hidden" name="month" value="<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES, 'UTF-8'); ?>"/>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-widest mb-2">Date</label>
                    <input class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold" type="date" name="date" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>"/>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-widest mb-2">Status</label>
                    <select class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold" name="status">
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedStatus === $value ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-widest mb-2">Search</label>
                    <input class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold" type="text" name="q" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Patient, booking, service"/>
                </div>
                <button class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-primary/25" type="submit">
                    Apply Filters
                </button>
            </form>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="elevated-card p-7 rounded-3xl">
                <div class="w-11 h-11 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mb-5">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_available</span>
                </div>
                <p class="text-4xl font-extrabold tracking-tight"><?php echo number_format($summary['in_progress']); ?></p>
                <p class="text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">In Progress</p>
            </div>
            <div class="elevated-card p-7 rounded-3xl">
                <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center mb-5">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">cancel</span>
                </div>
                <p class="text-4xl font-extrabold tracking-tight"><?php echo number_format($summary['cancelled']); ?></p>
                <p class="text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Cancelled</p>
            </div>
            <div class="elevated-card p-7 rounded-3xl">
                <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mb-5">
                    <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">pending_actions</span>
                </div>
                <p class="text-4xl font-extrabold tracking-tight"><?php echo number_format($summary['pending']); ?></p>
                <p class="text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Pending</p>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
            <div class="xl:col-span-2 elevated-card rounded-3xl overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-white">
                    <h3 class="text-2xl font-bold font-headline text-on-background">Daily Schedule</h3>
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                        <?php echo htmlspecialchars(date('M d, Y', strtotime($selectedDate)), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                        <tr class="bg-slate-50/70">
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Time</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Patient</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Service</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Type</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Status</th>
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500 text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (empty($dailyAppointments)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-slate-500 font-semibold">No appointments found for this day/filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dailyAppointments as $appointment): ?>
                                <?php
                                $patientName = trim(((string) ($appointment['patient_first_name'] ?? '')) . ' ' . ((string) ($appointment['patient_last_name'] ?? '')));
                                if ($patientName === '') {
                                    $patientName = 'Unknown Patient';
                                }
                                $timeLabel = !empty($appointment['appointment_time']) ? date('g:i A', strtotime((string) $appointment['appointment_time'])) : '-';
                                $statusResolved = staff_appointments_resolve_status_for_ui($appointment['status'] ?? '', $appointmentStatusText);
                                $statusRaw = $statusResolved['code'];
                                $statusLabel = $statusResolved['label'];
                                $statusClass = $statusResolved['class'];
                                $appointmentRowId = (int) ($appointment['appointment_row_id'] ?? 0);
                                $serviceKey = $appointmentRowId > 0 ? ('id:' . $appointmentRowId) : ('booking:' . (string) ($appointment['booking_id'] ?? ''));
                                $serviceLines = $appointmentServicesByKey[$serviceKey] ?? [];
                                if (empty($serviceLines)) {
                                    $fallbackTypeLabel = trim((string) ($appointment['booking_type_label'] ?? 'Short Term'));
                                    if ($fallbackTypeLabel === '') {
                                        $fallbackTypeLabel = 'Short Term';
                                    }
                                    $serviceLines[] = [
                                        'name' => (string) ($appointment['service_type'] ?? 'General Consultation'),
                                        'type_label' => $fallbackTypeLabel,
                                        'raw_type' => $fallbackTypeLabel === 'Long Term' ? 'installment' : 'regular',
                                    ];
                                }
                                $hasInstallmentInLines = false;
                                $serviceLineNames = [];
                                foreach ($serviceLines as $line) {
                                    $serviceLineNames[] = (string) ($line['name'] ?? '');
                                    if (strtolower(trim((string) ($line['raw_type'] ?? 'regular'))) === 'installment') {
                                        $hasInstallmentInLines = true;
                                    }
                                }
                                $typeLabel = $hasInstallmentInLines ? 'Long Term' : 'Short Term';
                                $treatmentType = $hasInstallmentInLines ? 'long_term' : 'short_term';
                                $serviceLabelForModal = implode(', ', array_values(array_filter($serviceLineNames, static function ($name) {
                                    return trim((string) $name) !== '';
                                })));
                                if ($serviceLabelForModal === '') {
                                    $serviceLabelForModal = (string) ($appointment['service_type'] ?? '');
                                }
                                $totalCost = (float) ($appointment['total_treatment_cost'] ?? 0);
                                $totalPaid = (float) ($appointment['total_paid'] ?? 0);
                                $pendingBalance = max(0, $totalCost - $totalPaid);
                                $patientIdLabel = (string) ($appointment['patient_display_id'] ?? $appointment['patient_id'] ?? 'N/A');
                                $staffLabel = trim((string) ($appointment['assigned_staff_name'] ?? ''));
                                if ($staffLabel === '') {
                                    $staffLabel = 'Unassigned';
                                }
                                ?>
                                <tr class="hover:bg-slate-50/40 transition-colors">
                                    <td class="px-6 py-5 text-sm font-bold text-primary"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-6 py-5">
                                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500 mt-0.5"><?php echo htmlspecialchars((string) $patientIdLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="space-y-1.5 min-w-[10rem]">
                                            <?php foreach ($serviceLines as $line): ?>
                                                <div class="min-h-[1.6rem] flex items-center">
                                                    <p class="text-sm font-semibold text-slate-700 leading-tight">
                                                        <?php echo htmlspecialchars((string) ($line['name'] ?? 'General Consultation'), ENT_QUOTES, 'UTF-8'); ?>
                                                    </p>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5">
                                        <div class="space-y-1.5 min-w-[9rem]">
                                            <?php foreach ($serviceLines as $line): ?>
                                                <?php
                                                $lineTypeLabel = (string) ($line['type_label'] ?? 'Short Term');
                                                $lineTypeClass = strtolower(trim($lineTypeLabel)) === 'long term'
                                                    ? 'bg-orange-50 text-orange-700 border border-orange-200'
                                                    : 'bg-blue-50 text-blue-700 border border-blue-200';
                                                ?>
                                                <div class="min-h-[1.6rem] flex items-center">
                                                    <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?php echo htmlspecialchars($lineTypeClass, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($lineTypeLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 whitespace-nowrap">
                                        <span class="inline-flex items-center justify-center px-3 py-1 rounded-full <?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?> text-[10px] font-black uppercase tracking-wider whitespace-nowrap">
                                            <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <button
                                            type="button"
                                            class="open-treatment-modal inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 text-primary hover:border-primary transition-all"
                                            data-booking-id="<?php echo htmlspecialchars((string) ($appointment['booking_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-patient-name="<?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-patient-id="<?php echo htmlspecialchars((string) $patientIdLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-patient-contact="<?php echo htmlspecialchars((string) ($appointment['patient_contact_number'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-patient-email="<?php echo htmlspecialchars((string) ($appointment['patient_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-staff="<?php echo htmlspecialchars($staffLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-date="<?php echo htmlspecialchars(date('F j, Y', strtotime((string) ($appointment['appointment_date'] ?? $selectedDate))), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-time="<?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-type="<?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-treatment="<?php echo htmlspecialchars($serviceLabelForModal, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-description="<?php echo htmlspecialchars((string) ($appointment['service_description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-cost="<?php echo htmlspecialchars((string) $totalCost, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-total-paid="<?php echo htmlspecialchars((string) $totalPaid, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-pending-balance="<?php echo htmlspecialchars((string) $pendingBalance, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-notes="<?php echo htmlspecialchars((string) ($appointment['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status-raw="<?php echo htmlspecialchars($statusRaw, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-treatment-type-raw="<?php echo htmlspecialchars($treatmentType, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-existing-service-ids="<?php echo htmlspecialchars((string) ($appointment['appointment_service_ids'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            <span class="material-symbols-outlined text-[20px]">visibility</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/40">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                        Showing <?php echo number_format(count($dailyAppointments)); ?> appointment(s)
                    </p>
                </div>
            </div>

            <div class="elevated-card rounded-3xl p-6 self-start h-fit">
                <div class="flex items-center justify-between mb-5">
                    <a href="<?php echo htmlspecialchars(buildAppointmentsUrl(['month' => $prevMonth]), ENT_QUOTES, 'UTF-8'); ?>" class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:text-primary hover:border-primary transition-colors">
                        <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                    </a>
                    <h4 class="text-lg font-bold text-primary"><?php echo htmlspecialchars(date('F Y', strtotime($selectedMonth . '-01')), ENT_QUOTES, 'UTF-8'); ?></h4>
                    <a href="<?php echo htmlspecialchars(buildAppointmentsUrl(['month' => $nextMonth]), ENT_QUOTES, 'UTF-8'); ?>" class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 hover:text-primary hover:border-primary transition-colors">
                        <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                    </a>
                </div>
                <div class="grid grid-cols-7 gap-2 text-center text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">
                    <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
                </div>
                <div class="grid grid-cols-7 gap-2">
                    <?php
                    $firstDayTs = strtotime($selectedMonth . '-01');
                    $firstDayWeekIndex = (int) date('w', $firstDayTs ?: time());
                    $daysInMonth = (int) date('t', $firstDayTs ?: time());
                    for ($blank = 0; $blank < $firstDayWeekIndex; $blank++):
                    ?>
                        <div class="h-10"></div>
                    <?php endfor; ?>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                        <?php
                        $dayDate = sprintf('%s-%02d', $selectedMonth, $day);
                        $isSelected = $dayDate === $selectedDate;
                        $count = isset($monthCounts[$dayDate]) ? (int) $monthCounts[$dayDate] : 0;
                        ?>
                        <a
                            href="<?php echo htmlspecialchars(buildAppointmentsUrl(['date' => $dayDate]), ENT_QUOTES, 'UTF-8'); ?>"
                            class="h-10 rounded-xl border flex items-center justify-center relative text-sm font-bold transition-colors <?php echo $isSelected ? 'bg-primary text-white border-primary' : 'border-slate-200 text-slate-700 hover:border-primary hover:text-primary'; ?>"
                        >
                            <?php echo (int) $day; ?>
                            <?php if ($count > 0): ?>
                                <span class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full <?php echo $isSelected ? 'bg-white' : 'bg-primary'; ?>"></span>
                            <?php endif; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </section>
    </div>
</main>

<div id="bookingTypeModal" class="staff-modal-overlay hidden fixed inset-0 z-[80]">
    <div class="staff-modal-backdrop" id="bookingTypeBackdrop"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="staff-modal-panel bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-100 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-primary/70">Create Booking</p>
                    <h4 class="text-xl font-bold text-on-background">Select Booking Type</h4>
                </div>
                <button type="button" id="bookingTypeModalCloseBtn" class="w-8 h-8 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-5 bg-slate-50/60">
                <p class="text-xs font-semibold text-slate-500 mb-4">Choose the type of appointment you want to create.</p>
                <div class="space-y-3">
                    <button type="button" id="bookingTypeWalkInBtn" class="booking-type-option w-full rounded-2xl border border-primary/20 bg-white px-4 py-3 text-left">
                        <span class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-3">
                                <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-blue-500 text-white flex items-center justify-center shadow-md shadow-primary/30">
                                    <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">directions_walk</span>
                                </span>
                                <span>
                                    <span class="block text-base font-bold text-slate-900">Walk-In</span>
                                    <span class="block text-xs font-medium text-slate-500 mt-0.5">Create an appointment for a patient without prior booking.</span>
                                </span>
                            </span>
                            <span class="material-symbols-outlined text-primary">arrow_forward</span>
                        </span>
                    </button>
                    <button type="button" id="bookingTypeAppointmentBtn" class="booking-type-option w-full rounded-2xl border border-primary/20 bg-white px-4 py-3 text-left">
                        <span class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-3">
                                <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-blue-500 text-white flex items-center justify-center shadow-md shadow-primary/30">
                                    <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">calendar_month</span>
                                </span>
                                <span>
                                    <span class="block text-base font-bold text-slate-900">Appointment</span>
                                    <span class="block text-xs font-medium text-slate-500 mt-0.5">Schedule a future appointment for a patient.</span>
                                </span>
                            </span>
                            <span class="material-symbols-outlined text-primary">arrow_forward</span>
                        </span>
                    </button>
                </div>
                <div class="mt-4">
                    <button type="button" id="bookingTypeCancelBtn" class="w-full rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 text-sm font-bold py-2.5 transition-colors">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="treatmentModal" class="staff-modal-overlay hidden fixed inset-0 z-[70]">
    <div class="staff-modal-backdrop" id="modalBackdrop"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="staff-modal-panel bg-white w-full max-w-5xl rounded-3xl shadow-2xl border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-white">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-slate-400">Appointment Overview</p>
                    <h4 class="text-2xl font-bold text-primary">Treatment Details</h4>
                </div>
                <button type="button" id="modalCloseBtn" class="w-8 h-8 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6 space-y-5 text-sm max-h-[80vh] overflow-y-auto bg-slate-50/50">
                <div class="bg-white rounded-2xl border border-slate-200 px-4 py-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Booking ID</p>
                        <p id="mBookingId" class="mt-1 text-sm font-extrabold text-primary">-</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Date & Time</p>
                        <p class="mt-1 text-sm font-bold text-slate-800"><span id="mDate">-</span> • <span id="mTime">-</span></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Treatment Type</p>
                        <p id="mType" class="mt-1 text-sm font-bold text-slate-800">-</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-wider text-slate-400">Status</p>
                        <p id="mStatus" class="mt-1 text-sm font-bold text-slate-800">-</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
                    <div class="xl:col-span-2 space-y-5">
                        <div class="bg-white rounded-2xl border border-slate-200 p-4">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-3">Patient Information</p>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-xs font-black uppercase tracking-wide text-slate-400">Name</dt>
                                    <dd id="mPatientName" class="mt-1 font-bold text-slate-900">-</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-black uppercase tracking-wide text-slate-400">Patient ID</dt>
                                    <dd id="mPatientId" class="mt-1 font-bold text-slate-900">-</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-black uppercase tracking-wide text-slate-400">Contact Number</dt>
                                    <dd id="mPatientContact" class="mt-1 font-bold text-slate-900">-</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-black uppercase tracking-wide text-slate-400">Patient Email</dt>
                                    <dd id="mPatientEmail" class="mt-1 font-bold text-slate-900">-</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-black uppercase tracking-wide text-slate-400">Assigned Staff</dt>
                                    <dd id="mStaff" class="mt-1 font-bold text-slate-900">-</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="bg-white rounded-2xl border border-slate-200 p-4">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-3">Treatment Information</p>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">Treatment / Service</p>
                                    <p id="mTreatment" class="mt-1 font-bold text-slate-900">-</p>
                                </div>
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">Service Description</p>
                                    <p id="mDescription" class="mt-1 font-medium text-slate-700 bg-slate-50 border border-slate-200 p-3 rounded-xl">-</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <p class="text-xs font-black uppercase tracking-wide text-slate-400">Total Cost</p>
                                        <p id="mCost" class="mt-1 font-bold text-slate-900">-</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-black uppercase tracking-wide text-slate-400">Notes</p>
                                        <p id="mNotes" class="mt-1 font-medium text-slate-700">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="bg-white rounded-2xl border border-slate-200 p-4">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-3">Payment Balance</p>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-semibold">Total Cost</span>
                                    <span id="mBalanceTotalCost" class="font-black text-slate-900">P0.00</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-600 font-semibold">Total Paid</span>
                                    <span id="mBalanceTotalPaid" class="font-black text-emerald-600">P0.00</span>
                                </div>
                                <div class="h-px bg-slate-200"></div>
                                <div class="flex items-center justify-between">
                                    <span class="text-slate-800 font-bold">Pending Balance</span>
                                    <span id="mBalancePending" class="font-black text-rose-600">P0.00</span>
                                </div>
                            </div>
                            <div id="mPaymentWarning" class="hidden mt-3 rounded-xl border border-primary/25 bg-primary/5 text-primary px-3 py-2">
                                <p class="text-xs font-semibold leading-relaxed">Appointment status is based on visit progress only and does not depend on payment status.</p>
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-slate-200 p-4">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-3">Update Status</p>
                            <form method="post" class="space-y-3">
                                <input type="hidden" name="modal_action" value="update_status"/>
                                <input type="hidden" name="modal_booking_id" id="statusBookingId" value=""/>
                                <select id="statusSelector" name="update_status" class="w-full bg-white border border-slate-200 rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold text-slate-700">
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="no_show">No Show</option>
                                </select>
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">update</span>
                                    Update Status
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="appointment-success-toast" class="fixed top-24 right-6 md:right-10 z-[200] max-w-md w-[min(100%-3rem,28rem)] flex justify-end transition-all duration-300 ease-out opacity-0 translate-y-3 scale-[0.98] pointer-events-none" aria-hidden="true" role="status" aria-live="polite">
    <div class="pointer-events-auto rounded-2xl border border-emerald-200/90 bg-white/95 backdrop-blur-md shadow-2xl shadow-slate-900/10 px-4 py-3.5 flex items-start gap-3">
        <span class="material-symbols-outlined text-emerald-600 shrink-0 text-2xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
        <p class="text-sm font-semibold text-slate-800 leading-snug flex-1 pt-0.5" id="appointment-success-toast-msg"></p>
        <button type="button" class="shrink-0 rounded-lg p-1 text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" id="appointment-success-toast-close" aria-label="Dismiss notification">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
    </div>
</div>

<div id="qrCheckInModal" class="staff-modal-overlay hidden fixed inset-0 z-[90]" aria-hidden="true">
    <div class="staff-modal-backdrop" id="qrCheckInBackdrop"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="staff-modal-panel bg-white w-full max-w-md rounded-3xl shadow-2xl border border-slate-100 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="qrCheckInTitle">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.18em] text-primary/70">Scan</p>
                    <h4 id="qrCheckInTitle" class="text-xl font-bold text-on-background">Patient Check-In</h4>
                </div>
                <button type="button" id="qrCheckInCloseBtn" class="w-8 h-8 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-5 bg-slate-50/60 space-y-4">
                <p class="text-sm font-medium text-slate-600">Scan the patient&apos;s booking QR code.</p>
                <div>
                    <label for="qrCheckInInput" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-widest mb-2">Scanner input</label>
                    <input
                        id="qrCheckInInput"
                        type="text"
                        class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary text-sm font-mono"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        aria-describedby="qrCheckInError"
                    />
                </div>
                <div id="qrCheckInError" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-800" role="alert"></div>
                <div class="flex justify-end">
                    <button type="button" id="qrCheckInCloseFooterBtn" class="rounded-xl border border-slate-200 bg-white hover:bg-slate-100 text-slate-700 text-sm font-bold px-5 py-2.5 transition-colors">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="qrCheckInSuccessModal" class="staff-modal-overlay hidden fixed inset-0 z-[95]" aria-hidden="true">
    <div class="staff-modal-backdrop" id="qrCheckInSuccessBackdrop"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="staff-modal-panel bg-white w-full max-w-md rounded-3xl shadow-2xl border border-emerald-100 overflow-hidden" role="dialog" aria-modal="true" aria-labelledby="qrCheckInSuccessTitle">
            <div class="px-5 py-5 text-center space-y-4">
                <span class="material-symbols-outlined text-emerald-600 text-5xl block mx-auto" style="font-variation-settings: 'FILL' 1;">check_circle</span>
                <div>
                    <h4 id="qrCheckInSuccessTitle" class="text-xl font-bold text-on-background">Checked in</h4>
                    <p class="text-sm text-slate-500 mt-1">Appointment status updated.</p>
                </div>
                <dl class="text-left rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400 shrink-0">Patient</dt>
                        <dd id="qrCheckInSuccessPatient" class="font-bold text-slate-900 text-right break-words"></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400 shrink-0">Booking ID</dt>
                        <dd id="qrCheckInSuccessBooking" class="font-bold text-primary text-right break-all"></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[10px] font-black uppercase tracking-wider text-slate-400 shrink-0">Status</dt>
                        <dd id="qrCheckInSuccessStatus" class="font-bold text-blue-700 text-right"></dd>
                    </div>
                </dl>
                <button type="button" id="qrCheckInSuccessOkBtn" class="w-full rounded-xl bg-primary hover:bg-primary/90 text-white text-sm font-bold py-3 transition-colors">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('treatmentModal');
    const modalBackdrop = document.getElementById('modalBackdrop');
    const closeBtn = document.getElementById('modalCloseBtn');
    const openButtons = document.querySelectorAll('.open-treatment-modal');
    const bookingTypeModal = document.getElementById('bookingTypeModal');
    const bookingTypeBackdrop = document.getElementById('bookingTypeBackdrop');
    const bookingTypeOpenBtn = document.getElementById('openBookingTypeModalBtn');
    const bookingTypeCloseBtn = document.getElementById('bookingTypeModalCloseBtn');
    const bookingTypeCancelBtn = document.getElementById('bookingTypeCancelBtn');
    const bookingTypeWalkInBtn = document.getElementById('bookingTypeWalkInBtn');
    const bookingTypeAppointmentBtn = document.getElementById('bookingTypeAppointmentBtn');
    const appointmentsPageContent = document.getElementById('appointmentsPageContent');

    const qrCheckInModal = document.getElementById('qrCheckInModal');
    const qrCheckInBackdrop = document.getElementById('qrCheckInBackdrop');
    const qrCheckInOpenBtn = document.getElementById('openPatientCheckInQrBtn');
    const qrCheckInCloseBtn = document.getElementById('qrCheckInCloseBtn');
    const qrCheckInCloseFooterBtn = document.getElementById('qrCheckInCloseFooterBtn');
    const qrCheckInInput = document.getElementById('qrCheckInInput');
    const qrCheckInError = document.getElementById('qrCheckInError');
    const qrCheckInSuccessModal = document.getElementById('qrCheckInSuccessModal');
    const qrCheckInSuccessBackdrop = document.getElementById('qrCheckInSuccessBackdrop');
    const qrCheckInSuccessOkBtn = document.getElementById('qrCheckInSuccessOkBtn');
    const qrCheckInSuccessPatient = document.getElementById('qrCheckInSuccessPatient');
    const qrCheckInSuccessBooking = document.getElementById('qrCheckInSuccessBooking');
    const qrCheckInSuccessStatus = document.getElementById('qrCheckInSuccessStatus');
    const qrCheckinApiUrl = <?php echo json_encode($qrCheckinApiUrl, JSON_UNESCAPED_SLASHES); ?>;

    let qrCheckInBusy = false;
    let qrCheckInSuccessTimer = null;

    function hideQrCheckInError() {
        if (!qrCheckInError) return;
        qrCheckInError.textContent = '';
        qrCheckInError.classList.add('hidden');
    }

    function showQrCheckInError(message) {
        if (!qrCheckInError) return;
        qrCheckInError.textContent = message;
        qrCheckInError.classList.remove('hidden');
    }

    function focusQrCheckInInput() {
        window.requestAnimationFrame(function () {
            if (qrCheckInInput) {
                qrCheckInInput.focus();
                qrCheckInInput.select();
            }
        });
    }

    function openQrCheckInModal() {
        if (!qrCheckInModal) return;
        hideQrCheckInError();
        qrCheckInModal.classList.remove('hidden');
        qrCheckInModal.setAttribute('aria-hidden', 'false');
        if (qrCheckInInput) {
            qrCheckInInput.value = '';
        }
        syncModalVisualState();
        focusQrCheckInInput();
    }

    function closeQrCheckInModal() {
        if (!qrCheckInModal) return;
        qrCheckInModal.classList.add('hidden');
        qrCheckInModal.setAttribute('aria-hidden', 'true');
        hideQrCheckInError();
        if (qrCheckInInput) {
            qrCheckInInput.value = '';
        }
        syncModalVisualState();
    }

    function closeQrCheckInSuccessModal() {
        if (qrCheckInSuccessTimer) {
            clearTimeout(qrCheckInSuccessTimer);
            qrCheckInSuccessTimer = null;
        }
        if (!qrCheckInSuccessModal) return;
        qrCheckInSuccessModal.classList.add('hidden');
        qrCheckInSuccessModal.setAttribute('aria-hidden', 'true');
        syncModalVisualState();
        if (qrCheckInModal && !qrCheckInModal.classList.contains('hidden')) {
            focusQrCheckInInput();
        }
    }

    function openQrCheckInSuccessModal(data) {
        if (!qrCheckInSuccessModal) return;
        if (qrCheckInSuccessPatient) qrCheckInSuccessPatient.textContent = data.patient_name || '';
        if (qrCheckInSuccessBooking) qrCheckInSuccessBooking.textContent = data.booking_id || '';
        if (qrCheckInSuccessStatus) qrCheckInSuccessStatus.textContent = data.status_label || data.status || 'In Progress';
        qrCheckInSuccessModal.classList.remove('hidden');
        qrCheckInSuccessModal.setAttribute('aria-hidden', 'false');
        syncModalVisualState();
        if (qrCheckInSuccessTimer) clearTimeout(qrCheckInSuccessTimer);
        qrCheckInSuccessTimer = window.setTimeout(function () {
            closeQrCheckInSuccessModal();
        }, 2600);
    }

    async function submitQrCheckInScan(raw) {
        const payload = typeof raw === 'string' ? raw.trim() : '';
        if (payload === '' || qrCheckInBusy) return;
        qrCheckInBusy = true;
        hideQrCheckInError();
        try {
            const res = await fetch(qrCheckinApiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ scan: payload }),
            });
            let json = null;
            try {
                json = await res.json();
            } catch (e) {
                showQrCheckInError('Could not read server response.');
                return;
            }
            if (res.status === 401) {
                showQrCheckInError('Session expired. Please sign in again.');
                return;
            }
            if (res.status === 403 || (json && json.data && json.data.code === 'forbidden')) {
                showQrCheckInError('Please sign in again.');
                return;
            }
            const code = json && json.data && json.data.code ? String(json.data.code) : '';
            const msg = json && json.message ? String(json.message) : 'Check-in failed.';
            if (!json || json.success !== true) {
                const alerts = {
                    invalid_qr: 'Invalid QR code.',
                    not_found: 'Booking not found.',
                    wrong_date: 'Appointment is not scheduled for today.',
                    cancelled: 'This appointment was cancelled.',
                    completed: 'This appointment is already completed.',
                    status_save_failed: msg,
                    forbidden: 'Please sign in again.',
                    server_error: msg,
                };
                showQrCheckInError(alerts[code] || msg);
                return;
            }
            const d = json.data || {};
            if (qrCheckInInput) {
                qrCheckInInput.value = '';
            }
            openQrCheckInSuccessModal({
                patient_name: d.patient_name || '',
                booking_id: d.booking_id || '',
                status_label: d.status_label || 'In Progress',
                status: d.status || 'in_progress',
            });
            focusQrCheckInInput();
        } catch (err) {
            showQrCheckInError('Network error. Try again.');
        } finally {
            qrCheckInBusy = false;
        }
    }

    function setText(id, value) {
        const node = document.getElementById(id);
        if (node) {
            node.textContent = value && value.trim() !== '' ? value : '-';
        }
    }

    function parseMoney(value) {
        const normalized = String(value || '0').replace(/,/g, '').trim();
        const parsed = Number.parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function syncModalVisualState() {
        const treatmentOpen = modal && !modal.classList.contains('hidden');
        const bookingTypeOpen = bookingTypeModal && !bookingTypeModal.classList.contains('hidden');
        const qrOpen = qrCheckInModal && !qrCheckInModal.classList.contains('hidden');
        const qrSuccessOpen = qrCheckInSuccessModal && !qrCheckInSuccessModal.classList.contains('hidden');
        const hasOpenModal = treatmentOpen || bookingTypeOpen || qrOpen || qrSuccessOpen;
        document.body.classList.toggle('appointments-modal-open', hasOpenModal);
        if (appointmentsPageContent) {
            appointmentsPageContent.classList.toggle('appointments-page-blurred', hasOpenModal);
        }
    }

    function openModal(button) {
        const totalCost = parseMoney(button.dataset.cost);
        const totalPaid = parseMoney(button.dataset.totalPaid);
        const pendingBalance = parseMoney(button.dataset.pendingBalance);
        const statusRaw = (button.dataset.statusRaw || '').toLowerCase();

        setText('mBookingId', button.dataset.bookingId || '');
        setText('mPatientName', button.dataset.patientName || '');
        setText('mPatientId', button.dataset.patientId || '');
        setText('mPatientContact', button.dataset.patientContact || '');
        setText('mPatientEmail', button.dataset.patientEmail || '');
        setText('mStaff', button.dataset.staff || '');
        setText('mDate', button.dataset.date || '');
        setText('mTime', button.dataset.time || '');
        setText('mType', button.dataset.type || '');
        setText('mStatus', button.dataset.status || '');
        setText('mTreatment', button.dataset.treatment || '');
        setText('mDescription', button.dataset.description || '');
        setText('mCost', 'PHP ' + totalCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        setText('mNotes', button.dataset.notes || '');

        setText('mBalanceTotalCost', 'PHP ' + totalCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        setText('mBalanceTotalPaid', 'PHP ' + totalPaid.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        setText('mBalancePending', 'PHP ' + pendingBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        const statusBookingId = document.getElementById('statusBookingId');
        const statusSelector = document.getElementById('statusSelector');
        const warning = document.getElementById('mPaymentWarning');
        if (statusBookingId) statusBookingId.value = button.dataset.bookingId || '';

        if (statusSelector) {
            let selectedStatus = 'pending';
            if (statusRaw === 'completed' || statusRaw === 'no_show' || statusRaw === 'pending' || statusRaw === 'in_progress' || statusRaw === 'cancelled') {
                selectedStatus = statusRaw;
            } else if (statusRaw === 'scheduled' || statusRaw === 'confirmed') {
                selectedStatus = 'pending';
            }
            statusSelector.value = selectedStatus;
            if (warning) {
                warning.classList.remove('hidden');
            }
        }
        modal.classList.remove('hidden');
        syncModalVisualState();
    }

    function closeModal() {
        modal.classList.add('hidden');
        syncModalVisualState();
    }

    function openBookingTypeModal() {
        if (!bookingTypeModal) return;
        bookingTypeModal.classList.remove('hidden');
        syncModalVisualState();
    }

    function closeBookingTypeModal() {
        if (!bookingTypeModal) return;
        bookingTypeModal.classList.add('hidden');
        syncModalVisualState();
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', () => openModal(button));
    });

    closeBtn.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', closeModal);
    if (bookingTypeOpenBtn) bookingTypeOpenBtn.addEventListener('click', openBookingTypeModal);
    if (bookingTypeCloseBtn) bookingTypeCloseBtn.addEventListener('click', closeBookingTypeModal);
    if (bookingTypeCancelBtn) bookingTypeCancelBtn.addEventListener('click', closeBookingTypeModal);
    if (bookingTypeBackdrop) bookingTypeBackdrop.addEventListener('click', closeBookingTypeModal);
    if (bookingTypeWalkInBtn) {
        bookingTypeWalkInBtn.addEventListener('click', () => {
            window.location.href = <?php echo json_encode($walkInBookingHref, JSON_UNESCAPED_SLASHES); ?>;
        });
    }
    if (bookingTypeAppointmentBtn) {
        bookingTypeAppointmentBtn.addEventListener('click', () => {
            window.location.href = <?php echo json_encode($setAppointmentHref, JSON_UNESCAPED_SLASHES); ?>;
        });
    }
    if (qrCheckInOpenBtn) qrCheckInOpenBtn.addEventListener('click', openQrCheckInModal);
    if (qrCheckInCloseBtn) qrCheckInCloseBtn.addEventListener('click', closeQrCheckInModal);
    if (qrCheckInCloseFooterBtn) qrCheckInCloseFooterBtn.addEventListener('click', closeQrCheckInModal);
    if (qrCheckInBackdrop) qrCheckInBackdrop.addEventListener('click', closeQrCheckInModal);
    if (qrCheckInSuccessBackdrop) qrCheckInSuccessBackdrop.addEventListener('click', closeQrCheckInSuccessModal);
    if (qrCheckInSuccessOkBtn) qrCheckInSuccessOkBtn.addEventListener('click', closeQrCheckInSuccessModal);
    if (qrCheckInInput) {
        qrCheckInInput.addEventListener('input', function () {
            hideQrCheckInError();
        });
        qrCheckInInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitQrCheckInScan(qrCheckInInput.value);
            }
        });
    }
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && qrCheckInSuccessModal && !qrCheckInSuccessModal.classList.contains('hidden')) {
            closeQrCheckInSuccessModal();
            event.preventDefault();
            return;
        }
        if (event.key === 'Escape' && qrCheckInModal && !qrCheckInModal.classList.contains('hidden')) {
            closeQrCheckInModal();
            event.preventDefault();
            return;
        }
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
        if (event.key === 'Escape' && bookingTypeModal && !bookingTypeModal.classList.contains('hidden')) {
            closeBookingTypeModal();
        }
    });

    let appointmentsPageWasHidden = false;
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            appointmentsPageWasHidden = true;
            return;
        }
        if (document.visibilityState === 'visible' && appointmentsPageWasHidden) {
            appointmentsPageWasHidden = false;
            window.location.reload();
        }
    });

    (function initStatusUpdateToast() {
        const toast = document.getElementById('appointment-success-toast');
        const msgEl = document.getElementById('appointment-success-toast-msg');
        const closeBtn = document.getElementById('appointment-success-toast-close');
        const message = <?php echo json_encode($statusUpdateToastMessage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        if (!toast || !msgEl || !message) {
            return;
        }
        let hideTimer = null;
        function hideToast() {
            if (hideTimer) {
                clearTimeout(hideTimer);
                hideTimer = null;
            }
            toast.setAttribute('aria-hidden', 'true');
            toast.classList.remove('opacity-100', 'translate-y-0', 'scale-100');
            toast.classList.add('opacity-0', 'translate-y-3', 'scale-[0.98]', 'pointer-events-none');
        }
        function showToast() {
            msgEl.textContent = message;
            toast.setAttribute('aria-hidden', 'false');
            toast.classList.remove('opacity-0', 'translate-y-3', 'scale-[0.98]', 'pointer-events-none');
            toast.classList.add('opacity-100', 'translate-y-0', 'scale-100');
            hideTimer = window.setTimeout(hideToast, 4800);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', hideToast);
        }
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(showToast);
        });
    })();

    syncModalVisualState();
</script>
</body>
</html>