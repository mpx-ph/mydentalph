<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';
$provider_nav_active = 'users';

$owner_prefill_full = trim($display_name !== '' ? $display_name : (string) ($_SESSION['full_name'] ?? $_SESSION['name'] ?? ''));
$owner_prefill_parts = preg_split('/\s+/', $owner_prefill_full, -1, PREG_SPLIT_NO_EMPTY);
$owner_prefill_first = '';
$owner_prefill_last = '';
if (is_array($owner_prefill_parts) && $owner_prefill_parts !== []) {
    $owner_prefill_first = (string) array_shift($owner_prefill_parts);
    $owner_prefill_last = trim(implode(' ', $owner_prefill_parts));
}
$owner_prefill_email = trim((string) ($_SESSION['email'] ?? ''));
if ($owner_prefill_email === '') {
    try {
        $st = $pdo->prepare('SELECT email FROM tbl_users WHERE user_id = ? LIMIT 1');
        $st->execute([$user_id]);
        $er = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($er)) {
            $owner_prefill_email = trim((string) ($er['email'] ?? ''));
        }
    } catch (Throwable $e) {
        // keep empty
    }
}
$add_user_owner_prefill_json = json_encode(
    [
        'first' => $owner_prefill_first,
        'last' => $owner_prefill_last,
        'email' => $owner_prefill_email,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
if ($add_user_owner_prefill_json === false) {
    $add_user_owner_prefill_json = '{"first":"","last":"","email":""}';
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Users</title>
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
                        "surface-variant": "#f1f5f9",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#e2e8f0",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#475569",
                        "background": "#f8fafc",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#8e4a00",
                        "tertiary-container": "#ffdcc3",
                        "error": "#ba1a1a"
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
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
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
        .provider-card-lift {
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .provider-card-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        @keyframes provider-modal-in {
            from { opacity: 0; transform: scale(0.94) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .provider-modal-panel {
            animation: provider-modal-in 0.4s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .provider-modal-backdrop {
            animation: provider-page-in 0.35s ease forwards;
        }
        body { font-family: 'Manrope', sans-serif; }
        .add-user-modal-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(43, 139, 235, 0.35) transparent;
        }
        .add-user-modal-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .add-user-modal-scroll::-webkit-scrollbar-thumb {
            background: rgba(43, 139, 235, 0.35);
            border-radius: 9999px;
        }
        .add-user-switch-track {
            width: 2.75rem;
            height: 1.5rem;
            border-radius: 9999px;
            background: #e2e8f0;
            transition: background 0.2s ease;
        }
        .add-user-switch-thumb {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 9999px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
            transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1);
        }
        input.add-user-switch-input:checked + .add-user-switch-track {
            background: #2b8beb;
        }
        input.add-user-switch-input:checked + .add-user-switch-track .add-user-switch-thumb {
            transform: translateX(1.25rem);
        }
        input.add-user-switch-input:focus-visible + .add-user-switch-track {
            outline: 2px solid rgba(43, 139, 235, 0.45);
            outline-offset: 2px;
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-background min-h-screen flex">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<!-- TopNavBar Component -->
<main class="flex-1 flex flex-col min-w-0 ml-64 provider-page-enter">
<header class="flex justify-between items-center w-full px-10 sticky top-0 z-30 bg-white/80 backdrop-blur-xl border-b border-slate-200/80 h-20 shadow-sm shadow-slate-200/40">
<div class="flex items-center gap-8">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
<span class="font-headline text-[10px] font-black uppercase tracking-[0.2em] text-primary">Clinic Status: Active</span>
</div>
<div class="h-4 w-px bg-slate-200"></div>
<span class="font-headline text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Plan: Premium Pro</span>
</div>
<div class="flex items-center gap-6">
<div class="flex items-center gap-6 text-on-surface-variant/60">
<button class="material-symbols-outlined hover:text-primary transition-colors" data-icon="notifications">notifications</button>
<button class="material-symbols-outlined hover:text-primary transition-colors" data-icon="help_outline">help_outline</button>
</div>
<div class="h-10 w-10 rounded-full overflow-hidden border-2 border-primary/20 p-0.5">
<img alt="Admin Avatar" class="w-full h-full rounded-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDtrO4z85y5D8iEXvQHkj0104-7MYy2wc5CzWIno3ZJoAATelGkmp38rAyasasOmmW4wQGq4BNoZKHdySndKoVpO0XB--E1A2_5vRl1kJ1g6AwqHiRB9aF7Yv5OPB7OqtgfW5uQki5KYLBdduA_b7JsF8T4nXHAsa8mcd_I8gYvQmeIbzL_UHdgtG6fegcuLkghbanknIbOkbHKq8KrbuR0fKD1anCt1DHD2CQeCqok7B1aLKIj7B0Yt_eChTBnS3Q8VKD6RcbiQO0"/>
</div>
</div>
</header>
<!-- Main Content -->
<div class="p-8 space-y-8">
<!-- Header Card -->
<section class="elevated-card provider-card-lift rounded-3xl p-10 flex flex-col gap-6">
<div class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> Team Management</div>
<div class="flex justify-between items-end">
<div>
<h2 class="font-headline font-extrabold tracking-tighter leading-tight text-on-background text-6xl">Team <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span></h2>
<p class="font-body text-xl font-medium text-slate-600 max-w-3xl leading-relaxed mt-6">Manage practitioner access and administrative permissions for your clinic.</p>
</div>
<button type="button" id="add-user-open" class="bg-primary text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:shadow-xl hover:shadow-primary/25 transition-all duration-300 active:scale-95 hover:scale-[1.02] flex items-center gap-2">
<span class="material-symbols-outlined text-base">person_add</span>
                        Add New User
                    </button>
</div>
</div>
<!-- Internal Control Bar (part of Header Card or separate, user asked for "each section" to sit on card) -->
<div class="flex flex-wrap gap-4 items-center justify-between pt-8 border-t border-slate-100">
<div class="flex gap-4">
<div class="relative">
<select class="appearance-none bg-slate-50 border border-slate-200 rounded-2xl px-8 py-3.5 pr-12 text-on-background text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 cursor-pointer transition-all">
<option>All Roles</option>
<option>Clinical Admin</option>
<option>Lead Dentist</option>
<option>Dental Hygienist</option>
<option>Reception Staff</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">expand_more</span>
</div>
<div class="relative">
<select class="appearance-none bg-slate-50 border border-slate-200 rounded-2xl px-8 py-3.5 pr-12 text-on-background text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 cursor-pointer transition-all">
<option>Active Status</option>
<option>Online Now</option>
<option>On Leave</option>
<option>Inactive</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">filter_list</span>
</div>
</div>
<div class="bg-primary/5 px-6 py-2.5 rounded-full border border-primary/10">
<p class="text-primary text-[10px] font-black uppercase tracking-widest">
                        Displaying <span class="text-slate-900">24</span> active staff members
                    </p>
</div>
</div>
</section>
<!-- Table Card -->
<div class="elevated-card provider-card-lift rounded-3xl overflow-hidden">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50 border-b border-slate-100">
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Practitioner</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Security Role</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Clinic Status</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Last Activity</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<!-- User Row 1 -->
<tr class="group hover:bg-slate-50/50 transition-colors duration-200">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-12 h-12 rounded-2xl bg-primary/10 overflow-hidden ring-2 ring-primary/5 transition-transform duration-300 group-hover:scale-105 group-hover:ring-primary/20">
<img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCVSmeAg0m57MZZCu5NZxbRzj4ccYyU2NhZv7uXtD8ZZzl6VM-_t_UKKOR7SsXeJBL2zA_qgH4SEtzpjRa_slK6vufp9KZXhX_0bCnBQ1vMUy537pHtbsLsdPyfIQsndvNbJ5SiWtgN3Q0A3fB3LXwI39N5GI5Uf0BWOccgT5dXNRxRUIw2mbysB05yMb9ft8P5aYIUdy7otgpj10EqNUAPRzuoLNCpSStugPTeCYLTlWKec3ImR8S8S92BA-VbpN3fCFBaQU3e5R0"/>
</div>
<div>
<div class="font-headline font-extrabold text-slate-900">Dr. Julian Thorne</div>
<div class="text-[11px] font-medium text-on-surface-variant/70 mt-0.5">j.thorne@aetheris-dental.com</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-primary/10 text-primary text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest">Lead Dentist</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
<span class="text-[10px] font-black text-on-surface-variant/70 uppercase tracking-widest">Active</span>
</div>
</td>
<td class="px-10 py-8">
<div class="text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest">2 mins ago</div>
</td>
<td class="px-10 py-8 text-right">
<div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110 hover:shadow-md">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-rose-500 hover:bg-rose-50 hover:border-rose-200 flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
<!-- Row 2 -->
<tr class="group hover:bg-slate-50/50 transition-colors duration-200">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-12 h-12 rounded-2xl bg-primary/10 overflow-hidden ring-2 ring-primary/5 transition-transform duration-300 group-hover:scale-105 group-hover:ring-primary/20">
<img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCBUOnZJ5ntR4l1hovJzsT9mMky3meAhD9alCVEISnezadCCcbDP4ks_MAxYbTIzI1V49EeK5hgB84Gzwq2flSHvaAtXCPxNliuef7NdnDcq10VBFnPLoPoWEbtqyDeHAXk3lQc72G8WCDRHuuSjatAgJItLzkudN1qW-3uBUQKlm1lJlgiWI7wNqCLzOUQxhfH7lQkJUom-X368c-Ek9828jUWnJS5g8Qygm-EVVmwhONQZSsU9o3wNkQa-ePg38bMFEEPJKmIqQc"/>
</div>
<div>
<div class="font-headline font-extrabold text-slate-900">Elena Rodriguez</div>
<div class="text-[11px] font-medium text-on-surface-variant/70 mt-0.5">e.rodriguez@aetheris-dental.com</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-slate-100 text-on-surface-variant text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest">Clinical Admin</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
<span class="text-[10px] font-black text-on-surface-variant/70 uppercase tracking-widest">Active</span>
</div>
</td>
<td class="px-10 py-8">
<div class="text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest">Today, 09:15 AM</div>
</td>
<td class="px-10 py-8 text-right">
<div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110 hover:shadow-md">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-rose-500 hover:bg-rose-50 hover:border-rose-200 flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
</tbody>
</table>
<!-- Pagination -->
<div class="px-10 py-8 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
<p class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant/70">
                    Showing <span class="text-slate-900">1 - 4</span> of 24
                </p>
<div class="flex gap-2">
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 disabled:opacity-50" disabled="">
<span class="material-symbols-outlined">chevron_left</span>
</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-primary text-white font-black text-xs shadow-lg shadow-primary/20">1</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-600 font-black text-xs hover:border-primary/30 transition-all">2</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-primary/30 transition-all">
<span class="material-symbols-outlined">chevron_right</span>
</button>
</div>
</div>
</div>
</div>
<!-- Footer Status -->
<footer class="mt-auto p-8 flex justify-center sticky bottom-0 z-10 pointer-events-none">
<div class="elevated-card pointer-events-auto px-10 py-4 rounded-full border border-slate-200/50 shadow-2xl flex items-center gap-10 text-[10px] font-black text-on-surface-variant/70 uppercase tracking-[0.2em]">
<div class="flex items-center gap-3 text-primary">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                System Log: Real-time
            </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">schedule</span>
                Last Login: 10:24 AM
            </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">location_on</span>
                IP: 192.168.1.1
            </div>
</div>
</footer>
<div id="add-user-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6" aria-hidden="true">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm provider-modal-backdrop" data-modal-dismiss></div>
<div class="relative w-full max-w-xl max-h-[min(92vh,46rem)] flex flex-col rounded-3xl bg-background shadow-2xl border border-slate-200/80 overflow-hidden provider-modal-panel" role="dialog" aria-modal="true" aria-labelledby="add-user-title">
<div class="shrink-0 px-8 pt-8 pb-5 bg-white border-b border-slate-100/90 relative pr-16">
<button type="button" class="absolute top-6 right-6 flex h-10 w-10 items-center justify-center rounded-full border border-slate-200/80 bg-white text-on-surface-variant hover:border-primary/30 hover:text-primary transition-all shadow-sm" data-modal-dismiss aria-label="Close">
<span class="material-symbols-outlined text-xl">close</span>
</button>
<p class="text-[10px] font-black uppercase tracking-[0.28em] text-primary flex items-center gap-3"><span class="w-8 h-px bg-primary/40"></span> Team onboarding</p>
<h3 id="add-user-title" class="font-headline mt-3 text-2xl sm:text-3xl font-extrabold tracking-tight text-on-background leading-tight">Add <span class="font-editorial italic font-normal text-primary">team member</span></h3>
<p class="mt-2 text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 leading-relaxed max-w-lg">Onboard a clinical specialist with tailored sidebar access. Provisioning hooks in later.</p>
</div>
<div class="add-user-modal-scroll flex-1 overflow-y-auto px-8 py-6 space-y-6">
<div class="rounded-2xl border border-primary/15 bg-surface-container-low/80 px-4 py-4 sm:px-5 sm:py-4 flex items-center gap-4">
<div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary">
<span class="material-symbols-outlined text-[22px]">person</span>
</div>
<div class="min-w-0 flex-1">
<p class="text-[11px] font-black uppercase tracking-widest text-on-background">Use my owner credentials</p>
<p class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant/80 mt-1 leading-snug">Register yourself as staff using your current session (no separate password).</p>
</div>
<label class="shrink-0 cursor-pointer flex items-center">
<input type="checkbox" id="add-user-owner-mode" class="add-user-switch-input sr-only" autocomplete="off"/>
<span class="add-user-switch-track relative flex items-center p-0.5">
<span class="add-user-switch-thumb"></span>
</span>
</label>
</div>
<div id="add-user-new-member-fields" class="space-y-5">
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">First name</label>
<input type="text" id="add-user-first" class="add-user-identity-field w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-medium text-on-background placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all read-only:bg-slate-50 read-only:text-on-surface-variant read-only:cursor-default" placeholder="Given name" autocomplete="given-name"/>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Last name</label>
<input type="text" id="add-user-last" class="add-user-identity-field w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-medium text-on-background placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all read-only:bg-slate-50 read-only:text-on-surface-variant read-only:cursor-default" placeholder="Family name" autocomplete="family-name"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Professional email</label>
<input type="email" id="add-user-email" class="add-user-identity-field w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-medium text-on-background placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all read-only:bg-slate-50 read-only:text-on-surface-variant read-only:cursor-default" placeholder="name@clinic.com" autocomplete="email"/>
</div>
<div id="add-user-password-wrap" class="rounded-2xl border border-slate-200/90 bg-white elevated-card p-5 space-y-4">
<div>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70">Sign-in password</p>
<p class="font-headline text-lg font-extrabold text-on-background mt-1">Set initial access password</p>
<p class="text-xs text-on-surface-variant mt-1.5 leading-relaxed">They’ll use this for first login. Share it through a secure channel outside the app.</p>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Password</label>
<div class="relative">
<span class="material-symbols-outlined pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-lg">key</span>
<input type="password" id="add-user-password" class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 pl-11 pr-11 py-3.5 text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Initial password" autocomplete="new-password"/>
<button type="button" class="add-user-pw-toggle absolute right-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-xl text-on-surface-variant hover:bg-slate-100 transition-colors" aria-label="Show password" data-target="add-user-password">
<span class="material-symbols-outlined text-lg">visibility_off</span>
</button>
</div>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Confirm</label>
<div class="relative">
<span class="material-symbols-outlined pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-lg">check_circle</span>
<input type="password" id="add-user-password-confirm" class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 pl-11 pr-11 py-3.5 text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Repeat password" autocomplete="new-password"/>
<button type="button" class="add-user-pw-toggle absolute right-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-xl text-on-surface-variant hover:bg-slate-100 transition-colors" aria-label="Show password" data-target="add-user-password-confirm">
<span class="material-symbols-outlined text-lg">visibility_off</span>
</button>
</div>
</div>
</div>
<div class="pt-1">
<div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
<span class="text-on-surface-variant/70">Password strength</span>
<span id="add-user-pw-strength-label" class="text-on-surface-variant">Waiting</span>
</div>
<div class="mt-2 h-1.5 rounded-full bg-slate-200 overflow-hidden">
<div id="add-user-pw-strength-bar" class="h-full rounded-full bg-primary/30 w-0 transition-all duration-300 ease-out"></div>
</div>
<p class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant/65 mt-3 leading-relaxed">At least 12 characters with uppercase, lowercase, a number, and a special character.</p>
</div>
</div>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Clinic role</label>
<div class="relative">
<select id="add-user-role" class="appearance-none w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 pr-12 text-sm font-semibold text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer">
<option>Manager</option>
<option>Staff</option>
<option>Doctor</option>
</select>
<span class="material-symbols-outlined pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-primary text-xl">expand_more</span>
</div>
</div>
</div>
<div class="shrink-0 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 px-8 py-5 bg-white border-t border-slate-100">
<button type="button" class="text-center sm:text-left text-[11px] font-black uppercase tracking-[0.2em] text-primary hover:text-primary/80 transition-colors py-2" data-modal-dismiss>Cancel</button>
<button type="button" id="add-user-submit" class="w-full sm:w-auto rounded-2xl bg-primary text-white px-8 py-4 text-[11px] font-black uppercase tracking-[0.18em] shadow-lg shadow-primary/25 hover:brightness-110 transition-all hover:scale-[1.02] active:scale-[0.98]">
Send verification code
</button>
</div>
</div>
</div>
</main>
<script type="application/json" id="add-user-owner-prefill"><?php echo $add_user_owner_prefill_json; ?></script>
<script>
(function () {
  var modal = document.getElementById('add-user-modal');
  var openBtn = document.getElementById('add-user-open');
  if (!modal || !openBtn) return;

  var ownerPrefill = { first: '', last: '', email: '' };
  var prefillEl = document.getElementById('add-user-owner-prefill');
  if (prefillEl) {
    try {
      ownerPrefill = JSON.parse(prefillEl.textContent || '{}');
    } catch (e) {
      ownerPrefill = { first: '', last: '', email: '' };
    }
  }

  var ownerCb = document.getElementById('add-user-owner-mode');
  var passwordWrap = document.getElementById('add-user-password-wrap');
  var pwInput = document.getElementById('add-user-password');
  var pwConfirm = document.getElementById('add-user-password-confirm');
  var strengthLabel = document.getElementById('add-user-pw-strength-label');
  var strengthBar = document.getElementById('add-user-pw-strength-bar');
  var firstInput = document.getElementById('add-user-first');
  var lastInput = document.getElementById('add-user-last');
  var emailInput = document.getElementById('add-user-email');

  var draftFirst = '';
  var draftLast = '';
  var draftEmail = '';

  function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (ownerCb && ownerCb.checked) {
      setOwnerMode(true);
    }
  }
  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    draftFirst = '';
    draftLast = '';
    draftEmail = '';
    if (ownerCb) ownerCb.checked = false;
    setOwnerMode(false);
    if (firstInput) firstInput.value = '';
    if (lastInput) lastInput.value = '';
    if (emailInput) emailInput.value = '';
    if (pwInput) pwInput.value = '';
    if (pwConfirm) pwConfirm.value = '';
    if (strengthLabel) strengthLabel.textContent = 'Waiting';
    if (strengthBar) {
      strengthBar.style.width = '0%';
      strengthBar.className = 'h-full rounded-full bg-primary/30 w-0 transition-all duration-300 ease-out';
    }
  }

  function applyOwnerIdentityFields() {
    if (!firstInput || !lastInput || !emailInput) return;
    firstInput.value = ownerPrefill.first || '';
    lastInput.value = ownerPrefill.last || '';
    emailInput.value = ownerPrefill.email || '';
    firstInput.readOnly = true;
    lastInput.readOnly = true;
    emailInput.readOnly = true;
  }

  function clearOwnerIdentityFields() {
    if (!firstInput || !lastInput || !emailInput) return;
    firstInput.readOnly = false;
    lastInput.readOnly = false;
    emailInput.readOnly = false;
    firstInput.value = draftFirst;
    lastInput.value = draftLast;
    emailInput.value = draftEmail;
  }

  function setOwnerMode(on) {
    if (on) {
      draftFirst = firstInput ? firstInput.value : '';
      draftLast = lastInput ? lastInput.value : '';
      draftEmail = emailInput ? emailInput.value : '';
      applyOwnerIdentityFields();
    } else {
      clearOwnerIdentityFields();
    }

    if (!passwordWrap) return;
    passwordWrap.classList.toggle('hidden', on);
    passwordWrap.setAttribute('aria-hidden', on ? 'true' : 'false');
    if (pwInput) {
      pwInput.disabled = on;
      pwInput.required = false;
    }
    if (pwConfirm) {
      pwConfirm.disabled = on;
      pwConfirm.required = false;
    }
    if (on) {
      if (strengthLabel) strengthLabel.textContent = '—';
      if (strengthBar) {
        strengthBar.style.width = '0%';
        strengthBar.className = 'h-full rounded-full w-0 transition-all duration-300 ease-out bg-slate-200';
      }
    } else if (pwInput) {
      pwInput.required = true;
      if (pwConfirm) pwConfirm.required = true;
      updatePasswordStrength();
    }
  }

  function scorePassword(pw) {
    if (!pw) return { score: 0, label: 'Waiting', width: 0, color: 'bg-slate-200' };
    var len = pw.length;
    var hasLower = /[a-z]/.test(pw);
    var hasUpper = /[A-Z]/.test(pw);
    var hasNum = /\d/.test(pw);
    var hasSpec = /[^A-Za-z0-9]/.test(pw);
    var rules = (len >= 12 ? 1 : 0) + (hasLower ? 1 : 0) + (hasUpper ? 1 : 0) + (hasNum ? 1 : 0) + (hasSpec ? 1 : 0);
    if (rules <= 2) return { score: 1, label: 'Weak', width: 33, color: 'bg-rose-400' };
    if (rules <= 4) return { score: 2, label: 'Good', width: 66, color: 'bg-amber-400' };
    return { score: 3, label: 'Strong', width: 100, color: 'bg-primary' };
  }

  function updatePasswordStrength() {
    if (!strengthLabel || !strengthBar || !pwInput || ownerCb && ownerCb.checked) return;
    var r = scorePassword(pwInput.value);
    strengthLabel.textContent = r.label;
    strengthLabel.className = r.score === 3 ? 'text-primary font-black' : 'text-on-surface-variant';
    strengthBar.style.width = r.width + '%';
    strengthBar.className = 'h-full rounded-full transition-all duration-300 ease-out ' + r.color;
  }

  if (ownerCb) {
    ownerCb.addEventListener('change', function () {
      setOwnerMode(ownerCb.checked);
    });
    setOwnerMode(ownerCb.checked);
  }

  modal.querySelectorAll('.add-user-pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-target');
      var input = id ? document.getElementById(id) : null;
      var icon = btn.querySelector('.material-symbols-outlined');
      if (!input || !icon) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon.textContent = show ? 'visibility' : 'visibility_off';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  if (pwInput) pwInput.addEventListener('input', updatePasswordStrength);

  openBtn.addEventListener('click', openModal);
  modal.querySelectorAll('[data-modal-dismiss]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });
})();
</script>
</body></html>