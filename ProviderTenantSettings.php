<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';

$clinic_settings_saved = false;
$clinic_settings_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['form'] ?? '') === 'clinic_details') {
    try {
        if ($is_owner) {
            $cn = trim((string) ($_POST['clinic_name'] ?? ''));
            $ce = trim((string) ($_POST['clinic_email'] ?? ''));
            $cp = trim((string) ($_POST['clinic_phone'] ?? ''));
            $ca = trim((string) ($_POST['clinic_address'] ?? ''));
            if ($cn !== '') {
                $stmt = $pdo->prepare('UPDATE tbl_tenants SET clinic_name = ?, contact_email = ?, contact_phone = ?, clinic_address = ? WHERE tenant_id = ?');
                $stmt->execute([$cn, $ce, $cp, $ca, (string) $tenant_id]);
            }
        }
        $clinic_settings_saved = true;
    } catch (Throwable $e) {
        $clinic_settings_error = 'Could not save settings. Please try again.';
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
    $stmt->execute([(string) $tenant_id]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $tenant = [];
}

if ($clinic_settings_saved) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.tenant_id, t.clinic_name, t.clinic_slug, t.contact_email, t.contact_phone, t.clinic_address, t.subscription_status,
                   u.full_name AS owner_name, u.email AS owner_email, u.phone AS owner_phone
            FROM tbl_tenants t
            LEFT JOIN tbl_users u ON t.owner_user_id = u.user_id
            WHERE t.tenant_id = ?
            LIMIT 1
        ");
        $stmt->execute([(string) $tenant_id]);
        $refetched = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($refetched) && $refetched !== []) {
            $tenant = $refetched;
        }
    } catch (Throwable $e) {
    }
}

if ($tenant === []) {
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

require_once __DIR__ . '/provider_tenant_plan_and_site_context.inc.php';
require_once __DIR__ . '/provider_tenant_header_context.inc.php';

$provider_nav_active = 'settings';
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Settings</title>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-variant": "#f7f9ff",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#c0c7d4",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#404752",
                        "background": "#f1f5f9",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#8e4a00",
                        "tertiary-container": "#ffdcc3",
                        "error": "#ba1a1a",
                        "error-container": "#fff1f2"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        html {
            scrollbar-gutter: stable;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .section-card {
            border: 1px solid rgba(0, 0, 0, 0.04);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease, border-color 0.25s ease;
        }
        .section-card:hover {
            box-shadow: 0 24px 40px -12px rgba(15, 23, 42, 0.12), 0 12px 16px -8px rgba(43, 139, 235, 0.06);
            transform: translateY(-5px);
            border-color: rgba(43, 139, 235, 0.12);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
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
        body { font-family: 'Manrope', sans-serif; }
        
        /* Consistent border styling for inputs, textareas, and selects */
        .form-input-styled {
            border: 1px solid #d1d5db !important; /* light gray/slate-300 */
        }
        .form-input-styled:focus {
            border-color: #2b8beb !important; /* primary color on focus */
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
        @media (max-width: 1023.98px) {
            .provider-top-header {
                left: 0 !important;
                min-height: 5rem;
            }
            #provider-sidebar {
                transform: translateX(-100%);
                transition: transform 220ms ease;
                z-index: 60;
                background: #ffffff;
                backdrop-filter: none;
                -webkit-backdrop-filter: none;
                border-right: 1px solid #e2e8f0;
            }
            body.provider-mobile-sidebar-open #provider-sidebar {
                transform: translateX(0);
            }
            #provider-mobile-sidebar-toggle {
                transition: left 220ms ease, background-color 220ms ease, color 220ms ease;
            }
            body.provider-mobile-sidebar-open #provider-mobile-sidebar-toggle {
                left: calc(16rem - 3.25rem);
                background: rgba(255, 255, 255, 0.98);
                color: #0066ff;
            }
            #provider-mobile-sidebar-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(19, 28, 37, 0.45);
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
                z-index: 55;
                opacity: 0;
                pointer-events: none;
                transition: opacity 220ms ease;
            }
            body.provider-mobile-sidebar-open #provider-mobile-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>
</head>
<body class="font-body text-on-background mesh-bg min-h-screen selection:bg-primary/10">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<?php include __DIR__ . '/provider_tenant_top_header.inc.php'; ?>
<button id="provider-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="provider-sidebar" aria-expanded="false" aria-label="Open navigation menu">
<span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="provider-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<main class="ml-0 lg:ml-64 pt-[4.75rem] sm:pt-24 min-h-screen provider-page-enter">
<div class="pt-4 sm:pt-6 px-6 lg:px-10 pb-20 max-w-7xl mx-auto w-full space-y-12">
<!-- Header -->
<section class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> Configuration</div>
<div>
<h2 class="font-headline text-4xl sm:text-5xl font-extrabold tracking-tighter leading-tight text-on-background">System <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Configuration</span></h2>
<p class="font-body text-base sm:text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">Manage your clinic's public identity and monitor infrastructure status from this centralized dashboard.</p>
</div>
</section>
<!-- Grid Layout -->
<div class="max-w-4xl">
<!-- Clinic Details -->
<div class="section-card bg-white rounded-[2.5rem] p-10 border-l-4 border-l-primary">
<div class="flex items-center gap-5 mb-10">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-2xl">apartment</span>
</div>
<div>
<h4 class="font-headline text-xl font-extrabold text-slate-900">Clinic Details</h4>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Public information for your dental practice</p>
</div>
</div>
<?php if ($clinic_settings_saved): ?>
<div class="mb-6 p-4 bg-emerald-50 border border-emerald-200/80 text-emerald-800 rounded-2xl text-sm font-medium">Clinic details saved successfully.</div>
<?php endif; ?>
<?php if ($clinic_settings_error !== ''): ?>
<div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm"><?php echo htmlspecialchars($clinic_settings_error); ?></div>
<?php endif; ?>
<?php if (!$is_owner): ?>
<div class="mb-6 p-4 bg-slate-50 border border-slate-200 text-on-surface-variant rounded-2xl text-sm font-medium">Only the clinic owner can edit these details. Contact your owner if something needs updating.</div>
<?php endif; ?>
<form class="space-y-8" method="post" action="" id="clinic-details-form" data-purpose="clinic-details-form">
<input type="hidden" name="form" value="clinic_details"/>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
<div class="space-y-2 md:col-span-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="clinic_name">Clinic Name</label>
<input class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="text" id="clinic_name" name="clinic_name" placeholder="Clinic name" value="<?php echo htmlspecialchars((string) ($tenant['clinic_name'] ?? '')); ?>" <?php echo $is_owner ? '' : 'readonly disabled'; ?>/>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="clinic_email">Clinic Email</label>
<input class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all <?php echo $is_owner ? '' : 'opacity-70 cursor-not-allowed'; ?>" type="email" id="clinic_email" name="clinic_email" placeholder="Clinic email" value="<?php echo htmlspecialchars((string) ($tenant['contact_email'] ?? '')); ?>" <?php echo $is_owner ? '' : 'readonly disabled'; ?>/>
</div>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="clinic_phone">Phone Number</label>
<input class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all <?php echo $is_owner ? '' : 'opacity-70 cursor-not-allowed'; ?>" type="tel" id="clinic_phone" name="clinic_phone" placeholder="Clinic phone" value="<?php echo htmlspecialchars((string) ($tenant['contact_phone'] ?? '')); ?>" <?php echo $is_owner ? '' : 'readonly disabled'; ?>/>
</div>
<div class="space-y-2 md:col-span-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="clinic_address">Address</label>
<textarea class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-6 py-4 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all min-h-[5rem] <?php echo $is_owner ? '' : 'opacity-70 cursor-not-allowed'; ?>" id="clinic_address" name="clinic_address" placeholder="Address" rows="3" <?php echo $is_owner ? '' : 'readonly disabled'; ?>><?php echo htmlspecialchars((string) ($tenant['clinic_address'] ?? '')); ?></textarea>
</div>
</div>
<div class="pt-4 flex justify-end">
<button class="bg-primary text-white px-10 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:shadow-xl hover:shadow-primary/25 transition-all duration-300 active:scale-95 hover:scale-[1.02] disabled:opacity-50 disabled:pointer-events-none" type="submit" <?php echo $is_owner ? '' : 'disabled'; ?>>Save Changes</button>
</div>
</form>
</div>
</div>
<section class="w-full" aria-labelledby="infra-status-heading">
<div class="dash-infra-panel backdrop-blur-xl p-8 sm:p-10 rounded-[2rem] border border-white/90">
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-8">
<h4 id="infra-status-heading" class="text-2xl font-extrabold font-headline text-on-background">Infrastructure <span class="text-primary italic font-editorial">Status</span></h4>
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
</section>
</div>
<!-- Footer -->
<footer class="mt-auto p-10 border-t border-on-surface/5 hidden lg:flex flex-col md:flex-row items-center justify-between gap-10">
<div class="flex items-center gap-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
<span class="material-symbols-outlined text-xl">verified</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 text-sm">Aetheris Dental System v2.4.0</p>
<div class="flex items-center gap-2 mt-1">
<span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span>
<span class="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Cloud Status: Optimal</span>
</div>
</div>
</div>
<div class="flex gap-10">
<a class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 hover:text-primary transition-colors" href="#">Privacy Policy</a>
<a class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 hover:text-primary transition-colors" href="#">Terms of Service</a>
<a class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 hover:text-primary transition-colors" href="#">Compliance (HIPAA)</a>
</div>
</footer>
</main>
<?php include __DIR__ . '/provider_tenant_profile_modal.inc.php'; ?>
<script>
(function () {
  var body = document.body;
  var sidebar = document.getElementById('provider-sidebar');
  var mobileToggle = document.getElementById('provider-mobile-sidebar-toggle');
  var mobileBackdrop = document.getElementById('provider-mobile-sidebar-backdrop');
  var desktopQuery = window.matchMedia('(min-width: 1024px)');

  if (!body || !sidebar || !mobileToggle || !mobileBackdrop) {
    return;
  }

  function setMobileSidebar(open) {
    body.classList.toggle('provider-mobile-sidebar-open', open);
    mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    mobileToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    var icon = mobileToggle.querySelector('.material-symbols-outlined');
    if (icon) {
      icon.textContent = open ? 'close' : 'menu';
    }
  }

  function closeOnDesktop() {
    if (desktopQuery.matches) {
      setMobileSidebar(false);
    }
  }

  mobileToggle.addEventListener('click', function () {
    setMobileSidebar(!body.classList.contains('provider-mobile-sidebar-open'));
  });
  mobileBackdrop.addEventListener('click', function () {
    setMobileSidebar(false);
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && body.classList.contains('provider-mobile-sidebar-open')) {
      setMobileSidebar(false);
    }
  });
  sidebar.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      if (!desktopQuery.matches) {
        setMobileSidebar(false);
      }
    });
  });
  if (typeof desktopQuery.addEventListener === 'function') {
    desktopQuery.addEventListener('change', closeOnDesktop);
  } else if (typeof desktopQuery.addListener === 'function') {
    desktopQuery.addListener(closeOnDesktop);
  }

  setMobileSidebar(false);
})();
</script>
</body></html>