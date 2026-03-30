<?php
/**
 * Staff appointments — requires staff-class login for the same tenant as clinic_slug.
 */
$pageTitle = 'Appointments';
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_GET['clinic_slug']) && !empty($_SESSION['public_tenant_slug'])) {
    $_GET['clinic_slug'] = $_SESSION['public_tenant_slug'];
}
if (empty($_GET['clinic_slug'])) {
    $reqUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $reqPath = $reqUri !== '' ? parse_url($reqUri, PHP_URL_PATH) : '';
    $scriptBase = isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : 'StaffAppointments.php';
    if (is_string($reqPath) && $reqPath !== '') {
        $segments = array_values(array_filter(explode('/', trim($reqPath, '/')), 'strlen'));
        $scriptIdx = array_search($scriptBase, $segments, true);
        if ($scriptIdx !== false && $scriptIdx > 0) {
            $slugFromPath = strtolower(trim((string) $segments[$scriptIdx - 1]));
            if ($slugFromPath !== '' && preg_match('/^[a-z0-9\-]+$/', $slugFromPath)) {
                $_GET['clinic_slug'] = $slugFromPath;
            }
        }
    }
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
$staffDashUrl = htmlspecialchars(BASE_URL . 'StaffDashboard.php?clinic_slug=' . rawurlencode($currentTenantSlug), ENT_QUOTES, 'UTF-8');
$staffApptsUrl = htmlspecialchars(BASE_URL . 'StaffAppointments.php?clinic_slug=' . rawurlencode($currentTenantSlug), ENT_QUOTES, 'UTF-8');
$staff_portal_sidebar_mode = 'appointments';
$staff_nav_active = 'appointments';
unset($__nm, $__parts, $__p);
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Appointments | Clinical Precision</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
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
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Content Area -->
<main class="flex-1 flex flex-col min-w-0 ml-64">
<!-- TopNavBar Component -->
<header class="flex justify-between items-center w-full px-10 sticky top-0 z-40 bg-white/80 backdrop-blur-md border-b border-slate-200 h-20">
<div class="flex items-center flex-1 max-w-xl">
<div class="relative w-full group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">search</span>
<input class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border-none rounded-full text-sm focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="Search appointments..." type="text"/>
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
<button class="bg-primary text-white px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-primary/30 flex items-center gap-2 hover:scale-[1.02] active:scale-95 transition-all">
<span class="material-symbols-outlined text-sm">add</span>
                    Add Appointment
                </button>
</div>
</header>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Welcome Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> APPOINTMENT MANAGEMENT
                </div>
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Manage <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Appointments</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Track and manage all patient visits and clinical staff scheduling for today.
                    </p>
</div>
</section>
<!-- Filters & View Switcher -->
<section class="flex flex-col md:flex-row md:items-center justify-between gap-4">
<div class="flex flex-wrap items-center gap-3">
<button class="flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
<span class="material-symbols-outlined text-primary text-lg">calendar_month</span>
                        Today, Oct 24
                        <span class="material-symbols-outlined text-xs">expand_more</span>
</button>
<button class="flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
<span class="material-symbols-outlined text-primary text-lg">filter_list</span>
                        All Statuses
                        <span class="material-symbols-outlined text-xs">expand_more</span>
</button>
<button class="flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
<span class="material-symbols-outlined text-primary text-lg">person</span>
                        All Staff
                        <span class="material-symbols-outlined text-xs">expand_more</span>
</button>
</div>
<div class="flex bg-slate-100 p-1 rounded-xl">
<button class="px-5 py-2 text-[10px] font-black uppercase tracking-widest bg-white text-primary rounded-lg shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-sm">format_list_bulleted</span>
                        Table View
                    </button>
<button class="px-5 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-primary transition-colors flex items-center gap-2">
<span class="material-symbols-outlined text-sm">calendar_view_month</span>
                        Calendar View
                    </button>
</div>
</section>
<!-- Appointments Table -->
<div class="elevated-card rounded-3xl overflow-hidden border-2 border-primary/20 w-full">
<div class="px-10 py-6 border-b border-slate-100 flex items-center justify-between bg-white">
<h5 class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant/60">Live Appointment Manifest</h5>
<p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total 24 Entries</p>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="bg-slate-50/50">
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Patient Name</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Date &amp; Time</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Service</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Assigned Staff</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60">Status</th>
<th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-on-surface-variant/60 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-primary/20">
<!-- Row 1: Confirmed -->
<tr class="group hover:bg-slate-50/50 transition-colors">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">AM</div>
<div>
<div class="text-lg font-bold text-on-background">Alice Morgenstern</div>
<div class="text-sm text-slate-500 font-medium">#PT-8821</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<div class="text-lg font-bold text-on-background">09:30 AM</div>
<div class="text-sm text-primary font-medium">Oct 24, 2023</div>
</td>
<td class="px-10 py-8">
<span class="bg-slate-100 text-slate-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Root Canal Therapy</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-3">
<img class="w-8 h-8 rounded-full object-cover border border-slate-200" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC3xcEPvMDx3hKWppixKOrr9H_ESv5Ox3wKTgMMWAl9jvv5sJ-sM_otbjdnQGCSpaD0Y1G8r5ACrVRTSTZItSHBgROwPNw2Wt_87L8rHTNzXjrB3A4YHtrb_JUtRrQUGCNwitn2a8T5iYFdX8X_uwAZOgUNPWzrTVhEbWb7iISWohOrlXGJTn2aw2wXAlSd4omAEGJED3VDPNfEc-o1RrMIiNUwMDdc5OFKbyVZKFG4Lh0wJdrwxxzDw0KSBl5pmyebE1i1QfQ1C1Q"/>
<span class="text-sm font-bold text-on-background">Dr. Robert Chen</span>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-blue-100 text-blue-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Confirmed</span>
</td>
<td class="px-10 py-8 text-right">
<button class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-primary hover:border-primary transition-all shadow-sm">
<span class="material-symbols-outlined text-xl">more_horiz</span>
</button>
</td>
</tr>
<!-- Row 2: Pending -->
<tr class="group hover:bg-slate-50/50 transition-colors">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-700 font-bold text-xs">JH</div>
<div>
<div class="text-lg font-bold text-on-background">James Henderson</div>
<div class="text-sm text-slate-500 font-medium">#PT-4590</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<div class="text-lg font-bold text-on-background">11:00 AM</div>
<div class="text-sm text-primary font-medium">Oct 24, 2023</div>
</td>
<td class="px-10 py-8">
<span class="bg-slate-100 text-slate-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Routine Checkup</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-3">
<img class="w-8 h-8 rounded-full object-cover border border-slate-200" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBfhQWKAdBy45m6Kfy6gisRKsXcfm64-3XzLPPgcfJor8a4_WryOgQp1LFH1q40sTaPWRKrqrWKPhcgIZrFBGSL2LjIQcM81GKW_kvv9SzwJLmwZOo2Q-v5eMGgCCzgVyn4C4sfMsQ39NxrDE_l2mYzD-YN9lBnMtiwdB0-_AhJGgOPCUEdpW8n3-W4OjYE5zDb4WstzBmyHMnM6z5H2FtfQW3vL_R_Muak2rOVsJdIZ2KO99JIbIrLYCsMY6qW4ER0Yqbt8MEi6YI"/>
<span class="text-sm font-bold text-on-background">Dr. Sarah Jenkins</span>
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
<!-- Row 3: Completed -->
<tr class="group hover:bg-slate-50/50 transition-colors">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold text-xs">LB</div>
<div>
<div class="text-lg font-bold text-on-background">Linda Belcher</div>
<div class="text-sm text-slate-500 font-medium">#PT-1209</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<div class="text-lg font-bold text-on-background">08:00 AM</div>
<div class="text-sm text-primary font-medium">Oct 24, 2023</div>
</td>
<td class="px-10 py-8">
<span class="bg-slate-100 text-slate-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Teeth Whitening</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-3">
<img class="w-8 h-8 rounded-full object-cover border border-slate-200" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD7K5wkGGvbQlR3D68i4kaLEW8dCAcoQsteLUon03eIgvCsFZaGEaekjBZP3NW83QCLivercvQ6n_A2f8yst2itmHiGXnoYkSw3Dx4N-XpZM1JtZY_7ZH7vrclKCGeHCHMMnmvNZglgrXHjkLdOCAjiz32Exjj_rv5TDEK7LheBzS0n3c3F1k3M4qvZ73n690xm6nVPnySFHzexspEnYoLGgbo-iTxHh6gixLKiEUzkI03I5R68oCRGuQFdAcaB3GfXNxAgmNWvTfE"/>
<span class="text-sm font-bold text-on-background">Dr. Michael Vance</span>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-green-100 text-green-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Completed</span>
</td>
<td class="px-10 py-8 text-right">
<button class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white border border-slate-200 text-primary hover:border-primary transition-all shadow-sm">
<span class="material-symbols-outlined text-xl">more_horiz</span>
</button>
</td>
</tr>
<!-- Row 4: Cancelled -->
<tr class="group hover:bg-slate-50/50 transition-colors opacity-75">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-700 font-bold text-xs">TP</div>
<div>
<div class="text-lg font-bold text-on-background">Tom Phillips</div>
<div class="text-sm text-slate-500 font-medium">#PT-7734</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<div class="text-lg font-bold text-on-background">01:30 PM</div>
<div class="text-sm text-primary font-medium">Oct 24, 2023</div>
</td>
<td class="px-10 py-8">
<span class="bg-slate-100 text-slate-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Braces Fitting</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-3">
<img class="w-8 h-8 rounded-full object-cover border border-slate-200" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC3xcEPvMDx3hKWppixKOrr9H_ESv5Ox3wKTgMMWAl9jvv5sJ-sM_otbjdnQGCSpaD0Y1G8r5ACrVRTSTZItSHBgROwPNw2Wt_87L8rHTNzXjrB3A4YHtrb_JUtRrQUGCNwitn2a8T5iYFdX8X_uwAZOgUNPWzrTVhEbWb7iISWohOrlXGJTn2aw2wXAlSd4omAEGJED3VDPNfEc-o1RrMIiNUwMDdc5OFKbyVZKFG4Lh0wJdrwxxzDw0KSBl5pmyebE1i1QfQ1C1Q"/>
<span class="text-sm font-bold text-on-background">Dr. Robert Chen</span>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-rose-100 text-rose-700 text-[10px] font-black px-4 py-1.5 rounded-lg uppercase tracking-widest">Cancelled</span>
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
<p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Showing 4 of 24 appointments</p>
<div class="flex gap-2">
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400">
<span class="material-symbols-outlined text-xl">chevron_left</span>
</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-primary text-white text-[10px] font-black shadow-lg shadow-primary/30">1</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-primary text-[10px] font-black hover:border-primary transition-all">2</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-primary text-[10px] font-black hover:border-primary transition-all">3</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-primary hover:border-primary transition-all shadow-sm">
<span class="material-symbols-outlined text-xl">chevron_right</span>
</button>
</div>
</div>
</div>
</div>
</main>
</body></html>