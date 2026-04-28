<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/appointment_reminder_service.php';

header('Content-Type: text/html; charset=UTF-8');

$reminderType = isset($_GET['reminder']) ? trim((string) $_GET['reminder']) : '1day';
if (!in_array($reminderType, ['3day', '1day', 'final'], true)) {
    $reminderType = '1day';
}

$pdo = getDBConnection();
$tables = clinic_resolve_appointment_db_tables($pdo);
$appointmentsTable = $tables['appointments'];
$patientsTable = $tables['patients'];
$usersTable = $tables['users'];
$dentistsTable = $tables['dentists'];

$tenantId = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : '';
$bookingId = isset($_GET['booking_id']) ? trim((string) $_GET['booking_id']) : '';

$appointment = null;

if ($appointmentsTable !== null && $patientsTable !== null && $usersTable !== null) {
    $qAppointments = appointment_reminder_quote_ident($appointmentsTable);
    $qPatients = appointment_reminder_quote_ident($patientsTable);
    $qUsers = appointment_reminder_quote_ident($usersTable);
    $appointmentsCols = clinic_table_columns($pdo, $appointmentsTable);

    $dentistJoinSql = '';
    $dentistNameExpr = "'Assigned Dentist'";
    if ($dentistsTable !== null && in_array('dentist_id', $appointmentsCols, true)) {
        $qDentists = appointment_reminder_quote_ident($dentistsTable);
        $dentistJoinSql = " LEFT JOIN {$qDentists} d ON d.tenant_id = a.tenant_id AND d.dentist_id = a.dentist_id ";
        $dentistNameExpr = "TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')))";
    }

    $sql = "
        SELECT
            a.tenant_id,
            a.booking_id,
            a.appointment_date,
            a.appointment_time,
            a.service_type AS service_name,
            COALESCE(NULLIF(TRIM(CONCAT(p.first_name, ' ', p.last_name)), ''), 'Patient') AS patient_name,
            {$dentistNameExpr} AS dentist_name
        FROM {$qAppointments} a
        INNER JOIN {$qPatients} p
            ON p.tenant_id = a.tenant_id
           AND p.patient_id = a.patient_id
        LEFT JOIN {$qUsers} u
            ON u.tenant_id = p.tenant_id
           AND p.linked_user_id = u.user_id
        {$dentistJoinSql}
        WHERE a.appointment_date >= CURDATE()
          AND COALESCE(a.status, 'pending') NOT IN ('cancelled', 'completed', 'no_show')
    ";

    $params = [];
    if ($tenantId !== '') {
        $sql .= " AND a.tenant_id = ? ";
        $params[] = $tenantId;
    }
    if ($bookingId !== '') {
        $sql .= " AND a.booking_id = ? ";
        $params[] = $bookingId;
    }

    $sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!is_array($appointment)) {
    $fallbackTenant = $tenantId;
    if ($fallbackTenant === '') {
        $tenantTable = clinic_get_physical_table_name($pdo, 'tbl_tenants') ?? clinic_get_physical_table_name($pdo, 'tenants');
        if ($tenantTable !== null) {
            $qTenantTable = appointment_reminder_quote_ident($tenantTable);
            $tenantStmt = $pdo->query("SELECT tenant_id FROM {$qTenantTable} ORDER BY tenant_id ASC LIMIT 1");
            $fallbackTenant = (string) ($tenantStmt->fetchColumn() ?: '');
        }
    }
    if ($fallbackTenant === '') {
        $fallbackTenant = 'TNT_DEMO';
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $sampleAt = $now->modify('+1 day')->setTime(11, 30);
    $appointment = [
        'tenant_id' => $fallbackTenant,
        'booking_id' => 'BK-DEMO-000001',
        'appointment_date' => $sampleAt->format('Y-m-d'),
        'appointment_time' => $sampleAt->format('H:i:s'),
        'service_name' => 'General Cleaning',
        'patient_name' => 'Sample Patient',
        'dentist_name' => 'Assigned Dentist',
    ];
}

$tenantId = trim((string) ($appointment['tenant_id'] ?? ''));
$clinicInfo = appointment_reminder_load_tenant_clinic_info($pdo, $tenantId);

$timeRaw = trim((string) ($appointment['appointment_time'] ?? ''));
if (strlen($timeRaw) === 5) {
    $timeRaw .= ':00';
}
$dateRaw = trim((string) ($appointment['appointment_date'] ?? ''));
$appointmentAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateRaw . ' ' . $timeRaw, new DateTimeZone('Asia/Manila'));
if (!$appointmentAt) {
    $appointmentAt = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
}

$appointment['appointment_date_label'] = $appointmentAt->format('F j, Y (l)');
$appointment['appointment_time_label'] = $appointmentAt->format('g:i A');
$payload = appointment_reminder_build_email($appointment, $clinicInfo, $reminderType);

$subject = (string) ($payload['subject'] ?? '');
$htmlBody = (string) ($payload['html'] ?? '');
$textBody = (string) ($payload['text'] ?? '');

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Reminder Email Preview</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 24px; color: #0f172a; }
        .wrap { max-width: 1000px; margin: 0 auto; }
        .meta { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin-bottom: 16px; }
        .subject { font-weight: 700; margin-bottom: 6px; }
        .hint { color: #475569; font-size: 13px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        iframe { width: 100%; min-height: 760px; border: 0; background: #fff; }
        details { margin-top: 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; }
        pre { white-space: pre-wrap; font-size: 13px; color: #334155; margin: 10px 0 0 0; }
        code { background: #e2e8f0; border-radius: 6px; padding: 2px 6px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="meta">
        <div class="subject">Subject: <?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="hint">
            Preview source: tenant <code><?php echo htmlspecialchars($tenantId, ENT_QUOTES, 'UTF-8'); ?></code>
            <?php if (!empty($appointment['booking_id'])): ?>
                , booking <code><?php echo htmlspecialchars((string) $appointment['booking_id'], ENT_QUOTES, 'UTF-8'); ?></code>
            <?php endif; ?>
            , reminder type <code><?php echo htmlspecialchars($reminderType, ENT_QUOTES, 'UTF-8'); ?></code>.
        </div>
    </div>

    <div class="card">
        <iframe srcdoc="<?php echo htmlspecialchars($htmlBody, ENT_QUOTES, 'UTF-8'); ?>"></iframe>
    </div>

    <details>
        <summary>Plain text email body</summary>
        <pre><?php echo htmlspecialchars($textBody, ENT_QUOTES, 'UTF-8'); ?></pre>
    </details>
</div>
</body>
</html>
