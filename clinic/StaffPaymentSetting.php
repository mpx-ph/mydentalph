<?php
$staff_nav_active = 'payment_settings';
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
<title>Clinical Precision - Payment Settings</title>
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
<span class="w-12 h-[1.5px] bg-primary"></span> FINANCE &amp; PAYMENTS
            </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Payment <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Settings</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Configure payment rules and down payment options for services
                    </p>
</div>
</div>
</section>
<!-- Configuration Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<!-- Regular Services Down Payment -->
<div class="elevated-card rounded-3xl p-10 flex flex-col gap-8 hover:border-primary/30 transition-all group bg-slate-50/50">
<div class="flex items-center gap-5">
<div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
</div>
<div>
<h3 class="font-headline font-bold text-2xl text-on-background tracking-tight">Regular Services Down Payment</h3>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-1">Standard clinic procedures &amp; checkups</p>
</div>
</div>
<div class="space-y-6">
<div class="space-y-3">
<label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Requirement Percentage</label>
<div class="relative">
<input class="w-full bg-white border-none rounded-xl px-6 py-5 text-2xl font-headline font-extrabold text-on-background focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" type="number" value="20"/>
<div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none">
<span class="text-2xl font-extrabold text-primary">%</span>
</div>
</div>
</div>
<div class="p-5 rounded-2xl bg-white/60 border border-slate-100 text-sm text-slate-500 font-medium leading-relaxed">
                        Applied to all basic diagnostic, preventative, and minor restorative services.
                    </div>
<button class="w-full py-4 bg-primary text-white font-bold text-xs uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:shadow-primary/40 active:scale-95 transition-all flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-lg">published_with_changes</span>
                        Update Rule
                    </button>
</div>
</div>
<!-- Long-Term Services Down Payment -->
<div class="elevated-card rounded-3xl p-10 flex flex-col gap-8 hover:border-primary/30 transition-all group bg-slate-50/50">
<div class="flex items-center gap-5">
<div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">event_repeat</span>
</div>
<div>
<h3 class="font-headline font-bold text-2xl text-on-background tracking-tight">Long-Term Services Down Payment</h3>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-1">Orthodontics, implants, and multi-stage plans</p>
</div>
</div>
<div class="space-y-6">
<div class="space-y-3">
<label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Fixed Minimum Amount</label>
<div class="relative">
<input class="w-full bg-white border-none rounded-xl pl-14 pr-16 py-5 text-2xl font-headline font-extrabold text-on-background focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" type="number" value="500"/>
<div class="absolute left-6 top-1/2 -translate-y-1/2 pointer-events-none">
<span class="material-symbols-outlined text-primary text-2xl" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none">
<span class="text-xs font-black text-slate-400 uppercase tracking-widest">USD</span>
</div>
</div>
</div>
<div class="p-5 rounded-2xl bg-white/60 border border-slate-100 text-sm text-slate-500 font-medium leading-relaxed">
                        Minimum deposit required to initiate multi-month treatment cycles and material orders.
                    </div>
<button class="w-full py-4 bg-primary text-white font-bold text-xs uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:shadow-primary/40 active:scale-95 transition-all flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-lg">published_with_changes</span>
                        Update Rule
                    </button>
</div>
</div>
</div>
<!-- Global Configuration Bento -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
<div class="md:col-span-2 elevated-card rounded-3xl p-8 flex items-center justify-between">
<div class="space-y-2">
<h4 class="font-headline font-bold text-xl text-on-background">Automatic Invoice Generation</h4>
<p class="text-sm text-on-surface-variant/60 font-medium font-body">Automatically send down-payment invoices upon appointment confirmation.</p>
</div>
<div class="relative inline-flex items-center cursor-pointer group">
<input checked="" class="sr-only peer" type="checkbox" value=""/>
<div class="w-16 h-9 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:start-[4px] after:bg-white after:border-slate-200 after:border after:rounded-full after:h-7 after:w-7 after:transition-all peer-checked:bg-primary shadow-sm"></div>
</div>
</div>
<div class="bg-primary rounded-3xl p-8 flex flex-col justify-center relative overflow-hidden active-glow">
<div class="relative z-10">
<p class="text-[10px] font-black uppercase tracking-[0.2em] text-white/70 mb-2">Financial Health</p>
<div class="flex items-baseline gap-2">
<span class="text-4xl font-headline font-extrabold text-white">98.4%</span>
<span class="text-[10px] text-white/70 font-black uppercase tracking-widest">Collection Rate</span>
</div>
</div>
<!-- Decorative pattern -->
<div class="absolute -right-6 -bottom-6 opacity-20 transform -rotate-12">
<span class="material-symbols-outlined text-[120px] text-white">trending_up</span>
</div>
</div>
</div>
<!-- Footer Meta Info -->
<div class="pt-8 border-t border-slate-200 flex justify-between items-center">
<div class="flex items-center gap-4">
<div class="flex -space-x-3">
<img alt="Admin" class="w-9 h-9 rounded-full border-2 border-white object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDlKy5_WAt4BER0EmlhL1incjxs3s8yftPBA7KR-4vxpcljzva4HHwtUD6nQrafpLqj12Vn167N86EV5dcFCUoojIfz9mkiSIhzXoYxvLjZUEXDS5y28MLGI_nIOJD85UpG_6UG-IP_-MutF53V5bqlFV4glVJeir90GnY0wFyKGopBq5N13y6SrQVd15BAfQXevf_Qg7MClVt1Q8Aq08HT_KZGzs6SO3s6AAfBlbqsK9WAVhBgHvKZWt3qCb_WMYB2T3qPNktt6k0"/>
<img alt="Admin 2" class="w-9 h-9 rounded-full border-2 border-white object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAzdJIJZU9YM4q5ghsEjXFPrGyNKmb18OXDMYd67PNLG22vzRTh19NYRmfeccugFRhfqNqSIYLw_GPRLk_dT59B6CLNUNti15BTzsoETD9SvwDAbYF0m6EN8K5wv5iTmKSZ5Je07-F4twAsImjVJSuyLTHPWKvCIobSAcq9SzZiteBCroz-cy8JsvGN8PMpjd5Ce7nnahguUC4TI0uafGLSyH0SlqFmC_Hp3ZcY6zUfrNWP6duoScvCy9_bU9Wjp55mf2UTX8juW4s"/>
</div>
<p class="text-[11px] text-slate-400 font-bold uppercase tracking-widest italic">Last modified by Sarah J. &amp; Dr. Miller today at 10:45 AM</p>
</div>
<div class="flex items-center gap-6">
<button class="text-[10px] font-black text-slate-400 hover:text-primary transition-colors uppercase tracking-widest">Export Audit Log</button>
<button class="text-[10px] font-black text-slate-400 hover:text-primary transition-colors uppercase tracking-widest">Restore Defaults</button>
</div>
</div>
</div>
</main>
</body></html>