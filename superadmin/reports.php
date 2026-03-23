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
?>
<!-- Main Content Area -->
<main class="ml-64 pt-20 min-h-screen">
<div class="pt-8 px-10 pb-16 space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Header Section -->
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div class="max-w-3xl">
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Reports</h2>
<p class="text-on-surface-variant mt-2 font-medium leading-relaxed">Contains generated summaries of system data: tenant activity, user registrations, usage metrics, and exports filtered by date, tenant, and other criteria.</p>
</div>
<div class="flex items-center gap-3 shrink-0">
<button type="button" class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">add_circle</span>
                        Generate New Report
                    </button>
</div>
</section>
<!-- Report scope: what this area covers -->
<section aria-label="Report types and filtering capabilities" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
<div class="bg-white/60 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm w-fit mb-4">
<span class="material-symbols-outlined">domain</span>
</div>
<h3 class="text-sm font-extrabold text-on-surface font-headline">Tenant activity reports</h3>
<p class="text-on-surface-variant text-sm mt-2 leading-relaxed">Logins, sessions, and actions per tenant for compliance and support.</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="p-2.5 bg-purple-50 text-purple-600 rounded-xl shadow-sm w-fit mb-4">
<span class="material-symbols-outlined">person_add</span>
</div>
<h3 class="text-sm font-extrabold text-on-surface font-headline">User registration reports</h3>
<p class="text-on-surface-variant text-sm mt-2 leading-relaxed">New accounts, roles, and verification status across the platform.</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="p-2.5 bg-green-50 text-green-600 rounded-xl shadow-sm w-fit mb-4">
<span class="material-symbols-outlined">bar_chart</span>
</div>
<h3 class="text-sm font-extrabold text-on-surface font-headline">Usage statistics</h3>
<p class="text-on-surface-variant text-sm mt-2 leading-relaxed">Feature use, API volume, and resource consumption over time.</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border border-primary/10">
<div class="p-2.5 bg-primary/10 text-primary rounded-xl shadow-sm w-fit mb-4">
<span class="material-symbols-outlined">filter_alt</span>
</div>
<h3 class="text-sm font-extrabold text-on-surface font-headline">Filtered data</h3>
<p class="text-on-surface-variant text-sm mt-2 leading-relaxed">Narrow results by date range, tenant, report type, and more before export.</p>
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
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all" aria-label="Tenant filter">
<option>All tenants</option>
<option>Acme Dental Group</option>
<option>Metro Oral Care</option>
<option>Harborview Clinic</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">domain</span>
</div>
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all" aria-label="Report type">
<option>All report types</option>
<option>Tenant activity</option>
<option>User registration</option>
<option>Usage statistics</option>
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
<th class="px-10 py-5">Report</th>
<th class="px-8 py-5">Tenant</th>
<th class="px-8 py-5">Generated</th>
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
<p class="text-sm font-bold text-on-surface">Tenant activity — weekly summary</p>
<p class="text-[10px] text-on-surface-variant font-medium">Tenant activity report</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">Acme Dental Group</span>
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
<p class="text-sm font-bold text-on-surface">New user registrations — September</p>
<p class="text-[10px] text-on-surface-variant font-medium">User registration report</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">All tenants</span>
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
<span class="material-symbols-outlined">data_usage</span>
</div>
<div>
<p class="text-sm font-bold text-on-surface">Platform usage — API &amp; storage</p>
<p class="text-[10px] text-on-surface-variant font-medium">Usage statistics</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">Metro Oral Care</span>
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
</div>
</main>
</body></html>