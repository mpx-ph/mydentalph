<?php
$staff_nav_active = 'reports';
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
<title>Reports - Aetheris Dental Systems</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
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
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64">
<header class="flex items-center w-full px-10 sticky top-0 z-40 bg-white border-b border-slate-200 h-20 justify-end">
<div class="flex items-center gap-4 text-slate-400">
<button class="material-symbols-outlined hover:text-primary transition-colors">notifications</button>
<button class="material-symbols-outlined hover:text-primary transition-colors">help_outline</button>
</div>
</header>
<div class="p-10 space-y-8">
<section class="flex flex-col gap-4 mb-2">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> CLINICAL PRECISION
            </div>
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Reports <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Overview</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                    Track appointments, service delivery, and revenue in one place.
                </p>
</div>
</section>

<section class="elevated-card p-8 rounded-3xl">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
<div class="lg:col-span-2">
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Search</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
<input class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Search by patient, service, or staff" type="text"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Export Data</label>
<button class="w-full bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-primary/30 flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-sm">download</span>
                        Export CSV
                    </button>
</div>
</div>
</section>

<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">Monthly</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">$58,400</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Revenue</p>
</div>
</div>

<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_available</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">All Time</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">428</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Appointments</p>
</div>
</div>

<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">group</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Registered</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">312</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Patients</p>
</div>
</div>
</section>

<section class="elevated-card p-8 rounded-3xl">
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date</label>
<div class="relative">
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_month</span>
<input class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Select date range" type="text"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Status</label>
<select class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option>All Statuses</option>
<option>Completed</option>
<option>Confirmed</option>
<option>Pending</option>
<option>Cancelled</option>
</select>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Assigned Staff</label>
<select class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option>All Staff</option>
<option>Dr. Helena Vance</option>
<option>Dr. Simon Kaan</option>
<option>Dr. Noah Ellis</option>
</select>
</div>
</div>
</section>

<section class="elevated-card rounded-3xl overflow-hidden">
<div class="px-8 py-6 border-b border-slate-100 bg-white">
<h3 class="text-2xl font-bold font-headline text-on-background">All Appointments</h3>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Details</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Appointment Info (Date and Time)</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Treatment/Service (Details)</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Assigned Staff</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center font-black text-primary text-xs">JR</div>
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Julian Reed</span>
<span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">ID: #PT-2103</span>
</div>
</div>
</td>
<td class="px-6 py-6">
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-700">Mar 28, 2026</span>
<span class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-0.5">10:30 AM</span>
</div>
</td>
<td class="px-6 py-6 text-sm font-semibold text-slate-700">Root Canal Treatment - Follow-up Session</td>
<td class="px-6 py-6 text-sm font-medium text-slate-700">Dr. Helena Vance</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Completed
                            </span>
</td>
<td class="px-8 py-6 text-right text-sm font-extrabold text-slate-900">$450.00</td>
</tr>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<img alt="Patient Avatar" class="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/5" src="https://lh3.googleusercontent.com/aida-public/AB6AXuA72WvYph63Cvd5Atneh6QZXaWc7iOrxN4WyZVU-_hZiGTZGeS3EHCBnfouNfAda5nzXjMUDmkO_VscwiN4byshrJtKn49rhoNdH6rJDBkTuK27skPUinXXpbIdzmhE3dOhJU6zNC-Ht_yiUbFlt1WsJtCYzoWhJrMsaUswo55fw_m86nBUcr71f8hYDIgocyA9pKTJgXnvYuHvlx-ZkOz8fp-NQ63yB-Fb5EXCpVgKs8L_3HtSunjZTi-qdXvahJjxaaomYFaoF-8"/>
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Elena Rodriguez</span>
<span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">ID: #PT-1889</span>
</div>
</div>
</td>
<td class="px-6 py-6">
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-700">Mar 29, 2026</span>
<span class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-0.5">01:45 PM</span>
</div>
</td>
<td class="px-6 py-6 text-sm font-semibold text-slate-700">Teeth Whitening - Full Session</td>
<td class="px-6 py-6 text-sm font-medium text-slate-700">Dr. Simon Kaan</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-primary/10 text-primary text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                Confirmed
                            </span>
</td>
<td class="px-8 py-6 text-right text-sm font-extrabold text-slate-900">$220.00</td>
</tr>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center font-black text-slate-600 text-xs">AM</div>
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Arthur Morgan</span>
<span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">ID: #PT-1742</span>
</div>
</div>
</td>
<td class="px-6 py-6">
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-700">Mar 30, 2026</span>
<span class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-0.5">09:00 AM</span>
</div>
</td>
<td class="px-6 py-6 text-sm font-semibold text-slate-700">Emergency Restoration - Initial Assessment</td>
<td class="px-6 py-6 text-sm font-medium text-slate-700">Dr. Noah Ellis</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                Pending
                            </span>
</td>
<td class="px-8 py-6 text-right text-sm font-extrabold text-slate-900">$300.00</td>
</tr>
</tbody>
</table>
</div>
</section>
</div>
</main>
</body></html>
