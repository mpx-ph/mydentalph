<?php
/**
 * Staff dashboard — requires staff-class login for the same tenant as clinic_slug.
 */
$pageTitle = 'Staff Dashboard';
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_GET['clinic_slug']) && !empty($_SESSION['public_tenant_slug'])) {
    $_GET['clinic_slug'] = $_SESSION['public_tenant_slug'];
}
$clinic_slug_boot = isset($_GET['clinic_slug']) ? trim((string) $_GET['clinic_slug']) : '';
if ($clinic_slug_boot !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($clinic_slug_boot))) {
    $_GET['clinic_slug'] = strtolower($clinic_slug_boot);
    require_once __DIR__ . '/tenant_bootstrap.php';
} else {
    header('Location: ' . BASE_URL . 'Login.php');
    exit;
}
require_once __DIR__ . '/includes/auth.php';

$staffTypes = ['manager', 'doctor', 'staff', 'admin'];
if (!isLoggedIn($staffTypes)) {
    header('Location: ' . BASE_URL . 'Login.php?clinic_slug=' . rawurlencode($currentTenantSlug));
    exit;
}
$sessionTenant = getClinicTenantId();
if ($sessionTenant === null || (string) $sessionTenant !== (string) $currentTenantId) {
    header('Location: ' . BASE_URL . 'Login.php?clinic_slug=' . rawurlencode($currentTenantSlug));
    exit;
}

$staffDisplayName = isset($_SESSION['user_name']) ? htmlspecialchars((string) $_SESSION['user_name'], ENT_QUOTES, 'UTF-8') : 'Staff';
$staffRoleLabel = isset($_SESSION['user_role']) ? htmlspecialchars(ucwords(str_replace('_', ' ', (string) $_SESSION['user_role'])), ENT_QUOTES, 'UTF-8') : 'Staff';
$staffInitials = 'S';
$__nm = trim((string) ($_SESSION['user_name'] ?? ''));
if ($__nm !== '') {
    $__parts = preg_split('/\s+/', $__nm, -1, PREG_SPLIT_NO_EMPTY);
    $staffInitials = '';
    foreach (array_slice($__parts, 0, 2) as $__p) {
        $staffInitials .= strtoupper(substr($__p, 0, 1));
    }
    if ($staffInitials === '') {
        $staffInitials = 'S';
    }
}
$staffInitialsEsc = htmlspecialchars($staffInitials, ENT_QUOTES, 'UTF-8');
$staffDisplayEmail = isset($_SESSION['user_email']) ? trim((string) $_SESSION['user_email']) : '';
$staffDisplayEmailEsc = $staffDisplayEmail !== '' ? htmlspecialchars($staffDisplayEmail, ENT_QUOTES, 'UTF-8') : '';
$staffLogoutUrl = htmlspecialchars(BASE_URL . 'api/logout.php', ENT_QUOTES, 'UTF-8');
unset($__nm, $__parts, $__p);
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars(isset($currentTenantData['clinic_name']) ? (string) $currentTenantData['clinic_name'] : 'Clinic', ENT_QUOTES, 'UTF-8'); ?></title>
<!-- Google Fonts: Manrope -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<!-- Tailwind CSS -->
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
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<!-- SideNavBar Component -->
<aside class="fixed left-0 top-0 h-full w-64 z-40 bg-white flex flex-col py-8 border-r border-slate-200/60">
<div class="px-7 mb-10">
<h1 class="text-xl font-extrabold text-slate-900 tracking-tight font-headline flex items-center gap-2">
<span class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shadow-lg shadow-primary/30">
<span class="material-symbols-outlined text-white text-lg" style="font-variation-settings: 'FILL' 1;">dentistry</span>
</span>
            <?php echo htmlspecialchars(isset($currentTenantData['clinic_name']) ? (string) $currentTenantData['clinic_name'] : 'Clinic', ENT_QUOTES, 'UTF-8'); ?>
        </h1>
<p class="text-primary font-bold text-[10px] tracking-[0.2em] uppercase mt-2 opacity-80">Staff Portal</p>
</div>
<nav class="flex-1 min-h-0 space-y-1 overflow-y-auto no-scrollbar">
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="#">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">dashboard</span>
<span class="font-headline text-sm font-bold tracking-tight">Dashboard</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">calendar_month</span>
<span class="font-headline text-sm font-medium tracking-tight">Appointments</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">person_add</span>
<span class="font-headline text-sm font-medium tracking-tight">Registration</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">group</span>
<span class="font-headline text-sm font-medium tracking-tight">Patients</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">payments</span>
<span class="font-headline text-sm font-medium tracking-tight">Payments</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">chat</span>
<span class="font-headline text-sm font-medium tracking-tight">Messages</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">settings</span>
<span class="font-headline text-sm font-medium tracking-tight">Settings</span>
</a>
</div>
</nav>
<div class="px-4 pt-5 mt-auto border-t border-slate-200/80 space-y-3 shrink-0">
<button type="button" class="w-full py-3.5 bg-primary text-white font-bold text-xs uppercase tracking-widest rounded-xl shadow-lg shadow-primary/30 transition-all active:scale-95">
            New Appointment
        </button>
<div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-3 flex items-center gap-3">
<div class="h-11 w-11 rounded-xl bg-primary/15 flex items-center justify-center text-primary font-bold text-sm shrink-0" aria-hidden="true">
<span class="select-none"><?php echo $staffInitialsEsc; ?></span>
</div>
<div class="min-w-0 flex-1">
<p class="text-sm font-bold text-slate-900 font-headline truncate leading-tight"><?php echo $staffDisplayName; ?></p>
<?php if ($staffDisplayEmailEsc !== '') { ?>
<p class="text-[11px] text-slate-500 truncate mt-0.5"><?php echo $staffDisplayEmailEsc; ?></p>
<?php } ?>
<p class="text-[10px] font-bold text-primary uppercase tracking-wider mt-1"><?php echo $staffRoleLabel !== '' ? $staffRoleLabel : 'Staff'; ?></p>
</div>
</div>
<a href="<?php echo $staffLogoutUrl; ?>" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl border border-slate-200 bg-white text-slate-700 font-bold text-xs uppercase tracking-widest hover:bg-slate-50 hover:border-slate-300 transition-all no-underline text-inherit">
<span class="material-symbols-outlined text-[20px] text-slate-500">logout</span>
            Log out
        </a>
</div>
</aside>
<!-- Main Content Area -->
<main class="flex-1 flex flex-col min-w-0 ml-64">
<!-- TopNavBar Component -->
<header class="flex justify-between items-center w-full px-10 sticky top-0 z-40 bg-white border-b border-slate-200 h-20">
<div class="flex items-center flex-1 max-w-xl">
<div class="relative w-full group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">search</span>
<input class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border-none rounded-full text-sm focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="Search patients, records, or staff..." type="text"/>
</div>
</div>
<div class="flex items-center gap-6 ml-8">
<div class="flex items-center gap-6 text-slate-400">
<button class="material-symbols-outlined hover:text-primary transition-colors relative">
                    notifications
                    <span class="absolute top-0 right-0 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span>
</button>
<button class="material-symbols-outlined hover:text-primary transition-colors">help_outline</button>
</div>
<div class="h-8 w-px bg-slate-200 mx-2"></div>
<div class="flex items-center gap-3">
<div class="text-right hidden sm:block">
<p class="text-sm font-bold text-slate-900 leading-none"><?php echo $staffDisplayName; ?></p>
<p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mt-1"><?php echo $staffRoleLabel !== '' ? $staffRoleLabel : 'Staff'; ?></p>
</div>
<div class="h-10 w-10 rounded-full overflow-hidden border-2 border-primary/20 p-0.5 bg-primary/10 flex items-center justify-center text-primary font-bold text-sm">
<span class="select-none"><?php echo $staffInitialsEsc; ?></span>
</div>
</div>
</div>
</header>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Welcome Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> STAFF DASHBOARD
    </div>
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
            Staff <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Dashboard</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
            Monitoring clinic performance and patient flow for today.
        </p>
</div>
</section>
<!-- Summary Cards -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<!-- Today's Appointments -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">calendar_today</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">+12%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">12</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Today's Appointments</p>
</div>
</div>
<!-- Pending -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600 transition-colors group-hover:bg-amber-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">pending_actions</span>
</div>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">3</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Pending Check-ins</p>
</div>
</div>
<!-- Completed -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">check_circle</span>
</div>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">9</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Completed Today</p>
</div>
</div>
<!-- Total Patients -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center text-slate-600 transition-colors group-hover:bg-slate-900 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">group</span>
</div>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">15</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Daily Patient Flow</p>
</div>
</div>
</section>
<!-- Main Section: Today's Schedule -->
<section class="space-y-6 pb-16">
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
<div class="flex items-center gap-4">
<h3 class="text-2xl font-bold font-headline text-on-background">Today's Schedule</h3>
<span class="px-4 py-1.5 bg-primary/10 text-primary text-[10px] font-black uppercase tracking-widest rounded-full">October 24, 2023</span>
</div>
<div class="flex items-center gap-3">
<div class="flex bg-slate-100 p-1 rounded-xl">
<button class="px-5 py-2 text-xs font-black uppercase tracking-widest bg-white text-primary rounded-lg shadow-sm">Today</button>
<button class="px-5 py-2 text-xs font-black uppercase tracking-widest text-slate-500 hover:text-primary transition-colors">Upcoming</button>
</div>
<button class="flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700 text-xs font-black uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
<span class="material-symbols-outlined text-lg">filter_list</span>
                        Filter
                    </button>
</div>
</div>
<!-- Schedule Table: Styled like Subscription Billing Table -->
<div class="elevated-card rounded-3xl overflow-hidden border-2 border-primary/20">
<div class="px-10 py-6 border-b border-slate-100 flex items-center justify-between">
<h5 class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant/60">Live Appointment Manifest</h5>
<p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total 12 Entries</p>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="bg-slate-50/50">
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Patient &amp; ID</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Appointment</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Clinical Service</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Assigned Staff</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Status</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-primary/20">
<!-- Row 1 -->
<tr class="group hover:bg-slate-50/50 transition-colors">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">AM</div>
<div>
<div class="text-lg font-bold text-on-background">Alexander Miller</div>
<div class="text-sm text-slate-500 font-medium">#PT-8821</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<div class="text-lg font-bold text-on-background">09:00 AM</div>
<div class="text-sm text-slate-500 font-medium flex items-center gap-1">
<span class="material-symbols-outlined text-sm">schedule</span> In 15 min
                                    </div>
</td>
<td class="px-10 py-8">
<span class="bg-slate-100 text-slate-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Scaling &amp; Polishing</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-3">
<img class="w-8 h-8 rounded-full object-cover border border-slate-200" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC-iEstP6S-it5gfwZADh9zDTwAmnB6ymId9llNn_dmSMcXaPkgqpH2tHwVBCI89SOu6tent3PmmPamfD1qWGTBelwA5Wk6m-krKD4VyE9tUixOt0n-ZpNFz_RqxtSE9xyQHtZK84J9Tj_Qy_JT3z6wKIsJpkJbdiZ5_CTzENVFBRlwgxbXE3ZrhDQA285QtWq78znd6nhjHbU34FyMTI6a1zalTC-jg9ogQuq_NMHPAefKxtlmSINK232IZD2c6entRD1A5nGnRMw"/>
<span class="text-sm font-bold text-on-background">Dr. Robert Chen</span>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-green-100 text-green-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Confirmed</span>
</td>
<td class="px-10 py-8 text-right">
<button class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-primary hover:border-primary transition-all shadow-sm">
<span class="material-symbols-outlined text-xl">more_horiz</span>
</button>
</td>
</tr>
<!-- Row 2 -->
<tr class="group hover:bg-slate-50/50 transition-colors">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 font-bold text-xs">EJ</div>
<div>
<div class="text-lg font-bold text-on-background">Elena Jakes</div>
<div class="text-sm text-slate-500 font-medium">#PT-4412</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<div class="text-lg font-bold text-on-background">10:30 AM</div>
<div class="text-sm text-slate-500 font-medium">Duration: 45 min</div>
</td>
<td class="px-10 py-8">
<span class="bg-slate-100 text-slate-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Orthodontic</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-3">
<img class="w-8 h-8 rounded-full object-cover border border-slate-200" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAgzKjrNLyWIxE9sKX2LhYg8xVK6g91YIPm0z87efZL59mI42c4vrCMw3JUMWLWdlsPisHCNNZBue3Ie0m5cJ2Wpv7hIS3vmjXNRaEbMx_ZiSa8Ow0uyBzSzBxpQeB7lq5km4_WebNYSBOGa-SW5W6NPAxRm0biyjbSF23Vb5a1TPh6ANFvHc4uwQdagvPsN-to4quPOEi9_3eeX52FBARbFKbZ8PwXveUMqCxut02Qm5fj4wk-MSvYanHt-t64z8RWfk0hDHmbLqU"/>
<span class="text-sm font-bold text-on-background">Dr. Sarah Aetheris</span>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-amber-100 text-amber-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Pending</span>
</td>
<td class="px-10 py-8 text-right">
<button class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-primary hover:border-primary transition-all shadow-sm">
<span class="material-symbols-outlined text-xl">more_horiz</span>
</button>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Pagination / Table Footer -->
<div class="bg-slate-50/50 px-10 py-5 border-t border-slate-100 flex justify-between items-center">
<p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Showing 2 of 12 appointments</p>
<div class="flex gap-2">
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 disabled:opacity-30 cursor-not-allowed">
<span class="material-symbols-outlined text-xl">chevron_left</span>
</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-primary hover:border-primary transition-all shadow-sm">
<span class="material-symbols-outlined text-xl">chevron_right</span>
</button>
</div>
</div>
</div>
</section>
</div>
<!-- Footer Status Bar -->
</main>
</body></html>