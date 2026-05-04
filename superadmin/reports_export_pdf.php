<?php
/**
 * PDF export for superadmin reports (visits, registrations, registration table).
 * Uses TCPDF when available, with FPDF fallback.
 * Date range logic matches reports.php (MySQL session +08:00).
 */
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

@date_default_timezone_set('Asia/Manila');
@ini_set('memory_limit', '128M');
@set_time_limit(120);

function reports_export_pdf_bool(string $key, bool $default = false): bool
{
    if (!isset($_GET[$key])) {
        return $default;
    }
    $v = (string) $_GET[$key];
    return $v === '1' || strtolower($v) === 'true' || strtolower($v) === 'on';
}

function reports_export_pdf_latin1_safe($text)
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

function reports_export_send_pdf_download($pdfBinary, $downloadName)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) $downloadName));
    if ($downloadName === '') {
        $downloadName = 'reports.pdf';
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $downloadName . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($pdfBinary));
    echo $pdfBinary;
}

function reports_export_resolve_tcpdf_path()
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

/**
 * @return array{start:string,end:string,label:string,end_inclusive:bool}
 */
function reports_export_mysql_period_range(PDO $pdo, string $period, ?string $dateFrom, ?string $dateTo): array
{
    $period = strtolower(trim($period));
    $allowed = ['today', 'yesterday', 'week', 'month', 'year', 'custom'];
    if (!in_array($period, $allowed, true)) {
        $period = 'yesterday';
    }

    if ($period === 'today') {
        $row = $pdo->query("
            SELECT CONCAT(CURDATE(), ' 00:00:00') AS s,
                   DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS e
        ")->fetch(PDO::FETCH_ASSOC);
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => 'Today (live)',
            'end_inclusive' => true,
        ];
    }

    if ($period === 'yesterday') {
        $row = $pdo->query("
            SELECT CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 00:00:00') AS s,
                   CONCAT(CURDATE(), ' 00:00:00') AS e
        ")->fetch(PDO::FETCH_ASSOC);
        $d = $pdo->query('SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), \'%Y-%m-%d\')')->fetchColumn();
        $label = $d ? date('M j, Y', strtotime((string) $d . ' 12:00:00')) : 'Yesterday';
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => $label,
            'end_inclusive' => false,
        ];
    }

    if ($period === 'week') {
        $row = $pdo->query("
            SELECT
                CONCAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), ' 00:00:00') AS s,
                LEAST(
                    NOW(),
                    DATE_ADD(CONCAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), ' 00:00:00'), INTERVAL 7 DAY)
                ) AS e
        ")->fetch(PDO::FETCH_ASSOC);
        $ds = $pdo->query("SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), '%Y-%m-%d')")->fetchColumn();
        $de = $pdo->query("
            SELECT DATE_FORMAT(
                LEAST(
                    NOW(),
                    DATE_ADD(CONCAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), ' 00:00:00'), INTERVAL 7 DAY)
                ),
                '%Y-%m-%d'
            )
        ")->fetchColumn();
        $label = 'This week · ' . date('M j', strtotime((string) $ds . ' 12:00:00'))
            . ' – ' . date('M j, Y', strtotime((string) $de . ' 12:00:00'));
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => $label,
            'end_inclusive' => true,
        ];
    }

    if ($period === 'month') {
        $row = $pdo->query("
            SELECT
                CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m-01'), ' 00:00:00') AS s,
                LEAST(
                    NOW(),
                    DATE_ADD(CONCAT(DATE_FORMAT(CURDATE(), '%Y-%m-01'), ' 00:00:00'), INTERVAL 1 MONTH)
                ) AS e
        ")->fetch(PDO::FETCH_ASSOC);
        $m = $pdo->query("SELECT DATE_FORMAT(CURDATE(), '%M %Y')")->fetchColumn();
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => 'This month · ' . (string) $m,
            'end_inclusive' => true,
        ];
    }

    if ($period === 'year') {
        $row = $pdo->query("
            SELECT
                CONCAT(YEAR(CURDATE()), '-01-01 00:00:00') AS s,
                LEAST(
                    NOW(),
                    DATE_ADD(CONCAT(YEAR(CURDATE()), '-01-01 00:00:00'), INTERVAL 1 YEAR)
                ) AS e
        ")->fetch(PDO::FETCH_ASSOC);
        $y = $pdo->query('SELECT YEAR(CURDATE())')->fetchColumn();
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => 'This year · ' . (string) $y,
            'end_inclusive' => true,
        ];
    }

    $okFrom = $dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom);
    $okTo = $dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo);
    if (!$okFrom || !$okTo) {
        $row = $pdo->query("
            SELECT CONCAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), ' 00:00:00') AS s,
                   CONCAT(CURDATE(), ' 00:00:00') AS e
        ")->fetch(PDO::FETCH_ASSOC);
        return [
            'start' => (string) ($row['s'] ?? ''),
            'end' => (string) ($row['e'] ?? ''),
            'label' => 'Custom · yesterday (set From and To)',
            'end_inclusive' => false,
        ];
    }
    $df = $dateFrom;
    $dt = $dateTo;
    if ($df > $dt) {
        $tmp = $df;
        $df = $dt;
        $dt = $tmp;
    }
    $stmt = $pdo->prepare('SELECT CONCAT(?, \' 00:00:00\') AS s, DATE_ADD(CONCAT(?, \' 00:00:00\'), INTERVAL 1 DAY) AS e');
    $stmt->execute([$df, $dt]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $label = 'Custom · ' . date('M j, Y', strtotime($df . ' 12:00:00')) . ' – ' . date('M j, Y', strtotime($dt . ' 12:00:00'));
    return [
        'start' => (string) ($row['s'] ?? ''),
        'end' => (string) ($row['e'] ?? ''),
        'label' => $label,
        'end_inclusive' => false,
    ];
}

function reports_export_datetime_predicate(string $column, bool $endInclusive): string
{
    if ($endInclusive) {
        return "{$column} >= ? AND {$column} <= ?";
    }
    return "{$column} >= ? AND {$column} < ?";
}

function reports_export_format_datetime($dateTime): string
{
    if (empty($dateTime)) {
        return '—';
    }
    $ts = strtotime((string) $dateTime);
    return $ts ? date('M j, Y g:i A', $ts) : (string) $dateTime;
}

function reports_export_format_date($date): string
{
    if (empty($date)) {
        return '—';
    }
    $ts = strtotime((string) $date);
    return $ts ? date('M j, Y', $ts) : (string) $date;
}

$optionKeys = ['include_visits_metric', 'include_registrations_metric', 'include_registration_table'];
$hasExplicitOptions = false;
foreach ($optionKeys as $k) {
    if (isset($_GET[$k])) {
        $hasExplicitOptions = true;
        break;
    }
}

$includeVisitsMetric = reports_export_pdf_bool('include_visits_metric', !$hasExplicitOptions);
$includeRegistrationsMetric = reports_export_pdf_bool('include_registrations_metric', !$hasExplicitOptions);
$includeRegistrationTable = reports_export_pdf_bool('include_registration_table', !$hasExplicitOptions);

$filterPeriod = isset($_GET['period']) ? (string) $_GET['period'] : 'yesterday';
$filterDateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$filterDateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$filterClinicId = isset($_GET['clinic']) ? trim((string) $_GET['clinic']) : '';
$filterRegSearch = isset($_GET['reg_q']) ? trim((string) $_GET['reg_q']) : '';

$totalMyDentalVisits = 0;
$userRegistrationsTotal = 0;
$registrationRows = [];
$periodLabel = '—';
$clinicLabel = 'All clinics';
$dbError = null;

try {
    try {
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        // ignore
    }

    $tenantsStmt = $pdo->query('SELECT tenant_id, clinic_name FROM tbl_tenants ORDER BY clinic_name ASC');
    $tenantsList = $tenantsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (strtolower($filterPeriod) === 'custom' && $filterDateFrom === '' && $filterDateTo === '') {
        $filterDateFrom = (string) $pdo->query("SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '%Y-%m-%d')")->fetchColumn();
        $filterDateTo = $filterDateFrom;
    }

    $range = reports_export_mysql_period_range(
        $pdo,
        $filterPeriod,
        $filterDateFrom !== '' ? $filterDateFrom : null,
        $filterDateTo !== '' ? $filterDateTo : null
    );
    $startStr = $range['start'];
    $endStr = $range['end'];
    $periodLabel = $range['label'];
    $endInclusive = !empty($range['end_inclusive']);

    if ($filterClinicId !== '') {
        $found = false;
        foreach ($tenantsList as $t) {
            if ((string) ($t['tenant_id'] ?? '') === $filterClinicId) {
                $clinicLabel = (string) $t['clinic_name'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $filterClinicId = '';
            $clinicLabel = 'All clinics';
        }
    }

    $visitPred = reports_export_datetime_predicate('created_at', $endInclusive);
    $visitParams = [$startStr, $endStr];
    $visitSql = "
        SELECT COUNT(DISTINCT ip_address) AS cnt
        FROM tbl_website_visits
        WHERE {$visitPred}
          AND ip_address IS NOT NULL
          AND TRIM(ip_address) <> ''
    ";
    if ($filterClinicId !== '') {
        $visitSql .= ' AND tenant_id = ?';
        $visitParams[] = $filterClinicId;
    }

    if ($includeVisitsMetric) {
        try {
            $stmt = $pdo->prepare($visitSql);
            $stmt->execute($visitParams);
            $totalMyDentalVisits = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
        } catch (Throwable $e) {
            error_log('reports_export_pdf tbl_website_visits: ' . $e->getMessage());
            $totalMyDentalVisits = 0;
        }
    }

    $regParams = [$startStr, $endStr];
    $regPred = reports_export_datetime_predicate('u.created_at', $endInclusive);
    $regWhere = "WHERE {$regPred}";
    if ($filterClinicId !== '') {
        $regWhere .= ' AND u.tenant_id = ?';
        $regParams[] = $filterClinicId;
    }
    if ($filterRegSearch !== '') {
        $likeBody = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filterRegSearch);
        $likeTerm = '%' . $likeBody . '%';
        $regWhere .= " AND (
            COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) LIKE ? ESCAPE '!'
            OR u.email LIKE ? ESCAPE '!'
            OR COALESCE(t.clinic_name, '') LIKE ? ESCAPE '!'
        )";
        $regParams[] = $likeTerm;
        $regParams[] = $likeTerm;
        $regParams[] = $likeTerm;
    }

    if ($includeRegistrationsMetric) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM tbl_users u LEFT JOIN tbl_tenants t ON t.tenant_id = u.tenant_id {$regWhere}");
        $stmt->execute($regParams);
        $userRegistrationsTotal = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
    }

    if ($includeRegistrationTable) {
        $sqlReg = "
            SELECT
                DATE(u.created_at) AS created_date,
                t.clinic_name AS tenant_name,
                COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS user_name,
                u.email AS user_email,
                COALESCE(u.last_active, u.last_login) AS last_active_at
            FROM tbl_users u
            LEFT JOIN tbl_tenants t ON t.tenant_id = u.tenant_id
            {$regWhere}
            ORDER BY u.created_at DESC
        ";
        try {
            $stmt = $pdo->prepare($sqlReg);
            $stmt->execute($regParams);
            $registrationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $sqlFallback = str_replace(
                'COALESCE(u.last_active, u.last_login)',
                'u.last_login',
                $sqlReg
            );
            $stmt = $pdo->prepare($sqlFallback);
            $stmt->execute($regParams);
            $registrationRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
    error_log('reports_export_pdf: ' . $dbError);
}

$filename = 'MyDental_Reports_' . date('Y-m-d') . '.pdf';
$tcpdfPath = reports_export_resolve_tcpdf_path();

if ($tcpdfPath !== null) {
    require_once $tcpdfPath;

    class ReportsExportPDF extends TCPDF
    {
        public function Footer()
        {
            $this->SetY(-14);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(
                0,
                10,
                'MyDental - Reports | Generated ' . date('M j, Y g:i A') . ' | Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(),
                0,
                0,
                'C'
            );
        }
    }

    try {
        $pdf = new ReportsExportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MyDental');
        $pdf->SetAuthor('MyDental Platform');
        $pdf->SetTitle('Platform Reports');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(12);
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 102, 255);
        $pdf->Cell(0, 8, 'MyDental Reports', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 90, 100);
        $pdf->Cell(0, 6, 'Website visits and user registrations', 0, 1, 'L');
        $pdf->Cell(0, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1, 'L');
        $pdf->Cell(0, 6, 'Scope: ' . $periodLabel . ' · ' . $clinicLabel, 0, 1, 'L');
        $pdf->Ln(2);
        $pdf->SetDrawColor(0, 102, 255);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        if ($dbError !== null) {
            $pdf->SetFillColor(255, 235, 235);
            $pdf->SetDrawColor(220, 180, 180);
            $pdf->MultiCell(0, 6, 'Data could not be loaded: ' . $dbError, 1, 'L', true);
        } else {
            if ($includeVisitsMetric || $includeRegistrationsMetric) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(25, 35, 50);
                $pdf->Cell(0, 6, 'Summary metrics', 0, 1, 'L');
                $lines = [];
                if ($includeVisitsMetric) {
                    $lines[] = 'Total mydental visits (distinct IPs): ' . number_format($totalMyDentalVisits);
                }
                if ($includeRegistrationsMetric) {
                    $lines[] = 'User registrations: ' . number_format($userRegistrationsTotal);
                }
                if (empty($lines)) {
                    $lines[] = 'No summary metrics selected.';
                }
                $pdf->SetFillColor(240, 245, 255);
                $pdf->SetDrawColor(190, 205, 235);
                $pdf->SetFont('helvetica', '', 9.5);
                $pdf->SetTextColor(45, 55, 65);
                $pdf->MultiCell(0, 6, implode('  |  ', $lines), 1, 'L', true);
                $pdf->Ln(4);
            }

            if ($includeRegistrationTable) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'User registrations (detail)', 0, 1, 'L');
                $html = '<table border="1" cellpadding="3" cellspacing="0" width="100%" style="font-size:7.8pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="14%">Date</th><th width="20%">Tenant</th><th width="18%">User</th><th width="24%">Email</th><th width="24%">Last active</th></tr>';
                if (empty($registrationRows)) {
                    $html .= '<tr><td colspan="5" align="center">No registrations for this period.</td></tr>';
                } else {
                    foreach ($registrationRows as $row) {
                        $html .= '<tr>';
                        $html .= '<td>' . htmlspecialchars(reports_export_format_date($row['created_date'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($row['tenant_name'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($row['user_name'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($row['user_email'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars(reports_export_format_datetime($row['last_active_at'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '</tr>';
                    }
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }
        }

        $pdfData = $pdf->Output($filename, 'S');
        reports_export_send_pdf_download($pdfData, $filename);
        exit;
    } catch (Throwable $e) {
        error_log('reports_export_pdf TCPDF: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/lib/fpdf.php';

class ReportsExportFPDF extends FPDF
{
    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 10, 'MyDental Reports | Generated ' . date('M j, Y g:i A') . ' | Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $pdf = new ReportsExportFPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetMargins(15, 18, 15);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    $pdf->SetFillColor(237, 244, 255);
    $pdf->SetDrawColor(190, 205, 235);
    $pdf->Rect(15, 14, 180, 30, 'DF');
    $pdf->SetY(19);
    $pdf->SetFont('Helvetica', '', 17);
    $pdf->SetTextColor(0, 102, 255);
    $pdf->Cell(0, 8, reports_export_pdf_latin1_safe('MyDental Reports'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(80, 90, 100);
    $pdf->Cell(0, 6, reports_export_pdf_latin1_safe('Website visits and user registrations'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, reports_export_pdf_latin1_safe('Generated: ' . date('M j, Y g:i A')), 0, 1, 'L');
    $pdf->Cell(0, 5, reports_export_pdf_latin1_safe('Scope: ' . $periodLabel . ' · ' . $clinicLabel), 0, 1, 'L');
    $pdf->Ln(6);

    if ($dbError !== null) {
        $pdf->SetFillColor(255, 235, 235);
        $pdf->SetDrawColor(220, 180, 180);
        $pdf->MultiCell(0, 6, reports_export_pdf_latin1_safe('Data could not be loaded: ' . $dbError), 1, 'L', true);
    } else {
        if ($includeVisitsMetric || $includeRegistrationsMetric) {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->SetTextColor(25, 35, 50);
            $pdf->Cell(0, 6, reports_export_pdf_latin1_safe('Summary metrics'), 0, 1, 'L');
            $pdf->SetFillColor(246, 249, 255);
            $pdf->SetDrawColor(205, 215, 235);
            $pdf->Rect(15, $pdf->GetY(), 180, 22, 'DF');
            $pdf->SetXY(19, $pdf->GetY() + 4);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(45, 55, 65);
            $txt = '';
            if ($includeVisitsMetric) {
                $txt .= '- Total mydental visits (distinct IPs): ' . number_format($totalMyDentalVisits) . "\n";
            }
            if ($includeRegistrationsMetric) {
                $txt .= '- User registrations: ' . number_format($userRegistrationsTotal) . "\n";
            }
            if ($txt === '') {
                $txt = "- No summary metrics selected.\n";
            }
            $pdf->MultiCell(172, 5, reports_export_pdf_latin1_safe(rtrim($txt)), 0, 'L', false);
            $pdf->Ln(8);
        }

        if ($includeRegistrationTable) {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->SetTextColor(25, 35, 50);
            $pdf->Cell(0, 6, reports_export_pdf_latin1_safe('User registrations (detail)'), 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 6.8);
            $pdf->SetFillColor(30, 41, 59);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(24, 6, reports_export_pdf_latin1_safe('Date'), 1, 0, 'L', true);
            $pdf->Cell(38, 6, reports_export_pdf_latin1_safe('Tenant'), 1, 0, 'L', true);
            $pdf->Cell(36, 6, reports_export_pdf_latin1_safe('User'), 1, 0, 'L', true);
            $pdf->Cell(44, 6, reports_export_pdf_latin1_safe('Email'), 1, 0, 'L', true);
            $pdf->Cell(38, 6, reports_export_pdf_latin1_safe('Last active'), 1, 1, 'L', true);
            $pdf->SetTextColor(35, 45, 55);
            if (empty($registrationRows)) {
                $pdf->Cell(180, 7, reports_export_pdf_latin1_safe('No registrations for this period.'), 1, 1, 'C');
            } else {
                $rowToggle = false;
                foreach ($registrationRows as $row) {
                    $pdf->SetFillColor($rowToggle ? 250 : 242, $rowToggle ? 252 : 247, 255);
                    $h = 6;
                    $pdf->Cell(24, $h, reports_export_pdf_latin1_safe(reports_export_format_date($row['created_date'] ?? null)), 1, 0, 'L', true);
                    $pdf->Cell(38, $h, reports_export_pdf_latin1_safe((string) ($row['tenant_name'] ?? '—')), 1, 0, 'L', true);
                    $pdf->Cell(36, $h, reports_export_pdf_latin1_safe((string) ($row['user_name'] ?? '—')), 1, 0, 'L', true);
                    $pdf->Cell(44, $h, reports_export_pdf_latin1_safe((string) ($row['user_email'] ?? '—')), 1, 0, 'L', true);
                    $pdf->Cell(38, $h, reports_export_pdf_latin1_safe(reports_export_format_datetime($row['last_active_at'] ?? null)), 1, 1, 'L', true);
                    $rowToggle = !$rowToggle;
                }
            }
        }
    }

    $pdfData = $pdf->Output('S', $filename);
    reports_export_send_pdf_download($pdfData, $filename);
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF generation failed. Please try again.';
}
