<?php $staff_nav_active = 'users'; ?>\n<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>User Management | Precision Dental</title>
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
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-64">
<!-- TopAppBar -->
<header class="flex justify-end items-center w-full px-10 sticky top-0 z-40 bg-white border-b border-slate-200 h-20">
<div class="flex items-center gap-6">
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
<button class="material-symbols-outlined hover:text-primary transition-colors">help</button>
</div>
<div class="h-8 w-px bg-slate-200 mx-2"></div>
<div class="flex items-center gap-3 cursor-pointer group">
<div class="text-right hidden sm:block">
<p class="text-sm font-bold text-slate-900 leading-none group-hover:text-primary transition-colors">Dr. Alistair Vaughn</p>
<p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mt-1">Chief Administrator</p>
</div>
<div class="h-10 w-10 rounded-xl overflow-hidden border-2 border-primary/20 p-0.5 group-hover:border-primary/40 transition-all">
<img alt="Manager Profile" class="w-full h-full rounded-lg object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAZo4E_DLVsjyaN0u8FvAm9je73LEaLVjNMT3I7yKQgHSNN0D5mxS0Cq-dbOuCirU1-sw4MVT3MtuMOg3d9WEw1CXMG6atw-CUCxSfWSRtuQLohaMTPC9293OU7ZdIZm53fWYnRsoMSXbrVFtPY1-Ri7s0zcSxxyMnEjAMnI36Fs7ADmnGeJrMsZgdSkNdhDiYdAKn3c2326y9Kze-pB2VSxZ8KDNErGUTlLkwmEGj9Y1Rs28u8EIN9k3TpVnGzX8d-HtFRNe63mCs"/>
</div>
</div>
</div>
</header>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> USER MANAGEMENT
                </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                            User <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                            Manage practitioner access and administrative permissions for your clinic.
                        </p>
</div>
<div class="flex items-center gap-4">
<div class="relative">
<select class="appearance-none bg-white border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl px-5 py-3 pr-10 focus:ring-primary/20 focus:border-primary transition-all outline-none">
<option>All Roles</option>
<option>Lead Dentist</option>
<option>Clinical Admin</option>
<option>Hygienist</option>
</select>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">expand_more</span>
</div>
<button class="px-6 py-3 bg-primary text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:scale-[0.98] transition-all">
                            Add New User
                        </button>
</div>
</div>
</section>
<!-- Stats Grid -->
<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<!-- Total Users -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">groups</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">+4%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">42</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Users</p>
</div>
</div>
<!-- Total Doctors -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">medical_services</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Active</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">18</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Doctors</p>
</div>
</div>
<!-- Total Staff -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">badge</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Contract</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">24</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Staffs</p>
</div>
</div>
<!-- Present Today -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">how_to_reg</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">92%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">38</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Present Today</p>
</div>
</div>
</section>
<!-- User Registry Table -->
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="p-8 border-b border-slate-100 flex justify-between items-center bg-white">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">User Registry</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Practitioner profiles and access logs</p>
</div>
<button class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">filter_list</span> Filter List
                    </button>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Name &amp; Profile</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Clinical Role</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Last Activity</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<!-- Row 1 -->
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<img alt="Dr. Julian Thorne" class="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/5 group-hover:ring-primary/20 transition-all" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAm1xcfHMOHYZ3Rp0z2JmxbVXV4luP91phGrfWu45ABgJ08Txpmxd42JOREQyqX9XQRLKgSgB2ArstafHKnNYBqwWjQPaFpfPnV3IWvEhtCH07Rkt62pzZHBrOYR0VNLWfg96PhvfBFGY66MLaxsRr18a6IhrUAditAZNM5JkqK_DDjEqUmg1eWJ6-yOq1VoWm4DphiEekFx18gIecRo93cQ2x83bQVpCFfCqb1LMb4NDZAYG1kjoicW1MmAUB1m1uLiZiMSlloAH4"/>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Dr. Julian Thorne</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">julian.t@aetherisdental.com</p>
</div>
</div>
</td>
<td class="px-6 py-6">
<span class="px-3 py-1 bg-primary/10 text-primary text-[10px] font-bold rounded-full uppercase tracking-wider">Lead Dentist</span>
</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
</td>
<td class="px-6 py-6">
<p class="text-sm font-semibold text-slate-700">2 mins ago</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">IP: 192.168.1.45</p>
</td>
<td class="px-8 py-6 text-right">
<div class="flex justify-end gap-2">
<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-all">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
<!-- Row 2 -->
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6 border-l-4 border-transparent hover:border-primary">
<div class="flex items-center gap-4">
<img alt="Sarah Jenkins" class="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/5 group-hover:ring-primary/20 transition-all" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDCj8N01J9eTJuiMe4SNP-AqwhjZ227LgfZ_15h4_JGWunxHebC-YFBuhUDkyRqpxJVlbKJRwvT8eY8CDFtJCRyMOaBkYV0y7yxDYhIgtdZ6FzlcAk9jT7hKo6YSnBz5WE0Wt8JQhsQYDBGe0WudW288QSMut8XQdUn7-lxcKpOj957I2P5RhqYg2ldUOgDLxEnOHLuFgiWwNyogV_hoAZIvDJvgZffEjyHI0J0vEH2inzELquxsYf8i84nJRpNW3L2ha_-6omY6Rg"/>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Sarah Jenkins</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">s.jenkins@aetherisdental.com</p>
</div>
</div>
</td>
<td class="px-6 py-6">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Clinical Admin</span>
</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
</td>
<td class="px-6 py-6">
<p class="text-sm font-semibold text-slate-700">Oct 24, 11:20 AM</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">Mobile App</p>
</td>
<td class="px-8 py-6 text-right">
<div class="flex justify-end gap-2">
<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-all">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
<!-- Row 3 -->
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-xs ring-2 ring-primary/5">MR</div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Marcus Rossi</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">m.rossi@aetherisdental.com</p>
</div>
</div>
</td>
<td class="px-6 py-6">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Hygienist</span>
</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-100 text-slate-500 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Inactive
                                    </span>
</td>
<td class="px-6 py-6">
<p class="text-sm font-semibold text-slate-700">3 days ago</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">User Disabled</p>
</td>
<td class="px-8 py-6 text-right">
<div class="flex justify-end gap-2">
<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-all">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
<!-- Row 4 -->
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<img alt="Elena Rodriguez" class="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/5 group-hover:ring-primary/20 transition-all" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAZo0L_oqtnr3M6sVNxchZH7HCYJsDdVH0qYoPLHXJ0uqq8Ox5LLZkHRE838biMfm-xe1Clq3zKv10JEZXpyEQJigT82BzszoCjXVT5D-eVN7Zzf4qv8deFrcA7N5lfq4AOObeUhwRDwrMGjGyNAidCNUHYTF0OsuHLZm0jqvYCYxVme-w7TfJ8bXagL8UgNY8HqQw2Zz3LQqoIU3rke8MV9oMpOg-3jXjiBq1BZL3uPp7HZdWI2yXgwuvjDZfd9gmN4LARChe9H3Y"/>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Elena Rodriguez</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">e.rodriguez@aetherisdental.com</p>
</div>
</div>
</td>
<td class="px-6 py-6">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider">Receptionist</span>
</td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
</td>
<td class="px-6 py-6">
<p class="text-sm font-semibold text-slate-700">14 mins ago</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">Dashboard</p>
</td>
<td class="px-8 py-6 text-right">
<div class="flex justify-end gap-2">
<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-primary hover:bg-primary/5 rounded-lg transition-all">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
</tbody>
</table>
</div>
<!-- Pagination Footer -->
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
<p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing 4 of 42 practitioners</p>
<div class="flex gap-2">
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary hover:bg-white transition-colors disabled:opacity-50" disabled="">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</button>
<button class="px-3 h-8 rounded-lg bg-primary text-white text-[10px] font-black uppercase">1</button>
<button class="px-3 h-8 rounded-lg border border-slate-200 text-slate-600 text-[10px] font-black uppercase hover:bg-white hover:text-primary transition-all">2</button>
<button class="px-3 h-8 rounded-lg border border-slate-200 text-slate-600 text-[10px] font-black uppercase hover:bg-white hover:text-primary transition-all">3</button>
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary hover:bg-white transition-colors">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</button>
</div>
</div>
</section>
</div>
<!-- Site Footer -->
<footer class="mt-auto px-10 py-8 border-t border-slate-100 flex justify-between items-center text-[11px] font-bold text-slate-400 uppercase tracking-widest">
<p>© 2024 Precision Dental Clinic System. All clinical data encrypted.</p>
<div class="flex gap-8">
<a class="hover:text-primary transition-colors" href="#">Privacy Protocol</a>
<a class="hover:text-primary transition-colors" href="#">System Status</a>
<a class="hover:text-primary transition-colors" href="#">Terms of Service</a>
</div>
</footer>
</main>
<!-- Floating Action Button -->
<button class="fixed bottom-8 right-8 w-14 h-14 bg-primary text-white rounded-2xl shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all z-50">
<span class="material-symbols-outlined text-2xl">add</span>
</button>
</body></html>