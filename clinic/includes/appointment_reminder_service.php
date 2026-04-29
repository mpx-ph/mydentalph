<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/appointment_db_tables.php';
require_once __DIR__ . '/clinic_customization.php';
require_once dirname(__DIR__, 2) . '/mail_config.php';

/**
 * Appointment reminder timing (runner: clinic/cron/send_appointment_reminders.php).
 *
 * Use strict same-day offsets with catch-up support for late-created bookings.
 */
const APPOINTMENT_REMINDER_SCHEDULE_SECONDS = [
    '3day' => 3 * 3600,
    '1day' => 1 * 3600,
    'final' => 30 * 60,
];

if (!function_exists('appointment_reminder_quote_ident')) {
    function appointment_reminder_quote_ident(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }
}

if (!function_exists('appointment_reminder_ensure_tracking_columns')) {
    function appointment_reminder_ensure_tracking_columns(PDO $pdo, string $appointmentsTable): void
    {
        $cols = clinic_table_columns($pdo, $appointmentsTable);
        $needed = [
            'reminder_3day_sent_at' => "ADD COLUMN `reminder_3day_sent_at` DATETIME NULL",
            'reminder_1day_sent_at' => "ADD COLUMN `reminder_1day_sent_at` DATETIME NULL",
            'reminder_final_sent_at' => "ADD COLUMN `reminder_final_sent_at` DATETIME NULL",
        ];
        $qAppointments = appointment_reminder_quote_ident($appointmentsTable);
        foreach ($needed as $col => $ddl) {
            if (in_array($col, $cols, true)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE {$qAppointments} {$ddl}");
            } catch (Throwable $e) {
                error_log('Appointment reminder schema ensure failed: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('appointment_reminder_load_tenant_clinic_info')) {
    function appointment_reminder_load_tenant_clinic_info(PDO $pdo, string $tenantId): array
    {
        global $CLINIC;

        $info = [
            'clinic_name' => trim((string) ($CLINIC['clinic_name'] ?? SITE_NAME)),
            'logo' => trim((string) ($CLINIC['logo_nav'] ?? ($CLINIC['logo'] ?? ''))),
            'contact_address' => trim((string) ($CLINIC['contact_address'] ?? '')),
            'contact_phone' => trim((string) ($CLINIC['contact_phone'] ?? '')),
            'contact_email' => trim((string) ($CLINIC['contact_email'] ?? '')),
            'footer_social_url' => trim((string) ($CLINIC['footer_social_url'] ?? '')),
        ];

        $tenantTable = clinic_get_physical_table_name($pdo, 'tbl_tenants') ?? clinic_get_physical_table_name($pdo, 'tenants');
        if ($tenantTable !== null) {
            try {
                $qTenants = appointment_reminder_quote_ident($tenantTable);
                $tenantCols = clinic_table_columns($pdo, $tenantTable);
                $selectFields = ['clinic_name'];

                if (in_array('clinic_address', $tenantCols, true)) {
                    $selectFields[] = 'clinic_address';
                } elseif (in_array('contact_address', $tenantCols, true)) {
                    $selectFields[] = 'contact_address';
                }
                if (in_array('contact_phone', $tenantCols, true)) {
                    $selectFields[] = 'contact_phone';
                }
                if (in_array('contact_email', $tenantCols, true)) {
                    $selectFields[] = 'contact_email';
                }
                if (in_array('logo_nav', $tenantCols, true)) {
                    $selectFields[] = 'logo_nav';
                } elseif (in_array('logo', $tenantCols, true)) {
                    $selectFields[] = 'logo';
                }

                $stmt = $pdo->prepare("SELECT " . implode(', ', $selectFields) . " FROM {$qTenants} WHERE tenant_id = ? LIMIT 1");
                $stmt->execute([$tenantId]);
                $tenantRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if (!empty($tenantRow['clinic_name'])) {
                    $info['clinic_name'] = trim((string) $tenantRow['clinic_name']);
                }
                if (!empty($tenantRow['clinic_address']) && $info['contact_address'] === '') {
                    $info['contact_address'] = trim((string) $tenantRow['clinic_address']);
                } elseif (!empty($tenantRow['contact_address']) && $info['contact_address'] === '') {
                    $info['contact_address'] = trim((string) $tenantRow['contact_address']);
                }
                if (!empty($tenantRow['contact_phone']) && $info['contact_phone'] === '') {
                    $info['contact_phone'] = trim((string) $tenantRow['contact_phone']);
                }
                if (!empty($tenantRow['contact_email']) && $info['contact_email'] === '') {
                    $info['contact_email'] = trim((string) $tenantRow['contact_email']);
                }
                if (!empty($tenantRow['logo_nav']) && $info['logo'] === '') {
                    $info['logo'] = trim((string) $tenantRow['logo_nav']);
                } elseif (!empty($tenantRow['logo']) && $info['logo'] === '') {
                    $info['logo'] = trim((string) $tenantRow['logo']);
                }
            } catch (Throwable $e) {
                error_log('Appointment reminder tenant fetch failed: ' . $e->getMessage());
            }
        }

        try {
            $tenantCustomizationTable = clinic_get_physical_table_name($pdo, 'clinic_customization_tenant');
            if ($tenantCustomizationTable !== null) {
                $qTenantCustomization = appointment_reminder_quote_ident($tenantCustomizationTable);
                $stmt = $pdo->prepare("
                    SELECT option_key, option_value
                    FROM {$qTenantCustomization}
                    WHERE tenant_id = ?
                      AND option_key IN (
                        'clinic_name', 'logo', 'logo_nav',
                        'contact_address', 'contact_phone', 'contact_email',
                        'footer_social_url'
                      )
                ");
                $stmt->execute([$tenantId]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $key = trim((string) ($row['option_key'] ?? ''));
                    $value = trim((string) ($row['option_value'] ?? ''));
                    if ($key === 'clinic_name' && $value !== '') {
                        $info['clinic_name'] = $value;
                    } elseif ($key === 'logo_nav' && $value !== '') {
                        $info['logo'] = $value;
                    } elseif ($key === 'logo' && $value !== '' && $info['logo'] === '') {
                        $info['logo'] = $value;
                    } elseif (in_array($key, ['contact_address', 'contact_phone', 'contact_email', 'footer_social_url'], true) && $value !== '') {
                        $info[$key] = $value;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Appointment reminder customization fetch failed: ' . $e->getMessage());
        }

        if ($info['clinic_name'] === '') {
            $info['clinic_name'] = SITE_NAME;
        }

        return $info;
    }
}

if (!function_exists('appointment_reminder_to_absolute_url')) {
    function appointment_reminder_to_absolute_url(string $path): string
    {
        $value = trim($path);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        $base = rtrim((string) BASE_URL, '/');
        if (strpos($value, '/') === 0) {
            return $base . $value;
        }
        $normalized = ltrim($value, '/\\');
        $directUrl = $base . '/' . $normalized;

        if (strpos($normalized, '/') !== false) {
            return $directUrl;
        }

        $uploadRelative = 'uploads/clinic/' . $normalized;
        $uploadUrl = $base . '/' . $uploadRelative;
        if (defined('ROOT_PATH')) {
            $directPath = ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $normalized);
            if (is_file($directPath)) {
                return $directUrl;
            }
            $uploadPath = ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $uploadRelative);
            if (is_file($uploadPath)) {
                return $uploadUrl;
            }
        }

        // Prefer direct BASE_URL-relative path as a safer default for tenant logo files.
        return $directUrl;
    }
}

if (!function_exists('appointment_reminder_build_email')) {
    function appointment_reminder_build_email(array $appointment, array $clinicInfo, string $reminderType): array
    {
        $patientName = trim((string) ($appointment['patient_name'] ?? 'Patient'));
        $dateLabel = trim((string) ($appointment['appointment_date_label'] ?? $appointment['appointment_date'] ?? ''));
        $timeLabel = trim((string) ($appointment['appointment_time_label'] ?? $appointment['appointment_time'] ?? ''));
        $dentistLabel = trim((string) ($appointment['dentist_name'] ?? 'Assigned Dentist'));
        $serviceLabel = trim((string) ($appointment['service_name'] ?? $appointment['service_type'] ?? 'Dental Service'));
        $bookingId = trim((string) ($appointment['booking_id'] ?? ''));
        $logoUrl = appointment_reminder_to_absolute_url((string) ($clinicInfo['logo'] ?? ''));
        $clinicName = htmlspecialchars((string) ($clinicInfo['clinic_name'] ?? 'Dental Clinic'), ENT_QUOTES, 'UTF-8');
        $address = htmlspecialchars((string) ($clinicInfo['contact_address'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phone = htmlspecialchars((string) ($clinicInfo['contact_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars((string) ($clinicInfo['contact_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $social = trim((string) ($clinicInfo['footer_social_url'] ?? ''));

        $subjectPrefix = [
            '3day' => 'Early Reminder',
            '1day' => 'Main Reminder',
            'final' => 'Final Reminder',
        ][$reminderType] ?? 'Appointment Reminder';

        $safePatientName = htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8');
        $safeDate = htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8');
        $safeTime = htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8');
        $safeDentist = htmlspecialchars($dentistLabel, ENT_QUOTES, 'UTF-8');
        $safeService = htmlspecialchars($serviceLabel, ENT_QUOTES, 'UTF-8');
        $safeBookingId = htmlspecialchars($bookingId, ENT_QUOTES, 'UTF-8');

        $emailContactFallback = $email !== '' ? $email : 'our clinic email';
        $phoneContactFallback = $phone !== '' ? $phone : 'our clinic hotline';

        $logoHtml = $logoUrl !== ''
            ? '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Clinic Logo" style="height:42px;max-width:180px;object-fit:contain;" />'
            : '';

        $socialHtml = $social !== ''
            ? '<p style="margin:10px 0 0 0;"><a href="' . htmlspecialchars($social, ENT_QUOTES, 'UTF-8') . '" style="color:#2563eb;text-decoration:none;">Follow us on social media</a></p>'
            : '';

        $subject = $subjectPrefix . ': ' . html_entity_decode($clinicName, ENT_QUOTES, 'UTF-8') . ' Appointment';
        $bodyText = "Dear {$patientName},\n\n"
            . "This is a reminder about your dental appointment.\n"
            . "Date: {$dateLabel}\n"
            . "Time: {$timeLabel}\n"
            . "Dentist: {$dentistLabel}\n"
            . "Service: {$serviceLabel}\n"
            . "Booking ID: {$bookingId}\n\n"
            . "Please arrive 10-15 minutes before your scheduled time.\n"
            . "For confirmation, reschedule, or cancellation, contact us via {$phoneContactFallback} or {$emailContactFallback}.\n";

        $bodyHtml = '<div style="font-family:Arial,sans-serif;background:#f3f6fb;padding:24px;">'
            . '<div style="max-width:680px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5e7eb;">'
            . '<div style="padding:18px 24px;background:#eff6ff;border-bottom:1px solid #dbeafe;">'
            . '<div style="display:flex;align-items:center;gap:12px;">'
            . $logoHtml
            . '<div>'
            . '<div style="font-size:20px;font-weight:700;color:#0f172a;">' . $clinicName . '</div>'
            . '<div style="font-size:14px;color:#1d4ed8;font-weight:600;">Appointment Reminder</div>'
            . '</div></div></div>'
            . '<div style="padding:24px;">'
            . '<p style="margin:0 0 10px 0;font-size:16px;color:#0f172a;">Dear ' . $safePatientName . ',</p>'
            . '<p style="margin:0 0 18px 0;color:#334155;line-height:1.5;">This is a friendly reminder for your upcoming dental appointment. Please review your details below.</p>'
            . '<div style="border:1px solid #e2e8f0;border-radius:12px;padding:16px;background:#f8fafc;">'
            . '<div style="font-size:15px;color:#0f172a;line-height:1.8;">'
            . '<div><strong>Date:</strong> ' . $safeDate . '</div>'
            . '<div><strong>Time:</strong> ' . $safeTime . '</div>'
            . '<div><strong>Dentist Name:</strong> ' . $safeDentist . '</div>'
            . '<div><strong>Service Name:</strong> ' . $safeService . '</div>'
            . '<div><strong>Booking ID:</strong> ' . $safeBookingId . '</div>'
            . '</div></div>'
            . '<p style="margin:18px 0 18px 0;color:#1e293b;font-weight:600;">Please arrive 10-15 minutes before your scheduled time.</p>'
            . '<div style="padding:14px;border-radius:10px;background:#f8fafc;border:1px dashed #cbd5e1;">'
            . '<p style="margin:0 0 8px 0;color:#334155;font-weight:600;">Appointment Actions</p>'
            . '<p style="margin:0;color:#475569;line-height:1.5;">To confirm, reschedule, or cancel your appointment, please contact us via phone or email. If your email client does not support buttons, simply reply to this message or call our clinic.</p>'
            . '</div>'
            . '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;color:#334155;font-size:14px;">'
            . '<div><strong>Clinic Address:</strong> ' . ($address !== '' ? $address : 'N/A') . '</div>'
            . '<div><strong>Contact Number:</strong> ' . ($phone !== '' ? $phone : 'N/A') . '</div>'
            . '<div><strong>Email:</strong> ' . ($email !== '' ? $email : 'N/A') . '</div>'
            . $socialHtml
            . '</div>'
            . '<p style="margin:20px 0 0 0;color:#64748b;font-size:13px;">Thank you for choosing ' . $clinicName . '. We look forward to seeing you.</p>'
            . '</div></div></div>';

        return ['subject' => $subject, 'text' => $bodyText, 'html' => $bodyHtml];
    }
}

if (!function_exists('appointment_reminder_normalize_sent_flag')) {
    function appointment_reminder_normalize_sent_flag($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '0000-00-00 00:00:00') {
            return null;
        }
        return $text;
    }
}

if (!function_exists('appointment_reminder_parse_datetime')) {
    function appointment_reminder_parse_datetime(string $dateValue, string $timeValue, DateTimeZone $timezone): ?DateTimeImmutable
    {
        $dateValue = trim($dateValue);
        $timeValue = trim($timeValue);
        if ($dateValue === '' || $timeValue === '') {
            return null;
        }

        $normalizedTime = preg_replace('/\s+/', ' ', strtoupper($timeValue));
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d g:i A',
            'Y-m-d g:iA',
            'Y-m-d h:i A',
            'Y-m-d h:iA',
        ];
        foreach ($formats as $format) {
            $candidate = DateTimeImmutable::createFromFormat($format, $dateValue . ' ' . $normalizedTime, $timezone);
            if ($candidate instanceof DateTimeImmutable) {
                return $candidate;
            }
        }

        // Last fallback for slightly unusual but valid time strings.
        try {
            return new DateTimeImmutable($dateValue . ' ' . $timeValue, $timezone);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('send_scheduled_appointment_reminders')) {
    function send_scheduled_appointment_reminders(PDO $pdo, ?string $forceTenantId = null): array
    {
        date_default_timezone_set('Asia/Manila');
        $timezone = new DateTimeZone('Asia/Manila');

        $tables = clinic_resolve_appointment_db_tables($pdo);
        $appointmentsTable = $tables['appointments'];
        $patientsTable = $tables['patients'];
        $usersTable = $tables['users'];
        $dentistsTable = $tables['dentists'];

        if ($appointmentsTable === null || $patientsTable === null || $usersTable === null) {
            return [
                'ok' => false,
                'checked' => 0,
                'sent' => ['3day' => 0, '1day' => 0, 'final' => 0],
                'errors' => ['Required appointment tables not found.'],
                'schedule_mode' => '3h_1h_30m',
            ];
        }

        appointment_reminder_ensure_tracking_columns($pdo, $appointmentsTable);

        $appointmentsCols = clinic_table_columns($pdo, $appointmentsTable);
        $dentistJoinSql = '';
        $dentistNameExpr = "'Assigned Dentist'";
        if ($dentistsTable !== null && in_array('dentist_id', $appointmentsCols, true)) {
            $qDentists = appointment_reminder_quote_ident($dentistsTable);
            $dentistJoinSql = " LEFT JOIN {$qDentists} d ON d.tenant_id = a.tenant_id AND d.dentist_id = a.dentist_id ";
            $dentistNameExpr = "TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, '')))";
        }

        $qAppointments = appointment_reminder_quote_ident($appointmentsTable);
        $qPatients = appointment_reminder_quote_ident($patientsTable);
        $qUsers = appointment_reminder_quote_ident($usersTable);
        $patientCols = clinic_table_columns($pdo, $patientsTable);
        $patientEmailExpr = "COALESCE(NULLIF(TRIM(u.email), ''), '')";
        if (in_array('email', $patientCols, true)) {
            $patientEmailExpr = "COALESCE(NULLIF(TRIM(u.email), ''), NULLIF(TRIM(p.email), ''), '')";
        }

        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->format('Y-m-d');
        $maxDate = $now->modify('+4 days')->format('Y-m-d');

        $sql = "
            SELECT
                a.id,
                a.tenant_id,
                a.booking_id,
                a.appointment_date,
                a.appointment_time,
                a.service_type,
                a.status,
                a.visit_type,
                a.reminder_3day_sent_at,
                a.reminder_1day_sent_at,
                a.reminder_final_sent_at,
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
            WHERE a.appointment_date >= ?
              AND a.appointment_date <= ?
              AND COALESCE(a.visit_type, 'pre_book') <> 'walk_in'
              AND COALESCE(a.status, 'pending') NOT IN ('cancelled', 'completed', 'no_show')
        ";
        $params = [$today, $maxDate];
        if ($forceTenantId !== null && trim($forceTenantId) !== '') {
            $sql .= " AND a.tenant_id = ? ";
            $params[] = trim($forceTenantId);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $result = [
            'ok' => true,
            'checked' => count($appointments),
            'sent' => ['3day' => 0, '1day' => 0, 'final' => 0],
            'errors' => [],
            'schedule_mode' => '3h_1h_30m',
            'server_now' => $now->format(DateTimeInterface::ATOM),
            'timezone' => $timezone->getName(),
            'debug' => [],
        ];
        $clinicCache = [];

        foreach ($appointments as $appointment) {
            $bookingId = (string) ($appointment['booking_id'] ?? '');
            $email = trim((string) ($appointment['patient_email'] ?? ''));
            $debug = [
                'booking_id' => $bookingId,
                'patient_email' => $email,
                'server_now' => $now->format(DateTimeInterface::ATOM),
                'appointment_date_raw' => (string) ($appointment['appointment_date'] ?? ''),
                'appointment_time_raw' => (string) ($appointment['appointment_time'] ?? ''),
                'matched_schedule' => null,
                'status' => 'pending',
            ];

            if ($email === '') {
                $debug['status'] = 'skipped';
                $debug['reason'] = 'missing_patient_email';
                $result['debug'][] = $debug;
                error_log('Appointment reminder skipped (missing email). booking=' . $bookingId);
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $debug['status'] = 'skipped';
                $debug['reason'] = 'invalid_patient_email';
                $result['debug'][] = $debug;
                error_log('Appointment reminder skipped (invalid email). booking=' . $bookingId . ' email=' . $email);
                continue;
            }

            $appointmentDate = trim((string) ($appointment['appointment_date'] ?? ''));
            $appointmentTimeRaw = trim((string) ($appointment['appointment_time'] ?? ''));
            if ($appointmentDate === '' || $appointmentTimeRaw === '') {
                $debug['status'] = 'skipped';
                $debug['reason'] = 'missing_schedule';
                $result['debug'][] = $debug;
                error_log('Appointment reminder skipped (missing schedule). booking=' . $bookingId);
                continue;
            }

            $appointmentAt = appointment_reminder_parse_datetime($appointmentDate, $appointmentTimeRaw, $timezone);
            if (!$appointmentAt) {
                $debug['status'] = 'skipped';
                $debug['reason'] = 'invalid_datetime';
                $result['debug'][] = $debug;
                error_log('Appointment reminder skipped (invalid datetime). booking=' . $bookingId . ' value=' . $appointmentDate . ' ' . $appointmentTimeRaw);
                continue;
            }
            $debug['appointment_time'] = $appointmentAt->format(DateTimeInterface::ATOM);

            $diffSeconds = $appointmentAt->getTimestamp() - $now->getTimestamp();
            $debug['seconds_until_appointment'] = $diffSeconds;
            if ($diffSeconds <= 0) {
                $debug['status'] = 'skipped';
                $debug['reason'] = 'appointment_in_past';
                $result['debug'][] = $debug;
                continue;
            }

            $sendType = null;
            $flagColumn = null;
            $reminder3daySentAt = appointment_reminder_normalize_sent_flag($appointment['reminder_3day_sent_at'] ?? null);
            $reminder1daySentAt = appointment_reminder_normalize_sent_flag($appointment['reminder_1day_sent_at'] ?? null);
            $reminderFinalSentAt = appointment_reminder_normalize_sent_flag($appointment['reminder_final_sent_at'] ?? null);
            $debug['existing_flags'] = [
                '3day' => $reminder3daySentAt,
                '1day' => $reminder1daySentAt,
                'final' => $reminderFinalSentAt,
            ];

            // Catch-up aware schedule:
            // if a reminder milestone has passed and is still unsent, send it now.
            // send at most one reminder per run (earliest milestone first) to avoid bursts.
            $flags = [
                '3day' => $reminder3daySentAt,
                '1day' => $reminder1daySentAt,
                'final' => $reminderFinalSentAt,
            ];
            $columns = [
                '3day' => 'reminder_3day_sent_at',
                '1day' => 'reminder_1day_sent_at',
                'final' => 'reminder_final_sent_at',
            ];

            foreach (['3day', '1day', 'final'] as $candidateType) {
                if ($flags[$candidateType] !== null) {
                    continue;
                }
                $targetOffset = (int) (APPOINTMENT_REMINDER_SCHEDULE_SECONDS[$candidateType] ?? 0);
                if ($targetOffset <= 0) {
                    continue;
                }
                if ($diffSeconds <= $targetOffset) {
                    $sendType = $candidateType;
                    $flagColumn = $columns[$candidateType];
                    break;
                }
            }

            if ($sendType === null || $flagColumn === null) {
                $debug['status'] = 'skipped';
                $debug['reason'] = 'outside_trigger_window_or_already_sent';
                $result['debug'][] = $debug;
                continue;
            }
            $debug['matched_schedule'] = $sendType;

            $tenantId = trim((string) ($appointment['tenant_id'] ?? ''));
            if ($tenantId === '') {
                $debug['status'] = 'skipped';
                $debug['reason'] = 'missing_tenant_id';
                $result['debug'][] = $debug;
                continue;
            }
            if (!isset($clinicCache[$tenantId])) {
                $clinicCache[$tenantId] = appointment_reminder_load_tenant_clinic_info($pdo, $tenantId);
            }

            $appointment['appointment_date_label'] = $appointmentAt->format('F j, Y (l)');
            $appointment['appointment_time_label'] = $appointmentAt->format('g:i A');
            $payload = appointment_reminder_build_email($appointment, $clinicCache[$tenantId], $sendType);
            $sent = send_smtp_gmail($email, $payload['subject'], $payload['text'], $payload['html']);

            if (!$sent) {
                $smtpError = (string) ($GLOBALS['smtp_last_error'] ?? 'Unknown SMTP error');
                $debug['status'] = 'failed';
                $debug['reason'] = 'smtp_send_failed';
                $debug['smtp_error'] = $smtpError;
                $result['debug'][] = $debug;
                $result['errors'][] = 'Failed to send ' . $sendType . ' reminder for booking ' . (string) ($appointment['booking_id'] ?? '') . ': ' . $smtpError;
                error_log('Appointment reminder send failed. booking=' . $bookingId . ' type=' . $sendType . ' error=' . $smtpError);
                continue;
            }

            try {
                $flagSetClauses = ["{$flagColumn} = NOW()"];
                if (in_array('updated_at', $appointmentsCols, true)) {
                    $flagSetClauses[] = "updated_at = NOW()";
                }
                $flagSql = "UPDATE {$qAppointments} SET " . implode(', ', $flagSetClauses) . " WHERE id = ? AND tenant_id = ?";
                $updateStmt = $pdo->prepare($flagSql);
                $updateStmt->execute([(int) $appointment['id'], $tenantId]);
                $result['sent'][$sendType]++;
                $debug['status'] = 'sent';
                $debug['flag_column_updated'] = $flagColumn;
                $result['debug'][] = $debug;
                error_log('Appointment reminder sent. booking=' . $bookingId . ' type=' . $sendType . ' appointment_at=' . $appointmentAt->format(DateTimeInterface::ATOM));
            } catch (Throwable $e) {
                $debug['status'] = 'warning';
                $debug['reason'] = 'sent_but_flag_update_failed';
                $debug['error'] = $e->getMessage();
                $result['debug'][] = $debug;
                $result['errors'][] = 'Reminder sent but failed to store status for booking ' . (string) ($appointment['booking_id'] ?? '') . ': ' . $e->getMessage();
            }
        }

        return $result;
    }
}
