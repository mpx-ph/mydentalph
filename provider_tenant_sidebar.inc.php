<?php
declare(strict_types=1);
/**
 * Provider tenant portal sidebar. Requires $provider_nav_active:
 * dashboard|messages|users|appointments|clinical_services|subs|customize|settings
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
    'clinical_services' => ['href' => 'clinic/TenantListofServices.php', 'icon' => 'medical_services', 'label' => 'Clinical services'],
    'subs' => ['href' => 'ProviderTenantSubs.php', 'icon' => 'payments', 'label' => 'Subscription & Billing'],
    'customize' => ['href' => 'ProviderTenantSiteBuilder.php', 'icon' => 'palette', 'label' => 'Customize'],
    'settings' => ['href' => 'ProviderTenantSettings.php', 'icon' => 'settings', 'label' => 'Settings'],
];

$ai = isset($avatar_initials) ? (string) $avatar_initials : 'MD';
$pn = isset($plan_name) ? (string) $plan_name : 'MyDental';
$rs = isset($renewal_sidebar) ? (string) $renewal_sidebar : '';
?>
<aside class="fixed left-0 top-0 h-full w-64 z-40 sidebar-glass flex flex-col py-8 min-h-screen" data-purpose="navigation-sidebar">
<div class="px-7 mb-10 shrink-0">
<a href="/" class="block transition-transform duration-200 hover:scale-[1.02]" aria-label="MyDental">
<img src="<?php echo htmlspecialchars($provider_portal_path_prefix . 'MyDental%20Logo.svg', ENT_QUOTES, 'UTF-8'); ?>" alt="MyDental" width="144" height="36" loading="eager" decoding="async" class="h-11 w-auto max-w-full object-contain object-left"/>
</a>
<p class="text-on-surface-variant text-[10px] font-bold tracking-[0.2em] mt-2 opacity-60 uppercase">Provider Console</p>
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
<span class="font-headline text-sm tracking-tight"><?php echo htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8'); ?></span>
</a>
<?php if ($isActive): ?>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
<div class="px-3 pt-2">
<a class="provider-nav-link flex items-center gap-3 px-4 py-3 text-error hover:text-error transition-all duration-200 hover:bg-error/10 rounded-xl font-medium" href="<?php echo htmlspecialchars($provider_portal_path_prefix . 'ProviderLogout.php', ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined text-[22px]">logout</span>
<span class="font-headline text-sm tracking-tight">Logout</span>
</a>
</div>
</nav>
<div class="px-4 mt-auto pt-4 shrink-0 border-t border-white/40">
<div class="bg-white/40 backdrop-blur-md rounded-2xl p-4 border border-white/60 shadow-sm transition-all duration-300 hover:shadow-md hover:border-primary/20">
<div class="flex items-center gap-3 min-w-0 mb-3">
<div class="w-10 h-10 rounded-full bg-primary/15 flex items-center justify-center text-primary text-xs font-bold border-2 border-white shadow-sm shrink-0"><?php echo htmlspecialchars($ai, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="min-w-0 flex-1">
<p class="text-on-background text-sm font-bold truncate"><?php echo htmlspecialchars($pn, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-on-surface-variant text-[11px] truncate"><?php echo htmlspecialchars($rs, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
</div>
<a class="block w-full text-center py-2.5 bg-white/80 border border-white hover:border-primary/30 text-on-background text-xs font-bold rounded-xl transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] shadow-sm" href="<?php echo htmlspecialchars($provider_portal_path_prefix . 'ProviderContact.php', ENT_QUOTES, 'UTF-8'); ?>">Support Portal</a>
</div>
</div>
</aside>
