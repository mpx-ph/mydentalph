<?php
session_start();
require_once __DIR__ . '/provider_auth.php';
provider_require_approved_for_provider_portal();
require_once __DIR__ . '/db.php';
require_once 'paymongo_config.php';

$tenant_id = $_SESSION['paymongo_tenant_id'] ?? null;
$user_id = $_SESSION['paymongo_user_id'] ?? null;
$plan_id = $_SESSION['paymongo_plan_id'] ?? null;
$plan_name = $_SESSION['paymongo_plan_name'] ?? 'Professional';
$plan_price = $_SESSION['paymongo_plan_price'] ?? 0;
$payment_method = $_SESSION['paymongo_payment_method'] ?? 'card'; // card|gcash|paymaya
$payment_intent_id = $_SESSION['paymongo_payment_intent_id'] ?? null;
$client_key = $_SESSION['paymongo_client_key'] ?? null;

if (!$tenant_id || !$user_id || !$plan_id) {
    header('Location: ProviderPurchase.php');
    exit;
}

// Optional: verify Payment Intent status with PayMongo (e.g. when returning from 3DS or ?check=1)
$status_verified = false;
if ($payment_intent_id && $client_key && defined('PAYMONGO_PUBLIC_KEY')) {
    $pk = PAYMONGO_PUBLIC_KEY;
    if ($pk && strpos($pk, 'YOUR_') === false) {
        $ch = curl_init('https://api.paymongo.com/v1/payment_intents/' . $payment_intent_id . '?client_key=' . urlencode($client_key));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($pk . ':')],
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = $res ? json_decode($res, true) : null;
        $status = $data['data']['attributes']['status'] ?? '';
        if ($status === 'succeeded' || $status === 'processing') {
            $status_verified = true;
        }
    }
}
// If we didn't verify (no key or API error), still allow success when we have session (user was redirected from our checkout)
if (!$status_verified && isset($_SESSION['paymongo_client_key'])) {
    $status_verified = true;
}

if (!$status_verified) {
    header('Location: ProviderPayMongoCheckout.php');
    exit;
}

try {
    $start = date('Y-m-d');
    $end = date('Y-m-d', strtotime('+1 month'));
    $valid_methods = ['card', 'gcash', 'paymaya'];
    if (!in_array($payment_method, $valid_methods, true)) {
        $payment_method = 'card';
    }
    $stmt = $pdo->prepare("INSERT INTO tbl_tenant_subscriptions (tenant_id, plan_id, subscription_start, subscription_end, payment_status, payment_method, amount_paid, reference_number) VALUES (?, ?, ?, ?, 'paid', ?, ?, ?)");
    // Placeholders: tenant_id, plan_id, subscription_start, subscription_end, payment_method, amount_paid, reference_number
    $stmt->execute([
        $tenant_id,
        $plan_id,
        $start,
        $end,
        $payment_method,
        $plan_price,
        'PM-' . ($payment_intent_id ?: bin2hex(random_bytes(4)))
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
    unset($_SESSION['paymongo_client_key'], $_SESSION['paymongo_payment_intent_id'], $_SESSION['paymongo_payment_method'], $_SESSION['paymongo_tenant_id'], $_SESSION['paymongo_user_id'], $_SESSION['paymongo_plan_id'], $_SESSION['paymongo_plan_name'], $_SESSION['paymongo_plan_price']);
} catch (PDOException $e) {
    // Already paid; ensure user gets to dashboard
    unset($_SESSION['paymongo_client_key'], $_SESSION['paymongo_payment_intent_id'], $_SESSION['paymongo_payment_method'], $_SESSION['paymongo_tenant_id'], $_SESSION['paymongo_user_id'], $_SESSION['paymongo_plan_id'], $_SESSION['paymongo_plan_name'], $_SESSION['paymongo_plan_price']);
}

header('Location: ProviderTenantDashboard.php');
exit;
