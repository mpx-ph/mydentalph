<?php
$staff_nav_active = 'patients';
if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Patients Management - Aetheris Dental Systems</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
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
<!-- SideNavBar Component -->
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Canvas -->
<main class="flex-1 flex flex-col min-w-0 ml-64">
<!-- TopAppBar -->
<header class="flex items-center w-full px-10 sticky top-0 z-40 bg-white border-b border-slate-200 h-20 justify-end">
<div class="flex items-center gap-6 ml-8">
<div class="flex items-center gap-4 text-slate-400">
<button class="material-symbols-outlined hover:text-primary transition-colors">notifications</button>
<button class="material-symbols-outlined hover:text-primary transition-colors">help_outline</button>
</div>
</div>
</header>
<!-- Content Area -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> CLINICAL PRECISION
            </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Patients <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Manage and view all registered patient records with architectural precision.
                    </p>
</div>
<button class="bg-primary hover:bg-primary/90 text-white px-8 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-primary/30 flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-sm">add</span>
    Add New Patient
</button></div>
</section>
<!-- Search & Filter Bar -->
<section class="elevated-card p-8 rounded-3xl space-y-6">
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Search Records</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person_search</span>
<input class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Name, Contact, or ID" type="text"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Status</label>
<select class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option>All Statuses</option>
<option>Active</option>
<option>Inactive</option>
</select>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Gender</label>
<select class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option>All Genders</option>
<option>Male</option>
<option>Female</option>
<option>Other</option>
</select>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Registration Date</label>
<div class="relative">
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_month</span>
<input class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Jan 2024 - Dec 2024" type="text"/>
</div>
</div>
</div>
<div class="flex items-center gap-3 pt-2">
<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mr-2">Quick Filters:</span>
<button class="px-5 py-2 rounded-full bg-primary text-white text-[10px] font-black uppercase tracking-widest transition-all shadow-md shadow-primary/20">All Patients</button>
<button class="px-5 py-2 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 text-[10px] font-black uppercase tracking-widest transition-all">New</button>
<button class="px-5 py-2 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 text-[10px] font-black uppercase tracking-widest transition-all">Returning</button>
<button class="px-5 py-2 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 text-[10px] font-black uppercase tracking-widest transition-all">High Priority</button>
</div>
</section>
<!-- Patients Table -->
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Details</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Contact Number</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Email Address</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Gender</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Last Visit</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<!-- Row 1 -->
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<img alt="Patient Avatar" class="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/5" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA2YA1IROx5Kwy9HKkSFqkcterez3eWXLJg9CVq8Nm0du8Z_yL1t-Irty1P26l6lcjODTDU8CZGzYyWUAcmO7bw6-9kPm_CSXiM-SGmiO1zrRvqqPmn7SmgZ0VYHe_fsFdm0ZbTjb9U3OwRNfeYPPmrJTCh8PjLgBcvPzSC5G3zBzvwT_1Ug8Iw5kN8y9DkCVu2Zcxjjj2OoAb-g1K37TYI_EKMUEXnuH9KFgm7tr5Utz--I1V8Awl0dJMst2BlEJeCn5UVJFFRchU"/>
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Julian Sterling</span>
<span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">ID: #AD-88219</span>
</div>
</div>
</td>
<td class="px-6 py-6 text-sm font-bold text-slate-700">+1 (555) 012-3456</td>
<td class="px-6 py-6 text-sm font-medium text-slate-500">j.sterling@outlook.com</td>
<td class="px-6 py-6 text-center">
<span class="material-symbols-outlined text-slate-300 text-xl" title="Male">male</span>
</td>
<td class="px-6 py-6">
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-700">Oct 12, 2023</span>
<span class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-0.5">Scaling &amp; Polish</span>
</div>
</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Active
                                </span>
</td>
<td class="px-8 py-6 text-right">
<div class="flex justify-end gap-2">
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/20 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">visibility</span>
</button>
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/20 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">edit_square</span>
</button>
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-100 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">delete</span>
</button>
</div>
</td>
</tr>
<!-- Row 2 -->
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<img alt="Patient Avatar" class="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/5" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA72WvYph63Cvd5Atneh6QZXaWc7iOrxN4WyZVU-_hZiGTZGeS3EHCBnfouNfAda5nzXjMUDmkO_VscwiN4byshrJtKn49rhoNdH6rJDBkTuK27skPUinXXpbIdzmhE3dOhJU6zNC-Ht_yiUbFlt1WsJtCYzoWhJrMsaUswo55fw_m86nBUcr71f8hYDIgocyA9pKTJgXnvYuHvlx-ZkOz8fp-NQ63yB-Fb5EXCpVgKs8L_3HtSunjZTi-qdXvahJjxaaomYFaoF-8"/>
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Elena Rodriguez</span>
<span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">ID: #AD-88220</span>
</div>
</div>
</td>
<td class="px-6 py-6 text-sm font-bold text-slate-700">+1 (555) 098-7654</td>
<td class="px-6 py-6 text-sm font-medium text-slate-500">elena.rod@gmail.com</td>
<td class="px-6 py-6 text-center">
<span class="material-symbols-outlined text-slate-300 text-xl" title="Female">female</span>
</td>
<td class="px-6 py-6">
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-700">Jan 04, 2024</span>
<span class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-0.5">Root Canal</span>
</div>
</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Active
                                </span>
</td>
<td class="px-8 py-6 text-right">
<div class="flex justify-end gap-2">
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/20 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">visibility</span>
</button>
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/20 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">edit_square</span>
</button>
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-100 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">delete</span>
</button>
</div>
</td>
</tr>
<!-- Row 3 -->
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center font-black text-primary text-xs">AM</div>
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Arthur Morgan</span>
<span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">ID: #AD-88221</span>
</div>
</div>
</td>
<td class="px-6 py-6 text-sm font-bold text-slate-700">+1 (555) 234-5678</td>
<td class="px-6 py-6 text-sm font-medium text-slate-500">amorgan@frontier.com</td>
<td class="px-6 py-6 text-center">
<span class="material-symbols-outlined text-slate-300 text-xl" title="Male">male</span>
</td>
<td class="px-6 py-6">
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-700">Dec 15, 2022</span>
<span class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-0.5">Checkup</span>
</div>
</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-500 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    Inactive
                                </span>
</td>
<td class="px-8 py-6 text-right">
<div class="flex justify-end gap-2">
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/20 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">visibility</span>
</button>
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/20 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">edit_square</span>
</button>
<button class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-100 rounded-xl transition-all">
<span class="material-symbols-outlined text-lg">delete</span>
</button>
</div>
</td>
</tr>
</tbody>
</table>
</div>
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
<p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing <span class="text-slate-900">10</span> of <span class="text-slate-900">250</span> patients</p>
<div class="flex items-center gap-2">
<button class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-400 hover:text-primary transition-all">
<span class="material-symbols-outlined text-lg">chevron_left</span>
</button>
<button class="w-9 h-9 flex items-center justify-center rounded-xl bg-primary text-white font-black text-xs">1</button>
<button class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:border-primary/20 transition-all">2</button>
<button class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:border-primary/20 transition-all">3</button>
<span class="px-2 text-slate-400 text-xs">...</span>
<button class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:border-primary/20 transition-all">25</button>
<button class="w-9 h-9 flex items-center justify-center rounded-xl border border-slate-200 text-slate-400 hover:text-primary transition-all">
<span class="material-symbols-outlined text-lg">chevron_right</span>
</button>
</div>
</div>
</section>
</div>
</main>
</body></html>