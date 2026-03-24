<?php
/**
 * PDF export for superadmin tenant management (summary, directory, workforce).
 * Uses TCPDF when available, with FPDF fallback.
 */
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

@ini_set('memory_limit', '128M');
@set_time_limit(120);

function tenantmgmt_pdf_bool(string $key, bool $default = false): bool
{
    if (!isset($_GET[$key])) {
        return $default;
    }
    $v = (string) $_GET[$key];
    return $v === '1' || strtolower($v) === 'true' || strtolower($v) === 'on';
}

function tenantmgmt_pdf_latin1_safe($text)
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

function tenantmgmt_send_pdf_download($pdfBinary, $downloadName)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $downloadName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename((string) $downloadName));
    if ($downloadName === '') {
        $downloadName = 'tenant_management.pdf';
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . strlen($pdfBinary));
    echo $pdfBinary;
}

function tenantmgmt_resolve_tcpdf_path()
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

function tenantmgmt_last_activity_label(?string $ts): string
{
    if ($ts === null || $ts === '') {
        return '—';
    }
    $t = strtotime($ts);
    if ($t === false) {
        return '—';
    }
    $diff = time() - $t;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ($m === 1 ? ' min ago' : ' mins ago');
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ($h === 1 ? ' hour ago' : ' hours ago');
    }
    if ($diff < 86400 * 60) {
        $d = (int) floor($diff / 86400);
        return $d . ($d === 1 ? ' day ago' : ' days ago');
    }
    return date('M j, Y', $t);
}

function tenantmgmt_status_label(string $st): string
{
    if ($st === 'active') {
        return 'Active';
    }
    if ($st === 'inactive') {
        return 'Inactive';
    }
    if ($st === 'suspended') {
        return 'Suspended';
    }
    return $st !== '' ? $st : '—';
}

$optionKeys = ['include_summary', 'include_tenant_directory', 'include_workforce'];
$hasExplicitOptions = false;
foreach ($optionKeys as $k) {
    if (isset($_GET[$k])) {
        $hasExplicitOptions = true;
        break;
    }
}

$includeSummary = tenantmgmt_pdf_bool('include_summary', !$hasExplicitOptions);
$includeTenantDirectory = tenantmgmt_pdf_bool('include_tenant_directory', !$hasExplicitOptions);
$includeWorkforce = tenantmgmt_pdf_bool('include_workforce', !$hasExplicitOptions);

$filterStatus = isset($_GET['status']) ? (string) $_GET['status'] : '';
$filterPlan = isset($_GET['plan']) ? (string) $_GET['plan'] : '';
$filterQ = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$totalTenants = 0;
$activeTenants = 0;
$inactiveTenants = 0;
$suspendedTenants = 0;
$tenantRows = [];
$workforceRows = [];
$dbError = null;

try {
    $totalTenants = (int) $pdo->query('SELECT COUNT(*) FROM tbl_tenants')->fetchColumn();
    $activeTenants = (int) $pdo->query("SELECT COUNT(*) FROM tbl_tenants WHERE subscription_status = 'active'")->fetchColumn();
    $inactiveTenants = (int) $pdo->query("SELECT COUNT(*) FROM tbl_tenants WHERE subscription_status = 'inactive'")->fetchColumn();
    $suspendedTenants = (int) $pdo->query("SELECT COUNT(*) FROM tbl_tenants WHERE subscription_status = 'suspended'")->fetchColumn();

    if ($includeTenantDirectory) {
        $where = ['1=1'];
        $params = [];
        $allowedStatus = ['active', 'inactive', 'suspended'];
        if ($filterStatus !== '' && in_array($filterStatus, $allowedStatus, true)) {
            $where[] = 't.subscription_status = ?';
            $params[] = $filterStatus;
        }
        if ($filterPlan !== '' && ctype_digit($filterPlan)) {
            $where[] = 'EXISTS (SELECT 1 FROM tbl_tenant_subscriptions tsf WHERE tsf.tenant_id = t.tenant_id AND tsf.plan_id = ?)';
            $params[] = (int) $filterPlan;
        }
        if ($filterQ !== '') {
            $like = '%' . $filterQ . '%';
            $where[] = '(t.clinic_name LIKE ? OR t.tenant_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);
        $listSql = "
            SELECT
                t.tenant_id,
                t.clinic_name,
                t.subscription_status,
                u.full_name AS owner_name,
                u.email AS owner_email,
                u.last_login AS owner_last_login,
                sp.plan_name
            FROM tbl_tenants t
            LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
            LEFT JOIN tbl_tenant_subscriptions ts ON ts.id = (
                SELECT MAX(ts2.id) FROM tbl_tenant_subscriptions ts2 WHERE ts2.tenant_id = t.tenant_id
            )
            LEFT JOIN tbl_subscription_plans sp ON sp.plan_id = ts.plan_id
            WHERE {$whereSql}
            ORDER BY t.created_at DESC
        ";
        $lstmt = $pdo->prepare($listSql);
        $lstmt->execute($params);
        $tenantRows = $lstmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($includeWorkforce) {
        $workforceSql = "
            SELECT
                t.tenant_id,
                t.clinic_name,
                COALESCE(SUM(CASE WHEN u.role = 'staff' THEN 1 ELSE 0 END), 0) AS staff_count,
                COALESCE(SUM(CASE WHEN u.role = 'dentist' THEN 1 ELSE 0 END), 0) AS doctor_count
            FROM tbl_tenants t
            LEFT JOIN tbl_users u ON u.tenant_id = t.tenant_id
            GROUP BY t.tenant_id, t.clinic_name
            ORDER BY t.clinic_name ASC
        ";
        $workforceRows = $pdo->query($workforceSql)->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$filterNote = 'All statuses · All plans · No search';
$parts = [];
if ($filterStatus !== '') {
    $parts[] = 'Status: ' . tenantmgmt_status_label($filterStatus);
}
if ($filterPlan !== '' && ctype_digit($filterPlan)) {
    $parts[] = 'Plan ID: ' . (int) $filterPlan;
}
if ($filterQ !== '') {
    $parts[] = 'Search: ' . $filterQ;
}
if (!empty($parts)) {
    $filterNote = implode(' · ', $parts);
}

$filename = 'MyDental_Tenant_Management_' . date('Y-m-d') . '.pdf';
$tcpdfPath = tenantmgmt_resolve_tcpdf_path();

if ($tcpdfPath !== null) {
    require_once $tcpdfPath;

    class TenantMgmtExportPDF extends TCPDF
    {
        public function Footer()
        {
            $this->SetY(-14);
            $this->SetFont('helvetica', 'I', 8);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(
                0,
                10,
                'MyDental - Tenant Management | Generated ' . date('M j, Y g:i A') . ' | Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(),
                0,
                0,
                'C'
            );
        }
    }

    try {
        $pdf = new TenantMgmtExportPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('MyDental');
        $pdf->SetAuthor('MyDental Platform');
        $pdf->SetTitle('Tenant Management');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(12);
        $pdf->SetMargins(15, 18, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();

        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->SetTextColor(0, 102, 255);
        $pdf->Cell(0, 8, 'MyDental Tenant Management', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(80, 90, 100);
        $pdf->Cell(0, 6, 'Clinic tenant accounts across the network', 0, 1, 'L');
        $pdf->Cell(0, 6, 'Generated: ' . date('M j, Y g:i A'), 0, 1, 'L');
        $pdf->Cell(0, 6, 'Filters (directory): ' . $filterNote, 0, 1, 'L');
        $pdf->Ln(2);
        $pdf->SetDrawColor(0, 102, 255);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(5);

        if ($dbError !== null) {
            $pdf->SetFillColor(255, 235, 235);
            $pdf->SetDrawColor(220, 180, 180);
            $pdf->MultiCell(0, 6, 'Data could not be loaded: ' . $dbError, 1, 'L', true);
        } else {
            if ($includeSummary) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->SetTextColor(25, 35, 50);
                $pdf->Cell(0, 6, 'Summary metrics', 0, 1, 'L');
                $pdf->SetFillColor(240, 245, 255);
                $pdf->SetDrawColor(190, 205, 235);
                $pdf->SetFont('helvetica', '', 9.5);
                $pdf->SetTextColor(45, 55, 65);
                $line = 'Total: ' . number_format($totalTenants)
                    . '  |  Active: ' . number_format($activeTenants)
                    . '  |  Inactive: ' . number_format($inactiveTenants)
                    . '  |  Suspended: ' . number_format($suspendedTenants);
                $pdf->MultiCell(0, 6, $line, 1, 'L', true);
                $pdf->Ln(4);
            }

            if ($includeTenantDirectory) {
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Tenant directory', 0, 1, 'L');
                $html = '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:8pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="22%">Owner</th><th width="20%">Clinic</th><th width="12%">Status</th><th width="18%">Plan</th><th width="28%">Last activity</th></tr>';
                if (empty($tenantRows)) {
                    $html .= '<tr><td colspan="5" align="center">No tenants match the current filters.</td></tr>';
                } else {
                    foreach ($tenantRows as $row) {
                        $owner = trim((string) ($row['owner_name'] ?? ''));
                        if ($owner === '') {
                            $owner = '—';
                        }
                        $email = trim((string) ($row['owner_email'] ?? ''));
                        $ownerCell = htmlspecialchars($owner, ENT_QUOTES, 'UTF-8');
                        if ($email !== '') {
                            $ownerCell .= '<br/><span style="font-size:7.5pt;color:#64748b;">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</span>';
                        }
                        $planName = trim((string) ($row['plan_name'] ?? ''));
                        $html .= '<tr>';
                        $html .= '<td>' . $ownerCell . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($row['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars(tenantmgmt_status_label((string) ($row['subscription_status'] ?? '')), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars($planName !== '' ? $planName : '—', ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars(tenantmgmt_last_activity_label($row['owner_last_login'] ?? null), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '</tr>';
                    }
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
                $pdf->Ln(3);
            }

            if ($includeWorkforce) {
                if ($pdf->GetY() > 240) {
                    $pdf->AddPage();
                }
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, 'Clinic workforce', 0, 1, 'L');
                $html = '<table border="1" cellpadding="4" cellspacing="0" width="100%" style="font-size:8.5pt;">';
                $html .= '<tr style="background-color:#1e293b;color:#ffffff;"><th width="18%">Tenant ID</th><th width="42%">Clinic</th><th width="20%" align="right">Staff</th><th width="20%" align="right">Doctors</th></tr>';
                if (empty($workforceRows)) {
                    $html .= '<tr><td colspan="4" align="center">No workforce records.</td></tr>';
                } else {
                    foreach ($workforceRows as $w) {
                        $html .= '<tr>';
                        $html .= '<td>' . htmlspecialchars((string) ($w['tenant_id'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td>' . htmlspecialchars((string) ($w['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                        $html .= '<td align="right">' . number_format((int) ($w['staff_count'] ?? 0)) . '</td>';
                        $html .= '<td align="right">' . number_format((int) ($w['doctor_count'] ?? 0)) . '</td>';
                        $html .= '</tr>';
                    }
                }
                $html .= '</table>';
                $pdf->writeHTML($html, true, false, true, false, '');
            }
        }

        $pdfData = $pdf->Output($filename, 'S');
        tenantmgmt_send_pdf_download($pdfData, $filename);
        exit;
    } catch (Throwable $e) {
        error_log('tenantmanagement_export_pdf TCPDF: ' . $e->getMessage());
    }
}

require_once __DIR__ . '/lib/fpdf.php';

class TenantMgmtExportFPDF extends FPDF
{
    public function Footer()
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(0, 10, 'MyDental Tenant Management | Generated ' . date('M j, Y g:i A') . ' | Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

try {
    $pdf = new TenantMgmtExportFPDF('P', 'mm', 'A4');
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
    $pdf->Cell(0, 8, tenantmgmt_pdf_latin1_safe('MyDental Tenant Management'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(80, 90, 100);
    $pdf->Cell(0, 6, tenantmgmt_pdf_latin1_safe('Clinic tenant accounts across the network'), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, tenantmgmt_pdf_latin1_safe('Generated: ' . date('M j, Y g:i A')), 0, 1, 'L');
    $pdf->Cell(0, 5, tenantmgmt_pdf_latin1_safe('Filters (directory): ' . $filterNote), 0, 1, 'L');
    $pdf->Ln(6);

    if ($dbError !== null) {
        $pdf->SetFillColor(255, 235, 235);
        $pdf->SetDrawColor(220, 180, 180);
        $pdf->MultiCell(0, 6, tenantmgmt_pdf_latin1_safe('Data could not be loaded: ' . $dbError), 1, 'L', true);
    } else {
        if ($includeSummary) {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->SetTextColor(25, 35, 50);
            $pdf->Cell(0, 6, tenantmgmt_pdf_latin1_safe('Summary metrics'), 0, 1, 'L');
            $pdf->SetFillColor(246, 249, 255);
            $pdf->SetDrawColor(205, 215, 235);
            $pdf->Rect(15, $pdf->GetY(), 180, 22, 'DF');
            $pdf->SetXY(19, $pdf->GetY() + 4);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(45, 55, 65);
            $txt = "- Total: " . number_format($totalTenants) . "\n"
                . "- Active: " . number_format($activeTenants) . "\n"
                . "- Inactive: " . number_format($inactiveTenants) . "\n"
                . "- Suspended: " . number_format($suspendedTenants);
            $pdf->MultiCell(172, 5, tenantmgmt_pdf_latin1_safe($txt), 0, 'L', false);
            $pdf->Ln(8);
        }

        if ($includeTenantDirectory) {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->SetTextColor(25, 35, 50);
            $pdf->Cell(0, 6, tenantmgmt_pdf_latin1_safe('Tenant directory'), 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 7.2);
            $pdf->SetFillColor(30, 41, 59);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(42, 6, tenantmgmt_pdf_latin1_safe('Owner'), 1, 0, 'L', true);
            $pdf->Cell(38, 6, tenantmgmt_pdf_latin1_safe('Clinic'), 1, 0, 'L', true);
            $pdf->Cell(22, 6, tenantmgmt_pdf_latin1_safe('Status'), 1, 0, 'L', true);
            $pdf->Cell(38, 6, tenantmgmt_pdf_latin1_safe('Plan'), 1, 0, 'L', true);
            $pdf->Cell(40, 6, tenantmgmt_pdf_latin1_safe('Last activity'), 1, 1, 'L', true);
            $pdf->SetTextColor(35, 45, 55);
            if (empty($tenantRows)) {
                $pdf->Cell(180, 7, tenantmgmt_pdf_latin1_safe('No tenants match the current filters.'), 1, 1, 'C');
            } else {
                $rowToggle = false;
                foreach ($tenantRows as $row) {
                    $owner = trim((string) ($row['owner_name'] ?? ''));
                    if ($owner === '') {
                        $owner = '—';
                    }
                    $email = trim((string) ($row['owner_email'] ?? ''));
                    $ownerLine = $owner . ($email !== '' ? ' / ' . $email : '');
                    $pdf->SetFillColor($rowToggle ? 250 : 242, $rowToggle ? 252 : 247, 255);
                    $pdf->Cell(42, 6, tenantmgmt_pdf_latin1_safe($ownerLine), 1, 0, 'L', true);
                    $pdf->Cell(38, 6, tenantmgmt_pdf_latin1_safe((string) ($row['clinic_name'] ?? '')), 1, 0, 'L', true);
                    $pdf->Cell(22, 6, tenantmgmt_pdf_latin1_safe(tenantmgmt_status_label((string) ($row['subscription_status'] ?? ''))), 1, 0, 'L', true);
                    $planName = trim((string) ($row['plan_name'] ?? ''));
                    $pdf->Cell(38, 6, tenantmgmt_pdf_latin1_safe($planName !== '' ? $planName : '—'), 1, 0, 'L', true);
                    $pdf->Cell(40, 6, tenantmgmt_pdf_latin1_safe(tenantmgmt_last_activity_label($row['owner_last_login'] ?? null)), 1, 1, 'L', true);
                    $rowToggle = !$rowToggle;
                }
            }
            $pdf->Ln(4);
        }

        if ($includeWorkforce) {
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->SetTextColor(25, 35, 50);
            $pdf->Cell(0, 6, tenantmgmt_pdf_latin1_safe('Clinic workforce'), 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetFillColor(30, 41, 59);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(28, 7, tenantmgmt_pdf_latin1_safe('Tenant ID'), 1, 0, 'L', true);
            $pdf->Cell(82, 7, tenantmgmt_pdf_latin1_safe('Clinic'), 1, 0, 'L', true);
            $pdf->Cell(35, 7, tenantmgmt_pdf_latin1_safe('Staff'), 1, 0, 'R', true);
            $pdf->Cell(35, 7, tenantmgmt_pdf_latin1_safe('Doctors'), 1, 1, 'R', true);
            $pdf->SetTextColor(35, 45, 55);
            if (empty($workforceRows)) {
                $pdf->Cell(180, 7, tenantmgmt_pdf_latin1_safe('No workforce records.'), 1, 1, 'C');
            } else {
                $rowToggle = false;
                foreach ($workforceRows as $w) {
                    $pdf->SetFillColor($rowToggle ? 250 : 242, $rowToggle ? 252 : 247, 255);
                    $pdf->Cell(28, 6, tenantmgmt_pdf_latin1_safe((string) ($w['tenant_id'] ?? '')), 1, 0, 'L', true);
                    $pdf->Cell(82, 6, tenantmgmt_pdf_latin1_safe((string) ($w['clinic_name'] ?? '')), 1, 0, 'L', true);
                    $pdf->Cell(35, 6, number_format((int) ($w['staff_count'] ?? 0)), 1, 0, 'R', true);
                    $pdf->Cell(35, 6, number_format((int) ($w['doctor_count'] ?? 0)), 1, 1, 'R', true);
                    $rowToggle = !$rowToggle;
                }
            }
        }
    }

    $pdfData = $pdf->Output('S', $filename);
    tenantmgmt_send_pdf_download($pdfData, $filename);
    exit;
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF generation failed. Please try again.';
}
