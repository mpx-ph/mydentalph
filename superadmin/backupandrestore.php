<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision | Backup &amp; Restore</title>
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
                        "primary": "#0066ff",
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
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface selection:bg-primary/10 min-h-screen">
<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-full w-64 z-40 sidebar-glass flex flex-col py-8">
<div class="px-7 mb-10">
<h1 class="text-xl font-extrabold text-on-surface tracking-tight font-headline flex items-center gap-2">
<span class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shadow-lg shadow-primary/30">
<span class="material-symbols-outlined text-white text-lg">medical_services</span>
</span>
            Clinical Precision
        </h1>
<p class="text-on-surface-variant text-[10px] font-bold tracking-[0.2em] uppercase mt-2 opacity-60">Management Console</p>
</div>
<nav class="flex-1 space-y-1 overflow-y-auto no-scrollbar">
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">dashboard</span>
<span class="font-headline text-sm font-medium tracking-tight">Dashboard Analytics</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">groups</span>
<span class="font-headline text-sm font-medium tracking-tight">Tenant Management</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">payments</span>
<span class="font-headline text-sm font-medium tracking-tight">Sales Report</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">assessment</span>
<span class="font-headline text-sm font-medium tracking-tight">Reports</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">history_edu</span>
<span class="font-headline text-sm font-medium tracking-tight">Audit Logs</span>
</a>
</div>
<!-- Active Item: Backup & Restore -->
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="#">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">settings_backup_restore</span>
<span class="font-headline text-sm font-bold tracking-tight">Backup and Restore</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
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
<input class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm transition-all placeholder:text-on-surface-variant/50" placeholder="Search archives or logs..." type="text"/>
</div>
</div>
<div class="flex items-center gap-4">
<button class="hover:bg-surface-container-low rounded-full p-2.5 transition-all relative">
<span class="material-symbols-outlined text-on-surface-variant">notifications</span>
<span class="absolute top-2.5 right-2.5 w-2 h-2 bg-error rounded-full border-2 border-white"></span>
</button>
<button class="hover:bg-surface-container-low rounded-full p-2.5 transition-all">
<span class="material-symbols-outlined text-on-surface-variant">help_outline</span>
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
<section>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Backup &amp; Restore</h2>
<p class="text-on-surface-variant mt-2 font-medium">Manage database snapshots and system recovery</p>
</section>
<!-- Summary Metrics -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white/60 backdrop-blur-md p-7 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-6">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">history</span>
</div>
<span class="text-[10px] font-extrabold text-primary bg-primary/10 px-3 py-1 rounded-lg uppercase">Status: OK</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Last Successful Backup</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">12 mins ago</h3>
<div class="mt-4 flex items-center text-[10px] text-green-600 font-extrabold uppercase gap-1.5">
<span class="material-symbols-outlined text-sm">check_circle</span>
                    Integrity Verified
                </div>
</div>
<div class="bg-white/60 backdrop-blur-md p-7 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-6">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">folder_zip</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Backups</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">42 Archives</h3>
<p class="mt-4 text-[10px] text-on-surface-variant font-bold uppercase tracking-widest opacity-60">Retention policy: 90 days active</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-7 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-6">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">cloud_queue</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Cloud Storage Used</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">1.2 TB <span class="text-base font-medium text-on-surface-variant">/ 5 TB</span></h3>
<div class="mt-6 h-2 w-full bg-slate-100 rounded-full overflow-hidden">
<div class="h-full bg-primary shadow-[0_0_8px_rgba(0,102,255,0.3)]" style="width: 24%"></div>
</div>
<p class="mt-2 text-[10px] text-right text-on-surface-variant font-bold uppercase tracking-widest">24% capacity used</p>
</div>
</section>
<!-- Database Backup Section -->
<section class="max-w-3xl mx-auto w-full">
<div class="bg-white/70 backdrop-blur-xl p-10 rounded-[2rem] editorial-shadow relative overflow-hidden group">
<div class="relative z-10">
<div class="flex items-center gap-5 mb-10">
<div class="w-14 h-14 bg-primary rounded-2xl flex items-center justify-center text-white primary-glow">
<span class="material-symbols-outlined text-3xl">backup</span>
</div>
<div>
<h2 class="text-2xl font-extrabold font-headline text-on-surface">Manual Database Backup</h2>
<p class="text-on-surface-variant text-sm font-medium">Trigger an immediate snapshot of all clinical data.</p>
</div>
</div>
<div class="space-y-8">
<div class="flex items-center justify-between p-6 bg-white/50 border border-white/60 rounded-2xl backdrop-blur-sm">
<div>
<h4 class="font-bold text-on-surface">Auto-Backup Schedule</h4>
<p class="text-xs text-on-surface-variant font-medium">Perform snapshots every 6 hours</p>
</div>
<label class="relative inline-flex items-center cursor-pointer">
<input checked="" class="sr-only peer" type="checkbox"/>
<div class="w-14 h-7 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
</label>
</div>
<button class="w-full bg-primary text-white py-5 rounded-2xl font-bold text-lg shadow-xl shadow-primary/20 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all flex items-center justify-center gap-3">
<span class="material-symbols-outlined">add_circle</span>
                            Create New Backup
                        </button>
</div>
</div>
<!-- Decorative element -->
<div class="absolute -bottom-10 -right-10 opacity-[0.03] pointer-events-none transition-transform group-hover:scale-110 duration-700">
<span class="material-symbols-outlined text-[240px]" style="font-variation-settings: 'FILL' 1;">database</span>
</div>
</div>
</section>
<!-- Backup History Table -->
<section class="bg-white/70 backdrop-blur-xl rounded-[2rem] editorial-shadow overflow-hidden">
<div class="p-8 border-b border-white flex items-center justify-between">
<div>
<h2 class="text-xl font-extrabold font-headline text-on-surface">Backup History</h2>
<p class="text-on-surface-variant text-sm font-medium mt-1">Audit log of system snapshots for the last 30 days</p>
</div>
<button class="text-primary font-bold text-sm flex items-center gap-2 px-5 py-2.5 bg-white/50 border border-white rounded-xl hover:bg-white transition-all shadow-sm">
                    View Full Archive <span class="material-symbols-outlined text-lg">arrow_forward</span>
</button>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="bg-surface-container-low/30 text-on-surface-variant font-bold text-[10px] uppercase tracking-[0.2em]">
<th class="px-8 py-5">Date &amp; Time</th>
<th class="px-8 py-5">Filename</th>
<th class="px-8 py-5">Size</th>
<th class="px-8 py-5">Type</th>
<th class="px-8 py-5 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-white">
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-8 py-6">
<div class="flex flex-col">
<span class="font-bold text-on-surface text-sm">Oct 24, 2023</span>
<span class="text-xs text-on-surface-variant font-medium mt-0.5">04:12 AM</span>
</div>
</td>
<td class="px-8 py-6">
<span class="font-mono text-xs text-on-surface-variant bg-surface-container-low px-2 py-1 rounded">full_snap_20231024_0412.zip</span>
</td>
<td class="px-8 py-6 text-sm font-semibold text-on-surface-variant">142.8 GB</td>
<td class="px-8 py-6">
<span class="px-3 py-1 bg-green-50 text-green-600 rounded-lg text-[10px] font-extrabold tracking-tight uppercase">Auto</span>
</td>
<td class="px-8 py-6 text-right space-x-2">
<button class="p-2 hover:bg-white text-primary rounded-xl transition-all shadow-sm group-hover:shadow-md" title="Download">
<span class="material-symbols-outlined text-xl">download</span>
</button>
<button class="p-2 hover:bg-error/10 text-error rounded-xl transition-all" title="Delete">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</td>
</tr>
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-8 py-6">
<div class="flex flex-col">
<span class="font-bold text-on-surface text-sm">Oct 23, 2023</span>
<span class="text-xs text-on-surface-variant font-medium mt-0.5">10:45 PM</span>
</div>
</td>
<td class="px-8 py-6">
<span class="font-mono text-xs text-on-surface-variant bg-surface-container-low px-2 py-1 rounded">pre_update_manual_231023.zip</span>
</td>
<td class="px-8 py-6 text-sm font-semibold text-on-surface-variant">139.2 GB</td>
<td class="px-8 py-6">
<span class="px-3 py-1 bg-blue-50 text-blue-600 rounded-lg text-[10px] font-extrabold tracking-tight uppercase">Manual</span>
</td>
<td class="px-8 py-6 text-right space-x-2">
<button class="p-2 hover:bg-white text-primary rounded-xl transition-all shadow-sm group-hover:shadow-md" title="Download">
<span class="material-symbols-outlined text-xl">download</span>
</button>
<button class="p-2 hover:bg-error/10 text-error rounded-xl transition-all" title="Delete">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</td>
</tr>
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-8 py-6">
<div class="flex flex-col">
<span class="font-bold text-on-surface text-sm">Oct 23, 2023</span>
<span class="text-xs text-on-surface-variant font-medium mt-0.5">04:00 AM</span>
</div>
</td>
<td class="px-8 py-6">
<span class="font-mono text-xs text-on-surface-variant bg-surface-container-low px-2 py-1 rounded">full_snap_20231023_0400.zip</span>
</td>
<td class="px-8 py-6 text-sm font-semibold text-on-surface-variant">141.5 GB</td>
<td class="px-8 py-6">
<span class="px-3 py-1 bg-green-50 text-green-600 rounded-lg text-[10px] font-extrabold tracking-tight uppercase">Auto</span>
</td>
<td class="px-8 py-6 text-right space-x-2">
<button class="p-2 hover:bg-white text-primary rounded-xl transition-all shadow-sm group-hover:shadow-md" title="Download">
<span class="material-symbols-outlined text-xl">download</span>
</button>
<button class="p-2 hover:bg-error/10 text-error rounded-xl transition-all" title="Delete">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</td>
</tr>
</tbody>
</table>
</div>
<div class="px-8 py-6 bg-surface-container-low/20 flex items-center justify-between border-t border-white">
<span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">Showing 3 of 42 historical backups</span>
<div class="flex gap-2">
<button class="px-4 py-2 text-xs font-bold bg-white/50 border border-white rounded-xl opacity-50 cursor-not-allowed">Previous</button>
<button class="px-4 py-2 text-xs font-bold bg-white border border-white rounded-xl hover:bg-white transition-all shadow-sm">Next</button>
</div>
</div>
</section>
</div>
</main>
</body></html>