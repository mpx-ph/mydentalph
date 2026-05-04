<?php
$staff_nav_active = 'reports';
require_once __DIR__ . '/config/config.php';
// Dentist role restriction: redirect to dashboard
if (session_status() === PHP_SESSION_NONE) { clinic_session_start(); }
if (isset($_SESSION['user_role']) && strtolower(trim((string) $_SESSION['user_role'])) === 'dentist') {
    header('Location: StaffDashboard.php');
    exit;
}
if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}

/**
 * @param float $amount
 */
function staff_reports_money($amount)
{
    return '₱' . number_format((float) $amount, 2, '.', ',');
}

/**
 * @param string|null $input
 * @return string|null
 */
function staff_reports_parse_date($input)
{
    if ($input === null) {
        return null;
    }
    $trim = trim((string) $input);
    if ($trim === '') {
        return null;
    }
    $ts = strtotime($trim);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d', $ts);
}

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$filterQ = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filterDateFrom = staff_reports_parse_date(isset($_GET['date_from']) ? (string) $_GET['date_from'] : null);
$filterDateTo = staff_reports_parse_date(isset($_GET['date_to']) ? (string) $_GET['date_to'] : null);
if ($filterDateFrom !== null && $filterDateTo !== null && $filterDateFrom > $filterDateTo) {
    $tmp = $filterDateFrom;
    $filterDateFrom = $filterDateTo;
    $filterDateTo = $tmp;
}
$filterStatus = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
$allowedAppointmentStatus = ['', 'completed', 'confirmed', 'pending', 'cancelled', 'no_show'];
if ($filterStatus !== '' && !in_array($filterStatus, $allowedAppointmentStatus, true)) {
    $filterStatus = '';
}
$filterDentistId = isset($_GET['dentist_id']) ? trim((string) $_GET['dentist_id']) : '';
if ($filterDentistId !== '' && !ctype_digit($filterDentistId)) {
    $filterDentistId = '';
}

$reportClinicName = '';
if (isset($_SESSION['clinic_name']) && trim((string) $_SESSION['clinic_name']) !== '') {
    $reportClinicName = trim((string) $_SESSION['clinic_name']);
} elseif ($currentTenantSlug !== '') {
    $reportClinicName = ucwords(str_replace('-', ' ', (string) $currentTenantSlug));
}

$totalRevenue = 0.0;
$totalAppointments = 0;
$totalPatients = 0;
$allAppointments = [];
$dentistsList = [];
$reportsRowCap = 500;

try {
    $pdo = getDBConnection();

    if ($tenantId === '' && $currentTenantSlug !== '') {
        $tenantStmt = $pdo->prepare('SELECT tenant_id, clinic_name FROM tbl_tenants WHERE clinic_slug = ? LIMIT 1');
        $tenantStmt->execute([$currentTenantSlug]);
        $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenantRow && isset($tenantRow['tenant_id'])) {
            $tenantId = (string) $tenantRow['tenant_id'];
            if ($reportClinicName === '' && !empty($tenantRow['clinic_name'])) {
                $reportClinicName = trim((string) $tenantRow['clinic_name']);
            }
        }
    }

    if ($tenantId !== '') {
        $baseFrom = '
            FROM tbl_appointments a
            LEFT JOIN tbl_patients p
                ON p.tenant_id = a.tenant_id
               AND p.patient_id = a.patient_id
            LEFT JOIN tbl_dentists d
                ON d.tenant_id = a.tenant_id
               AND d.dentist_id = a.dentist_id
        ';
        $whereParts = ['a.tenant_id = ?'];
        $params = [$tenantId];
        if ($filterDateFrom !== null) {
            $whereParts[] = 'a.appointment_date >= ?';
            $params[] = $filterDateFrom;
        }
        if ($filterDateTo !== null) {
            $whereParts[] = 'a.appointment_date <= ?';
            $params[] = $filterDateTo;
        }
        if ($filterStatus !== '') {
            $whereParts[] = 'LOWER(TRIM(COALESCE(a.status, \'\'))) = ?';
            $params[] = $filterStatus;
        }
        if ($filterDentistId !== '') {
            $whereParts[] = 'a.dentist_id = ?';
            $params[] = $filterDentistId;
        }
        if ($filterQ !== '') {
            $likeTerm = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filterQ) . '%';
            $whereParts[] = '(
                CONCAT(COALESCE(p.first_name, \'\'), \' \', COALESCE(p.last_name, \'\')) LIKE ?
                OR COALESCE(a.service_type, \'\') LIKE ?
                OR COALESCE(a.service_description, \'\') LIKE ?
                OR CONCAT(COALESCE(d.first_name, \'\'), \' \', COALESCE(d.last_name, \'\')) LIKE ?
            )';
            $params[] = $likeTerm;
            $params[] = $likeTerm;
            $params[] = $likeTerm;
            $params[] = $likeTerm;
        }
        $whereSql = 'WHERE ' . implode(' AND ', $whereParts);

        $revWhereParts = $whereParts;
        $revParams = $params;
        $revWhereParts[] = 'LOWER(TRIM(COALESCE(a.status, \'\'))) = \'completed\'';
        $revWhereSql = 'WHERE ' . implode(' AND ', $revWhereParts);

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(a.total_treatment_cost), 0) AS total_revenue ' . $baseFrom . $revWhereSql);
        $stmt->execute($revParams);
        $totalRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total_appointments ' . $baseFrom . $whereSql);
        $stmt->execute($params);
        $totalAppointments = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_appointments'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT a.patient_id) AS total_patients ' . $baseFrom . $whereSql . ' AND a.patient_id IS NOT NULL AND TRIM(COALESCE(a.patient_id, \'\')) <> \'\'');
        $stmt->execute($params);
        $totalPatients = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_patients'] ?? 0);

        $dentStmt = $pdo->prepare('SELECT dentist_id, first_name, last_name FROM tbl_dentists WHERE tenant_id = ? ORDER BY last_name ASC, first_name ASC');
        $dentStmt->execute([$tenantId]);
        $dentistsList = $dentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $appointmentsSql = "
            SELECT
                a.booking_id,
                a.appointment_date,
                a.appointment_time,
                a.service_type,
                a.service_description,
                a.status,
                a.total_treatment_cost,
                p.patient_id,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                d.first_name AS dentist_first_name,
                d.last_name AS dentist_last_name
            " . $baseFrom . $whereSql . '
            ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC
            LIMIT ' . (int) $reportsRowCap . '
        ';
        $stmt = $pdo->prepare($appointmentsSql);
        $stmt->execute($params);
        $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    error_log('Staff reports load error: ' . $e->getMessage());
}

$filterSummaryParts = [];
if ($filterDateFrom !== null || $filterDateTo !== null) {
    $filterSummaryParts[] = 'Dates: '
        . ($filterDateFrom !== null ? date('M j, Y', strtotime($filterDateFrom)) : '…')
        . ' – '
        . ($filterDateTo !== null ? date('M j, Y', strtotime($filterDateTo)) : '…');
}
if ($filterStatus !== '') {
    $filterSummaryParts[] = 'Status: ' . ucfirst(str_replace('_', ' ', $filterStatus));
}
if ($filterDentistId !== '') {
    $dn = '';
    foreach ($dentistsList as $dr) {
        if ((string) ($dr['dentist_id'] ?? '') === $filterDentistId) {
            $dn = trim((string) ($dr['first_name'] ?? '') . ' ' . (string) ($dr['last_name'] ?? ''));
            break;
        }
    }
    $filterSummaryParts[] = 'Staff: ' . ($dn !== '' ? $dn : ('#' . $filterDentistId));
}
if ($filterQ !== '') {
    $filterSummaryParts[] = 'Search: "' . $filterQ . '"';
}
$filterSummaryText = $filterSummaryParts !== [] ? implode(' · ', $filterSummaryParts) : 'All appointments (no filters)';
$generatedReportLine = date('M j, Y \a\t h:i A');
$hasActiveFilters = ($filterQ !== '' || $filterDateFrom !== null || $filterDateTo !== null || $filterStatus !== '' || $filterDentistId !== '');
$reportsPageBase = basename(isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : 'StaffReports.php');
$reportsResetHref = htmlspecialchars($reportsPageBase . ($currentTenantSlug !== '' ? ('?clinic_slug=' . rawurlencode($currentTenantSlug)) : ''), ENT_QUOTES, 'UTF-8');
$reportsTableTruncated = $tenantId !== '' && $totalAppointments > count($allAppointments);
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Reports - Aetheris Dental Systems</title>
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
                        "on-surface-variant": "#404752",
                        "surface-container-low": "#edf4ff",
                        "outline-variant": "#cbd5e1"
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
                    },
                },
            },
        }
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
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .staff-reports-print-header {
            display: none;
        }
        @page {
            margin: 14mm 16mm;
        }
        @media print {
            body {
                background: #fff !important;
            }
            .mesh-bg {
                background: #fff !important;
                background-image: none !important;
            }
            #staff-sidebar,
            #staff-sidebar-toggle,
            header[data-purpose="top-header"],
            .staff-reports-no-print {
                display: none !important;
            }
            main {
                margin-left: 0 !important;
                padding-top: 0 !important;
            }
            main > div {
                padding: 0 !important;
            }
            .provider-page-enter {
                animation: none;
            }
            .elevated-card {
                box-shadow: none !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 10px !important;
                break-inside: avoid;
            }
            .elevated-card:hover {
                transform: none !important;
            }
            .staff-reports-print-header {
                display: block !important;
                margin-bottom: 20px;
                padding-bottom: 16px;
                border-bottom: 1px solid #e2e8f0;
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            .staff-reports-print-header h1 {
                margin: 0 0 4px 0;
                font-size: 1.5rem;
                font-weight: 800;
                letter-spacing: -0.02em;
                color: #0f172a;
            }
            .staff-reports-print-header .staff-reports-print-sub {
                margin: 0 0 12px 0;
                font-size: 10px;
                font-weight: 600;
                letter-spacing: 0.14em;
                text-transform: uppercase;
                color: #94a3b8;
            }
            .staff-reports-print-header .staff-reports-print-filters {
                margin: 0;
                font-size: 12px;
                color: #475569;
                line-height: 1.45;
            }
            .staff-reports-print-table thead th {
                font-size: 10px;
                letter-spacing: 0.08em;
                color: #475569 !important;
                border-bottom: 1px solid #cbd5e1 !important;
            }
            .staff-reports-print-table tbody td {
                border-bottom: 1px solid #e2e8f0 !important;
                color: #111827 !important;
            }
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter print:ml-0 print:pt-4">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<div class="p-10 space-y-8 print:p-0">
<div class="staff-reports-print-header">
<h1><?php echo htmlspecialchars($reportClinicName !== '' ? $reportClinicName : 'Clinic', ENT_QUOTES, 'UTF-8'); ?> <span style="color:#0d9488;font-weight:700">Staff reports</span></h1>
<p class="staff-reports-print-sub">Generated on <?php echo htmlspecialchars(strtoupper($generatedReportLine), ENT_QUOTES, 'UTF-8'); ?></p>
<p class="staff-reports-print-filters"><?php echo htmlspecialchars($filterSummaryText, ENT_QUOTES, 'UTF-8'); ?></p>
</div>

<section class="flex flex-col gap-4 mb-2 staff-reports-no-print">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> CLINICAL PRECISION
            </div>
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Reports <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Overview</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                    Track appointments, service delivery, and revenue in one place.
                </p>
</div>
</section>

<form method="get" action="<?php echo htmlspecialchars($reportsPageBase, ENT_QUOTES, 'UTF-8'); ?>" id="staff-reports-filter-form" class="staff-reports-no-print space-y-8">
<?php if ($currentTenantSlug !== ''): ?>
<input type="hidden" name="clinic_slug" value="<?php echo htmlspecialchars($currentTenantSlug, ENT_QUOTES, 'UTF-8'); ?>"/>
<?php endif; ?>
<section class="elevated-card p-8 rounded-3xl">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
<div class="lg:col-span-2">
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2" for="staff-reports-q">Search</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">search</span>
<input id="staff-reports-q" name="q" class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Patient, service, or staff" type="search" autocomplete="off" value="<?php echo htmlspecialchars($filterQ, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Print</label>
<button type="button" id="staff-reports-print-btn" class="w-full bg-white border-2 border-primary/25 hover:border-primary/50 hover:bg-surface-container-low text-primary px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-sm">print</span>
                        Print preview
                    </button>
</div>
</div>
</section>

<section class="elevated-card p-8 rounded-3xl">
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2" for="staff-reports-date-from">From date</label>
<input id="staff-reports-date-from" name="date_from" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" type="date" value="<?php echo $filterDateFrom !== null ? htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8') : ''; ?>"/>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2" for="staff-reports-date-to">To date</label>
<input id="staff-reports-date-to" name="date_to" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" type="date" value="<?php echo $filterDateTo !== null ? htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8') : ''; ?>"/>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2" for="staff-reports-status">Status</label>
<select id="staff-reports-status" name="status" onchange="if(this.form)this.form.submit()" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option value=""<?php echo $filterStatus === '' ? ' selected' : ''; ?>>All statuses</option>
<option value="completed"<?php echo $filterStatus === 'completed' ? ' selected' : ''; ?>>Completed</option>
<option value="confirmed"<?php echo $filterStatus === 'confirmed' ? ' selected' : ''; ?>>Confirmed</option>
<option value="pending"<?php echo $filterStatus === 'pending' ? ' selected' : ''; ?>>Pending</option>
<option value="cancelled"<?php echo $filterStatus === 'cancelled' ? ' selected' : ''; ?>>Cancelled</option>
<option value="no_show"<?php echo $filterStatus === 'no_show' ? ' selected' : ''; ?>>No show</option>
</select>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2" for="staff-reports-dentist">Assigned staff</label>
<select id="staff-reports-dentist" name="dentist_id" onchange="if(this.form)this.form.submit()" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option value=""<?php echo $filterDentistId === '' ? ' selected' : ''; ?>>All staff</option>
<?php foreach ($dentistsList as $dent): ?>
<?php
    $did = (string) ($dent['dentist_id'] ?? '');
    $dname = trim((string) ($dent['first_name'] ?? '') . ' ' . (string) ($dent['last_name'] ?? ''));
?>
<option value="<?php echo htmlspecialchars($did, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filterDentistId === $did ? ' selected' : ''; ?>><?php echo htmlspecialchars($dname !== '' ? $dname : ('Staff #' . $did), ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="flex flex-wrap items-center gap-3 mt-6">
<button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary text-white px-5 py-2.5 text-xs font-bold uppercase tracking-wide shadow-lg shadow-primary/25 hover:bg-primary/90 transition-all">Apply filters</button>
<a href="<?php echo $reportsResetHref; ?>" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-xs font-bold uppercase tracking-wide text-on-surface-variant hover:bg-slate-50 transition-all">
<span class="material-symbols-outlined text-base">restart_alt</span> Reset</a>
</div>
</section>
</form>

<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest"><?php echo $hasActiveFilters ? 'Filtered' : 'Completed'; ?></span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo staff_reports_money($totalRevenue); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Treatment total (completed)</p>
</div>
</div>

<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_available</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest"><?php echo $hasActiveFilters ? 'Filtered' : 'All'; ?></span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo number_format($totalAppointments); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Appointments</p>
</div>
</div>

<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">group</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest"><?php echo $hasActiveFilters ? 'Filtered' : 'Distinct'; ?></span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo number_format($totalPatients); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Patients in view</p>
</div>
</div>
</section>

<section class="elevated-card rounded-3xl overflow-hidden staff-reports-print-table-wrap">
<div class="px-8 py-6 border-b border-slate-100 bg-white">
<h3 class="text-2xl font-bold font-headline text-on-background">All appointments</h3>
<?php if ($reportsTableTruncated): ?>
<p class="text-xs text-slate-500 font-medium mt-1">Showing the first <?php echo number_format(count($allAppointments)); ?> of <?php echo number_format($totalAppointments); ?> matching rows. Narrow filters for a shorter list.</p>
<?php endif; ?>
</div>
<div class="overflow-x-auto">
<table class="staff-reports-print-table w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Details</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Appointment Info (Date and Time)</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Treatment/Service (Details)</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Assigned Staff</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<?php if (empty($allAppointments)): ?>
<tr>
<td class="px-8 py-8 text-sm font-semibold text-slate-500" colspan="6"><?php
if ($tenantId === '') {
    echo 'Sign in and select a clinic to load reports.';
} elseif ($hasActiveFilters) {
    echo 'No appointments match your filters.';
} else {
    echo 'No appointments found for this clinic yet.';
}
?></td>
</tr>
<?php else: ?>
<?php foreach ($allAppointments as $appointment): ?>
<?php
    $patientName = trim(((string) ($appointment['patient_first_name'] ?? '')) . ' ' . ((string) ($appointment['patient_last_name'] ?? '')));
    if ($patientName === '') {
        $patientName = 'Unknown Patient';
    }
    $patientId = trim((string) ($appointment['patient_id'] ?? ''));
    $patientInitials = strtoupper(substr(trim((string) ($appointment['patient_first_name'] ?? 'U')), 0, 1) . substr(trim((string) ($appointment['patient_last_name'] ?? 'P')), 0, 1));
    if ($patientInitials === '') {
        $patientInitials = 'NA';
    }
    $dateLabel = '-';
    if (!empty($appointment['appointment_date'])) {
        $dateLabel = date('M d, Y', strtotime((string) $appointment['appointment_date']));
    }
    $timeLabel = '-';
    if (!empty($appointment['appointment_time'])) {
        $timeLabel = date('h:i A', strtotime((string) $appointment['appointment_time']));
    }
    $serviceMain = trim((string) ($appointment['service_type'] ?? ''));
    if ($serviceMain === '') {
        $serviceMain = 'General Consultation';
    }
    $serviceDetails = trim((string) ($appointment['service_description'] ?? ''));
    $serviceLabel = $serviceDetails !== '' ? ($serviceMain . ' - ' . $serviceDetails) : $serviceMain;
    $dentistName = trim(((string) ($appointment['dentist_first_name'] ?? '')) . ' ' . ((string) ($appointment['dentist_last_name'] ?? '')));
    if ($dentistName === '') {
        $dentistName = 'Unassigned Dentist';
    }
    $status = strtolower(trim((string) ($appointment['status'] ?? 'pending')));
    $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Pending';
    $statusClasses = 'bg-amber-50 text-amber-600';
    $dotClass = 'bg-amber-500';
    if ($status === 'completed') {
        $statusClasses = 'bg-emerald-50 text-emerald-600';
        $dotClass = 'bg-emerald-500';
    } elseif ($status === 'confirmed') {
        $statusClasses = 'bg-primary/10 text-primary';
        $dotClass = 'bg-primary';
    } elseif ($status === 'cancelled' || $status === 'no_show') {
        $statusClasses = 'bg-slate-100 text-slate-600';
        $dotClass = 'bg-slate-500';
    }
    $amount = (float) ($appointment['total_treatment_cost'] ?? 0);
?>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center font-black text-primary text-xs"><?php echo htmlspecialchars($patientInitials, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5"><?php echo $patientId !== '' ? 'ID: #' . htmlspecialchars($patientId, ENT_QUOTES, 'UTF-8') : 'ID: N/A'; ?></span>
</div>
</div>
</td>
<td class="px-6 py-6">
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-0.5"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</td>
<td class="px-6 py-6 text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($serviceLabel, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-6 py-6 text-sm font-medium text-slate-700"><?php echo htmlspecialchars($dentistName, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 <?php echo $statusClasses; ?> text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full <?php echo $dotClass; ?>"></span>
<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
</span>
</td>
<td class="px-8 py-6 text-right text-sm font-extrabold text-slate-900"><?php echo staff_reports_money($amount); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
</div>
<script>
(function () {
    var btn = document.getElementById('staff-reports-print-btn');
    if (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    }
})();
</script>
</main>
</body></html>
