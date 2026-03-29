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
$profile_first_name = '';
$profile_last_name = '';
$full_for_split = trim((string) ($current_user['full_name'] ?? ''));
if ($full_for_split !== '') {
    $parts = preg_split('/\s+/', $full_for_split, 2, PREG_SPLIT_NO_EMPTY);
    if (is_array($parts)) {
        $profile_first_name = (string) ($parts[0] ?? '');
        $profile_last_name = (string) ($parts[1] ?? '');
    }
}
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
      .profile-modal-overlay {
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
      }
      .profile-modal-panel {
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.2), 0 0 0 1px rgba(226, 232, 240, 0.9);
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
<button type="button" id="open-profile-modal" class="group flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white/80 pl-1 pr-2.5 py-1 shadow-sm text-left cursor-pointer hover:border-primary/35 hover:bg-white hover:shadow-md transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:ring-offset-2" aria-label="Account settings" aria-haspopup="dialog" aria-expanded="false" aria-controls="profile-account-modal">
<div id="header-account-avatar" class="w-10 h-10 rounded-xl bg-primary/15 flex items-center justify-center text-primary text-xs font-bold border border-primary/10 shrink-0 group-hover:bg-primary/20 transition-colors" aria-hidden="true"><?php echo htmlspecialchars($avatar_initials, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="min-w-0 text-left">
<p id="header-account-name" class="text-xs font-bold text-on-background truncate max-w-[10rem] sm:max-w-[14rem] group-hover:text-primary transition-colors"><?php echo htmlspecialchars($display_name !== '' ? $display_name : 'Signed in', ENT_QUOTES, 'UTF-8'); ?></p>
<p id="header-account-email" class="text-[11px] text-on-surface-variant truncate max-w-[10rem] sm:max-w-[14rem]<?php echo $user_email_display === '' ? ' hidden' : ''; ?>"><?php echo htmlspecialchars($user_email_display, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
</button>
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
</div>
</section>

</div>
</main>
<script type="application/json" id="profile-modal-initial"><?php echo json_encode([
    'first_name' => $profile_first_name,
    'last_name' => $profile_last_name,
    'email' => $user_email_display,
    'display_name' => $display_name !== '' ? $display_name : 'Signed in',
    'avatar_initials' => $avatar_initials,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<div id="profile-account-modal" class="fixed inset-0 z-[100] hidden" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title">
<div class="profile-modal-overlay absolute inset-0" data-profile-modal-dismiss="1"></div>
<div class="absolute inset-0 flex items-end sm:items-center justify-center p-0 sm:p-6 pointer-events-none">
<div class="profile-modal-panel pointer-events-auto w-full sm:max-w-3xl lg:max-w-4xl max-h-[100dvh] sm:max-h-[min(92vh,52rem)] overflow-hidden rounded-t-3xl sm:rounded-3xl bg-white flex flex-col">
<div class="flex items-start justify-between gap-4 px-5 sm:px-8 pt-5 sm:pt-7 pb-4 border-b border-slate-100 shrink-0">
<div>
<p class="text-[10px] font-bold uppercase tracking-[0.2em] text-primary/80">Account</p>
<h2 id="profile-modal-title" class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight mt-1">Your profile</h2>
<p class="text-sm text-on-surface-variant mt-1 max-w-xl">Update how you appear on the portal, your sign-in email, or your password.</p>
</div>
<button type="button" class="rounded-xl p-2 text-on-surface-variant hover:bg-slate-100 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40" data-profile-modal-dismiss="1" aria-label="Close">
<span class="material-symbols-outlined text-2xl">close</span>
</button>
</div>
<div class="overflow-y-auto flex-1 min-h-0 px-5 sm:px-8 py-5 sm:py-6">
<form id="profile-account-form" class="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8">
<div class="lg:col-span-3 space-y-4">
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-first-name">First name</label>
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="text" id="profile-first-name" name="first_name" autocomplete="given-name" required maxlength="120"/>
</div>
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-last-name">Last name</label>
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="text" id="profile-last-name" name="last_name" autocomplete="family-name" maxlength="120"/>
</div>
</div>
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-email">Email</label>
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="email" id="profile-email" name="email" autocomplete="email" required/>
<p class="text-[11px] text-on-surface-variant mt-2 leading-relaxed">If you change this address, we will send a verification code to the new inbox via email.</p>
</div>
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-current-password">Current password</label>
<div class="relative">
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-12 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="password" id="profile-current-password" name="current_password" autocomplete="current-password" placeholder="Required when changing email or password"/>
<button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-on-surface-variant hover:bg-slate-200/80" data-pw-toggle="profile-current-password" aria-label="Show password"><span class="material-symbols-outlined text-xl">visibility</span></button>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-new-password">New password</label>
<div class="relative">
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-12 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="password" id="profile-new-password" name="new_password" autocomplete="new-password" placeholder="Leave blank to keep"/>
<button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-on-surface-variant hover:bg-slate-200/80" data-pw-toggle="profile-new-password" aria-label="Show password"><span class="material-symbols-outlined text-xl">visibility</span></button>
</div>
<div class="mt-2">
<div class="flex items-center justify-between gap-2 mb-1">
<span class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant/80">Strength</span>
<span id="profile-pw-strength-label" class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Waiting</span>
</div>
<div class="h-1.5 rounded-full bg-slate-100 overflow-hidden flex gap-0.5" id="profile-pw-strength-bar" aria-hidden="true">
<span class="h-full flex-1 rounded-sm bg-slate-200 transition-colors profile-pw-seg"></span>
<span class="h-full flex-1 rounded-sm bg-slate-200 transition-colors profile-pw-seg"></span>
<span class="h-full flex-1 rounded-sm bg-slate-200 transition-colors profile-pw-seg"></span>
<span class="h-full flex-1 rounded-sm bg-slate-200 transition-colors profile-pw-seg"></span>
</div>
</div>
</div>
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-confirm-password">Confirm new</label>
<div class="relative">
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-12 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="password" id="profile-confirm-password" name="confirm_password" autocomplete="new-password" placeholder="Repeat new password"/>
<button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-on-surface-variant hover:bg-slate-200/80" data-pw-toggle="profile-confirm-password" aria-label="Show password"><span class="material-symbols-outlined text-xl">visibility</span></button>
</div>
<p class="text-[11px] text-on-surface-variant mt-2">Only fill these in when you want a new password.</p>
</div>
</div>
<div id="profile-otp-block" class="hidden rounded-2xl border border-primary/20 bg-surface-container-low/80 p-4 sm:p-5">
<label class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1.5" for="profile-email-otp">Email verification code</label>
<div class="flex flex-col sm:flex-row gap-3">
<input class="flex-1 rounded-xl border border-primary/25 bg-white px-4 py-3 text-sm font-mono tracking-[0.25em] text-center focus:border-primary focus:ring-2 focus:ring-primary/15" type="text" id="profile-email-otp" inputmode="numeric" maxlength="6" placeholder="6 digits" autocomplete="one-time-code"/>
<button type="button" id="profile-verify-otp-btn" class="shrink-0 rounded-xl bg-primary text-white px-5 py-3 text-sm font-bold primary-glow hover:brightness-105 transition-all">Verify email</button>
</div>
</div>
</div>
<div class="lg:col-span-2 space-y-4">
<div class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-5">
<div class="flex gap-3">
<span class="material-symbols-outlined text-primary text-3xl shrink-0">shield_lock</span>
<div>
<p class="text-xs font-extrabold font-headline text-on-background leading-snug">Sensitive updates need your current password</p>
<p class="text-sm text-on-surface-variant mt-2 leading-relaxed">Changing email or password requires confirming your current password. A fresh code is emailed when you change your address.</p>
</div>
</div>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5">
<p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-2">Password rules</p>
<p class="text-sm text-on-surface-variant leading-relaxed">At least 12 characters with uppercase, lowercase, a number, and a special character.</p>
</div>
</div>
<div class="lg:col-span-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2 border-t border-slate-100 mt-2">
<button type="button" class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-on-background hover:bg-slate-50 transition-colors" data-profile-modal-dismiss="1">Cancel</button>
<button type="submit" id="profile-save-btn" class="rounded-xl bg-primary text-white px-8 py-3 text-sm font-bold primary-glow hover:brightness-105 transition-all disabled:opacity-50 disabled:pointer-events-none">Save profile</button>
</div>
</form>
<p id="profile-form-message" class="mt-4 text-sm font-semibold hidden px-1" role="status"></p>
</div>
</div>
</div>
</div>
<script data-purpose="profile-modal">
(function () {
  var modal = document.getElementById('profile-account-modal');
  var openBtn = document.getElementById('open-profile-modal');
  var form = document.getElementById('profile-account-form');
  var msgEl = document.getElementById('profile-form-message');
  var initialEl = document.getElementById('profile-modal-initial');
  var otpBlock = document.getElementById('profile-otp-block');
  var otpInput = document.getElementById('profile-email-otp');
  var verifyOtpBtn = document.getElementById('profile-verify-otp-btn');
  var saveBtn = document.getElementById('profile-save-btn');
  var newPw = document.getElementById('profile-new-password');
  var strengthLabel = document.getElementById('profile-pw-strength-label');
  var strengthBar = document.getElementById('profile-pw-strength-bar');
  var segs = strengthBar ? strengthBar.querySelectorAll('.profile-pw-seg') : [];
  var apiUrl = 'ProviderTenantProfileApi.php';

  function initialData() {
    try {
      return JSON.parse(initialEl.textContent || '{}');
    } catch (e) {
      return {};
    }
  }

  function setMsg(text, kind) {
    msgEl.textContent = text || '';
    msgEl.classList.remove('hidden', 'text-emerald-700', 'text-red-700', 'text-primary');
    if (!text) {
      msgEl.classList.add('hidden');
      return;
    }
    msgEl.classList.remove('hidden');
    if (kind === 'ok') msgEl.classList.add('text-emerald-700');
    else if (kind === 'err') msgEl.classList.add('text-red-700');
    else msgEl.classList.add('text-primary');
  }

  function openModal() {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
    var d = initialData();
    document.getElementById('profile-first-name').value = d.first_name || '';
    document.getElementById('profile-last-name').value = d.last_name || '';
    document.getElementById('profile-email').value = d.email || '';
    document.getElementById('profile-current-password').value = '';
    newPw.value = '';
    document.getElementById('profile-confirm-password').value = '';
    otpBlock.classList.add('hidden');
    if (otpInput) otpInput.value = '';
    setMsg('');
    updateStrength();
    setTimeout(function () {
      document.getElementById('profile-first-name').focus();
    }, 50);
  }

  function closeModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
  }

  function updateJsonInitial(partial) {
    var cur = initialData();
    Object.assign(cur, partial || {});
    initialEl.textContent = JSON.stringify(cur);
  }

  function initialsFromFullName(full) {
    var parts = (full || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'MD';
    var a = (parts[0].charAt(0) || '').toUpperCase();
    var b = parts[1] ? (parts[1].charAt(0) || '').toUpperCase() : (parts[0].charAt(1) || '').toUpperCase();
    return (a + b).slice(0, 2);
  }

  function scorePassword(pw) {
    var s = 0;
    if (pw.length >= 12) s++;
    if (/[A-Z]/.test(pw)) s++;
    if (/[a-z]/.test(pw)) s++;
    if (/[0-9]/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s;
  }

  function updateStrength() {
    var pw = newPw.value || '';
    if (!strengthLabel || !segs.length) return;
    if (!pw) {
      strengthLabel.textContent = 'Waiting';
      strengthLabel.className = 'text-[10px] font-bold uppercase tracking-wide text-slate-400';
      segs.forEach(function (el) {
        el.classList.remove('bg-primary', 'bg-amber-400', 'bg-emerald-500');
        el.classList.add('bg-slate-200');
      });
      return;
    }
    var sc = scorePassword(pw);
    var labels = ['Weak', 'Fair', 'Good', 'Strong', 'Excellent'];
    var label = labels[Math.max(0, sc - 1)] || 'Weak';
    strengthLabel.textContent = label;
    strengthLabel.className = 'text-[10px] font-bold uppercase tracking-wide ' + (sc >= 5 ? 'text-emerald-600' : sc >= 3 ? 'text-amber-600' : 'text-red-600');
    for (var i = 0; i < segs.length; i++) {
      segs[i].classList.remove('bg-slate-200', 'bg-primary', 'bg-amber-400', 'bg-emerald-500');
      if (i < sc) {
        if (sc >= 5) segs[i].classList.add('bg-emerald-500');
        else if (sc >= 3) segs[i].classList.add('bg-amber-400');
        else segs[i].classList.add('bg-primary');
      } else segs[i].classList.add('bg-slate-200');
    }
  }

  document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-pw-toggle');
      var inp = document.getElementById(id);
      if (!inp) return;
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      var icon = btn.querySelector('.material-symbols-outlined');
      if (icon) icon.textContent = show ? 'visibility_off' : 'visibility';
    });
  });

  if (openBtn) openBtn.addEventListener('click', openModal);
  modal.querySelectorAll('[data-profile-modal-dismiss]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });

  newPw.addEventListener('input', updateStrength);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    setMsg('');
    var fd = new FormData(form);
    var body = {
      action: 'save_profile',
      first_name: (fd.get('first_name') || '').toString().trim(),
      last_name: (fd.get('last_name') || '').toString().trim(),
      email: (fd.get('email') || '').toString().trim(),
      current_password: (fd.get('current_password') || '').toString(),
      new_password: (fd.get('new_password') || '').toString(),
      confirm_password: (fd.get('confirm_password') || '').toString()
    };
    saveBtn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); })
      .then(function (res) {
        if (res.j && res.j.ok) {
          setMsg(res.j.message || 'Saved.', 'ok');
          updateJsonInitial(res.j.user || {});
          if (res.j.user) {
            var nm = document.getElementById('header-account-name');
            var em = document.getElementById('header-account-email');
            var av = document.getElementById('header-account-avatar');
            if (nm) nm.textContent = res.j.user.full_name || 'Signed in';
            if (em) {
              em.textContent = res.j.user.email || '';
              em.classList.toggle('hidden', !res.j.user.email);
            }
            if (av && res.j.user.full_name) av.textContent = initialsFromFullName(res.j.user.full_name);
          }
          if (res.j.email_verification_sent) {
            otpBlock.classList.remove('hidden');
            if (otpInput) otpInput.focus();
          } else {
            setTimeout(closeModal, 900);
          }
        } else {
          setMsg((res.j && res.j.error) || 'Something went wrong.', 'err');
        }
      })
      .catch(function () { setMsg('Network error. Try again.', 'err'); })
      .finally(function () { saveBtn.disabled = false; });
  });

  if (verifyOtpBtn) {
    verifyOtpBtn.addEventListener('click', function () {
      var code = (otpInput && otpInput.value) ? otpInput.value.trim() : '';
      setMsg('');
      verifyOtpBtn.disabled = true;
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'verify_email_otp', otp_code: code })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (res.j && res.j.ok) {
            setMsg(res.j.message || 'Verified.', 'ok');
            otpBlock.classList.add('hidden');
            setTimeout(closeModal, 800);
          } else {
            setMsg((res.j && res.j.error) || 'Invalid code.', 'err');
          }
        })
        .catch(function () { setMsg('Network error.', 'err'); })
        .finally(function () { verifyOtpBtn.disabled = false; });
    });
  }
})();
</script>
</body></html>