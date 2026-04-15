<?php
/**
 * Shared superadmin sidebar. Set $superadmin_nav before include:
 * dashboard | messages | tenantmanagement | salesreport | reports | auditlogs | superadmin_approval | adddevs | settings
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
$saSquareLogoSrc = htmlspecialchars('SQUARE MYDENTAL LOGO.svg', ENT_QUOTES, 'UTF-8');
$saTagline = htmlspecialchars($saBranding['brand_tagline'], ENT_QUOTES, 'UTF-8');

$navMain = [
    ['key' => 'dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'Dashboard Analytics'],
    ['key' => 'messages', 'href' => 'SAMessage.php', 'icon' => 'chat', 'label' => 'Messages'],
    ['key' => 'tenantmanagement', 'href' => 'tenantmanagement.php', 'icon' => 'groups', 'label' => 'Tenant Management'],
    ['key' => 'salesreport', 'href' => 'salesreport.php', 'icon' => 'payments', 'label' => 'Sales Report'],
    ['key' => 'reports', 'href' => 'reports.php', 'icon' => 'assessment', 'label' => 'Reports'],
    ['key' => 'auditlogs', 'href' => 'auditlogs.php', 'icon' => 'history_edu', 'label' => 'Audit Logs'],
    ['key' => 'superadmin_approval', 'href' => 'superadmin_approval.php', 'icon' => 'verified', 'label' => 'Clinic Approvals'],
    ['key' => 'adddevs', 'href' => 'adddevs.php', 'icon' => 'person_add', 'label' => 'Add Dev Accounts'],
    ['key' => 'settings', 'href' => 'settings.php', 'icon' => 'settings', 'label' => 'Settings'],
];
?>
<style>
body.superadmin-shell .sa-logo-square {
    display: none;
}
@media (min-width: 1024px) {
    body.superadmin-shell main {
        margin-left: 16rem;
        transition: margin-left 340ms cubic-bezier(0.22, 1, 0.36, 1);
    }
    body.superadmin-shell .sa-top-header {
        width: calc(100% - 16rem);
        transition: width 340ms cubic-bezier(0.22, 1, 0.36, 1);
    }
    body.superadmin-shell.sa-sidebar-collapsed main {
        margin-left: 5.5rem !important;
    }
    body.superadmin-shell.sa-sidebar-collapsed .sa-top-header {
        width: calc(100% - 5.5rem);
    }
    body.superadmin-shell #superadmin-sidebar {
        transition: width 340ms cubic-bezier(0.22, 1, 0.36, 1);
        overflow: hidden;
    }
    body.superadmin-shell .sa-sidebar-divider-toggle {
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
        transition: left 340ms cubic-bezier(0.22, 1, 0.36, 1), color 180ms ease, background-color 180ms ease;
    }
    body.superadmin-shell .sa-sidebar-divider-toggle:hover {
        color: #0066ff;
        background: #ffffff;
    }
    body.superadmin-shell.sa-sidebar-collapsed .sa-sidebar-divider-toggle {
        left: calc(5.5rem - 0.8rem);
    }
    body.superadmin-shell .sa-sidebar-label,
    body.superadmin-shell .sa-sidebar-tagline,
    body.superadmin-shell .sa-sidebar-profile-text {
        max-width: 16rem;
        opacity: 1;
        transform: translateX(0);
        white-space: nowrap;
        overflow: hidden;
        transition: max-width 300ms cubic-bezier(0.22, 1, 0.36, 1), opacity 180ms ease, transform 220ms ease;
    }
    body.superadmin-shell.sa-sidebar-collapsed .sa-sidebar-label,
    body.superadmin-shell.sa-sidebar-collapsed .sa-sidebar-tagline,
    body.superadmin-shell.sa-sidebar-collapsed .sa-sidebar-profile-text {
        max-width: 0;
        opacity: 0;
        transform: translateX(-6px);
        pointer-events: none;
    }
    body.superadmin-shell .sa-logo-rect,
    body.superadmin-shell .sa-logo-square {
        transition: opacity 220ms ease, transform 300ms cubic-bezier(0.22, 1, 0.36, 1);
        transform-origin: left center;
    }
    body.superadmin-shell .sa-logo-square {
        display: block;
        opacity: 0;
        transform: scale(0.92);
        pointer-events: none;
        position: absolute;
        left: 0;
        top: 0;
    }
    body.superadmin-shell.sa-sidebar-collapsed .sa-logo-rect {
        opacity: 0;
        transform: scale(0.92);
        pointer-events: none;
    }
    body.superadmin-shell.sa-sidebar-collapsed .sa-logo-square {
        opacity: 1;
        transform: scale(1);
        pointer-events: auto;
    }
}
</style>
<aside id="superadmin-sidebar" class="fixed left-0 top-0 h-full w-64 z-40 sidebar-glass flex flex-col py-8 min-h-screen transition-all duration-200">
<div class="px-7 mb-10 shrink-0">
<a href="dashboard.php" class="block" aria-label="<?php echo $saSystemName; ?>">
<span class="relative block h-11">
<img src="<?php echo $saLogoSrc; ?>" alt="<?php echo $saSystemName; ?>" class="sa-logo-rect h-11 w-auto max-w-full object-contain object-left"/>
<img src="<?php echo $saSquareLogoSrc; ?>" alt="<?php echo $saSystemName; ?>" class="sa-logo-square h-11 w-11 object-contain"/>
</span>
</a>
<p class="sa-sidebar-tagline text-on-surface-variant text-[10px] font-bold tracking-[0.2em] mt-2 opacity-60"><?php echo $saTagline; ?></p>
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
<span class="sa-sidebar-label font-headline text-sm font-bold tracking-tight"><?php echo $label; ?></span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
</div>
<?php else: ?>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3 text-on-surface-variant hover:text-on-surface transition-colors duration-200 hover:bg-white/50 rounded-xl" href="<?php echo $href; ?>">
<span class="material-symbols-outlined text-[22px]"><?php echo $icon; ?></span>
<span class="sa-sidebar-label font-headline text-sm font-medium tracking-tight"><?php echo $label; ?></span>
</a>
</div>
<?php
    endif;
endforeach;
?>
</nav>
<div class="px-4 mt-auto pt-4 shrink-0 border-t border-white/40">
<div class="bg-white/40 backdrop-blur-md rounded-2xl p-4 border border-white/60 shadow-sm">
<div class="flex items-center gap-3 min-w-0">
<?php if ($u['photo'] !== ''): ?>
<img src="<?php echo htmlspecialchars($u['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm shrink-0"/>
<?php else: ?>
<div class="w-10 h-10 rounded-full bg-primary/15 flex items-center justify-center text-primary text-xs font-bold border-2 border-white shadow-sm shrink-0"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<div class="sa-sidebar-profile-text min-w-0 flex-1">
<p class="text-on-surface text-sm font-bold truncate"><?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($emailDisplay !== ''): ?>
<p class="text-on-surface-variant text-[11px] truncate"><?php echo htmlspecialchars($emailDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60 mt-0.5">Superadmin</p>
</div>
</div>
<div class="mt-3 grid grid-cols-2 gap-2">
<button type="button" id="sa-open-edit-profile" class="sa-profile-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-primary/20 bg-primary/10 text-primary px-2.5 py-2 text-[11px] font-bold tracking-wide uppercase hover:bg-primary/15 transition-colors">
<span class="material-symbols-outlined text-[16px]">edit_square</span>
<span class="sa-sidebar-profile-text">Edit Profile</span>
</button>
<a href="../ProviderLogout.php" class="sa-profile-action-btn flex items-center justify-center gap-1.5 rounded-lg border border-error/20 bg-error/10 text-error px-2.5 py-2 text-[11px] font-bold tracking-wide uppercase hover:bg-error/15 transition-colors">
<span class="material-symbols-outlined text-[16px]">logout</span>
<span class="sa-sidebar-profile-text">Logout</span>
</a>
</div>
</div>
</div>
</aside>
<div id="sa-edit-profile-modal" class="fixed inset-0 z-[120] hidden" aria-hidden="true">
<div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" data-sa-modal-close></div>
<div class="relative min-h-full w-full flex items-center justify-center p-4 sm:p-6">
<div class="w-full max-w-3xl overflow-hidden rounded-3xl border border-white/70 bg-white/95 shadow-[0_26px_80px_-28px_rgba(15,23,42,0.65)]">
<div class="px-5 sm:px-7 pt-6 sm:pt-7 pb-5 bg-gradient-to-r from-[#eef4ff] via-[#f8fbff] to-[#f0f7ff] border-b border-slate-100">
<div class="flex items-start justify-between gap-3">
<div>
<h3 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-on-surface">EDIT PROFILE</h3>
<p class="text-sm text-on-surface-variant mt-1.5">Update your Profile Details</p>
</div>
<button type="button" class="h-10 w-10 rounded-full border border-slate-200 bg-white text-on-surface-variant hover:text-on-surface hover:bg-slate-100 transition-colors flex items-center justify-center shadow-sm" aria-label="Close edit profile modal" data-sa-modal-close>
<span class="material-symbols-outlined text-[20px]">close</span>
</button>
</div>
</div>
<form class="px-5 sm:px-7 pb-5 sm:pb-7 space-y-5" method="post" action="" enctype="multipart/form-data" autocomplete="off">
<div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
<div class="flex items-center gap-3">
<?php if ($u['photo'] !== ''): ?>
<img src="<?php echo htmlspecialchars($u['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow shrink-0"/>
<?php else: ?>
<div class="w-14 h-14 rounded-full bg-primary/15 text-primary flex items-center justify-center text-sm font-bold border-2 border-white shadow shrink-0"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<div class="min-w-0">
<p class="text-sm font-bold text-on-surface truncate"><?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-xs text-on-surface-variant truncate"><?php echo htmlspecialchars($emailDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[11px] text-primary font-semibold mt-0.5 uppercase tracking-wide">Superadmin Account</p>
</div>
</div>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 space-y-4">
<p class="text-[11px] font-bold tracking-[0.15em] uppercase text-on-surface-variant">Profile Details</p>
<div>
<label for="sa-profile-photo" class="block text-[11px] font-bold tracking-[0.14em] uppercase text-on-surface-variant mb-2">PHOTO</label>
<input id="sa-profile-photo" name="profile_photo" type="file" accept="image/*" class="block w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-on-surface file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:opacity-95 focus:outline-none focus:ring-2 focus:ring-primary/30"/>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label for="sa-profile-fullname" class="block text-[11px] font-bold tracking-[0.14em] uppercase text-on-surface-variant mb-2">FULL NAME</label>
<input id="sa-profile-fullname" name="full_name" type="text" value="<?php echo htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-on-surface placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/40"/>
</div>
<div>
<label for="sa-profile-username" class="block text-[11px] font-bold tracking-[0.14em] uppercase text-on-surface-variant mb-2">USERNAME</label>
<input id="sa-profile-username" name="username" type="text" value="<?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-on-surface placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/40"/>
</div>
</div>
<div>
<label for="sa-profile-email" class="block text-[11px] font-bold tracking-[0.14em] uppercase text-on-surface-variant mb-2">EMAIL</label>
<input id="sa-profile-email" name="email" type="email" value="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-on-surface placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/40"/>
<p class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-[11px] text-amber-800">
<span class="material-symbols-outlined text-[14px]">mark_email_read</span>
<span>Email changes require OTP verification sent to the updated email address.</span>
</p>
</div>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 space-y-4">
<p class="text-[11px] font-bold tracking-[0.15em] uppercase text-on-surface-variant">Security</p>
<div>
<label for="sa-profile-current-password" class="block text-[11px] font-bold tracking-[0.14em] uppercase text-on-surface-variant mb-2">CURRENT PASSWORD</label>
<input id="sa-profile-current-password" name="current_password" type="password" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-on-surface placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/40"/>
<p class="text-[11px] text-on-surface-variant mt-1.5">Required for email or password changes.</p>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label for="sa-profile-new-password" class="block text-[11px] font-bold tracking-[0.14em] uppercase text-on-surface-variant mb-2">NEW PASSWORD</label>
<input id="sa-profile-new-password" name="new_password" type="password" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-on-surface placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/40"/>
<p class="text-[11px] text-on-surface-variant mt-1.5">Only required when changing your password.</p>
</div>
<div>
<label for="sa-profile-confirm-password" class="block text-[11px] font-bold tracking-[0.14em] uppercase text-on-surface-variant mb-2">CONFIRM NEW PASSWORD</label>
<input id="sa-profile-confirm-password" name="confirm_new_password" type="password" class="w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 py-2.5 text-sm text-on-surface placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/40"/>
</div>
</div>
</div>
<div class="pt-1 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-2.5">
<button type="button" class="rounded-xl border border-slate-300 bg-white px-4.5 py-2.5 text-sm font-semibold text-on-surface-variant hover:text-on-surface hover:bg-slate-100 transition-colors" data-sa-modal-close>Cancel</button>
<button type="submit" class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold shadow-[0_12px_24px_-14px_rgba(0,102,255,0.8)] hover:opacity-95 transition-opacity">Save Changes</button>
</div>
</form>
</div>
</div>
</div>
<button id="superadmin-sidebar-toggle" class="sa-sidebar-divider-toggle hidden lg:flex items-center justify-center" type="button" aria-label="Collapse sidebar" aria-expanded="true">
<span class="material-symbols-outlined text-[20px]">chevron_left</span>
</button>
<script>
(function () {
    var body = document.body;
    var sidebar = document.getElementById('superadmin-sidebar');
    var toggle = document.getElementById('superadmin-sidebar-toggle');
    if (!body || !sidebar || !toggle) return;
    body.classList.add('superadmin-shell');

    var mqDesktop = window.matchMedia('(min-width: 1024px)');
    var storageKey = 'superadmin.sidebarCollapsed.desktop';

    function setCollapsed(collapsed) {
        if (!mqDesktop.matches) {
            body.classList.remove('sa-sidebar-collapsed');
            sidebar.classList.remove('w-[5.5rem]');
            sidebar.classList.add('w-64');
            return;
        }
        body.classList.toggle('sa-sidebar-collapsed', collapsed);
        sidebar.classList.toggle('w-[5.5rem]', collapsed);
        sidebar.classList.toggle('w-64', !collapsed);
        sidebar.querySelectorAll('nav a').forEach(function (a) {
            a.classList.toggle('justify-center', collapsed);
            a.classList.toggle('gap-3', !collapsed);
            if (collapsed) {
                var labelNode = a.querySelector('.sa-sidebar-label');
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
        var collapsed = !body.classList.contains('sa-sidebar-collapsed');
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

    var editProfileBtn = document.getElementById('sa-open-edit-profile');
    var editProfileModal = document.getElementById('sa-edit-profile-modal');
    if (editProfileBtn && editProfileModal) {
        function setModalOpen(isOpen) {
            editProfileModal.classList.toggle('hidden', !isOpen);
            editProfileModal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            document.body.classList.toggle('overflow-hidden', isOpen);
        }

        editProfileBtn.addEventListener('click', function () {
            setModalOpen(true);
        });

        editProfileModal.querySelectorAll('[data-sa-modal-close]').forEach(function (el) {
            el.addEventListener('click', function () {
                setModalOpen(false);
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !editProfileModal.classList.contains('hidden')) {
                setModalOpen(false);
            }
        });
    }
})();
</script>
