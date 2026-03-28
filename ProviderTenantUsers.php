<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';
$provider_nav_active = 'users';
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Users</title>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
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
                        "surface-variant": "#f1f5f9",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#e2e8f0",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#475569",
                        "background": "#f8fafc",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#8e4a00",
                        "tertiary-container": "#ffdcc3",
                        "error": "#ba1a1a"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .provider-nav-link:not(.provider-nav-link--active):hover {
            transform: translateX(4px);
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .provider-card-lift {
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .provider-card-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        @keyframes provider-modal-in {
            from { opacity: 0; transform: scale(0.94) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .provider-modal-panel {
            animation: provider-modal-in 0.4s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .provider-modal-backdrop {
            animation: provider-page-in 0.35s ease forwards;
        }
        body { font-family: 'Manrope', sans-serif; }
    </style>
</head>
<body class="mesh-bg font-body text-on-background min-h-screen flex">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<!-- TopNavBar Component -->
<main class="flex-1 flex flex-col min-w-0 ml-64 provider-page-enter">
<header class="flex justify-between items-center w-full px-10 sticky top-0 z-30 bg-white/80 backdrop-blur-xl border-b border-slate-200/80 h-20 shadow-sm shadow-slate-200/40">
<div class="flex items-center gap-8">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
<span class="font-headline text-[10px] font-black uppercase tracking-[0.2em] text-primary">Clinic Status: Active</span>
</div>
<div class="h-4 w-px bg-slate-200"></div>
<span class="font-headline text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Plan: Premium Pro</span>
</div>
<div class="flex items-center gap-6">
<div class="flex items-center gap-6 text-on-surface-variant/60">
<button class="material-symbols-outlined hover:text-primary transition-colors" data-icon="notifications">notifications</button>
<button class="material-symbols-outlined hover:text-primary transition-colors" data-icon="help_outline">help_outline</button>
</div>
<div class="h-10 w-10 rounded-full overflow-hidden border-2 border-primary/20 p-0.5">
<img alt="Admin Avatar" class="w-full h-full rounded-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDtrO4z85y5D8iEXvQHkj0104-7MYy2wc5CzWIno3ZJoAATelGkmp38rAyasasOmmW4wQGq4BNoZKHdySndKoVpO0XB--E1A2_5vRl1kJ1g6AwqHiRB9aF7Yv5OPB7OqtgfW5uQki5KYLBdduA_b7JsF8T4nXHAsa8mcd_I8gYvQmeIbzL_UHdgtG6fegcuLkghbanknIbOkbHKq8KrbuR0fKD1anCt1DHD2CQeCqok7B1aLKIj7B0Yt_eChTBnS3Q8VKD6RcbiQO0"/>
</div>
</div>
</header>
<!-- Main Content -->
<div class="p-8 space-y-8">
<!-- Header Card -->
<section class="elevated-card provider-card-lift rounded-3xl p-10 flex flex-col gap-6">
<div class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> User Management</div>
<div class="flex justify-between items-end">
<div>
<h2 class="font-headline font-extrabold tracking-tighter leading-tight text-on-background text-6xl">User <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span></h2>
<p class="font-body text-xl font-medium text-slate-600 max-w-3xl leading-relaxed mt-6">Manage practitioner access and administrative permissions for your clinic.</p>
</div>
<button type="button" id="add-user-open" class="bg-primary text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:shadow-xl hover:shadow-primary/25 transition-all duration-300 active:scale-95 hover:scale-[1.02] flex items-center gap-2">
<span class="material-symbols-outlined text-base">person_add</span>
                        Add New User
                    </button>
</div>
</div>
<!-- Internal Control Bar (part of Header Card or separate, user asked for "each section" to sit on card) -->
<div class="flex flex-wrap gap-4 items-center justify-between pt-8 border-t border-slate-100">
<div class="flex gap-4">
<div class="relative">
<select class="appearance-none bg-slate-50 border border-slate-200 rounded-2xl px-8 py-3.5 pr-12 text-on-background text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 cursor-pointer transition-all">
<option>All Roles</option>
<option>Clinical Admin</option>
<option>Lead Dentist</option>
<option>Dental Hygienist</option>
<option>Reception Staff</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">expand_more</span>
</div>
<div class="relative">
<select class="appearance-none bg-slate-50 border border-slate-200 rounded-2xl px-8 py-3.5 pr-12 text-on-background text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 cursor-pointer transition-all">
<option>Active Status</option>
<option>Online Now</option>
<option>On Leave</option>
<option>Inactive</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">filter_list</span>
</div>
</div>
<div class="bg-primary/5 px-6 py-2.5 rounded-full border border-primary/10">
<p class="text-primary text-[10px] font-black uppercase tracking-widest">
                        Displaying <span class="text-slate-900">24</span> active staff members
                    </p>
</div>
</div>
</section>
<!-- Table Card -->
<div class="elevated-card provider-card-lift rounded-3xl overflow-hidden">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50 border-b border-slate-100">
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Practitioner</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Security Role</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Clinic Status</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Last Activity</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<!-- User Row 1 -->
<tr class="group hover:bg-slate-50/50 transition-colors duration-200">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-12 h-12 rounded-2xl bg-primary/10 overflow-hidden ring-2 ring-primary/5 transition-transform duration-300 group-hover:scale-105 group-hover:ring-primary/20">
<img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCVSmeAg0m57MZZCu5NZxbRzj4ccYyU2NhZv7uXtD8ZZzl6VM-_t_UKKOR7SsXeJBL2zA_qgH4SEtzpjRa_slK6vufp9KZXhX_0bCnBQ1vMUy537pHtbsLsdPyfIQsndvNbJ5SiWtgN3Q0A3fB3LXwI39N5GI5Uf0BWOccgT5dXNRxRUIw2mbysB05yMb9ft8P5aYIUdy7otgpj10EqNUAPRzuoLNCpSStugPTeCYLTlWKec3ImR8S8S92BA-VbpN3fCFBaQU3e5R0"/>
</div>
<div>
<div class="font-headline font-extrabold text-slate-900">Dr. Julian Thorne</div>
<div class="text-[11px] font-medium text-on-surface-variant/70 mt-0.5">j.thorne@aetheris-dental.com</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-primary/10 text-primary text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest">Lead Dentist</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
<span class="text-[10px] font-black text-on-surface-variant/70 uppercase tracking-widest">Active</span>
</div>
</td>
<td class="px-10 py-8">
<div class="text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest">2 mins ago</div>
</td>
<td class="px-10 py-8 text-right">
<div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110 hover:shadow-md">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-rose-500 hover:bg-rose-50 hover:border-rose-200 flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
<!-- Row 2 -->
<tr class="group hover:bg-slate-50/50 transition-colors duration-200">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-12 h-12 rounded-2xl bg-primary/10 overflow-hidden ring-2 ring-primary/5 transition-transform duration-300 group-hover:scale-105 group-hover:ring-primary/20">
<img class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCBUOnZJ5ntR4l1hovJzsT9mMky3meAhD9alCVEISnezadCCcbDP4ks_MAxYbTIzI1V49EeK5hgB84Gzwq2flSHvaAtXCPxNliuef7NdnDcq10VBFnPLoPoWEbtqyDeHAXk3lQc72G8WCDRHuuSjatAgJItLzkudN1qW-3uBUQKlm1lJlgiWI7wNqCLzOUQxhfH7lQkJUom-X368c-Ek9828jUWnJS5g8Qygm-EVVmwhONQZSsU9o3wNkQa-ePg38bMFEEPJKmIqQc"/>
</div>
<div>
<div class="font-headline font-extrabold text-slate-900">Elena Rodriguez</div>
<div class="text-[11px] font-medium text-on-surface-variant/70 mt-0.5">e.rodriguez@aetheris-dental.com</div>
</div>
</div>
</td>
<td class="px-10 py-8">
<span class="bg-slate-100 text-on-surface-variant text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest">Clinical Admin</span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
<span class="text-[10px] font-black text-on-surface-variant/70 uppercase tracking-widest">Active</span>
</div>
</td>
<td class="px-10 py-8">
<div class="text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest">Today, 09:15 AM</div>
</td>
<td class="px-10 py-8 text-right">
<div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110 hover:shadow-md">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-rose-500 hover:bg-rose-50 hover:border-rose-200 flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
</tbody>
</table>
<!-- Pagination -->
<div class="px-10 py-8 bg-slate-50 border-t border-slate-100 flex justify-between items-center">
<p class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant/70">
                    Showing <span class="text-slate-900">1 - 4</span> of 24
                </p>
<div class="flex gap-2">
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-400 disabled:opacity-50" disabled="">
<span class="material-symbols-outlined">chevron_left</span>
</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-primary text-white font-black text-xs shadow-lg shadow-primary/20">1</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-600 font-black text-xs hover:border-primary/30 transition-all">2</button>
<button class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-primary/30 transition-all">
<span class="material-symbols-outlined">chevron_right</span>
</button>
</div>
</div>
</div>
</div>
<!-- Footer Status -->
<footer class="mt-auto p-8 flex justify-center sticky bottom-0 z-10 pointer-events-none">
<div class="elevated-card pointer-events-auto px-10 py-4 rounded-full border border-slate-200/50 shadow-2xl flex items-center gap-10 text-[10px] font-black text-on-surface-variant/70 uppercase tracking-[0.2em]">
<div class="flex items-center gap-3 text-primary">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                System Log: Real-time
            </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">schedule</span>
                Last Login: 10:24 AM
            </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">location_on</span>
                IP: 192.168.1.1
            </div>
</div>
</footer>
<div id="add-user-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4" aria-hidden="true">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm provider-modal-backdrop" data-modal-dismiss></div>
<div class="relative w-full max-w-md rounded-3xl bg-white shadow-2xl border border-slate-200/80 p-8 provider-modal-panel" role="dialog" aria-modal="true" aria-labelledby="add-user-title">
<h3 id="add-user-title" class="font-headline text-xl font-extrabold text-on-background">Add team member</h3>
<p class="text-sm text-on-surface-variant mt-2">Invite a new user to your clinic workspace. Full provisioning will connect here later.</p>
<div class="mt-6 space-y-4">
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant">Email</label>
<input type="email" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary transition-all" placeholder="name@clinic.com"/>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant">Role</label>
<select class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:ring-2 focus:ring-primary/25 focus:border-primary transition-all">
<option>Clinical Admin</option>
<option>Lead Dentist</option>
<option>Reception Staff</option>
</select>
</div>
<div class="mt-8 flex gap-3 justify-end">
<button type="button" class="px-5 py-2.5 rounded-xl text-sm font-bold text-on-surface-variant hover:bg-slate-100 transition-colors" data-modal-dismiss>Cancel</button>
<button type="button" class="px-5 py-2.5 rounded-xl text-sm font-bold bg-primary text-white hover:brightness-110 shadow-lg shadow-primary/25 transition-all hover:scale-[1.02] active:scale-95">Send invite</button>
</div>
</div>
</div>
</main>
<script>
(function () {
  var modal = document.getElementById('add-user-modal');
  var openBtn = document.getElementById('add-user-open');
  if (!modal || !openBtn) return;
  function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
  openBtn.addEventListener('click', openModal);
  modal.querySelectorAll('[data-modal-dismiss]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });
})();
</script>
</body></html>