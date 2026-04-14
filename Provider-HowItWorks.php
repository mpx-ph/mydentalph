<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Features | Aetheris OS</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "on-surface": "#131c25",
                        "surface": "#ffffff",
                        "surface-variant": "#f7f9ff",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "primary-fixed": "#d4e3ff",
                        "on-primary-fixed-variant": "#004883",
                        "surface-container-low": "#edf4ff",
                        "inverse-surface": "#131c25",

                        /* Existing app colors used by ProviderNavbar */
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"],
                        "inter": ["Inter", "sans-serif"],
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "3xl": "2.5rem", "full": "9999px" },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        @keyframes slowFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-14px); }
        }
        .slow-float {
            animation: slowFloat 10s ease-in-out infinite;
        }

        /* Scroll-reveal animation (section-level) */
        .reveal {
            opacity: 0;
            transform: translateY(34px) scale(0.985);
            filter: blur(12px);
            transition:
                opacity 900ms cubic-bezier(0.22, 1, 0.36, 1),
                transform 900ms cubic-bezier(0.22, 1, 0.36, 1),
                filter 900ms cubic-bezier(0.22, 1, 0.36, 1);
            will-change: opacity, transform, filter;
        }
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
        }

        @media (prefers-reduced-motion: reduce) {
            .reveal {
                opacity: 1;
                transform: none;
                filter: none;
                transition: none;
            }
            .slow-float { animation: none; }
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .slanted-container {
            clip-path: polygon(15% 0%, 100% 0%, 100% 100%, 0% 100%);
        }
        .mesh-gradient {
            background-color: #ffffff;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
        }
        .step-connector {
            background: linear-gradient(90deg, #2b8beb 0%, #2b8beb 50%, transparent 50%, transparent 100%);
            background-size: 20px 1px;
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
    </style>
</head>
<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
<!-- Navigation -->
<?php include 'ProviderNavbar.php'; ?>

<main>
<!-- Hero Section -->
<section class="relative py-20 md:py-24 bg-white overflow-hidden mesh-gradient reveal" data-reveal="section">
<div class="max-w-7xl mx-auto px-10 text-center relative z-10">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">
                    The Clinical Precision Framework
                </div>
<h1 class="font-headline text-[clamp(3rem,6vw,5.5rem)] font-extrabold tracking-[-0.04em] text-on-surface mb-6 leading-[1.1]">Precision Engineered <br/>
<span class="font-editorial italic font-normal text-primary editorial-word inline-block">System Capabilities.</span></h1>
<p class="font-body text-xl md:text-2xl max-w-2xl mx-auto mb-10 leading-relaxed text-on-surface-variant font-medium">
                    Aetheris OS redefines dental management through a clinical lens. Every feature is curated to serve practitioners and patients with uncompromising accuracy.
                </p>
<div class="flex flex-wrap justify-center gap-6">
<a href="ProviderContact.php" class="px-10 py-5 bg-primary text-white font-bold rounded-full shadow-lg shadow-primary/25 hover:shadow-primary/40 transition-all active:scale-95">
                        Request Live Demo
                    </a>
</div>
</div>
</section>

<!-- Features Content -->
<section class="py-16 space-y-24 max-w-7xl mx-auto px-10 reveal" data-reveal="section">
<!-- Feature 1: Appointment Scheduling -->
<div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-20">
<div class="lg:w-1/2 order-2 lg:order-1">
<div class="relative group">
<div class="bg-surface-variant p-8 rounded-[2.5rem] border border-on-surface/5 overflow-hidden transition-all duration-700 group-hover:shadow-[0_40px_80px_-20px_rgba(43,139,235,0.08)]">
<img alt="AI smart scheduling dashboard" class="rounded-2xl shadow-2xl w-full aspect-video object-cover transition-transform duration-700 group-hover:scale-[1.02]" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDl1RL8H3aP1H2gLu52d_-r5jan-Mi994yrkmLfNq9_GCqbx-O78JMUybjSkFf7hF1ceyIr9FkkzS2jnG-OLeV21IJl_Wxq_VT5MGo_QTDs6cGvo1-kzccYtdYs9m4bdtK9Mu0szsn3MVIsiKryw1_GIq4n66xBwy46WHwmXydC_cAGl9KAjcxucMqkGAgJx1aV_vd_Jt8A1X-hr7m57uP0sekl-eXJqAPsSYuVzrFzYfVYQkalMIinayHjBb3YepggQHJVJ1fH_T4"/>
<div class="absolute top-12 right-12 bg-primary text-white text-[10px] font-black px-4 py-1.5 rounded-full uppercase tracking-widest shadow-lg">AI-Powered</div>
</div>
</div>
</div>
<div class="lg:w-1/2 order-1 lg:order-2">
<div class="text-primary font-bold text-xs uppercase tracking-[0.4em] mb-6 flex items-center gap-4">
<span class="w-12 h-[1.5px] bg-primary"></span> Optimization
                    </div>
<h2 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-8">
                        Appointment <br/><span class="text-primary"><span class="font-editorial italic font-normal text-primary editorial-word inline-block">Scheduling</span></span>
</h2>
<p class="text-on-surface-variant text-xl leading-relaxed font-medium mb-10">
                        Our proprietary AI-driven gap prediction engine analyzes historical data to optimize your chair time. It automatically identifies potential no-shows and suggests fills in real-time.
                    </p>
<div class="space-y-4">
<div class="flex items-center gap-4 p-4 rounded-2xl border border-on-surface/5 hover:border-primary/20 transition-all bg-white shadow-sm">
<span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">dynamic_feed</span>
<span class="font-bold text-on-surface">Dynamic queue management</span>
</div>
<div class="flex items-center gap-4 p-4 rounded-2xl border border-on-surface/5 hover:border-primary/20 transition-all bg-white shadow-sm">
<span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">account_tree</span>
<span class="font-bold text-on-surface">Multi-resource coordination</span>
</div>
</div>
</div>
</div>

<!-- Feature 2: Patient Intelligence -->
<div class="flex flex-col lg:flex-row-reverse items-center gap-12 lg:gap-20">
<div class="lg:w-1/2">
<div class="relative group">
<div class="bg-surface-variant p-8 rounded-[2.5rem] border border-on-surface/5 overflow-hidden transition-all duration-700 group-hover:shadow-[0_40px_80px_-20px_rgba(43,139,235,0.08)]">
<img alt="Dental imaging analysis" class="rounded-2xl shadow-2xl w-full aspect-video object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDt3paASEq-UQQDjSRGiZBZ7hVe66UAMo6xIq9h4aGmQQXTFGkLll2XiGwuRci9conA7peAGiNnTgZdfg0X7_XJZeYRTQFDbtGKxbCWIynOVAJR-rWXTMAR6EWmhzOhRpEl5YiuSDq-3wWjtG8WDhvQID2-VmUBXNuZNw5xdZSUBIvWh8pZ9ea1acGmN3eoYDECRpIRd9Y39eMVFvQjHYOKf356PFOFqTQFxjGYZUPBJ8b_S7QfhLh9N_d42-LOspVKMaWKUcVIqmU"/>
</div>
</div>
</div>
<div class="lg:w-1/2">
<div class="text-primary font-bold text-xs uppercase tracking-[0.4em] mb-6 flex items-center gap-4">
<span class="w-12 h-[1.5px] bg-primary"></span> Data Architecture
                    </div>
<h2 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-8">
                        Unified Patient <br/><span class="text-primary"><span class="font-editorial italic font-normal text-primary editorial-word inline-block">Intelligence</span></span>
</h2>
<p class="text-on-surface-variant text-xl leading-relaxed font-medium mb-10">
                        Centralize high-resolution 3D X-rays, treatment plans, and clinical notes in one high-velocity interface. The "Digital Curator" view provides an editorialized look at patient progress.
                    </p>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
<div class="p-6 rounded-[2rem] bg-surface-container-low border border-primary/10 transition-all hover:bg-white hover:shadow-xl group">
<div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-4 group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined">imagesmode</span>
</div>
<p class="text-lg font-bold mb-1">DICOM Ready</p>
<p class="text-sm text-on-surface-variant">Seamless imaging integration</p>
</div>
<div class="p-6 rounded-[2rem] bg-surface-container-low border border-primary/10 transition-all hover:bg-white hover:shadow-xl group">
<div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-4 group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined">encrypted</span>
</div>
<p class="text-lg font-bold mb-1">Secure Vault</p>
<p class="text-sm text-on-surface-variant">HIPAA compliant encryption</p>
</div>
</div>
</div>
</div>

<!-- Feature 3 & 4: Billing & Staff -->
<div class="grid lg:grid-cols-12 gap-8 lg:gap-10">
<div class="lg:col-span-7 bg-white p-10 md:p-12 rounded-[2.5rem] border border-on-surface/5 transition-all duration-700 hover:shadow-2xl relative overflow-hidden group">
<div class="absolute -right-8 -top-8 w-48 h-48 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors"></div>
<div class="relative z-10">
<div class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center mb-6 text-primary">
<span class="material-symbols-outlined text-3xl">payments</span>
</div>
<h2 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-8">Billing &amp; <br/><span class="text-primary"><span class="font-editorial italic font-normal text-primary editorial-word inline-block">Collections</span></span></h2>
<p class="text-on-surface-variant text-lg font-medium mb-8 max-w-md">Fintech-grade tracking ensures no procedure goes unbilled. Integrated payment gateways for instant checkout.</p>
<img alt="Financial dashboard" class="w-full h-72 object-cover rounded-2xl shadow-lg transition-transform group-hover:scale-[1.01]" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDdH09SxKkP3BTr1H_b5xfWro-U3MSLNKHsTsCkIrGzX2zlaJ_xhQy09tTxDRpzWsJumuc73djtIZXd6EBqBUTdHBGo2VERbysG5mxqcwAoLcATIh7m4RV0iUe523jqMMFf3JueLxrxlwj_wTKliv5or9iOqsxBrwxrkYV_fv-c2z51g95rgIwJDYr7gVoQ-JJbU8QOStlFzbqTdiNMEXAwTGgrXSZIJp4bNevxukNZvzH360Y_408xVpMKrCl9tx81klilnqKTVqA"/>
</div>
</div>
<div class="lg:col-span-5 bg-primary p-10 md:p-12 rounded-[2.5rem] shadow-xl text-white relative overflow-hidden flex flex-col">
<div class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none">
<svg class="w-full h-full stroke-white fill-none" viewbox="0 0 100 100">
<circle cx="100" cy="0" r="80" stroke-width="0.5"></circle>
<circle cx="100" cy="0" r="60" stroke-width="0.5"></circle>
</svg>
</div>
<div class="relative z-10">
<div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-6 text-white border border-white/20">
<span class="material-symbols-outlined text-3xl font-light">admin_panel_settings</span>
</div>
<h3 class="font-headline text-4xl font-extrabold mb-6 tracking-tight">Staff Hierarchy</h3>
<p class="text-white/80 text-lg font-medium mb-8">Granular permissions and role-based access control. Define exactly who sees clinical data vs. financial reports.</p>
<div class="space-y-4">
<div class="flex justify-between items-center py-4 border-b border-white/20">
<span class="font-bold">Admin Access</span>
<span class="material-symbols-outlined">toggle_on</span>
</div>
<div class="flex justify-between items-center py-4 border-b border-white/20">
<span class="font-bold">Clinical Only</span>
<span class="material-symbols-outlined">toggle_on</span>
</div>
<div class="flex justify-between items-center py-4">
<span class="font-bold">Front Desk</span>
<span class="material-symbols-outlined">toggle_on</span>
</div>
</div>
</div>
</div>
</div>

<!-- Feature 5 & 6: Insights & Comms -->
<div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-20">
<div class="lg:w-1/2 order-2 lg:order-1">
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
<div class="space-y-6">
<div class="aspect-square rounded-3xl bg-surface-variant flex flex-col justify-end p-8 border border-on-surface/5 group hover:border-primary/30 transition-all">
<span class="material-symbols-outlined text-primary text-4xl mb-4 group-hover:scale-110 transition-transform">bar_chart</span>
<p class="text-xl font-bold">Analytics</p>
</div>
<div class="h-40 rounded-3xl bg-primary-fixed flex flex-col justify-end p-8 transition-all hover:shadow-lg">
<span class="material-symbols-outlined text-primary text-4xl mb-4">campaign</span>
<p class="text-xl font-bold text-on-primary-fixed-variant">Omnichannel</p>
</div>
</div>
<div class="space-y-6 pt-12">
<div class="h-40 rounded-3xl bg-surface-container-low border border-primary/10 flex flex-col justify-end p-8">
<span class="material-symbols-outlined text-primary text-4xl mb-4">mark_email_unread</span>
<p class="text-xl font-bold">Alerts</p>
</div>
<div class="aspect-square rounded-3xl bg-primary text-white flex flex-col justify-end p-8 shadow-xl">
<span class="material-symbols-outlined text-4xl mb-4">dashboard_customize</span>
<p class="text-xl font-bold">Dynamic Reports</p>
</div>
</div>
</div>
</div>
<div class="lg:w-1/2 order-1 lg:order-2">
<div class="text-primary font-bold text-xs uppercase tracking-[0.4em] mb-6 flex items-center gap-4">
<span class="w-12 h-[1.5px] bg-primary"></span> Intelligence
                    </div>
<h2 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-8">
                        Reports &amp; <br/><span class="text-primary"><span class="font-editorial italic font-normal text-primary editorial-word inline-block">Notifications</span></span>
</h2>
<p class="text-on-surface-variant text-xl leading-relaxed font-medium mb-10">
                        Stay ahead of clinic performance with dynamic dashboards. Automate patient reminders via SMS, Email, and Push notifications to virtually eliminate late arrivals.
                    </p>
<div class="flex items-center gap-6 p-6 rounded-[2rem] border border-on-surface/5 bg-white">
<div class="flex -space-x-3">
<div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center border-4 border-white shadow-sm">
<span class="material-symbols-outlined text-xs text-white">sms</span>
</div>
<div class="w-12 h-12 rounded-full bg-primary-fixed flex items-center justify-center border-4 border-white shadow-sm">
<span class="material-symbols-outlined text-xs text-primary">mail</span>
</div>
<div class="w-12 h-12 rounded-full bg-on-surface flex items-center justify-center border-4 border-white shadow-sm">
<span class="material-symbols-outlined text-xs text-white">notifications_active</span>
</div>
</div>
<p class="font-bold text-on-surface">Omnichannel Ready Notification Protocol</p>
</div>
</div>
</div>
</section>

<?php require_once __DIR__ . '/provider_evolve_practice_cta.inc.php'; ?>
</main>

<?php require_once __DIR__ . '/provider_marketing_footer.inc.php'; ?>
<script>
    (function () {
        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var elements = document.querySelectorAll('[data-reveal="section"]');

        if (!elements || !elements.length) return;
        if (prefersReduced || !('IntersectionObserver' in window)) {
            elements.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.01, rootMargin: '0px 0px -5% 0px' });

        elements.forEach(function (el) { observer.observe(el); });
    })();
</script>
</body></html>
<?php exit; ?>

<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Features | Aetheris OS</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "on-surface": "#131c25",
                        "surface": "#ffffff",
                        "surface-variant": "#f7f9ff",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "primary-fixed": "#d4e3ff",
                        "on-primary-fixed-variant": "#004883",
                        "surface-container-low": "#edf4ff",
                        "inverse-surface": "#131c25",

                        /* Existing app colors used by ProviderNavbar */
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"],
                        "inter": ["Inter", "sans-serif"],
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "3xl": "2.5rem", "full": "9999px" },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .mesh-gradient {
            background-color: #ffffff;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.05) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.03) 0px, transparent 50%);
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
    </style>
</head>
<body class="bg-surface font-body text-on-surface">
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
<!-- Navigation -->
<?php include 'ProviderNavbar.php'; ?>
<main class="flex-1">
<!-- Hero Section -->
<section class="relative py-20 md:py-24 bg-white overflow-hidden mesh-gradient">
<div class="max-w-7xl mx-auto px-10 text-center relative z-10">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">
                    The Clinical Precision Framework
                </div>
<h1 class="font-headline text-[clamp(3rem,6vw,5.5rem)] font-extrabold tracking-[-0.04em] text-on-surface mb-6 leading-[1.1]">Precision Engineered <br/>
<span class="font-editorial italic font-normal text-primary editorial-word inline-block">System Capabilities.</span></h1>
<p class="font-body text-xl md:text-2xl max-w-2xl mx-auto mb-10 leading-relaxed text-on-surface-variant font-medium">
                    Aetheris OS redefines dental management through a clinical lens. Every feature is curated to serve practitioners and patients with uncompromising accuracy.
                </p>
<div class="flex flex-wrap justify-center gap-6">
<a href="ProviderContact.php" class="px-10 py-5 bg-primary text-white font-bold rounded-full shadow-lg shadow-primary/25 hover:shadow-primary/40 transition-all active:scale-95">
                        Request Live Demo
                    </a>
</div>
</div>
</section>
<!-- Features Content -->
<section class="py-16 space-y-24 max-w-7xl mx-auto px-10">
<!-- Feature 1: Appointment Scheduling -->
<div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-20">
<div class="lg:w-1/2 order-2 lg:order-1">
<div class="relative group">
<div class="bg-surface-variant p-8 rounded-[2.5rem] border border-on-surface/5 overflow-hidden transition-all duration-700 group-hover:shadow-[0_40px_80px_-20px_rgba(43,139,235,0.08)]">
<img alt="AI smart scheduling dashboard" class="rounded-2xl shadow-2xl w-full transition-transform duration-700 group-hover:scale-[1.02]" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDl1RL8H3aP1H2gLu52d_-r5jan-Mi994yrkmLfNq9_GCqbx-O78JMUybjSkFf7hF1ceyIr9FkkzS2jnG-OLeV21IJl_Wxq_VT5MGo_QTDs6cGvo1-kzccYtdYs9m4bdtK9Mu0szsn3MVIsiKryw1_GIq4n66xBwy46WHwmXydC_cAGl9KAjcxucMqkGAgJx1aV_vd_Jt8A1X-hr7m57uP0sekl-eXJqAPsSYuVzrFzYfVYQkalMIinayHjBb3YepggQHJVJ1fH_T4"/>
<div class="absolute top-12 right-12 bg-primary text-white text-[10px] font-black px-4 py-1.5 rounded-full uppercase tracking-widest shadow-lg">AI-Powered</div>
</div>
</div>
</div>
<div class="lg:w-1/2 order-1 lg:order-2">
<div class="text-primary font-bold text-xs uppercase tracking-[0.4em] mb-6 flex items-center gap-4">
<span class="w-12 h-[1.5px] bg-primary"></span> Optimization
                    </div>
<h2 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-8">
                        Appointment <br/><span class="text-primary"><span class="font-editorial italic font-normal text-primary editorial-word inline-block">Scheduling</span></span>
</h2>
<p class="text-on-surface-variant text-xl leading-relaxed font-medium mb-10">
                        Our proprietary AI-driven gap prediction engine analyzes historical data to optimize your chair time. It automatically identifies potential no-shows and suggests fills in real-time.
                    </p>
<div class="space-y-4">
<div class="flex items-center gap-4 p-4 rounded-2xl border border-on-surface/5 hover:border-primary/20 transition-all bg-white shadow-sm">
<span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">dynamic_feed</span>
<span class="font-bold text-on-surface">Dynamic queue management</span>
</div>
<div class="flex items-center gap-4 p-4 rounded-2xl border border-on-surface/5 hover:border-primary/20 transition-all bg-white shadow-sm">
<span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">account_tree</span>
<span class="font-bold text-on-surface">Multi-resource coordination</span>
</div>
</div>
</div>
</div>
<!-- Feature 2: Patient Intelligence -->
<div class="flex flex-col lg:flex-row-reverse items-center gap-12 lg:gap-20">
<div class="lg:w-1/2">
<div class="relative group">
<div class="bg-surface-variant p-8 rounded-[2.5rem] border border-on-surface/5 overflow-hidden transition-all duration-700 group-hover:shadow-[0_40px_80px_-20px_rgba(43,139,235,0.08)]">
<img alt="Dental imaging analysis" class="rounded-2xl shadow-2xl w-full" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDt3paASEq-UQQDjSRGiZBZ7hVe66UAMo6xIq9h4aGmQQXTFGkLll2XiGwuRci9conA7peAGiNnTgZdfg0X7_XJZeYRTQFDbtGKxbCWIynOVAJR-rWXTMAR6EWmhzOhRpEl5YiuSDq-3wWjtG8WDhvQID2-VmUBXNuZNw5xdZSUBIvWh8pZ9ea1acGmN3eoYDECRpIRd9Y39eMVFvQjHYOKf356PFOFqTQFxjGYZUPBJ8b_S7QfhLh9N_d42-LOspVKMaWKUcVIqmU"/>
</div>
</div>
</div>
<div class="lg:w-1/2">
<div class="text-primary font-bold text-xs uppercase tracking-[0.4em] mb-6 flex items-center gap-4">
<span class="w-12 h-[1.5px] bg-primary"></span> Data Architecture
                    </div>
<h2 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-8">
                        Unified Patient <br/><span class="text-primary"><span class="font-editorial italic font-normal text-primary editorial-word inline-block">Intelligence</span></span>
</h2>
<p class="text-on-surface-variant text-xl leading-relaxed font-medium mb-10">
                        Centralize high-resolution 3D X-rays, treatment plans, and clinical notes in one high-velocity interface. The "Digital Curator" view provides an editorialized look at patient progress.
                    </p>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
<div class="p-6 rounded-[2rem] bg-surface-container-low border border-primary/10 transition-all hover:bg-white hover:shadow-xl group">
<div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-4 group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined">imagesmode</span>
</div>
<p class="text-lg font-bold mb-1">DICOM Ready</p>
<p class="text-sm text-on-surface-variant">Seamless imaging integration</p>
</div>
<div class="p-6 rounded-[2rem] bg-surface-container-low border border-primary/10 transition-all hover:bg-white hover:shadow-xl group">
<div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-4 group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined">encrypted</span>
</div>
<p class="text-lg font-bold mb-1">Secure Vault</p>
<p class="text-sm text-on-surface-variant">HIPAA compliant encryption</p>
</div>
</div>
</div>
</div>
<!-- Feature 3 & 4: Billing & Staff -->
<div class="grid lg:grid-cols-12 gap-8 lg:gap-10">
<div class="lg:col-span-7 bg-white p-10 md:p-12 rounded-[2.5rem] border border-on-surface/5 transition-all duration-700 hover:shadow-2xl relative overflow-hidden group">
<div class="absolute -right-8 -top-8 w-48 h-48 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-colors"></div>
<div class="relative z-10">
<div class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center mb-6 text-primary">
<span class="material-symbols-outlined text-3xl">payments</span>
</div>
<h2 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-8">Billing &amp; <br/><span class="text-primary"><span class="font-editorial italic font-normal text-primary editorial-word inline-block">Collections</span></span></h2>
<p class="text-on-surface-variant text-lg font-medium mb-8 max-w-md">Fintech-grade tracking ensures no procedure goes unbilled. Integrated payment gateways for instant checkout.</p>
<img alt="Financial dashboard" class="w-full h-72 object-cover rounded-2xl shadow-lg transition-transform group-hover:scale-[1.01]" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDdH09SxKkP3BTr1H_b5xfWro-U3MSLNKHsTsCkIrGzX2zlaJ_xhQy09tTxDRpzWsJumuc73djtIZXd6EBqBUTdHBGo2VERbysG5mxqcwAoLcATIh7m4RV0iUe523jqMMFf3JueLxrxlwj_wTKliv5or9iOqsxBrwxrkYV_fv-c2z51g95rgIwJDYr7gVoQ-JJbU8QOStlFzbqTdiNMEXAwTGgrXSZIJp4bNevxukNZvzH360Y_408xVpMKrCl9tx81klilnqKTVqA"/>
</div>
</div>
<div class="lg:col-span-5 bg-primary p-10 md:p-12 rounded-[2.5rem] shadow-xl text-white relative overflow-hidden flex flex-col">
<div class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none">
<svg class="w-full h-full stroke-white fill-none" viewbox="0 0 100 100">
<circle cx="100" cy="0" r="80" stroke-width="0.5"></circle>
<circle cx="100" cy="0" r="60" stroke-width="0.5"></circle>
</svg>
</div>
<div class="relative z-10">
<div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-6 text-white border border-white/20">
<span class="material-symbols-outlined text-3xl font-light">admin_panel_settings</span>
</div>
<h3 class="font-headline text-4xl font-extrabold mb-6 tracking-tight">Staff Hierarchy</h3>
<p class="text-white/80 text-lg font-medium mb-8">Granular permissions and role-based access control. Define exactly who sees clinical data vs. financial reports.</p>
<div class="space-y-4">
<div class="flex justify-between items-center py-4 border-b border-white/20">
<span class="font-bold">Admin Access</span>
<span class="material-symbols-outlined">toggle_on</span>
</div>
<div class="flex justify-between items-center py-4 border-b border-white/20">
<span class="font-bold">Clinical Only</span>
<span class="material-symbols-outlined">toggle_on</span>
</div>
<div class="flex justify-between items-center py-4">
<span class="font-bold">Front Desk</span>
<span class="material-symbols-outlined">toggle_on</span>
</div>
</div>
</div>
</div>
</div>
<!-- Feature 5 & 6: Insights & Comms -->
<div class="flex flex-col lg:flex-row items-center gap-12 lg:gap-20">
<div class="lg:w-1/2 order-2 lg:order-1">
<div class="grid grid-cols-2 gap-6">
<div class="space-y-6">
<div class="aspect-square rounded-3xl bg-surface-variant flex flex-col justify-end p-8 border border-on-surface/5 group hover:border-primary/30 transition-all">
<span class="material-symbols-outlined text-primary text-4xl mb-4 group-hover:scale-110 transition-transform">bar_chart</span>
<p class="text-xl font-bold">Analytics</p>
</div>
<div class="h-40 rounded-3xl bg-primary-fixed flex flex-col justify-end p-8 transition-all hover:shadow-lg">
<span class="material-symbols-outlined text-primary text-4xl mb-4">campaign</span>
<p class="text-xl font-bold text-on-primary-fixed-variant">Omnichannel</p>
</div>
</div>
<div class="space-y-6 pt-12">
<div class="h-40 rounded-3xl bg-surface-container-low border border-primary/10 flex flex-col justify-end p-8">
<span class="material-symbols-outlined text-primary text-4xl mb-4">mark_email_unread</span>
<p class="text-xl font-bold">Alerts</p>
</div>
<div class="aspect-square rounded-3xl bg-primary text-white flex flex-col justify-end p-8 shadow-xl">
<span class="material-symbols-outlined text-4xl mb-4">dashboard_customize</span>
<p class="text-xl font-bold">Dynamic Reports</p>
</div>
</div>
</div>
</div>
<div class="lg:w-1/2 order-1 lg:order-2">
<div class="text-primary font-bold text-xs uppercase tracking-[0.4em] mb-6 flex items-center gap-4">
<span class="w-12 h-[1.5px] bg-primary"></span> Intelligence
                    </div>
<h2 class="font-headline text-5xl md:text-6xl font-extrabold tracking-tighter leading-tight mb-8">
                        Reports &amp; <br/><span class="text-primary"><span class="font-editorial italic font-normal text-primary editorial-word inline-block">Notifications</span></span>
</h2>
<p class="text-on-surface-variant text-xl leading-relaxed font-medium mb-10">
                        Stay ahead of clinic performance with dynamic dashboards. Automate patient reminders via SMS, Email, and Push notifications to virtually eliminate late arrivals.
                    </p>
<div class="flex items-center gap-6 p-6 rounded-[2rem] border border-on-surface/5 bg-white">
<div class="flex -space-x-3">
<div class="w-12 h-12 rounded-full bg-primary flex items-center justify-center border-4 border-white shadow-sm">
<span class="material-symbols-outlined text-xs text-white">sms</span>
</div>
<div class="w-12 h-12 rounded-full bg-primary-fixed flex items-center justify-center border-4 border-white shadow-sm">
<span class="material-symbols-outlined text-xs text-primary">mail</span>
</div>
<div class="w-12 h-12 rounded-full bg-on-surface flex items-center justify-center border-4 border-white shadow-sm">
<span class="material-symbols-outlined text-xs text-white">notifications_active</span>
</div>
</div>
<p class="font-bold text-on-surface">Omnichannel Ready Notification Protocol</p>
</div>
</div>
</div>
</section>
<!-- Final CTA Section -->
<section class="py-16 px-10">
<div class="mx-auto rounded-[3rem] md:rounded-[4rem] bg-primary relative overflow-hidden flex flex-col items-center text-center shadow-[0_40px_100px_-20px_rgba(43,139,235,0.4)] max-w-6xl py-16 px-10 md:px-20">
<div class="relative z-10 max-w-3xl">
<div class="inline-block px-4 py-1 rounded-full bg-white/20 text-white text-[10px] font-black uppercase tracking-[0.3em] mb-8">
                        Institutional Scale
                    </div>
<h2 class="font-headline text-5xl font-extrabold text-white tracking-tighter leading-[0.85] md:text-6xl mb-8">Ready to scale your clinic?</h2>
<p class="text-white/70 text-xl md:text-2xl max-w-xl mx-auto leading-relaxed mb-10">Join over 500+ practices worldwide using Aetheris OS to streamline operations and enhance patient care.</p>
<a href="Provider-Plans.php" class="bg-white text-primary px-16 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-2xl active:scale-95 inline-block">
                        Explore Pricing Plans
                    </a>
</div>
<!-- Abstract Architectural Accents -->
<div class="absolute top-0 right-0 w-1/3 h-full border-l border-white/10 pointer-events-none"></div>
<div class="absolute bottom-0 left-0 w-full h-1/4 border-t border-white/10 pointer-events-none"></div>
<div class="absolute -right-20 -bottom-20 w-80 h-80 bg-white/5 rounded-full blur-3xl"></div>
</div>
</section>
</main>
<?php require_once __DIR__ . '/provider_marketing_footer.inc.php'; ?>
</div>
</body></html>

<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Features - MyDental Practice Management</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8cee",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                    },
                    fontFamily: {
                        "display": ["Manrope", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100">
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
<!-- Navigation -->
<?php include 'ProviderNavbar.php'; ?>
<main class="flex-1">
<!-- Hero Section -->
<section class="px-6 lg:px-40 py-16 lg:py-24 bg-gradient-to-b from-primary/5 to-transparent">
<div class="mx-auto max-w-[1200px] text-center">
<span class="inline-block px-4 py-1.5 mb-6 text-xs font-bold tracking-widest uppercase rounded-full bg-primary/10 text-primary">Platform Features</span>
<h1 class="text-4xl lg:text-6xl font-black tracking-tight text-slate-900 dark:text-slate-50 mb-6">
                        The Operating System for <br/><span class="text-primary">Modern Dentistry</span>
</h1>
<p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto mb-10">
                        Everything you need to run a high-performance dental clinic, from clinical charting to automated patient marketing.
                    </p>
<div class="flex flex-col sm:flex-row justify-center gap-4">
<button class="px-8 py-4 bg-primary text-white rounded-xl font-bold text-lg shadow-xl shadow-primary/20">Explore All Features</button>
<button class="px-8 py-4 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100 rounded-xl font-bold text-lg">Watch Demo</button>
</div>
</div>
</section>
<!-- Clinic Management -->
<section class="px-6 lg:px-40 py-20">
<div class="mx-auto max-w-[1200px]">
<div class="grid lg:grid-cols-2 gap-16 items-center">
<div class="relative aspect-video rounded-2xl overflow-hidden shadow-2xl bg-slate-200" data-alt="Modern dental clinic office interior setup">
<img alt="Clinic Management" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuC9wBgIIbMnO6L_S_y67nZoE5sxC3XrP_wlxvt6y63wj1LjuFdJ-uo06OVBRv7JBXaDHr94C4ymCY6RHMW6t2eCkaJmGkxlwwKmH9JHBFWj4armrFkSMpO9T9ytBREuMmV9hQbs-2vRtHCEaJ5fiRlxXAncr4TcMelpbWv1_VS0pqH2io36vn-a9ec3tVGEiEFjxqvmre-gcF7AUyB-kuGGT6Fk9KVhXYOP4O0uGBMBypFP7iUg-jPI5_P0dNOAzsfREBNN6vEvTSI"/>
<div class="absolute inset-0 bg-primary/10"></div>
</div>
<div>
<div class="flex items-center gap-3 text-primary mb-4">
<span class="material-symbols-outlined">domain</span>
<span class="font-bold tracking-wider uppercase text-sm">Operations</span>
</div>
<h2 class="text-3xl font-bold mb-6 text-slate-900 dark:text-slate-50">Clinic Management</h2>
<p class="text-slate-600 dark:text-slate-400 mb-8">Centralize your practice operations with a robust infrastructure designed for scale and efficiency.</p>
<div class="grid sm:grid-cols-2 gap-6">
<div class="flex gap-4">
<span class="material-symbols-outlined text-primary">inventory_2</span>
<div>
<h4 class="font-bold text-slate-900 dark:text-slate-100">Inventory Tracking</h4>
<p class="text-sm text-slate-500">Automated stock alerts and supply management.</p>
</div>
</div>
<div class="flex gap-4">
<span class="material-symbols-outlined text-primary">account_balance_wallet</span>
<div>
<h4 class="font-bold text-slate-900 dark:text-slate-100">Billing &amp; Invoicing</h4>
<p class="text-sm text-slate-500">Integrated payment processing and insurance claims.</p>
</div>
</div>
<div class="flex gap-4">
<span class="material-symbols-outlined text-primary">description</span>
<div>
<h4 class="font-bold text-slate-900 dark:text-slate-100">Document Vault</h4>
<p class="text-sm text-slate-500">Secure storage for HIPAA-compliant documentation.</p>
</div>
</div>
<div class="flex gap-4">
<span class="material-symbols-outlined text-primary">settings_suggest</span>
<div>
<h4 class="font-bold text-slate-900 dark:text-slate-100">Workflow Automation</h4>
<p class="text-sm text-slate-500">Custom triggers for recurring administrative tasks.</p>
</div>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Appointment Management -->
<section class="px-6 lg:px-40 py-20 bg-slate-100 dark:bg-slate-900/50">
<div class="mx-auto max-w-[1200px]">
<div class="text-center max-w-3xl mx-auto mb-16">
<h2 class="text-3xl font-bold mb-4">Appointment Management</h2>
<p class="text-slate-600 dark:text-slate-400">Maximize your chair utilization with smart scheduling tools and automated patient communications.</p>
</div>
<div class="grid md:grid-cols-3 gap-8">
<div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
<div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
<span class="material-symbols-outlined">calendar_month</span>
</div>
<h3 class="text-xl font-bold mb-3">Online Booking</h3>
<p class="text-slate-500 text-sm leading-relaxed mb-4">Patient-facing scheduling portal that syncs in real-time with your clinic's availability.</p>
<ul class="space-y-2 text-sm font-medium text-slate-600 dark:text-slate-400">
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Website Integration</li>
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Treatment-specific Slots</li>
</ul>
</div>
<div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
<div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
<span class="material-symbols-outlined">notifications_active</span>
</div>
<h3 class="text-xl font-bold mb-3">Smart Reminders</h3>
<p class="text-slate-500 text-sm leading-relaxed mb-4">Reduce no-shows by 40% with automated SMS and email confirmations and reminders.</p>
<ul class="space-y-2 text-sm font-medium text-slate-600 dark:text-slate-400">
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> 2-Way SMS Chat</li>
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Custom Templates</li>
</ul>
</div>
<div class="bg-white dark:bg-slate-800 p-8 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700">
<div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary mb-6">
<span class="material-symbols-outlined">dynamic_feed</span>
</div>
<h3 class="text-xl font-bold mb-3">Waitlist Management</h3>
<p class="text-slate-500 text-sm leading-relaxed mb-4">Instantly fill last-minute cancellations with automated waitlist notifications.</p>
<ul class="space-y-2 text-sm font-medium text-slate-600 dark:text-slate-400">
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Priority Ranking</li>
<li class="flex items-center gap-2"><span class="material-symbols-outlined text-xs text-primary">check_circle</span> Auto-filling slots</li>
</ul>
</div>
</div>
</div>
</section>
<!-- Patient Management -->
<section class="px-6 lg:px-40 py-20">
<div class="mx-auto max-w-[1200px]">
<div class="grid lg:grid-cols-2 gap-16 items-center">
<div class="order-2 lg:order-1">
<h2 class="text-3xl font-bold mb-6">Patient Management</h2>
<p class="text-slate-600 dark:text-slate-400 mb-8">Build stronger relationships with a 360-degree view of every patient’s clinical and financial history.</p>
<div class="space-y-4">
<div class="p-4 bg-primary/5 rounded-xl border-l-4 border-primary">
<h4 class="font-bold mb-1">Interactive Clinical Charting</h4>
<p class="text-sm text-slate-500">Visual 3D tooth charting with treatment phase tracking.</p>
</div>
<div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
<h4 class="font-bold mb-1">Digital Patient Onboarding</h4>
<p class="text-sm text-slate-500">Contactless intake forms and medical history updates via mobile.</p>
</div>
<div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
<h4 class="font-bold mb-1">Patient Loyalty Portal</h4>
<p class="text-sm text-slate-500">Patients can view X-rays, treatment plans, and pay bills online.</p>
</div>
</div>
</div>
<div class="order-1 lg:order-2 bg-slate-100 rounded-2xl p-8 flex items-center justify-center min-h-[400px]" data-alt="Screenshot of a patient digital health record and charting interface">
<div class="w-full h-64 bg-white dark:bg-slate-800 rounded-lg shadow-xl flex flex-col p-4">
<div class="flex items-center gap-3 border-b pb-3 mb-3">
<div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center text-primary font-bold">JD</div>
<div>
<div class="font-bold text-sm">John Doe</div>
<div class="text-xs text-slate-400">Patient ID: #8829</div>
</div>
</div>
<div class="grid grid-cols-2 gap-4 flex-1">
<div class="bg-slate-50 dark:bg-slate-900 rounded p-2">
<div class="text-[10px] uppercase font-bold text-slate-400 mb-1">Last Visit</div>
<div class="text-xs font-bold">Oct 12, 2023</div>
</div>
<div class="bg-slate-50 dark:bg-slate-900 rounded p-2">
<div class="text-[10px] uppercase font-bold text-slate-400 mb-1">Treatment Plan</div>
<div class="text-xs font-bold text-primary">In Progress</div>
</div>
</div>
<div class="mt-4 flex gap-2">
<div class="flex-1 h-2 bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden"><div class="w-2/3 h-full bg-primary"></div></div>
<span class="text-[10px] font-bold">66% Complete</span>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Staff and Dentist Management -->
<section class="px-6 lg:px-40 py-20 bg-background-dark text-slate-100">
<div class="mx-auto max-w-[1200px]">
<div class="flex flex-col lg:flex-row gap-12 items-end mb-16">
<div class="flex-1">
<h2 class="text-3xl font-bold mb-4">Staff &amp; Provider Management</h2>
<p class="text-slate-400">Manage schedules, commissions, and access rights for your entire clinical team from one dashboard.</p>
</div>
<div class="flex gap-4">
<div class="text-center px-6 py-4 bg-slate-800 rounded-xl">
<div class="text-2xl font-bold text-primary">99.9%</div>
<div class="text-xs uppercase tracking-widest text-slate-500">Uptime</div>
</div>
<div class="text-center px-6 py-4 bg-slate-800 rounded-xl">
<div class="text-2xl font-bold text-primary">HIPAA</div>
<div class="text-xs uppercase tracking-widest text-slate-500">Compliant</div>
</div>
</div>
</div>
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
<div class="p-6 border border-slate-700 rounded-xl hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-primary mb-4">badge</span>
<h4 class="font-bold mb-2">Provider Credentialing</h4>
<p class="text-sm text-slate-400">Store and track licenses, certifications, and malpractice insurance.</p>
</div>
<div class="p-6 border border-slate-700 rounded-xl hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-primary mb-4">monitoring</span>
<h4 class="font-bold mb-2">Commission Tracking</h4>
<p class="text-sm text-slate-400">Automatically calculate dentist and hygienist pay based on production or collections.</p>
</div>
<div class="p-6 border border-slate-700 rounded-xl hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-primary mb-4">lock_person</span>
<h4 class="font-bold mb-2">Granular Permissions</h4>
<p class="text-sm text-slate-400">Control feature access per role to ensure data security and compliance.</p>
</div>
<div class="p-6 border border-slate-700 rounded-xl hover:bg-slate-800 transition-colors">
<span class="material-symbols-outlined text-primary mb-4">history</span>
<h4 class="font-bold mb-2">Audit Logs</h4>
<p class="text-sm text-slate-400">Detailed logs of every action taken within the system for accountability.</p>
</div>
</div>
</div>
</section>
<!-- Reporting and Monitoring -->
<section class="px-6 lg:px-40 py-20">
<div class="mx-auto max-w-[1200px]">
<div class="flex flex-col lg:flex-row gap-16">
<div class="w-full lg:w-1/3">
<h2 class="text-3xl font-bold mb-6">Reporting &amp; Analytics</h2>
<p class="text-slate-600 dark:text-slate-400 mb-8">Data-driven insights to help you optimize clinic performance and patient outcomes.</p>
<ul class="space-y-6">
<li class="flex gap-4">
<div class="flex-shrink-0 w-6 h-6 rounded bg-primary/20 text-primary flex items-center justify-center"><span class="material-symbols-outlined text-sm">trending_up</span></div>
<p class="text-sm"><span class="font-bold block">Production Reports</span> Track daily, monthly, and yearly production vs. goals.</p>
</li>
<li class="flex gap-4">
<div class="flex-shrink-0 w-6 h-6 rounded bg-primary/20 text-primary flex items-center justify-center"><span class="material-symbols-outlined text-sm">pie_chart</span></div>
<p class="text-sm"><span class="font-bold block">Collection Analysis</span> Visualize aging accounts and collection efficiency ratios.</p>
</li>
<li class="flex gap-4">
<div class="flex-shrink-0 w-6 h-6 rounded bg-primary/20 text-primary flex items-center justify-center"><span class="material-symbols-outlined text-sm">groups</span></div>
<p class="text-sm"><span class="font-bold block">Referral Tracking</span> Measure the ROI of your marketing campaigns and referral sources.</p>
</li>
</ul>
</div>
<div class="flex-1 bg-slate-50 dark:bg-slate-800 p-8 rounded-3xl" data-alt="Colorful business analytics dashboard showing clinic performance charts">
<div class="h-full w-full min-h-[300px] bg-white dark:bg-slate-900 rounded-2xl shadow-inner p-6 flex flex-col gap-6">
<div class="flex justify-between items-center">
<h4 class="font-bold">Monthly Revenue Overview</h4>
<div class="flex gap-2">
<div class="w-24 h-8 bg-slate-100 dark:bg-slate-800 rounded"></div>
<div class="w-24 h-8 bg-slate-100 dark:bg-slate-800 rounded"></div>
</div>
</div>
<div class="flex-1 flex items-end gap-3 pb-4">
<div class="flex-1 bg-primary/20 rounded-t h-[40%]"></div>
<div class="flex-1 bg-primary/40 rounded-t h-[60%]"></div>
<div class="flex-1 bg-primary/30 rounded-t h-[45%]"></div>
<div class="flex-1 bg-primary/60 rounded-t h-[80%]"></div>
<div class="flex-1 bg-primary/80 rounded-t h-[70%]"></div>
<div class="flex-1 bg-primary rounded-t h-[95%]"></div>
</div>
<div class="grid grid-cols-3 gap-4 border-t pt-4">
<div class="text-center"><div class="text-xs text-slate-400">Production</div><div class="font-bold">$124,500</div></div>
<div class="text-center"><div class="text-xs text-slate-400">Collections</div><div class="font-bold">$118,200</div></div>
<div class="text-center"><div class="text-xs text-slate-400">Adjustment</div><div class="font-bold">-$6,300</div></div>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Multi-Tenant System -->
<section class="px-6 lg:px-40 py-20 bg-primary/5">
<div class="mx-auto max-w-[1200px]">
<div class="bg-white dark:bg-slate-800 rounded-[2.5rem] p-8 lg:p-16 shadow-xl border border-slate-200 dark:border-slate-700">
<div class="grid lg:grid-cols-2 gap-12 items-center">
<div>
<h2 class="text-3xl font-bold mb-6">Multi-Tenant Architecture</h2>
<p class="text-slate-600 dark:text-slate-400 mb-8">Designed for Solo Practices and Large DSOs alike. Manage multiple locations from a single master account with ease.</p>
<div class="space-y-6">
<div class="flex gap-4">
<div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">1</div>
<div>
<h4 class="font-bold">Global Reporting</h4>
<p class="text-sm text-slate-500">Roll up data from all clinics into a single corporate-level dashboard.</p>
</div>
</div>
<div class="flex gap-4">
<div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">2</div>
<div>
<h4 class="font-bold">Centralized Marketing</h4>
<p class="text-sm text-slate-500">Deploy brand-wide campaigns and communication templates across all branches.</p>
</div>
</div>
<div class="flex gap-4">
<div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center flex-shrink-0 font-bold">3</div>
<div>
<h4 class="font-bold">Standardized Protocols</h4>
<p class="text-sm text-slate-500">Maintain clinical standards with unified treatment plan templates and procedures.</p>
</div>
</div>
</div>
</div>
<div class="relative bg-slate-100 dark:bg-slate-900 rounded-2xl p-6 min-h-[360px]" data-alt="Abstract network diagram showing multiple clinic locations connected to a central hub">
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-24 h-24 bg-primary rounded-2xl shadow-2xl flex items-center justify-center text-white">
<span class="material-symbols-outlined text-4xl">hub</span>
</div>
<div class="absolute top-10 left-10 w-16 h-16 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-primary/20 flex items-center justify-center text-primary">
<span class="material-symbols-outlined">apartment</span>
</div>
<div class="absolute top-10 right-10 w-16 h-16 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-primary/20 flex items-center justify-center text-primary">
<span class="material-symbols-outlined">apartment</span>
</div>
<div class="absolute bottom-10 left-10 w-16 h-16 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-primary/20 flex items-center justify-center text-primary">
<span class="material-symbols-outlined">apartment</span>
</div>
<div class="absolute bottom-10 right-10 w-16 h-16 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-primary/20 flex items-center justify-center text-primary">
<span class="material-symbols-outlined">apartment</span>
</div>
<!-- Connecting lines visualized as dashed SVG paths -->
<svg class="absolute inset-0 w-full h-full opacity-20" viewbox="0 0 100 100">
<line stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" x1="20" x2="50" y1="20" y2="50"></line>
<line stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" x1="80" x2="50" y1="20" y2="50"></line>
<line stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" x1="20" x2="50" y1="80" y2="50"></line>
<line stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" x1="80" x2="50" y1="80" y2="50"></line>
</svg>
</div>
</div>
</div>
</div>
</section>
<!-- CTA Section -->
<section class="px-6 lg:px-40 py-24 text-center">
<div class="mx-auto max-w-[800px]">
<h2 class="text-4xl font-bold mb-6">Ready to Modernize Your Practice?</h2>
<p class="text-lg text-slate-600 dark:text-slate-400 mb-10">Join 5,000+ dental professionals who trust MyDental for their daily operations.</p>
<div class="flex flex-col sm:flex-row justify-center gap-4">
<button class="px-10 py-4 bg-primary text-white rounded-xl font-bold text-lg hover:scale-105 transition-transform">Get Started Free</button>
<button class="px-10 py-4 border border-slate-300 dark:border-slate-700 rounded-xl font-bold text-lg">Contact Sales</button>
</div>
</div>
</section>
</main>
<?php require_once __DIR__ . '/provider_marketing_footer.inc.php'; ?>
</div>
</body></html>