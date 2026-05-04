<?php
declare(strict_types=1);
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

function subscriptions_build_query(array $base, array $overrides = []): string
{
    $merged = array_merge($base, $overrides);
    $parts = [];
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        if ($k === 'page' && (int) $v === 1) {
            continue;
        }
        $parts[$k] = $v;
    }

    return http_build_query($parts);
}

function subscriptions_url(array $base, array $overrides = []): string
{
    $q = subscriptions_build_query($base, $overrides);

    return $q === '' ? 'subscriptions.php' : ('subscriptions.php?' . $q);
}

function subscriptions_parse_date(?string $input): ?string
{
    if ($input === null) {
        return null;
    }
    $trim = trim($input);
    if ($trim === '') {
        return null;
    }
    $ts = strtotime($trim);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d', $ts);
}

function subscriptions_money(?float $amount): string
{
    if ($amount === null) {
        return '—';
    }

    return '₱' . number_format($amount, 2, '.', ',');
}

function subscriptions_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    $sub = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $up = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        $a = $sub($parts[0], 0, 1);
        $b = $sub($parts[count($parts) - 1], 0, 1);

        return $up($a . $b);
    }

    return $up($sub($name, 0, min(2, $len)));
}

/**
 * Canonical subscription lifecycle label for tenant + subscription row.
 *
 * @return 'active'|'expired'|'cancelled'|'suspended'
 */
function subscriptions_derive_display_status(array $row): string
{
    $tenantSt = strtolower(trim((string) ($row['tenant_subscription_status'] ?? '')));
    $paySt = strtolower(trim((string) ($row['payment_status'] ?? '')));
    $endRaw = trim((string) ($row['subscription_end'] ?? ''));
    $endPassed = false;
    if ($endRaw !== '') {
        $endTs = strtotime($endRaw);
        $today = strtotime(date('Y-m-d'));
        if ($endTs !== false && $today !== false && $endTs < $today) {
            $endPassed = true;
        }
    }
    if ($tenantSt === 'suspended') {
        return 'suspended';
    }
    if ($paySt === 'cancelled') {
        return 'cancelled';
    }
    if (
        $tenantSt === 'active'
        && $paySt === 'paid'
        && ($endRaw === '' || !$endPassed)
    ) {
        return 'active';
    }

    return 'expired';
}

function subscriptions_status_badge(string $displayStatus): string
{
    switch ($displayStatus) {
        case 'active':
            return '<span class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-[10px] font-bold uppercase tracking-wider">Active</span>';
        case 'expired':
            return '<span class="px-3 py-1.5 bg-amber-50 text-amber-700 rounded-xl text-[10px] font-bold uppercase tracking-wider">Expired</span>';
        case 'cancelled':
            return '<span class="px-3 py-1.5 bg-rose-50 text-rose-700 rounded-xl text-[10px] font-bold uppercase tracking-wider">Cancelled</span>';
        case 'suspended':
            return '<span class="px-3 py-1.5 bg-error-container/20 text-error rounded-xl text-[10px] font-bold uppercase tracking-wider">Suspended</span>';
        default:
            return '<span class="px-3 py-1.5 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-bold uppercase tracking-wider">' . htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

function subscriptions_format_date_disp(?string $d): string
{
    if ($d === null || trim($d) === '') {
        return '—';
    }
    $t = strtotime($d);
    if ($t === false) {
        return '—';
    }

    return date('M j, Y', $t);
}

$page = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 12;

$searchQ = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filterClinic = isset($_GET['clinic']) ? trim((string) $_GET['clinic']) : '';
$filterPlan = isset($_GET['plan']) ? trim((string) $_GET['plan']) : '';
$filterStatus = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
$allowedStatus = ['', 'active', 'expired', 'cancelled', 'suspended'];
if ($filterStatus !== '' && !in_array($filterStatus, $allowedStatus, true)) {
    $filterStatus = '';
}

$filterDateFrom = subscriptions_parse_date(isset($_GET['date_from']) ? (string) $_GET['date_from'] : null);
$filterDateTo = subscriptions_parse_date(isset($_GET['date_to']) ? (string) $_GET['date_to'] : null);
if ($filterDateFrom !== null && $filterDateTo !== null && $filterDateFrom > $filterDateTo) {
    $tmp = $filterDateFrom;
    $filterDateFrom = $filterDateTo;
    $filterDateTo = $tmp;
}

$filterBase = [
    'q' => $searchQ,
    'clinic' => $filterClinic,
    'plan' => $filterPlan,
    'status' => $filterStatus,
    'date_from' => $filterDateFrom !== null ? $filterDateFrom : '',
    'date_to' => $filterDateTo !== null ? $filterDateTo : '',
];

$dbError = null;
$plans = [];
$clinics = [];
$subsRows = [];
$totalRows = 0;
$totalPages = 1;
$rangeStart = 0;
$rangeEnd = 0;
$metricTotalRevenue = 0.0;
$metricActivePlans = 0;
$metricPendingOverdue = 0;

$sqlStatusFragment = <<<SQL
(
    (? = '')
    OR
    (? = 'suspended' AND t.subscription_status = 'suspended')
    OR
    (? = 'cancelled' AND ts.payment_status = 'cancelled')
    OR
    (
        ? = 'active'
        AND t.subscription_status = 'active'
        AND ts.payment_status = 'paid'
        AND (ts.subscription_end IS NULL OR ts.subscription_end >= CURDATE())
    )
    OR
    (
        ? = 'expired'
        AND t.subscription_status <> 'suspended'
        AND ts.payment_status <> 'cancelled'
        AND NOT (
            t.subscription_status = 'active'
            AND ts.payment_status = 'paid'
            AND (ts.subscription_end IS NULL OR ts.subscription_end >= CURDATE())
        )
    )
)
SQL;
$sqlStatusFragment = preg_replace('/\s+/', ' ', trim($sqlStatusFragment));

try {
    $plans = $pdo->query('SELECT plan_id, plan_name FROM tbl_subscription_plans ORDER BY plan_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $clinicsStmt = $pdo->query('SELECT tenant_id, clinic_name FROM tbl_tenants ORDER BY clinic_name ASC');
    $clinics = $clinicsStmt ? ($clinicsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $where = ['1=1'];
    $params = [];

    if ($filterClinic !== '') {
        if (strlen($filterClinic) <= 20) {
            $where[] = 'ts.tenant_id = ?';
            $params[] = $filterClinic;
        }
    }

    if ($filterPlan !== '' && ctype_digit($filterPlan)) {
        $where[] = 'ts.plan_id = ?';
        $params[] = (int) $filterPlan;
    }

    if ($filterDateFrom !== null) {
        $where[] = 'DATE(COALESCE(ts.subscription_start, ts.created_at)) >= ?';
        $params[] = $filterDateFrom;
    }
    if ($filterDateTo !== null) {
        $where[] = 'DATE(COALESCE(ts.subscription_start, ts.created_at)) <= ?';
        $params[] = $filterDateTo;
    }

    if ($searchQ !== '') {
        $like = '%' . $searchQ . '%';
        $where[] = '(t.clinic_name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where[] = $sqlStatusFragment;
    for ($__i = 0; $__i < 5; $__i++) {
        $params[] = $filterStatus;
    }

    $whereSql = implode(' AND ', $where);

    /** @var array<int, mixed> $countParams */
    $countParams = $params;

    $countSql = "
        SELECT COUNT(*)
        FROM tbl_tenant_subscriptions ts
        INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id
        LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
        WHERE {$whereSql}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRows = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $metricsSql = "
        SELECT
            COALESCE(SUM(CASE WHEN ts.payment_status = 'paid' THEN COALESCE(ts.amount_paid, 0) ELSE 0 END), 0) AS total_revenue,
            SUM(
                CASE
                    WHEN (
                        t.subscription_status = 'active'
                        AND ts.payment_status = 'paid'
                        AND (ts.subscription_end IS NULL OR ts.subscription_end >= CURDATE())
                    ) THEN 1 ELSE 0
                END
            ) AS active_plans,
            SUM(
                CASE WHEN ts.payment_status IN ('pending', 'failed') THEN 1 ELSE 0 END
            ) AS pending_rows
        FROM tbl_tenant_subscriptions ts
        INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id
        LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
        WHERE {$whereSql}
    ";
    $metricsStmt = $pdo->prepare($metricsSql);
    /** @var array<int, mixed> $metricsBind */
    $metricsBind = $countParams;
    $metricsStmt->execute($metricsBind);
    $metricsRow = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $metricTotalRevenue = (float) ($metricsRow['total_revenue'] ?? 0);
    $metricActivePlans = (int) ($metricsRow['active_plans'] ?? 0);
    $pendingSubRows = (int) ($metricsRow['pending_rows'] ?? 0);

    $invoiceWhere = ['i.status = ?'];
    $invoiceParams = ['overdue'];
    if ($filterClinic !== '' && strlen($filterClinic) <= 20) {
        $invoiceWhere[] = 'i.tenant_id = ?';
        $invoiceParams[] = $filterClinic;
    }
    if ($filterPlan !== '' && ctype_digit($filterPlan)) {
        $invoiceWhere[] = 'i.plan_id = ?';
        $invoiceParams[] = (int) $filterPlan;
    }
    if ($filterDateFrom !== null) {
        $invoiceWhere[] = 'DATE(COALESCE(i.due_date, i.created_at)) >= ?';
        $invoiceParams[] = $filterDateFrom;
    }
    if ($filterDateTo !== null) {
        $invoiceWhere[] = 'DATE(COALESCE(i.due_date, i.created_at)) <= ?';
        $invoiceParams[] = $filterDateTo;
    }
    if ($searchQ !== '') {
        $like = '%' . $searchQ . '%';
        $invoiceWhere[] = '(ti.clinic_name LIKE ? OR uo.full_name LIKE ? OR uo.email LIKE ?)';
        $invoiceParams[] = $like;
        $invoiceParams[] = $like;
        $invoiceParams[] = $like;
    }
    $invoiceWhereSql = implode(' AND ', $invoiceWhere);

    // Overdue tenant invoices tied to tenants (same clinic/plan/date/search axes; excludes subscription-status filter).
    $invSql = "
        SELECT COUNT(*)
        FROM tbl_tenant_invoices i
        INNER JOIN tbl_tenants ti ON ti.tenant_id = i.tenant_id
        LEFT JOIN tbl_users uo ON uo.user_id = ti.owner_user_id
        WHERE {$invoiceWhereSql}
    ";
    $invStmt = $pdo->prepare($invSql);
    $invStmt->execute($invoiceParams);
    $overdueInvoiceCount = (int) $invStmt->fetchColumn();
    $metricPendingOverdue = $pendingSubRows + $overdueInvoiceCount;

    $listSql = "
        SELECT
            ts.id,
            ts.subscription_start,
            ts.subscription_end,
            ts.payment_status,
            ts.amount_paid,
            ts.created_at,
            t.tenant_id,
            t.clinic_name,
            t.subscription_status AS tenant_subscription_status,
            sp.plan_name,
            u.full_name AS owner_name,
            u.email AS owner_email
        FROM tbl_tenant_subscriptions ts
        INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id
        LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
        LEFT JOIN tbl_subscription_plans sp ON sp.plan_id = ts.plan_id
        WHERE {$whereSql}
        ORDER BY ts.id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $listStmt = $pdo->prepare($listSql);
    $listStmt->execute($countParams);
    $subsRows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $dbError = 'database';
}

if (!isset($offset)) {
    $offset = ($page - 1) * $perPage;
}

$rangeStart = $totalRows === 0 ? 0 : $offset + 1;
$rangeEnd = $totalRows === 0 ? 0 : min($totalRows, $offset + count($subsRows));
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Subscriptions | Clinical Precision</title>
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
                        "error-container": "#ffdad6",
                    },
                    fontFamily: {
                        "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"],
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
$superadmin_nav = 'subscriptions';
require __DIR__ . '/superadmin_sidebar.php';
$superadmin_header_center = '';
require __DIR__ . '/superadmin_header.php';
?>
<button id="sa-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="superadmin-sidebar" aria-expanded="false" aria-label="Open navigation menu">
<span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="sa-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<main class="ml-0 lg:ml-64 pt-20 min-h-screen">
<div class="pt-6 sm:pt-8 px-4 sm:px-6 lg:px-10 pb-12 sm:pb-16 space-y-8 sm:space-y-10 relative">
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-3xl sm:text-4xl font-extrabold font-headline tracking-tight text-on-surface">Subscriptions</h2>
<p class="text-on-surface-variant mt-2 font-medium">Monitor SaaS subscriptions, renewal dates, and payments across clinics.</p>
<form method="get" action="subscriptions.php" class="relative w-full max-w-md group mt-4">
<input type="hidden" name="clinic" value="<?php echo htmlspecialchars($filterBase['clinic'], ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="plan" value="<?php echo htmlspecialchars($filterBase['plan'], ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="status" value="<?php echo htmlspecialchars($filterBase['status'], ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filterBase['date_from'], ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filterBase['date_to'], ENT_QUOTES, 'UTF-8'); ?>"/>
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-xl pointer-events-none">search</span>
<input name="q" value="<?php echo htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm transition-all placeholder:text-on-surface-variant/50" placeholder="Search clinic, owner name or email..." type="search" autocomplete="off"/>
</form>
</div>
<div class="flex flex-wrap gap-3 w-full md:w-auto md:justify-end">
<a href="subscriptions.php" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white bg-white/60 px-5 py-2.5 text-sm font-bold text-on-surface-variant hover:bg-white transition-all shadow-sm">
<span class="material-symbols-outlined text-lg">restart_alt</span>
                    Reset filters
                </a>
</div>
</section>
<?php if ($dbError !== null): ?>
<div class="rounded-[2rem] bg-error/10 border border-error/20 px-8 py-4 text-error text-sm font-medium">
    Could not load subscription data. Please try again or check the database connection.
</div>
<?php endif; ?>
<section class="grid grid-cols-1 sm:grid-cols-3 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">payments</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">₱<?php echo number_format($metricTotalRevenue, 2, '.', ','); ?></h3>
<p class="text-[11px] text-on-surface-variant mt-2 font-medium">Paid subscription amounts in the current filtered set.</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">verified</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Active Plans</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($metricActivePlans); ?></h3>
<p class="text-[11px] text-on-surface-variant mt-2 font-medium">Subscriptions that are paid and currently within billing period.</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-amber-400/70">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-amber-50 text-amber-700 rounded-xl shadow-sm">
<span class="material-symbols-outlined">schedule</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Pending / Overdue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($metricPendingOverdue); ?></h3>
<p class="text-[11px] text-on-surface-variant mt-2 font-medium">Subscription rows awaiting payment plus overdue invoices (clinic/date filters apply).</p>
</div>
</section>
<div id="subscriptions-directory-panel" class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<div class="px-5 sm:px-6 lg:px-8 py-6 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4 border-b border-white/50">
<form method="get" action="subscriptions.php" class="flex flex-wrap items-center gap-3 sm:gap-4">
<input type="search" name="q" value="<?php echo htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search..." class="shrink min-w-[8rem] w-44 sm:w-52 bg-surface-container-low/50 border-none rounded-xl px-4 py-2.5 text-sm font-semibold text-on-surface placeholder:text-on-surface-variant/60 focus:ring-2 focus:ring-primary/20"/>
<button type="submit" class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-xl bg-surface-container-low/60 text-on-surface-variant hover:bg-white/80 transition-colors" aria-label="Apply search">
<span class="material-symbols-outlined text-[20px]">search</span>
</button>
<div class="relative group shrink-0">
<select name="clinic" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all min-w-[10rem] max-w-[14rem]" title="Clinic">
<option value="">All Clinics</option>
<?php foreach ($clinics as $c): ?>
<option value="<?php echo htmlspecialchars((string) $c['tenant_id'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filterClinic === (string) $c['tenant_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $c['clinic_name'], ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">domain</span>
</div>
<div class="relative group shrink-0">
<select name="plan" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all min-w-[9rem]" title="Plan type">
<option value="">All plan types</option>
<?php foreach ($plans as $p): ?>
<option value="<?php echo (int) $p['plan_id']; ?>"<?php echo $filterPlan === (string) $p['plan_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $p['plan_name'], ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">category</span>
</div>
<div class="relative group shrink-0">
<select name="status" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all min-w-[10rem]" title="Lifecycle status">
<option value=""<?php echo $filterStatus === '' ? ' selected' : ''; ?>>All Status</option>
<option value="active"<?php echo $filterStatus === 'active' ? ' selected' : ''; ?>>Active</option>
<option value="expired"<?php echo $filterStatus === 'expired' ? ' selected' : ''; ?>>Expired</option>
<option value="cancelled"<?php echo $filterStatus === 'cancelled' ? ' selected' : ''; ?>>Cancelled</option>
<option value="suspended"<?php echo $filterStatus === 'suspended' ? ' selected' : ''; ?>>Suspended</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">tune</span>
</div>
<label class="sr-only" for="subs-date-from">From date</label>
<input id="subs-date-from" type="date" name="date_from" value="<?php echo htmlspecialchars($filterBase['date_from'], ENT_QUOTES, 'UTF-8'); ?>" class="shrink-0 bg-surface-container-low/50 border-none rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold text-on-surface focus:ring-2 focus:ring-primary/20"/>
<label class="sr-only" for="subs-date-to">To date</label>
<input id="subs-date-to" type="date" name="date_to" value="<?php echo htmlspecialchars($filterBase['date_to'], ENT_QUOTES, 'UTF-8'); ?>" class="shrink-0 bg-surface-container-low/50 border-none rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold text-on-surface focus:ring-2 focus:ring-primary/20"/>
<a href="subscriptions.php" class="inline-flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-white/70 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-on-surface-variant hover:bg-white">
<span class="material-symbols-outlined text-base">restart_alt</span> Reset</a>
<button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary text-white px-5 py-2.5 text-xs font-bold uppercase tracking-wide primary-glow hover:brightness-105">Apply</button>
</form>
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60 xl:text-right">
                    Showing <span class="text-primary opacity-100"><?php echo $totalRows === 0 ? '0' : number_format($rangeStart) . '–' . number_format($rangeEnd); ?></span> of <?php echo number_format($totalRows); ?> subscriptions
                </div>
</div>
<div class="md:hidden px-4 sm:px-6 py-5 space-y-4">
<?php if ($dbError !== null): ?>
<div class="rounded-2xl border border-error/20 bg-error/10 px-4 py-5 text-sm text-error font-medium">Unable to load subscriptions.</div>
<?php elseif ($subsRows === []): ?>
<div class="rounded-2xl border border-outline-variant/20 bg-white/70 px-4 py-5 text-sm text-on-surface-variant font-medium">No subscriptions match your filters.</div>
<?php else: ?>
<?php foreach ($subsRows as $row):
    $dispStatus = subscriptions_derive_display_status($row);
    $badge = subscriptions_status_badge($dispStatus);
    $planName = trim((string) ($row['plan_name'] ?? ''));
    $ownerName = trim((string) ($row['owner_name'] ?? ''));
    $ownerEmail = trim((string) ($row['owner_email'] ?? ''));
    $payOk = strtolower((string) ($row['payment_status'] ?? '')) === 'paid';
    $nextBillingStr = $dispStatus === 'active'
        ? subscriptions_format_date_disp($row['subscription_end'] ?? null)
        : '—';
    $lastAmt = subscriptions_money(isset($row['amount_paid']) ? (float) $row['amount_paid'] : null);
?>
<article class="rounded-2xl bg-white/80 border border-white/70 shadow-sm p-4 space-y-3">
<div class="flex items-start justify-between gap-2">
<div>
<p class="text-xs font-bold text-on-surface-variant uppercase tracking-wide">Clinic</p>
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars((string) ($row['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php echo $badge; ?>
</div>
<div class="flex gap-3">
<div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-primary text-[10px] font-bold shrink-0"><?php echo htmlspecialchars(subscriptions_initials($ownerName !== '' ? $ownerName : (string) ($row['clinic_name'] ?? '?')), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="min-w-0">
<p class="text-sm font-semibold text-on-surface truncate"><?php echo htmlspecialchars($ownerName !== '' ? $ownerName : '—', ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[11px] text-on-surface-variant truncate"><?php echo $ownerEmail !== '' ? htmlspecialchars($ownerEmail, ENT_QUOTES, 'UTF-8') : '—'; ?></p>
</div>
</div>
<div class="grid grid-cols-2 gap-3 text-xs">
<div>
<p class="text-[10px] font-bold uppercase text-on-surface-variant/70 tracking-wide">Plan</p>
<p class="font-semibold text-primary mt-0.5"><?php echo $planName !== '' ? htmlspecialchars($planName, ENT_QUOTES, 'UTF-8') : '—'; ?></p>
</div>
<div>
<p class="text-[10px] font-bold uppercase text-on-surface-variant/70 tracking-wide">Next billing</p>
<p class="font-medium text-on-surface mt-0.5"><?php echo htmlspecialchars($nextBillingStr, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div class="col-span-2">
<p class="text-[10px] font-bold uppercase text-on-surface-variant/70 tracking-wide">Last payment</p>
<p class="font-bold text-on-surface mt-0.5"><?php echo $payOk ? $lastAmt : '—'; ?></p>
</div>
</div>
</article>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="hidden md:block overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Clinic Name</th>
<th class="px-8 py-5">Owner</th>
<th class="px-8 py-5">Plan</th>
<th class="px-8 py-5">Status</th>
<th class="px-8 py-5">Next Billing</th>
<th class="px-10 py-5 text-right">Last Payment</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if ($dbError !== null): ?>
<tr>
<td colspan="6" class="px-10 py-12 text-center text-sm text-error font-medium">Unable to load subscriptions.</td>
</tr>
<?php elseif ($subsRows === []): ?>
<tr>
<td colspan="6" class="px-10 py-12 text-center text-sm text-on-surface-variant font-medium">No subscriptions match your filters.</td>
</tr>
<?php else: ?>
<?php foreach ($subsRows as $row):
    $dispStatus = subscriptions_derive_display_status($row);
    $badge = subscriptions_status_badge($dispStatus);
    $planName = trim((string) ($row['plan_name'] ?? ''));
    $ownerName = trim((string) ($row['owner_name'] ?? ''));
    $ownerEmail = trim((string) ($row['owner_email'] ?? ''));
    $displayOwner = $ownerName !== '' ? $ownerName : '—';
    $payOk = strtolower((string) ($row['payment_status'] ?? '')) === 'paid';
    $nextBillingStr = $dispStatus === 'active'
        ? subscriptions_format_date_disp($row['subscription_end'] ?? null)
        : '—';
    $lastAmt = subscriptions_money(isset($row['amount_paid']) ? (float) $row['amount_paid'] : null);
?>
<tr class="hover:bg-primary/5 transition-colors">
<td class="px-10 py-5">
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars((string) ($row['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-on-surface-variant font-medium mt-0.5"><?php echo htmlspecialchars((string) ($row['tenant_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-8 py-5">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-primary text-[10px] font-bold border-2 border-white shadow-sm shrink-0"><?php echo htmlspecialchars(subscriptions_initials($displayOwner !== '—' ? $displayOwner : (string) ($row['clinic_name'] ?? '?')), ENT_QUOTES, 'UTF-8'); ?></div>
<div>
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($displayOwner, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-on-surface-variant font-medium"><?php echo $ownerEmail !== '' ? htmlspecialchars($ownerEmail, ENT_QUOTES, 'UTF-8') : '—'; ?></p>
</div>
</div>
</td>
<td class="px-8 py-5">
<?php if ($planName !== ''): ?>
<span class="text-sm font-bold text-primary"><?php echo htmlspecialchars($planName, ENT_QUOTES, 'UTF-8'); ?></span>
<?php else: ?>
<span class="text-sm font-medium text-on-surface-variant/60">—</span>
<?php endif; ?>
</td>
<td class="px-8 py-5"><?php echo $badge; ?></td>
<td class="px-8 py-5 text-sm font-medium text-on-surface-variant"><?php echo htmlspecialchars($nextBillingStr, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-10 py-5 text-right text-sm font-bold text-on-surface"><?php echo $payOk ? $lastAmt : '—'; ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="px-4 sm:px-6 lg:px-10 py-8 flex flex-wrap items-center justify-between gap-4 border-t border-white/50">
<?php if ($page > 1): ?>
<a href="<?php echo htmlspecialchars(subscriptions_url($filterBase, ['page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>" class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </a>
<?php else: ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant text-sm font-bold rounded-xl border border-white/60 shadow-sm flex items-center gap-2 opacity-40 cursor-not-allowed">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </span>
<?php endif; ?>
<p class="text-sm font-bold text-on-surface order-first sm:order-none w-full sm:w-auto text-center sm:text-left">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></p>
<?php if ($page < $totalPages): ?>
<a href="<?php echo htmlspecialchars(subscriptions_url($filterBase, ['page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>" class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
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
<script>
(function () {
    var panel = document.getElementById('subscriptions-directory-panel');
    if (!panel) return;
    panel.querySelectorAll('select[name="clinic"], select[name="plan"], select[name="status"]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var f = sel.form;
            if (!f) return;
            if (typeof f.requestSubmit === 'function') {
                f.requestSubmit();
            } else {
                f.submit();
            }
        });
    });
})();
</script>
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
    if (typeof mqDesktop.addEventListener === 'function') mqDesktop.addEventListener('change', closeOnDesktop);
    else if (typeof mqDesktop.addListener === 'function') mqDesktop.addListener(closeOnDesktop);
})();
</script>
</body>
</html>
