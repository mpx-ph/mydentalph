<?php
session_start();
require_once __DIR__ . '/provider_auth.php';
provider_require_approved_for_provider_portal();
require_once __DIR__ . '/db.php';
require_once 'paymongo_config.php';

$payment_intent_id = $_SESSION['paymongo_payment_intent_id'] ?? null;
$client_key = $_SESSION['paymongo_client_key'] ?? null;
$plan_name = $_SESSION['paymongo_plan_name'] ?? 'Professional';
$plan_price = $_SESSION['paymongo_plan_price'] ?? 0;
$payment_method = $_SESSION['paymongo_payment_method'] ?? 'card'; // card|gcash|paymaya
$billing_email = $_SESSION['paymongo_billing_email'] ?? '';
$tenant_id = $_SESSION['paymongo_tenant_id'] ?? null;
$user_id = $_SESSION['paymongo_user_id'] ?? null;
$plan_id = $_SESSION['paymongo_plan_id'] ?? null;
$reference_number = $_SESSION['paymongo_reference_number'] ?? ($payment_intent_id ? ('PM-' . $payment_intent_id) : '');

if (
    !$payment_intent_id
    || !$client_key
    || !$tenant_id
    || !$user_id
    || !$plan_id
    || (float) $plan_price <= 0
) {
    header('Location: ProviderPurchase.php');
    exit;
}

$method_label = $payment_method === 'gcash' ? 'GCash' : ($payment_method === 'paymaya' ? 'Maya' : 'Card');
$now = new DateTime('now');
$invoice_no = 'PM-' . preg_replace('/[^A-Za-z0-9]/', '', (string)$payment_intent_id);
$invoice_no = substr($invoice_no, 0, 22);

$status = null;
$status_detail = null;
if (defined('PAYMONGO_SECRET_KEY')) {
    $secret = PAYMONGO_SECRET_KEY;
    if ($secret && strpos($secret, 'YOUR_') === false && function_exists('curl_init')) {
        $ch = curl_init('https://api.paymongo.com/v1/payment_intents/' . $payment_intent_id . '?client_key=' . urlencode($client_key));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($secret . ':')],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        $data = $res ? json_decode($res, true) : null;
        $status = $data['data']['attributes']['status'] ?? null;
        $status_detail = $data['data']['attributes']['last_payment_error']['message'] ?? null;
    }
}

if ($status === null) {
    header('Location: ProviderPurchase.php?payment=failed&reason=' . urlencode('Unable to verify payment status. Please contact support if charged.'));
    exit;
}
// If PayMongo says it failed, do not continue.
if ($status !== 'succeeded' && $status !== 'processing') {
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
                        <p class="text-sm text-slate-500 mt-2">
                            We’re finishing setup. You’ll be redirected to your dashboard shortly.
                        </p>
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
                        Continue
                    </a>
                    <a href="ProviderPurchase.php" class="inline-flex justify-center items-center rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-semibold px-5 py-3 transition-colors">
                        Back to plans
                    </a>
                </div>

                <p class="text-xs text-slate-400 mt-5">
                    If you’re not redirected automatically, click Continue.
                </p>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var nextUrl = <?php echo json_encode($success_finalizer); ?>;
            setTimeout(function () { window.location.href = nextUrl; }, 2500);
        })();
    </script>
</body>
</html>

