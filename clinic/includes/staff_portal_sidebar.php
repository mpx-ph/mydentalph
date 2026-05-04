<?php
$staff_nav_active = isset($staff_nav_active) ? (string) $staff_nav_active : 'dashboard';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($currentTenantId) || trim((string) $currentTenantId) === '') {
    if (isset($_SESSION['tenant_id']) && trim((string) $_SESSION['tenant_id']) !== '') {
        $currentTenantId = trim((string) $_SESSION['tenant_id']);
    }
}

require_once __DIR__ . '/clinic_customization.php';

$clinicName = '';
if (isset($CLINIC['clinic_name']) && trim((string) $CLINIC['clinic_name']) !== '') {
    $clinicName = (string) $CLINIC['clinic_name'];
} elseif (isset($currentTenantData['clinic_name']) && trim((string) $currentTenantData['clinic_name']) !== '') {
    $clinicName = (string) $currentTenantData['clinic_name'];
} elseif (isset($_SESSION['clinic_name']) && trim((string) $_SESSION['clinic_name']) !== '') {
    $clinicName = (string) $_SESSION['clinic_name'];
} elseif (isset($currentTenantSlug) && trim((string) $currentTenantSlug) !== '') {
    $clinicName = ucwords(str_replace('-', ' ', (string) $currentTenantSlug));
}
if ($clinicName === '') {
    $clinicName = 'Precision Dental';
}

$clinicLogo = '';
if (isset($CLINIC['logo_nav']) && trim((string) $CLINIC['logo_nav']) !== '') {
    $clinicLogo = (string) $CLINIC['logo_nav'];
} elseif (isset($currentTenantData['logo_nav']) && trim((string) $currentTenantData['logo_nav']) !== '') {
    $clinicLogo = (string) $currentTenantData['logo_nav'];
} elseif (isset($_SESSION['clinic_logo_nav']) && trim((string) $_SESSION['clinic_logo_nav']) !== '') {
    $clinicLogo = (string) $_SESSION['clinic_logo_nav'];
}
if ($clinicLogo === '') {
    $clinicLogo = 'DRCGLogo2.png';
}

$clinicLogoUrl = strpos($clinicLogo, 'http') === 0 ? $clinicLogo : (BASE_URL . ltrim($clinicLogo, '/'));
$clinicLogoLocalPath = null;
if (strpos($clinicLogo, 'http') !== 0 && defined('ROOT_PATH')) {
    $clinicLogoLocalPath = ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($clinicLogo, '/\\'));
}
if (
    strpos($clinicLogoUrl, '?') === false &&
    is_string($clinicLogoLocalPath) &&
    $clinicLogoLocalPath !== '' &&
    is_file($clinicLogoLocalPath)
) {
    $clinicLogoUrl .= '?v=' . @filemtime($clinicLogoLocalPath);
}

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';

$sidebarClinicSlug = '';
if (isset($currentTenantSlug) && trim((string) $currentTenantSlug) !== '') {
    $sidebarClinicSlug = trim((string) $currentTenantSlug);
} elseif (isset($_GET['clinic_slug']) && trim((string) $_GET['clinic_slug']) !== '') {
    $sidebarClinicSlug = trim((string) $_GET['clinic_slug']);
} elseif (!empty($_SESSION['public_tenant_slug'])) {
    $sidebarClinicSlug = trim((string) $_SESSION['public_tenant_slug']);
} else {
    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    if ($requestUri !== '') {
        $uriPath = parse_url($requestUri, PHP_URL_PATH);
        if (is_string($uriPath) && $uriPath !== '') {
            $segments = array_values(array_filter(explode('/', trim($uriPath, '/')), 'strlen'));
            $scriptBase = basename($scriptName);
            $scriptIdx = array_search($scriptBase, $segments, true);
            if ($scriptIdx !== false && $scriptIdx > 0) {
                $candidate = strtolower(trim((string) $segments[$scriptIdx - 1]));
                if ($candidate !== '' && preg_match('/^[a-z0-9\-]+$/', $candidate)) {
                    $sidebarClinicSlug = $candidate;
                }
            }
        }
    }
}

$requestPath = isset($_SERVER['REQUEST_URI']) ? (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
if (!is_string($requestPath) || $requestPath === '') {
    $requestPath = '/' . basename($scriptName !== '' ? $scriptName : 'StaffDashboard.php');
}
$requestPath = str_replace('\\', '/', $requestPath);
$linkBaseDir = rtrim(dirname($requestPath), '/');
if ($linkBaseDir === '.' || $linkBaseDir === '\\') {
    $linkBaseDir = '';
}

$buildStaffHref = function ($targetFile) use ($linkBaseDir, $sidebarClinicSlug) {
    if ($sidebarClinicSlug !== '') {
        return '/' . rawurlencode($sidebarClinicSlug) . '/' . $targetFile;
    }
    $base = $linkBaseDir !== '' ? $linkBaseDir : '';
    return ($base === '' ? '/' : ($base . '/')) . $targetFile;
};

$navSections = [
    [
        'label' => 'Overview',
        'items' => [
            ['key' => 'dashboard',    'label' => 'Dashboard',          'icon' => 'dashboard',        'href' => $buildStaffHref('StaffDashboard.php')],
        ],
    ],
    [
        'label' => 'Scheduling',
        'items' => [
            ['key' => 'appointments', 'label' => 'Appointments',       'icon' => 'calendar_month',   'href' => $buildStaffHref('StaffAppointments.php')],
            ['key' => 'my_schedule',  'label' => 'My Schedule',        'icon' => 'view_week',        'href' => $buildStaffHref('StaffScheduling.php')],
            ['key' => 'clinic_hours', 'label' => 'Clinic Hours',       'icon' => 'schedule',         'href' => $buildStaffHref('StaffClinicHours.php')],
        ],
    ],
    [
        'label' => 'Patients & Services',
        'items' => [
            ['key' => 'patients',     'label' => 'Patients',           'icon' => 'group',            'href' => $buildStaffHref('StaffManagePatient.php')],
            ['key' => 'messages',   'label' => 'Patient Messages',   'icon' => 'chat',             'href' => $buildStaffHref('StaffMessage.php')],
            ['key' => 'discount_verification', 'label' => 'Discount Verification', 'icon' => 'verified_user', 'href' => $buildStaffHref('StaffDiscountVerification.php')],
            ['key' => 'services',     'label' => 'Services & Pricing', 'icon' => 'medical_services', 'href' => $buildStaffHref('StaffManageServices.php')],
        ],
    ],
    [
        'label' => 'Payments',
        'items' => [
            ['key' => 'payments',         'label' => 'Payments',         'icon' => 'payments',       'href' => $buildStaffHref('StaffPaymentRecording.php')],
            ['key' => 'payment_settings', 'label' => 'Payment Settings', 'icon' => 'settings',       'href' => $buildStaffHref('StaffPaymentSetting.php')],
        ],
    ],
    [
        'label' => 'Management',
        'items' => [
            ['key' => 'reports',      'label' => 'Reports',            'icon' => 'bar_chart',        'href' => $buildStaffHref('StaffReports.php')],
            ['key' => 'users',        'label' => 'Users',              'icon' => 'people',           'href' => $buildStaffHref('StaffManageUsers.php')],
            ['key' => 'reviews',      'label' => 'Reviews',            'icon' => 'rate_review',      'href' => $buildStaffHref('StaffManageReview.php')],
        ],
    ],
    [
        'label' => 'Account',
        'items' => [
            ['key' => 'profile',      'label' => 'My Profile',         'icon' => 'account_circle',   'href' => $buildStaffHref('StaffMyProfile.php')],
        ],
    ],
];

// Dentist role: only these pages are accessible
$dentistAllowedKeys = ['dashboard', 'patients', 'messages', 'discount_verification', 'appointments', 'my_schedule', 'clinic_hours', 'profile'];
$currentUserRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : '';
$currentUserType = isset($_SESSION['user_type']) ? strtolower(trim((string) $_SESSION['user_type'])) : '';
if ($currentUserRole === 'dentist') {
    $portalLabel = 'Dentist Portal';
} elseif ($currentUserRole === 'manager' || $currentUserType === 'manager') {
    $portalLabel = 'Manager Portal';
} else {
    $portalLabel = 'Staff Portal';
}

if ($currentUserRole === 'dentist') {
    $navSections = array_values(array_filter(array_map(function ($section) use ($dentistAllowedKeys) {
        $section['items'] = array_values(array_filter($section['items'], function ($item) use ($dentistAllowedKeys) {
            return in_array($item['key'], $dentistAllowedKeys, true);
        }));
        return $section;
    }, $navSections), function ($section) {
        return !empty($section['items']);
    }));
}
?>
<style>
    .staff-sidebar-brand .editorial-word {
        text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
        letter-spacing: -0.02em;
    }
@media (min-width: 1024px) {
    body.staff-shell {
        --staff-collapse-duration: 460ms;
        --staff-collapse-ease: cubic-bezier(0.2, 0.85, 0.24, 1);
    }
    body.staff-shell main {
        margin-left: 16rem !important;
        transition: margin-left var(--staff-collapse-duration) var(--staff-collapse-ease);
        will-change: margin-left;
    }
    body.staff-shell.staff-sidebar-collapsed main {
        margin-left: 5.5rem !important;
    }
    body.staff-shell #staff-sidebar {
        transition: width var(--staff-collapse-duration) var(--staff-collapse-ease);
        overflow: hidden;
        will-change: width;
    }
    body.staff-shell .staff-sidebar-divider-toggle {
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
        transition: left var(--staff-collapse-duration) var(--staff-collapse-ease), color 180ms ease, background-color 180ms ease;
    }
    body.staff-shell .staff-sidebar-divider-toggle:hover {
        color: #0066ff;
        background: #ffffff;
    }
    body.staff-shell.staff-sidebar-collapsed .staff-sidebar-divider-toggle {
        left: calc(5.5rem - 0.8rem);
    }
    body.staff-shell .staff-sidebar-label,
    body.staff-shell .staff-sidebar-tagline,
    body.staff-shell .staff-sidebar-clinic-name {
        max-width: 16rem;
        opacity: 1;
        transform: translateX(0);
        white-space: nowrap;
        overflow: hidden;
        transition:
            max-width 360ms var(--staff-collapse-ease),
            opacity 240ms ease,
            transform 320ms var(--staff-collapse-ease);
    }
    body.staff-shell.staff-sidebar-collapsed .staff-sidebar-label,
    body.staff-shell.staff-sidebar-collapsed .staff-sidebar-tagline,
    body.staff-shell.staff-sidebar-collapsed .staff-sidebar-clinic-name {
        max-width: 0;
        opacity: 0;
        transform: translateX(-6px);
        pointer-events: none;
    }
    body.staff-shell.staff-sidebar-collapsed .staff-sidebar-brand-row {
        justify-content: center;
    }
    body.staff-shell .staff-nav-link {
        transition: transform 240ms var(--staff-collapse-ease), color 200ms ease, background-color 200ms ease;
    }
    body.staff-shell.staff-sidebar-collapsed .staff-sidebar-brand nav a:hover {
        transform: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    body.staff-shell main,
    body.staff-shell #staff-sidebar,
    body.staff-shell .staff-sidebar-divider-toggle,
    body.staff-shell .staff-sidebar-label,
    body.staff-shell .staff-sidebar-tagline,
    body.staff-shell .staff-sidebar-clinic-name,
    body.staff-shell .staff-nav-link {
        transition: none !important;
    }
}
</style>
<aside id="staff-sidebar" class="staff-sidebar-brand fixed left-0 top-0 h-full w-64 z-40 bg-white flex flex-col py-8 border-r border-slate-200/60 min-h-screen">
    <div class="px-7 mb-8 shrink-0">
        <h1 class="staff-sidebar-brand-row flex items-center gap-2 min-w-0 font-headline tracking-tight">
            <img
                src="<?php echo htmlspecialchars($clinicLogoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars($clinicName, ENT_QUOTES, 'UTF-8'); ?>"
                class="h-9 w-auto shrink-0 object-contain"
            />
            <span class="staff-sidebar-clinic-name font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block text-lg leading-snug min-w-0 break-words"><?php echo htmlspecialchars($clinicName, ENT_QUOTES, 'UTF-8'); ?></span>
        </h1>
        <p class="staff-sidebar-tagline text-primary font-bold text-[10px] tracking-[0.2em] uppercase mt-2 opacity-80"><?php echo htmlspecialchars($portalLabel, ENT_QUOTES, 'UTF-8'); ?></p>
    </div>

    <nav class="flex-1 min-h-0 space-y-3 overflow-y-auto no-scrollbar">
        <?php foreach ($navSections as $section) { ?>
            <div>
                <p class="staff-sidebar-label px-7 mb-1 text-[10px] font-bold tracking-[0.16em] uppercase text-slate-400">
                    <?php echo htmlspecialchars($section['label'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <?php foreach ($section['items'] as $item) { ?>
                    <?php $isActive = $staff_nav_active === $item['key']; ?>
                    <div class="<?php echo $isActive ? 'relative ' : ''; ?>px-3">
                        <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="staff-nav-link flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $isActive ? 'bg-primary/10 text-primary active-glow' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                            <span class="material-symbols-outlined text-[22px] shrink-0" <?php echo $isActive ? 'style="font-variation-settings: \'FILL\' 1;"' : ''; ?>><?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="staff-sidebar-label font-headline text-[13px] <?php echo $isActive ? 'font-bold' : 'font-medium'; ?> tracking-tight"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                        <?php if ($isActive) { ?>
                            <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </nav>
</aside>
<button id="staff-sidebar-toggle" class="staff-sidebar-divider-toggle hidden lg:flex items-center justify-center" type="button" aria-label="Collapse sidebar" aria-expanded="true">
    <span class="material-symbols-outlined text-[20px]">chevron_left</span>
</button>
<script>
(function () {
    var body = document.body;
    var sidebar = document.getElementById('staff-sidebar');
    var toggle = document.getElementById('staff-sidebar-toggle');
    if (!body || !sidebar || !toggle) return;
    body.classList.add('staff-shell');

    var mqDesktop = window.matchMedia('(min-width: 1024px)');
    var storageKey = 'staff.sidebarCollapsed.desktop';

    function setCollapsed(collapsed) {
        if (!mqDesktop.matches) {
            body.classList.remove('staff-sidebar-collapsed');
            sidebar.classList.remove('w-[5.5rem]');
            sidebar.classList.add('w-64');
            sidebar.querySelectorAll('nav a.staff-nav-link').forEach(function (a) {
                a.classList.remove('justify-center');
                a.classList.add('gap-3');
                a.removeAttribute('title');
            });
            return;
        }
        body.classList.toggle('staff-sidebar-collapsed', collapsed);
        sidebar.classList.toggle('w-[5.5rem]', collapsed);
        sidebar.classList.toggle('w-64', !collapsed);
        sidebar.querySelectorAll('nav a.staff-nav-link').forEach(function (a) {
            a.classList.toggle('justify-center', collapsed);
            a.classList.toggle('gap-3', !collapsed);
            if (collapsed) {
                var labelNode = a.querySelector('.staff-sidebar-label');
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
        var collapsed = !body.classList.contains('staff-sidebar-collapsed');
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
