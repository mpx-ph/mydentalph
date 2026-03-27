<?php
session_start();
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
provider_require_approved_for_provider_portal();
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    header('Location: ProviderLogin.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$user_id = $_SESSION['user_id'];
$is_owner = !empty($_SESSION['is_owner']);
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

// Unified dashboard flow for new payment pipeline:
// 1) Save settings (optional POST)
// 2) Load tenant + user records
// 3) Resolve subscription state with fallback for paid/succeeded
// 4) Ensure clinic slug exists for active paid owner accounts
// 5) Build final website links from fresh state
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
          AND EXISTS (
              SELECT 1
              FROM tbl_users su
              WHERE su.user_id = ?
                AND su.tenant_id = t.tenant_id
          )
        LIMIT 1
    ");
    $stmt->execute([(string) $tenant_id, (string) $user_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $tenant = [];
}

// Fallback: if strict user/tenant mapping check fails, still try direct tenant lookup.
if (empty($tenant)) {
    try {
        $stmt = $pdo->prepare("
            SELECT tenant_id, clinic_name, clinic_slug, contact_email, contact_phone, clinic_address, subscription_status
            FROM tbl_tenants
            WHERE tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([(string) $tenant_id]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $tenant = [];
    }
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
try {
    $subscription_meta = provider_get_tenant_subscription_state($pdo, (string) $tenant_id);
    $subscription_state = (string) ($subscription_meta['state'] ?? 'none');
    $is_subscription_active = !empty($subscription_meta['has_active_subscription']);
    if ($is_subscription_active && !empty($subscription_meta['active_subscription'])) {
        $sub = (array) $subscription_meta['active_subscription'];
    } elseif (!empty($subscription_meta['latest_subscription'])) {
        $sub = (array) $subscription_meta['latest_subscription'];
    }
} catch (Throwable $e) {
    $sub = [];
}

if (!$is_subscription_active) {
    try {
        $paidStmt = $pdo->prepare("
            SELECT ts.id, ts.subscription_end, ts.payment_status, p.plan_name
            FROM tbl_tenant_subscriptions ts
            LEFT JOIN tbl_subscription_plans p ON p.plan_id = ts.plan_id
            WHERE ts.tenant_id = ?
              AND LOWER(ts.payment_status) IN ('paid', 'succeeded')
            ORDER BY ts.id DESC
            LIMIT 1
        ");
        $paidStmt->execute([(string) $tenant_id]);
        $fallback = $paidStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($fallback) {
            $fallback_end = trim((string) ($fallback['subscription_end'] ?? ''));
            $fallback_end_ts = $fallback_end !== '' ? strtotime($fallback_end . ' 23:59:59') : false;
            $not_expired = ($fallback_end_ts === false) || ($fallback_end_ts >= time());
            if ($not_expired) {
                $is_subscription_active = true;
                $subscription_state = 'active';
                if (empty($sub)) {
                    $sub = $fallback;
                }
            }
        }
    } catch (Throwable $e) {
        // Keep helper result if fallback fails.
    }
}

$clinic_name = trim((string) ($tenant['clinic_name'] ?? ''));
$raw_website_value = trim((string) ($tenant['clinic_slug'] ?? ''));
$clinic_slug = provider_dashboard_normalize_slug((string) ($tenant['clinic_slug'] ?? ''));
if ($clinic_name === '') {
    $clinic_name = 'My Clinic';
}

// Resolve slug robustly using tenant_id + user_id based lookups and session fallback.
if ($raw_website_value === '' && $clinic_slug === '') {
    $session_slug = provider_dashboard_normalize_slug((string) ($_SESSION['tenant_clinic_slug'] ?? ''));
    if ($session_slug !== '') {
        $clinic_slug = $session_slug;
        $raw_website_value = $session_slug;
    }
}
if ($raw_website_value === '' && $clinic_slug === '') {
    try {
        $slugStmt = $pdo->prepare("SELECT clinic_slug FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
        $slugStmt->execute([(string) $tenant_id]);
        $slugFromTenant = trim((string) $slugStmt->fetchColumn());
        if ($slugFromTenant !== '') {
            $raw_website_value = $slugFromTenant;
            $clinic_slug = provider_dashboard_normalize_slug($slugFromTenant);
        }
    } catch (Throwable $e) {
        // Continue to next fallback.
    }
}
if ($raw_website_value === '' && $clinic_slug === '') {
    try {
        $slugStmt = $pdo->prepare("
            SELECT t.clinic_slug
            FROM tbl_users u
            INNER JOIN tbl_tenants t ON t.tenant_id = u.tenant_id
            WHERE u.user_id = ?
            LIMIT 1
        ");
        $slugStmt->execute([(string) $user_id]);
        $slugFromUserTenant = trim((string) $slugStmt->fetchColumn());
        if ($slugFromUserTenant !== '') {
            $raw_website_value = $slugFromUserTenant;
            $clinic_slug = provider_dashboard_normalize_slug($slugFromUserTenant);
        }
    } catch (Throwable $e) {
        // Continue to next fallback.
    }
}
if ($raw_website_value === '' && $clinic_slug === '') {
    try {
        $slugStmt = $pdo->prepare("SELECT clinic_slug FROM tbl_tenants WHERE owner_user_id = ? LIMIT 1");
        $slugStmt->execute([(string) $user_id]);
        $slugFromOwner = trim((string) $slugStmt->fetchColumn());
        if ($slugFromOwner !== '') {
            $raw_website_value = $slugFromOwner;
            $clinic_slug = provider_dashboard_normalize_slug($slugFromOwner);
        }
    } catch (Throwable $e) {
        // Keep empty slug.
    }
}
if ($clinic_slug !== '') {
    $tenant['clinic_slug'] = $clinic_slug;
    $_SESSION['tenant_clinic_slug'] = $clinic_slug;
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
            $_SESSION['tenant_clinic_slug'] = $new_slug;
        }
    } catch (Throwable $e) {
        // Do not block dashboard rendering.
    }
}

$plan_name_raw = $sub['plan_name'] ?? null;
$plan_name = is_string($plan_name_raw) && trim($plan_name_raw) !== ''
    ? trim($plan_name_raw)
    : ($is_subscription_active ? 'Active Plan' : 'No Active Subscription');
$renewal_end = trim((string) ($sub['subscription_end'] ?? ''));
$renewal_ts = $renewal_end !== '' ? strtotime($renewal_end) : false;
$renewal_date = ($renewal_ts !== false) ? date('M j, Y', $renewal_ts) : '—';

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'mydental.ct.ws'));
if ($host === '') {
    $host = 'mydental.ct.ws';
}
$raw_website_value = $raw_website_value !== '' ? $raw_website_value : $clinic_slug;
[$tenant_base_url, $admin_dashboard_url, $domain_display, $has_visible_website] = provider_dashboard_resolve_website_urls($raw_website_value, $scheme, $host);
$has_clinic_slug = $clinic_slug !== '';
if (!$has_visible_website && !empty($_SESSION['tenant_clinic_link'])) {
    $sessionLink = trim((string) $_SESSION['tenant_clinic_link']);
    if ($sessionLink !== '') {
        $sessionDisplay = preg_replace('#^https?://#i', '', $sessionLink);
        $tenant_base_url = preg_match('#^https?://#i', $sessionLink) ? $sessionLink : ($scheme . '://' . $sessionLink);
        $admin_dashboard_url = rtrim($tenant_base_url, '/') . '/AdminDashboard.php';
        $domain_display = $sessionDisplay;
        $has_visible_website = true;
    }
}
if (!$has_visible_website) {
    $domain_display = 'No Active Website';
}
if ($has_visible_website) {
    $_SESSION['tenant_clinic_link'] = preg_replace('#^https?://#i', '', (string) $tenant_base_url);
    if (!$is_subscription_active && !is_string($plan_name_raw)) {
        $plan_name = 'Website Available';
    }
}
?>
<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Tenant Dashboard</title>
<!-- Tailwind CSS v3 with Plugins -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<!-- Google Fonts: Inter -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'dental-dark': '#101922',
            'dental-blue': '#2b8beb',
          },
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
          },
        }
      }
    }
  </script>
<style data-purpose="custom-scrollbar">
    ::-webkit-scrollbar {
      width: 6px;
    }
    ::-webkit-scrollbar-track {
      background: #f1f1f1;
    }
    ::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 10px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased">
<!-- BEGIN: LayoutWrapper -->
<div class="flex min-h-screen">
<!-- BEGIN: LeftSidebar -->
<aside class="w-64 bg-dental-dark text-white flex-shrink-0 flex flex-col fixed inset-y-0 lg:static z-50" data-purpose="navigation-sidebar">
<div class="p-6 flex items-center gap-3">
<div class="w-8 h-8 bg-dental-blue rounded-lg flex items-center justify-center">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
<path d="M12 4c-4.418 0-8 1.791-8 4s3.582 4 8 4 8-1.791 8-4-3.582-4-8-4zM4 8v4c0 2.209 3.582 4 8 4s8-1.791 8-4V8M4 12v4c0 2.209 3.582 4 8 4s8-1.791 8-4v-4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path>
</svg>
</div>
<span class="text-xl font-bold tracking-tight">MyDental</span>
</div>
<nav class="flex-1 px-4 space-y-2 mt-4">
<a class="flex items-center gap-3 px-4 py-3 bg-dental-blue rounded-xl transition-all duration-200" data-purpose="nav-item" href="#">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
<span class="font-medium">Dashboard</span>
</a>
<a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-white/5 rounded-xl transition-all duration-200" data-purpose="nav-item" href="#">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
<span class="font-medium">Billing</span>
</a>
<a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-white/5 rounded-xl transition-all duration-200" data-purpose="nav-item" href="#">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
<span class="font-medium">Website</span>
</a>
<a class="flex items-center gap-3 px-4 py-3 text-slate-400 hover:text-white hover:bg-white/5 rounded-xl transition-all duration-200" data-purpose="nav-item" href="#">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
<span class="font-medium">Settings</span>
</a>
</nav>
<div class="p-4 mt-auto border-t border-white/10">
<div class="flex items-center gap-3 px-2">
<div class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-sm font-bold">JD</div>
<div class="flex-1 overflow-hidden">
<p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($clinic_name); ?></p>
<p class="text-xs text-slate-400 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></p>
</div>
</div>
<a href="ProviderLogout.php" class="flex items-center gap-3 px-4 py-3 mt-2 text-slate-400 hover:text-red-400 hover:bg-white/5 rounded-xl transition-all duration-200">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
<span class="font-medium">Log out</span>
</a>
</div>
</aside>
<!-- END: LeftSidebar -->
<!-- BEGIN: MainContent -->
<main class="flex-1 lg:ml-0 overflow-y-auto min-h-screen transition-all duration-300">
<!-- BEGIN: Header -->
<header class="bg-white border-b border-slate-200 sticky top-0 z-40 px-8 py-4 flex items-center justify-between" data-purpose="top-header">
<h1 class="text-2xl font-bold text-dental-dark">Tenant Dashboard</h1>
<div class="flex items-center gap-4">
<button class="p-2 text-slate-400 hover:text-dental-blue transition-colors">
<svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
</button>
</div>
</header>
<!-- END: Header -->
<div class="p-8 max-w-7xl mx-auto space-y-8">
<!-- BEGIN: ActionBanner -->
<section class="bg-gradient-to-r from-dental-blue to-blue-600 rounded-3xl p-8 shadow-lg shadow-blue-200 text-white flex flex-col md:flex-row items-center justify-between gap-6" data-purpose="primary-action-card">
<div class="space-y-2">
<h2 class="text-3xl font-bold tracking-tight">Access Clinic Management</h2>
<p class="text-blue-50 opacity-90">Manage patients, appointments, and medical records from one place.</p>
</div>
<a
  class="bg-white text-dental-blue px-8 py-4 rounded-2xl font-bold text-lg hover:bg-slate-50 transition-all transform hover:scale-105 active:scale-95 shadow-xl inline-flex items-center justify-center"
  id="open-dashboard-btn"
  href="<?php echo $admin_dashboard_url ? htmlspecialchars($admin_dashboard_url, ENT_QUOTES, 'UTF-8') : '#'; ?>"
  <?php if ($has_visible_website && $admin_dashboard_url): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
>
  <?php echo $has_visible_website ? 'Open Clinic Management Dashboard' : 'No Active Website'; ?>
</a>
</section>
<!-- END: ActionBanner -->
<!-- BEGIN: OverviewGrid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" data-purpose="overview-stats">
<!-- Plan Card -->
<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
<div class="flex items-center justify-between mb-4">
<span class="text-slate-500 text-sm font-medium uppercase tracking-wider">Current Plan</span>
<div class="p-2 bg-blue-50 rounded-lg text-dental-blue">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.040L3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622l-.382-3.016z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
</div>
</div>
<div class="flex items-center gap-3">
<h3 class="text-2xl font-bold text-dental-dark"><?php echo htmlspecialchars($plan_name); ?></h3>
<?php if ($is_subscription_active): ?>
<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Active</span>
<?php elseif ($subscription_state === 'expired'): ?>
<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Expired</span>
<?php elseif ($subscription_state === 'inactive'): ?>
<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-700">Inactive</span>
<?php else: ?>
<span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-600">None</span>
<?php endif; ?>
</div>
<p class="text-slate-400 text-sm mt-2">Renewal: <?php echo htmlspecialchars($renewal_date); ?></p>
</div>
<!-- Domain Card -->
<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
<div class="flex items-center justify-between mb-4">
<span class="text-slate-500 text-sm font-medium uppercase tracking-wider">Domain &amp; Hosting</span>
<div class="p-2 bg-slate-50 rounded-lg text-slate-600">
<svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
</div>
</div>
<?php if ($has_visible_website): ?>
<a class="text-lg font-bold text-dental-dark truncate hover:underline inline-block" href="<?php echo htmlspecialchars($tenant_base_url . '/', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
    <?php echo htmlspecialchars($domain_display); ?>
</a>
<?php else: ?>
<h3 class="text-lg font-bold text-dental-dark truncate"><?php echo htmlspecialchars($domain_display); ?></h3>
<?php endif; ?>
<div class="flex items-center gap-2 mt-2">
<span class="w-2 h-2 rounded-full <?php echo $has_visible_website ? 'bg-emerald-500' : 'bg-amber-500'; ?>"></span>
<span class="text-slate-500 text-sm"><?php echo $has_visible_website ? 'Website Published' : 'Website Not Yet Published'; ?></span>
</div>
</div>
<!-- Quick Actions Card -->
<div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow">
<div class="flex items-center justify-between mb-4">
<span class="text-slate-500 text-sm font-medium uppercase tracking-wider">Website Control</span>
</div>
<div class="flex flex-wrap gap-2">
<button class="bg-dental-blue/10 text-dental-blue hover:bg-dental-blue hover:text-white px-4 py-2 rounded-lg text-sm font-semibold transition-colors">Publish</button>
<button class="bg-slate-100 text-slate-600 hover:bg-slate-200 px-4 py-2 rounded-lg text-sm font-semibold transition-colors">Unpublish</button>
<a
  class="w-full text-center mt-2 text-dental-blue text-sm font-medium hover:underline flex items-center justify-center gap-1"
  href="<?php echo $has_visible_website && $tenant_base_url ? htmlspecialchars($tenant_base_url . '/', ENT_QUOTES, 'UTF-8') : '#'; ?>"
  <?php if ($has_visible_website && $tenant_base_url): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
>
                <?php echo $has_visible_website ? 'View Live Website' : 'Website Link Unavailable'; ?>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
</a>
</div>
</div>
</div>
<!-- END: OverviewGrid -->
<!-- BEGIN: SplitView -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
<!-- Subscription Management -->
<div class="lg:col-span-1 space-y-6">
<div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
<h3 class="text-lg font-bold text-dental-dark mb-6">Subscription &amp; Billing</h3>
<div class="space-y-3">
<button class="w-full text-left px-4 py-3 rounded-xl border border-slate-100 hover:border-dental-blue hover:bg-blue-50/30 transition-all flex items-center justify-between group">
<span class="text-slate-700 font-medium">Upgrade Plan</span>
<svg class="w-4 h-4 text-slate-400 group-hover:text-dental-blue" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
</button>
<button class="w-full text-left px-4 py-3 rounded-xl border border-slate-100 hover:border-dental-blue hover:bg-blue-50/30 transition-all flex items-center justify-between group">
<span class="text-slate-700 font-medium">View Billing History</span>
<svg class="w-4 h-4 text-slate-400 group-hover:text-dental-blue" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
</button>
<button class="w-full text-left px-4 py-3 rounded-xl border border-slate-100 hover:border-dental-blue hover:bg-blue-50/30 transition-all flex items-center justify-between group">
<span class="text-slate-700 font-medium">Payment Methods</span>
<svg class="w-4 h-4 text-slate-400 group-hover:text-dental-blue" fill="none" stroke="currentColor" viewbox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
</button>
<div class="pt-4 mt-4 border-t border-slate-100 flex flex-col gap-3">
<button class="text-sm font-medium text-slate-400 hover:text-slate-600 transition-colors">Downgrade Plan</button>
<button class="text-sm font-medium text-red-400 hover:text-red-600 transition-colors">Cancel Subscription</button>
</div>
</div>
</div>
</div>
<!-- Account Settings Form -->
<div class="lg:col-span-2">
<div class="bg-white p-8 rounded-3xl border border-slate-200 shadow-sm">
<?php if ($settings_saved): ?>
<div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">Settings saved successfully.</div>
<?php endif; ?>
<?php if ($show_activated_banner): ?>
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl text-sm font-semibold">Subscription activated. Your clinic website is now live and ready to manage.</div>
<?php endif; ?>
<?php if ($settings_error): ?>
<div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm"><?php echo htmlspecialchars($settings_error); ?></div>
<?php endif; ?>
<div class="flex items-center justify-between mb-8">
<div>
<h3 class="text-xl font-bold text-dental-dark">Account Settings</h3>
<p class="text-slate-500 text-sm">Clinic details (tbl_tenants) and your account (tbl_users).</p>
</div>
<button class="px-6 py-2.5 bg-dental-blue text-white rounded-xl font-semibold hover:bg-blue-600 transition-shadow shadow-md shadow-blue-100" form="settings-form" type="submit">Save Changes</button>
</div>
<!-- Form: clinic data → tbl_tenants, owner/account → tbl_users -->
<form class="space-y-6" method="post" action="" data-purpose="account-form" id="settings-form">
<?php if ($is_owner): ?>
<div class="border-b border-slate-200 pb-6">
<h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Clinic details</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="md:col-span-2">
<label class="text-sm font-semibold text-slate-700" for="clinic_name">Clinic Name</label>
<input class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-dental-blue focus:ring-dental-blue transition-all" id="clinic_name" name="clinic_name" placeholder="Clinic name" type="text" value="<?php echo htmlspecialchars($tenant['clinic_name'] ?? ''); ?>"/>
</div>
<div>
<label class="text-sm font-semibold text-slate-700" for="clinic_email">Clinic Email</label>
<input class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-dental-blue focus:ring-dental-blue transition-all" id="clinic_email" name="clinic_email" placeholder="Clinic email" type="email" value="<?php echo htmlspecialchars($tenant['contact_email'] ?? ''); ?>"/>
</div>
<div>
<label class="text-sm font-semibold text-slate-700" for="clinic_phone">Clinic Phone</label>
<input class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-dental-blue focus:ring-dental-blue transition-all" id="clinic_phone" name="clinic_phone" placeholder="Clinic phone" type="tel" value="<?php echo htmlspecialchars($tenant['contact_phone'] ?? ''); ?>"/>
</div>
<div class="md:col-span-2">
<label class="text-sm font-semibold text-slate-700" for="clinic_address">Clinic Address</label>
<textarea class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-dental-blue focus:ring-dental-blue transition-all" id="clinic_address" name="clinic_address" placeholder="Address" rows="2"><?php echo htmlspecialchars($tenant['clinic_address'] ?? ''); ?></textarea>
</div>
</div>
</div>
<?php endif; ?>
<div>
<h4 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Your account</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div>
<label class="text-sm font-semibold text-slate-700" for="full-name">Full Name</label>
<input class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-dental-blue focus:ring-dental-blue transition-all" id="full-name" name="full_name" placeholder="Full name" type="text" value="<?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?>"/>
</div>
<div>
<label class="text-sm font-semibold text-slate-700" for="email">Email Address</label>
<input class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-dental-blue focus:ring-dental-blue transition-all" id="email" name="email" placeholder="Email" type="email" value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>"/>
</div>
<div>
<label class="text-sm font-semibold text-slate-700" for="contact">Contact Number</label>
<input class="w-full px-4 py-3 rounded-xl border-slate-200 focus:border-dental-blue focus:ring-dental-blue transition-all" id="contact" name="phone" placeholder="Phone" type="tel" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>"/>
</div>
</div>
</div>
</form>
</div>
</div>
</div>
<!-- END: SplitView -->
</div>
</main>
<!-- END: MainContent -->
</div>
<!-- END: LayoutWrapper -->
<script data-purpose="event-handlers">
    // Keep JS block for future UI interactions (no forced redirects here).
  </script>
</body></html>