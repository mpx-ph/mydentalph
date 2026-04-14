<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>FAQs | MyDental.com</title>
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
          headline: ["Manrope", "sans-serif"],
          body: ["Inter", "sans-serif"],
          editorial: ["Playfair Display", "serif"],
        },
        borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "3xl": "2.5rem", "full": "9999px" },
      }
    }
  }
</script>
<style>
  .material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
  }
  .editorial-word {
    text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
    letter-spacing: -0.02em;
  }
  .mesh-gradient {
    background-color: #ffffff;
    background-image:
      radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
      radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
  }
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
  }
  @keyframes slowFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-14px); }
  }
  .slow-float {
    animation: slowFloat 10s ease-in-out infinite;
  }
</style>
</head>

<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
<?php include 'ProviderNavbar.php'; ?>

<main>
  <!-- Hero Section -->
  <section class="max-w-[1800px] mx-auto px-10 pt-16 pb-10 text-center reveal" data-reveal="section">
    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.4em] mb-7">
      Support Center
    </div>

    <h1 class="font-headline font-extrabold text-[clamp(2.6rem,5.2vw,4.6rem)] tracking-[-0.05em] text-slate-900 dark:text-white mb-6 leading-[0.95]">
      Frequently Asked <br/>
      <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Questions</span>
    </h1>

    <p class="font-body text-lg md:text-xl max-w-2xl mx-auto leading-relaxed text-on-surface-variant font-medium">
      Find answers to common questions about the MyDental platform and how it can transform your clinical operations.
    </p>

    <!-- Search Bar -->
    <div class="relative max-w-2xl mx-auto mt-10">
      <div class="absolute inset-y-0 left-6 flex items-center pointer-events-none">
        <span class="material-symbols-outlined text-primary/40" data-icon="search">search</span>
      </div>
      <input id="faq-search" class="w-full pl-16 pr-8 py-6 bg-white border border-on-surface/5 rounded-3xl focus:ring-4 focus:ring-primary/10 focus:border-primary/20 transition-all text-on-surface font-medium placeholder:text-on-surface/30 shadow-[0_20px_40px_-10px_rgba(0,0,0,0.03)] dark:bg-slate-900/40 dark:border-slate-800 dark:text-slate-100 dark:placeholder:text-slate-400" placeholder="Search for a topic..." type="text"/>
    </div>
  </section>

  <!-- FAQ Content -->
  <section class="max-w-[900px] mx-auto px-10 pb-20">
    <div class="space-y-16">
      <!-- Category: Getting Started -->
      <div id="getting-started" class="reveal" data-reveal="section">
        <div class="text-primary font-bold text-xs uppercase mb-8 flex items-center gap-4 tracking-[0.3em]">
          <span class="w-12 h-[1.5px] bg-primary"></span> Getting Started
        </div>

        <div class="space-y-6">
          <details data-faq-item data-faq-title="What is MyDental?" class="group bg-white rounded-3xl p-10 border border-on-surface/5 hover:border-primary/20 transition-all duration-500 hover:shadow-2xl hover:shadow-primary/5 cursor-pointer">
            <summary class="flex justify-between items-center gap-6 list-none outline-none cursor-pointer [&::-webkit-details-marker]:hidden">
              <h3 class="font-bold tracking-tight text-on-surface text-base md:text-lg">What is MyDental?</h3>
              <div class="w-10 h-10 rounded-full bg-surface-container-low flex items-center justify-center text-primary group-open:bg-primary group-open:text-white transition-all duration-300">
                <span class="material-symbols-outlined text-lg transition-transform duration-300 group-open:rotate-45" data-icon="add">add</span>
              </div>
            </summary>
            <div class="mt-8 text-on-surface-variant text-lg leading-relaxed font-medium border-t border-on-surface/5 pt-8">
              MyDental is a comprehensive, cloud-based dental practice management platform. We provide tools for patient scheduling, electronic health records, billing automation, and clinical imaging, all designed to streamline operations and enhance the patient experience through modern digital interfaces.
            </div>
          </details>

          <details data-faq-item data-faq-title="How do clinics create accounts?" class="group bg-white rounded-3xl p-10 border border-on-surface/5 hover:border-primary/20 transition-all duration-500 hover:shadow-2xl hover:shadow-primary/5 cursor-pointer">
            <summary class="flex justify-between items-center gap-6 list-none outline-none cursor-pointer [&::-webkit-details-marker]:hidden">
              <h3 class="font-bold tracking-tight text-on-surface text-base md:text-lg">How do clinics create accounts?</h3>
              <div class="w-10 h-10 rounded-full bg-surface-container-low flex items-center justify-center text-primary group-open:bg-primary group-open:text-white transition-all duration-300">
                <span class="material-symbols-outlined text-lg transition-transform duration-300 group-open:rotate-45" data-icon="add">add</span>
              </div>
            </summary>
            <div class="mt-8 text-on-surface-variant text-lg leading-relaxed font-medium border-t border-on-surface/5 pt-8">
              Registering your clinic is simple. Click on the "Get Started" button on our homepage, provide your clinic's basic information and license details, and our team will verify your credentials within 24 hours. Once verified, you can immediately begin setting up your team profiles and patient database.
            </div>
          </details>

          <details data-faq-item data-faq-title="Will each clinic have its own dashboard?" class="group bg-white rounded-3xl p-10 border border-on-surface/5 hover:border-primary/20 transition-all duration-500 hover:shadow-2xl hover:shadow-primary/5 cursor-pointer">
            <summary class="flex justify-between items-center gap-6 list-none outline-none cursor-pointer [&::-webkit-details-marker]:hidden">
              <h3 class="font-bold tracking-tight text-on-surface text-base md:text-lg">Will each clinic have its own dashboard?</h3>
              <div class="w-10 h-10 rounded-full bg-surface-container-low flex items-center justify-center text-primary group-open:bg-primary group-open:text-white transition-all duration-300">
                <span class="material-symbols-outlined text-lg transition-transform duration-300 group-open:rotate-45" data-icon="add">add</span>
              </div>
            </summary>
            <div class="mt-8 text-on-surface-variant text-lg leading-relaxed font-medium border-t border-on-surface/5 pt-8">
              Yes, absolutely. Every clinic registered on MyDental receives a private, secure, and fully customizable dashboard. This dashboard provides real-time analytics on patient visits, revenue tracking, inventory management, and staff performance, ensuring you have total visibility into your practice's health.
            </div>
          </details>

          <details data-faq-item data-faq-title="Can multiple clinics use the platform?" class="group bg-white rounded-3xl p-10 border border-on-surface/5 hover:border-primary/20 transition-all duration-500 hover:shadow-2xl hover:shadow-primary/5 cursor-pointer">
            <summary class="flex justify-between items-center gap-6 list-none outline-none cursor-pointer [&::-webkit-details-marker]:hidden">
              <h3 class="font-bold tracking-tight text-on-surface text-base md:text-lg">Can multiple clinics use the platform?</h3>
              <div class="w-10 h-10 rounded-full bg-surface-container-low flex items-center justify-center text-primary group-open:bg-primary group-open:text-white transition-all duration-300">
                <span class="material-symbols-outlined text-lg transition-transform duration-300 group-open:rotate-45" data-icon="add">add</span>
              </div>
            </summary>
            <div class="mt-8 text-on-surface-variant text-lg leading-relaxed font-medium border-t border-on-surface/5 pt-8">
              Yes, MyDental is built for scale. Whether you are a single private practice or a large Dental Service Organization (DSO) with hundreds of locations, our platform supports multi-site management. You can switch between locations seamlessly and generate consolidated reports for the entire organization.
            </div>
          </details>
        </div>
      </div>

      <!-- Category: Billing & Plans -->
      <div id="billing" class="reveal" data-reveal="section">
        <div class="text-primary font-bold text-xs uppercase mb-8 flex items-center gap-4 tracking-[0.3em]">
          <span class="w-12 h-[1.5px] bg-primary"></span> Billing &amp; Plans
        </div>

        <div class="space-y-6">
          <details data-faq-item data-faq-title="What are the payment options?" class="group bg-white rounded-3xl p-10 border border-on-surface/5 hover:border-primary/20 transition-all duration-500 hover:shadow-2xl hover:shadow-primary/5 cursor-pointer">
            <summary class="flex justify-between items-center gap-6 list-none outline-none cursor-pointer [&::-webkit-details-marker]:hidden">
              <h3 class="font-bold tracking-tight text-on-surface text-base md:text-lg">What are the payment options?</h3>
              <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center text-white transition-all duration-300">
                <span class="material-symbols-outlined text-lg" data-icon="remove">remove</span>
              </div>
            </summary>
            <div class="mt-8 text-on-surface-variant text-lg leading-relaxed font-medium border-t border-on-surface/5 pt-8">
              We accept all major credit cards, bank transfers (ACH), and digital wallets including Apple Pay and Google Pay. For enterprise clients, we can also support quarterly and annual invoicing options.
            </div>
          </details>

          <details data-faq-item data-faq-title="Can I change my plan at any time?" class="group bg-white rounded-3xl p-10 border border-on-surface/5 hover:border-primary/20 transition-all duration-500 hover:shadow-2xl hover:shadow-primary/5 cursor-pointer">
            <summary class="flex justify-between items-center gap-6 list-none outline-none cursor-pointer [&::-webkit-details-marker]:hidden">
              <h3 class="font-bold tracking-tight text-on-surface text-base md:text-lg">Can I change my plan at any time?</h3>
              <div class="w-10 h-10 rounded-full bg-surface-container-low flex items-center justify-center text-primary group-open:bg-primary group-open:text-white transition-all duration-300">
                <span class="material-symbols-outlined text-lg transition-transform duration-300 group-open:rotate-45" data-icon="add">add</span>
              </div>
            </summary>
            <div class="mt-8 text-on-surface-variant text-lg leading-relaxed font-medium border-t border-on-surface/5 pt-8">
              Yes. You can upgrade or adjust your plan whenever you are ready. Changes take effect according to your billing cycle, and our support team can help you choose the best option for your clinic's current needs.
            </div>
          </details>
        </div>
      </div>
    </div>
  </section>

  <div class="mt-16 lg:mt-20">
    <?php require_once __DIR__ . '/provider_evolve_practice_cta.inc.php'; ?>
  </div>
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

  (function () {
    var search = document.getElementById('faq-search');
    if (!search) return;

    var items = Array.prototype.slice.call(document.querySelectorAll('[data-faq-item]'));
    var normalize = function (s) { return (s || '').toString().toLowerCase(); };

    search.addEventListener('input', function () {
      var q = normalize(search.value.trim());
      items.forEach(function (item) {
        var title = normalize(item.getAttribute('data-faq-title'));
        var content = normalize(item.innerText);
        var match = !q || title.indexOf(q) !== -1 || content.indexOf(q) !== -1;
        item.style.display = match ? '' : 'none';
      });
    });
  })();
</script>
</body></html>