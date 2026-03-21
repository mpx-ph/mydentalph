<?php
session_start();
require_once __DIR__ . '/../db.php';

$role = $_SESSION['role'] ?? null;
if ($role !== 'superadmin') {
    header('Location: ProviderLogin.php?redirect=ProviderSuperAdmin.php');
    exit;
}

$total_websites = 0;
$active_subscriptions = 0;
$total_revenue = 0.00;
$clinics = [];

try {
    // Total Websites = count of tenants
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_tenants");
    $total_websites = (int) $stmt->fetchColumn();

    // Active Subscriptions = count of tenants with subscription_status = 'active'
    $stmt = $pdo->query("SELECT COUNT(*) FROM tbl_tenants WHERE subscription_status = 'active'");
    $active_subscriptions = (int) $stmt->fetchColumn();

    // Total Revenue = sum of amount_paid from paid tenant subscriptions (plan-based)
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(amount_paid), 0) AS total
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid' AND amount_paid IS NOT NULL
    ");
    $total_revenue = (float) $stmt->fetchColumn();

    // Fetch clinics (tenants) with operator and subscription/plan info
    $stmt = $pdo->query("
        SELECT
            t.tenant_id,
            t.clinic_name,
            t.clinic_slug,
            t.subscription_status,
            t.created_at,
            u.full_name AS operator_name,
            latest.plan_name
        FROM tbl_tenants t
        LEFT JOIN tbl_users u
            ON t.owner_user_id = u.user_id
        LEFT JOIN (
            SELECT ts.tenant_id, sp.plan_name
            FROM tbl_tenant_subscriptions ts
            JOIN tbl_subscription_plans sp ON ts.plan_id = sp.plan_id
            WHERE ts.payment_status = 'paid'
              AND ts.id = (
                SELECT MAX(ts2.id)
                FROM tbl_tenant_subscriptions ts2
                WHERE ts2.tenant_id = ts.tenant_id
                  AND ts2.payment_status = 'paid'
              )
        ) AS latest
            ON latest.tenant_id = t.tenant_id
        ORDER BY t.created_at DESC
    ");
    $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // $total_websites, $active_subscriptions, $total_revenue, $clinics already set above
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background-light": "#f8f6f6",
                        "background-dark": "#101922",
                        "card-dark": "#1a2634",
                        "border-dark": "#2d3d50",
                    },
                    fontFamily: {
                        "display": ["Public Sans"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        body { font-family: 'Public Sans', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen">
<div class="flex h-screen overflow-hidden">
<!-- Sidebar -->
<aside class="w-64 bg-slate-900 dark:bg-[#0a0f16] flex flex-col border-r border-slate-200 dark:border-border-dark">
<div class="p-6 flex items-center gap-3">
<div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center text-white">
<span class="material-symbols-outlined">dentistry</span>
</div>
<h1 class="text-xl font-bold text-white tracking-tight">MyDental</h1>
</div>
<nav class="flex-1 px-4 py-4 space-y-1">
<a class="flex items-center gap-3 px-3 py-2 text-white bg-primary rounded-lg" href="#">
<span class="material-symbols-outlined">dashboard</span>
<span class="font-medium">Dashboard</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors" href="#">
<span class="material-symbols-outlined">domain</span>
<span class="font-medium">Clinics Registry</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors" href="#">
<span class="material-symbols-outlined">subscriptions</span>
<span class="font-medium">Subscriptions</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors" href="#">
<span class="material-symbols-outlined">payments</span>
<span class="font-medium">Revenue</span>
</a>
<div class="pt-4 pb-2">
<p class="px-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">System</p>
</div>
<a class="flex items-center gap-3 px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors" href="#">
<span class="material-symbols-outlined">settings</span>
<span class="font-medium">Settings</span>
</a>
<a class="flex items-center gap-3 px-3 py-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors" href="#">
<span class="material-symbols-outlined">help</span>
<span class="font-medium">Support</span>
</a>
</nav>
<div class="p-4 border-t border-slate-800">
<button class="w-full flex items-center justify-center gap-2 py-2.5 bg-primary hover:bg-primary/90 text-white font-semibold rounded-xl transition-all shadow-lg shadow-primary/20">
<span class="material-symbols-outlined text-sm">add</span>
<span>Add New Clinic</span>
</button>
</div>
</aside>
<!-- Main Content -->
<main class="flex-1 flex flex-col overflow-y-auto bg-background-light dark:bg-background-dark">
<!-- Header -->
<header class="h-16 flex items-center justify-between px-8 bg-white dark:bg-card-dark border-b border-slate-200 dark:border-border-dark sticky top-0 z-10">
<div class="flex items-center gap-4 flex-1">
<div class="relative w-full max-w-md">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xl">search</span>
<input class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 border-none focus:ring-2 focus:ring-primary text-sm transition-all" placeholder="Search clinics, domains, or plans..." type="text"/>
</div>
</div>
<div class="flex items-center gap-4">
<button class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full relative">
<span class="material-symbols-outlined">notifications</span>
<span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-card-dark"></span>
</button>
<div class="h-8 w-[1px] bg-slate-200 dark:bg-border-dark mx-2"></div>
<div class="flex items-center gap-3 cursor-pointer">
<div class="text-right">
<p class="text-sm font-bold leading-none">Super Admin</p>
<p class="text-xs text-slate-500">admin@mydental.ph</p>
</div>
<div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden border border-slate-300 dark:border-border-dark">
<img alt="Admin Profile" data-alt="Professional profile photo of a male admin user" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBQ1xGiIQjttpBhPJ3FMp-yZ0YlhyXBhOqXNJ0iVsvXPZup64tnzZb57H-acwuyrU7ycnZ6_h9KaMrVw6AW0QwLNgb6yM2apYzJ9KLOie6HAbsVrt27Zp2XAGQyYRFI-jhvGABYVYClS2K4_ErRnSP7I0Y_kuviSnNcDRGlB8Z6c9w1hdw4O3WL25NarmIrNStFyGjt1Mcn_7sniK64W99GvMjDm05LchmZjz6Bg3xhvanlikq-QFmMFLrpcpP3AjsQfllwuGiER9k"/>
</div>
</div>
</div>
</header>
<!-- Content Area -->
<div class="p-8 space-y-8">
<!-- Page Title -->
<div>
<h2 class="text-2xl font-bold">Dashboard Overview</h2>
<p class="text-slate-500 dark:text-slate-400">Welcome back. Here's what's happening today.</p>
</div>
<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white dark:bg-card-dark p-6 rounded-2xl border border-slate-200 dark:border-border-dark shadow-sm">
<div class="flex justify-between items-start mb-4">
<div class="p-2 bg-blue-50 dark:bg-blue-900/20 text-primary rounded-lg">
<span class="material-symbols-outlined">language</span>
</div>
</div>
<p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Total Websites</p>
<h3 class="text-3xl font-bold mt-1"><?php echo (int) $total_websites; ?></h3>
</div>
<div class="bg-white dark:bg-card-dark p-6 rounded-2xl border border-slate-200 dark:border-border-dark shadow-sm">
<div class="flex justify-between items-start mb-4">
<div class="p-2 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500 rounded-lg">
<span class="material-symbols-outlined">task_alt</span>
</div>
</div>
<p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Active Subscriptions</p>
<h3 class="text-3xl font-bold mt-1"><?php echo (int) $active_subscriptions; ?></h3>
</div>
<div class="bg-white dark:bg-card-dark p-6 rounded-2xl border border-slate-200 dark:border-border-dark shadow-sm">
<div class="flex justify-between items-start mb-4">
<div class="p-2 bg-amber-50 dark:bg-amber-900/20 text-amber-500 rounded-lg">
<span class="material-symbols-outlined">payments</span>
</div>
</div>
<p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Total Revenue</p>
<h3 class="text-3xl font-bold mt-1">₱<?php echo number_format($total_revenue, 0); ?></h3>
</div>
</div>
<!-- Clinics Registry -->
<div class="bg-white dark:bg-card-dark rounded-2xl border border-slate-200 dark:border-border-dark shadow-sm overflow-hidden">
<div class="p-6 border-b border-slate-200 dark:border-border-dark flex items-center justify-between">
<div>
<h3 class="text-lg font-bold">Clinics Registry</h3>
<p class="text-sm text-slate-500 dark:text-slate-400">Manage all registered dental clinics on the platform</p>
</div>
<div class="flex gap-2">
<button class="flex items-center gap-2 px-4 py-2 border border-slate-200 dark:border-border-dark rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-lg">filter_list</span>
                                Filter
                            </button>
<button class="flex items-center gap-2 px-4 py-2 border border-slate-200 dark:border-border-dark rounded-xl text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-lg">download</span>
                                Export
                            </button>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400 text-xs uppercase tracking-wider font-bold">
<th class="px-6 py-4">Clinic Name</th>
<th class="px-6 py-4">Domain</th>
<th class="px-6 py-4">Operator</th>
<th class="px-6 py-4">Plan</th>
<th class="px-6 py-4">Status</th>
<th class="px-6 py-4">Date Joined</th>
<th class="px-6 py-4 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100 dark:divide-border-dark">
<?php if (!empty($clinics)): ?>
<?php foreach ($clinics as $clinic): ?>
<?php
    $clinicName = $clinic['clinic_name'] ?? '—';
    $slug = $clinic['clinic_slug'] ?? '';
    $domain = $slug !== '' ? 'mydental.ct.ws/' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') : '—';
    $operator = $clinic['operator_name'] ?? '—';
    $plan = $clinic['plan_name'] ?? '—';
    $statusRaw = $clinic['subscription_status'] ?? 'inactive';
    $statusLabel = ucfirst($statusRaw);
    $statusColor = $statusRaw === 'active' ? 'text-emerald-500' : ($statusRaw === 'suspended' ? 'text-amber-500' : 'text-slate-400');
    $statusDot = $statusRaw === 'active' ? 'bg-emerald-500' : ($statusRaw === 'suspended' ? 'bg-amber-500' : 'bg-slate-400');
    $createdAt = $clinic['created_at'] ? date('M d, Y', strtotime($clinic['created_at'])) : '—';
    $initial = mb_strtoupper(mb_substr($clinicName, 0, 1, 'UTF-8'), 'UTF-8');
?>
<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
<td class="px-6 py-5">
<div class="flex items-center gap-3">
<div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/40 text-primary flex items-center justify-center font-bold text-xs">
<?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?>
</div>
<span class="font-semibold">
<?php echo htmlspecialchars($clinicName, ENT_QUOTES, 'UTF-8'); ?>
</span>
</div>
</td>
<td class="px-6 py-5 text-sm text-slate-500 dark:text-slate-400">
<?php echo htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>
</td>
<td class="px-6 py-5 text-sm text-slate-500 dark:text-slate-400">
<?php echo htmlspecialchars($operator, ENT_QUOTES, 'UTF-8'); ?>
</td>
<td class="px-6 py-5">
<span class="px-3 py-1 bg-slate-100 dark:bg-slate-800 rounded-full text-xs font-bold">
<?php echo htmlspecialchars($plan, ENT_QUOTES, 'UTF-8'); ?>
</span>
</td>
<td class="px-6 py-5">
<span class="flex items-center gap-1.5 <?php echo $statusColor; ?> text-xs font-bold uppercase">
<span class="w-1.5 h-1.5 rounded-full <?php echo $statusDot; ?>"></span>
<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
</span>
</td>
<td class="px-6 py-5 text-sm text-slate-500">
<?php echo htmlspecialchars($createdAt, ENT_QUOTES, 'UTF-8'); ?>
</td>
<td class="px-6 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-1.5 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-primary rounded-lg transition-colors" title="View">
<span class="material-symbols-outlined">visibility</span>
</button>
<button class="p-1.5 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-500 rounded-lg transition-colors" title="Deactivate">
<span class="material-symbols-outlined">block</span>
</button>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="7" class="px-6 py-6 text-center text-sm text-slate-500">
No clinics found.
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="p-6 border-t border-slate-200 dark:border-border-dark flex items-center justify-between">
<?php
$clinic_count = count($clinics);
$showing_to = $clinic_count;
?>
<p class="text-sm text-slate-500">Showing <?php echo $clinic_count > 0 ? '1 to ' . $showing_to . ' of' : '0 of'; ?> <?php echo $total_websites; ?> clinics</p>
<div class="flex gap-2">
<button class="px-3 py-1.5 border border-slate-200 dark:border-border-dark rounded-lg text-sm disabled:opacity-50" disabled>Previous</button>
<button class="px-3 py-1.5 border border-slate-200 dark:border-border-dark rounded-lg text-sm bg-primary text-white font-semibold">1</button>
<button class="px-3 py-1.5 border border-slate-200 dark:border-border-dark rounded-lg text-sm hover:bg-slate-50 dark:hover:bg-slate-800" disabled>Next</button>
</div>
</div>
</div>
</div>
</main>
</div>
</body></html>