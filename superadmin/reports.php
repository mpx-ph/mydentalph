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
    </style>
</head>
<body class="mesh-bg font-body text-on-surface antialiased min-h-screen">
<?php
$superadmin_nav = 'reports';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';

date_default_timezone_set('Asia/Manila');

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

$dbError = null;
$yesterdayStart = null;
$yesterdayEnd = null;
$yesterdayLabel = '';

// Note: this file uses the shared `$pdo` provided by `superadmin_sidebar.php`.
$totalMyDentalVisits = 0;
$userRegistrationsTotal = 0;
$registrationRows = [];

try {
    $yesterdayStart = new DateTime('yesterday');
    $yesterdayStart->setTime(0, 0, 0);
    $yesterdayEnd = clone $yesterdayStart;
    $yesterdayEnd->modify('+1 day');
    $yesterdayLabel = $yesterdayStart->format('M j, Y');

    $startStr = $yesterdayStart->format('Y-m-d H:i:s');
    $endStr = $yesterdayEnd->format('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT ip_address) AS cnt
            FROM tbl_website_visits
            WHERE created_at >= ?
              AND created_at < ?
              AND ip_address IS NOT NULL
              AND TRIM(ip_address) <> ''
        ");
        $stmt->execute([$startStr, $endStr]);
        $totalMyDentalVisits = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    } catch (Throwable $e) {
        error_log('superadmin/reports.php tbl_website_visits: ' . $e->getMessage());
        $totalMyDentalVisits = 0;
    }

    // Registrations created yesterday (tbl_users.created_at).
    $stmt = $pdo->prepare('
        SELECT COUNT(*) AS cnt
        FROM tbl_users
        WHERE created_at >= ?
          AND created_at < ?
    ');
    $stmt->execute([$startStr, $endStr]);
    $userRegistrationsTotal = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    // Table: last_active preferred when column exists (migration 006).
    $sqlReg = "
        SELECT
            DATE(u.created_at) AS created_date,
            t.clinic_name AS tenant_name,
            COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS user_name,
            u.email AS user_email,
            COALESCE(u.last_active, u.last_login) AS last_active_at
        FROM tbl_users u
        LEFT JOIN tbl_tenants t ON t.tenant_id = u.tenant_id
        WHERE u.created_at >= ?
          AND u.created_at < ?
        ORDER BY u.created_at DESC
    ";
    try {
        $stmt = $pdo->prepare($sqlReg);
        $stmt->execute([$startStr, $endStr]);
        $registrationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("
            SELECT
                DATE(u.created_at) AS created_date,
                t.clinic_name AS tenant_name,
                COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS user_name,
                u.email AS user_email,
                u.last_login AS last_active_at
            FROM tbl_users u
            LEFT JOIN tbl_tenants t ON t.tenant_id = u.tenant_id
            WHERE u.created_at >= ?
              AND u.created_at < ?
            ORDER BY u.created_at DESC
        ");
        $stmt->execute([$startStr, $endStr]);
        $registrationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $dbError = 'Unable to load reports.';
    error_log('superadmin/reports.php: ' . $e->getMessage());
}
?>
<!-- Main Content Area -->
<main class="ml-64 pt-20 min-h-screen">
<div class="pt-8 px-10 pb-16 space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Header Section -->
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Reports</h2>
<p class="text-on-surface-variant mt-2 font-medium">View and generate detailed reports</p>
</div>
<div class="flex items-center gap-3">
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">add_circle</span>
                        Generate New Report
                    </button>
</div>
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
    Yesterday · <?php echo htmlspecialchars($yesterdayLabel ?: '—', ENT_QUOTES, 'UTF-8'); ?>
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
    Yesterday · <?php echo htmlspecialchars($yesterdayLabel ?: '—', ENT_QUOTES, 'UTF-8'); ?>
</p>
</div>
</section>
<!-- Export Buttons -->
<div class="flex items-center gap-3">
<button class="px-6 py-2.5 bg-white/60 text-primary text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">picture_as_pdf</span> Export PDF
                </button>
<button class="px-6 py-2.5 bg-white/60 text-primary text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">table_chart</span> Export Excel
                </button>
</div>
<!-- Table Container -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<!-- Table Controls -->
<div class="px-8 py-6 flex flex-wrap items-center justify-between gap-4 border-b border-white/50">
<div class="flex items-center gap-4">
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>Yesterday</option>
<option>Last 7 Days</option>
<option>Last 30 Days</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">expand_more</span>
</div>
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>All Clinics</option>
<option>Downtown Branch</option>
<option>Westside Dental</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">filter_list</span>
</div>
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>All Types</option>
<option>Financial</option>
<option>Staff Performance</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">tune</span>
</div>
</div>
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
    <?php $registrationRowsCount = count($registrationRows); ?>
    Showing <span class="text-primary opacity-100"><?php echo htmlspecialchars($registrationRowsCount > 0 ? ('1-' . (string) $registrationRowsCount) : '0', ENT_QUOTES, 'UTF-8'); ?></span>
    of <?php echo htmlspecialchars(number_format($userRegistrationsTotal), ENT_QUOTES, 'UTF-8'); ?> registrations
</div>
</div>
<!-- Table Content -->
<div class="overflow-x-auto">
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
            No registrations for this day.
        <?php endif; ?>
    </td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>
</body></html>