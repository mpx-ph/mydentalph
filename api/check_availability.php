<?php
// api/check_availability.php
require_once '../db.php';
require_once __DIR__ . '/../clinic/includes/patient_booking_slots.php';
require_once __DIR__ . '/../clinic/includes/appointment_db_tables.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(['status' => 'error', 'message' => 'GET required']));
}

$dentist_id = $_GET['dentist_id'] ?? null;
$appointment_date = $_GET['appointment_date'] ?? null;
$appointment_time = $_GET['appointment_time'] ?? null;
$tenant_id = isset($_GET['tenant_id']) ? trim((string) $_GET['tenant_id']) : '';

if (!$dentist_id || !$appointment_date || !$appointment_time) {
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields']));
}

try {
    $dentistIdInt = (int) $dentist_id;
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $aptTable = $tables['appointments'] ?? null;
    if ($aptTable === null) {
        die(json_encode(['status' => 'error', 'message' => 'Appointments table not found']));
    }
    $qApt = clinic_quote_identifier($aptTable);

    $stmt = $pdo->prepare("SELECT id FROM {$qApt}
        WHERE dentist_id = ? AND DATE(appointment_date) = ? AND appointment_time = ?
        AND LOWER(COALESCE(status, '')) <> 'cancelled' LIMIT 1");
    $params = [$dentistIdInt, $appointment_date, $appointment_time];
    if ($tenant_id !== '') {
        $stmt = $pdo->prepare("SELECT id FROM {$qApt}
            WHERE tenant_id = ? AND dentist_id = ? AND DATE(appointment_date) = ? AND appointment_time = ?
            AND LOWER(COALESCE(status, '')) <> 'cancelled' LIMIT 1");
        $params = [$tenant_id, $dentistIdInt, $appointment_date, $appointment_time];
    }
    $stmt->execute($params);

    $exists = $stmt->fetch();
    $available = !$exists;

    if ($available && $tenant_id !== '') {
        $available = patient_booking_slot_available_at_time(
            $pdo,
            $tenant_id,
            $dentistIdInt,
            $appointment_date,
            $appointment_time
        );
    }

    echo json_encode([
        'status' => 'success',
        'available' => $available,
        'message' => $available ? 'Slot is available.' : 'This time slot is not available.',
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}

