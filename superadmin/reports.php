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
<!-- SideNavBar (Matches SCREEN_4) -->
<aside class="fixed left-0 top-0 h-full w-64 z-40 sidebar-glass flex flex-col py-8">
<div class="px-7 mb-10">
<a href="dashboard.php" class="block" aria-label="MyDental">
<img src="MyDental Logo.svg" alt="MyDental" class="h-11 w-auto max-w-full object-contain object-left"/>
</a>
<p class="text-on-surface-variant text-[10px] font-bold tracking-[0.2em] mt-2 opacity-60">MANAGEMENT CONSOLE</p>
</div>
<nav class="flex-1 space-y-1 overflow-y-auto no-scrollbar">
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="dashboard.php">
<span class="material-symbols-outlined text-[22px]">dashboard</span>
<span class="font-headline text-sm font-medium tracking-tight">Dashboard Analytics</span>
</a>
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
<!-- Active Item: Reports -->
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="reports.php">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">assessment</span>
<span class="font-headline text-sm font-bold tracking-tight">Reports</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
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
<!-- TopNavBar (Matches SCREEN_4) -->
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
<p class="text-sm font-bold text-on-surface">Dr. Smith</p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest opacity-60">Administrator</p>
</div>
<img alt="Dr. Smith Profile" class="w-10 h-10 rounded-full bg-surface-container-high border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDvM-XTip8l03DhTsEE8BwYgAWdhwg0pjXCb-NnaYgVm1iwI9UJVLepvfCtBM1H2gIxNZ_DBNcC2xB6Gkmhj8bkB7FmQFwkwKSQaVdlsHIyGlI2TZc2yYKnkaISoI2QGLMoVF_RaTlzaM38-z_lHukL-XtX45-tnnQ1lgFtOpio3hHsvmXJ4SMExptLTKdAmuGwYvXc9JDaHDaNO7XJgSVHQ37_E4CVh4nBf1__5AFT_RnDQVh23NVRLSeb-S_-Yq3GjoNJhEvhiUs"/>
</div>
</div>
</header>
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
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">article</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+15%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Reports</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">2,410</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-error/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-error-container/10 text-error rounded-xl shadow-sm">
<span class="material-symbols-outlined">pending_actions</span>
</div>
<span class="text-[10px] font-extrabold text-error bg-error-container px-2 py-1 rounded-lg uppercase">Action Required</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Pending Reports</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">42</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-green-50 text-green-600 rounded-xl shadow-sm">
<span class="material-symbols-outlined">task_alt</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+12%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Completed Reports</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">2,368</h3>
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
<option>Last 30 Days</option>
<option>Last 7 Days</option>
<option>Last Quarter</option>
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
                        Showing <span class="text-primary opacity-100">1-4</span> of 2,410 reports
                    </div>
</div>
<!-- Table Content -->
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Report Name</th>
<th class="px-8 py-5">Clinic</th>
<th class="px-8 py-5">Date Created</th>
<th class="px-8 py-5">Status</th>
<th class="px-10 py-5 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<!-- Row 1 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-primary shadow-sm border border-white">
<span class="material-symbols-outlined">monitoring</span>
</div>
<div>
<p class="text-sm font-bold text-on-surface">Q3 Revenue Growth Analysis</p>
<p class="text-[10px] text-on-surface-variant font-medium">Financial Report</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">Downtown Branch</span>
</td>
<td class="px-8 py-5 text-xs font-medium text-on-surface-variant">Oct 24, 2023, 14:30</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-[10px] font-bold uppercase tracking-wider">Completed</span>
</td>
<td class="px-10 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-xl">visibility</span></button>
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-xl">download</span></button>
<button class="p-2 text-on-surface-variant hover:text-error transition-colors"><span class="material-symbols-outlined text-xl">delete</span></button>
</div>
</td>
</tr>
<!-- Row 2 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-tertiary shadow-sm border border-white">
<span class="material-symbols-outlined">person_search</span>
</div>
<div>
<p class="text-sm font-bold text-on-surface">Patient Retention Survey</p>
<p class="text-[10px] text-on-surface-variant font-medium">Patient Experience</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">All Clinics</span>
</td>
<td class="px-8 py-5 text-xs font-medium text-on-surface-variant">Oct 23, 2023, 09:15</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-blue-50 text-blue-600 rounded-xl text-[10px] font-bold uppercase tracking-wider">In Progress</span>
</td>
<td class="px-10 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-xl">visibility</span></button>
<button class="p-2 text-on-surface-variant/30 cursor-not-allowed"><span class="material-symbols-outlined text-xl">download</span></button>
<button class="p-2 text-on-surface-variant hover:text-error transition-colors"><span class="material-symbols-outlined text-xl">delete</span></button>
</div>
</td>
</tr>
<!-- Row 3 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center text-purple-600 shadow-sm border border-white">
<span class="material-symbols-outlined">clinical_notes</span>
</div>
<div>
<p class="text-sm font-bold text-on-surface">Surgical Supplies Audit</p>
<p class="text-[10px] text-on-surface-variant font-medium">Inventory Report</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">Westside Dental</span>
</td>
<td class="px-8 py-5 text-xs font-medium text-on-surface-variant">Oct 21, 2023, 11:00</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-amber-50 text-amber-600 rounded-xl text-[10px] font-bold uppercase tracking-wider">Pending</span>
</td>
<td class="px-10 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-xl">visibility</span></button>
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-xl">download</span></button>
<button class="p-2 text-on-surface-variant hover:text-error transition-colors"><span class="material-symbols-outlined text-xl">delete</span></button>
</div>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Pagination (Matches SCREEN_4) -->
<div class="px-10 py-8 flex items-center justify-between border-t border-white/50">
<button class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2 disabled:opacity-40" disabled="">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                    </button>
<div class="flex items-center gap-2">
<button class="w-10 h-10 bg-primary text-white rounded-xl font-bold text-sm active-glow">1</button>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">2</button>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">3</button>
<span class="px-2 opacity-40">...</span>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">241</button>
</div>
<button class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
                        Next <span class="material-symbols-outlined text-lg">chevron_right</span>
</button>
</div>
</div>
<!-- Footer Grid (Same style as SCREEN_4) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
<div class="p-10 bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow flex items-center justify-between group cursor-pointer hover:-translate-y-1 transition-all">
<div>
<h4 class="text-xl font-extrabold font-headline text-on-surface">Report Templates</h4>
<p class="text-sm text-on-surface-variant mt-2 font-medium">Create customized templates for recurring clinic analytics.</p>
<button class="mt-6 px-6 py-2.5 bg-primary/10 text-primary hover:bg-primary hover:text-white rounded-2xl text-xs font-bold transition-all flex items-center gap-2">
                            Manage Templates
                            <span class="material-symbols-outlined text-sm">settings</span>
</button>
</div>
<div class="w-24 h-24 rounded-[2rem] bg-primary/5 flex items-center justify-center group-hover:bg-primary/10 transition-colors">
<span class="material-symbols-outlined text-4xl text-primary">description</span>
</div>
</div>
<div class="p-10 bg-gradient-to-br from-[#ffdcc3] to-[#ffb77e] rounded-[2.5rem] shadow-xl shadow-orange-900/10 flex items-center justify-between group cursor-pointer hover:-translate-y-1 transition-all">
<div>
<h4 class="text-xl font-extrabold font-headline text-[#2f1500]">System Health</h4>
<p class="text-sm text-[#6e3900]/80 mt-2 font-medium leading-relaxed">Infrastructure status and report generation latency metrics.</p>
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