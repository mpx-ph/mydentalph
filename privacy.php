<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Privacy Policy | MyDental Philippines Incorporated</title>
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
Privacy <span class="font-editorial italic font-normal text-primary editorial-word inline-block">Policy.</span>
</h1>
<p class="font-body text-on-surface-variant font-medium text-sm uppercase tracking-[0.2em]">Last updated: April 12, 2026</p>
</div>
</section>

<section class="max-w-[1800px] mx-auto px-6 sm:px-10 pb-20">
<article class="max-w-3xl mx-auto bg-white dark:bg-slate-900/70 rounded-[2rem] border border-on-surface/5 shadow-[0_20px_50px_-15px_rgba(43,139,235,0.08)] px-6 sm:px-10 md:px-12 py-10 md:py-14 space-y-10 text-on-surface-variant leading-relaxed">
<div class="prose-policy space-y-10 [&amp;_h2]:font-headline [&amp;_h2]:text-xl [&amp;_h2]:md:text-2xl [&amp;_h2]:font-extrabold [&amp;_h2]:text-on-surface [&amp;_h2]:tracking-tight [&amp;_h2]:mb-3 [&amp;_p]:text-[15px] [&amp;_p]:md:text-base [&amp;_ul]:list-disc [&amp;_ul]:pl-5 [&amp;_ul]:space-y-2 [&amp;_ul]:text-[15px] [&amp;_ul]:md:text-base">

<section>
<h2>1. Introduction</h2>
<p>At MyDental Philippines Incorporated (&ldquo;MyDental,&rdquo; &ldquo;we,&rdquo; &ldquo;our,&rdquo; or &ldquo;us&rdquo;), we are committed to protecting your personal information and upholding your rights under the Data Privacy Act of 2012 (Republic Act No. 10173). This Privacy Policy explains how we collect, use, store, and safeguard information when you use our clinic management platform and related services.</p>
</section>

<section>
<h2>2. Scope of This Policy</h2>
<p>This Privacy Policy applies to all users of our platform, including dental clinics, practitioners, staff, and authorized personnel who access and manage patient information through our system.</p>
</section>

<section>
<h2>3. Information We Collect</h2>
<p>To effectively deliver our services, we may collect and process the following types of data:</p>
<ul>
<li><span class="font-semibold text-on-surface">Clinic Information:</span> Business name, address, contact numbers, and operational details</li>
<li><span class="font-semibold text-on-surface">User Account Information:</span> Names, roles, login credentials, and contact details of practitioners and staff</li>
<li><span class="font-semibold text-on-surface">Patient Records:</span> Clinical data, treatment history, appointment details, and other health-related information entered by the clinic</li>
<li><span class="font-semibold text-on-surface">Technical and Usage Data:</span> System logs, access timestamps, device information, and performance metrics</li>
</ul>
<p>All sensitive personal information is handled with strict confidentiality and appropriate safeguards.</p>
</section>

<section>
<h2>4. Purpose of Data Processing</h2>
<p>We process your data for the following purposes:</p>
<ul>
<li>To provide and maintain our clinic management services</li>
<li>To enable secure access and user authentication</li>
<li>To support clinic operations such as scheduling, billing, and record-keeping</li>
<li>To improve system performance, reliability, and user experience</li>
<li>To comply with legal and regulatory requirements</li>
</ul>
</section>

<section>
<h2>5. Data Privacy Act Compliance</h2>
<p>MyDental adheres to the principles of transparency, legitimate purpose, and proportionality under the Data Privacy Act of the Philippines. We implement organizational, physical, and technical security measures to protect personal data against unauthorized access, disclosure, alteration, or destruction.</p>
</section>

<section>
<h2>6. Data Security Measures</h2>
<p>We employ industry-standard security practices, including:</p>
<ul>
<li>Data encryption (in transit and at rest)</li>
<li>Role-based access controls</li>
<li>Secure authentication protocols</li>
<li>Regular system monitoring and vulnerability assessments</li>
</ul>
<p>While we provide a secure platform, clinics are responsible for ensuring that their internal processes also comply with applicable privacy laws.</p>
</section>

<section>
<h2>7. Data Ownership and Control</h2>
<p>All patient and clinic data stored within our platform remain the sole property of the respective clinic. MyDental does not sell, rent, or share your data with third parties for commercial purposes.</p>
<p>Each clinic operates within a logically isolated environment to ensure confidentiality and data separation.</p>
</section>

<section>
<h2>8. Data Retention</h2>
<p>We retain personal data only for as long as necessary to fulfill the purposes outlined in this policy or as required by applicable laws and regulations. Clinics may request data export or deletion subject to verification and legal obligations.</p>
</section>

<section>
<h2>9. Data Sharing and Disclosure</h2>
<p>We may disclose information only under the following circumstances:</p>
<ul>
<li>When required by law, regulation, or legal process</li>
<li>To protect the rights, safety, and security of users or the public</li>
<li>With authorized service providers under strict confidentiality agreements (if applicable)</li>
</ul>
</section>

<section>
<h2>10. Your Rights as a Data Subject</h2>
<p>Under the Data Privacy Act, you have the right to:</p>
<ul>
<li>Be informed about how your data is processed</li>
<li>Access and request a copy of your personal data</li>
<li>Correct inaccurate or outdated information</li>
<li>Object to or restrict processing under certain conditions</li>
<li>Request erasure or blocking of your data, when applicable</li>
</ul>
<p>Requests may be subject to identity verification and legal limitations.</p>
</section>

<section>
<h2>11. Updates to This Policy</h2>
<p>We may update this Privacy Policy from time to time to reflect changes in our services, legal requirements, or security practices. Any updates will be posted on this page with the revised date.</p>
</section>

<section>
<h2>12. Contact Us</h2>
<p>If you have questions, concerns, or requests related to your privacy or this policy, you may contact our Data Protection Officer:</p>
<p class="mt-4 rounded-2xl border border-primary/15 bg-surface-container-low/80 dark:bg-slate-800/50 px-5 py-4">
<span class="font-headline font-extrabold text-on-surface text-sm uppercase tracking-wide block mb-1">Email</span>
<a class="text-primary font-semibold hover:underline break-all" href="mailto:privacy@mydental.ph">privacy@mydental.ph</a>
</p>
<p class="text-[15px] md:text-base border-t border-on-surface/10 mt-8 pt-8">By using our platform, you acknowledge that you have read and understood this Privacy Policy and agree to the collection and use of your information in accordance with it.</p>
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
