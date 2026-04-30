<?php
// Patient reminder emails are sent by the cron job clinic/cron/send_appointment_reminders.php (timing: clinic/includes/appointment_reminder_service.php).
$pageTitle = 'Set Appointment';
$staff_nav_active = 'appointments';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/tenant.php';
require_once __DIR__ . '/includes/appointment_db_tables.php';
require_once __DIR__ . '/includes/availability.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentStaffUserId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';

if (empty($_GET['clinic_slug']) && !empty($_SESSION['public_tenant_slug'])) {
    $_GET['clinic_slug'] = $_SESSION['public_tenant_slug'];
}

if (empty($_GET['clinic_slug'])) {
    $reqUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $reqPath = $reqUri !== '' ? parse_url($reqUri, PHP_URL_PATH) : '';
    $scriptBase = isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : 'StaffSetAppointments.php';
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

$manilaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
$selectedDateValue = $manilaNow->format('Y-m-d');
$selectedTimeValue = $manilaNow->format('H:i');

$baseParams = [];
if ($currentTenantSlug !== '') {
    $baseParams['clinic_slug'] = $currentTenantSlug;
}
$manilaToday = $manilaNow->format('Y-m-d');
$baseParams['date'] = $manilaToday;
$baseParams['month'] = substr($manilaToday, 0, 7);
// Same-origin paths from this script (do not use BASE_URL here — on some hosts it differs from SCRIPT_NAME and breaks API/redirects).
$clinicWebRoot = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
$backToAppointmentsHref = $clinicWebRoot . '/StaffAppointments.php' . ($baseParams ? ('?' . http_build_query($baseParams)) : '');
$appointmentsCreateApiPath = $clinicWebRoot . '/api/appointments.php';

/**
 * Build dentist schedule and availability snapshot for a target date/time.
 *
 * @return array<int, array<string, mixed>>
 */
function buildSetAppointmentDentistsSnapshot(PDO $pdo, string $tenantId, string $targetDate, string $targetTime = ''): array
{
    $wiTables = clinic_resolve_appointment_db_tables($pdo);
    $wiDentists = $wiTables['dentists'] ?? null;
    if ($wiDentists === null) {
        return [];
    }

    $wiQDent = '`' . str_replace('`', '``', $wiDentists) . '`';
    $stmt = $pdo->prepare("
        SELECT
            d.dentist_id,
            COALESCE(NULLIF(TRIM(d.user_id), ''), NULLIF(TRIM(u.user_id), ''), '') AS user_id,
            COALESCE(d.dentist_display_id, '') AS dentist_display_id,
            COALESCE(d.first_name, '') AS first_name,
            COALESCE(d.last_name, '') AS last_name,
            COALESCE(d.profile_image, '') AS profile_image,
            COALESCE(d.status, 'active') AS status
        FROM {$wiQDent} d
        LEFT JOIN tbl_users u
            ON u.tenant_id = d.tenant_id
            AND LOWER(TRIM(COALESCE(u.email, ''))) = LOWER(TRIM(COALESCE(d.email, '')))
            AND u.role = 'dentist'
        WHERE d.tenant_id = ?
        ORDER BY d.first_name ASC, d.last_name ASC
    ");
    $stmt->execute([$tenantId]);
    $walkInDentists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $normalizeBlockReason = static function (string $blockType, string $notes): string {
        $normalizedType = strtolower(trim($blockType));
        if (in_array($normalizedType, ['break', 'emergency', 'personal', 'other'], true)) {
            return ucfirst($normalizedType);
        }
        if (preg_match('/^\s*Reason\s*:\s*(.+)$/im', $notes, $matches)) {
            $parsed = trim((string) ($matches[1] ?? ''));
            if ($parsed !== '') {
                return ucwords(strtolower($parsed));
            }
        }
        return 'Blocked';
    };
    $formatDisplayTime = static function (string $timeValue): string {
        $timeValue = trim($timeValue);
        if ($timeValue === '') {
            return '';
        }
        $formats = ['H:i:s', 'H:i'];
        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $timeValue);
            if ($parsed instanceof DateTimeImmutable) {
                return $parsed->format('g:i A');
            }
        }
        return $timeValue;
    };
    $normalizeTimeValue = static function (string $timeValue): string {
        $timeValue = trim($timeValue);
        if ($timeValue === '') {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('H:i:s', $timeValue)
            ?: DateTimeImmutable::createFromFormat('H:i', $timeValue)
            ?: DateTimeImmutable::createFromFormat('G:i:s', $timeValue)
            ?: DateTimeImmutable::createFromFormat('G:i', $timeValue);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed->format('H:i:s');
        }
        return '';
    };

    $slotTime = $normalizeTimeValue($targetTime);

    foreach ($walkInDentists as &$dentistRow) {
        $dentistUserId = trim((string) ($dentistRow['user_id'] ?? ''));
        $shiftRanges = [];
        $shiftWindows = [];
        $isInsideShift = false;
        $activeBlockReason = '';
        $activeBlockTypes = ['break', 'emergency', 'personal', 'other'];
        if ($dentistUserId !== '') {
            $effectiveBlocks = clinic_get_effective_schedule_blocks($pdo, $tenantId, $dentistUserId, $targetDate);
            foreach ($effectiveBlocks as $block) {
                $blockType = strtolower((string) ($block['block_type'] ?? ''));
                $startTime = $normalizeTimeValue((string) ($block['start_time'] ?? ''));
                $endTime = $normalizeTimeValue((string) ($block['end_time'] ?? ''));
                if ($startTime === '' || $endTime === '' || $startTime >= $endTime) {
                    continue;
                }

                if (!in_array($blockType, ['shift', 'work'], true)) {
                    if (!in_array($blockType, $activeBlockTypes, true)) {
                        continue;
                    }
                    if ($slotTime !== '' && $slotTime >= $startTime && $slotTime < $endTime) {
                        $activeBlockReason = $normalizeBlockReason(
                            (string) ($block['block_type'] ?? ''),
                            (string) ($block['notes'] ?? '')
                        );
                    }
                    continue;
                }

                $shiftRanges[] = $formatDisplayTime($startTime) . ' - ' . $formatDisplayTime($endTime);
                $shiftWindows[] = [
                    'start' => $startTime,
                    'end' => $endTime,
                    'label' => $formatDisplayTime($startTime) . ' - ' . $formatDisplayTime($endTime),
                ];
                if ($slotTime !== '' && $slotTime >= $startTime && $slotTime < $endTime) {
                    $isInsideShift = true;
                }
            }
        }

        $dentistRow['schedule_label'] = !empty($shiftRanges)
            ? implode(' | ', array_values(array_unique($shiftRanges)))
            : 'No schedule';
        $dentistRow['shift_windows'] = array_values($shiftWindows);

        if ($slotTime === '') {
            $dentistRow['is_available_for_slot'] = 1;
            $dentistRow['availability_reason'] = !empty($shiftRanges) ? 'Scheduled' : 'No schedule';
            continue;
        }

        $isBlockedByActiveBlock = $activeBlockReason !== '';
        $dentistRow['is_available_for_slot'] = ($isInsideShift && !$isBlockedByActiveBlock) ? 1 : 0;
        if ($isBlockedByActiveBlock) {
            $dentistRow['availability_reason'] = 'Dentist is on ' . $activeBlockReason;
        } else {
            $dentistRow['availability_reason'] = $isInsideShift
                ? 'Available'
                : (!empty($shiftRanges) ? 'Outside Shift' : 'No schedule');
        }
    }
    unset($dentistRow);

    return $walkInDentists;
}

$walkInDentists = [];
$walkInPaymentSettings = [
    'regular_downpayment_percentage' => 20.0,
    'long_term_min_downpayment' => 500.0,
];
try {
    if (function_exists('getDBConnection')) {
        $pdo = getDBConnection();
        $tenantId = null;
        if (function_exists('getClinicTenantId')) {
            $tenantId = getClinicTenantId();
        }
        if (empty($tenantId) && isset($currentTenantId) && $currentTenantId !== '') {
            $tenantId = (string) $currentTenantId;
        }
        if (empty($tenantId) && !empty($_SESSION['tenant_id'])) {
            $tenantId = (string) $_SESSION['tenant_id'];
        }
        if (empty($tenantId) && !empty($_SESSION['public_tenant_id'])) {
            $tenantId = (string) $_SESSION['public_tenant_id'];
        }
        if ($pdo && $tenantId) {
            $todayDate = $manilaNow->format('Y-m-d');
            $todayTime = $manilaNow->format('H:i:s');
            $walkInDentists = buildSetAppointmentDentistsSnapshot($pdo, (string) $tenantId, $todayDate, $todayTime);

            $ajaxAction = isset($_GET['action']) ? trim((string) $_GET['action']) : '';
            if ($ajaxAction === 'dentist_availability') {
                $requestDate = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
                $requestTime = isset($_GET['time']) ? trim((string) $_GET['time']) : '';
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate)) {
                    $requestDate = $todayDate;
                }
                if ($requestTime !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $requestTime)) {
                    $requestTime = '';
                }

                $snapshot = buildSetAppointmentDentistsSnapshot($pdo, (string) $tenantId, $requestDate, $requestTime);
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                }
                echo json_encode([
                    'success' => true,
                    'message' => 'Dentist schedule loaded.',
                    'data' => [
                        'date' => $requestDate,
                        'time' => $requestTime,
                        'dentists' => $snapshot,
                    ],
                ]);
                exit;
            }
            $paymentSettingsTable = clinic_get_physical_table_name($pdo, 'tbl_payment_settings')
                ?? clinic_get_physical_table_name($pdo, 'payment_settings');
            if ($paymentSettingsTable !== null) {
                $qpSettings = '`' . str_replace('`', '``', $paymentSettingsTable) . '`';
                $psStmt = $pdo->prepare("
                    SELECT regular_downpayment_percentage, long_term_min_downpayment
                    FROM {$qpSettings}
                    WHERE tenant_id = ?
                    LIMIT 1
                ");
                $psStmt->execute([$tenantId]);
                $psRow = $psStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                if ($psRow && isset($psRow['regular_downpayment_percentage'])) {
                    $walkInPaymentSettings['regular_downpayment_percentage'] = (float) $psRow['regular_downpayment_percentage'];
                }
                if ($psRow && isset($psRow['long_term_min_downpayment'])) {
                    $walkInPaymentSettings['long_term_min_downpayment'] = (float) $psRow['long_term_min_downpayment'];
                }
            }
        }
    }
} catch (Throwable $e) {
    $walkInDentists = [];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Set Appointment | Clinical Precision</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
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
                    }
                }
            }
        };
    </script>
    <style>
        body { font-family: "Manrope", sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.02) 0px, transparent 50%);
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
        .walkin-input {
            border: none;
            background: #f8fafc;
            border-radius: 0.9rem;
            font-size: 0.86rem;
            font-weight: 700;
            color: #0f172a;
            transition: box-shadow 0.25s ease, background-color 0.25s ease;
        }
        .walkin-input:focus {
            outline: none;
            background: #f1f5f9;
            box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.18);
        }
        .walkin-input-bordered {
            border: 2px solid rgba(43, 139, 235, 0.35) !important;
            background: #ffffff;
            border-radius: 1rem !important;
        }
        .walkin-input-bordered:focus {
            border-color: rgba(43, 139, 235, 0.8) !important;
        }
        .walkin-primary-btn {
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.25s ease, filter 0.25s ease;
        }
        .walkin-primary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 35px -20px rgba(43, 139, 235, 0.9);
            filter: brightness(1.02);
        }
        .walkin-primary-btn:active {
            transform: translateY(0) scale(0.99);
        }
        .selection-pill-btn {
            border: 2px solid rgba(59, 130, 246, 0.35);
            background: #ffffff;
            border-radius: 1rem;
            transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        }
        .selection-pill-btn:hover {
            border-color: rgba(59, 130, 246, 0.55);
            box-shadow: 0 8px 20px -14px rgba(43, 139, 235, 0.65);
        }
        .selection-pill-btn:focus-visible {
            outline: none;
            border-color: rgba(43, 139, 235, 0.85);
            box-shadow: 0 0 0 3px rgba(43, 139, 235, 0.15);
        }
        .selection-pill-icon {
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 999px;
            background: linear-gradient(120deg, #2b8beb, #3b82f6);
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 8px 16px -10px rgba(43, 139, 235, 0.85);
        }
        .service-filter-chip {
            border: 1px solid #dbe5f1;
            background: #ffffff;
            color: #475569;
            border-radius: 999px;
            padding: 0.5rem 0.9rem;
            font-size: 0.69rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .service-filter-chip:hover {
            border-color: rgba(43, 139, 235, 0.35);
            color: #1e3a8a;
            background: rgba(43, 139, 235, 0.06);
        }
        .service-filter-chip.is-active {
            background: linear-gradient(120deg, #2b8beb, #3b82f6);
            border-color: #2b8beb;
            color: #ffffff;
            box-shadow: 0 8px 18px -12px rgba(43, 139, 235, 0.9);
        }
        .staff-modal-overlay:not(.hidden) {
            animation: staff-modal-fade-in 0.25s ease forwards;
        }
        .staff-modal-panel {
            animation: staff-modal-panel-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes staff-modal-fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes staff-modal-panel-in {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .field-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15) !important;
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="p-10 space-y-4">
        <section class="flex flex-col gap-2">
            <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
                <span class="w-12 h-[1.5px] bg-primary"></span> APPOINTMENT MANAGEMENT
            </div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Create <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Scheduled Appointment</span>
                    </h1>
                    <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-2">
                        Register and schedule a future patient appointment with full service and payment preview.
                    </p>
                </div>
                <div class="flex flex-col items-start lg:items-end gap-2 lg:mt-2">
                    <a
                        href="<?php echo htmlspecialchars($backToAppointmentsHref, ENT_QUOTES, 'UTF-8'); ?>"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-slate-700 transition-colors"
                    >
                        <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                        Back to Appointments
                    </a>
                    <div class="h-4"></div>
                    <button id="registerPatientBtnTop" type="button" class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-primary to-blue-500 text-white px-4 py-2.5 text-xs font-black uppercase tracking-wider shadow-lg shadow-primary/30 walkin-primary-btn">
                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">person_add</span>
                        Register New Patient
                    </button>
                    <p class="text-xs font-semibold text-slate-500 leading-relaxed max-w-xs lg:text-right whitespace-nowrap">
                        Click the button to register new Patient.
                    </p>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-stretch">
            <div class="xl:col-span-4 xl:row-span-2 flex min-h-0 self-stretch">
                <div class="elevated-card rounded-3xl p-6 h-full min-h-0 w-full flex flex-col">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person_search</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Patient Selection</p>
                            <h2 class="text-lg font-extrabold text-slate-900">Select Patient</h2>
                        </div>
                    </div>
                    <div class="space-y-3 flex-1 flex flex-col min-h-0">
                        <div class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Search Patient</span>
                            <input id="selectedPatientId" type="hidden" value=""/>
                            <input id="patientSearchInput" type="text" class="walkin-input walkin-input-bordered w-full py-3 px-4" placeholder="Search patient name, ID, or contact number"/>
                        </div>
                        <div class="flex items-stretch gap-2">
                            <div class="rounded-xl border border-primary/15 bg-primary/5 px-4 py-2.5 flex-1 max-w-[88%]">
                                <p class="text-[10px] font-black uppercase tracking-[0.18em] text-primary/70 mb-1">Selected Patient</p>
                                <p id="selectedPatientLabel" class="text-sm font-extrabold text-slate-900 truncate">Choose patient from list</p>
                            </div>
                            <button id="clearSelectedPatientBtn" type="button" class="shrink-0 h-auto px-2.5 rounded-md border border-slate-300 text-slate-500 hover:text-slate-700 hover:bg-white/70 inline-flex items-center justify-center transition-colors self-stretch" aria-label="Clear selected patient" title="Clear selected patient">
                                <span class="material-symbols-outlined text-[16px]">close</span>
                            </button>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/60 flex-1 min-h-[22rem] overflow-y-auto">
                            <div id="patientListEmptyState" class="hidden px-4 py-8 text-center text-sm font-semibold text-slate-500"></div>
                            <div id="patientListContainer" class="divide-y divide-slate-100"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-8 space-y-6">
                <div class="elevated-card rounded-3xl p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_note</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Appointment Details</p>
                            <h2 class="text-lg font-extrabold text-slate-900">Booking Information</h2>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Assigned Dentist</span>
                            <input id="selectedDentistId" type="hidden" value=""/>
                            <input id="selectedDentistUserId" type="hidden" value=""/>
                            <button id="chooseDentistBtn" type="button" class="selection-pill-btn w-full py-3 px-4 text-left inline-flex items-center justify-between gap-3">
                                <span class="inline-flex items-center gap-3 min-w-0">
                                    <span class="selection-pill-icon">
                                        <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">badge</span>
                                    </span>
                                    <span id="selectedDentistLabel" class="text-slate-900 text-[1.05rem] leading-tight font-extrabold truncate">Tap to choose dentist</span>
                                </span>
                                <span class="material-symbols-outlined text-[18px] text-slate-500">keyboard_arrow_down</span>
                            </button>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Service / Treatment</span>
                            <div class="flex items-center gap-2">
                                <input id="selectedServiceId" type="hidden" value=""/>
                                <button id="chooseServiceBtn" type="button" class="selection-pill-btn w-full py-3 px-4 text-left inline-flex items-center justify-between gap-3">
                                    <span class="inline-flex items-center gap-3 min-w-0">
                                        <span class="selection-pill-icon">
                                            <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">medical_services</span>
                                        </span>
                                        <span id="selectedServiceLabel" class="text-slate-900 text-[1.05rem] leading-tight font-extrabold truncate">Tap to choose service</span>
                                    </span>
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">keyboard_arrow_down</span>
                                </button>
                                <button id="addServiceBtn" type="button" class="w-11 h-11 rounded-xl bg-primary text-white inline-flex items-center justify-center hover:bg-primary/90 transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">add</span>
                                </button>
                            </div>
                            <div id="selectedServicesContainer" class="mt-3 flex flex-wrap gap-2"></div>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Appointment Date</span>
                            <input id="walkInDateInput" type="date" min="<?php echo htmlspecialchars($selectedDateValue, ENT_QUOTES, 'UTF-8'); ?>" class="walkin-input w-full py-3 px-4" value="<?php echo htmlspecialchars($selectedDateValue, ENT_QUOTES, 'UTF-8'); ?>"/>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Appointment Time</span>
                            <input id="walkInTimeInput" type="time" step="60" class="walkin-input w-full py-3 px-4" value="<?php echo htmlspecialchars($selectedTimeValue, ENT_QUOTES, 'UTF-8'); ?>"/>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Notes (Optional)</span>
                            <textarea id="walkInNotesInput" rows="4" class="walkin-input w-full py-3 px-4 resize-y" placeholder="Additional notes or special instructions for this appointment."></textarea>
                        </label>
                    </div>

                    <div class="mt-4 rounded-2xl border border-primary/15 bg-primary/5 px-4 py-3">
                        <p class="text-xs font-bold text-primary flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">info</span>
                            Scheduled Appointment
                        </p>
                        <p class="text-[11px] font-semibold text-slate-600 mt-1">
                            Choose the appointment date and time manually. Future schedules are supported.
                        </p>
                    </div>
                </div>

                <div id="walkInDefaultPaymentDetailsSection" class="elevated-card rounded-3xl p-6">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Payment Details</p>
                            <h3 class="text-lg font-extrabold text-slate-900">Payment Breakdown</h3>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-left">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total Amount</p>
                            <p id="walkInDefaultEstimatedTotal" class="mt-2 text-2xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Down Payment</p>
                            <p id="walkInDefaultDownPayment" class="mt-2 text-2xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Monthly</p>
                            <p id="walkInDefaultMonthlyEstimate" class="mt-2 text-2xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Duration</p>
                            <p id="walkInDefaultDurationMax" class="mt-2 text-2xl font-extrabold text-slate-900">0 Months</p>
                        </div>
                    </div>
                </div>

                <div id="walkInTreatmentPaymentProgressSection" class="elevated-card rounded-3xl p-6 hidden">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Payment Details</p>
                            <h3 class="text-lg font-extrabold text-slate-900">Treatment Payment Progress</h3>
                        </div>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-slate-500">
                            <span class="material-symbols-outlined text-[16px]">payments</span>
                            <span id="walkInInstallmentAvailable">Active Installment Treatment: No</span>
                        </span>
                    </div>
                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4 mb-4">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-600">
                            <span>Payment Completion</span>
                            <span id="walkInPaymentProgressLabel">0%</span>
                        </div>
                        <div class="mt-2 h-3 w-full rounded-full bg-slate-200 overflow-hidden">
                            <div id="walkInPaymentProgressBar" class="h-full bg-gradient-to-r from-primary to-blue-500 rounded-full" style="width: 0%;"></div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-left">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total Cost</p>
                            <p id="walkInTotalAmount" class="mt-2 text-2xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Amount Paid</p>
                            <p id="walkInAmountPaid" class="mt-2 text-xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Remaining Balance</p>
                            <p id="walkInRemainingBalance" class="mt-2 text-xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Months Left</p>
                            <p id="walkInMonthsLeft" class="mt-2 text-xl font-extrabold text-slate-900">0 Months</p>
                        </div>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-500 mt-4">Installment treatment progress is computed at treatment level and reused across follow-up visits.</p>
                </div>
            </div>

            <div class="xl:col-start-5 xl:col-span-8 w-full min-w-0">
                <button id="createWalkInAppointmentBtn" type="button" class="walkin-primary-btn w-full max-w-full rounded-2xl bg-gradient-to-r from-primary to-blue-500 text-white py-3.5 text-sm font-extrabold uppercase tracking-wide shadow-lg shadow-primary/35 inline-flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">calendar_add_on</span>
                    Create Scheduled Appointment
                </button>
            </div>
        </section>
    </div>
</main>

<div id="addPatientModal" class="staff-modal-overlay fixed inset-0 z-[75] hidden items-center justify-center bg-slate-900/50 backdrop-blur-[2px] p-4">
    <div class="staff-modal-panel bg-white rounded-3xl shadow-[0_24px_64px_-12px_rgba(15,23,42,0.25)] border border-slate-100 w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col">
        <div class="shrink-0 px-6 sm:px-8 pt-7 pb-5 border-b border-slate-100 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                <span class="material-symbols-outlined text-2xl text-primary">person_add</span>
            </div>
            <div class="min-w-0 flex-1 pr-2">
                <h2 id="patientModalTitle" class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight">Patient Registration</h2>
                <p class="text-sm text-slate-500 mt-1 leading-relaxed">Register a new patient to the clinic management system</p>
            </div>
            <button id="closeAddPatientModal" type="button" class="shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <form id="addPatientForm" class="flex-1 min-h-0 flex flex-col">
            <input id="editingPatientId" type="hidden" value=""/>
            <div class="px-6 sm:px-8 pt-3 pb-5 space-y-6 overflow-y-auto">
                <section>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-primary text-[22px]">badge</span>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Personal Information</h3>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">person</span>
                                    First Name <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addFirstName" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="Enter first name"/>
                                <p id="addFirstNameError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">badge</span>
                                    Last Name <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addLastName" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="Enter last name"/>
                                <p id="addLastNameError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                        <span class="material-symbols-outlined text-[18px] text-slate-500">event</span>
                                        Date of Birth <span class="text-red-500 font-bold">*</span>
                                    </label>
                                    <input id="addDob" type="date" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all"/>
                                    <p id="addDobError" class="mt-1 text-xs text-red-500 hidden"></p>
                                </div>
                                <div>
                                    <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                        <span class="material-symbols-outlined text-[18px] text-slate-500">cake</span>
                                        Age
                                    </label>
                                    <input id="addAge" type="text" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-600 text-[15px] shadow-inner" placeholder="0"/>
                                </div>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">wc</span>
                                    Gender <span class="text-red-500 font-bold">*</span>
                                </label>
                                <div class="flex items-center gap-6 h-[48px]">
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                        <input type="radio" name="addGender" value="Male" class="text-primary border-slate-300 accent-primary focus:ring-primary"/>
                                        Male
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                        <input type="radio" name="addGender" value="Female" class="text-primary border-slate-300 accent-primary focus:ring-primary"/>
                                        Female
                                    </label>
                                </div>
                                <p id="addGenderError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">bloodtype</span>
                                    Blood Type <span class="text-red-500 font-bold">*</span>
                                </label>
                                <select id="addBloodType" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer">
                                    <option value="">Select type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                                <p id="addBloodTypeError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">clinical_notes</span>
                                    Medical History &amp; Alerts <span class="ml-1 text-[11px] font-semibold text-slate-400">(Optional)</span>
                                </label>
                                <textarea id="addMedicalHistory" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all resize-y min-h-[100px]" placeholder="Allergies, chronic conditions, current medications..."></textarea>
                                <p id="addMedicalHistoryError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                        </div>
                    </div>
                </section>
                <section>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-primary text-[22px]">contact_page</span>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Contact Information</h3>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">call</span>
                                    Contact No. <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addContact" type="tel" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="09XX XXX XXXX"/>
                                <p id="addContactError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">mail</span>
                                    Email Address <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addEmail" type="email" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="patient@example.com"/>
                                <p id="addEmailError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                        </div>
                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Residential Address</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">map</span>
                                    Province <span class="text-red-500 font-bold">*</span>
                                </label>
                                <select id="addProvince" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer">
                                    <option value="">Select province</option>
                                </select>
                                <p id="addProvinceError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">location_city</span>
                                    City / Municipality <span class="text-red-500 font-bold">*</span>
                                </label>
                                <select id="addCity" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer" disabled>
                                    <option value="">Select city/municipality</option>
                                </select>
                                <p id="addCityError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">pin_drop</span>
                                    Barangay <span class="text-red-500 font-bold">*</span>
                                </label>
                                <select id="addBarangay" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer" disabled>
                                    <option value="">Select barangay</option>
                                </select>
                                <p id="addBarangayError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">home_pin</span>
                                    Street / House No. <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addHouseStreet" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="Unit, building, street"/>
                                <p id="addHouseStreetError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="border-t border-slate-100 bg-slate-50/50 px-6 sm:px-8 py-4 flex flex-wrap items-center justify-end gap-3">
                <button type="button" id="cancelAddPatientBtn" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:bg-slate-50 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                    Cancel
                </button>
                <button type="submit" id="savePatientBtn" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-primary hover:bg-primary/92 text-white text-sm font-bold shadow-lg shadow-primary/25 transition-all">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    Register Patient
                </button>
            </div>
        </form>
    </div>
</div>

<div id="chooseDentistModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/45"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Dentist Selection</p>
                    <h3 class="text-lg font-extrabold text-slate-900">Choose Dentist</h3>
                </div>
                <button id="closeChooseDentistModalBtn" type="button" class="w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            <div id="dentistListContainer" class="p-5 flex flex-wrap justify-center gap-4"></div>
            <div id="dentistListEmptyState" class="hidden px-5 pb-5 text-sm font-semibold text-slate-500"></div>
        </div>
    </div>
</div>

<div id="chooseServiceModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/45"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Service Selection</p>
                    <h3 class="text-lg font-extrabold text-slate-900">Choose Service</h3>
                </div>
                <button id="closeChooseServiceModalBtn" type="button" class="w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60 space-y-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 mb-2">Find Service</p>
                    <div class="relative">
                        <span class="material-symbols-outlined text-[18px] text-slate-400 absolute left-4 top-1/2 -translate-y-1/2">search</span>
                        <input id="serviceSearchInput" type="text" class="walkin-input w-full py-3.5 pl-11 pr-4 border border-primary/20 shadow-sm" placeholder="Search service name, category, or code"/>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500 mb-2">Filter by Category</p>
                    <div id="serviceCategoryFilters" class="flex items-center gap-2 overflow-x-auto pb-1"></div>
                </div>
            </div>

            <div class="max-h-[24rem] overflow-y-auto">
                <div id="serviceListEmptyState" class="hidden px-5 py-8 text-center text-sm font-semibold text-slate-500"></div>
                <div id="serviceListContainer" class="divide-y divide-slate-100 bg-white"></div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>js/staff-ui-dialogs.js"></script>
<script>
    (function () {
        const STAFF_OWNER_USER_ID = <?php echo json_encode($currentStaffUserId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const dateInput = document.getElementById('walkInDateInput');
        const timeInput = document.getElementById('walkInTimeInput');
        const registerPatientBtnTop = document.getElementById('registerPatientBtnTop');
        const selectedPatientLabel = document.getElementById('selectedPatientLabel');
        const clearSelectedPatientBtn = document.getElementById('clearSelectedPatientBtn');
        const selectedPatientIdInput = document.getElementById('selectedPatientId');
        const patientSearchInput = document.getElementById('patientSearchInput');
        const patientListContainer = document.getElementById('patientListContainer');
        const patientListEmptyState = document.getElementById('patientListEmptyState');
        const chooseDentistBtn = document.getElementById('chooseDentistBtn');
        const selectedDentistLabel = document.getElementById('selectedDentistLabel');
        const selectedDentistIdInput = document.getElementById('selectedDentistId');
        const selectedDentistUserIdInput = document.getElementById('selectedDentistUserId');
        const chooseDentistModal = document.getElementById('chooseDentistModal');
        const closeChooseDentistModalBtn = document.getElementById('closeChooseDentistModalBtn');
        const dentistListContainer = document.getElementById('dentistListContainer');
        const dentistListEmptyState = document.getElementById('dentistListEmptyState');
        const chooseServiceBtn = document.getElementById('chooseServiceBtn');
        const selectedServiceLabel = document.getElementById('selectedServiceLabel');
        const selectedServiceIdInput = document.getElementById('selectedServiceId');
        const addServiceBtn = document.getElementById('addServiceBtn');
        const selectedServicesContainer = document.getElementById('selectedServicesContainer');
        const chooseServiceModal = document.getElementById('chooseServiceModal');
        const closeChooseServiceModalBtn = document.getElementById('closeChooseServiceModalBtn');
        const serviceSearchInput = document.getElementById('serviceSearchInput');
        const serviceCategoryFilters = document.getElementById('serviceCategoryFilters');
        const serviceListContainer = document.getElementById('serviceListContainer');
        const serviceListEmptyState = document.getElementById('serviceListEmptyState');
        const notesInput = document.getElementById('walkInNotesInput');
        const createWalkInAppointmentBtn = document.getElementById('createWalkInAppointmentBtn');
        const walkInInstallmentAvailableEl = document.getElementById('walkInInstallmentAvailable');
        const walkInPaymentProgressBarEl = document.getElementById('walkInPaymentProgressBar');
        const walkInPaymentProgressLabelEl = document.getElementById('walkInPaymentProgressLabel');
        const walkInAmountPaidEl = document.getElementById('walkInAmountPaid');
        const walkInRemainingBalanceEl = document.getElementById('walkInRemainingBalance');
        const walkInMonthsLeftEl = document.getElementById('walkInMonthsLeft');
        const walkInTotalAmountEl = document.getElementById('walkInTotalAmount');
        const walkInDefaultPaymentDetailsSectionEl = document.getElementById('walkInDefaultPaymentDetailsSection');
        const walkInTreatmentPaymentProgressSectionEl = document.getElementById('walkInTreatmentPaymentProgressSection');
        const walkInDefaultPaymentSectionTitleEl = walkInDefaultPaymentDetailsSectionEl
            ? walkInDefaultPaymentDetailsSectionEl.querySelector('h3')
            : null;
        const walkInDefaultEstimatedTotalEl = document.getElementById('walkInDefaultEstimatedTotal');
        const walkInDefaultDownPaymentEl = document.getElementById('walkInDefaultDownPayment');
        const walkInDefaultMonthlyEstimateEl = document.getElementById('walkInDefaultMonthlyEstimate');
        const walkInDefaultDurationMaxEl = document.getElementById('walkInDefaultDurationMax');
        const walkInPaymentSettings = <?php echo json_encode($walkInPaymentSettings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const dentistsSeedData = <?php echo json_encode($walkInDentists, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const dentistAvailabilityApiUrl = <?php
            $dentistAvailabilityPath = $clinicWebRoot . '/StaffSetAppointments.php?action=dentist_availability';
            echo json_encode($dentistAvailabilityPath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>;
        const patientsApiUrl = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/patients.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const servicesApiUrl = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/services.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const patientTreatmentContextApiUrl = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/patient_treatment_context.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const appointmentsApiUrl = <?php echo json_encode($appointmentsCreateApiPath, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const clinicSlug = <?php echo json_encode((string) $currentTenantSlug, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const stockDentistImage = 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&w=300&q=60';
        const clinicAssetBaseUrl = <?php echo json_encode(rtrim(BASE_URL, '/') . '/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const patientAddressApiUrl = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/philippine_address.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const addPatientModal = document.getElementById('addPatientModal');
        const addPatientForm = document.getElementById('addPatientForm');
        const closeAddPatientModalBtn = document.getElementById('closeAddPatientModal');
        const cancelAddPatientBtn = document.getElementById('cancelAddPatientBtn');
        const addDobInput = document.getElementById('addDob');
        const addAgeInput = document.getElementById('addAge');
        const addProvinceSelect = document.getElementById('addProvince');
        const addCitySelect = document.getElementById('addCity');
        const addBarangaySelect = document.getElementById('addBarangay');
        const addGenderRadios = Array.from(document.querySelectorAll('input[name="addGender"]'));
        let allPatients = [];
        let allServices = [];
        let selectedServices = [];
        let dentistsData = Array.isArray(dentistsSeedData) ? dentistsSeedData.slice() : [];
        let activeTreatmentContext = null;
        let isSubmittingAppointment = false;
        let liveValidationRequestToken = 0;
        let liveValidationState = {
            hasTimeSlotConflict: false,
            hasPatientDuplicate: false,
            hasShiftBoundaryViolation: false
        };
        let selectedServiceCategoryFilter = 'all';
        const SERVICE_CATEGORY_FILTERS = [
            { key: 'all', label: 'All' },
            { key: 'general_dentistry', label: 'General' },
            { key: 'restorative_dentistry', label: 'Restorative' },
            { key: 'oral_surgery', label: 'Oral Surgery' },
            { key: 'crowns_and_bridges', label: 'Crowns and Bridges' },
            { key: 'cosmetic_dentistry', label: 'Cosmetic' },
            { key: 'pediatric_dentistry', label: 'Pediatric' },
            { key: 'orthodontics', label: 'Orthodontics' },
            { key: 'specialized_and_others', label: 'Specialized and Others' }
        ];
        const SERVICE_CATEGORY_BADGE_CLASSES = {
            'general_dentistry': 'bg-blue-100 text-blue-700',
            'restorative_dentistry': 'bg-green-100 text-green-700',
            'oral_surgery': 'bg-rose-100 text-rose-700',
            'crowns_and_bridges': 'bg-amber-100 text-amber-700',
            'cosmetic_dentistry': 'bg-violet-100 text-violet-700',
            'pediatric_dentistry': 'bg-pink-100 text-pink-700',
            'orthodontics': 'bg-orange-100 text-orange-700',
            'specialized_and_others': 'bg-slate-100 text-slate-700'
        };

        function pad(number) {
            return String(number).padStart(2, '0');
        }

        function formatTimeForApi(timeValue) {
            const raw = String(timeValue || '').trim();
            if (!raw) return '';
            const parts = raw.split(':');
            if (parts.length < 2) return '';
            const h = pad(Number(parts[0]) || 0);
            const m = pad(Number(parts[1]) || 0);
            return h + ':' + m + ':00';
        }

        function timeToMinutes(timeValue) {
            const normalized = formatTimeForApi(timeValue);
            if (!normalized) return NaN;
            const parts = normalized.split(':');
            if (parts.length < 2) return NaN;
            const h = Number(parts[0]);
            const m = Number(parts[1]);
            if (!Number.isFinite(h) || !Number.isFinite(m)) return NaN;
            return (h * 60) + m;
        }

        function minutesToDisplayTime(totalMinutes) {
            const safe = Math.max(0, Number(totalMinutes) || 0);
            const minutes = safe % (24 * 60);
            let hour24 = Math.floor(minutes / 60);
            const minute = minutes % 60;
            const suffix = hour24 >= 12 ? 'PM' : 'AM';
            hour24 = hour24 % 12;
            if (hour24 === 0) hour24 = 12;
            return hour24 + ':' + String(minute).padStart(2, '0') + ' ' + suffix;
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        const regBloodTypeOptions = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        const regFieldValidators = {
            addFirstName: { required: true, pattern: /^[a-zA-Z\s.'-]{2,50}$/, message: 'Enter a valid first name (2-50 letters).' },
            addLastName: { required: true, pattern: /^[a-zA-Z\s.'-]{2,50}$/, message: 'Enter a valid last name (2-50 letters).' },
            addDob: { required: true, message: 'Date of birth is required.' },
            addGender: { required: true, message: 'Gender is required.' },
            addBloodType: { required: true, message: 'Blood type is required.' },
            addMedicalHistory: { required: false },
            addContact: { required: true, pattern: /^09\d{9}$/, message: 'Use PH mobile format: 09XXXXXXXXX.' },
            addEmail: { required: true, pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, message: 'Enter a valid email address.' },
            addProvince: { required: true, message: 'Province is required.' },
            addCity: { required: true, message: 'City/Municipality is required.' },
            addBarangay: { required: true, message: 'Barangay is required.' },
            addHouseStreet: { required: true, minLength: 3, message: 'Street / House No. is required.' }
        };

        function regCalculateAge(dateValue) {
            if (!dateValue) return '';
            const birthDate = new Date(dateValue);
            if (Number.isNaN(birthDate.getTime())) return '';
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age -= 1;
            return age >= 0 ? String(age) : '';
        }

        function regGetDobMaxDateForMinimumAge(minYears) {
            const maxDate = new Date();
            maxDate.setFullYear(maxDate.getFullYear() - minYears);
            return maxDate.toISOString().slice(0, 10);
        }

        function regIsAtLeastOneYearOld(dateValue) {
            if (!dateValue) return false;
            const birthDate = new Date(dateValue);
            if (Number.isNaN(birthDate.getTime())) return false;
            return birthDate <= new Date(regGetDobMaxDateForMinimumAge(1));
        }

        function regClearFieldError(fieldId) {
            const inputEl = document.getElementById(fieldId);
            const errorEl = document.getElementById(fieldId + 'Error');
            if (inputEl) inputEl.classList.remove('field-error');
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.classList.add('hidden');
            }
        }

        function regShowFieldError(fieldId, message) {
            const inputEl = document.getElementById(fieldId);
            const errorEl = document.getElementById(fieldId + 'Error');
            if (inputEl) inputEl.classList.add('field-error');
            if (errorEl) {
                errorEl.textContent = message;
                errorEl.classList.remove('hidden');
            }
        }

        function regResetFormValidation() {
            Object.keys(regFieldValidators).forEach(regClearFieldError);
        }

        function regGetSelectedGender() {
            const selected = addGenderRadios.find(function (radio) { return radio.checked; });
            return selected ? selected.value : '';
        }

        function regFillSelect(selectEl, values, placeholder) {
            if (!selectEl) return;
            selectEl.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>' + values
                .map(function (v) { return '<option value="' + escapeHtml(v) + '">' + escapeHtml(v) + '</option>'; })
                .join('');
            selectEl.disabled = values.length === 0;
        }

        async function regFetchAddress(action, params) {
            const url = new URL(patientAddressApiUrl, window.location.origin);
            url.searchParams.set('action', action);
            Object.entries(params || {}).forEach(function (entry) {
                url.searchParams.set(entry[0], entry[1]);
            });
            const response = await fetch(buildApiUrl(url.pathname + url.search), { credentials: 'include' });
            const data = await parseJsonResponse(response);
            if (!response.ok || !data.success || !Array.isArray(data.data)) {
                throw new Error(data.message || 'Failed to load address data.');
            }
            return data.data;
        }

        async function regLoadProvinces(selectedProvince) {
            try {
                const provinces = await regFetchAddress('provinces', {});
                regFillSelect(addProvinceSelect, provinces, 'Select province');
                if (addProvinceSelect) addProvinceSelect.value = selectedProvince || '';
            } catch (error) {
                regFillSelect(addProvinceSelect, [], 'Unable to load provinces');
            }
        }

        async function regLoadCities(province, selectedCity) {
            if (!province) {
                regFillSelect(addCitySelect, [], 'Select city/municipality');
                regFillSelect(addBarangaySelect, [], 'Select barangay');
                return;
            }
            try {
                const cities = await regFetchAddress('cities', { province: province });
                regFillSelect(addCitySelect, cities, 'Select city/municipality');
                if (addCitySelect) addCitySelect.value = selectedCity || '';
            } catch (error) {
                regFillSelect(addCitySelect, [], 'Unable to load cities');
            }
        }

        async function regLoadBarangays(province, city, selectedBarangay) {
            if (!province || !city) {
                regFillSelect(addBarangaySelect, [], 'Select barangay');
                return;
            }
            try {
                const barangays = await regFetchAddress('barangays', { province: province, city: city });
                regFillSelect(addBarangaySelect, barangays, 'Select barangay');
                if (addBarangaySelect) addBarangaySelect.value = selectedBarangay || '';
            } catch (error) {
                regFillSelect(addBarangaySelect, [], 'Unable to load barangays');
            }
        }

        function regValidatePatientForm(payload) {
            regResetFormValidation();
            let isValid = true;
            Object.entries(regFieldValidators).forEach(function (entry) {
                const fieldId = entry[0];
                const rules = entry[1];
                const raw = fieldId === 'addGender'
                    ? payload.gender
                    : ((document.getElementById(fieldId) && document.getElementById(fieldId).value) || '');
                const value = String(raw || '').trim();
                if (rules.required && !value) {
                    regShowFieldError(fieldId, rules.message);
                    isValid = false;
                    return;
                }
                if (value && rules.minLength && value.length < rules.minLength) {
                    regShowFieldError(fieldId, rules.message);
                    isValid = false;
                    return;
                }
                if (value && rules.pattern && !rules.pattern.test(value)) {
                    regShowFieldError(fieldId, rules.message);
                    isValid = false;
                }
            });

            if (payload.date_of_birth) {
                const selectedDob = new Date(payload.date_of_birth);
                if (Number.isNaN(selectedDob.getTime())) {
                    regShowFieldError('addDob', 'Enter a valid date of birth.');
                    isValid = false;
                } else if (!regIsAtLeastOneYearOld(payload.date_of_birth)) {
                    regShowFieldError('addDob', 'Patient must be at least 1 year old.');
                    isValid = false;
                }
            }
            if (payload.blood_type && regBloodTypeOptions.indexOf(payload.blood_type) === -1) {
                regShowFieldError('addBloodType', 'Select a valid blood type.');
                isValid = false;
            }
            return isValid;
        }

        function openAddPatientModal() {
            if (!addPatientModal) return;
            const titleEl = document.getElementById('patientModalTitle');
            const editingPatientIdEl = document.getElementById('editingPatientId');
            const savePatientBtn = document.getElementById('savePatientBtn');
            if (titleEl) titleEl.textContent = 'Patient Registration';
            if (editingPatientIdEl) editingPatientIdEl.value = '';
            if (savePatientBtn) savePatientBtn.textContent = 'Register Patient';
            if (addPatientForm) addPatientForm.reset();
            if (addAgeInput) addAgeInput.value = '';
            addGenderRadios.forEach(function (radio) { radio.checked = false; });
            regFillSelect(addCitySelect, [], 'Select city/municipality');
            regFillSelect(addBarangaySelect, [], 'Select barangay');
            regResetFormValidation();
            addPatientModal.classList.remove('hidden');
            addPatientModal.classList.add('flex');
            syncModalBodyScrollLock();
        }

        function closeAddPatientModal() {
            if (!addPatientModal) return;
            if (addPatientForm) addPatientForm.reset();
            const editingPatientIdEl = document.getElementById('editingPatientId');
            if (editingPatientIdEl) editingPatientIdEl.value = '';
            if (addAgeInput) addAgeInput.value = '';
            addGenderRadios.forEach(function (radio) { radio.checked = false; });
            regFillSelect(addCitySelect, [], 'Select city/municipality');
            regFillSelect(addBarangaySelect, [], 'Select barangay');
            regResetFormValidation();
            addPatientModal.classList.add('hidden');
            addPatientModal.classList.remove('flex');
            syncModalBodyScrollLock();
        }

        async function savePatientFromWalkIn(event) {
            event.preventDefault();
            const payload = {
                first_name: (document.getElementById('addFirstName').value || '').trim(),
                last_name: (document.getElementById('addLastName').value || '').trim(),
                contact_number: (document.getElementById('addContact').value || '').trim(),
                mobile: (document.getElementById('addContact').value || '').trim(),
                email: (document.getElementById('addEmail').value || '').trim(),
                date_of_birth: document.getElementById('addDob').value,
                gender: regGetSelectedGender(),
                blood_type: (document.getElementById('addBloodType').value || '').trim(),
                medical_history: (document.getElementById('addMedicalHistory').value || '').trim(),
                house_street: (document.getElementById('addHouseStreet').value || '').trim(),
                barangay: document.getElementById('addBarangay').value,
                city_municipality: document.getElementById('addCity').value,
                province: document.getElementById('addProvince').value,
                owner_user_id: STAFF_OWNER_USER_ID || ''
            };

            if (!regValidatePatientForm(payload)) return;

            const saveBtn = document.getElementById('savePatientBtn');
            const oldHtml = saveBtn ? saveBtn.innerHTML : '';
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">sync</span> Saving...';
            }

            try {
                const response = await fetch(buildApiUrl(patientsApiUrl), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });
                const data = await parseJsonResponse(response);
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Failed to save patient.');
                }
                closeAddPatientModal();
                await loadAllPatients();
            } catch (error) {
                await staffUiAlert({
                    title: 'Could not save patient',
                    message: error && error.message ? error.message : 'Failed to save patient.',
                    variant: 'error'
                });
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = oldHtml;
                }
            }
        }

        function setEmptyState(message) {
            if (!patientListEmptyState || !patientListContainer) return;
            patientListEmptyState.textContent = message;
            patientListEmptyState.classList.remove('hidden');
            patientListContainer.innerHTML = '';
        }

        function renderPatientsList(patients) {
            if (!patientListContainer || !patientListEmptyState) return;
            if (!patients.length) {
                setEmptyState('No patients found.');
                return;
            }

            patientListEmptyState.classList.add('hidden');
            patientListContainer.innerHTML = patients.map(function (patient) {
                const displayId = escapeHtml(patient.patient_id || '-');
                const fullName = escapeHtml((patient.first_name || '') + ' ' + (patient.last_name || ''));
                const contact = escapeHtml(patient.contact_number || '-');

                return '' +
                    '<div class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">' +
                        '<div class="min-w-0">' +
                            '<p class="text-sm font-bold text-slate-900 truncate">' + fullName + '</p>' +
                            '<p class="text-xs font-semibold text-slate-500 mt-1">ID: ' + displayId + ' | Contact: ' + contact + '</p>' +
                        '</div>' +
                        '<button type="button" data-action="select-patient" data-patient-id="' + escapeHtml(patient.patient_id || '') + '" data-patient-name="' + fullName + '" class="shrink-0 rounded-lg bg-primary text-white px-3 py-2 text-xs font-extrabold uppercase tracking-wide hover:bg-primary/90 transition-colors">' +
                            'Select' +
                        '</button>' +
                    '</div>';
            }).join('');
        }

        function setServiceEmptyState(message) {
            if (!serviceListContainer || !serviceListEmptyState) return;
            serviceListEmptyState.textContent = message;
            serviceListEmptyState.classList.remove('hidden');
            serviceListContainer.innerHTML = '';
        }

        function renderServicesList(services) {
            if (!serviceListContainer || !serviceListEmptyState) return;
            if (!services.length) {
                setServiceEmptyState('No services found.');
                return;
            }
            serviceListEmptyState.classList.add('hidden');
            serviceListContainer.innerHTML = services.map(function (service) {
                const serviceId = escapeHtml(service.service_id || '');
                const serviceName = escapeHtml(service.service_name || '');
                const category = escapeHtml(service.category || '-');
                const categoryBadgeClass = getServiceCategoryBadgeClass(service.category);
                const price = Number(service.price || 0);
                const isInstallment = serviceInstallmentEnabled(service);
                const selectionRule = getServiceSelectionRule(service);
                const buttonDisabled = !selectionRule.allowed;
                const buttonClass = buttonDisabled
                    ? 'shrink-0 rounded-lg bg-slate-200 text-slate-500 px-3 py-2 text-xs font-extrabold uppercase tracking-wide cursor-not-allowed'
                    : 'shrink-0 rounded-lg bg-primary text-white px-3 py-2 text-xs font-extrabold uppercase tracking-wide hover:bg-primary/90 transition-colors';
                return '' +
                    '<div class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hover:bg-slate-50/80 transition-colors">' +
                        '<div class="min-w-0">' +
                            '<p class="text-sm font-bold text-slate-900 truncate">' + serviceName + '</p>' +
                            '<p class="text-xs font-semibold text-slate-500 mt-1 inline-flex items-center gap-2"><span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide ' + categoryBadgeClass + '">' + category + '</span><span>Php ' + price.toFixed(2) + '</span>' + (isInstallment ? '<span class="text-primary">Installment</span>' : '') + (selectionRule.allowed ? '' : '<span class="text-amber-700">' + escapeHtml(selectionRule.reason || 'Not available') + '</span>') + '</p>' +
                        '</div>' +
                        '<button type="button" data-action="select-service" data-service-id="' + serviceId + '" ' + (buttonDisabled ? 'disabled' : '') + ' class="' + buttonClass + '">' + (selectionRule.allowed ? 'Select' : 'Locked') + '</button>' +
                    '</div>';
            }).join('');
        }

        function getServiceCategoryBadgeClass(category) {
            const key = normalizeServiceFilterCategory(category);
            return SERVICE_CATEGORY_BADGE_CLASSES[key] || 'bg-slate-100 text-slate-700';
        }

        function normalizeServiceFilterCategory(category) {
            return normalizeClinicalCategory(category);
        }

        function applyServiceFilters() {
            const keyword = serviceSearchInput ? serviceSearchInput.value.trim().toLowerCase() : '';
            const filtered = allServices.filter(function (service) {
                if (!serviceMatchesTreatmentServiceVisibility(service)) {
                    return false;
                }
                const categoryKey = normalizeServiceFilterCategory(service.category);
                const categoryMatches = selectedServiceCategoryFilter === 'all' || categoryKey === selectedServiceCategoryFilter;
                if (!categoryMatches) {
                    return false;
                }
                if (!keyword) {
                    return true;
                }
                const haystack = [
                    service.service_name,
                    service.category,
                    service.service_id
                ].join(' ').toLowerCase();
                return haystack.indexOf(keyword) !== -1;
            });
            renderServicesList(filtered);
        }

        function renderServiceCategoryFilters() {
            if (!serviceCategoryFilters) return;
            serviceCategoryFilters.innerHTML = SERVICE_CATEGORY_FILTERS.map(function (item) {
                const isActive = item.key === selectedServiceCategoryFilter;
                return '' +
                    '<button type="button" data-action="service-category-filter" data-category-key="' + escapeHtml(item.key) + '" class="service-filter-chip' + (isActive ? ' is-active' : '') + '">' +
                        escapeHtml(item.label) +
                    '</button>';
            }).join('');
        }

        function serviceInstallmentEnabled(service) {
            if (!service) return false;
            const v = service.enable_installment;
            if (v === true || v === 1 || v === '1') {
                return true;
            }
            const rawServiceType = String(service.service_type || '').toLowerCase().trim();
            return rawServiceType === 'installment';
        }

        function normalizeServiceType(serviceType) {
            return String(serviceType || '').trim().toLowerCase();
        }

        function isIncludedPlanService(service) {
            return normalizeServiceType(service && service.service_type) === 'included_plan';
        }

        function formatPeso(amount) {
            return 'P' + Number(amount || 0).toFixed(2);
        }

        function getServiceById(serviceId) {
            const key = String(serviceId || '').trim();
            if (!key) return null;
            return allServices.find(function (service) {
                return String(service && service.service_id ? service.service_id : '') === key;
            }) || null;
        }

        function getDefaultBreakdownServices() {
            const list = selectedServices.slice();
            const selectedId = selectedServiceIdInput ? String(selectedServiceIdInput.value || '').trim() : '';
            if (selectedId) {
                const exists = list.some(function (service) {
                    return String(service && service.service_id ? service.service_id : '') === selectedId;
                });
                if (!exists) {
                    const selectedService = getServiceById(selectedId);
                    if (selectedService) {
                        list.push(selectedService);
                    }
                }
            }
            return list;
        }

        function getAddedServicesForInstallmentTreatment() {
            if (!treatmentIsInstallmentPlan(activeTreatmentContext)) {
                return getDefaultBreakdownServices();
            }
            const primaryServiceId = String((activeTreatmentContext && activeTreatmentContext.treatment && activeTreatmentContext.treatment.primary_service && activeTreatmentContext.treatment.primary_service.service_id) || '');
            return selectedServices.filter(function (service) {
                return String(service && service.service_id ? service.service_id : '') !== primaryServiceId;
            });
        }

        function treatmentIsInstallmentPlan(treatmentContext) {
            if (!treatmentContext || !treatmentContext.has_active_treatment || !treatmentContext.treatment) {
                return false;
            }
            if (treatmentIsFullyPaid(treatmentContext)) {
                return false;
            }
            const treatment = treatmentContext.treatment;
            const primaryService = treatment.primary_service || {};
            const rawEnableInstallment = primaryService.enable_installment;
            const serviceInstallment = rawEnableInstallment === true || rawEnableInstallment === 1 || rawEnableInstallment === '1';
            const durationMonths = Number(treatment.duration_months || 0);
            const monthsLeft = computeTreatmentMonthsLeft(treatment);
            return serviceInstallment || durationMonths > 1 || monthsLeft > 0;
        }

        function getActiveInstallmentPrimaryServiceId() {
            if (!treatmentIsInstallmentPlan(activeTreatmentContext)) {
                return '';
            }
            return String((activeTreatmentContext && activeTreatmentContext.treatment && activeTreatmentContext.treatment.primary_service && activeTreatmentContext.treatment.primary_service.service_id) || '');
        }

        function hasActiveInstallmentTreatment() {
            return getActiveInstallmentPrimaryServiceId() !== '';
        }

        function patientHasActiveTreatmentPlan() {
            return Boolean(
                activeTreatmentContext
                && activeTreatmentContext.has_active_treatment
                && !treatmentIsFullyPaid(activeTreatmentContext)
            );
        }

        function getActiveTreatmentCategoryKey() {
            if (!activeTreatmentContext || !activeTreatmentContext.treatment) {
                return '';
            }
            const treatment = activeTreatmentContext.treatment;
            const primaryCategoryKey = normalizeServiceFilterCategory(treatment.primary_service && treatment.primary_service.category);
            if (primaryCategoryKey) {
                return primaryCategoryKey;
            }
            return normalizeServiceFilterCategory(treatment.category || treatment.treatment_category || '');
        }

        function serviceMatchesTreatmentServiceVisibility(service) {
            const serviceType = normalizeServiceType(service && service.service_type);
            const isIncludedPlan = isIncludedPlanService(service);
            const isInstallmentType = serviceType === 'installment';
            const hasInstallmentTreatment = treatmentIsInstallmentPlan(activeTreatmentContext);
            if (!hasInstallmentTreatment) {
                return !isIncludedPlan;
            }
            if (isInstallmentType) {
                return false;
            }
            if (!isIncludedPlan) {
                return true;
            }
            const treatmentCategoryKey = getActiveTreatmentCategoryKey();
            if (!treatmentCategoryKey) {
                return false;
            }
            return normalizeServiceFilterCategory(service && service.category) === treatmentCategoryKey;
        }

        function getServiceSelectionRule(service) {
            const serviceType = normalizeServiceType(service && service.service_type);
            if (serviceType === 'installment' && patientHasActiveTreatmentPlan()) {
                return {
                    allowed: false,
                    reason: 'This patient already has an active treatment plan. Only one active installment plan is allowed per patient.'
                };
            }
            if (!serviceMatchesTreatmentServiceVisibility(service)) {
                return {
                    allowed: false,
                    reason: 'This service is not available for the patient\'s active treatment category.'
                };
            }
            return { allowed: true, reason: '' };
        }

        function computeTreatmentMonthsLeft(treatment) {
            if (!treatment) return 0;
            const durationMonths = Math.max(0, Number(treatment.duration_months || 0));
            const monthsPaid = Math.max(0, Number(treatment.months_paid || 0));
            const storedMonthsLeft = Math.max(0, Number(treatment.months_left || 0));
            if (storedMonthsLeft > 0) {
                return storedMonthsLeft;
            }
            const derivedFromProgress = Math.max(0, Math.ceil(durationMonths - monthsPaid));
            if (durationMonths > 0) {
                return Math.min(durationMonths, derivedFromProgress);
            }
            return storedMonthsLeft;
        }

        function treatmentIsFullyPaid(treatmentContext) {
            if (!treatmentContext || !treatmentContext.treatment) {
                return false;
            }
            const treatment = treatmentContext.treatment;
            const remainingBalanceRaw = Number(treatment.remaining_balance);
            const hasRemainingBalance = Number.isFinite(remainingBalanceRaw);
            if (hasRemainingBalance && remainingBalanceRaw <= 0) {
                return true;
            }
            const installments = Array.isArray(treatment.installments) ? treatment.installments : [];
            if (installments.length > 0) {
                const allInstallmentsPaid = installments.every(function (installment) {
                    return String(installment && installment.status ? installment.status : '').trim().toLowerCase() === 'paid';
                });
                if (allInstallmentsPaid) {
                    return true;
                }
            }
            const totalSlots = Number(treatment.installment_total_slots);
            const settledSlots = Number(treatment.installment_settled_slots);
            if (Number.isFinite(totalSlots) && totalSlots > 0 && Number.isFinite(settledSlots) && settledSlots >= totalSlots) {
                return true;
            }
            const installmentTotalAmount = Number(treatment.installment_total_amount);
            const installmentPaidAmount = Number(treatment.installment_paid_amount);
            if (
                Number.isFinite(installmentTotalAmount)
                && installmentTotalAmount > 0
                && Number.isFinite(installmentPaidAmount)
                && installmentPaidAmount >= (installmentTotalAmount - 0.009)
            ) {
                return true;
            }
            return false;
        }

        function updatePaymentDetailsVisibility() {
            const hasInstallmentTreatment = treatmentIsInstallmentPlan(activeTreatmentContext);
            const hasAddedServices = getAddedServicesForInstallmentTreatment().length > 0;
            if (walkInDefaultPaymentDetailsSectionEl) {
                walkInDefaultPaymentDetailsSectionEl.classList.toggle('hidden', hasInstallmentTreatment ? !hasAddedServices : false);
            }
            if (walkInTreatmentPaymentProgressSectionEl) {
                walkInTreatmentPaymentProgressSectionEl.classList.toggle('hidden', !hasInstallmentTreatment);
            }
            if (walkInDefaultPaymentSectionTitleEl) {
                walkInDefaultPaymentSectionTitleEl.textContent = 'Payment Breakdown';
            }
        }

        function computeServiceDownPayment(service, configuredRegularPct, configuredMinDown) {
            const servicePrice = Math.max(0, Number(service && service.price ? service.price : 0));
            if (servicePrice <= 0) return 0;
            if (!serviceInstallmentEnabled(service)) {
                return Math.min(servicePrice, Math.max(0, servicePrice * (configuredRegularPct / 100)));
            }
            const serviceConfiguredDownRaw = Number(service && service.installment_downpayment !== undefined ? service.installment_downpayment : 0);
            const hasServiceConfiguredDown = Number.isFinite(serviceConfiguredDownRaw) && serviceConfiguredDownRaw > 0;
            const installmentDownPayment = hasServiceConfiguredDown ? serviceConfiguredDownRaw : configuredMinDown;
            return Math.min(servicePrice, Math.max(0, installmentDownPayment));
        }

        function computeServiceMonthlyEstimate(service, downPayment) {
            if (!serviceInstallmentEnabled(service)) {
                return 0;
            }
            const durationMonths = Math.max(0, Number(service && service.installment_duration_months ? service.installment_duration_months : 0));
            const servicePrice = Math.max(0, Number(service && service.price ? service.price : 0));
            if (durationMonths <= 0 || servicePrice <= 0) {
                return 0;
            }
            const netAmountAfterDownPayment = Math.max(0, servicePrice - Math.max(0, Number(downPayment || 0)));
            return netAmountAfterDownPayment / durationMonths;
        }

        function updateDefaultPaymentDetails() {
            const breakdownServices = getAddedServicesForInstallmentTreatment();
            const totalAmount = breakdownServices.reduce(function (sum, service) {
                return sum + Number(service && service.price ? service.price : 0);
            }, 0);
            const configuredRegularPctRaw = walkInPaymentSettings && walkInPaymentSettings.regular_downpayment_percentage !== undefined
                ? Number(walkInPaymentSettings.regular_downpayment_percentage)
                : 20;
            const configuredRegularPct = Number.isFinite(configuredRegularPctRaw) ? Math.max(0, configuredRegularPctRaw) : 20;
            const configuredMinDownRaw = walkInPaymentSettings && walkInPaymentSettings.long_term_min_downpayment !== undefined
                ? Number(walkInPaymentSettings.long_term_min_downpayment)
                : 500;
            const configuredMinDown = Number.isFinite(configuredMinDownRaw) ? Math.max(0, configuredMinDownRaw) : 500;
            let monthlyEstimate = 0;
            let downPayment = 0;
            let durationMonths = 0;
            breakdownServices.forEach(function (service) {
                const serviceDownPayment = computeServiceDownPayment(service, configuredRegularPct, configuredMinDown);
                downPayment += serviceDownPayment;
                monthlyEstimate += computeServiceMonthlyEstimate(service, serviceDownPayment);
                if (serviceInstallmentEnabled(service)) {
                    durationMonths = Math.max(durationMonths, Math.max(0, Number(service && service.installment_duration_months ? service.installment_duration_months : 0)));
                }
            });

            if (walkInDefaultEstimatedTotalEl) {
                walkInDefaultEstimatedTotalEl.textContent = formatPeso(totalAmount);
            }
            if (walkInDefaultDownPaymentEl) {
                walkInDefaultDownPaymentEl.textContent = formatPeso(downPayment);
            }
            if (walkInDefaultMonthlyEstimateEl) {
                walkInDefaultMonthlyEstimateEl.textContent = formatPeso(monthlyEstimate);
            }
            if (walkInDefaultDurationMaxEl) {
                walkInDefaultDurationMaxEl.textContent = String(durationMonths) + (durationMonths === 1 ? ' Month' : ' Months');
            }
        }

        function updateTreatmentProgressCards(treatment) {
            const total = treatment ? Math.max(0, Number(treatment.total_cost || 0)) : 0;
            const paidRaw = treatment ? Math.max(0, Number(treatment.amount_paid || 0)) : 0;
            const paid = total > 0 ? Math.min(total, paidRaw) : paidRaw;
            const remaining = Math.max(0, total - paid);
            const monthsLeftRaw = treatment ? Number(treatment.months_left) : NaN;
            const slotsLeftRaw = treatment
                ? Number(treatment.installment_total_slots || 0) - Number(treatment.installment_settled_slots || 0)
                : NaN;
            const monthsLeft = Number.isFinite(monthsLeftRaw)
                ? Math.max(0, Math.round(monthsLeftRaw))
                : (Number.isFinite(slotsLeftRaw) ? Math.max(0, Math.round(slotsLeftRaw)) : 0);
            const percent = total > 0
                ? Math.max(0, Math.min(100, Math.round((paid / total) * 1000) / 10))
                : 0;

            if (walkInTotalAmountEl) walkInTotalAmountEl.textContent = formatPeso(total);
            if (walkInAmountPaidEl) walkInAmountPaidEl.textContent = formatPeso(paid);
            if (walkInRemainingBalanceEl) walkInRemainingBalanceEl.textContent = formatPeso(remaining);
            if (walkInMonthsLeftEl) walkInMonthsLeftEl.textContent = String(monthsLeft) + (monthsLeft === 1 ? ' Month' : ' Months');
            if (walkInPaymentProgressLabelEl) walkInPaymentProgressLabelEl.textContent = percent + '% paid';
            if (walkInPaymentProgressBarEl) walkInPaymentProgressBarEl.style.width = Math.max(0, Math.min(100, percent)) + '%';
            if (walkInInstallmentAvailableEl) {
                walkInInstallmentAvailableEl.textContent = 'Active Installment Treatment: ' + (treatmentIsInstallmentPlan(activeTreatmentContext) ? 'Yes' : 'No');
            }
            updatePaymentDetailsVisibility();
            updateDefaultPaymentDetails();
        }

        function renderSelectedServices() {
            if (!selectedServicesContainer) return;
            if (!selectedServices.length) {
                selectedServicesContainer.innerHTML = '<p class="text-[11px] font-semibold text-slate-500">No services added yet.</p>';
                updateTreatmentProgressCards(activeTreatmentContext ? activeTreatmentContext.treatment : null);
                return;
            }
            selectedServicesContainer.innerHTML = selectedServices.map(function (service) {
                const serviceId = escapeHtml(service.service_id || '');
                const serviceName = escapeHtml(service.service_name || '');
                const price = Number(service.price || 0).toFixed(2);
                const activePrimaryServiceId = treatmentIsInstallmentPlan(activeTreatmentContext)
                    ? String((activeTreatmentContext.treatment && activeTreatmentContext.treatment.primary_service && activeTreatmentContext.treatment.primary_service.service_id) || '')
                    : '';
                const isLockedPrimary = activePrimaryServiceId !== '' && String(service.service_id || '') === activePrimaryServiceId;
                return '' +
                    '<div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">' +
                        '<span class="text-xs font-bold text-slate-700">' + serviceName + ' (Php ' + price + ')' + (isLockedPrimary ? ' - ongoing installment' : '') + '</span>' +
                        '<button type="button" data-action="remove-service" data-service-id="' + serviceId + '" ' + (isLockedPrimary ? 'disabled' : '') + ' class="w-6 h-6 rounded-md ' + (isLockedPrimary ? 'bg-slate-200 text-slate-400 cursor-not-allowed' : 'bg-red-50 text-red-500 hover:bg-red-100') + ' inline-flex items-center justify-center">' +
                            '<span class="material-symbols-outlined text-[14px]">close</span>' +
                        '</button>' +
                    '</div>';
            }).join('');
            updateTreatmentProgressCards(activeTreatmentContext ? activeTreatmentContext.treatment : null);
        }

        function normalizeClinicalCategory(category) {
            const raw = String(category || '').toLowerCase();
            if (raw.indexOf('crowns') !== -1 && raw.indexOf('bridges') !== -1) return 'crowns_and_bridges';
            if (raw.indexOf('oral') !== -1 && raw.indexOf('surgery') !== -1) return 'oral_surgery';
            if (raw.indexOf('orthodont') !== -1) return 'orthodontics';
            if (raw.indexOf('pediatric') !== -1) return 'pediatric_dentistry';
            if (raw.indexOf('cosmetic') !== -1) return 'cosmetic_dentistry';
            if (raw.indexOf('restorative') !== -1) return 'restorative_dentistry';
            if (raw.indexOf('general') !== -1) return 'general_dentistry';
            if (raw.indexOf('specialized') !== -1 || raw.indexOf('specialised') !== -1) return 'specialized_and_others';
            return '';
        }

        function validateServiceCompatibility(services) {
            const categories = new Set();
            services.forEach(function (service) {
                const normalized = normalizeClinicalCategory(service && service.category);
                if (normalized) {
                    categories.add(normalized);
                }
            });
            const blockedPairs = [
                {
                    pair: ['oral_surgery', 'crowns_and_bridges'],
                    message: 'Oral Surgery and Crowns and Bridges cannot be combined in one booking because healing must occur first before crown placement.'
                },
                {
                    pair: ['orthodontics', 'crowns_and_bridges'],
                    message: 'Orthodontics and Crowns and Bridges cannot be combined because permanent bridges should not be placed while teeth are still moving.'
                },
                {
                    pair: ['orthodontics', 'cosmetic_dentistry'],
                    message: 'Orthodontics and Cosmetic Dentistry cannot be combined because cosmetic procedures like veneers should be done after alignment is complete.'
                },
                {
                    pair: ['pediatric_dentistry', 'cosmetic_dentistry'],
                    message: 'Pediatric Dentistry and Cosmetic Dentistry cannot be combined because major cosmetic procedures are not appropriate for pediatric patients.'
                }
            ];
            for (let i = 0; i < blockedPairs.length; i++) {
                const rule = blockedPairs[i];
                if (categories.has(rule.pair[0]) && categories.has(rule.pair[1])) {
                    return {
                        valid: false,
                        message: rule.message + ' Please schedule these services separately if needed.'
                    };
                }
            }
            return { valid: true, message: '' };
        }

        function resolveDentistProfileImageUrl(raw) {
            const path = String(raw || '').trim();
            if (!path) {
                return stockDentistImage;
            }
            if (/^https?:\/\//i.test(path)) {
                return path;
            }
            return clinicAssetBaseUrl.replace(/\/?$/, '/') + path.replace(/^\/+/, '');
        }

        function renderDentistsList() {
            if (!dentistListContainer || !dentistListEmptyState) return;
            if (!dentistsData.length) {
                dentistListEmptyState.textContent = 'No dentists available.';
                dentistListEmptyState.classList.remove('hidden');
                dentistListContainer.innerHTML = '';
                return;
            }

            dentistListEmptyState.classList.add('hidden');
            dentistListContainer.innerHTML = dentistsData.map(function (dentist) {
                const firstName = String(dentist.first_name || '').trim();
                const lastName = String(dentist.last_name || '').trim();
                const fullNameText = (firstName + ' ' + lastName).trim() || 'Unnamed Dentist';
                const fullName = escapeHtml(fullNameText);
                const isAvailable = Number(dentist.is_available_for_slot || 0) === 1;
                const statusLabelRaw = String(dentist.availability_reason || '').trim();
                const statusLabel = statusLabelRaw !== ''
                    ? statusLabelRaw
                    : (isAvailable ? 'Available' : 'Unavailable (Outside Shift)');
                const statusTitle = escapeHtml(statusLabel);
                const statusDotClass = isAvailable ? 'bg-emerald-500' : 'bg-red-500';
                const statusTextClass = isAvailable ? 'text-emerald-700' : 'text-red-700';
                const imageSrc = escapeHtml(resolveDentistProfileImageUrl(dentist.profile_image));
                const dentistId = escapeHtml(dentist.dentist_id || '');
                const dentistUserId = escapeHtml(dentist.user_id || '');
                const shiftWindowsJson = escapeHtml(JSON.stringify(Array.isArray(dentist.shift_windows) ? dentist.shift_windows : []));
                const displayId = String(dentist.dentist_display_id || '').trim();
                const idLineRaw = displayId !== '' ? displayId : ('ID #' + String(dentist.dentist_id || '').trim());
                const idLine = escapeHtml(idLineRaw);
                const scheduleLineRaw = String(dentist.schedule_label || '').trim() || 'No schedule';
                const scheduleLine = escapeHtml(scheduleLineRaw);
                const selectButtonClass = isAvailable
                    ? 'mt-3 rounded-lg px-3 py-2 text-xs font-extrabold uppercase tracking-wide transition-colors bg-primary text-white hover:bg-primary/90'
                    : 'mt-3 rounded-lg px-3 py-2 text-xs font-extrabold uppercase tracking-wide transition-colors bg-slate-200 text-slate-500 cursor-not-allowed';
                return '' +
                    '<div class="w-full sm:w-[19rem] rounded-2xl border border-slate-200 bg-slate-50/50 p-4 flex flex-col items-center text-center">' +
                        '<div class="relative shrink-0">' +
                            '<img src="' + imageSrc + '" alt="" class="w-24 h-24 rounded-full object-cover border border-slate-200 bg-white"/>' +
                            '<span class="absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full border-2 border-white ' + statusDotClass + '" title="' + statusTitle + '" role="img" aria-label="' + statusTitle + '"></span>' +
                        '</div>' +
                        '<p class="mt-3 text-sm font-extrabold text-slate-900">' + fullName + '</p>' +
                        '<p class="mt-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">' + idLine + '</p>' +
                        '<p class="mt-1 text-[11px] font-semibold text-slate-600">' + scheduleLine + '</p>' +
                        '<p class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold ' + statusTextClass + '">' +
                            '<span class="w-2.5 h-2.5 rounded-full shrink-0 ' + statusDotClass + '" title="' + statusTitle + '" aria-hidden="true"></span>' +
                            '<span>' + escapeHtml(statusLabel) + '</span>' +
                        '</p>' +
                        '<button type="button" data-action="select-dentist" data-dentist-id="' + dentistId + '" data-dentist-user-id="' + dentistUserId + '" data-dentist-name="' + fullName + '" data-dentist-shift-windows="' + shiftWindowsJson + '" ' + (isAvailable ? '' : 'disabled') + ' class="' + selectButtonClass + '">' + (isAvailable ? 'Select' : 'Unavailable') + '</button>' +
                    '</div>';
            }).join('');
        }

        function computeRequiredMinutesForSelectedServices() {
            return selectedServices.reduce(function (sum, service) {
                const duration = Math.max(0, Number(service && service.service_duration ? service.service_duration : 0));
                const buffer = Math.max(0, Number(service && service.buffer_time ? service.buffer_time : 0));
                return sum + duration + buffer;
            }, 0);
        }

        function getSelectedDentistSnapshot() {
            const selectedDentistId = selectedDentistIdInput ? String(selectedDentistIdInput.value || '').trim() : '';
            if (!selectedDentistId) return null;
            return dentistsData.find(function (dentist) {
                return String(dentist && dentist.dentist_id ? dentist.dentist_id : '').trim() === selectedDentistId;
            }) || null;
        }

        function validateShiftCapacityForSelectedSlot() {
            const dentist = getSelectedDentistSnapshot();
            if (!dentist) {
                return { ok: true };
            }
            const selectedTimeRaw = timeInput ? String(timeInput.value || '').trim() : '';
            const selectedTimeMinutes = timeToMinutes(selectedTimeRaw);
            if (!Number.isFinite(selectedTimeMinutes)) {
                return { ok: true };
            }
            const requiredMinutes = computeRequiredMinutesForSelectedServices();
            if (!(requiredMinutes > 0)) {
                return { ok: true };
            }
            const requiredEndMinutes = selectedTimeMinutes + requiredMinutes;
            const shiftWindows = Array.isArray(dentist.shift_windows) ? dentist.shift_windows : [];
            if (!shiftWindows.length) {
                return {
                    ok: false,
                    reason: 'no_schedule',
                    message: 'The selected time exceeds the dentist’s working hours. Please choose a valid time.'
                };
            }

            let activeShift = null;
            for (let i = 0; i < shiftWindows.length; i += 1) {
                const shift = shiftWindows[i] || {};
                const shiftStart = timeToMinutes(String(shift.start || ''));
                const shiftEnd = timeToMinutes(String(shift.end || ''));
                if (!Number.isFinite(shiftStart) || !Number.isFinite(shiftEnd) || shiftEnd <= shiftStart) continue;
                if (selectedTimeMinutes >= shiftStart && selectedTimeMinutes < shiftEnd) {
                    activeShift = { start: shiftStart, end: shiftEnd, label: String(shift.label || '').trim() };
                    break;
                }
            }

            if (!activeShift) {
                return {
                    ok: false,
                    reason: 'outside_shift',
                    message: 'The selected time exceeds the dentist’s working hours. Please choose a valid time.'
                };
            }

            if (requiredEndMinutes > activeShift.end) {
                return {
                    ok: false,
                    reason: 'insufficient_shift_time',
                    message: 'The selected time exceeds the dentist’s working hours. Please choose a valid time.'
                };
            }

            return { ok: true };
        }

        async function runShiftBoundaryValidation(options) {
            const opts = options || {};
            const showAlerts = Boolean(opts.showAlerts);
            const validation = validateShiftCapacityForSelectedSlot();
            liveValidationState = {
                hasTimeSlotConflict: liveValidationState.hasTimeSlotConflict,
                hasPatientDuplicate: liveValidationState.hasPatientDuplicate,
                hasShiftBoundaryViolation: !validation.ok
            };
            syncCreateAppointmentButtonState();
            if (showAlerts && !validation.ok) {
                await staffUiAlert({
                    title: 'Dentist Working Hours Exceeded',
                    message: validation.message || 'The selected time exceeds the dentist’s working hours. Please choose a valid time.',
                    variant: 'warning'
                });
            }
            return validation;
        }

        async function refreshDentistAvailabilityForSelection() {
            const selectedDate = dateInput ? String(dateInput.value || '').trim() : '';
            if (!selectedDate) return;
            const selectedTime = formatTimeForApi(timeInput ? String(timeInput.value || '').trim() : '');
            const url = new URL(buildApiUrl(dentistAvailabilityApiUrl), window.location.origin);
            url.searchParams.set('date', selectedDate);
            if (selectedTime) {
                url.searchParams.set('time', selectedTime);
            }

            try {
                const response = await fetch(url.pathname + url.search, { credentials: 'include' });
                const data = await parseJsonResponse(response);
                if (!response.ok || !data.success || !data.data || !Array.isArray(data.data.dentists)) {
                    throw new Error(data.message || 'Failed to load dentist schedule.');
                }
                dentistsData = data.data.dentists.slice();
                renderDentistsList();

                const currentDentistId = selectedDentistIdInput ? String(selectedDentistIdInput.value || '').trim() : '';
                if (!currentDentistId) return;
                const selectedDentist = dentistsData.find(function (dentist) {
                    return String(dentist.dentist_id || '').trim() === currentDentistId;
                });
                const stillAvailable = selectedDentist && Number(selectedDentist.is_available_for_slot || 0) === 1;
                if (!stillAvailable) {
                    setSelectedDentist('', '', '');
                }
            } catch (error) {
                // Keep current dentist list on transient fetch failures.
            }
        }

        async function loadAllPatients() {
            if (!patientListContainer) return;
            setEmptyState('Loading patients...');

            const mergedPatients = [];
            let page = 1;
            let totalPages = 1;

            try {
                while (page <= totalPages) {
                    const response = await fetch(patientsApiUrl + '?page=' + page + '&limit=100', {
                        credentials: 'include'
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to load patients.');
                    }

                    const pagePatients = Array.isArray(data.data && data.data.patients) ? data.data.patients : [];
                    mergedPatients.push.apply(mergedPatients, pagePatients);
                    const pagesCount = Number(data.data && data.data.pagination && data.data.pagination.pages);
                    totalPages = Number.isFinite(pagesCount) && pagesCount > 0 ? pagesCount : 1;
                    page += 1;
                }
            } catch (error) {
                setEmptyState(error.message || 'Failed to load patients.');
                return;
            }

            allPatients = mergedPatients;
            renderPatientsList(allPatients);
        }

        async function loadAllServices() {
            setServiceEmptyState('Loading services...');
            const mergedServices = [];
            let page = 1;
            let hasMore = true;

            try {
                while (hasMore) {
                    const response = await fetch(servicesApiUrl + '?status=active&page=' + page + '&limit=100', {
                        credentials: 'include'
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to load services.');
                    }
                    const pageServices = Array.isArray(data.data && data.data.services) ? data.data.services : [];
                    mergedServices.push.apply(mergedServices, pageServices);
                    hasMore = pageServices.length === 100;
                    page += 1;
                }
            } catch (error) {
                setServiceEmptyState(error.message || 'Failed to load services.');
                return;
            }

            allServices = mergedServices;
            applyServiceFilters();
        }

        function syncModalBodyScrollLock() {
            const hasOpenModal = [addPatientModal, chooseDentistModal, chooseServiceModal].some(function (modalEl) {
                return modalEl && !modalEl.classList.contains('hidden');
            });
            document.body.classList.toggle('overflow-hidden', hasOpenModal);
        }

        function openChooseDentistModal() {
            if (!chooseDentistModal) return;
            chooseDentistModal.classList.remove('hidden');
            syncModalBodyScrollLock();
            renderDentistsList();
        }

        function closeChooseDentistModal() {
            if (!chooseDentistModal) return;
            chooseDentistModal.classList.add('hidden');
            syncModalBodyScrollLock();
        }

        function openChooseServiceModal() {
            if (!chooseServiceModal) return;
            chooseServiceModal.classList.remove('hidden');
            syncModalBodyScrollLock();
            if (serviceSearchInput) serviceSearchInput.value = '';
            selectedServiceCategoryFilter = 'all';
            renderServiceCategoryFilters();
            loadAllServices();
        }

        function closeChooseServiceModal() {
            if (!chooseServiceModal) return;
            chooseServiceModal.classList.add('hidden');
            syncModalBodyScrollLock();
        }

        function setSelectedPatient(patientId, patientName) {
            if (selectedPatientIdInput) selectedPatientIdInput.value = patientId || '';
            if (selectedPatientLabel) selectedPatientLabel.textContent = patientName || 'Choose patient from list';
            activeTreatmentContext = null;
            selectedServices = [];
            renderSelectedServices();
            setSelectedService('', 'Select service');
            updateTreatmentProgressCards(null);
            updatePaymentDetailsVisibility();
            updateDefaultPaymentDetails();
            syncCreateAppointmentButtonState();
        }

        function setSelectedDentist(dentistId, dentistUserId, dentistName) {
            if (selectedDentistIdInput) selectedDentistIdInput.value = dentistId || '';
            if (selectedDentistUserIdInput) selectedDentistUserIdInput.value = dentistUserId || '';
            if (selectedDentistLabel) selectedDentistLabel.textContent = dentistName || 'Tap to choose dentist';
            syncCreateAppointmentButtonState();
        }

        function setSelectedService(serviceId, serviceName) {
            const selectedService = getServiceById(serviceId);
            if (selectedService) {
                const serviceRule = getServiceSelectionRule(selectedService);
                if (!serviceRule.allowed) {
                    void staffUiAlert({
                        title: 'Installment service locked',
                        message: serviceRule.reason || 'Only the ongoing installment service can be selected while treatment is active.',
                        variant: 'warning'
                    });
                    return false;
                }
            }
            if (selectedServiceIdInput) selectedServiceIdInput.value = serviceId || '';
            if (selectedServiceLabel) selectedServiceLabel.textContent = serviceName || 'Tap to choose service';
            updateTreatmentProgressCards(activeTreatmentContext ? activeTreatmentContext.treatment : null);
            return true;
        }

        function buildApiUrl(baseUrl) {
            if (!clinicSlug) return baseUrl;
            const separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
            return baseUrl + separator + 'clinic_slug=' + encodeURIComponent(clinicSlug);
        }

        function isConflictBlockingStatus(statusValue) {
            const status = String(statusValue || '').trim().toLowerCase();
            if (!status) return true;
            return ['cancelled', 'completed', 'no_show'].indexOf(status) === -1;
        }

        function syncCreateAppointmentButtonState() {
            if (!createWalkInAppointmentBtn) return;
            const hasLiveConflict = liveValidationState.hasTimeSlotConflict || liveValidationState.hasPatientDuplicate;
            const hasShiftBoundaryViolation = Boolean(liveValidationState.hasShiftBoundaryViolation);
            const hasRequiredFields = Boolean(
                selectedPatientIdInput && String(selectedPatientIdInput.value || '').trim()
                && selectedDentistIdInput && String(selectedDentistIdInput.value || '').trim()
                && dateInput && String(dateInput.value || '').trim()
                && timeInput && String(timeInput.value || '').trim()
                && selectedServices.length > 0
            );
            const shouldDisable = isSubmittingAppointment || hasLiveConflict || hasShiftBoundaryViolation || !hasRequiredFields;
            createWalkInAppointmentBtn.disabled = shouldDisable;
            createWalkInAppointmentBtn.classList.toggle('opacity-70', shouldDisable);
            createWalkInAppointmentBtn.classList.toggle('cursor-not-allowed', shouldDisable);
        }

        function rangesOverlap(startA, endA, startB, endB) {
            if (!Number.isFinite(startA) || !Number.isFinite(endA) || !Number.isFinite(startB) || !Number.isFinite(endB)) {
                return false;
            }
            return startA < endB && startB < endA;
        }

        function parsePositiveMinutes(value) {
            const minutes = Number(value);
            if (!Number.isFinite(minutes) || minutes <= 0) return NaN;
            return minutes;
        }

        function normalizeServiceNameToken(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/\s+/g, ' ')
                .trim();
        }

        function extractDurationFromServiceCatalog(serviceNames) {
            if (!Array.isArray(serviceNames) || !serviceNames.length || !Array.isArray(allServices) || !allServices.length) {
                return NaN;
            }
            const wantedNames = new Set(serviceNames.map(normalizeServiceNameToken).filter(Boolean));
            if (!wantedNames.size) {
                return NaN;
            }
            let totalMinutes = 0;
            allServices.forEach(function (service) {
                const serviceNameKey = normalizeServiceNameToken(service && service.service_name ? service.service_name : '');
                if (!serviceNameKey || !wantedNames.has(serviceNameKey)) {
                    return;
                }
                const duration = Math.max(0, Number(service && service.service_duration ? service.service_duration : 0));
                const buffer = Math.max(0, Number(service && service.buffer_time ? service.buffer_time : 0));
                totalMinutes += duration + buffer;
            });
            return totalMinutes > 0 ? totalMinutes : NaN;
        }

        function extractServiceNamesFromAppointment(appointment) {
            if (!appointment || typeof appointment !== 'object') {
                return [];
            }
            const names = [];
            const description = String(appointment.service_description || '');
            if (description) {
                const descriptionMatches = description.match(/([^;|]+?)\s*\([P₱]\s*[\d,]+(?:\.\d+)?\)/g) || [];
                descriptionMatches.forEach(function (item) {
                    const cleaned = String(item || '').replace(/\s*\([P₱].*$/, '').trim();
                    if (cleaned) names.push(cleaned);
                });
            }
            const serviceTypeRaw = String(appointment.service_type || '').trim();
            if (serviceTypeRaw) {
                const parsedTypeNames = serviceTypeRaw
                    .split(',')
                    .map(function (part) {
                        return String(part || '').replace(/\(\+\d+\s+more\)/i, '').trim();
                    })
                    .filter(Boolean);
                names.push.apply(names, parsedTypeNames);
            }
            return Array.from(new Set(names.map(normalizeServiceNameToken))).filter(Boolean);
        }

        function inferAppointmentDurationMinutes(appointment) {
            if (!appointment || typeof appointment !== 'object') {
                return 60;
            }
            const directDurationCandidates = [
                appointment.duration_minutes,
                appointment.appointment_duration_minutes,
                appointment.total_duration_minutes,
                appointment.estimated_duration_minutes
            ];
            for (let i = 0; i < directDurationCandidates.length; i += 1) {
                const directDuration = parsePositiveMinutes(directDurationCandidates[i]);
                if (Number.isFinite(directDuration)) {
                    return directDuration;
                }
            }

            const serviceDuration = parsePositiveMinutes(appointment.service_duration);
            const bufferDuration = parsePositiveMinutes(appointment.buffer_time);
            if (Number.isFinite(serviceDuration) && Number.isFinite(bufferDuration)) {
                return serviceDuration + bufferDuration;
            }
            if (Number.isFinite(serviceDuration)) {
                return serviceDuration;
            }

            const appointmentServiceNames = extractServiceNamesFromAppointment(appointment);
            const catalogDuration = extractDurationFromServiceCatalog(appointmentServiceNames);
            if (Number.isFinite(catalogDuration)) {
                return catalogDuration;
            }

            return 60;
        }

        function getAppointmentTimeRange(appointment) {
            const existingStartMinutes = timeToMinutes(String(appointment && appointment.appointment_time ? appointment.appointment_time : '').trim());
            if (!Number.isFinite(existingStartMinutes)) {
                return null;
            }
            const explicitEndCandidates = [
                appointment.end_time,
                appointment.appointment_end_time,
                appointment.end_at_time
            ];
            for (let i = 0; i < explicitEndCandidates.length; i += 1) {
                const explicitEndMinutes = timeToMinutes(String(explicitEndCandidates[i] || '').trim());
                if (Number.isFinite(explicitEndMinutes) && explicitEndMinutes > existingStartMinutes) {
                    return { start: existingStartMinutes, end: explicitEndMinutes };
                }
            }

            const inferredDurationMinutes = inferAppointmentDurationMinutes(appointment);
            return {
                start: existingStartMinutes,
                end: existingStartMinutes + inferredDurationMinutes
            };
        }

        async function runLiveConflictValidation(options) {
            const opts = options || {};
            const showAlerts = Boolean(opts.showAlerts);
            const token = ++liveValidationRequestToken;
            const patientId = selectedPatientIdInput ? String(selectedPatientIdInput.value || '').trim() : '';
            const dentistId = selectedDentistIdInput ? String(selectedDentistIdInput.value || '').trim() : '';
            const appointmentDate = dateInput ? String(dateInput.value || '').trim() : '';
            const appointmentTime = formatTimeForApi(timeInput ? String(timeInput.value || '').trim() : '');

            if (!appointmentDate) {
                const previousNoDateState = {
                    hasTimeSlotConflict: liveValidationState.hasTimeSlotConflict,
                    hasPatientDuplicate: liveValidationState.hasPatientDuplicate
                };
                liveValidationState = { hasTimeSlotConflict: false, hasPatientDuplicate: false };
                syncCreateAppointmentButtonState();
                return previousNoDateState;
            }

            let appointments = [];
            try {
                const conflictUrl = new URL(buildApiUrl(appointmentsApiUrl), window.location.origin);
                conflictUrl.searchParams.set('date', appointmentDate);
                const response = await fetch(conflictUrl.pathname + conflictUrl.search, {
                    credentials: 'include'
                });
                const data = await parseJsonResponse(response);
                if (!response.ok || !data.success) {
                    throw new Error(data && data.message ? data.message : 'Failed to validate appointment conflicts.');
                }
                appointments = Array.isArray(data.data && data.data.appointments) ? data.data.appointments : [];
            } catch (error) {
                if (token !== liveValidationRequestToken) {
                    return {
                        hasTimeSlotConflict: liveValidationState.hasTimeSlotConflict,
                        hasPatientDuplicate: liveValidationState.hasPatientDuplicate
                    };
                }
                liveValidationState = { hasTimeSlotConflict: false, hasPatientDuplicate: false };
                syncCreateAppointmentButtonState();
                return {
                    hasTimeSlotConflict: false,
                    hasPatientDuplicate: false
                };
            }

            if (token !== liveValidationRequestToken) {
                return {
                    hasTimeSlotConflict: liveValidationState.hasTimeSlotConflict,
                    hasPatientDuplicate: liveValidationState.hasPatientDuplicate
                };
            }

            const selectedStartMinutes = timeToMinutes(appointmentTime);
            const selectedServiceMinutes = computeRequiredMinutesForSelectedServices();
            const hasKnownSelectedDuration = selectedServiceMinutes > 0;
            const selectedDurationMinutes = hasKnownSelectedDuration ? selectedServiceMinutes : 0;
            const selectedEndMinutes = Number.isFinite(selectedStartMinutes) ? selectedStartMinutes + selectedDurationMinutes : NaN;
            const hasTimeSlotConflict = Boolean(dentistId && Number.isFinite(selectedStartMinutes) && appointments.some(function (appointment) {
                if (!isConflictBlockingStatus(appointment.final_status || appointment.status)) return false;
                if (String(appointment.dentist_id || '').trim() !== dentistId) return false;
                const existingRange = getAppointmentTimeRange(appointment);
                if (!existingRange) return false;
                if (!hasKnownSelectedDuration) {
                    // Before service selection, only block if the chosen start is inside an occupied range.
                    return selectedStartMinutes >= existingRange.start && selectedStartMinutes < existingRange.end;
                }
                return rangesOverlap(selectedStartMinutes, selectedEndMinutes, existingRange.start, existingRange.end);
            }));
            const sameDayPatientAppointments = patientId ? appointments.filter(function (appointment) {
                if (!isConflictBlockingStatus(appointment.final_status || appointment.status)) return false;
                return String(appointment.patient_id || '').trim() === patientId;
            }) : [];
            const overlappingPatientAppointment = Number.isFinite(selectedStartMinutes) ? (sameDayPatientAppointments.find(function (appointment) {
                const existingRange = getAppointmentTimeRange(appointment);
                if (!existingRange) return false;
                if (!hasKnownSelectedDuration) {
                    return selectedStartMinutes >= existingRange.start && selectedStartMinutes < existingRange.end;
                }
                return rangesOverlap(selectedStartMinutes, selectedEndMinutes, existingRange.start, existingRange.end);
            }) || null) : null;
            const hasPatientDuplicate = Boolean(overlappingPatientAppointment);
            const patientHasSameDayAppointment = sameDayPatientAppointments.length > 0;

            const previousState = {
                hasPatientDuplicate: liveValidationState.hasPatientDuplicate
            };
            liveValidationState = {
                hasTimeSlotConflict: hasTimeSlotConflict,
                hasPatientDuplicate: hasPatientDuplicate
            };
            syncCreateAppointmentButtonState();

            if (showAlerts && hasTimeSlotConflict) {
                await staffUiAlert({
                    title: 'Schedule conflict',
                    message: 'There is already an existing appointment scheduled for this dentist at the selected time. Please choose a different time slot.',
                    variant: 'warning'
                });
                if (token !== liveValidationRequestToken) {
                    return {
                        hasTimeSlotConflict: liveValidationState.hasTimeSlotConflict,
                        hasPatientDuplicate: liveValidationState.hasPatientDuplicate
                    };
                }
                if (timeInput) {
                    timeInput.value = '00:00';
                }
                liveValidationState = {
                    hasTimeSlotConflict: false,
                    hasPatientDuplicate: liveValidationState.hasPatientDuplicate
                };
                syncCreateAppointmentButtonState();
                await refreshDentistAvailabilityForSelection();
                return runLiveConflictValidation({ showAlerts: false });
            }
            if (showAlerts && !previousState.hasPatientDuplicate && hasPatientDuplicate) {
                await staffUiAlert({
                    title: 'Schedule conflict',
                    message: 'This patient already has an appointment that overlaps with the selected time. Please choose a different time slot.',
                    variant: 'warning'
                });
                liveValidationState = {
                    hasTimeSlotConflict: liveValidationState.hasTimeSlotConflict,
                    hasPatientDuplicate: true
                };
                syncCreateAppointmentButtonState();
            }

            return {
                hasTimeSlotConflict: hasTimeSlotConflict,
                hasPatientDuplicate: hasPatientDuplicate,
                patientHasSameDayAppointment: patientHasSameDayAppointment,
                overlappingPatientAppointment: overlappingPatientAppointment,
                firstPatientAppointment: sameDayPatientAppointments.length ? sameDayPatientAppointments[0] : null
            };
        }

        async function loadPatientTreatmentContext(patientId) {
            activeTreatmentContext = null;
            updateTreatmentProgressCards(null);
            if (!patientId) {
                updatePaymentDetailsVisibility();
                updateDefaultPaymentDetails();
                return;
            }
            try {
                const response = await fetch(buildApiUrl(patientTreatmentContextApiUrl + '?patient_id=' + encodeURIComponent(patientId)), {
                    credentials: 'include'
                });
                const data = await parseJsonResponse(response);
                if (!response.ok || !data.success || !data.data || !data.data.has_active_treatment) {
                    activeTreatmentContext = null;
                    selectedServices = [];
                    setSelectedService('', 'Select service');
                    renderSelectedServices();
                    applyServiceFilters();
                    updatePaymentDetailsVisibility();
                    updateDefaultPaymentDetails();
                    return;
                }
                if (treatmentIsFullyPaid(data.data)) {
                    activeTreatmentContext = null;
                    selectedServices = [];
                    setSelectedService('', 'Select service');
                    renderSelectedServices();
                    applyServiceFilters();
                    updatePaymentDetailsVisibility();
                    updateDefaultPaymentDetails();
                    return;
                }
                activeTreatmentContext = data.data;
                selectedServices = [];
                setSelectedService('', 'Select service');
                if (treatmentIsInstallmentPlan(activeTreatmentContext)) {
                    const treatment = data.data.treatment || {};
                    const treatmentName = String((treatment.primary_service && treatment.primary_service.service_name) || treatment.treatment_name || 'N/A').trim();
                    const treatmentStatus = String(treatment.status || 'Ongoing').trim() || 'Ongoing';
                    const remainingBalanceRaw = Number(treatment.remaining_balance);
                    const remainingBalanceLabel = Number.isFinite(remainingBalanceRaw)
                        ? formatPeso(Math.max(0, remainingBalanceRaw))
                        : 'N/A';
                    await staffUiAlert({
                        title: 'Active Treatment Detected',
                        message: 'This patient is currently under an active installment treatment:\n\nTreatment: ' + treatmentName + '\nStatus: ' + treatmentStatus + '\nRemaining Balance: ' + remainingBalanceLabel + '\n\nPlease select the appropriate follow-up service (e.g., Adjustment or Checkup).',
                        variant: 'warning'
                    });
                }
                renderSelectedServices();
                applyServiceFilters();
                updateTreatmentProgressCards(data.data.treatment || null);
            } catch (error) {
                activeTreatmentContext = null;
                selectedServices = [];
                setSelectedService('', 'Select service');
                renderSelectedServices();
                applyServiceFilters();
                updatePaymentDetailsVisibility();
                updateDefaultPaymentDetails();
            }
        }

        async function parseJsonResponse(response) {
            const rawText = await response.text();
            if (!rawText || !rawText.trim()) {
                return {
                    success: false,
                    message: 'Server returned an empty response.'
                };
            }
            try {
                return JSON.parse(rawText);
            } catch (error) {
                return {
                    success: false,
                    message: 'Unexpected server response. Please try again.',
                    raw: rawText
                };
            }
        }

        async function submitWalkInAppointment() {
            const patientId = selectedPatientIdInput ? String(selectedPatientIdInput.value || '').trim() : '';
            const dentistId = selectedDentistIdInput ? String(selectedDentistIdInput.value || '').trim() : '';
            const notes = notesInput ? String(notesInput.value || '').trim() : '';
            const dentistUserId = selectedDentistUserIdInput ? String(selectedDentistUserIdInput.value || '').trim() : '';

            if (!patientId) {
                void staffUiAlert({ message: 'Please select a patient first.', variant: 'warning', title: 'Patient required' });
                return;
            }
            if (!dentistId) {
                void staffUiAlert({ message: 'Please select an assigned dentist.', variant: 'warning', title: 'Dentist required' });
                return;
            }
            if (!selectedServices.length) {
                void staffUiAlert({ message: 'Please add at least one service.', variant: 'warning', title: 'Services required' });
                return;
            }
            const shiftValidation = await runShiftBoundaryValidation({ showAlerts: true });
            if (!shiftValidation.ok) {
                return;
            }
            const hasInstallmentSelection = selectedServices.some(function (service) {
                return normalizeServiceType(service && service.service_type) === 'installment';
            });
            if (patientHasActiveTreatmentPlan() && hasInstallmentSelection) {
                await staffUiAlert({
                    title: 'Active treatment plan exists',
                    message: 'This patient already has an active treatment plan. Only one active installment plan is allowed per patient.',
                    variant: 'warning'
                });
                return;
            }
            const compatibility = validateServiceCompatibility(selectedServices);
            if (!compatibility.valid) {
                await staffUiAlert({
                    title: 'Service combination not allowed',
                    message: compatibility.message,
                    variant: 'warning'
                });
                return;
            }

            const appointmentDate = dateInput ? String(dateInput.value || '').trim() : '';
            const appointmentTimeRaw = timeInput ? String(timeInput.value || '').trim() : '';
            const appointmentTime = formatTimeForApi(appointmentTimeRaw);
            if (!appointmentDate) {
                void staffUiAlert({ message: 'Please select an appointment date.', variant: 'warning', title: 'Date required' });
                return;
            }
            if (!appointmentTime) {
                void staffUiAlert({ message: 'Please select an appointment time.', variant: 'warning', title: 'Time required' });
                return;
            }
            const selectedDateTime = new Date(appointmentDate + 'T' + appointmentTime);
            if (Number.isNaN(selectedDateTime.getTime())) {
                void staffUiAlert({ message: 'Please provide a valid appointment schedule.', variant: 'warning', title: 'Invalid schedule' });
                return;
            }
            if (selectedDateTime.getTime() < Date.now()) {
                void staffUiAlert({
                    title: 'Schedule must be current or future',
                    message: 'Please choose a current or future time slot.',
                    variant: 'warning'
                });
                return;
            }
            const payload = {
                patient_id: patientId,
                clinic_slug: clinicSlug || '',
                booking_source: 'staff_set_appointments',
                treatment_id: treatmentIsInstallmentPlan(activeTreatmentContext) && activeTreatmentContext.treatment
                    ? (activeTreatmentContext.treatment.treatment_id || '')
                    : '',
                appointment_date: appointmentDate,
                appointment_time: appointmentTime,
                services: selectedServices.map(function (service) {
                    const serviceType = normalizeServiceType(service && service.service_type);
                    const isIncludedPlan = serviceType === 'included_plan';
                    return {
                        id: service.service_id || null,
                        name: service.service_name || '',
                        price: isIncludedPlan ? 0 : Number(service.price || 0),
                        category: service.category || '',
                        service_type: serviceType || 'regular'
                    };
                }),
                service_categories: Array.from(new Set(selectedServices.map(function (service) {
                    return String(service.category || '').trim();
                }).filter(Boolean))),
                notes: notes,
                dentist_id: dentistId,
                dentist_user_id: dentistUserId,
                visit_type: 'pre_book',
                status: 'pending'
            };

            const shiftCapacityValidation = await runShiftBoundaryValidation({ showAlerts: true });
            if (!shiftCapacityValidation.ok) {
                return;
            }

            const liveConflicts = await runLiveConflictValidation({ showAlerts: true });
            if (liveConflicts.hasTimeSlotConflict || liveConflicts.hasPatientDuplicate) {
                return;
            }
            if (liveConflicts.patientHasSameDayAppointment) {
                const existingAppointment = liveConflicts.firstPatientAppointment || {};
                const existingRange = getAppointmentTimeRange(existingAppointment);
                const existingStartLabel = existingRange
                    ? minutesToDisplayTime(existingRange.start)
                    : String(existingAppointment.appointment_time || '').trim();
                const existingEndLabel = existingRange
                    ? minutesToDisplayTime(existingRange.end)
                    : '-';
                const proceedWithSameDay = await staffUiConfirm({
                    title: 'Existing appointment found',
                    message: 'This patient already has an appointment today at ' + existingStartLabel + ' – ' + existingEndLabel + '.\n\nDo you still want to create another appointment?',
                    cancelLabel: 'No',
                    confirmLabel: 'Yes, Continue',
                    variant: 'warning'
                });
                if (!proceedWithSameDay) {
                    return;
                }
            }

            isSubmittingAppointment = true;
            syncCreateAppointmentButtonState();

            try {
                const response = await fetch(buildApiUrl(appointmentsApiUrl), {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await parseJsonResponse(response);
                if (!response.ok || !data.success) {
                    const message = (data && data.message) ? data.message : 'Failed to create scheduled appointment.';
                    if (String(message).toLowerCase().indexOf('selected staff/dentist is not available at this time') !== -1) {
                        const retryShiftValidation = validateShiftCapacityForSelectedSlot();
                        if (!retryShiftValidation.ok && retryShiftValidation.reason === 'insufficient_shift_time') {
                            throw new Error(retryShiftValidation.message);
                        }
                    }
                    throw new Error(message);
                }

                var successMsg = 'Scheduled appointment created successfully. Booking ID: ' + (data.data && data.data.booking_id ? data.data.booking_id : 'N/A');
                await staffUiAlert({
                    title: 'Appointment scheduled',
                    message: successMsg,
                    variant: 'success'
                });
                var _back = <?php echo json_encode($backToAppointmentsHref, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                window.location.href = _back;
            } catch (error) {
                await staffUiAlert({
                    title: 'Could not schedule appointment',
                    message: error && error.message ? error.message : 'Unable to create the scheduled appointment right now.',
                    variant: 'error'
                });
            } finally {
                isSubmittingAppointment = false;
                syncCreateAppointmentButtonState();
            }
        }

        if (chooseDentistBtn) {
            chooseDentistBtn.addEventListener('click', openChooseDentistModal);
        }
        if (registerPatientBtnTop) {
            registerPatientBtnTop.addEventListener('click', openAddPatientModal);
        }
        if (clearSelectedPatientBtn) {
            clearSelectedPatientBtn.addEventListener('click', async function () {
                setSelectedPatient('', '');
                await loadPatientTreatmentContext('');
                await runLiveConflictValidation({ showAlerts: true });
            });
        }
        if (closeAddPatientModalBtn) {
            closeAddPatientModalBtn.addEventListener('click', closeAddPatientModal);
        }
        if (cancelAddPatientBtn) {
            cancelAddPatientBtn.addEventListener('click', closeAddPatientModal);
        }
        if (addPatientModal) {
            addPatientModal.addEventListener('click', function (event) {
                if (event.target === addPatientModal) {
                    closeAddPatientModal();
                }
            });
        }
        if (addPatientForm) {
            addPatientForm.addEventListener('submit', savePatientFromWalkIn);
        }
        if (closeChooseDentistModalBtn) {
            closeChooseDentistModalBtn.addEventListener('click', closeChooseDentistModal);
        }
        if (chooseDentistModal) {
            chooseDentistModal.addEventListener('click', function (event) {
                if (event.target === chooseDentistModal || event.target === chooseDentistModal.firstElementChild) {
                    closeChooseDentistModal();
                }
            });
        }
        if (chooseServiceBtn) {
            chooseServiceBtn.addEventListener('click', openChooseServiceModal);
        }
        if (closeChooseServiceModalBtn) {
            closeChooseServiceModalBtn.addEventListener('click', closeChooseServiceModal);
        }
        if (chooseServiceModal) {
            chooseServiceModal.addEventListener('click', function (event) {
                if (event.target === chooseServiceModal || event.target === chooseServiceModal.firstElementChild) {
                    closeChooseServiceModal();
                }
            });
        }
        if (serviceSearchInput) {
            serviceSearchInput.addEventListener('input', function () {
                applyServiceFilters();
            });
        }
        if (serviceCategoryFilters) {
            serviceCategoryFilters.addEventListener('click', function (event) {
                const button = event.target.closest('button[data-action="service-category-filter"]');
                if (!button) return;
                const next = button.getAttribute('data-category-key') || 'all';
                selectedServiceCategoryFilter = next;
                renderServiceCategoryFilters();
                applyServiceFilters();
            });
        }
        if (patientSearchInput) {
            patientSearchInput.addEventListener('input', function () {
                const keyword = patientSearchInput.value.trim().toLowerCase();
                if (!keyword) {
                    renderPatientsList(allPatients);
                    return;
                }
                const filtered = allPatients.filter(function (patient) {
                    const haystack = [
                        patient.patient_id,
                        patient.first_name,
                        patient.last_name,
                        patient.contact_number
                    ].join(' ').toLowerCase();
                    return haystack.indexOf(keyword) !== -1;
                });
                renderPatientsList(filtered);
            });
        }
        if (addDobInput) {
            addDobInput.max = regGetDobMaxDateForMinimumAge(1);
            addDobInput.addEventListener('change', function () {
                if (addAgeInput) addAgeInput.value = regCalculateAge(addDobInput.value);
                if (addDobInput.value && !regIsAtLeastOneYearOld(addDobInput.value)) {
                    regShowFieldError('addDob', 'Patient must be at least 1 year old.');
                } else {
                    regClearFieldError('addDob');
                }
            });
        }
        if (addProvinceSelect) {
            addProvinceSelect.addEventListener('change', async function () {
                regClearFieldError('addProvince');
                await regLoadCities(addProvinceSelect.value, '');
                regFillSelect(addBarangaySelect, [], 'Select barangay');
            });
        }
        if (addCitySelect) {
            addCitySelect.addEventListener('change', async function () {
                regClearFieldError('addCity');
                await regLoadBarangays(addProvinceSelect ? addProvinceSelect.value : '', addCitySelect.value, '');
            });
        }
        if (addBarangaySelect) {
            addBarangaySelect.addEventListener('change', function () { regClearFieldError('addBarangay'); });
        }
        addGenderRadios.forEach(function (radio) {
            radio.addEventListener('change', function () { regClearFieldError('addGender'); });
        });
        Object.keys(regFieldValidators).forEach(function (fieldId) {
            if (fieldId === 'addGender') return;
            const inputEl = document.getElementById(fieldId);
            if (!inputEl) return;
            inputEl.addEventListener('input', function () { regClearFieldError(fieldId); });
            inputEl.addEventListener('change', function () { regClearFieldError(fieldId); });
        });
        if (patientListContainer) {
            patientListContainer.addEventListener('click', async function (event) {
                const button = event.target.closest('button[data-action="select-patient"]');
                if (!button) return;
                const patientId = button.getAttribute('data-patient-id') || '';
                const patientName = button.getAttribute('data-patient-name') || '';
                setSelectedPatient(patientId, patientName);
                await loadPatientTreatmentContext(patientId);
                await runShiftBoundaryValidation({ showAlerts: true });
                await runLiveConflictValidation({ showAlerts: true });
            });
        }
        if (dentistListContainer) {
            dentistListContainer.addEventListener('click', async function (event) {
                const button = event.target.closest('button[data-action="select-dentist"]');
                if (!button) return;
                if (button.disabled) return;
                setSelectedDentist(
                    button.getAttribute('data-dentist-id') || '',
                    button.getAttribute('data-dentist-user-id') || '',
                    button.getAttribute('data-dentist-name') || ''
                );
                closeChooseDentistModal();
                await runShiftBoundaryValidation({ showAlerts: true });
                await runLiveConflictValidation({ showAlerts: true });
            });
        }
        if (serviceListContainer) {
            serviceListContainer.addEventListener('click', function (event) {
                const button = event.target.closest('button[data-action="select-service"]');
                if (!button) return;
                if (button.disabled) return;
                const serviceId = button.getAttribute('data-service-id') || '';
                const service = allServices.find(function (item) {
                    return String(item.service_id || '') === serviceId;
                });
                if (!service) return;
                const selected = setSelectedService(service.service_id || '', service.service_name || '');
                if (!selected) return;
                closeChooseServiceModal();
            });
        }
        if (addServiceBtn) {
            addServiceBtn.addEventListener('click', async function () {
                const serviceId = selectedServiceIdInput ? selectedServiceIdInput.value : '';
                if (!serviceId) return;
                const service = allServices.find(function (item) {
                    return String(item.service_id || '') === String(serviceId);
                });
                if (!service) return;
                const serviceRule = getServiceSelectionRule(service);
                if (!serviceRule.allowed) {
                    void staffUiAlert({
                        title: 'Installment service locked',
                        message: serviceRule.reason || 'Only the ongoing installment service can be used while treatment is active.',
                        variant: 'warning'
                    });
                    return;
                }
                const alreadyAdded = selectedServices.some(function (item) {
                    return String(item.service_id || '') === String(service.service_id || '');
                });
                if (alreadyAdded) return;
                const nextServices = selectedServices.concat([service]);
                const compatibility = validateServiceCompatibility(nextServices);
                if (!compatibility.valid) {
                    void staffUiAlert({
                        title: 'Service combination not allowed',
                        message: compatibility.message,
                        variant: 'warning'
                    });
                    return;
                }
                selectedServices.push(service);
                setSelectedService('', 'Select service');
                renderSelectedServices();
                await runShiftBoundaryValidation({ showAlerts: true });
                await runLiveConflictValidation({ showAlerts: true });
            });
        }
        if (selectedServicesContainer) {
            selectedServicesContainer.addEventListener('click', async function (event) {
                const button = event.target.closest('button[data-action="remove-service"]');
                if (!button) return;
                const serviceId = button.getAttribute('data-service-id') || '';
                selectedServices = selectedServices.filter(function (item) {
                    return String(item.service_id || '') !== String(serviceId);
                });
                renderSelectedServices();
                await runShiftBoundaryValidation({ showAlerts: true });
                await runLiveConflictValidation({ showAlerts: true });
            });
        }
        if (createWalkInAppointmentBtn) {
            createWalkInAppointmentBtn.addEventListener('click', submitWalkInAppointment);
        }
        if (dateInput) {
            dateInput.addEventListener('change', async function () {
                await refreshDentistAvailabilityForSelection();
                await runShiftBoundaryValidation({ showAlerts: true });
                await runLiveConflictValidation({ showAlerts: true });
            });
        }
        if (timeInput) {
            timeInput.addEventListener('change', async function () {
                await refreshDentistAvailabilityForSelection();
                await runShiftBoundaryValidation({ showAlerts: true });
                await runLiveConflictValidation({ showAlerts: true });
            });
        }

        if (dateInput && !dateInput.value) {
            dateInput.value = new Date().toISOString().slice(0, 10);
        }
        if (timeInput && !timeInput.value) {
            const now = new Date();
            timeInput.value = pad(now.getHours()) + ':' + pad(now.getMinutes());
        }
        updatePaymentDetailsVisibility();
        updateDefaultPaymentDetails();
        renderSelectedServices();
        void refreshDentistAvailabilityForSelection();
        void runShiftBoundaryValidation({ showAlerts: false });
        void runLiveConflictValidation({ showAlerts: false });
        regLoadProvinces('');
        loadAllPatients();
    })();
</script>
</body>
</html>
