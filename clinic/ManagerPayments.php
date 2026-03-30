<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision - Payment Recording</title>
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
        .glass-form {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            background-image: radial-gradient(circle at top right, rgba(43, 139, 235, 0.05), transparent);
        }
        .form-input-styled {
            border: 2px solid transparent;
            background: rgba(241, 245, 249, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-input-styled:focus {
            border-color: #2b8beb;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(43, 139, 235, 0.1);
        }
        .payment-card {
            transition: all 0.2s ease;
        }
        .payment-card:hover {
            transform: translateY(-2px);
        }
        .payment-card.active {
            background: #2b8beb;
            color: white;
            border-color: #2b8beb;
            box-shadow: 0 8px 16px -4px rgba(43, 139, 235, 0.4);
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
<span class="font-headline text-sm font-medium tracking-tight">Registration</span>
</a>
</div>
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="#">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">payments</span>
<span class="font-headline text-sm font-bold tracking-tight">Payments</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
<span class="material-symbols-outlined text-[22px]">group</span>
<span class="font-headline text-sm font-medium tracking-tight">Patients</span>
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
<button class="material-symbols-outlined hover:text-primary transition-colors">help_outline</button>
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
<span class="w-12 h-[1.5px] bg-primary"></span> PAYMENT RECORDING
            </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Payment <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Recording</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Record and track all clinic payment transactions
                    </p>
</div>
<button class="px-6 py-3 bg-primary text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all">
                    New Transaction
                </button>
</div>
</section>
<!-- Summary Cards -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<!-- Total Revenue -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">+12.5%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">₱1.2M</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Revenue</p>
</div>
</div>
<!-- Today's Revenue -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">today</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Today</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">₱42k</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Today's Revenue</p>
</div>
</div>
<!-- Total Payments -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">receipt_long</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Lifetime</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">3,492</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Payments</p>
</div>
</div>
</section>
<!-- Record New Payment Module -->
<section class="max-w-4xl mx-auto w-full">
<div class="glass-form p-10 rounded-[2.5rem] shadow-2xl shadow-primary/10">
<div class="flex justify-between items-start mb-10 border-b border-primary/10 pb-6">
<div>
<h3 class="text-3xl font-black font-headline text-slate-900">Record New Payment</h3>
<p class="text-xs text-primary font-bold uppercase tracking-[0.2em] mt-1">Submit digital transaction receipt</p>
</div>
<div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">add_card</span>
</div>
</div>
<form class="space-y-10">
<div class="space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Patient Identification</label>
<div class="relative group">
<span class="material-symbols-outlined absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">person_search</span>
<input class="w-full pl-14 pr-6 py-4 form-input-styled rounded-2xl text-base font-medium outline-none" placeholder="Enter patient name or ID number..." type="text"/>
</div>
</div>
<div class="flex flex-col md:flex-row gap-8 items-center">
<div class="flex-1 w-full space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Payment Amount</label>
<div class="relative group">
<span class="absolute left-5 top-1/2 -translate-y-1/2 text-lg font-extrabold text-slate-500 group-focus-within:text-primary transition-colors">₱</span>
<input class="w-full pl-12 pr-6 py-4 form-input-styled rounded-2xl text-xl font-black outline-none" placeholder="0.00" type="number"/>
</div>
</div>
<div class="hidden md:block h-12 w-px bg-slate-200 mt-6"></div>
<div class="flex-1 w-full space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Transaction Date</label>
<div class="relative group">
<input class="w-full px-6 py-4 form-input-styled rounded-2xl text-base font-semibold outline-none" type="date" value="2023-10-24"/>
</div>
</div>
</div>
<div class="space-y-4">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Payment Method</label>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">account_balance_wallet</span>
<span class="text-[11px] font-black uppercase tracking-widest">GCash</span>
</button>
<button class="payment-card active p-4 rounded-2xl border-2 border-primary bg-primary text-white flex flex-col items-center justify-center gap-3" type="button">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">payments</span>
<span class="text-[11px] font-black uppercase tracking-widest">Cash</span>
</button>
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">account_balance</span>
<span class="text-[11px] font-black uppercase tracking-widest">Bank</span>
</button>
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">credit_card</span>
<span class="text-[11px] font-black uppercase tracking-widest">Card</span>
</button>
</div>
</div>
<div class="space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Additional Notes</label>
<textarea class="w-full px-6 py-4 form-input-styled rounded-2xl text-sm font-medium outline-none resize-none" placeholder="Describe the treatment or specific billing details..." rows="3"></textarea>
</div>
<div class="pt-4">
<button class="w-full py-5 bg-primary text-white font-black text-sm uppercase tracking-[0.3em] rounded-2xl shadow-2xl shadow-primary/40 hover:shadow-primary/60 hover:-translate-y-1 active:translate-y-0 active:scale-[0.99] transition-all flex items-center justify-center gap-4 relative overflow-hidden group" type="submit">
<span class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></span>
<span class="material-symbols-outlined text-2xl relative" style="font-variation-settings: 'FILL' 1;">verified</span>
<span class="relative">Confirm &amp; Post Payment</span>
</button>
</div>
</form>
</div>
</section>
<!-- Recent Transactions Section -->
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="p-8 border-b border-slate-100 flex justify-between items-center bg-white">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">Recent Transactions</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Latest daily transaction log</p>
</div>
<div class="flex gap-3">
<button class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">filter_list</span> Filter
                    </button>
<button class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">download</span> Export CSV
                    </button>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Name</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Amount</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date &amp; Time</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Method</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-black text-xs">RM</div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Ricardo J. Manabat</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">ID: PAT-2024-001</p>
</div>
</div>
</td>
<td class="px-6 py-5">
<p class="text-sm font-extrabold text-slate-900">₱12,500.00</p>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700">Oct 24, 2023</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">02:30 PM</p>
</td>
<td class="px-6 py-5">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-primary text-sm">wallet</span>
<span class="text-[10px] font-bold text-slate-600 uppercase tracking-wider">GCash</span>
</div>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Completed
                                </span>
</td>
<td class="px-8 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 hover:bg-primary/10 rounded-lg text-primary transition-colors">
<span class="material-symbols-outlined text-sm">visibility</span>
</button>
<button class="p-2 hover:bg-slate-100 rounded-lg text-slate-400 transition-colors">
<span class="material-symbols-outlined text-sm">edit</span>
</button>
</div>
</td>
</tr>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-black text-xs">ES</div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">Elena S. Santos</p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">ID: PAT-2024-142</p>
</div>
</div>
</td>
<td class="px-6 py-5">
<p class="text-sm font-extrabold text-slate-900">₱4,800.00</p>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700">Oct 24, 2023</p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5">01:15 PM</p>
</td>
<td class="px-6 py-5">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-slate-500 text-sm">payments</span>
<span class="text-[10px] font-bold text-slate-600 uppercase tracking-wider">Cash</span>
</div>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-50 text-amber-600 text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Pending
                                </span>
</td>
<td class="px-8 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 hover:bg-primary/10 rounded-lg text-primary transition-colors">
<span class="material-symbols-outlined text-sm">visibility</span>
</button>
<button class="p-2 hover:bg-slate-100 rounded-lg text-slate-400 transition-colors">
<span class="material-symbols-outlined text-sm">edit</span>
</button>
</div>
</td>
</tr>
</tbody>
</table>
</div>
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
<p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing 2 of 3,492 recent entries</p>
<div class="flex gap-2">
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</button>
<button class="w-8 h-8 rounded-lg bg-primary text-white text-xs font-black">1</button>
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</button>
</div>
</div>
</section>
</div>
</main>
</body></html>