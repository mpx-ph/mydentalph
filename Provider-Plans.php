<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin/superadmin_settings_lib.php';

$go_to_purchase = false;
$max_sites_reached = false;
if (!empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id'])) {
    $tid = $_SESSION['tenant_id'];
    $stmt = $pdo->prepare("SELECT subscription_status FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_active = ($row && ($row['subscription_status'] ?? '') === 'active');
    if (!$has_active) {
        $stmt = $pdo->prepare("SELECT 1 FROM tbl_tenant_subscriptions WHERE tenant_id = ? AND payment_status = 'paid' LIMIT 1");
        $stmt->execute([$tid]);
        $has_active = (bool) $stmt->fetch();
    }
    if ($has_active) {
        $max_sites_reached = true;
    } else {
        $go_to_purchase = true;
    }
}
$plan_base = $go_to_purchase ? 'ProviderPurchase.php' : 'ProviderCreate.php';
$settings = superadmin_get_settings($pdo);
$providerPlans = isset($settings['provider_plans']) && is_array($settings['provider_plans'])
    ? $settings['provider_plans']
    : superadmin_default_provider_plans();
$starter = isset($providerPlans['starter']) && is_array($providerPlans['starter']) ? $providerPlans['starter'] : [];
$professional = isset($providerPlans['professional']) && is_array($providerPlans['professional']) ? $providerPlans['professional'] : [];
$enterprise = isset($providerPlans['enterprise']) && is_array($providerPlans['enterprise']) ? $providerPlans['enterprise'] : [];
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
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
              "headline": ["Manrope", "sans-serif"],
              "body": ["Inter", "sans-serif"],
              "editorial": ["Playfair Display", "serif"],
              "display": ["Manrope"]
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
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
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.08) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.06) 0px, transparent 50%);
        }
        .dark .mesh-gradient {
            background-color: #101922;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.14) 0px, transparent 55%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.10) 0px, transparent 55%);
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.12);
            letter-spacing: -0.02em;
        }
    </style>
<title>Pricing Plans | MyDental.com</title>
</head>
<body class="bg-background-light dark:bg-background-dark font-body text-slate-900 dark:text-slate-100 transition-colors duration-200">
<div class="relative flex min-h-screen flex-col">
<!-- Header / Navigation -->
<?php include 'ProviderNavbar.php'; ?>
<main class="flex-grow mesh-gradient overflow-x-hidden">
<!-- Hero Section -->
<section class="max-w-[1800px] mx-auto px-10 mb-12 pt-16 text-center">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-8">
                Simple Clinical Pricing
            </div>
<h1 class="font-headline font-extrabold text-[clamp(2.4rem,5vw,4.2rem)] tracking-[-0.04em] text-slate-900 dark:text-white mb-8 leading-[1.1]">
                Flexible plans for <br class="hidden md:block"/>every modern <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">practice.</span>
            </h1>
<p class="font-body text-lg text-slate-600 dark:text-slate-300 max-w-2xl mx-auto font-semibold leading-relaxed">
                Choose the billing flow that fits your clinic operations. Upgrade when you are ready to scale.
            </p>
</section>
<?php if ($max_sites_reached): ?>
<!-- Max sites warning -->
<section class="mx-auto max-w-7xl px-6 pb-4">
<div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 text-amber-800 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200 sm:px-6">
<div class="flex flex-wrap items-start gap-3">
<span class="material-symbols-outlined mt-0.5 text-2xl">info</span>
<div>
<p class="font-semibold">Maximum site limit reached</p>
<p class="mt-1 text-sm opacity-90">Your account can have only one site. To change your plan or manage your clinic, go to your <a href="ProviderTenantDashboard.php" class="font-semibold underline hover:no-underline">dashboard</a>.</p>
</div>
</div>
</div>
</section>
<?php endif; ?>
<!-- Pricing Grid -->
<section class="mx-auto max-w-6xl px-6 pb-20">
<div class="grid grid-cols-1 gap-8 md:grid-cols-3 lg:gap-6 items-stretch">
<!-- Starter Plan -->
<div class="bg-white p-10 md:p-14 rounded-[3rem] border border-slate-100 flex flex-col justify-between hover:border-primary/20 transition-all duration-500 shadow-sm hover:shadow-2xl group">
<div>
<div class="flex justify-between items-start mb-10">
<div>
<span class="text-primary font-bold text-[10px] uppercase tracking-[0.4em] mb-4 block">Flexible Access</span>
<h3 class="font-headline text-4xl text-slate-900 dark:text-white font-editorial italic font-normal text-primary editorial-word"><?php echo htmlspecialchars((string) ($starter['name'] ?? 'Starter'), ENT_QUOTES, 'UTF-8'); ?></h3>
</div>
<div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center group-hover:bg-primary/5 transition-colors">
<span class="material-symbols-outlined text-primary">calendar_today</span>
</div>
</div>
<div class="flex items-baseline gap-1 mb-10">
<span class="text-6xl font-headline font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) ($starter['price'] ?? '₱999'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-slate-500 dark:text-slate-400 font-bold text-lg">/mo</span>
</div>
<p class="text-sm text-slate-600 dark:text-slate-300 mb-10"><?php echo htmlspecialchars((string) ($starter['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<ul class="space-y-6 mb-12">
<?php foreach ((array) ($starter['features'] ?? []) as $feature): ?>
<li class="flex items-start gap-4 text-slate-600 dark:text-slate-300">
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
<span class="text-base font-bold leading-relaxed"><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php if ($max_sites_reached): ?>
<span class="w-full py-5 cursor-not-allowed bg-slate-100 text-slate-400 font-black text-xs uppercase tracking-[0.2em] rounded-2xl border border-slate-200 text-center active:scale-95">
                        <?php echo htmlspecialchars((string) ($starter['cta'] ?? 'Choose Starter'), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
<?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=starter" class="w-full py-5 bg-primary text-white font-black text-xs uppercase tracking-[0.2em] rounded-2xl hover:bg-primary/90 transition-all duration-300 shadow-lg active:scale-95 text-center">
                        <?php echo htmlspecialchars((string) ($starter['cta'] ?? 'Choose Starter'), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
<?php endif; ?>
</div>
<!-- Professional Plan -->
<div class="relative bg-primary p-10 md:p-14 rounded-[3rem] flex flex-col justify-between shadow-2xl shadow-primary/30 overflow-hidden group">
<div class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none">
<svg class="w-full h-full stroke-white fill-none" viewBox="0 0 100 100" preserveAspectRatio="none">
<circle cx="100" cy="0" r="90" stroke-width="0.3"></circle>
<circle cx="100" cy="0" r="70" stroke-width="0.3"></circle>
<circle cx="100" cy="0" r="50" stroke-width="0.3"></circle>
</svg>
</div>
<div class="relative z-10">
<div class="flex justify-between items-start mb-10">
<div>
<div class="inline-block bg-white/20 text-white px-3 py-1 rounded-full text-[9px] font-black tracking-[0.2em] uppercase mb-4">
                                Best Value
                            </div>
<h3 class="font-headline text-4xl text-white font-editorial italic font-normal editorial-word"><?php echo htmlspecialchars((string) ($professional['name'] ?? 'Professional'), ENT_QUOTES, 'UTF-8'); ?></h3>
</div>
<div class="w-12 h-12 rounded-2xl bg-white/10 flex items-center justify-center">
<span class="material-symbols-outlined text-white">verified</span>
</div>
</div>
<div class="flex items-baseline gap-1 mb-10">
<span class="text-6xl font-headline font-black text-white"><?php echo htmlspecialchars((string) ($professional['price'] ?? '₱2,499'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-white/60 font-bold text-lg">/mo</span>
</div>
<p class="text-sm text-white/80 mb-10"><?php echo htmlspecialchars((string) ($professional['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<ul class="space-y-6 mb-12">
<?php foreach ((array) ($professional['features'] ?? []) as $feature): ?>
<li class="flex items-start gap-4 text-white">
<span class="material-symbols-outlined text-white text-xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
<span class="text-base font-bold leading-relaxed"><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
</ul>
</div>
<div class="relative z-10">
<?php if ($max_sites_reached): ?>
<span class="w-full py-5 cursor-not-allowed bg-white/10 text-white/60 font-black text-xs uppercase tracking-[0.2em] rounded-2xl border border-white/15 text-center active:scale-95">
                            <?php echo htmlspecialchars((string) ($professional['cta'] ?? 'Choose Professional'), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
<?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=professional" class="w-full py-5 bg-white text-primary font-black text-xs uppercase tracking-[0.2em] rounded-2xl shadow-xl hover:scale-[1.03] transition-all duration-300 active:scale-95 text-center">
                            <?php echo htmlspecialchars((string) ($professional['cta'] ?? 'Choose Professional'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
<?php endif; ?>
</div>
</div>
<!-- Enterprise Plan -->
<div class="bg-white p-10 md:p-14 rounded-[3rem] border border-slate-100 flex flex-col justify-between hover:border-primary/20 transition-all duration-500 shadow-sm hover:shadow-2xl group">
<div>
<div class="flex justify-between items-start mb-10">
<div>
<span class="text-primary font-bold text-[10px] uppercase tracking-[0.4em] mb-4 block">Scalable for Teams</span>
<h3 class="font-headline text-4xl text-slate-900 dark:text-white font-editorial italic font-normal text-primary editorial-word"><?php echo htmlspecialchars((string) ($enterprise['name'] ?? 'Enterprise'), ENT_QUOTES, 'UTF-8'); ?></h3>
</div>
<div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center group-hover:bg-primary/5 transition-colors">
<span class="material-symbols-outlined text-primary">groups</span>
</div>
</div>
<div class="flex items-baseline gap-1 mb-10">
<span class="text-6xl font-headline font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) ($enterprise['price'] ?? '₱4,999'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-slate-500 dark:text-slate-400 font-bold text-lg">/mo</span>
</div>
<p class="text-sm text-slate-600 dark:text-slate-300 mb-10"><?php echo htmlspecialchars((string) ($enterprise['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<ul class="space-y-6 mb-12">
<?php foreach ((array) ($enterprise['features'] ?? []) as $feature): ?>
<li class="flex items-start gap-4 text-slate-600 dark:text-slate-300">
<span class="material-symbols-outlined text-primary text-xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
<span class="text-base font-bold leading-relaxed"><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php if ($max_sites_reached): ?>
<span class="w-full py-5 cursor-not-allowed bg-slate-100 text-slate-400 font-black text-xs uppercase tracking-[0.2em] rounded-2xl border border-slate-200 text-center active:scale-95">
                        <?php echo htmlspecialchars((string) ($enterprise['cta'] ?? 'Choose Enterprise'), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
<?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=enterprise" class="w-full py-5 bg-primary text-white font-black text-xs uppercase tracking-[0.2em] rounded-2xl hover:bg-primary/90 transition-all duration-300 shadow-lg active:scale-95 text-center">
                        <?php echo htmlspecialchars((string) ($enterprise['cta'] ?? 'Choose Enterprise'), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
<?php endif; ?>
</div>
</div>
 </section>
<!-- Final CTA -->
<section class="py-12 px-6">
<div class="mx-auto rounded-[3.5rem] bg-primary relative overflow-hidden flex flex-col items-center text-center shadow-2xl max-w-6xl py-24 px-10">
<div class="relative z-10 max-w-3xl">
<div class="inline-block px-4 py-1 rounded-full bg-white/20 text-white text-[10px] font-black uppercase tracking-[0.3em] mb-10">
                        Institutional Boarding
                    </div>
<h2 class="font-headline text-5xl font-extrabold text-white tracking-tighter leading-tight md:text-6xl mb-8">
                        Ready to curate your clinical future?
                    </h2>
<p class="text-white/70 text-xl font-bold max-w-xl mx-auto leading-relaxed mb-10">
                        Join clinics using MyDental OS to deliver premium patient experiences.
                    </p>
<div class="flex flex-col md:flex-row gap-6 justify-center">
<a href="ProviderContact.php" class="bg-white text-primary px-16 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-xl active:scale-95">
                            Request a Demo
                        </a>
<a href="Provider-HowItWorks.php" class="bg-white/10 text-white border border-white/20 px-10 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] hover:bg-white/20 transition-all">
                            Feature Guide
                        </a>
</div>
</div>
<!-- Abstract Accents -->
<div class="absolute top-0 right-0 w-1/3 h-full border-l border-white/10 pointer-events-none"></div>
<div class="absolute -right-20 -bottom-20 w-80 h-80 bg-white/5 rounded-full blur-3xl"></div>
</div>
</section>
<!-- FAQ Section -->
<section class="mx-auto max-w-3xl px-6 pb-24">
<h2 class="mb-8 text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Frequently Asked Questions</h2>
<div class="divide-y divide-primary/10 rounded-3xl border border-primary/10 bg-white/60 dark:bg-slate-900/30 backdrop-blur px-6">
<details class="group py-4" open="">
<summary class="flex cursor-pointer list-none items-center justify-between text-base font-semibold text-slate-900 dark:text-slate-100">
                            Can I switch plans later?
                            <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
</summary>
<p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            Absolutely! You can upgrade or downgrade your plan at any time from your billing dashboard. Changes take effect on your next billing cycle.
                        </p>
</details>
<details class="group py-4">
<summary class="flex cursor-pointer list-none items-center justify-between text-base font-semibold text-slate-900 dark:text-slate-100">
                            Is there a free trial?
                            <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
</summary>
<p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            Yes, we offer a 14-day full-featured free trial of the Professional plan so you can experience everything MyDental has to offer before committing.
                        </p>
</details>
<details class="group py-4">
<summary class="flex cursor-pointer list-none items-center justify-between text-base font-semibold text-slate-900 dark:text-slate-100">
                            What payment methods do you accept?
                            <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
</summary>
<p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            We accept all major credit cards, GCash, PayMaya, and bank transfers for annual subscriptions.
                        </p>
</details>
<details class="group py-4">
<summary class="flex cursor-pointer list-none items-center justify-between text-base font-semibold text-slate-900 dark:text-slate-100">
                            Is my data secure and HIPAA compliant?
                            <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
</summary>
<p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            Security is our top priority. All data is encrypted at rest and in transit, and we maintain strict adherence to healthcare privacy standards.
                        </p>
</details>
</div>
</section>
</main>
<!-- Footer -->
<footer class="w-full border-t border-slate-100 bg-white py-12 dark:bg-slate-950">
<div class="mx-auto max-w-7xl px-6 lg:px-10">
<div class="flex flex-col items-center justify-between gap-6 md:flex-row">
<div class="flex items-center gap-3">
<div class="flex h-8 w-8 items-center justify-center rounded bg-primary text-white">
<span class="material-symbols-outlined text-sm">dentistry</span>
</div>
<span class="text-sm font-bold tracking-tight text-slate-900 dark:text-slate-50">MyDental.com</span>
</div>
<p class="text-xs text-slate-500">© 2024 MyDental Solutions Inc. All rights reserved.</p>
<div class="flex gap-6">
<a class="text-xs font-semibold text-slate-500 hover:text-primary" href="#">Privacy Policy</a>
<a class="text-xs font-semibold text-slate-500 hover:text-primary" href="#">Terms of Service</a>
</div>
</div>
</div>
</footer>
</div>
</body></html>