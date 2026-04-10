<?php
/**
 * Top bar: search/custom center + notifications only (no profile — see sidebar).
 * Set $superadmin_header_center to override the default search block (HTML string).
 */
if (!isset($superadmin_header_center)) {
    $ph = isset($superadmin_header_search_placeholder)
        ? (string) $superadmin_header_search_placeholder
        : 'Search analytics, tenants, or logs...';
    $superadmin_header_center = '<div class="relative w-full max-w-md group">'
        . '<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-xl">search</span>'
        . '<input class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm transition-all placeholder:text-on-surface-variant/50" placeholder="'
        . htmlspecialchars($ph, ENT_QUOTES, 'UTF-8')
        . '" type="text"/>'
        . '</div>';
}
?>
<header class="sa-top-header fixed top-0 right-0 w-[calc(100%-16rem)] h-20 z-30 bg-white/70 backdrop-blur-xl border-b border-white/50 flex items-center justify-between px-8 transition-[width] duration-200">
<div class="flex items-center gap-6 flex-1">
<?php echo $superadmin_header_center; ?>
</div>
<div class="flex items-center gap-4">
<button class="hover:bg-surface-container-low rounded-full p-2.5 transition-all relative" type="button" aria-label="Notifications">
<span class="material-symbols-outlined text-on-surface-variant">notifications</span>
<span class="absolute top-2.5 right-2.5 w-2 h-2 bg-error rounded-full border-2 border-white"></span>
</button>
</div>
</header>
