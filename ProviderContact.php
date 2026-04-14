<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Contact Us | MyDental.com</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
              headline: ["Manrope", "sans-serif"],
              body: ["Inter", "sans-serif"],
              editorial: ["Playfair Display", "serif"],
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
          radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
          radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
      }
      .editorial-word {
        text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
        letter-spacing: -0.02em;
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

      @keyframes popIn {
        0% { transform: translateY(10px) scale(0.985); }
        60% { transform: translateY(-3px) scale(1.01); }
        100% { transform: translateY(0) scale(1); }
      }
      .pop-up {
        animation: popIn 650ms cubic-bezier(0.22, 1, 0.36, 1) both;
      }
</style>
</head>
<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
<div class="relative flex min-h-screen w-full flex-col overflow-x-clip">
<div class="layout-container flex h-full grow flex-col">
<!-- Navigation Bar -->
<?php include 'ProviderNavbar.php'; ?>
<main class="mesh-gradient">
<!-- Hero Section -->
<section class="py-12 md:py-18 text-center px-4 overflow-hidden reveal" data-reveal="section">
<div class="max-w-[1800px] mx-auto">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.4em] mb-7">
Reach Out to Excellence
</div>
<h1 class="font-headline text-[clamp(2.6rem,5.2vw,4.6rem)] font-extrabold tracking-[-0.05em] text-on-surface mb-6 leading-[0.95]">
Get in <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Touch.</span>
</h1>
<p class="font-body text-lg md:text-xl max-w-2xl mx-auto leading-relaxed text-on-surface-variant font-medium">
Elevating dental practice management through clinical precision and digital curation. Our team is ready to assist your clinic's transformation.
</p>
</div>
</section>

<!-- Form & Info Section -->
<section class="max-w-[1800px] mx-auto px-10 mb-16 reveal" data-reveal="section">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:items-stretch">
<!-- Left Column: Contact Information -->
<div class="flex min-h-0 h-full">
<div data-reveal="section" class="bg-white dark:bg-slate-900/70 p-8 rounded-[2rem] border border-on-surface/5 shadow-[0_20px_50px_-15px_rgba(43,139,235,0.05)] space-y-7 reveal h-full min-h-0 w-full">
<div>
<div class="text-primary font-bold text-xs uppercase mb-7 flex items-center gap-4 tracking-[0.3em]">
  <span class="w-12 h-[1.5px] bg-primary"></span> Contact Information
</div>
<div class="space-y-7">
<div class="flex items-start gap-6 group">
<div class="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-2xl font-light" data-icon="location_on">location_on</span>
</div>
<div>
<p class="font-headline font-extrabold text-on-surface text-lg mb-1 tracking-tight">Office Address</p>
<p class="text-on-surface-variant font-medium leading-relaxed">Quezon City, Philippines</p>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-2xl font-light" data-icon="call">call</span>
</div>
<div>
<p class="font-headline font-extrabold text-on-surface text-lg mb-1 tracking-tight">Phone</p>
<p class="text-on-surface-variant font-medium leading-relaxed">+63 912 345 6789</p>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-2xl font-light" data-icon="mail">mail</span>
</div>
<div>
<p class="font-headline font-extrabold text-on-surface text-lg mb-1 tracking-tight">Email</p>
<p class="text-on-surface-variant font-medium leading-relaxed">support@mydental.com</p>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-12 h-12 rounded-xl bg-surface-container-low flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-2xl font-light" data-icon="schedule">schedule</span>
</div>
<div>
<p class="font-headline font-extrabold text-on-surface text-lg mb-1 tracking-tight">Support Hours</p>
<p class="text-on-surface-variant font-medium leading-relaxed">Mon - Fri: 8:00 AM - 6:00 PM<br/>24/7 Priority Emergency Support</p>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- Right Column: Contact Form -->
<div data-reveal="section" class="bg-white/80 dark:bg-slate-900/60 backdrop-blur-xl p-8 rounded-[2rem] shadow-[0_40px_100px_-30px_rgba(43,139,235,0.15)] border border-primary/10 border-t-4 border-t-primary relative reveal h-full min-h-0 w-full">
<form class="space-y-8">
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-on-surface-variant/60 ml-1 font-headline text-primary/70">Name</label>
<input class="w-full bg-slate-50/50 border border-slate-100 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-4 py-3 rounded-2xl text-on-surface font-medium placeholder:text-on-surface-variant/40 outline-none" placeholder="Dr. Julian Pierce" type="text"/>
</div>
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-on-surface-variant/60 ml-1 font-headline text-primary/70">Clinic Name</label>
<input class="w-full bg-slate-50/50 border border-slate-100 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-4 py-3 rounded-2xl text-on-surface font-medium placeholder:text-on-surface-variant/40 outline-none" placeholder="Apex Dental Group" type="text"/>
</div>
</div>
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-on-surface-variant/60 ml-1 font-headline text-primary/70">Email</label>
<input class="w-full bg-slate-50/50 border border-slate-100 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-4 py-3 rounded-2xl text-on-surface font-medium placeholder:text-on-surface-variant/40 outline-none" placeholder="julian@apexdental.com" type="email"/>
</div>
<div class="space-y-3">
<label class="text-xs font-black uppercase tracking-[0.2em] text-on-surface-variant/60 ml-1 font-headline text-primary/70">Message</label>
<textarea class="w-full bg-slate-50/50 border border-slate-100 focus:border-primary/30 focus:ring-4 focus:ring-primary/10 transition-all px-4 py-3 rounded-2xl text-on-surface font-medium placeholder:text-on-surface-variant/40 outline-none" placeholder="Describe your clinic's needs..." rows="5"></textarea>
</div>
<button class="w-full bg-primary text-white font-headline font-black text-sm uppercase tracking-[0.2em] py-4 rounded-2xl shadow-[0_20px_40px_-10px_rgba(43,139,235,0.4)] hover:shadow-[0_25px_50px_-12px_rgba(43,139,235,0.6)] hover:-translate-y-0.5 active:scale-[0.98] transition-all flex items-center justify-center gap-3 group" type="button">
Send Inquiry
<span class="material-symbols-outlined transition-transform group-hover:translate-x-1" data-icon="arrow_forward">arrow_right_alt</span>
</button>
</form>
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
                } else {
                    entry.target.classList.remove('is-visible');
                }
            });
        }, { threshold: 0.18, rootMargin: '0px 0px -10% 0px' });

        elements.forEach(function (el) { observer.observe(el); });
    })();
</script>
</div>
</div>
</body></html>