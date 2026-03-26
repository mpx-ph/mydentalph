<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('provider_normalize_status')) {
    function provider_normalize_status($status): string {
        return strtolower(trim((string) $status));
    }
}

// If session has a user_id, verify the user still exists and is approved.
// user_id can be 0 for hardcoded superadmin — empty() treats 0 as empty, so handle superadmin first
$logged_in = false;
$is_superadmin = (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'superadmin');
if ($is_superadmin) {
    $logged_in = true;
} elseif (!empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id'])) {
    try {
        require_once __DIR__ . '/db.php';
        $userId = (string) $_SESSION['user_id'];
        $tenantId = (string) $_SESSION['tenant_id'];

        $stmt = $pdo->prepare("SELECT 1 FROM tbl_users WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$userId, $tenantId]);
        $userActive = (bool) $stmt->fetchColumn();

        if (!$userActive) {
            // User no longer exists or is inactive — clear session so navbar shows Login
            unset(
                $_SESSION['user_id'],
                $_SESSION['tenant_id'],
                $_SESSION['username'],
                $_SESSION['email'],
                $_SESSION['full_name'],
                $_SESSION['role'],
                $_SESSION['is_owner']
            );
        } else {
            $stmt2 = $pdo->prepare("
                SELECT status
                FROM tbl_tenant_verification_requests
                WHERE tenant_id = ? AND owner_user_id = ?
                ORDER BY request_id DESC
                LIMIT 1
            ");
            $stmt2->execute([$tenantId, $userId]);
            $reqStatus = $stmt2->fetchColumn();
            $isApproved = provider_normalize_status($reqStatus !== false ? (string) $reqStatus : '') === 'approved';

            if ($isApproved) {
                $logged_in = true;
            } else {
                // Pending/rejected users must not see provider menus
                unset(
                    $_SESSION['user_id'],
                    $_SESSION['tenant_id'],
                    $_SESSION['username'],
                    $_SESSION['email'],
                    $_SESSION['full_name'],
                    $_SESSION['role'],
                    $_SESSION['is_owner']
                );
            }
        }
    } catch (Throwable $e) {
        // On DB error, clear session to avoid showing stale logged-in state
        unset(
            $_SESSION['user_id'],
            $_SESSION['tenant_id'],
            $_SESSION['username'],
            $_SESSION['email'],
            $_SESSION['full_name'],
            $_SESSION['role'],
            $_SESSION['is_owner']
        );
    }
}

$user_display_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? $_SESSION['email']
    ?? $_SESSION['onboarding_full_name'] ?? $_SESSION['onboarding_email'] ?? 'Account';
$user_initial = mb_strtoupper(mb_substr(trim($user_display_name), 0, 1)) ?: '?';
?>
<!-- Navigation -->
<style>
    /* Prevent navbar "twitch" during page navigation while Tailwind CDN loads. */
    .provider-navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 64px;
        width: 100%;
        z-index: 50;
        overflow: visible;
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        background: rgba(255, 255, 255, 0.7);
        border-bottom: 1px solid rgba(19, 28, 37, 0.05);
        transition: background-color 200ms ease-out;
        will-change: background-color;
    }
    .dark .provider-navbar {
        background: rgba(16, 25, 34, 0.7);
    }
    .provider-navbar-spacer {
        height: 64px;
    }
</style>
<header class="provider-navbar fixed top-0 left-0 right-0 z-50 h-16 w-full border-b border-on-surface/5 bg-white/70 dark:bg-background-dark/70 backdrop-blur-xl transition-colors duration-200 ease-out">
<div class="mx-auto max-w-[1800px] px-6 sm:px-8 lg:px-10 h-full">
<div class="grid h-full grid-cols-[auto,1fr,auto] items-center">
<div class="flex items-center gap-3">
<img
    src="MyDental%20Logo.svg"
    alt="MyDental Logo"
    width="144"
    height="36"
    loading="eager"
    decoding="async"
    class="h-9 w-[144px]"
/>
</div>
<nav class="hidden md:flex items-center justify-self-center gap-2 lg:gap-3">
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderMain.php">Home</a>
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="Provider-HowItWorks.php">More Features</a>
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="Provider-Plans.php">Pricing</a>
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderContact.php">Contact Us</a>
<a class="rounded-full px-4 py-2 leading-none text-[11px] font-semibold uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderFAQs.php">FAQs</a>
</nav>
<div class="flex items-center gap-3">
<?php if ($logged_in): ?>
<!-- Logged-in user card with dropdown -->
<div class="relative">
<details class="relative group">
<summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-on-surface/10 bg-white/70 dark:bg-slate-900/30 leading-none px-3 py-2 shadow-sm hover:border-primary/25 hover:bg-white/90 dark:hover:bg-slate-900/45 transition-all focus:outline-none focus:ring-2 focus:ring-primary/25 [&::-webkit-details-marker]:hidden">
<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary text-sm font-black"><?php echo htmlspecialchars($user_initial); ?></span>
<span class="hidden sm:inline text-[12px] font-black uppercase tracking-[0.12em] text-on-surface/70 dark:text-surface/80 max-w-[160px] truncate"><?php echo htmlspecialchars($user_display_name); ?></span>
<span class="material-symbols-outlined text-on-surface/40 dark:text-surface/50 text-lg transition-transform group-open:rotate-180">expand_more</span>
</summary>
<div class="absolute right-0 top-full z-50 mt-2 min-w-[200px] rounded-2xl border border-on-surface/10 bg-white/90 dark:bg-slate-900/80 py-2 shadow-xl backdrop-blur-xl">
<?php if ($is_superadmin): ?>
<a href="superadmin/dashboard.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface dark:text-surface hover:bg-primary/5 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg text-primary/70">admin_panel_settings</span> <span class="font-semibold">Super Admin</span>
</a>
<?php else: ?>
<a href="ProviderTenantDashboard.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface dark:text-surface hover:bg-primary/5 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg text-primary/70">dashboard</span> <span class="font-semibold">Manage</span>
</a>
<a href="ProviderProfile.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-on-surface dark:text-surface hover:bg-primary/5 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg text-primary/70">person</span> <span class="font-semibold">My Profile</span>
</a>
<?php endif; ?>
<div class="my-2 border-t border-on-surface/10"></div>
<a href="ProviderLogout.php" class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50/80 dark:hover:bg-red-900/20 transition-colors rounded-xl mx-2">
<span class="material-symbols-outlined text-lg">logout</span> <span class="font-semibold">Log Out</span>
</a>
</div>
</details>
</div>
<?php else: ?>
<a href="ProviderLogin.php" class="px-8 py-3 leading-none bg-primary text-white font-black rounded-full overflow-hidden transition-transform hover:scale-[1.02] active:scale-95 text-[11px] uppercase tracking-[0.22em] text-center transform-gpu focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 shadow-lg shadow-primary/25">
Login
</a>
<?php endif; ?>
</div>
</div>
</div>
</header>
<div aria-hidden="true" class="provider-navbar-spacer"></div>
