<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/provider_auth.php';

// Navbar display should follow the active login session directly.
// Access control and approval enforcement are handled by login + provider_auth guards.
$is_superadmin = (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'superadmin');
$has_provider_session = provider_has_authenticated_provider_session();
$logged_in = $is_superadmin || $has_provider_session;
$manage_href = 'ProviderTenantDashboard.php';
// Keep profile navigation valid even when legacy ProviderProfile.php does not exist.
$profile_href = 'ProviderTenantDashboard.php';

$user_display_name = $_SESSION['name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email']
    ?? $_SESSION['onboarding_full_name'] ?? $_SESSION['onboarding_email'] ?? 'Account';
$display_name_trimmed = trim((string) $user_display_name);
if (function_exists('mb_substr')) {
    $first_char = (string) mb_substr($display_name_trimmed, 0, 1);
} else {
    $first_char = (string) substr($display_name_trimmed, 0, 1);
}
if (function_exists('mb_strtoupper')) {
    $user_initial = (string) mb_strtoupper($first_char);
} else {
    $user_initial = (string) strtoupper($first_char);
}
if ($user_initial === '') {
    $user_initial = '?';
}
?>
<!-- Navigation -->
<style>
    /* Prevent navbar "twitch" during page navigation while Tailwind CDN loads. */
    /* No backdrop-filter here: it creates a containing block so position:fixed children
       (mobile full-screen menu) only cover the header height and the page shows through. */
    .provider-navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 64px;
        width: 100%;
        z-index: 50;
        overflow: visible;
        background: rgba(255, 255, 255, 0.94);
        border-bottom: 1px solid rgba(19, 28, 37, 0.05);
        transition: background-color 200ms ease-out;
        will-change: background-color;
    }
    .dark .provider-navbar {
        background: rgba(16, 25, 34, 0.7);
    }
    .provider-navbar-spacer {
        height: 64px;
    }

    .provider-nav-mobile__panel {
        overscroll-behavior: contain;
        min-height: 100vh;
        min-height: 100dvh;
        /* Solid layer so hero never shows through; covers full viewport once header blur is gone */
        background-color: #f7f9ff;
        background-image: linear-gradient(180deg, #edf4ff 0%, #ffffff 52%, #f7f9ff 100%);
    }

    .dark .provider-nav-mobile__panel {
        background-color: #101922;
        background-image: linear-gradient(180deg, #131c25 0%, #101922 55%, #0c1218 100%);
    }

    .provider-nav-mobile__sheet a:not(.provider-nav-mobile__cta) {
        color: #131c25;
    }

    .dark .provider-nav-mobile__sheet a:not(.provider-nav-mobile__cta) {
        color: #f7f9ff;
    }

    .provider-login-transition {
        position: fixed;
        inset: 0;
        z-index: 999;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(247, 249, 255, 0.96);
    }

    .provider-login-transition.is-visible {
        display: flex;
    }

    .provider-login-transition__card {
        width: min(520px, 100%);
        border-radius: 1.5rem;
        border: 1px solid rgba(19, 28, 37, 0.06);
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 28px 60px -24px rgba(43, 139, 235, 0.28);
        padding: 1.25rem 1.25rem 1.5rem;
    }

    .provider-login-transition__anim {
        width: 100%;
        max-width: 420px;
        aspect-ratio: 7 / 5;
        margin: 0 auto;
    }
</style>
<header class="provider-navbar fixed top-0 left-0 right-0 z-50 h-16 w-full overflow-visible border-b border-on-surface/5 bg-white/95 dark:bg-background-dark/95 transition-colors duration-200 ease-out">
<div class="mx-auto max-w-[1800px] px-6 sm:px-8 lg:px-10 h-full overflow-visible">
<div class="grid h-full grid-cols-[auto,1fr,auto] items-center overflow-visible">
<div class="flex items-center gap-3">
<img
    src="MyDental%20Logo.svg"
    alt="MyDental Logo"
    width="144"
    height="36"
    loading="eager"
    decoding="async"
    class="h-9 w-[144px]"
/>
</div>
<nav class="hidden md:flex items-center justify-self-center gap-2 lg:gap-3">
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="/">Home</a>
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="Provider-HowItWorks.php">More Features</a>
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="Provider-Plans.php">Pricing</a>
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderContact.php">Contact Us</a>
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderFAQs.php">FAQs</a>
</nav>
<div class="flex items-center justify-end gap-2 sm:gap-3 overflow-visible">
<?php if ($logged_in): ?>
<!-- Logged-in user card with dropdown -->
<div class="relative">
<details class="relative group">
<summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-on-surface/10 bg-white/70 dark:bg-slate-900/30 leading-none px-3 py-2 shadow-sm hover:border-primary/25 hover:bg-white/90 dark:hover:bg-slate-900/45 transition-all focus:outline-none focus:ring-2 focus:ring-primary/25 [&::-webkit-details-marker]:hidden">
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
<a href="<?php echo htmlspecialchars($manage_href, ENT_QUOTES, 'UTF-8'); ?>" target="_top" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface dark:text-surface hover:bg-primary/5 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg text-primary/70">dashboard</span> <span class="font-semibold">Manage</span>
</a>
<a href="<?php echo htmlspecialchars($profile_href, ENT_QUOTES, 'UTF-8'); ?>" target="_top" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface dark:text-surface hover:bg-primary/5 transition-colors rounded-xl mx-2">
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
<a href="ProviderLogin.php" data-login-transition="true" class="px-8 py-3 leading-none bg-primary text-white font-black rounded-full overflow-hidden transition-transform hover:scale-[1.02] active:scale-95 text-[11px] uppercase tracking-[0.22em] text-center transform-gpu focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 shadow-lg shadow-primary/25">
Login
</a>
<?php endif; ?>
<!-- Mobile menu: full-screen overlay (md+ uses horizontal nav) -->
<details class="provider-nav-mobile relative z-[60] md:hidden">
<summary
    class="relative flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-xl border border-on-surface/10 bg-white/80 text-on-surface shadow-sm transition-colors hover:border-primary/25 hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 dark:border-white/10 dark:bg-slate-900/40 dark:text-surface [&::-webkit-details-marker]:hidden"
    aria-label="Open menu"
    aria-expanded="false">
    <span class="material-symbols-outlined text-2xl leading-none" aria-hidden="true">menu</span>
</summary>
<div
    class="provider-nav-mobile__panel fixed inset-0 z-[200] flex min-h-0 w-screen flex-col md:hidden"
    role="dialog"
    aria-modal="true"
    aria-label="Site menu">
    <button
        type="button"
        class="absolute right-4 top-5 z-30 flex h-12 w-12 items-center justify-center rounded-full border border-on-surface/10 bg-white/90 text-on-surface shadow-md transition-colors hover:border-primary/30 hover:bg-white hover:text-primary dark:border-white/10 dark:bg-slate-900/80 dark:text-surface dark:hover:bg-slate-800"
        aria-label="Close menu">
        <span class="material-symbols-outlined text-2xl leading-none" aria-hidden="true">close</span>
    </button>
    <div class="absolute inset-0 z-0 bg-on-surface/[0.04] dark:bg-black/30" aria-hidden="true"></div>
    <div class="provider-nav-mobile__sheet relative z-10 flex min-h-0 flex-1 flex-col items-center overflow-y-auto px-8 pb-16 pt-20">
        <a href="/" class="mb-14 shrink-0 rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
            <img src="MyDental%20Logo.svg" alt="MyDental" width="160" height="40" class="h-10 w-auto" loading="lazy" decoding="async" />
        </a>
        <nav class="flex w-full max-w-md flex-col items-center gap-3 sm:gap-4" aria-label="Primary">
            <a class="font-headline w-full rounded-2xl py-4 text-center text-xl font-extrabold uppercase tracking-[0.18em] transition-colors hover:bg-primary/10 hover:text-primary sm:text-2xl" href="/">Home</a>
            <a class="font-headline w-full rounded-2xl py-4 text-center text-xl font-extrabold uppercase tracking-[0.18em] transition-colors hover:bg-primary/10 hover:text-primary sm:text-2xl" href="Provider-HowItWorks.php">More Features</a>
            <a class="font-headline w-full rounded-2xl py-4 text-center text-xl font-extrabold uppercase tracking-[0.18em] transition-colors hover:bg-primary/10 hover:text-primary sm:text-2xl" href="Provider-Plans.php">Pricing</a>
            <a class="font-headline w-full rounded-2xl py-4 text-center text-xl font-extrabold uppercase tracking-[0.18em] transition-colors hover:bg-primary/10 hover:text-primary sm:text-2xl" href="ProviderContact.php">Contact Us</a>
            <a class="font-headline w-full rounded-2xl py-4 text-center text-xl font-extrabold uppercase tracking-[0.18em] transition-colors hover:bg-primary/10 hover:text-primary sm:text-2xl" href="ProviderFAQs.php">FAQs</a>
        </nav>
        <a href="Provider-Plans.php" class="provider-nav-mobile__cta font-headline mt-12 shrink-0 rounded-full bg-primary px-12 py-4 text-sm font-extrabold uppercase tracking-[0.2em] text-white shadow-lg shadow-primary/25 transition-transform hover:scale-[1.02] active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50">
            View your plans
        </a>
    </div>
</div>
</details>
<script>
(function () {
    var root = document.querySelector('.provider-nav-mobile');
    if (!root) return;
    var summary = root.querySelector('summary');
    var panel = root.querySelector('.provider-nav-mobile__panel');
    function closeNav() {
        root.removeAttribute('open');
    }
    function setOpenState(open) {
        document.documentElement.style.overflow = open ? 'hidden' : '';
        if (summary) summary.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    root.addEventListener('toggle', function () {
        setOpenState(root.open);
    });
    if (panel) {
        panel.addEventListener('click', function () {
            closeNav();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root.open) closeNav();
    });
})();
</script>
<div class="provider-login-transition" id="provider-login-transition" aria-hidden="true">
    <div class="provider-login-transition__card">
        <div class="provider-login-transition__anim" id="provider-login-transition-anim"></div>
        <p class="text-center text-sm font-semibold tracking-wide text-slate-500">Preparing secure login...</p>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js"></script>
<script>
(function () {
    var trigger = document.querySelector('[data-login-transition="true"]');
    var overlay = document.getElementById('provider-login-transition');
    var animContainer = document.getElementById('provider-login-transition-anim');

    if (!trigger || !overlay || !animContainer) return;

    var redirected = false;
    function go(url) {
        if (redirected) return;
        redirected = true;
        window.location.href = url;
    }

    trigger.addEventListener('click', function (event) {
        var destination = trigger.getAttribute('href') || 'ProviderLogin.php';
        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        event.preventDefault();
        overlay.classList.add('is-visible');
        document.documentElement.style.overflow = 'hidden';

        if (prefersReduced || !window.lottie) {
            setTimeout(function () { go(destination); }, 150);
            return;
        }

        try {
            var animation = window.lottie.loadAnimation({
                container: animContainer,
                renderer: 'svg',
                loop: false,
                autoplay: true,
                path: 'flyingteeth1.json'
            });

            animation.addEventListener('complete', function () { go(destination); });
            setTimeout(function () { go(destination); }, 7000);
        } catch (err) {
            setTimeout(function () { go(destination); }, 150);
        }
    });
})();
</script>
</div>
</div>
</div>
</header>
<div aria-hidden="true" class="provider-navbar-spacer"></div>
