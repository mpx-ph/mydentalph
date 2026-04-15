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
$monthCounts = [];

$monthStart = $selectedMonth . '-01';
$monthStartTs = strtotime($monthStart);
$monthEnd = date('Y-m-t', $monthStartTs ?: time());
$prevMonth = date('Y-m', strtotime('-1 month', $monthStartTs ?: time()));
$nextMonth = date('Y-m', strtotime('+1 month', $monthStartTs ?: time()));
$pageNotice = null;
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

if (isset($_SESSION['staff_appointments_notice']) && is_array($_SESSION['staff_appointments_notice'])) {
    $noticeType = (string) ($_SESSION['staff_appointments_notice']['type'] ?? '');
    $noticeMessage = (string) ($_SESSION['staff_appointments_notice']['message'] ?? '');
    if ($noticeType !== '' && $noticeMessage !== '') {
        $pageNotice = ['type' => $noticeType, 'message' => $noticeMessage];
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

            $statusStmt = $pdo->prepare("
                UPDATE {$qAppt}
                SET status = ?
                WHERE tenant_id = ? AND booking_id = ?
                LIMIT 1
            ");
            $statusStmt->execute([$newStatus, $tenantId, $bookingId]);
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

        if ($tAppt === null) {
            error_log('Staff appointments: appointments table not found.');
        } else {
            $qAppt = clinic_quote_identifier($tAppt);
            $qAps = $tAps !== null ? clinic_quote_identifier($tAps) : null;
            $qPat = $tPat !== null ? clinic_quote_identifier($tPat) : null;
            $qUsr = $tUsr !== null ? clinic_quote_identifier($tUsr) : null;
            $qPay = $tPay !== null ? clinic_quote_identifier($tPay) : null;
            $qSvc = $tSvc !== null ? clinic_quote_identifier($tSvc) : null;
            $apptCols = clinic_table_columns($pdo, $tAppt);
            $apsCols = $tAps !== null ? clinic_table_columns($pdo, $tAps) : [];
            $apptIdCol = in_array('id', $apptCols, true) ? 'id' : (in_array('appointment_id', $apptCols, true) ? 'appointment_id' : null);
            $supportsApsAppointmentId = in_array('appointment_id', $apsCols, true) && $apptIdCol !== null;
            $apsMatchSql = $supportsApsAppointmentId
                ? 'svc.appointment_id = a.' . $apptIdCol
                : 'svc.booking_id = a.booking_id';

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
            $bookingTypeExpr = $qAps !== null
                ? "(
                    SELECT CASE
                        WHEN SUM(CASE WHEN LOWER(COALESCE(NULLIF(TRIM(svc.service_type), ''), 'regular')) = 'installment' THEN 1 ELSE 0 END) > 0 THEN 'Long Term'
                        ELSE 'Short Term'
                    END
                    FROM {$qAps} svc
                    WHERE svc.tenant_id = a.tenant_id
                      AND {$apsMatchSql}
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

            $patientSelectSql = $qPat !== null
                ? 'p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.patient_id AS patient_display_id'
                : 'NULL AS patient_first_name,
                NULL AS patient_last_name,
                NULL AS patient_contact_number,
                a.patient_id AS patient_display_id';

            $createdBySelectSql = $qUsr !== null
                ? 'u.email AS created_by_email'
                : 'NULL AS created_by_email';

            $patientJoinSql = $qPat !== null
                ? "LEFT JOIN {$qPat} p
              ON p.tenant_id = a.tenant_id
             AND p.patient_id = a.patient_id"
                : '';
            $userJoinSql = $qUsr !== null
                ? "LEFT JOIN {$qUsr} u
              ON u.user_id = a.created_by"
                : '';

            $dailySql = "
            SELECT
                a.booking_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                {$serviceTypeExpr} as service_type,
                {$serviceIdsExpr} AS appointment_service_ids,
                a.service_description,
                a.treatment_type,
                {$bookingTypeExpr} AS booking_type_label,
                a.status,
                a.notes,
                a.total_treatment_cost,
                {$totalPaidSelectSql},
                a.created_by,
                {$patientSelectSql},
                {$createdBySelectSql}
            FROM {$qAppt} a
            {$patientJoinSql}
            {$userJoinSql}
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
$walkInBookingHref = BASE_URL . 'StaffWalkIn.php';
if ($currentTenantSlug !== '') {
    $walkInBookingHref .= '?' . http_build_query(['clinic_slug' => $currentTenantSlug]);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Appointments | Clinical Precision</title>
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
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
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
                <div class="shrink-0">
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

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
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
                            <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Treatment</th>
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
                                $statusRaw = strtolower(trim((string) ($appointment['status'] ?? 'pending')));
                                if ($statusRaw === 'confirmed' || $statusRaw === 'scheduled') {
                                    $statusRaw = 'pending';
                                }
                                $statusLabel = ucfirst(str_replace('_', ' ', $statusRaw));
                                $statusClass = 'bg-amber-50 text-amber-600';
                                if ($statusRaw === 'in_progress') {
                                    $statusClass = 'bg-primary/10 text-primary';
                                } elseif ($statusRaw === 'cancelled') {
                                    $statusClass = 'bg-rose-50 text-rose-600';
                                } elseif ($statusRaw === 'completed') {
                                    $statusClass = 'bg-emerald-50 text-emerald-600';
                                } elseif ($statusRaw === 'no_show') {
                                    $statusClass = 'bg-slate-200 text-slate-700';
                                }
                                $typeLabel = trim((string) ($appointment['booking_type_label'] ?? ''));
                                if ($typeLabel === '') {
                                    $typeLabel = 'Short Term';
                                }
                                $treatmentType = $typeLabel === 'Long Term' ? 'long_term' : 'short_term';
                                $normalizedTypeLabel = strtolower(trim($typeLabel));
                                $typeBadgeClass = 'bg-blue-50 text-blue-700 border border-blue-200';
                                if ($normalizedTypeLabel === 'long term') {
                                    $typeBadgeClass = 'bg-orange-50 text-orange-700 border border-orange-200';
                                }
                                $totalCost = (float) ($appointment['total_treatment_cost'] ?? 0);
                                $totalPaid = (float) ($appointment['total_paid'] ?? 0);
                                $pendingBalance = max(0, $totalCost - $totalPaid);
                                $patientIdLabel = (string) ($appointment['patient_display_id'] ?? $appointment['patient_id'] ?? 'N/A');
                                $staffLabel = trim((string) ($appointment['created_by_email'] ?? ''));
                                if ($staffLabel === '') {
                                    $staffLabel = trim((string) ($appointment['created_by'] ?? ''));
                                }
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
                                    <td class="px-6 py-5 text-sm font-semibold text-slate-700"><?php echo htmlspecialchars((string) ($appointment['service_type'] ?? 'General Consultation'), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-6 py-5">
                                        <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider <?php echo htmlspecialchars($typeBadgeClass, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-5">
                                        <span class="px-2.5 py-1 rounded-full <?php echo $statusClass; ?> text-[10px] font-black uppercase tracking-wider">
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
                                            data-staff="<?php echo htmlspecialchars($staffLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-date="<?php echo htmlspecialchars(date('F j, Y', strtotime((string) ($appointment['appointment_date'] ?? $selectedDate))), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-time="<?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-type="<?php echo htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-treatment="<?php echo htmlspecialchars((string) ($appointment['service_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-description="<?php echo htmlspecialchars((string) ($appointment['service_description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-cost="<?php echo htmlspecialchars((string) $totalCost, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-total-paid="<?php echo htmlspecialchars((string) $totalPaid, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-pending-balance="<?php echo htmlspecialchars((string) $pendingBalance, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-notes="<?php echo htmlspecialchars((string) ($appointment['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status-raw="<?php echo htmlspecialchars((string) ($appointment['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?>"
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

            <div class="elevated-card rounded-3xl p-6">
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
    <div class="absolute inset-0 bg-slate-900/50" id="bookingTypeBackdrop"></div>
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
    <div class="absolute inset-0 bg-slate-900/50" id="modalBackdrop"></div>
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
                <div class="bg-white rounded-2xl border border-slate-200 p-4">
                    <div class="mb-3 space-y-1">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Additional Services</p>
                            <p class="text-xs font-semibold text-slate-500">Select one or more services to append in this booking.</p>
                        </div>
                        <p class="text-xs font-medium text-slate-500 leading-relaxed">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 text-slate-600 px-2.5 py-1">
                                <span class="material-symbols-outlined text-[14px] text-slate-500" aria-hidden="true">info</span>
                                <span>Already included: services listed on this appointment cannot be selected again here.</span>
                            </span>
                        </p>
                    </div>
                    <form method="post" class="space-y-3">
                        <input type="hidden" name="modal_action" value="add_services"/>
                        <input type="hidden" name="modal_booking_id" id="addServiceBookingId" value=""/>
                        <div class="border border-slate-200 rounded-xl p-2.5 max-h-56 overflow-y-auto bg-slate-50/40 space-y-1.5">
                            <?php if (empty($availableServices)): ?>
                                <p class="text-sm font-semibold text-slate-500">No active services available.</p>
                            <?php else: ?>
                                <?php foreach ($availableServices as $service): ?>
                                    <?php
                                    $serviceId = (string) ($service['service_id'] ?? '');
                                    $serviceName = (string) ($service['service_name'] ?? 'Service');
                                    $serviceCategory = (string) ($service['category'] ?? 'General');
                                    $servicePrice = (float) ($service['price'] ?? 0);
                                    ?>
                                    <label class="additional-service-option flex items-center justify-between gap-3 p-2.5 rounded-lg hover:bg-white border border-transparent hover:border-slate-200">
                                        <span class="flex items-start gap-3 min-w-0">
                                            <input type="checkbox" name="service_ids[]" value="<?php echo htmlspecialchars($serviceId, ENT_QUOTES, 'UTF-8'); ?>" class="mt-1 rounded border-slate-300 text-primary focus:ring-primary/30">
                                            <span class="min-w-0">
                                                <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                    <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($serviceName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <span class="svc-already-inline hidden shrink-0 text-[10px] font-black uppercase tracking-wider text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 bg-white">Already on appointment</span>
                                                </span>
                                                <span class="block text-xs text-slate-500"><?php echo htmlspecialchars($serviceCategory, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </span>
                                        </span>
                                        <span class="text-sm font-black text-primary">P<?php echo htmlspecialchars(number_format($servicePrice, 2), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors">
                            <span class="material-symbols-outlined text-[18px]">add</span>
                            Add Extra Services
                        </button>
                    </form>
                </div>
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

    function openModal(button) {
        const totalCost = parseMoney(button.dataset.cost);
        const totalPaid = parseMoney(button.dataset.totalPaid);
        const pendingBalance = parseMoney(button.dataset.pendingBalance);
        const statusRaw = (button.dataset.statusRaw || '').toLowerCase();

        setText('mBookingId', button.dataset.bookingId || '');
        setText('mPatientName', button.dataset.patientName || '');
        setText('mPatientId', button.dataset.patientId || '');
        setText('mPatientContact', button.dataset.patientContact || '');
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

        const addServiceBookingId = document.getElementById('addServiceBookingId');
        const statusBookingId = document.getElementById('statusBookingId');
        const statusSelector = document.getElementById('statusSelector');
        const warning = document.getElementById('mPaymentWarning');
        const existingRaw = button.dataset.existingServiceIds || '';
        const existingIds = new Set(
            existingRaw
                .split(',')
                .map((s) => String(s).trim())
                .filter((s) => s !== '')
        );
        document.querySelectorAll('#treatmentModal input[name="service_ids[]"]').forEach((checkbox) => {
            const sid = String(checkbox.value || '').trim();
            const alreadyOnBooking = sid !== '' && existingIds.has(sid);
            checkbox.checked = false;
            checkbox.disabled = alreadyOnBooking;
            const label = checkbox.closest('label.additional-service-option');
            if (label) {
                label.classList.toggle('opacity-60', alreadyOnBooking);
                label.classList.toggle('pointer-events-none', alreadyOnBooking);
                label.classList.toggle('hover:bg-white', !alreadyOnBooking);
                const badge = label.querySelector('.svc-already-inline');
                if (badge) {
                    badge.classList.toggle('hidden', !alreadyOnBooking);
                }
            }
        });
        if (addServiceBookingId) addServiceBookingId.value = button.dataset.bookingId || '';
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
    }

    function closeModal() {
        document.querySelectorAll('#treatmentModal input[name="service_ids[]"]').forEach((checkbox) => {
            checkbox.checked = false;
            checkbox.disabled = false;
            const label = checkbox.closest('label.additional-service-option');
            if (label) {
                label.classList.remove('opacity-60', 'pointer-events-none');
                label.classList.add('hover:bg-white');
                const badge = label.querySelector('.svc-already-inline');
                if (badge) {
                    badge.classList.add('hidden');
                }
            }
        });
        modal.classList.add('hidden');
    }

    function openBookingTypeModal() {
        if (!bookingTypeModal) return;
        bookingTypeModal.classList.remove('hidden');
    }

    function closeBookingTypeModal() {
        if (!bookingTypeModal) return;
        bookingTypeModal.classList.add('hidden');
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
        bookingTypeAppointmentBtn.addEventListener('click', closeBookingTypeModal);
    }
    document.addEventListener('keydown', (event) => {
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
</script>
</body>
</html>