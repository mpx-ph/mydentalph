<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

@ini_set('memory_limit', '128M');
@set_time_limit(60);

function dashboard_pdf_bool(string $key, bool $default = false): bool
{
    if (!isset($_GET[$key])) {
        return $default;
    }
    $v = strtolower((string) $_GET[$key]);
    return $v === '1' || $v === 'true' || $v === 'on';
}

function dashboard_pdf_latin1_safe($text)
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($conv !== false) {
            return $conv;
        }
    }
    $clean = @preg_replace('/[^\x00-\x7F]/u', '?', $text);
    return ($clean === null) ? $text : $clean;
}

function dashboard_pdf_money(float $amount): string
{
    return 'PHP ' . number_format($amount, 2);
}

function dashboard_resolve_tcpdf_path()
{
    $candidates = [
        dirname(__DIR__) . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        dirname(dirname(__DIR__)) . '/vendor/tecnickcom/tcpdf/tcpdf.php',
    ];
    foreach ($candidates as $rel) {
        $path = realpath($rel);
        if ($path !== false && is_readable($path)) {
            return $path;
        }
    }
    return null;
}

date_default_timezone_set('Asia/Manila');
$period = isset($_GET['period']) ? strtolower(trim((string) $_GET['period'])) : 'last30';
$allowedPeriods = ['last30', 'today', 'week', 'month', 'year'];
if (!in_array($period, $allowedPeriods, true)) {
    $period = 'last30';
}

$labels = [
    'last30' => 'Last 30 Days',
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'year' => 'This Year',
];
$periodLabel = $labels[$period] ?? 'Last 30 Days';

$end = new DateTime('today');
$end->setTime(23, 59, 59);
$start = clone $end;
switch ($period) {
    case 'today':
        $start->setTime(0, 0, 0);
        break;
    case 'week':
        $dow = (int) $start->format('N');
        $start->modify('-' . ($dow - 1) . ' days');
        $start->setTime(0, 0, 0);
        break;
    case 'month':
        $start = new DateTime('first day of this month 00:00:00');
        break;
    case 'year':
        $start = new DateTime('first day of January ' . date('Y') . ' 00:00:00');
        break;
    case 'last30':
    default:
        $start->modify('-29 days');
        $start->setTime(0, 0, 0);
        break;
}
$startSql = $start->format('Y-m-d H:i:s');
$endSql = $end->format('Y-m-d H:i:s');

$optionKeys = ['include_overview', 'include_revenue', 'include_growth', 'include_activity'];
$hasExplicit = false;
foreach ($optionKeys as $k) {
    if (isset($_GET[$k])) {
        $hasExplicit = true;
        break;
    }
}
$includeOverview = dashboard_pdf_bool('include_overview', !$hasExplicit);
$includeRevenue = dashboard_pdf_bool('include_revenue', !$hasExplicit);
$includeGrowth = dashboard_pdf_bool('include_growth', !$hasExplicit);
$includeActivity = dashboard_pdf_bool('include_activity', !$hasExplicit);

$metrics = [
    'total_registered_clinics' => 0,
    'active_clinics' => 0,
    'revenue' => 0.0,
    'patient_records' => 0,
    'expiring_subscriptions' => 0,
];
$topPerforming = [];
$growthRows = [];
$activity = ['active' => 0, 'inactive' => 0, 'suspended' => 0];
$revenueByMonth = [];
$revenueByWeek = [];
$revenueByYear = [];
$dbError = null;

try {
    $metrics['total_registered_clinics'] = (int) $pdo->query('SELECT COUNT(*) FROM tbl_tenants')->fetchColumn();
    $metrics['active_clinics'] = (int) $pdo->query("
        SELECT COUNT(DISTINCT t.tenant_id)
        FROM tbl_tenants t
        INNER JOIN tbl_tenant_subscriptions s ON s.tenant_id = t.tenant_id
        WHERE t.subscription_status = 'active'
          AND t.clinic_slug IS NOT NULL AND TRIM(t.clinic_slug) <> ''
          AND s.payment_status = 'paid'
          AND (s.subscription_end IS NULL OR s.subscription_end >= CURDATE())
    ")->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(COALESCE(amount_paid, 0)), 0)
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND created_at >= ?
          AND created_at <= ?
    ");
    $stmt->execute([$startSql, $endSql]);
    $metrics['revenue'] = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_patients WHERE created_at >= ? AND created_at <= ?");
    $stmt->execute([$startSql, $endSql]);
    $metrics['patient_records'] = (int) $stmt->fetchColumn();

    $todayDate = (new DateTime('today'))->format('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT tenant_id)
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND subscription_end IS NOT NULL
          AND subscription_end BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)
    ");
    $stmt->execute([$todayDate, $todayDate]);
    $metrics['expiring_subscriptions'] = (int) $stmt->fetchColumn();

    if ($includeGrowth) {
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
            FROM tbl_patients
            WHERE created_at >= ?
              AND created_at <= ?
            GROUP BY ym
            ORDER BY ym ASC
        ");
        $stmt->execute([$startSql, $endSql]);
        $growthRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("
            SELECT t.clinic_name, COALESCE(SUM(COALESCE(s.amount_paid, 0)), 0) AS revenue
            FROM tbl_tenants t
            INNER JOIN tbl_tenant_subscriptions s
                ON s.tenant_id = t.tenant_id AND s.payment_status = 'paid'
            WHERE s.created_at >= ?
              AND s.created_at <= ?
            GROUP BY t.tenant_id, t.clinic_name
            ORDER BY revenue DESC
            LIMIT 10
        ");
        $stmt->execute([$startSql, $endSql]);
        $topPerforming = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($includeRevenue) {
        $stmt = $pdo->prepare("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS bucket, COALESCE(SUM(COALESCE(amount_paid, 0)), 0) AS total
            FROM tbl_tenant_subscriptions
            WHERE payment_status = 'paid'
              AND created_at >= ?
              AND created_at <= ?
            GROUP BY bucket
            ORDER BY bucket ASC
        ");
        $stmt->execute([$startSql, $endSql]);
        $revenueByMonth = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("
            SELECT DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY)) AS bucket, COALESCE(SUM(COALESCE(amount_paid, 0)), 0) AS total
            FROM tbl_tenant_subscriptions
            WHERE payment_status = 'paid'
              AND created_at >= ?
              AND created_at <= ?
            GROUP BY bucket
            ORDER BY bucket ASC
        ");
        $stmt->execute([$startSql, $endSql]);
        $revenueByWeek = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("
            SELECT YEAR(created_at) AS bucket, COALESCE(SUM(COALESCE(amount_paid, 0)), 0) AS total
            FROM tbl_tenant_subscriptions
            WHERE payment_status = 'paid'
              AND created_at >= ?
              AND created_at <= ?
            GROUP BY bucket
            ORDER BY bucket ASC
        ");
        $stmt->execute([$startSql, $endSql]);
        $revenueByYear = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($includeActivity) {
        $stmt = $pdo->query("
            SELECT subscription_status, COUNT(*) AS cnt
            FROM tbl_tenants
            GROUP BY subscription_status
        ");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = (string) ($row['subscription_status'] ?? '');
            if (isset($activity[$status])) {
                $activity[$status] = (int) ($row['cnt'] ?? 0);
            }
        }
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$filename = 'MyDental_Dashboard_' . date('Y-m-d') . '.pdf';
$tcpdfPath = dashboard_resolve_tcpdf_path();

if ($tcpdfPath !== null) {
    require_once $tcpdfPath;
    class DashboardExportPDF extends TCPDF
    {
        public function Footer()
        {
            $this->SetY(-14);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(0, 10, 'MyDental Dashboard Analytics | Generated ' . date('M j, Y g:i A') . ' | Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }
    try {
        $pdf = new DashboardExportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(12);
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 102, 255);
        $pdf->Cell(0, 8, 'MyDental Dashboard Analytics', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 90, 100);
        $pdf->Cell(0, 6, 'Period: ' . $periodLabel . ' (' . $start->format('M j, Y') . ' - ' . $end->format('M j, Y') . ')', 0, 1, 'L');
        $pdf->Ln(3);

        if ($dbError !== null) {
            $pdf->SetFillColor(255, 235, 235);
            $pdf->SetDrawColor(220, 180, 180);
            $pdf->MultiCell(0, 6, 'Data could not be loaded: ' . $dbError, 1, 'L', true);
        } else {
            if ($includeOverview) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Overview metrics', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 9.5);
                $summary = 'Total registered clinics: ' . number_format($metrics['total_registered_clinics'])
                    . ' | Active clinics: ' . number_format($metrics['active_clinics'])
                    . ' | Revenue (selected period): ' . dashboard_pdf_money((float) $metrics['revenue'])
                    . ' | Patient records (selected period): ' . number_format($metrics['patient_records'])
                    . ' | Expiring subscriptions (next 30 days): ' . number_format($metrics['expiring_subscriptions']);
                $pdf->MultiCell(0, 6, $summary, 1, 'L', true);
                $pdf->Ln(3);
            }

            if ($includeGrowth) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Tenant growth (patient registrations)', 0, 1, 'L');
                $html = '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:8.8pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="60%">Month</th><th width="40%" align="right">Registrations</th></tr>';
                if (empty($growthRows)) {
                    $html .= '<tr><td colspan="2" align="center">No data for this period.</td></tr>';
                } else {
                    foreach ($growthRows as $r) {
                        $html .= '<tr><td>' . htmlspecialchars((string) ($r['ym'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td align="right">' . number_format((int) ($r['cnt'] ?? 0)) . '</td></tr>';
                    }
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf->Ln(2);

                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Top performing clinics (revenue)', 0, 1, 'L');
                $html = '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:8.8pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="12%">Rank</th><th width="58%">Clinic</th><th width="30%" align="right">Revenue</th></tr>';
                if (empty($topPerforming)) {
                    $html .= '<tr><td colspan="3" align="center">No paid subscription revenue for this period.</td></tr>';
                } else {
                    foreach ($topPerforming as $idx => $r) {
                        $html .= '<tr><td align="center">' . (int) ($idx + 1) . '</td><td>' . htmlspecialchars((string) ($r['clinic_name'] ?? 'Unknown Clinic'), ENT_QUOTES, 'UTF-8') . '</td><td align="right">' . htmlspecialchars(dashboard_pdf_money((float) ($r['revenue'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td></tr>';
                    }
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }

            if ($includeRevenue) {
                if ($pdf->GetY() > 230) {
                    $pdf->AddPage();
                }
                $pdf->Ln(3);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Revenue analytics breakdown', 0, 1, 'L');
                $html = '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:8.5pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="26%">Granularity</th><th width="44%">Period</th><th width="30%" align="right">Revenue</th></tr>';
                if (empty($revenueByMonth) && empty($revenueByWeek) && empty($revenueByYear)) {
                    $html .= '<tr><td colspan="3" align="center">No paid subscription revenue in this period.</td></tr>';
                } else {
                    foreach ($revenueByMonth as $r) {
                        $html .= '<tr><td>Monthly</td><td>' . htmlspecialchars((string) ($r['bucket'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td align="right">' . htmlspecialchars(dashboard_pdf_money((float) ($r['total'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td></tr>';
                    }
                    foreach ($revenueByWeek as $r) {
                        $html .= '<tr><td>Weekly</td><td>' . htmlspecialchars((string) ($r['bucket'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td align="right">' . htmlspecialchars(dashboard_pdf_money((float) ($r['total'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td></tr>';
                    }
                    foreach ($revenueByYear as $r) {
                        $html .= '<tr><td>Yearly</td><td>' . htmlspecialchars((string) ($r['bucket'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td align="right">' . htmlspecialchars(dashboard_pdf_money((float) ($r['total'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td></tr>';
                    }
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }

            if ($includeActivity) {
                if ($pdf->GetY() > 235) {
                    $pdf->AddPage();
                }
                $pdf->Ln(3);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Clinic activity distribution', 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 9.5);
                $pdf->MultiCell(
                    0,
                    6,
                    'Active: ' . number_format((int) $activity['active']) . ' | Inactive: ' . number_format((int) $activity['inactive']) . ' | Suspended: ' . number_format((int) $activity['suspended']),
                    1,
                    'L',
                    true
                );
            }
        }
        $bytes = $pdf->Output($filename, 'S');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    } catch (Throwable $e) {
        error_log('dashboard_export_pdf TCPDF failed: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/lib/fpdf.php';
class DashboardExportFPDF extends FPDF
{
    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 10, dashboard_pdf_latin1_safe('MyDental Dashboard Analytics | Page ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }
}

try {
    $pdf = new DashboardExportFPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(15, 18, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 16);
    $pdf->SetTextColor(0, 102, 255);
    $pdf->Cell(0, 8, dashboard_pdf_latin1_safe('MyDental Dashboard Analytics'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(60, 70, 80);
    $pdf->Cell(0, 6, dashboard_pdf_latin1_safe('Period: ' . $periodLabel . ' (' . $start->format('M j, Y') . ' - ' . $end->format('M j, Y') . ')'), 0, 1, 'L');
    $pdf->Ln(4);

    if ($dbError !== null) {
        $pdf->SetTextColor(180, 20, 20);
        $pdf->MultiCell(0, 6, dashboard_pdf_latin1_safe('Data could not be loaded: ' . $dbError), 1, 'L');
    } else {
        $pdf->SetTextColor(30, 40, 50);
        if ($includeOverview) {
            $txt = "Overview metrics\n"
                . '- Total registered clinics: ' . number_format($metrics['total_registered_clinics']) . "\n"
                . '- Active clinics: ' . number_format($metrics['active_clinics']) . "\n"
                . '- Revenue (selected period): ' . dashboard_pdf_money((float) $metrics['revenue']) . "\n"
                . '- Patient records (selected period): ' . number_format($metrics['patient_records']) . "\n"
                . '- Expiring subscriptions (next 30 days): ' . number_format($metrics['expiring_subscriptions']);
            $pdf->MultiCell(0, 6, dashboard_pdf_latin1_safe($txt), 1, 'L');
            $pdf->Ln(3);
        }
        if ($includeGrowth) {
            $txt = "Tenant growth (patient registrations)\n";
            if (empty($growthRows)) {
                $txt .= '- No data for this period.';
            } else {
                foreach ($growthRows as $r) {
                    $txt .= '- ' . (string) ($r['ym'] ?? '') . ': ' . number_format((int) ($r['cnt'] ?? 0)) . "\n";
                }
            }
            $pdf->MultiCell(0, 6, dashboard_pdf_latin1_safe(rtrim($txt)), 1, 'L');
            $pdf->Ln(3);

            $txt = "Top performing clinics (revenue)\n";
            if (empty($topPerforming)) {
                $txt .= '- No paid subscription revenue for this period.';
            } else {
                foreach ($topPerforming as $idx => $r) {
                    $txt .= '- ' . ($idx + 1) . '. ' . (string) ($r['clinic_name'] ?? 'Unknown Clinic') . ': ' . dashboard_pdf_money((float) ($r['revenue'] ?? 0)) . "\n";
                }
            }
            $pdf->MultiCell(0, 6, dashboard_pdf_latin1_safe(rtrim($txt)), 1, 'L');
            $pdf->Ln(3);
        }
        if ($includeRevenue) {
            $txt = "Revenue analytics breakdown\n";
            if (empty($revenueByMonth) && empty($revenueByWeek) && empty($revenueByYear)) {
                $txt .= '- No paid subscription revenue in this period.';
            } else {
                foreach ($revenueByMonth as $r) {
                    $txt .= '- Monthly ' . (string) ($r['bucket'] ?? '') . ': ' . dashboard_pdf_money((float) ($r['total'] ?? 0)) . "\n";
                }
                foreach ($revenueByWeek as $r) {
                    $txt .= '- Weekly ' . (string) ($r['bucket'] ?? '') . ': ' . dashboard_pdf_money((float) ($r['total'] ?? 0)) . "\n";
                }
                foreach ($revenueByYear as $r) {
                    $txt .= '- Yearly ' . (string) ($r['bucket'] ?? '') . ': ' . dashboard_pdf_money((float) ($r['total'] ?? 0)) . "\n";
                }
            }
            $pdf->MultiCell(0, 6, dashboard_pdf_latin1_safe(rtrim($txt)), 1, 'L');
            $pdf->Ln(3);
        }
        if ($includeActivity) {
            $txt = "Clinic activity distribution\n"
                . '- Active: ' . number_format((int) $activity['active']) . "\n"
                . '- Inactive: ' . number_format((int) $activity['inactive']) . "\n"
                . '- Suspended: ' . number_format((int) $activity['suspended']);
            $pdf->MultiCell(0, 6, dashboard_pdf_latin1_safe($txt), 1, 'L');
        }
    }
    $bytes = $pdf->Output('S', $filename);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) . '"');
    header('Content-Length: ' . strlen($bytes));
    echo $bytes;
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF generation failed. Please try again.';
}

