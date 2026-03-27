<?php
session_start();

require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
provider_require_approved_for_provider_portal();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin/superadmin_settings_lib.php';
require_once __DIR__ . '/paymongo_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ProviderPurchase.php');
    exit;
}

if (!provider_has_authenticated_provider_session()) {
    header('Location: ProviderLogin.php?redirect=' . urlencode('ProviderPurchase.php'));
    exit;
}

[$tenant_id, $user_id] = provider_get_authenticated_provider_identity_from_session();
$valid_methods = ['card', 'gcash', 'paymaya'];
$allowed_plans = ['starter', 'professional', 'enterprise'];

$posted_token = (string) ($_POST['purchase_form_token'] ?? '');
$session_token = (string) ($_SESSION['provider_purchase_form_token'] ?? '');
if ($posted_token === '' || $session_token === '' || !hash_equals($session_token, $posted_token)) {
    $_SESSION['provider_purchase_error'] = 'Your purchase session expired. Please refresh and try again.';
    header('Location: ProviderPurchase.php');
    exit;
}
unset($_SESSION['provider_purchase_form_token']);

$payment_method = strtolower(trim((string) ($_POST['payment_method'] ?? '')));
if ($payment_method === 'maya') {
    $payment_method = 'paymaya';
}
if (!in_array($payment_method, $valid_methods, true)) {
    $_SESSION['provider_purchase_error'] = 'Please select a valid payment method.';
    header('Location: ProviderPurchase.php');
    exit;
}

$plan_slug = strtolower(trim((string) ($_POST['selected_plan_slug'] ?? 'professional')));
if (!in_array($plan_slug, $allowed_plans, true)) {
    $plan_slug = 'professional';
}

$plan_label_map = [
    'starter' => 'Starter',
    'professional' => 'Professional',
    'enterprise' => 'Enterprise',
];
$plan_price_map = [
    'starter' => 999.0,
    'professional' => 2499.0,
    'enterprise' => 4999.0,
];
$plan_id_map = [
    'starter' => 1,
    'professional' => 2,
    'enterprise' => 3,
];

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

try {
    $settings = superadmin_get_settings($pdo);
    $provider_plans_settings = isset($settings['provider_plans']) && is_array($settings['provider_plans'])
        ? $settings['provider_plans']
        : [];
    foreach ($allowed_plans as $slug) {
        $setting_plan = isset($provider_plans_settings[$slug]) && is_array($provider_plans_settings[$slug])
            ? $provider_plans_settings[$slug]
            : [];
        $settings_label = trim((string) ($setting_plan['name'] ?? ''));
        if ($settings_label !== '') {
            $plan_label_map[$slug] = $settings_label;
        }
        $settings_price = $normalize_plan_price($setting_plan['price'] ?? null);
        if ($settings_price !== null) {
            $plan_price_map[$slug] = $settings_price;
        }
    }
} catch (Throwable $e) {
    // Fallback maps stay in place.
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
        $slug = $resolve_allowed_slug($row);
        if (!in_array($slug, $allowed_plans, true)) {
            continue;
        }
        $row_price = $normalize_plan_price($row['price'] ?? null);
        if ($row_price !== null) {
            $plan_price_map[$slug] = $row_price;
        }
        if (!empty($row['plan_id'])) {
            $plan_id_map[$slug] = (int) $row['plan_id'];
        }
        if (!empty($row['plan_name'])) {
            $plan_label_map[$slug] = (string) $row['plan_name'];
        }
    }
} catch (Throwable $e) {
    // Keep fallback plan values.
}

$plan_id = (int) ($plan_id_map[$plan_slug] ?? 0);
$plan_name = (string) ($plan_label_map[$plan_slug] ?? 'Professional');
$plan_price = (float) ($plan_price_map[$plan_slug] ?? 0);
if ($plan_id <= 0 || $plan_price <= 0) {
    $_SESSION['provider_purchase_error'] = 'Invalid plan details. Please choose a plan and try again.';
    header('Location: ProviderPurchase.php?plan=' . urlencode($plan_slug));
    exit;
}

$clinic_name = trim((string) ($_POST['clinic_name'] ?? ''));
$contact_email = trim((string) ($_POST['clinic_email'] ?? ''));
$contact_phone = trim((string) ($_POST['clinic_phone'] ?? ''));
$clinic_address = trim((string) ($_POST['clinic_address'] ?? ''));

if ($clinic_name === '') {
    $clinic_name = 'MyDental Clinic';
}
if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    $contact_email = 'billing+' . preg_replace('/[^a-z0-9]/i', '', (string) $tenant_id) . '@mydental.local';
}

try {
    $stmt = $pdo->prepare("
        UPDATE tbl_tenants
        SET clinic_name = COALESCE(NULLIF(?, ''), clinic_name),
            contact_email = COALESCE(NULLIF(?, ''), contact_email),
            contact_phone = ?,
            clinic_address = ?
        WHERE tenant_id = ?
    ");
    $stmt->execute([$clinic_name, $contact_email, $contact_phone, $clinic_address, $tenant_id]);
} catch (Throwable $e) {
    $_SESSION['provider_purchase_error'] = 'Could not save clinic details. Please try again.';
    header('Location: ProviderPurchase.php?plan=' . urlencode($plan_slug));
    exit;
}

$secret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
if ($secret === '' || strpos($secret, 'YOUR_') !== false) {
    $_SESSION['provider_purchase_error'] = 'PayMongo API key is missing. Please configure PAYMONGO_SECRET_KEY.';
    header('Location: ProviderPurchase.php?plan=' . urlencode($plan_slug));
    exit;
}

$existing_lock = (int) ($_SESSION['provider_purchase_submit_lock_at'] ?? 0);
if ($existing_lock > 0 && (time() - $existing_lock) < 12) {
    $_SESSION['provider_purchase_error'] = 'Purchase is already being processed. Please wait a moment.';
    header('Location: ProviderPurchase.php?plan=' . urlencode($plan_slug));
    exit;
}
$_SESSION['provider_purchase_submit_lock_at'] = time();

$amount_centavos = (int) round($plan_price * 100);
if ($amount_centavos < 10000) {
    $amount_centavos = 10000;
}

$request_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$request_host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
$callback_base = rtrim($request_scheme . '://' . $request_host . dirname((string) ($_SERVER['PHP_SELF'] ?? '/')), '/\\');
$success_url = $callback_base . '/ProviderPurchaseReceipt.php?source=checkout';
$cancel_url = $callback_base . '/ProviderPurchase.php?payment=failed&reason=' . urlencode('Payment was cancelled. Please try again.');
$checkout_reference = 'CHK-' . strtoupper(bin2hex(random_bytes(5)));

$payload = json_encode([
    'data' => [
        'attributes' => [
            'billing' => [
                'name' => $clinic_name,
                'email' => $contact_email,
            ],
            'send_email_receipt' => false,
            'show_description' => true,
            'show_line_items' => true,
            'description' => 'MyDental subscription checkout',
            'line_items' => [[
                'currency' => 'PHP',
                'amount' => $amount_centavos,
                'name' => 'MyDental - ' . $plan_name . ' plan',
                'quantity' => 1,
            ]],
            'payment_method_types' => [$payment_method],
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'reference_number' => $checkout_reference,
            'metadata' => [
                'tenant_id' => (string) $tenant_id,
                'user_id' => (string) $user_id,
                'plan_id' => (string) $plan_id,
                'plan_name' => (string) $plan_name,
                'plan_price' => (string) $plan_price,
                'plan_slug' => (string) $plan_slug,
                'payment_method' => (string) $payment_method,
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES);

try {
    $endpoint = 'https://api.paymongo.com/v1/checkout_sessions';
    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($secret . ':'),
        'X-Request-Id: ' . bin2hex(random_bytes(12)),
    ];
    $res = false;
    $transport_error = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $res = curl_exec($ch);
        if ($res === false) {
            $transport_error = (string) curl_error($ch);
        }
        curl_close($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $res = @file_get_contents($endpoint, false, $context);
        if ($res === false) {
            $transport_error = 'Unable to contact PayMongo endpoint.';
        }
    }

    $data = is_string($res) && $res !== '' ? json_decode($res, true) : null;
    $checkout_url = $data['data']['attributes']['checkout_url'] ?? null;
    $checkout_session_id = $data['data']['id'] ?? null;

    if ($checkout_url && $checkout_session_id) {
        unset($_SESSION['provider_purchase_submit_lock_at']);
        unset($_SESSION['paymongo_client_key'], $_SESSION['paymongo_payment_intent_id']);
        $_SESSION['paymongo_checkout_session_id'] = $checkout_session_id;
        $_SESSION['paymongo_payment_method'] = $payment_method;
        $_SESSION['paymongo_billing_email'] = $contact_email;
        $_SESSION['paymongo_tenant_id'] = $tenant_id;
        $_SESSION['paymongo_user_id'] = $user_id;
        $_SESSION['paymongo_plan_id'] = $plan_id;
        $_SESSION['paymongo_plan_name'] = $plan_name;
        $_SESSION['paymongo_plan_price'] = $plan_price;
        $_SESSION['paymongo_plan_slug'] = $plan_slug;
        $_SESSION['paymongo_reference_number'] = $checkout_reference;
        header('Location: ' . $checkout_url);
        exit;
    }

    $api_error = $data['errors'][0]['detail'] ?? ($data['errors'][0]['title'] ?? '');
    if ($api_error !== '') {
        $_SESSION['provider_purchase_error'] = 'Could not create payment session: ' . $api_error;
    } elseif ($transport_error !== '') {
        $_SESSION['provider_purchase_error'] = 'Could not reach PayMongo: ' . $transport_error;
    } else {
        $_SESSION['provider_purchase_error'] = 'PayMongo did not return a checkout URL. Please try again.';
    }
} catch (Throwable $e) {
    $_SESSION['provider_purchase_error'] = 'Payment provider is currently unavailable. Please try again shortly.';
}

unset($_SESSION['provider_purchase_submit_lock_at']);
$_SESSION['provider_purchase_last_method'] = $payment_method;
header('Location: ProviderPurchase.php?plan=' . urlencode($plan_slug));
exit;
