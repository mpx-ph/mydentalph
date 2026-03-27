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
require_once __DIR__ . '/superadmin/superadmin_settings_lib.php';

$tenant_id = null;
$user_id = null;
$plan_slug = null;
$allowed = ['starter', 'professional', 'enterprise'];
$plan_label_map = [
    'starter' => 'Starter',
    'professional' => 'Professional',
    'enterprise' => 'Enterprise',
];
$plan_price_fallback_map = [
    'starter' => 999,
    'professional' => 2499,
    'enterprise' => 4999,
];
try {
    $settings = superadmin_get_settings($pdo);
    $provider_plans_settings = isset($settings['provider_plans']) && is_array($settings['provider_plans'])
        ? $settings['provider_plans']
        : [];
} catch (Throwable $e) {
    $provider_plans_settings = [];
}
$normalize_plan_price = static function ($raw_value): ?float {
    if (is_numeric($raw_value)) {
        $numeric = (float) $raw_value;
        return $numeric > 0 ? $numeric : null;
    }
    if (!is_string($raw_value) || trim($raw_value) === '') {
        return null;
    }
    $cleaned = preg_replace('/[^0-9.\-]/', '', $raw_value);
    if (!is_string($cleaned) || $cleaned === '' || !is_numeric($cleaned)) {
        return null;
    }
    $numeric = (float) $cleaned;
    return $numeric > 0 ? $numeric : null;
};
foreach ($allowed as $allowed_slug) {
    $setting_plan = isset($provider_plans_settings[$allowed_slug]) && is_array($provider_plans_settings[$allowed_slug])
        ? $provider_plans_settings[$allowed_slug]
        : [];
    $settings_label = trim((string) ($setting_plan['name'] ?? ''));
    if ($settings_label !== '') {
        $plan_label_map[$allowed_slug] = $settings_label;
    }
    $settings_price = $normalize_plan_price($setting_plan['price'] ?? null);
    if ($settings_price !== null) {
        $plan_price_fallback_map[$allowed_slug] = $settings_price;
    }
}
$has_explicit_plan_request = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_plan_slug']))
    || isset($_GET['plan']);
$requested_plan_source = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['selected_plan_slug'] ?? ($_GET['plan'] ?? 'professional'))
    : ($_GET['plan'] ?? 'professional');
$requested_plan_slug = strtolower(trim((string) $requested_plan_source));
if (!in_array($requested_plan_slug, $allowed, true)) {
    $requested_plan_slug = 'professional';
}
$force_from_clinic_setup_once = !empty($_SESSION['force_purchase_from_clinic_setup_once']);
// Consumed after we decide identity; used to skip VerifyBusiness on first load after clinic setup / approval redirect.
$skip_business_verification_gate = $force_from_clinic_setup_once;

if (!provider_has_authenticated_provider_session()) {
    $redirect = 'ProviderPurchase.php?plan=' . urlencode($requested_plan_slug);
    header('Location: ProviderLogin.php?redirect=' . urlencode($redirect));
    exit;
}

[$tenant_id, $user_id] = provider_get_authenticated_provider_identity_from_session();

if (!empty($_SESSION['onboarding_user_id']) && !empty($_SESSION['onboarding_tenant_id'])) {
    $onboarding_tenant_id = (string) $_SESSION['onboarding_tenant_id'];
    $onboarding_user_id = (string) $_SESSION['onboarding_user_id'];
    if ($onboarding_tenant_id !== (string) $tenant_id || $onboarding_user_id !== (string) $user_id) {
        unset(
            $_SESSION['onboarding_tenant_id'],
            $_SESSION['onboarding_user_id'],
            $_SESSION['onboarding_plan'],
            $_SESSION['onboarding_email']
        );
    }
}

// Keep users on purchase page when they explicitly navigate here.
// (Do not auto-forward to dashboard from this entry point.)
if (!empty($_SESSION['allow_purchase_page_once_after_payment'])) {
    unset($_SESSION['allow_purchase_page_once_after_payment']);
}
if ($force_from_clinic_setup_once) {
    unset($_SESSION['force_purchase_from_clinic_setup_once']);
}
$plan_slug = $requested_plan_slug;
$_SESSION['onboarding_plan'] = $plan_slug;

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
               u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone,
               au.email AS account_email
        FROM tbl_tenants t
        LEFT JOIN tbl_users u ON t.owner_user_id = u.user_id
        LEFT JOIN tbl_users au ON au.user_id = ? AND au.tenant_id = t.tenant_id AND au.status = 'active'
        WHERE t.tenant_id = ?
    ");
    $stmt->execute([$user_id, $tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $tenant = [];
}

$prefill_clinic_name = (string) ($tenant['clinic_name'] ?? '');
$prefill_email = (string) ($tenant['account_email'] ?? $tenant['owner_email'] ?? $tenant['contact_email'] ?? $_SESSION['email'] ?? '');
if ($prefill_clinic_name === '') {
    $prefill_clinic_name = (string) ($_SESSION['onboarding_clinic_name'] ?? '');
}

$available_plans = [];
foreach ($allowed as $allowed_slug) {
    $available_plans[$allowed_slug] = [
        'plan_id' => null,
        'plan_name' => $plan_label_map[$allowed_slug] ?? ucfirst($allowed_slug),
        'plan_price' => (float) ($plan_price_fallback_map[$allowed_slug] ?? 0),
        'plan_slug' => $allowed_slug,
    ];
}
$resolve_allowed_slug = static function (array $row): string {
    $raw_slug = strtolower(trim((string) ($row['plan_slug'] ?? '')));
    $raw_name = strtolower(trim((string) ($row['plan_name'] ?? '')));
    $combined = trim($raw_slug . ' ' . $raw_name);
    if ($combined === '') {
        return '';
    }
    if (strpos($combined, 'starter') !== false) {
        return 'starter';
    }
    if (strpos($combined, 'professional') !== false || strpos($combined, 'pro') !== false) {
        return 'professional';
    }
    if (strpos($combined, 'enterprise') !== false || strpos($combined, 'premium') !== false) {
        return 'enterprise';
    }
    return '';
};
try {
    $stmt = $pdo->query("SELECT plan_id, plan_name, price, plan_slug FROM tbl_subscription_plans ORDER BY plan_id ASC");
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($rows as $row) {
        $row_slug = $resolve_allowed_slug($row);
        if (!in_array($row_slug, $allowed, true)) {
            continue;
        }
        $row_price = $normalize_plan_price($row['price'] ?? null);
        $available_plans[$row_slug] = [
            'plan_id' => isset($row['plan_id']) ? (int) $row['plan_id'] : null,
            'plan_name' => (string) ($plan_label_map[$row_slug] ?? ($row['plan_name'] ?? ucfirst($row_slug))),
            'plan_price' => (float) ($row_price ?? ($plan_price_fallback_map[$row_slug] ?? 0)),
            'plan_slug' => $row_slug,
        ];
    }
} catch (Throwable $e) {
    // Keep fallback plan defaults when plans table cannot be read.
}
$selected_plan_data = $available_plans[$plan_slug] ?? null;
if (!$selected_plan_data) {
    $plan_slug = 'professional';
    $selected_plan_data = $available_plans[$plan_slug] ?? [
        'plan_id' => 1,
        'plan_name' => 'Professional',
        'plan_price' => 2499.0,
        'plan_slug' => 'professional',
    ];
}
$plan_name = (string) ($selected_plan_data['plan_name'] ?? ($plan_label_map[$plan_slug] ?? 'Professional'));
$plan_price = (float) ($selected_plan_data['plan_price'] ?? ($plan_price_fallback_map[$plan_slug] ?? 2499));
$plan_id = (int) ($selected_plan_data['plan_id'] ?? 1);
$_SESSION['onboarding_plan'] = $plan_slug;
$available_plans_json = json_encode($available_plans, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if (!is_string($available_plans_json) || $available_plans_json === '') {
    $available_plans_json = '{}';
}

$error = '';
$selected_payment_method = (string) ($_SESSION['provider_purchase_last_method'] ?? 'card');
unset($_SESSION['provider_purchase_last_method']);
if (!in_array($selected_payment_method, ['card', 'gcash', 'paymaya'], true)) {
    $selected_payment_method = 'card';
}
$flash_error = trim((string) ($_SESSION['provider_purchase_error'] ?? ''));
unset($_SESSION['provider_purchase_error']);
if ($flash_error !== '') {
    $error = $flash_error;
}
if (isset($_GET['payment']) && $_GET['payment'] === 'failed' && $error === '') {
    $error_reason = trim((string) ($_GET['reason'] ?? ''));
    $error = $error_reason !== '' ? $error_reason : 'Payment did not complete. Please try again.';
}

$form_action_qs = [];
if (!empty($plan_slug)) {
    $form_action_qs['plan'] = $plan_slug;
}
if ($debug_mode) {
    $form_action_qs['debug'] = '1';
}
$form_token = $_SESSION['provider_purchase_form_token'] ?? '';
if (!is_string($form_token) || $form_token === '') {
    $form_token = bin2hex(random_bytes(16));
    $_SESSION['provider_purchase_form_token'] = $form_token;
}
$form_action_href = 'ProviderPurchaseCheckout.php' . ($form_action_qs !== [] ? '?' . http_build_query($form_action_qs) : '');

$back_href = 'ProviderClinicSetup.php';
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
      .plan-modal-backdrop {
        opacity: 0;
        pointer-events: none;
        transition: opacity 220ms ease;
      }
      .plan-modal-backdrop.is-open {
        opacity: 1;
        pointer-events: auto;
      }
      .plan-modal-panel {
        opacity: 0;
        transform: translateY(14px) scale(0.98);
        transition: opacity 240ms ease, transform 240ms ease;
      }
      .plan-modal-backdrop.is-open .plan-modal-panel {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    </style>
</head>
<body class="bg-surface font-body text-on-surface text-[15px] leading-normal min-h-screen antialiased">
<div class="relative flex min-h-screen w-full flex-col overflow-x-hidden">
<?php include 'ProviderNavbar.php'; ?>
<main class="pt-4 pb-10 px-4 sm:px-6 max-w-5xl mx-auto w-full flex-1">
<a href="<?php echo htmlspecialchars($back_href, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-on-surface-variant hover:text-primary transition-colors mb-5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 rounded-lg -ml-1 px-1 py-1">
<span class="material-symbols-outlined text-xl leading-none" aria-hidden="true">arrow_back</span>
        Back to Clinic Setup
    </a>
<div class="mb-8">
<h1 class="font-headline text-3xl sm:text-4xl font-extrabold tracking-tight text-on-surface mb-3 leading-tight">
        Purchase Your <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block text-[1.12em]">Plan</span>
</h1>
<p class="font-body text-on-surface-variant text-[15px] sm:text-base max-w-xl font-medium">Complete your subscription setup to activate your selected clinic plan and provider portal access.</p>
</div>
<?php if ($error): ?>
<div class="mb-6 p-3.5 rounded-xl border border-error/20 bg-red-50 text-error text-sm font-medium"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form id="provider-purchase-form" method="post" action="<?php echo htmlspecialchars($form_action_href, ENT_QUOTES, 'UTF-8'); ?>" class="space-y-0">
<input id="selected_plan_slug" name="selected_plan_slug" type="hidden" value="<?php echo htmlspecialchars($plan_slug, ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="purchase_form_token" type="hidden" value="<?php echo htmlspecialchars($form_token, ENT_QUOTES, 'UTF-8'); ?>"/>
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 items-start">
<!-- Left Column: Clinic & payment -->
<div class="lg:col-span-7 space-y-6">
<section class="bg-surface-container-low rounded-2xl p-5 sm:p-6 border border-on-surface/5">
<div class="flex items-center gap-3 mb-5">
<div class="bg-primary text-white p-2 rounded-xl shadow-md shadow-primary/20">
<span class="material-symbols-outlined text-[22px]">account_balance</span>
</div>
<div>
<h2 class="font-headline text-lg sm:text-xl font-extrabold tracking-tight">Clinic Details</h2>
<p class="text-xs font-medium text-on-surface-variant">Identification for billing and licensing</p>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
<div class="space-y-1.5 md:col-span-2">
<label class="text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant px-0.5">Clinic Name</label>
<input name="clinic_name" autocomplete="organization" class="purchase-input w-full bg-white focus:ring-2 focus:ring-primary/20 rounded-xl px-4 py-2.5 text-sm shadow-sm transition-all placeholder:text-outline-variant/60 font-medium border border-on-surface/10" placeholder="e.g. Precision Dental" type="text" value="<?php echo htmlspecialchars($prefill_clinic_name); ?>"/>
</div>
<div class="space-y-1.5">
<label class="text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant px-0.5">Work Email</label>
<input name="clinic_email" autocomplete="email" class="purchase-input w-full bg-white focus:ring-2 focus:ring-primary/20 rounded-xl px-4 py-2.5 text-sm shadow-sm transition-all placeholder:text-outline-variant/60 font-medium border border-on-surface/10" placeholder="admin@clinic.com" type="email" value="<?php echo htmlspecialchars($prefill_email); ?>"/>
</div>
<div class="space-y-1.5">
<label class="text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant px-0.5">Contact Number</label>
<input name="clinic_phone" autocomplete="tel" class="purchase-input w-full bg-white focus:ring-2 focus:ring-primary/20 rounded-xl px-4 py-2.5 text-sm shadow-sm transition-all placeholder:text-outline-variant/60 font-medium border border-on-surface/10" placeholder="+63 912 345 6789" type="tel" value="<?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?>"/>
</div>
<div class="space-y-1.5 md:col-span-2">
<label class="text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant px-0.5">Business Address</label>
<textarea name="clinic_address" autocomplete="street-address" class="purchase-input w-full bg-white focus:ring-2 focus:ring-primary/20 rounded-xl px-4 py-2.5 text-sm shadow-sm transition-all placeholder:text-outline-variant/60 font-medium border border-on-surface/10 min-h-[88px] resize-y" placeholder="Street, City, ZIP" rows="3"><?php echo htmlspecialchars($tenant['clinic_address'] ?? ''); ?></textarea>
</div>
</div>
</section>

<section class="bg-surface-container-low rounded-2xl p-5 sm:p-6 border border-on-surface/5">
<div class="flex items-center gap-3 mb-5">
<div class="bg-primary text-white p-2 rounded-xl shadow-md shadow-primary/20">
<span class="material-symbols-outlined text-[22px]">payments</span>
</div>
<div>
<h2 class="font-headline text-lg sm:text-xl font-extrabold tracking-tight">Payment Method</h2>
<p class="text-xs font-medium text-on-surface-variant">Choose your preferred gateway</p>
</div>
</div>
<div id="payment-methods" class="grid grid-cols-2 md:grid-cols-3 gap-2.5 sm:gap-3">
<label for="pm_gcash" data-method="gcash" class="pm-option group flex flex-col items-center justify-center p-3 sm:p-4 bg-white border rounded-xl transition-all duration-300 cursor-pointer border-on-surface/5 hover:border-primary/30">
<input id="pm_gcash" class="sr-only" name="payment_method" value="gcash" type="radio" <?php echo $selected_payment_method === 'gcash' ? 'checked' : ''; ?>/>
<span class="material-symbols-outlined pm-icon text-primary text-2xl mb-1.5">account_balance_wallet</span>
<span class="pm-text font-bold text-[10px] uppercase tracking-wider text-on-surface-variant group-hover:text-primary transition-colors">GCash</span>
</label>

<label for="pm_card" data-method="card" class="pm-option group flex flex-col items-center justify-center p-3 sm:p-4 bg-white border rounded-xl transition-all duration-300 cursor-pointer border-on-surface/5 hover:border-primary/30">
<input id="pm_card" class="sr-only" name="payment_method" value="card" type="radio" <?php echo $selected_payment_method === 'card' ? 'checked' : ''; ?>/>
<span class="material-symbols-outlined pm-icon pm-icon-card text-primary text-2xl mb-1.5">credit_card</span>
<span class="pm-text font-bold text-[10px] uppercase tracking-wider text-on-surface-variant group-hover:text-primary transition-colors">Credit Card</span>
</label>

<label for="pm_maya" data-method="maya" class="pm-option group flex flex-col items-center justify-center p-3 sm:p-4 bg-white border rounded-xl transition-all duration-300 cursor-pointer border-on-surface/5 hover:border-primary/30">
<input id="pm_maya" class="sr-only" name="payment_method" value="maya" type="radio" <?php echo $selected_payment_method === 'paymaya' ? 'checked' : ''; ?>/>
<span class="material-symbols-outlined pm-icon text-primary text-2xl mb-1.5">phone_iphone</span>
<span class="pm-text font-bold text-[10px] uppercase tracking-wider text-on-surface-variant group-hover:text-primary transition-colors">Maya</span>
</label>
</div>
<p id="payment-method-error" class="mt-2 hidden text-xs font-semibold text-error">Please choose a payment method before confirming your purchase.</p>
</section>
</div>

<!-- Right Column: Order summary -->
<aside class="lg:col-span-5 lg:sticky lg:top-24 self-start w-full">
<!-- Match Provider-Plans.php plan cards: bg-primary + white text + SVG arc overlay (same as Professional card hover) -->
<div class="relative overflow-hidden rounded-3xl border border-slate-200/70 bg-primary p-5 sm:p-6 text-white shadow-sm shadow-primary/25">
<div class="absolute top-0 right-0 h-full w-full opacity-10 pointer-events-none" aria-hidden="true">
<svg class="h-full w-full fill-none stroke-white/45" viewBox="0 0 100 100" preserveAspectRatio="none">
<circle cx="100" cy="0" r="90" stroke-width="0.3"></circle>
<circle cx="100" cy="0" r="70" stroke-width="0.3"></circle>
<circle cx="100" cy="0" r="50" stroke-width="0.3"></circle>
</svg>
</div>
<div class="relative z-10">
<h3 class="font-headline text-lg sm:text-xl font-extrabold mb-5 tracking-tight text-white">Order Summary</h3>
<div class="space-y-5">
<div class="flex justify-between items-start gap-3 border-b border-white/20 pb-5">
<div class="min-w-0">
<p class="mb-1 text-[10px] font-black uppercase tracking-[0.18em] text-white/70">Selected Plan</p>
<div class="flex items-center gap-2">
<h4 id="summary-plan-name" class="font-headline text-base sm:text-lg font-extrabold break-words font-editorial italic text-white editorial-word"><?php echo htmlspecialchars($plan_name); ?></h4>
<button id="open-plan-modal" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/25 bg-white/10 text-white transition-all hover:bg-white/20 hover:border-white/45 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60" aria-label="Change subscription plan">
<span class="material-symbols-outlined text-lg leading-none">sync_alt</span>
</button>
</div>
<p class="mt-1.5 flex items-center gap-1.5 text-xs font-medium text-white/80">
<span class="material-symbols-outlined shrink-0 text-base text-white/90">event_repeat</span>
                                    Billing monthly
                                </p>
</div>
<p id="summary-plan-price-head" class="font-headline shrink-0 text-base sm:text-lg font-extrabold text-white">₱<?php echo number_format($plan_price, 2); ?></p>
</div>
<div class="space-y-2.5 pt-1">
<div class="flex justify-between text-xs font-medium text-white sm:text-sm">
<span class="text-white/75">Plan Subtotal</span>
<span id="summary-plan-subtotal">₱<?php echo number_format($plan_price, 2); ?>/mo</span>
</div>
<div class="flex justify-between text-xs font-medium text-white sm:text-sm">
<span class="text-white/75">Setup Fee</span>
<span>₱0.00</span>
</div>
</div>
<div class="mt-4 flex flex-col gap-2 border-t border-white/20 pt-5 sm:flex-row sm:items-end sm:justify-between">
<div>
<p class="mb-0.5 text-[10px] font-black uppercase tracking-[0.18em] text-white/70">Total Amount Due</p>
<p class="text-[11px] font-bold text-white/80">PHP</p>
</div>
<p id="summary-plan-total" class="font-headline text-2xl font-extrabold tracking-tight text-white sm:text-3xl">₱<?php echo number_format($plan_price, 2); ?></p>
</div>
<button id="confirm-purchase-btn" form="provider-purchase-form" type="submit" class="mt-3 flex w-full items-center justify-center gap-2 rounded-xl bg-white py-3.5 text-xs font-bold uppercase tracking-wider text-primary shadow-xl transition-all hover:scale-[1.02] hover:bg-white/90 active:scale-[0.99] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50">
                            Confirm Purchase
                            <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_right_alt</span>
</button>
<?php
$simulate_fail_qs = ['simulate' => 'fail'];
if (!empty($plan_slug)) {
    $simulate_fail_qs['plan'] = $plan_slug;
}
if ($debug_mode) {
    $simulate_fail_qs['debug'] = '1';
}
$simulate_fail_href = 'ProviderPurchase.php?' . http_build_query($simulate_fail_qs);
?>
<a href="<?php echo htmlspecialchars($simulate_fail_href, ENT_QUOTES, 'UTF-8'); ?>" class="mt-3 block text-center text-[11px] text-white/70 transition-colors hover:text-white">Simulate payment failure</a>
<p class="mt-3 text-center text-[10px] leading-relaxed text-white/75">
                                    By confirming, you agree to our Terms of Service and Privacy Policy. Your subscription will renew automatically.
                                </p>
<div class="flex flex-col items-center gap-3 pt-5">
<div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-[0.15em] text-white/55">
<span class="material-symbols-outlined text-sm">lock</span>
                                Secure Payment Gateway
                            </div>
<p class="text-[10px] font-semibold uppercase tracking-wider text-white/50">Visa · Mastercard · American Express</p>
</div>
</div>
</div>
</div>

<div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-primary text-xl shrink-0 pm-fill-icon">verified_user</span>
<div>
<p class="text-[10px] font-black uppercase tracking-[0.08em] text-on-surface">Secure &amp; compliant</p>
<p class="text-[11px] font-medium text-on-surface-variant leading-snug">Encrypted checkout and clinic data handling</p>
</div>
</div>
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-primary text-xl shrink-0 pm-fill-icon">support_agent</span>
<div>
<p class="text-[10px] font-black uppercase tracking-[0.08em] text-on-surface">Provider support</p>
<p class="text-[11px] font-medium text-on-surface-variant leading-snug">Help with onboarding and billing questions</p>
</div>
</div>
</div>
</aside>
</div>

<div id="plan-selection-modal" class="plan-modal-backdrop fixed inset-0 z-[70] flex items-center justify-center bg-[#131c25]/45 p-4 sm:p-6" aria-hidden="true">
<div class="plan-modal-panel w-full max-w-2xl rounded-2xl border border-on-surface/10 bg-white p-5 shadow-2xl sm:p-6">
<div class="mb-4 flex items-start justify-between gap-4 border-b border-on-surface/10 pb-4">
<div>
<h3 class="font-headline text-xl font-extrabold tracking-tight text-on-surface">Switch Subscription Plan</h3>
<p class="mt-1 text-sm font-medium text-on-surface-variant">Choose a plan and apply it to your order summary instantly.</p>
</div>
<button id="close-plan-modal" type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-on-surface/10 text-on-surface-variant transition-colors hover:border-primary/40 hover:text-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30" aria-label="Close plan selector">
<span class="material-symbols-outlined text-lg leading-none">close</span>
</button>
</div>
<div id="plan-modal-options" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
<?php foreach ($allowed as $plan_option_slug): ?>
<?php
$plan_option = $available_plans[$plan_option_slug] ?? [
    'plan_name' => ($plan_label_map[$plan_option_slug] ?? ucfirst($plan_option_slug)),
    'plan_price' => (float) ($plan_price_fallback_map[$plan_option_slug] ?? 0),
];
$is_modal_selected = ($plan_option_slug === $plan_slug);
?>
<button
    type="button"
    data-plan-slug="<?php echo htmlspecialchars($plan_option_slug, ENT_QUOTES, 'UTF-8'); ?>"
    class="plan-option group rounded-xl border bg-surface-container-low p-4 text-left transition-all duration-200 hover:border-primary/40 hover:shadow-md <?php echo $is_modal_selected ? 'is-selected border-primary ring-2 ring-primary/20' : 'border-on-surface/10'; ?>"
>
<p class="text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant">Monthly Plan</p>
<div class="mt-2 flex items-center justify-between gap-2">
<h4 class="font-headline text-base font-extrabold text-on-surface"><?php echo htmlspecialchars((string) ($plan_option['plan_name'] ?? ucfirst($plan_option_slug)), ENT_QUOTES, 'UTF-8'); ?></h4>
<span class="material-symbols-outlined text-base text-primary opacity-0 transition-opacity group-[.is-selected]:opacity-100">check_circle</span>
</div>
<p class="mt-1.5 text-sm font-semibold text-primary">₱<?php echo number_format((float) ($plan_option['plan_price'] ?? 0), 2); ?>/mo</p>
</button>
<?php endforeach; ?>
</div>
<div class="mt-5 flex items-center justify-end gap-2">
<button id="cancel-plan-change" type="button" class="rounded-xl border border-on-surface/10 px-4 py-2.5 text-sm font-semibold text-on-surface-variant transition-colors hover:border-on-surface/20 hover:text-on-surface">Cancel</button>
<button id="confirm-plan-change" type="button" class="rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-transform hover:scale-[1.01] active:scale-[0.99]">Apply Plan</button>
</div>
</div>
</div>
</form>
</main>
</div>
<script>
(function () {
  var root = document.getElementById('payment-methods');
  var form = document.getElementById('provider-purchase-form');
  var submitBtn = document.getElementById('confirm-purchase-btn');
  var paymentError = document.getElementById('payment-method-error');
  var selectedPlanInput = document.getElementById('selected_plan_slug');
  if (!root) return;

  var activeBorder = ['border-2', 'border-primary', 'shadow-lg', 'shadow-primary/10'];
  var inactiveBorder = ['border', 'border-on-surface/5', 'hover:border-primary/30'];

  function setPmIconFill(icon, filled) {
    if (!icon || !icon.classList.contains('pm-icon-card')) return;
    icon.style.fontVariationSettings = filled ? "'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24" : "'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24";
  }

  function applySelection() {
    var selected = root.querySelector('input[name="payment_method"]:checked');
    var selectedVal = selected ? selected.value : '';

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
    if (e.target && e.target.name === 'payment_method') {
      if (paymentError) paymentError.classList.add('hidden');
      if (submitBtn) submitBtn.disabled = false;
      applySelection();
    }
  });

  if (form && submitBtn) {
    submitBtn.addEventListener('click', function () {
      if (paymentError) paymentError.classList.add('hidden');
    });

    form.addEventListener('submit', function (e) {
      var selected = root.querySelector('input[name="payment_method"]:checked');
      if (!selected) {
        var fallbackCard = document.getElementById('pm_card');
        if (fallbackCard) {
          fallbackCard.checked = true;
          selected = fallbackCard;
          applySelection();
        }
      }
      if (!selected) {
        // Do not block submission; backend will still validate and return clear errors.
      }
      if (!selectedPlanInput || !selectedPlanInput.value) {
        // Fail-safe default plan if hidden field was unexpectedly cleared client-side.
        if (selectedPlanInput) {
          selectedPlanInput.value = 'professional';
        }
      }

      // Prevent duplicate clicks while backend creates checkout session.
      submitBtn.disabled = true;
      submitBtn.setAttribute('aria-disabled', 'true');
      submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
      submitBtn.dataset.originalHtml = submitBtn.dataset.originalHtml || submitBtn.innerHTML;
      submitBtn.innerHTML = 'Redirecting to PayMongo...';
    });
  }

  applySelection();
})();

(function () {
  var plans = <?php echo $available_plans_json; ?>;
  var hasExplicitPlanRequest = <?php echo $has_explicit_plan_request ? 'true' : 'false'; ?>;
  var selectedPlanInput = document.getElementById('selected_plan_slug');
  var summaryPlanName = document.getElementById('summary-plan-name');
  var summaryPriceHead = document.getElementById('summary-plan-price-head');
  var summarySubtotal = document.getElementById('summary-plan-subtotal');
  var summaryTotal = document.getElementById('summary-plan-total');
  var modal = document.getElementById('plan-selection-modal');
  var openBtn = document.getElementById('open-plan-modal');
  var closeBtn = document.getElementById('close-plan-modal');
  var cancelBtn = document.getElementById('cancel-plan-change');
  var confirmBtn = document.getElementById('confirm-plan-change');
  var optionButtons = Array.prototype.slice.call(document.querySelectorAll('.plan-option'));
  var storageKey = 'providerPurchaseSelectedPlan';
  var stagedPlanSlug = selectedPlanInput ? selectedPlanInput.value : '';
  var currentPlanSlug = stagedPlanSlug;

  if (!selectedPlanInput || !summaryPlanName || !summaryPriceHead || !summarySubtotal || !summaryTotal || !modal || !openBtn || optionButtons.length === 0) {
    return;
  }

  function formatMoney(amount) {
    var numeric = Number(amount) || 0;
    return '\u20b1' + numeric.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function getPlan(planSlug) {
    return plans && Object.prototype.hasOwnProperty.call(plans, planSlug) ? plans[planSlug] : null;
  }

  function applyPlanToSummary(planSlug) {
    var plan = getPlan(planSlug);
    if (!plan) return;
    var name = plan.plan_name || 'Professional';
    var price = Number(plan.plan_price || 0);
    summaryPlanName.textContent = name;
    summaryPriceHead.textContent = formatMoney(price);
    summarySubtotal.textContent = formatMoney(price) + '/mo';
    summaryTotal.textContent = formatMoney(price);
    selectedPlanInput.value = planSlug;
    currentPlanSlug = planSlug;
    syncOptionSelection(planSlug);
    try {
      window.localStorage.setItem(storageKey, planSlug);
    } catch (e) {}
  }

  function syncOptionSelection(targetSlug) {
    optionButtons.forEach(function (btn) {
      var isSelected = btn.getAttribute('data-plan-slug') === targetSlug;
      btn.classList.toggle('is-selected', isSelected);
      btn.classList.toggle('border-primary', isSelected);
      btn.classList.toggle('ring-2', isSelected);
      btn.classList.toggle('ring-primary/20', isSelected);
      btn.classList.toggle('border-on-surface/10', !isSelected);
      btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
    });
  }

  function openModal() {
    stagedPlanSlug = currentPlanSlug;
    syncOptionSelection(stagedPlanSlug);
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
  }

  optionButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var planSlug = btn.getAttribute('data-plan-slug') || '';
      if (!getPlan(planSlug) || planSlug === stagedPlanSlug) return;
      stagedPlanSlug = planSlug;
      syncOptionSelection(stagedPlanSlug);
    });
  });

  openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);
  if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
  if (confirmBtn) {
    confirmBtn.addEventListener('click', function () {
      applyPlanToSummary(stagedPlanSlug);
      closeModal();
    });
  }
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  try {
    var savedPlanSlug = window.localStorage.getItem(storageKey);
    if (!hasExplicitPlanRequest && savedPlanSlug && getPlan(savedPlanSlug) && savedPlanSlug !== currentPlanSlug) {
      applyPlanToSummary(savedPlanSlug);
    } else {
      applyPlanToSummary(currentPlanSlug);
    }
  } catch (e) {
    applyPlanToSummary(currentPlanSlug);
  }
})();
</script>
</body></html>