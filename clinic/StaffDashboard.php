<?php
$staff_nav_active = 'dashboard';
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
<title>Clinical Precision - Manager Dashboard</title>
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
<!-- SideNavBar Component -->
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> MANAGER DASHBOARD
            </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Manager <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Dashboard</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Overview of clinic operations and performance
                    </p>
</div>
<button class="px-6 py-3 bg-primary/10 text-primary text-[11px] font-black uppercase tracking-widest rounded-xl hover:bg-primary hover:text-white transition-all">
                    View Reports
                </button>
</div>
</section>
<!-- Stats Grid -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<!-- Services -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">medical_services</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">+4%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">24</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Services Offered</p>
</div>
</div>
<!-- Staff -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">group</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Active</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">12</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Active Staff</p>
</div>
</div>
<!-- Appointments -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_available</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Today</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">18</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Appointments</p>
</div>
</div>
<!-- Revenue -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">+12%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">$42k</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Monthly Revenue</p>
</div>
</div>
</section>
<!-- Main Content Area: Analytics & Appointments -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
<!-- Performance Analytics -->
<div class="lg:col-span-2 elevated-card rounded-3xl p-10 flex flex-col">
<div class="flex justify-between items-start mb-8">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">Performance Analytics</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Revenue vs Appointments (Last 7 Days)</p>
</div>
<button class="w-10 h-10 flex items-center justify-center text-slate-400 hover:bg-slate-50 rounded-xl transition-all">
<span class="material-symbols-outlined">more_vert</span>
</button>
</div>
<div class="flex-1 flex items-end justify-between gap-4 mt-6 min-h-[250px]">
<!-- Chart Bars -->
<div class="flex-1 flex flex-col items-center gap-4 group">
<div class="w-full bg-primary/10 rounded-xl relative h-32 overflow-hidden">
<div class="absolute bottom-0 w-full bg-primary transition-all group-hover:scale-y-110 origin-bottom" style="height: 60%;"></div>
</div>
<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Mon</span>
</div>
<div class="flex-1 flex flex-col items-center gap-4 group">
<div class="w-full bg-primary/10 rounded-xl relative h-48 overflow-hidden">
<div class="absolute bottom-0 w-full bg-primary transition-all group-hover:scale-y-110 origin-bottom" style="height: 80%;"></div>
</div>
<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Tue</span>
</div>
<div class="flex-1 flex flex-col items-center gap-4 group">
<div class="w-full bg-primary/10 rounded-xl relative h-40 overflow-hidden">
<div class="absolute bottom-0 w-full bg-primary transition-all group-hover:scale-y-110 origin-bottom" style="height: 70%;"></div>
</div>
<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Wed</span>
</div>
<div class="flex-1 flex flex-col items-center gap-4 group">
<div class="w-full bg-primary/10 rounded-xl relative h-56 overflow-hidden">
<div class="absolute bottom-0 w-full bg-primary transition-all group-hover:scale-y-110 origin-bottom" style="height: 95%;"></div>
</div>
<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Thu</span>
</div>
<div class="flex-1 flex flex-col items-center gap-4 group">
<div class="w-full bg-primary/10 rounded-xl relative h-36 overflow-hidden">
<div class="absolute bottom-0 w-full bg-primary transition-all group-hover:scale-y-110 origin-bottom" style="height: 55%;"></div>
</div>
<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Fri</span>
</div>
<div class="flex-1 flex flex-col items-center gap-4 group">
<div class="w-full bg-primary/10 rounded-xl relative h-24 overflow-hidden">
<div class="absolute bottom-0 w-full bg-primary transition-all group-hover:scale-y-110 origin-bottom" style="height: 40%;"></div>
</div>
<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sat</span>
</div>
<div class="flex-1 flex flex-col items-center gap-4 group">
<div class="w-full bg-primary/10 rounded-xl relative h-20 overflow-hidden">
<div class="absolute bottom-0 w-full bg-primary transition-all group-hover:scale-y-110 origin-bottom" style="height: 30%;"></div>
</div>
<span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Sun</span>
</div>
</div>
</div>
<!-- Upcoming Appointments -->
<div class="elevated-card rounded-3xl p-8 flex flex-col">
<h3 class="text-xl font-bold font-headline text-on-background mb-8">Upcoming Appointments</h3>
<div class="flex-1 space-y-6">
<div class="flex gap-4 items-center group cursor-pointer">
<img alt="Sarah Jenkins" class="w-11 h-11 rounded-full object-cover ring-2 ring-primary/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDCKFeADgd9maUSKi7u9rfRcbwszVeKD2P8XrfW_5RSoEJ5aCxvlW774Pl7W0gsa6BtFo3rFcyD3_K128vVnJ9SPPjqawrbJLZp46r5PIbNnq1BccgxJS574eqXImK9oeNyhHct_jXi4LeEttwR_Mj0jKknSThyv3pHHcaxg-PPMapLhpuyKrbgjM5iQKA3-UOx3KSAQ6aOkmxVzdy1ReMJ1lWudi6KOCZLFYC09VWQRcnDmsMAhXpkiY6djOcPth4tBJfQE28-TGY"/>
<div class="flex-1 border-b border-slate-100 pb-3 group-hover:border-primary/20 transition-all">
<p class="text-sm font-bold text-on-background group-hover:text-primary transition-colors">Sarah Jenkins</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Root Canal • 09:30 AM</p>
</div>
</div>
<div class="flex gap-4 items-center group cursor-pointer">
<img alt="Michael Chen" class="w-11 h-11 rounded-full object-cover ring-2 ring-primary/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAphMnPQqk3s0rAnVLEy9fpdG7FN4xomr8h-Cu8HZlTehtdEPQUOORz9QKXN5aXMRAx4JFKWnvp41un4SvYYzZIcpU0n2mULCBWo308d6Y4ax2K1RZJj_5BOsQBh9gMTYeZn_RbdQ_WpyDg4B7TmeX1NHR5F_lAhIrurJkbJH3Kv7OhfvA6YGn6HzqGP-MF91shtOn_IeGtbSNa6DhfBrEo_KNMefpztKtWC9TKbwB59ab4d73D4BpDyFowKRw9xLPnh75UVJ1umMg"/>
<div class="flex-1 border-b border-slate-100 pb-3 group-hover:border-primary/20 transition-all">
<p class="text-sm font-bold text-on-background group-hover:text-primary transition-colors">Michael Chen</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Cleaning • 11:00 AM</p>
</div>
</div>
<div class="flex gap-4 items-center group cursor-pointer">
<div class="w-11 h-11 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-xs ring-2 ring-primary/10">JS</div>
<div class="flex-1 border-b border-slate-100 pb-3 group-hover:border-primary/20 transition-all">
<p class="text-sm font-bold text-on-background group-hover:text-primary transition-colors">James Smith</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Consult • 02:30 PM</p>
</div>
</div>
<div class="flex gap-4 items-center group cursor-pointer">
<img alt="Emily Rivera" class="w-11 h-11 rounded-full object-cover ring-2 ring-primary/10" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBYHVEeUPe6-2j7_z7Q1aegznqCbTXSlGCQwKeTLiw-PS2XkaBcONBAT6SJWxpki4bZFRklHpg7HmApY0r_Et891aWMsp5UE2bpLLzwvQ3Q3Y-fiSHmfkeTG9F0k2gqq8xqu5ecLD0K-Vi9rSxwKxYJ5LgIn8wsRVuSM3_o1WP_Qxi82S7ojVnjHinRbgMTa36AWgjMbfkyJMZiHux6aALjVmuVPkzdCaeehUXC13rRREXdSEpxO7MlrVMsI5hNS5LkKFD382YLA5o"/>
<div class="flex-1 pb-3">
<p class="text-sm font-bold text-on-background group-hover:text-primary transition-colors">Emily Rivera</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mt-1">Whitening • 04:00 PM</p>
</div>
</div>
</div>
<button class="w-full mt-6 py-3.5 bg-primary/10 text-primary font-black text-[11px] uppercase tracking-[0.2em] rounded-xl hover:bg-primary hover:text-white transition-all duration-300">
                    View Full Schedule
                </button>
</div>
</div>
<!-- Recent Bookings Section -->
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="p-8 border-b border-slate-100 flex justify-between items-center bg-white">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">Recent Bookings</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Daily appointment logs and financial summary</p>
</div>
<button class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">filter_list</span> Filter List
                </button>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Name</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date &amp; Time</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Service Type</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Assigned Dentist</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<img alt="Marcus Wright" class="w-9 h-9 rounded-full object-cover ring-2 ring-primary/5" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCwXG7XG0Zz6w6Yk_W7X-k7N8m8e9r-k1w2x3y4z5a6b7c8d9e0f1g2h3i4j5k6l7m8n9o0p1q2r3s4t5u6v7w8x9y0"/>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Marcus Wright</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">ID: #PD-4421</p>
</div>
</div>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700">Oct 24, 2023</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">09:15 AM</p>
</td>
<td class="px-6 py-5">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Tooth Extraction</span>
</td>
<td class="px-6 py-5">
<p class="text-sm font-medium text-slate-700">Dr. Helena Vance</p>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Completed
                                </span>
</td>
<td class="px-8 py-5 text-right">
<p class="text-sm font-extrabold text-slate-900">$350.00</p>
</td>
</tr>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-black text-xs">LH</div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Linda Harrison</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">ID: #PD-4422</p>
</div>
</div>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700">Oct 24, 2023</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">10:45 AM</p>
</td>
<td class="px-6 py-5">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Orthodontic Review</span>
</td>
<td class="px-6 py-5">
<p class="text-sm font-medium text-slate-700">Dr. Simon Kaan</p>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-primary/10 text-primary text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                    Confirmed
                                </span>
</td>
<td class="px-8 py-5 text-right">
<p class="text-sm font-extrabold text-slate-900">$120.00</p>
</td>
</tr>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<img alt="David Miller" class="w-9 h-9 rounded-full object-cover ring-2 ring-primary/5" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAphMnPQqk3s0rAnVLEy9fpdG7FN4xomr8h-Cu8HZlTehtdEPQUOORz9QKXN5aXMRAx4JFKWnvp41un4SvYYzZIcpU0n2mULCBWo308d6Y4ax2K1RZJj_5BOsQBh9gMTYeZn_RbdQ_WpyDg4B7TmeX1NHR5F_lAhIrurJkbJH3Kv7OhfvA6YGn6HzqGP-MF91shtOn_IeGtbSNa6DhfBrEo_KNMefpztKtWC9TKbwB59ab4d73D4BpDyFowKRw9xLPnh75UVJ1umMg"/>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">David Miller</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">ID: #PD-4423</p>
</div>
</div>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700">Oct 24, 2023</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">01:30 PM</p>
</td>
<td class="px-6 py-5">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Emergency Repair</span>
</td>
<td class="px-6 py-5">
<p class="text-sm font-medium text-slate-700">Dr. Helena Vance</p>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Pending
                                </span>
</td>
<td class="px-8 py-5 text-right">
<p class="text-sm font-extrabold text-slate-900">$580.00</p>
</td>
</tr>
</tbody>
</table>
</div>
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
<p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing 3 of 12 recent entries</p>
<div class="flex gap-2">
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</button>
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</button>
</div>
</div>
</section>
</div>
</main>
</body></html>