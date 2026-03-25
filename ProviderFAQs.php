<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>FAQ - MyDental</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8cee",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922",
                    },
                    fontFamily: {
                        "display": ["Manrope"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        body {
            font-family: 'Manrope', sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 antialiased">
<div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<!-- Navigation Bar -->
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
?>
<!-- Navigation -->
<header style="position: sticky; top: 0; z-index: 50;" class="sticky top-0 z-50 w-full border-b border-on-surface/5 bg-white/70 dark:bg-background-dark/70 backdrop-blur-xl">
<div class="mx-auto max-w-[1800px] px-6 sm:px-8 lg:px-10">
<div class="flex h-16 items-center justify-between">
<div class="flex items-center gap-3">
<img src="MyDental%20Logo.svg" alt="MyDental Logo" class="h-9 w-auto" />
</div>
<nav class="hidden md:flex items-center gap-2 lg:gap-3">
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderMain.php">Home</a>
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="Provider-HowItWorks.php">More Features</a>
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="Provider-Plans.php">Pricing</a>
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderContact.php">Contact Us</a>
<a class="rounded-full px-4 py-2 text-[11px] font-black uppercase tracking-[0.18em] text-on-surface/70 hover:text-primary hover:bg-primary/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" href="ProviderFAQs.php">FAQs</a>
</nav>
<div class="flex items-center gap-3">
<?php if ($logged_in): ?>
<!-- Logged-in user card with dropdown -->
<div class="relative">
<details class="relative group">
<summary class="flex cursor-pointer list-none items-center gap-2 rounded-full border border-on-surface/10 bg-white/70 dark:bg-slate-900/30 px-3 py-2 shadow-sm hover:border-primary/25 hover:bg-white/90 dark:hover:bg-slate-900/45 transition-all focus:outline-none focus:ring-2 focus:ring-primary/25 [&::-webkit-details-marker]:hidden">
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
<a href="ProviderLogin.php" class="px-8 py-3 bg-primary text-white font-black rounded-full overflow-hidden transition-transform hover:scale-[1.02] active:scale-95 text-[11px] uppercase tracking-[0.22em] text-center transform-gpu focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 shadow-lg shadow-primary/25">
Login
</a>
<?php endif; ?>
</div>
</div>
</div>
</header>
<main class="flex-1">
<div class="px-6 lg:px-40 flex flex-1 justify-center py-12">
<div class="layout-content-container flex flex-col max-w-[800px] flex-1">
<div class="flex flex-col gap-4 mb-10 text-center md:text-left">
<h1 class="text-slate-900 dark:text-white text-4xl md:text-5xl font-black leading-tight tracking-[-0.033em]">Frequently Asked Questions</h1>
<p class="text-slate-600 dark:text-slate-400 text-lg font-normal max-w-2xl">
                            Everything you need to know about the MyDental platform and how it can transform your clinical operations.
                        </p>
</div>
<div class="flex flex-col gap-4">
<details class="flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-4 group shadow-sm hover:shadow-md transition-shadow" open="">
<summary class="flex cursor-pointer items-center justify-between gap-6 py-2 list-none outline-none">
<p class="text-slate-900 dark:text-white text-base md:text-lg font-semibold">What is MyDental?</p>
<div class="text-primary group-open:rotate-180 transition-transform duration-300">
<span class="material-symbols-outlined">expand_more</span>
</div>
</summary>
<div class="text-slate-600 dark:text-slate-400 text-base leading-relaxed pb-4 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                                MyDental is a comprehensive, cloud-based dental practice management platform. We provide tools for patient scheduling, electronic health records, billing automation, and clinical imaging, all designed to streamline operations and enhance the patient experience through modern digital interfaces.
                            </div>
</details>
<details class="flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-4 group shadow-sm hover:shadow-md transition-shadow">
<summary class="flex cursor-pointer items-center justify-between gap-6 py-2 list-none outline-none">
<p class="text-slate-900 dark:text-white text-base md:text-lg font-semibold">How do clinics create accounts?</p>
<div class="text-primary group-open:rotate-180 transition-transform duration-300">
<span class="material-symbols-outlined">expand_more</span>
</div>
</summary>
<div class="text-slate-600 dark:text-slate-400 text-base leading-relaxed pb-4 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                                Registering your clinic is simple. Click on the "Get Started" button on our homepage, provide your clinic's basic information and license details, and our team will verify your credentials within 24 hours. Once verified, you can immediately begin setting up your team profiles and patient database.
                            </div>
</details>
<details class="flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-4 group shadow-sm hover:shadow-md transition-shadow">
<summary class="flex cursor-pointer items-center justify-between gap-6 py-2 list-none outline-none">
<p class="text-slate-900 dark:text-white text-base md:text-lg font-semibold">Will each clinic have its own dashboard?</p>
<div class="text-primary group-open:rotate-180 transition-transform duration-300">
<span class="material-symbols-outlined">expand_more</span>
</div>
</summary>
<div class="text-slate-600 dark:text-slate-400 text-base leading-relaxed pb-4 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                                Yes, absolutely. Every clinic registered on MyDental receives a private, secure, and fully customizable dashboard. This dashboard provides real-time analytics on patient visits, revenue tracking, inventory management, and staff performance, ensuring you have total visibility into your practice's health.
                            </div>
</details>
<details class="flex flex-col rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-6 py-4 group shadow-sm hover:shadow-md transition-shadow">
<summary class="flex cursor-pointer items-center justify-between gap-6 py-2 list-none outline-none">
<p class="text-slate-900 dark:text-white text-base md:text-lg font-semibold">Can multiple clinics use the platform?</p>
<div class="text-primary group-open:rotate-180 transition-transform duration-300">
<span class="material-symbols-outlined">expand_more</span>
</div>
</summary>
<div class="text-slate-600 dark:text-slate-400 text-base leading-relaxed pb-4 pt-2 border-t border-slate-100 dark:border-slate-800 mt-2">
                                Yes, MyDental is built for scale. Whether you are a single private practice or a large Dental Service Organization (DSO) with hundreds of locations, our platform supports multi-site management. You can switch between locations seamlessly and generate consolidated reports for the entire organization.
                            </div>
</details>
</div>
<div class="mt-16 p-8 rounded-2xl bg-primary/5 border border-primary/10 flex flex-col md:flex-row items-center justify-between gap-6">
<div class="text-center md:text-left">
<h2 class="text-slate-900 dark:text-white text-[22px] font-bold leading-tight tracking-[-0.015em]">Still have questions?</h2>
<p class="text-slate-600 dark:text-slate-400 text-base font-normal mt-2">
                                If you cannot find the answer you are looking for, please contact our support team.
                            </p>
</div>
<div class="flex gap-3">
<button class="flex min-w-[120px] cursor-pointer items-center justify-center rounded-lg h-12 px-6 bg-primary text-white text-sm font-bold shadow-lg shadow-primary/20 hover:opacity-90 transition-all">
                                Contact Support
                            </button>
<button class="flex min-w-[120px] cursor-pointer items-center justify-center rounded-lg h-12 px-6 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">
                                View Docs
                            </button>
</div>
</div>
</div>
</div>
</main>
<footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 px-6 lg:px-40 py-10">
<div class="flex flex-col md:flex-row justify-between items-center gap-8">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-primary text-2xl">dentistry</span>
<span class="text-slate-900 dark:text-white font-bold text-lg">MyDental</span>
</div>
<div class="flex gap-8 text-slate-500 dark:text-slate-400 text-sm">
<a class="hover:text-primary transition-colors" href="#">Privacy Policy</a>
<a class="hover:text-primary transition-colors" href="#">Terms of Service</a>
<a class="hover:text-primary transition-colors" href="#">Cookies</a>
</div>
<div class="text-slate-400 text-sm">
                    © 2024 MyDental Inc. All rights reserved.
                </div>
</div>
</footer>
</div>
</div>
</body></html>