<?php
/**
 * Shared superadmin sidebar. Set $superadmin_nav before include:
 * dashboard | messages | tenantmanagement | salesreport | reports | auditlogs | backupandrestore | superadmin_approval | adddevs | settings
 */
if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}
require_once __DIR__ . '/superadmin_user.php';
require_once __DIR__ . '/superadmin_settings_lib.php';

$superadmin_nav = isset($superadmin_nav) ? (string) $superadmin_nav : 'dashboard';
$u = superadmin_current_user($pdo);
$initials = superadmin_initials($u['full_name']);
$emailDisplay = $u['email'] !== '' ? $u['email'] : ($u['username'] !== '' ? $u['username'] : '');
$saBranding = superadmin_get_settings($pdo);
$saSystemName = htmlspecialchars($saBranding['system_name'], ENT_QUOTES, 'UTF-8');
$saLogoSrc = htmlspecialchars($saBranding['brand_logo_path'], ENT_QUOTES, 'UTF-8');
$saTagline = htmlspecialchars($saBranding['brand_tagline'], ENT_QUOTES, 'UTF-8');

$navMain = [
    ['key' => 'dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard Analytics'],
    ['key' => 'messages', 'href' => 'SAMessage.php', 'icon' => 'chat', 'label' => 'Messages'],
    ['key' => 'tenantmanagement', 'href' => 'tenantmanagement.php', 'icon' => 'groups', 'label' => 'Tenant Management'],
    ['key' => 'salesreport', 'href' => 'salesreport.php', 'icon' => 'payments', 'label' => 'Sales Report'],
    ['key' => 'reports', 'href' => 'reports.php', 'icon' => 'assessment', 'label' => 'Reports'],
    ['key' => 'auditlogs', 'href' => 'auditlogs.php', 'icon' => 'history_edu', 'label' => 'Audit Logs'],
    ['key' => 'backupandrestore', 'href' => 'backupandrestore.php', 'icon' => 'settings_backup_restore', 'label' => 'Backup and Restore'],
    ['key' => 'superadmin_approval', 'href' => 'superadmin_approval.php', 'icon' => 'verified', 'label' => 'Clinic Approvals'],
    ['key' => 'adddevs', 'href' => 'adddevs.php', 'icon' => 'person_add', 'label' => 'Add Dev Accounts'],
    ['key' => 'settings', 'href' => 'settings.php', 'icon' => 'settings', 'label' => 'Settings'],
];
?>
<aside class="fixed left-0 top-0 h-full w-64 z-40 sidebar-glass flex flex-col py-8 min-h-screen">
<div class="px-7 mb-10 shrink-0">
<a href="dashboard.php" class="block" aria-label="<?php echo $saSystemName; ?>">
<img src="<?php echo $saLogoSrc; ?>" alt="<?php echo $saSystemName; ?>" class="h-11 w-auto max-w-full object-contain object-left"/>
</a>
<p class="text-on-surface-variant text-[10px] font-bold tracking-[0.2em] mt-2 opacity-60"><?php echo $saTagline; ?></p>
</div>
<nav class="flex-1 min-h-0 space-y-1 overflow-y-auto no-scrollbar">
<?php foreach ($navMain as $it):
    $active = $superadmin_nav === $it['key'];
    $href = htmlspecialchars($it['href'], ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars($it['icon'], ENT_QUOTES, 'UTF-8');
    if ($active):
        ?>
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="<?php echo $href; ?>">
<span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;"><?php echo $icon; ?></span>
<span class="font-headline text-sm font-bold tracking-tight"><?php echo $label; ?></span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
</div>
<?php else: ?>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="<?php echo $href; ?>">
<span class="material-symbols-outlined text-[22px]"><?php echo $icon; ?></span>
<span class="font-headline text-sm font-medium tracking-tight"><?php echo $label; ?></span>
</a>
</div>
<?php
    endif;
endforeach;
?>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-error hover:text-error transition-colors duration-200 hover:bg-error/10 rounded-xl" href="../ProviderLogout.php">
<span class="material-symbols-outlined text-[22px]">logout</span>
<span class="font-headline text-sm font-medium tracking-tight">Logout</span>
</a>
</div>
</nav>
<div class="px-4 mt-auto pt-4 shrink-0 border-t border-white/40">
<div class="bg-white/40 backdrop-blur-md rounded-2xl p-4 border border-white/60 shadow-sm">
<div class="flex items-center gap-3 min-w-0">
<?php if ($u['photo'] !== ''): ?>
<img src="<?php echo htmlspecialchars($u['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm shrink-0"/>
<?php else: ?>
<div class="w-10 h-10 rounded-full bg-primary/15 flex items-center justify-center text-primary text-xs font-bold border-2 border-white shadow-sm shrink-0"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<div class="min-w-0 flex-1">
<p class="text-on-surface text-sm font-bold truncate"><?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($emailDisplay !== ''): ?>
<p class="text-on-surface-variant text-[11px] truncate"><?php echo htmlspecialchars($emailDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60 mt-0.5">Superadmin</p>
</div>
</div>
</div>
</div>
</aside>
