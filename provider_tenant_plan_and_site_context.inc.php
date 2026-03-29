<?php
/**
 * Tenant subscription, plan display, and public site URL resolution.
 * Requires: $pdo (PDO), $tenant_id (string), $user_id (string), $is_owner (bool)
 * from provider_tenant_canonical_context.inc.php (load canonical first).
 */
declare(strict_types=1);

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

$sub = [];
$subscription_state = 'none';
$is_subscription_active = false;

$latest_subscription_row = null;
try {
    $latestStmt = $pdo->prepare("
        SELECT ts.id, ts.plan_id, ts.subscription_start, ts.subscription_end, ts.payment_status, ts.amount_paid,
               ts.payment_method, ts.reference_number, ts.created_at,
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
            SELECT id, plan_id, subscription_start, subscription_end, payment_status, amount_paid,
                   payment_method, reference_number, created_at
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

$active_subscription_row = null;
try {
    $activeStmt = $pdo->prepare("
        SELECT ts.id, ts.plan_id, ts.subscription_start, ts.subscription_end, ts.payment_status, ts.amount_paid,
               ts.payment_method, ts.reference_number, ts.created_at,
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

$tenant_public_site_url = ($has_visible_website && $tenant_base_url !== '') ? (rtrim($tenant_base_url, '/') . '/') : '';
$tenant_public_site_url_h = $tenant_public_site_url !== '' ? htmlspecialchars($tenant_public_site_url, ENT_QUOTES, 'UTF-8') : '';

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

$renewal_sidebar = $renewal_date !== '—' ? ('Renews ' . $renewal_date) : ($has_subscription_row ? 'See billing for dates' : 'No active renewal');

/** Payment snapshot for current dashboard subscription row (amount + paid-at from DB). */
$sub_payment_amount_display = '';
$sub_payment_date_display = '';
$sub_payment_time_display = '';
if ($has_subscription_row && is_array($dashboard_subscription)) {
    $ds = $dashboard_subscription;
    $amtRaw = $ds['amount_paid'] ?? null;
    $amt = is_numeric($amtRaw) ? (float) $amtRaw : null;
    if ($amt === null || $amt <= 0) {
        $priceFallback = $ds['price'] ?? null;
        if (is_numeric($priceFallback)) {
            $amt = (float) $priceFallback;
        }
    }
    $sub_payment_amount_display = ($amt !== null && $amt > 0)
        ? ('₱' . number_format($amt, 2, '.', ','))
        : '—';
    $createdRaw = trim((string) ($ds['created_at'] ?? ''));
    $paidTs = false;
    if ($createdRaw !== '') {
        $paidTs = strtotime($createdRaw);
    }
    if ($paidTs === false) {
        $startOnly = trim((string) ($ds['subscription_start'] ?? ''));
        if ($startOnly !== '') {
            $paidTs = strtotime($startOnly . ' 12:00:00');
        }
    }
    if ($paidTs !== false) {
        $sub_payment_date_display = date('M j, Y', $paidTs);
        $sub_payment_time_display = date('g:i A', $paidTs);
    } else {
        $sub_payment_date_display = '—';
        $sub_payment_time_display = '—';
    }
}
