<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

$metrics = [
    'total_registered_clinics' => 0,
    'active_clinics' => 0,
    'monthly_revenue' => 0.0,
    'total_patient_records' => 0,
    'expiring_subscriptions' => 0,
];
$revenue_series = [
    'monthly' => ['labels' => [], 'values' => []],
    'weekly' => ['labels' => [], 'values' => []],
    'yearly' => ['labels' => [], 'values' => []],
];
$tenant_growth = ['labels' => [], 'counts' => []];
$top_performing = [];

try {
    $metrics['total_registered_clinics'] = (int) $pdo->query('SELECT COUNT(*) FROM tbl_tenants')->fetchColumn();

    // Active: tenant account active, clinic site published (clinic_slug), paid subscription not ended
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT t.tenant_id)
        FROM tbl_tenants t
        INNER JOIN tbl_tenant_subscriptions s ON s.tenant_id = t.tenant_id
        WHERE t.subscription_status = 'active'
          AND t.clinic_slug IS NOT NULL AND TRIM(t.clinic_slug) <> ''
          AND s.payment_status = 'paid'
          AND (s.subscription_end IS NULL OR s.subscription_end >= CURDATE())
    ");
    $metrics['active_clinics'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("
        SELECT COALESCE(SUM(COALESCE(amount_paid, 0)), 0)
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND YEAR(created_at) = YEAR(CURDATE())
          AND MONTH(created_at) = MONTH(CURDATE())
    ");
    $metrics['monthly_revenue'] = (float) $stmt->fetchColumn();

    $metrics['total_patient_records'] = (int) $pdo->query('SELECT COUNT(*) FROM tbl_patients')->fetchColumn();

    // Subscriptions ending within the next 30 days (still current as of today)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT s.tenant_id)
        FROM tbl_tenant_subscriptions s
        WHERE s.payment_status = 'paid'
          AND s.subscription_end IS NOT NULL
          AND s.subscription_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $metrics['expiring_subscriptions'] = (int) $stmt->fetchColumn();

    // Revenue Analytics chart: paid subscription amounts by period (created_at)
    $monthKeys = [];
    for ($i = 11; $i >= 0; $i--) {
        $d = new DateTime('first day of this month 00:00:00');
        $d->modify("-{$i} months");
        $monthKeys[] = $d->format('Y-m');
    }
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(COALESCE(amount_paid, 0)), 0) AS total
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND created_at >= ?
        GROUP BY ym
    ");
    $stmt->execute([$monthKeys[0] . '-01 00:00:00']);
    $monthData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $monthData[$row['ym']] = (float) $row['total'];
    }
    foreach ($monthKeys as $ym) {
        $d = DateTime::createFromFormat('Y-m', $ym);
        $revenue_series['monthly']['labels'][] = $d ? $d->format('M') : $ym;
        $revenue_series['monthly']['values'][] = $monthData[$ym] ?? 0.0;
    }

    $weekKeys = [];
    $today = new DateTime('today');
    $dow = (int) $today->format('N');
    $thisMonday = clone $today;
    $thisMonday->modify('-' . ($dow - 1) . ' days');
    $thisMonday->setTime(0, 0, 0);
    for ($i = 7; $i >= 0; $i--) {
        $wk = clone $thisMonday;
        $wk->modify("-{$i} weeks");
        $weekKeys[] = $wk->format('Y-m-d');
    }
    $stmt = $pdo->prepare("
        SELECT DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY)) AS week_start,
               COALESCE(SUM(COALESCE(amount_paid, 0)), 0) AS total
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND created_at >= ?
        GROUP BY week_start
    ");
    $stmt->execute([$weekKeys[0] . ' 00:00:00']);
    $weekData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $wkKey = $row['week_start'] ? substr((string) $row['week_start'], 0, 10) : '';
        if ($wkKey !== '') {
            $weekData[$wkKey] = (float) $row['total'];
        }
    }
    foreach ($weekKeys as $wk) {
        $d = DateTime::createFromFormat('Y-m-d', $wk);
        $revenue_series['weekly']['labels'][] = $d ? $d->format('M j') : $wk;
        // Normalize DB date string (Y-m-d) for array key match
        $revenue_series['weekly']['values'][] = $weekData[$wk] ?? 0.0;
    }

    $yStart = (int) date('Y') - 4;
    $yEnd = (int) date('Y');
    $stmt = $pdo->prepare("
        SELECT YEAR(created_at) AS y, COALESCE(SUM(COALESCE(amount_paid, 0)), 0) AS total
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND YEAR(created_at) BETWEEN ? AND ?
        GROUP BY y
    ");
    $stmt->execute([$yStart, $yEnd]);
    $yearData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $yearData[(int) $row['y']] = (float) $row['total'];
    }
    for ($y = $yStart; $y <= $yEnd; $y++) {
        $revenue_series['yearly']['labels'][] = (string) $y;
        $revenue_series['yearly']['values'][] = $yearData[$y] ?? 0.0;
    }

    // Tenant growth (by patients): new patient registrations per month, last 6 months
    $growthMonthKeys = [];
    for ($i = 5; $i >= 0; $i--) {
        $d = new DateTime('first day of this month 00:00:00');
        $d->modify("-{$i} months");
        $growthMonthKeys[] = $d->format('Y-m');
    }
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
        FROM tbl_patients
        WHERE created_at >= ?
        GROUP BY ym
    ");
    $stmt->execute([$growthMonthKeys[0] . '-01 00:00:00']);
    $patientMonthData = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $patientMonthData[$row['ym']] = (int) $row['cnt'];
    }
    foreach ($growthMonthKeys as $ym) {
        $d = DateTime::createFromFormat('Y-m', $ym);
        $tenant_growth['labels'][] = $d ? $d->format('M') : $ym;
        $tenant_growth['counts'][] = $patientMonthData[$ym] ?? 0;
    }

    // Top performing tenants by total paid subscription revenue
    $stmt = $pdo->query("
        SELECT t.clinic_name,
               COALESCE(SUM(COALESCE(s.amount_paid, 0)), 0) AS revenue
        FROM tbl_tenants t
        INNER JOIN tbl_tenant_subscriptions s
            ON s.tenant_id = t.tenant_id AND s.payment_status = 'paid'
        GROUP BY t.tenant_id, t.clinic_name
        ORDER BY revenue DESC
        LIMIT 5
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $top_performing[] = [
            'name' => (string) $row['clinic_name'],
            'revenue' => (float) $row['revenue'],
        ];
    }
} catch (PDOException $e) {
    error_log('superadmin dashboard metrics: ' . $e->getMessage());
    $revenue_series = [
        'monthly' => ['labels' => [], 'values' => []],
        'weekly' => ['labels' => [], 'values' => []],
        'yearly' => ['labels' => [], 'values' => []],
    ];
    $tenant_growth = ['labels' => [], 'counts' => []];
    $top_performing = [];
}

$tenant_growth_max = !empty($tenant_growth['counts']) ? max($tenant_growth['counts']) : 0;
if ($tenant_growth_max < 1) {
    $tenant_growth_max = 1;
}
$top_revenue_max = 0.0;
foreach ($top_performing as $tp) {
    if ($tp['revenue'] > $top_revenue_max) {
        $top_revenue_max = $tp['revenue'];
    }
}

// JSON_HEX_* keeps </script> and & safe inside <script type="application/json">
$revenue_chart_json = json_encode(
    $revenue_series,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
if ($revenue_chart_json === false) {
    $revenue_chart_json = '{}';
}

function dashboard_format_revenue(float $amount): string
{
    if ($amount >= 1000000) {
        return '₱' . number_format($amount / 1000000, 1) . 'M';
    }
    if ($amount >= 1000) {
        return '₱' . number_format($amount / 1000, 1) . 'k';
    }
    return '₱' . number_format($amount, 0);
}

function dashboard_format_int(int $n): string
{
    return number_format($n);
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision | Dashboard Analytics</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-error": "#ffffff",
                        "on-tertiary-fixed-variant": "#6e3900",
                        "on-surface": "#131c25",
                        "primary-fixed-dim": "#a4c9ff",
                        "on-secondary-fixed": "#001c39",
                        "surface-container-high": "#e0e9f6",
                        "on-background": "#131c25",
                        "inverse-on-surface": "#e8f1ff",
                        "tertiary-container": "#b25f00",
                        "surface-bright": "#f7f9ff",
                        "secondary-fixed-dim": "#adc8f3",
                        "surface-variant": "#dae3f0",
                        "on-tertiary": "#ffffff",
                        "outline": "#717784",
                        "inverse-surface": "#28313b",
                        "on-primary-container": "#fdfcff",
                        "inverse-primary": "#a4c9ff",
                        "secondary-container": "#b8d3fe",
                        "error-container": "#ffdad6",
                        "primary-container": "#0076d2",
                        "on-secondary-container": "#405b80",
                        "surface": "#f7f9ff",
                        "on-secondary": "#ffffff",
                        "on-primary": "#ffffff",
                        "on-primary-fixed": "#001c39",
                        "on-primary-fixed-variant": "#004883",
                        "surface-container-lowest": "#ffffff",
                        "tertiary-fixed-dim": "#ffb77e",
                        "surface-dim": "#d2dbe8",
                        "on-tertiary-container": "#fffbff",
                        "on-error-container": "#93000a",
                        "background": "#f7f9ff",
                        "surface-tint": "#0060ac",
                        "surface-container": "#e6effc",
                        "tertiary": "#8e4a00",
                        "primary-fixed": "#d4e3ff",
                        "on-tertiary-fixed": "#2f1500",
                        "surface-container-low": "#edf4ff",
                        "tertiary-fixed": "#ffdcc3",
                        "primary": "#0066ff", /* Vibrant Brand Blue */
                        "surface-container-highest": "#dae3f0",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "error": "#ba1a1a",
                        "secondary": "#456085",
                        "secondary-fixed": "#d4e3ff",
                        "on-secondary-fixed-variant": "#2c486c"
                    },
                    fontFamily: {
                        "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
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
        .pulse-live {
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4);
            animation: pulse-animation 2s infinite;
        }
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .ai-glow {
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 40px -15px rgba(0, 102, 255, 0.4);
        }
        #revenue-chart-tooltip {
            pointer-events: none;
            z-index: 50;
            transition: opacity 0.12s ease;
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface selection:bg-primary/10 min-h-screen">
<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-full w-64 z-40 sidebar-glass flex flex-col py-8">
<div class="px-7 mb-10">
<a href="dashboard.php" class="block" aria-label="MyDental">
<img src="MyDental Logo.svg" alt="MyDental" class="h-11 w-auto max-w-full object-contain object-left"/>
</a>
<p class="text-on-surface-variant text-[10px] font-bold tracking-[0.2em] mt-2 opacity-60">MANAGEMENT CONSOLE</p>
</div>
<nav class="flex-1 space-y-1 overflow-y-auto no-scrollbar">
<!-- Active Item: Dashboard Analytics -->
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="dashboard.php">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">dashboard</span>
<span class="font-headline text-sm font-bold tracking-tight">Dashboard Analytics</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="tenantmanagement.php">
<span class="material-symbols-outlined text-[22px]">groups</span>
<span class="font-headline text-sm font-medium tracking-tight">Tenant Management</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="salesreport.php">
<span class="material-symbols-outlined text-[22px]">payments</span>
<span class="font-headline text-sm font-medium tracking-tight">Sales Report</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="reports.php">
<span class="material-symbols-outlined text-[22px]">assessment</span>
<span class="font-headline text-sm font-medium tracking-tight">Reports</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="auditlogs.php">
<span class="material-symbols-outlined text-[22px]">history_edu</span>
<span class="font-headline text-sm font-medium tracking-tight">Audit Logs</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">settings_backup_restore</span>
<span class="font-headline text-sm font-medium tracking-tight">Backup and Restore</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">settings</span>
<span class="font-headline text-sm font-medium tracking-tight">Settings</span>
</a>
</div>
</nav>
<div class="px-4 mt-auto">
<div class="bg-white/40 backdrop-blur-md rounded-2xl p-5 border border-white/60 shadow-sm">
<div class="flex items-center gap-3 mb-4">
<div class="w-9 h-9 rounded-full bg-primary-container flex items-center justify-center text-primary text-xs font-bold">CP</div>
<div>
<p class="text-on-surface text-xs font-bold">Pro Plan</p>
<p class="text-on-surface-variant text-[10px]">Renewal in 12 days</p>
</div>
</div>
<button class="w-full py-2.5 bg-white border border-outline-variant/30 hover:border-primary/50 text-on-surface text-xs font-bold rounded-xl transition-all shadow-sm">Manage Subscription</button>
</div>
</div>
</aside>
<!-- Main Content Area -->
<main class="ml-64 min-h-screen">
<!-- TopNavBar -->
<header class="fixed top-0 right-0 w-[calc(100%-16rem)] h-20 z-30 bg-white/70 backdrop-blur-xl border-b border-white/50 flex items-center justify-between px-8">
<div class="flex items-center gap-6 flex-1">
<div class="relative w-full max-w-md group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-xl">search</span>
<input class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm transition-all placeholder:text-on-surface-variant/50" placeholder="Search analytics, tenants, or logs..." type="text"/>
</div>
</div>
<div class="flex items-center gap-4">
<button class="hover:bg-surface-container-low rounded-full p-2.5 transition-all relative">
<span class="material-symbols-outlined text-on-surface-variant">notifications</span>
<span class="absolute top-2.5 right-2.5 w-2 h-2 bg-error rounded-full border-2 border-white"></span>
</button>
<div class="h-8 w-[1px] bg-outline-variant/30 mx-2"></div>
<div class="flex items-center gap-3 pl-2">
<div class="text-right hidden sm:block">
<p class="text-sm font-bold text-on-surface">Admin Profile</p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest opacity-60">System Administrator</p>
</div>
<img alt="Administrator Avatar" class="w-10 h-10 rounded-full bg-surface-container-high border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCxmCwuZsw3FIjKZMlXMhmtedAfM8l15FTTXdghPSBaGUpwaVh7F7yxLeBqEPo9BEis6mcx4-Dt8sp115aiOPHzNZcaoQ_6m-APqGIy2CZJ3YmfbFs7OgoSNf2oZHGHF0ZYw0HaEVJfTAuY6Urd2B0uL7whKQ9Q80LSTdIkm7cWqYTL7l-kP-MFKFY5iw_bpXSKEzGCsPNV5C4-ztcbAysDnjkEWkf38QkT2xsMIU_vpCEXGFw_z09P439roJ2AD1esYwOdBkaQKUk"/>
</div>
</div>
</header>
<!-- Page Canvas -->
<div class="pt-28 px-10 pb-16 space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Header Section -->
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Dashboard Analytics</h2>
<p class="text-on-surface-variant mt-2 font-medium">Real-time performance metrics for Clinical Precision ecosystem.</p>
</div>
<div class="flex items-center gap-3">
<button class="bg-white/80 backdrop-blur-md text-primary px-5 py-2.5 rounded-2xl text-sm font-bold border border-white flex items-center gap-2 hover:bg-white transition-all shadow-sm">
<span class="material-symbols-outlined text-lg">calendar_today</span>
                        Last 30 Days
                    </button>
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">download</span>
                        Export Report
                    </button>
</div>
</section>
<!-- Top Metrics Bento Grid -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
<!-- Card 1 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">corporate_fare</span>
</div>
<span class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest">Live</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Registered Clinics</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo htmlspecialchars(dashboard_format_int($metrics['total_registered_clinics'])); ?></h3>
</div>
<!-- Card 2 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">medical_services</span>
</div>
<span class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest">Live</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Active Clinics</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo htmlspecialchars(dashboard_format_int($metrics['active_clinics'])); ?></h3>
</div>
<!-- Card 3 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">payments</span>
</div>
<span class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest">Live</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Monthly Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo htmlspecialchars(dashboard_format_revenue($metrics['monthly_revenue'])); ?></h3>
</div>
<!-- Card 4 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">clinical_notes</span>
</div>
<span class="text-[10px] font-bold text-on-surface-variant/60 uppercase tracking-widest">Live</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Patient Records</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo htmlspecialchars(dashboard_format_int($metrics['total_patient_records'])); ?></h3>
</div>
<!-- Card 5 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-tertiary-container/10 text-tertiary rounded-xl shadow-sm">
<span class="material-symbols-outlined">warning</span>
</div>
<?php if ($metrics['expiring_subscriptions'] > 0): ?>
<span class="text-[10px] font-extrabold text-tertiary bg-tertiary-fixed px-2 py-1 rounded-lg uppercase">Alert</span>
<?php else: ?>
<span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">OK</span>
<?php endif; ?>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Expiring Subscription</p>
<p class="text-[10px] text-on-surface-variant/70 mt-0.5">Next 30 days</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo htmlspecialchars(dashboard_format_int($metrics['expiring_subscriptions'])); ?></h3>
</div>
</section>
<!-- Main Charts & Insights Section -->
<section class="grid grid-cols-12 gap-8">
<!-- Revenue Analytics & AI Widget -->
<div class="col-span-12 lg:col-span-8 space-y-8">
<!-- Revenue Analytics Line Chart -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow relative overflow-hidden">
<div class="absolute top-0 right-0 p-8">
<div class="flex bg-surface-container-low/50 p-1.5 rounded-2xl border border-white/40" id="revenue-period-toggle" role="tablist">
<button type="button" data-period="monthly" class="revenue-period-btn px-5 py-2 text-xs font-bold rounded-xl bg-white shadow-sm text-primary">Monthly</button>
<button type="button" data-period="weekly" class="revenue-period-btn px-5 py-2 text-xs font-bold rounded-xl text-on-surface-variant hover:text-on-surface">Weekly</button>
<button type="button" data-period="yearly" class="revenue-period-btn px-5 py-2 text-xs font-bold rounded-xl text-on-surface-variant hover:text-on-surface">Yearly</button>
</div>
</div>
<div class="mb-10 pr-44">
<h4 class="text-xl font-extrabold font-headline">Revenue Analytics</h4>
<p class="text-sm text-on-surface-variant font-medium">Paid subscription revenue (<span class="whitespace-nowrap">amount_paid</span>) by <span id="revenue-period-label">month</span></p>
</div>
<script type="application/json" id="revenue-chart-data"><?php echo $revenue_chart_json; ?></script>
<div class="h-64 relative" id="revenue-chart-container">
<div class="absolute inset-0 flex flex-col justify-between pointer-events-none opacity-[0.07] z-0">
<div class="border-t border-on-surface"></div>
<div class="border-t border-on-surface"></div>
<div class="border-t border-on-surface"></div>
<div class="border-t border-on-surface"></div>
</div>
<svg class="absolute inset-0 w-full h-full z-[1]" preserveAspectRatio="none" viewBox="0 0 1000 200" role="img" aria-labelledby="revenue-chart-title">
<title id="revenue-chart-title">Revenue by period</title>
<defs>
<linearGradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
<stop offset="0%" stop-color="#0066ff" stop-opacity="0.25"></stop>
<stop offset="100%" stop-color="#0066ff" stop-opacity="0"></stop>
</linearGradient>
</defs>
<path id="revenue-area" d="" fill="url(#chartGradient)" pointer-events="none"></path>
<path id="revenue-line" d="" fill="none" stroke="#0066ff" stroke-linecap="round" stroke-width="4" pointer-events="none"></path>
<g id="revenue-points" pointer-events="all"></g>
</svg>
<div id="revenue-chart-tooltip" class="absolute z-[50] hidden opacity-0 rounded-xl bg-on-surface text-white text-xs font-bold px-3 py-2 shadow-xl border border-white/10 max-w-[220px]">
<span id="revenue-chart-tooltip-label" class="block text-[10px] font-semibold uppercase tracking-wider text-white/70"></span>
<span id="revenue-chart-tooltip-value" class="block text-sm mt-0.5"></span>
</div>
<div class="absolute bottom-0 left-0 right-0 flex w-full justify-between pt-3 text-[10px] font-bold text-on-surface-variant uppercase tracking-[0.15em] z-[2] pointer-events-none" id="revenue-chart-labels"></div>
</div>
<div class="flex flex-wrap justify-between gap-2 mt-3 text-[10px] font-semibold text-on-surface-variant">
<span id="revenue-chart-sum"></span>
<span id="revenue-chart-peak"></span>
</div>
</div>
<script>
(function () {
    function initRevenueChart() {
        var dataEl = document.getElementById('revenue-chart-data');
        var container = document.getElementById('revenue-chart-container');
        if (!dataEl || !container) return;

        var chartData = {};
        try {
            var raw = (dataEl.textContent || '').trim();
            chartData = JSON.parse(raw);
        } catch (e) {
            console.error('Revenue chart JSON parse error', e);
            var sumEl = document.getElementById('revenue-chart-sum');
            if (sumEl) sumEl.textContent = 'Chart data could not be loaded.';
            return;
        }

        var W = 1000, H = 200, PAD = 18;
        var labelWords = { monthly: 'month', weekly: 'week', yearly: 'year' };
        var currentPeriod = 'monthly';
        var tooltipHideTimer = null;
        var TOOLTIP_HIDE_MS = 200;

        function buildPath(values, labels) {
            labels = labels || [];
            var n = values.length;
            if (n === 0) {
                return { line: '', area: '', cx: W / 2, cy: H / 2, max: 0, sum: 0, pts: [] };
            }
            var max = 0;
            for (var i = 0; i < n; i++) if (values[i] > max) max = values[i];
            if (max < 1e-9) max = 1;
            var sum = 0;
            for (var j = 0; j < n; j++) sum += values[j];

            var pts = [];
            if (n === 1) {
                var x0 = W / 2;
                var y0 = H - PAD - (values[0] / max) * (H - 2 * PAD);
                pts.push({ x: x0, y: y0, label: labels[0] || '', value: values[0] });
                return {
                    line: 'M ' + (x0 - 1) + ',' + y0 + ' L ' + (x0 + 1) + ',' + y0,
                    area: 'M ' + (x0 - 1) + ',' + y0 + ' L ' + (x0 + 1) + ',' + y0 + ' L ' + (x0 + 1) + ',' + H + ' L ' + (x0 - 1) + ',' + H + ' Z',
                    cx: x0,
                    cy: y0,
                    max: max,
                    sum: sum,
                    pts: pts
                };
            }
            for (var k = 0; k < n; k++) {
                var x = k * (W / (n - 1));
                var y = H - PAD - (values[k] / max) * (H - 2 * PAD);
                pts.push({ x: x, y: y, label: labels[k] || '', value: values[k] });
            }
            var d = 'M ' + pts[0].x + ',' + pts[0].y;
            for (var m = 1; m < n; m++) d += ' L ' + pts[m].x + ',' + pts[m].y;
            var area = d + ' L ' + pts[n - 1].x + ',' + H + ' L ' + pts[0].x + ',' + H + ' Z';
            return {
                line: d,
                area: area,
                cx: pts[n - 1].x,
                cy: pts[n - 1].y,
                max: max,
                sum: sum,
                pts: pts
            };
        }

        function fmtMoney(x) {
            if (x >= 1000000) return '₱' + (x / 1000000).toFixed(1) + 'M';
            if (x >= 1000) return '₱' + (x / 1000).toFixed(1) + 'k';
            return '₱' + Math.round(x).toLocaleString();
        }

        function hideTooltip() {
            if (tooltipHideTimer) {
                clearTimeout(tooltipHideTimer);
                tooltipHideTimer = null;
            }
            var tt = document.getElementById('revenue-chart-tooltip');
            if (!tt) return;
            tt.classList.add('hidden', 'opacity-0');
            tt.classList.remove('opacity-100');
        }

        function resetPointDots() {
            document.querySelectorAll('#revenue-points .revenue-point-dot').forEach(function (v) {
                v.setAttribute('r', '5');
                v.setAttribute('stroke-width', '2');
            });
        }

        function scheduleHideTooltip() {
            if (tooltipHideTimer) clearTimeout(tooltipHideTimer);
            tooltipHideTimer = setTimeout(function () {
                tooltipHideTimer = null;
                hideTooltip();
                resetPointDots();
            }, TOOLTIP_HIDE_MS);
        }

        function cancelHideTooltip() {
            if (tooltipHideTimer) {
                clearTimeout(tooltipHideTimer);
                tooltipHideTimer = null;
            }
        }

        function showTooltip(cx, cy, label, value) {
            var tt = document.getElementById('revenue-chart-tooltip');
            var tl = document.getElementById('revenue-chart-tooltip-label');
            var tv = document.getElementById('revenue-chart-tooltip-value');
            if (!tt || !tl || !tv) return;
            tl.textContent = label || 'Period';
            tv.textContent = fmtMoney(value);
            var pctX = (cx / W) * 100;
            var pctY = (cy / H) * 100;
            tt.style.left = pctX + '%';
            tt.style.top = pctY + '%';
            tt.style.transform = 'translate(-50%, calc(-100% - 10px))';
            tt.classList.remove('hidden', 'opacity-0');
            tt.classList.add('opacity-100');
        }

        function render(period) {
            currentPeriod = period;
            var s = chartData[period];
            var labEl = document.getElementById('revenue-period-label');
            if (labEl) labEl.textContent = labelWords[period] || period;

            var areaEl = document.getElementById('revenue-area');
            var lineEl = document.getElementById('revenue-line');
            var pointsG = document.getElementById('revenue-points');

            cancelHideTooltip();
            hideTooltip();
            resetPointDots();

            if (!s || !s.values || s.values.length === 0) {
                if (areaEl) areaEl.setAttribute('d', '');
                if (lineEl) lineEl.setAttribute('d', '');
                if (pointsG) pointsG.innerHTML = '';
                var labelsEl = document.getElementById('revenue-chart-labels');
                if (labelsEl) labelsEl.innerHTML = '';
                var sumEl = document.getElementById('revenue-chart-sum');
                var peakEl = document.getElementById('revenue-chart-peak');
                if (sumEl) sumEl.textContent = '';
                if (peakEl) peakEl.textContent = 'No data';
                return;
            }

            var p = buildPath(s.values, s.labels || []);
            if (areaEl) areaEl.setAttribute('d', p.area);
            if (lineEl) lineEl.setAttribute('d', p.line);

            if (pointsG) {
                pointsG.innerHTML = '';
                p.pts.forEach(function (pt) {
                    var g = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                    g.setAttribute('class', 'revenue-point-group cursor-pointer');
                    var hit = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    hit.setAttribute('cx', pt.x);
                    hit.setAttribute('cy', pt.y);
                    hit.setAttribute('r', '32');
                    hit.setAttribute('fill', 'transparent');
                    hit.setAttribute('stroke', 'none');
                    var vis = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    vis.setAttribute('class', 'revenue-point-dot transition-all duration-150');
                    vis.setAttribute('cx', pt.x);
                    vis.setAttribute('cy', pt.y);
                    vis.setAttribute('r', '5');
                    vis.setAttribute('fill', '#ffffff');
                    vis.setAttribute('stroke', '#0066ff');
                    vis.setAttribute('stroke-width', '2');
                    vis.setAttribute('pointer-events', 'none');
                    g.appendChild(hit);
                    g.appendChild(vis);
                    hit.addEventListener('mouseenter', function () {
                        cancelHideTooltip();
                        vis.setAttribute('r', '8');
                        vis.setAttribute('stroke-width', '3');
                        showTooltip(pt.x, pt.y, pt.label, pt.value);
                    });
                    hit.addEventListener('mouseleave', function () {
                        scheduleHideTooltip();
                    });
                    hit.addEventListener('focus', function () {
                        cancelHideTooltip();
                        vis.setAttribute('r', '8');
                        vis.setAttribute('stroke-width', '3');
                        showTooltip(pt.x, pt.y, pt.label, pt.value);
                    });
                    hit.addEventListener('blur', function () {
                        hideTooltip();
                        resetPointDots();
                    });
                    hit.setAttribute('tabindex', '0');
                    hit.setAttribute('role', 'button');
                    hit.setAttribute('aria-label', 'Revenue ' + (pt.label || '') + ': ' + fmtMoney(pt.value));
                    pointsG.appendChild(g);
                });
            }

            var labels = document.getElementById('revenue-chart-labels');
            if (labels) {
                labels.innerHTML = '';
                (s.labels || []).forEach(function (lab) {
                    var sp = document.createElement('span');
                    sp.textContent = lab;
                    labels.appendChild(sp);
                });
            }

            var sumOut = document.getElementById('revenue-chart-sum');
            var peakOut = document.getElementById('revenue-chart-peak');
            if (sumOut) sumOut.textContent = 'Period total: ' + fmtMoney(p.sum);
            if (peakOut) peakOut.textContent = 'Peak: ' + fmtMoney(p.max);
        }

        document.querySelectorAll('.revenue-period-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var period = btn.getAttribute('data-period');
                document.querySelectorAll('.revenue-period-btn').forEach(function (b) {
                    b.classList.remove('bg-white', 'shadow-sm', 'text-primary');
                    b.classList.add('text-on-surface-variant');
                });
                btn.classList.add('bg-white', 'shadow-sm', 'text-primary');
                btn.classList.remove('text-on-surface-variant');
                render(period);
            });
        });

        container.addEventListener('mouseleave', function () {
            cancelHideTooltip();
            hideTooltip();
            resetPointDots();
        });

        render('monthly');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRevenueChart);
    } else {
        initRevenueChart();
    }
})();
</script>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<!-- Tenant Growth Bar Chart (new patients per month) -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<h4 class="text-lg font-extrabold font-headline mb-1">Tenant Growth</h4>
<p class="text-xs text-on-surface-variant font-medium mb-6">New patient registrations per month</p>
<div class="flex items-end justify-between h-40 px-2 gap-1">
<?php
$tg_counts = $tenant_growth['counts'] ?? [];
$tg_labels = $tenant_growth['labels'] ?? [];
$tg_n = count($tg_counts);
$tg_max_count = $tg_n > 0 ? max($tg_counts) : 0;
$tg_chart_px = 160;
for ($ti = 0; $ti < $tg_n; $ti++) {
    $tc = (int) $tg_counts[$ti];
    $bar_h_px = $tenant_growth_max > 0 ? (int) round(($tc / $tenant_growth_max) * $tg_chart_px) : 0;
    if ($tc > 0 && $bar_h_px < 6) {
        $bar_h_px = 6;
    }
    $is_peak = $tg_max_count > 0 && $tc === $tg_max_count;
    $bar_class = $is_peak
        ? 'w-full max-w-8 bg-primary rounded-xl primary-glow transition-colors'
        : 'w-full max-w-8 bg-surface-container-high rounded-xl hover:bg-primary/20 transition-colors';
    ?>
<div class="flex flex-1 flex-col justify-end items-center h-40 min-w-0">
<div class="<?php echo $bar_class; ?>" style="height: <?php echo (int) $bar_h_px; ?>px; min-height: 0;"></div>
</div>
<?php } ?>
<?php if ($tg_n === 0): ?>
<p class="text-sm text-on-surface-variant w-full text-center py-8">No patient data for this period.</p>
<?php endif; ?>
</div>
<div class="flex justify-between mt-6 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest gap-1">
<?php foreach ($tg_labels as $lab): ?>
<span class="truncate"><?php echo htmlspecialchars($lab); ?></span>
<?php endforeach; ?>
</div>
</div>
<!-- AI Insights Widget -->
<div class="bg-gradient-to-br from-primary via-[#1a80ff] to-[#0052cc] text-white p-8 rounded-[2rem] ai-glow relative overflow-hidden flex flex-col justify-between group">
<div class="absolute -right-8 -top-8 w-40 h-40 bg-white/10 rounded-full blur-[60px] group-hover:bg-white/20 transition-all duration-700"></div>
<div class="absolute -left-10 -bottom-10 w-32 h-32 bg-primary-fixed-dim/10 rounded-full blur-[50px]"></div>
<div>
<div class="flex items-center gap-2 mb-6">
<div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center backdrop-blur-md">
<span class="material-symbols-outlined text-white text-lg">psychology</span>
</div>
<span class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/90">AI Insights</span>
</div>
<h5 class="text-xl font-bold leading-tight font-headline">Tenant growth increased by 18% in the last quarter.</h5>
<p class="text-white/80 text-sm mt-4 leading-relaxed font-medium">Consider increasing infrastructure capacity in the North-East region to maintain 99.9% uptime.</p>
</div>
<button class="w-fit mt-8 px-6 py-2.5 bg-white text-primary hover:bg-white/90 rounded-2xl text-xs font-bold transition-all shadow-xl shadow-black/10">View Full Analysis</button>
</div>
</div>
</div>
<!-- Side Panels: Distribution & Activity -->
<div class="col-span-12 lg:col-span-4 space-y-8">
<!-- Activity Distribution Donut -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<h4 class="text-lg font-extrabold font-headline mb-6">Clinic Activity</h4>
<div class="relative w-48 h-48 mx-auto flex items-center justify-center">
<svg class="w-full h-full -rotate-90" viewbox="0 0 100 100">
<circle cx="50" cy="50" fill="transparent" r="40" stroke="#f1f5f9" stroke-width="10"></circle>
<circle class="drop-shadow-[0_0_8px_rgba(0,102,255,0.4)]" cx="50" cy="50" fill="transparent" r="40" stroke="#0066ff" stroke-dasharray="170 251" stroke-linecap="round" stroke-width="12"></circle>
<circle cx="50" cy="50" fill="transparent" r="40" stroke="#ba1a1a" stroke-dasharray="30 251" stroke-dashoffset="-170" stroke-linecap="round" stroke-width="12"></circle>
</svg>
<div class="absolute flex flex-col items-center">
<span class="text-4xl font-extrabold font-headline text-on-surface">942</span>
<span class="text-[10px] uppercase font-bold text-on-surface-variant tracking-widest opacity-60">Total Units</span>
</div>
</div>
<div class="mt-8 space-y-4">
<div class="flex items-center justify-between p-3 rounded-xl hover:bg-primary/5 transition-colors cursor-default group">
<div class="flex items-center gap-3">
<div class="w-3 h-3 rounded-full bg-primary group-hover:scale-125 transition-transform"></div>
<span class="text-sm font-semibold">Active</span>
</div>
<span class="text-sm font-bold text-primary">84%</span>
</div>
<div class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-100 transition-colors cursor-default group">
<div class="flex items-center gap-3">
<div class="w-3 h-3 rounded-full bg-surface-container-high group-hover:scale-125 transition-transform"></div>
<span class="text-sm font-semibold">Inactive</span>
</div>
<span class="text-sm font-bold">12%</span>
</div>
<div class="flex items-center justify-between p-3 rounded-xl hover:bg-error/5 transition-colors cursor-default group">
<div class="flex items-center gap-3">
<div class="w-3 h-3 rounded-full bg-error group-hover:scale-125 transition-transform"></div>
<span class="text-sm font-semibold">Suspended</span>
</div>
<span class="text-sm font-bold text-error">4%</span>
</div>
</div>
</div>
<!-- Top Performing Clinics Horizontal Bar Chart (revenue) -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<h4 class="text-lg font-extrabold font-headline mb-1">Top Performing</h4>
<p class="text-xs text-on-surface-variant font-medium mb-6">Total paid subscription revenue</p>
<div class="space-y-6">
<?php if (empty($top_performing)): ?>
<p class="text-sm text-on-surface-variant text-center py-4">No paid subscription revenue yet.</p>
<?php else: ?>
<?php foreach ($top_performing as $tp): ?>
<?php
$tp_rev = $tp['revenue'];
$tp_pct = $top_revenue_max > 0 ? ($tp_rev / $top_revenue_max) * 100 : 0;
$tp_pct = max(0, min(100, $tp_pct));
?>
<div class="space-y-2">
<div class="flex justify-between text-xs font-bold mb-1 gap-2">
<span class="truncate"><?php echo htmlspecialchars($tp['name']); ?></span>
<span class="text-primary shrink-0"><?php echo htmlspecialchars(dashboard_format_revenue($tp_rev)); ?></span>
</div>
<div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
<div class="h-full bg-primary rounded-full shadow-[0_0_10px_rgba(0,102,255,0.3)]" style="width: <?php echo htmlspecialchars(number_format($tp_pct, 2, '.', '')); ?>%;"></div>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<a href="salesreport.php" class="block w-full mt-8 py-3.5 bg-surface-container-low/50 border border-white hover:bg-white text-primary text-sm font-bold rounded-2xl transition-all shadow-sm text-center">Sales report</a>
</div>
</div>
</section>
<!-- Bottom Section: Recent Activity -->
<section class="grid grid-cols-1 gap-8">
<!-- Recent Activity Feed -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<div class="flex justify-between items-center mb-8">
<h4 class="text-xl font-extrabold font-headline">Recent Activity</h4>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-white shadow-sm hover:shadow-md transition-all">
<span class="material-symbols-outlined text-on-surface-variant text-xl">filter_list</span>
</button>
</div>
<div class="space-y-8 relative">
<div class="absolute left-5 top-2 bottom-2 w-[1.5px] bg-slate-100"></div>
<div class="relative flex gap-6 group">
<div class="z-10 w-10 h-10 rounded-2xl bg-blue-100 flex items-center justify-center border-4 border-white shadow-sm group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-base text-primary" style="font-variation-settings: 'FILL' 1;">add_business</span>
</div>
<div>
<p class="text-sm font-bold group-hover:text-primary transition-colors">New Tenant: <span>Stellar Orthodontics</span></p>
<p class="text-xs text-on-surface-variant mt-1.5 font-medium leading-relaxed">Provisioned Pro-Tier environment in US-East node.</p>
<p class="text-[10px] text-slate-400 mt-2.5 font-bold uppercase tracking-widest">24 mins ago</p>
</div>
</div>
<div class="relative flex gap-6 group">
<div class="z-10 w-10 h-10 rounded-2xl bg-green-100 flex items-center justify-center border-4 border-white shadow-sm group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-base text-green-600" style="font-variation-settings: 'FILL' 1;">check_circle</span>
</div>
<div>
<p class="text-sm font-bold group-hover:text-green-600 transition-colors">Billing Completed: <span>HealthFirst Group</span></p>
<p class="text-xs text-on-surface-variant mt-1.5 font-medium leading-relaxed">Annual subscription renewed successfully ($12,400).</p>
<p class="text-[10px] text-slate-400 mt-2.5 font-bold uppercase tracking-widest">2 hours ago</p>
</div>
</div>
<div class="relative flex gap-6 group">
<div class="z-10 w-10 h-10 rounded-2xl bg-amber-100 flex items-center justify-center border-4 border-white shadow-sm group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-base text-amber-600" style="font-variation-settings: 'FILL' 1;">sync</span>
</div>
<div>
<p class="text-sm font-bold group-hover:text-amber-600 transition-colors">System Update <span class="text-amber-600">v2.4.1</span></p>
<p class="text-xs text-on-surface-variant mt-1.5 font-medium leading-relaxed">Automatic backup completed for all European region shards.</p>
<p class="text-[10px] text-slate-400 mt-2.5 font-bold uppercase tracking-widest">5 hours ago</p>
</div>
</div>
</div>
</div>
</section>
</div>
</main>
</body></html>