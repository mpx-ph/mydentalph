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

require_once __DIR__ . '/provider_tenant_canonical_context.inc.php';
require_once __DIR__ . '/provider_tenant_plan_and_site_context.inc.php';

$current_user = [];
try {
    $stmt = $pdo->prepare("SELECT full_name, email, phone FROM tbl_users WHERE user_id = ? LIMIT 1");
    $stmt->execute([(string) $user_id]);
    $current_user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $current_user = [];
}

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

if (!function_exists('provider_tenant_dash_resolve_table')) {
    /**
     * @param array<int, string> $candidates
     */
    function provider_tenant_dash_resolve_table(PDO $pdo, array $candidates): string
    {
        foreach ($candidates as $n) {
            if (!is_string($n) || !preg_match('/^[a-z][a-z0-9_]{0,62}$/i', $n)) {
                continue;
            }
            try {
                $pdo->query('SELECT 1 FROM `' . $n . '` LIMIT 0');
                return $n;
            } catch (Throwable $e) {
            }
        }
        return '';
    }
}

$tz_manila = null;
try {
    $tz_manila = new DateTimeZone('Asia/Manila');
} catch (Throwable $e) {
    $tz_manila = new DateTimeZone('UTC');
}
$hour_now = (int) (new DateTimeImmutable('now', $tz_manila))->format('G');
if ($hour_now < 12) {
    $greeting_time = 'Good morning';
} elseif ($hour_now < 17) {
    $greeting_time = 'Good afternoon';
} else {
    $greeting_time = 'Good evening';
}
$today_sql = (new DateTimeImmutable('now', $tz_manila))->format('Y-m-d');
$today_label_long = (new DateTimeImmutable('now', $tz_manila))->format('l, F j, Y');

$t_patients = provider_tenant_dash_resolve_table($pdo, ['patients', 'tbl_patients']);
$t_appts = provider_tenant_dash_resolve_table($pdo, ['appointments', 'tbl_appointments']);
$t_payments = provider_tenant_dash_resolve_table($pdo, ['payments', 'tbl_payments']);

$dash_patient_count = 0;
if ($t_patients !== '') {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM `{$t_patients}` WHERE tenant_id = ?");
        $st->execute([$tenant_id]);
        $dash_patient_count = (int) $st->fetchColumn();
    } catch (Throwable $e) {
        $dash_patient_count = 0;
    }
}

$dash_revenue_total = 0.0;
if ($t_payments !== '') {
    try {
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM `{$t_payments}`
            WHERE tenant_id = ?
              AND LOWER(TRIM(COALESCE(status, ''))) IN ('completed', 'complete', 'paid', 'success')
        ");
        $st->execute([$tenant_id]);
        $dash_revenue_total = (float) $st->fetchColumn();
    } catch (Throwable $e) {
        $dash_revenue_total = 0.0;
    }
}

$dash_team_count = 0;
try {
    $st = $pdo->prepare("
        SELECT COUNT(*) FROM tbl_users
        WHERE tenant_id = ?
          AND status = 'active'
          AND role IN ('tenant_owner', 'manager', 'dentist', 'staff')
    ");
    $st->execute([$tenant_id]);
    $dash_team_count = (int) $st->fetchColumn();
} catch (Throwable $e) {
    $dash_team_count = 0;
}

$dash_today_pending = 0;
$dash_today_completed = 0;
$dash_today_cancelled = 0;
$dash_today_total = 0;
$dash_recent_appts = [];

if ($t_appts !== '') {
    try {
        $st = $pdo->prepare("
            SELECT
                SUM(CASE WHEN LOWER(TRIM(status)) IN ('pending', 'confirmed') THEN 1 ELSE 0 END) AS c_pending,
                SUM(CASE WHEN LOWER(TRIM(status)) = 'completed' THEN 1 ELSE 0 END) AS c_done,
                SUM(CASE WHEN LOWER(TRIM(status)) IN ('cancelled', 'canceled', 'no_show') THEN 1 ELSE 0 END) AS c_cancel,
                COUNT(*) AS c_all
            FROM `{$t_appts}`
            WHERE tenant_id = ? AND appointment_date = ?
        ");
        $st->execute([$tenant_id, $today_sql]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $dash_today_pending = (int) ($row['c_pending'] ?? 0);
        $dash_today_completed = (int) ($row['c_done'] ?? 0);
        $dash_today_cancelled = (int) ($row['c_cancel'] ?? 0);
        $dash_today_total = (int) ($row['c_all'] ?? 0);
    } catch (Throwable $e) {
    }

    $pjoin = $t_patients !== '' ? "LEFT JOIN `{$t_patients}` p ON a.patient_id = p.patient_id AND p.tenant_id = a.tenant_id" : '';
    try {
        $sql = "
            SELECT a.booking_id, a.appointment_date, a.appointment_time, a.status, a.service_type,
                   p.first_name AS pf, p.last_name AS pl
            FROM `{$t_appts}` a
            {$pjoin}
            WHERE a.tenant_id = ?
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 12
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$tenant_id]);
        $dash_recent_appts = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $dash_recent_appts = [];
    }
}

$user_email_display = trim((string) ($current_user['email'] ?? ''));
$clinic_display_name = isset($clinic_name) ? (string) $clinic_name : 'My Clinic';
$manage_team_href = 'ProviderTenantUsers.php';
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
      ::-webkit-scrollbar { width: 6px; }
      ::-webkit-scrollbar-track { background: #f1f1f1; }
      ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
      ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
      .provider-welcome-banner {
        background: linear-gradient(120deg, #1e3a5f 0%, #2b8beb 42%, #5ab0ff 100%);
        box-shadow: 0 20px 50px -20px rgba(43, 139, 235, 0.45);
      }
    </style>
</head>
<body class="mesh-bg font-body text-sm text-on-background selection:bg-primary/10 min-h-screen">
<?php
$provider_nav_active = 'dashboard';
include __DIR__ . '/provider_tenant_sidebar.inc.php';
?>
<header class="fixed top-0 right-0 left-64 z-30 min-h-[4.5rem] bg-white/90 backdrop-blur-xl border-b border-slate-200/60 shadow-sm shadow-slate-200/30" data-purpose="top-header">
<div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between px-6 lg:px-10 py-3 sm:py-3.5">
<div class="min-w-0 flex-1">
<p class="text-[10px] font-bold uppercase tracking-[0.2em] text-primary/80 mb-0.5">Clinic</p>
<h1 class="text-lg sm:text-xl font-extrabold font-headline text-on-background truncate tracking-tight"><?php echo htmlspecialchars($clinic_display_name, ENT_QUOTES, 'UTF-8'); ?></h1>
</div>
<div class="flex items-center gap-2 sm:gap-3 shrink-0">
<button type="button" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all relative border-0 bg-transparent cursor-pointer hidden sm:inline-flex" aria-label="Notifications">
<span class="material-symbols-outlined text-on-surface-variant">notifications</span>
</button>
<button type="button" class="hover:bg-surface-container-low rounded-full p-2.5 transition-all border-0 bg-transparent cursor-pointer hidden sm:inline-flex" aria-label="Help">
<span class="material-symbols-outlined text-on-surface-variant">help_outline</span>
</button>
<div class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/80 pl-1 pr-3 py-1 shadow-sm">
<div class="w-10 h-10 rounded-xl bg-primary/15 flex items-center justify-center text-primary text-xs font-bold border border-primary/10 shrink-0" aria-hidden="true"><?php echo htmlspecialchars($avatar_initials, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="min-w-0 text-left">
<p class="text-xs font-bold text-on-background truncate max-w-[10rem] sm:max-w-[14rem]"><?php echo htmlspecialchars($display_name !== '' ? $display_name : 'Signed in', ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($user_email_display !== ''): ?>
<p class="text-[11px] text-on-surface-variant truncate max-w-[10rem] sm:max-w-[14rem]"><?php echo htmlspecialchars($user_email_display, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
</div>
</div>
</div>
</div>
</header>
<main class="ml-64 pt-[4.5rem] sm:pt-24 min-h-screen provider-page-enter">
<div class="pt-4 sm:pt-6 px-6 lg:px-10 pb-20 space-y-8 relative">
<div class="absolute top-24 right-8 w-[28rem] h-[28rem] bg-primary/10 rounded-full blur-[120px] -z-10 pointer-events-none" aria-hidden="true"></div>
<div class="absolute bottom-40 left-10 w-72 h-72 bg-teal-400/10 rounded-full blur-[100px] -z-10 pointer-events-none" aria-hidden="true"></div>

<section>
<p class="text-primary font-bold text-[10px] sm:text-xs uppercase tracking-[0.35em] flex items-center gap-3 mb-3"><span class="w-8 sm:w-10 h-px bg-primary/40"></span> Provider dashboard</p>
<h2 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold font-headline tracking-tight text-on-background">Clinic <span class="font-editorial italic font-normal text-primary editorial-word">Overview</span></h2>
<p class="text-on-surface-variant mt-3 text-base sm:text-lg font-medium max-w-2xl leading-relaxed">A quick snapshot of patients, revenue, team, and today&rsquo;s schedule.</p>
<?php if ($show_activated_banner): ?>
<div class="mt-5 p-5 bg-gradient-to-r from-surface-container-low to-primary/5 border border-primary/25 text-primary rounded-2xl text-sm font-headline font-bold shadow-sm shadow-primary/10 max-w-2xl">Subscription activated. Your clinic website is now live and ready to manage.</div>
<?php endif; ?>
</section>

<section class="provider-welcome-banner rounded-3xl px-6 sm:px-10 py-7 sm:py-8 text-white relative overflow-hidden">
<div class="absolute inset-0 opacity-[0.12] pointer-events-none" style="background-image: radial-gradient(circle at 20% 120%, #fff 0, transparent 55%), radial-gradient(circle at 90% -20%, #fff 0, transparent 45%);" aria-hidden="true"></div>
<div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
<div class="max-w-2xl">
<p class="text-white/80 text-xs font-bold uppercase tracking-[0.25em] mb-2"><?php echo htmlspecialchars($today_label_long, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-2xl sm:text-3xl font-extrabold font-headline tracking-tight"><?php echo htmlspecialchars($greeting_time, ENT_QUOTES, 'UTF-8'); ?>, <span class="font-editorial italic font-normal text-white/95"><?php echo htmlspecialchars($welcome_name, ENT_QUOTES, 'UTF-8'); ?></span>.</p>
<p class="text-white/85 mt-2 text-sm sm:text-base font-medium leading-relaxed">Here&rsquo;s what&rsquo;s happening at your practice today. Invite staff and manage roles anytime.</p>
</div>
<div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 shrink-0">
<a class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white text-primary px-6 py-3.5 text-sm font-bold shadow-lg shadow-black/10 hover:brightness-[1.03] transition-all ring-2 ring-white/30" href="<?php echo htmlspecialchars($manage_team_href, ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined text-xl">group</span>
Manage Team
</a>
<a
  class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white/10 text-white border border-white/25 px-6 py-3.5 text-sm font-bold hover:bg-white/15 transition-all backdrop-blur-sm"
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

<section>
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
<div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-sm provider-card-lift dash-stat-card">
<div class="flex items-start justify-between gap-2">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Total patients</p>
<p class="mt-2 text-2xl sm:text-3xl font-extrabold font-headline text-on-background tabular-nums"><?php echo number_format($dash_patient_count); ?></p>
</div>
<span class="material-symbols-outlined text-primary/80 text-3xl">diversity_3</span>
</div>
</div>
<div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-sm provider-card-lift dash-stat-card">
<div class="flex items-start justify-between gap-2">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Revenue (paid)</p>
<p class="mt-2 text-xl sm:text-2xl font-extrabold font-headline text-on-background tabular-nums">₱<?php echo number_format($dash_revenue_total, 2); ?></p>
</div>
<span class="material-symbols-outlined text-emerald-600/90 text-3xl">payments</span>
</div>
</div>
<div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-sm provider-card-lift dash-stat-card">
<div class="flex items-start justify-between gap-2">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Team members</p>
<p class="mt-2 text-2xl sm:text-3xl font-extrabold font-headline text-on-background tabular-nums"><?php echo number_format($dash_team_count); ?></p>
</div>
<span class="material-symbols-outlined text-violet-600/90 text-3xl">badge</span>
</div>
</div>
<div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-sm provider-card-lift dash-stat-card col-span-2 xl:col-span-1">
<div class="flex items-start justify-between gap-2">
<div>
<p class="text-[11px] font-bold uppercase tracking-wider text-on-surface-variant">Today&rsquo;s schedule</p>
<p class="mt-2 text-2xl sm:text-3xl font-extrabold font-headline text-on-background tabular-nums"><?php echo number_format($dash_today_total); ?> <span class="text-sm font-semibold text-on-surface-variant">appts</span></p>
<p class="text-xs text-on-surface-variant mt-1"><?php echo htmlspecialchars($today_sql, ENT_QUOTES, 'UTF-8'); ?> · Asia/Manila</p>
</div>
<span class="material-symbols-outlined text-sky-600/90 text-3xl">calendar_today</span>
</div>
</div>
</div>
</section>

<section class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 items-start">
<div class="lg:col-span-2 glass-card rounded-3xl p-6 sm:p-8 editorial-shadow">
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
<div>
<h3 class="text-lg font-extrabold font-headline text-on-background">Recent activity</h3>
<p class="text-sm text-on-surface-variant mt-1">Latest appointments across your clinic</p>
</div>
</div>
<?php if ($dash_recent_appts === []): ?>
<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 py-16 px-6 text-center">
<span class="material-symbols-outlined text-4xl text-slate-300 mb-3 block mx-auto">event_busy</span>
<p class="text-on-surface-variant font-medium">No recent appointments yet.</p>
<p class="text-sm text-on-surface-variant/80 mt-1">When patients book in clinic management, they will appear here.</p>
</div>
<?php else: ?>
<ul class="divide-y divide-slate-100">
<?php foreach ($dash_recent_appts as $ra): ?>
<?php
    $pfn = trim((string) ($ra['pf'] ?? ''));
    $pln = trim((string) ($ra['pl'] ?? ''));
    $pname = trim($pfn . ' ' . $pln);
    if ($pname === '') {
        $pname = 'Patient';
    }
    $st = strtolower(trim((string) ($ra['status'] ?? '')));
    $st_label = $st !== '' ? ucfirst(str_replace('_', ' ', $st)) : '—';
    $svc = trim((string) ($ra['service_type'] ?? ''));
    if ($svc === '') {
        $svc = 'Appointment';
    }
    $bid = trim((string) ($ra['booking_id'] ?? ''));
    $d = trim((string) ($ra['appointment_date'] ?? ''));
    $t = trim((string) ($ra['appointment_time'] ?? ''));
    $when = $d;
    if ($t !== '') {
        $when .= ' · ' . $t;
    }
    $badge_cls = 'bg-slate-100 text-slate-700';
    if (in_array($st, ['completed'], true)) {
        $badge_cls = 'bg-emerald-50 text-emerald-800';
    } elseif (in_array($st, ['pending', 'confirmed'], true)) {
        $badge_cls = 'bg-amber-50 text-amber-900';
    } elseif (in_array($st, ['cancelled', 'canceled', 'no_show'], true)) {
        $badge_cls = 'bg-red-50 text-red-800';
    }
?>
<li class="py-4 first:pt-0 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
<div class="flex-1 min-w-0">
<p class="font-bold text-on-background truncate"><?php echo htmlspecialchars($pname, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-sm text-on-surface-variant truncate"><?php echo htmlspecialchars($svc, ENT_QUOTES, 'UTF-8'); ?><?php if ($bid !== ''): ?> · <span class="font-mono text-xs"><?php echo htmlspecialchars($bid, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?></p>
<p class="text-xs text-on-surface-variant/80 mt-1"><?php echo htmlspecialchars($when, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold shrink-0 <?php echo $badge_cls; ?>"><?php echo htmlspecialchars($st_label, ENT_QUOTES, 'UTF-8'); ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
</div>

<div class="space-y-4">
<div class="glass-card rounded-3xl p-6 editorial-shadow">
<h3 class="text-base font-extrabold font-headline text-on-background mb-1">Today&rsquo;s schedule</h3>
<p class="text-xs text-on-surface-variant mb-5">Status breakdown for <?php echo htmlspecialchars($today_sql, ENT_QUOTES, 'UTF-8'); ?></p>
<div class="space-y-4">
<div class="flex items-center justify-between gap-3 rounded-2xl bg-amber-50/90 border border-amber-100 px-4 py-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-amber-700 text-xl shrink-0">schedule</span>
<span class="text-sm font-bold text-amber-950">Pending</span>
</div>
<span class="text-xl font-extrabold tabular-nums text-amber-950"><?php echo number_format($dash_today_pending); ?></span>
</div>
<div class="flex items-center justify-between gap-3 rounded-2xl bg-emerald-50/90 border border-emerald-100 px-4 py-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-emerald-700 text-xl shrink-0">check_circle</span>
<span class="text-sm font-bold text-emerald-950">Completed</span>
</div>
<span class="text-xl font-extrabold tabular-nums text-emerald-950"><?php echo number_format($dash_today_completed); ?></span>
</div>
<div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-100/90 border border-slate-200 px-4 py-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-slate-600 text-xl shrink-0">cancel</span>
<span class="text-sm font-bold text-slate-900">Cancelled</span>
</div>
<span class="text-xl font-extrabold tabular-nums text-slate-900"><?php echo number_format($dash_today_cancelled); ?></span>
</div>
</div>
<?php if ($dash_today_total > 0): ?>
<div class="mt-5 pt-4 border-t border-slate-100">
<div class="flex h-2 rounded-full overflow-hidden bg-slate-100">
<span class="h-full min-w-0 bg-amber-400 transition-all" style="flex: <?php echo max(0, $dash_today_pending); ?> 1 0%"></span>
<span class="h-full min-w-0 bg-emerald-500 transition-all" style="flex: <?php echo max(0, $dash_today_completed); ?> 1 0%"></span>
<span class="h-full min-w-0 bg-slate-400 transition-all" style="flex: <?php echo max(0, $dash_today_cancelled); ?> 1 0%"></span>
</div>
<p class="text-[11px] text-on-surface-variant mt-2">Share of today&rsquo;s appointments: pending, completed, and cancelled.</p>
</div>
<?php endif; ?>
</div>

<div class="glass-card rounded-3xl p-6 editorial-shadow">
<h3 class="text-base font-extrabold font-headline text-on-background mb-1">Quick insight</h3>
<p class="text-xs text-on-surface-variant mb-3">Subscription &amp; public site</p>
<p class="text-sm font-bold text-on-background"><?php echo htmlspecialchars(isset($plan_name) ? (string) $plan_name : 'Plan', ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-xs text-on-surface-variant mt-2 break-all"><?php echo htmlspecialchars(isset($domain_display) ? (string) $domain_display : '—', ENT_QUOTES, 'UTF-8'); ?></p>
<a class="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-primary hover:underline" href="ProviderTenantSubs.php">View billing<span class="material-symbols-outlined text-base">arrow_forward</span></a>
</div>
</div>
</section>

</div>
</main>
<script data-purpose="event-handlers">
    // Keep JS block for future UI interactions (no forced redirects here).
  </script>
</body></html>