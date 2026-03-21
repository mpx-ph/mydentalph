<?php
/**
 * PDF export for superadmin audit logs (login / logout events).
 * Prefers TCPDF from project vendor/; falls back to FPDF in superadmin/lib/fpdf.php.
 */
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

@ini_set('memory_limit', '128M');
@set_time_limit(60);

/**
 * FPDF core fonts expect single-byte encoding; strip/convert UTF-8 for stable output.
 */
function auditlogs_pdf_latin1_safe($text)
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

/**
 * Send PDF bytes as download (avoids FPDF/TCPDF Output(D) which fails if ob contains any junk).
 */
function auditlogs_send_pdf_download($pdfBinary, $downloadName)
{
    if (headers_sent($hsFile, $hsLine)) {
        error_log('auditlogs_send_pdf_download: headers already sent at ' . $hsFile . ':' . $hsLine);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'PDF was built but could not be sent (output already started). Check for spaces/BOM before <?php in PHP files.';
        exit;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($downloadName));
    if ($downloadName === '') {
        $downloadName = 'export.pdf';
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($pdfBinary));
    echo $pdfBinary;
}

/**
 * TCPDF lives at project root: .../vendor/tecnickcom/tcpdf/tcpdf.php
 * (same level as superadmin/, not inside superadmin/). Tries a few layouts.
 *
 * @return string|null Absolute path to tcpdf.php if readable
 */
function auditlogs_resolve_tcpdf_path()
{
    $candidates = [
        dirname(__DIR__) . '/vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        // If app is nested one level deeper on the server (PHP 5.x-safe)
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

$tcpdfPath = auditlogs_resolve_tcpdf_path();

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

$filename = 'MyDental_Audit_Logs_' . date('Y-m-d') . '.pdf';

if ($tcpdfPath !== null) {
    require_once $tcpdfPath;

    /**
     * Footer with page numbers (ASCII-safe for core fonts).
     */
    class AuditLogsExportPDF extends TCPDF
    {
        public function Footer()
        {
            $this->SetY(-14);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(90, 90, 90);
            $txt = 'MyDental - Confidential  |  Generated ' . date('M j, Y g:i A')
                . '  |  Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages();
            $this->Cell(0, 10, $txt, 0, 0, 'C');
        }
    }

    try {
        $pdf = new AuditLogsExportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MyDental');
        $pdf->SetAuthor('MyDental Platform');
        $pdf->SetTitle('Audit Logs - Login and Logout');
        $pdf->SetSubject('Login and logout activity report');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(12);
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();

        $headerTop = 12;
        $pngLogo = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'MyDental_logo_pdf.png');
        if ($pngLogo !== false && is_readable($pngLogo) && (int) filesize($pngLogo) < 2000000) {
            $pdf->Image($pngLogo, 15, $headerTop, 48, 0, '', '', '', false, 300, '', false, false, 0);
        } else {
            $pdf->SetFillColor(237, 244, 255);
            $pdf->SetDrawColor(200, 215, 240);
            $pdf->RoundedRect(15, $headerTop - 1, 78, 16, 2, '1111', 'DF');
            $pdf->SetXY(19, $headerTop + 1);
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(0, 102, 255);
            $pdf->Cell(70, 10, 'MyDental', 0, 0, 'L');
        }
        $pdf->SetTextColor(30, 30, 30);

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
            $pdf->MultiCell(0, 6, 'Data could not be loaded: ' . $dbError, 1, 'L', true);
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
            $pdf->MultiCell(0, 6, $summary, 1, 'L', true);
        }

        $pdf->Ln(5);

        if ($dbError === null) {
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->SetTextColor(25, 35, 50);
            $pdf->Cell(0, 6, 'Login & logout events', 0, 1, 'L');
            $pdf->Ln(1);

            $html = '<style type="text/css">
            table.audit { border-collapse: collapse; width: 100%; font-size: 8.5pt; }
            table.audit th { background-color: #1e293b; color: #ffffff; font-weight: bold; padding: 5px 3px; border: 1px solid #1e293b; }
            table.audit td { border: 1px solid #e2e8f0; padding: 4px 3px; vertical-align: top; }
            .muted { color: #64748b; font-size: 8pt; }
            .tag-login { color: #15803d; font-weight: bold; }
            .tag-logout { color: #b45309; font-weight: bold; }
        </style>';
            $html .= '<table class="audit" cellspacing="0" cellpadding="2"><thead><tr>';
            $html .= '<th width="26%">User</th>';
            $html .= '<th width="24%">Action</th>';
            $html .= '<th width="28%">Date &amp; time</th>';
            $html .= '<th width="12%" align="center">Type</th>';
            $html .= '</tr></thead><tbody>';

            if (empty($eventRows)) {
                $html .= '<tr><td colspan="4" align="center" style="padding:10px;">No login or logout events recorded yet.</td></tr>';
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
                    $ipLabel = $ip !== '' ? $ip : '-';

                    $ts = strtotime((string) ($row['created_at']));
                    $dateStr = $ts ? date('M j, Y', $ts) : '-';
                    $timeStr = $ts ? date('H:i:s', $ts) : '';

                    $html .= '<tr>';
                    $html .= '<td><strong>' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</strong><br/>';
                    $html .= '<span class="muted">IP: ' . htmlspecialchars($ipLabel, ENT_QUOTES, 'UTF-8') . '</span></td>';
                    $html .= '<td>' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</td>';
                    $html .= '<td>' . htmlspecialchars($dateStr, ENT_QUOTES, 'UTF-8') . '<br/><span class="muted">' . htmlspecialchars($timeStr, ENT_QUOTES, 'UTF-8') . '</span></td>';
                    $html .= '<td align="center"><span class="' . $typeClass . '">' . htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8') . '</span></td>';
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody></table>';
            $pdf->writeHTML($html, true, false, true, false, '');
        }

        $pdfData = $pdf->Output($filename, 'S');
        auditlogs_send_pdf_download($pdfData, $filename);
        exit;
    } catch (Throwable $e) {
        error_log('auditlogs_export_pdf TCPDF: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        // Fall through to FPDF fallback below (do not exit here)
    }
}

// ---- FPDF fallback (bundled under superadmin/lib; no vendor/ required) ----
require_once __DIR__ . '/lib/fpdf.php';

class AuditLogsExportFPDF extends FPDF
{
    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 10, 'MyDental - Confidential  |  Generated ' . date('M j, Y g:i A')
            . '  |  Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $pdf = new AuditLogsExportFPDF('P', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->SetTitle('Audit Logs - Login and Logout');
    $pdf->SetAuthor('MyDental Platform');
    $pdf->SetMargins(15, 18, 15);
    $pdf->SetAutoPageBreak(true, 22);
    $pdf->AddPage();

    $headerTop = 12;
    $pngLogo = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'MyDental_logo_pdf.png');
    if ($pngLogo !== false && is_readable($pngLogo) && (int) filesize($pngLogo) < 2000000) {
        $pdf->Image($pngLogo, 15, $headerTop, 48);
    } else {
        $pdf->SetFillColor(237, 244, 255);
        $pdf->SetDrawColor(200, 215, 240);
        $pdf->Rect(15, $headerTop - 1, 78, 16, 'DF');
        $pdf->SetXY(19, $headerTop + 1);
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->SetTextColor(0, 102, 255);
        $pdf->Cell(70, 10, 'MyDental', 0, 0, 'L');
    }
    $pdf->SetTextColor(30, 30, 30);

    $pdf->SetFont('Helvetica', 'B', 13);
    $pdf->SetXY(100, $headerTop + 2);
    $pdf->Cell(95, 7, 'Login & Logout Audit Report', 0, 1, 'R');
    $pdf->SetFont('Helvetica', '', 9);
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

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(25, 35, 50);
    $pdf->Cell(0, 6, 'Executive summary', 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(45, 55, 65);

    if ($dbError !== null) {
        $pdf->SetFillColor(255, 235, 235);
        $pdf->SetDrawColor(220, 180, 180);
        $pdf->MultiCell(0, 5, auditlogs_pdf_latin1_safe('Data could not be loaded: ' . $dbError), 1, 'L', true);
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
        $pdf->MultiCell(0, 5, auditlogs_pdf_latin1_safe($summary), 1, 'L', true);
    }

    $pdf->Ln(4);

    if ($dbError === null) {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(25, 35, 50);
        $pdf->Cell(0, 6, 'Login & logout events', 0, 1, 'L');
        $pdf->Ln(1);

        $wUser = 48;
        $wAction = 48;
        $wDate = 48;
        $wType = 36;

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($wUser, 7, 'User', 1, 0, 'L', true);
        $pdf->Cell($wAction, 7, 'Action', 1, 0, 'L', true);
        $pdf->Cell($wDate, 7, 'Date & time', 1, 0, 'L', true);
        $pdf->Cell($wType, 7, 'Type', 1, 1, 'C', true);

        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(30, 30, 30);

        if (empty($eventRows)) {
            $pdf->SetFillColor(250, 250, 250);
            $pdf->Cell($wUser + $wAction + $wDate + $wType, 8, 'No login or logout events recorded yet.', 1, 1, 'C', true);
        } else {
            foreach ($eventRows as $row) {
                $action = (string) ($row['action'] ?? '');
                $lowerAction = strtolower($action);
                $isLogout = strpos($lowerAction, 'logout') !== false;
                $typeLabel = $isLogout ? 'Logout' : 'Login';

                $displayName = trim((string) ($row['full_name'] ?? ''));
                if ($displayName === '') {
                    $displayName = trim((string) ($row['user_id'] ?? ''));
                }
                if ($displayName === '') {
                    $displayName = 'System';
                }
                $ip = trim((string) ($row['ip_address'] ?? ''));
                $ipLabel = $ip !== '' ? $ip : '-';

                $ts = strtotime((string) ($row['created_at']));
                $dateStr = $ts ? date('M j, Y', $ts) : '-';
                $timeStr = $ts ? date('H:i:s', $ts) : '';

                $userCol = $displayName . ' | IP: ' . $ipLabel;
                $userCol = auditlogs_pdf_latin1_safe($userCol);
                if (strlen($userCol) > 52) {
                    $userCol = substr($userCol, 0, 49) . '...';
                }
                $actCol = auditlogs_pdf_latin1_safe($action);
                $actCol = strlen($actCol) > 48 ? substr($actCol, 0, 45) . '...' : $actCol;
                $dtCol = auditlogs_pdf_latin1_safe($dateStr . ' ' . $timeStr);

                $pdf->SetFont('Helvetica', '', 7.5);
                $pdf->SetTextColor(30, 30, 30);
                $pdf->Cell($wUser, 6, $userCol, 1, 0, 'L');
                $pdf->Cell($wAction, 6, $actCol, 1, 0, 'L');
                $pdf->Cell($wDate, 6, $dtCol, 1, 0, 'L');
                $pdf->SetFont('Helvetica', 'B', 7.5);
                $pdf->SetTextColor($isLogout ? 180 : 21, $isLogout ? 83 : 128, $isLogout ? 9 : 61);
                $pdf->Cell($wType, 6, $typeLabel, 1, 1, 'C');
                $pdf->SetTextColor(30, 30, 30);
            }
        }
    }

    $pdfData = $pdf->Output('S', $filename);
    auditlogs_send_pdf_download($pdfData, $filename);
    exit;
} catch (Throwable $e) {
    error_log('auditlogs_export_pdf FPDF: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF generation failed. If this continues, contact support.';
    if (!empty($_GET['pdf_debug']) && $_GET['pdf_debug'] === '1') {
        echo "\n\n" . $e->getMessage();
    }
}

exit;
