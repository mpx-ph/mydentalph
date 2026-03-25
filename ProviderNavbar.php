<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If session has a user_id, verify the user still exists in DB (e.g. after a DB reset)
// Also treat onboarding (onboarding_user_id) as "logged in" so Purchase/Setup show the account
// user_id can be 0 for hardcoded superadmin — empty() treats 0 as empty, so handle superadmin first
$logged_in = false;
$is_superadmin = (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'superadmin');
if ($is_superadmin) {
    $logged_in = true;
} elseif (!empty($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/db.php';
        $stmt = $pdo->prepare("SELECT 1 FROM tbl_users WHERE user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $logged_in = true;
        } else {
            // User no longer exists or is inactive — clear session so navbar shows Login
            unset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['full_name'], $_SESSION['role'], $_SESSION['is_owner']);
        }
    } catch (Throwable $e) {
        // On DB error, clear session to avoid showing stale logged-in state
        unset($_SESSION['user_id'], $_SESSION['tenant_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['full_name'], $_SESSION['role'], $_SESSION['is_owner']);
    }
}
if (!$logged_in && (!empty($_SESSION['onboarding_user_id']) || !empty($_SESSION['onboarding_pending_id']))) {
    $logged_in = true;
}

$user_display_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email']
    ?? $_SESSION['onboarding_full_name'] ?? $_SESSION['onboarding_email'] ?? 'Account';
$user_initial = mb_strtoupper(mb_substr(trim($user_display_name), 0, 1)) ?: '?';

$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$nav_items = [
    ['href' => 'ProviderMain.php', 'label' => 'Home'],
    ['href' => 'Provider-HowItWorks.php', 'label' => 'More Features'],
    ['href' => 'Provider-Plans.php', 'label' => 'Pricing'],
    ['href' => 'ProviderContact.php', 'label' => 'Contact Us'],
    ['href' => 'ProviderFAQs.php', 'label' => 'FAQs'],
];
?>
<!-- Navigation -->
<header class="sticky top-0 z-50 w-full border-b border-primary/10 bg-background-light/75 dark:bg-background-dark/75 backdrop-blur-xl shadow-sm">
<div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
<div class="flex h-16 items-center justify-between">
<a class="flex items-center gap-3 rounded-xl px-2 py-1 transition-colors hover:bg-white/60 dark:hover:bg-white/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderMain.php">
<img src="MyDental%20Logo.svg" alt="My Dental Logo" class="h-9 w-auto" />
<div class="hidden sm:block leading-none">
<div class="text-sm font-extrabold tracking-tight text-slate-900 dark:text-white font-headline">My Dental</div>
<div class="mt-0.5 text-[11px] font-semibold tracking-tight text-slate-500 dark:text-slate-400">Clinic Management OS</div>
</div>
</a>
</div>
<nav class="hidden md:flex items-center gap-2 rounded-full border border-slate-200/70 dark:border-slate-700/60 bg-white/60 dark:bg-white/5 px-2 py-1">
<?php foreach ($nav_items as $item): ?>
<?php $is_active = ($current_page === $item['href']); ?>
<a
  href="<?php echo htmlspecialchars($item['href']); ?>"
  class="<?php echo $is_active
      ? 'relative rounded-full px-4 py-2 text-sm font-extrabold tracking-tight text-primary bg-primary/10 ring-1 ring-primary/20'
      : 'relative rounded-full px-4 py-2 text-sm font-semibold tracking-tight text-slate-700 dark:text-slate-200 hover:text-primary hover:bg-primary/5 transition-colors'; ?>"
>
  <?php echo htmlspecialchars($item['label']); ?>
  <?php if ($is_active): ?>
    <span class="pointer-events-none absolute left-1/2 -bottom-1.5 h-1 w-1 -translate-x-1/2 rounded-full bg-primary shadow-[0_0_0_4px_rgba(43,139,235,0.12)]"></span>
  <?php endif; ?>
</a>
<?php endforeach; ?>
</nav>
<div class="flex items-center gap-3">
<?php if ($logged_in): ?>
<!-- Logged-in user card with dropdown -->
<div class="relative">
<details class="relative group">
<summary class="flex cursor-pointer list-none items-center gap-2 rounded-xl border border-primary/20 bg-white/90 dark:bg-slate-800/80 px-3 py-2 shadow-sm hover:border-primary/40 hover:bg-white dark:hover:bg-slate-800 transition-all focus:outline-none focus:ring-2 focus:ring-primary/20 [&::-webkit-details-marker]:hidden">
<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary text-sm font-bold"><?php echo htmlspecialchars($user_initial); ?></span>
<span class="hidden sm:inline text-sm font-semibold tracking-tight text-slate-700 dark:text-slate-200 max-w-[140px] truncate"><?php echo htmlspecialchars($user_display_name); ?></span>
<span class="material-symbols-outlined text-slate-500 dark:text-slate-400 text-lg transition-transform group-open:rotate-180">expand_more</span>
</summary>
<div class="absolute right-0 top-full z-50 mt-1 min-w-[180px] rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 py-1 shadow-lg">
<?php if ($is_superadmin): ?>
<a href="superadmin/dashboard.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/80 transition-colors">
<span class="material-symbols-outlined text-lg text-slate-500">admin_panel_settings</span> Super Admin
</a>
<?php else: ?>
<a href="ProviderTenantDashboard.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/80 transition-colors">
<span class="material-symbols-outlined text-lg text-slate-500">dashboard</span> Manage
</a>
<a href="ProviderProfile.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700/80 transition-colors">
<span class="material-symbols-outlined text-lg text-slate-500">person</span> My Profile
</a>
<?php endif; ?>
<div class="my-1 border-t border-slate-100 dark:border-slate-700"></div>
<a href="ProviderLogout.php" class="flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
<span class="material-symbols-outlined text-lg">logout</span> Log Out
</a>
</div>
</details>
</div>
<?php else: ?>
<a href="ProviderLogin.php" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-primary/25 hover:bg-primary/90 transition-all">
                        Login
                    </a>
<?php endif; ?>
</div>
</div>
</div>
</header>
