<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';
$provider_nav_active = 'reports';

require_once __DIR__ . '/provider_tenant_plan_and_site_context.inc.php';
require_once __DIR__ . '/provider_tenant_header_context.inc.php';

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

function provider_tenant_rep_table_has_column(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/i', $table) || !preg_match('/^[a-z][a-z0-9_]{0,62}$/i', $column)) {
        return false;
    }
    try {
        $q = $pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE ' . $pdo->quote($column));
        return $q !== false && $q->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function provider_tenant_rep_pay_norm(string $alias = 'py'): string
{
    return 'LOWER(TRIM(COALESCE(`' . $alias . '`.status, \'\')))';
}

/** @return array{sql: string, params: array<int, string|int|float>} */
function provider_tenant_rep_build_payment_conditions(
    PDO $pdo,
    string $t_payments,
    string $t_patients,
    string $t_appts,
    string $tenant_id,
    string $date_from,
    string $date_to,
    string $q_search,
    string $payment_scope
): array {
    $where = ["`py`.tenant_id = ?"];
    $params = [$tenant_id];

    if ($date_from !== '') {
        $where[] = 'DATE(`py`.payment_date) >= ?';
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where[] = 'DATE(`py`.payment_date) <= ?';
        $params[] = $date_to;
    }

    $pn = provider_tenant_rep_pay_norm('py');
    if ($payment_scope === 'paid') {
        $where[] = "{$pn} IN ('completed','complete','paid','success')";
    } elseif ($payment_scope === 'pending') {
        $where[] = "{$pn} IN ('pending')";
    }

    if ($q_search !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q_search) . '%';
        $parts = ['`py`.booking_id LIKE ?', '`py`.patient_id LIKE ?'];
        $sp = [$like, $like];
        if (provider_tenant_rep_table_has_column($pdo, $t_payments, 'payment_id')) {
            $parts[] = '`py`.payment_id LIKE ?';
            $sp[] = $like;
        }
        if ($t_patients !== '') {
            $parts[] = '`p`.first_name LIKE ?';
            $parts[] = '`p`.last_name LIKE ?';
            $parts[] = 'CONCAT(COALESCE(`p`.first_name,\'\'), \' \', COALESCE(`p`.last_name,\'\')) LIKE ?';
            $sp[] = $like;
            $sp[] = $like;
            $sp[] = $like;
        }
        if ($t_appts !== '') {
            $has_st = provider_tenant_rep_table_has_column($pdo, $t_appts, 'service_type');
            $has_sd = provider_tenant_rep_table_has_column($pdo, $t_appts, 'service_description');
            if ($has_st) {
                $parts[] = '`a`.service_type LIKE ?';
                $sp[] = $like;
            }
            if ($has_sd) {
                $parts[] = '`a`.service_description LIKE ?';
                $sp[] = $like;
            }
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
        $params = array_merge($params, $sp);
    }

    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function provider_tenant_rep_fmt_peso(float $n, int $decimals = 0): string
{
    return '₱' . number_format($n, $decimals, '.', ',');
}

function provider_tenant_rep_month_key(string $ym): string
{
    $ym = trim($ym);
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ym, $m)) {
        return $ym;
    }
    $ts = strtotime($m[1] . '-' . $m[2] . '-01');
    if ($ts === false) {
        return $ym;
    }
    return date('M Y', $ts);
}

/**
 * Map colors like the reference donut: largest slices get deeper greens/teals,
 * smaller slices shift toward mint/cyan; unlinked stays slate.
 *
 * @param array<int, string> $labels
 * @param array<int, float|int|string> $values
 * @return array<int, string>
 */
function provider_tenant_rep_assign_service_colors(array $labels, array $values): array
{
    $n = count($labels);
    if ($n === 0) {
        return [];
    }
    $colors = array_fill(0, $n, '#22C55E');
    $pairs = [];
    foreach ($labels as $i => $lbl) {
        $pairs[] = [
            'i' => $i,
            'v' => isset($values[$i]) ? (float) $values[$i] : 0.0,
            'lbl' => strtolower(trim((string) $lbl)),
        ];
    }
    usort($pairs, static function (array $a, array $b): int {
        if ($a['v'] === $b['v']) {
            return $a['i'] <=> $b['i'];
        }
        return $a['v'] < $b['v'] ? 1 : -1;
    });
    $palette = [
        '#14532D', '#166534', '#15803D', '#047857', '#059669', '#0F766E',
        '#0D9488', '#14B8A6', '#0E7490', '#2DD4BF', '#22D3BB', '#5EEAD4',
        '#99F6E4', '#A7F3D0', '#BBF7D0', '#86EFAC', '#4ADE80', '#34D399',
    ];
    $pi = 0;
    foreach ($pairs as $p) {
        if ($p['lbl'] === 'unlinked payment' || str_contains($p['lbl'], 'unlinked')) {
            $colors[$p['i']] = '#64748B';
            continue;
        }
        $colors[$p['i']] = $palette[$pi % count($palette)];
        $pi++;
    }

    return $colors;
}

$t_appts = provider_tenant_dash_resolve_table($pdo, ['appointments', 'tbl_appointments']);
$t_patients = provider_tenant_dash_resolve_table($pdo, ['patients', 'tbl_patients']);
$t_payments = provider_tenant_dash_resolve_table($pdo, ['payments', 'tbl_payments']);
$t_reviews = provider_tenant_dash_resolve_table($pdo, ['tbl_reviews', 'reviews']);

$has_service_type = $t_appts !== '' && provider_tenant_rep_table_has_column($pdo, $t_appts, 'service_type');
$has_service_desc = $t_appts !== '' && provider_tenant_rep_table_has_column($pdo, $t_appts, 'service_description');
$service_label_expr = '\'Unknown service\'';
if ($t_appts !== '') {
    if ($has_service_type && $has_service_desc) {
        $service_label_expr = "NULLIF(TRIM(COALESCE(NULLIF(TRIM(`a`.service_type),''), LEFT(`a`.service_description, 120))), '')";
    } elseif ($has_service_type) {
        $service_label_expr = "NULLIF(TRIM(`a`.service_type), '')";
    } elseif ($has_service_desc) {
        $service_label_expr = 'LEFT(`a`.service_description, 120)';
    }
}

$date_from = trim((string) ($_GET['date_from'] ?? ''));
$date_to = trim((string) ($_GET['date_to'] ?? ''));
if ($date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $date_from = '';
}
if ($date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $date_to = '';
}

$q_search = trim((string) ($_GET['q'] ?? ''));

$filter_payment = strtolower(trim((string) ($_GET['payment_status'] ?? 'all')));
if (!in_array($filter_payment, ['all', 'paid', 'pending'], true)) {
    $filter_payment = 'all';
}

$self_php = 'ProviderTenantReports.php';

$pay_joins = '';
if ($t_patients !== '') {
    $pay_joins .= " LEFT JOIN `{$t_patients}` p ON `py`.patient_id = `p`.patient_id AND `p`.tenant_id = `py`.tenant_id";
}
if ($t_appts !== '') {
    $pay_joins .= " LEFT JOIN `{$t_appts}` a ON `py`.booking_id = `a`.booking_id AND `a`.tenant_id = `py`.tenant_id";
}

$scope_pay_charts = $filter_payment === 'pending' ? 'pending' : 'paid';

$cond_all_pay = provider_tenant_rep_build_payment_conditions(
    $pdo,
    $t_payments,
    $t_patients,
    $t_appts,
    $tenant_id,
    $date_from,
    $date_to,
    $q_search,
    'all'
);

$total_revenue = 0.0;
$total_pending_amt = 0.0;
$billing_paid_amt = 0.0;
$billing_pending_amt = 0.0;
$billing_paid_n = 0;
$billing_pend_n = 0;

$monthly_labels = [];
$monthly_values = [];

$service_labels = [];
$service_values = [];

$top_clients = [];

$n_confirmed = 0;
$n_completed = 0;
$n_in_progress = 0;
$ops_total = 0;

$reviews_avg = 0.0;
$reviews_count = 0;

if ($t_payments !== '') {
    $pn = provider_tenant_rep_pay_norm('py');

    try {
        if ($filter_payment !== 'pending') {
            $st = $pdo->prepare("
                SELECT COALESCE(SUM(`py`.amount), 0) AS s
                FROM `{$t_payments}` py
                {$pay_joins}
                WHERE {$cond_all_pay['sql']} AND {$pn} IN ('completed','complete','paid','success')
            ");
            $st->execute($cond_all_pay['params']);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $total_revenue = (float) ($row['s'] ?? 0);
        }

        $st = $pdo->prepare("
            SELECT COALESCE(SUM(`py`.amount), 0) AS s
            FROM `{$t_payments}` py
            {$pay_joins}
            WHERE {$cond_all_pay['sql']} AND {$pn} IN ('pending')
        ");
        $st->execute($cond_all_pay['params']);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $total_pending_amt = (float) ($row['s'] ?? 0);

        if ($filter_payment === 'paid') {
            $total_pending_amt = 0.0;
        }
        if ($filter_payment === 'pending') {
            $total_revenue = 0.0;
        }

        $st = $pdo->prepare("
            SELECT COALESCE(SUM(CASE WHEN {$pn} IN ('completed','complete','paid','success') THEN `py`.amount ELSE 0 END), 0) AS paid_amt,
                   COALESCE(SUM(CASE WHEN {$pn} IN ('pending') THEN `py`.amount ELSE 0 END), 0) AS pend_amt,
                   SUM(CASE WHEN {$pn} IN ('completed','complete','paid','success') THEN 1 ELSE 0 END) AS paid_n,
                   SUM(CASE WHEN {$pn} IN ('pending') THEN 1 ELSE 0 END) AS pend_n
            FROM `{$t_payments}` py
            {$pay_joins}
            WHERE {$cond_all_pay['sql']}
        ");
        $st->execute($cond_all_pay['params']);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $billing_paid_amt = (float) ($row['paid_amt'] ?? 0);
        $billing_pending_amt = (float) ($row['pend_amt'] ?? 0);
        $billing_paid_n = (int) ($row['paid_n'] ?? 0);
        $billing_pend_n = (int) ($row['pend_n'] ?? 0);

        if ($filter_payment === 'paid') {
            $billing_pending_amt = 0.0;
            $billing_pend_n = 0;
        }
        if ($filter_payment === 'pending') {
            $billing_paid_amt = 0.0;
            $billing_paid_n = 0;
        }
    } catch (Throwable $e) {
        $billing_paid_n = 0;
        $billing_pend_n = 0;
    }

    try {
        $trend_cond = provider_tenant_rep_build_payment_conditions(
            $pdo,
            $t_payments,
            $t_patients,
            $t_appts,
            $tenant_id,
            $date_from,
            $date_to,
            $q_search,
            $scope_pay_charts === 'pending' ? 'pending' : 'paid'
        );
        $status_sql = $scope_pay_charts === 'pending'
            ? "{$pn} IN ('pending')"
            : "{$pn} IN ('completed','complete','paid','success')";
        $st = $pdo->prepare("
            SELECT DATE_FORMAT(`py`.payment_date, '%Y-%m') AS ym,
                   COALESCE(SUM(`py`.amount), 0) AS total_amt
            FROM `{$t_payments}` py
            {$pay_joins}
            WHERE {$trend_cond['sql']} AND {$status_sql}
            GROUP BY ym
            ORDER BY ym ASC
        ");
        $st->execute($trend_cond['params']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $ym = (string) ($r['ym'] ?? '');
                if ($ym === '') {
                    continue;
                }
                $monthly_labels[] = provider_tenant_rep_month_key($ym);
                $monthly_values[] = round((float) ($r['total_amt'] ?? 0), 2);
            }
        }
    } catch (Throwable $e) {
    }

    try {
        $svc_cond = provider_tenant_rep_build_payment_conditions(
            $pdo,
            $t_payments,
            $t_patients,
            $t_appts,
            $tenant_id,
            $date_from,
            $date_to,
            $q_search,
            $scope_pay_charts === 'pending' ? 'pending' : 'paid'
        );
        $svc_status = $scope_pay_charts === 'pending'
            ? "{$pn} IN ('pending')"
            : "{$pn} IN ('completed','complete','paid','success')";
        $svc_case = "COALESCE({$service_label_expr}, 'Unlinked payment')";

        $st = $pdo->prepare("
            SELECT {$svc_case} AS svc_name,
                   COALESCE(SUM(`py`.amount), 0) AS total_amt
            FROM `{$t_payments}` py
            {$pay_joins}
            WHERE {$svc_cond['sql']} AND {$svc_status}
            GROUP BY {$svc_case}
            ORDER BY total_amt DESC
            LIMIT 12
        ");
        $st->execute($svc_cond['params']);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($rows)) {
            foreach ($rows as $r) {
                $lbl = trim((string) ($r['svc_name'] ?? ''));
                if ($lbl === '') {
                    $lbl = 'General';
                }
                $service_labels[] = $lbl;
                $service_values[] = round((float) ($r['total_amt'] ?? 0), 2);
            }
        }
    } catch (Throwable $e) {
    }

    try {
        $ap_w = ['`a`.tenant_id = ?'];
        $ap_p = [$tenant_id];
        if ($date_from !== '') {
            $ap_w[] = '`a`.appointment_date >= ?';
            $ap_p[] = $date_from;
        }
        if ($date_to !== '') {
            $ap_w[] = '`a`.appointment_date <= ?';
            $ap_p[] = $date_to;
        }
        if ($q_search !== '' && $t_patients !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q_search) . '%';
            $ap_w[] = '(`a`.booking_id LIKE ? OR `a`.patient_id LIKE ? OR `p2`.first_name LIKE ? OR `p2`.last_name LIKE ? OR CONCAT(COALESCE(`p2`.first_name,\'\'), \' \', COALESCE(`p2`.last_name,\'\')) LIKE ?)';
            array_push($ap_p, $like, $like, $like, $like, $like);
        } elseif ($q_search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q_search) . '%';
            $ap_w[] = '(`a`.booking_id LIKE ? OR `a`.patient_id LIKE ?)';
            $ap_p[] = $like;
            $ap_p[] = $like;
        }

        $ap_where = implode(' AND ', $ap_w);
        $p2join = $t_patients !== ''
            ? "LEFT JOIN `{$t_patients}` p2 ON `a`.patient_id = `p2`.patient_id AND `p2`.tenant_id = `a`.tenant_id"
            : '';

        $spent_scope = $filter_payment === 'pending' ? 'pending' : ($filter_payment === 'paid' ? 'paid' : 'paid');
        $spent_cond = provider_tenant_rep_build_payment_conditions(
            $pdo,
            $t_payments,
            $t_patients,
            $t_appts,
            $tenant_id,
            $date_from,
            $date_to,
            $q_search,
            $spent_scope === 'pending' ? 'pending' : 'paid'
        );
        $spent_pn = provider_tenant_rep_pay_norm('py');
        $spent_status_sql = $spent_scope === 'pending'
            ? "{$spent_pn} IN ('pending')"
            : "{$spent_pn} IN ('completed','complete','paid','success')";

        $sqlTop = "
            SELECT
                ids.patient_id AS patient_id,
                COALESCE(ac.cnt, 0) AS appt_cnt,
                COALESCE(sp.total_spent, 0) AS spent
            FROM (
                SELECT DISTINCT `a`.patient_id AS patient_id
                FROM `{$t_appts}` a
                {$p2join}
                WHERE {$ap_where} AND COALESCE(TRIM(`a`.patient_id), '') <> ''
                UNION
                SELECT DISTINCT `py`.patient_id AS patient_id
                FROM `{$t_payments}` py
                {$pay_joins}
                WHERE {$spent_cond['sql']} AND {$spent_status_sql}
                  AND COALESCE(TRIM(`py`.patient_id), '') <> ''
            ) ids
            LEFT JOIN (
                SELECT `a`.patient_id AS patient_id, COUNT(*) AS cnt
                FROM `{$t_appts}` a
                {$p2join}
                WHERE {$ap_where}
                GROUP BY `a`.patient_id
            ) ac ON ids.patient_id = ac.patient_id
            LEFT JOIN (
                SELECT `py`.patient_id AS patient_id, COALESCE(SUM(`py`.amount), 0) AS total_spent
                FROM `{$t_payments}` py
                {$pay_joins}
                WHERE {$spent_cond['sql']} AND {$spent_status_sql}
                GROUP BY `py`.patient_id
            ) sp ON ids.patient_id = sp.patient_id
            WHERE COALESCE(ac.cnt, 0) > 0 OR COALESCE(sp.total_spent, 0) > 0
            ORDER BY spent DESC, appt_cnt DESC
            LIMIT 8
        ";

        if ($t_appts !== '') {
            $st = $pdo->prepare($sqlTop);
            $paramsTop = array_merge($ap_p, $spent_cond['params'], $ap_p, $spent_cond['params']);
            $st->execute($paramsTop);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $pid = (string) ($r['patient_id'] ?? '');
                    if ($pid === '') {
                        continue;
                    }
                    $name = $pid;
                    if ($t_patients !== '') {
                        try {
                            $pn_st = $pdo->prepare("SELECT first_name, last_name FROM `{$t_patients}` WHERE tenant_id = ? AND patient_id = ? LIMIT 1");
                            $pn_st->execute([$tenant_id, $pid]);
                            $prow = $pn_st->fetch(PDO::FETCH_ASSOC);
                            if (is_array($prow)) {
                                $fn = trim((string) ($prow['first_name'] ?? ''));
                                $ln = trim((string) ($prow['last_name'] ?? ''));
                                $full = trim($fn . ' ' . $ln);
                                if ($full !== '') {
                                    $name = $full;
                                }
                            }
                        } catch (Throwable $e) {
                        }
                    }
                    $top_clients[] = [
                        'patient_id' => $pid,
                        'name' => $name,
                        'appts' => (int) ($r['appt_cnt'] ?? 0),
                        'spent' => (float) ($r['spent'] ?? 0),
                    ];
                }
            }
        }
    } catch (Throwable $e) {
    }
} else {
    $billing_paid_n = 0;
    $billing_pend_n = 0;
}

if ($t_appts !== '') {
    try {
        $ow = ['`a`.tenant_id = ?'];
        $op = [$tenant_id];
        if ($date_from !== '') {
            $ow[] = '`a`.appointment_date >= ?';
            $op[] = $date_from;
        }
        if ($date_to !== '') {
            $ow[] = '`a`.appointment_date <= ?';
            $op[] = $date_to;
        }
        if ($q_search !== '' && $t_patients !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q_search) . '%';
            $pj = "LEFT JOIN `{$t_patients}` p3 ON `a`.patient_id = `p3`.patient_id AND `p3`.tenant_id = `a`.tenant_id";
            $ow[] = '(`a`.booking_id LIKE ? OR `a`.patient_id LIKE ? OR `p3`.first_name LIKE ? OR `p3`.last_name LIKE ? OR CONCAT(COALESCE(`p3`.first_name,\'\'), \' \', COALESCE(`p3`.last_name,\'\')) LIKE ?)';
            array_push($op, $like, $like, $like, $like, $like);
        } elseif ($q_search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q_search) . '%';
            $pj = '';
            $ow[] = '(`a`.booking_id LIKE ? OR `a`.patient_id LIKE ?)';
            $op[] = $like;
            $op[] = $like;
        } else {
            $pj = '';
        }

        $osql = implode(' AND ', $ow);
        $sqlOps = "
            SELECT
                SUM(CASE WHEN LOWER(TRIM(`a`.status)) IN ('confirmed','scheduled') THEN 1 ELSE 0 END) AS n_conf,
                SUM(CASE WHEN LOWER(TRIM(`a`.status)) = 'completed' THEN 1 ELSE 0 END) AS n_done,
                SUM(CASE WHEN LOWER(TRIM(`a`.status)) IN ('in_progress','in procedure','in_procedure') THEN 1 ELSE 0 END) AS n_prog
            FROM `{$t_appts}` a
            {$pj}
            WHERE {$osql}
        ";
        $st = $pdo->prepare($sqlOps);
        $st->execute($op);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $n_confirmed = (int) ($row['n_conf'] ?? 0);
        $n_completed = (int) ($row['n_done'] ?? 0);
        $n_in_progress = (int) ($row['n_prog'] ?? 0);
        $ops_total = $n_confirmed + $n_completed + $n_in_progress;
    } catch (Throwable $e) {
    }
}

if ($t_reviews !== '') {
    try {
        $rw = ['`r`.tenant_id = ?'];
        $rp = [$tenant_id];
        if ($date_from !== '') {
            $rw[] = 'DATE(`r`.created_at) >= ?';
            $rp[] = $date_from;
        }
        if ($date_to !== '') {
            $rw[] = 'DATE(`r`.created_at) <= ?';
            $rp[] = $date_to;
        }
        if ($q_search !== '' && $t_patients !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q_search) . '%';
            $rw[] = '(`r`.patient_id LIKE ? OR `rp`.first_name LIKE ? OR `rp`.last_name LIKE ? OR CONCAT(COALESCE(`rp`.first_name,\'\'), \' \', COALESCE(`rp`.last_name,\'\')) LIKE ?)';
            array_push($rp, $like, $like, $like, $like);
            $rj = "LEFT JOIN `{$t_patients}` rp ON `r`.patient_id = `rp`.patient_id AND `rp`.tenant_id = `r`.tenant_id";
        } elseif ($q_search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q_search) . '%';
            $rw[] = '`r`.patient_id LIKE ?';
            $rp[] = $like;
            $rj = '';
        } else {
            $rj = '';
        }

        $rsql = implode(' AND ', $rw);
        $sqlRev = "
            SELECT AVG(`r`.rating) AS avg_r, COUNT(*) AS c
            FROM `{$t_reviews}` r
            {$rj}
            WHERE {$rsql}
        ";
        $st = $pdo->prepare($sqlRev);
        $st->execute($rp);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $reviews_avg = (float) ($row['avg_r'] ?? 0);
        $reviews_count = (int) ($row['c'] ?? 0);
    } catch (Throwable $e) {
    }
}

$billing_max_amt = max($billing_paid_amt, $billing_pending_amt, 1.0);
$billing_paid_pct = (int) round(100 * $billing_paid_amt / $billing_max_amt);
$billing_pend_pct = (int) round(100 * $billing_pending_amt / $billing_max_amt);

$ops_den = max($ops_total, 1);
$pct_conf = (int) round(100 * $n_confirmed / $ops_den);
$pct_done = (int) round(100 * $n_completed / $ops_den);
$pct_prog = (int) round(100 * $n_in_progress / $ops_den);

$service_colors = provider_tenant_rep_assign_service_colors($service_labels, $service_values);

$reports_clinic_name = isset($clinic_display_name) ? trim((string) $clinic_display_name) : '';
if ($reports_clinic_name === '') {
    $reports_clinic_name = trim((string) ($tenant['clinic_name'] ?? ''));
}
if ($reports_clinic_name === '') {
    $reports_clinic_name = 'My Clinic';
}
try {
    $reports_tz = new DateTimeZone('Asia/Manila');
} catch (Throwable $e) {
    $reports_tz = new DateTimeZone('UTC');
}
$reports_generated_at = (new DateTimeImmutable('now', $reports_tz))->format('M j, Y g:i A');
$reports_pay_label = strtoupper(str_replace('_', ' ', $filter_payment));
if ($date_from !== '' || $date_to !== '') {
    $reports_period_line = trim(
        ($date_from !== '' ? 'From ' . $date_from : '')
            . (($date_from !== '' && $date_to !== '') ? ' · ' : '')
            . ($date_to !== '' ? 'To ' . $date_to : '')
    );
} else {
    $reports_period_line = 'All dates';
}
$reports_search_line = $q_search !== '' ? $q_search : '—';

?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?php echo htmlspecialchars('Reports | ' . $reports_clinic_name, ENT_QUOTES, 'UTF-8'); ?> | MyDental</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-variant": "#f1f5f9",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#e2e8f0",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#475569",
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
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
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
        .reports-chart-wrap {
            position: relative;
            height: 260px;
        }
        /* Donut card: do NOT reuse .reports-chart-wrap — fixed height breaks chart + legend */
        .reports-service-chart-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        .reports-donut-canvas-host {
            position: relative;
            width: min(100%, 340px);
            height: 280px;
            margin-left: auto;
            margin-right: auto;
        }
        .reports-service-legend {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.65rem 1.25rem;
            width: 100%;
            max-width: 44rem;
            margin-left: auto;
            margin-right: auto;
        }
        @media (min-width: 640px) {
            .reports-service-legend {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.65rem 1.75rem;
            }
        }
        .reports-service-legend-item {
            min-width: 0;
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
        body { font-family: 'Manrope', sans-serif; }
        @media print {
            @page {
                margin: 14mm;
                size: auto;
            }
            body.provider-report-print-session aside,
            body.provider-report-print-session .provider-top-header,
            body.provider-report-print-session main,
            body.provider-report-print-session #provider-mobile-sidebar-toggle,
            body.provider-report-print-session #provider-mobile-sidebar-backdrop,
            body.provider-report-print-session #provider-sidebar-toggle {
                display: none !important;
            }
            body.provider-report-print-session #reports-preview-modal {
                position: static !important;
                inset: auto !important;
                display: block !important;
                overflow: visible !important;
                max-height: none !important;
                height: auto !important;
                padding: 0 !important;
                margin: 0 !important;
                background: transparent !important;
                z-index: auto !important;
            }
            body.provider-report-print-session #reports-preview-modal .reports-preview-shell {
                max-height: none !important;
                height: auto !important;
                overflow: visible !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            body.provider-report-print-session #reports-preview-modal .reports-preview-no-print {
                display: none !important;
            }
            body.provider-report-print-session #reports-print-sheet {
                padding: 0 !important;
            }
            body.provider-report-print-session #reports-print-sheet .reports-print-table {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-background min-h-screen selection:bg-primary/10">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<?php include __DIR__ . '/provider_tenant_top_header.inc.php'; ?>
<button id="provider-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="provider-sidebar" aria-expanded="false" aria-label="Open navigation menu">
<span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="provider-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<main class="ml-0 lg:ml-64 pt-[4.75rem] sm:pt-24 min-h-screen provider-page-enter">
<div class="pt-4 sm:pt-6 px-6 lg:px-10 pb-20 space-y-8">
<section class="flex flex-col gap-6">
<div class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> Tenant management</div>
<div class="flex flex-col xl:flex-row xl:justify-between xl:items-end gap-8">
<div>
<h2 class="font-headline font-extrabold tracking-tighter leading-tight text-on-background text-5xl sm:text-6xl">Clinic <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Reports</span></h2>
<p class="font-body text-xl font-medium text-slate-600 max-w-3xl leading-relaxed mt-6">Revenue, collections, reviews, and operations scoped to your clinic data.</p>
</div>
<div class="flex flex-col gap-4 items-stretch xl:items-end shrink-0 w-full xl:w-auto">
<div class="flex flex-wrap items-center justify-start xl:justify-end gap-2 w-full">
<button type="button" id="reports-open-preview-btn" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-[10px] font-black uppercase tracking-widest text-primary hover:border-primary/35 hover:bg-primary/5 transition-colors">
<span class="material-symbols-outlined text-[18px]">preview</span>
                Preview
            </button>
<button type="button" id="reports-open-print-btn" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary text-white px-6 py-3 text-[10px] font-black uppercase tracking-widest hover:shadow-lg hover:shadow-primary/25 transition-all active:scale-[0.98]">
<span class="material-symbols-outlined text-[18px]">print</span>
                Print / PDF
            </button>
</div>
<div class="flex flex-wrap items-center gap-3 justify-start xl:justify-end">
<span class="material-symbols-outlined text-primary text-2xl">analytics</span>
<div class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80">
<span class="text-slate-900"><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $filter_payment)), ENT_QUOTES, 'UTF-8'); ?></span> payments in view
</div>
</div>
</div>
</div>
</div>

<form class="pt-8 border-t border-slate-100" method="get" action="<?php echo htmlspecialchars($self_php, ENT_QUOTES, 'UTF-8'); ?>">
<div class="rounded-2xl border border-slate-200 bg-white/80 p-4 sm:p-5">
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 sm:gap-4 items-end">
<div class="lg:col-span-2">
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="rep-date-from">From</label>
<input type="date" id="rep-date-from" name="date_from" value="<?php echo htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
</div>
<div class="lg:col-span-2">
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="rep-date-to">To</label>
<input type="date" id="rep-date-to" name="date_to" value="<?php echo htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
</div>
<div class="relative lg:col-span-2">
<label class="sr-only" for="rep-pay-status">Payment status</label>
<select id="rep-pay-status" name="payment_status" class="w-full appearance-none bg-slate-50 border border-slate-200 rounded-2xl px-5 py-3 pr-11 text-on-background text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 cursor-pointer transition-all">
<option value="all"<?php echo $filter_payment === 'all' ? ' selected' : ''; ?>>All payments</option>
<option value="paid"<?php echo $filter_payment === 'paid' ? ' selected' : ''; ?>>Paid only</option>
<option value="pending"<?php echo $filter_payment === 'pending' ? ' selected' : ''; ?>>Pending only</option>
</select>
<span class="material-symbols-outlined absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">payments</span>
</div>
<div class="sm:col-span-2 lg:col-span-4">
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="rep-q">Search</label>
<div class="relative">
<span class="material-symbols-outlined pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/45 text-lg">search</span>
<input type="search" id="rep-q" name="q" value="<?php echo htmlspecialchars($q_search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Patient, booking ID, service…" class="w-full rounded-2xl border border-slate-200 bg-white pl-11 pr-4 py-3 text-sm font-medium placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
</div>
</div>
<div class="sm:col-span-2 lg:col-span-2 flex flex-col-reverse sm:flex-row lg:justify-end gap-2.5">
<?php if ($filter_payment !== 'all' || $date_from !== '' || $date_to !== '' || $q_search !== '') { ?>
<a class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-[10px] font-black uppercase tracking-widest text-primary hover:border-primary/30 hover:bg-primary/5 transition-colors" href="<?php echo htmlspecialchars($self_php, ENT_QUOTES, 'UTF-8'); ?>">Reset</a>
<?php } ?>
<button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-primary text-white px-8 py-3 text-[10px] font-black uppercase tracking-widest hover:shadow-lg hover:shadow-primary/25 transition-all active:scale-[0.98]">Apply</button>
</div>
</div>
</div>
</form>
</section>

<?php if ($t_payments === '') { ?>
<div class="elevated-card rounded-3xl border border-dashed border-slate-200 p-8 text-center text-on-surface-variant font-medium">
Payment records are not available for this tenant context. Connect <code class="text-slate-700">tbl_payments</code> to unlock revenue widgets.
</div>
<?php } ?>

<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
<div class="elevated-card provider-card-lift rounded-3xl p-7 flex flex-col justify-between gap-4">
<div class="flex justify-between items-start">
<div>
<p class="text-[10px] font-black uppercase tracking-[0.25em] text-on-surface-variant/65">Total revenue</p>
<p class="font-headline font-extrabold text-3xl sm:text-4xl text-slate-900 mt-2 tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($total_revenue), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<span class="material-symbols-outlined text-emerald-500 text-3xl">account_balance_wallet</span>
</div>
<p class="text-[11px] font-semibold text-on-surface-variant/70"><?php echo $filter_payment === 'pending' ? 'Collected totals hidden while Pending filter is active.' : 'Paid / completed collections in range.'; ?></p>
</div>

<div class="elevated-card provider-card-lift rounded-3xl p-7 flex flex-col justify-between gap-4">
<div class="flex justify-between items-start">
<div>
<p class="text-[10px] font-black uppercase tracking-[0.25em] text-on-surface-variant/65">Pending / unpaid</p>
<p class="font-headline font-extrabold text-3xl sm:text-4xl text-amber-700 mt-2 tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($total_pending_amt), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<span class="material-symbols-outlined text-amber-500 text-3xl">hourglass_top</span>
</div>
<p class="text-[11px] font-semibold text-on-surface-variant/70"><?php echo $filter_payment === 'paid' ? 'Pending totals hidden while Paid filter is active.' : 'Outstanding recorded payments.'; ?></p>
</div>

<div class="elevated-card provider-card-lift rounded-3xl p-7 flex flex-col justify-between gap-4">
<div class="flex justify-between items-start">
<div>
<p class="text-[10px] font-black uppercase tracking-[0.25em] text-on-surface-variant/65">Average rating</p>
<p class="font-headline font-extrabold text-3xl sm:text-4xl text-slate-900 mt-2 tabular-nums"><?php echo $reviews_count > 0 ? htmlspecialchars(number_format($reviews_avg, 2), ENT_QUOTES, 'UTF-8') : '—'; ?><?php if ($reviews_count > 0) { ?><span class="text-lg font-bold text-on-surface-variant"> / 5</span><?php } ?></p>
</div>
<span class="material-symbols-outlined text-primary text-3xl">star_half</span>
</div>
<p class="text-[11px] font-semibold text-on-surface-variant/70"><?php echo $t_reviews === '' ? 'Reviews table not found.' : 'From tbl_reviews in range.'; ?></p>
</div>

<div class="elevated-card provider-card-lift rounded-3xl p-7 flex flex-col justify-between gap-4">
<div class="flex justify-between items-start">
<div>
<p class="text-[10px] font-black uppercase tracking-[0.25em] text-on-surface-variant/65">Reviews</p>
<p class="font-headline font-extrabold text-3xl sm:text-4xl text-slate-900 mt-2 tabular-nums"><?php echo (int) $reviews_count; ?></p>
</div>
<span class="material-symbols-outlined text-slate-400 text-3xl">rate_review</span>
</div>
<p class="text-[11px] font-semibold text-on-surface-variant/70">Patient feedback count</p>
</div>
</section>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
<div class="elevated-card rounded-3xl p-8 provider-card-lift">
<p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-900">Revenue collection trend</p>
<p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/55 mt-1"><?php echo $scope_pay_charts === 'pending' ? 'Monthly pending volume' : 'Monthly collection performance'; ?></p>
<div class="reports-chart-wrap mt-6">
<?php if ($t_payments === '') { ?>
<p class="text-sm text-on-surface-variant font-medium pt-16 text-center">No payment data.</p>
<?php } elseif ($monthly_labels === []) { ?>
<p class="text-sm text-on-surface-variant font-medium pt-16 text-center">No <?php echo $scope_pay_charts === 'pending' ? 'pending' : 'collection'; ?> rows match these filters.</p>
<?php } else { ?>
<canvas id="chart-collection-trend" aria-label="Revenue collection trend chart"></canvas>
<?php } ?>
</div>
</div>

<div class="elevated-card rounded-3xl p-8 provider-card-lift">
<p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-900">Revenue by service</p>
<p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/55 mt-1"><?php echo $scope_pay_charts === 'pending' ? 'Pending breakdown by treatment' : 'Earnings breakdown by treatment'; ?></p>
<div class="reports-service-chart-panel mt-6">
<?php if ($t_payments === '') { ?>
<p class="text-sm text-on-surface-variant font-medium text-center py-16">No payment data.</p>
<?php } elseif ($service_labels === []) { ?>
<p class="text-sm text-on-surface-variant font-medium text-center py-16">No service-linked <?php echo $scope_pay_charts === 'pending' ? 'pending' : 'paid'; ?> amounts for these filters.</p>
<?php } else { ?>
<div class="reports-donut-canvas-host">
<canvas id="chart-service-donut" aria-label="Revenue by service donut chart"></canvas>
</div>
<div id="chart-service-legend" class="reports-service-legend mt-10 px-1">
<?php foreach ($service_labels as $si => $sl) {
    $col = $service_colors[$si] ?? '#22C55E';
    ?>
<span class="reports-service-legend-item inline-flex items-start gap-2.5 text-[11px] font-semibold text-slate-800 leading-snug">
<span class="w-3 h-3 rounded-full shrink-0 mt-0.5 ring-2 ring-white shadow-sm" style="background:<?php echo htmlspecialchars($col, ENT_QUOTES, 'UTF-8'); ?>"></span>
<span class="min-w-0"><?php echo htmlspecialchars($sl, ENT_QUOTES, 'UTF-8'); ?></span>
</span>
<?php } ?>
</div>
<?php } ?>
</div>
</div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
<div class="elevated-card rounded-3xl p-8 provider-card-lift xl:col-span-1">
<div class="flex items-start justify-between gap-3">
<div>
<p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-900">Top performers</p>
<p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/55 mt-1">Leading clients by visits & spend</p>
</div>
</div>
<ul class="mt-8 space-y-5">
<?php if ($t_appts === '') { ?>
<li class="text-sm text-on-surface-variant font-medium">Connect appointments to rank patients.</li>
<?php } elseif ($top_clients === []) { ?>
<li class="text-sm text-on-surface-variant font-medium">No patient activity matches these filters.</li>
<?php } else { ?>
<?php foreach ($top_clients as $idx => $tc) {
    $rank = $idx + 1;
    ?>
<li class="flex items-start gap-4">
<span class="shrink-0 w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 text-xs font-black flex items-center justify-center"><?php echo (int) $rank; ?></span>
<div class="min-w-0 flex-1">
<p class="font-headline font-extrabold text-slate-900 truncate"><?php echo htmlspecialchars($tc['name'], ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant/60 mt-1"><?php echo (int) $tc['appts']; ?> appointments</p>
</div>
<div class="text-right shrink-0">
<p class="text-emerald-600 font-extrabold tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($tc['spent']), ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[9px] font-black uppercase tracking-widest text-on-surface-variant/50 mt-0.5"><?php echo $filter_payment === 'pending' ? 'Pending' : 'Revenue'; ?></p>
</div>
</li>
<?php } ?>
<?php } ?>
</ul>
</div>

<div class="elevated-card rounded-3xl p-8 provider-card-lift xl:col-span-1">
<p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-900">Billing collection</p>
<p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/55 mt-1">Paid vs unpaid transaction volume</p>
<?php if ($t_payments === '') { ?>
<p class="text-sm text-on-surface-variant font-medium mt-10">No payment data.</p>
<?php } else { ?>
<div class="mt-10 space-y-8">
<div>
<div class="flex justify-between items-baseline gap-2 mb-2">
<span class="text-[10px] font-black uppercase tracking-wider text-slate-800">Paid (<?php echo (int) $billing_paid_n; ?>)</span>
<span class="text-sm font-extrabold text-emerald-600 tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($billing_paid_amt), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
<div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: <?php echo (int) $billing_paid_pct; ?>%;"></div>
</div>
</div>
<div>
<div class="flex justify-between items-baseline gap-2 mb-2">
<span class="text-[10px] font-black uppercase tracking-wider text-slate-800">Pending (<?php echo (int) $billing_pend_n; ?>)</span>
<span class="text-sm font-extrabold text-amber-500 tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($billing_pending_amt), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
<div class="h-full rounded-full bg-amber-400 transition-all duration-500" style="width: <?php echo (int) $billing_pend_pct; ?>%;"></div>
</div>
</div>
</div>
<?php } ?>
</div>

<div class="elevated-card rounded-3xl p-8 provider-card-lift xl:col-span-1 relative overflow-hidden">
<div class="flex items-start justify-between gap-3">
<div>
<p class="text-[11px] font-black uppercase tracking-[0.28em] text-slate-900">Clinic operations</p>
<p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/55 mt-1">Appointment lifecycle breakdown</p>
</div>
<span class="shrink-0 min-w-[2.25rem] h-9 px-2 rounded-full bg-slate-900 text-white text-xs font-black flex items-center justify-center" title="Confirmed + completed + in progress"><?php echo (int) $ops_total; ?></span>
</div>
<?php if ($t_appts === '') { ?>
<p class="text-sm text-on-surface-variant font-medium mt-10">No appointment storage resolved.</p>
<?php } elseif ($ops_total === 0) { ?>
<p class="text-sm text-on-surface-variant font-medium mt-10">No appointments in Confirmed / Completed / In progress for these filters.</p>
<?php } else { ?>
<div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-6">
<div>
<div class="flex justify-between items-center gap-2 mb-2">
<span class="text-[9px] font-black uppercase tracking-wider text-on-surface-variant">Confirmed</span>
<span class="text-[11px] font-bold text-slate-700 tabular-nums"><?php echo (int) $n_confirmed; ?> (<?php echo (int) $pct_conf; ?>%)</span>
</div>
<div class="h-2 rounded-full bg-slate-100 overflow-hidden">
<div class="h-full rounded-full bg-emerald-500" style="width: <?php echo (int) $pct_conf; ?>%;"></div>
</div>
</div>
<div>
<div class="flex justify-between items-center gap-2 mb-2">
<span class="text-[9px] font-black uppercase tracking-wider text-on-surface-variant">Completed</span>
<span class="text-[11px] font-bold text-slate-700 tabular-nums"><?php echo (int) $n_completed; ?> (<?php echo (int) $pct_done; ?>%)</span>
</div>
<div class="h-2 rounded-full bg-slate-100 overflow-hidden">
<div class="h-full rounded-full bg-emerald-600" style="width: <?php echo (int) $pct_done; ?>%;"></div>
</div>
</div>
<div>
<div class="flex justify-between items-center gap-2 mb-2">
<span class="text-[9px] font-black uppercase tracking-wider text-on-surface-variant">In progress</span>
<span class="text-[11px] font-bold text-slate-700 tabular-nums"><?php echo (int) $n_in_progress; ?> (<?php echo (int) $pct_prog; ?>%)</span>
</div>
<div class="h-2 rounded-full bg-slate-100 overflow-hidden">
<div class="h-full rounded-full bg-slate-400" style="width: <?php echo (int) $pct_prog; ?>%;"></div>
</div>
</div>
</div>
<?php } ?>
</div>
</div>

</div>
</main>

<div id="reports-preview-modal" class="hidden fixed inset-0 z-[110] overflow-y-auto py-6 px-4 sm:py-10 sm:px-6" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="reports-preview-heading">
<button type="button" id="reports-preview-backdrop" class="reports-preview-no-print fixed inset-0 cursor-default border-0 bg-slate-900/55 p-0 backdrop-blur-[2px]" aria-label="Close preview"></button>
<div class="reports-preview-shell relative z-10 mx-auto flex w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl max-h-[min(92vh,calc(100vh-3rem))]">
<div class="reports-preview-no-print flex flex-shrink-0 flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-5 py-4">
<div class="min-w-0">
<h2 id="reports-preview-heading" class="font-headline text-lg font-extrabold tracking-tight text-slate-900">Report preview</h2>
<p class="mt-1 truncate text-xs font-semibold text-on-surface-variant sm:whitespace-normal"><span class="text-slate-900"><?php echo htmlspecialchars($reports_clinic_name, ENT_QUOTES, 'UTF-8'); ?></span> · Check layout, then print or save as PDF.</p>
</div>
<div class="flex flex-shrink-0 flex-wrap gap-2">
<button type="button" id="reports-preview-print-btn" class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-white shadow-md hover:shadow-lg hover:shadow-primary/25 transition-all">
<span class="material-symbols-outlined text-[18px]">picture_as_pdf</span>
                    Print / PDF
                </button>
<button type="button" id="reports-preview-close-btn" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-on-background hover:bg-slate-50 transition-colors">
                    Close
                </button>
</div>
</div>
<div class="min-h-0 flex-1 overflow-y-auto bg-white">
<div id="reports-print-sheet" class="px-8 py-10 text-slate-900 sm:px-12 sm:py-12">
<header class="border-b border-slate-200 pb-6 mb-8">
<p class="text-[10px] font-black uppercase tracking-[0.35em] text-primary">Clinic reports</p>
<h1 class="font-headline mt-2 text-3xl font-extrabold tracking-tight text-slate-900"><?php echo htmlspecialchars($reports_clinic_name, ENT_QUOTES, 'UTF-8'); ?></h1>
<p class="mt-3 text-sm font-semibold text-slate-600">Summary exports for the tenant console</p>
<dl class="mt-6 grid gap-2 text-xs font-semibold text-slate-700 sm:grid-cols-2">
<div class="flex gap-2"><dt class="text-on-surface-variant shrink-0">Period</dt><dd><?php echo htmlspecialchars($reports_period_line, ENT_QUOTES, 'UTF-8'); ?></dd></div>
<div class="flex gap-2"><dt class="text-on-surface-variant shrink-0">Payment scope</dt><dd><?php echo htmlspecialchars($reports_pay_label, ENT_QUOTES, 'UTF-8'); ?></dd></div>
<div class="flex gap-2 sm:col-span-2"><dt class="text-on-surface-variant shrink-0">Search</dt><dd><?php echo htmlspecialchars($reports_search_line, ENT_QUOTES, 'UTF-8'); ?></dd></div>
<div class="flex gap-2 sm:col-span-2"><dt class="text-on-surface-variant shrink-0">Generated</dt><dd><?php echo htmlspecialchars($reports_generated_at, ENT_QUOTES, 'UTF-8'); ?> (Asia/Manila)</dd></div>
</dl>
</header>

<section class="mb-10">
<h2 class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-900">Summary</h2>
<table class="reports-print-table mt-4 w-full border-collapse border border-slate-200 text-sm">
<tbody>
<tr class="border-b border-slate-200"><td class="px-4 py-3 font-semibold text-slate-700">Total revenue (paid)</td><td class="px-4 py-3 text-right font-bold tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($total_revenue), ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr class="border-b border-slate-200"><td class="px-4 py-3 font-semibold text-slate-700">Pending / unpaid</td><td class="px-4 py-3 text-right font-bold tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($total_pending_amt), ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr class="border-b border-slate-200"><td class="px-4 py-3 font-semibold text-slate-700">Average rating</td><td class="px-4 py-3 text-right font-bold"><?php echo $reviews_count > 0 ? htmlspecialchars(number_format($reviews_avg, 2) . ' / 5', ENT_QUOTES, 'UTF-8') : '—'; ?></td></tr>
<tr><td class="px-4 py-3 font-semibold text-slate-700">Reviews count</td><td class="px-4 py-3 text-right font-bold"><?php echo (int) $reviews_count; ?></td></tr>
</tbody>
</table>
<p class="mt-3 text-[11px] font-medium text-slate-500">Charts on screen are not rendered here; figures below mirror the same filtered data.</p>
</section>

<section class="mb-10">
<h2 class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-900"><?php echo $scope_pay_charts === 'pending' ? 'Monthly pending volume' : 'Monthly collections'; ?></h2>
<?php if ($monthly_labels === []) { ?>
<p class="mt-3 text-sm text-slate-600">No rows for this filter.</p>
<?php } else { ?>
<table class="reports-print-table mt-4 w-full border-collapse border border-slate-200 text-sm">
<thead><tr class="bg-slate-50"><th class="border-b border-slate-200 px-4 py-2 text-left font-black uppercase tracking-wider text-[10px] text-slate-600">Month</th><th class="border-b border-slate-200 px-4 py-2 text-right font-black uppercase tracking-wider text-[10px] text-slate-600">Amount</th></tr></thead>
<tbody>
<?php foreach ($monthly_labels as $mi => $ml) {
    $mv = $monthly_values[$mi] ?? 0.0;
    ?>
<tr class="border-b border-slate-100"><td class="px-4 py-2 font-medium"><?php echo htmlspecialchars($ml, ENT_QUOTES, 'UTF-8'); ?></td><td class="px-4 py-2 text-right tabular-nums font-semibold"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso((float) $mv), ENT_QUOTES, 'UTF-8'); ?></td></tr>
<?php } ?>
</tbody>
</table>
<?php } ?>
</section>

<section class="mb-10">
<h2 class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-900"><?php echo $scope_pay_charts === 'pending' ? 'Pending by service' : 'Revenue by service'; ?></h2>
<?php if ($service_labels === []) { ?>
<p class="mt-3 text-sm text-slate-600">No service-linked amounts.</p>
<?php } else { ?>
<table class="reports-print-table mt-4 w-full border-collapse border border-slate-200 text-sm">
<thead><tr class="bg-slate-50"><th class="border-b border-slate-200 px-4 py-2 text-left font-black uppercase tracking-wider text-[10px] text-slate-600">Service</th><th class="border-b border-slate-200 px-4 py-2 text-right font-black uppercase tracking-wider text-[10px] text-slate-600">Amount</th></tr></thead>
<tbody>
<?php foreach ($service_labels as $si => $svcName) {
    $svcAmt = $service_values[$si] ?? 0.0;
    ?>
<tr class="border-b border-slate-100"><td class="px-4 py-2 font-medium"><?php echo htmlspecialchars((string) $svcName, ENT_QUOTES, 'UTF-8'); ?></td><td class="px-4 py-2 text-right tabular-nums font-semibold"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso((float) $svcAmt), ENT_QUOTES, 'UTF-8'); ?></td></tr>
<?php } ?>
</tbody>
</table>
<?php } ?>
</section>

<section class="mb-10">
<h2 class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-900">Top clients</h2>
<?php if ($top_clients === []) { ?>
<p class="mt-3 text-sm text-slate-600">No ranked patients for these filters.</p>
<?php } else { ?>
<table class="reports-print-table mt-4 w-full border-collapse border border-slate-200 text-sm">
<thead><tr class="bg-slate-50"><th class="border-b border-slate-200 px-4 py-2 text-left font-black uppercase tracking-wider text-[10px] text-slate-600">#</th><th class="border-b border-slate-200 px-4 py-2 text-left font-black uppercase tracking-wider text-[10px] text-slate-600">Client</th><th class="border-b border-slate-200 px-4 py-2 text-right font-black uppercase tracking-wider text-[10px] text-slate-600">Appointments</th><th class="border-b border-slate-200 px-4 py-2 text-right font-black uppercase tracking-wider text-[10px] text-slate-600"><?php echo $filter_payment === 'pending' ? 'Pending' : 'Spent'; ?></th></tr></thead>
<tbody>
<?php foreach ($top_clients as $ti => $tc) { ?>
<tr class="border-b border-slate-100"><td class="px-4 py-2 font-bold"><?php echo (int) ($ti + 1); ?></td><td class="px-4 py-2 font-medium"><?php echo htmlspecialchars((string) ($tc['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td><td class="px-4 py-2 text-right tabular-nums"><?php echo (int) ($tc['appts'] ?? 0); ?></td><td class="px-4 py-2 text-right tabular-nums font-semibold"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso((float) ($tc['spent'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td></tr>
<?php } ?>
</tbody>
</table>
<?php } ?>
</section>

<section class="mb-10">
<h2 class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-900">Billing collection</h2>
<table class="reports-print-table mt-4 w-full border-collapse border border-slate-200 text-sm">
<tbody>
<tr class="border-b border-slate-200"><td class="px-4 py-3 font-semibold text-slate-700">Paid (<?php echo (int) $billing_paid_n; ?>)</td><td class="px-4 py-3 text-right font-bold tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($billing_paid_amt), ENT_QUOTES, 'UTF-8'); ?></td></tr>
<tr><td class="px-4 py-3 font-semibold text-slate-700">Pending (<?php echo (int) $billing_pend_n; ?>)</td><td class="px-4 py-3 text-right font-bold tabular-nums"><?php echo htmlspecialchars(provider_tenant_rep_fmt_peso($billing_pending_amt), ENT_QUOTES, 'UTF-8'); ?></td></tr>
</tbody>
</table>
</section>

<section class="mb-4">
<h2 class="text-[11px] font-black uppercase tracking-[0.22em] text-slate-900">Clinic operations</h2>
<p class="mt-2 text-sm font-semibold text-slate-700">Tracked statuses (confirmed / scheduled, completed, in progress): <strong><?php echo (int) $ops_total; ?></strong></p>
<table class="reports-print-table mt-4 w-full border-collapse border border-slate-200 text-sm">
<tbody>
<tr class="border-b border-slate-200"><td class="px-4 py-3 font-semibold text-slate-700">Confirmed</td><td class="px-4 py-3 text-right font-bold"><?php echo (int) $n_confirmed; ?> (<?php echo (int) $pct_conf; ?>%)</td></tr>
<tr class="border-b border-slate-200"><td class="px-4 py-3 font-semibold text-slate-700">Completed</td><td class="px-4 py-3 text-right font-bold"><?php echo (int) $n_completed; ?> (<?php echo (int) $pct_done; ?>%)</td></tr>
<tr><td class="px-4 py-3 font-semibold text-slate-700">In progress</td><td class="px-4 py-3 text-right font-bold"><?php echo (int) $n_in_progress; ?> (<?php echo (int) $pct_prog; ?>%)</td></tr>
</tbody>
</table>
</section>

<footer class="mt-12 border-t border-slate-200 pt-6 text-[11px] font-semibold text-slate-500">
<p><?php echo htmlspecialchars($reports_clinic_name, ENT_QUOTES, 'UTF-8'); ?> · Tenant reports · <?php echo htmlspecialchars($reports_generated_at, ENT_QUOTES, 'UTF-8'); ?></p>
</footer>
</div>
</div>
</div>
</div>

<?php include __DIR__ . '/provider_tenant_profile_modal.inc.php'; ?>
<script>
(function () {
  var body = document.body;
  var sidebar = document.getElementById('provider-sidebar');
  var mobileToggle = document.getElementById('provider-mobile-sidebar-toggle');
  var mobileBackdrop = document.getElementById('provider-mobile-sidebar-backdrop');
  var desktopQuery = window.matchMedia('(min-width: 1024px)');
  if (!body || !sidebar || !mobileToggle || !mobileBackdrop) return;
  function setMobileSidebar(open) {
    body.classList.toggle('provider-mobile-sidebar-open', open);
    mobileToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    mobileToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    var icon = mobileToggle.querySelector('.material-symbols-outlined');
    if (icon) icon.textContent = open ? 'close' : 'menu';
  }
  function closeOnDesktop() {
    if (desktopQuery.matches) setMobileSidebar(false);
  }
  mobileToggle.addEventListener('click', function () {
    setMobileSidebar(!body.classList.contains('provider-mobile-sidebar-open'));
  });
  mobileBackdrop.addEventListener('click', function () { setMobileSidebar(false); });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && body.classList.contains('provider-mobile-sidebar-open')) setMobileSidebar(false);
  });
  sidebar.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      if (!desktopQuery.matches) setMobileSidebar(false);
    });
  });
  if (typeof desktopQuery.addEventListener === 'function') desktopQuery.addEventListener('change', closeOnDesktop);
  else if (typeof desktopQuery.addListener === 'function') desktopQuery.addListener(closeOnDesktop);
  setMobileSidebar(false);
})();
</script>
<script>
(function () {
  var modal = document.getElementById('reports-preview-modal');
  var backdrop = document.getElementById('reports-preview-backdrop');
  var btnPrev = document.getElementById('reports-open-preview-btn');
  var btnPrintTop = document.getElementById('reports-open-print-btn');
  var btnClose = document.getElementById('reports-preview-close-btn');
  var btnPrintModal = document.getElementById('reports-preview-print-btn');
  if (!modal || !btnPrev || !btnPrintTop || !btnClose || !btnPrintModal) {
    return;
  }

  function openModal() {
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    try {
      btnClose.focus();
    } catch (e) {}
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  function runPrint() {
    document.body.classList.add('provider-report-print-session');
    function cleanup() {
      document.body.classList.remove('provider-report-print-session');
      window.removeEventListener('afterprint', cleanup);
    }
    window.addEventListener('afterprint', cleanup);
    window.print();
  }

  btnPrev.addEventListener('click', openModal);
  btnClose.addEventListener('click', closeModal);
  if (backdrop) {
    backdrop.addEventListener('click', closeModal);
  }

  btnPrintModal.addEventListener('click', function () {
    runPrint();
  });

  btnPrintTop.addEventListener('click', function () {
    openModal();
    window.requestAnimationFrame(function () {
      try {
        btnPrintModal.focus();
      } catch (e) {}
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape' || modal.classList.contains('hidden')) {
      return;
    }
    closeModal();
  });
})();
</script>
<script>
(function () {
  var lbls = <?php echo json_encode($monthly_labels, JSON_UNESCAPED_UNICODE); ?>;
  var vals = <?php echo json_encode($monthly_values, JSON_UNESCAPED_UNICODE); ?>;
  var ctx = document.getElementById('chart-collection-trend');
  if (!ctx || typeof Chart === 'undefined' || !lbls.length) return;

  var green = '#22C55E';
  var grad = ctx.getContext('2d').createLinearGradient(0, 0, 0, 260);
  grad.addColorStop(0, 'rgba(34, 197, 94, 0.35)');
  grad.addColorStop(1, 'rgba(34, 197, 94, 0)');

  new Chart(ctx, {
    type: 'line',
    data: {
      labels: lbls,
      datasets: [{
        label: 'Amount',
        data: vals,
        borderColor: green,
        backgroundColor: grad,
        fill: true,
        tension: 0.35,
        pointRadius: 5,
        pointBackgroundColor: '#ffffff',
        pointBorderColor: green,
        pointBorderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function (item) {
              var v = item.parsed.y;
              return '₱' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 });
            }
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#64748b', font: { size: 11, weight: '600' } }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(148, 163, 184, 0.25)' },
          ticks: {
            color: '#64748b',
            callback: function (value) { return '₱' + Number(value).toLocaleString(); }
          }
        }
      }
    }
  });
})();

(function () {
  var svcLbl = <?php echo json_encode($service_labels, JSON_UNESCAPED_UNICODE); ?>;
  var svcVal = <?php echo json_encode($service_values, JSON_UNESCAPED_UNICODE); ?>;
  var cols = <?php echo json_encode($service_colors, JSON_UNESCAPED_UNICODE); ?>;
  var canvas = document.getElementById('chart-service-donut');
  if (!canvas || typeof Chart === 'undefined' || !svcLbl.length) return;

  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: svcLbl,
      datasets: [{
        data: svcVal,
        backgroundColor: svcLbl.map(function (_, i) { return cols[i % cols.length]; }),
        borderColor: '#ffffff',
        borderWidth: 3,
        hoverBorderColor: '#ffffff',
        hoverBorderWidth: 3,
        hoverOffset: 5,
        spacing: 1,
        borderRadius: 3
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { top: 4, bottom: 4, left: 4, right: 4 } },
      cutout: '54%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              var v = typeof ctx.raw === 'number' ? ctx.raw : (ctx.parsed !== undefined ? ctx.parsed : 0);
              return ctx.label + ': ₱' + Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 });
            }
          }
        }
      }
    }
  });
})();
</script>
</body></html>
