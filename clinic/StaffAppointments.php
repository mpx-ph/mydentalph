<?php
$pageTitle = 'Appointments';
$staff_nav_active = 'appointments';
require_once __DIR__ . '/config/config.php';

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
$selectedDate = isset($_GET['date']) ? trim((string) $_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$selectedMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : substr($selectedDate, 0, 7);
if (!preg_match('/^\d{4}\-\d{2}$/', $selectedMonth)) {
    $selectedMonth = substr($selectedDate, 0, 7);
}

$allowedStatuses = ['all', 'scheduled', 'pending', 'cancelled', 'completed', 'no_show'];
$selectedStatus = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : 'all';
if (!in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = 'all';
}

$searchTerm = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$summary = [
    'scheduled' => 0,
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

try {
    $pdo = getDBConnection();

    if ($tenantId === '' && $currentTenantSlug !== '') {
        $tenantStmt = $pdo->prepare('SELECT tenant_id FROM tbl_tenants WHERE clinic_slug = ? LIMIT 1');
        $tenantStmt->execute([$currentTenantSlug]);
        $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenantRow && isset($tenantRow['tenant_id'])) {
            $tenantId = (string) $tenantRow['tenant_id'];
        }
    }

    if ($tenantId !== '') {
        $summaryStmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN status IN ('confirmed', 'scheduled') THEN 1 ELSE 0 END) AS scheduled_count,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count
            FROM tbl_appointments
            WHERE tenant_id = ? AND appointment_date = ?
        ");
        $summaryStmt->execute([$tenantId, $selectedDate]);
        $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary['scheduled'] = (int) ($summaryRow['scheduled_count'] ?? 0);
        $summary['cancelled'] = (int) ($summaryRow['cancelled_count'] ?? 0);
        $summary['pending'] = (int) ($summaryRow['pending_count'] ?? 0);

        $monthStmt = $pdo->prepare("
            SELECT appointment_date, COUNT(*) AS day_count
            FROM tbl_appointments
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

        $dailySql = "
            SELECT
                a.booking_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                (SELECT GROUP_CONCAT(service_name SEPARATOR ', ') FROM tbl_appointment_services WHERE booking_id = a.booking_id) as service_type,
                a.service_description,
                a.treatment_type,
                a.status,
                a.notes,
                a.total_treatment_cost,
                a.created_by,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact_number,
                p.patient_id AS patient_display_id,
                u.email AS created_by_email
            FROM tbl_appointments a
            LEFT JOIN tbl_patients p
              ON p.tenant_id = a.tenant_id
             AND p.patient_id = a.patient_id
            LEFT JOIN tbl_users u
              ON u.user_id = a.created_by
            WHERE a.tenant_id = ?
              AND a.appointment_date = ?
        ";
        $params = [$tenantId, $selectedDate];

        if ($selectedStatus === 'scheduled') {
            $dailySql .= " AND a.status IN ('confirmed', 'scheduled')";
        } elseif ($selectedStatus !== 'all') {
            $dailySql .= " AND a.status = ?";
            $params[] = $selectedStatus;
        }

        if ($searchTerm !== '') {
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
        }

        $dailySql .= " ORDER BY a.appointment_time ASC, a.created_at ASC";
        $dailyStmt = $pdo->prepare($dailySql);
        $dailyStmt->execute($params);
        $dailyAppointments = $dailyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    error_log('Staff appointments load error: ' . $e->getMessage());
}

$statusLabels = [
    'all' => 'All Statuses',
    'scheduled' => 'Scheduled',
    'pending' => 'Pending',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed',
    'no_show' => 'No Show',
];
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
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
    <div class="p-10 space-y-8">
        <section class="flex flex-col gap-4">
            <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
                <span class="w-12 h-[1.5px] bg-primary"></span> APPOINTMENT MANAGEMENT
            </div>
            <div>
                <h2 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Bookings <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Manager</span>
                </h2>
                <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                    Daily schedule with status tracking and treatment details.
                </p>
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
                <p class="text-4xl font-extrabold tracking-tight"><?php echo number_format($summary['scheduled']); ?></p>
                <p class="text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Scheduled</p>
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
                                if ($statusRaw === 'confirmed') {
                                    $statusRaw = 'scheduled';
                                }
                                $statusLabel = ucfirst(str_replace('_', ' ', $statusRaw));
                                $statusClass = 'bg-amber-50 text-amber-600';
                                if ($statusRaw === 'scheduled') {
                                    $statusClass = 'bg-primary/10 text-primary';
                                } elseif ($statusRaw === 'cancelled') {
                                    $statusClass = 'bg-rose-50 text-rose-600';
                                } elseif ($statusRaw === 'completed') {
                                    $statusClass = 'bg-emerald-50 text-emerald-600';
                                } elseif ($statusRaw === 'no_show') {
                                    $statusClass = 'bg-slate-200 text-slate-700';
                                }
                                $treatmentType = (string) ($appointment['treatment_type'] ?? 'short_term');
                                $typeLabel = ucfirst(str_replace('_', ' ', $treatmentType));
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
                                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-wider">
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
                                            data-cost="<?php echo htmlspecialchars(number_format((float) ($appointment['total_treatment_cost'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-notes="<?php echo htmlspecialchars((string) ($appointment['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>"
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

<div id="treatmentModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/50" id="modalBackdrop"></div>
    <div class="absolute inset-0 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-2xl border border-slate-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h4 class="text-2xl font-bold text-primary">Treatment Details</h4>
                <button type="button" id="modalCloseBtn" class="w-8 h-8 rounded-lg text-slate-500 hover:text-slate-700 hover:bg-slate-100">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-3">Patient Information</p>
                    <p class="font-semibold text-slate-500">Name</p>
                    <p id="mPatientName" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Patient ID</p>
                    <p id="mPatientId" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Contact Number</p>
                    <p id="mPatientContact" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Assigned Staff</p>
                    <p id="mStaff" class="font-bold text-slate-900">-</p>
                </div>
                <div>
                    <p class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-3">Appointment Details</p>
                    <p class="font-semibold text-slate-500">Booking ID</p>
                    <p id="mBookingId" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Date</p>
                    <p id="mDate" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Time</p>
                    <p id="mTime" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Treatment Type</p>
                    <p id="mType" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Status</p>
                    <p id="mStatus" class="font-bold text-slate-900">-</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-[11px] font-black uppercase tracking-widest text-slate-400 mb-3">Treatment Information</p>
                    <p class="font-semibold text-slate-500">Treatment/Service</p>
                    <p id="mTreatment" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Service Description</p>
                    <p id="mDescription" class="font-medium text-slate-700 bg-slate-50 p-3 rounded-lg mb-3">-</p>
                    <p class="font-semibold text-slate-500">Total Cost</p>
                    <p id="mCost" class="font-bold text-slate-900 mb-3">-</p>
                    <p class="font-semibold text-slate-500">Notes</p>
                    <p id="mNotes" class="font-medium text-slate-700">-</p>
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

    function setText(id, value) {
        const node = document.getElementById(id);
        if (node) {
            node.textContent = value && value.trim() !== '' ? value : '-';
        }
    }

    function openModal(button) {
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
        setText('mCost', button.dataset.cost ? 'PHP ' + button.dataset.cost : '');
        setText('mNotes', button.dataset.notes || '');
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    openButtons.forEach((button) => {
        button.addEventListener('click', () => openModal(button));
    });

    closeBtn.addEventListener('click', closeModal);
    modalBackdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
</script>
</body>
</html>