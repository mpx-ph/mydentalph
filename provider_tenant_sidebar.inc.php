<?php
declare(strict_types=1);
/**
 * Provider tenant portal sidebar. Requires $provider_nav_active:
 * dashboard|messages|users|appointments|reports|clinical_services|subs|customize|settings
 * Uses $avatar_initials, $plan_name, $renewal_sidebar (set by dashboard or lite bootstrap).
 */
$na = isset($provider_nav_active) ? (string) $provider_nav_active : 'dashboard';

/** When this page lives under /clinic/, set to '../' so nav and logo resolve to the portal root. */
$provider_portal_path_prefix = isset($provider_portal_path_prefix) ? (string) $provider_portal_path_prefix : '';
if ($provider_portal_path_prefix !== '' && $provider_portal_path_prefix !== '../') {
    $provider_portal_path_prefix = '';
}

if (!function_exists('provider_tenant_sidebar_resolve_href')) {
    /**
     * @param string $href path from project root (e.g. ProviderTenantDashboard.php or clinic/TenantListofServices.php)
     */
    function provider_tenant_sidebar_resolve_href(string $href, string $prefix): string
    {
        if ($prefix === '') {
            return $href;
        }
        if ($href === 'clinic/TenantListofServices.php') {
            return 'TenantListofServices.php';
        }
        return $prefix . $href;
    }
}

$nav = [
    'dashboard' => ['href' => 'ProviderTenantDashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard'],
    'messages' => ['href' => 'ProviderTenantMessage.php', 'icon' => 'chat', 'label' => 'Messages'],
    'users' => ['href' => 'ProviderTenantUsers.php', 'icon' => 'group', 'label' => 'Staff & Doctors'],
    'appointments' => ['href' => 'ProviderTenantAppointments.php', 'icon' => 'calendar_month', 'label' => 'Appointments'],
    'reports' => ['href' => 'ProviderTenantReports.php', 'icon' => 'bar_chart', 'label' => 'Reports'],
    'clinical_services' => ['href' => 'clinic/TenantListofServices.php', 'icon' => 'medical_services', 'label' => 'Clinical services'],
    'subs' => ['href' => 'ProviderTenantSubs.php', 'icon' => 'payments', 'label' => 'Subscription & Billing'],
    'customize' => ['href' => 'ProviderTenantSiteBuilder.php', 'icon' => 'palette', 'label' => 'Customize'],
    'settings' => ['href' => 'ProviderTenantSettings.php', 'icon' => 'settings', 'label' => 'Settings'],
];

$pn = isset($plan_name) ? (string) $plan_name : 'MyDental';
$rs = isset($renewal_sidebar) ? (string) $renewal_sidebar : '';
?>
<style>
body.provider-shell .provider-logo-square {
    display: none;
}
@media (min-width: 1024px) {
    body.provider-shell .provider-logo-square {
        display: block;
    }
    body.provider-shell {
        --provider-collapse-duration: 460ms;
        --provider-collapse-ease: cubic-bezier(0.2, 0.85, 0.24, 1);
    }
    body.provider-shell main {
        margin-left: 16rem !important;
        transition: margin-left var(--provider-collapse-duration) var(--provider-collapse-ease);
        will-change: margin-left;
    }
    body.provider-shell .provider-top-header {
        left: 16rem;
        transition: left var(--provider-collapse-duration) var(--provider-collapse-ease);
        will-change: left;
    }
    body.provider-shell.provider-sidebar-collapsed main {
        margin-left: 5.5rem !important;
    }
    body.provider-shell.provider-sidebar-collapsed .provider-top-header {
        left: 5.5rem;
    }
    body.provider-shell #provider-sidebar {
        transition: width var(--provider-collapse-duration) var(--provider-collapse-ease);
        overflow: hidden;
        will-change: width;
    }
    body.provider-shell .provider-sidebar-divider-toggle {
        position: fixed;
        top: 44%;
        left: calc(16rem - 0.8rem);
        z-index: 55;
        width: 1.6rem;
        height: 4.25rem;
        border-radius: 9999px;
        border: 1px solid rgba(224, 233, 246, 0.95);
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 0 10px 28px -15px rgba(19, 28, 37, 0.5);
        color: #456085;
        transition: left var(--provider-collapse-duration) var(--provider-collapse-ease), color 180ms ease, background-color 180ms ease;
    }
    body.provider-shell .provider-sidebar-divider-toggle:hover {
        color: #0066ff;
        background: #ffffff;
    }
    body.provider-shell.provider-sidebar-collapsed .provider-sidebar-divider-toggle {
        left: calc(5.5rem - 0.8rem);
    }
    body.provider-shell .provider-sidebar-label,
    body.provider-shell .provider-sidebar-tagline,
    body.provider-shell .provider-sidebar-profile-text {
        max-width: 16rem;
        opacity: 1;
        transform: translateX(0);
        white-space: nowrap;
        overflow: hidden;
        transition:
            max-width 360ms var(--provider-collapse-ease),
            opacity 240ms ease,
            transform 320ms var(--provider-collapse-ease);
    }
    body.provider-shell.provider-sidebar-collapsed .provider-sidebar-label,
    body.provider-shell.provider-sidebar-collapsed .provider-sidebar-tagline,
    body.provider-shell.provider-sidebar-collapsed .provider-sidebar-profile-text {
        max-width: 0;
        opacity: 0;
        transform: translateX(-6px);
        pointer-events: none;
    }
    body.provider-shell .provider-logo-rect,
    body.provider-shell .provider-logo-square {
        transition:
            opacity 260ms ease,
            transform 360ms var(--provider-collapse-ease);
        transform-origin: left center;
    }
    body.provider-shell .provider-nav-link {
        transition: transform 240ms var(--provider-collapse-ease), color 200ms ease, background-color 200ms ease;
    }
    body.provider-shell .provider-logo-square {
        opacity: 0;
        transform: scale(0.92);
        pointer-events: none;
        position: absolute;
        left: 0;
        top: 0;
    }
    body.provider-shell.provider-sidebar-collapsed .provider-logo-rect {
        opacity: 0;
        transform: scale(0.92);
        pointer-events: none;
    }
    body.provider-shell.provider-sidebar-collapsed .provider-logo-square {
        opacity: 1;
        transform: scale(1);
        pointer-events: auto;
    }
}
@media (prefers-reduced-motion: reduce) {
    body.provider-shell main,
    body.provider-shell .provider-top-header,
    body.provider-shell #provider-sidebar,
    body.provider-shell .provider-sidebar-divider-toggle,
    body.provider-shell .provider-sidebar-label,
    body.provider-shell .provider-sidebar-tagline,
    body.provider-shell .provider-sidebar-profile-text,
    body.provider-shell .provider-logo-rect,
    body.provider-shell .provider-logo-square,
    body.provider-shell .provider-nav-link {
        transition: none !important;
    }
}
</style>
<aside id="provider-sidebar" class="fixed left-0 top-0 h-full w-64 z-40 sidebar-glass flex flex-col py-8 min-h-screen" data-purpose="navigation-sidebar">
<div class="px-7 mb-10 shrink-0">
<a href="/" class="block transition-transform duration-200 hover:scale-[1.02]" aria-label="MyDental">
<span class="relative block h-11">
<img src="<?php echo htmlspecialchars($provider_portal_path_prefix . 'MyDental%20Logo.svg', ENT_QUOTES, 'UTF-8'); ?>" alt="MyDental" width="144" height="36" loading="eager" decoding="async" class="provider-logo-rect h-11 w-auto max-w-full object-contain object-left"/>
<img src="<?php echo htmlspecialchars($provider_portal_path_prefix . 'SQUARE MYDENTAL LOGO.svg', ENT_QUOTES, 'UTF-8'); ?>" alt="MyDental" class="provider-logo-square h-11 w-11 object-contain"/>
</span>
</a>
<p class="provider-sidebar-tagline text-on-surface-variant text-[10px] font-bold tracking-[0.2em] mt-2 opacity-60 uppercase">Tenant Console</p>
</div>
<nav class="flex-1 min-h-0 space-y-1 overflow-y-auto no-scrollbar">
<?php foreach ($nav as $key => $meta): ?>
<?php
    $isActive = ($na === $key);
    $base = 'provider-nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 ease-out';
    $active = 'bg-primary/10 text-primary active-glow provider-nav-link--active font-bold';
    $idle = 'text-on-surface-variant hover:text-on-background hover:bg-white/50 font-medium';
?>
<div class="relative px-3">
<a class="<?php echo $base . ' ' . ($isActive ? $active : $idle); ?>" data-purpose="nav-item" href="<?php echo htmlspecialchars(provider_tenant_sidebar_resolve_href($meta['href'], $provider_portal_path_prefix), ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined text-[22px]"<?php echo $isActive ? ' style="font-variation-settings: \'FILL\' 1;"' : ''; ?>><?php echo htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
<span class="provider-sidebar-label font-headline text-sm tracking-tight"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
</a>
<?php if ($isActive): ?>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<div class="px-3 pt-2">
<a class="provider-nav-link flex items-center gap-3 px-4 py-3 text-error hover:text-error transition-all duration-200 hover:bg-error/10 rounded-xl font-medium" href="<?php echo htmlspecialchars($provider_portal_path_prefix . 'ProviderLogout.php', ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined text-[22px]">logout</span>
<span class="provider-sidebar-label font-headline text-sm tracking-tight">Logout</span>
</a>
</div>
</nav>
<div class="px-4 mt-auto pt-4 pb-6 lg:pb-0 shrink-0 border-t border-white/40">
<div class="bg-white/40 backdrop-blur-md rounded-2xl p-4 border border-white/60 shadow-sm transition-all duration-300 hover:shadow-md hover:border-primary/20">
<div class="provider-sidebar-profile-text min-w-0 flex-1 mb-3">
<p class="text-[10px] font-bold uppercase tracking-[0.18em] text-on-surface-variant/70 mb-1">Your Plan</p>
<p class="text-on-background text-lg font-extrabold leading-tight truncate"><?php echo htmlspecialchars($pn, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-on-surface-variant text-[12px] truncate"><?php echo htmlspecialchars($rs, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<a class="block w-full text-center py-2.5 bg-primary/10 border border-primary/20 hover:border-primary/35 text-primary text-xs font-bold rounded-xl transition-all duration-200 hover:bg-primary/15 active:scale-[0.98] shadow-sm" href="<?php echo htmlspecialchars($provider_portal_path_prefix . 'ProviderTenantSubs.php', ENT_QUOTES, 'UTF-8'); ?>">Manage Subscription</a>
</div>
</div>
</aside>
<button id="provider-sidebar-toggle" class="provider-sidebar-divider-toggle hidden lg:flex items-center justify-center" type="button" aria-label="Collapse sidebar" aria-expanded="true">
<span class="material-symbols-outlined text-[20px]">chevron_left</span>
</button>
<script>
(function () {
    var body = document.body;
    var sidebar = document.getElementById('provider-sidebar');
    var toggle = document.getElementById('provider-sidebar-toggle');
    if (!body || !sidebar || !toggle) return;
    body.classList.add('provider-shell');

    var mqDesktop = window.matchMedia('(min-width: 1024px)');
    var storageKey = 'provider.sidebarCollapsed.desktop';

    function setCollapsed(collapsed) {
        if (!mqDesktop.matches) {
            body.classList.remove('provider-sidebar-collapsed');
            sidebar.classList.remove('w-[5.5rem]');
            sidebar.classList.add('w-64');
            return;
        }
        body.classList.toggle('provider-sidebar-collapsed', collapsed);
        sidebar.classList.toggle('w-[5.5rem]', collapsed);
        sidebar.classList.toggle('w-64', !collapsed);
        sidebar.querySelectorAll('nav a.provider-nav-link').forEach(function (a) {
            a.classList.toggle('justify-center', collapsed);
            a.classList.toggle('gap-3', !collapsed);
            if (collapsed) {
                var labelNode = a.querySelector('.provider-sidebar-label');
                a.setAttribute('title', labelNode ? (labelNode.textContent || '').trim() : '');
            } else {
                a.removeAttribute('title');
            }
        });
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        var icon = toggle.querySelector('.material-symbols-outlined');
        if (icon) icon.textContent = collapsed ? 'chevron_right' : 'chevron_left';
    }

    function restoreState() {
        var collapsed = false;
        try {
            collapsed = localStorage.getItem(storageKey) === '1';
        } catch (e) {}
        setCollapsed(collapsed);
    }

    toggle.addEventListener('click', function () {
        var collapsed = !body.classList.contains('provider-sidebar-collapsed');
        setCollapsed(collapsed);
        try {
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
        } catch (e) {}
    });

    if (typeof mqDesktop.addEventListener === 'function') {
        mqDesktop.addEventListener('change', restoreState);
    } else if (typeof mqDesktop.addListener === 'function') {
        mqDesktop.addListener(restoreState);
    }

    restoreState();
})();
</script>
<?php include __DIR__ . '/provider_tenant_notify.inc.php'; ?>
