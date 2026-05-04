<?php
declare(strict_types=1);
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

function subscriptions_build_query(array $base, array $overrides = []): string
{
    $merged = array_merge($base, $overrides);
    $parts = [];
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        if ($k === 'page' && (int) $v === 1) {
            continue;
        }
        $parts[$k] = $v;
    }

    return http_build_query($parts);
}

function subscriptions_url(array $base, array $overrides = []): string
{
    $q = subscriptions_build_query($base, $overrides);

    return $q === '' ? 'subscriptions.php' : ('subscriptions.php?' . $q);
}

function subscriptions_parse_date(?string $input): ?string
{
    if ($input === null) {
        return null;
    }
    $trim = trim($input);
    if ($trim === '') {
        return null;
    }
    $ts = strtotime($trim);
    if ($ts === false) {
        return null;
    }

    return date('Y-m-d', $ts);
}

function subscriptions_money(?float $amount): string
{
    if ($amount === null) {
        return '—';
    }

    return '₱' . number_format($amount, 2, '.', ',');
}

function subscriptions_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    $sub = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $up = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        $a = $sub($parts[0], 0, 1);
        $b = $sub($parts[count($parts) - 1], 0, 1);

        return $up($a . $b);
    }

    return $up($sub($name, 0, min(2, $len)));
}

/**
 * Canonical subscription lifecycle label for tenant + subscription row.
 *
 * @return 'active'|'expired'|'cancelled'|'suspended'
 */
function subscriptions_derive_display_status(array $row): string
{
    $tenantSt = strtolower(trim((string) ($row['tenant_subscription_status'] ?? '')));
    $paySt = strtolower(trim((string) ($row['payment_status'] ?? '')));
    $endRaw = trim((string) ($row['subscription_end'] ?? ''));
    $endPassed = false;
    if ($endRaw !== '') {
        $endTs = strtotime($endRaw);
        $today = strtotime(date('Y-m-d'));
        if ($endTs !== false && $today !== false && $endTs < $today) {
            $endPassed = true;
        }
    }
    if ($tenantSt === 'suspended') {
        return 'suspended';
    }
    if ($paySt === 'cancelled') {
        return 'cancelled';
    }
    if (
        $tenantSt === 'active'
        && $paySt === 'paid'
        && ($endRaw === '' || !$endPassed)
    ) {
        return 'active';
    }

    return 'expired';
}

function subscriptions_status_badge(string $displayStatus): string
{
    switch ($displayStatus) {
        case 'active':
            return '<span class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-[10px] font-bold uppercase tracking-wider">Active</span>';
        case 'expired':
            return '<span class="px-3 py-1.5 bg-amber-50 text-amber-700 rounded-xl text-[10px] font-bold uppercase tracking-wider">Expired</span>';
        case 'cancelled':
            return '<span class="px-3 py-1.5 bg-rose-50 text-rose-700 rounded-xl text-[10px] font-bold uppercase tracking-wider">Cancelled</span>';
        case 'suspended':
            return '<span class="px-3 py-1.5 bg-error-container/20 text-error rounded-xl text-[10px] font-bold uppercase tracking-wider">Suspended</span>';
        default:
            return '<span class="px-3 py-1.5 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-bold uppercase tracking-wider">' . htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') . '</span>';
    }
}

function subscriptions_format_date_disp(?string $d): string
{
    if ($d === null || trim($d) === '') {
        return '—';
    }
    $t = strtotime($d);
    if ($t === false) {
        return '—';
    }

    return date('M j, Y', $t);
}

/**
 * @param array<string, mixed> $row
 */
function subscriptions_cycle_label(array $row): string
{
    $raw = strtolower(trim((string) ($row['billing_cycle'] ?? '')));
    return $raw === '' ? '—' : $raw;
}

/**
 * @param array<string, mixed> $row
 * @param 'screen'|'print' $variant
 *
 * @return array{label: string, class: string}
 */
function subscriptions_status_visual(array $row, string $variant): array
{
    $tenantSt = strtolower(trim((string) ($row['tenant_subscription_status'] ?? '')));
    $paySt = strtolower(trim((string) ($row['payment_status'] ?? '')));
    $disp = subscriptions_derive_display_status($row);
    $labelBase = match ($disp) {
        'active' => 'ACTIVE',
        'expired' => 'EXPIRED',
        'cancelled' => 'CANCELLED',
        'suspended' => 'SUSPENDED',
        default => strtoupper((string) $disp),
    };
    if ($variant === 'print' && in_array($paySt, ['pending', 'failed'], true) && $tenantSt !== 'suspended') {
        return ['label' => 'TRIAL', 'class' => 'subs-p-stat subs-p-stat--trial'];
    }
    return match ($disp) {
        'active' => ['label' => $labelBase, 'class' => 'subs-p-stat subs-p-stat--active'],
        'expired' => ['label' => $labelBase, 'class' => 'subs-p-stat subs-p-stat--expired'],
        'cancelled', 'suspended' => ['label' => $labelBase, 'class' => 'subs-p-stat'],
        default => ['label' => $labelBase, 'class' => 'subs-p-stat subs-p-stat--expired'],
    };
}

/**
 * @param array<string, mixed> $row
 */
function subscriptions_owner_email_cell(array $row): string
{
    $email = trim((string) ($row['owner_email'] ?? ''));

    return $email !== '' ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '—';
}

/**
 * Renders AJAX-replaceable table body (pagination, mobile cards, desktop table).
 *
 * @param array<int, array<string, mixed>> $subsRows
 * @param array<string, string> $filterBase
 */
function subscriptions_render_table_fragment(
    ?string $dbError,
    array $subsRows,
    int $totalRows,
    int $rangeStart,
    int $rangeEnd,
    int $page,
    int $totalPages,
    array $filterBase
): void {
    ?>
<div class="px-5 sm:px-6 lg:px-8 py-5 flex flex-wrap items-center justify-between gap-3 border-b border-white/50">
<p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-75">
                        Showing <span class="text-primary opacity-100"><?php echo $totalRows === 0 ? '0' : number_format($rangeStart) . '–' . number_format($rangeEnd); ?></span>
                        of <?php echo number_format($totalRows); ?> subscriptions
                    </p>
<p class="text-sm font-semibold text-on-surface-variant">Page <span id="subs-page-indicator"><?php echo (int) $page; ?></span> / <?php echo (int) $totalPages; ?></p>
</div>
<div class="md:hidden px-4 sm:px-6 py-5 space-y-4 subs-table-swap-cards">
<?php if ($dbError !== null): ?>
<div class="rounded-2xl border border-error/20 bg-error/10 px-4 py-5 text-sm text-error font-medium">Unable to load subscriptions.</div>
<?php elseif ($subsRows === []): ?>
<div class="rounded-2xl border border-outline-variant/20 bg-white/70 px-4 py-5 text-sm text-on-surface-variant font-medium">No subscriptions match your filters.</div>
<?php else: ?>
<?php foreach ($subsRows as $row):
        $dispStatus = subscriptions_derive_display_status($row);
        $badge = subscriptions_status_badge($dispStatus);
        $planName = trim((string) ($row['plan_name'] ?? ''));
        $ownerName = trim((string) ($row['owner_name'] ?? ''));
        $ownerEmail = trim((string) ($row['owner_email'] ?? ''));
        $payOk = strtolower((string) ($row['payment_status'] ?? '')) === 'paid';
        $nextBillingStr = $dispStatus === 'active'
            ? subscriptions_format_date_disp($row['subscription_end'] ?? null)
            : '—';
        $lastAmt = subscriptions_money(isset($row['amount_paid']) ? (float) $row['amount_paid'] : null);
?>
<article class="rounded-2xl bg-white/80 border border-white/70 shadow-sm p-4 space-y-3">
<div class="flex items-start justify-between gap-2">
<div>
<p class="text-xs font-bold text-on-surface-variant uppercase tracking-wide">Clinic</p>
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars((string) ($row['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<?php echo $badge; ?>
</div>
<div class="flex gap-3">
<div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-primary text-[10px] font-bold shrink-0"><?php echo htmlspecialchars(subscriptions_initials($ownerName !== '' ? $ownerName : (string) ($row['clinic_name'] ?? '?')), ENT_QUOTES, 'UTF-8'); ?></div>
<div class="min-w-0">
<p class="text-sm font-semibold text-on-surface truncate"><?php echo htmlspecialchars($ownerName !== '' ? $ownerName : '—', ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[11px] text-on-surface-variant truncate"><?php echo $ownerEmail !== '' ? htmlspecialchars($ownerEmail, ENT_QUOTES, 'UTF-8') : '—'; ?></p>
</div>
</div>
<div class="grid grid-cols-2 gap-3 text-xs">
<div>
<p class="text-[10px] font-bold uppercase text-on-surface-variant/70 tracking-wide">Plan</p>
<p class="font-semibold text-primary mt-0.5"><?php echo $planName !== '' ? htmlspecialchars($planName, ENT_QUOTES, 'UTF-8') : '—'; ?></p>
</div>
<div>
<p class="text-[10px] font-bold uppercase text-on-surface-variant/70 tracking-wide">Next billing</p>
<p class="font-medium text-on-surface mt-0.5"><?php echo htmlspecialchars($nextBillingStr, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div class="col-span-2">
<p class="text-[10px] font-bold uppercase text-on-surface-variant/70 tracking-wide">Last payment</p>
<p class="font-bold text-on-surface mt-0.5"><?php echo $payOk ? $lastAmt : '—'; ?></p>
</div>
</div>
</article>
<?php endforeach; ?>
<?php endif; ?>
</div>
<div class="hidden md:block overflow-x-auto subs-table-swap-desktop">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Clinic Name</th>
<th class="px-8 py-5">Owner</th>
<th class="px-8 py-5">Plan</th>
<th class="px-8 py-5">Status</th>
<th class="px-8 py-5">Next Billing</th>
<th class="px-10 py-5 text-right">Last Payment</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if ($dbError !== null): ?>
<tr>
<td colspan="6" class="px-10 py-12 text-center text-sm text-error font-medium">Unable to load subscriptions.</td>
</tr>
<?php elseif ($subsRows === []): ?>
<tr>
<td colspan="6" class="px-10 py-12 text-center text-sm text-on-surface-variant font-medium">No subscriptions match your filters.</td>
</tr>
<?php else: ?>
<?php foreach ($subsRows as $row):
        $dispStatus = subscriptions_derive_display_status($row);
        $badge = subscriptions_status_badge($dispStatus);
        $planName = trim((string) ($row['plan_name'] ?? ''));
        $ownerName = trim((string) ($row['owner_name'] ?? ''));
        $ownerEmail = trim((string) ($row['owner_email'] ?? ''));
        $displayOwner = $ownerName !== '' ? $ownerName : '—';
        $payOk = strtolower((string) ($row['payment_status'] ?? '')) === 'paid';
        $nextBillingStr = $dispStatus === 'active'
            ? subscriptions_format_date_disp($row['subscription_end'] ?? null)
            : '—';
        $lastAmt = subscriptions_money(isset($row['amount_paid']) ? (float) $row['amount_paid'] : null);
?>
<tr class="hover:bg-primary/5 transition-colors">
<td class="px-10 py-5">
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars((string) ($row['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-on-surface-variant font-medium mt-0.5"><?php echo htmlspecialchars((string) ($row['tenant_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-8 py-5">
<div class="flex items-center gap-3">
<div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-primary text-[10px] font-bold border-2 border-white shadow-sm shrink-0"><?php echo htmlspecialchars(subscriptions_initials($displayOwner !== '—' ? $displayOwner : (string) ($row['clinic_name'] ?? '?')), ENT_QUOTES, 'UTF-8'); ?></div>
<div>
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($displayOwner, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-on-surface-variant font-medium"><?php echo $ownerEmail !== '' ? htmlspecialchars($ownerEmail, ENT_QUOTES, 'UTF-8') : '—'; ?></p>
</div>
</div>
</td>
<td class="px-8 py-5">
<?php if ($planName !== ''): ?>
<span class="text-sm font-bold text-primary"><?php echo htmlspecialchars($planName, ENT_QUOTES, 'UTF-8'); ?></span>
<?php else: ?>
<span class="text-sm font-medium text-on-surface-variant/60">—</span>
<?php endif; ?>
</td>
<td class="px-8 py-5"><?php echo $badge; ?></td>
<td class="px-8 py-5 text-sm font-medium text-on-surface-variant"><?php echo htmlspecialchars($nextBillingStr, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-10 py-5 text-right text-sm font-bold text-on-surface"><?php echo $payOk ? $lastAmt : '—'; ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="px-4 sm:px-6 lg:px-10 py-8 flex flex-wrap items-center justify-between gap-4 border-t border-white/50 subs-table-pager">
<?php if ($page > 1): ?>
<a href="<?php echo htmlspecialchars(subscriptions_url($filterBase, ['page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>" class="subs-pjax-link px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2" data-subs-pjax="1">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </a>
<?php else: ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant text-sm font-bold rounded-xl border border-white/60 shadow-sm flex items-center gap-2 opacity-40 cursor-not-allowed">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </span>
<?php endif; ?>
<p class="text-sm font-bold text-on-surface order-first sm:order-none w-full sm:w-auto text-center sm:text-left">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></p>
<?php if ($page < $totalPages): ?>
<a href="<?php echo htmlspecialchars(subscriptions_url($filterBase, ['page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>" class="subs-pjax-link px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2" data-subs-pjax="1">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
                </a>
<?php else: ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant text-sm font-bold rounded-xl border border-white/60 shadow-sm flex items-center gap-2 opacity-40 cursor-not-allowed">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
                </span>
<?php endif; ?>
</div>
    <?php
}

/**
 * Standalone minimalist print preview (filtered list, up to capped rows).
 *
 * @param array<int, array<string, mixed>> $printRows
 */
function subscriptions_render_print_view(
    string $systemName,
    ?string $dbError,
    array $printRows,
    int $totalFiltered,
    bool $truncated,
    float $metricTotalRevenue,
    int $metricActivePlans,
    int $metricTrials
): void {
    $generated = strtoupper(date('M j, Y \a\t h:i A'));
    $brandEsc = htmlspecialchars($systemName, ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title><?php echo $brandEsc; ?> Subscriptions List</title>
<style>
@page { margin: 14mm 16mm; }
* { box-sizing: border-box; }
body {
    margin: 0;
    padding: 28px 32px 40px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px;
    color: #111827;
    background: #fff;
}
.subs-ph1 { margin: 0 0 6px 0; font-size: 1.65rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.15; color: #0f172a; }
.subs-ph1-accent { font-weight: 700; color: #0d9488; }
.subs-p-gen { margin: 0 0 28px 0; font-size: 10px; font-weight: 600; letter-spacing: 0.14em; text-transform: uppercase; color: #94a3b8; }
.subs-p-cards { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
.subs-p-card {
    flex: 1 1 140px;
    min-width: 120px;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px 18px;
    text-align: center;
    background: #fff;
}
.subs-p-card-label {
    margin: 0 0 8px 0;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #64748b;
}
.subs-p-card-val { margin: 0; font-size: 1.75rem; font-weight: 800; color: #0f172a; line-height: 1.15; }
.subs-p-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.subs-p-table th {
    text-align: left;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #475569;
    padding: 10px 8px 10px 0;
    border-bottom: 1px solid #cbd5e1;
}
.subs-p-table td {
    padding: 12px 8px 12px 0;
    vertical-align: top;
    border-bottom: 1px solid #e2e8f0;
    word-wrap: break-word;
}
.subs-p-table th:last-child, .subs-p-table td:last-child { text-align: right; padding-right: 0; padding-left: 8px; }
.subs-p-stat { display: inline-block; margin: 0; font-weight: 800; font-size: 11px; letter-spacing: 0.06em; }
.subs-p-stat--active { color: #166534; }
.subs-p-stat--trial { color: #9a3412; }
.subs-p-stat--expired { color: #64748b; }
.subs-p-note { margin: 16px 0 0; font-size: 11px; color: #64748b; }
.subs-p-err { padding: 16px; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: 8px; }
.subs-p-cycle { text-transform: lowercase; color: #334155; }
@media print {
    body { padding: 0; }
    .subs-p-no-print { display: none; }
}
</style>
</head>
<body>
<?php if ($dbError !== null): ?>
<p class="subs-p-err">Could not load subscription data for print. Please return to the subscriptions page and try again.</p>
<?php else: ?>
<h1 class="subs-ph1"><span class="subs-ph1-brand"><?php echo $brandEsc; ?></span> <span class="subs-ph1-accent">Subscriptions List</span></h1>
<p class="subs-p-gen">Generated on <?php echo htmlspecialchars($generated, ENT_QUOTES, 'UTF-8'); ?></p>
<div class="subs-p-cards">
<div class="subs-p-card">
<p class="subs-p-card-label">Total</p>
<p class="subs-p-card-val"><?php echo number_format($totalFiltered); ?></p>
</div>
<div class="subs-p-card">
<p class="subs-p-card-label">Active</p>
<p class="subs-p-card-val"><?php echo number_format($metricActivePlans); ?></p>
</div>
<div class="subs-p-card">
<p class="subs-p-card-label">Trials</p>
<p class="subs-p-card-val"><?php echo number_format($metricTrials); ?></p>
</div>
<div class="subs-p-card">
<p class="subs-p-card-label">Revenue</p>
<p class="subs-p-card-val"><?php echo '₱' . number_format($metricTotalRevenue, 0, '.', ','); ?></p>
</div>
</div>
<table class="subs-p-table">
<thead>
<tr>
<th>Clinic</th>
<th>Owner</th>
<th>Plan</th>
<th>Cycle</th>
<th>Status</th>
<th>Next billing</th>
<th>Last payment</th>
</tr>
</thead>
<tbody>
<?php if ($printRows === []): ?>
<tr><td colspan="7" style="border:none;padding:20px 0;color:#64748b;">No subscriptions match the current filters.</td></tr>
<?php else: ?>
<?php foreach ($printRows as $row):
        $disp = subscriptions_derive_display_status($row);
        $sv = subscriptions_status_visual($row, 'print');
        $payOk = strtolower((string) ($row['payment_status'] ?? '')) === 'paid';
        $nextB = $disp === 'active'
            ? htmlspecialchars(subscriptions_format_date_disp($row['subscription_end'] ?? null), ENT_QUOTES, 'UTF-8')
            : '—';
        $lastP = $payOk
            ? subscriptions_money(isset($row['amount_paid']) ? (float) $row['amount_paid'] : null)
            : '—';
        $planN = trim((string) ($row['plan_name'] ?? ''));
        $cycle = htmlspecialchars(subscriptions_cycle_label($row), ENT_QUOTES, 'UTF-8');
?>
<tr>
<td><?php echo htmlspecialchars((string) ($row['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
<td><?php echo subscriptions_owner_email_cell($row); ?></td>
<td><?php echo $planN !== '' ? htmlspecialchars($planN, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
<td><span class="subs-p-cycle"><?php echo $cycle; ?></span></td>
<td><span class="<?php echo htmlspecialchars($sv['class'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($sv['label'], ENT_QUOTES, 'UTF-8'); ?></span></td>
<td><?php echo $nextB; ?></td>
<td><?php echo $lastP; ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
<?php if ($truncated): ?>
<p class="subs-p-note">Showing the first <?php echo number_format(count($printRows)); ?> rows of <?php echo number_format($totalFiltered); ?> matching subscriptions. Refine filters for a shorter list.</p>
<?php endif; ?>
<?php endif; ?>
<p class="subs-p-note subs-p-no-print" style="margin-top:28px"><button type="button" onclick="window.print()" style="padding:10px 20px;border-radius:8px;border:1px solid #cbd5e1;background:#f8fafc;font-weight:700;cursor:pointer">Print</button></p>
</body>
</html>
    <?php
}

$page = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 12;

$searchQ = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$filterClinic = isset($_GET['clinic']) ? trim((string) $_GET['clinic']) : '';
$filterPlan = isset($_GET['plan']) ? trim((string) $_GET['plan']) : '';
$filterStatus = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
$allowedStatus = ['', 'active', 'expired', 'cancelled', 'suspended'];
if ($filterStatus !== '' && !in_array($filterStatus, $allowedStatus, true)) {
    $filterStatus = '';
}

$filterDateFrom = subscriptions_parse_date(isset($_GET['date_from']) ? (string) $_GET['date_from'] : null);
$filterDateTo = subscriptions_parse_date(isset($_GET['date_to']) ? (string) $_GET['date_to'] : null);
if ($filterDateFrom !== null && $filterDateTo !== null && $filterDateFrom > $filterDateTo) {
    $tmp = $filterDateFrom;
    $filterDateFrom = $filterDateTo;
    $filterDateTo = $tmp;
}

$filterBase = [
    'q' => $searchQ,
    'clinic' => $filterClinic,
    'plan' => $filterPlan,
    'status' => $filterStatus,
    'date_from' => $filterDateFrom !== null ? $filterDateFrom : '',
    'date_to' => $filterDateTo !== null ? $filterDateTo : '',
];

$isPartialTable = isset($_GET['partial']) && (string) $_GET['partial'] === 'table';
$isPrintView = isset($_GET['print']) && (string) $_GET['print'] === '1';

$dbError = null;
$plans = [];
$clinics = [];
$subsRows = [];
$totalRows = 0;
$totalPages = 1;
$rangeStart = 0;
$rangeEnd = 0;
$metricTotalRevenue = 0.0;
$metricActivePlans = 0;
$metricPendingOverdue = 0;
$metricTrialLike = 0;
$printSubsRows = [];
$printRowCap = 2000;
$printTruncated = false;

$sqlStatusFragment = <<<SQL
(
    (? = '')
    OR
    (? = 'suspended' AND t.subscription_status = 'suspended')
    OR
    (? = 'cancelled' AND ts.payment_status = 'cancelled')
    OR
    (
        ? = 'active'
        AND t.subscription_status = 'active'
        AND ts.payment_status = 'paid'
        AND (ts.subscription_end IS NULL OR ts.subscription_end >= CURDATE())
    )
    OR
    (
        ? = 'expired'
        AND t.subscription_status <> 'suspended'
        AND ts.payment_status <> 'cancelled'
        AND NOT (
            t.subscription_status = 'active'
            AND ts.payment_status = 'paid'
            AND (ts.subscription_end IS NULL OR ts.subscription_end >= CURDATE())
        )
    )
)
SQL;
$sqlStatusFragment = preg_replace('/\s+/', ' ', trim($sqlStatusFragment));

try {
    $plans = $pdo->query('SELECT plan_id, plan_name FROM tbl_subscription_plans ORDER BY plan_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $clinicsStmt = $pdo->query('SELECT tenant_id, clinic_name FROM tbl_tenants ORDER BY clinic_name ASC');
    $clinics = $clinicsStmt ? ($clinicsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $where = ['1=1'];
    $params = [];

    if ($filterClinic !== '') {
        if (strlen($filterClinic) <= 20) {
            $where[] = 'ts.tenant_id = ?';
            $params[] = $filterClinic;
        }
    }

    if ($filterPlan !== '' && ctype_digit($filterPlan)) {
        $where[] = 'ts.plan_id = ?';
        $params[] = (int) $filterPlan;
    }

    if ($filterDateFrom !== null) {
        $where[] = 'DATE(COALESCE(ts.subscription_start, ts.created_at)) >= ?';
        $params[] = $filterDateFrom;
    }
    if ($filterDateTo !== null) {
        $where[] = 'DATE(COALESCE(ts.subscription_start, ts.created_at)) <= ?';
        $params[] = $filterDateTo;
    }

    if ($searchQ !== '') {
        $like = '%' . $searchQ . '%';
        $where[] = '(t.clinic_name LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where[] = $sqlStatusFragment;
    for ($__i = 0; $__i < 5; $__i++) {
        $params[] = $filterStatus;
    }

    $whereSql = implode(' AND ', $where);

    /** @var array<int, mixed> $countParams */
    $countParams = $params;

    $countSql = "
        SELECT COUNT(*)
        FROM tbl_tenant_subscriptions ts
        INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id
        LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
        WHERE {$whereSql}
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRows = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $metricsSql = "
        SELECT
            COALESCE(SUM(CASE WHEN ts.payment_status = 'paid' THEN COALESCE(ts.amount_paid, 0) ELSE 0 END), 0) AS total_revenue,
            SUM(
                CASE
                    WHEN (
                        t.subscription_status = 'active'
                        AND ts.payment_status = 'paid'
                        AND (ts.subscription_end IS NULL OR ts.subscription_end >= CURDATE())
                    ) THEN 1 ELSE 0
                END
            ) AS active_plans,
            SUM(
                CASE WHEN ts.payment_status IN ('pending', 'failed') THEN 1 ELSE 0 END
            ) AS pending_rows
        FROM tbl_tenant_subscriptions ts
        INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id
        LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
        WHERE {$whereSql}
    ";
    $metricsStmt = $pdo->prepare($metricsSql);
    /** @var array<int, mixed> $metricsBind */
    $metricsBind = $countParams;
    $metricsStmt->execute($metricsBind);
    $metricsRow = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $metricTotalRevenue = (float) ($metricsRow['total_revenue'] ?? 0);
    $metricActivePlans = (int) ($metricsRow['active_plans'] ?? 0);
    $pendingSubRows = (int) ($metricsRow['pending_rows'] ?? 0);
    $metricTrialLike = $pendingSubRows;

    $invoiceWhere = ['i.status = ?'];
    $invoiceParams = ['overdue'];
    if ($filterClinic !== '' && strlen($filterClinic) <= 20) {
        $invoiceWhere[] = 'i.tenant_id = ?';
        $invoiceParams[] = $filterClinic;
    }
    if ($filterPlan !== '' && ctype_digit($filterPlan)) {
        $invoiceWhere[] = 'i.plan_id = ?';
        $invoiceParams[] = (int) $filterPlan;
    }
    if ($filterDateFrom !== null) {
        $invoiceWhere[] = 'DATE(COALESCE(i.due_date, i.created_at)) >= ?';
        $invoiceParams[] = $filterDateFrom;
    }
    if ($filterDateTo !== null) {
        $invoiceWhere[] = 'DATE(COALESCE(i.due_date, i.created_at)) <= ?';
        $invoiceParams[] = $filterDateTo;
    }
    if ($searchQ !== '') {
        $like = '%' . $searchQ . '%';
        $invoiceWhere[] = '(ti.clinic_name LIKE ? OR uo.full_name LIKE ? OR uo.email LIKE ?)';
        $invoiceParams[] = $like;
        $invoiceParams[] = $like;
        $invoiceParams[] = $like;
    }
    $invoiceWhereSql = implode(' AND ', $invoiceWhere);

    // Overdue tenant invoices tied to tenants (same clinic/plan/date/search axes; excludes subscription-status filter).
    $invSql = "
        SELECT COUNT(*)
        FROM tbl_tenant_invoices i
        INNER JOIN tbl_tenants ti ON ti.tenant_id = i.tenant_id
        LEFT JOIN tbl_users uo ON uo.user_id = ti.owner_user_id
        WHERE {$invoiceWhereSql}
    ";
    $invStmt = $pdo->prepare($invSql);
    $invStmt->execute($invoiceParams);
    $overdueInvoiceCount = (int) $invStmt->fetchColumn();
    $metricPendingOverdue = $pendingSubRows + $overdueInvoiceCount;

    if ($isPrintView) {
        $pcap = (int) $printRowCap;
        $printSql = "
            SELECT
                ts.id,
                ts.subscription_start,
                ts.subscription_end,
                ts.payment_status,
                ts.amount_paid,
                ts.created_at,
                t.tenant_id,
                t.clinic_name,
                t.subscription_status AS tenant_subscription_status,
                sp.plan_name,
                sp.billing_cycle,
                u.full_name AS owner_name,
                u.email AS owner_email
            FROM tbl_tenant_subscriptions ts
            INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id
            LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
            LEFT JOIN tbl_subscription_plans sp ON sp.plan_id = ts.plan_id
            WHERE {$whereSql}
            ORDER BY ts.id DESC
            LIMIT {$pcap}
        ";
        $printStmt = $pdo->prepare($printSql);
        $printStmt->execute($countParams);
        $printSubsRows = $printStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $printTruncated = $totalRows > count($printSubsRows);
    } elseif (!$isPrintView) {
        $listSql = "
            SELECT
                ts.id,
                ts.subscription_start,
                ts.subscription_end,
                ts.payment_status,
                ts.amount_paid,
                ts.created_at,
                t.tenant_id,
                t.clinic_name,
                t.subscription_status AS tenant_subscription_status,
                sp.plan_name,
                sp.billing_cycle,
                u.full_name AS owner_name,
                u.email AS owner_email
            FROM tbl_tenant_subscriptions ts
            INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id
            LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
            LEFT JOIN tbl_subscription_plans sp ON sp.plan_id = ts.plan_id
            WHERE {$whereSql}
            ORDER BY ts.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";
        $listStmt = $pdo->prepare($listSql);
        $listStmt->execute($countParams);
        $subsRows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    $dbError = 'database';
}

if (!isset($offset)) {
    $offset = ($page - 1) * $perPage;
}

$rangeStart = $totalRows === 0 ? 0 : $offset + 1;
$rangeEnd = $totalRows === 0 ? 0 : min($totalRows, $offset + count($subsRows));

if ($isPartialTable) {
    header('Content-Type: text/html; charset=utf-8');
    subscriptions_render_table_fragment(
        $dbError,
        $subsRows,
        $totalRows,
        $rangeStart,
        $rangeEnd,
        $page,
        $totalPages,
        $filterBase
    );
    exit;
}

if ($isPrintView) {
    require_once __DIR__ . '/superadmin_settings_lib.php';
    $saBrand = superadmin_get_settings($pdo);
    subscriptions_render_print_view(
        (string) ($saBrand['system_name'] ?? 'MyDental'),
        $dbError,
        $printSubsRows,
        $totalRows,
        $printTruncated,
        $metricTotalRevenue,
        $metricActivePlans,
        $metricTrialLike
    );
    exit;
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Subscriptions | Clinical Precision</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0066ff",
                        "on-surface": "#131c25",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "error": "#ba1a1a",
                        "surface-container-low": "#edf4ff",
                        "surface-container-high": "#e0e9f6",
                        "surface-container-highest": "#dae3f0",
                        "background": "#f7f9ff",
                        "error-container": "#ffdad6",
                    },
                    fontFamily: {
                        "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"],
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "3xl": "1.5rem", "full": "9999px" },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
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
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(0, 102, 255, 0.3);
        }
        .primary-glow {
            box-shadow: 0 8px 25px -5px rgba(0, 102, 255, 0.4);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210,100%,98%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217,100%,94%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210,100%,98%,1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        #subscriptions-table-swap.subs-swap-busy {
            opacity: 0.58;
            pointer-events: none;
            transition: opacity 140ms ease;
        }
        @media (prefers-reduced-motion: reduce) {
            #subscriptions-table-swap.subs-swap-busy { transition: none; }
        }
        @media (max-width: 1023px) {
            #superadmin-sidebar {
                transform: translateX(-100%);
                transition: transform 220ms ease;
                z-index: 60;
            }
            body.sa-mobile-sidebar-open #superadmin-sidebar {
                transform: translateX(0);
            }
            .sa-top-header {
                left: 0;
                width: 100% !important;
                padding-left: 5.5rem;
                padding-right: 1rem;
            }
            #sa-mobile-sidebar-toggle {
                top: 1rem;
                left: 0.75rem;
                width: 2.75rem;
                height: 2.75rem;
                transition: left 220ms ease, background-color 220ms ease, color 220ms ease;
            }
            body.sa-mobile-sidebar-open #sa-mobile-sidebar-toggle {
                left: calc(16rem - 3.25rem);
                background: rgba(255, 255, 255, 0.98);
                color: #0066ff;
            }
            #sa-mobile-sidebar-backdrop {
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
            body.sa-mobile-sidebar-open #sa-mobile-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface antialiased min-h-screen">
<?php
$superadmin_nav = 'subscriptions';
require __DIR__ . '/superadmin_sidebar.php';
$superadmin_header_center = '';
require __DIR__ . '/superadmin_header.php';
?>
<button id="sa-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="superadmin-sidebar" aria-expanded="false" aria-label="Open navigation menu">
<span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="sa-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<main class="ml-0 lg:ml-64 pt-20 min-h-screen">
<div class="pt-6 sm:pt-8 px-4 sm:px-6 lg:px-10 pb-12 sm:pb-16 space-y-8 sm:space-y-10 relative">
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<section class="flex flex-col md:flex-row md:items-start md:justify-between gap-6">
<div>
<h2 class="text-3xl sm:text-4xl font-extrabold font-headline tracking-tight text-on-surface">Subscriptions</h2>
<p class="text-on-surface-variant mt-2 font-medium max-w-xl">Monitor SaaS subscriptions, renewal dates, and payments across clinics. Use filters below; the subscriptions table refreshes without reloading the page.</p>
</div>
<div class="flex flex-wrap gap-3 w-full md:w-auto shrink-0">
<a href="subscriptions.php" class="subs-reset-link inline-flex items-center justify-center gap-2 rounded-2xl border border-white bg-white/60 px-5 py-2.5 text-sm font-bold text-on-surface-variant hover:bg-white transition-all shadow-sm">
<span class="material-symbols-outlined text-lg">restart_alt</span>
               Clear all filters</a>
</div>
</section>
<?php if ($dbError !== null): ?>
<div class="rounded-[2rem] bg-error/10 border border-error/20 px-8 py-4 text-error text-sm font-medium">
    Could not load subscription data. Please try again or check the database connection.
</div>
<?php endif; ?>
<section class="grid grid-cols-1 sm:grid-cols-3 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">payments</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Revenue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline">₱<?php echo number_format($metricTotalRevenue, 2, '.', ','); ?></h3>
<p class="text-[11px] text-on-surface-variant mt-2 font-medium">Paid subscription amounts in the current filtered set.</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">verified</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Active Plans</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($metricActivePlans); ?></h3>
<p class="text-[11px] text-on-surface-variant mt-2 font-medium">Subscriptions that are paid and currently within billing period.</p>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-amber-400/70">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-amber-50 text-amber-700 rounded-xl shadow-sm">
<span class="material-symbols-outlined">schedule</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Pending / Overdue</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($metricPendingOverdue); ?></h3>
<p class="text-[11px] text-on-surface-variant mt-2 font-medium">Subscription rows awaiting payment plus overdue invoices (clinic/date filters apply).</p>
</div>
</section>
<div id="subscriptions-filter-panel" class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<div class="px-6 sm:px-8 py-6 border-b border-white/55">
<h3 class="text-lg font-extrabold font-headline text-on-surface">Filters</h3>
<p class="text-sm text-on-surface-variant font-medium mt-1">Refine subscriptions by clinic, plan, lifecycle status, dates, or search.</p>
</div>
<form id="subs-filter-form" method="get" action="subscriptions.php" class="px-6 sm:px-8 py-7 flex flex-col gap-5">
<div class="relative w-full md:max-w-lg group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-xl pointer-events-none">search</span>
<input name="q" value="<?php echo htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-3 text-sm transition-all placeholder:text-on-surface-variant/50 font-semibold" placeholder="Search clinic, owner name or email..." type="search" autocomplete="off"/>
</div>
<div class="flex flex-wrap items-center gap-3 sm:gap-4">
<div class="relative group shrink-0">
<select name="clinic" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all min-w-[10rem] max-w-[14rem]" title="Clinic">
<option value="">All Clinics</option>
<?php foreach ($clinics as $c): ?>
<option value="<?php echo htmlspecialchars((string) $c['tenant_id'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $filterClinic === (string) $c['tenant_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $c['clinic_name'], ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">domain</span>
</div>
<div class="relative group shrink-0">
<select name="plan" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all min-w-[9rem]" title="Plan type">
<option value="">All plan types</option>
<?php foreach ($plans as $p): ?>
<option value="<?php echo (int) $p['plan_id']; ?>"<?php echo $filterPlan === (string) $p['plan_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $p['plan_name'], ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">category</span>
</div>
<div class="relative group shrink-0">
<select name="status" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all min-w-[10rem]" title="Lifecycle status">
<option value=""<?php echo $filterStatus === '' ? ' selected' : ''; ?>>All Status</option>
<option value="active"<?php echo $filterStatus === 'active' ? ' selected' : ''; ?>>Active</option>
<option value="expired"<?php echo $filterStatus === 'expired' ? ' selected' : ''; ?>>Expired</option>
<option value="cancelled"<?php echo $filterStatus === 'cancelled' ? ' selected' : ''; ?>>Cancelled</option>
<option value="suspended"<?php echo $filterStatus === 'suspended' ? ' selected' : ''; ?>>Suspended</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">tune</span>
</div>
<label class="sr-only" for="subs-date-from">From date</label>
<input id="subs-date-from" type="date" name="date_from" value="<?php echo htmlspecialchars($filterBase['date_from'], ENT_QUOTES, 'UTF-8'); ?>" class="shrink-0 bg-surface-container-low/50 border-none rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold text-on-surface focus:ring-2 focus:ring-primary/20"/>
<label class="sr-only" for="subs-date-to">To date</label>
<input id="subs-date-to" type="date" name="date_to" value="<?php echo htmlspecialchars($filterBase['date_to'], ENT_QUOTES, 'UTF-8'); ?>" class="shrink-0 bg-surface-container-low/50 border-none rounded-xl px-3 py-2.5 text-xs sm:text-sm font-semibold text-on-surface focus:ring-2 focus:ring-primary/20"/>
<a href="subscriptions.php" class="subs-reset-link inline-flex items-center gap-2 rounded-xl border border-outline-variant/30 bg-white/70 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-on-surface-variant hover:bg-white transition-colors">
<span class="material-symbols-outlined text-base">restart_alt</span> Reset</a>
<button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-primary text-white px-5 py-2.5 text-xs font-bold uppercase tracking-wide primary-glow hover:brightness-105">Apply filters</button>
</div>
</form>
</div>
<div id="subscriptions-table-shell" class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<div class="px-6 sm:px-8 py-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-white/55">
<div>
<h3 class="text-lg font-extrabold font-headline text-on-surface">All subscriptions</h3>
<p class="text-sm text-on-surface-variant font-medium mt-0.5">Table and pagination refresh in place.</p>
</div>
<button type="button" class="subs-open-print inline-flex items-center justify-center gap-2 rounded-2xl border border-outline-variant/30 bg-white/90 px-5 py-2.5 text-sm font-bold text-on-surface shadow-sm hover:bg-white transition-colors">
<span class="material-symbols-outlined text-lg text-primary">print</span>
            Print list
        </button>
</div>
<div id="subscriptions-table-swap" class="subs-table-swap-root transition-opacity duration-150 ease-out min-h-[12rem]" data-subs-swap-root="1">
<?php
    subscriptions_render_table_fragment(
        $dbError,
        $subsRows,
        $totalRows,
        $rangeStart,
        $rangeEnd,
        $page,
        $totalPages,
        $filterBase
    );
?>
</div>
</div>
</div>
</main>
<script>
(function () {
    var swap = document.getElementById('subscriptions-table-swap');
    var form = document.getElementById('subs-filter-form');
    if (!swap || !form) return;

    function syncFormFromLocation() {
        var sp = new URL(window.location.href).searchParams;
        var qi = form.querySelector('[name="q"]');
        if (qi) qi.value = sp.get('q') || '';
        var map = [['clinic', ''], ['plan', ''], ['status', ''], ['date_from', ''], ['date_to', '']];
        map.forEach(function (pair) {
            var name = pair[0];
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            var v = sp.get(name);
            el.value = v != null ? v : '';
        });
    }

    function qsFromForm(resetPage, extra) {
        var fd = new FormData(form);
        if (resetPage !== false) {
            fd.delete('page');
        }
        fd.delete('partial');
        fd.delete('print');
        fd.set('partial', 'table');
        if (extra && typeof extra === 'object') {
            Object.keys(extra).forEach(function (k) {
                fd.delete(k);
                if (extra[k] !== '' && extra[k] != null) {
                    fd.set(k, String(extra[k]));
                }
            });
        }
        return new URLSearchParams(fd).toString();
    }

    function loadSwap(serialized, pushHistory, skipSyncBefore) {
        if (!skipSyncBefore) syncFormFromLocation();
        swap.classList.add('subs-swap-busy');
        var url = window.location.pathname + '?' + serialized;
        fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        })
            .then(function (res) {
                return res.text();
            })
            .then(function (html) {
                swap.innerHTML = html;
                if (pushHistory !== false && window.history.pushState) {
                    var up = new URLSearchParams(serialized);
                    up.delete('partial');
                    up.delete('print');
                    window.history.pushState({}, '', window.location.pathname + (up.toString() ? '?' + up.toString() : ''));
                }
                syncFormFromLocation();
            })
            .finally(function () {
                swap.classList.remove('subs-swap-busy');
            });
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        syncFormFromLocation();
        loadSwap(qsFromForm(true), true, true);
    });

    form.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t || t.tagName !== 'SELECT') return;
        syncFormFromLocation();
        loadSwap(qsFromForm(true), true, true);
    });

    document.body.addEventListener('click', function (ev) {
        var reset = ev.target.closest('a.subs-reset-link');
        if (reset) {
            ev.preventDefault();
            if (window.history.pushState) {
                window.history.pushState({}, '', window.location.pathname);
            }
            var qi = form.querySelector('[name="q"]');
            if (qi) qi.value = '';
            ['clinic', 'plan', 'status'].forEach(function (n) {
                var el = form.querySelector('[name="' + n + '"]');
                if (el) el.selectedIndex = 0;
            });
            [['date_from', ''], ['date_to', '']].forEach(function (pair) {
                var el = form.querySelector('[name="' + pair[0] + '"]');
                if (el) el.value = pair[1];
            });
            loadSwap('partial=table', true, true);
            return;
        }
        var a = ev.target.closest('a.subs-pjax-link');
        if (!a || !swap.contains(a)) return;
        ev.preventDefault();
        var u = new URL(a.href);
        var sp = new URLSearchParams(u.search);
        sp.set('partial', 'table');
        loadSwap(sp.toString(), true);
    });

    document.body.querySelectorAll('button.subs-open-print').forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            var fd = new FormData(form);
            fd.delete('partial');
            fd.delete('page');
            fd.delete('print');
            fd.append('print', '1');
            window.open(window.location.pathname + '?' + new URLSearchParams(fd).toString(), '_blank', 'noopener');
        });
    });

    window.addEventListener('popstate', function () {
        syncFormFromLocation();
        var sp = new URL(window.location.href).searchParams;
        sp.set('partial', 'table');
        loadSwap(sp.toString(), false, true);
    });
})();
</script>
<script>
(function () {
    var toggleBtn = document.getElementById('sa-mobile-sidebar-toggle');
    var backdrop = document.getElementById('sa-mobile-sidebar-backdrop');
    var mqDesktop = window.matchMedia('(min-width: 1024px)');
    if (!toggleBtn || !backdrop) return;

    function setOpen(isOpen) {
        document.body.classList.toggle('sa-mobile-sidebar-open', isOpen);
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggleBtn.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        var icon = toggleBtn.querySelector('.material-symbols-outlined');
        if (icon) icon.textContent = isOpen ? 'close' : 'menu';
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    toggleBtn.addEventListener('click', function () {
        setOpen(!document.body.classList.contains('sa-mobile-sidebar-open'));
    });
    backdrop.addEventListener('click', function () {
        setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sa-mobile-sidebar-open')) {
            setOpen(false);
        }
    });

    function closeOnDesktop() {
        if (mqDesktop.matches) setOpen(false);
    }
    if (typeof mqDesktop.addEventListener === 'function') mqDesktop.addEventListener('change', closeOnDesktop);
    else if (typeof mqDesktop.addListener === 'function') mqDesktop.addListener(closeOnDesktop);
})();
</script>
</body>
</html>
