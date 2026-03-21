<?php
/**
 * Audit logs PDF export — requires superadmin session.
 */
declare(strict_types=1);

require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_readable($autoload)) {
    header('Content-Type: text/plain; charset=UTF-8', true, 500);
    echo 'PDF library not installed. Run: composer install (from project root).';
    exit;
}
require_once $autoload;

if (!class_exists('TCPDF')) {
    header('Content-Type: text/plain; charset=UTF-8', true, 500);
    echo 'TCPDF is not available.';
    exit;
}

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

class AuditLogsPDF extends TCPDF
{
    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(90, 100, 115);
        $this->Cell(95, 8, 'MyDental — Management Console', 0, 0, 'L');
        $this->Cell(95, 8, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

$pdf = new AuditLogsPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('MyDental');
$pdf->SetAuthor('MyDental');
$pdf->SetTitle('Audit Logs Report');
$pdf->SetSubject('Login and logout events');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(14, 14, 14);
$pdf->SetAutoPageBreak(true, 18);
$pdf->SetFooterMargin(12);

$pdf->AddPage();

$logoPath = __DIR__ . DIRECTORY_SEPARATOR . 'MyDental Logo.svg';
$logoOk = false;
if (is_readable($logoPath)) {
    try {
        // Constrain size so the header stays a single professional band
        $pdf->ImageSVG($logoPath, 14, 11, 38, 16, '', '', '', 0, false);
        $logoOk = true;
    } catch (Throwable $e) {
        $logoOk = false;
    }
}

$headerTop = 12;
if ($logoOk) {
    $pdf->SetXY(60, 14);
} else {
    $pdf->SetXY(14, $headerTop);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(19, 28, 37);
    $pdf->Cell(0, 10, 'MyDental', 0, 1, 'L');
    $pdf->SetXY(60, 14);
}

$pdf->SetFont('helvetica', '', 7);
$pdf->SetTextColor(100, 110, 125);
$pdf->SetX(60);
$pdf->Cell(0, 4, 'MANAGEMENT CONSOLE', 0, 1, 'L');
$pdf->SetX(60);
$pdf->SetFont('helvetica', 'B', 15);
$pdf->SetTextColor(19, 28, 37);
$pdf->Cell(0, 7, 'Audit Logs Report', 0, 1, 'L');
$pdf->SetX(60);
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(64, 71, 82);
$pdf->Cell(0, 5, 'Login & logout activity across all tenants', 0, 1, 'L');

$tz = new DateTimeZone('Asia/Manila');
$generated = (new DateTimeImmutable('now', $tz))->format('F j, Y \a\t g:i A T');
$pdf->SetX(60);
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(120, 128, 140);
$pdf->Cell(0, 6, 'Generated: ' . $generated, 0, 1, 'L');

$pdf->Ln(2);
$pdf->SetDrawColor(0, 102, 255);
$pdf->SetLineWidth(0.35);
$pdf->Line(14, $pdf->GetY(), 196, $pdf->GetY());
$pdf->Ln(6);

if ($dbError !== null) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetTextColor(186, 26, 26);
    $pdf->MultiCell(0, 6, 'Unable to load audit data. Please try again later.', 0, 'L');
    $pdf->Output('Audit_Logs_Report.pdf', 'I');
    exit;
}

// Summary strip
$boxY = $pdf->GetY();
$pdf->SetFillColor(237, 244, 255);
$pdf->SetDrawColor(220, 230, 245);
$pdf->RoundedRect(14, $boxY, 182, 22, 2, '1111', 'DF');
$pdf->SetXY(18, $boxY + 4);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor(90, 100, 115);
$wCol = 58;
$pdf->Cell($wCol, 4, 'TOTAL LOGS (ALL)', 0, 0, 'L');
$pdf->Cell($wCol, 4, 'LOGIN EVENTS', 0, 0, 'L');
$pdf->Cell($wCol, 4, 'LOGOUT EVENTS', 0, 1, 'L');
$pdf->SetX(18);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColor(19, 28, 37);
$pdf->Cell($wCol, 8, number_format($totalLogs), 0, 0, 'L');
$pdf->Cell($wCol, 8, number_format($loginEvents), 0, 0, 'L');
$pdf->Cell($wCol, 8, number_format($logoutEvents), 0, 1, 'L');
$pdf->SetY($boxY + 22 + 6);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetTextColor(19, 28, 37);
$pdf->Cell(0, 6, 'Event detail', 0, 1, 'L');
$pdf->Ln(1);

// HTML table
$html = '<style>
    table { border-collapse: collapse; width: 100%; font-size: 8pt; }
    th { background-color: #f0f5ff; color: #404752; font-weight: bold; border-bottom: 1px solid #c0c7d4; padding: 5px 4px; text-align: left; }
    td { border-bottom: 1px solid #e8edf5; padding: 4px; vertical-align: top; }
    .muted { color: #6b7280; font-size: 7pt; }
    .badge-in { color: #047857; font-weight: bold; }
    .badge-out { color: #b45309; font-weight: bold; }
</style>';
$html .= '<table cellspacing="0" cellpadding="2"><thead><tr>';
$html .= '<th width="26%">User</th><th width="22%">Action</th><th width="22%">Date &amp; time</th><th width="14%">IP</th><th width="16%">Status</th>';
$html .= '</tr></thead><tbody>';

if (empty($eventRows)) {
    $html .= '<tr><td colspan="5" style="text-align:center;padding:12px;color:#6b7280;">No login or logout events recorded.</td></tr>';
} else {
    foreach ($eventRows as $row) {
        $action = (string) ($row['action'] ?? '');
        $lowerAction = strtolower($action);
        $isLogout = strpos($lowerAction, 'logout') !== false;
        $statusLabel = $isLogout ? 'Logout' : 'Login';
        $badgeClass = $isLogout ? 'badge-out' : 'badge-in';

        $displayName = trim((string) ($row['full_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) ($row['user_id'] ?? ''));
        }
        if ($displayName === '') {
            $displayName = 'System';
        }

        $ip = trim((string) ($row['ip_address'] ?? ''));
        if ($ip === '') {
            $ip = '—';
        }

        $ts = strtotime((string) ($row['created_at']));
        $dateStr = $ts ? date('M j, Y', $ts) : '—';
        $timeStr = $ts ? date('H:i:s', $ts) : '';

        $html .= '<tr>';
        $html .= '<td><strong>' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</strong></td>';
        $html .= '<td>' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') . '<br/><span class="muted">' . htmlspecialchars($timeStr, ENT_QUOTES, 'UTF-8') . '</span></td>';
        $html .= '<td class="muted">' . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td><span class="' . $badgeClass . '">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</span></td>';
        $html .= '</tr>';
    }
}

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Ln(4);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->SetTextColor(140, 145, 155);
$pdf->MultiCell(0, 4, 'This document contains security-sensitive information. Distribution is restricted to authorized personnel only.', 0, 'L');

$filename = 'MyDental_Audit_Logs_' . date('Y-m-d_His') . '.pdf';
$pdf->Output($filename, 'I');
