<?php
session_start();
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug_mode) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

set_exception_handler(function (Throwable $e) use ($debug_mode): void {
    error_log('[ProviderPurchase][UnhandledException] ' . $e->getMessage());
    if ($debug_mode) {
        http_response_code(500);
        echo '<pre style="white-space:pre-wrap;font-family:monospace;padding:12px;background:#fff3f3;border:1px solid #f5c2c7;">';
        echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        echo "\n\n" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    } elseif (!headers_sent()) {
        $_SESSION['provider_setup_link_error'] = 'Could not open the purchase page right now. Please try again.';
        header('Location: ProviderApprovalStatus.php');
    }
    exit;
});

register_shutdown_function(function () use ($debug_mode): void {
    $fatal = error_get_last();
    if (!is_array($fatal)) {
        return;
    }
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($fatal['type'] ?? 0), $fatal_types, true)) {
        return;
    }
    $message = (string) ($fatal['message'] ?? 'Unknown fatal error');
    error_log('[ProviderPurchase][FatalShutdown] ' . $message);
    if ($debug_mode) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<pre style="white-space:pre-wrap;font-family:monospace;padding:12px;background:#fff3f3;border:1px solid #f5c2c7;">';
        echo 'Fatal error: ' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    } elseif (!headers_sent()) {
        $_SESSION['provider_setup_link_error'] = 'Purchase page encountered a server error. Please try again.';
        header('Location: ProviderApprovalStatus.php');
    }
});

require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
provider_require_approved_for_provider_portal();
require_once __DIR__ . '/db.php';

$tenant_id = null;
$user_id = null;
$plan_slug = null;
$allowed = ['starter', 'professional', 'enterprise'];
$requested_plan_slug = isset($_GET['plan']) ? strtolower(trim((string) $_GET['plan'])) : 'professional';
if (!in_array($requested_plan_slug, $allowed, true)) {
    $requested_plan_slug = 'professional';
}
$force_from_clinic_setup_once = !empty($_SESSION['force_purchase_from_clinic_setup_once']);
// Consumed after we decide identity; used to skip VerifyBusiness on first load after clinic setup / approval redirect.
$skip_business_verification_gate = $force_from_clinic_setup_once;

if (!empty($_SESSION['onboarding_user_id']) && !empty($_SESSION['onboarding_tenant_id'])) {
    $tenant_id = $_SESSION['onboarding_tenant_id'];
    $user_id = $_SESSION['onboarding_user_id'];
    $plan_slug = $_SESSION['onboarding_plan'] ?? 'professional';
    if ($force_from_clinic_setup_once) {
        unset($_SESSION['force_purchase_from_clinic_setup_once']);
    }
} elseif (provider_has_authenticated_provider_session()) {
    [$tid, $uid] = provider_get_authenticated_provider_identity_from_session();
    // Keep users on purchase page when they explicitly navigate here.
    // (Do not auto-forward to dashboard from this entry point.)
    if (!empty($_SESSION['allow_purchase_page_once_after_payment'])) {
        unset($_SESSION['allow_purchase_page_once_after_payment']);
    }
    if ($force_from_clinic_setup_once) {
        unset($_SESSION['force_purchase_from_clinic_setup_once']);
    }
    $tenant_id = $tid;
    $user_id = $uid;
    $plan_slug = $requested_plan_slug;
    $_SESSION['onboarding_plan'] = $plan_slug;
}

if ($tenant_id === null || $user_id === null) {
    $redirect = 'ProviderPurchase.php?plan=' . urlencode($requested_plan_slug);
    header('Location: ProviderLogin.php?redirect=' . urlencode($redirect));
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
if (!$business_verification && !$skip_business_verification_gate) {
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
$tenant = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.clinic_name, t.contact_email, t.contact_phone, t.clinic_address,
               u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
        FROM tbl_tenants t
        LEFT JOIN tbl_users u ON t.owner_user_id = u.user_id
        WHERE t.tenant_id = ?
    ");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $tenant = [];
}

$plan = null;
try {
    $stmt = $pdo->prepare("SELECT plan_id, plan_name, price FROM tbl_subscription_plans WHERE plan_slug = ? LIMIT 1");
    $stmt->execute([$plan_slug]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $plan = null;
}
if (!$plan) {
    try {
        $stmt = $pdo->query("SELECT plan_id, plan_name, price FROM tbl_subscription_plans ORDER BY plan_id ASC LIMIT 1");
        $plan = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    } catch (Throwable $e) {
        $plan = null;
    }
}
if (!$plan) {
    $plan = ['plan_id' => 1, 'plan_name' => 'Professional', 'price' => 2499];
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
            if (!function_exists('curl_init')) {
                $error = 'Card/GCash/Maya checkout is temporarily unavailable on this server (cURL extension missing). Please choose Bank Transfer or contact support.';
            } else {
                try {
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
                } catch (Throwable $e) {
                    $error = 'Payment provider is currently unavailable. Please choose Bank Transfer or try again later.';
                }
            }
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
            $stmt = $pdo->prepare("SELECT user_id, tenant_id, username, email, full_name, role, status FROM tbl_users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $_SESSION['name'] = $user['full_name'] ?: $user['username'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status'] = $user['status'];
                $stmt2 = $pdo->prepare("SELECT owner_user_id FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
                $stmt2->execute([$user['tenant_id']]);
                $t = $stmt2->fetch(PDO::FETCH_ASSOC);
                $_SESSION['is_owner'] = ($t && isset($t['owner_user_id']) && $t['owner_user_id'] === $user['user_id']);
            }
            $_SESSION['allow_purchase_page_once_after_payment'] = true;
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

<html class="light scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Purchase Your Plan | MyDental.com</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "primary": "#2b8beb",
              "on-surface": "#131c25",
              "surface": "#ffffff",
              "surface-variant": "#f7f9ff",
              "on-surface-variant": "#404752",
              "outline-variant": "#c0c7d4",
              "primary-fixed": "#d4e3ff",
              "on-primary-fixed-variant": "#004883",
              "surface-container-low": "#edf4ff",
              "inverse-surface": "#131c25",
              "error": "#ba1a1a",
              "surface-container-lowest": "#ffffff",
              "surface-container": "#e6effc",
              "surface-bright": "#ffffff",
              "background-light": "#f6f7f8",
              "background-dark": "#101922",
            },
            fontFamily: {
              "headline": ["Manrope", "sans-serif"],
              "body": ["Inter", "sans-serif"],
              "editorial": ["Playfair Display", "serif"],
              "label": ["Inter", "sans-serif"],
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "3xl": "2.5rem", "full": "9999px"},
          },
        },
      }
    </script>
<style>
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      }
      .glass-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.3);
      }
      .primary-gradient {
        background: linear-gradient(135deg, #2b8beb 0%, #1a73e8 100%);
      }
      .editorial-word {
        text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
        letter-spacing: -0.02em;
      }
      .purchase-input:focus {
        border-color: rgba(43, 139, 235, 0.45);
        box-shadow: 0 0 0 3px rgba(43, 139, 235, 0.15);
        outline: none;
      }
      .pm-fill-icon {
        font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      }
    </style>
</head>
<body class="bg-surface font-body text-on-surface min-h-screen">
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
<?php include 'ProviderNavbar.php'; ?>
<main class="pt-8 md:pt-16 pb-16 md:pb-20 px-4 sm:px-8 max-w-screen-2xl mx-auto w-full flex-1">
<div class="mb-12 md:mb-16">
<h1 class="font-headline text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold tracking-tighter text-on-surface mb-4 md:mb-6 leading-[1.1]">
        Purchase Your <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Plan</span>
</h1>
<p class="font-body text-on-surface-variant text-lg md:text-xl max-w-2xl font-medium">Complete your subscription setup to activate your professional dental clinic tools and provider portal access.</p>
</div>
<?php if ($error): ?>
<div class="mb-8 p-4 md:p-5 rounded-2xl border border-error/20 bg-red-50 text-error text-sm font-medium"><?php echo $error; ?></div>
<?php endif; ?>
<form method="POST" action="">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-start">
<!-- Left Column: Clinic, account, payment -->
<div class="lg:col-span-7 space-y-10 md:space-y-12">
<section class="bg-surface-container-low rounded-3xl p-6 sm:p-10 border border-on-surface/5">
<div class="flex items-center gap-4 mb-8">
<div class="bg-primary text-white p-3 rounded-2xl shadow-lg shadow-primary/20">
<span class="material-symbols-outlined">account_balance</span>
</div>
<div>
<h2 class="font-headline text-2xl sm:text-3xl font-extrabold tracking-tight">Clinic Details</h2>
<p class="text-sm font-medium text-on-surface-variant">Identification for billing and licensing</p>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
<div class="space-y-2 md:col-span-2">
<label class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant px-1">Clinic Name</label>
<input name="clinic_name" class="purchase-input w-full bg-white focus:ring-2 focus:ring-primary/20 rounded-2xl px-6 py-4 shadow-sm transition-all placeholder:text-outline-variant/60 font-medium border border-on-surface/10" placeholder="e.g. Precision Dental" type="text" value="<?php echo htmlspecialchars($tenant['clinic_name'] ?? ''); ?>"/>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant px-1">Work Email</label>
<input name="clinic_email" class="purchase-input w-full bg-white focus:ring-2 focus:ring-primary/20 rounded-2xl px-6 py-4 shadow-sm transition-all placeholder:text-outline-variant/60 font-medium border border-on-surface/10" placeholder="admin@clinic.com" type="email" value="<?php echo htmlspecialchars($tenant['contact_email'] ?? $_SESSION['onboarding_email'] ?? ''); ?>"/>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant px-1">Contact Number</label>
<input name="clinic_phone" class="purchase-input w-full bg-white focus:ring-2 focus:ring-primary/20 rounded-2xl px-6 py-4 shadow-sm transition-all placeholder:text-outline-variant/60 font-medium border border-on-surface/10" placeholder="+63 912 345 6789" type="tel" value="<?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?>"/>
</div>
<div class="space-y-2 md:col-span-2">
<label class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant px-1">Business Address</label>
<textarea name="clinic_address" class="purchase-input w-full bg-white focus:ring-2 focus:ring-primary/20 rounded-2xl px-6 py-4 shadow-sm transition-all placeholder:text-outline-variant/60 font-medium border border-on-surface/10 min-h-[100px] resize-y" placeholder="Street, City, ZIP" rows="3"><?php echo htmlspecialchars($tenant['clinic_address'] ?? ''); ?></textarea>
</div>
</div>
</section>

<section class="bg-surface-container-low rounded-3xl p-6 sm:p-10 border border-on-surface/5">
<div class="flex items-center gap-4 mb-8">
<div class="bg-primary text-white p-3 rounded-2xl shadow-lg shadow-primary/20">
<span class="material-symbols-outlined">person</span>
</div>
<div>
<h2 class="font-headline text-2xl sm:text-3xl font-extrabold tracking-tight">Account Authority</h2>
<p class="text-sm font-medium text-on-surface-variant">Clinic owner from registration — update in Profile after login</p>
</div>
</div>
<div class="rounded-2xl bg-white/80 border border-on-surface/10 p-5 sm:p-6 space-y-3 shadow-sm">
<p class="text-sm text-on-surface-variant">To change name, email, or phone for the owner account, use <strong class="text-on-surface">Profile / Settings</strong> after logging in.</p>
<div class="pt-2 space-y-2 border-t border-on-surface/10">
<div class="text-sm"><span class="font-semibold text-on-surface-variant">Name:</span> <?php echo htmlspecialchars($tenant['owner_name'] ?? $_SESSION['onboarding_full_name'] ?? '—'); ?></div>
<div class="text-sm"><span class="font-semibold text-on-surface-variant">Email:</span> <?php echo htmlspecialchars($tenant['owner_email'] ?? $_SESSION['onboarding_email'] ?? '—'); ?></div>
<div class="text-sm"><span class="font-semibold text-on-surface-variant">Phone:</span> <?php echo htmlspecialchars($tenant['owner_phone'] ?? '—'); ?></div>
</div>
</div>
</section>

<section class="bg-surface-container-low rounded-3xl p-6 sm:p-10 border border-on-surface/5">
<div class="flex items-center gap-4 mb-8">
<div class="bg-primary text-white p-3 rounded-2xl shadow-lg shadow-primary/20">
<span class="material-symbols-outlined">payments</span>
</div>
<div>
<h2 class="font-headline text-2xl sm:text-3xl font-extrabold tracking-tight">Payment Method</h2>
<p class="text-sm font-medium text-on-surface-variant">Choose your preferred gateway</p>
</div>
</div>
<div id="payment-methods" class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
<label for="pm_gcash" data-method="gcash" class="pm-option group flex flex-col items-center justify-center p-4 sm:p-6 bg-white border rounded-2xl transition-all duration-300 cursor-pointer border-on-surface/5 hover:border-primary/30">
<input id="pm_gcash" class="sr-only" name="payment_method" value="gcash" type="radio"/>
<span class="material-symbols-outlined pm-icon text-primary text-2xl sm:text-3xl mb-2 sm:mb-3">account_balance_wallet</span>
<span class="pm-text font-bold text-[10px] sm:text-xs uppercase tracking-widest text-on-surface-variant group-hover:text-primary transition-colors">GCash</span>
</label>

<label for="pm_bank" data-method="bank_transfer" class="pm-option group flex flex-col items-center justify-center p-4 sm:p-6 bg-white border rounded-2xl transition-all duration-300 cursor-pointer border-on-surface/5 hover:border-primary/30">
<input id="pm_bank" class="sr-only" name="payment_method" value="bank_transfer" type="radio"/>
<span class="material-symbols-outlined pm-icon text-primary text-2xl sm:text-3xl mb-2 sm:mb-3">account_balance</span>
<span class="pm-text font-bold text-[10px] sm:text-xs uppercase tracking-widest text-on-surface-variant group-hover:text-primary transition-colors">Bank</span>
</label>

<label for="pm_card" data-method="card" class="pm-option group flex flex-col items-center justify-center p-4 sm:p-6 bg-white border-2 rounded-2xl transition-all duration-300 cursor-pointer border-primary shadow-xl shadow-primary/5">
<input id="pm_card" checked class="sr-only" name="payment_method" value="card" type="radio"/>
<span class="material-symbols-outlined pm-icon pm-icon-card text-primary text-2xl sm:text-3xl mb-2 sm:mb-3">credit_card</span>
<span class="pm-text font-bold text-[10px] sm:text-xs uppercase tracking-widest text-primary">Credit Card</span>
</label>

<label for="pm_maya" data-method="maya" class="pm-option group flex flex-col items-center justify-center p-4 sm:p-6 bg-white border rounded-2xl transition-all duration-300 cursor-pointer border-on-surface/5 hover:border-primary/30">
<input id="pm_maya" class="sr-only" name="payment_method" value="maya" type="radio"/>
<span class="material-symbols-outlined pm-icon text-primary text-2xl sm:text-3xl mb-2 sm:mb-3">phone_iphone</span>
<span class="pm-text font-bold text-[10px] sm:text-xs uppercase tracking-widest text-on-surface-variant group-hover:text-primary transition-colors">Maya</span>
</label>
</div>
</section>
</div>

<!-- Right Column: Order summary -->
<aside class="lg:col-span-5 lg:sticky lg:top-28 self-start w-full">
<div class="bg-white rounded-[2rem] sm:rounded-[2.5rem] p-8 sm:p-12 shadow-[0_40px_100px_-20px_rgba(43,139,235,0.08)] relative overflow-hidden border border-on-surface/5">
<div class="absolute top-0 right-0 w-40 h-40 bg-primary/5 rounded-bl-full pointer-events-none" aria-hidden="true"></div>
<h3 class="font-headline text-2xl sm:text-3xl font-extrabold mb-8 sm:mb-10 tracking-tight relative">Order Summary</h3>
<div class="space-y-8 relative">
<div class="flex justify-between items-start gap-4 pb-8 border-b border-on-surface/5">
<div class="min-w-0">
<p class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant mb-2">Selected Plan</p>
<h4 class="font-headline text-xl sm:text-2xl font-extrabold text-primary break-words"><?php echo htmlspecialchars($plan_name); ?></h4>
<p class="text-sm font-medium text-on-surface-variant flex items-center gap-2 mt-2">
<span class="material-symbols-outlined text-[18px] shrink-0">event_repeat</span>
                                    Billing monthly
                                </p>
</div>
<p class="font-headline text-xl sm:text-2xl font-extrabold shrink-0">₱<?php echo number_format($plan_price, 2); ?></p>
</div>
<div class="space-y-4 pt-2">
<div class="flex justify-between text-on-surface-variant font-medium text-sm">
<span>Plan Subtotal</span>
<span>₱<?php echo number_format($plan_price, 2); ?>/mo</span>
</div>
<div class="flex justify-between text-on-surface-variant font-medium text-sm">
<span>Setup Fee</span>
<span>₱0.00</span>
</div>
</div>
<div class="pt-8 mt-6 border-t-2 border-primary/5 flex flex-col sm:flex-row sm:justify-between sm:items-end gap-4">
<div>
<p class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant mb-1">Total Amount Due</p>
<p class="text-xs font-bold text-on-surface-variant">PHP</p>
</div>
<p class="font-headline text-4xl sm:text-5xl font-extrabold text-on-surface tracking-tighter">₱<?php echo number_format($plan_price, 2); ?></p>
</div>
<button type="submit" class="w-full bg-primary py-5 sm:py-6 rounded-2xl text-white font-black text-xs sm:text-sm uppercase tracking-[0.15em] sm:tracking-[0.2em] shadow-xl shadow-primary/30 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-3 mt-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                            Confirm Purchase
                            <span class="material-symbols-outlined" aria-hidden="true">arrow_right_alt</span>
</button>
<a href="ProviderPurchase.php?simulate=fail" class="block text-center text-xs text-on-surface-variant/70 hover:text-error transition-colors mt-4">Simulate payment failure</a>
<p class="text-[10px] text-center text-on-surface-variant/80 mt-4 leading-relaxed">
                                    By confirming, you agree to our Terms of Service and Privacy Policy. Your subscription will renew automatically.
                                </p>
<div class="flex flex-col items-center gap-4 pt-8">
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant/40">
<span class="material-symbols-outlined text-base">lock</span>
                                Secure Payment Gateway
                            </div>
<div class="flex flex-wrap justify-center gap-6 sm:gap-8 opacity-30 grayscale hover:grayscale-0 transition-all">
<img alt="Visa" class="h-6" width="48" height="20" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAkec4WzW0InMbUUUzJz178nkUflSZ8w8sUzCwVAjPYZTWor18lJR2nHj2TNNVYcbYv0gDcpp9PmJMQp6Opixca2Jr4ec6KF3uwutpi_4jDPfYUhJOrCk72h7GJVHfqOQUiXx5Ziu64rrhAS3Knpn4kodfHpxvT5PAVvEwwuMerz82w_qSylaBTo-Cv2sO7sGj9-milkAxEQ_csHCj9d3sxtAYzjFT2j2pU0gX2r1LdGR5NU6ibgb7F0HA5PUL6qxocZWkn0T9JLrc"/>
<img alt="Mastercard" class="h-6" width="48" height="20" src="https://lh3.googleusercontent.com/aida-public/AB6AXuArA5CUnyFGFtbA6iRnMWTXz0kXKTuh0IGkQppzrprvI4vW9bMPi61RVqsHFK0v-W_xdqFCOjO-h3UgdnR9-I3BBirXZZtJKJ7_xWTmsxdrTpAy_RZyZAFDpj5YsrRG0yLBfZPhJW_D9GlCaA90oQ-oIWbZIh-lYYsHwIpftRBpeGuEZVbrnV17deXiAOXh_cuzRgvd8lhjUCVCNNsOtg6zDgC2W9xCAB6VOKTEesAcNcIpBLcuYyMXkzSc1IHiwNwDtZKaiqdfNQg"/>
<img alt="American Express" class="h-6" width="48" height="20" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAUv5Z5m29X0ZyZAg547MLJLpV0FXMrrDaTOReu6II7YQ60FJQxkTKthL5QqB4-gpKRJ8SDvGeY7mwHyuZ4qIaOfCWEK7gSc8vdX4tq73vetlNdDPpQko1TFkQ3OqCZthNs68etwbqWerrZe1AJBl4LRWeXrJjx80IL2mxQC4FDDH_cX97tJ4uzN2N71R_8CuqeVYz6n2UtzjbayRvktDN-icL-Gx9-LrslTyMJj1XDZvswQs9NxxKptxj66eZ97OnoeUJVIhyQtW8"/>
</div>
</div>
</div>
</div>

<div class="mt-10 sm:mt-12 grid grid-cols-1 sm:grid-cols-2 gap-6 sm:gap-8 px-0 sm:px-2">
<div class="flex items-start gap-4">
<span class="material-symbols-outlined text-primary text-2xl shrink-0 pm-fill-icon">verified_user</span>
<div>
<p class="text-xs font-black uppercase tracking-[0.1em] text-on-surface">Secure &amp; compliant</p>
<p class="text-[11px] font-medium text-on-surface-variant">Encrypted checkout and clinic data handling</p>
</div>
</div>
<div class="flex items-start gap-4">
<span class="material-symbols-outlined text-primary text-2xl shrink-0 pm-fill-icon">support_agent</span>
<div>
<p class="text-xs font-black uppercase tracking-[0.1em] text-on-surface">Provider support</p>
<p class="text-[11px] font-medium text-on-surface-variant">Help with onboarding and billing questions</p>
</div>
</div>
</div>
</aside>
</div>
</form>
</main>
<footer class="w-full border-t border-slate-200 bg-slate-50 mt-auto">
<div class="flex flex-col md:flex-row justify-between items-center py-10 sm:py-12 px-4 sm:px-8 max-w-screen-2xl mx-auto gap-4">
<div class="text-lg font-bold text-slate-900 font-headline">MyDental.com</div>
<div class="flex flex-wrap justify-center gap-6 sm:gap-8 text-xs font-body text-slate-500">
<a class="hover:text-primary transition-all" href="#">Privacy Policy</a>
<a class="hover:text-primary transition-all" href="#">Terms of Service</a>
<a class="hover:text-primary transition-all" href="ProviderContact.php">Contact</a>
</div>
<div class="text-xs text-slate-500 font-body opacity-80 text-center md:text-right">
            © <?php echo date('Y'); ?> MyDental.com. All rights reserved.
        </div>
</div>
</footer>
</div>
<script>
(function () {
  var root = document.getElementById('payment-methods');
  if (!root) return;

  var activeBorder = ['border-2', 'border-primary', 'shadow-xl', 'shadow-primary/5'];
  var inactiveBorder = ['border', 'border-on-surface/5', 'hover:border-primary/30'];

  function setPmIconFill(icon, filled) {
    if (!icon || !icon.classList.contains('pm-icon-card')) return;
    icon.style.fontVariationSettings = filled ? "'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24" : "'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24";
  }

  function applySelection() {
    var selected = root.querySelector('input[name="payment_method"]:checked');
    var selectedVal = selected ? selected.value : 'card';

    root.querySelectorAll('.pm-option').forEach(function (label) {
      var method = label.getAttribute('data-method');
      var isActive = method === selectedVal;

      activeBorder.forEach(function (c) { label.classList.toggle(c, isActive); });
      inactiveBorder.forEach(function (c) { label.classList.toggle(c, !isActive); });

      var icon = label.querySelector('.pm-icon');
      if (icon) {
        icon.classList.toggle('text-primary', true);
      }

      var text = label.querySelector('.pm-text');
      if (text) {
        text.classList.toggle('text-primary', isActive);
        text.classList.toggle('text-on-surface-variant', !isActive);
      }

      setPmIconFill(label.querySelector('.pm-icon-card'), isActive && method === 'card');
    });
  }

  root.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'payment_method') applySelection();
  });

  applySelection();
})();
</script>
</body></html>