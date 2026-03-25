<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/db.php';

$tenant_id = null;
$user_id = null;
$plan_slug = null;

if (!empty($_SESSION['onboarding_user_id']) && !empty($_SESSION['onboarding_tenant_id'])) {
    $tenant_id = $_SESSION['onboarding_tenant_id'];
    $user_id = $_SESSION['onboarding_user_id'];
    $plan_slug = $_SESSION['onboarding_plan'] ?? 'professional';
} elseif (!empty($_SESSION['user_id']) && !empty($_SESSION['tenant_id'])) {
    $tid = $_SESSION['tenant_id'];
    $stmt = $pdo->prepare("SELECT subscription_status FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $has_active = ($row && ($row['subscription_status'] ?? '') === 'active');
    if (!$has_active) {
        $stmt = $pdo->prepare("SELECT 1 FROM tbl_tenant_subscriptions WHERE tenant_id = ? AND payment_status = 'paid' LIMIT 1");
        $stmt->execute([$tid]);
        $has_active = (bool) $stmt->fetch();
    }
    if ($has_active) {
        header('Location: ProviderTenantDashboard.php');
        exit;
    }
    $tenant_id = $_SESSION['tenant_id'];
    $user_id = $_SESSION['user_id'];
    $plan_slug = isset($_GET['plan']) ? strtolower(trim($_GET['plan'])) : 'professional';
    $allowed = ['starter', 'professional', 'enterprise'];
    if (!in_array($plan_slug, $allowed, true)) {
        $plan_slug = 'professional';
    }
    $_SESSION['onboarding_plan'] = $plan_slug;
}

if ($tenant_id === null || $user_id === null) {
    header('Location: ProviderClinicSetup.php');
    exit;
}

// Require business permit verification before purchase step.
$business_verification = null;
try {
    $stmt = $pdo->prepare("
        SELECT verification_id
        FROM tbl_tenant_business_verifications
        WHERE tenant_id = ? AND verification_status = 'submitted'
        ORDER BY verification_id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $business_verification = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $business_verification = null;
}
if (!$business_verification) {
    header('Location: VerifyBusiness.php');
    exit;
}

// Simulate payment failure (for testing)
if (isset($_GET['simulate']) && $_GET['simulate'] === 'fail') {
    $_SESSION = [];
    header('Location: ProviderMain.php');
    exit;
}

// Get tenant (clinic) and owner user details
$stmt = $pdo->prepare("
    SELECT t.clinic_name, t.contact_email, t.contact_phone, t.clinic_address,
           u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
    FROM tbl_tenants t
    LEFT JOIN tbl_users u ON t.owner_user_id = u.user_id
    WHERE t.tenant_id = ?
");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT plan_id, plan_name, price FROM tbl_subscription_plans WHERE plan_slug = ? LIMIT 1");
$stmt->execute([$plan_slug]);
$plan = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$plan) {
    $stmt = $pdo->query("SELECT plan_id, plan_name, price FROM tbl_subscription_plans WHERE plan_slug = 'professional' LIMIT 1");
    $plan = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['plan_id' => 1, 'plan_name' => 'Professional', 'price' => 2499];
}
$plan_name = $plan['plan_name'] ?? 'Professional';
$plan_price = $plan['price'] ?? 2499;
$plan_id = $plan['plan_id'] ?? 1;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = strtolower(trim($_POST['payment_method'] ?? 'card'));
    // Accept "maya" from UI but normalize to PayMongo's "paymaya"
    if ($payment_method === 'maya') {
        $payment_method = 'paymaya';
    }
    $valid_methods = ['card', 'gcash', 'paymaya', 'bank_transfer'];
    if (!in_array($payment_method, $valid_methods, true)) {
        $payment_method = 'card';
    }
    $clinic_name = trim($_POST['clinic_name'] ?? '');
    $contact_email = trim($_POST['clinic_email'] ?? '');
    $contact_phone = trim($_POST['clinic_phone'] ?? '');
    $clinic_address = trim($_POST['clinic_address'] ?? '');
    try {
        $stmt = $pdo->prepare("UPDATE tbl_tenants SET clinic_name = COALESCE(NULLIF(?, ''), clinic_name), contact_email = COALESCE(NULLIF(?, ''), contact_email), contact_phone = ?, clinic_address = ? WHERE tenant_id = ?");
        $stmt->execute([$clinic_name, $contact_email, $contact_phone, $clinic_address, $tenant_id]);
    } catch (PDOException $e) {
        $error = "Could not save clinic details. Please try again.";
    }

    if ($error === '' && in_array($payment_method, ['card', 'gcash', 'paymaya'], true)) {
        // PayMongo flow (card / gcash / maya): create Payment Intent and redirect to checkout
        require_once __DIR__ . '/paymongo_config.php';
        $secret = defined('PAYMONGO_SECRET_KEY') ? PAYMONGO_SECRET_KEY : '';
        if ($secret !== '' && strpos($secret, 'YOUR_') === false) {
            $amount_centavos = (int) round($plan_price * 100);
            if ($amount_centavos < 10000) {
                $amount_centavos = 10000;
            }
            $allowed_methods = [$payment_method];
            $payload = json_encode([
                'data' => [
                    'attributes' => [
                        'amount' => $amount_centavos,
                        'currency' => 'PHP',
                        'payment_method_allowed' => $allowed_methods,
                        'description' => 'MyDental - ' . ($plan_name ?? 'Professional') . ' plan',
                    ]
                ]
            ]);
            $ch = curl_init('https://api.paymongo.com/v1/payment_intents');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode($secret . ':'),
                ],
                CURLOPT_RETURNTRANSFER => true,
            ]);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = $res ? json_decode($res, true) : null;
            $client_key = $data['data']['attributes']['client_key'] ?? null;
            $payment_intent_id = $data['data']['id'] ?? null;
            if ($client_key && $payment_intent_id) {
                $_SESSION['paymongo_client_key'] = $client_key;
                $_SESSION['paymongo_payment_intent_id'] = $payment_intent_id;
                $_SESSION['paymongo_payment_method'] = $payment_method; // card | gcash | paymaya
                $_SESSION['paymongo_billing_email'] = $tenant['owner_email'] ?? $tenant['contact_email'] ?? '';
                $_SESSION['paymongo_tenant_id'] = $tenant_id;
                $_SESSION['paymongo_user_id'] = $user_id;
                $_SESSION['paymongo_plan_id'] = $plan_id;
                $_SESSION['paymongo_plan_name'] = $plan_name;
                $_SESSION['paymongo_plan_price'] = $plan_price;
                header('Location: ProviderPayMongoCheckout.php');
                exit;
            }
            $error = 'Could not create payment session. Please try again or choose another payment method.';
        } else {
            $error = 'PayMongo payments require API keys. Set PAYMONGO_SECRET_KEY and PAYMONGO_PUBLIC_KEY in paymongo_config.php or environment, or choose Bank Transfer for demo.';
        }
    }

    if ($error === '' && $payment_method === 'bank_transfer') {
        // Bank transfer/manual: mark as paid directly
        try {
            $start = date('Y-m-d');
            $end = date('Y-m-d', strtotime('+1 month'));
            $stmt = $pdo->prepare("INSERT INTO tbl_tenant_subscriptions (tenant_id, plan_id, subscription_start, subscription_end, payment_status, payment_method, amount_paid, reference_number) VALUES (?, ?, ?, ?, 'paid', ?, ?, ?)");
            // Placeholders: tenant_id, plan_id, subscription_start, subscription_end, payment_method, amount_paid, reference_number
            $stmt->execute([
                $tenant_id,
                $plan_id,
                $start,
                $end,
                $payment_method,
                $plan_price,
                'TXN-' . strtoupper(bin2hex(random_bytes(4)))
            ]);
            $stmt = $pdo->prepare("UPDATE tbl_tenants SET subscription_status = 'active' WHERE tenant_id = ?");
            $stmt->execute([$tenant_id]);
            $stmt = $pdo->prepare("SELECT user_id, tenant_id, username, email, full_name, role FROM tbl_users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $stmt2 = $pdo->prepare("SELECT owner_user_id FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
                $stmt2->execute([$user['tenant_id']]);
                $t = $stmt2->fetch(PDO::FETCH_ASSOC);
                $_SESSION['is_owner'] = ($t && isset($t['owner_user_id']) && $t['owner_user_id'] === $user['user_id']);
            }
            unset($_SESSION['onboarding_user_id'], $_SESSION['onboarding_tenant_id'], $_SESSION['onboarding_pending_id'], $_SESSION['onboarding_email'], $_SESSION['onboarding_plan'], $_SESSION['onboarding_full_name'], $_SESSION['onboarding_username']);
            header('Location: ProviderTenantDashboard.php');
            exit;
        } catch (PDOException $e) {
            $error = "Payment could not be processed. Please try again or <a href=\"ProviderPurchase.php?simulate=fail\">return to home</a>.";
        }
    }
}
?>
<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Purchase Your Plan - MyDental.com</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
        .form-input:focus {
            border-color: #2b8cee !important;
            ring-color: #2b8cee !important;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen font-display">
<div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
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
<header class="sticky top-0 z-50 w-full border-b border-on-surface/5 bg-white/70 dark:bg-background-dark/70 backdrop-blur-xl">
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
<main class="flex flex-1 justify-center py-10 px-4 md:px-0">
<div class="layout-content-container flex flex-col max-w-[800px] flex-1">
<!-- Page Header -->
<div class="flex flex-col gap-2 mb-8">
<h1 class="text-slate-900 dark:text-white text-4xl font-black leading-tight tracking-tight">Purchase Your Plan</h1>
<p class="text-slate-500 dark:text-slate-400 text-lg">Complete the details below to activate your professional dental clinic subscription.</p>
</div>
<?php if ($error): ?>
<div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-xl text-sm"><?php echo $error; ?></div>
<?php endif; ?>
<form method="POST" action="">
<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
<!-- Main Form Section -->
<div class="md:col-span-2 space-y-8">
<!-- Clinic Information -->
<section class="bg-white dark:bg-slate-900/50 p-6 rounded-xl border border-primary/5 shadow-sm">
<div class="flex items-center gap-2 mb-6 border-b border-slate-100 dark:border-slate-800 pb-4">
<span class="material-symbols-outlined text-primary">domain</span>
<h2 class="text-slate-900 dark:text-white text-xl font-bold">Clinic Details</h2>
</div>
<div class="space-y-5">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Clinic Name</label>
<input name="clinic_name" class="form-input w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary focus:border-primary px-4 py-3" placeholder="Enter full clinic name" type="text" value="<?php echo htmlspecialchars($tenant['clinic_name'] ?? ''); ?>"/>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Clinic Email</label>
<input name="clinic_email" class="form-input w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary focus:border-primary px-4 py-3" placeholder="email@clinic.com" type="email" value="<?php echo htmlspecialchars($tenant['contact_email'] ?? $_SESSION['onboarding_email'] ?? ''); ?>"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Contact Number</label>
<input name="clinic_phone" class="form-input w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary focus:border-primary px-4 py-3" placeholder="+63 912 345 6789" type="tel" value="<?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?>"/>
</div>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Clinic Address</label>
<textarea name="clinic_address" class="form-input w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:ring-primary focus:border-primary px-4 py-3" placeholder="Street address, City, State, Zip Code" rows="2"><?php echo htmlspecialchars($tenant['clinic_address'] ?? ''); ?></textarea>
</div>
</div>
</section>
<!-- Owner account (read-only; update in Profile/Settings) -->
<section class="bg-white dark:bg-slate-900/50 p-6 rounded-xl border border-primary/5 shadow-sm">
<div class="flex items-center gap-2 mb-6 border-b border-slate-100 dark:border-slate-800 pb-4">
<span class="material-symbols-outlined text-primary">person</span>
<h2 class="text-slate-900 dark:text-white text-xl font-bold">Account Authority</h2>
</div>
<div class="space-y-3">
<p class="text-sm text-slate-500 dark:text-slate-400">Clinic owner account (from your registration). To change name, email, or phone, use <strong>Profile / Settings</strong> after logging in.</p>
<div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 p-4 space-y-2">
<div class="text-sm"><span class="font-semibold text-slate-600 dark:text-slate-400">Name:</span> <?php echo htmlspecialchars($tenant['owner_name'] ?? $_SESSION['onboarding_full_name'] ?? '—'); ?></div>
<div class="text-sm"><span class="font-semibold text-slate-600 dark:text-slate-400">Email:</span> <?php echo htmlspecialchars($tenant['owner_email'] ?? $_SESSION['onboarding_email'] ?? '—'); ?></div>
<div class="text-sm"><span class="font-semibold text-slate-600 dark:text-slate-400">Phone:</span> <?php echo htmlspecialchars($tenant['owner_phone'] ?? '—'); ?></div>
</div>
</div>
</section>
<!-- Payment Method Selection -->
<section class="bg-white dark:bg-slate-900/50 p-6 rounded-xl border border-primary/5 shadow-sm">
<div class="flex items-center gap-2 mb-6 border-b border-slate-100 dark:border-slate-800 pb-4">
<span class="material-symbols-outlined text-primary">payments</span>
<h2 class="text-slate-900 dark:text-white text-xl font-bold">Payment Method</h2>
</div>
<div id="payment-methods" class="grid grid-cols-1 md:grid-cols-2 gap-4">
<label for="pm_card" data-method="card" class="pm-option relative flex flex-col items-center justify-center p-4 border-2 rounded-xl cursor-pointer border-primary bg-primary/5">
<input id="pm_card" checked class="sr-only" name="payment_method" value="card" type="radio"/>
<span class="material-symbols-outlined pm-icon text-primary mb-2">credit_card</span>
<span class="pm-text text-sm font-bold text-slate-900 dark:text-white">Credit Card</span>
<div class="pm-check absolute top-2 right-2 size-4 rounded-full bg-primary flex items-center justify-center">
<span class="material-symbols-outlined text-[10px] text-white font-bold">check</span>
</div>
</label>

<label for="pm_gcash" data-method="gcash" class="pm-option relative flex flex-col items-center justify-center p-4 border-2 rounded-xl hover:border-primary/50 transition-all cursor-pointer border-slate-100 dark:border-slate-800">
<input id="pm_gcash" class="sr-only" name="payment_method" value="gcash" type="radio"/>
<span class="material-symbols-outlined pm-icon text-slate-400 mb-2">account_balance_wallet</span>
<span class="pm-text text-sm font-bold text-slate-600 dark:text-slate-300">GCash</span>
<div class="pm-check hidden absolute top-2 right-2 size-4 rounded-full bg-primary items-center justify-center">
<span class="material-symbols-outlined text-[10px] text-white font-bold">check</span>
</div>
</label>

<label for="pm_maya" data-method="maya" class="pm-option relative flex flex-col items-center justify-center p-4 border-2 rounded-xl hover:border-primary/50 transition-all cursor-pointer border-slate-100 dark:border-slate-800">
<input id="pm_maya" class="sr-only" name="payment_method" value="maya" type="radio"/>
<span class="material-symbols-outlined pm-icon text-slate-400 mb-2">smartphone</span>
<span class="pm-text text-sm font-bold text-slate-600 dark:text-slate-300">Maya</span>
<div class="pm-check hidden absolute top-2 right-2 size-4 rounded-full bg-primary items-center justify-center">
<span class="material-symbols-outlined text-[10px] text-white font-bold">check</span>
</div>
</label>

<label for="pm_bank" data-method="bank_transfer" class="pm-option relative flex flex-col items-center justify-center p-4 border-2 rounded-xl hover:border-primary/50 transition-all cursor-pointer border-slate-100 dark:border-slate-800">
<input id="pm_bank" class="sr-only" name="payment_method" value="bank_transfer" type="radio"/>
<span class="material-symbols-outlined pm-icon text-slate-400 mb-2">account_balance</span>
<span class="pm-text text-sm font-bold text-slate-600 dark:text-slate-300">Bank Transfer</span>
<div class="pm-check hidden absolute top-2 right-2 size-4 rounded-full bg-primary items-center justify-center">
<span class="material-symbols-outlined text-[10px] text-white font-bold">check</span>
</div>
</label>
</div>
</section>
</div>
<!-- Side Summary Section -->
<div class="space-y-6">
<div class="bg-white dark:bg-slate-900/50 p-6 rounded-xl border border-primary/10 shadow-lg sticky top-24">
<h3 class="text-slate-900 dark:text-white text-lg font-bold mb-4">Order Summary</h3>
<div class="flex flex-col gap-4 mb-6">
<label class="text-sm font-semibold text-slate-700 dark:text-slate-300">Chosen Plan</label>
<div class="relative">
<input type="text" class="form-select w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-slate-900 dark:text-white font-medium py-3 px-4" value="<?php echo htmlspecialchars($plan_name); ?>" readonly/>
</div>
</div>
<div class="space-y-3 py-4 border-t border-slate-100 dark:border-slate-800">
<div class="flex justify-between text-sm">
<span class="text-slate-500">Plan Subtotal</span>
<span class="font-medium">₱<?php echo number_format($plan_price, 2); ?>/mo</span>
</div>
<div class="flex justify-between text-sm">
<span class="text-slate-500">Setup Fee</span>
<span class="font-medium">₱0.00</span>
</div>
<div class="flex justify-between text-xl font-bold pt-4 border-t border-slate-100 dark:border-slate-800">
<span class="text-slate-900 dark:text-white">Total</span>
<span class="text-primary">₱<?php echo number_format($plan_price, 2); ?></span>
</div>
</div>
<button type="submit" class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 rounded-xl transition-all shadow-md shadow-primary/20 flex items-center justify-center gap-2 mt-6">
<span>Proceed to Payment</span>
<span class="material-symbols-outlined text-lg">arrow_forward</span>
</button>
<a href="ProviderPurchase.php?simulate=fail" class="block text-center text-xs text-slate-400 hover:text-red-500 mt-3">Simulate payment failure</a>
<p class="text-[10px] text-center text-slate-400 mt-4 leading-relaxed">
                                    By clicking 'Proceed to Payment', you agree to our Terms of Service and Privacy Policy. Your subscription will renew automatically.
                                </p>
</div>
<div class="bg-primary/5 p-4 rounded-xl border border-primary/20 flex gap-3">
<span class="material-symbols-outlined text-primary">verified_user</span>
<div class="flex flex-col gap-1">
<p class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Secure Transaction</p>
<p class="text-[11px] text-slate-500 dark:text-slate-400">256-bit SSL encrypted connection ensuring your data is safe and private.</p>
</div>
</div>
</div>
</form>
</div>
</div>
</main>
<footer class="mt-auto py-10 border-t border-primary/5 bg-slate-50 dark:bg-slate-900/80">
<div class="max-w-[800px] mx-auto px-4 text-center">
<p class="text-slate-400 text-sm">© 2024 MyDental.com. All rights reserved. Trusted by 5,000+ dental clinics worldwide.</p>
</div>
</footer>
</div>
</div>
<script>
(function () {
  var root = document.getElementById('payment-methods');
  if (!root) return;

  function applySelection() {
    var selected = root.querySelector('input[name="payment_method"]:checked');
    var selectedVal = selected ? selected.value : 'card';

    root.querySelectorAll('.pm-option').forEach(function (label) {
      var method = label.getAttribute('data-method');
      var isActive = method === selectedVal;

      label.classList.toggle('border-primary', isActive);
      label.classList.toggle('bg-primary/5', isActive);
      label.classList.toggle('border-slate-100', !isActive);
      label.classList.toggle('dark:border-slate-800', !isActive);

      var icon = label.querySelector('.pm-icon');
      if (icon) {
        icon.classList.toggle('text-primary', isActive);
        icon.classList.toggle('text-slate-400', !isActive);
      }

      var text = label.querySelector('.pm-text');
      if (text) {
        text.classList.toggle('text-slate-900', isActive);
        text.classList.toggle('dark:text-white', isActive);
        text.classList.toggle('text-slate-600', !isActive);
        text.classList.toggle('dark:text-slate-300', !isActive);
      }

      var check = label.querySelector('.pm-check');
      if (check) {
        check.classList.toggle('hidden', !isActive);
        check.classList.toggle('flex', isActive);
      }
    });
  }

  root.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'payment_method') applySelection();
  });

  applySelection();
})();
</script>
</body></html>