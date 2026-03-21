<?php
session_start();
require_once __DIR__ . '/../db.php';

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
?>
<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&amp;display=swap" rel="stylesheet"/>
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
              "display": ["Manrope"]
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
          },
        },
      }
    </script>
<title>Pricing Plans | MyDental.com</title>
</head>
<body class="bg-background-light dark:bg-background-dark font-display text-slate-900 dark:text-slate-100 transition-colors duration-200">
<div class="relative flex min-h-screen flex-col">
<!-- Header / Navigation -->
<?php include 'ProviderNavbar.php'; ?>
<main class="flex-grow">
<!-- Hero Section -->
<section class="mx-auto max-w-5xl px-6 py-16 text-center lg:py-24">
<h1 class="text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white sm:text-6xl">
                    Choose Your Plan
                </h1>
<p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600 dark:text-slate-400">
                    Affordable dental management solutions designed to help your practice grow. From solo practitioners to large multi-clinic enterprises.
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
<section class="mx-auto max-w-7xl px-6 pb-24">
<div class="grid grid-cols-1 gap-8 md:grid-cols-3 lg:gap-6">
<!-- Starter Plan -->
<div class="flex flex-col rounded-2xl border border-primary/10 bg-white p-8 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-primary/10 dark:bg-slate-900/50">
<div class="mb-8">
<h3 class="text-lg font-bold text-primary uppercase tracking-wider">Starter</h3>
<div class="mt-4 flex items-baseline gap-1">
<span class="text-4xl font-black text-slate-900 dark:text-white">₱999</span>
<span class="text-sm font-semibold text-slate-500">/mo</span>
</div>
<p class="mt-4 text-sm text-slate-600 dark:text-slate-400">Essential tools for independent clinics starting their digital journey.</p>
</div>
<?php if ($max_sites_reached): ?>
<span class="mb-8 flex w-full cursor-not-allowed items-center justify-center rounded-xl border-2 border-slate-200 bg-slate-100 py-3 text-sm font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">Choose Starter</span>
                        <?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=starter" class="mb-8 flex w-full items-center justify-center rounded-xl border-2 border-primary/20 bg-primary/5 py-3 text-sm font-bold text-primary hover:bg-primary hover:text-white transition-all">
                            Choose Starter
                        </a>
                        <?php endif; ?>
<ul class="flex-1 space-y-4">
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Unlimited Patient Records</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Basic Calendar Scheduling</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Email Support</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Treatment Charting</span>
</li>
</ul>
</div>
<!-- Professional Plan -->
<div class="relative flex flex-col rounded-2xl border-2 border-primary bg-white p-8 shadow-xl shadow-primary/10 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/20 scale-105 z-10 dark:bg-slate-900">
<div class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-primary px-4 py-1 text-xs font-bold text-white uppercase tracking-widest">
                            Most Popular
                        </div>
<div class="mb-8">
<h3 class="text-lg font-bold text-primary uppercase tracking-wider">Professional</h3>
<div class="mt-4 flex items-baseline gap-1">
<span class="text-4xl font-black text-slate-900 dark:text-white">₱2,499</span>
<span class="text-sm font-semibold text-slate-500">/mo</span>
</div>
<p class="mt-4 text-sm text-slate-600 dark:text-slate-400">Comprehensive features for busy practices looking to automate.</p>
</div>
<?php if ($max_sites_reached): ?>
<span class="mb-8 flex w-full cursor-not-allowed items-center justify-center rounded-xl border-2 border-slate-200 bg-slate-100 py-3 text-sm font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">Choose Professional</span>
                        <?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=professional" class="mb-8 flex w-full items-center justify-center rounded-xl bg-primary py-3 text-sm font-bold text-white shadow-lg shadow-primary/30 hover:bg-primary/90 transition-all">
                            Choose Professional
                        </a>
                        <?php endif; ?>
<ul class="flex-1 space-y-4">
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300 font-semibold">Everything in Starter</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Advanced Analytics Dashboards</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Automated SMS Reminders</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">24/7 Priority Support</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Multi-user Access Control</span>
</li>
</ul>
</div>
<!-- Enterprise Plan -->
<div class="flex flex-col rounded-2xl border border-primary/10 bg-white p-8 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-primary/10 dark:bg-slate-900/50">
<div class="mb-8">
<h3 class="text-lg font-bold text-primary uppercase tracking-wider">Enterprise</h3>
<div class="mt-4 flex items-baseline gap-1">
<span class="text-4xl font-black text-slate-900 dark:text-white">₱4,999</span>
<span class="text-sm font-semibold text-slate-500">/mo</span>
</div>
<p class="mt-4 text-sm text-slate-600 dark:text-slate-400">Customized solutions for dental networks and multi-branch clinics.</p>
</div>
<?php if ($max_sites_reached): ?>
<span class="mb-8 flex w-full cursor-not-allowed items-center justify-center rounded-xl border-2 border-slate-200 bg-slate-100 py-3 text-sm font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500">Choose Enterprise</span>
                        <?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=enterprise" class="mb-8 flex w-full items-center justify-center rounded-xl border-2 border-primary/20 bg-primary/5 py-3 text-sm font-bold text-primary hover:bg-primary hover:text-white transition-all">
                            Choose Enterprise
                        </a>
                        <?php endif; ?>
<ul class="flex-1 space-y-4">
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300 font-semibold">Everything in Professional</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Custom API Integrations</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Unlimited Cloud Storage</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">Dedicated Account Manager</span>
</li>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300">White-label Patient Portal</span>
</li>
</ul>
</div>
</div>
</section>
<!-- FAQ Section -->
<section class="mx-auto max-w-3xl px-6 pb-24">
<h2 class="mb-8 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Frequently Asked Questions</h2>
<div class="divide-y divide-primary/10">
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
<footer class="border-t border-primary/10 bg-white py-12 dark:bg-slate-950">
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