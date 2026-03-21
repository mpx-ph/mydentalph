<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision | Dashboard Analytics</title>
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
                        "primary": "#0066ff", /* Vibrant Brand Blue */
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
        .pulse-live {
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4);
            animation: pulse-animation 2s infinite;
        }
        @keyframes pulse-animation {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .ai-glow {
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 20px 40px -15px rgba(0, 102, 255, 0.4);
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
<!-- Active Item: Dashboard Analytics -->
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="dashboard.php">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">dashboard</span>
<span class="font-headline text-sm font-bold tracking-tight">Dashboard Analytics</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
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
<!-- Main Content Area -->
<main class="ml-64 min-h-screen">
<!-- TopNavBar -->
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
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Dashboard Analytics</h2>
<p class="text-on-surface-variant mt-2 font-medium">Real-time performance metrics for Clinical Precision ecosystem.</p>
</div>
<div class="flex items-center gap-3">
<button class="bg-white/80 backdrop-blur-md text-primary px-5 py-2.5 rounded-2xl text-sm font-bold border border-white flex items-center gap-2 hover:bg-white transition-all shadow-sm">
<span class="material-symbols-outlined text-lg">calendar_today</span>
                        Last 30 Days
                    </button>
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">download</span>
                        Export Report
                    </button>
</div>
</section>
<!-- Top Metrics Bento Grid -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6">
<!-- Card 1 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">corporate_fare</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+12%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Tenants</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">1,284</h3>
</div>
<!-- Card 2 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">medical_services</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+8%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Active Clinics</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">942</h3>
</div>
<!-- Card 3 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">payments</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+24%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Monthly Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">$428k</h3>
</div>
<!-- Card 4 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">person_add</span>
</div>
<span class="text-[10px] font-extrabold text-blue-600 bg-blue-50 px-2 py-1 rounded-lg uppercase">New</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Registrations</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">42</h3>
</div>
<!-- Card 5 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-tertiary-container/10 text-tertiary rounded-xl shadow-sm">
<span class="material-symbols-outlined">warning</span>
</div>
<span class="text-[10px] font-extrabold text-tertiary bg-tertiary-fixed px-2 py-1 rounded-lg uppercase">Alert</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Expiring</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">15</h3>
</div>
<!-- Card 6 -->
<div class="bg-white/60 backdrop-blur-md p-6 rounded-2xl editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">bolt</span>
</div>
<div class="flex items-center gap-1.5">
<div class="w-2 h-2 rounded-full bg-green-500 pulse-live"></div>
<span class="text-[10px] font-extrabold text-green-600 uppercase">99.9%</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Activity Rate</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">94%</h3>
</div>
</section>
<!-- Main Charts & Insights Section -->
<section class="grid grid-cols-12 gap-8">
<!-- Revenue Analytics & AI Widget -->
<div class="col-span-12 lg:col-span-8 space-y-8">
<!-- Revenue Analytics Line Chart -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow relative overflow-hidden">
<div class="absolute top-0 right-0 p-8">
<div class="flex bg-surface-container-low/50 p-1.5 rounded-2xl border border-white/40">
<button class="px-5 py-2 text-xs font-bold rounded-xl bg-white shadow-sm text-primary">Monthly</button>
<button class="px-5 py-2 text-xs font-bold rounded-xl text-on-surface-variant hover:text-on-surface">Weekly</button>
<button class="px-5 py-2 text-xs font-bold rounded-xl text-on-surface-variant hover:text-on-surface">Yearly</button>
</div>
</div>
<div class="mb-10">
<h4 class="text-xl font-extrabold font-headline">Revenue Analytics</h4>
<p class="text-sm text-on-surface-variant font-medium">Performance tracking across global nodes</p>
</div>
<!-- Mock Chart Area -->
<div class="h-64 flex items-end justify-between gap-2 relative">
<div class="absolute inset-0 flex flex-col justify-between opacity-5">
<div class="border-t border-on-surface"></div>
<div class="border-t border-on-surface"></div>
<div class="border-t border-on-surface"></div>
<div class="border-t border-on-surface"></div>
</div>
<svg class="absolute inset-0 w-full h-full" preserveaspectratio="none" viewbox="0 0 1000 200">
<defs>
<lineargradient id="chartGradient" x1="0" x2="0" y1="0" y2="1">
<stop offset="0%" stop-color="#0066ff" stop-opacity="0.25"></stop>
<stop offset="100%" stop-color="#0066ff" stop-opacity="0"></stop>
</lineargradient>
</defs>
<path d="M0,150 Q100,140 200,160 T400,100 T600,80 T800,120 T1000,60 L1000,200 L0,200 Z" fill="url(#chartGradient)"></path>
<path d="M0,150 Q100,140 200,160 T400,100 T600,80 T800,120 T1000,60" fill="none" stroke="#0066ff" stroke-linecap="round" stroke-width="4"></path>
<circle class="shadow-lg" cx="400" cy="100" fill="#0066ff" r="6"></circle>
</svg>
<div class="flex w-full justify-between pt-4 mt-auto text-[10px] font-bold text-on-surface-variant uppercase tracking-[0.2em] relative z-10">
<span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span><span>Aug</span>
</div>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<!-- Tenant Growth Bar Chart -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<h4 class="text-lg font-extrabold font-headline mb-6">Tenant Growth</h4>
<div class="flex items-end justify-between h-40 px-2">
<div class="w-8 bg-surface-container-high rounded-xl h-24 hover:bg-primary/20 transition-colors"></div>
<div class="w-8 bg-surface-container-high rounded-xl h-16 hover:bg-primary/20 transition-colors"></div>
<div class="w-8 bg-surface-container-high rounded-xl h-32 hover:bg-primary/20 transition-colors"></div>
<div class="w-8 bg-primary rounded-xl h-40 primary-glow"></div>
<div class="w-8 bg-surface-container-high rounded-xl h-28 hover:bg-primary/20 transition-colors"></div>
<div class="w-8 bg-surface-container-high rounded-xl h-36 hover:bg-primary/20 transition-colors"></div>
</div>
<div class="flex justify-between mt-6 text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">
<span>Mar</span><span>Apr</span><span>May</span><span>Jun</span><span>Jul</span><span>Aug</span>
</div>
</div>
<!-- AI Insights Widget -->
<div class="bg-gradient-to-br from-primary via-[#1a80ff] to-[#0052cc] text-white p-8 rounded-[2rem] ai-glow relative overflow-hidden flex flex-col justify-between group">
<div class="absolute -right-8 -top-8 w-40 h-40 bg-white/10 rounded-full blur-[60px] group-hover:bg-white/20 transition-all duration-700"></div>
<div class="absolute -left-10 -bottom-10 w-32 h-32 bg-primary-fixed-dim/10 rounded-full blur-[50px]"></div>
<div>
<div class="flex items-center gap-2 mb-6">
<div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center backdrop-blur-md">
<span class="material-symbols-outlined text-white text-lg">psychology</span>
</div>
<span class="text-[10px] font-bold uppercase tracking-[0.2em] text-white/90">AI Insights</span>
</div>
<h5 class="text-xl font-bold leading-tight font-headline">Tenant growth increased by 18% in the last quarter.</h5>
<p class="text-white/80 text-sm mt-4 leading-relaxed font-medium">Consider increasing infrastructure capacity in the North-East region to maintain 99.9% uptime.</p>
</div>
<button class="w-fit mt-8 px-6 py-2.5 bg-white text-primary hover:bg-white/90 rounded-2xl text-xs font-bold transition-all shadow-xl shadow-black/10">View Full Analysis</button>
</div>
</div>
</div>
<!-- Side Panels: Distribution & Activity -->
<div class="col-span-12 lg:col-span-4 space-y-8">
<!-- Activity Distribution Donut -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<h4 class="text-lg font-extrabold font-headline mb-6">Clinic Activity</h4>
<div class="relative w-48 h-48 mx-auto flex items-center justify-center">
<svg class="w-full h-full -rotate-90" viewbox="0 0 100 100">
<circle cx="50" cy="50" fill="transparent" r="40" stroke="#f1f5f9" stroke-width="10"></circle>
<circle class="drop-shadow-[0_0_8px_rgba(0,102,255,0.4)]" cx="50" cy="50" fill="transparent" r="40" stroke="#0066ff" stroke-dasharray="170 251" stroke-linecap="round" stroke-width="12"></circle>
<circle cx="50" cy="50" fill="transparent" r="40" stroke="#ba1a1a" stroke-dasharray="30 251" stroke-dashoffset="-170" stroke-linecap="round" stroke-width="12"></circle>
</svg>
<div class="absolute flex flex-col items-center">
<span class="text-4xl font-extrabold font-headline text-on-surface">942</span>
<span class="text-[10px] uppercase font-bold text-on-surface-variant tracking-widest opacity-60">Total Units</span>
</div>
</div>
<div class="mt-8 space-y-4">
<div class="flex items-center justify-between p-3 rounded-xl hover:bg-primary/5 transition-colors cursor-default group">
<div class="flex items-center gap-3">
<div class="w-3 h-3 rounded-full bg-primary group-hover:scale-125 transition-transform"></div>
<span class="text-sm font-semibold">Active</span>
</div>
<span class="text-sm font-bold text-primary">84%</span>
</div>
<div class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-100 transition-colors cursor-default group">
<div class="flex items-center gap-3">
<div class="w-3 h-3 rounded-full bg-surface-container-high group-hover:scale-125 transition-transform"></div>
<span class="text-sm font-semibold">Inactive</span>
</div>
<span class="text-sm font-bold">12%</span>
</div>
<div class="flex items-center justify-between p-3 rounded-xl hover:bg-error/5 transition-colors cursor-default group">
<div class="flex items-center gap-3">
<div class="w-3 h-3 rounded-full bg-error group-hover:scale-125 transition-transform"></div>
<span class="text-sm font-semibold">Suspended</span>
</div>
<span class="text-sm font-bold text-error">4%</span>
</div>
</div>
</div>
<!-- Top Performing Clinics Horizontal Bar Chart -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<h4 class="text-lg font-extrabold font-headline mb-6">Top Performing</h4>
<div class="space-y-6">
<div class="space-y-2">
<div class="flex justify-between text-xs font-bold mb-1">
<span>Lumineer Dental Hub</span>
<span class="text-primary">$82k</span>
</div>
<div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
<div class="h-full bg-primary w-[90%] rounded-full shadow-[0_0_10px_rgba(0,102,255,0.3)]"></div>
</div>
</div>
<div class="space-y-2">
<div class="flex justify-between text-xs font-bold mb-1">
<span>Radiant Smile Clinic</span>
<span class="text-primary">$74k</span>
</div>
<div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
<div class="h-full bg-primary w-[82%] rounded-full shadow-[0_0_10px_rgba(0,102,255,0.3)]"></div>
</div>
</div>
<div class="space-y-2">
<div class="flex justify-between text-xs font-bold mb-1">
<span>Pearl White Institute</span>
<span class="text-primary">$68k</span>
</div>
<div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">
<div class="h-full bg-primary w-[75%] rounded-full shadow-[0_0_10px_rgba(0,102,255,0.3)]"></div>
</div>
</div>
</div>
<button class="w-full mt-8 py-3.5 bg-surface-container-low/50 border border-white hover:bg-white text-primary text-sm font-bold rounded-2xl transition-all shadow-sm">View All Rankings</button>
</div>
</div>
</section>
<!-- Bottom Section: Timeline & Alerts -->
<section class="grid grid-cols-1 md:grid-cols-2 gap-8">
<!-- Recent Activity Feed -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<div class="flex justify-between items-center mb-8">
<h4 class="text-xl font-extrabold font-headline">Recent Activity</h4>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-white shadow-sm hover:shadow-md transition-all">
<span class="material-symbols-outlined text-on-surface-variant text-xl">filter_list</span>
</button>
</div>
<div class="space-y-8 relative">
<div class="absolute left-5 top-2 bottom-2 w-[1.5px] bg-slate-100"></div>
<div class="relative flex gap-6 group">
<div class="z-10 w-10 h-10 rounded-2xl bg-blue-100 flex items-center justify-center border-4 border-white shadow-sm group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-base text-primary" style="font-variation-settings: 'FILL' 1;">add_business</span>
</div>
<div>
<p class="text-sm font-bold group-hover:text-primary transition-colors">New Tenant: <span>Stellar Orthodontics</span></p>
<p class="text-xs text-on-surface-variant mt-1.5 font-medium leading-relaxed">Provisioned Pro-Tier environment in US-East node.</p>
<p class="text-[10px] text-slate-400 mt-2.5 font-bold uppercase tracking-widest">24 mins ago</p>
</div>
</div>
<div class="relative flex gap-6 group">
<div class="z-10 w-10 h-10 rounded-2xl bg-green-100 flex items-center justify-center border-4 border-white shadow-sm group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-base text-green-600" style="font-variation-settings: 'FILL' 1;">check_circle</span>
</div>
<div>
<p class="text-sm font-bold group-hover:text-green-600 transition-colors">Billing Completed: <span>HealthFirst Group</span></p>
<p class="text-xs text-on-surface-variant mt-1.5 font-medium leading-relaxed">Annual subscription renewed successfully ($12,400).</p>
<p class="text-[10px] text-slate-400 mt-2.5 font-bold uppercase tracking-widest">2 hours ago</p>
</div>
</div>
<div class="relative flex gap-6 group">
<div class="z-10 w-10 h-10 rounded-2xl bg-amber-100 flex items-center justify-center border-4 border-white shadow-sm group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-base text-amber-600" style="font-variation-settings: 'FILL' 1;">sync</span>
</div>
<div>
<p class="text-sm font-bold group-hover:text-amber-600 transition-colors">System Update <span class="text-amber-600">v2.4.1</span></p>
<p class="text-xs text-on-surface-variant mt-1.5 font-medium leading-relaxed">Automatic backup completed for all European region shards.</p>
<p class="text-[10px] text-slate-400 mt-2.5 font-bold uppercase tracking-widest">5 hours ago</p>
</div>
</div>
</div>
</div>
<!-- Critical Alerts Panel -->
<div class="bg-white/70 backdrop-blur-xl p-8 rounded-[2rem] editorial-shadow">
<div class="flex justify-between items-center mb-8">
<h4 class="text-xl font-extrabold font-headline">Critical Alerts</h4>
<span class="bg-error/10 text-error text-[10px] font-extrabold px-3 py-1.5 rounded-xl uppercase tracking-widest">3 Actions Required</span>
</div>
<div class="space-y-4">
<div class="bg-error-container/5 backdrop-blur-sm p-5 rounded-3xl border border-error-container/20 flex items-start gap-5 group hover:bg-error-container/10 transition-colors">
<div class="w-10 h-10 rounded-2xl bg-error/10 flex items-center justify-center shrink-0 border border-error/10">
<span class="material-symbols-outlined text-error">error</span>
</div>
<div class="flex-1">
<h6 class="text-sm font-extrabold text-on-surface">Subscription Expiring Soon</h6>
<p class="text-xs text-on-surface-variant mt-1.5 leading-relaxed font-medium">Apex Dental (ID: #8821) expires in 48 hours.</p>
<div class="flex gap-3 mt-5">
<button class="text-[10px] font-bold text-error uppercase tracking-widest bg-white px-4 py-2 rounded-xl shadow-sm hover:shadow-md transition-all border border-error/10">Notify Tenant</button>
<button class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest px-2 py-2 hover:text-on-surface transition-colors">Dismiss</button>
</div>
</div>
</div>
<div class="bg-tertiary-container/5 backdrop-blur-sm p-5 rounded-3xl border border-tertiary-container/10 flex items-start gap-5 group hover:bg-tertiary-container/10 transition-colors">
<div class="w-10 h-10 rounded-2xl bg-tertiary/10 flex items-center justify-center shrink-0 border border-tertiary/10">
<span class="material-symbols-outlined text-tertiary">priority_high</span>
</div>
<div class="flex-1">
<h6 class="text-sm font-extrabold text-on-surface">Inactivity Detected</h6>
<p class="text-xs text-on-surface-variant mt-1.5 leading-relaxed font-medium">City Center Dental has shown 0 activity for the past 14 days.</p>
<div class="flex gap-3 mt-5">
<button class="text-[10px] font-bold text-tertiary uppercase tracking-widest bg-white px-4 py-2 rounded-xl shadow-sm hover:shadow-md transition-all border border-tertiary/10">Review Status</button>
</div>
</div>
</div>
<div class="bg-white/40 p-5 rounded-3xl flex items-start gap-5 border border-white/60">
<div class="w-10 h-10 rounded-2xl bg-primary/10 flex items-center justify-center shrink-0 border border-primary/10">
<span class="material-symbols-outlined text-primary">dns</span>
</div>
<div>
<h6 class="text-sm font-extrabold text-on-surface">Node Optimization</h6>
<p class="text-xs text-on-surface-variant mt-1.5 leading-relaxed font-medium">Server load on Asia-Central-1 is approaching 85% capacity threshold.</p>
</div>
</div>
</div>
</div>
</section>
</div>
</main>
</body></html>