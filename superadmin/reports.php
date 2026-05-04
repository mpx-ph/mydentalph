<?php require_once __DIR__ . '/require_superadmin.php'; ?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Reports | Clinical Precision</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0066ff",
                        "on-surface": "#131c25",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "error": "#ba1a1a",
                        "surface-container-low": "#edf4ff",
                        "surface-container-high": "#e0e9f6",
                        "surface-container-highest": "#dae3f0",
                        "background": "#f7f9ff",
                        "tertiary": "#8e4a00",
                        "error-container": "#ffdad6",
                    },
                    fontFamily: {
                        "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "3xl": "1.5rem", "full": "9999px" },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .editorial-shadow {
            box-shadow: 0 12px 40px -10px rgba(19, 28, 37, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(0, 102, 255, 0.3);
        }
        .primary-glow {
            box-shadow: 0 8px 25px -5px rgba(0, 102, 255, 0.4);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image: 
                radial-gradient(at 0% 0%, hsla(210,100%,98%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(217,100%,94%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(210,100%,98%,1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .js-paginated-panel {
            position: relative;
            transition: opacity 180ms ease, transform 180ms ease;
            will-change: opacity, transform;
        }
        .js-paginated-panel.tm-loading {
            opacity: 0.68;
            transform: translateY(4px);
            pointer-events: none;
        }
        .js-paginated-panel.tm-loading::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(
                110deg,
                rgba(255, 255, 255, 0) 15%,
                rgba(255, 255, 255, 0.38) 45%,
                rgba(255, 255, 255, 0) 75%
            );
            animation: tm-shimmer 1s linear infinite;
            pointer-events: none;
        }
        @keyframes tm-shimmer {
            from { transform: translateX(-35%); }
            to { transform: translateX(35%); }
        }
        @media (prefers-reduced-motion: reduce) {
            .js-paginated-panel,
            .js-paginated-panel.tm-loading {
                transition: none;
                transform: none;
            }
            .js-paginated-panel.tm-loading::after {
                animation: none;
            }
        }
        .export-modal-backdrop {
            background: rgba(19, 28, 37, 0.35);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .export-modal-panel {
            background: rgba(255, 255, 255, 0.92);
            color: #131c25;
            border: 1px solid rgba(224, 233, 246, 0.85);
            box-shadow: 0 30px 80px -25px rgba(19, 28, 37, 0.25);
        }
        .export-option-card {
            background: rgba(237, 244, 255, 0.7);
            border: 1px solid rgba(192, 199, 212, 0.45);
        }
        @media (max-width: 1023px) {
            #superadmin-sidebar {
                transform: translateX(-100%);
                transition: transform 220ms ease;
                z-index: 60;
            }
            body.sa-mobile-sidebar-open #superadmin-sidebar {
                transform: translateX(0);
            }
            .sa-top-header {
                left: 0;
                width: 100% !important;
                padding-left: 5.5rem;
                padding-right: 1rem;
            }
            #sa-mobile-sidebar-toggle {
                top: 1rem;
                left: 0.75rem;
                width: 2.75rem;
                height: 2.75rem;
                transition: left 220ms ease, background-color 220ms ease, color 220ms ease;
            }
            body.sa-mobile-sidebar-open #sa-mobile-sidebar-toggle {
                left: calc(16rem - 3.25rem);
                background: rgba(255, 255, 255, 0.98);
                color: #0066ff;
            }
            #sa-mobile-sidebar-backdrop {
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
            body.sa-mobile-sidebar-open #sa-mobile-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface antialiased min-h-screen">
<?php
$superadmin_nav = 'reports';
$superadmin_header_center = '';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';

// Superadmin reports: Manila wall clock + MySQL session +08:00 (see try { SET time_zone }).
@date_default_timezone_set('Asia/Manila');

/**
 * Human-friendly datetime for small dashboard tables.
 */
function reports_format_datetime_for_table($dateTime): string
{
    if (empty($dateTime)) {
        return '—';
    }
    $ts = strtotime((string) $dateTime);
    return $ts ? date('M j, Y g:i A', $ts) : (string) $dateTime;
}

function reports_format_date_for_table($date): string
{
    if (empty($date)) {
        return '—';
    }
    $ts = strtotime((string) $date);
    return $ts ? date('M j, Y', $ts) : (string) $date;
}

/**
 * Date range using MySQL CURDATE()/NOW() after SET time_zone = '+08:00' so "today" matches Philippines calendar.
 *
 * @return array{start:string,end:string,label:string,end_inclusive:bool}
 */
function reports_mysql_period_range(PDO $pdo, string $period, ?string $dateFrom, ?string $dateTo): array
{
    $period = strtolower(trim($period));
    $allowed = ['today', 'yesterday', 'week', 'month', 'year', 'custom'];
    if (!in_array($period, $allowed, true)) {
        $period = 'yesterday';
    }

    if ($period === 'today') {
        $row = $pdo->query("
            SELECT CONCAT(CURDATE(), ' 00:00:00') AS s,
                   DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS e
        ")->fetch(PDO::FETCH_ASSOC);
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => 'Today (live)',
            'end_inclusive' => true,
        ];
    }

    if ($period === 'yesterday') {
        $row = $pdo->query("
            SELECT CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 00:00:00') AS s,
                   CONCAT(CURDATE(), ' 00:00:00') AS e
        ")->fetch(PDO::FETCH_ASSOC);
        $d = $pdo->query('SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), \'%Y-%m-%d\')')->fetchColumn();
        $label = $d ? date('M j, Y', strtotime((string) $d . ' 12:00:00')) : 'Yesterday';
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => $label,
            'end_inclusive' => false,
        ];
    }

    if ($period === 'week') {
        $row = $pdo->query("
            SELECT
                CONCAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), ' 00:00:00') AS s,
                LEAST(
                    NOW(),
                    DATE_ADD(CONCAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), ' 00:00:00'), INTERVAL 7 DAY)
                ) AS e
        ")->fetch(PDO::FETCH_ASSOC);
        $ds = $pdo->query("SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), '%Y-%m-%d')")->fetchColumn();
        $de = $pdo->query("
            SELECT DATE_FORMAT(
                LEAST(
                    NOW(),
                    DATE_ADD(CONCAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), ' 00:00:00'), INTERVAL 7 DAY)
                ),
                '%Y-%m-%d'
            )
        ")->fetchColumn();
        $label = 'This week · ' . date('M j', strtotime((string) $ds . ' 12:00:00'))
            . ' – ' . date('M j, Y', strtotime((string) $de . ' 12:00:00'));
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => $label,
            'end_inclusive' => true,
        ];
    }

    if ($period === 'month') {
        $row = $pdo->query("
            SELECT
                CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m-01'), ' 00:00:00') AS s,
                LEAST(
                    NOW(),
                    DATE_ADD(CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m-01'), ' 00:00:00'), INTERVAL 1 MONTH)
                ) AS e
        ")->fetch(PDO::FETCH_ASSOC);
        $m = $pdo->query("SELECT DATE_FORMAT(CURDATE(), '%M %Y')")->fetchColumn();
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => 'This month · ' . (string) $m,
            'end_inclusive' => true,
        ];
    }

    if ($period === 'year') {
        $row = $pdo->query("
            SELECT
                CONCAT(YEAR(CURDATE()), '-01-01 00:00:00') AS s,
                LEAST(
                    NOW(),
                    DATE_ADD(CONCAT(YEAR(CURDATE()), '-01-01 00:00:00'), INTERVAL 1 YEAR)
                ) AS e
        ")->fetch(PDO::FETCH_ASSOC);
        $y = $pdo->query('SELECT YEAR(CURDATE())')->fetchColumn();
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => 'This year · ' . (string) $y,
            'end_inclusive' => true,
        ];
    }

    // custom — calendar days in MySQL session (matches how CURDATE() works)
    $okFrom = $dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom);
    $okTo = $dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo);
    if (!$okFrom || !$okTo) {
        $row = $pdo->query("
            SELECT CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 00:00:00') AS s,
                   CONCAT(CURDATE(), ' 00:00:00') AS e
        ")->fetch(PDO::FETCH_ASSOC);
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => 'Custom · yesterday (set From and To)',
            'end_inclusive' => false,
        ];
    }
    $df = $dateFrom;
    $dt = $dateTo;
    if ($df > $dt) {
        $tmp = $df;
        $df = $dt;
        $dt = $tmp;
    }
    $stmt = $pdo->prepare('SELECT CONCAT(?, \' 00:00:00\') AS s, DATE_ADD(CONCAT(?, \' 00:00:00\'), INTERVAL 1 DAY) AS e');
    $stmt->execute([$df, $dt]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $label = 'Custom · ' . date('M j, Y', strtotime($df . ' 12:00:00')) . ' – ' . date('M j, Y', strtotime($dt . ' 12:00:00'));
    return [
        'start' => (string) ($row['s'] ?? ''),
        'end' => (string) ($row['e'] ?? ''),
        'label' => $label,
        'end_inclusive' => false,
    ];
}

/**
 * Build datetime predicate for range queries.
 */
function reports_datetime_predicate(string $column, bool $endInclusive): string
{
    if ($endInclusive) {
        return "{$column} >= ? AND {$column} <= ?";
    }
    return "{$column} >= ? AND {$column} < ?";
}

$dbError = null;
$totalMyDentalVisits = 0;
$userRegistrationsTotal = 0;
$registrationRows = [];
$tenantsList = [];
$filterPeriod = isset($_GET['period']) ? (string) $_GET['period'] : 'yesterday';
$filterDateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$filterDateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$filterClinicId = isset($_GET['clinic']) ? trim((string) $_GET['clinic']) : '';
$filterRegSearch = isset($_GET['reg_q']) ? trim((string) $_GET['reg_q']) : '';
$filterClinicLabel = 'All clinics';
$periodLabel = '—';
$reportsFormAction = htmlspecialchars(basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'reports.php'), ENT_QUOTES, 'UTF-8');
$registrationsPerPage = 10;
$registrationsPage = filter_var($_GET['reg_page'] ?? null, FILTER_VALIDATE_INT);
if ($registrationsPage === false || $registrationsPage < 1) {
    $registrationsPage = 1;
}
$registrationsTotalPages = 1;
$registrationOffset = 0;
$registrationRowsCount = 0;
$registrationRangeStart = 0;
$registrationRangeEnd = 0;

try {
    try {
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        // If hosting disallows SET time_zone, CURDATE()/NOW() stay server-default; Manila PHP still formats display.
    }

    $tenantsStmt = $pdo->query('SELECT tenant_id, clinic_name FROM tbl_tenants ORDER BY clinic_name ASC');
    $tenantsList = $tenantsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (strtolower($filterPeriod) === 'custom' && $filterDateFrom === '' && $filterDateTo === '') {
        $filterDateFrom = (string) $pdo->query("SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%Y-%m-%d')")->fetchColumn();
        $filterDateTo = $filterDateFrom;
    }

    $range = reports_mysql_period_range(
        $pdo,
        $filterPeriod,
        $filterDateFrom !== '' ? $filterDateFrom : null,
        $filterDateTo !== '' ? $filterDateTo : null
    );
    $startStr = $range['start'];
    $endStr = $range['end'];
    $periodLabel = $range['label'];
    $endInclusive = !empty($range['end_inclusive']);

    if ($filterClinicId !== '') {
        $found = false;
        foreach ($tenantsList as $t) {
            if ((string) ($t['tenant_id'] ?? '') === $filterClinicId) {
                $filterClinicLabel = (string) $t['clinic_name'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $filterClinicId = '';
            $filterClinicLabel = 'All clinics';
        }
    }

    $visitPred = reports_datetime_predicate('created_at', $endInclusive);
    $visitParams = [$startStr, $endStr];
    $visitSql = "
        SELECT COUNT(DISTINCT ip_address) AS cnt
        FROM tbl_website_visits
        WHERE {$visitPred}
          AND ip_address IS NOT NULL
          AND TRIM(ip_address) <> ''
    ";
    if ($filterClinicId !== '') {
        $visitSql .= ' AND tenant_id = ?';
        $visitParams[] = $filterClinicId;
    }
    try {
        $stmt = $pdo->prepare($visitSql);
        $stmt->execute($visitParams);
        $totalMyDentalVisits = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    } catch (Throwable $e) {
        error_log('superadmin/reports.php tbl_website_visits: ' . $e->getMessage());
        $totalMyDentalVisits = 0;
    }

    $regParams = [$startStr, $endStr];
    $regPred = reports_datetime_predicate('u.created_at', $endInclusive);
    $regWhere = "WHERE {$regPred}";
    if ($filterClinicId !== '') {
        $regWhere .= ' AND u.tenant_id = ?';
        $regParams[] = $filterClinicId;
    }
    if ($filterRegSearch !== '') {
        $likeBody = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filterRegSearch);
        $likeTerm = '%' . $likeBody . '%';
        $regWhere .= " AND (
            COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) LIKE ? ESCAPE '!'
            OR u.email LIKE ? ESCAPE '!'
            OR COALESCE(t.clinic_name, '') LIKE ? ESCAPE '!'
        )";
        $regParams[] = $likeTerm;
        $regParams[] = $likeTerm;
        $regParams[] = $likeTerm;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM tbl_users u LEFT JOIN tbl_tenants t ON t.tenant_id = u.tenant_id {$regWhere}");
    $stmt->execute($regParams);
    $userRegistrationsTotal = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    $registrationsTotalPages = $userRegistrationsTotal > 0 ? max(1, (int) ceil($userRegistrationsTotal / $registrationsPerPage)) : 1;
    if ($registrationsPage > $registrationsTotalPages) {
        $registrationsPage = $registrationsTotalPages;
    }
    $registrationOffset = ($registrationsPage - 1) * $registrationsPerPage;

    $sqlReg = "
        SELECT
            DATE(u.created_at) AS created_date,
            t.clinic_name AS tenant_name,
            COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS user_name,
            u.email AS user_email,
            COALESCE(u.last_active, u.last_login) AS last_active_at
        FROM tbl_users u
        LEFT JOIN tbl_tenants t ON t.tenant_id = u.tenant_id
        {$regWhere}
        ORDER BY u.created_at DESC
        LIMIT {$registrationsPerPage} OFFSET {$registrationOffset}
    ";
    try {
        $stmt = $pdo->prepare($sqlReg);
        $stmt->execute($regParams);
        $registrationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $sqlFallback = str_replace(
            'COALESCE(u.last_active, u.last_login)',
            'u.last_login',
            $sqlReg
        );
        $stmt = $pdo->prepare($sqlFallback);
        $stmt->execute($regParams);
        $registrationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $dbError = 'Unable to load reports.';
    error_log('superadmin/reports.php: ' . $e->getMessage());
}

$registrationRowsCount = count($registrationRows);
$registrationRangeStart = $registrationRowsCount > 0 ? ($registrationOffset + 1) : 0;
$registrationRangeEnd = $registrationRowsCount > 0 ? ($registrationOffset + $registrationRowsCount) : 0;

$reportsQueryParams = [];
foreach ($_GET as $k => $v) {
    if ($k === 'reg_page') {
        continue;
    }
    $reportsQueryParams[$k] = $v;
}
if ($registrationsPage > 1) {
    $reportsQueryParams['reg_page'] = (string) $registrationsPage;
}

function reports_pagination_url(int $page, array $baseParams): string
{
    $params = $baseParams;
    if ($page <= 1) {
        unset($params['reg_page']);
    } else {
        $params['reg_page'] = (string) $page;
    }
    $q = http_build_query($params);
    $script = $_SERVER['SCRIPT_NAME'] ?? ('/' . basename(__FILE__));
    return htmlspecialchars($script, ENT_QUOTES, 'UTF-8') . ($q !== '' ? '?' . $q : '');
}

$periodOptions = [
    'today' => 'Today (live)',
    'yesterday' => 'Yesterday',
    'week' => 'This week',
    'month' => 'This month',
    'year' => 'This year',
    'custom' => 'Custom date range',
];
$isCustomPeriod = (strtolower($filterPeriod) === 'custom');
?>
<button id="sa-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="superadmin-sidebar" aria-expanded="false" aria-label="Open navigation menu">
<span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="sa-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<!-- Main Content Area -->
<main class="ml-0 lg:ml-64 pt-20 min-h-screen">
<div class="pt-6 sm:pt-8 px-4 sm:px-6 lg:px-10 pb-12 sm:pb-16 space-y-8 sm:space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Header Section -->
<section class="flex flex-col gap-4">
<div>
<h2 class="text-3xl sm:text-4xl font-extrabold font-headline tracking-tight text-on-surface">Reports</h2>
<p class="text-on-surface-variant mt-2 font-medium">View registration activity for the selected period</p>
<div class="relative w-full max-w-md group mt-4">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-xl pointer-events-none">search</span>
<input form="reportsFilters" name="reg_q" id="reportsRegSearch" value="<?php echo htmlspecialchars($filterRegSearch, ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm transition-all placeholder:text-on-surface-variant/50" placeholder="Search name, email, or clinic..." type="search" autocomplete="off"/>
</div>
<p class="text-on-surface-variant text-xs font-medium mt-2">Press Enter or Apply filters to search the registration table.</p>
</div>
</section>
<!-- Filters (summary cards + table use the same range and clinic) -->
<section class="bg-white/70 backdrop-blur-xl rounded-[2rem] editorial-shadow border border-white/60 px-4 sm:px-6 lg:px-8 py-6">
<form method="get" action="<?php echo $reportsFormAction; ?>" id="reportsFilters" class="js-reports-filter-form flex flex-col gap-4">
<div class="flex flex-col xl:flex-row xl:flex-wrap xl:items-end gap-4">
<div class="flex flex-col gap-1.5 min-w-[200px]">
<label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70" for="reportsPeriod">Date period</label>
<select name="period" id="reportsPeriod" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-5 pr-10 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<?php foreach ($periodOptions as $val => $lab): ?>
<option value="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"<?php echo strtolower($filterPeriod) === $val ? ' selected' : ''; ?>><?php echo htmlspecialchars($lab, ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
</div>
<div id="reportsCustomDates" class="flex flex-wrap items-end gap-3 <?php echo $isCustomPeriod ? '' : 'hidden'; ?>">
<div class="flex flex-col gap-1.5">
<label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70" for="reportsDateFrom">From</label>
<input type="date" name="date_from" id="reportsDateFrom" value="<?php echo htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8'); ?>" class="bg-surface-container-low/50 border-none rounded-xl px-4 py-2.5 text-sm font-bold text-on-surface focus:ring-2 focus:ring-primary/20"/>
</div>
<div class="flex flex-col gap-1.5">
<label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70" for="reportsDateTo">To</label>
<input type="date" name="date_to" id="reportsDateTo" value="<?php echo htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8'); ?>" class="bg-surface-container-low/50 border-none rounded-xl px-4 py-2.5 text-sm font-bold text-on-surface focus:ring-2 focus:ring-primary/20"/>
</div>
</div>
<div class="flex flex-col gap-1.5 min-w-[220px] flex-1 max-w-md">
<label class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70" for="reportsClinic">Clinic</label>
<select name="clinic" id="reportsClinic" class="w-full appearance-none bg-surface-container-low/50 border-none rounded-xl px-5 pr-10 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option value=""<?php echo $filterClinicId === '' ? ' selected' : ''; ?>>All clinics</option>
<?php foreach ($tenantsList as $t): ?>
<option value="<?php echo htmlspecialchars((string) ($t['tenant_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filterClinicId === (string) ($t['tenant_id'] ?? '') ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($t['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="flex items-end pb-0.5">
<button type="submit" class="bg-primary text-white px-6 py-2.5 rounded-xl text-sm font-bold primary-glow hover:brightness-110 transition-all">Apply filters</button>
</div>
</div>
<p class="text-on-surface-variant text-xs font-medium">
<?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?>
<span class="opacity-60"> · </span>
<?php echo htmlspecialchars($filterClinicLabel, ENT_QUOTES, 'UTF-8'); ?>
</p>
</form>
</section>
<!-- Metrics Grid -->
<section class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">article</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total mydental Visits</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo htmlspecialchars(number_format($totalMyDentalVisits), ENT_QUOTES, 'UTF-8'); ?></h3>
<p class="text-on-surface-variant text-xs font-medium mt-2">
    <?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($filterClinicLabel, ENT_QUOTES, 'UTF-8'); ?>
</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-primary/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-primary/10 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">person_add</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">User Registration</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo htmlspecialchars(number_format($userRegistrationsTotal), ENT_QUOTES, 'UTF-8'); ?></h3>
<p class="text-on-surface-variant text-xs font-medium mt-2">
    <?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($filterClinicLabel, ENT_QUOTES, 'UTF-8'); ?>
</p>
</div>
</section>
<script>
(function () {
  var sel = document.getElementById('reportsPeriod');
  var box = document.getElementById('reportsCustomDates');
  if (!sel || !box) return;
  function sync() { box.classList.toggle('hidden', sel.value !== 'custom'); }
  sel.addEventListener('change', sync);
  sync();
})();
(function () {
  var inp = document.getElementById('reportsRegSearch');
  var frm = document.getElementById('reportsFilters');
  if (!inp || !frm || typeof frm.requestSubmit !== 'function') return;
  inp.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      frm.requestSubmit();
    }
  });
})();
</script>
<!-- Export Buttons -->
<div class="flex flex-wrap items-center gap-3">
<button type="button" id="open-reports-export-modal" class="px-6 py-2.5 bg-white/60 text-primary text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center justify-center gap-2 w-full sm:w-auto">
<span class="material-symbols-outlined text-lg">picture_as_pdf</span> Export PDF
                </button>
</div>
<!-- Table Container -->
<div id="registrations-panel" class="js-paginated-panel bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<!-- Table header (same filters as summary cards — use form above) -->
<div class="px-4 sm:px-6 lg:px-8 py-6 flex flex-wrap items-center justify-between gap-4 border-b border-white/50">
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60 max-w-xl">
    Same period and clinic as above · <?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($filterClinicLabel, ENT_QUOTES, 'UTF-8'); ?>
</div>
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
    Showing <span class="text-primary opacity-100"><?php echo htmlspecialchars($registrationRowsCount > 0 ? ((string) $registrationRangeStart . '-' . (string) $registrationRangeEnd) : '0', ENT_QUOTES, 'UTF-8'); ?></span>
    of <?php echo htmlspecialchars(number_format($userRegistrationsTotal), ENT_QUOTES, 'UTF-8'); ?> registrations
</div>
</div>
<!-- Table Content -->
<div class="md:hidden px-4 sm:px-6 py-5 space-y-4">
<?php if (!empty($registrationRows)): ?>
<?php foreach ($registrationRows as $row): ?>
<article class="rounded-2xl bg-white/80 border border-white/70 shadow-sm p-4 space-y-4">
    <div>
        <p class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant/70">Date</p>
        <p class="mt-1 text-sm font-semibold text-on-surface-variant"><?php echo htmlspecialchars(reports_format_date_for_table($row['created_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div>
        <p class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant/70">Tenant Name</p>
        <p class="mt-1 text-sm font-semibold text-on-surface"><?php echo htmlspecialchars((string) ($row['tenant_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="grid grid-cols-1 gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant/70">User Name</p>
            <p class="mt-1 text-sm font-semibold text-on-surface-variant"><?php echo htmlspecialchars((string) ($row['user_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <div>
            <p class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant/70">User Email</p>
            <p class="mt-1 text-sm font-semibold text-on-surface-variant break-all"><?php echo htmlspecialchars((string) ($row['user_email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
    <div>
        <p class="text-[10px] uppercase tracking-wide font-bold text-on-surface-variant/70">Last Active</p>
        <p class="mt-1 text-xs font-medium text-on-surface-variant"><?php echo htmlspecialchars(reports_format_datetime_for_table($row['last_active_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
</article>
<?php endforeach; ?>
<?php else: ?>
<div class="rounded-2xl border border-outline-variant/20 bg-white/70 px-4 py-5 text-sm text-on-surface-variant font-medium text-center">
    <?php if ($dbError): ?>
        <?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?>
    <?php else: ?>
        No registrations for this period.
    <?php endif; ?>
</div>
<?php endif; ?>
</div>
<div class="hidden md:block overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-8 py-5">Date</th>
<th class="px-10 py-5">Tenant Name</th>
<th class="px-10 py-5">User Name</th>
<th class="px-10 py-5">User Email</th>
<th class="px-10 py-5">Last Active</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if (!empty($registrationRows)): ?>
<?php foreach ($registrationRows as $row): ?>
<tr class="hover:bg-primary/5 transition-colors group">
    <td class="px-8 py-5 text-xs font-medium text-on-surface-variant">
        <?php echo htmlspecialchars(reports_format_date_for_table($row['created_date'] ?? null), ENT_QUOTES, 'UTF-8'); ?>
    </td>
    <td class="px-10 py-5">
        <span class="text-sm font-semibold text-on-surface-variant"><?php echo htmlspecialchars((string) ($row['tenant_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
    </td>
    <td class="px-10 py-5">
        <span class="text-sm font-semibold text-on-surface-variant"><?php echo htmlspecialchars((string) ($row['user_name'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
    </td>
    <td class="px-10 py-5">
        <span class="text-sm font-semibold text-on-surface-variant break-words"><?php echo htmlspecialchars((string) ($row['user_email'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
    </td>
    <td class="px-10 py-5 text-xs font-medium text-on-surface-variant">
        <?php echo htmlspecialchars(reports_format_datetime_for_table($row['last_active_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?>
    </td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
    <td colspan="5" class="px-8 py-10 text-center text-on-surface-variant/70 text-sm font-bold">
        <?php if ($dbError): ?>
            <?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?>
        <?php else: ?>
            No registrations for this period.
        <?php endif; ?>
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="px-4 sm:px-6 lg:px-8 py-5 border-t border-white/50 flex flex-wrap items-center justify-between gap-4">
<?php if ($registrationsPage > 1): ?>
<a href="<?php echo reports_pagination_url($registrationsPage - 1, $reportsQueryParams); ?>" class="js-registration-pagination-link px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
</a>
<?php else: ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant text-sm font-bold rounded-xl border border-white/60 shadow-sm flex items-center gap-2 opacity-40 cursor-not-allowed">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
</span>
<?php endif; ?>
<p class="text-sm font-bold text-on-surface order-first sm:order-none w-full sm:w-auto text-center sm:text-left">Page <?php echo (int) $registrationsPage; ?> of <?php echo (int) $registrationsTotalPages; ?></p>
<?php if ($registrationsPage < $registrationsTotalPages): ?>
<a href="<?php echo reports_pagination_url($registrationsPage + 1, $reportsQueryParams); ?>" class="js-registration-pagination-link px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
Next <span class="material-symbols-outlined text-lg">chevron_right</span>
</a>
<?php else: ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant text-sm font-bold rounded-xl border border-white/60 shadow-sm flex items-center gap-2 opacity-40 cursor-not-allowed">
Next <span class="material-symbols-outlined text-lg">chevron_right</span>
</span>
<?php endif; ?>
</div>
</div>
</div>
</main>
<!-- Reports PDF Export Modal -->
<div id="reports-export-modal" class="fixed inset-0 z-[70] hidden export-modal-backdrop items-center justify-center p-4 sm:p-8">
<div class="export-modal-panel w-full max-w-3xl rounded-[2rem] overflow-hidden">
<div class="px-8 py-6 border-b border-outline-variant/40 flex items-start justify-between gap-4">
<div>
<h3 class="text-2xl font-extrabold tracking-tight">
<span class="text-on-surface">Export</span> <span class="text-primary">Options</span>
</h3>
<p class="mt-2 text-xs font-bold uppercase tracking-[0.2em] text-on-surface-variant/60">Select sections to include in your PDF</p>
</div>
<button id="close-reports-export-modal" type="button" class="w-14 h-14 rounded-2xl bg-surface-container-low hover:bg-white transition-colors flex items-center justify-center text-on-surface-variant">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form action="reports_export_pdf.php" method="get" target="_blank" class="max-h-[70vh] overflow-y-auto p-8 space-y-7">
<input type="hidden" name="period" value="<?php echo htmlspecialchars(strtolower($filterPeriod), ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom, ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filterDateTo, ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="clinic" value="<?php echo htmlspecialchars($filterClinicId, ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="reg_q" value="<?php echo htmlspecialchars($filterRegSearch, ENT_QUOTES, 'UTF-8'); ?>"/>
<div>
<h4 class="text-sm font-bold uppercase tracking-[0.16em] text-on-surface-variant/70 mb-4">Report sections</h4>
<div class="space-y-4">
<label class="export-option-card rounded-3xl p-5 flex items-center justify-between gap-3 cursor-pointer">
<span class="flex items-center gap-3">
<input type="hidden" name="include_visits_metric" value="0"/>
<input type="checkbox" name="include_visits_metric" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Total mydental visits</span>
</span>
<span class="text-primary font-black"><?php echo htmlspecialchars(number_format($totalMyDentalVisits), ENT_QUOTES, 'UTF-8'); ?></span>
</label>
<label class="export-option-card rounded-3xl p-5 flex items-center justify-between gap-3 cursor-pointer">
<span class="flex items-center gap-3">
<input type="hidden" name="include_registrations_metric" value="0"/>
<input type="checkbox" name="include_registrations_metric" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">User registrations (count)</span>
</span>
<span class="text-primary font-black"><?php echo htmlspecialchars(number_format($userRegistrationsTotal), ENT_QUOTES, 'UTF-8'); ?></span>
</label>
<label class="export-option-card rounded-3xl p-5 flex items-center gap-3 cursor-pointer">
<input type="hidden" name="include_registration_table" value="0"/>
<input type="checkbox" name="include_registration_table" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">User registrations table <span class="text-on-surface-variant font-semibold text-sm">(full list for the current filters)</span></span>
</label>
</div>
</div>
<p class="text-xs text-on-surface-variant font-medium">Uses the same date period and clinic as the filters above (apply filters first if needed).</p>
<div class="pt-2 flex justify-end gap-3">
<button type="button" id="cancel-reports-export-modal" class="px-6 py-3 rounded-2xl text-sm font-bold text-on-surface-variant bg-surface-container-low hover:bg-white transition-colors">Cancel</button>
<button type="submit" class="px-7 py-3 rounded-2xl text-sm font-bold text-white bg-primary hover:brightness-110 transition-colors">Preview PDF</button>
</div>
</form>
</div>
</div>
<script>
(function () {
    var toggleBtn = document.getElementById('sa-mobile-sidebar-toggle');
    var backdrop = document.getElementById('sa-mobile-sidebar-backdrop');
    var mqDesktop = window.matchMedia('(min-width: 1024px)');
    if (!toggleBtn || !backdrop) return;

    function setOpen(isOpen) {
        document.body.classList.toggle('sa-mobile-sidebar-open', isOpen);
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggleBtn.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        var icon = toggleBtn.querySelector('.material-symbols-outlined');
        if (icon) icon.textContent = isOpen ? 'close' : 'menu';
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    toggleBtn.addEventListener('click', function () {
        setOpen(!document.body.classList.contains('sa-mobile-sidebar-open'));
    });
    backdrop.addEventListener('click', function () {
        setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sa-mobile-sidebar-open')) {
            setOpen(false);
        }
    });

    function closeOnDesktop() {
        if (mqDesktop.matches) setOpen(false);
    }
    if (typeof mqDesktop.addEventListener === 'function') {
        mqDesktop.addEventListener('change', closeOnDesktop);
    } else if (typeof mqDesktop.addListener === 'function') {
        mqDesktop.addListener(closeOnDesktop);
    }
})();
</script>
<script>
(function () {
    if (!window.fetch || !window.DOMParser) return;

    function setupPanelPagination(panelId, linkClass, options) {
        var panel = document.getElementById(panelId);
        if (!panel) return;
        var isLoading = false;
        var config = options || {};
        var resetPageParam = config.resetPageParam || 'reg_page';

        function loadPage(url, pushState) {
            if (isLoading) return;
            isLoading = true;
            panel.classList.add('tm-loading');
            panel.setAttribute('aria-busy', 'true');

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) { return res.text(); })
                .then(function (html) {
                    var parser = new DOMParser();
                    var nextDoc = parser.parseFromString(html, 'text/html');
                    var nextPanel = nextDoc.getElementById(panelId);
                    if (!nextPanel) {
                        throw new Error('Panel not found');
                    }
                    panel.style.opacity = '0';
                    panel.style.transform = 'translateY(6px)';
                    window.requestAnimationFrame(function () {
                        panel.innerHTML = nextPanel.innerHTML;
                        panel.style.opacity = '';
                        panel.style.transform = '';
                        var panelTop = panel.getBoundingClientRect().top + window.pageYOffset;
                        window.scrollTo({
                            top: Math.max(0, panelTop - 96),
                            behavior: 'smooth'
                        });
                    });
                    if (pushState && window.history && typeof window.history.pushState === 'function') {
                        var state = {};
                        state[panelId] = url;
                        window.history.pushState(state, '', url);
                    }
                })
                .catch(function () {
                    window.location.href = url;
                })
                .finally(function () {
                    isLoading = false;
                    panel.classList.remove('tm-loading');
                    panel.removeAttribute('aria-busy');
                });
        }

        document.addEventListener('click', function (e) {
            var raw = e.target;
            var origin = raw && raw.nodeType === 1
                ? raw
                : (raw && raw.parentElement && raw.parentElement.nodeType === 1 ? raw.parentElement : null);
            if (!origin || typeof origin.closest !== 'function') return;
            var link = origin.closest('.' + linkClass);
            if (!link || !panel.contains(link)) return;
            e.preventDefault();
            loadPage(link.href, true);
        });

        if (config.filterFormSelector) {
            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!form || !form.matches(config.filterFormSelector)) return;
                e.preventDefault();
                var action = form.getAttribute('action') || window.location.pathname;
                var method = (form.getAttribute('method') || 'get').toLowerCase();
                if (method !== 'get') {
                    form.submit();
                    return;
                }
                var formData = new FormData(form);
                formData.delete(resetPageParam);
                var params = new URLSearchParams(formData);
                var nextUrl = action + (params.toString() ? ('?' + params.toString()) : '');
                loadPage(nextUrl, true);
            });
        }

        window.addEventListener('popstate', function () {
            loadPage(window.location.href, false);
        });
    }

    setupPanelPagination('registrations-panel', 'js-registration-pagination-link', {
        filterFormSelector: '.js-reports-filter-form',
        resetPageParam: 'reg_page'
    });
})();
</script>
<script>
(function () {
    var openBtn = document.getElementById('open-reports-export-modal');
    var closeBtn = document.getElementById('close-reports-export-modal');
    var cancelBtn = document.getElementById('cancel-reports-export-modal');
    var modal = document.getElementById('reports-export-modal');
    if (!openBtn || !modal) return;
    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
    openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
})();
</script>
</body></html>