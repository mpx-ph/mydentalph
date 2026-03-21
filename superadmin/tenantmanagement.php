<?php require_once __DIR__ . '/require_superadmin.php'; ?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Tenant Management | Clinical Precision</title>
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
<!-- SideNavBar (Matches SCREEN_2) -->
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
<!-- Active Item: Tenant Management -->
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="tenantmanagement.php">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">groups</span>
<span class="font-headline text-sm font-bold tracking-tight">Tenant Management</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
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
<!-- TopNavBar (Matches SCREEN_2) -->
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
<p class="text-sm font-bold text-on-surface">Dr. Julian</p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-widest opacity-60">Admin Manager</p>
</div>
<img alt="Dr. Julian Profile" class="w-10 h-10 rounded-full bg-surface-container-high border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDa5Hm5Qj9GBJdlIFq95iJVw8xI37VvQSwpzCCPtaHX_DBdrPsRP0otd28FikWt0xhUXx9g4ecq8uAQafVZreSU6D4BO5EV-2KYtlgRNtZ3k8QISo8LSNXujmWhY1sAp-MULw0Jm_xZOqx9zBo5JMUIqSQFHTCd-rnwMtR73GJND8FP3M_zurmtNzy3JOuMvM5FlPSIXcT7JmHGgpyrkjvXIvTpFfAgltlUP0cOIJD18FvbRiq_XGARsUjvPO88LVXy4HqqythNxq8"/>
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
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Tenant Management</h2>
<p class="text-on-surface-variant mt-2 font-medium">View and manage clinic tenant accounts across the network.</p>
</div>
<div class="flex items-center gap-3">
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">add</span>
                    Add New Tenant
                </button>
</div>
</section>
<!-- Metrics Bento Grid (Styled like SCREEN_2 cards) -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">corporate_fare</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+12%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Tenants</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">1,284</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">medical_services</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+8%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Active Clinics</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">942</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-error/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-error-container/10 text-error rounded-xl shadow-sm">
<span class="material-symbols-outlined">warning</span>
</div>
<span class="text-[10px] font-extrabold text-error bg-error-container px-2 py-1 rounded-lg uppercase">Action Required</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Suspended Accounts</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">14</h3>
</div>
</section>
<!-- Main Data Table Container (Glassmorphism & Style from SCREEN_2) -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<!-- Table Controls -->
<div class="px-8 py-6 flex flex-wrap items-center justify-between gap-4 border-b border-white/50">
<div class="flex items-center gap-4">
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>All Status</option>
<option>Active</option>
<option>Inactive</option>
<option>Suspended</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">expand_more</span>
</div>
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>All Plans</option>
<option>Enterprise</option>
<option>Pro</option>
<option>Basic</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">filter_list</span>
</div>
</div>
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
                    Showing <span class="text-primary opacity-100">1-10</span> of 1,284 tenants
                </div>
</div>
<!-- Table Content -->
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Tenant Name</th>
<th class="px-8 py-5">Clinic Name</th>
<th class="px-8 py-5">Status</th>
<th class="px-8 py-5">Subscription</th>
<th class="px-8 py-5">Last Activity</th>
<th class="px-10 py-5 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<!-- Row 1 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<img alt="Tenant 1" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDZ84sX81i1GPCrjS58akL1mYFBaX7VUgGPCxqm3VurBUwYX1qM13_KzpZ8BcnrHG-4IOvCU3vpkpCoWdnIWTgLXN0WbWFtDiIE7prYB3zL6v4n88Ku681TGNtdsAoE8JZ0VY9OL5II9BkcHN5SwLwNy5xmwWKCr_-foGMpce3zmWRuAzCBpJpq0WMQENueXyU0veVLZbdgxbb-OijwhWB59-cAY9zfhd_K7SMWMmbF6gy5x5bc2lFjn-pX6GWWAQFdwOUFsO4QXjE"/>
<div>
<p class="text-sm font-bold text-on-surface">Dr. Sarah Thompson</p>
<p class="text-[10px] text-on-surface-variant font-medium">sarah.t@clinic.com</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">BrightSmile Dental</span>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-[10px] font-bold uppercase tracking-wider">Active</span>
</td>
<td class="px-8 py-5">
<span class="flex items-center gap-2 text-sm font-bold text-primary">
<span class="material-symbols-outlined text-lg">verified</span>
                                    Enterprise
                                </span>
</td>
<td class="px-8 py-5 text-xs font-medium text-on-surface-variant">2 mins ago</td>
<td class="px-10 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-xl">edit</span></button>
<button class="p-2 text-on-surface-variant hover:text-error transition-colors"><span class="material-symbols-outlined text-xl">block</span></button>
</div>
</td>
</tr>
<!-- Row 2 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-primary text-xs font-bold border-2 border-white shadow-sm">MJ</div>
<div>
<p class="text-sm font-bold text-on-surface">Mark Janson</p>
<p class="text-[10px] text-on-surface-variant font-medium">mark.j@metrocare.com</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">MetroCare Dental Hub</span>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-bold uppercase tracking-wider">Inactive</span>
</td>
<td class="px-8 py-5">
<span class="flex items-center gap-2 text-sm font-bold text-on-surface-variant">
<span class="material-symbols-outlined text-lg">workspace_premium</span>
                                    Pro
                                </span>
</td>
<td class="px-8 py-5 text-xs font-medium text-on-surface-variant">4 hours ago</td>
<td class="px-10 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-xl">edit</span></button>
<button class="p-2 text-on-surface-variant hover:text-error transition-colors"><span class="material-symbols-outlined text-xl">block</span></button>
</div>
</td>
</tr>
<!-- Row 3 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<img alt="Tenant 3" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDO5PLkDXpTS3_Mc7cMnIyCZWoQ0pw4rY5SH_X_eoRl3EeXu9Y9Rmjl10wSdbkv7yHPUoIGaGV6JrPJpg3SInPknS1-jraDdhxIE-GUWXBIQjWAqGJerMYLR_CDF4KqImAdFs3AEIerELchpWgzqJODVyVwPoATOWdJ-4NfH4wYJl0b6GU0wxxQHewUweTve2fTfcOHqLVyywKaBaRxzmV4yYOsaXslVT0EwsoWRckY03nLjvwNL-oEWsWadurx9O7XcU60T9DjBXs"/>
<div>
<p class="text-sm font-bold text-on-surface">Dr. Robert Liao</p>
<p class="text-[10px] text-on-surface-variant font-medium">robert.liao@zenith.com</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant">Zenith Specialist Clinic</span>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-error-container/20 text-error rounded-xl text-[10px] font-bold uppercase tracking-wider">Suspended</span>
</td>
<td class="px-8 py-5">
<span class="flex items-center gap-2 text-sm font-bold text-on-surface-variant opacity-60">
<span class="material-symbols-outlined text-lg">stars</span>
                                    Basic
                                </span>
</td>
<td class="px-8 py-5 text-xs font-medium text-on-surface-variant">3 days ago</td>
<td class="px-10 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 text-on-surface-variant hover:text-primary transition-colors"><span class="material-symbols-outlined text-xl">edit</span></button>
<button class="p-2 text-error hover:brightness-125 transition-all"><span class="material-symbols-outlined text-xl">lock_open</span></button>
</div>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Pagination (Matches SCREEN_2 Button Style) -->
<div class="px-10 py-8 flex items-center justify-between border-t border-white/50">
<button class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2 disabled:opacity-40" disabled="">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </button>
<div class="flex items-center gap-2">
<button class="w-10 h-10 bg-primary text-white rounded-xl font-bold text-sm active-glow">1</button>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">2</button>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">3</button>
<span class="px-2 opacity-40">...</span>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">128</button>
</div>
<button class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
</button>
</div>
</div>
<!-- Footer Grid (Styled like Bottom Section in SCREEN_2) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
<div class="p-10 bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow flex items-center justify-between group cursor-pointer hover:-translate-y-1 transition-all">
<div>
<h4 class="text-xl font-extrabold font-headline text-on-surface">Data Export Center</h4>
<p class="text-sm text-on-surface-variant mt-2 font-medium">Download monthly reports for all clinic activity.</p>
<button class="mt-6 px-6 py-2.5 bg-primary/10 text-primary hover:bg-primary hover:text-white rounded-2xl text-xs font-bold transition-all flex items-center gap-2">
                        Go to Reports
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
</button>
</div>
<div class="w-24 h-24 rounded-[2rem] bg-primary/5 flex items-center justify-center group-hover:bg-primary/10 transition-colors">
<span class="material-symbols-outlined text-4xl text-primary">analytics</span>
</div>
</div>
<div class="p-10 bg-gradient-to-br from-[#ffdcc3] to-[#ffb77e] rounded-[2.5rem] shadow-xl shadow-orange-900/10 flex items-center justify-between group cursor-pointer hover:-translate-y-1 transition-all">
<div>
<h4 class="text-xl font-extrabold font-headline text-[#2f1500]">Service Health</h4>
<p class="text-sm text-[#6e3900]/80 mt-2 font-medium leading-relaxed">Cloud infrastructure status and API latency metrics.</p>
<button class="mt-6 px-6 py-2.5 bg-white/30 text-[#2f1500] hover:bg-white/50 rounded-2xl text-xs font-bold transition-all flex items-center gap-2">
                        System Status
                        <span class="material-symbols-outlined text-sm">cloud_done</span>
</button>
</div>
<div class="w-24 h-24 rounded-[2rem] bg-white/20 flex items-center justify-center group-hover:scale-105 transition-transform">
<span class="material-symbols-outlined text-4xl text-[#2f1500]">dns</span>
</div>
</div>
</div>
</div>
</main>
</body></html>