<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/appointment_db_tables.php';
require_once __DIR__ . '/clinic_customization.php';
require_once dirname(__DIR__, 2) . '/mail_config.php';

/**
 * Appointment reminder timing (runner: clinic/cron/send_appointment_reminders.php).
 *
 * Set to false for production-style offsets (~3 days, ~1 day, final ~2–4 hours before).
 * Set to true while debugging so reminders fire at ~3 hours, ~1 hour, and ~15–20 minutes before.
 *
 * IMPORTANT: Flip back to false and verify cron intervals before going live.
 */
const APPOINTMENT_REMINDER_TEST_SCHEDULE = true;

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
                $stmt = $pdo->prepare("SELECT clinic_name FROM {$qTenants} WHERE tenant_id = ? LIMIT 1");
                $stmt->execute([$tenantId]);
                $tenantRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if (!empty($tenantRow['clinic_name'])) {
                    $info['clinic_name'] = trim((string) $tenantRow['clinic_name']);
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
        return $base . '/uploads/clinic/' . ltrim($value, '/');
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

if (!function_exists('send_scheduled_appointment_reminders')) {
    function send_scheduled_appointment_reminders(PDO $pdo, ?string $forceTenantId = null): array
    {
        date_default_timezone_set('Asia/Manila');

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
                'schedule_mode' => APPOINTMENT_REMINDER_TEST_SCHEDULE ? 'test' : 'production',
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
                COALESCE(NULLIF(TRIM(u.email), ''), '') AS patient_email,
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
              AND a.appointment_date <= DATE_ADD(CURDATE(), INTERVAL 4 DAY)
              AND COALESCE(a.visit_type, 'pre_book') <> 'walk_in'
              AND COALESCE(a.status, 'pending') NOT IN ('cancelled', 'completed', 'no_show')
        ";
        $params = [];
        if ($forceTenantId !== null && trim($forceTenantId) !== '') {
            $sql .= " AND a.tenant_id = ? ";
            $params[] = trim($forceTenantId);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        /** Match window around each target; cron should run at least every ~5 minutes during tests. */
        $windowSeconds = 10 * 60;
        $result = [
            'ok' => true,
            'checked' => count($appointments),
            'sent' => ['3day' => 0, '1day' => 0, 'final' => 0],
            'errors' => [],
            'schedule_mode' => APPOINTMENT_REMINDER_TEST_SCHEDULE ? 'test' : 'production',
        ];
        $clinicCache = [];

        foreach ($appointments as $appointment) {
            $email = trim((string) ($appointment['patient_email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $appointmentDate = trim((string) ($appointment['appointment_date'] ?? ''));
            $appointmentTimeRaw = trim((string) ($appointment['appointment_time'] ?? ''));
            if ($appointmentDate === '' || $appointmentTimeRaw === '') {
                continue;
            }

            $timeValue = strlen($appointmentTimeRaw) === 5 ? $appointmentTimeRaw . ':00' : $appointmentTimeRaw;
            $appointmentAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $appointmentDate . ' ' . $timeValue, new DateTimeZone('Asia/Manila'));
            if (!$appointmentAt) {
                continue;
            }

            $diffSeconds = $appointmentAt->getTimestamp() - $now->getTimestamp();
            if ($diffSeconds <= 0) {
                continue;
            }

            $sendType = null;
            $flagColumn = null;

            if (APPOINTMENT_REMINDER_TEST_SCHEDULE) {
                // Test: ~3h, ~1h, final in the 15–20 min band (center 17.5 min ± 2.5 min).
                $targetFirst = 3 * 3600;
                $targetSecond = 1 * 3600;
                $targetFinalCenterSeconds = (int) round((15 + 20) / 2 * 60);
                $finalHalfBandSeconds = (int) round((20 - 15) / 2 * 60);

                if (empty($appointment['reminder_3day_sent_at']) && abs($diffSeconds - $targetFirst) <= $windowSeconds) {
                    $sendType = '3day';
                    $flagColumn = 'reminder_3day_sent_at';
                } elseif (empty($appointment['reminder_1day_sent_at']) && abs($diffSeconds - $targetSecond) <= $windowSeconds) {
                    $sendType = '1day';
                    $flagColumn = 'reminder_1day_sent_at';
                } elseif (empty($appointment['reminder_final_sent_at'])
                    && abs($diffSeconds - $targetFinalCenterSeconds) <= $finalHalfBandSeconds) {
                    $sendType = 'final';
                    $flagColumn = 'reminder_final_sent_at';
                }
            } else {
                // Production: ~3 days, ~1 day, final once in the 2–4 hour window before start.
                $target3Day = 3 * 24 * 3600;
                $target1Day = 24 * 3600;
                $minFinal = 2 * 3600;
                $maxFinal = 4 * 3600;

                if (empty($appointment['reminder_3day_sent_at']) && abs($diffSeconds - $target3Day) <= $windowSeconds) {
                    $sendType = '3day';
                    $flagColumn = 'reminder_3day_sent_at';
                } elseif (empty($appointment['reminder_1day_sent_at']) && abs($diffSeconds - $target1Day) <= $windowSeconds) {
                    $sendType = '1day';
                    $flagColumn = 'reminder_1day_sent_at';
                } elseif (empty($appointment['reminder_final_sent_at']) && $diffSeconds >= $minFinal && $diffSeconds <= $maxFinal) {
                    $sendType = 'final';
                    $flagColumn = 'reminder_final_sent_at';
                }
            }

            if ($sendType === null || $flagColumn === null) {
                continue;
            }

            $tenantId = trim((string) ($appointment['tenant_id'] ?? ''));
            if ($tenantId === '') {
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
                $result['errors'][] = 'Failed to send ' . $sendType . ' reminder for booking ' . (string) ($appointment['booking_id'] ?? '') . ': ' . (string) ($GLOBALS['smtp_last_error'] ?? 'Unknown SMTP error');
                continue;
            }

            try {
                $flagSql = "UPDATE {$qAppointments} SET {$flagColumn} = NOW(), updated_at = NOW() WHERE id = ? AND tenant_id = ?";
                $updateStmt = $pdo->prepare($flagSql);
                $updateStmt->execute([(int) $appointment['id'], $tenantId]);
                $result['sent'][$sendType]++;
            } catch (Throwable $e) {
                $result['errors'][] = 'Reminder sent but failed to store status for booking ' . (string) ($appointment['booking_id'] ?? '') . ': ' . $e->getMessage();
            }
        }

        return $result;
    }
}
