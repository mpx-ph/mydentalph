<?php require_once __DIR__ . '/require_superadmin.php'; ?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Sales Report | Clinical Precision</title>
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
    </style>
</head>
<body class="mesh-bg font-body text-on-surface antialiased min-h-screen">
<?php
$superadmin_nav = 'salesreport';
$superadmin_header_search_placeholder = 'Search clinic data...';
require_once __DIR__ . '/../db.php';
date_default_timezone_set('Asia/Manila');

function salesreport_format_money_card(float $amount): string
{
    if ($amount >= 1000000) {
        return '₱' . number_format($amount / 1000000, 1, '.', '') . 'M';
    }
    if ($amount >= 1000) {
        return '₱' . number_format($amount / 1000, 1, '.', '') . 'k';
    }
    return '₱' . number_format($amount, 0, '.', ',');
}

function salesreport_format_money_exact(float $amount): string
{
    return '₱' . number_format($amount, 2, '.', ',');
}

function salesreport_format_date_for_table(string $dateTime): string
{
    $ts = strtotime($dateTime);
    return $ts ? date('M j, Y', $ts) : $dateTime;
}

// Revenue stats use paid subscription payments across all tenants.
$todayStart = new DateTime('today');
$todayStart->setTime(0, 0, 0);
$todayEnd = clone $todayStart;
$todayEnd->modify('+1 day');
$todayRevenue = 0.0;
$weekRevenue = 0.0;
$monthRevenue = 0.0;
$yearRevenue = 0.0;

$weekStart = clone $todayStart;
$weekdayN = (int) $weekStart->format('N'); // 1..7 (Mon..Sun)
$weekStart->modify('-' . ($weekdayN - 1) . ' days');
$weekEnd = clone $weekStart;
$weekEnd->modify('+7 days');

$monthStart = new DateTime('first day of this month');
$monthStart->setTime(0, 0, 0);
$monthEnd = clone $monthStart;
$monthEnd->modify('+1 month');

$yearStart = new DateTime('first day of January ' . $todayStart->format('Y'));
$yearStart->setTime(0, 0, 0);
$yearEnd = clone $yearStart;
$yearEnd->modify('+1 year');

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as revenue
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND created_at >= ?
          AND created_at < ?
    ");

    // NOTE: tbl_tenant_subscriptions uses created_at as the transaction date.
    $stmt->execute([$todayStart->format('Y-m-d H:i:s'), $todayEnd->format('Y-m-d H:i:s')]);
    $todayRevenue = (float) ($stmt->fetch()['revenue'] ?? 0);

    $stmt->execute([$weekStart->format('Y-m-d H:i:s'), $weekEnd->format('Y-m-d H:i:s')]);
    $weekRevenue = (float) ($stmt->fetch()['revenue'] ?? 0);

    $stmt->execute([$monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
    $monthRevenue = (float) ($stmt->fetch()['revenue'] ?? 0);

    $stmt->execute([$yearStart->format('Y-m-d H:i:s'), $yearEnd->format('Y-m-d H:i:s')]);
    $yearRevenue = (float) ($stmt->fetch()['revenue'] ?? 0);
} catch (Exception $e) {
    error_log('salesreport revenue stats error: ' . $e->getMessage());
}

// Recent daily revenue: last 5 days (including today), paid subscriptions.
$recentDailyRevenue = [];
$dailyStart = clone $todayStart;
$dailyStart->modify('-4 days');
$dailyEnd = clone $todayEnd; // exclusive

try {
    $stmt = $pdo->prepare("
        SELECT
            DATE(created_at) as payment_day,
            COALESCE(SUM(amount_paid), 0) as revenue
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND created_at >= ?
          AND created_at < ?
        GROUP BY DATE(created_at)
        ORDER BY payment_day ASC
    ");
    $stmt->execute([$dailyStart->format('Y-m-d H:i:s'), $dailyEnd->format('Y-m-d H:i:s')]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $revenueByDay = [];
    foreach ($rows as $row) {
        $dayKey = (string) ($row['payment_day'] ?? '');
        if ($dayKey !== '') {
            $revenueByDay[$dayKey] = (float) ($row['revenue'] ?? 0);
        }
    }

    // Render latest -> oldest
    for ($i = 4; $i >= 0; $i--) {
        $d = clone $dailyStart;
        $d->modify('+' . $i . ' days');
        $key = $d->format('Y-m-d');
        $recentDailyRevenue[] = [
            'label' => $d->format('M j'),
            'revenue' => (float) ($revenueByDay[$key] ?? 0),
        ];
    }
} catch (Exception $e) {
    error_log('salesreport recent daily revenue error: ' . $e->getMessage());
}

// Recent transactions table: latest 5 paid subscription records across all tenants.
$recentTransactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            ts.created_at as payment_date,
            ts.amount_paid as amount,
            ts.payment_status as status,
            ts.reference_number as payment_id,
            NULL as booking_id,
            sp.plan_name as service_type,
            t.clinic_name
        FROM tbl_tenant_subscriptions ts
        LEFT JOIN tbl_tenants t ON ts.tenant_id = t.tenant_id
        LEFT JOIN tbl_subscription_plans sp ON ts.plan_id = sp.plan_id
        WHERE ts.payment_status = 'paid'
        ORDER BY ts.created_at DESC, ts.id DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('salesreport recent transactions error: ' . $e->getMessage());
}

// Top clinics ranking by total paid subscription spend.
$topClinics = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            t.clinic_name,
            COUNT(ts.id) as paid_transactions,
            COALESCE(SUM(ts.amount_paid), 0) as total_spend
        FROM tbl_tenant_subscriptions ts
        INNER JOIN tbl_tenants t ON ts.tenant_id = t.tenant_id
        WHERE ts.payment_status = 'paid'
        GROUP BY ts.tenant_id, t.clinic_name
        ORDER BY total_spend DESC, paid_transactions DESC, t.clinic_name ASC
    ");
    $stmt->execute();
    $topClinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('salesreport top clinics error: ' . $e->getMessage());
}

require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';
?>
<main class="ml-64 pt-20 min-h-screen">
<div class="pt-8 px-10 pb-16 space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Page Header -->
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Sales Report</h2>
<p class="text-on-surface-variant mt-2 font-medium">View and analyze clinic sales performance across all branches.</p>
</div>
<div class="flex items-center gap-3">
<button id="open-sales-export-modal" type="button" class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                        PDF Export
                    </button>
</div>
</section>
<!-- Filters Bar (Glassmorphism) -->
<div class="flex flex-wrap items-center gap-4">
<div class="bg-white/60 backdrop-blur-md px-6 py-3 rounded-2xl editorial-shadow flex items-center gap-4">
<div class="flex items-center gap-2 text-primary font-bold bg-primary/5 px-3 py-1.5 rounded-xl text-xs">
<span class="material-symbols-outlined text-[18px]">calendar_today</span>
                        Last 30 Days
                    </div>
<div class="h-6 w-px bg-outline-variant/30"></div>
<div class="relative group">
<select class="appearance-none bg-transparent border-none text-sm font-bold text-on-surface cursor-pointer focus:ring-0 pr-8">
<option>All Clinics</option>
<option>Downtown Branch</option>
<option>Eastside Medical</option>
</select>
<span class="material-symbols-outlined absolute right-0 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-lg">expand_more</span>
</div>
<div class="relative group">
<select class="appearance-none bg-transparent border-none text-sm font-bold text-on-surface cursor-pointer focus:ring-0 pr-8">
<option>All Services</option>
<option>Orthodontics</option>
<option>Implants</option>
<option>Cleaning</option>
</select>
<span class="material-symbols-outlined absolute right-0 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-lg">filter_list</span>
</div>
</div>
</div>
<!-- Summary Cards (Styled like SCREEN_4 metrics) -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">calendar_today</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Today Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline tracking-tighter"><?php echo htmlspecialchars(salesreport_format_money_card((float) $todayRevenue)); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">schedule</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">This Week Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline tracking-tighter"><?php echo htmlspecialchars(salesreport_format_money_card((float) $weekRevenue)); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-primary/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-primary/5 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">This Month Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline tracking-tighter"><?php echo htmlspecialchars(salesreport_format_money_card((float) $monthRevenue)); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">campaign</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">This Year Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline tracking-tighter"><?php echo htmlspecialchars(salesreport_format_money_card((float) $yearRevenue)); ?></h3>
</div>
</section>
<div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
<!-- Recent Daily Revenue -->
<section class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-10 editorial-shadow overflow-hidden">
<div class="flex items-start justify-between gap-4">
<div>
<h3 class="text-xl font-extrabold font-headline text-on-surface tracking-tight">Recent Daily Revenue</h3>
<p class="text-sm text-on-surface-variant font-medium mt-1">Revenue per day (last 5 days, paid subscriptions)</p>
</div>
<div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest opacity-60">
<span class="material-symbols-outlined text-lg">insights</span>
<span>Last 5 Days</span>
</div>
</div>
<div class="overflow-x-auto mt-8">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-8 py-5">Date</th>
<th class="px-8 py-5 text-right">Revenue</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if (empty($recentDailyRevenue)): ?>
<tr>
<td class="px-8 py-6 text-sm font-bold text-on-surface-variant" colspan="2">No revenue data.</td>
</tr>
<?php else: ?>
<?php foreach ($recentDailyRevenue as $day): ?>
<tr class="hover:bg-primary/5 transition-colors">
<td class="px-8 py-6 text-sm font-bold text-on-surface-variant"><?php echo htmlspecialchars((string) $day['label']); ?></td>
<td class="px-8 py-6 text-right text-sm font-black text-on-surface"><?php echo htmlspecialchars(salesreport_format_money_exact((float) $day['revenue'])); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="px-8 py-5 border-t border-white/50">
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
<?php echo htmlspecialchars(number_format(count($recentDailyRevenue))); ?> results · Page 1 of 1
</p>
</div>
</section>

<!-- Top Clinics Ranking -->
<section class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<div class="px-10 py-8 flex items-center justify-between border-b border-white/50">
<div>
<h3 class="text-xl font-extrabold font-headline text-on-surface tracking-tight">Top Clinics</h3>
<p class="text-sm text-on-surface-variant font-medium mt-1">Ranked by total paid subscription spend</p>
</div>
<div class="flex items-center gap-2 text-[10px] font-bold uppercase tracking-widest opacity-60">
<span class="material-symbols-outlined text-lg">leaderboard</span>
<span>All Clinics</span>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Rank</th>
<th class="px-8 py-5">Clinic</th>
<th class="px-8 py-5 text-right">Paid Transactions</th>
<th class="px-10 py-5 text-right">Total Spend</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if (empty($topClinics)): ?>
<tr>
<td class="px-10 py-6 text-sm font-bold text-on-surface-variant" colspan="4">No paid subscription data found.</td>
</tr>
<?php else: ?>
<?php foreach ($topClinics as $idx => $clinic): ?>
<?php
$rank = $idx + 1;
$rankBadgeClasses = 'bg-surface-container-high text-on-surface-variant';
if ($rank === 1) {
    $rankBadgeClasses = 'bg-amber-50 text-amber-600';
} elseif ($rank === 2) {
    $rankBadgeClasses = 'bg-slate-100 text-slate-600';
} elseif ($rank === 3) {
    $rankBadgeClasses = 'bg-orange-50 text-orange-600';
}
?>
<tr class="hover:bg-primary/5 transition-colors">
<td class="px-10 py-6">
<span class="px-3 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wider <?php echo htmlspecialchars($rankBadgeClasses); ?>">
<?php echo htmlspecialchars((string) $rank); ?>
</span>
</td>
<td class="px-8 py-6 text-sm font-bold text-on-surface"><?php echo htmlspecialchars((string) ($clinic['clinic_name'] ?? 'Unknown Clinic')); ?></td>
<td class="px-8 py-6 text-right text-sm font-bold text-on-surface-variant"><?php echo htmlspecialchars(number_format((int) ($clinic['paid_transactions'] ?? 0))); ?></td>
<td class="px-10 py-6 text-right text-sm font-black text-on-surface"><?php echo htmlspecialchars(salesreport_format_money_exact((float) ($clinic['total_spend'] ?? 0))); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="px-10 py-5 border-t border-white/50">
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
<?php echo htmlspecialchars(number_format(count($topClinics))); ?> results · Page 1 of 1
</p>
</div>
</section>
</div>

<!-- Recent Transactions Table (Styled like SCREEN_4 table) -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<div class="px-10 py-8 flex items-center justify-between border-b border-white/50">
<h3 class="text-xl font-extrabold font-headline text-on-surface tracking-tight">Recent Transactions</h3>
<button class="text-primary font-bold text-sm hover:underline">View All History</button>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Date</th>
<th class="px-8 py-5">Tenant/Clinic</th>
<th class="px-8 py-5">Service</th>
<th class="px-8 py-5">Amount</th>
<th class="px-8 py-5">Status</th>
<th class="px-10 py-5 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if (empty($recentTransactions)): ?>
<tr>
<td class="px-10 py-6 text-sm font-bold text-on-surface-variant" colspan="6">No recent transactions found.</td>
</tr>
<?php else: ?>
<?php foreach ($recentTransactions as $tx): ?>
<?php
$status = (string) ($tx['status'] ?? '');
$badgeLabel = 'Unknown';
$badgeClasses = 'bg-on-surface-variant/5 text-on-surface-variant';
$dotClasses = 'bg-on-surface-variant';
if ($status === 'paid') {
    $badgeLabel = 'Paid';
    $badgeClasses = 'bg-green-50 text-green-600';
    $dotClasses = 'bg-green-600';
} elseif ($status === 'pending') {
    $badgeLabel = 'Pending';
    $badgeClasses = 'bg-amber-50 text-amber-600';
    $dotClasses = 'bg-amber-600';
} elseif ($status === 'failed' || $status === 'cancelled') {
    $badgeLabel = 'Cancelled';
    $badgeClasses = 'bg-error/10 text-error';
    $dotClasses = 'bg-error';
} else {
    $badgeLabel = ucfirst($status);
}

$clinicName = (string) ($tx['clinic_name'] ?? 'Unknown Clinic');
$serviceType = (string) ($tx['service_type'] ?? 'N/A');
$amount = (float) ($tx['amount'] ?? 0);
?>
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-6 text-sm font-bold text-on-surface-variant"><?php echo htmlspecialchars(salesreport_format_date_for_table((string) ($tx['payment_date'] ?? ''))); ?></td>
<td class="px-8 py-6">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-primary shadow-sm border border-white">
<span class="material-symbols-outlined text-lg">domain</span>
</div>
<span class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($clinicName); ?></span>
</div>
</td>
<td class="px-8 py-6">
<span class="px-3 py-1.5 bg-primary/5 text-primary rounded-xl text-[10px] font-bold uppercase tracking-wider"><?php echo htmlspecialchars($serviceType); ?></span>
</td>
<td class="px-8 py-6 font-black text-sm text-on-surface"><?php echo htmlspecialchars(salesreport_format_money_exact($amount)); ?></td>
<td class="px-8 py-6">
<span class="px-3 py-1.5 <?php echo htmlspecialchars($badgeClasses); ?> rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full <?php echo htmlspecialchars($dotClasses); ?>"></span>
                                        <?php echo htmlspecialchars($badgeLabel); ?>
</span>
</td>
<td class="px-10 py-6 text-right">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors">
<span class="material-symbols-outlined">more_horiz</span>
</button>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="px-10 py-5 border-t border-white/50">
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
<?php echo htmlspecialchars(number_format(count($recentTransactions))); ?> results · Page 1 of 1
</p>
</div>
</div>
</div>
</main>
<!-- Floating Action Button (Matches SCREEN_4 aesthetic) -->
<div class="fixed bottom-10 right-10 z-50">
<button class="w-16 h-16 rounded-3xl bg-primary text-white primary-glow flex items-center justify-center hover:scale-110 active:scale-95 transition-all">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">add</span>
</button>
</div>
<!-- Sales Report Export Modal -->
<div id="sales-export-modal" class="fixed inset-0 z-[70] hidden export-modal-backdrop items-center justify-center p-4 sm:p-8">
<div class="export-modal-panel w-full max-w-3xl rounded-[2rem] overflow-hidden">
<div class="px-8 py-6 border-b border-outline-variant/40 flex items-start justify-between gap-4">
<div>
<h3 class="text-2xl font-extrabold tracking-tight">
<span class="text-on-surface">Export</span> <span class="text-primary">Options</span>
</h3>
<p class="mt-2 text-xs font-bold uppercase tracking-[0.2em] text-on-surface-variant/60">Select categories to include in your report</p>
</div>
<button id="close-sales-export-modal" type="button" class="w-14 h-14 rounded-2xl bg-surface-container-low hover:bg-white transition-colors flex items-center justify-center text-on-surface-variant">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form action="salesreport_export_pdf.php" method="get" class="max-h-[70vh] overflow-y-auto p-8 space-y-7">
<div>
<h4 class="text-sm font-bold uppercase tracking-[0.16em] text-on-surface-variant/70 mb-4">Revenue Breakdown</h4>
<div class="space-y-4">
<label class="export-option-card rounded-3xl p-5 flex items-center justify-between gap-3 cursor-pointer">
<span class="flex items-center gap-3">
<input type="hidden" name="include_today" value="0"/>
<input type="checkbox" name="include_today" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Today's Revenue</span>
</span>
<span class="text-primary font-black"><?php echo htmlspecialchars(salesreport_format_money_exact((float) $todayRevenue)); ?></span>
</label>
<label class="export-option-card rounded-3xl p-5 flex items-center justify-between gap-3 cursor-pointer">
<span class="flex items-center gap-3">
<input type="hidden" name="include_week" value="0"/>
<input type="checkbox" name="include_week" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Weekly Revenue</span>
</span>
<span class="text-primary font-black"><?php echo htmlspecialchars(salesreport_format_money_exact((float) $weekRevenue)); ?></span>
</label>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<label class="export-option-card rounded-3xl p-5 flex items-center justify-between gap-3 cursor-pointer">
<span class="flex items-center gap-3">
<input type="hidden" name="include_month" value="0"/>
<input type="checkbox" name="include_month" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Monthly</span>
</span>
<span class="text-primary font-black"><?php echo htmlspecialchars(salesreport_format_money_exact((float) $monthRevenue)); ?></span>
</label>
<label class="export-option-card rounded-3xl p-5 flex items-center justify-between gap-3 cursor-pointer">
<span class="flex items-center gap-3">
<input type="hidden" name="include_year" value="0"/>
<input type="checkbox" name="include_year" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Yearly</span>
</span>
<span class="text-primary font-black"><?php echo htmlspecialchars(salesreport_format_money_exact((float) $yearRevenue)); ?></span>
</label>
</div>
<label class="export-option-card rounded-3xl p-5 flex items-center gap-3 cursor-pointer">
<input type="hidden" name="include_daily" value="0"/>
<input type="checkbox" name="include_daily" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Daily Revenue List (Recent)</span>
</label>
<label class="export-option-card rounded-3xl p-5 flex items-center gap-3 cursor-pointer">
<input type="hidden" name="include_transactions" value="0"/>
<input type="checkbox" name="include_transactions" value="1" class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Full Transaction Log (Paid Subscriptions)</span>
</label>
</div>
</div>
<div>
<h4 class="text-sm font-bold uppercase tracking-[0.16em] text-on-surface-variant/70 mb-4">Other Insights</h4>
<label class="export-option-card rounded-3xl p-5 flex items-center gap-3 cursor-pointer">
<input type="hidden" name="include_top_clinics" value="0"/>
<input type="checkbox" name="include_top_clinics" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Clinic Performance Leaderboard</span>
</label>
</div>
<div class="pt-2 flex justify-end gap-3">
<button type="button" id="cancel-sales-export-modal" class="px-6 py-3 rounded-2xl text-sm font-bold text-on-surface-variant bg-surface-container-low hover:bg-white transition-colors">Cancel</button>
<button type="submit" class="px-7 py-3 rounded-2xl text-sm font-bold text-white bg-primary hover:brightness-110 transition-colors">Download PDF</button>
</div>
</form>
</div>
</div>
<script>
(function () {
    var openBtn = document.getElementById('open-sales-export-modal');
    var closeBtn = document.getElementById('close-sales-export-modal');
    var cancelBtn = document.getElementById('cancel-sales-export-modal');
    var modal = document.getElementById('sales-export-modal');
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