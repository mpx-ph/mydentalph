<?php
/**
 * Staff Portal Sidebar (shared include).
 *
 * Expected variables (set by calling page):
 * - string $staff_nav_active: 'dashboard' | 'appointments'
 * - string $staff_portal_sidebar_mode: 'dashboard' | 'appointments' (controls which extra menu section is shown)
 *
 * Expected variables (provided by calling page for URLs / user info):
 * - string $staffDashUrl, $staffApptsUrl, $staffLogoutUrl
 * - string $staffInitialsEsc, $staffDisplayName, $staffDisplayEmailEsc, $staffRoleLabel
 * - (dashboard mode only) $managerDashUrl, $managerPatientsUrl, $managerPaymentsUrl, $managerPaymentSettingsUrl, $managerServicesUrl, $managerUsersUrl, $managerReviewsUrl
 * - array/values from $currentTenantData used for clinic name
 */

$staff_nav_active = $staff_nav_active ?? 'dashboard';
$staff_portal_sidebar_mode = $staff_portal_sidebar_mode ?? 'appointments';

$isDashboardActive = $staff_nav_active === 'dashboard';
$isAppointmentsActive = $staff_nav_active === 'appointments';
?>
<aside class="fixed left-0 top-0 h-full w-64 z-40 bg-white flex flex-col py-8 border-r border-slate-200/60">
    <div class="px-7 mb-10">
        <h1 class="text-xl font-extrabold text-slate-900 tracking-tight font-headline flex items-center gap-2">
            <span class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center shadow-lg shadow-primary/30">
                <span class="material-symbols-outlined text-white text-lg" style="font-variation-settings: 'FILL' 1;">dentistry</span>
            </span>
            <?php echo htmlspecialchars(isset($currentTenantData['clinic_name']) ? (string) $currentTenantData['clinic_name'] : 'Clinic', ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <p class="text-primary font-bold text-[10px] tracking-[0.2em] uppercase mt-2 opacity-80">Staff Portal</p>
    </div>

    <nav class="flex-1 min-h-0 space-y-1 overflow-y-auto no-scrollbar">
        <?php if ($isDashboardActive) { ?>
        <div class="relative px-3">
            <a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="<?php echo $staffDashUrl; ?>">
                <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">dashboard</span>
                <span class="font-headline text-sm font-bold tracking-tight">Staff Dashboard</span>
            </a>
            <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
        </div>
        <?php } else { ?>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $staffDashUrl; ?>">
                <span class="material-symbols-outlined text-[22px]">dashboard</span>
                <span class="font-headline text-sm font-medium tracking-tight">Dashboard</span>
            </a>
        </div>
        <?php } ?>

        <?php if ($isAppointmentsActive) { ?>
        <div class="relative px-3">
            <a class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" href="<?php echo $staffApptsUrl; ?>">
                <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">calendar_month</span>
                <span class="font-headline text-sm font-bold tracking-tight">Appointments</span>
            </a>
            <div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-primary rounded-r-full"></div>
        </div>
        <?php } else { ?>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $staffApptsUrl; ?>">
                <span class="material-symbols-outlined text-[22px]">calendar_month</span>
                <span class="font-headline text-sm font-medium tracking-tight">Appointments</span>
            </a>
        </div>
        <?php } ?>

        <?php if ($staff_portal_sidebar_mode === 'dashboard') { ?>
        <div class="px-3 mt-6">
            <p class="px-4 text-[10px] font-bold text-primary uppercase tracking-[0.2em] opacity-80">Manager Menus</p>
        </div>

        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $managerDashUrl; ?>">
                <span class="material-symbols-outlined text-[22px]">dashboard</span>
                <span class="font-headline text-sm font-medium tracking-tight">Dashboard</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $managerPatientsUrl; ?>">
                <span class="material-symbols-outlined text-[22px]">group</span>
                <span class="font-headline text-sm font-medium tracking-tight">Patients</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $managerPaymentsUrl; ?>">
                <span class="material-symbols-outlined text-[22px]">payments</span>
                <span class="font-headline text-sm font-medium tracking-tight">Payments</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $managerPaymentSettingsUrl; ?>">
                <span class="material-symbols-outlined text-[22px]">settings</span>
                <span class="font-headline text-sm font-medium tracking-tight">Payment Settings</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $managerServicesUrl; ?>">
                <span class="material-symbols-outlined text-[22px]" style="font-variation-settings: 'FILL' 1;">medical_services</span>
                <span class="font-headline text-sm font-medium tracking-tight">Services</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $managerUsersUrl; ?>">
                <span class="material-symbols-outlined text-[22px]">people</span>
                <span class="font-headline text-sm font-medium tracking-tight">Users</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="<?php echo $managerReviewsUrl; ?>">
                <span class="material-symbols-outlined text-[22px]">rate_review</span>
                <span class="font-headline text-sm font-medium tracking-tight">Reviews</span>
            </a>
        </div>

        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">person_add</span>
                <span class="font-headline text-sm font-medium tracking-tight">Registration</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">group</span>
                <span class="font-headline text-sm font-medium tracking-tight">Patients</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">payments</span>
                <span class="font-headline text-sm font-medium tracking-tight">Payments</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">chat</span>
                <span class="font-headline text-sm font-medium tracking-tight">Messages</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">settings</span>
                <span class="font-headline text-sm font-medium tracking-tight">Settings</span>
            </a>
        </div>
        <?php } else { ?>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">person_add</span>
                <span class="font-headline text-sm font-medium tracking-tight">Registration</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">group</span>
                <span class="font-headline text-sm font-medium tracking-tight">Patients</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">payments</span>
                <span class="font-headline text-sm font-medium tracking-tight">Payments</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">chat</span>
                <span class="font-headline text-sm font-medium tracking-tight">Messages</span>
            </a>
        </div>
        <div class="px-3">
            <a class="flex items-center gap-3 px-4 py-3 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" href="#">
                <span class="material-symbols-outlined text-[22px]">settings</span>
                <span class="font-headline text-sm font-medium tracking-tight">Settings</span>
            </a>
        </div>
        <?php } ?>
    </nav>

    <div class="px-4 pt-5 mt-auto border-t border-slate-200/80 space-y-3 shrink-0">
        <button type="button" class="w-full py-3.5 bg-primary text-white font-bold text-xs uppercase tracking-widest rounded-xl shadow-lg shadow-primary/30 transition-all active:scale-95">
            New Appointment
        </button>

        <div class="rounded-2xl bg-slate-50 border border-slate-200/80 p-3 flex items-center gap-3">
            <div class="h-11 w-11 rounded-xl bg-primary/15 flex items-center justify-center text-primary font-bold text-sm shrink-0" aria-hidden="true">
                <span class="select-none"><?php echo $staffInitialsEsc; ?></span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-slate-900 font-headline truncate leading-tight"><?php echo $staffDisplayName; ?></p>
                <?php if ($staffDisplayEmailEsc !== '') { ?>
                <p class="text-[11px] text-slate-500 truncate mt-0.5"><?php echo $staffDisplayEmailEsc; ?></p>
                <?php } ?>
                <p class="text-[10px] font-bold text-primary uppercase tracking-wider mt-1"><?php echo $staffRoleLabel !== '' ? $staffRoleLabel : 'Staff'; ?></p>
            </div>
        </div>

        <a href="<?php echo $staffLogoutUrl; ?>" class="flex items-center justify-center gap-2 w-full py-3 rounded-xl border border-slate-200 bg-white text-slate-700 font-bold text-xs uppercase tracking-widest hover:bg-slate-50 hover:border-slate-300 transition-all no-underline text-inherit">
            <span class="material-symbols-outlined text-[20px] text-slate-500">logout</span>
            Log out
        </a>
    </div>
</aside>

