<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>My Dental | Dental Clinic Management OS</title>
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
    </style>
</head>
<body class="bg-surface font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
<!-- Navigation (preserve existing login functionality) -->
<?php include 'ProviderNavbar.php'; ?>
<main>
<!-- Asymmetrical Hero Section -->
<section class="relative min-h-[85vh] flex items-center mesh-gradient pt-8 overflow-hidden">
<div class="max-w-[1800px] mx-auto w-full grid grid-cols-1 lg:grid-cols-12 gap-0 items-center px-6">
<div class="lg:col-span-6 z-10 py-10 pr-8">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-8">
                    <img src="MyDental%20Logo.svg" alt="My Dental Logo" class="h-4 w-auto" />
                    Platform Management
                </div>
<h1 class="font-headline text-[clamp(2.6rem,5.5vw,4.6rem)] font-extrabold tracking-[-0.05em] text-on-surface mb-6 leading-[0.88]">
<span class="block">Modernize Your</span>
<span class="relative block">
<span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Practice.</span>
</span>
<span class="block text-[clamp(1.2rem,2vw,2rem)] font-body font-semibold tracking-tight text-on-surface/80 mt-2">
                    with My Dental
                </span>
</h1>
<p class="font-body text-lg max-w-lg mb-8 leading-relaxed text-on-surface-variant font-medium">
                    The My Dental OS: a unified dental management suite designed for efficiency, architectural precision, and multi-tenant clinic scaling.
                </p>
<div class="flex items-center gap-10">
<a href="Provider-Plans.php" class="group relative px-10 py-5 bg-primary text-white font-bold rounded-full overflow-hidden transition-all hover:pr-14 active:scale-95 text-center">
<span class="relative z-10">View Your Plans</span>
<span class="material-symbols-outlined absolute right-4 opacity-0 group-hover:opacity-100 transition-all">arrow_right_alt</span>
</a>
<a class="font-bold text-sm uppercase tracking-widest flex items-center gap-3 hover:text-primary transition-colors" href="ProviderContact.php">
<span class="w-10 h-[1px] bg-on-surface/20"></span>
                        Request Demo
                    </a>
</div>
</div>
<div class="lg:col-span-6 relative h-[65vh] -mr-6">
<div class="slanted-container h-full w-full bg-slate-100 overflow-hidden shadow-2xl relative">
<img alt="Modern dental clinic with advanced technology" class="w-full h-full object-cover scale-110" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAKSToKSubciBNDHHIIhBLQWuwv70uupwQdixl7SdJZDmgnDrO7KwPH0nU9Tuyv8aNshhWTfTeP75EKGGbML5Ge0AweBfsy2V4AmVWId5nTGtpGe6_7fZcwoTag1cM1PJdBpkLGRE47XjINHeAHov0gmJegOGXOaY4Xsbphb11ypnokm_GnMy42Lk5byi_6B13so8CQ8mAtQE0e6twPfwumg6xkxXcDNMUMRCwqnTWdqYYK6EWku_TTChy4ON47ltF4FcaFeaL3nCw"/>
<div class="absolute inset-0 bg-primary/5 mix-blend-multiply"></div>
</div>
<div class="absolute bottom-12 -left-6 glass-card p-6 rounded-2xl shadow-xl max-w-xs border border-white/40">
<div class="flex gap-4 items-start">
<div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white shrink-0">
<span class="material-symbols-outlined text-sm">dashboard_customize</span>
</div>
<div>
<h4 class="font-headline font-extrabold text-sm tracking-tight mb-1">Unified Console</h4>
<p class="text-[11px] leading-tight text-on-surface-variant font-medium">Manage patient lifecycles, billing, and clinical assets from a single digital command center.</p>
</div>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- System Features: Clinical Intelligence -->
<section class="py-20 px-6 bg-white relative overflow-hidden" id="features">
<div class="max-w-[1800px] mx-auto">
<div class="flex flex-col lg:flex-row justify-between items-start mb-20 gap-12">
<div class="max-w-3xl">
<div class="text-primary font-bold text-xs uppercase mb-6 flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> System Capability
                    </div>
<h2 class="font-headline text-4xl md:text-6xl font-extrabold tracking-tighter leading-[0.95] mb-6">Clinical <br/> <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Intelligence</span></h2>
<p class="text-on-surface-variant text-xl leading-relaxed max-w-xl font-medium">
                        Sophisticated architectural tooling designed for high-performance healthcare systems.
                    </p>
</div>
<div class="relative hidden lg:block pr-20">
<span class="text-[16rem] font-headline font-black text-primary/[0.03] leading-none tracking-tighter absolute -right-20 -top-24 select-none">CI</span>
</div>
</div>
<!-- Staggered Card Layout -->
<div class="grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-10">
<!-- Feature 1: Staggered Up -->
<div class="md:col-span-5 lg:col-span-4 md:mt-24">
<div class="group h-full bg-white p-12 rounded-[2.5rem] border border-on-surface/5 hover:border-primary/20 transition-all duration-700 hover:shadow-[0_40px_80px_-20px_rgba(43,139,235,0.08)] relative overflow-hidden">
<div class="absolute -right-8 -top-8 w-32 h-32 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors"></div>
<div class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">monitoring</span>
</div>
<h3 class="font-headline text-3xl font-extrabold mb-6 tracking-tight">Tenant Monitoring</h3>
<p class="text-on-surface-variant text-lg leading-relaxed font-medium mb-8">Real-time oversight across multiple clinic branches. Monitor chair occupancy, staff performance, and inventory health instantly.</p>
<div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-widest tracking-[0.3em]">
<span class="w-8 h-[1px] bg-primary/30"></span> Live Node Access
                        </div>
</div>
</div>
<!-- Feature 2: Large / Primary -->
<div class="md:col-span-7 lg:col-span-4">
<div class="group h-full bg-primary p-12 rounded-[2.5rem] shadow-[0_50px_100px_-20px_rgba(43,139,235,0.3)] transition-all duration-700 relative overflow-hidden flex flex-col justify-between">
<div class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none">
<svg class="w-full h-full stroke-white fill-none" viewbox="0 0 100 100">
<circle cx="100" cy="0" r="80" stroke-width="0.5"></circle>
<circle cx="100" cy="0" r="60" stroke-width="0.5"></circle>
<circle cx="100" cy="0" r="40" stroke-width="0.5"></circle>
</svg>
</div>
<div>
<div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-10 text-white border border-white/20">
<span class="material-symbols-outlined text-3xl font-light">analytics</span>
</div>
<h3 class="font-headline text-4xl font-extrabold mb-6 tracking-tight text-white leading-tight">Advanced<br/>Analytics Engine</h3>
<p class="text-white/80 text-xl leading-relaxed font-medium mb-12">Deep-dive into clinical outcomes and operational ROI with automated reporting and predictive diagnostic modeling.</p>
</div>
<a href="Provider-HowItWorks.php" class="bg-white text-primary w-full py-5 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-surface-container-low transition-colors text-center">
                            Explore Metrics
                        </a>
</div>
</div>
<!-- Feature 3: Staggered Down / Glassmorphism -->
<div class="md:col-span-12 lg:col-span-4 lg:mt-36">
<div class="group h-full glass-card p-12 rounded-[2.5rem] border border-on-surface/5 hover:border-primary/30 transition-all duration-700 hover:shadow-xl relative overflow-hidden">
<div class="w-14 h-14 bg-surface-container-low rounded-2xl flex items-center justify-center mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">groups</span>
</div>
<h3 class="font-headline text-3xl font-extrabold mb-6 tracking-tight">Patient CRM</h3>
<p class="text-on-surface-variant text-lg leading-relaxed font-medium mb-8">Automated engagement workflows, digital records management, and omnichannel patient communication in one hub.</p>
<div class="flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-widest tracking-[0.3em]">
<span class="w-8 h-[1px] bg-primary/30"></span> Core Integration
                        </div>
</div>
</div>
</div>
</div>
</section>
<!-- Onboarding Protocol -->
<section class="py-24 bg-[#fdfdfe] relative border-y border-on-surface/5" id="boarding">
<div class="max-w-[1800px] mx-auto px-6">
<div class="flex flex-col items-center text-center mb-24">
<div class="inline-flex items-center gap-4 px-4 py-2 rounded-full bg-primary/5 text-primary text-[10px] font-black uppercase tracking-[0.4em] mb-6">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span> Implementation
                </div>
<h2 class="font-headline text-4xl md:text-6xl font-extrabold tracking-tighter text-on-surface mb-6 leading-[1.1]">The Onboarding <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Protocol</span></h2>
<p class="text-on-surface-variant text-lg font-medium max-w-2xl">A precision-engineered pathway to institutional activation.</p>
</div>
<!-- Integrated Visual Flow -->
<div class="relative">
<!-- Desktop Connector Line -->
<div class="hidden lg:block absolute top-1/2 left-0 w-full h-px step-connector -translate-y-1/2 z-0 opacity-20"></div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-24 relative z-10">
<!-- Step 1 -->
<div class="relative group">
<div class="bg-white rounded-[2rem] p-12 border border-on-surface/5 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">
                                01
                            </div>
<div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">database_upload</span>
</div>
<h4 class="font-headline font-extrabold text-2xl mb-4">Data Migration</h4>
<p class="text-on-surface-variant leading-relaxed font-medium mb-8">Seamlessly porting legacy patient records and billing history with zero downtime.</p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60">
<span class="material-symbols-outlined text-lg">verified_user</span>
                                High-Integrity Port
                            </div>
</div>
</div>
<!-- Step 2 -->
<div class="relative group">
<div class="bg-white rounded-[2rem] p-12 border border-on-surface/5 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">
                                02
                            </div>
<div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">school</span>
</div>
<h4 class="font-headline font-extrabold text-2xl mb-4">Staff Certification</h4>
<p class="text-on-surface-variant leading-relaxed font-medium mb-8">Comprehensive modules ensuring every clinician masters the OS efficiency tools.</p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60">
<span class="material-symbols-outlined text-lg">military_tech</span>
                                Expert Accreditation
                            </div>
</div>
</div>
<!-- Step 3 -->
<div class="relative group">
<div class="bg-white rounded-[2rem] p-12 border border-on-surface/5 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">
                                03
                            </div>
<div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">rocket_launch</span>
</div>
<h4 class="font-headline font-extrabold text-2xl mb-4">System Activation</h4>
<p class="text-on-surface-variant leading-relaxed font-medium mb-8">Switching to the unified command center with real-time support specialists.</p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60">
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
<section class="py-20 px-6">
<div class="mx-auto rounded-[4rem] bg-primary relative overflow-hidden flex flex-col items-center text-center shadow-[0_40px_100px_-20px_rgba(43,139,235,0.4)] max-w-6xl py-24 px-10 md:px-20">
<div class="relative z-10 max-w-3xl">
<div class="inline-block px-4 py-1 rounded-full bg-white/20 text-white text-[10px] font-black uppercase tracking-[0.3em] mb-10">
                    Institutional Boarding
                </div>
<h2 class="font-headline text-4xl font-extrabold text-white tracking-tighter leading-[0.85] md:text-5xl mb-7">Ready to evolve your practice?</h2>
<p class="text-white/70 text-lg md:text-xl max-w-xl mx-auto leading-relaxed mb-10">Join hundreds of dental clinics streamlining their clinical operations through the My Dental ecosystem.</p>
<a href="Provider-Plans.php" class="bg-white text-primary px-16 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-2xl active:scale-95 inline-block">
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
<!-- Footer -->
<footer class="w-full border-t border-slate-200 bg-slate-50">
<div class="flex flex-col md:flex-row justify-between items-center py-12 px-8 max-w-screen-2xl mx-auto gap-4">
<div class="flex items-center gap-3 text-lg font-bold text-slate-900 font-headline">
<img src="MyDental%20Logo.svg" alt="My Dental Logo" class="h-8 w-auto"/>
                    My Dental
                </div>
<div class="flex flex-wrap justify-center gap-8 text-xs font-inter text-slate-500">
<a class="hover:text-blue-500 hover:underline transition-all" href="#">Privacy Policy</a>
<a class="hover:text-blue-500 hover:underline transition-all" href="#">Terms of Service</a>
<a class="hover:text-blue-500 hover:underline transition-all" href="#">Interoperability Standards</a>
<a class="hover:text-blue-500 hover:underline transition-all" href="#">Contact Sales</a>
</div>
<div class="text-xs text-slate-500 font-inter opacity-80 hover:opacity-100">
            © 2024 My Dental Inc. All rights reserved.
        </div>
</div>
</footer>
</body>
</html>