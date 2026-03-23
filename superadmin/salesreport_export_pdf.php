<?php
/**
 * PDF export for superadmin sales report (paid subscriptions).
 * Uses TCPDF when available, with FPDF fallback.
 */
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

@ini_set('memory_limit', '128M');
@set_time_limit(60);

function salesreport_pdf_bool(string $key, bool $default = false): bool
{
    if (!isset($_GET[$key])) {
        return $default;
    }
    $v = (string) $_GET[$key];
    return $v === '1' || strtolower($v) === 'true' || strtolower($v) === 'on';
}

function salesreport_pdf_latin1_safe($text)
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

function salesreport_send_pdf_download($pdfBinary, $downloadName)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) $downloadName));
    if ($downloadName === '') {
        $downloadName = 'sales_report.pdf';
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($pdfBinary));
    echo $pdfBinary;
}

function salesreport_resolve_tcpdf_path()
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

function salesreport_money($amount): string
{
    return 'PHP ' . number_format((float) $amount, 2);
}

date_default_timezone_set('Asia/Manila');
$todayStart = new DateTime('today');
$todayStart->setTime(0, 0, 0);
$todayEnd = clone $todayStart;
$todayEnd->modify('+1 day');
$weekStart = clone $todayStart;
$weekStart->modify('-' . (((int) $weekStart->format('N')) - 1) . ' days');
$weekEnd = clone $weekStart;
$weekEnd->modify('+7 days');
$monthStart = new DateTime('first day of this month');
$monthStart->setTime(0, 0, 0);
$monthEnd = clone $monthStart;
$monthEnd->modify('+1 month');
$yearStart = new DateTime('first day of January ' . $todayStart->format('Y'));
$yearStart->setTime(0, 0, 0);
$yearEnd = clone $yearStart;
$yearEnd->modify('+1 year');

$includeToday = salesreport_pdf_bool('include_today', true);
$includeWeek = salesreport_pdf_bool('include_week', true);
$includeMonth = salesreport_pdf_bool('include_month', true);
$includeYear = salesreport_pdf_bool('include_year', true);
$includeDaily = salesreport_pdf_bool('include_daily', true);
$includeTransactions = salesreport_pdf_bool('include_transactions', false);
$includeTopClinics = salesreport_pdf_bool('include_top_clinics', true);

$todayRevenue = 0.0;
$weekRevenue = 0.0;
$monthRevenue = 0.0;
$yearRevenue = 0.0;
$recentDailyRevenue = [];
$transactionRows = [];
$topClinics = [];
$dbError = null;

try {
    $sumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) AS revenue
        FROM tbl_tenant_subscriptions
        WHERE payment_status = 'paid'
          AND created_at >= ?
          AND created_at < ?
    ");
    $sumStmt->execute([$todayStart->format('Y-m-d H:i:s'), $todayEnd->format('Y-m-d H:i:s')]);
    $todayRevenue = (float) ($sumStmt->fetchColumn() ?: 0);
    $sumStmt->execute([$weekStart->format('Y-m-d H:i:s'), $weekEnd->format('Y-m-d H:i:s')]);
    $weekRevenue = (float) ($sumStmt->fetchColumn() ?: 0);
    $sumStmt->execute([$monthStart->format('Y-m-d H:i:s'), $monthEnd->format('Y-m-d H:i:s')]);
    $monthRevenue = (float) ($sumStmt->fetchColumn() ?: 0);
    $sumStmt->execute([$yearStart->format('Y-m-d H:i:s'), $yearEnd->format('Y-m-d H:i:s')]);
    $yearRevenue = (float) ($sumStmt->fetchColumn() ?: 0);

    if ($includeDaily) {
        $dailyStart = clone $todayStart;
        $dailyStart->modify('-4 days');
        $dailyMapStmt = $pdo->prepare("
            SELECT DATE(created_at) AS d, COALESCE(SUM(amount_paid), 0) AS revenue
            FROM tbl_tenant_subscriptions
            WHERE payment_status = 'paid'
              AND created_at >= ?
              AND created_at < ?
            GROUP BY DATE(created_at)
            ORDER BY d DESC
        ");
        $dailyMapStmt->execute([$dailyStart->format('Y-m-d H:i:s'), $todayEnd->format('Y-m-d H:i:s')]);
        $dayMap = [];
        foreach ($dailyMapStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dayMap[(string) $r['d']] = (float) $r['revenue'];
        }
        for ($i = 0; $i < 5; $i++) {
            $d = clone $todayStart;
            $d->modify('-' . $i . ' days');
            $k = $d->format('Y-m-d');
            $recentDailyRevenue[] = [
                'label' => $d->format('M j, Y'),
                'revenue' => (float) ($dayMap[$k] ?? 0),
            ];
        }
    }

    if ($includeTransactions) {
        $txStmt = $pdo->prepare("
            SELECT
                ts.created_at,
                ts.amount_paid,
                ts.payment_status,
                ts.reference_number,
                t.clinic_name,
                sp.plan_name
            FROM tbl_tenant_subscriptions ts
            LEFT JOIN tbl_tenants t ON ts.tenant_id = t.tenant_id
            LEFT JOIN tbl_subscription_plans sp ON ts.plan_id = sp.plan_id
            WHERE ts.payment_status = 'paid'
            ORDER BY ts.created_at DESC, ts.id DESC
        ");
        $txStmt->execute();
        $transactionRows = $txStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($includeTopClinics) {
        $topStmt = $pdo->prepare("
            SELECT
                t.clinic_name,
                COUNT(ts.id) AS paid_transactions,
                COALESCE(SUM(ts.amount_paid), 0) AS total_spend
            FROM tbl_tenant_subscriptions ts
            INNER JOIN tbl_tenants t ON ts.tenant_id = t.tenant_id
            WHERE ts.payment_status = 'paid'
            GROUP BY ts.tenant_id, t.clinic_name
            ORDER BY total_spend DESC, paid_transactions DESC, t.clinic_name ASC
        ");
        $topStmt->execute();
        $topClinics = $topStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$filename = 'MyDental_Sales_Report_' . date('Y-m-d') . '.pdf';
$tcpdfPath = salesreport_resolve_tcpdf_path();

if ($tcpdfPath !== null) {
    require_once $tcpdfPath;

    class SalesReportExportPDF extends TCPDF
    {
        public function Footer()
        {
            $this->SetY(-14);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(
                0,
                10,
                'MyDental - Sales Report | Generated ' . date('M j, Y g:i A') . ' | Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(),
                0,
                0,
                'C'
            );
        }
    }

    try {
        $pdf = new SalesReportExportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MyDental');
        $pdf->SetAuthor('MyDental Platform');
        $pdf->SetTitle('Sales Report - Paid Subscriptions');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(12);
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(0, 102, 255);
        $pdf->Cell(0, 8, 'MyDental Sales Report', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9.5);
        $pdf->SetTextColor(80, 90, 100);
        $pdf->Cell(0, 6, 'Paid subscription analytics across all clinics', 0, 1, 'L');
        $pdf->Ln(2);
        $pdf->SetDrawColor(0, 102, 255);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        if ($dbError !== null) {
            $pdf->SetFillColor(255, 235, 235);
            $pdf->SetDrawColor(220, 180, 180);
            $pdf->MultiCell(0, 6, 'Data could not be loaded: ' . $dbError, 1, 'L', true);
        } else {
            $summaryLines = [];
            if ($includeToday) $summaryLines[] = "Today's Revenue: " . salesreport_money($todayRevenue);
            if ($includeWeek) $summaryLines[] = "Weekly Revenue: " . salesreport_money($weekRevenue);
            if ($includeMonth) $summaryLines[] = "Monthly Revenue: " . salesreport_money($monthRevenue);
            if ($includeYear) $summaryLines[] = "Yearly Revenue: " . salesreport_money($yearRevenue);
            if (empty($summaryLines)) $summaryLines[] = 'No revenue summary metrics selected.';

            $pdf->SetFillColor(240, 245, 255);
            $pdf->SetDrawColor(190, 205, 235);
            $pdf->SetFont('helvetica', '', 9.5);
            $pdf->MultiCell(0, 6, implode('  |  ', $summaryLines), 1, 'L', true);
            $pdf->Ln(4);

            if ($includeDaily) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(25, 35, 50);
                $pdf->Cell(0, 6, 'Recent daily revenue (last 5 days)', 0, 1, 'L');
                $html = '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:9pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="60%">Date</th><th width="40%" align="right">Revenue</th></tr>';
                foreach ($recentDailyRevenue as $r) {
                    $html .= '<tr><td>' . htmlspecialchars((string) $r['label'], ENT_QUOTES, 'UTF-8') . '</td><td align="right">' . htmlspecialchars(salesreport_money((float) $r['revenue']), ENT_QUOTES, 'UTF-8') . '</td></tr>';
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf->Ln(3);
            }

            if ($includeTopClinics) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Top clinics by total subscription spend', 0, 1, 'L');
                $html = '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:8.8pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="12%">Rank</th><th width="44%">Clinic</th><th width="20%" align="right">Transactions</th><th width="24%" align="right">Total Spend</th></tr>';
                if (empty($topClinics)) {
                    $html .= '<tr><td colspan="4" align="center">No paid subscription data found.</td></tr>';
                } else {
                    foreach ($topClinics as $i => $c) {
                        $html .= '<tr>';
                        $html .= '<td align="center">' . (int) ($i + 1) . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($c['clinic_name'] ?? 'Unknown Clinic'), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td align="right">' . number_format((int) ($c['paid_transactions'] ?? 0)) . '</td>';
                        $html .= '<td align="right">' . htmlspecialchars(salesreport_money((float) ($c['total_spend'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '</tr>';
                    }
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf->Ln(3);
            }

            if ($includeTransactions) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Full transaction log (paid subscriptions)', 0, 1, 'L');
                $html = '<table border="1" cellpadding="3" cellspacing="0" width="100%" style="font-size:8.3pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="24%">Date</th><th width="28%">Clinic</th><th width="20%">Plan</th><th width="14%" align="right">Amount</th><th width="14%">Reference</th></tr>';
                if (empty($transactionRows)) {
                    $html .= '<tr><td colspan="5" align="center">No paid subscription transactions found.</td></tr>';
                } else {
                    foreach ($transactionRows as $row) {
                        $ts = strtotime((string) ($row['created_at'] ?? ''));
                        $dateLabel = $ts ? date('M j, Y H:i', $ts) : '-';
                        $html .= '<tr>';
                        $html .= '<td>' . htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($row['clinic_name'] ?? 'Unknown Clinic'), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($row['plan_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td align="right">' . htmlspecialchars(salesreport_money((float) ($row['amount_paid'] ?? 0)), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($row['reference_number'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '</tr>';
                    }
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }
        }

        $pdfData = $pdf->Output($filename, 'S');
        salesreport_send_pdf_download($pdfData, $filename);
        exit;
    } catch (Throwable $e) {
        error_log('salesreport_export_pdf TCPDF: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/lib/fpdf.php';

class SalesReportExportFPDF extends FPDF
{
    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 10, 'MyDental Sales Report | Generated ' . date('M j, Y g:i A') . ' | Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $pdf = new SalesReportExportFPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(15, 18, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $pdf->SetFont('Helvetica', '', 16);
    $pdf->SetTextColor(0, 102, 255);
    $pdf->Cell(0, 8, salesreport_pdf_latin1_safe('MyDental Sales Report'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(80, 90, 100);
    $pdf->Cell(0, 6, salesreport_pdf_latin1_safe('Paid subscription analytics across all clinics'), 0, 1, 'L');
    $pdf->Ln(3);

    if ($dbError !== null) {
        $pdf->SetFillColor(255, 235, 235);
        $pdf->SetDrawColor(220, 180, 180);
        $pdf->MultiCell(0, 6, salesreport_pdf_latin1_safe('Data could not be loaded: ' . $dbError), 1, 'L', true);
    } else {
        $summary = [];
        if ($includeToday) $summary[] = "Today's Revenue: " . salesreport_money($todayRevenue);
        if ($includeWeek) $summary[] = "Weekly Revenue: " . salesreport_money($weekRevenue);
        if ($includeMonth) $summary[] = "Monthly Revenue: " . salesreport_money($monthRevenue);
        if ($includeYear) $summary[] = "Yearly Revenue: " . salesreport_money($yearRevenue);
        if (empty($summary)) $summary[] = 'No revenue summary metrics selected.';
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->MultiCell(0, 6, salesreport_pdf_latin1_safe(implode(' | ', $summary)), 1, 'L', false);
        $pdf->Ln(3);

        if ($includeDaily) {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->Cell(0, 6, salesreport_pdf_latin1_safe('Recent daily revenue (last 5 days)'), 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 9);
            foreach ($recentDailyRevenue as $r) {
                $pdf->Cell(100, 6, salesreport_pdf_latin1_safe((string) $r['label']), 1, 0, 'L');
                $pdf->Cell(80, 6, salesreport_pdf_latin1_safe(salesreport_money((float) $r['revenue'])), 1, 1, 'R');
            }
            $pdf->Ln(3);
        }
    }

    $pdfData = $pdf->Output('S', $filename);
    salesreport_send_pdf_download($pdfData, $filename);
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF generation failed. Please try again.';
}

