<?php
/**
 * Mobile booking: month/day grid from tbl_clinic_hours, tbl_schedule_blocks, tbl_appointments.
 *
 * GET action=month&tenant_id=&dentist_id=&year=&month=   (month 1-12)
 * GET action=day&tenant_id=&dentist_id=&date=yyyy-mm-dd
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../clinic/includes/patient_booking_slots.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET required']);

    return;
}

$action = trim((string) ($_GET['action'] ?? ''));
$tenantId = trim((string) ($_GET['tenant_id'] ?? ''));
$dentistId = (int) ($_GET['dentist_id'] ?? 0);

if ($tenantId === '' || $dentistId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'tenant_id and dentist_id are required']);

    return;
}

try {
    if ($action === 'month') {
        $year = (int) ($_GET['year'] ?? 0);
        $month = (int) ($_GET['month'] ?? 0);
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid year or month']);

            return;
        }
        $tz = new DateTimeZone('Asia/Manila');
        $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $tz);
        $daysInMonth = (int) $start->format('t');
        $days = [];
        for ($d = 1; $d <= $daysInMonth; ++$d) {
            $dateYmd = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $meta = patient_booking_day_selectable_meta($pdo, $tenantId, $dentistId, $dateYmd);
            $days[$dateYmd] = [
                'selectable' => $meta['ok'],
                'code' => $meta['code'],
            ];
        }
        echo json_encode([
            'success' => true,
            'action' => 'month',
            'tenant_id' => $tenantId,
            'dentist_id' => $dentistId,
            'year' => $year,
            'month' => $month,
            'days' => $days,
        ], JSON_UNESCAPED_SLASHES);

        return;
    }

    if ($action === 'day') {
        $date = trim((string) ($_GET['date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid date']);

            return;
        }
        $slots = patient_booking_slots_for_day($pdo, $tenantId, $dentistId, $date);
        $ch = patient_booking_clinic_hours_for_date($pdo, $tenantId, $date);
        echo json_encode([
            'success' => true,
            'action' => 'day',
            'tenant_id' => $tenantId,
            'dentist_id' => $dentistId,
            'date' => $date,
            'clinic_closed' => $ch['is_closed'],
            'clinic_open' => $ch['open'],
            'clinic_close' => $ch['close'],
            'slots' => $slots,
        ], JSON_UNESCAPED_SLASHES);

        return;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action; use month or day']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
