<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';

$clinic_settings_saved = false;
$clinic_settings_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['form'] ?? '') === 'clinic_details') {
    try {
        if ($is_owner) {
            $cn = trim((string) ($_POST['clinic_name'] ?? ''));
            $ce = trim((string) ($_POST['clinic_email'] ?? ''));
            $cp = trim((string) ($_POST['clinic_phone'] ?? ''));
            $ca = trim((string) ($_POST['clinic_address'] ?? ''));
            if ($cn !== '') {
                $stmt = $pdo->prepare('UPDATE tbl_tenants SET clinic_name = ?, contact_email = ?, contact_phone = ?, clinic_address = ? WHERE tenant_id = ?');
                $stmt->execute([$cn, $ce, $cp, $ca, (string) $tenant_id]);
            }
        }
        $clinic_settings_saved = true;
    } catch (Throwable $e) {
        $clinic_settings_error = 'Could not save settings. Please try again.';
    }
}

$tenant = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.tenant_id, t.clinic_name, t.clinic_slug, t.contact_email, t.contact_phone, t.clinic_address, t.subscription_status,
               u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
        FROM tbl_tenants t
        LEFT JOIN tbl_users u ON t.owner_user_id = u.user_id
        WHERE t.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([(string) $tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $tenant = [];
}

if ($clinic_settings_saved) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.tenant_id, t.clinic_name, t.clinic_slug, t.contact_email, t.contact_phone, t.clinic_address, t.subscription_status,
                   u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
            FROM tbl_tenants t
            LEFT JOIN tbl_users u ON t.owner_user_id = u.user_id
            WHERE t.tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([(string) $tenant_id]);
        $refetched = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($refetched) && $refetched !== []) {
            $tenant = $refetched;
        }
    } catch (Throwable $e) {
    }
}

if ($tenant === []) {
    $tenant = [
        'tenant_id' => (string) $tenant_id,
        'clinic_name' => '',
        'clinic_slug' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'clinic_address' => '',
        'subscription_status' => '',
    ];
}

require_once __DIR__ . '/provider_tenant_plan_and_site_context.inc.php';

$provider_nav_active = 'settings';
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Settings</title>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
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
                        "surface-variant": "#f7f9ff",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#c0c7d4",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#404752",
                        "background": "#f1f5f9",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#8e4a00",
                        "tertiary-container": "#ffdcc3",
                        "error": "#ba1a1a",
                        "error-container": "#fff1f2"
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
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .section-card {
            border: 1px solid rgba(0, 0, 0, 0.04);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease, border-color 0.25s ease;
        }
        .section-card:hover {
            box-shadow: 0 24px 40px -12px rgba(15, 23, 42, 0.12), 0 12px 16px -8px rgba(43, 139, 235, 0.06);
            transform: translateY(-5px);
            border-color: rgba(43, 139, 235, 0.12);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
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
        body { font-family: 'Manrope', sans-serif; }
        
        /* Consistent border styling for inputs, textareas, and selects */
        .form-input-styled {
            border: 1px solid #d1d5db !important; /* light gray/slate-300 */
        }
        .form-input-styled:focus {
            border-color: #2b8beb !important; /* primary color on focus */
        }
        .dash-infra-panel {
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(241, 245, 249, 0.5) 100%);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.9),
                0 20px 50px -15px rgba(15, 23, 42, 0.08);
        }
        .dash-infra-row {
            transition: background-color 0.2s ease;
        }
        .dash-infra-row:hover {
            background: rgba(43, 139, 235, 0.04);
        }
    </style>
</head>
<body class="font-body text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<!-- Main Content Area -->
<main class="flex-1 flex flex-col min-w-0 ml-64 provider-page-enter">
<!-- TopNavBar -->
<header class="flex justify-between items-center w-full px-10 sticky top-0 z-30 bg-white/80 backdrop-blur-xl h-20 border-b border-slate-200/80 shadow-sm shadow-slate-200/40">
<div class="flex items-center gap-8">
<h2 class="font-headline text-lg font-extrabold text-slate-900">MyDental Provider</h2>
<div class="h-4 w-px bg-outline-variant/30"></div>
<span class="font-headline text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">System Settings</span>
</div>
<div class="flex items-center gap-6">
<div class="relative hidden md:block">
<span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">search</span>
<input class="bg-primary/5 border border-slate-300 rounded-full py-2.5 pl-11 pr-4 text-xs w-64 focus:ring-2 focus:ring-primary/20 placeholder:text-slate-400" placeholder="Search settings..." type="text"/>
</div>
<div class="flex items-center gap-6 text-on-surface-variant/60">
<button class="material-symbols-outlined hover:text-primary transition-colors">notifications</button>
<button class="material-symbols-outlined hover:text-primary transition-colors">help_outline</button>
</div>
<div class="h-10 w-10 rounded-full overflow-hidden border-2 border-primary/20 p-0.5 shadow-sm">
<img alt="Admin Avatar" class="w-full h-full rounded-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA1L6CErhIlC70phuJM6zTquKiKBYvLOyfRk2af42qldmhP1fqWU4Fqg0Zd_-dtByCaAGXwLrtBd0LK_lzNeaasWGDSQQYlLtIATaMVsUquiFX8mgDW72JytVn4eNqmowEP1T3tkPv52-wTkpFw5QUBo33YdTOn5dnfjnGodxPC3iOK3_a9s8ema86EKxRcm3m1Jbr-OndSxEthemuUgEhNyTV9ewF6ICnO4D9XWu3U9XotWeAK8G_LY7wbkKqZl4x5h---4iur2X8"/>
</div>
</div>
</header>
<!-- Page Content -->
<div class="p-10 max-w-7xl mx-auto w-full space-y-12">
<!-- Header -->
<section class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> Configuration</div>
<div>
<h2 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">System <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Configuration</span></h2>
<p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">Manage your clinic's identity, security protocols, and personal account preferences from this centralized dashboard.</p>
</div>
</section>
<!-- Grid Layout -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
<!-- Left Column -->
<div class="lg:col-span-7 space-y-10">
<!-- Clinic Details -->
<div class="section-card bg-white rounded-[2.5rem] p-10 border-l-4 border-l-primary">
<div class="flex items-center gap-5 mb-10">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-2xl">apartment</span>
</div>
<div>
<h4 class="font-headline text-xl font-extrabold text-slate-900">Clinic Details</h4>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Public information for your dental practice</p>
</div>
</div>
<?php if ($clinic_settings_saved): ?>
<div class="mb-6 p-4 bg-emerald-50 border border-emerald-200/80 text-emerald-800 rounded-2xl text-sm font-medium">Clinic details saved successfully.</div>
<?php endif; ?>
<?php if ($clinic_settings_error !== ''): ?>
<div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm"><?php echo htmlspecialchars($clinic_settings_error); ?></div>
<?php endif; ?>
<?php if (!$is_owner): ?>
<div class="mb-6 p-4 bg-slate-50 border border-slate-200 text-on-surface-variant rounded-2xl text-sm font-medium">Only the clinic owner can edit these details. Contact your owner if something needs updating.</div>
<?php endif; ?>
<form class="space-y-8" method="post" action="" id="clinic-details-form" data-purpose="clinic-details-form">
<input type="hidden" name="form" value="clinic_details"/>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<div class="space-y-2 md:col-span-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="clinic_name">Clinic Name</label>
<input class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="text" id="clinic_name" name="clinic_name" placeholder="Clinic name" value="<?php echo htmlspecialchars((string) ($tenant['clinic_name'] ?? '')); ?>" <?php echo $is_owner ? '' : 'readonly disabled'; ?>/>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="clinic_email">Clinic Email</label>
<input class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all <?php echo $is_owner ? '' : 'opacity-70 cursor-not-allowed'; ?>" type="email" id="clinic_email" name="clinic_email" placeholder="Clinic email" value="<?php echo htmlspecialchars((string) ($tenant['contact_email'] ?? '')); ?>" <?php echo $is_owner ? '' : 'readonly disabled'; ?>/>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="clinic_phone">Phone Number</label>
<input class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all <?php echo $is_owner ? '' : 'opacity-70 cursor-not-allowed'; ?>" type="tel" id="clinic_phone" name="clinic_phone" placeholder="Clinic phone" value="<?php echo htmlspecialchars((string) ($tenant['contact_phone'] ?? '')); ?>" <?php echo $is_owner ? '' : 'readonly disabled'; ?>/>
</div>
<div class="space-y-2 md:col-span-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="clinic_address">Address</label>
<textarea class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all min-h-[5rem] <?php echo $is_owner ? '' : 'opacity-70 cursor-not-allowed'; ?>" id="clinic_address" name="clinic_address" placeholder="Address" rows="3" <?php echo $is_owner ? '' : 'readonly disabled'; ?>><?php echo htmlspecialchars((string) ($tenant['clinic_address'] ?? '')); ?></textarea>
</div>
</div>
<div class="pt-4 flex justify-end">
<button class="bg-primary text-white px-10 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:shadow-xl hover:shadow-primary/25 transition-all duration-300 active:scale-95 hover:scale-[1.02] disabled:opacity-50 disabled:pointer-events-none" type="submit" <?php echo $is_owner ? '' : 'disabled'; ?>>Save Changes</button>
</div>
</form>
</div>
<!-- Your Account -->
<div class="section-card bg-slate-50/80 rounded-[2.5rem] p-10 border border-slate-200/60">
<div class="flex items-center gap-5 mb-10">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-2xl">person</span>
</div>
<div>
<h4 class="font-headline text-xl font-extrabold text-slate-900">Your Account</h4>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Manage your administrator credentials</p>
</div>
</div>
<form class="space-y-8">
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1">Full Name</label>
<input class="w-full bg-white border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="text" value="Alexander Sterling"/>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1">Email</label>
<input class="w-full bg-white border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="email" value="alex.sterling@aetheris.com"/>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1">Contact Number</label>
<input class="w-full bg-white border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="tel" value="+1 (555) 987-6543"/>
</div>
</div>
<div class="pt-4 flex justify-end">
<button class="bg-white border-2 border-primary/20 text-primary px-10 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-primary/5 hover:border-primary/30 transition-all duration-300 active:scale-95 hover:scale-[1.02] shadow-sm" type="submit">Save Changes</button>
</div>
</form>
</div>
</div>
<!-- Right Column -->
<div class="lg:col-span-5 space-y-10">
<!-- Security -->
<div class="section-card bg-[#fcfdff] rounded-[2.5rem] p-10 border border-primary/5">
<div class="flex items-center gap-5 mb-10">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-2xl">lock_reset</span>
</div>
<div>
<h4 class="font-headline text-xl font-extrabold text-slate-900">Security</h4>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Keep your account protected</p>
</div>
</div>
<form class="space-y-8">
<div class="space-y-6">
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1">Current Password</label>
<input class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="••••••••" type="password"/>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1">New Password</label>
<input class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Min. 12 characters" type="password"/>
</div>
</div>
<button class="w-full bg-white border-2 border-primary/20 text-primary py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-primary/5 transition-all shadow-sm" type="submit">Update Password</button>
</form>
</div>
<!-- Preferences -->
<div class="section-card bg-indigo-50/20 rounded-[2.5rem] p-10 border border-indigo-100/50">
<div class="flex items-center gap-5 mb-10">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-2xl">tune</span>
</div>
<div>
<h4 class="font-headline text-xl font-extrabold text-slate-900">Preferences</h4>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">System localization and alerts</p>
</div>
</div>
<div class="space-y-10">
<!-- Toggles -->
<div class="space-y-6">
<div class="flex items-center justify-between">
<div>
<p class="font-headline font-extrabold text-slate-900 text-sm">Email Notifications</p>
<p class="text-[10px] font-bold text-on-surface-variant/70 uppercase tracking-widest mt-1">Daily summary and alerts</p>
</div>
<label class="relative inline-flex items-center cursor-pointer">
<input checked="" class="sr-only peer" type="checkbox"/>
<div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
</label>
</div>
<div class="flex items-center justify-between">
<div>
<p class="font-headline font-extrabold text-slate-900 text-sm">SMS Alerts</p>
<p class="text-[10px] font-bold text-on-surface-variant/70 uppercase tracking-widest mt-1">Critical system alerts</p>
</div>
<label class="relative inline-flex items-center cursor-pointer">
<input class="sr-only peer" type="checkbox"/>
<div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
</label>
</div>
</div>
<!-- Selectors -->
<div class="space-y-6 pt-10 border-t border-slate-200">
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1">Timezone</label>
<div class="relative">
<select class="appearance-none w-full bg-white border border-slate-300 rounded-2xl px-6 py-4 text-xs font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 focus:border-primary cursor-pointer transition-all">
<option>GMT -5:00 Eastern Time (US &amp; Canada)</option>
<option>GMT -8:00 Pacific Time (US &amp; Canada)</option>
<option>GMT +0:00 London (UTC)</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">expand_more</span>
</div>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1">Language</label>
<div class="relative">
<select class="appearance-none w-full bg-white border border-slate-300 rounded-2xl px-6 py-4 text-xs font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 focus:border-primary cursor-pointer transition-all">
<option>English (United States)</option>
<option>Spanish (ES)</option>
<option>French (FR)</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">language</span>
</div>
</div>
</div>
</div>
</div>
<!-- Danger Zone -->
</div>
</div>
<section class="w-full" aria-labelledby="infra-status-heading">
<div class="dash-infra-panel backdrop-blur-xl p-8 sm:p-10 rounded-[2rem] border border-white/90">
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
<h4 id="infra-status-heading" class="text-2xl font-extrabold font-headline text-on-background">Infrastructure <span class="text-primary italic font-editorial">Status</span></h4>
<p class="text-xs font-semibold text-on-surface-variant uppercase tracking-widest">Live system checks</p>
</div>
<div class="grid sm:grid-cols-3 gap-4">
<div class="dash-infra-row flex items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white/60 px-5 py-4">
<div class="flex items-center gap-3 min-w-0">
<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
<span class="material-symbols-outlined text-[22px]">database</span>
</span>
<span class="text-sm font-bold text-on-background truncate">Database</span>
</div>
<span class="text-[10px] font-black text-emerald-600 flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>OK</span>
</div>
<div class="dash-infra-row flex items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white/60 px-5 py-4">
<div class="flex items-center gap-3 min-w-0">
<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?php echo $has_visible_website ? 'bg-sky-50 text-sky-600 ring-sky-100' : 'bg-amber-50 text-amber-700 ring-amber-100'; ?> ring-1">
<span class="material-symbols-outlined text-[22px]">web</span>
</span>
<span class="text-sm font-bold text-on-background truncate">Clinic portal</span>
</div>
<span class="text-[10px] font-black <?php echo $has_visible_website ? 'text-emerald-600' : 'text-amber-600'; ?> flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full <?php echo $has_visible_website ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'; ?>"></span><?php echo $has_visible_website ? 'Ready' : 'Off'; ?></span>
</div>
<div class="dash-infra-row flex items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white/60 px-5 py-4">
<div class="flex items-center gap-3 min-w-0">
<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?php echo $is_subscription_active ? 'bg-primary/10 text-primary ring-primary/15' : 'bg-slate-100 text-slate-500 ring-slate-200'; ?> ring-1">
<span class="material-symbols-outlined text-[22px]">subscriptions</span>
</span>
<span class="text-sm font-bold text-on-background truncate">Subscription</span>
</div>
<span class="text-[10px] font-black <?php echo $is_subscription_active ? 'text-emerald-600' : 'text-on-surface-variant/70'; ?> flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full <?php echo $is_subscription_active ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span><?php echo $is_subscription_active ? 'Active' : ucfirst($subscription_state); ?></span>
</div>
</div>
</div>
</section>
</div>
<!-- Footer -->
<footer class="mt-auto p-10 border-t border-on-surface/5 flex flex-col md:flex-row items-center justify-between gap-10">
<div class="flex items-center gap-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-xl">verified</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 text-sm">Aetheris Dental System v2.4.0</p>
<div class="flex items-center gap-2 mt-1">
<span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
<span class="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Cloud Status: Optimal</span>
</div>
</div>
</div>
<div class="flex gap-10">
<a class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 hover:text-primary transition-colors" href="#">Privacy Policy</a>
<a class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 hover:text-primary transition-colors" href="#">Terms of Service</a>
<a class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 hover:text-primary transition-colors" href="#">Compliance (HIPAA)</a>
</div>
</footer>
</main>
</body></html>