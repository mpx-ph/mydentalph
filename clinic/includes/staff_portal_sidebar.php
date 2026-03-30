<?php
$staff_nav_active = isset($staff_nav_active) ? (string) $staff_nav_active : 'dashboard';

$clinicName = 'Precision Dental';
if (isset($currentTenantData['clinic_name']) && trim((string) $currentTenantData['clinic_name']) !== '') {
    $clinicName = (string) $currentTenantData['clinic_name'];
}

$scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
$requestBasePath = $scriptName !== '' ? rtrim(str_replace('\\', '/', dirname($scriptName)), '/') . '/' : '';
if ($requestBasePath === '//' || $requestBasePath === './') {
    $requestBasePath = '/';
}

$sidebarClinicSlug = '';
if (isset($currentTenantSlug) && trim((string) $currentTenantSlug) !== '') {
    $sidebarClinicSlug = trim((string) $currentTenantSlug);
} elseif (isset($_GET['clinic_slug']) && trim((string) $_GET['clinic_slug']) !== '') {
    $sidebarClinicSlug = trim((string) $_GET['clinic_slug']);
}

$slugQuery = '';
if ($sidebarClinicSlug !== '') {
    $slugQuery = '?clinic_slug=' . rawurlencode($sidebarClinicSlug);
}

$navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => $requestBasePath . 'StaffDashboard.php' . $slugQuery],
    ['key' => 'patients', 'label' => 'Patients', 'icon' => 'group', 'href' => $requestBasePath . 'StaffManagePatient.php' . $slugQuery],
    ['key' => 'appointments', 'label' => 'Appointments', 'icon' => 'calendar_month', 'href' => $requestBasePath . 'StaffAppointments.php' . $slugQuery],
    ['key' => 'services', 'label' => 'Services & Pricing', 'icon' => 'medical_services', 'href' => $requestBasePath . 'StaffManageServices.php' . $slugQuery],
    ['key' => 'payments', 'label' => 'Payments', 'icon' => 'payments', 'href' => $requestBasePath . 'StaffPaymentRecording.php' . $slugQuery],
    ['key' => 'payment_settings', 'label' => 'Payment Settings', 'icon' => 'settings', 'href' => $requestBasePath . 'StaffPaymentSetting.php' . $slugQuery],
    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'bar_chart', 'href' => $requestBasePath . 'StaffReports.php' . $slugQuery],
    ['key' => 'users', 'label' => 'Users', 'icon' => 'people', 'href' => $requestBasePath . 'StaffManageUsers.php' . $slugQuery],
    ['key' => 'reviews', 'label' => 'Reviews', 'icon' => 'rate_review', 'href' => $requestBasePath . 'StaffManageReview.php' . $slugQuery],
    ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'account_circle', 'href' => $requestBasePath . 'StaffMyProfile.php' . $slugQuery],
];
?>
<aside class="fixed left-0 top-0 h-full w-64 z-40 bg-white flex flex-col py-8 border-r border-slate-200/60">
    <div class="px-7 mb-8">
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight font-headline flex items-center gap-2">
            <span class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shadow-lg shadow-primary/30">
                <span class="material-symbols-outlined text-white text-lg" style="font-variation-settings: 'FILL' 1;">dentistry</span>
            </span>
            <?php echo htmlspecialchars($clinicName, ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <p class="text-primary font-bold text-[10px] tracking-[0.2em] uppercase mt-2 opacity-80">Staff Portal</p>
    </div>

    <nav class="flex-1 min-h-0 space-y-1 overflow-y-auto no-scrollbar">
        <?php foreach ($navItems as $item) { ?>
            <?php $isActive = $staff_nav_active === $item['key']; ?>
            <div class="<?php echo $isActive ? 'relative ' : ''; ?>px-3">
                <a href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 <?php echo $isActive ? 'bg-primary/10 text-primary active-glow' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'; ?>">
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
