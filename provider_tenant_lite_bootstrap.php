<?php
/**
 * Shared auth + sidebar context for provider tenant portal pages (Users, Subs, Settings).
 * Sets: $pdo, $tenant_id, $user_id, $avatar_initials, $plan_name, $renewal_sidebar
 */
declare(strict_types=1);

require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
require_once __DIR__ . '/db.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    exit('Database is not available.');
}

provider_require_approved_for_provider_portal();

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    header('Location: ProviderLogin.php');
    exit;
}

$tenant_id = (string) $_SESSION['tenant_id'];
$user_id = (string) $_SESSION['user_id'];

require_once __DIR__ . '/provider_tenant_canonical_context.inc.php';

$current_user = [];
try {
    $stmt = $pdo->prepare('SELECT full_name FROM tbl_users WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $current_user = $row;
    }
} catch (Throwable $e) {
    $current_user = [];
}

$display_name = trim((string) ($current_user['full_name'] ?? ($_SESSION['full_name'] ?? '')));
$avatar_initials = 'MD';
if ($display_name !== '') {
    $parts = preg_split('/\s+/', $display_name, -1, PREG_SPLIT_NO_EMPTY);
    if (is_array($parts) && $parts !== []) {
        $a = strtoupper(substr($parts[0], 0, 1));
        $b = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : strtoupper(substr($parts[0], 1, 1));
        $avatar_initials = $a . ($b !== '' ? $b : '');
        if (strlen($avatar_initials) > 2) {
            $avatar_initials = substr($avatar_initials, 0, 2);
        }
    }
}

$plan_name = 'MyDental';
$renewal_sidebar = 'Subscription details on dashboard';
try {
    $st = $pdo->prepare('
        SELECT ts.subscription_end, sp.plan_name AS plan_name, sp.plan_slug AS plan_slug
        FROM tbl_tenant_subscriptions ts
        LEFT JOIN tbl_subscription_plans sp ON sp.plan_id = ts.plan_id
        WHERE ts.tenant_id = ?
        ORDER BY ts.id DESC
        LIMIT 1
    ');
    $st->execute([$tenant_id]);
    $subRow = $st->fetch(PDO::FETCH_ASSOC);
    if (is_array($subRow)) {
        $pn = trim((string) ($subRow['plan_name'] ?? ''));
        if ($pn === '') {
            $slug = trim((string) ($subRow['plan_slug'] ?? ''));
            if ($slug !== '') {
                $pn = ucwords(str_replace(['-', '_'], ' ', $slug));
            }
        }
        if ($pn !== '') {
            $plan_name = $pn;
        }
        $end = trim((string) ($subRow['subscription_end'] ?? ''));
        if ($end !== '') {
            $ts = strtotime($end . ' 23:59:59');
            if ($ts !== false && $ts >= time()) {
                $renewal_sidebar = 'Renews ' . date('M j, Y', $ts);
            } elseif ($ts !== false) {
                $renewal_sidebar = 'Ended ' . date('M j, Y', $ts);
            }
        }
    }
} catch (Throwable $e) {
    // keep defaults
}
