<?php

declare(strict_types=1);

require_once __DIR__ . '/../../clinic/includes/appointment_db_tables.php';

/**
 * Patient booking confirmation email — same transport as staff receipts (mail_config.php → send_smtp_gmail).
 */

function mobile_booking_confirmation_load_mail(): bool
{
    static $done = false;
    if ($done) {
        return function_exists('send_smtp_gmail');
    }
    $done = true;
    $root = dirname(__DIR__, 2);
    $path = $root . '/mail_config.php';
    if (!is_file($path)) {
        return false;
    }
    require_once $path;

    return function_exists('send_smtp_gmail');
}

/** Appended to tbl_payments.notes after a confirmation email sends successfully (avoid duplicate sends). */
const MOBILE_BOOKING_CONF_EMAIL_NOTE_TAG = '[booking_conf_mail:sent]';

/**
 * Email on file for the chart row — tbl_patients.email (not tbl_users).
 */
function mobile_booking_confirmation_patient_email(PDO $pdo, string $tenantId, string $patientId): string
{
    $patientId = trim($patientId);
    if ($patientId === '') {
        return '';
    }
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $patPhys = $tables['patients'] ?? 'tbl_patients';
    if ($patPhys === null || $patPhys === '') {
        $patPhys = 'tbl_patients';
    }
    $asc = clinic_table_columns($pdo, (string) $patPhys);
    if (!in_array('email', $asc, true)) {
        return '';
    }

    $pq = clinic_quote_identifier((string) $patPhys);
    $tenantId = trim($tenantId);
    try {
        if ($tenantId !== '') {
            $stmt = $pdo->prepare(
                "SELECT NULLIF(TRIM(p.email), '') AS patient_email
                 FROM {$pq} p
                 WHERE TRIM(p.patient_id) = ?
                   AND TRIM(COALESCE(p.tenant_id, '')) = TRIM(?)
                 LIMIT 1"
            );
            $stmt->execute([$patientId, $tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $em = trim((string) ($row['patient_email'] ?? ''));
            if ($em !== '') {
                return $em;
            }
        }

        $stmt = $pdo->prepare(
            "SELECT NULLIF(TRIM(p.email), '') AS patient_email
             FROM {$pq} p
             WHERE TRIM(p.patient_id) = ?
             LIMIT 1"
        );
        $stmt->execute([$patientId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return trim((string) ($row['patient_email'] ?? ''));
    } catch (Throwable $e) {
        error_log('mobile_booking_confirmation_patient_email failed: ' . $e->getMessage());

        return '';
    }
}

/** @return array{0: string, 1: string} [display_name, doctor_label] */
function mobile_booking_confirmation_appointment_context(PDO $pdo, string $tenantId, string $bookingId): array
{
    $bookingId = trim($bookingId);
    $display = '';
    $doctor = '';
    if ($bookingId === '') {
        return [$display, $doctor];
    }
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $apptPhys = $tables['appointments'] ?? null;
    if ($apptPhys === null || $apptPhys === '') {
        return [$display, $doctor];
    }
    $denPhys = $tables['dentists'] ?? clinic_get_physical_table_name($pdo, 'tbl_dentists');
    if ($denPhys === null || $denPhys === '') {
        $denPhys = 'tbl_dentists';
    }
    $aq = clinic_quote_identifier((string) $apptPhys);
    $dq = clinic_quote_identifier((string) $denPhys);
    $sql = "SELECT TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))) AS dn,
                   a.appointment_date, a.appointment_time
            FROM {$aq} a
            LEFT JOIN {$dq} d ON d.dentist_id = a.dentist_id
            WHERE TRIM(a.booking_id) = ?
            LIMIT 1";
    if ($tenantId !== '') {
        $sql = "SELECT TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))) AS dn,
                       a.appointment_date, a.appointment_time
                FROM {$aq} a
                LEFT JOIN {$dq} d ON d.dentist_id = a.dentist_id
                WHERE TRIM(a.booking_id) = ?
                  AND (a.tenant_id = ? OR TRIM(COALESCE(a.tenant_id, '')) = '')
                LIMIT 1";
    }
    $st = $pdo->prepare($sql);
    if ($tenantId !== '') {
        $st->execute([$bookingId, $tenantId]);
    } else {
        $st->execute([$bookingId]);
    }
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!is_array($r)) {
        return [$display, $doctor];
    }
    $dn = trim((string) ($r['dn'] ?? ''));
    if ($dn !== '') {
        $doctor = 'Dr. ' . $dn;
    }
    $d = trim((string) ($r['appointment_date'] ?? ''));
    $t = trim((string) ($r['appointment_time'] ?? ''));
    if ($d !== '' || $t !== '') {
        $display = trim($d . ($d !== '' && $t !== '' ? ' · ' : '') . $t);
    }

    return [$display, $doctor];
}

function mobile_booking_confirmation_clinic_name(PDO $pdo, string $tenantId): string
{
    $tenantId = trim($tenantId);
    if ($tenantId === '') {
        return 'MyDental Philippines';
    }
    try {
        $st = $pdo->prepare('SELECT clinic_name FROM tbl_tenants WHERE tenant_id = ? LIMIT 1');
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        $name = trim((string) ($row['clinic_name'] ?? ''));

        return $name !== '' ? $name : 'MyDental Philippines';
    } catch (Throwable $e) {
        return 'MyDental Philippines';
    }
}

function mobile_booking_confirmation_send_safe(string $toEmail, string $subject, string $bodyText, string $bodyHtml): bool
{
    if (!mobile_booking_confirmation_load_mail()) {
        error_log('mobile_booking_confirmation: mail_config.php missing or send_smtp_gmail undefined (path tried: ' . dirname(__DIR__, 2) . '/mail_config.php)');

        return false;
    }

    $ok = send_smtp_gmail($toEmail, $subject, $bodyText, $bodyHtml);
    if (!$ok) {
        $err = isset($GLOBALS['smtp_last_error']) ? (string) $GLOBALS['smtp_last_error'] : '';
        error_log('mobile_booking_confirmation: SMTP failed — ' . ($err !== '' ? $err : 'unknown'));
    }

    return $ok;
}

/**
 * Idempotent: sets notes tag when send succeeds (prevents duplicate mail on payment_success refresh).
 */
function mobile_booking_confirmation_mark_payment_email_sent(PDO $pdo, string $paymentId): void
{
    $paymentId = trim($paymentId);
    if ($paymentId === '') {
        return;
    }
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $paymentsPhys = $tables['payments'] ?? 'tbl_payments';
    if ($paymentsPhys === null || $paymentsPhys === '') {
        $paymentsPhys = 'tbl_payments';
    }
    $quoted = clinic_quote_identifier((string) $paymentsPhys);
    $tag = MOBILE_BOOKING_CONF_EMAIL_NOTE_TAG;
    $asc = clinic_table_columns($pdo, (string) $paymentsPhys);
    if (!in_array('notes', $asc, true)) {
        return;
    }

    try {
        $sql = 'UPDATE ' . $quoted . ' SET notes = TRIM(CONCAT(COALESCE(notes, \'\'), ?
            )) WHERE payment_id = ?
             AND (COALESCE(notes, \'\') NOT LIKE ?)';
        $st = $pdo->prepare($sql);
        $st->execute([' ' . $tag . ' ', $paymentId, '%' . $tag . '%']);
    } catch (Throwable $e) {
        error_log('mobile_booking_confirmation_mark_payment_email_sent: ' . $e->getMessage());
    }
}

/**
 * Sends booking confirmation to the patient's registered email.
 *
 * @param array<string, mixed> $paymentLikeRow Minimal: tenant_id, patient_id, booking_id, optional amount, payment_type
 */
function mobile_try_send_booking_confirmation_email(PDO $pdo, array $paymentLikeRow): bool
{
    $bookingId = trim((string) ($paymentLikeRow['booking_id'] ?? ''));
    $patientId = trim((string) ($paymentLikeRow['patient_id'] ?? ''));
    $tenantId = trim((string) ($paymentLikeRow['tenant_id'] ?? ''));
    $paymentId = trim((string) ($paymentLikeRow['payment_id'] ?? ''));
    $notes = (string) ($paymentLikeRow['notes'] ?? '');
    if ($paymentId !== '' && strpos($notes, MOBILE_BOOKING_CONF_EMAIL_NOTE_TAG) !== false) {
        return true;
    }
    if ($bookingId === '' || $patientId === '') {
        return false;
    }
    $to = mobile_booking_confirmation_patient_email($pdo, $tenantId, $patientId);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('mobile_booking_confirmation: no tbl_patients.email for booking ' . $bookingId . ' patient ' . $patientId);

        return false;
    }
    $clinicName = htmlspecialchars(mobile_booking_confirmation_clinic_name($pdo, $tenantId), ENT_QUOTES, 'UTF-8');
    [$whenLine, $doctorLine] = mobile_booking_confirmation_appointment_context($pdo, $tenantId, $bookingId);
    $amount = isset($paymentLikeRow['amount']) ? (float) $paymentLikeRow['amount'] : 0.0;
    $ptype = strtolower(trim((string) ($paymentLikeRow['payment_type'] ?? '')));
    $payLine = $amount > 0.009
        ? 'Amount recorded: PHP ' . number_format($amount, 2, '.', ',') . ($ptype !== '' ? " ({$ptype})" : '')
        : '';

    $subject = 'Booking confirmed — ' . strip_tags($clinicName);
    $lines = [
        'Your appointment is confirmed.',
        'Clinic: ' . strip_tags($clinicName),
        'Booking reference: ' . $bookingId,
    ];
    if ($whenLine !== '') {
        $lines[] = 'Schedule: ' . $whenLine;
    }
    if ($doctorLine !== '') {
        $lines[] = 'Dentist: ' . $doctorLine;
    }
    if ($payLine !== '') {
        $lines[] = $payLine;
    }
    $lines[] = '';
    $lines[] = 'Please bring a valid ID and arrive a few minutes early. If you need to reschedule, contact the clinic.';
    $bodyText = implode("\n", $lines);

    $htmlBits = [
        '<p>Your appointment is <strong>confirmed</strong>.</p>',
        '<p><strong>Clinic:</strong> ' . $clinicName . '</p>',
        '<p><strong>Booking reference:</strong> ' . htmlspecialchars($bookingId, ENT_QUOTES, 'UTF-8') . '</p>',
    ];
    if ($whenLine !== '') {
        $htmlBits[] = '<p><strong>Schedule:</strong> ' . htmlspecialchars($whenLine, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($doctorLine !== '') {
        $htmlBits[] = '<p><strong>Dentist:</strong> ' . htmlspecialchars($doctorLine, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    if ($payLine !== '') {
        $htmlBits[] = '<p><strong>Payment:</strong> ' . htmlspecialchars($payLine, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    $htmlBits[] = '<p>Please bring a valid ID and arrive a few minutes early. If you need to reschedule, contact the clinic.</p>';
    $bodyHtml = '<div style="font-family:Segoe UI,Roboto,sans-serif;font-size:15px;line-height:1.5;color:#0f172a;">'
        . implode('', $htmlBits) . '</div>';

    $sent = mobile_booking_confirmation_send_safe($to, $subject, $bodyText, $bodyHtml);
    if ($sent && $paymentId !== '') {
        mobile_booking_confirmation_mark_payment_email_sent($pdo, $paymentId);
    }

    return $sent;
}
