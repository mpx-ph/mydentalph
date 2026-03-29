<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';
$provider_nav_active = 'subs';
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Subscription &amp; Billing</title>
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
                        "outline-variant": "#cbd5e1",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#404752",
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
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .primary-gradient {
            background: linear-gradient(135deg, #2b8beb 0%, #1a74d1 100%);
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
        body { font-family: 'Manrope', sans-serif; }
    </style>
</head>
<body class="font-body text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<!-- Main Content Shell -->
<main class="flex-1 flex flex-col min-w-0 ml-64 provider-page-enter">
<!-- TopNavBar Component -->
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
<button class="material-symbols-outlined hover:text-primary transition-colors">notifications</button>
<button class="material-symbols-outlined hover:text-primary transition-colors">help_outline</button>
</div>
<div class="h-10 w-10 rounded-full overflow-hidden border-2 border-primary/20 p-0.5">
<img alt="Admin Avatar" class="w-full h-full rounded-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDtrO4z85y5D8iEXvQHkj0104-7MYy2wc5CzWIno3ZJoAATelGkmp38rAyasasOmmW4wQGq4BNoZKHdySndKoVpO0XB--E1A2_5vRl1kJ1g6AwqHiRB9aF7Yv5OPB7OqtgfW5uQki5KYLBdduA_b7JsF8T4nXHAsa8mcd_I8gYvQmeIbzL_UHdgtG6fegcuLkghbanknIbOkbHKq8KrbuR0fKD1anCt1DHD2CQeCqok7B1aLKIj7B0Yt_eChTBnS3Q8VKD6RcbiQO0"/>
</div>
</div>
</header>
<!-- Main Content -->
<div class="p-10 space-y-6">
<!-- Bottom Section: Billing History -->
<div class="pt-4">
<!-- Billing History Card -->
<div class="elevated-card provider-card-lift rounded-3xl overflow-hidden flex flex-col w-full border-2 border-primary/20">
<div class="px-10 py-6 border-b border-slate-100">
<h5 class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant/60">Billing History</h5>
</div>
<div class="overflow-x-auto flex-1">
<table class="w-full text-left">
<thead>
<tr class="bg-slate-50/50">
<th class="px-10 py-6 text-sm font-black uppercase tracking-widest text-on-surface-variant/60">Date</th>
<th class="px-10 py-6 text-sm font-black uppercase tracking-widest text-on-surface-variant/60 text-right">Amount</th>
<th class="px-10 py-6 text-sm font-black uppercase tracking-widest text-on-surface-variant/60">Status</th>
<th class="px-10 py-6 text-sm font-black uppercase tracking-widest text-on-surface-variant/60 text-right">Invoice</th>
</tr>
</thead>
<tbody class="divide-y divide-y divide-primary/20">
<tr class="group hover:bg-slate-50/50 transition-colors">
<td class="px-10 py-8">
<div class="text-lg font-bold text-on-background">Oct 24, 2023</div>
<div class="text-sm text-slate-500 font-medium">Yearly Pro Plan</div>
</td>
<td class="px-10 py-8 text-xl font-black text-on-background text-right">$1,490.00</td>
<td class="px-10 py-8">
<span class="bg-green-100 text-green-700 text-xs font-black px-5 py-2 rounded-lg uppercase tracking-widest">Paid</span>
</td>
<td class="px-10 py-8 text-right">
<button class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-white border border-slate-200 text-primary hover:border-primary/50 transition-all shadow-sm">
<span class="material-symbols-outlined text-2xl">download</span>
</button>
</td>
</tr>
<tr class="group hover:bg-slate-50/50 transition-colors">
<td class="px-10 py-8">
<div class="text-lg font-bold text-on-background">Oct 24, 2022</div>
<div class="text-sm text-slate-500 font-medium">Yearly Pro Plan</div>
</td>
<td class="px-10 py-8 text-xl font-black text-on-background text-right">$1,490.00</td>
<td class="px-10 py-8">
<span class="bg-green-100 text-green-700 text-xs font-black px-5 py-2 rounded-lg uppercase tracking-widest">Paid</span>
</td>
<td class="px-10 py-8 text-right">
<button class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-white border border-slate-200 text-primary hover:border-primary/50 transition-all shadow-sm">
<span class="material-symbols-outlined text-2xl">download</span>
</button>
</td>
</tr>
</tbody>
</table>
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
</main>
</body></html>