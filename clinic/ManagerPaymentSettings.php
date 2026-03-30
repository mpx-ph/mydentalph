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
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">dashboard</span>
<span class="font-headline text-sm font-medium tracking-tight">Dashboard</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">calendar_today</span>
<span class="font-headline text-sm font-medium tracking-tight">Appointments</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">person_add</span>
<span class="font-headline text-sm font-medium tracking-tight">Patient Registration</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">group</span>
<span class="font-headline text-sm font-medium tracking-tight">Patients</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">payments</span>
<span class="font-headline text-sm font-medium tracking-tight">Payments</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">chat</span>
<span class="font-headline text-sm font-medium tracking-tight">Messages</span>
</a>
</div>
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="#">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">settings</span>
<span class="font-headline text-sm font-bold tracking-tight">Settings</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
</div>
</nav>
<div class="mt-auto pt-6 space-y-1">
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-500 hover:text-slate-900 transition-colors font-headline font-medium text-sm" href="#">
<span class="material-symbols-outlined text-[22px]">contact_support</span>
                Support
            </a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-rose-500 transition-colors duration-200 hover:bg-rose-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">logout</span>
<span class="font-headline text-sm font-medium tracking-tight">Logout</span>
</a>
</div>
</div>
</aside>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-64">
<!-- TopAppBar -->
<header class="flex justify-between items-center w-full px-10 sticky top-0 z-40 bg-white border-b border-slate-200 h-20">
<div class="flex items-center flex-1 max-w-xl">
<div class="relative w-full group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">search</span>
<input class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border-none rounded-full text-sm focus:ring-2 focus:ring-primary/20 transition-all outline-none" placeholder="Search patients, records, or staff..." type="text"/>
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
<p class="text-sm font-bold text-slate-900 leading-none">Clinical Precision</p>
<p class="text-[11px] text-slate-500 font-bold uppercase tracking-wider mt-1">Clinic Manager</p>
</div>
<div class="h-10 w-10 rounded-full overflow-hidden border-2 border-primary/20 p-0.5">
<img alt="Manager Profile" class="w-full h-full rounded-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC7dGIBm_3RlenM-4jNkyKdHQT-M-7rXFP6FVYEx_NLY3PuxuE_nUTMlZKI8OSdySvqsSepJyfw4wZ_zLXBfpPE5rSUCRwQ0kn9WObJlvQsZho7CZPKQf3rGBISrdc4eRxDuD_zybVHwMUipDqnCFlIzwk3ZorxLWj_E2DVVHtd3Aq198CpkcDZUFNlDfg5midtgcJW6f8CYIQGJrPUiug3sDkkG12W0HX16N7-uPoo7FLUM7jnSIMboaaH3nqMZ-7I8S5yn89-07A"/>
</div>
</div>
</div>
</header>
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