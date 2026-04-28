<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/appointment_db_tables.php';
require_once __DIR__ . '/includes/appointment_reminder_service.php';

date_default_timezone_set('Asia/Manila');

/**
 * Resolve one upcoming appointment for preview purposes.
 */
function preview_fetch_upcoming_appointment(PDO $pdo): ?array
{
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $appointmentsTable = $tables['appointments'] ?? null;
    $patientsTable = $tables['patients'] ?? null;
    $usersTable = $tables['users'] ?? null;
    $dentistsTable = $tables['dentists'] ?? null;

    if ($appointmentsTable === null || $patientsTable === null || $usersTable === null) {
        return null;
    }

    $qAppointments = appointment_reminder_quote_ident($appointmentsTable);
    $qPatients = appointment_reminder_quote_ident($patientsTable);
    $qUsers = appointment_reminder_quote_ident($usersTable);

    $appointmentsCols = clinic_table_columns($pdo, $appointmentsTable);
    $patientCols = clinic_table_columns($pdo, $patientsTable);

    $dentistJoinSql = '';
    $dentistNameExpr = "'Assigned Dentist'";
    if ($dentistsTable !== null && in_array('dentist_id', $appointmentsCols, true)) {
        $qDentists = appointment_reminder_quote_ident($dentistsTable);
        $dentistJoinSql = " LEFT JOIN {$qDentists} d ON d.tenant_id = a.tenant_id AND d.dentist_id = a.dentist_id ";
        $dentistNameExpr = "TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')))";
    }

    $patientEmailExpr = "COALESCE(NULLIF(TRIM(u.email), ''), '')";
    if (in_array('email', $patientCols, true)) {
        $patientEmailExpr = "COALESCE(NULLIF(TRIM(u.email), ''), NULLIF(TRIM(p.email), ''), '')";
    }

    $sql = "
        SELECT
            a.tenant_id,
            a.booking_id,
            a.appointment_date,
            a.appointment_time,
            a.service_type,
            COALESCE(NULLIF(TRIM(CONCAT(p.first_name, ' ', p.last_name)), ''), 'Patient') AS patient_name,
            {$patientEmailExpr} AS patient_email,
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
          AND COALESCE(a.visit_type, 'pre_book') <> 'walk_in'
          AND COALESCE(a.status, 'pending') NOT IN ('cancelled', 'completed', 'no_show')
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 1
    ";

    $stmt = $pdo->query($sql);
    $row = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: null) : null;
    if (!is_array($row)) {
        return null;
    }

    $date = trim((string) ($row['appointment_date'] ?? ''));
    $timeRaw = trim((string) ($row['appointment_time'] ?? ''));
    if ($date !== '' && $timeRaw !== '') {
        $timeValue = strlen($timeRaw) === 5 ? ($timeRaw . ':00') : $timeRaw;
        $appointmentAt = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date . ' ' . $timeValue,
            new DateTimeZone('Asia/Manila')
        );
        if ($appointmentAt) {
            $row['appointment_date_label'] = $appointmentAt->format('F j, Y (l)');
            $row['appointment_time_label'] = $appointmentAt->format('g:i A');
        }
    }

    return $row;
}

function preview_fallback_appointment(): array
{
    return [
        'tenant_id' => '',
        'booking_id' => 'BK-PREVIEW-0001',
        'appointment_date' => date('Y-m-d', strtotime('+2 days')),
        'appointment_time' => '10:30:00',
        'service_type' => 'Dental Cleaning',
        'patient_name' => 'Sample Patient',
        'patient_email' => 'sample@example.com',
        'dentist_name' => 'Assigned Dentist',
        'appointment_date_label' => date('F j, Y (l)', strtotime('+2 days')),
        'appointment_time_label' => '10:30 AM',
    ];
}

$reminderType = isset($_GET['type']) ? trim((string) $_GET['type']) : '1day';
if (!in_array($reminderType, ['3day', '1day', 'final'], true)) {
    $reminderType = '1day';
}

$appointment = preview_fetch_upcoming_appointment($pdo);
$usingFallback = false;
if ($appointment === null) {
    $appointment = preview_fallback_appointment();
    $usingFallback = true;
}

$tenantId = trim((string) ($appointment['tenant_id'] ?? ''));
$clinicInfo = $tenantId !== ''
    ? appointment_reminder_load_tenant_clinic_info($pdo, $tenantId)
    : [
        'clinic_name' => 'Clinic Preview',
        'logo' => '',
        'contact_address' => '',
        'contact_phone' => '',
        'contact_email' => '',
        'footer_social_url' => '',
    ];

$payload = appointment_reminder_build_email($appointment, $clinicInfo, $reminderType);
$emailHtml = (string) ($payload['html'] ?? '');
$emailSubject = (string) ($payload['subject'] ?? 'Appointment Reminder Preview');
$rawOnly = isset($_GET['raw']) && $_GET['raw'] === '1';

if ($rawOnly) {
    header('Content-Type: text/html; charset=UTF-8');
    echo $emailHtml;
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Reminder Email Preview</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f8fafc; color: #0f172a; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 16px; margin-bottom: 14px; }
        .meta { font-size: 14px; color: #334155; line-height: 1.6; }
        .meta strong { color: #0f172a; }
        .links a { color: #1d4ed8; text-decoration: none; margin-right: 12px; }
        .links a:hover { text-decoration: underline; }
        iframe { width: 100%; height: 78vh; border: 1px solid #cbd5e1; border-radius: 10px; background: #fff; }
        .warn { color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 8px 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="panel">
            <h2 style="margin:0 0 10px 0;">Appointment Reminder Email Preview</h2>
            <div class="meta">
                <div><strong>Subject:</strong> <?php echo htmlspecialchars($emailSubject, ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong>Reminder Type:</strong> <?php echo htmlspecialchars($reminderType, ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong>Booking ID:</strong> <?php echo htmlspecialchars((string) ($appointment['booking_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong>Patient:</strong> <?php echo htmlspecialchars((string) ($appointment['patient_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong>Date/Time:</strong> <?php echo htmlspecialchars((string) ($appointment['appointment_date_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> at <?php echo htmlspecialchars((string) ($appointment['appointment_time_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php if ($usingFallback): ?>
                <div class="warn">No upcoming appointment found, showing fallback sample data.</div>
            <?php endif; ?>
            <div class="links" style="margin-top:10px;">
                <a href="?type=3day">3-Day</a>
                <a href="?type=1day">1-Day</a>
                <a href="?type=final">Final</a>
                <a href="?type=<?php echo urlencode($reminderType); ?>&raw=1" target="_blank" rel="noopener">Open Raw Email HTML</a>
            </div>
        </div>
        <iframe
            title="Reminder Email Preview"
            src="?type=<?php echo urlencode($reminderType); ?>&raw=1"></iframe>
    </div>
</body>
</html>
