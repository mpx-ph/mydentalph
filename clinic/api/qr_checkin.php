<?php
declare(strict_types=1);

/**
 * Staff QR / barcode check-in: accepts scanned payload (keyboard wedge), resolves booking_id,
 * validates, sets appointment status to in_progress. Session + tenant required.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/appointment_db_tables.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use POST with JSON body.', 'data' => ['code' => 'method']]);
    exit;
}

$tenantId = requireClinicTenantId();

$staffOk = isLoggedIn(['manager', 'doctor', 'staff', 'admin']);
if (!$staffOk && !empty($_SESSION['user_id'])) {
    $role = strtolower(trim((string) ($_SESSION['user_type'] ?? '')));
    $staffOk = ($role !== '' && $role !== 'client');
}
if (!$staffOk) {
    http_response_code(403);
    jsonResponse(false, 'Staff login required for check-in.', ['code' => 'forbidden']);
}

$rawBody = (string) file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    jsonResponse(false, 'Invalid JSON body.', ['code' => 'invalid_body']);
}

$scanRaw = (string) ($input['scan'] ?? $input['scan_payload'] ?? $input['raw'] ?? '');

/**
 * Extract booking_id from JSON, URL, or BK-YYYY-NNNNNN pattern.
 */
$parseBookingId = static function (string $raw): string {
    $s = trim($raw);
    $s = preg_replace('/\x{FEFF}|\x{200B}|\x{00AD}/u', '', (string) $s);
    $s = trim((string) $s);
    if ($s === '') {
        return '';
    }

    $decoded = json_decode($s, true);
    if (is_array($decoded)) {
        if (!empty($decoded['booking_id'])) {
            $s = trim((string) $decoded['booking_id']);
        } elseif (!empty($decoded['bookingId'])) {
            $s = trim((string) $decoded['bookingId']);
        }
    }

    if (preg_match('/(?:booking_id|bookingId)=([A-Za-z0-9\-]+)/i', $s, $m)) {
        return trim($m[1]);
    }

    if (preg_match('#/(BK-\d{4}-\d{6})#i', $s, $m)) {
        return $m[1];
    }

    if (preg_match('/BK-\d{4}-\d{6}/i', $s, $m)) {
        return $m[0];
    }

    return '';
};

$bookingId = $parseBookingId($scanRaw);
if ($bookingId === '') {
    jsonResponse(false, 'Invalid QR code. Could not read a booking ID.', ['code' => 'invalid_qr']);
}

$manilaToday = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');

try {
    $pdo = getDBConnection();
    $dbTables = clinic_resolve_appointment_db_tables($pdo);
    $tAppt = $dbTables['appointments'];
    $tPat = $dbTables['patients'];
    if ($tAppt === null) {
        throw new RuntimeException('Appointments table is not available.');
    }

    $qAppt = clinic_quote_identifier($tAppt);
    $qPat = $tPat !== null ? clinic_quote_identifier($tPat) : null;

    $patientSql = $qPat !== null
        ? "SELECT a.booking_id, a.appointment_date, a.status, a.patient_id,
                  TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS patient_full_name
           FROM {$qAppt} a
           LEFT JOIN {$qPat} p ON p.tenant_id = a.tenant_id AND p.patient_id = a.patient_id
           WHERE a.tenant_id = ? AND a.booking_id = ?
           LIMIT 1"
        : "SELECT a.booking_id, a.appointment_date, a.status, a.patient_id,
                  '' AS patient_full_name
           FROM {$qAppt} a
           WHERE a.tenant_id = ? AND a.booking_id = ?
           LIMIT 1";

    $stmt = $pdo->prepare($patientSql);
    $stmt->execute([$tenantId, $bookingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonResponse(false, 'No booking found for this code.', ['code' => 'not_found']);
    }

    $apptDate = trim((string) ($row['appointment_date'] ?? ''));
    $rawStatus = (string) ($row['status'] ?? '');
    $rawStatus = preg_replace('/\x{FEFF}|\x{200B}|\x{00AD}/u', '', $rawStatus);
    $rawStatus = preg_replace('/\p{Zs}/u', ' ', (string) $rawStatus);
    $slug = strtolower(str_replace([' ', '-'], '_', trim($rawStatus)));
    if ($slug === 'inprogress' || $slug === 'ongoing') {
        $slug = 'in_progress';
    }
    if ($slug === '' || $slug === '0') {
        $slug = 'pending';
    }
    if (in_array($slug, ['confirmed', 'scheduled'], true)) {
        $slug = 'pending';
    }
    if (!in_array($slug, ['pending', 'in_progress', 'completed', 'cancelled', 'no_show'], true)) {
        $slug = 'pending';
    }

    if ($slug === 'completed') {
        jsonResponse(false, 'This appointment is already completed.', ['code' => 'completed']);
    }
    if ($slug === 'cancelled') {
        jsonResponse(false, 'This appointment was cancelled.', ['code' => 'cancelled']);
    }
    if ($apptDate !== $manilaToday) {
        jsonResponse(false, 'This appointment is not scheduled for today.', ['code' => 'wrong_date']);
    }

    $patientName = trim(preg_replace('/\s+/', ' ', (string) ($row['patient_full_name'] ?? '')));
    if ($patientName === '') {
        $patientName = 'Patient';
    }

    if ($slug !== 'in_progress') {
        clinic_appointments_ensure_in_progress_in_status_enum($pdo, $tAppt);
        $upd = $pdo->prepare("UPDATE {$qAppt} SET status = ? WHERE tenant_id = ? AND booking_id = ? LIMIT 1");
        $upd->execute(['in_progress', $tenantId, $bookingId]);

        $vStmt = $pdo->prepare("SELECT status FROM {$qAppt} WHERE tenant_id = ? AND booking_id = ? LIMIT 1");
        $vStmt->execute([$tenantId, $bookingId]);
        $vRow = $vStmt->fetch(PDO::FETCH_ASSOC);
        $stored = strtolower(str_replace([' ', '-'], '_', trim((string) ($vRow['status'] ?? ''))));
        if ($stored === 'inprogress') {
            $stored = 'in_progress';
        }
        if ($stored !== 'in_progress') {
            jsonResponse(
                false,
                'Could not set status to In Progress. Your database may need the in_progress status value on the appointment status column.',
                ['code' => 'status_save_failed']
            );
        }
    }

    jsonResponse(true, 'Patient checked in.', [
        'booking_id' => $bookingId,
        'patient_name' => $patientName,
        'status' => 'in_progress',
        'status_label' => 'In Progress',
    ]);
} catch (Throwable $e) {
    error_log('qr_checkin: ' . $e->getMessage());
    jsonResponse(false, 'Check-in failed. Please try again.', ['code' => 'server_error']);
}
