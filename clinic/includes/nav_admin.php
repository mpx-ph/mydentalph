<?php
/**
 * Admin Navigation Sidebar
 * Includes both desktop and mobile navigation
 */
if (!isset($CLINIC)) { require_once __DIR__ . '/clinic_customization.php'; }
$currentPage = basename($_SERVER['PHP_SELF']);
$adminNavLogo = isset($CLINIC['logo_nav']) ? trim($CLINIC['logo_nav']) : 'DRCGLogo2.png';
$adminNavLogoUrl = (strpos($adminNavLogo, 'http') === 0) ? $adminNavLogo : (BASE_URL . ltrim($adminNavLogo, '/'));
$adminNavLogoAlt = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Admin Panel';

// Slug-aware page URLs for tenant-scoped admin navigation
$urlAdminDashboard = clinicPageUrl('AdminDashboard.php');
$urlAdminPatientRecords = clinicPageUrl('AdminPatientRecords.php');
$urlAdminManageServices = clinicPageUrl('AdminManageServices.php');
$urlAdminPaymentRecording = clinicPageUrl('AdminPaymentRecording.php');
$urlAdminPaymentSettings = clinicPageUrl('Admin_PaymentSettings.php');
$urlAdminReports = clinicPageUrl('AdminReports.php');
$urlAdminUserAccounts = clinicPageUrl('AdminUserAccounts.php');
$urlAdminReviews = clinicPageUrl('Admin_Reviews.php');
$urlAdminCustomize = clinicPageUrl('AdminCustomize.php');
$urlAdminMyProfile = clinicPageUrl('AdminMyProfile.php');

// Fetch real user data from database
$userName = 'Admin User';
$userRole = 'Admin';
$userProfileImage = null;

if (isset($_SESSION['user_id'])) {
    try {
        if (!function_exists('getDBConnection')) {
            require_once __DIR__ . '/../config/database.php';
        }
        $pdo = getDBConnection();
        $sid = $_SESSION['user_id'];
        $user = null;

        // String user_id = from provider tbl_users (e.g. tenant_owner SSO)
        if (!is_numeric($sid)) {
            try {
                $stmt = $pdo->prepare("SELECT user_id, full_name, role FROM tbl_users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$sid]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $userName = isset($user['full_name']) ? $user['full_name'] : (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin User');
                    $userRole = ($user['role'] === 'tenant_owner' || $user['role'] === 'manager') ? 'Manager' : (($user['role'] === 'dentist') ? 'Doctor' : 'Staff');
                }
            } catch (Exception $e) {
                $user = null;
            }
        }

        // Numeric id = clinic users table
        if ($user === null) {
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.role,
                       COALESCE(p.first_name, s.first_name) as first_name,
                       COALESCE(p.last_name, s.last_name) as last_name,
                       COALESCE(p.profile_image, s.profile_image) as profile_image
                FROM tbl_users u
                LEFT JOIN patients p ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id AND u.role = 'client'
                LEFT JOIN staffs s ON s.user_id = u.user_id AND u.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                WHERE u.user_id = ?
            ");
            $stmt->execute([$sid]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($user) {
            if (isset($user['first_name']) || isset($user['last_name'])) {
                $userName = trim((isset($user['first_name']) ? $user['first_name'] : '') . ' ' . (isset($user['last_name']) ? $user['last_name'] : ''));
            }
            if (isset($user['role'])) {
                $userRole = ($user['role'] === 'dentist') ? 'Doctor' : (($user['role'] === 'tenant_owner') ? 'Manager' : ucfirst($user['role']));
            }
            if (!empty($user['profile_image'])) {
                $userProfileImage = BASE_URL . ltrim($user['profile_image'], '/');
            }
        } else {
            $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin User';
            $userRole = isset($_SESSION['user_type']) ? ucfirst($_SESSION['user_type']) : 'Admin';
        }
    } catch (Exception $e) {
        error_log('Error fetching user data in nav_admin.php: ' . $e->getMessage());
        $userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin User';
        $userRole = isset($_SESSION['user_type']) ? ucfirst($_SESSION['user_type']) : 'Admin';
    }
} else {
    // No session, use defaults
    $userName = 'Admin User';
    $userRole = 'Admin';
}

$userInitials = strtoupper(substr($userName, 0, 1));
?>
<div class="flex h-screen w-full overflow-hidden">
<aside class="hidden w-72 flex-col border-r border-slate-200/60 dark:border-slate-800 bg-surface-light dark:bg-surface-dark md:flex z-30">
<div class="flex min-h-24 justify-center px-6 border-b border-slate-100 dark:border-slate-800 pt-5 pb-4">
<div class="flex flex-col items-center w-full">
<img src="<?php echo $adminNavLogoUrl; ?>" alt="<?php echo $adminNavLogoAlt; ?>" class="h-14 object-contain max-w-full"/>
<span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mt-1 mb-2">Admin Panel</span>
</div>
</div>
<nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1.5">
<div class="px-4 pb-2 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Main Menu</div>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage === 'AdminDashboard.php') ? 'bg-primary text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> px-4 py-3.5 text-sm font-semibold transition-all" href="<?php echo htmlspecialchars($urlAdminDashboard, ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined <?php echo ($currentPage === 'AdminDashboard.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">dashboard</span>
                Dashboard
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'AdminPatientRecords.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminPatientRecords, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">group</span>
                Patients
            </a>
<div class="my-6 border-t border-slate-100 dark:border-slate-800 mx-4"></div>
<div class="px-4 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Management</div>
<a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'AdminManageServices.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminManageServices, ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined" style="font-size: 20px;">payments</span>
                Services & Pricing
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'AdminPaymentRecording.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminPaymentRecording, ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined" style="font-size: 20px;">receipt</span>
                Payments
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'Admin_PaymentSettings.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminPaymentSettings, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">settings</span>
                Payment Settings
            </a>
<a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'AdminReports.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminReports, ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined" style="font-size: 20px;">bar_chart</span>
                Reports
            </a>
<a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'AdminUserAccounts.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminUserAccounts, ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined" style="font-size: 20px;">people</span>
                Users
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'Admin_Reviews.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminReviews, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">rate_review</span>
                Reviews
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'AdminCustomize.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminCustomize, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">palette</span>
                Customize Website
            </a>
            <div class="my-6 border-t border-slate-100 dark:border-slate-800 mx-4"></div>
            <div class="px-4 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Account</div>
            <a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage === 'AdminMyProfile.php') ? 'bg-primary text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> px-4 py-3.5 text-sm font-semibold transition-all" href="<?php echo htmlspecialchars($urlAdminMyProfile, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined <?php echo ($currentPage === 'AdminMyProfile.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">account_circle</span>
                My Profile
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all" href="<?php echo PROVIDER_BASE_URL; ?>ProviderMyDentalSSO.php">
                <span class="material-symbols-outlined" style="font-size: 20px;">login</span>
                Login using MyDental
            </a>
</nav>
<div class="m-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-4 border border-slate-100 dark:border-slate-700/50">
<div class="flex items-center gap-3">
<div id="userPhoto" class="h-10 w-10 overflow-hidden rounded-full ring-2 ring-white dark:ring-slate-700 shadow-sm flex items-center justify-center bg-primary text-white font-bold" style='background-image: url("https://ui-avatars.com/api/?name=<?php echo urlencode($userName); ?>&background=2563eb&color=fff&size=128"); background-size: cover; background-position: center;'>
</div>
<div class="flex flex-col min-w-0">
<p id="userName" class="truncate text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($userName); ?></p>
<p id="userRole" class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($userRole); ?></p>
</div>
<button type="button" onclick="showLogoutModal('<?php echo BASE_URL; ?>api/logout.php', event)" class="ml-auto flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-primary dark:hover:bg-slate-700 transition-colors">
<span class="material-symbols-outlined" style="font-size: 18px;">logout</span>
</button>
</div>
</div>
</aside>
<!-- Mobile Sidebar Overlay -->
<div id="mobileSidebar" class="fixed inset-0 z-50 hidden md:hidden">
    <div class="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm" id="mobileSidebarBackdrop"></div>
    <aside class="absolute left-0 top-0 h-full w-72 flex-col border-r border-slate-200/60 dark:border-slate-800 bg-surface-light dark:bg-surface-dark z-50 transform transition-transform duration-300 ease-in-out -translate-x-full" id="mobileSidebarMenu">
        <div class="flex min-h-24 justify-center px-6 border-b border-slate-100 dark:border-slate-800 pt-5 pb-4">
            <div class="flex flex-col items-center w-full">
                <img src="<?php echo $adminNavLogoUrl; ?>" alt="<?php echo $adminNavLogoAlt; ?>" class="h-14 object-contain max-w-full"/>
                <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mt-1 mb-2">Admin Panel</span>
            </div>
        </div>
        <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1.5">
            <div class="px-4 pb-2 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Main Menu</div>
            <a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage === 'AdminDashboard.php') ? 'bg-primary text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> px-4 py-3.5 text-sm font-semibold transition-all" href="<?php echo htmlspecialchars($urlAdminDashboard, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined <?php echo ($currentPage === 'AdminDashboard.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">dashboard</span>
                Dashboard
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all" href="<?php echo htmlspecialchars($urlAdminPatientRecords, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">group</span>
                Patients
            </a>
            <div class="my-6 border-t border-slate-100 dark:border-slate-800 mx-4"></div>
            <div class="px-4 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Management</div>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'AdminManageServices.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminManageServices, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">payments</span>
                Services & Pricing
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all" href="<?php echo htmlspecialchars($urlAdminPaymentRecording, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">receipt</span>
                Payments
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'Admin_PaymentSettings.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminPaymentSettings, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">settings</span>
                Payment Settings
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all" href="<?php echo htmlspecialchars($urlAdminReports, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">bar_chart</span>
                Reports
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all" href="<?php echo htmlspecialchars($urlAdminUserAccounts, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">people</span>
                Users
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all" href="<?php echo htmlspecialchars($urlAdminReviews, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">rate_review</span>
                Reviews
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all <?php echo ($currentPage === 'AdminCustomize.php') ? 'bg-primary/10 text-primary' : ''; ?>" href="<?php echo htmlspecialchars($urlAdminCustomize, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">palette</span>
                Customize Website
            </a>
            <div class="my-6 border-t border-slate-100 dark:border-slate-800 mx-4"></div>
            <div class="px-4 pb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Account</div>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all" href="<?php echo htmlspecialchars($urlAdminMyProfile, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" style="font-size: 20px;">account_circle</span>
                My Profile
            </a>
            <a class="group flex items-center gap-3 rounded-xl px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white transition-all" href="<?php echo PROVIDER_BASE_URL; ?>ProviderMyDentalSSO.php">
                <span class="material-symbols-outlined" style="font-size: 20px;">login</span>
                Login using MyDental
            </a>
        </nav>
        <div class="m-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-4 border border-slate-100 dark:border-slate-700/50">
            <div class="flex items-center gap-3">
                <div id="mobileUserPhoto" class="h-10 w-10 overflow-hidden rounded-full ring-2 ring-white dark:ring-slate-700 shadow-sm flex items-center justify-center bg-primary text-white font-bold" style='background-image: url("https://ui-avatars.com/api/?name=<?php echo urlencode($userName); ?>&background=2563eb&color=fff&size=128"); background-size: cover; background-position: center;'>
                </div>
                <div class="flex flex-col min-w-0">
                    <p id="mobileUserName" class="truncate text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($userName); ?></p>
                    <p id="mobileUserRole" class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($userRole); ?></p>
                </div>
                <button type="button" onclick="showLogoutModal('<?php echo BASE_URL; ?>api/logout.php', event)" class="ml-auto flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-primary dark:hover:bg-slate-700 transition-colors">
                    <span class="material-symbols-outlined" style="font-size: 18px;">logout</span>
                </button>
            </div>
        </div>
    </aside>
</div>
<main class="flex flex-1 flex-col h-full overflow-hidden relative bg-background-light dark:bg-background-dark">
<header class="flex h-20 items-center justify-between px-6 lg:px-10 z-10 sticky top-0 bg-background-light/80 dark:bg-background-dark/80 backdrop-blur-md lg:hidden">
<div class="flex items-center gap-4">
<button id="mobileMenuBtn" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" aria-label="Toggle menu">
<span class="material-symbols-outlined text-[24px]">menu</span>
</button>
<img src="<?php echo $adminNavLogoUrl; ?>" alt="<?php echo $adminNavLogoAlt; ?>" class="h-8 object-contain"/>
</div>
</header>
<script>
// Mobile sidebar toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const mobileSidebarMenu = document.getElementById('mobileSidebarMenu');
    const mobileSidebarBackdrop = document.getElementById('mobileSidebarBackdrop');
    
    if (mobileMenuBtn && mobileSidebar) {
        mobileMenuBtn.addEventListener('click', function() {
            mobileSidebar.classList.remove('hidden');
            setTimeout(() => {
                mobileSidebarMenu.classList.remove('-translate-x-full');
            }, 10);
        });
        
        mobileSidebarBackdrop.addEventListener('click', function() {
            mobileSidebarMenu.classList.add('-translate-x-full');
            setTimeout(() => {
                mobileSidebar.classList.add('hidden');
            }, 300);
        });
    }
    
    // Load user profile photo from API
    async function loadProfilePhoto() {
        try {
            // Determine which API to use based on user type
            const userType = '<?php echo isset($_SESSION['user_type']) ? $_SESSION['user_type'] : ''; ?>';
            const apiUrl = (userType === 'client') 
                ? '<?php echo BASE_URL; ?>api/profile.php'
                : '<?php echo BASE_URL; ?>api/admin_profile.php';
            
            const response = await fetch(apiUrl, {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            
            if (data.success && data.data && data.data.profile_image) {
                const photoUrl = '<?php echo BASE_URL; ?>' + data.data.profile_image.replace(/^\/+/, '');
                updateSidebarPhoto(photoUrl);
            }
        } catch (error) {
            console.error('Error loading profile photo:', error);
        }
    }
    
    // Update sidebar photo
    function updateSidebarPhoto(photoUrl) {
        const userPhotoEl = document.getElementById('userPhoto');
        const mobileUserPhotoEl = document.getElementById('mobileUserPhoto');
        
        if (userPhotoEl && photoUrl) {
            userPhotoEl.style.backgroundImage = `url("${photoUrl}")`;
        }
        
        if (mobileUserPhotoEl && photoUrl) {
            mobileUserPhotoEl.style.backgroundImage = `url("${photoUrl}")`;
        }
    }
    
    // Load profile photo on page load
    loadProfilePhoto();
    
    // Update user name and role from PHP session if needed
    <?php if (!empty($userName)): ?>
    const userNameEl = document.getElementById('userName');
    const userRoleEl = document.getElementById('userRole');
    const mobileUserNameEl = document.getElementById('mobileUserName');
    const mobileUserRoleEl = document.getElementById('mobileUserRole');
    
    if (userNameEl) userNameEl.textContent = '<?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>';
    if (userRoleEl) userRoleEl.textContent = '<?php echo htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8'); ?>';
    if (mobileUserNameEl) mobileUserNameEl.textContent = '<?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>';
    if (mobileUserRoleEl) mobileUserRoleEl.textContent = '<?php echo htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8'); ?>';
    <?php endif; ?>
    
    // Update photo if available from PHP
    <?php if (!empty($userProfileImage)): ?>
    updateSidebarPhoto('<?php echo htmlspecialchars($userProfileImage, ENT_QUOTES, 'UTF-8'); ?>');
    <?php endif; ?>
});
</script>
<script src="<?php echo BASE_URL; ?>js/logout-modal.js"></script>

