<?php
/**
 * Client Portal Navigation Sidebar
 * For authenticated client pages (BookAppointmentClient, ConvoClient)
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$userName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Guest User';
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'Patient';
$userInitials = isset($_SESSION['full_name']) ? strtoupper(substr($_SESSION['full_name'], 0, 1)) : 'U';
?>
<div class="flex h-screen w-full overflow-hidden">
<aside class="hidden w-72 flex-col border-r border-slate-200/60 dark:border-slate-800 bg-surface-light dark:bg-surface-dark md:flex z-30">
<div class="flex min-h-24 justify-center px-6 border-b border-slate-100 dark:border-slate-800 pt-5 pb-4">
<div class="flex flex-col items-center w-full">
<img src="<?php echo BASE_URL; ?>DRCGLogo2.png" alt="DR. ROMARICO C. GONZALES" class="h-14 object-contain max-w-full"/>
<span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mt-1 mb-2">Client Portal</span>
</div>
</div>
<nav class="flex-1 overflow-y-auto px-4 py-4 space-y-1.5">
<div class="px-4 pb-2 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-400">Main Menu</div>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage === 'BookAppointmentClient.php') ? 'bg-primary text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> px-4 py-3.5 text-sm font-semibold transition-all" href="<?php echo BASE_URL; ?>BookAppointmentClient.php">
<span class="material-symbols-outlined <?php echo ($currentPage === 'BookAppointmentClient.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">event_available</span>
                Book Appointment
            </a>
<a class="group flex items-center gap-3 rounded-xl <?php echo ($currentPage === 'ConvoClient.php') ? 'bg-primary text-white shadow-md shadow-primary/25' : 'px-4 py-3.5 text-sm font-medium text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/50 hover:text-slate-900 dark:hover:text-white'; ?> px-4 py-3.5 text-sm font-semibold transition-all" href="<?php echo BASE_URL; ?>ConvoClient.php">
<span class="material-symbols-outlined <?php echo ($currentPage === 'ConvoClient.php') ? 'fill-1' : ''; ?>" style="font-size: 20px;">chat</span>
                Messages
            </a>
</nav>
<div class="m-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 p-4 border border-slate-100 dark:border-slate-700/50">
<div class="flex items-center gap-3">
<div id="userPhoto" class="h-10 w-10 overflow-hidden rounded-full ring-2 ring-white dark:ring-slate-700 shadow-sm flex items-center justify-center bg-primary text-white font-bold" style='background-image: url("https://ui-avatars.com/api/?name=<?php echo urlencode($userName); ?>&background=2b8cee&color=fff&size=128"); background-size: cover; background-position: center;'>
</div>
<div class="flex flex-col min-w-0">
<p id="userName" class="truncate text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($userName); ?></p>
<p id="userRole" class="truncate text-xs font-medium text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($userRole); ?></p>
</div>
<a href="<?php echo BASE_URL; ?>api/logout.php" class="ml-auto flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-white hover:text-primary dark:hover:bg-slate-700 transition-colors">
<span class="material-symbols-outlined" style="font-size: 18px;">logout</span>
</a>
</div>
</div>
</aside>
<main class="flex flex-1 flex-col h-full overflow-hidden relative bg-background-light dark:bg-background-dark">

