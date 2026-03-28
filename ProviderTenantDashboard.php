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
$show_activated_banner = isset($_GET['activated']) && $_GET['activated'] === '1';

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

require_once __DIR__ . '/provider_tenant_canonical_context.inc.php';

// Dashboard: session → canonical tenant_id → DB only (tbl_tenants + tbl_tenant_subscriptions + tbl_subscription_plans).

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
              'error': '#ba1a1a',
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
      .sidebar-glass {
        background: rgba(252, 253, 255, 0.85);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-right: 1px solid rgba(224, 233, 246, 0.5);
      }
      .editorial-shadow {
        box-shadow: 0 12px 40px -10px rgba(19, 28, 37, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.8);
      }
      .editorial-word {
        text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
        letter-spacing: -0.02em;
      }
      .mesh-bg {
        background-color: #f7f9ff;
        background-image:
          radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
          radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
          radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
      }
      .primary-glow {
        box-shadow: 0 8px 25px -5px rgba(43, 139, 235, 0.4);
      }
      .no-scrollbar::-webkit-scrollbar {
          display: none;
      }
      .active-glow {
          box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
      }
      .provider-nav-link:not(.provider-nav-link--active):hover {
        transform: translateX(4px);
      }
      @keyframes provider-page-in {
        from { opacity: 0; transform: translateY(14px); }
        to { opacity: 1; transform: translateY(0); }
      }
      .provider-page-enter {
        animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
      }
      .provider-card-lift {
        transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
      }
      .provider-card-lift:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
      }
      .dash-hero-panel {
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.92) 0%, rgba(248, 250, 252, 0.75) 45%, rgba(237, 244, 255, 0.85) 100%);
        box-shadow:
          0 0 0 1px rgba(255, 255, 255, 0.9),
          0 4px 6px -1px rgba(15, 23, 42, 0.04),
          0 25px 50px -12px rgba(43, 139, 235, 0.12);
      }
      .dash-stat-card {
        background: linear-gradient(165deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.92) 100%);
        box-shadow:
          0 0 0 1px rgba(255, 255, 255, 0.95),
          0 10px 40px -10px rgba(15, 23, 42, 0.1);
      }
      .dash-stat-card::before {
        content: '';
        position: absolute;
        inset: 0 0 auto 0;
        height: 3px;
        border-radius: 1.5rem 1.5rem 0 0;
        opacity: 0.85;
        pointer-events: none;
      }
      .dash-stat-card--plan::before {
        background: linear-gradient(90deg, #2b8beb, #60a5fa);
      }
      .dash-stat-card--domain::before {
        background: linear-gradient(90deg, #0d9488, #2dd4bf);
      }
      .dash-stat-card--actions::before {
        background: linear-gradient(90deg, #7c3aed, #a78bfa);
      }
      .dash-domain-link {
        text-decoration: none;
        color: inherit;
        border-radius: 0.875rem;
        margin: -0.25rem;
        padding: 0.5rem 0.5rem 0.5rem 0.25rem;
        transition: background-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease;
      }
      .dash-domain-link:hover {
        background: rgba(43, 139, 235, 0.08);
        box-shadow: 0 0 0 1px rgba(43, 139, 235, 0.15);
      }
      .dash-domain-link:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px #fff, 0 0 0 4px rgba(43, 139, 235, 0.45);
      }
      .dash-infra-panel {
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.95) 0%, rgba(241, 245, 249, 0.5) 100%);
        box-shadow:
          0 0 0 1px rgba(255, 255, 255, 0.9),
          0 20px 50px -15px rgba(15, 23, 42, 0.08);
      }
      .dash-infra-row {
        transition: background-color 0.2s ease;
      }
      .dash-infra-row:hover {
        background: rgba(43, 139, 235, 0.04);
      }
      ::-webkit-scrollbar { width: 6px; }
      ::-webkit-scrollbar-track { background: #f1f1f1; }
      ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
      ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="mesh-bg font-body text-sm text-on-background selection:bg-primary/10 min-h-screen">
<?php
$provider_nav_active = 'dashboard';
include __DIR__ . '/provider_tenant_sidebar.inc.php';
?>
<header class="fixed top-0 right-0 w-[calc(100%-16rem)] h-20 z-30 bg-white/80 backdrop-blur-xl border-b border-slate-200/60 shadow-sm shadow-slate-200/40 flex items-center justify-end px-8" data-purpose="top-header">
<div class="flex items-center gap-2 sm:gap-4 shrink-0">
<button type="button" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all relative border-0 bg-transparent cursor-pointer" aria-label="Notifications">
<span class="material-symbols-outlined text-on-surface-variant">notifications</span>
</button>
<button type="button" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all border-0 bg-transparent cursor-pointer" aria-label="Help">
<span class="material-symbols-outlined text-on-surface-variant">help_outline</span>
</button>
<div class="w-10 h-10 rounded-full bg-primary/15 flex items-center justify-center text-primary text-xs font-bold border-2 border-white shadow-sm shrink-0" aria-hidden="true"><?php echo htmlspecialchars($avatar_initials); ?></div>
</div>
</header>
<main class="ml-64 pt-20 min-h-screen provider-page-enter">
<div class="pt-8 px-10 pb-20 space-y-12 relative">
<div class="absolute top-32 right-8 w-[28rem] h-[28rem] bg-primary/10 rounded-full blur-[120px] -z-10 pointer-events-none" aria-hidden="true"></div>
<div class="absolute bottom-40 left-10 w-72 h-72 bg-teal-400/10 rounded-full blur-[100px] -z-10 pointer-events-none" aria-hidden="true"></div>
<section class="dash-hero-panel rounded-[2rem] border border-white/80 backdrop-blur-md p-8 sm:p-10 lg:p-12 relative overflow-hidden">
<div class="absolute top-0 right-0 w-64 h-64 bg-primary/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/3 pointer-events-none" aria-hidden="true"></div>
<div class="relative flex flex-col lg:flex-row lg:items-end justify-between gap-8">
<div class="max-w-3xl">
<p class="text-primary font-bold text-[10px] sm:text-xs uppercase tracking-[0.35em] flex items-center gap-3 mb-4"><span class="w-8 sm:w-10 h-px bg-primary/40"></span> Provider dashboard</p>
<h2 class="text-5xl sm:text-6xl font-extrabold font-headline tracking-tight text-on-background">Clinic <span class="font-editorial italic font-normal text-primary editorial-word">Overview</span></h2>
<p class="text-on-surface-variant mt-4 sm:mt-5 text-lg sm:text-xl font-medium max-w-2xl leading-relaxed">Welcome back, <span class="text-on-background font-semibold"><?php echo htmlspecialchars($welcome_name); ?></span>. Open clinic management, review your plan and website, and keep account details up to date in Settings.</p>
</div>
<div class="flex items-center gap-3 shrink-0">
<a
  class="bg-primary text-white px-8 py-3.5 rounded-2xl text-sm font-bold primary-glow inline-flex items-center gap-2.5 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all text-center ring-2 ring-primary/20 ring-offset-2 ring-offset-white/80 shadow-lg shadow-primary/20"
  id="open-dashboard-btn"
  data-purpose="primary-action-card"
  href="<?php echo $admin_dashboard_url ? htmlspecialchars($admin_dashboard_url, ENT_QUOTES, 'UTF-8') : '#'; ?>"
  <?php if ($has_visible_website && $admin_dashboard_url): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
>
<span class="material-symbols-outlined text-xl">dashboard</span>
  <?php echo $has_visible_website ? 'Open Clinic Management' : 'No Active Website'; ?>
</a>
</div>
</div>
</section>
<section class="grid grid-cols-1 md:grid-cols-3 gap-7 lg:gap-8" data-purpose="overview-stats">
<div class="dash-stat-card dash-stat-card--plan relative overflow-hidden rounded-3xl backdrop-blur-md p-7 provider-card-lift group">
<div class="flex justify-between items-start mb-5">
<div class="p-3 bg-gradient-to-br from-primary/15 to-blue-100/80 text-primary rounded-2xl shadow-inner ring-1 ring-white/60">
<span class="material-symbols-outlined text-[26px]">subscriptions</span>
</div>
<?php if ($is_subscription_active): ?>
<span class="text-[10px] font-bold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">Active</span>
<?php elseif ($subscription_state === 'expired'): ?>
<span class="text-[10px] font-extrabold text-amber-800 bg-amber-50 px-2 py-1 rounded-lg uppercase">Expired</span>
<?php elseif ($subscription_state === 'inactive'): ?>
<span class="text-[10px] font-bold text-on-surface-variant bg-surface-container-low px-2 py-1 rounded-lg uppercase">Inactive</span>
<?php else: ?>
<span class="text-[10px] font-bold text-on-surface-variant/60 bg-slate-100 px-2 py-1 rounded-lg uppercase">None</span>
<?php endif; ?>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-[0.2em] opacity-70">Current Plan</p>
<h3 class="text-2xl font-extrabold text-on-background mt-2 font-headline break-words leading-tight"><?php echo htmlspecialchars($plan_name); ?></h3>
<div class="mt-5 space-y-3">
<?php if ($plan_billing_cycle_label !== ''): ?>
<p class="text-on-surface-variant text-sm font-medium"><?php echo htmlspecialchars($plan_billing_cycle_label); ?></p>
<?php endif; ?>
<?php if ($has_subscription_row && $period_start_ts !== false && $renewal_ts !== false): ?>
<div class="w-full bg-slate-200/80 h-2.5 rounded-full overflow-hidden ring-1 ring-white/50">
<div class="bg-gradient-to-r from-primary to-sky-400 h-full rounded-full transition-all shadow-sm" style="width: <?php echo (int) $plan_period_util_pct; ?>%;"></div>
</div>
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wide">Renewal <?php echo htmlspecialchars($renewal_date); ?> · <?php echo (int) $plan_period_util_pct; ?>% period</p>
<?php elseif ($has_subscription_row): ?>
<p class="text-sm text-on-surface-variant"><?php echo htmlspecialchars($period_start_display); ?> — <?php echo htmlspecialchars($renewal_date); ?></p>
<?php else: ?>
<p class="text-sm text-on-surface-variant/70">No subscription record yet.</p>
<?php endif; ?>
</div>
</div>
<div class="dash-stat-card dash-stat-card--domain relative overflow-hidden rounded-3xl backdrop-blur-md p-7 provider-card-lift group flex flex-col">
<div class="flex justify-between items-start mb-5">
<div class="p-3 bg-gradient-to-br from-teal-400/20 to-cyan-100/90 text-teal-800 rounded-2xl shadow-inner ring-1 ring-white/60">
<span class="material-symbols-outlined text-[26px]">language</span>
</div>
<span class="text-[10px] font-bold <?php echo $has_visible_website ? 'text-teal-700 bg-teal-50' : 'text-amber-800 bg-amber-50'; ?> px-2.5 py-1 rounded-lg uppercase tracking-wider"><?php echo $has_visible_website ? 'Live' : 'Pending'; ?></span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-[0.2em] opacity-70">Domain &amp; Hosting</p>
<?php if ($tenant_public_site_url_h !== ''): ?>
<a href="<?php echo $tenant_public_site_url_h; ?>" target="_blank" rel="noopener noreferrer" class="dash-domain-link block mt-2 group/link">
<h3 class="text-xl font-extrabold text-on-background font-headline break-all leading-snug group-hover/link:text-primary transition-colors"><?php echo htmlspecialchars($domain_display); ?></h3>
<span class="inline-flex items-center gap-1 text-primary text-xs font-bold uppercase tracking-wide mt-2">Open website <span class="material-symbols-outlined text-base transition-transform group-hover/link:translate-x-0.5 group-hover/link:-translate-y-0.5">open_in_new</span></span>
</a>
<?php else: ?>
<h3 class="text-xl font-extrabold text-on-background mt-2 font-headline"><?php echo htmlspecialchars($domain_display); ?></h3>
<?php endif; ?>
<p class="text-sm text-on-surface-variant font-medium mt-3"><?php echo htmlspecialchars($hosting_status_label); ?></p>
<div class="flex items-center gap-2.5 mt-auto pt-5 border-t border-slate-200/80">
<span class="material-symbols-outlined <?php echo $has_visible_website ? 'text-emerald-500' : 'text-amber-500'; ?> text-xl">verified_user</span>
<span class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest"><?php echo $has_visible_website ? 'Published' : 'Not published'; ?></span>
</div>
</div>
<div class="dash-stat-card dash-stat-card--actions relative overflow-hidden rounded-3xl backdrop-blur-md p-7 provider-card-lift group flex flex-col">
<div class="flex justify-between items-start mb-5">
<div class="p-3 bg-gradient-to-br from-violet-400/20 to-violet-100/90 text-violet-800 rounded-2xl shadow-inner ring-1 ring-white/60">
<span class="material-symbols-outlined text-[26px]">tune</span>
</div>
<span class="text-[10px] font-bold text-violet-800/80 bg-violet-50 px-2.5 py-1 rounded-lg uppercase tracking-wider">Shortcuts</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-[0.2em] opacity-70">Website</p>
<h3 class="text-lg font-extrabold text-on-background mt-2 font-headline">Quick actions</h3>
<div class="flex flex-wrap gap-2 mt-5">
<button type="button" class="bg-primary/12 text-primary hover:bg-primary hover:text-white px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wide transition-all ring-1 ring-primary/10">Publish</button>
<button type="button" class="bg-slate-100/90 text-on-surface-variant hover:bg-slate-200/90 px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wide transition-all ring-1 ring-slate-200/80">Unpublish</button>
</div>
<a class="inline-flex items-center gap-2 text-primary text-xs font-bold uppercase tracking-wide mt-auto pt-6 hover:gap-2.5 transition-all <?php echo ($tenant_public_site_url_h === '') ? 'pointer-events-none opacity-50' : ''; ?>" href="<?php echo $tenant_public_site_url_h !== '' ? $tenant_public_site_url_h : '#'; ?>" <?php if ($tenant_public_site_url_h !== ''): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>>
<?php echo $tenant_public_site_url_h !== '' ? 'View live site' : 'Link unavailable'; ?> <span class="material-symbols-outlined text-base">open_in_new</span>
</a>
</div>
</section>
<section class="grid grid-cols-12 gap-8">
<div class="col-span-12">
<div class="dash-infra-panel backdrop-blur-xl p-8 sm:p-10 rounded-[2rem] border border-white/90">
<?php if ($show_activated_banner): ?>
<div class="mb-8 p-5 bg-gradient-to-r from-surface-container-low to-primary/5 border border-primary/25 text-primary rounded-2xl text-sm font-headline font-bold shadow-sm shadow-primary/10">Subscription activated. Your clinic website is now live and ready to manage.</div>
<?php endif; ?>
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
<h4 class="text-2xl font-extrabold font-headline text-on-background">Infrastructure <span class="text-primary italic font-editorial">Status</span></h4>
<p class="text-xs font-semibold text-on-surface-variant uppercase tracking-widest">Live system checks</p>
</div>
<div class="grid sm:grid-cols-3 gap-4">
<div class="dash-infra-row flex items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white/60 px-5 py-4">
<div class="flex items-center gap-3 min-w-0">
<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100">
<span class="material-symbols-outlined text-[22px]">database</span>
</span>
<span class="text-sm font-bold text-on-background truncate">Database</span>
</div>
<span class="text-[10px] font-black text-emerald-600 flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>OK</span>
</div>
<div class="dash-infra-row flex items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white/60 px-5 py-4">
<div class="flex items-center gap-3 min-w-0">
<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?php echo $has_visible_website ? 'bg-sky-50 text-sky-600 ring-sky-100' : 'bg-amber-50 text-amber-700 ring-amber-100'; ?> ring-1">
<span class="material-symbols-outlined text-[22px]">web</span>
</span>
<span class="text-sm font-bold text-on-background truncate">Clinic portal</span>
</div>
<span class="text-[10px] font-black <?php echo $has_visible_website ? 'text-emerald-600' : 'text-amber-600'; ?> flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full <?php echo $has_visible_website ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'; ?>"></span><?php echo $has_visible_website ? 'Ready' : 'Off'; ?></span>
</div>
<div class="dash-infra-row flex items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-white/60 px-5 py-4">
<div class="flex items-center gap-3 min-w-0">
<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?php echo $is_subscription_active ? 'bg-primary/10 text-primary ring-primary/15' : 'bg-slate-100 text-slate-500 ring-slate-200'; ?> ring-1">
<span class="material-symbols-outlined text-[22px]">subscriptions</span>
</span>
<span class="text-sm font-bold text-on-background truncate">Subscription</span>
</div>
<span class="text-[10px] font-black <?php echo $is_subscription_active ? 'text-emerald-600' : 'text-on-surface-variant/70'; ?> flex items-center gap-2 uppercase tracking-wider shrink-0">
<span class="w-2 h-2 rounded-full <?php echo $is_subscription_active ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span><?php echo $is_subscription_active ? 'Active' : ucfirst($subscription_state); ?></span>
</div>
</div>
</div>
</div>
</section>
</div>
</main>
<script data-purpose="event-handlers">
    // Keep JS block for future UI interactions (no forced redirects here).
  </script>
</body></html>