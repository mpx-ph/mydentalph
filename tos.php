<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Terms of Service | MyDental Philippines Incorporated</title>
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
    </style>
</head>
<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<?php include 'ProviderNavbar.php'; ?>
<main class="mesh-gradient flex-1">
<section class="py-12 md:py-16 text-center px-4 overflow-hidden reveal" data-reveal="section">
<div class="max-w-[1800px] mx-auto">
<p class="font-headline text-sm font-bold text-on-surface-variant tracking-wide mb-3">MyDental Philippines Incorporated</p>
<h1 class="font-headline text-[clamp(2.2rem,4.5vw,3.75rem)] font-extrabold tracking-[-0.04em] text-on-surface mb-4 leading-[1.1]">
Terms of <span class="font-editorial italic font-normal text-primary editorial-word inline-block">Service.</span>
</h1>
<p class="font-body text-on-surface-variant font-medium text-sm uppercase tracking-[0.2em]">Last updated: April 14, 2026</p>
</div>
</section>

<section class="max-w-[1800px] mx-auto px-6 sm:px-10 pb-20">
<article class="max-w-3xl mx-auto bg-white dark:bg-slate-900/70 rounded-[2rem] border border-on-surface/5 shadow-[0_20px_50px_-15px_rgba(43,139,235,0.08)] px-6 sm:px-10 md:px-12 py-10 md:py-14 space-y-10 text-on-surface-variant leading-relaxed">
<div class="prose-policy space-y-10 [&amp;_h2]:font-headline [&amp;_h2]:text-xl [&amp;_h2]:md:text-2xl [&amp;_h2]:font-extrabold [&amp;_h2]:text-on-surface [&amp;_h2]:tracking-tight [&amp;_h2]:mb-3 [&amp;_p]:text-[15px] [&amp;_p]:md:text-base [&amp;_ul]:list-disc [&amp;_ul]:pl-5 [&amp;_ul]:space-y-2 [&amp;_ul]:text-[15px] [&amp;_ul]:md:text-base">

<section>
<h2>1. Acceptance of Terms</h2>
<p>By accessing or using the MyDental Philippines Incorporated (&ldquo;MyDental,&rdquo; &ldquo;we,&rdquo; &ldquo;our,&rdquo; or &ldquo;us&rdquo;) platform, you agree to comply with and be bound by these Terms of Service. If you do not agree to these terms, you must discontinue use of the platform immediately.</p>
</section>

<section>
<h2>2. Description of Services</h2>
<p>MyDental provides a cloud-based clinic management system designed for dental practices, which may include patient record management, appointment scheduling, billing, reporting, and related tools.</p>
<p>We reserve the right to enhance, modify, suspend, or discontinue any part of the service at any time, with reasonable notice where applicable.</p>
</section>

<section>
<h2>3. User Accounts and Responsibilities</h2>
<p>Users are responsible for:</p>
<ul>
<li>Maintaining the confidentiality of their login credentials</li>
<li>Ensuring that all account information is accurate and up to date</li>
<li>Restricting access to authorized personnel only</li>
</ul>
<p>You are accountable for all activities that occur under your account.</p>
</section>

<section>
<h2>4. Subscription, Fees, and Billing</h2>
<p>Access to certain features of the platform may require a paid subscription. By subscribing:</p>
<ul>
<li>You agree to the applicable fees based on your selected plan</li>
<li>Subscriptions may renew automatically unless cancelled prior to the renewal date</li>
<li>Failure to settle outstanding balances may result in suspension or termination of access</li>
</ul>
<p>All fees are non-refundable unless otherwise stated.</p>
</section>

<section>
<h2>5. Acceptable Use</h2>
<p>You agree to use the platform only for lawful and legitimate clinical and administrative purposes. You must not:</p>
<ul>
<li>Use the system for fraudulent, unlawful, or unethical activities</li>
<li>Attempt to gain unauthorized access to other accounts or data</li>
<li>Interfere with system integrity, security, or performance</li>
<li>Upload malicious code, viruses, or harmful content</li>
</ul>
</section>

<section>
<h2>6. Data Management and Compliance</h2>
<p>Clinics using MyDental are responsible for ensuring that the collection, use, and management of patient data comply with applicable laws, including the Data Privacy Act of 2012.</p>
<p>MyDental provides tools to support compliance but does not replace the clinic&rsquo;s legal obligations as a personal information controller.</p>
</section>

<section>
<h2>7. Intellectual Property</h2>
<p>All software, features, design elements, and content provided by MyDental remain the property of MyDental Philippines Incorporated or its licensors.</p>
<p>You are granted a limited, non-exclusive, non-transferable right to use the platform solely for its intended purpose.</p>
</section>

<section>
<h2>8. Service Availability</h2>
<p>We strive to maintain reliable and secure access to our platform; however, we do not guarantee uninterrupted or error-free service. Scheduled maintenance and unforeseen technical issues may occasionally affect availability.</p>
</section>

<section>
<h2>9. Limitation of Liability</h2>
<p>To the fullest extent permitted by law, MyDental shall not be liable for:</p>
<ul>
<li>Any indirect, incidental, or consequential damages</li>
<li>Loss of data, revenue, or business opportunities</li>
<li>Issues arising from user negligence or misuse of the platform</li>
</ul>
<p>Use of the platform is at your own risk.</p>
</section>

<section>
<h2>10. Termination</h2>
<p>We reserve the right to suspend or terminate access to the platform if:</p>
<ul>
<li>These Terms of Service are violated</li>
<li>There is suspected misuse or security risk</li>
<li>Required payments are not fulfilled</li>
</ul>
<p>Users may also terminate their subscription subject to applicable notice periods.</p>
</section>

<section>
<h2>11. Amendments to Terms</h2>
<p>We may update these Terms of Service from time to time. Continued use of the platform after changes take effect constitutes acceptance of the revised terms.</p>
</section>

<section>
<h2>12. Governing Law</h2>
<p>These Terms shall be governed by and interpreted in accordance with the laws of the Republic of the Philippines.</p>
</section>

<section>
<h2>13. Contact Information</h2>
<p>For any questions or clarifications regarding these Terms of Service, you may contact us at:</p>
<p class="mt-4 rounded-2xl border border-primary/15 bg-surface-container-low/80 dark:bg-slate-800/50 px-5 py-4">
<span class="font-headline font-extrabold text-on-surface text-sm uppercase tracking-wide block mb-1">Email</span>
<a class="text-primary font-semibold hover:underline break-all" href="mailto:legal@mydental.ph">legal@mydental.ph</a>
</p>
<p class="text-[15px] md:text-base border-t border-on-surface/10 mt-8 pt-8">By using MyDental, you confirm that you have read, understood, and agreed to these Terms of Service.</p>
</section>

</div>
</article>
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
</div>
</div>
</body>
</html>
