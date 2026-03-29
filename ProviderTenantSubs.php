<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';
require_once __DIR__ . '/provider_tenant_plan_and_site_context.inc.php';
require_once __DIR__ . '/provider_tenant_header_context.inc.php';
$provider_nav_active = 'subs';

$subscription_financial_log = [];
try {
    $logStmt = $pdo->prepare("
        SELECT ts.id, ts.payment_status, ts.amount_paid, ts.payment_method, ts.reference_number, ts.created_at,
               ts.subscription_start,
               p.plan_name, p.plan_slug, p.price
        FROM tbl_tenant_subscriptions ts
        LEFT JOIN tbl_subscription_plans p ON p.plan_id = ts.plan_id
        WHERE ts.tenant_id = ?
        ORDER BY ts.id DESC
        LIMIT 100
    ");
    $logStmt->execute([(string) $tenant_id]);
    $subscription_financial_log = $logStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $subscription_financial_log = [];
}
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Subscription &amp; Billing</title>
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
                        "surface-variant": "#f1f5f9",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#cbd5e1",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#404752",
                        "background": "#f8fafc",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#8e4a00",
                        "tertiary-container": "#ffdcc3",
                        "error": "#ba1a1a"
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
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
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
        .primary-gradient {
            background: linear-gradient(135deg, #2b8beb 0%, #1a74d1 100%);
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
        body { font-family: 'Manrope', sans-serif; }
    </style>
</head>
<body class="font-body text-on-background mesh-bg min-h-screen selection:bg-primary/10">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<?php include __DIR__ . '/provider_tenant_top_header.inc.php'; ?>
<main class="ml-64 pt-[4.5rem] sm:pt-24 min-h-screen provider-page-enter">
<div class="pt-4 sm:pt-6 px-6 lg:px-10 pb-20 space-y-6">
<!-- Editorial Header Section -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> Subscription &amp; Billing</div>
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">Subscription <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span></h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">Manage your clinic's subscription, plans, and payment methods.</p>
</div>
</section>
<section class="grid grid-cols-1 md:grid-cols-3 gap-7 lg:gap-8" data-purpose="subscription-overview-stats">
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
<h3 class="text-2xl font-extrabold text-on-background mt-2 font-headline break-words leading-tight"><?php echo htmlspecialchars($plan_name, ENT_QUOTES, 'UTF-8'); ?></h3>
<div class="mt-5 space-y-3">
<?php if ($plan_billing_cycle_label !== ''): ?>
<p class="text-on-surface-variant text-sm font-medium"><?php echo htmlspecialchars($plan_billing_cycle_label, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php if ($has_subscription_row): ?>
<div class="mt-4 pt-4 border-t border-slate-200/80 grid grid-cols-1 gap-4 sm:grid-cols-3">
<div>
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-[0.2em] opacity-70">Subscription cost</p>
<p class="text-base font-extrabold text-on-background mt-1.5 tabular-nums"><?php echo htmlspecialchars($sub_payment_amount_display, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div>
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-[0.2em] opacity-70">Payment date</p>
<p class="text-sm font-bold text-on-background mt-1.5"><?php echo htmlspecialchars($sub_payment_date_display, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div>
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-[0.2em] opacity-70">Payment time</p>
<p class="text-sm font-medium text-on-surface-variant/80 mt-1.5 tabular-nums"><?php echo htmlspecialchars($sub_payment_time_display, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
</div>
<?php endif; ?>
<?php if ($has_subscription_row && $period_start_ts !== false && $renewal_ts !== false): ?>
<div class="w-full bg-slate-200/80 h-2.5 rounded-full overflow-hidden ring-1 ring-white/50">
<div class="bg-gradient-to-r from-primary to-sky-400 h-full rounded-full transition-all shadow-sm" style="width: <?php echo (int) $plan_period_util_pct; ?>%;"></div>
</div>
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-wide">Renewal <?php echo htmlspecialchars($renewal_date, ENT_QUOTES, 'UTF-8'); ?> · <?php echo (int) $plan_period_util_pct; ?>% period</p>
<?php elseif ($has_subscription_row): ?>
<p class="text-sm text-on-surface-variant"><?php echo htmlspecialchars($period_start_display, ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars($renewal_date, ENT_QUOTES, 'UTF-8'); ?></p>
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
<h3 class="text-xl font-extrabold text-on-background font-headline break-all leading-snug group-hover/link:text-primary transition-colors"><?php echo htmlspecialchars($domain_display, ENT_QUOTES, 'UTF-8'); ?></h3>
<span class="inline-flex items-center gap-1 text-primary text-xs font-bold uppercase tracking-wide mt-2">Open website <span class="material-symbols-outlined text-base transition-transform group-hover/link:translate-x-0.5 group-hover/link:-translate-y-0.5">open_in_new</span></span>
</a>
<?php else: ?>
<h3 class="text-xl font-extrabold text-on-background mt-2 font-headline"><?php echo htmlspecialchars($domain_display, ENT_QUOTES, 'UTF-8'); ?></h3>
<?php endif; ?>
<p class="text-sm text-on-surface-variant font-medium mt-3"><?php echo htmlspecialchars($hosting_status_label, ENT_QUOTES, 'UTF-8'); ?></p>
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
<section class="elevated-card rounded-2xl border border-slate-200/80 overflow-hidden">
<div class="px-6 sm:px-8 py-6 sm:py-7 border-b border-slate-100">
<p class="text-[11px] font-extrabold text-on-surface-variant/55 uppercase tracking-[0.28em]">Financial transmission log</p>
<p class="text-xs font-semibold text-on-surface-variant/45 uppercase tracking-widest mt-2">Audit trail of comprehensive membership payments</p>
</div>
<div class="overflow-x-auto">
<table class="w-full min-w-[720px] text-left border-collapse">
<thead>
<tr class="border-b border-slate-100">
<th class="px-6 sm:px-8 py-4 text-[10px] font-bold text-on-surface-variant/50 uppercase tracking-[0.2em] whitespace-nowrap">Audit ID</th>
<th class="px-4 py-4 text-[10px] font-bold text-on-surface-variant/50 uppercase tracking-[0.2em] whitespace-nowrap">Plan</th>
<th class="px-4 py-4 text-[10px] font-bold text-on-surface-variant/50 uppercase tracking-[0.2em] whitespace-nowrap">Amount</th>
<th class="px-4 py-4 text-[10px] font-bold text-on-surface-variant/50 uppercase tracking-[0.2em] whitespace-nowrap">Date paid</th>
<th class="px-4 py-4 text-[10px] font-bold text-on-surface-variant/50 uppercase tracking-[0.2em] whitespace-nowrap">Ref ID</th>
<th class="px-6 sm:px-8 py-4 text-[10px] font-bold text-on-surface-variant/50 uppercase tracking-[0.2em] whitespace-nowrap">Status</th>
</tr>
</thead>
<tbody class="text-on-background">
<?php if ($subscription_financial_log === []): ?>
<tr>
<td colspan="6" class="px-6 sm:px-8 py-12 text-center text-sm text-on-surface-variant/70 font-medium">No payment records yet.</td>
</tr>
<?php else: ?>
<?php foreach ($subscription_financial_log as $logRow): ?>
<?php
    $logId = (int) ($logRow['id'] ?? 0);
    $auditId = 'SUB-' . str_pad((string) max(1, $logId), 6, '0', STR_PAD_LEFT);
    $logPlanName = trim((string) ($logRow['plan_name'] ?? ''));
    if ($logPlanName === '') {
        $slugBit = trim((string) ($logRow['plan_slug'] ?? ''));
        $logPlanName = $slugBit !== '' ? ucwords(str_replace(['-', '_'], ' ', $slugBit)) : '—';
    }
    $logPlanUpper = strtoupper($logPlanName);
    $logAmtRaw = $logRow['amount_paid'] ?? null;
    $logAmt = is_numeric($logAmtRaw) ? (float) $logAmtRaw : null;
    if ($logAmt === null || $logAmt <= 0) {
        $pf = $logRow['price'] ?? null;
        $logAmt = is_numeric($pf) ? (float) $pf : null;
    }
    $logAmountDisp = ($logAmt !== null && $logAmt > 0) ? ('₱' . number_format($logAmt, 2, '.', ',')) : '—';
    $logCreated = trim((string) ($logRow['created_at'] ?? ''));
    $logPaidTs = $logCreated !== '' ? strtotime($logCreated) : false;
    if ($logPaidTs === false) {
        $ss = trim((string) ($logRow['subscription_start'] ?? ''));
        $logPaidTs = $ss !== '' ? strtotime($ss . ' 12:00:00') : false;
    }
    $logDateLine = $logPaidTs !== false ? date('M j, Y', $logPaidTs) : '—';
    $logTimeLine = $logPaidTs !== false ? date('g:i A', $logPaidTs) : '';
    $pm = strtolower(trim((string) ($logRow['payment_method'] ?? '')));
    $refNum = trim((string) ($logRow['reference_number'] ?? ''));
    if ($pm !== '') {
        $refPill = $pm === 'paymaya' ? 'paymaya' : $pm;
    } elseif ($refNum !== '') {
        $refPill = strlen($refNum) > 24 ? (substr($refNum, 0, 12) . '…') : $refNum;
    } else {
        $refPill = '—';
    }
    $ps = strtolower(trim((string) ($logRow['payment_status'] ?? '')));
    if (provider_dashboard_payment_means_paid($ps)) {
        $statusLabel = 'PAID';
        $statusClass = 'bg-emerald-50 text-emerald-800 ring-emerald-200/80';
    } elseif ($ps === 'pending') {
        $statusLabel = 'PENDING';
        $statusClass = 'bg-amber-50 text-amber-900 ring-amber-200/80';
    } elseif ($ps === 'failed') {
        $statusLabel = 'FAILED';
        $statusClass = 'bg-red-50 text-red-800 ring-red-200/80';
    } elseif ($ps === 'cancelled' || $ps === 'canceled') {
        $statusLabel = 'CANCELLED';
        $statusClass = 'bg-slate-100 text-on-surface-variant ring-slate-200/80';
    } else {
        $statusLabel = $ps !== '' ? strtoupper($ps) : '—';
        $statusClass = 'bg-slate-100 text-on-surface-variant ring-slate-200/80';
    }
?>
<tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50/60 transition-colors">
<td class="px-6 sm:px-8 py-4 align-top whitespace-nowrap font-mono text-xs text-on-surface-variant"><?php echo htmlspecialchars($auditId, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-4 py-4 align-top font-extrabold text-sm uppercase tracking-tight"><?php echo htmlspecialchars($logPlanUpper, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-4 py-4 align-top font-bold tabular-nums text-sm"><?php echo htmlspecialchars($logAmountDisp, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-4 py-4 align-top">
<span class="block font-bold text-sm"><?php echo htmlspecialchars($logDateLine, ENT_QUOTES, 'UTF-8'); ?></span>
<?php if ($logTimeLine !== ''): ?><span class="block text-xs text-on-surface-variant/65 mt-0.5 tabular-nums"><?php echo htmlspecialchars($logTimeLine, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
</td>
<td class="px-4 py-4 align-top">
<?php if ($refPill !== '—'): ?>
<span class="inline-flex items-center rounded-full bg-slate-100 text-on-surface-variant text-[11px] font-semibold px-3 py-1 ring-1 ring-slate-200/80"><?php echo htmlspecialchars($refPill, ENT_QUOTES, 'UTF-8'); ?></span>
<?php else: ?>
<span class="text-on-surface-variant/50 text-sm">—</span>
<?php endif; ?>
</td>
<td class="px-6 sm:px-8 py-4 align-top">
<span class="inline-flex items-center rounded-full text-[10px] font-extrabold uppercase tracking-wider px-3 py-1 ring-1 <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
</div>
<!-- Footer Status -->
<footer class="mt-auto p-8 flex justify-center sticky bottom-0 z-10 pointer-events-none">
<div class="elevated-card pointer-events-auto px-10 py-4 rounded-full border border-slate-200/50 shadow-2xl flex items-center gap-10 text-[10px] font-black text-on-surface-variant/70 uppercase tracking-[0.2em]">
<div class="flex items-center gap-3 text-primary">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    System Log: Real-time
                </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">schedule</span>
                    Last Login: 10:24 AM
                </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">location_on</span>
                    IP: 192.168.1.1
                </div>
</div>
</footer>
</main>
<?php include __DIR__ . '/provider_tenant_profile_modal.inc.php'; ?>
</body></html>