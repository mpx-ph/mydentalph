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
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If session has a user_id, verify the user still exists in DB (e.g. after a DB reset)
// Also treat onboarding (onboarding_user_id) as "logged in" so Purchase/Setup show the account
// user_id can be 0 for hardcoded superadmin — empty() treats 0 as empty, so handle superadmin first
$logged_in = false;
$is_superadmin = (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'superadmin');
if ($is_superadmin) {
    $logged_in = true;
} elseif (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/db.php';
        $stmt = $pdo->prepare("SELECT 1 FROM tbl_users WHERE user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $logged_in = true;
        } else {
            // User no longer exists or is inactive — clear session so navbar shows Login
            unset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['full_name'], $_SESSION['role'], $_SESSION['is_owner']);
        }
    } catch (Throwable $e) {
        // On DB error, clear session to avoid showing stale logged-in state
        unset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['full_name'], $_SESSION['role'], $_SESSION['is_owner']);
    }
}
if (!$logged_in && (!empty($_SESSION['onboarding_user_id']) || !empty($_SESSION['onboarding_pending_id']))) {
    $logged_in = true;
}

$user_display_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email']
    ?? $_SESSION['onboarding_full_name'] ?? $_SESSION['onboarding_email'] ?? 'Account';
$user_initial = mb_strtoupper(mb_substr(trim($user_display_name), 0, 1)) ?: '?';
?>
<!-- Navigation -->
<header style="position: sticky; top: 0; z-index: 50;" class="sticky top-0 z-50 w-full border-b border-on-surface/5 bg-white/70 dark:bg-background-dark/70 backdrop-blur-xl">
<div class="mx-auto max-w-[1800px] px-6 sm:px-8 lg:px-10">
<div class="flex h-16 items-center justify-between">
<div class="flex items-center gap-3">
<img src="MyDental%20Logo.svg" alt="MyDental Logo" class="h-9 w-auto" />
</div>
<nav class="hidden md:flex items-center gap-2 lg:gap-3">
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderMain.php">Home</a>
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="Provider-HowItWorks.php">More Features</a>
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="Provider-Plans.php">Pricing</a>
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderContact.php">Contact Us</a>
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderFAQs.php">FAQs</a>
</nav>
<div class="flex items-center gap-3">
<?php if ($logged_in): ?>
<!-- Logged-in user card with dropdown -->
<div class="relative">
<details class="relative group">
<summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-on-surface/10 bg-white/70 dark:bg-slate-900/30 px-3 py-2 shadow-sm hover:border-primary/25 hover:bg-white/90 dark:hover:bg-slate-900/45 transition-all focus:outline-none focus:ring-2 focus:ring-primary/25 [&::-webkit-details-marker]:hidden">
<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary text-sm font-black"><?php echo htmlspecialchars($user_initial); ?></span>
<span class="hidden sm:inline text-[12px] font-black uppercase tracking-[0.12em] text-on-surface/70 dark:text-surface/80 max-w-[160px] truncate"><?php echo htmlspecialchars($user_display_name); ?></span>
<span class="material-symbols-outlined text-on-surface/40 dark:text-surface/50 text-lg transition-transform group-open:rotate-180">expand_more</span>
</summary>
<div class="absolute right-0 top-full z-50 mt-2 min-w-[200px] rounded-2xl border border-on-surface/10 bg-white/90 dark:bg-slate-900/80 py-2 shadow-xl backdrop-blur-xl">
<?php if ($is_superadmin): ?>
<a href="superadmin/dashboard.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface dark:text-surface hover:bg-primary/5 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg text-primary/70">admin_panel_settings</span> <span class="font-semibold">Super Admin</span>
</a>
<?php else: ?>
<a href="ProviderTenantDashboard.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface dark:text-surface hover:bg-primary/5 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg text-primary/70">dashboard</span> <span class="font-semibold">Manage</span>
</a>
<a href="ProviderProfile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface dark:text-surface hover:bg-primary/5 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg text-primary/70">person</span> <span class="font-semibold">My Profile</span>
</a>
<?php endif; ?>
<div class="my-2 border-t border-on-surface/10"></div>
<a href="ProviderLogout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50/80 dark:hover:bg-red-900/20 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg">logout</span> <span class="font-semibold">Log Out</span>
</a>
</div>
</details>
</div>
<?php else: ?>
<a href="ProviderLogin.php" class="px-8 py-3 bg-primary text-white font-black rounded-full overflow-hidden transition-transform hover:scale-[1.02] active:scale-95 text-[11px] uppercase tracking-[0.22em] text-center transform-gpu focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 shadow-lg shadow-primary/25">
Login
</a>
<?php endif; ?>
</div>
</div>
</div>
</header>
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
<h3 class="text-lg font-bold text-primary uppercase tracking-wider"><?php echo htmlspecialchars((string) ($starter['name'] ?? 'Starter'), ENT_QUOTES, 'UTF-8'); ?></h3>
<div class="mt-4 flex items-baseline gap-1">
<span class="text-4xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) ($starter['price'] ?? '₱999'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-sm font-semibold text-slate-500">/mo</span>
</div>
<p class="mt-4 text-sm text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars((string) ($starter['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php if ($max_sites_reached): ?>
<span class="mb-8 flex w-full cursor-not-allowed items-center justify-center rounded-xl border-2 border-slate-200 bg-slate-100 py-3 text-sm font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500"><?php echo htmlspecialchars((string) ($starter['cta'] ?? 'Choose Starter'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=starter" class="mb-8 flex w-full items-center justify-center rounded-xl border-2 border-primary/20 bg-primary/5 py-3 text-sm font-bold text-primary hover:bg-primary hover:text-white transition-all">
                            <?php echo htmlspecialchars((string) ($starter['cta'] ?? 'Choose Starter'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php endif; ?>
<ul class="flex-1 space-y-4">
<?php foreach ((array) ($starter['features'] ?? []) as $feature): ?>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
</ul>
</div>
<!-- Professional Plan -->
<div class="relative flex flex-col rounded-2xl border-2 border-primary bg-white p-8 shadow-xl shadow-primary/10 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/20 scale-105 z-10 dark:bg-slate-900">
<div class="absolute -top-4 left-1/2 -translate-x-1/2 rounded-full bg-primary px-4 py-1 text-xs font-bold text-white uppercase tracking-widest">
                            Most Popular
                        </div>
<div class="mb-8">
<h3 class="text-lg font-bold text-primary uppercase tracking-wider"><?php echo htmlspecialchars((string) ($professional['name'] ?? 'Professional'), ENT_QUOTES, 'UTF-8'); ?></h3>
<div class="mt-4 flex items-baseline gap-1">
<span class="text-4xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) ($professional['price'] ?? '₱2,499'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-sm font-semibold text-slate-500">/mo</span>
</div>
<p class="mt-4 text-sm text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars((string) ($professional['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php if ($max_sites_reached): ?>
<span class="mb-8 flex w-full cursor-not-allowed items-center justify-center rounded-xl border-2 border-slate-200 bg-slate-100 py-3 text-sm font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500"><?php echo htmlspecialchars((string) ($professional['cta'] ?? 'Choose Professional'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=professional" class="mb-8 flex w-full items-center justify-center rounded-xl bg-primary py-3 text-sm font-bold text-white shadow-lg shadow-primary/30 hover:bg-primary/90 transition-all">
                            <?php echo htmlspecialchars((string) ($professional['cta'] ?? 'Choose Professional'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php endif; ?>
<ul class="flex-1 space-y-4">
<?php foreach ((array) ($professional['features'] ?? []) as $feature): ?>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
</ul>
</div>
<!-- Enterprise Plan -->
<div class="flex flex-col rounded-2xl border border-primary/10 bg-white p-8 shadow-sm transition-all duration-300 hover:-translate-y-2 hover:shadow-xl hover:shadow-primary/10 dark:bg-slate-900/50">
<div class="mb-8">
<h3 class="text-lg font-bold text-primary uppercase tracking-wider"><?php echo htmlspecialchars((string) ($enterprise['name'] ?? 'Enterprise'), ENT_QUOTES, 'UTF-8'); ?></h3>
<div class="mt-4 flex items-baseline gap-1">
<span class="text-4xl font-black text-slate-900 dark:text-white"><?php echo htmlspecialchars((string) ($enterprise['price'] ?? '₱4,999'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-sm font-semibold text-slate-500">/mo</span>
</div>
<p class="mt-4 text-sm text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars((string) ($enterprise['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php if ($max_sites_reached): ?>
<span class="mb-8 flex w-full cursor-not-allowed items-center justify-center rounded-xl border-2 border-slate-200 bg-slate-100 py-3 text-sm font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-500"><?php echo htmlspecialchars((string) ($enterprise['cta'] ?? 'Choose Enterprise'), ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=enterprise" class="mb-8 flex w-full items-center justify-center rounded-xl border-2 border-primary/20 bg-primary/5 py-3 text-sm font-bold text-primary hover:bg-primary hover:text-white transition-all">
                            <?php echo htmlspecialchars((string) ($enterprise['cta'] ?? 'Choose Enterprise'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php endif; ?>
<ul class="flex-1 space-y-4">
<?php foreach ((array) ($enterprise['features'] ?? []) as $feature): ?>
<li class="flex items-start gap-3 text-sm">
<span class="material-symbols-outlined text-primary text-xl">check_circle</span>
<span class="text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
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