<?php
/**
 * PDF export for superadmin audit logs (login / logout events).
 * Uses TCPDF (bundled in vendor).
 */
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

ini_set('memory_limit', '256M');
@set_time_limit(120);

$totalLogs = 0;
$loginEvents = 0;
$logoutEvents = 0;
$eventRows = [];
$dbError = null;

try {
    $totalLogs = (int) $pdo->query('SELECT COUNT(*) FROM tbl_audit_logs')->fetchColumn();

    $loginStmt = $pdo->query("
        SELECT COUNT(*)
        FROM tbl_audit_logs
        WHERE LOWER(action) LIKE '%login%'
    ");
    $loginEvents = (int) $loginStmt->fetchColumn();

    $logoutStmt = $pdo->query("
        SELECT COUNT(*)
        FROM tbl_audit_logs
        WHERE LOWER(action) LIKE '%logout%'
    ");
    $logoutEvents = (int) $logoutStmt->fetchColumn();

    $eventsStmt = $pdo->query("
        SELECT
            l.log_id,
            l.user_id,
            l.action,
            l.ip_address,
            l.created_at,
            u.full_name
        FROM tbl_audit_logs l
        LEFT JOIN tbl_users u ON u.user_id = l.user_id
        WHERE LOWER(l.action) LIKE '%login%' OR LOWER(l.action) LIKE '%logout%'
        ORDER BY l.created_at DESC, l.log_id DESC
    ");
    $eventRows = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

/**
 * @extends TCPDF
 */
class AuditLogsExportPDF extends TCPDF
{
    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFont('helvetica', 'I', 8);
        $this->SetTextColor(90, 90, 90);
        $txt = 'MyDental — Confidential  |  Generated ' . date('M j, Y \a\t g:i A')
            . '  |  Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages();
        $this->Cell(0, 10, $txt, 0, 0, 'C');
    }
}

$pdf = new AuditLogsExportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('MyDental');
$pdf->SetAuthor('MyDental Platform');
$pdf->SetTitle('Audit Logs — Login & Logout');
$pdf->SetSubject('Login and logout activity report');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetFooterMargin(12);
$pdf->SetMargins(15, 18, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('helvetica', '', 10);
$pdf->AddPage();

$logoFile = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'MyDental Logo.svg');
$headerTop = 12;

if ($logoFile !== false && is_readable($logoFile)) {
    try {
        // Width ~52 mm keeps header balanced on A4
        $pdf->ImageSVG($logoFile, 15, $headerTop, 52);
    } catch (Throwable $e) {
        $logoFile = false;
    }
}

if ($logoFile === false) {
    $pdf->SetXY(15, $headerTop);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(0, 102, 255);
    $pdf->Cell(80, 10, 'MyDental', 0, 0, 'L');
    $pdf->SetTextColor(30, 30, 30);
}

$pdf->SetFont('helvetica', 'B', 13);
$pdf->SetTextColor(25, 35, 50);
$pdf->SetXY(100, $headerTop + 2);
$pdf->Cell(95, 7, 'Login & Logout Audit Report', 0, 1, 'R');
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(75, 85, 100);
$pdf->SetX(100);
$pdf->Cell(95, 5, 'Super Administrator Console', 0, 1, 'R');
$pdf->SetX(100);
$pdf->Cell(95, 5, 'Security & access activity', 0, 1, 'R');

$pdf->SetY(30);
$pdf->SetDrawColor(0, 102, 255);
$pdf->SetLineWidth(0.35);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColor(25, 35, 50);
$pdf->Cell(0, 6, 'Executive summary', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9.5);
$pdf->SetTextColor(45, 55, 65);

if ($dbError !== null) {
    $pdf->SetFillColor(255, 235, 235);
    $pdf->SetDrawColor(220, 180, 180);
    $pdf->MultiCell(
        0,
        6,
        'Data could not be loaded: ' . $dbError,
        1,
        'L',
        true,
        1,
        '',
        '',
        true,
        0,
        false,
        true,
        0,
        'T',
        false
    );
} else {
    $summary = sprintf(
        'Total audit records (all actions): %s  |  Login events: %s  |  Logout events: %s  |  Events listed below: %s',
        number_format($totalLogs),
        number_format($loginEvents),
        number_format($logoutEvents),
        number_format(count($eventRows))
    );
    $pdf->SetFillColor(240, 245, 255);
    $pdf->SetDrawColor(190, 205, 235);
    $pdf->MultiCell(0, 6, $summary, 1, 'L', true, 1, '', '', true, 0, false, true, 0, 'T', false);
}

$pdf->Ln(5);

if ($dbError === null) {
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(25, 35, 50);
    $pdf->Cell(0, 6, 'Login & logout events', 0, 1, 'L');
    $pdf->Ln(1);

    $html = '<style>
        table.audit { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
        table.audit th { background-color: #1e293b; color: #ffffff; font-weight: bold; padding: 6px 4px; text-align: left; }
        table.audit td { border: 1px solid #e2e8f0; padding: 5px 4px; vertical-align: top; }
        table.audit tr:nth-child(even) { background-color: #f8fafc; }
        .muted { color: #64748b; font-size: 8pt; }
        .tag-login { color: #15803d; font-weight: bold; }
        .tag-logout { color: #b45309; font-weight: bold; }
    </style>';
    $html .= '<table class="audit" cellpadding="0" cellspacing="0"><thead><tr>';
    $html .= '<th style="width:26%;">User</th>';
    $html .= '<th style="width:24%;">Action</th>';
    $html .= '<th style="width:28%;">Date &amp; time</th>';
    $html .= '<th style="width:12%;text-align:center;">Type</th>';
    $html .= '</tr></thead><tbody>';

    if (empty($eventRows)) {
        $html .= '<tr><td colspan="4" style="text-align:center;padding:12px;">No login or logout events recorded yet.</td></tr>';
    } else {
        foreach ($eventRows as $row) {
            $action = (string) ($row['action'] ?? '');
            $lowerAction = strtolower($action);
            $isLogout = strpos($lowerAction, 'logout') !== false;
            $typeLabel = $isLogout ? 'Logout' : 'Login';
            $typeClass = $isLogout ? 'tag-logout' : 'tag-login';

            $displayName = trim((string) ($row['full_name'] ?? ''));
            if ($displayName === '') {
                $displayName = trim((string) ($row['user_id'] ?? ''));
            }
            if ($displayName === '') {
                $displayName = 'System';
            }
            $ip = trim((string) ($row['ip_address'] ?? ''));
            $ipLabel = $ip !== '' ? $ip : '—';

            $ts = strtotime((string) ($row['created_at']));
            $dateStr = $ts ? date('M j, Y', $ts) : '—';
            $timeStr = $ts ? date('H:i:s', $ts) : '';

            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</strong><br/>';
            $html .= '<span class="muted">IP: ' . htmlspecialchars($ipLabel, ENT_QUOTES, 'UTF-8') . '</span></td>';
            $html .= '<td>' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') . '<br/><span class="muted">' . htmlspecialchars($timeStr, ENT_QUOTES, 'UTF-8') . '</span></td>';
            $html .= '<td style="text-align:center;"><span class="' . $typeClass . '">' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</span></td>';
            $html .= '</tr>';
        }
    }

    $html .= '</tbody></table>';
    $pdf->writeHTML($html, true, false, true, false, '');
}

$filename = 'MyDental_Audit_Logs_' . date('Y-m-d') . '.pdf';
$pdf->Output($filename, 'D');
exit;
