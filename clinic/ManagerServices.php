<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Manage Services &amp; Pricing | Precision Dental</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
<!-- SideNavBar -->
<aside class="fixed left-0 top-0 h-full w-64 z-40 bg-white flex flex-col py-8 border-r border-slate-200/60">
<div class="px-7 mb-10">
<h1 class="text-xl font-extrabold text-slate-900 tracking-tight font-headline flex items-center gap-2">
<span class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shadow-lg shadow-primary/30">
<span class="material-symbols-outlined text-white text-lg" style="font-variation-settings: 'FILL' 1;">medical_services</span>
</span>
                Precision Dental
            </h1>
<p class="text-primary font-bold text-[10px] tracking-[0.2em] uppercase mt-2 opacity-80">Admin Console</p>
</div>
<nav class="flex-1 space-y-1 overflow-y-auto no-scrollbar">
<div class="px-3 mt-6">
<p class="px-4 text-[10px] font-bold text-primary uppercase tracking-[0.2em] opacity-80">Manager Menus</p>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="ManagerDashboard.php">
<span class="material-symbols-outlined text-[22px]">dashboard</span>
<span class="font-headline text-sm font-medium tracking-tight">Dashboard</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="ManagerPatients.php">
<span class="material-symbols-outlined text-[22px]">group</span>
<span class="font-headline text-sm font-medium tracking-tight">Patients</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="ManagerPayments.php">
<span class="material-symbols-outlined text-[22px]">payments</span>
<span class="font-headline text-sm font-medium tracking-tight">Payments</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="ManagerPaymentSettings.php">
<span class="material-symbols-outlined text-[22px]">settings</span>
<span class="font-headline text-sm font-medium tracking-tight">Payment Settings</span>
</a>
</div>
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="ManagerServices.php">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">medical_services</span>
<span class="font-headline text-sm font-bold tracking-tight">Services</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="ManagerUsers.php">
<span class="material-symbols-outlined text-[22px]">people</span>
<span class="font-headline text-sm font-medium tracking-tight">Users</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="ManagerReviews.php">
<span class="material-symbols-outlined text-[22px]">rate_review</span>
<span class="font-headline text-sm font-medium tracking-tight">Reviews</span>
</a>
</div>
<div class="px-3 mt-6">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">settings</span>
<span class="font-headline text-sm font-medium tracking-tight">Settings</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-rose-500 transition-colors duration-200 hover:bg-rose-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">logout</span>
<span class="font-headline text-sm font-medium tracking-tight">Logout</span>
</a>
</div>
</nav>
<div class="px-4 mt-auto">
<div class="bg-slate-50/80 rounded-2xl p-4 flex items-center gap-3 border border-slate-100 mb-4">
<img alt="Dr. Smith Profile" class="w-10 h-10 rounded-full border-2 border-white shadow-sm" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDIHuVFLVDn-IhBz55MtnVsFjck_dZwjbL6ziDR6xYV1kwjDqn45CWd7yP9kBO3mG8q_rl4qbwRBKuswBvKB-UF5JnZD9mANa8hFXZmXSGlRqQpQ-kp9RvDimvmzLtkFSzXbOHhM8JyB8U4dkAq1qXA4JbffNvGXO4KBvzFL-DnXJXlhJlITXN7oL5Hz0adk_ZHVhtyLVT4Se1BbGi5hFo4FLumani987jVnsOOXXLdvvABiKHJz8UgEzRHNjHsriD1hhfof6sVQRY"/>
<div class="overflow-hidden">
<p class="text-[13px] font-bold truncate text-slate-900 leading-tight">Dr. Smith</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Clinic Manager</p>
</div>
</div>
<button class="w-full py-3.5 bg-primary text-white font-bold text-xs uppercase tracking-widest rounded-xl shadow-lg shadow-primary/30 transition-all active:scale-95">
                Schedule Surgery
            </button>
</div>
</aside>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-64">
<!-- TopAppBar -->
<header class="flex justify-between items-center w-full px-10 sticky top-0 z-40 bg-white border-b border-slate-200 h-20">
<div class="flex items-center flex-1 max-w-xl">
<div class="relative w-full group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">search</span>
<input class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border-none rounded-full text-sm focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="Search clinic data..." type="text"/>
</div>
</div>
<div class="flex items-center gap-6 ml-8">
<div class="hidden lg:flex items-center gap-6 text-slate-500 font-bold text-xs uppercase tracking-widest">
<a class="hover:text-primary transition-colors" href="#">Support</a>
<a class="hover:text-primary transition-colors" href="#">System Status</a>
</div>
<div class="flex items-center gap-4 text-slate-400 ml-4">
<button class="material-symbols-outlined hover:text-primary transition-colors relative">
                        notifications
                        <span class="absolute top-0 right-0 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white"></span>
</button>
<button class="material-symbols-outlined hover:text-primary transition-colors">settings</button>
</div>
<div class="h-8 w-px bg-slate-200 mx-2"></div>
<div class="flex items-center gap-3">
<div class="text-right hidden sm:block">
<p class="text-sm font-bold text-slate-900 leading-none">Clinical Portal</p>
<p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mt-1">ID: PD-8829</p>
</div>
</div>
</div>
</header>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> CLINICAL SERVICES
                </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                            Manage Services &amp; <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Pricing</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">Efficiently curate and manage your clinic's services and pricing.</p>
</div>
<button class="px-6 py-3.5 bg-primary text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-primary/30 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">add</span>
                        Add New Service
                    </button>
</div>
</section>
<!-- Services Registry Table -->
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="p-8 border-b border-slate-100 flex flex-col md:flex-row justify-between items-center bg-white gap-6">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">Services Registry</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Configure clinical offerings and market pricing</p>
</div>
<div class="flex flex-wrap items-center gap-3">
<div class="relative group">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
<input class="pl-9 pr-4 py-2.5 bg-slate-50 border-none rounded-xl text-[12px] focus:ring-2 focus:ring-primary/20 transition-all w-64 font-medium" placeholder="Search service or code..." type="text"/>
</div>
<select class="px-4 py-2.5 bg-slate-50 border-none rounded-xl text-[11px] font-bold uppercase tracking-wider focus:ring-2 focus:ring-primary/20 cursor-pointer">
<option>All Categories</option>
<option>Surgery</option>
<option>General</option>
<option>Cosmetic</option>
</select>
<button class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">filter_list</span> Filter
                        </button>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Service Name</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Category</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Duration</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Price</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<!-- Row 1 -->
<tr class="hover:bg-slate-50/30 transition-colors group cursor-pointer">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
<span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">dentistry</span>
</div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Dental Implant</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">Code: SRV-204</p>
</div>
</div>
</td>
<td class="px-6 py-5">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Surgery</span>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700">90 min</p>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
</td>
<td class="px-8 py-5 text-right">
<p class="text-sm font-extrabold text-slate-900">$1,200.00</p>
</td>
</tr>
<!-- Row 2 -->
<tr class="hover:bg-slate-50/30 transition-colors group cursor-pointer">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
<span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">cleaning_services</span>
</div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Routine Cleaning</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">Code: SRV-101</p>
</div>
</div>
</td>
<td class="px-6 py-5">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">General Dentistry</span>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700">45 min</p>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
</td>
<td class="px-8 py-5 text-right">
<p class="text-sm font-extrabold text-slate-900">$150.00</p>
</td>
</tr>
<!-- Row 3 -->
<tr class="hover:bg-slate-50/30 transition-colors group cursor-pointer">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
<span class="material-symbols-outlined text-lg" style="font-variation-settings: 'FILL' 1;">auto_fix_high</span>
</div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Professional Whitening</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">Code: SRV-305</p>
</div>
</div>
</td>
<td class="px-6 py-5">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Cosmetic</span>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700">60 min</p>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-500 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        Draft
                                    </span>
</td>
<td class="px-8 py-5 text-right">
<p class="text-sm font-extrabold text-slate-900">$450.00</p>
</td>
</tr>
</tbody>
</table>
</div>
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
<p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing 3 of 32 recent entries</p>
<div class="flex items-center gap-4">
<div class="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest mr-4">
                            Rows per page
                            <select class="bg-transparent border-none text-slate-900 text-[11px] font-black focus:ring-0 cursor-pointer">
<option>10</option>
<option>20</option>
<option>50</option>
</select>
</div>
<div class="flex gap-2">
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</button>
<button class="w-8 h-8 rounded-lg bg-primary text-white text-[11px] font-black flex items-center justify-center">1</button>
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-600 hover:text-primary text-[11px] font-bold">2</button>
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</button>
</div>
</div>
</div>
</section>
<!-- Insights Section -->
</div>
<!-- Footer Spacer -->
<div class="h-10"></div>
</main>
</body></html>