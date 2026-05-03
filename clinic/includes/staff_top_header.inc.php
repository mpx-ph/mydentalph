<?php
declare(strict_types=1);

$__slug_title = '';
if (isset($currentTenantSlug) && is_string($currentTenantSlug)) {
    $__slug_title = trim($currentTenantSlug);
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

$__header_avatar_url = '';
if (function_exists('getDBConnection') && defined('BASE_URL') && !empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id'])) {
    try {
        $pdoHdr = getDBConnection();
        $uid = (string) $_SESSION['user_id'];
        $tid = (string) $_SESSION['tenant_id'];
        $st = $pdoHdr->prepare(
            'SELECT COALESCE(
                NULLIF(TRIM(d.profile_image), \'\'),
                NULLIF(TRIM(s.profile_image), \'\'),
                NULLIF(TRIM(u.photo), \'\')
             ) AS img
             FROM tbl_users u
             LEFT JOIN tbl_staffs s ON s.user_id = u.user_id AND s.tenant_id = u.tenant_id
             LEFT JOIN tbl_dentists d ON d.tenant_id = u.tenant_id
               AND u.role = \'dentist\'
               AND LOWER(TRIM(COALESCE(d.email, \'\'))) = LOWER(TRIM(COALESCE(u.email, \'\')))
             WHERE u.user_id = ? AND u.tenant_id = ?
             LIMIT 1'
        );
        $st->execute([$uid, $tid]);
        $hdrRow = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($hdrRow) && !empty($hdrRow['img'])) {
            $p = trim((string) $hdrRow['img']);
            if ($p !== '') {
                if (preg_match('#^https?://#i', $p)) {
                    $__header_avatar_url = $p;
                } else {
                    $__header_avatar_url = rtrim(BASE_URL, '/') . '/' . ltrim(str_replace('\\', '/', $p), '/');
                }
            }
        }
    } catch (Throwable $e) {
        // Header must always render
    }
}

$__staff_profile_url = 'StaffMyProfile.php';
if ($__slug_title !== '') {
    $__staff_profile_url .= '?clinic_slug=' . rawurlencode($__slug_title);
}
$__staff_appointments_checkin_url = 'StaffAppointments.php?open_patient_check_in=1';
if ($__slug_title !== '') {
    $__staff_appointments_checkin_url .= '&clinic_slug=' . rawurlencode($__slug_title);
}
$__staff_logout_url = '/clinic/api/logout.php';

$__clinic_name = '';
if (isset($CLINIC['clinic_name']) && is_string($CLINIC['clinic_name'])) {
    $__clinic_name = trim($CLINIC['clinic_name']);
}
if ($__clinic_name === '' && isset($currentTenantData['clinic_name']) && is_string($currentTenantData['clinic_name'])) {
    $__clinic_name = trim($currentTenantData['clinic_name']);
}
if ($__clinic_name === '' && isset($_SESSION['clinic_name']) && is_string($_SESSION['clinic_name'])) {
    $__clinic_name = trim($_SESSION['clinic_name']);
}
if ($__clinic_name === '' && $__slug_title !== '') {
    $__clinic_name = ucwords(str_replace('-', ' ', $__slug_title));
}
if ($__clinic_name === '') {
    $__clinic_name = 'Dental Clinic';
}

$__portal_label = 'Staff Workspace';
$__hdr_role = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : '';
$__hdr_type = isset($_SESSION['user_type']) ? strtolower(trim((string) $_SESSION['user_type'])) : '';
if ($__hdr_role === 'dentist') {
    $__portal_label = 'Dentist Workspace';
} elseif ($__hdr_role === 'manager' || $__hdr_type === 'manager') {
    $__portal_label = 'Manager Workspace';
}
?>
<header class="sticky top-0 z-30 min-h-[4.5rem] sm:h-20 sm:min-h-0 bg-white/90 backdrop-blur-xl border-b border-slate-200/60 shadow-sm shadow-slate-200/30" data-purpose="top-header">
  <div class="flex items-center justify-between gap-4 px-4 lg:px-8 py-3 sm:py-0 sm:h-full">
    <div class="min-w-0">
      <p class="text-[10px] sm:text-[11px] font-black uppercase tracking-[0.24em] text-primary/80 truncate"><?php echo htmlspecialchars($__portal_label, ENT_QUOTES, 'UTF-8'); ?></p>
      <p class="text-lg sm:text-[1.75rem] leading-tight font-extrabold tracking-tight text-[#0b3463] truncate"><?php echo htmlspecialchars($__clinic_name, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <a href="<?php echo htmlspecialchars($__staff_appointments_checkin_url, ENT_QUOTES, 'UTF-8'); ?>" id="staff-header-qr-btn" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all relative border-0 bg-transparent cursor-pointer hidden sm:inline-flex items-center justify-center text-inherit no-underline shrink-0" aria-label="Open patient check-in (QR scanner)">
        <span class="material-symbols-outlined text-on-surface-variant">qr_code_2</span>
      </a>
      <div class="relative">
        <button id="staff-user-menu-trigger" type="button" class="group flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/80 pl-1 pr-2.5 py-1 shadow-sm text-left cursor-pointer hover:border-primary/35 hover:bg-white hover:shadow-md transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2" aria-label="Open account menu" aria-expanded="false" aria-haspopup="menu" aria-controls="staff-user-menu">
          <div id="staff-header-avatar" class="w-10 h-10 rounded-xl flex items-center justify-center text-primary text-xs font-bold border border-primary/10 shrink-0 bg-cover bg-center overflow-hidden <?php echo $__header_avatar_url === '' ? 'bg-primary/15' : ''; ?>"<?php echo $__header_avatar_url !== '' ? ' style="background-image:url(\'' . htmlspecialchars($__header_avatar_url, ENT_QUOTES, 'UTF-8') . '\')"' : ''; ?>>
            <span id="staff-header-avatar-initials" class="flex h-full w-full items-center justify-center <?php echo $__header_avatar_url !== '' ? 'sr-only' : ''; ?>"><?php echo htmlspecialchars($__avatar, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="min-w-0 text-left">
            <p id="staff-header-user-name" class="text-xs font-bold text-on-background truncate max-w-[10rem] sm:max-w-[14rem]"><?php echo htmlspecialchars($__staff_name, ENT_QUOTES, 'UTF-8'); ?></p>
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
    var headerRoot = document.currentScript ? document.currentScript.previousElementSibling : null;
    if (headerRoot && headerRoot.tagName === 'HEADER') {
      var parentMain = headerRoot.closest('main');
      if (parentMain) {
        parentMain.style.paddingTop = '0px';
      }
    }

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
