<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';
$provider_nav_active = 'appointments';

require_once __DIR__ . '/provider_tenant_plan_and_site_context.inc.php';
require_once __DIR__ . '/provider_tenant_header_context.inc.php';

if (!function_exists('provider_tenant_dash_resolve_table')) {
    /**
     * @param array<int, string> $candidates
     */
    function provider_tenant_dash_resolve_table(PDO $pdo, array $candidates): string
    {
        foreach ($candidates as $n) {
            if (!is_string($n) || !preg_match('/^[a-z][a-z0-9_]{0,62}$/i', $n)) {
                continue;
            }
            try {
                $pdo->query('SELECT 1 FROM `' . $n . '` LIMIT 0');
                return $n;
            } catch (Throwable $e) {
            }
        }
        return '';
    }
}

function provider_tenant_table_has_column(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/i', $table) || !preg_match('/^[a-z][a-z0-9_]{0,62}$/i', $column)) {
        return false;
    }
    try {
        $q = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column));
        return $q !== false && $q->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return array{0: string, 1: string}
 */
function provider_tenant_appt_status_badge(string $raw): array
{
    $s = strtolower(trim($raw));
    return match ($s) {
        'pending' => ['bg-amber-100 text-amber-900', 'Pending'],
        'confirmed' => ['bg-primary/10 text-primary', 'Confirmed'],
        'completed' => ['bg-emerald-100 text-emerald-900', 'Completed'],
        'cancelled', 'canceled' => ['bg-slate-100 text-on-surface-variant', 'Cancelled'],
        'no_show' => ['bg-rose-100 text-rose-800', 'No-show'],
        default => ['bg-slate-100 text-on-surface-variant', $raw !== '' ? ucfirst($s) : 'Unknown'],
    };
}

$t_appts = provider_tenant_dash_resolve_table($pdo, ['appointments', 'tbl_appointments']);
$t_patients = provider_tenant_dash_resolve_table($pdo, ['patients', 'tbl_patients']);
$t_dentists = provider_tenant_dash_resolve_table($pdo, ['tbl_dentists', 'dentists']);

$has_dentist_col = $t_appts !== '' && provider_tenant_table_has_column($pdo, $t_appts, 'dentist_id');
$has_created_by = $t_appts !== '' && provider_tenant_table_has_column($pdo, $t_appts, 'created_by');
$has_service_type = $t_appts !== '' && provider_tenant_table_has_column($pdo, $t_appts, 'service_type');
$has_service_desc = $t_appts !== '' && provider_tenant_table_has_column($pdo, $t_appts, 'service_description');

$filter_status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$allowed_status = ['all', 'pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
if (!in_array($filter_status, $allowed_status, true)) {
    $filter_status = 'all';
}

$date_from = trim((string) ($_GET['date_from'] ?? ''));
$date_to = trim((string) ($_GET['date_to'] ?? ''));
if ($date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = '';
}
if ($date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = '';
}

$q_search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;

$appointments_rows = [];
$total_filtered = 0;
$total_all = 0;

if ($t_appts !== '') {
    try {
        $stc = $pdo->prepare("SELECT COUNT(*) FROM `{$t_appts}` WHERE tenant_id = ?");
        $stc->execute([$tenant_id]);
        $total_all = (int) $stc->fetchColumn();
    } catch (Throwable $e) {
        $total_all = 0;
    }

    $where = ['a.tenant_id = ?'];
    $params = [$tenant_id];

    if ($filter_status !== 'all') {
        if ($filter_status === 'cancelled') {
            $where[] = '(LOWER(TRIM(a.status)) IN (\'cancelled\',\'canceled\'))';
        } else {
            $where[] = 'LOWER(TRIM(a.status)) = ?';
            $params[] = $filter_status;
        }
    }

    if ($date_from !== '') {
        $where[] = 'a.appointment_date >= ?';
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where[] = 'a.appointment_date <= ?';
        $params[] = $date_to;
    }

    if ($q_search !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q_search) . '%';
        $searchParts = ['a.booking_id LIKE ?', 'a.patient_id LIKE ?'];
        $searchParams = [$like, $like];
        if ($t_patients !== '') {
            $searchParts[] = 'p.first_name LIKE ?';
            $searchParts[] = 'p.last_name LIKE ?';
            $searchParts[] = 'CONCAT(COALESCE(p.first_name,\'\'), \' \', COALESCE(p.last_name,\'\')) LIKE ?';
            $searchParams[] = $like;
            $searchParams[] = $like;
            $searchParams[] = $like;
        }
        if ($has_created_by) {
            $searchParts[] = 'u.full_name LIKE ?';
            $searchParams[] = $like;
        }
        if ($has_dentist_col && $t_dentists !== '') {
            $searchParts[] = 'd.first_name LIKE ?';
            $searchParts[] = 'd.last_name LIKE ?';
            $searchParts[] = 'CONCAT(COALESCE(d.first_name,\'\'), \' \', COALESCE(d.last_name,\'\')) LIKE ?';
            $searchParams[] = $like;
            $searchParams[] = $like;
            $searchParams[] = $like;
        }
        $where[] = '(' . implode(' OR ', $searchParts) . ')';
        $params = array_merge($params, $searchParams);
    }

    $where_sql = implode(' AND ', $where);

    $pjoin = $t_patients !== ''
        ? "LEFT JOIN `{$t_patients}` p ON a.patient_id = p.patient_id AND p.tenant_id = a.tenant_id"
        : '';
    $djoin = ($has_dentist_col && $t_dentists !== '')
        ? "LEFT JOIN `{$t_dentists}` d ON a.dentist_id = d.dentist_id AND d.tenant_id = a.tenant_id"
        : '';
    $ujoin = $has_created_by
        ? 'LEFT JOIN tbl_users u ON a.created_by = u.user_id'
        : '';

    $dentist_expr = ($has_dentist_col && $t_dentists !== '')
        ? "TRIM(CONCAT(COALESCE(d.first_name,''), ' ', COALESCE(d.last_name,'')))"
        : 'NULL';

    $service_expr = 'NULL';
    if ($has_service_type && $has_service_desc) {
        $service_expr = "NULLIF(TRIM(COALESCE(NULLIF(TRIM(a.service_type),''), LEFT(a.service_description, 120))), '')";
    } elseif ($has_service_type) {
        $service_expr = "NULLIF(TRIM(a.service_type), '')";
    } elseif ($has_service_desc) {
        $service_expr = 'LEFT(a.service_description, 120)';
    }

    try {
        $sqlCount = "
            SELECT COUNT(*) FROM `{$t_appts}` a
            {$pjoin}
            {$djoin}
            {$ujoin}
            WHERE {$where_sql}
        ";
        $stn = $pdo->prepare($sqlCount);
        $stn->execute($params);
        $total_filtered = (int) $stn->fetchColumn();
    } catch (Throwable $e) {
        $total_filtered = 0;
    }

    $total_pages = max(1, (int) ceil($total_filtered / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;

    try {
        $sqlList = "
            SELECT
                a.booking_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                a.status,
                {$service_expr} AS service_display,
                {$dentist_expr} AS dentist_display,
                " . ($has_created_by ? 'u.full_name AS booked_by_name' : 'NULL AS booked_by_name') . ",
                " . ($t_patients !== '' ? 'p.first_name AS pf, p.last_name AS pl, p.contact_number AS p_phone' : 'NULL AS pf, NULL AS pl, NULL AS p_phone') . "
            FROM `{$t_appts}` a
            {$pjoin}
            {$djoin}
            {$ujoin}
            WHERE {$where_sql}
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT " . (int) $per_page . ' OFFSET ' . (int) $offset . '
        ';
        $stl = $pdo->prepare($sqlList);
        $stl->execute($params);
        $appointments_rows = $stl->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($appointments_rows)) {
            $appointments_rows = [];
        }
    } catch (Throwable $e) {
        $appointments_rows = [];
    }
} else {
    $total_pages = 1;
}

$self_php = 'ProviderTenantAppointments.php';

function provider_tenant_appt_format_time(?string $t): string
{
    $t = trim((string) $t);
    if ($t === '') {
        return '—';
    }
    $ts = strtotime($t);
    if ($ts !== false) {
        return date('g:i A', $ts);
    }
    if (preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) {
        $h = (int) $m[1];
        $mm = $m[2];
        $ap = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12;
        if ($h12 === 0) {
            $h12 = 12;
        }
        return $h12 . ':' . $mm . ' ' . $ap;
    }
    return $t;
}

function provider_tenant_appt_format_date(?string $d): string
{
    $d = trim((string) $d);
    if ($d === '') {
        return '—';
    }
    $ts = strtotime($d);
    return $ts !== false ? date('M j, Y', $ts) : $d;
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Appointments</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-variant": "#f1f5f9",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#e2e8f0",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#475569",
                        "background": "#f8fafc",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#8e4a00",
                        "tertiary-container": "#ffdcc3",
                        "error": "#ba1a1a"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        html {
            scrollbar-gutter: stable;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .provider-nav-link:not(.provider-nav-link--active):hover {
            transform: translateX(4px);
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .provider-card-lift {
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .provider-card-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        @media (max-width: 1023.98px) {
            .provider-top-header {
                left: 0 !important;
                min-height: 5rem;
            }
            #provider-sidebar {
                transform: translateX(-100%);
                transition: transform 220ms ease;
                z-index: 60;
                background: #ffffff;
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
                border-right: 1px solid #e2e8f0;
            }
            body.provider-mobile-sidebar-open #provider-sidebar {
                transform: translateX(0);
            }
            #provider-mobile-sidebar-toggle {
                transition: left 220ms ease, background-color 220ms ease, color 220ms ease;
            }
            body.provider-mobile-sidebar-open #provider-mobile-sidebar-toggle {
                left: calc(16rem - 3.25rem);
                background: rgba(255, 255, 255, 0.98);
                color: #0066ff;
            }
            #provider-mobile-sidebar-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(19, 28, 37, 0.45);
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
                z-index: 55;
                opacity: 0;
                pointer-events: none;
                transition: opacity 220ms ease;
            }
            body.provider-mobile-sidebar-open #provider-mobile-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
        }
        body { font-family: 'Manrope', sans-serif; }
    </style>
</head>
<body class="mesh-bg font-body text-on-background min-h-screen selection:bg-primary/10">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<?php include __DIR__ . '/provider_tenant_top_header.inc.php'; ?>
<button id="provider-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="provider-sidebar" aria-expanded="false" aria-label="Open navigation menu">
<span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="provider-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<main class="ml-0 lg:ml-64 pt-[4.75rem] sm:pt-24 min-h-screen provider-page-enter">
<div class="pt-4 sm:pt-6 px-6 lg:px-10 pb-20 space-y-8">
<section class="flex flex-col gap-6">
<div class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> Tenant management</div>
<div class="flex flex-col xl:flex-row xl:justify-between xl:items-end gap-8">
<div>
<h2 class="font-headline font-extrabold tracking-tighter leading-tight text-on-background text-5xl sm:text-6xl">Appointment <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Journal</span></h2>
<p class="font-body text-xl font-medium text-slate-600 max-w-3xl leading-relaxed mt-6">A running ledger of patient visits, assigned clinicians, and session outcomes for this clinic.</p>
</div>
<div class="flex flex-wrap items-center gap-3 shrink-0">
<span class="material-symbols-outlined text-primary text-2xl">calendar_clock</span>
<div class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80">
<span class="text-slate-900"><?php echo (int) $total_filtered; ?></span> in view
<span class="text-on-surface-variant/50 mx-1">·</span>
<span class="text-slate-900"><?php echo (int) $total_all; ?></span> total stored
</div>
</div>
</div>
</div>
<form class="pt-8 border-t border-slate-100" method="get" action="<?php echo htmlspecialchars($self_php, ENT_QUOTES, 'UTF-8'); ?>">
<div class="rounded-2xl border border-slate-200 bg-white/80 p-4 sm:p-5">
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 sm:gap-4 items-end">
<div class="relative lg:col-span-2">
<label class="sr-only" for="filter-status">Status</label>
<select id="filter-status" name="status" class="w-full appearance-none bg-slate-50 border border-slate-200 rounded-2xl px-5 py-3 pr-11 text-on-background text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 cursor-pointer transition-all" onchange="this.form.submit()">
<option value="all"<?php echo $filter_status === 'all' ? ' selected' : ''; ?>>All statuses</option>
<option value="pending"<?php echo $filter_status === 'pending' ? ' selected' : ''; ?>>Pending</option>
<option value="confirmed"<?php echo $filter_status === 'confirmed' ? ' selected' : ''; ?>>Confirmed</option>
<option value="completed"<?php echo $filter_status === 'completed' ? ' selected' : ''; ?>>Completed</option>
<option value="cancelled"<?php echo $filter_status === 'cancelled' ? ' selected' : ''; ?>>Cancelled</option>
<option value="no_show"<?php echo $filter_status === 'no_show' ? ' selected' : ''; ?>>No-show</option>
</select>
<span class="material-symbols-outlined absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">tune</span>
</div>
<div class="lg:col-span-2">
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="date-from">From</label>
<input type="date" id="date-from" name="date_from" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
</div>
<div class="lg:col-span-2">
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="date-to">To</label>
<input type="date" id="date-to" name="date_to" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
</div>
<div class="sm:col-span-2 lg:col-span-4">
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="q-search">Search</label>
<div class="relative">
<span class="material-symbols-outlined pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/45 text-lg">search</span>
<input type="search" id="q-search" name="q" value="<?php echo htmlspecialchars($q_search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Patient, booking ID, doctor…" class="w-full rounded-2xl border border-slate-200 bg-white pl-11 pr-4 py-3 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
</div>
</div>
</div>
<div class="sm:col-span-2 lg:col-span-2 flex flex-col-reverse sm:flex-row lg:justify-end sm:items-center gap-2.5">
<?php if ($filter_status !== 'all' || $date_from !== '' || $date_to !== '' || $q_search !== '') { ?>
<a class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-[10px] font-black uppercase tracking-widest text-primary hover:border-primary/30 hover:bg-primary/5 transition-colors" href="<?php echo htmlspecialchars($self_php, ENT_QUOTES, 'UTF-8'); ?>">Reset</a>
<?php } ?>
<button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-primary text-white px-6 py-3 text-[10px] font-black uppercase tracking-widest whitespace-nowrap hover:shadow-lg hover:shadow-primary/25 transition-all active:scale-[0.98]">Apply filters</button>
</div>
</div>
</form>
</section>

<div class="elevated-card provider-card-lift rounded-3xl overflow-hidden">
<div class="md:hidden p-4 space-y-3">
<?php if ($t_appts === '') { ?>
<div class="rounded-2xl border border-dashed border-slate-200 p-6 text-sm text-on-surface-variant font-medium text-center">Appointment storage is not connected for this environment. Once your clinic database is linked, visits will appear here.</div>
<?php } elseif ($total_all === 0) { ?>
<div class="rounded-2xl border border-dashed border-slate-200 p-6 text-sm text-on-surface-variant font-medium text-center">No appointment records found for this clinic yet. Bookings from your clinic console will show up in this journal.</div>
<?php } elseif ($total_filtered === 0) { ?>
<div class="rounded-2xl border border-dashed border-slate-200 p-6 text-sm text-on-surface-variant font-medium text-center">No records match these filters. <a class="text-primary font-bold hover:underline" href="<?php echo htmlspecialchars($self_php, ENT_QUOTES, 'UTF-8'); ?>">Clear filters</a></div>
<?php } else { ?>
<?php foreach ($appointments_rows as $row) {
    $pf = trim((string) ($row['pf'] ?? ''));
    $pl = trim((string) ($row['pl'] ?? ''));
    $patient_line = trim($pf . ' ' . $pl);
    if ($patient_line === '') {
        $patient_line = trim((string) ($row['patient_id'] ?? '')) !== '' ? (string) $row['patient_id'] : '—';
    }
    $phone = trim((string) ($row['p_phone'] ?? ''));
    $dentistDisp = trim((string) ($row['dentist_display'] ?? ''));
    $bookedBy = trim((string) ($row['booked_by_name'] ?? ''));
    $practitioner = $dentistDisp !== '' ? $dentistDisp : ($bookedBy !== '' ? $bookedBy : '—');
    $service = trim((string) ($row['service_display'] ?? ''));
    if ($service === '') {
        $service = '—';
    }
    $statusRaw = (string) ($row['status'] ?? '');
    [$badgeClass, $statusLabel] = provider_tenant_appt_status_badge($statusRaw);
    ?>
<article class="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
<div class="flex items-start justify-between gap-3">
<div class="min-w-0">
<p class="font-headline font-extrabold text-slate-900 truncate"><?php echo htmlspecialchars($patient_line, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($phone !== '') { ?>
<p class="text-[11px] font-medium text-on-surface-variant/75 mt-0.5 truncate"><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>
<?php $bid = trim((string) ($row['booking_id'] ?? '')); if ($bid !== '') { ?>
<p class="text-[10px] font-black uppercase tracking-wider text-primary/80 mt-1"><?php echo htmlspecialchars($bid, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>
</div>
<span class="<?php echo $badgeClass; ?> text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest inline-block shrink-0"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Date</p>
<p class="text-xs font-semibold text-on-surface-variant mt-1"><?php echo htmlspecialchars(provider_tenant_appt_format_date(isset($row['appointment_date']) ? (string) $row['appointment_date'] : null), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Time</p>
<p class="text-xs font-semibold text-on-surface-variant mt-1"><?php echo htmlspecialchars(provider_tenant_appt_format_time(isset($row['appointment_time']) ? (string) $row['appointment_time'] : null), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
</div>
<div>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Practitioner</p>
<p class="text-sm font-semibold text-slate-800 mt-1"><?php echo htmlspecialchars($practitioner, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($dentistDisp !== '' && $bookedBy !== '' && strcasecmp($dentistDisp, $bookedBy) !== 0) { ?>
<p class="text-[10px] font-semibold text-on-surface-variant/65 mt-1">Booked by <?php echo htmlspecialchars($bookedBy, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>
</div>
<div>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Service type</p>
<p class="text-sm font-medium text-on-surface-variant leading-snug mt-1"><?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
</article>
<?php } ?>
<?php } ?>
</div>
<div class="hidden md:block overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50 border-b border-slate-100">
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Patient details</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Practitioner</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Service type</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Date</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Time slot</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Session status</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<?php if ($t_appts === '') { ?>
<tr>
<td colspan="6" class="px-10 py-16 text-center text-on-surface-variant font-medium">Appointment storage is not connected for this environment. Once your clinic database is linked, visits will appear here.</td>
</tr>
<?php } elseif ($total_all === 0) { ?>
<tr>
<td colspan="6" class="px-10 py-16 text-center text-on-surface-variant font-medium">No appointment records found for this clinic yet. Bookings from your clinic console will show up in this journal.</td>
</tr>
<?php } elseif ($total_filtered === 0) { ?>
<tr>
<td colspan="6" class="px-10 py-16 text-center text-on-surface-variant font-medium">No records match these filters. <a class="text-primary font-bold hover:underline" href="<?php echo htmlspecialchars($self_php, ENT_QUOTES, 'UTF-8'); ?>">Clear filters</a></td>
</tr>
<?php } else { ?>
<?php foreach ($appointments_rows as $row) {
    $pf = trim((string) ($row['pf'] ?? ''));
    $pl = trim((string) ($row['pl'] ?? ''));
    $patient_line = trim($pf . ' ' . $pl);
    if ($patient_line === '') {
        $patient_line = trim((string) ($row['patient_id'] ?? '')) !== '' ? (string) $row['patient_id'] : '—';
    }
    $phone = trim((string) ($row['p_phone'] ?? ''));
    $dentistDisp = trim((string) ($row['dentist_display'] ?? ''));
    $bookedBy = trim((string) ($row['booked_by_name'] ?? ''));
    $practitioner = $dentistDisp !== '' ? $dentistDisp : ($bookedBy !== '' ? $bookedBy : '—');
    $service = trim((string) ($row['service_display'] ?? ''));
    if ($service === '') {
        $service = '—';
    }
    $statusRaw = (string) ($row['status'] ?? '');
    [$badgeClass, $statusLabel] = provider_tenant_appt_status_badge($statusRaw);
    ?>
<tr class="group hover:bg-slate-50/50 transition-colors duration-200">
<td class="px-10 py-8">
<div class="font-headline font-extrabold text-slate-900"><?php echo htmlspecialchars($patient_line, ENT_QUOTES, 'UTF-8'); ?></div>
<?php if ($phone !== '') { ?>
<div class="text-[11px] font-medium text-on-surface-variant/70 mt-0.5"><?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
<?php $bid = trim((string) ($row['booking_id'] ?? '')); if ($bid !== '') { ?>
<div class="text-[10px] font-black uppercase tracking-wider text-primary/80 mt-1"><?php echo htmlspecialchars($bid, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
</td>
<td class="px-10 py-8">
<div class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($practitioner, ENT_QUOTES, 'UTF-8'); ?></div>
<?php if ($dentistDisp !== '' && $bookedBy !== '' && strcasecmp($dentistDisp, $bookedBy) !== 0) { ?>
<div class="text-[10px] font-semibold text-on-surface-variant/65 mt-1">Booked by <?php echo htmlspecialchars($bookedBy, ENT_QUOTES, 'UTF-8'); ?></div>
<?php } ?>
</td>
<td class="px-10 py-8">
<p class="text-sm font-medium text-on-surface-variant leading-snug max-w-xs"><?php echo htmlspecialchars($service, ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-10 py-8">
<div class="text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest"><?php echo htmlspecialchars(provider_tenant_appt_format_date(isset($row['appointment_date']) ? (string) $row['appointment_date'] : null), ENT_QUOTES, 'UTF-8'); ?></div>
</td>
<td class="px-10 py-8">
<div class="text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest"><?php echo htmlspecialchars(provider_tenant_appt_format_time(isset($row['appointment_time']) ? (string) $row['appointment_time'] : null), ENT_QUOTES, 'UTF-8'); ?></div>
</td>
<td class="px-10 py-8">
<span class="<?php echo $badgeClass; ?> text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest inline-block"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</td>
</tr>
<?php } ?>
<?php } ?>
</tbody>
</table>
</div>
<?php if ($t_appts !== '' && $total_filtered > 0) { ?>
<div class="px-10 py-6 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center justify-between gap-4">
<p class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant/70">
Page <span class="text-slate-900"><?php echo (int) $page; ?></span> of <span class="text-slate-900"><?php echo (int) $total_pages; ?></span>
</p>
<div class="flex flex-wrap gap-2">
<?php
    $qs = static function (int $p) use ($filter_status, $date_from, $date_to, $q_search, $self_php): string {
        $q = ['page' => (string) $p];
        if ($filter_status !== 'all') {
            $q['status'] = $filter_status;
        }
        if ($date_from !== '') {
            $q['date_from'] = $date_from;
        }
        if ($date_to !== '') {
            $q['date_to'] = $date_to;
        }
        if ($q_search !== '') {
            $q['q'] = $q_search;
        }
        return $self_php . '?' . http_build_query($q);
    };
?>
<?php if ($page > 1) { ?>
<a class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-on-background hover:border-primary/30 hover:text-primary transition-colors" href="<?php echo htmlspecialchars($qs($page - 1), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
<?php } ?>
<?php if ($page < $total_pages) { ?>
<a class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-[10px] font-black uppercase tracking-widest text-on-background hover:border-primary/30 hover:text-primary transition-colors" href="<?php echo htmlspecialchars($qs($page + 1), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
<?php } ?>
</div>
</div>
<?php } ?>
</div>

<footer class="mt-auto p-8 hidden lg:flex justify-center sticky bottom-0 z-10 pointer-events-none">
<div class="elevated-card pointer-events-auto px-10 py-4 rounded-full border border-slate-200/50 shadow-2xl flex items-center gap-10 text-[10px] font-black text-on-surface-variant/70 uppercase tracking-[0.2em]">
<div class="flex items-center gap-3 text-primary">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                Appointment ledger
            </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">sync</span>
                Synced with clinic data
            </div>
</div>
</footer>
</div>
</main>
<?php include __DIR__ . '/provider_tenant_profile_modal.inc.php'; ?>
<script>
(function () {
  var body = document.body;
  var sidebar = document.getElementById('provider-sidebar');
  var mobileToggle = document.getElementById('provider-mobile-sidebar-toggle');
  var mobileBackdrop = document.getElementById('provider-mobile-sidebar-backdrop');
  var desktopQuery = window.matchMedia('(min-width: 1024px)');

  if (!body || !sidebar || !mobileToggle || !mobileBackdrop) {
    return;
  }

  function setMobileSidebar(open) {
    body.classList.toggle('provider-mobile-sidebar-open', open);
    mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    mobileToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    var icon = mobileToggle.querySelector('.material-symbols-outlined');
    if (icon) {
      icon.textContent = open ? 'close' : 'menu';
    }
  }

  function closeOnDesktop() {
    if (desktopQuery.matches) {
      setMobileSidebar(false);
    }
  }

  mobileToggle.addEventListener('click', function () {
    setMobileSidebar(!body.classList.contains('provider-mobile-sidebar-open'));
  });
  mobileBackdrop.addEventListener('click', function () {
    setMobileSidebar(false);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && body.classList.contains('provider-mobile-sidebar-open')) {
      setMobileSidebar(false);
    }
  });
  sidebar.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      if (!desktopQuery.matches) {
        setMobileSidebar(false);
      }
    });
  });
  if (typeof desktopQuery.addEventListener === 'function') {
    desktopQuery.addEventListener('change', closeOnDesktop);
  } else if (typeof desktopQuery.addListener === 'function') {
    desktopQuery.addListener(closeOnDesktop);
  }

  setMobileSidebar(false);
})();
</script>
</body></html>
