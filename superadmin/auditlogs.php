<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auditlogs_tz_helper.php';

@date_default_timezone_set('Asia/Manila');

$auditLogStorageTz = new DateTimeZone('+00:00');

$totalLogs = 0;
$loginEvents = 0;
$logoutEvents = 0;
$totalEventRows = 0;
$eventRows = [];
$dbError = null;

try {
    // Rows use MySQL CURRENT_TIMESTAMP in the connection's zone (usually SYSTEM) — infer BEFORE SET +08.
    $auditLogStorageTz = auditlogs_infer_mysql_storage_timezone($pdo);

    try {
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        // Hosting may block SET time_zone; page still uses Manila for PHP display.
    }

    $totalLogs = (int) $pdo->query('SELECT COUNT(*) FROM tbl_audit_logs')->fetchColumn();

    $loginStmt = $pdo->query("
        SELECT COUNT(*)
        FROM tbl_audit_logs
        WHERE LOWER(action) LIKE '%login%'
    ");
    $loginEvents = (int) $loginStmt->fetchColumn();

    $logoutStmt = $pdo->query("
        SELECT COUNT(*)
        FROM tbl_audit_logs
        WHERE LOWER(action) LIKE '%logout%'
    ");
    $logoutEvents = (int) $logoutStmt->fetchColumn();

    $eventsStmt = $pdo->query("
        SELECT
            l.log_id,
            l.user_id,
            l.action,
            l.ip_address,
            l.created_at,
            u.full_name
        FROM tbl_audit_logs l
        LEFT JOIN tbl_users u ON u.user_id = l.user_id
        WHERE LOWER(l.action) LIKE '%login%' OR LOWER(l.action) LIKE '%logout%'
        ORDER BY l.created_at DESC, l.log_id DESC
    ");
    $eventRows = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalEventRows = count($eventRows);
} catch (Throwable $e) {
    $dbError = 'Unable to load audit logs right now.';
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Audit Logs | Clinical Precision</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
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
                        "headline": ["Manrope", "Plus Jakarta Sans", "sans-serif"],
                        "body": ["Manrope", "Inter", "sans-serif"],
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
$superadmin_nav = 'auditlogs';
$superadmin_header_search_placeholder = 'Search system logs...';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';
?>
<!-- Main Content Area -->
<main class="ml-64 pt-20 min-h-screen">
<div class="pt-8 px-10 pb-16 space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Header Section -->
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Audit Logs</h2>
<p class="text-on-surface-variant mt-2 font-medium">Track and monitor system activities across all clinic modules.</p>
</div>
<div class="flex items-center gap-3">
<a href="auditlogs_export_pdf.php" target="_blank" rel="noopener noreferrer" class="inline-flex bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all no-underline">
<span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                    PDF Export
                </a>
</div>
</section>
<!-- Metrics Grid -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">history</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+12%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Logs</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($totalLogs); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-error/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-error-container/10 text-error rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">login</span>
</div>
<span class="text-[10px] font-extrabold text-error bg-error-container px-2 py-1 rounded-lg uppercase">Live Data</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Login Events</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($loginEvents); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">logout</span>
</div>
<span class="text-[10px] font-extrabold text-primary bg-primary/5 px-2 py-1 rounded-lg uppercase">Live Data</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Logout Events</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($logoutEvents); ?></h3>
</div>
</section>
<!-- Action Center Buttons -->
<div class="flex items-center gap-3">
<button class="px-6 py-2.5 bg-white/60 text-primary text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">table_chart</span> Excel Export
            </button>
<button class="px-6 py-2.5 bg-white/60 text-error text-sm font-bold rounded-xl border border-white hover:bg-error hover:text-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">delete_sweep</span> Clear Logs
            </button>
</div>
<!-- Table Container -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<!-- Table Controls -->
<div class="px-8 py-6 flex flex-wrap items-center justify-between gap-4 border-b border-white/50">
<div class="flex items-center gap-4">
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>Last 7 Days</option>
<option>Last 30 Days</option>
<option>Last Quarter</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">expand_more</span>
</div>
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>Action Type: All</option>
<option>Security Updates</option>
<option>Patient Records</option>
<option>Financial Updates</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">filter_list</span>
</div>
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>Status: All</option>
<option>Completed</option>
<option>Pending</option>
<option>Failed</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">tune</span>
</div>
</div>
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
                    Showing <span class="text-primary opacity-100"><?php echo $totalEventRows === 0 ? '0' : ('1-' . $totalEventRows); ?></span> of <?php echo number_format($totalEventRows); ?> results
                </div>
</div>
<!-- Table Content -->
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">User</th>
<th class="px-8 py-5">Action</th>
<th class="px-8 py-5">Date &amp; Time</th>
<th class="px-8 py-5">Status</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if ($dbError !== null): ?>
<tr>
<td class="px-10 py-5 text-sm text-error font-bold" colspan="4"><?php echo htmlspecialchars($dbError); ?></td>
</tr>
<?php elseif (empty($eventRows)): ?>
<tr>
<td class="px-10 py-5 text-sm text-on-surface-variant font-bold" colspan="4">No login/logout events found.</td>
</tr>
<?php else: ?>
<?php foreach ($eventRows as $row): ?>
<?php
    $action = (string) ($row['action'] ?? '');
    $lowerAction = strtolower($action);
    $isLogin = strpos($lowerAction, 'login') !== false;
    $isLogout = strpos($lowerAction, 'logout') !== false;
    $statusLabel = $isLogout ? 'Logout' : 'Login';
    $statusClasses = $isLogout
        ? 'bg-amber-50 text-amber-600'
        : 'bg-green-50 text-green-600';
    $dotClass = $isLogout ? 'bg-amber-600' : 'bg-green-600';
    $displayName = trim((string) ($row['full_name'] ?? ''));
    if ($displayName === '') {
        $displayName = trim((string) ($row['user_id'] ?? ''));
    }
    if ($displayName === '') {
        $displayName = 'System';
    }

    $createdAtRaw = trim((string) ($row['created_at'] ?? ''));
    // If MySQL returns microseconds, strip them so DateTime parsing is predictable.
    $createdAtRaw = preg_replace('/\.\d+$/', '', $createdAtRaw);

    $fmt = auditlogs_format_created_at_manila($createdAtRaw, $auditLogStorageTz);
    $createdAtDate = $fmt['date'];
    $createdAtTime = $fmt['time'];
?>
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div>
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($displayName); ?></p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider">
<?php echo htmlspecialchars((string) ($row['ip_address'] ?: 'Unknown IP')); ?>
</p>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($action); ?></span>
</td>
<td class="px-8 py-5">
<div class="text-xs">
<p class="text-on-surface font-black"><?php echo htmlspecialchars($createdAtDate); ?></p>
<p class="text-on-surface-variant font-bold"><?php echo htmlspecialchars($createdAtTime); ?></p>
</div>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 <?php echo $statusClasses; ?> rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full <?php echo $dotClass; ?>"></span> <?php echo $statusLabel; ?>
</span>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<!-- Pagination -->
<div class="px-10 py-8 flex items-center justify-between border-t border-white/50">
<button class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </button>
<div class="flex items-center gap-2">
<button class="w-10 h-10 bg-primary text-white rounded-xl font-bold text-sm active-glow">1</button>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">2</button>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">3</button>
<span class="px-2 opacity-40">...</span>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">1284</button>
</div>
<button class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
</button>
</div>
</div>
<!-- Footer Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
<div class="p-10 bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow flex items-center justify-between group cursor-pointer hover:-translate-y-1 transition-all">
<div>
<h4 class="text-xl font-extrabold font-headline text-on-surface">Export Center</h4>
<p class="text-sm text-on-surface-variant mt-2 font-medium">Access all generated audit files and security reports.</p>
<button class="mt-6 px-6 py-2.5 bg-primary/10 text-primary hover:bg-primary hover:text-white rounded-2xl text-xs font-bold transition-all flex items-center gap-2">
                        Manage Exports
                        <span class="material-symbols-outlined text-sm">settings</span>
</button>
</div>
<div class="w-24 h-24 rounded-[2rem] bg-primary/5 flex items-center justify-center group-hover:bg-primary/10 transition-colors">
<span class="material-symbols-outlined text-4xl text-primary">folder_open</span>
</div>
</div>
<div class="p-10 bg-gradient-to-br from-[#ffdcc3] to-[#ffb77e] rounded-[2.5rem] shadow-xl shadow-orange-900/10 flex items-center justify-between group cursor-pointer hover:-translate-y-1 transition-all">
<div>
<h4 class="text-xl font-extrabold font-headline text-[#2f1500]">System Health</h4>
<p class="text-sm text-[#6e3900]/80 mt-2 font-medium leading-relaxed">Infrastructure status and system monitoring metrics.</p>
<button class="mt-6 px-6 py-2.5 bg-white/30 text-[#2f1500] hover:bg-white/50 rounded-2xl text-xs font-bold transition-all flex items-center gap-2">
                        View Status
                        <span class="material-symbols-outlined text-sm">cloud_done</span>
</button>
</div>
<div class="w-24 h-24 rounded-[2rem] bg-white/20 flex items-center justify-center group-hover:scale-105 transition-transform">
<span class="material-symbols-outlined text-4xl text-[#2f1500]">analytics</span>
</div>
</div>
</div>
</div>
</main>
</body></html>