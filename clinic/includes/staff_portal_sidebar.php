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

$allNavItems = [
    ['key' => 'dashboard',        'label' => 'Dashboard',         'icon' => 'dashboard',        'href' => $buildStaffHref('StaffDashboard.php')],
    ['key' => 'patients',         'label' => 'Patients',          'icon' => 'group',             'href' => $buildStaffHref('StaffManagePatient.php')],
    ['key' => 'appointments',     'label' => 'Appointments',      'icon' => 'calendar_month',   'href' => $buildStaffHref('StaffAppointments.php')],
    ['key' => 'block_schedule',   'label' => 'Block Schedule',    'icon' => 'event_busy',        'href' => $buildStaffHref('StaffBlockSchedule.php')],
    ['key' => 'services',         'label' => 'Services & Pricing','icon' => 'medical_services',  'href' => $buildStaffHref('StaffManageServices.php')],
    ['key' => 'payments',         'label' => 'Payments',          'icon' => 'payments',          'href' => $buildStaffHref('StaffPaymentRecording.php')],
    ['key' => 'payment_settings', 'label' => 'Payment Settings',  'icon' => 'settings',          'href' => $buildStaffHref('StaffPaymentSetting.php')],
    ['key' => 'reports',          'label' => 'Reports',           'icon' => 'bar_chart',         'href' => $buildStaffHref('StaffReports.php')],
    ['key' => 'users',            'label' => 'Users',             'icon' => 'people',            'href' => $buildStaffHref('StaffManageUsers.php')],
    ['key' => 'reviews',          'label' => 'Reviews',           'icon' => 'rate_review',       'href' => $buildStaffHref('StaffManageReview.php')],
    ['key' => 'profile',          'label' => 'My Profile',        'icon' => 'account_circle',    'href' => $buildStaffHref('StaffMyProfile.php')],
];

// Dentist role: only these pages are accessible
$dentistAllowedKeys = ['dashboard', 'patients', 'appointments', 'block_schedule', 'profile'];
$currentUserRole = isset($_SESSION['user_role']) ? strtolower(trim((string) $_SESSION['user_role'])) : '';

if ($currentUserRole === 'dentist') {
    $navItems = array_values(array_filter($allNavItems, function ($item) use ($dentistAllowedKeys) {
        return in_array($item['key'], $dentistAllowedKeys, true);
    }));
} else {
    $navItems = $allNavItems;
}
?>
<style>
    .staff-sidebar-brand .editorial-word {
        text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
        letter-spacing: -0.02em;
    }
    .staff-sidebar-shell {
        background: rgba(252, 253, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-right: 1px solid rgba(224, 233, 246, 0.5);
    }
    .staff-nav-link {
        transition: transform 0.25s ease, background-color 0.25s ease, color 0.25s ease, box-shadow 0.25s ease;
    }
    .staff-nav-link:not(.staff-nav-link--active):hover {
        transform: translateX(4px);
        box-shadow: inset 0 0 0 1px rgba(226, 232, 240, 0.8);
    }
</style>
<aside class="staff-sidebar-brand staff-sidebar-shell fixed left-0 top-0 h-full w-64 z-40 flex flex-col py-8">
    <div class="px-7 mb-8">
        <h1 class="flex items-center gap-2 min-w-0 font-headline tracking-tight">
            <img
                src="<?php echo htmlspecialchars($clinicLogoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars($clinicName, ENT_QUOTES, 'UTF-8'); ?>"
                class="h-9 w-auto shrink-0 object-contain"
            />
            <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block text-lg leading-snug min-w-0 break-words"><?php echo htmlspecialchars($clinicName, ENT_QUOTES, 'UTF-8'); ?></span>
        </h1>
        <p class="text-primary font-bold text-[10px] tracking-[0.2em] uppercase mt-2 opacity-80">Staff Portal</p>
    </div>

    <nav class="flex-1 min-h-0 space-y-1 overflow-y-auto no-scrollbar">
        <?php foreach ($navItems as $item) { ?>
            <?php $isActive = $staff_nav_active === $item['key']; ?>
            <div class="<?php echo $isActive ? 'relative ' : ''; ?>px-3">
                <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="staff-nav-link <?php echo $isActive ? 'staff-nav-link--active' : ''; ?> flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $isActive ? 'bg-primary/10 text-primary active-glow' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
                    <span class="material-symbols-outlined text-[22px]" <?php echo $isActive ? 'style="font-variation-settings: \'FILL\' 1;"' : ''; ?>><?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="font-headline text-sm <?php echo $isActive ? 'font-bold' : 'font-medium'; ?> tracking-tight"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
                <?php if ($isActive) { ?>
                    <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
                <?php } ?>
            </div>
        <?php } ?>
    </nav>
</aside>
