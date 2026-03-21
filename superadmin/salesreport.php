<?php require_once __DIR__ . '/require_superadmin.php'; ?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Sales Report | Clinical Precision</title>
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
<!-- SideNavBar (Styled like SCREEN_4) -->
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
<!-- Active Item: Sales Report -->
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="salesreport.php">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">payments</span>
<span class="font-headline text-sm font-bold tracking-tight">Sales Report</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="reports.php">
<span class="material-symbols-outlined text-[22px]">assessment</span>
<span class="font-headline text-sm font-medium tracking-tight">Reports</span>
</a>
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
<!-- TopNavBar (Styled like SCREEN_4) -->
<header class="fixed top-0 right-0 w-[calc(100%-16rem)] h-20 z-30 bg-white/70 backdrop-blur-xl border-b border-white/50 flex items-center justify-between px-8">
<div class="flex items-center gap-6 flex-1">
<div class="relative w-full max-w-md group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-xl">search</span>
<input class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm transition-all placeholder:text-on-surface-variant/50" placeholder="Search clinic data..." type="text"/>
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
<p class="text-sm font-bold text-on-surface">Admin Profile</p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest opacity-60">Super Administrator</p>
</div>
<img alt="Admin Avatar" class="w-10 h-10 rounded-full bg-surface-container-high border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBMpBH2Mgmt_qUei9Sl8nQK_RMFZ5j9uEC5mqyjuwQlorJ5kGRNtq6bF56x47D5qxX6F6PDJ7ofJPbUD0ovmyM8h4yK58g7ZzFxWkieCGGiueLLuZfCH212goxBcp7WkszrJdg_5e43D306GyR8bvkbLkgTUzRMuD-D2UnFFe2zVuouDvLdssr1ZhMxgkufKOAr9CpNIH297yPAqHpHrf4Hb5iqt5wti4XSbe5TkmDhZ2NLPM7diSgjj8hpCaZ1WDAxKHvo270m8o8"/>
</div>
</div>
</header>
<main class="ml-64 pt-20 min-h-screen">
<div class="pt-8 px-10 pb-16 space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Page Header -->
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Sales Report</h2>
<p class="text-on-surface-variant mt-2 font-medium">View and analyze clinic sales performance across all branches.</p>
</div>
<div class="flex items-center gap-3">
<button class="bg-white/60 backdrop-blur-md border border-white text-on-surface px-6 py-2.5 rounded-2xl text-sm font-bold shadow-sm flex items-center gap-2 hover:bg-white transition-all">
<span class="material-symbols-outlined text-lg">description</span>
                        Excel Export
                    </button>
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                        PDF Export
                    </button>
</div>
</section>
<!-- Filters Bar (Glassmorphism) -->
<div class="flex flex-wrap items-center gap-4">
<div class="bg-white/60 backdrop-blur-md px-6 py-3 rounded-2xl editorial-shadow flex items-center gap-4">
<div class="flex items-center gap-2 text-primary font-bold bg-primary/5 px-3 py-1.5 rounded-xl text-xs">
<span class="material-symbols-outlined text-[18px]">calendar_today</span>
                        Last 30 Days
                    </div>
<div class="h-6 w-px bg-outline-variant/30"></div>
<div class="relative group">
<select class="appearance-none bg-transparent border-none text-sm font-bold text-on-surface cursor-pointer focus:ring-0 pr-8">
<option>All Clinics</option>
<option>Downtown Branch</option>
<option>Eastside Medical</option>
</select>
<span class="material-symbols-outlined absolute right-0 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-lg">expand_more</span>
</div>
<div class="relative group">
<select class="appearance-none bg-transparent border-none text-sm font-bold text-on-surface cursor-pointer focus:ring-0 pr-8">
<option>All Services</option>
<option>Orthodontics</option>
<option>Implants</option>
<option>Cleaning</option>
</select>
<span class="material-symbols-outlined absolute right-0 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-lg">filter_list</span>
</div>
</div>
</div>
<!-- Summary Cards (Styled like SCREEN_4 metrics) -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+15%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Sales</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline tracking-tighter">$1.2M</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">analytics</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+8%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Monthly Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline tracking-tighter">$124k</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-primary/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-primary/5 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">stars</span>
</div>
<span class="text-[10px] font-extrabold text-primary bg-primary/5 px-2 py-1 rounded-lg uppercase">Main Driver</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Best Service</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline tracking-tighter">Orthodontics</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person_add</span>
</div>
<span class="text-[10px] font-extrabold text-primary bg-primary/5 px-2 py-1 rounded-lg uppercase">This Month</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">New Clients</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline tracking-tighter">45</h3>
</div>
</section>
<!-- Charts Section (Bento Grid Style) -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
<!-- Monthly Sales Trends -->
<div class="lg:col-span-2 bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-10 editorial-shadow">
<div class="flex items-center justify-between mb-8">
<div>
<h3 class="text-xl font-extrabold text-on-surface font-headline tracking-tight">Monthly Sales Trends</h3>
<p class="text-sm text-on-surface-variant font-medium">Revenue growth over time</p>
</div>
<div class="flex bg-surface-container-low/50 rounded-full p-1 border border-white/50">
<button class="px-6 py-1.5 rounded-full bg-white text-primary text-xs font-bold shadow-sm">Year</button>
<button class="px-6 py-1.5 rounded-full text-on-surface-variant text-xs font-bold hover:text-on-surface transition-colors">Quarter</button>
</div>
</div>
<div class="relative h-[300px] w-full mt-4 flex items-end justify-between px-2">
<div class="absolute inset-0 flex flex-col justify-between opacity-20 pointer-events-none">
<div class="border-b border-outline-variant w-full h-0"></div>
<div class="border-b border-outline-variant w-full h-0"></div>
<div class="border-b border-outline-variant w-full h-0"></div>
<div class="border-b border-outline-variant w-full h-0"></div>
</div>
<div class="relative w-full h-full">
<svg class="absolute bottom-0 left-0 w-full h-full overflow-visible" preserveaspectratio="none">
<defs>
<lineargradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
<stop offset="0%" stop-color="#0066ff" stop-opacity="0.2"></stop>
<stop offset="100%" stop-color="#0066ff" stop-opacity="0"></stop>
</lineargradient>
</defs>
<path d="M0 240 Q 100 200, 200 220 T 400 120 T 600 150 T 800 60" fill="none" stroke="#0066ff" stroke-linecap="round" stroke-width="4"></path>
<path d="M0 240 Q 100 200, 200 220 T 400 120 T 600 150 T 800 60 V 300 H 0 Z" fill="url(#chartGradient)"></path>
<circle cx="400" cy="120" fill="#0066ff" r="6" stroke="white" stroke-width="2"></circle>
</svg>
<div class="absolute left-1/2 top-[100px] -translate-x-1/2 bg-on-surface text-white px-3 py-1.5 rounded-xl text-[10px] font-bold shadow-xl">
                                JUN: $142,000
                            </div>
</div>
</div>
<div class="flex justify-between mt-8 px-2 text-[10px] font-bold text-on-surface-variant uppercase tracking-[0.2em] opacity-60">
<span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span><span>Aug</span>
</div>
</div>
<!-- Sales by Service (Donut) -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] p-10 editorial-shadow flex flex-col">
<h3 class="text-xl font-extrabold text-on-surface font-headline tracking-tight mb-1">Sales by Service</h3>
<p class="text-sm text-on-surface-variant font-medium mb-10">Revenue distribution</p>
<div class="relative h-64 w-full flex items-center justify-center">
<svg class="w-48 h-48 -rotate-90">
<circle cx="96" cy="96" fill="none" r="80" stroke="#f0f4f8" stroke-width="24"></circle>
<circle cx="96" cy="96" fill="none" r="80" stroke="#0066ff" stroke-dasharray="311 502" stroke-dashoffset="0" stroke-linecap="round" stroke-width="24"></circle>
<circle cx="96" cy="96" fill="none" r="80" stroke="#404752" stroke-dasharray="115 502" stroke-dashoffset="-311" stroke-linecap="round" stroke-width="24"></circle>
<circle cx="96" cy="96" fill="none" r="80" stroke="#c0c7d4" stroke-dasharray="75 502" stroke-dashoffset="-426" stroke-linecap="round" stroke-width="24"></circle>
</svg>
<div class="absolute inset-0 flex flex-col items-center justify-center">
<span class="text-3xl font-black text-on-surface font-headline">62%</span>
<span class="text-[10px] uppercase font-bold text-primary tracking-widest">Orthodontics</span>
</div>
</div>
<div class="mt-8 space-y-4">
<div class="flex items-center justify-between">
<div class="flex items-center gap-3">
<span class="w-3 h-3 rounded-full bg-primary"></span>
<span class="text-sm font-bold text-on-surface">Orthodontics</span>
</div>
<span class="text-sm font-bold text-on-surface">62%</span>
</div>
<div class="flex items-center justify-between">
<div class="flex items-center gap-3">
<span class="w-3 h-3 rounded-full bg-on-surface-variant"></span>
<span class="text-sm font-bold text-on-surface">Implants</span>
</div>
<span class="text-sm font-bold text-on-surface">23%</span>
</div>
<div class="flex items-center justify-between">
<div class="flex items-center gap-3">
<span class="w-3 h-3 rounded-full bg-outline-variant"></span>
<span class="text-sm font-bold text-on-surface">Cleaning</span>
</div>
<span class="text-sm font-bold text-on-surface">15%</span>
</div>
</div>
</div>
</div>
<!-- Recent Transactions Table (Styled like SCREEN_4 table) -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<div class="px-10 py-8 flex items-center justify-between border-b border-white/50">
<h3 class="text-xl font-extrabold font-headline text-on-surface tracking-tight">Recent Transactions</h3>
<button class="text-primary font-bold text-sm hover:underline">View All History</button>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Date</th>
<th class="px-8 py-5">Tenant/Clinic</th>
<th class="px-8 py-5">Service</th>
<th class="px-8 py-5">Amount</th>
<th class="px-8 py-5">Status</th>
<th class="px-10 py-5 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<!-- Row 1 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-6 text-sm font-bold text-on-surface-variant">Oct 12, 2023</td>
<td class="px-8 py-6">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-primary shadow-sm border border-white">
<span class="material-symbols-outlined text-lg">domain</span>
</div>
<span class="text-sm font-bold text-on-surface">Downtown Branch</span>
</div>
</td>
<td class="px-8 py-6">
<span class="px-3 py-1.5 bg-primary/5 text-primary rounded-xl text-[10px] font-bold uppercase tracking-wider">Invisalign</span>
</td>
<td class="px-8 py-6 font-black text-sm text-on-surface">$4,500.00</td>
<td class="px-8 py-6">
<span class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>
                                        Paid
                                    </span>
</td>
<td class="px-10 py-6 text-right">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors">
<span class="material-symbols-outlined">more_horiz</span>
</button>
</td>
</tr>
<!-- Row 2 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-6 text-sm font-bold text-on-surface-variant">Oct 11, 2023</td>
<td class="px-8 py-6">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-surface-container-high flex items-center justify-center text-on-surface-variant shadow-sm border border-white">
<span class="material-symbols-outlined text-lg">domain</span>
</div>
<span class="text-sm font-bold text-on-surface">Eastside Medical</span>
</div>
</td>
<td class="px-8 py-6">
<span class="px-3 py-1.5 bg-on-surface-variant/5 text-on-surface-variant rounded-xl text-[10px] font-bold uppercase tracking-wider">Dental Implant</span>
</td>
<td class="px-8 py-6 font-black text-sm text-on-surface">$2,850.00</td>
<td class="px-8 py-6">
<span class="px-3 py-1.5 bg-amber-50 text-amber-600 rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span>
                                        Pending
                                    </span>
</td>
<td class="px-10 py-6 text-right">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors">
<span class="material-symbols-outlined">more_horiz</span>
</button>
</td>
</tr>
<!-- Row 3 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-6 text-sm font-bold text-on-surface-variant">Oct 10, 2023</td>
<td class="px-8 py-6">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-xl bg-blue-50 flex items-center justify-center text-primary shadow-sm border border-white">
<span class="material-symbols-outlined text-lg">domain</span>
</div>
<span class="text-sm font-bold text-on-surface">Downtown Branch</span>
</div>
</td>
<td class="px-8 py-6">
<span class="px-3 py-1.5 bg-primary/5 text-primary rounded-xl text-[10px] font-bold uppercase tracking-wider">Teeth Whitening</span>
</td>
<td class="px-8 py-6 font-black text-sm text-on-surface">$650.00</td>
<td class="px-8 py-6">
<span class="px-3 py-1.5 bg-error/10 text-error rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                                        Overdue
                                    </span>
</td>
<td class="px-10 py-6 text-right">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors">
<span class="material-symbols-outlined">more_horiz</span>
</button>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Pagination -->
<div class="px-10 py-8 flex items-center justify-between border-t border-white/50">
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">Showing 3 of 1,240 transactions</p>
<div class="flex gap-2">
<button class="w-10 h-10 bg-white/60 text-on-surface-variant hover:bg-white rounded-xl border border-white flex items-center justify-center transition-all shadow-sm">
<span class="material-symbols-outlined text-lg">chevron_left</span>
</button>
<button class="w-10 h-10 bg-primary text-white rounded-xl font-bold text-sm active-glow flex items-center justify-center">1</button>
<button class="w-10 h-10 bg-white/60 text-on-surface-variant hover:bg-white rounded-xl border border-white flex items-center justify-center transition-all shadow-sm">
<span class="material-symbols-outlined text-lg">chevron_right</span>
</button>
</div>
</div>
</div>
</div>
</main>
<!-- Floating Action Button (Matches SCREEN_4 aesthetic) -->
<div class="fixed bottom-10 right-10 z-50">
<button class="w-16 h-16 rounded-3xl bg-primary text-white primary-glow flex items-center justify-center hover:scale-110 active:scale-95 transition-all">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">add</span>
</button>
</div>
</body></html>