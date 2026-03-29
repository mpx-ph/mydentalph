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
<div class="pt-3 sm:pt-4 px-10 pb-20 space-y-10 relative">
<div class="absolute top-24 right-8 w-[28rem] h-[28rem] bg-primary/10 rounded-full blur-[120px] -z-10 pointer-events-none" aria-hidden="true"></div>
<div class="absolute bottom-40 left-10 w-72 h-72 bg-teal-400/10 rounded-full blur-[100px] -z-10 pointer-events-none" aria-hidden="true"></div>
<section class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 lg:gap-8">
<div class="max-w-3xl">
<p class="text-primary font-bold text-[10px] sm:text-xs uppercase tracking-[0.35em] flex items-center gap-3 mb-3"><span class="w-8 sm:w-10 h-px bg-primary/40"></span> Provider dashboard</p>
<h2 class="text-5xl sm:text-6xl font-extrabold font-headline tracking-tight text-on-background">Clinic <span class="font-editorial italic font-normal text-primary editorial-word">Overview</span></h2>
<p class="text-on-surface-variant mt-3 sm:mt-4 text-lg sm:text-xl font-medium max-w-2xl leading-relaxed">Welcome back, <span class="text-on-background font-semibold"><?php echo htmlspecialchars($welcome_name); ?></span>. Open clinic management, manage subscription and site details under Subscription &amp; Billing, and keep account details up to date in Settings.</p>
</div>
<div class="flex items-center gap-3 shrink-0">
<a
  class="bg-primary text-white px-8 py-3.5 rounded-2xl text-sm font-bold primary-glow inline-flex items-center gap-2.5 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all text-center ring-2 ring-primary/20 ring-offset-2 ring-offset-background shadow-lg shadow-primary/20"
  id="open-dashboard-btn"
  data-purpose="primary-action-card"
  href="<?php echo $admin_dashboard_url ? htmlspecialchars($admin_dashboard_url, ENT_QUOTES, 'UTF-8') : '#'; ?>"
  <?php if ($has_visible_website && $admin_dashboard_url): ?>target="_blank" rel="noopener noreferrer"<?php endif; ?>
>
<span class="material-symbols-outlined text-xl">dashboard</span>
  <?php echo $has_visible_website ? 'Open Clinic Management' : 'No Active Website'; ?>
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