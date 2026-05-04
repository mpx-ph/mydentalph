<?php

declare(strict_types=1);

/**
 * Patient mobile: move an existing SCHEDULED/PENDING appointment to a new slot (no new booking row, no payment).
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../clinic/includes/patient_booking_slots.php';
require_once __DIR__ . '/../clinic/includes/booking_treatment_ledger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'success' => false, 'message' => 'POST required']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$tenant_id = trim((string) ($input['tenant_id'] ?? ''));
$user_id = trim((string) ($input['user_id'] ?? ''));
$patient_id_in = trim((string) ($input['patient_id'] ?? ''));
$booking_id_in = trim((string) ($input['booking_id'] ?? ''));
$appointment_id_in = trim((string) ($input['appointment_id'] ?? ''));
$dentist_id_in = isset($input['dentist_id']) ? (int) $input['dentist_id'] : 0;
$notes_in = trim((string) ($input['notes'] ?? ''));

$fallbackYmd = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
$appointment_date = booking_normalize_mobile_date_input($input['appointment_date'] ?? null, $fallbackYmd);
$appointment_time = patient_booking_normalize_time($input['appointment_time'] ?? '');

if (
    $tenant_id === '' ||
    $user_id === '' ||
    $patient_id_in === '' ||
    ($booking_id_in === '' && $appointment_id_in === '') ||
    $appointment_date === null ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date) ||
    $appointment_time === null
) {
    die(json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Missing or invalid fields for reschedule.',
    ]));
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT patient_id FROM tbl_patients
         WHERE tenant_id = ? AND TRIM(patient_id) = ?
           AND (owner_user_id = ? OR linked_user_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$tenant_id, $patient_id_in, $user_id, $user_id]);
    $pRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pRow) {
        throw new Exception('Patient not found for this account.');
    }
    $patient_id = trim((string) $pRow['patient_id']);

    $tables = clinic_resolve_appointment_db_tables($pdo);
    $apptPhys = $tables['appointments'] ?? 'tbl_appointments';
    if ($apptPhys === null || $apptPhys === '') {
        throw new Exception('Appointments table not found.');
    }
    $apptQuoted = clinic_quote_identifier((string) $apptPhys);

    if ($appointment_id_in !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, booking_id, status, dentist_id, notes, appointment_date, appointment_time
             FROM {$apptQuoted}
             WHERE tenant_id = ? AND id = ? AND patient_id = ?
             LIMIT 1"
        );
        $stmt->execute([$tenant_id, $appointment_id_in, $patient_id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, booking_id, status, dentist_id, notes, appointment_date, appointment_time
             FROM {$apptQuoted}
             WHERE tenant_id = ? AND TRIM(booking_id) = ? AND patient_id = ?
             LIMIT 1"
        );
        $stmt->execute([$tenant_id, $booking_id_in, $patient_id]);
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('Appointment not found or access denied.');
    }

    $resolvedId = trim((string) ($row['id'] ?? ''));
    $resolvedBookingId = trim((string) ($row['booking_id'] ?? ''));
    /** Raw tbl_appointments.status — get_appointments maps e.g. confirmed → SCHEDULED for the app. */
    $dbStatus = strtolower(trim((string) ($row['status'] ?? '')));
    if ($dbStatus === '') {
        $dbStatus = 'pending';
    }
    /** Block terminal visits only — raw DB values (e.g. `confirmed`) differ from app display labels (`SCHEDULED`). */
    $nonReschedulable = ['completed', 'cancelled', 'canceled', 'no_show', 'no-show'];
    if (in_array($dbStatus, $nonReschedulable, true)) {
        throw new Exception('This booking cannot be rescheduled because it is completed or cancelled.');
    }

    $dentist_id = patient_booking_resolve_mobile_dentist_choice(
        $pdo,
        $tenant_id,
        (string) $appointment_date,
        (string) $appointment_time,
        $dentist_id_in
    );
    if ($dentist_id <= 0) {
        throw new Exception(
            'No dentist is available for this date and time. Choose another slot or pick a specific dentist.'
        );
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM {$apptQuoted}
         WHERE tenant_id = ?
           AND dentist_id = ?
           AND appointment_date = ?
           AND appointment_time = ?
           AND LOWER(TRIM(COALESCE(status, ''))) NOT IN ('cancelled','canceled')
           AND CAST(id AS CHAR) <> CAST(? AS CHAR)
         LIMIT 1"
    );
    $stmt->execute([$tenant_id, $dentist_id, $appointment_date, $appointment_time, $resolvedId]);
    if ($stmt->fetch()) {
        throw new Exception('This time slot is already reserved. Please choose a different time.');
    }

    $oldNotes = trim((string) ($row['notes'] ?? ''));
    $mergedNotes = $oldNotes;
    if ($notes_in !== '') {
        $tag = '[Patient reschedule] ';
        $mergedNotes = $oldNotes === '' ? ($tag . $notes_in) : ($oldNotes . "\n\n" . $tag . $notes_in);
    }

    $stmt = $pdo->prepare(
        "UPDATE {$apptQuoted}
         SET dentist_id = ?, appointment_date = ?, appointment_time = ?, notes = ?
         WHERE tenant_id = ?
           AND id = ?
           AND patient_id = ?
         LIMIT 1"
    );
    $stmt->execute([
        $dentist_id,
        $appointment_date,
        $appointment_time,
        $mergedNotes,
        $tenant_id,
        $resolvedId,
        $patient_id,
    ]);
    if ($stmt->rowCount() < 1) {
        throw new Exception('Failed to update appointment.');
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'success' => true,
        'message' => 'Appointment rescheduled.',
        'appointment_id' => $resolvedId,
        'booking_id' => $resolvedBookingId !== '' ? $resolvedBookingId : $booking_id_in,
        'appointment_date' => $appointment_date,
        'appointment_time' => $appointment_time,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
