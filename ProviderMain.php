<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Dental | Dental Clinic Management OS</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@1,400;1,700&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
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

                        /* Keep existing app colors used by ProviderNavbar */
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
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

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-14px);
            }
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

            .slow-float {
                animation: none;
            }
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

        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }

        .step-connector {
            background: linear-gradient(90deg, #2b8beb 0%, #2b8beb 50%, transparent 50%, transparent 100%);
            background-size: 20px 1px;
        }

        /* Provider landing hero: full-width text over Banner1.svg (no extra overlays) */
        .provider-hero {
            /* Taller banner so more of Banner1.svg is visible without crowding the subject */
            min-height: clamp(36rem, 90vh, 64rem);
            display: flex;
            align-items: center;
        }

        .provider-hero__bg {
            position: absolute;
            inset: 0;
            z-index: 0;
            background-color: #f7f9ff;
            background-image: url('Banner1.svg');
            background-repeat: no-repeat;
            background-size: cover;
            /* Anchor toward top of artwork so heads / upper scene stay in frame (cover crops bottom first) */
            background-position: 72% top;
        }

        @media (max-width: 1023px) {
            .provider-hero__bg {
                background-position: 60% top;
            }
        }

        @media (max-width: 639px) {
            .provider-hero {
                min-height: clamp(30rem, 82vh, 56rem);
            }

            .provider-hero__bg {
                background-position: 55% top;
            }
        }
    </style>
</head>

<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
    <!-- Navigation (preserve existing login functionality) -->
    <?php include 'ProviderNavbar.php'; ?>
    <main>
        <!-- Hero: full-width banner (text over Banner1.svg) -->
        <section class="provider-hero relative w-full overflow-hidden reveal" data-reveal="section">
            <div class="provider-hero__bg" aria-hidden="true"></div>
            <div
                class="relative z-10 w-full max-w-[1800px] mx-auto px-5 sm:px-8 lg:px-14 xl:px-20 py-14 sm:py-16 lg:py-24">
                <div class="max-w-xl lg:max-w-2xl xl:max-w-[40rem]">
                    <h1
                        class="font-headline text-[clamp(2.25rem,6.2vw,4.75rem)] font-extrabold tracking-[-0.045em] text-on-surface mb-6 sm:mb-8 leading-[0.92]">
                        <span class="block">Modernize Your</span>
                        <span class="relative block">
                            <span
                                class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Practice.</span>
                        </span>
                        <span
                            class="block text-[clamp(1.05rem,2.4vw,1.85rem)] font-headline font-semibold tracking-tight text-on-surface/85 mt-2 sm:mt-3">
                            with <span
                                class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">My
                                Dental</span>
                        </span>
                    </h1>
                    <p
                        class="font-body text-base sm:text-lg max-w-xl mb-9 sm:mb-10 leading-relaxed text-on-surface-variant font-medium">
                        The My Dental OS: a unified dental management suite designed for efficiency, architectural
                        precision, and multi-tenant clinic scaling.
                    </p>
                    <div>
                        <a href="Provider-Plans.php"
                            class="group relative inline-flex px-9 sm:px-10 py-4 sm:py-5 bg-primary text-white font-bold rounded-full overflow-hidden transition-all hover:pr-14 active:scale-95 text-center transform-gpu focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 w-full max-w-xs sm:w-auto sm:max-w-none">
                            <span class="relative z-10">View Your Plans</span>
                            <span
                                class="material-symbols-outlined absolute right-4 opacity-0 group-hover:opacity-100 transition-all">arrow_right_alt</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <!-- System Features: Clinical Intelligence -->
        <section class="py-20 px-6 sm:px-8 lg:px-10 bg-white relative overflow-hidden reveal" data-reveal="section"
            id="features">
            <div class="max-w-[1800px] mx-auto">
                <div class="flex flex-col lg:flex-row justify-between items-start mb-20 gap-12">
                    <div class="max-w-3xl">
                        <div
                            class="text-primary font-bold text-xs uppercase mb-6 flex items-center gap-4 tracking-[0.3em]">
                            <span class="w-12 h-[1.5px] bg-primary"></span> System Capability
                        </div>
                        <h2
                            class="font-headline text-4xl md:text-6xl font-extrabold tracking-tighter leading-[0.95] mb-6">
                            Clinical <br /> <span
                                class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Intelligence</span>
                        </h2>
                        <p class="text-on-surface-variant text-xl leading-relaxed max-w-xl font-medium">
                            Sophisticated architectural tooling designed for high-performance healthcare systems.
                        </p>
                    </div>
                    <div class="relative hidden lg:block pr-20">
                        <span
                            class="text-[16rem] font-headline font-black text-primary/[0.03] leading-none tracking-tighter absolute -right-20 -top-24 select-none slow-float">CI</span>
                    </div>
                </div>
                <!-- Staggered Card Layout -->
                <div class="grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-10">
                    <!-- Feature 1: Staggered Up -->
                    <div class="md:col-span-5 lg:col-span-4 md:mt-24">
                        <div
                            class="group h-full bg-white p-12 rounded-[2.5rem] border border-on-surface/5 transition-all duration-500 transform-gpu hover:-translate-y-1 hover:bg-primary hover:border-primary/20 hover:shadow-[0_50px_100px_-20px_rgba(43,139,235,0.22)] relative overflow-hidden">
                            <div
                                class="absolute -right-8 -top-8 w-32 h-32 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors">
                            </div>
                            <div
                                class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center mb-10 text-primary transition-all duration-500 group-hover:scale-110 group-hover:bg-white/10 group-hover:text-white group-hover:border group-hover:border-white/20">
                                <span class="material-symbols-outlined text-3xl font-light">monitoring</span>
                            </div>
                            <h3
                                class="font-headline text-3xl font-extrabold mb-6 tracking-tight transition-colors group-hover:text-white">
                                Tenant Monitoring</h3>
                            <p
                                class="text-on-surface-variant text-lg leading-relaxed font-medium mb-8 transition-colors group-hover:text-white/80">
                                Real-time oversight across multiple clinic branches. Monitor chair occupancy, staff
                                performance, and inventory health instantly.</p>
                            <div
                                class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-widest tracking-[0.3em] transition-colors group-hover:text-white">
                                <span
                                    class="w-8 h-[1px] bg-primary/30 transition-colors group-hover:bg-white/30"></span>
                                Live Node Access
                            </div>
                        </div>
                    </div>
                    <!-- Feature 2: Large / Primary -->
                    <div class="md:col-span-7 lg:col-span-4">
                        <div
                            class="group h-full bg-white p-12 rounded-[2.5rem] border border-on-surface/5 transition-all duration-500 transform-gpu hover:-translate-y-1 hover:bg-primary hover:border-primary/20 hover:shadow-[0_50px_100px_-20px_rgba(43,139,235,0.3)] relative overflow-hidden flex flex-col justify-between">
                            <div
                                class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none transition-opacity group-hover:opacity-12">
                                <svg class="w-full h-full stroke-primary/30 fill-none transition-colors group-hover:stroke-white/45"
                                    viewbox="0 0 100 100">
                                    <circle cx="100" cy="0" r="80" stroke-width="0.5"></circle>
                                    <circle cx="100" cy="0" r="60" stroke-width="0.5"></circle>
                                    <circle cx="100" cy="0" r="40" stroke-width="0.5"></circle>
                                </svg>
                            </div>
                            <div>
                                <div
                                    class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center mb-10 text-primary border border-on-surface/5 transition-colors group-hover:bg-white/10 group-hover:text-white group-hover:border-white/20">
                                    <span class="material-symbols-outlined text-3xl font-light">analytics</span>
                                </div>
                                <h3
                                    class="font-headline text-3xl font-extrabold mb-6 tracking-tight leading-tight text-on-surface transition-colors group-hover:text-white">
                                    Advanced<br />Analytics Engine</h3>
                                <p
                                    class="text-on-surface-variant text-lg leading-relaxed font-medium mb-0 transition-colors group-hover:text-white/80">
                                    Deep-dive into clinical outcomes and operational ROI with automated reporting and
                                    predictive diagnostic modeling.</p>
                            </div>
                            <a href="Provider-HowItWorks.php"
                                class="bg-white text-primary w-full py-5 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-surface-container-low transition-colors transform-gpu hover:scale-[1.01] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 text-center">
                                Explore Metrics
                            </a>
                        </div>
                    </div>
                    <!-- Feature 3: Staggered Down / Glassmorphism -->
                    <div class="md:col-span-12 lg:col-span-4 lg:mt-36">
                        <div
                            class="group h-full glass-card p-12 rounded-[2.5rem] border border-on-surface/5 transition-all duration-500 transform-gpu hover:-translate-y-1 hover:bg-primary hover:border-primary/30 hover:shadow-xl relative overflow-hidden">
                            <div
                                class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center mb-10 text-primary transition-all duration-500 group-hover:scale-110 group-hover:bg-white/10 group-hover:text-white group-hover:border group-hover:border-white/20">
                                <span class="material-symbols-outlined text-3xl font-light">groups</span>
                            </div>
                            <h3
                                class="font-headline text-3xl font-extrabold mb-6 tracking-tight transition-colors group-hover:text-white">
                                Patient CRM</h3>
                            <p
                                class="text-on-surface-variant text-lg leading-relaxed font-medium mb-8 transition-colors group-hover:text-white/80">
                                Automated engagement workflows, digital records management, and omnichannel patient
                                communication in one hub.</p>
                            <div
                                class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-widest tracking-[0.3em] transition-colors group-hover:text-white">
                                <span
                                    class="w-8 h-[1px] bg-primary/30 transition-colors group-hover:bg-white/30"></span>
                                Core Integration
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Onboarding Protocol -->
        <section class="py-24 bg-[#fdfdfe] relative border-y border-on-surface/5 reveal" data-reveal="section"
            id="boarding">
            <div class="max-w-[1800px] mx-auto px-6 sm:px-8 lg:px-10">
                <div class="flex flex-col items-center text-center mb-24">
                    <div
                        class="inline-flex items-center gap-4 px-4 py-2 rounded-full bg-primary/5 text-primary text-[10px] font-black uppercase tracking-[0.4em] mb-6">
                        <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span> Implementation
                    </div>
                    <h2
                        class="font-headline text-4xl md:text-6xl font-extrabold tracking-tighter text-on-surface mb-6 leading-[1.1]">
                        The Onboarding <span
                            class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Protocol</span>
                    </h2>
                    <p class="text-on-surface-variant text-lg font-medium max-w-2xl">A precision-engineered pathway to
                        institutional activation.</p>
                </div>
                <!-- Integrated Visual Flow -->
                <div class="relative">
                    <!-- Desktop Connector Line -->
                    <div
                        class="hidden lg:block absolute top-1/2 left-0 w-full h-px step-connector -translate-y-1/2 z-0 opacity-20">
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-24 relative z-10">
                        <!-- Step 1 -->
                        <div class="relative group">
                            <div
                                class="bg-white rounded-[2rem] p-12 border border-on-surface/5 transition-all duration-500 transform-gpu group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
                                <div
                                    class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">
                                    01
                                </div>
                                <div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
                                    <span class="material-symbols-outlined text-5xl font-light">database_upload</span>
                                </div>
                                <h4 class="font-headline font-extrabold text-2xl mb-4">Data Migration</h4>
                                <p class="text-on-surface-variant leading-relaxed font-medium mb-8">Seamlessly porting
                                    legacy patient records and billing history with zero downtime.</p>
                                <div
                                    class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60">
                                    <span class="material-symbols-outlined text-lg">verified_user</span>
                                    High-Integrity Port
                                </div>
                            </div>
                        </div>
                        <!-- Step 2 -->
                        <div class="relative group">
                            <div
                                class="bg-white rounded-[2rem] p-12 border border-on-surface/5 transition-all duration-500 transform-gpu group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
                                <div
                                    class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">
                                    02
                                </div>
                                <div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
                                    <span class="material-symbols-outlined text-5xl font-light">school</span>
                                </div>
                                <h4 class="font-headline font-extrabold text-2xl mb-4">Staff Certification</h4>
                                <p class="text-on-surface-variant leading-relaxed font-medium mb-8">Comprehensive
                                    modules ensuring every clinician masters the OS efficiency tools.</p>
                                <div
                                    class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60">
                                    <span class="material-symbols-outlined text-lg">military_tech</span>
                                    Expert Accreditation
                                </div>
                            </div>
                        </div>
                        <!-- Step 3 -->
                        <div class="relative group">
                            <div
                                class="bg-white rounded-[2rem] p-12 border border-on-surface/5 transition-all duration-500 transform-gpu group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
                                <div
                                    class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">
                                    03
                                </div>
                                <div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
                                    <span class="material-symbols-outlined text-5xl font-light">rocket_launch</span>
                                </div>
                                <h4 class="font-headline font-extrabold text-2xl mb-4">System Activation</h4>
                                <p class="text-on-surface-variant leading-relaxed font-medium mb-8">Switching to the
                                    unified command center with real-time support specialists.</p>
                                <div
                                    class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60">
                                    <span class="material-symbols-outlined text-lg">bolt</span>
                                    Node Activation
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- Final CTA Section -->
        <section class="py-20 px-6 sm:px-8 lg:px-10 reveal" data-reveal="section">
            <div
                class="mx-auto rounded-[4rem] bg-primary relative overflow-hidden flex flex-col items-center text-center shadow-[0_40px_100px_-20px_rgba(43,139,235,0.4)] max-w-6xl py-20 px-6 sm:px-8 md:px-14 lg:px-20">
                <div class="relative z-10 max-w-3xl">
                    <div
                        class="inline-block px-4 py-1 rounded-full bg-white/20 text-white text-[10px] font-black uppercase tracking-[0.3em] mb-10">
                        Institutional Boarding
                    </div>
                    <h2
                        class="font-headline text-4xl font-extrabold text-white tracking-tighter leading-[0.85] md:text-5xl mb-7">
                        Ready to evolve your practice?</h2>
                    <p class="text-white/70 text-lg md:text-xl max-w-xl mx-auto leading-relaxed mb-10">Join hundreds of
                        dental clinics streamlining their clinical operations through the My Dental ecosystem.</p>
                    <a href="Provider-Plans.php"
                        class="bg-white text-primary px-16 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] hover:scale-[1.03] transition-transform duration-200 shadow-2xl active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 inline-block">
                        Start Your Subscription
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
                        } else {
                            entry.target.classList.remove('is-visible');
                        }
                    });
                }, { threshold: 0.18, rootMargin: '0px 0px -10% 0px' });

                elements.forEach(function (el) { observer.observe(el); });
            })();
    </script>
</body>

</html>