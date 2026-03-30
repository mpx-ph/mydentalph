<?php
declare(strict_types=1);

$__slug_title = '';
if (isset($currentTenantSlug) && is_string($currentTenantSlug)) {
    $__slug_title = trim($currentTenantSlug);
}

$__clinic_title = '';
if (isset($clinic_name) && is_string($clinic_name)) {
    $__clinic_title = trim($clinic_name);
}
if ($__clinic_title === '' && isset($_SESSION['clinic_name']) && is_string($_SESSION['clinic_name'])) {
    $__clinic_title = trim($_SESSION['clinic_name']);
}
if ($__clinic_title === '' && $__slug_title !== '') {
    $__clinic_title = ucwords(str_replace('-', ' ', $__slug_title));
}
if ($__clinic_title === '') {
    $__clinic_title = 'Staff Portal';
}

$__staff_name = '';
if (isset($_SESSION['user_name']) && is_string($_SESSION['user_name'])) {
    $__staff_name = trim($_SESSION['user_name']);
}
if ($__staff_name === '' && isset($_SESSION['full_name']) && is_string($_SESSION['full_name'])) {
    $__staff_name = trim($_SESSION['full_name']);
}
if ($__staff_name === '') {
    $__staff_name = 'Staff Account';
}

$__staff_role = '';
if (isset($_SESSION['user_role']) && is_string($_SESSION['user_role'])) {
    $__staff_role = trim($_SESSION['user_role']);
}
$__staff_role = $__staff_role !== '' ? ucwords(str_replace('_', ' ', $__staff_role)) : 'Staff';

$__avatar = 'ST';
$__parts = preg_split('/\s+/', $__staff_name, -1, PREG_SPLIT_NO_EMPTY);
if (is_array($__parts) && $__parts !== []) {
    $__avatar = strtoupper(substr((string) $__parts[0], 0, 1));
    if (isset($__parts[1])) {
        $__avatar .= strtoupper(substr((string) $__parts[1], 0, 1));
    } else {
        $__avatar .= strtoupper(substr((string) $__parts[0], 1, 1));
    }
    $__avatar = substr($__avatar, 0, 2);
}
if ($__avatar === '') {
    $__avatar = 'ST';
}

$__staff_profile_url = 'StaffMyProfile.php';
if ($__slug_title !== '') {
    $__staff_profile_url .= '?clinic_slug=' . rawurlencode($__slug_title);
}
$__staff_logout_url = '/clinic/api/logout.php';
?>
<header class="fixed top-0 right-0 left-64 z-30 min-h-[4.5rem] sm:h-20 sm:min-h-0 bg-white/90 backdrop-blur-xl border-b border-slate-200/60 shadow-sm shadow-slate-200/30" data-purpose="top-header">
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-6 lg:px-10 py-3 sm:py-0 sm:h-full">
    <div class="min-w-0 flex-1">
      <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-primary/80 mb-0.5">Clinic</p>
      <h1 class="text-lg sm:text-xl font-extrabold font-headline text-on-background truncate tracking-tight"><?php echo htmlspecialchars($__clinic_title, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <button type="button" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all relative border-0 bg-transparent cursor-pointer hidden sm:inline-flex" aria-label="Notifications">
        <span class="material-symbols-outlined text-on-surface-variant">notifications</span>
      </button>
      <button type="button" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all border-0 bg-transparent cursor-pointer hidden sm:inline-flex" aria-label="Help">
        <span class="material-symbols-outlined text-on-surface-variant">help_outline</span>
      </button>
      <div class="relative">
        <button id="staff-user-menu-trigger" type="button" class="group flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/80 pl-1 pr-2.5 py-1 shadow-sm text-left cursor-pointer hover:border-primary/35 hover:bg-white hover:shadow-md transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2" aria-label="Open account menu" aria-expanded="false" aria-haspopup="menu" aria-controls="staff-user-menu">
          <div class="w-10 h-10 rounded-xl bg-primary/15 flex items-center justify-center text-primary text-xs font-bold border border-primary/10 shrink-0"><?php echo htmlspecialchars($__avatar, ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="min-w-0 text-left">
            <p class="text-xs font-bold text-on-background truncate max-w-[10rem] sm:max-w-[14rem]"><?php echo htmlspecialchars($__staff_name, ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="text-[11px] leading-tight text-on-surface-variant truncate max-w-[10rem] sm:max-w-[14rem]"><?php echo htmlspecialchars($__staff_role, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
          <span class="material-symbols-outlined text-slate-400 text-[18px]">expand_more</span>
        </button>
        <div id="staff-user-menu" class="hidden absolute right-0 mt-2 w-48 rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/60 p-1.5 z-50" role="menu" aria-labelledby="staff-user-menu-trigger">
          <a href="<?php echo htmlspecialchars($__staff_profile_url, ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 transition-colors" role="menuitem">
            <span class="material-symbols-outlined text-[18px] text-slate-500">edit_square</span>
            Edit Profile
          </a>
          <a href="<?php echo htmlspecialchars($__staff_logout_url, ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 transition-colors" role="menuitem">
            <span class="material-symbols-outlined text-[18px]">logout</span>
            Logout
          </a>
        </div>
      </div>
    </div>
  </div>
</header>
<script>
  (function () {
    var trigger = document.getElementById('staff-user-menu-trigger');
    var menu = document.getElementById('staff-user-menu');
    if (!trigger || !menu) {
      return;
    }

    function closeMenu() {
      menu.classList.add('hidden');
      trigger.setAttribute('aria-expanded', 'false');
    }

    function openMenu() {
      menu.classList.remove('hidden');
      trigger.setAttribute('aria-expanded', 'true');
    }

    trigger.addEventListener('click', function (event) {
      event.stopPropagation();
      if (menu.classList.contains('hidden')) {
        openMenu();
      } else {
        closeMenu();
      }
    });

    document.addEventListener('click', function (event) {
      if (!menu.contains(event.target) && !trigger.contains(event.target)) {
        closeMenu();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        closeMenu();
      }
    });
  })();
</script>
