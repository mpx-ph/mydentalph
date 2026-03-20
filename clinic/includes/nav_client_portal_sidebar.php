<?php
/**
 * Client Portal Sidebar Navigation
 * Complete sidebar with menu and user card for authenticated patient pages
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Guest User';
?>
<aside class="hidden w-72 flex-col border-r border-slate-200/60 dark:border-slate-800 bg-surface-light dark:bg-surface-dark md:flex z-30">
<div class="flex min-h-24 justify-center px-6 border-b border-slate-100 dark:border-slate-800 pt-5 pb-4">
<div class="flex flex-col items-center w-full">
<img src="<?php echo BASE_URL; ?>DRCGLogo2.png" alt="DR. ROMARICO C. GONZALES" class="h-14 object-contain max-w-full"/>
<span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mt-1 mb-2">Client Portal</span>
</div>
</div>
<nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1.5">
<div class="px-4 pb-2 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Main Menu</div>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage == 'ClientWelcomeDashboard.php') ? 'bg-primary px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> transition-all" href="<?php echo BASE_URL; ?>ClientWelcomeDashboard.php">
<span class="material-symbols-outlined <?php echo ($currentPage == 'ClientWelcomeDashboard.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">dashboard</span>
                Dashboard
            </a>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage == 'BookAppointmentClient.php') ? 'bg-primary px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> transition-all" href="<?php echo BASE_URL; ?>BookAppointmentClient.php">
<span class="material-symbols-outlined <?php echo ($currentPage == 'BookAppointmentClient.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">event_available</span>
                Book Appointment
            </a>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage == 'MyBookingsClient.php') ? 'bg-primary px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> transition-all" href="<?php echo BASE_URL; ?>MyBookingsClient.php">
<span class="material-symbols-outlined <?php echo ($currentPage == 'MyBookingsClient.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">calendar_month</span>
                My Bookings
            </a>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage == 'ConvoClient.php') ? 'bg-primary px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> transition-all" href="<?php echo BASE_URL; ?>ConvoClient.php">
<span class="material-symbols-outlined <?php echo ($currentPage == 'ConvoClient.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">chat</span>
                Messages
            </a>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage == 'RecordPaymentClient.php') ? 'bg-primary px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> transition-all" href="<?php echo BASE_URL; ?>RecordPaymentClient.php">
<span class="material-symbols-outlined <?php echo ($currentPage == 'RecordPaymentClient.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">payments</span>
                Record Payment
            </a>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage == 'ClientMyFiles.php') ? 'bg-primary px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> transition-all" href="<?php echo BASE_URL; ?>ClientMyFiles.php">
<span class="material-symbols-outlined <?php echo ($currentPage == 'ClientMyFiles.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">folder</span>
                My Files
            </a>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage == 'MyProfileClient.php') ? 'bg-primary px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> transition-all" href="<?php echo BASE_URL; ?>MyProfileClient.php">
<span class="material-symbols-outlined <?php echo ($currentPage == 'MyProfileClient.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">account_circle</span>
                My Profile
            </a>
</nav>
<div class="m-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-4 border border-slate-100 dark:border-slate-700/50">
<div class="flex items-center gap-3">
<div id="userPhoto" class="h-10 w-10 overflow-hidden rounded-full ring-2 ring-white dark:ring-slate-700 shadow-sm" style='background-image: url("https://ui-avatars.com/api/?name=<?php echo urlencode($userName); ?>&background=2b8cee&color=fff&size=128"); background-size: cover; background-position: center;'></div>
<script>
(function() {
    try {
        const cachedPhoto = sessionStorage.getItem('userProfilePhoto');
        const cachedName = sessionStorage.getItem('userProfileName');
        if (cachedPhoto) {
            const userPhotoEl = document.getElementById('userPhoto');
            if (userPhotoEl) {
                userPhotoEl.style.backgroundImage = `url("${cachedPhoto}")`;
            }
        }
        if (cachedName) {
            const userNameEl = document.getElementById('userName');
            if (userNameEl) {
                userNameEl.textContent = cachedName;
            }
        } else {
            // Cache the name from PHP if not already cached
            const userNameEl = document.getElementById('userName');
            if (userNameEl) {
                const name = userNameEl.textContent.trim();
                if (name && name !== 'Guest User') {
                    sessionStorage.setItem('userProfileName', name);
                }
            }
        }
    } catch(e) {}
})();
</script>
<div class="flex flex-col min-w-0">
<p id="userName" class="truncate text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($userName); ?></p>
<p id="userRole" class="truncate text-xs font-medium text-slate-500 dark:text-slate-400">Patient</p>
</div>
<button id="logoutBtn" type="button" onclick="showLogoutModal('<?php echo BASE_URL; ?>api/logout.php', event)" class="ml-auto flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-primary dark:hover:bg-slate-700 transition-colors">
<span class="material-symbols-outlined" style="font-size: 18px;">logout</span>
</button>
    </div>
</div>
</aside>
<script>
    // Expose BASE_URL to JavaScript for smooth navigation
    window.BASE_URL = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo BASE_URL; ?>js/smooth-navigation.js"></script>
<script src="<?php echo BASE_URL; ?>js/logout-modal.js"></script>