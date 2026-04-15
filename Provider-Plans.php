<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_maintenance_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/provider_auth.php';
require_once __DIR__ . '/superadmin/superadmin_settings_lib.php';

function provider_parse_plan_price_amount($raw): ?float
{
    if (is_numeric($raw)) {
        $n = (float) $raw;
        return $n >= 0 ? $n : null;
    }
    if (!is_string($raw)) {
        return null;
    }
    $cleaned = preg_replace('/[^0-9.\-]/', '', $raw);
    if (!is_string($cleaned) || $cleaned === '' || !is_numeric($cleaned)) {
        return null;
    }
    $n = (float) $cleaned;
    return $n >= 0 ? $n : null;
}

$max_sites_reached = false;
if (provider_has_authenticated_provider_session()) {
    try {
        $identity = provider_get_authenticated_provider_identity_from_session();
        $tid = $identity[0];
        $subscriptionState = provider_get_tenant_subscription_state($pdo, (string) $tid);
        $has_active = !empty($subscriptionState['has_active_subscription']);
        if ($has_active) {
            $max_sites_reached = true;
        }
    } catch (Throwable $e) {
        // Keep button routing based on helper resolution below.
    }
}

$plan_base = provider_resolve_plan_selection_redirect($pdo);
$settings = superadmin_get_settings($pdo);
$providerPlans = isset($settings['provider_plans']) && is_array($settings['provider_plans'])
    ? $settings['provider_plans']
    : superadmin_default_provider_plans();
$monthly = isset($providerPlans['monthly']) && is_array($providerPlans['monthly']) ? $providerPlans['monthly'] : [];
$yearly = isset($providerPlans['yearly']) && is_array($providerPlans['yearly']) ? $providerPlans['yearly'] : [];
$monthlyAmount = provider_parse_plan_price_amount($monthly['price'] ?? null);
$yearlyAmount = provider_parse_plan_price_amount($yearly['price'] ?? null);
$baseYearlyAmount = ($monthlyAmount !== null) ? ($monthlyAmount * 12) : null;
$yearlySavingsAmount = ($baseYearlyAmount !== null && $yearlyAmount !== null)
    ? max(0.0, $baseYearlyAmount - $yearlyAmount)
    : 11990.0;
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
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

        .mesh-gradient {
            background-color: #ffffff;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
        }
    </style>
<title>Pricing Plans | MyDental.com</title>
</head>
<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
<!-- Header / Navigation -->
<?php include 'ProviderNavbar.php'; ?>
<main class="mesh-gradient">
<!-- Hero Section -->
<section class="max-w-[1800px] mx-auto px-10 mb-12 pt-16 text-center reveal" data-reveal="section">
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
<section class="mx-auto max-w-7xl px-6 pb-4 reveal" data-reveal="section">
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
<section class="mx-auto max-w-7xl px-6 pb-20 reveal" data-reveal="section">
<div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:gap-6 items-stretch">
<!-- Monthly Plan -->
<div class="group bg-white p-10 md:p-14 rounded-[3rem] border border-slate-200/70 flex flex-col justify-between hover:border-primary/40 hover:bg-primary hover:text-white transition-all duration-500 shadow-sm hover:shadow-2xl transform-gpu hover:-translate-y-1">
<div>
<div class="flex justify-between items-start mb-10">
<div>
<span class="text-primary group-hover:text-white/90 font-bold text-[10px] uppercase tracking-[0.4em] mb-4 block">Flexible Billing</span>
<h3 class="font-headline text-4xl text-slate-900 dark:text-white group-hover:text-white font-editorial italic font-normal text-primary editorial-word"><?php echo htmlspecialchars((string) ($monthly['name'] ?? 'MONTHLY'), ENT_QUOTES, 'UTF-8'); ?></h3>
</div>
<div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center transition-colors group-hover:bg-white/10">
<span class="material-symbols-outlined text-primary group-hover:text-white">calendar_today</span>
</div>
</div>
<div class="flex items-baseline gap-1 mb-10">
<span class="text-6xl font-headline font-black text-slate-900 dark:text-white group-hover:text-white"><?php echo htmlspecialchars((string) ($monthly['price'] ?? '₱4,999'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-slate-500 dark:text-slate-400 group-hover:text-white/70 font-bold text-lg">/mo</span>
</div>
<p class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-white/80 mb-10"><?php echo htmlspecialchars((string) ($monthly['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<ul class="space-y-6 mb-12">
<?php foreach ((array) ($monthly['features'] ?? []) as $feature): ?>
<li class="flex items-start gap-4 text-slate-600 dark:text-slate-300 group-hover:text-white/90">
<span class="material-symbols-outlined text-primary group-hover:text-white text-xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
<span class="text-base font-bold leading-relaxed"><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
</ul>
</div>
<?php if ($max_sites_reached): ?>
<span class="w-full py-5 cursor-not-allowed bg-slate-100 text-slate-400 font-black text-xs uppercase tracking-[0.2em] rounded-2xl border border-slate-200 text-center active:scale-95 group-hover:bg-white/10 group-hover:text-white/60 group-hover:border-white/15">
                        <?php echo htmlspecialchars((string) ($monthly['cta'] ?? 'Choose Monthly'), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
<?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=monthly" class="w-full py-5 bg-primary text-white font-black text-xs uppercase tracking-[0.2em] rounded-2xl transition-all duration-300 shadow-lg active:scale-95 text-center hover:bg-primary/90 group-hover:bg-white group-hover:text-primary group-hover:hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                        <?php echo htmlspecialchars((string) ($monthly['cta'] ?? 'Choose Monthly'), ENT_QUOTES, 'UTF-8'); ?>
                    </a>
<?php endif; ?>
</div>
<!-- Yearly Plan -->
<div class="relative group bg-white p-10 md:p-14 rounded-[3rem] flex flex-col justify-between border border-slate-200/70 shadow-sm hover:shadow-2xl overflow-hidden hover:border-primary/40 hover:bg-primary hover:text-white transition-all duration-500 transform-gpu hover:-translate-y-1">
<div class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none transition-opacity group-hover:opacity-12">
<svg class="w-full h-full stroke-primary/30 fill-none transition-colors group-hover:stroke-white/45" viewBox="0 0 100 100" preserveAspectRatio="none">
<circle cx="100" cy="0" r="90" stroke-width="0.3"></circle>
<circle cx="100" cy="0" r="70" stroke-width="0.3"></circle>
<circle cx="100" cy="0" r="50" stroke-width="0.3"></circle>
</svg>
</div>
<div class="relative z-10">
<div class="flex justify-between items-start mb-10">
<div>
<div class="inline-block bg-emerald-100 text-emerald-700 group-hover:bg-white/20 group-hover:text-white px-3 py-1 rounded-full text-[9px] font-black tracking-[0.2em] uppercase mb-4">
                                Save 20% Promo
                            </div>
<h3 class="font-headline text-4xl text-primary group-hover:text-white font-editorial italic font-normal editorial-word"><?php echo htmlspecialchars((string) ($yearly['name'] ?? 'YEARLY'), ENT_QUOTES, 'UTF-8'); ?></h3>
</div>
<div class="w-12 h-12 rounded-2xl bg-slate-50 flex items-center justify-center transition-colors group-hover:bg-white/10">
<span class="material-symbols-outlined text-primary group-hover:text-white">workspace_premium</span>
</div>
</div>
<div class="mb-10">
<p class="text-sm font-extrabold uppercase tracking-[0.18em] text-emerald-700 group-hover:text-white/85 mb-2">Regular ₱<?php echo number_format((float) round((float) ($baseYearlyAmount ?? 59988)), 0); ?>/year</p>
<div class="flex items-baseline gap-2">
<span class="text-6xl font-headline font-black text-slate-900 dark:text-white group-hover:text-white"><?php echo htmlspecialchars((string) ($yearly['price'] ?? '₱47,998'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-slate-500 dark:text-slate-400 group-hover:text-white/70 font-bold text-lg">/year</span>
</div>
<p class="mt-2 text-sm font-semibold text-emerald-700 group-hover:text-white">Save ₱<?php echo number_format((float) round($yearlySavingsAmount), 0); ?>!</p>
</div>
<p class="text-sm text-slate-600 dark:text-slate-300 group-hover:text-white/80 mb-10"><?php echo htmlspecialchars((string) ($yearly['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<ul class="space-y-6 mb-12">
<?php foreach ((array) ($yearly['features'] ?? []) as $feature): ?>
<li class="flex items-start gap-4 text-slate-600 dark:text-slate-300 group-hover:text-white/90">
<span class="material-symbols-outlined text-primary group-hover:text-white text-xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
<span class="text-base font-bold leading-relaxed"><?php echo htmlspecialchars((string) $feature, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
</ul>
</div>
<div class="relative z-10">
<?php if ($max_sites_reached): ?>
<span class="block w-full py-5 cursor-not-allowed bg-slate-100 text-slate-400 font-black text-xs uppercase tracking-[0.2em] rounded-2xl border border-slate-200 text-center active:scale-95 group-hover:bg-white/10 group-hover:text-white/60 group-hover:border-white/15">
                            <?php echo htmlspecialchars((string) ($yearly['cta'] ?? 'Choose Yearly'), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
<?php else: ?>
<a href="<?php echo htmlspecialchars($plan_base); ?>?plan=yearly" class="block w-full py-5 bg-primary text-white font-black text-xs uppercase tracking-[0.2em] rounded-2xl shadow-xl hover:scale-[1.03] transition-all duration-300 active:scale-95 text-center hover:bg-primary/90 group-hover:bg-white group-hover:text-primary group-hover:hover:bg-white/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                            <?php echo htmlspecialchars((string) ($yearly['cta'] ?? 'Choose Yearly'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
<?php endif; ?>
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
                } else {
                    entry.target.classList.remove('is-visible');
                }
            });
        }, { threshold: 0.18, rootMargin: '0px 0px -10% 0px' });

        elements.forEach(function (el) { observer.observe(el); });
    })();
</script>
</body></html>