<?php
$staff_nav_active = 'reviews';
require_once __DIR__ . '/config/config.php';
// Dentist role restriction: redirect to dashboard
if (session_status() === PHP_SESSION_NONE) { clinic_session_start(); }
if (isset($_SESSION['user_role']) && strtolower(trim((string) $_SESSION['user_role'])) === 'dentist') {
    header('Location: StaffDashboard.php');
    exit;
}
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
<title>Clinical Precision - Patient Reviews</title>
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
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .elevated-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
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
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Page Header (High-contrast typography) -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> PATIENT REVIEWS
                </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                            Patient <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Reviews</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                            View and manage feedback from patients
                        </p>
</div>
</div>
</section>
<!-- Stats Grid (Card depths/spacing from SCREEN_142) -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<!-- Average Rating -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">star</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Active</span>
</div>
<div>
<div class="flex items-baseline gap-2">
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">4.8</p>
<p class="text-sm font-bold text-on-surface-variant/40">/ 5.0</p>
</div>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Average Rating</p>
</div>
</div>
<!-- Total Reviews -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">forum</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">+12%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">242</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Reviews</p>
</div>
</div>
<!-- Positive Feedback -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">recommend</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">Top Rated</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">96%</p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Positive Feedback</p>
</div>
</div>
</section>
<!-- Filters Bar -->
<div class="elevated-card p-5 rounded-2xl flex flex-wrap items-center gap-4">
<div class="flex-1 relative min-w-[300px]">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input class="w-full pl-12 pr-4 py-2.5 bg-slate-50 border-none rounded-xl text-sm focus:ring-2 focus:ring-primary/20 transition-all" placeholder="Search keywords..." type="text"/>
</div>
<div class="flex gap-3">
<select class="bg-slate-50 border-none rounded-xl py-2.5 px-5 pr-10 text-xs font-bold uppercase tracking-wider text-slate-600 focus:ring-2 focus:ring-primary/20 cursor-pointer">
<option>All Ratings</option>
<option>5 Stars</option>
<option>4 Stars</option>
</select>
<select class="bg-slate-50 border-none rounded-xl py-2.5 px-5 pr-10 text-xs font-bold uppercase tracking-wider text-slate-600 focus:ring-2 focus:ring-primary/20 cursor-pointer">
<option>All Services</option>
<option>Cleaning</option>
<option>Root Canal</option>
</select>
<button class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">filter_list</span> Filter
                    </button>
</div>
</div>
<!-- Reviews Feed (Styled like Recent Bookings table/rows) -->
<section class="elevated-card rounded-3xl overflow-hidden divide-y divide-slate-100">
<!-- Review Card 1 -->
<div class="p-8 hover:bg-slate-50/30 transition-colors group">
<div class="flex justify-between items-start mb-6">
<div class="flex gap-4 items-center">
<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center text-primary font-headline font-bold text-sm ring-2 ring-primary/5">SJ</div>
<div>
<h4 class="font-headline font-extrabold text-lg group-hover:text-primary transition-colors">Sarah J.</h4>
<p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Oct 24, 2023 • Verified Patient</p>
</div>
</div>
<div class="flex gap-0.5">
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
</div>
</div>
<div class="mb-6">
<span class="inline-block px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-widest mb-3">Teeth Whitening</span>
<p class="text-on-surface-variant font-medium leading-relaxed italic text-base">"Exceptional care and a very modern facility. Dr. Vance was very thorough and made the whole process incredibly comfortable. Highly recommend!"</p>
</div>
<div class="flex justify-end pt-6 border-t border-slate-100">
<button class="px-6 py-2.5 bg-primary text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">reply</span> Reply
                        </button>
</div>
</div>
<!-- Review Card 2 -->
<div class="p-8 hover:bg-slate-50/30 transition-colors group">
<div class="flex justify-between items-start mb-6">
<div class="flex gap-4 items-center">
<div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-headline font-bold text-sm ring-2 ring-primary/5">MC</div>
<div>
<h4 class="font-headline font-extrabold text-lg group-hover:text-primary transition-colors">Michael C.</h4>
<p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Oct 20, 2023 • Verified Patient</p>
</div>
</div>
<div class="flex gap-0.5">
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-slate-200 text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
</div>
</div>
<div class="mb-6">
<span class="inline-block px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-widest mb-3">General Cleaning</span>
<p class="text-on-surface-variant font-medium leading-relaxed italic text-base">"The waiting room feels more like a lounge. Staff is very professional and prompt. A bit of a wait for the appointment but the quality of care is worth it."</p>
</div>
<div class="flex justify-end pt-6 border-t border-slate-100">
<button class="px-6 py-2.5 bg-primary text-white text-[10px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">reply</span> Reply
                        </button>
</div>
</div>
<!-- Review Card 3 -->
<div class="p-8 hover:bg-slate-50/30 transition-colors group">
<div class="flex justify-between items-start mb-6">
<div class="flex gap-4 items-center">
<div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-headline font-bold text-sm ring-2 ring-primary/5">AL</div>
<div>
<h4 class="font-headline font-extrabold text-lg group-hover:text-primary transition-colors">Anita L.</h4>
<p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Oct 15, 2023 • Verified Patient</p>
</div>
</div>
<div class="flex gap-0.5">
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">star</span>
</div>
</div>
<div class="mb-6">
<span class="inline-block px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-widest mb-3">Root Canal</span>
<p class="text-on-surface-variant font-medium leading-relaxed italic text-base">"I was terrified of having a root canal but the technology they use here is incredible. Painless experience and wonderful follow-up."</p>
</div>
<div class="flex justify-between items-center pt-6 border-t border-slate-100">
<div class="flex items-center gap-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
<span class="material-symbols-outlined text-primary text-base">check_circle</span>
                            Replied by Clinic on Oct 16
                        </div>
<button class="px-6 py-2.5 bg-slate-50 text-slate-900 text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-slate-100 transition-all border border-slate-200">
                            Edit Reply
                        </button>
</div>
</div>
</section>
<!-- Pagination -->
<div class="flex justify-center pt-4">
<button class="px-10 py-4 bg-primary/10 text-primary font-black text-xs uppercase tracking-[0.2em] rounded-2xl hover:bg-primary hover:text-white transition-all duration-300 shadow-sm">
                    Load More Reviews
                </button>
</div>
</div>
</main>
</body></html>