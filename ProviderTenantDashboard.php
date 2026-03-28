<?php
session_start();
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
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
$is_owner = !empty($_SESSION['is_owner']);
$show_activated_banner = isset($_GET['activated']) && $_GET['activated'] === '1';
$user_role = (string) ($_SESSION['role'] ?? '');

if (!function_exists('provider_dashboard_slugify')) {
    function provider_dashboard_slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim((string) $value, '-');
        $value = preg_replace('/-+/', '-', (string) $value);
        return (string) $value;
    }
}
if (!function_exists('provider_dashboard_unique_slug')) {
    function provider_dashboard_unique_slug(PDO $pdo, string $tenantId, string $base): string
    {
        $candidate = provider_dashboard_slugify($base);
        if ($candidate === '' || strlen($candidate) < 3) {
            $candidate = 'clinic';
        }
        if (strlen($candidate) > 100) {
            $candidate = rtrim(substr($candidate, 0, 100), '-');
            if ($candidate === '') {
                $candidate = 'clinic';
            }
        }

        $check = $pdo->prepare("SELECT tenant_id FROM tbl_tenants WHERE clinic_slug = ? LIMIT 1");
        for ($i = 0; $i < 100; $i++) {
            $slug = $candidate;
            if ($i > 0) {
                $suffix = '-' . ($i + 1);
                $maxBase = 100 - strlen($suffix);
                $baseTrimmed = rtrim(substr($candidate, 0, max(1, $maxBase)), '-');
                $slug = ($baseTrimmed !== '' ? $baseTrimmed : 'clinic') . $suffix;
            }
            $check->execute([$slug]);
            $owner = $check->fetchColumn();
            if ($owner === false || (string) $owner === $tenantId) {
                return $slug;
            }
        }
        return $candidate . '-' . substr((string) $tenantId, 0, 6);
    }
}
if (!function_exists('provider_dashboard_normalize_slug')) {
    function provider_dashboard_normalize_slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', (string) $slug);
        $slug = trim((string) $slug, '-');
        return (string) $slug;
    }
}
if (!function_exists('provider_dashboard_payment_means_paid')) {
    function provider_dashboard_payment_means_paid(string $status): bool
    {
        $s = strtolower(trim($status));
        return in_array($s, ['paid', 'succeeded', 'complete', 'completed', 'success'], true);
    }
}
if (!function_exists('provider_dashboard_subscription_row_active')) {
    /**
     * Paid (or equivalent) and not past subscription_end (empty end = treated as active).
     */
    function provider_dashboard_subscription_row_active(array $row): bool
    {
        if (!provider_dashboard_payment_means_paid((string) ($row['payment_status'] ?? ''))) {
            return false;
        }
        $end = trim((string) ($row['subscription_end'] ?? ''));
        if ($end === '') {
            return true;
        }
        $endTs = strtotime($end . ' 23:59:59');
        return $endTs === false || $endTs >= time();
    }
}
if (!function_exists('provider_dashboard_resolve_website_urls')) {
    function provider_dashboard_resolve_website_urls(string $rawValue, string $scheme, string $host): array
    {
        $raw = trim($rawValue);
        if ($raw === '') {
            return ['', '', '', false];
        }

        $raw = str_replace('\\', '/', $raw);
        $raw = trim($raw);
        $looks_full_url = preg_match('#^https?://#i', $raw) === 1;
        $looks_domain = (strpos($raw, '.') !== false && strpos($raw, ' ') === false && strpos($raw, '/') === false);

        if ($looks_full_url) {
            $parsedHost = (string) (parse_url($raw, PHP_URL_HOST) ?? '');
            $display = $parsedHost !== '' ? $parsedHost : preg_replace('#^https?://#i', '', $raw);
            return [$raw, $raw, $display, true];
        }
        if ($looks_domain) {
            $url = $scheme . '://' . $raw;
            return [$url, $url . '/AdminDashboard.php', $raw, true];
        }

        $slug = provider_dashboard_normalize_slug($raw);
        if ($slug === '') {
            return ['', '', '', false];
        }
        $base = $scheme . '://' . $host . '/' . rawurlencode($slug);
        return [$base, $base . '/AdminDashboard.php', $host . '/' . $slug, true];
    }
}

if (!function_exists('provider_dashboard_tenant_has_billing_assets')) {
    function provider_dashboard_tenant_has_billing_assets(PDO $pdo, string $tid): bool
    {
        $tid = trim($tid);
        if ($tid === '') {
            return false;
        }
        try {
            $s = $pdo->prepare('SELECT 1 FROM tbl_tenant_subscriptions WHERE tenant_id = ? LIMIT 1');
            $s->execute([$tid]);
            if ($s->fetchColumn()) {
                return true;
            }
        } catch (Throwable $e) {
        }
        try {
            $s = $pdo->prepare("SELECT TRIM(COALESCE(clinic_slug, '')) AS s FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
            $s->execute([$tid]);
            $slug = $s->fetchColumn();
            return $slug !== false && trim((string) $slug) !== '';
        } catch (Throwable $e) {
            return false;
        }
    }
}

/**
 * Tenant that actually has billing/website data for this login — does not depend on session tenant_id alone.
 * Matches subscription rows linked via tbl_tenants.owner_user_id OR tbl_users.tenant_id.
 */
if (!function_exists('provider_dashboard_resolve_tenant_id_for_user')) {
    function provider_dashboard_resolve_tenant_id_for_user(PDO $pdo, string $user_id, string $session_tid): string
    {
        $session_tid = trim($session_tid);
        $user_id = trim($user_id);
        if ($user_id === '') {
            return $session_tid;
        }
        $best_tid = '';
        $best_sub_id = -1;
        try {
            $st = $pdo->prepare('
                SELECT ts.tenant_id, ts.id AS sid
                FROM tbl_tenant_subscriptions ts
                INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id AND t.owner_user_id = ?
                ORDER BY ts.id DESC
                LIMIT 1
            ');
            $st->execute([$user_id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($r) && isset($r['sid'], $r['tenant_id'])) {
                $sid = (int) $r['sid'];
                if ($sid > $best_sub_id) {
                    $best_sub_id = $sid;
                    $best_tid = trim((string) $r['tenant_id']);
                }
            }
        } catch (Throwable $e) {
        }
        try {
            $st = $pdo->prepare('
                SELECT ts.tenant_id, ts.id AS sid
                FROM tbl_tenant_subscriptions ts
                INNER JOIN tbl_users u ON u.user_id = ? AND u.tenant_id = ts.tenant_id
                ORDER BY ts.id DESC
                LIMIT 1
            ');
            $st->execute([$user_id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($r) && isset($r['sid'], $r['tenant_id'])) {
                $sid = (int) $r['sid'];
                if ($sid > $best_sub_id) {
                    $best_sub_id = $sid;
                    $best_tid = trim((string) $r['tenant_id']);
                }
            }
        } catch (Throwable $e) {
        }
        if ($best_tid !== '') {
            return $best_tid;
        }

        try {
            $st = $pdo->prepare("
                SELECT t.tenant_id
                FROM tbl_tenants t
                WHERE t.owner_user_id = ?
                  AND TRIM(COALESCE(t.clinic_slug, '')) <> ''
                ORDER BY t.tenant_id DESC
                LIMIT 1
            ");
            $st->execute([$user_id]);
            $slugTenant = $st->fetchColumn();
            if ($slugTenant !== false && trim((string) $slugTenant) !== '') {
                return trim((string) $slugTenant);
            }
        } catch (Throwable $e) {
        }

        try {
            $st = $pdo->prepare('SELECT tenant_id FROM tbl_tenants WHERE owner_user_id = ? ORDER BY tenant_id DESC LIMIT 1');
            $st->execute([$user_id]);
            $ownerRow = $st->fetchColumn();
            if ($ownerRow !== false && trim((string) $ownerRow) !== '') {
                return trim((string) $ownerRow);
            }
        } catch (Throwable $e) {
        }

        try {
            $st = $pdo->prepare('SELECT tenant_id FROM tbl_users WHERE user_id = ? LIMIT 1');
            $st->execute([$user_id]);
            $ut = $st->fetchColumn();
            if ($ut !== false && trim((string) $ut) !== '') {
                return trim((string) $ut);
            }
        } catch (Throwable $e) {
        }

        return $session_tid;
    }
}

// Canonical tenant_id: DB truth from subscriptions / ownership, then align session + tbl_users.
$session_tid = trim((string) $_SESSION['tenant_id']);
$tenant_id = provider_dashboard_resolve_tenant_id_for_user($pdo, $user_id, $session_tid);

if ($tenant_id !== $session_tid) {
    $_SESSION['tenant_id'] = $tenant_id;
    try {
        $repairStmt = $pdo->prepare('UPDATE tbl_users SET tenant_id = ? WHERE user_id = ?');
        $repairStmt->execute([$tenant_id, $user_id]);
    } catch (Throwable $e) {
    }
}

if ($user_role === 'tenant_owner') {
    try {
        $stmt = $pdo->prepare('SELECT tenant_id FROM tbl_tenants WHERE owner_user_id = ? ORDER BY tenant_id DESC LIMIT 1');
        $stmt->execute([$user_id]);
        $ownerTenantId = $stmt->fetchColumn();
        $ownerTenantId = ($ownerTenantId !== false) ? trim((string) $ownerTenantId) : '';

        if ($ownerTenantId !== '' && $ownerTenantId !== $tenant_id) {
            $current_assets = provider_dashboard_tenant_has_billing_assets($pdo, $tenant_id);
            $owner_assets = provider_dashboard_tenant_has_billing_assets($pdo, $ownerTenantId);
            if (!$current_assets && $owner_assets) {
                $tenant_id = $ownerTenantId;
                $_SESSION['tenant_id'] = $tenant_id;
                try {
                    $repairStmt = $pdo->prepare('UPDATE tbl_users SET tenant_id = ? WHERE user_id = ?');
                    $repairStmt->execute([$tenant_id, $user_id]);
                } catch (Throwable $e) {
                }
            }
        }
    } catch (Throwable $e) {
    }
}

if (!provider_dashboard_tenant_has_billing_assets($pdo, $tenant_id)) {
    $user_row_tid = '';
    try {
        $uStmt = $pdo->prepare('SELECT tenant_id FROM tbl_users WHERE user_id = ? LIMIT 1');
        $uStmt->execute([$user_id]);
        $c = $uStmt->fetchColumn();
        $user_row_tid = ($c !== false) ? trim((string) $c) : '';
    } catch (Throwable $e) {
    }
    foreach (array_unique(array_filter([$session_tid, $user_row_tid])) as $alt_tid) {
        if ($alt_tid === '' || $alt_tid === $tenant_id) {
            continue;
        }
        if (provider_dashboard_tenant_has_billing_assets($pdo, $alt_tid)) {
            $tenant_id = $alt_tid;
            $_SESSION['tenant_id'] = $tenant_id;
            try {
                $repairStmt = $pdo->prepare('UPDATE tbl_users SET tenant_id = ? WHERE user_id = ?');
                $repairStmt->execute([$tenant_id, $user_id]);
            } catch (Throwable $e) {
            }
            break;
        }
    }
}

try {
    $ownChk = $pdo->prepare('SELECT owner_user_id FROM tbl_tenants WHERE tenant_id = ? LIMIT 1');
    $ownChk->execute([(string) $tenant_id]);
    $resolvedOwner = $ownChk->fetchColumn();
    $_SESSION['is_owner'] = ($resolvedOwner !== false && (string) $resolvedOwner === (string) $user_id);
} catch (Throwable $e) {
}
$is_owner = !empty($_SESSION['is_owner']);

// Dashboard: session → canonical tenant_id → DB only (tbl_tenants + tbl_tenant_subscriptions + tbl_subscription_plans).
// No reliance on payment/receipt session keys. Optional POST, then fresh tenant row when settings save.
$settings_saved = false;
$settings_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($is_owner) {
            $cn = trim((string) ($_POST['clinic_name'] ?? ''));
            $ce = trim((string) ($_POST['clinic_email'] ?? ''));
            $cp = trim((string) ($_POST['clinic_phone'] ?? ''));
            $ca = trim((string) ($_POST['clinic_address'] ?? ''));
            if ($cn !== '') {
                $stmt = $pdo->prepare("UPDATE tbl_tenants SET clinic_name = ?, contact_email = ?, contact_phone = ?, clinic_address = ? WHERE tenant_id = ?");
                $stmt->execute([$cn, $ce, $cp, $ca, (string) $tenant_id]);
            }
        }
        $full_name = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        if ($full_name !== '' && $email !== '') {
            $stmt = $pdo->prepare("UPDATE tbl_users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
            $stmt->execute([$full_name, $email, $phone, (string) $user_id]);
            $_SESSION['email'] = $email;
            $_SESSION['full_name'] = $full_name;
        }
        $settings_saved = true;
    } catch (Throwable $e) {
        $settings_error = 'Could not save settings. Please try again.';
    }
}

$tenant = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.tenant_id, t.clinic_name, t.clinic_slug, t.contact_email, t.contact_phone, t.clinic_address, t.subscription_status,
               u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
        FROM tbl_tenants t
        LEFT JOIN tbl_users u ON t.owner_user_id = u.user_id
        WHERE t.tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $tenant = [];
}

// Fresh tenant row after save (clinic_slug / subscription_status may have changed elsewhere).
if ($settings_saved) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.tenant_id, t.clinic_name, t.clinic_slug, t.contact_email, t.contact_phone, t.clinic_address, t.subscription_status,
                   u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
            FROM tbl_tenants t
            LEFT JOIN tbl_users u ON t.owner_user_id = u.user_id
            WHERE t.tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenant_id]);
        $refetched = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($refetched) && $refetched !== []) {
            $tenant = $refetched;
        }
    } catch (Throwable $e) {
        // Keep prior $tenant.
    }
}

if (empty($tenant)) {
    $tenant = [
        'tenant_id' => (string) $tenant_id,
        'clinic_name' => '',
        'clinic_slug' => '',
        'contact_email' => '',
        'contact_phone' => '',
        'clinic_address' => '',
        'subscription_status' => '',
    ];
}

$current_user = [];
try {
    $stmt = $pdo->prepare("SELECT full_name, email, phone FROM tbl_users WHERE user_id = ? LIMIT 1");
    $stmt->execute([(string) $user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $current_user = [];
}

$sub = [];
$subscription_state = 'none';
$is_subscription_active = false;

// Latest subscription row for this tenant (any payment_status).
$latest_subscription_row = null;
try {
    $latestStmt = $pdo->prepare("
        SELECT ts.id, ts.plan_id, ts.subscription_start, ts.subscription_end, ts.payment_status, ts.amount_paid,
               p.plan_name, p.plan_slug, p.billing_cycle, p.price
        FROM tbl_tenant_subscriptions ts
        LEFT JOIN tbl_subscription_plans p ON p.plan_id = ts.plan_id
        WHERE ts.tenant_id = ?
        ORDER BY ts.id DESC
        LIMIT 1
    ");
    $latestStmt->execute([(string) $tenant_id]);
    $latest_subscription_row = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $latest_subscription_row = null;
}
if ($latest_subscription_row === null) {
    try {
        $bare = $pdo->prepare('
            SELECT id, plan_id, subscription_start, subscription_end, payment_status, amount_paid
            FROM tbl_tenant_subscriptions
            WHERE tenant_id = ?
            ORDER BY id DESC
            LIMIT 1
        ');
        $bare->execute([(string) $tenant_id]);
        $latest_subscription_row = $bare->fetch(PDO::FETCH_ASSOC) ?: null;
        if (is_array($latest_subscription_row) && !empty($latest_subscription_row['plan_id'])) {
            $pStmt = $pdo->prepare('SELECT plan_name, plan_slug, billing_cycle, price FROM tbl_subscription_plans WHERE plan_id = ? LIMIT 1');
            $pStmt->execute([(int) $latest_subscription_row['plan_id']]);
            $prow = $pStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($prow)) {
                $latest_subscription_row = array_merge($latest_subscription_row, $prow);
            }
        }
    } catch (Throwable $e) {
        $latest_subscription_row = null;
    }
}

// Current period: paid (or gateway success) and not past subscription_end (PHP end-date check avoids DB/session timezone drift).
$active_subscription_row = null;
try {
    $activeStmt = $pdo->prepare("
        SELECT ts.id, ts.plan_id, ts.subscription_start, ts.subscription_end, ts.payment_status, ts.amount_paid,
               p.plan_name, p.plan_slug, p.billing_cycle, p.price
        FROM tbl_tenant_subscriptions ts
        LEFT JOIN tbl_subscription_plans p ON p.plan_id = ts.plan_id
        WHERE ts.tenant_id = ?
          AND LOWER(TRIM(ts.payment_status)) IN ('paid', 'succeeded', 'complete', 'completed', 'success')
        ORDER BY ts.id DESC
        LIMIT 1
    ");
    $activeStmt->execute([(string) $tenant_id]);
    $candidate = $activeStmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($candidate) && provider_dashboard_subscription_row_active($candidate)) {
        $active_subscription_row = $candidate;
    }
} catch (Throwable $e) {
    $active_subscription_row = null;
}

if ($active_subscription_row === null && $latest_subscription_row !== null && provider_dashboard_subscription_row_active($latest_subscription_row)) {
    $active_subscription_row = $latest_subscription_row;
}

$tenant_account_active = strtolower(trim((string) ($tenant['subscription_status'] ?? ''))) === 'active';
if ($active_subscription_row === null && $tenant_account_active && $latest_subscription_row !== null && provider_dashboard_payment_means_paid((string) ($latest_subscription_row['payment_status'] ?? ''))) {
    $end = trim((string) ($latest_subscription_row['subscription_end'] ?? ''));
    $endTs = $end !== '' ? strtotime($end . ' 23:59:59') : false;
    if ($end === '' || ($endTs !== false && $endTs >= time())) {
        $active_subscription_row = $latest_subscription_row;
    }
}

$dashboard_subscription = $active_subscription_row ?? $latest_subscription_row;
$is_subscription_active = $active_subscription_row !== null;
$sub = $active_subscription_row ?? ($latest_subscription_row ?: []);

if ($is_subscription_active) {
    $subscription_state = 'active';
} elseif ($latest_subscription_row !== null) {
    $ps = strtolower(trim((string) ($latest_subscription_row['payment_status'] ?? '')));
    $end = trim((string) ($latest_subscription_row['subscription_end'] ?? ''));
    $endTs = $end !== '' ? strtotime($end . ' 23:59:59') : false;
    if (provider_dashboard_payment_means_paid((string) ($latest_subscription_row['payment_status'] ?? '')) && $endTs !== false && $endTs < time()) {
        $subscription_state = 'expired';
    } elseif ($ps === 'pending') {
        $subscription_state = 'inactive';
    } else {
        $subscription_state = 'inactive';
    }
} else {
    $subscription_state = 'none';
}

$clinic_name = trim((string) ($tenant['clinic_name'] ?? ''));
$raw_website_value = trim((string) ($tenant['clinic_slug'] ?? ''));
$clinic_slug = provider_dashboard_normalize_slug($raw_website_value);
if ($clinic_name === '') {
    $clinic_name = 'My Clinic';
}

// Canonical path segment for this tenant (tbl_tenants.clinic_slug).
if ($clinic_slug !== '') {
    $tenant['clinic_slug'] = $clinic_slug;
}

if ($is_owner && $is_subscription_active && $clinic_slug === '') {
    try {
        $slug_seed = $clinic_name !== '' ? $clinic_name : ('clinic-' . preg_replace('/[^a-z0-9]/i', '', (string) $tenant_id));
        $new_slug = provider_dashboard_unique_slug($pdo, (string) $tenant_id, $slug_seed);
        if ($new_slug !== '') {
            $slugUpdate = $pdo->prepare("UPDATE tbl_tenants SET clinic_slug = ?, subscription_status = 'active' WHERE tenant_id = ?");
            $slugUpdate->execute([$new_slug, (string) $tenant_id]);
            $clinic_slug = $new_slug;
            $tenant['clinic_slug'] = $new_slug;
            $tenant['subscription_status'] = 'active';
        }
    } catch (Throwable $e) {
        // Do not block dashboard rendering.
    }
}

$dash_sub = is_array($dashboard_subscription) ? $dashboard_subscription : [];
$db_plan_name = trim((string) ($dash_sub['plan_name'] ?? ''));
if ($db_plan_name === '') {
    $slugLabel = trim((string) ($dash_sub['plan_slug'] ?? ''));
    if ($slugLabel !== '') {
        $db_plan_name = ucwords(str_replace(['-', '_'], ' ', $slugLabel));
    }
}
$plan_name_raw = $db_plan_name !== '' ? $db_plan_name : ($sub['plan_name'] ?? null);
if ($plan_name_raw === null || trim((string) $plan_name_raw) === '') {
    $subSlug = trim((string) ($sub['plan_slug'] ?? ''));
    if ($subSlug !== '') {
        $plan_name_raw = ucwords(str_replace(['-', '_'], ' ', $subSlug));
    }
}
$plan_name = is_string($plan_name_raw) && trim($plan_name_raw) !== ''
    ? trim((string) $plan_name_raw)
    : ($is_subscription_active ? 'Active Plan' : 'No Active Subscription');
$renewal_end = trim((string) ($dash_sub['subscription_end'] ?? ($sub['subscription_end'] ?? '')));
$renewal_ts = $renewal_end !== '' ? strtotime($renewal_end) : false;
$renewal_date = ($renewal_ts !== false) ? date('M j, Y', $renewal_ts) : '—';

$billing_cycle_raw = strtolower(trim((string) ($dash_sub['billing_cycle'] ?? '')));
if ($billing_cycle_raw === 'monthly') {
    $plan_billing_cycle_label = 'Monthly billing';
} elseif ($billing_cycle_raw === 'yearly') {
    $plan_billing_cycle_label = 'Yearly billing';
} else {
    $plan_billing_cycle_label = '';
}

$period_start_raw = trim((string) ($dash_sub['subscription_start'] ?? ''));
$period_start_ts = $period_start_raw !== '' ? strtotime($period_start_raw) : false;
$period_start_display = ($period_start_ts !== false) ? date('M j, Y', $period_start_ts) : '—';
$has_subscription_row = $latest_subscription_row !== null;

$tenant_subscription_status = strtolower(trim((string) ($tenant['subscription_status'] ?? '')));
if ($tenant_subscription_status === 'active') {
    $hosting_status_label = 'Account: active';
} elseif ($tenant_subscription_status === 'inactive') {
    $hosting_status_label = 'Account: inactive';
} elseif ($tenant_subscription_status === 'suspended') {
    $hosting_status_label = 'Account: suspended';
} else {
    $hosting_status_label = 'Account: not set';
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'mydental.ct.ws'));
if ($host === '') {
    $host = 'mydental.ct.ws';
}
// Re-read slug after optional auto-create above (DB + $tenant were updated).
$raw_website_value = trim((string) ($tenant['clinic_slug'] ?? ''));
if ($raw_website_value === '') {
    $raw_website_value = $clinic_slug;
}
$clinic_slug = provider_dashboard_normalize_slug($raw_website_value);
if ($clinic_slug !== '') {
    $tenant['clinic_slug'] = $clinic_slug;
    $raw_website_value = $clinic_slug;
}
[$tenant_base_url, $admin_dashboard_url, $domain_display, $has_visible_website] = provider_dashboard_resolve_website_urls($raw_website_value, $scheme, $host);
if (!$has_visible_website && $clinic_slug !== '') {
    [$tenant_base_url, $admin_dashboard_url, $domain_display, $has_visible_website] = provider_dashboard_resolve_website_urls($clinic_slug, $scheme, $host);
}
if (!$has_visible_website && $clinic_slug !== '') {
    $tenant_base_url = $scheme . '://' . $host . '/' . rawurlencode($clinic_slug);
    $admin_dashboard_url = $tenant_base_url . '/AdminDashboard.php';
    $domain_display = $host . '/' . $clinic_slug;
    $has_visible_website = true;
}
if (!$has_visible_website) {
    $domain_display = 'No Active Website';
}

$plan_period_util_pct = 0;
if ($period_start_ts !== false && $renewal_ts !== false && $renewal_ts > $period_start_ts) {
    $now = time();
    $span = $renewal_ts - $period_start_ts;
    if ($span > 0) {
        if ($now >= $renewal_ts) {
            $plan_period_util_pct = 100;
        } elseif ($now <= $period_start_ts) {
            $plan_period_util_pct = 0;
        } else {
            $plan_period_util_pct = (int) round((($now - $period_start_ts) / $span) * 100);
        }
    }
}
$plan_period_util_pct = max(0, min(100, $plan_period_util_pct));

$display_name = trim((string) ($current_user['full_name'] ?? ''));
if ($display_name === '') {
    $display_name = trim((string) ($_SESSION['full_name'] ?? ''));
}
$welcome_name = $display_name !== '' ? $display_name : 'there';
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
$renewal_sidebar = $renewal_date !== '—' ? ('Renews ' . $renewal_date) : ($has_subscription_row ? 'See billing for dates' : 'No active renewal');
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Tenant Dashboard</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
      tailwind.config = {
        darkMode: 'class',
        theme: {
          extend: {
            colors: {
              'surface-variant': '#f1f5f9',
              'on-background': '#101922',
              'surface': '#ffffff',
              'outline-variant': '#cbd5e1',
              'primary': '#2b8beb',
              'on-surface-variant': '#404752',
              'background': '#f8fafc',
              'surface-container-low': '#edf4ff',
              'surface-container-lowest': '#ffffff',
              'tertiary': '#8e4a00',
              'tertiary-container': '#ffdcc3',
              'dental-dark': '#101922',
              'dental-blue': '#2b8beb',
            },
            fontFamily: {
              headline: ['Manrope', 'sans-serif'],
              body: ['Manrope', 'sans-serif'],
              editorial: ['Playfair Display', 'serif'],
            },
            borderRadius: {
              DEFAULT: '0.25rem',
              lg: '0.5rem',
              xl: '1rem',
              '2xl': '1.5rem',
              '3xl': '2.5rem',
              full: '9999px',
            },
          },
        },
      }
    </script>
<style>
      .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      }
      .glass-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
      }
      .editorial-word {
        text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
        letter-spacing: -0.02em;
      }
      .mesh-bg {
        background-color: #f8fafc;
        background-image:
          radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.05) 0px, transparent 50%),
          radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.02) 0px, transparent 50%);
      }
      .sidebar-bg {
        background-color: #ffffff;
      }
      .no-scrollbar::-webkit-scrollbar {
          display: none;
      }
      .active-glow {
          box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
      }
      ::-webkit-scrollbar { width: 6px; }
      ::-webkit-scrollbar-track { background: #f1f1f1; }
      ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
      ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-background font-body text-[17px] text-on-background mesh-bg min-h-screen flex leading-relaxed">
<aside class="fixed left-0 top-0 h-full w-64 z-40 sidebar-bg flex flex-col py-9 border-r border-slate-200/60" data-purpose="navigation-sidebar">
<div class="px-7 mb-11">
<h1 class="text-2xl font-extrabold text-slate-900 tracking-tight font-headline flex items-center gap-2.5">
<span class="w-9 h-9 bg-primary rounded-lg flex items-center justify-center shadow-lg shadow-primary/30 shrink-0">
<span class="material-symbols-outlined text-white text-xl">select_check_box</span>
</span>
<span class="leading-tight">MyDental</span>
</h1>
<p class="text-primary font-bold text-[11px] tracking-[0.2em] uppercase mt-2.5 opacity-80">Provider Console</p>
</div>
<nav class="flex-1 space-y-1 overflow-y-auto no-scrollbar">
<div class="relative px-3">
<a class="flex items-center gap-3 px-4 py-3.5 bg-primary/10 text-primary rounded-xl transition-all duration-200 active-glow" data-purpose="nav-item" href="#">
<span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">dashboard</span>
<span class="font-headline text-[15px] font-bold tracking-tight">Dashboard</span>
</a>
<div class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-7 bg-primary rounded-r-full"></div>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3.5 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" data-purpose="nav-item" href="#">
<span class="material-symbols-outlined text-2xl">group</span>
<span class="font-headline text-[15px] font-medium tracking-tight">Users</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3.5 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" data-purpose="nav-item" href="#">
<span class="material-symbols-outlined text-2xl">payments</span>
<span class="font-headline text-[15px] font-medium tracking-tight">Subscription &amp; Billing</span>
</a>
</div>
<div class="px-3">
<a class="flex items-center gap-3 px-4 py-3.5 text-slate-600 hover:text-slate-900 transition-colors duration-200 hover:bg-slate-50 rounded-xl" data-purpose="nav-item" href="#">
<span class="material-symbols-outlined text-2xl">settings</span>
<span class="font-headline text-[15px] font-medium tracking-tight">Settings</span>
</a>
</div>
</nav>
<div class="px-3 pb-4">
<a class="flex items-center gap-3 px-4 py-3.5 text-rose-600 hover:text-rose-700 transition-colors duration-200 hover:bg-rose-50 rounded-xl" href="ProviderLogout.php">
<span class="material-symbols-outlined text-2xl">logout</span>
<span class="font-headline text-[15px] font-bold tracking-tight">Logout</span>
</a>
</div>
<div class="px-4 mt-4">
<div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 shadow-sm">
<div class="flex items-center gap-3 mb-4">
<div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center text-primary text-sm font-bold shrink-0"><?php echo htmlspecialchars($avatar_initials); ?></div>
<div class="min-w-0 flex-1">
<p class="text-slate-900 text-sm font-bold truncate"><?php echo htmlspecialchars($plan_name); ?></p>
<p class="text-slate-500 text-xs truncate"><?php echo htmlspecialchars($renewal_sidebar); ?></p>
</div>
</div>
<a class="block w-full text-center py-3 bg-white border border-slate-200 hover:border-primary/30 text-slate-700 text-sm font-bold rounded-xl transition-all shadow-sm" href="ProviderContact.php">Support Portal</a>
</div>
</div>
</aside>
<main class="flex-1 flex flex-col min-w-0 ml-64">
<header class="flex justify-between items-center w-full px-7 lg:px-10 sticky top-0 z-40 bg-white/80 backdrop-blur-xl h-[4.25rem] border-b border-slate-200/50" data-purpose="top-header">
<div class="flex flex-wrap items-center gap-4 lg:gap-7">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full <?php echo $has_visible_website ? 'bg-green-500 animate-pulse' : 'bg-amber-500'; ?>"></span>
<span class="font-headline text-xs font-black uppercase tracking-[0.18em] text-primary">Website: <?php echo $has_visible_website ? 'Online' : 'Not published'; ?></span>
</div>
<div class="hidden sm:block h-4 w-px bg-slate-200"></div>
<span class="font-headline text-xs font-black uppercase tracking-[0.18em] text-slate-400 truncate max-w-[220px] lg:max-w-none">Plan: <?php echo htmlspecialchars($plan_name); ?></span>
</div>
<div class="flex items-center gap-4">
<div class="hidden sm:flex items-center gap-5 text-on-surface-variant/60">
<button type="button" class="material-symbols-outlined text-[1.65rem] hover:text-primary transition-colors bg-transparent border-0 cursor-pointer p-0" aria-label="Notifications">notifications</button>
<button type="button" class="material-symbols-outlined text-[1.65rem] hover:text-primary transition-colors bg-transparent border-0 cursor-pointer p-0" aria-label="Help">help_outline</button>
</div>
<div class="h-10 w-10 rounded-full overflow-hidden border-2 border-primary/20 bg-primary/10 flex items-center justify-center text-primary text-sm font-black shrink-0" aria-hidden="true"><?php echo htmlspecialchars($avatar_initials); ?></div>
</div>
</header>
<div class="p-7 lg:p-10 max-w-7xl mx-auto w-full space-y-9 flex-1">
<section class="flex flex-col gap-3.5">
<div class="text-primary font-bold text-sm uppercase flex items-center gap-3 tracking-[0.25em]">
<span class="w-10 h-px bg-primary"></span> Tenant dashboard
            </div>
<h2 class="font-headline text-4xl sm:text-[2.5rem] font-extrabold tracking-tight text-on-background">
                Clinic <span class="font-editorial italic font-normal text-primary editorial-word">Overview</span>
</h2>
<p class="font-body text-lg text-on-surface-variant max-w-2xl leading-relaxed">Welcome back, <?php echo htmlspecialchars($welcome_name); ?>. Open clinic management, review your plan and website, and keep account details up to date.</p>
</section>
<section class="glass-card rounded-2xl p-7 border border-slate-200/80 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-6" data-purpose="primary-action-card">
<div class="space-y-2">
<h2 class="font-headline text-2xl font-extrabold tracking-tight text-on-background">Access clinic management</h2>
<p class="text-on-surface-variant text-base max-w-xl leading-relaxed">Manage patients, appointments, and records from your clinic admin dashboard.</p>
</div>
<a
  class="inline-flex items-center justify-center shrink-0 bg-primary text-white px-7 py-3.5 rounded-xl font-headline font-bold text-base hover:bg-blue-600 transition-all shadow-md shadow-primary/25 text-center"
  id="open-dashboard-btn"
  href="<?php echo $admin_dashboard_url ? htmlspecialchars($admin_dashboard_url, ENT_QUOTES, 'UTF-8') : '#'; ?>"
  <?php if ($has_visible_website && $admin_dashboard_url): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
>
  <?php echo $has_visible_website ? 'Open Clinic Management Dashboard' : 'No Active Website'; ?>
</a>
</section>
<div class="space-y-7">
<div class="grid grid-cols-1 md:grid-cols-3 gap-7" data-purpose="overview-stats">
<div class="glass-card p-7 rounded-2xl flex flex-col justify-between hover:shadow-lg hover:shadow-primary/5 transition-all duration-300 min-h-[220px]">
<div class="flex justify-between items-start gap-3">
<div class="min-w-0">
<p class="text-on-surface-variant font-bold text-sm uppercase tracking-wider mb-2">Current Plan</p>
<h3 class="font-headline text-2xl font-extrabold text-primary leading-snug break-words"><?php echo htmlspecialchars($plan_name); ?></h3>
</div>
<?php if ($is_subscription_active): ?>
<span class="shrink-0 bg-primary/10 text-primary px-3 py-1 rounded-full text-sm font-black uppercase tracking-wide">Active</span>
<?php elseif ($subscription_state === 'expired'): ?>
<span class="shrink-0 bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-sm font-black uppercase tracking-wide">Expired</span>
<?php elseif ($subscription_state === 'inactive'): ?>
<span class="shrink-0 bg-slate-200 text-slate-800 px-3 py-1 rounded-full text-sm font-black uppercase tracking-wide">Inactive</span>
<?php else: ?>
<span class="shrink-0 bg-slate-100 text-slate-600 px-3 py-1 rounded-full text-sm font-black uppercase tracking-wide">None</span>
<?php endif; ?>
</div>
<div class="space-y-4 mt-6">
<?php if ($plan_billing_cycle_label !== ''): ?>
<p class="text-on-surface-variant/80 text-base font-medium"><?php echo htmlspecialchars($plan_billing_cycle_label); ?></p>
<?php endif; ?>
<?php if ($has_subscription_row && $period_start_ts !== false && $renewal_ts !== false): ?>
<div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
<div class="bg-primary h-full rounded-full shadow-[0_0_10px_rgba(43,139,235,0.35)] transition-all" style="width: <?php echo (int) $plan_period_util_pct; ?>%;"></div>
</div>
<div class="flex flex-col sm:flex-row sm:justify-between gap-1 text-sm font-bold uppercase tracking-wide text-on-surface-variant/70">
<span>Renewal: <?php echo htmlspecialchars($renewal_date); ?></span>
<span>Period: <?php echo (int) $plan_period_util_pct; ?>%</span>
</div>
<?php elseif ($has_subscription_row): ?>
<p class="text-on-surface-variant text-base">Current period: <?php echo htmlspecialchars($period_start_display); ?> — <?php echo htmlspecialchars($renewal_date); ?></p>
<?php else: ?>
<p class="text-on-surface-variant/80 text-base">No subscription record in the database yet.</p>
<?php endif; ?>
</div>
</div>
<div class="glass-card p-7 rounded-2xl flex flex-col justify-between hover:shadow-lg hover:shadow-primary/5 transition-all duration-300 min-h-[220px]">
<div>
<p class="text-on-surface-variant font-bold text-sm uppercase tracking-wider mb-2">Domain &amp; Hosting</p>
<?php if ($has_visible_website): ?>
<h3 class="font-headline text-xl font-extrabold tracking-tight break-all"><?php echo htmlspecialchars($domain_display); ?></h3>
<a class="inline-flex items-center gap-2 text-primary text-sm font-black uppercase tracking-wide mt-4 hover:gap-2.5 transition-all" href="<?php echo htmlspecialchars($tenant_base_url . '/', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                Visit Website
                                <span class="material-symbols-outlined text-lg">arrow_forward</span>
</a>
<?php else: ?>
<h3 class="font-headline text-xl font-extrabold tracking-tight text-on-background"><?php echo htmlspecialchars($domain_display); ?></h3>
<?php endif; ?>
<p class="text-base font-semibold text-on-surface-variant/80 mt-3"><?php echo htmlspecialchars($hosting_status_label); ?></p>
</div>
<div class="flex items-center gap-2.5 mt-5 pt-5 border-t border-slate-100">
<span class="material-symbols-outlined <?php echo $has_visible_website ? 'text-green-500' : 'text-amber-500'; ?> text-2xl">verified_user</span>
<span class="text-sm font-bold text-on-surface-variant/70 uppercase tracking-wide"><?php echo $has_visible_website ? 'Website published' : 'Website not yet published'; ?></span>
</div>
</div>
<div class="glass-card p-7 rounded-2xl hover:shadow-lg hover:shadow-primary/5 transition-all duration-300 flex flex-col min-h-[220px]">
<h4 class="font-headline font-extrabold text-lg mb-4">Website <span class="text-primary italic font-editorial">Control</span></h4>
<div class="flex flex-wrap gap-2.5 mb-4">
<button type="button" class="bg-primary/10 text-primary hover:bg-primary hover:text-white px-4 py-3 rounded-xl text-sm font-black uppercase tracking-wide transition-colors">Publish</button>
<button type="button" class="bg-slate-100 text-slate-600 hover:bg-slate-200 px-4 py-3 rounded-xl text-sm font-black uppercase tracking-wide transition-colors">Unpublish</button>
</div>
<a
  class="inline-flex items-center gap-2 text-primary text-sm font-black uppercase tracking-wide mt-auto hover:gap-2.5 transition-all"
  href="<?php echo $has_visible_website && $tenant_base_url ? htmlspecialchars($tenant_base_url . '/', ENT_QUOTES, 'UTF-8') : '#'; ?>"
  <?php if ($has_visible_website && $tenant_base_url): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
>
                <?php echo $has_visible_website ? 'View Live Website' : 'Website Link Unavailable'; ?>
                <span class="material-symbols-outlined text-lg">open_in_new</span>
</a>
</div>
</div>
<div class="glass-card rounded-2xl p-7 border border-slate-200/80">
<h4 class="font-headline font-extrabold text-lg mb-5">Infrastructure <span class="text-primary italic font-editorial">Status</span></h4>
<div class="space-y-5">
<div class="flex items-center justify-between gap-3">
<span class="text-base font-bold uppercase tracking-wide text-on-surface-variant/70">Database</span>
<span class="text-sm font-black text-green-500 flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                                    Connected
                                </span>
</div>
<div class="flex items-center justify-between gap-3">
<span class="text-base font-bold uppercase tracking-wide text-on-surface-variant/70">Clinic portal</span>
<span class="text-sm font-black <?php echo $has_visible_website ? 'text-green-500' : 'text-amber-600'; ?> flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full <?php echo $has_visible_website ? 'bg-green-500 animate-pulse' : 'bg-amber-500'; ?>"></span>
                                    <?php echo $has_visible_website ? 'Ready' : 'Unavailable'; ?>
                                </span>
</div>
<div class="flex items-center justify-between gap-3">
<span class="text-base font-bold uppercase tracking-wide text-on-surface-variant/70">Subscription</span>
<span class="text-sm font-black <?php echo $is_subscription_active ? 'text-green-500' : 'text-slate-500'; ?> flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full <?php echo $is_subscription_active ? 'bg-green-500' : 'bg-slate-400'; ?>"></span>
                                    <?php echo $is_subscription_active ? 'Active' : ucfirst($subscription_state); ?>
                                </span>
</div>
</div>
</div>
<div class="glass-card p-7 sm:p-9 rounded-2xl border border-slate-200/80 max-w-4xl">
<?php if ($settings_saved): ?>
<div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-base font-medium">Settings saved successfully.</div>
<?php endif; ?>
<?php if ($show_activated_banner): ?>
<div class="mb-6 p-4 bg-surface-container-low border border-primary/20 text-primary rounded-xl text-base font-headline font-bold">Subscription activated. Your clinic website is now live and ready to manage.</div>
<?php endif; ?>
<?php if ($settings_error): ?>
<div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-base"><?php echo htmlspecialchars($settings_error); ?></div>
<?php endif; ?>
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-7">
<div>
<h3 class="text-2xl font-headline font-extrabold text-on-background">Account Settings</h3>
<p class="text-on-surface-variant text-base mt-1">Clinic details (tbl_tenants) and your account (tbl_users).</p>
</div>
<button class="px-6 py-3 bg-primary text-white rounded-xl font-headline text-base font-bold hover:bg-blue-600 transition-all shadow-md shadow-primary/20 shrink-0" form="settings-form" type="submit">Save Changes</button>
</div>
<form class="space-y-7" method="post" action="" data-purpose="account-form" id="settings-form">
<?php if ($is_owner): ?>
<div class="border-b border-slate-200 pb-7">
<h4 class="text-sm font-black text-on-surface-variant uppercase tracking-[0.2em] mb-4">Clinic details</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="md:col-span-2">
<label class="text-base font-semibold text-slate-700 font-headline" for="clinic_name">Clinic Name</label>
<input class="mt-2 w-full px-4 py-3 text-[17px] rounded-xl border-slate-200 focus:border-primary focus:ring-primary transition-all" id="clinic_name" name="clinic_name" placeholder="Clinic name" type="text" value="<?php echo htmlspecialchars($tenant['clinic_name'] ?? ''); ?>"/>
</div>
<div>
<label class="text-base font-semibold text-slate-700 font-headline" for="clinic_email">Clinic Email</label>
<input class="mt-2 w-full px-4 py-3 text-[17px] rounded-xl border-slate-200 focus:border-primary focus:ring-primary transition-all" id="clinic_email" name="clinic_email" placeholder="Clinic email" type="email" value="<?php echo htmlspecialchars($tenant['contact_email'] ?? ''); ?>"/>
</div>
<div>
<label class="text-base font-semibold text-slate-700 font-headline" for="clinic_phone">Clinic Phone</label>
<input class="mt-2 w-full px-4 py-3 text-[17px] rounded-xl border-slate-200 focus:border-primary focus:ring-primary transition-all" id="clinic_phone" name="clinic_phone" placeholder="Clinic phone" type="tel" value="<?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?>"/>
</div>
<div class="md:col-span-2">
<label class="text-base font-semibold text-slate-700 font-headline" for="clinic_address">Clinic Address</label>
<textarea class="mt-2 w-full px-4 py-3 text-[17px] rounded-xl border-slate-200 focus:border-primary focus:ring-primary transition-all" id="clinic_address" name="clinic_address" placeholder="Address" rows="2"><?php echo htmlspecialchars($tenant['clinic_address'] ?? ''); ?></textarea>
</div>
</div>
</div>
<?php endif; ?>
<div>
<h4 class="text-sm font-black text-on-surface-variant uppercase tracking-[0.2em] mb-4">Your account</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label class="text-base font-semibold text-slate-700 font-headline" for="full-name">Full Name</label>
<input class="mt-2 w-full px-4 py-3 text-[17px] rounded-xl border-slate-200 focus:border-primary focus:ring-primary transition-all" id="full-name" name="full_name" placeholder="Full name" type="text" value="<?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?>"/>
</div>
<div>
<label class="text-base font-semibold text-slate-700 font-headline" for="email">Email Address</label>
<input class="mt-2 w-full px-4 py-3 text-[17px] rounded-xl border-slate-200 focus:border-primary focus:ring-primary transition-all" id="email" name="email" placeholder="Email" type="email" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>"/>
</div>
<div>
<label class="text-base font-semibold text-slate-700 font-headline" for="contact">Contact Number</label>
<input class="mt-2 w-full px-4 py-3 text-[17px] rounded-xl border-slate-200 focus:border-primary focus:ring-primary transition-all" id="contact" name="phone" placeholder="Phone" type="tel" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>"/>
</div>
</div>
</div>
</form>
</div>
</div>
</div>
</main>
<script data-purpose="event-handlers">
    // Keep JS block for future UI interactions (no forced redirects here).
  </script>
</body></html>