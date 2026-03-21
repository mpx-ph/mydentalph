<?php require_once __DIR__ . '/require_superadmin.php'; ?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Audit Logs | Clinical Precision</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
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
                        "headline": ["Manrope", "Plus Jakarta Sans", "sans-serif"],
                        "body": ["Manrope", "Inter", "sans-serif"],
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
<!-- SideNavBar -->
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
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="reports.php">
<span class="material-symbols-outlined text-[22px]">assessment</span>
<span class="font-headline text-sm font-medium tracking-tight">Reports</span>
</a>
</div>
<!-- Active Item: Audit Logs -->
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="auditlogs.php">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">history_edu</span>
<span class="font-headline text-sm font-bold tracking-tight">Audit Logs</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
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
<!-- TopNavBar -->
<header class="fixed top-0 right-0 w-[calc(100%-16rem)] h-20 z-30 bg-white/70 backdrop-blur-xl border-b border-white/50 flex items-center justify-between px-8">
<div class="flex items-center gap-6 flex-1">
<div class="relative w-full max-w-md group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-xl">search</span>
<input class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm transition-all placeholder:text-on-surface-variant/50" placeholder="Search system logs..." type="text"/>
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
<img alt="Administrator" class="w-10 h-10 rounded-full bg-surface-container-high border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBar8dHjRP-N2Jhu-PKE_XzJEKNd8IhDCKnAI92e4ffVRksOT6KXP37jxcZKP3hCYMm0YrXzzXav91yYMY98PR1bRgOFkGBwpCvBN-XNcwEinowBVbzBFiazLI3e5VKJCN7KNXQNRt34dNE8FeuNoxzChszAc7UnpZylwnvqO1fpaS5DoxlzO-8kxdoB3oGKmUiMBs-nZDwqpo1-ZoMun3426oL-pfGg54HlDQGM4St1HmbXqCC_EYR9B5pLD-qXyiJeIbBNaeTZ5s"/>
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
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Audit Logs</h2>
<p class="text-on-surface-variant mt-2 font-medium">Track and monitor system activities across all clinic modules.</p>
</div>
<div class="flex items-center gap-3">
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                    PDF Export
                </button>
</div>
</section>
<!-- Metrics Grid -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">history</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+12%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Logs</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">12,842</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-error/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-error-container/10 text-error rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">warning</span>
</div>
<span class="text-[10px] font-extrabold text-error bg-error-container px-2 py-1 rounded-lg uppercase">Action Required</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Critical Alerts</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">24</h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">bolt</span>
</div>
<span class="text-[10px] font-extrabold text-primary bg-primary/5 px-2 py-1 rounded-lg uppercase">Today</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Recent Activities</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">318</h3>
</div>
</section>
<!-- Action Center Buttons -->
<div class="flex items-center gap-3">
<button class="px-6 py-2.5 bg-white/60 text-primary text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">table_chart</span> Excel Export
            </button>
<button class="px-6 py-2.5 bg-white/60 text-error text-sm font-bold rounded-xl border border-white hover:bg-error hover:text-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">delete_sweep</span> Clear Logs
            </button>
</div>
<!-- Table Container -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<!-- Table Controls -->
<div class="px-8 py-6 flex flex-wrap items-center justify-between gap-4 border-b border-white/50">
<div class="flex items-center gap-4">
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>Last 7 Days</option>
<option>Last 30 Days</option>
<option>Last Quarter</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">expand_more</span>
</div>
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>Action Type: All</option>
<option>Security Updates</option>
<option>Patient Records</option>
<option>Financial Updates</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">filter_list</span>
</div>
<div class="relative group">
<select class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option>Status: All</option>
<option>Completed</option>
<option>Pending</option>
<option>Failed</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">tune</span>
</div>
</div>
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
                    Showing <span class="text-primary opacity-100">1-4</span> of 12,842 results
                </div>
</div>
<!-- Table Content -->
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">User</th>
<th class="px-8 py-5">Action</th>
<th class="px-8 py-5">Date &amp; Time</th>
<th class="px-8 py-5">Status</th>
<th class="px-10 py-5 text-right">Details</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<!-- Row 1 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<img alt="Dr. Sarah Chen" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuD6gnZui_FPaMktbvXWxlyPOQcKbgkG0LYeOyrpTKUw5l5m52eUld0IsEE5w7Ey2Yjqnyzttn541tJqQfowf07exQ0IWGI8OOUuAeUAaWVe-s5ZXtk1KVB6UI7yZDOJwzX869-o5b5XzZSx4VB6F29GxHda3UQlGC0glUCcc95g1JLd890U2SyNyirzFUhLndttkcwAgBYZ77EOWBzEz8JnRVKekYIQv5DarCMyVIupTDexMl5IMLuBphTz3z4mPsdLnJQTyV1ifGM"/>
<div>
<p class="text-sm font-bold text-on-surface">Dr. Sarah Chen</p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider">Lead Dentist</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-bold text-on-surface">Clinic Settings Updated</span>
</td>
<td class="px-8 py-5">
<div class="text-xs">
<p class="text-on-surface font-black">Oct 24, 2023</p>
<p class="text-on-surface-variant font-bold">14:30:22</p>
</div>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full bg-green-600"></span> Completed
                                </span>
</td>
<td class="px-10 py-5 text-right">
<button class="text-primary text-sm font-bold hover:underline">View JSON</button>
</td>
</tr>
<!-- Row 2 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<img alt="Admin Mark" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB4L6MTIYSlFoFqqBbfvt6bZn4ZJneCnkADls1GNqp6X0UEf04krdIp-GRX2cszUniZVzlPP9eV3uxAwCKCzoqvS5Q1SclsXv9bb99cS2W0bgx-ZnYOtvjrlx6cDyLMYm8nx5bTHnk0Vs9-E2QHUsiuCo-fXjZHI9nG1zlKy-TElbfhnCB8hnoSLk23KzmziID8eXRiqXBGVQEfO5l382ZXF2lnG_pckiQcM4abUXSaDKF03riYw6t6DgCrHN17-JNhhtFsNeAW2DE"/>
<div>
<p class="text-sm font-bold text-on-surface">Admin Mark</p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider">System Admin</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-bold text-on-surface">New Tenant Registered</span>
</td>
<td class="px-8 py-5">
<div class="text-xs">
<p class="text-on-surface font-black">Oct 24, 2023</p>
<p class="text-on-surface-variant font-bold">13:15:45</p>
</div>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-amber-50 text-amber-600 rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full bg-amber-600"></span> Pending
                                </span>
</td>
<td class="px-10 py-5 text-right">
<button class="text-primary text-sm font-bold hover:underline">View Details</button>
</td>
</tr>
<!-- Row 3 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<img alt="Security Bot" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAlZdbB3lZwHZN7Hq09KrjFjf8TxkavFqEWAa7Ip5hWoARBdLdgX04VTb92Qlw40opDz_Doto2lTCQFNAdhlnJGWiSPnoIzG2S57d01L6-KHo0fVguf64i2i2uzxBQ7c-PjVnqFO0ZBK-yQtxIDpCFOqOY439cZw6ciOYKxBGCzMKyVJF9AnwrotTQi7vVCbKFi-qNkVuSB0c9N8YlehdcQH1F43b0dC6IDpIo6Is03LG4yT2Fl7BS6NDGLduZdxYM1WaIfkUhU9nM"/>
<div>
<p class="text-sm font-bold text-on-surface">Security Bot</p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider">System Process</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-bold text-error">Unauthorized Login Attempt</span>
</td>
<td class="px-8 py-5">
<div class="text-xs">
<p class="text-on-surface font-black">Oct 24, 2023</p>
<p class="text-on-surface-variant font-bold">11:02:10</p>
</div>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-error/10 text-error rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full bg-error"></span> Failed
                                </span>
</td>
<td class="px-10 py-5 text-right">
<button class="text-error text-sm font-bold hover:underline">Review Incident</button>
</td>
</tr>
<!-- Row 4 -->
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<img alt="Lisa Miller" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-md" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB1Mk1WHoHwjhTvJw_CedBrwDG3Gj_VhaaNF5OT_DFvZRgufLThLJX8vxE1muGPLupN_TA8CgekDwFaqmFvY8HJQMfc3KkziPGsEXHkrBZVv0anNovZmpo0nVLiUv3b7zTs484ZNs05YOeSC09-3kxrV9e3wav0QhOARnjXkDmJPpLLKZmA6I4ebJHz_YREtdfz_cYmlPJo-jBAa12BG2p6wPBekt42iRRsYVCaOWnpOpg_J8_wtsJTvltNR0Rz7eqKzE5yt4EuipQ"/>
<div>
<p class="text-sm font-bold text-on-surface">Lisa Miller</p>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider">Receptionist</p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-bold text-on-surface">Patient Record Exported</span>
</td>
<td class="px-8 py-5">
<div class="text-xs">
<p class="text-on-surface font-black">Oct 24, 2023</p>
<p class="text-on-surface-variant font-bold">09:45:00</p>
</div>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full bg-green-600"></span> Completed
                                </span>
</td>
<td class="px-10 py-5 text-right">
<button class="text-primary text-sm font-bold hover:underline">View File</button>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Pagination -->
<div class="px-10 py-8 flex items-center justify-between border-t border-white/50">
<button class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </button>
<div class="flex items-center gap-2">
<button class="w-10 h-10 bg-primary text-white rounded-xl font-bold text-sm active-glow">1</button>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">2</button>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">3</button>
<span class="px-2 opacity-40">...</span>
<button class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all">1284</button>
</div>
<button class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
</button>
</div>
</div>
<!-- Footer Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
<div class="p-10 bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow flex items-center justify-between group cursor-pointer hover:-translate-y-1 transition-all">
<div>
<h4 class="text-xl font-extrabold font-headline text-on-surface">Export Center</h4>
<p class="text-sm text-on-surface-variant mt-2 font-medium">Access all generated audit files and security reports.</p>
<button class="mt-6 px-6 py-2.5 bg-primary/10 text-primary hover:bg-primary hover:text-white rounded-2xl text-xs font-bold transition-all flex items-center gap-2">
                        Manage Exports
                        <span class="material-symbols-outlined text-sm">settings</span>
</button>
</div>
<div class="w-24 h-24 rounded-[2rem] bg-primary/5 flex items-center justify-center group-hover:bg-primary/10 transition-colors">
<span class="material-symbols-outlined text-4xl text-primary">folder_open</span>
</div>
</div>
<div class="p-10 bg-gradient-to-br from-[#ffdcc3] to-[#ffb77e] rounded-[2.5rem] shadow-xl shadow-orange-900/10 flex items-center justify-between group cursor-pointer hover:-translate-y-1 transition-all">
<div>
<h4 class="text-xl font-extrabold font-headline text-[#2f1500]">System Health</h4>
<p class="text-sm text-[#6e3900]/80 mt-2 font-medium leading-relaxed">Infrastructure status and system monitoring metrics.</p>
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