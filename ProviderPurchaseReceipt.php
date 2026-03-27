<?php
session_start();
set_exception_handler(function (Throwable $e): void {
    error_log('[ProviderPurchaseReceipt][UnhandledException] ' . $e->getMessage());
    if (!headers_sent()) {
        $_SESSION['provider_purchase_error'] = 'Unable to finalize payment right now. Please try again.';
        header('Location: ProviderPurchase.php?payment=failed&reason=' . urlencode('Unable to finalize payment. Please try again.'));
    }
    exit;
});
register_shutdown_function(function (): void {
    $fatal = error_get_last();
    if (!is_array($fatal)) {
        return;
    }
    $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($fatal['type'] ?? 0), $fatal_types, true)) {
        return;
    }
    error_log('[ProviderPurchaseReceipt][FatalShutdown] ' . (string) ($fatal['message'] ?? 'Unknown fatal error'));
    if (!headers_sent()) {
        $_SESSION['provider_purchase_error'] = 'Payment page encountered a server error. Please try again.';
        header('Location: ProviderPurchase.php?payment=failed&reason=' . urlencode('Payment page server error. Please try again.'));
    }
    exit;
});
require_once __DIR__ . '/provider_auth.php';
if (!provider_has_authenticated_provider_session()) {
    header('Location: ProviderLogin.php?redirect=' . urlencode('ProviderPurchase.php'));
    exit;
}
require_once __DIR__ . '/db.php';
require_once 'paymongo_config.php';

$source = trim((string) ($_GET['source'] ?? ''));
$return_token = trim((string) ($_GET['token'] ?? ''));
$checkout_return_token = trim((string) ($_SESSION['paymongo_checkout_return_token'] ?? ''));
$status_from_query = strtolower(trim((string) ($_GET['status'] ?? '')));
$allowed_query_statuses = ['success', 'paid', 'failed', 'cancelled', 'canceled', 'processing'];
if ($status_from_query !== '' && !in_array($status_from_query, $allowed_query_statuses, true)) {
    $status_from_query = '';
}

[$auth_tenant_id, $auth_user_id] = provider_get_authenticated_provider_identity_from_session();
$payment_intent_id = $_SESSION['paymongo_payment_intent_id'] ?? null;
$checkout_session_id = $_SESSION['paymongo_checkout_session_id'] ?? null;
$client_key = $_SESSION['paymongo_client_key'] ?? null;
$plan_name = $_SESSION['paymongo_plan_name'] ?? 'Professional';
$plan_price = $_SESSION['paymongo_plan_price'] ?? 0;
$payment_method = $_SESSION['paymongo_payment_method'] ?? 'card'; // card|gcash|paymaya
$billing_email = $_SESSION['paymongo_billing_email'] ?? '';
$tenant_id = $_SESSION['paymongo_tenant_id'] ?? null;
$user_id = $_SESSION['paymongo_user_id'] ?? null;
$plan_id = $_SESSION['paymongo_plan_id'] ?? null;
$reference_number = $_SESSION['paymongo_reference_number'] ?? ($checkout_session_id ? ('PM-' . $checkout_session_id) : ($payment_intent_id ? ('PM-' . $payment_intent_id) : ''));

if (!$tenant_id || !$user_id || !$plan_id || (float) $plan_price <= 0) {
    header('Location: ProviderPurchase.php');
    exit;
}
if ((string) $tenant_id !== (string) $auth_tenant_id || (string) $user_id !== (string) $auth_user_id) {
    $_SESSION['provider_purchase_error'] = 'Invalid payment session. Please try again.';
    header('Location: ProviderPurchase.php');
    exit;
}
if ($source === 'checkout' && ($return_token === '' || $checkout_return_token === '' || !hash_equals($checkout_return_token, $return_token))) {
    $_SESSION['provider_purchase_error'] = 'Invalid payment return token. Please retry checkout.';
    header('Location: ProviderPurchase.php');
    exit;
}

$method_label = $payment_method === 'gcash' ? 'GCash' : ($payment_method === 'paymaya' ? 'Maya' : 'Card');
$now = new DateTime('now');
$invoice_seed = $checkout_session_id ?: $payment_intent_id;
$invoice_no = 'PM-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $invoice_seed);
$invoice_no = substr($invoice_no, 0, 22);

$status = null;
$status_detail = null;
if (!function_exists('provider_paymongo_get_json')) {
    function provider_paymongo_get_json(string $endpoint, string $secret): ?array
    {
        $headers = ['Authorization: Basic ' . base64_encode($secret . ':')];
        $res = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            $res = curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 15,
                    'ignore_errors' => true,
                ],
            ]);
            $res = @file_get_contents($endpoint, false, $context);
        }
        if (!is_string($res) || trim($res) === '') {
            return null;
        }
        $decoded = json_decode($res, true);
        return is_array($decoded) ? $decoded : null;
    }
}
if (!function_exists('provider_array_get')) {
    function provider_array_get(array $source, array $path, $default = '')
    {
        $cursor = $source;
        foreach ($path as $segment) {
            if (is_array($cursor) && array_key_exists($segment, $cursor)) {
                $cursor = $cursor[$segment];
                continue;
            }
            return $default;
        }
        return $cursor;
    }
}

if (defined('PAYMONGO_SECRET_KEY')) {
    $secret = PAYMONGO_SECRET_KEY;
    if ($secret && strpos($secret, 'YOUR_') === false) {
        if ($checkout_session_id) {
            $checkout_endpoint = 'https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode((string) $checkout_session_id);
            $checkout_data = provider_paymongo_get_json($checkout_endpoint, $secret);
            $checkout_data = is_array($checkout_data) ? $checkout_data : [];
            $checkout_payment_intent_attr_status = strtolower((string) provider_array_get($checkout_data, ['data', 'attributes', 'payment_intent', 'attributes', 'status'], ''));
            $checkout_attr_status = strtolower((string) provider_array_get($checkout_data, ['data', 'attributes', 'status'], ''));
            $checkout_payment_status = strtolower((string) provider_array_get($checkout_data, ['data', 'attributes', 'payments', 0, 'attributes', 'status'], ''));
            $checkout_payment_intent = (string) provider_array_get($checkout_data, ['data', 'attributes', 'payment_intent', 'id'], '');
            $checkout_payment_id = (string) provider_array_get($checkout_data, ['data', 'attributes', 'payments', 0, 'id'], '');
            $checkout_method = strtolower((string) provider_array_get($checkout_data, ['data', 'attributes', 'payments', 0, 'attributes', 'source', 'type'], ''));
            $status_detail = (string) provider_array_get($checkout_data, ['data', 'attributes', 'payments', 0, 'attributes', 'failed_message'], '');

            if ($checkout_payment_intent !== '') {
                $payment_intent_id = $checkout_payment_intent;
            }
            if ($checkout_payment_id !== '') {
                $reference_number = 'PM-' . $checkout_payment_id;
            } elseif ($reference_number === '') {
                $reference_number = 'PM-' . (string) $checkout_session_id;
            }
            if (in_array($checkout_payment_status, ['paid', 'succeeded', 'failed', 'cancelled'], true)) {
                $status = $checkout_payment_status;
            } elseif (in_array($checkout_payment_intent_attr_status, ['succeeded', 'processing', 'awaiting_payment_method', 'awaiting_next_action'], true)) {
                $status = $checkout_payment_intent_attr_status;
            } elseif ($checkout_attr_status !== '') {
                $status = $checkout_attr_status;
            }
            if (in_array($checkout_method, ['gcash', 'paymaya', 'card'], true)) {
                $payment_method = $checkout_method;
            }
        } elseif ($payment_intent_id && $client_key) {
            $intent_endpoint = 'https://api.paymongo.com/v1/payment_intents/' . rawurlencode((string) $payment_intent_id) . '?client_key=' . urlencode((string) $client_key);
            $intent_data = provider_paymongo_get_json($intent_endpoint, $secret);
            $intent_data = is_array($intent_data) ? $intent_data : [];
            $status = strtolower((string) provider_array_get($intent_data, ['data', 'attributes', 'status'], ''));
            $status_detail = provider_array_get($intent_data, ['data', 'attributes', 'last_payment_error', 'message'], null);
        }
    }
}

if ($status === null || $status === '') {
    $_SESSION['provider_purchase_last_method'] = (string) $payment_method;
    header('Location: ProviderPurchase.php?payment=failed&reason=' . urlencode('Unable to verify payment status. Please contact support if charged.'));
    exit;
}
// If PayMongo says it failed, do not continue.
if (in_array((string) $status, ['cancelled', 'canceled', 'failed', 'awaiting_payment_method'], true)) {
    $_SESSION['provider_purchase_last_method'] = (string) $payment_method;
    $msg = $status_detail ?: 'Payment did not complete. Please try again.';
    header('Location: ProviderPurchase.php?payment=failed&reason=' . urlencode($msg));
    exit;
}
if (!in_array((string) $status, ['succeeded', 'paid'], true)) {
    $_SESSION['provider_purchase_last_method'] = (string) $payment_method;
    $msg = 'Payment is still being processed. Please wait a moment and refresh this page.';
    header('Location: ProviderPurchase.php?payment=failed&reason=' . urlencode($msg));
    exit;
}
if (in_array((string) $status_from_query, ['failed', 'cancelled', 'canceled'], true)) {
    $_SESSION['provider_purchase_last_method'] = (string) $payment_method;
    $msg = $status_detail ?: 'Payment did not complete. Please try again.';
    header('Location: ProviderPurchase.php?payment=failed&reason=' . urlencode($msg));
    exit;
}

// Idempotent finalization: do not insert duplicate successful subscriptions.
try {
    $pdo->beginTransaction();
    $checkStmt = $pdo->prepare("
        SELECT id
        FROM tbl_tenant_subscriptions
        WHERE tenant_id = ? AND reference_number = ?
        LIMIT 1
    ");
    $checkStmt->execute([(string) $tenant_id, (string) $reference_number]);
    $existingId = $checkStmt->fetchColumn();

    if (!$existingId) {
        $start = date('Y-m-d');
        $end = date('Y-m-d', strtotime('+1 month'));
        $insertStmt = $pdo->prepare("
            INSERT INTO tbl_tenant_subscriptions
            (tenant_id, plan_id, subscription_start, subscription_end, payment_status, payment_method, amount_paid, reference_number)
            VALUES (?, ?, ?, ?, 'paid', ?, ?, ?)
        ");
        $insertStmt->execute([
            (string) $tenant_id,
            (int) $plan_id,
            $start,
            $end,
            (string) $payment_method,
            (float) $plan_price,
            (string) $reference_number,
        ]);
    }

    $tenantStmt = $pdo->prepare("UPDATE tbl_tenants SET subscription_status = 'active' WHERE tenant_id = ?");
    $tenantStmt->execute([(string) $tenant_id]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ProviderPurchase.php?payment=failed&reason=' . urlencode('Could not finalize subscription. Please contact support.'));
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT user_id, tenant_id, username, email, full_name, role, status FROM tbl_users WHERE user_id = ?");
    $stmt->execute([(string) $user_id]);
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
} catch (Throwable $e) {
    // Keep existing session values if refresh cannot complete.
}

unset(
    $_SESSION['onboarding_user_id'],
    $_SESSION['onboarding_tenant_id'],
    $_SESSION['onboarding_pending_id'],
    $_SESSION['onboarding_email'],
    $_SESSION['onboarding_plan'],
    $_SESSION['onboarding_full_name'],
    $_SESSION['onboarding_username']
);
unset($_SESSION['paymongo_checkout_return_token']);

$success_finalizer = 'ProviderTenantDashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payment Receipt - MyDental</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: '#2b8cee' } } } };
    </script>
    <style> body { font-family: 'Manrope', sans-serif; } </style>
</head>
<body class="bg-slate-50 min-h-screen py-10 px-4">
    <div class="max-w-lg mx-auto">
        <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
            <div class="p-6 md:p-8 border-b border-slate-100">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-emerald-700">Payment successful</p>
                        <h1 class="text-2xl font-extrabold text-slate-900 mt-1">Receipt</h1>
                        <p class="text-sm text-slate-500 mt-2">Your subscription is now active and payment has been confirmed.</p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-3 py-2 text-emerald-700 text-sm font-bold">
                        ₱<?php echo number_format((float)$plan_price, 2); ?>
                    </div>
                </div>
            </div>

            <div class="p-6 md:p-8">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold text-slate-500">Plan</p>
                        <p class="text-sm font-bold text-slate-900 mt-1"><?php echo htmlspecialchars((string)$plan_name); ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold text-slate-500">Payment method</p>
                        <p class="text-sm font-bold text-slate-900 mt-1"><?php echo htmlspecialchars((string)$method_label); ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold text-slate-500">Billing email</p>
                        <p class="text-sm font-bold text-slate-900 mt-1 break-all"><?php echo htmlspecialchars((string)$billing_email ?: '—'); ?></p>
                    </div>
                    <div class="rounded-xl border border-slate-200 p-4">
                        <p class="text-xs font-semibold text-slate-500">Invoice / reference</p>
                        <p class="text-sm font-bold text-slate-900 mt-1 break-all"><?php echo htmlspecialchars((string)$invoice_no); ?></p>
                    </div>
                </div>

                <div class="mt-6 rounded-xl bg-slate-50 border border-slate-200 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-slate-700">Date</p>
                        <p class="text-sm text-slate-600"><?php echo htmlspecialchars($now->format('M d, Y • h:i A')); ?></p>
                    </div>
                    <div class="flex items-center justify-between gap-3 mt-2">
                        <p class="text-sm font-semibold text-slate-700">Total paid</p>
                        <p class="text-sm font-bold text-slate-900">₱<?php echo number_format((float)$plan_price, 2); ?></p>
                    </div>
                    <div class="flex items-center justify-between gap-3 mt-2">
                        <p class="text-sm font-semibold text-slate-700">Payment intent</p>
                        <p class="text-sm text-slate-600 break-all"><?php echo htmlspecialchars((string)$payment_intent_id); ?></p>
                    </div>
                    <?php if ($status): ?>
                        <div class="flex items-center justify-between gap-3 mt-2">
                            <p class="text-sm font-semibold text-slate-700">PayMongo status</p>
                            <p class="text-sm text-slate-600"><?php echo htmlspecialchars((string)$status); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row gap-3">
                    <a href="<?php echo htmlspecialchars($success_finalizer); ?>" class="inline-flex justify-center items-center rounded-xl bg-primary hover:bg-primary/90 text-white font-bold px-5 py-3 transition-colors">
                        Go to dashboard
                    </a>
                    <a href="ProviderPurchase.php" class="inline-flex justify-center items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold px-5 py-3 transition-colors">
                        Back to plans
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

