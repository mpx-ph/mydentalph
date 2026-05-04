<?php
// api/cancel_appointment.php
require_once '../db.php';
require_once __DIR__ . '/includes/refund_requests_schema.inc.php';
require_once __DIR__ . '/includes/booking_cancel_wallet.inc.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'success' => false, 'message' => 'POST required']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$appointment_id = $input['appointment_id'] ?? null;
$booking_id = $input['booking_id'] ?? null;
$tenant_id = $input['tenant_id'] ?? null;
$user_id = $input['user_id'] ?? null;
$notes = trim((string) ($input['notes'] ?? ''));

$action = strtolower(trim((string) ($input['action'] ?? 'cancel')));
$requestRefundOnly = $action === 'request_refund' || !empty($input['request_refund']);

if (!$tenant_id || !$user_id || (!$appointment_id && !$booking_id)) {
    die(json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Missing required fields: tenant_id, user_id, and appointment_id or booking_id',
    ]));
}

try {
    // DDL (CREATE TABLE) implicitly commits in MySQL — must run before beginTransaction().
    refund_requests_ensure_table($pdo);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        'SELECT patient_id
         FROM tbl_patients
         WHERE tenant_id = ?
           AND (owner_user_id = ? OR linked_user_id = ?)
         LIMIT 1'
    );
    $stmt->execute([$tenant_id, $user_id, $user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        throw new Exception('Patient record not found for this user');
    }

    $patient_id = $patient['patient_id'];

    if ($appointment_id) {
        $stmt = $pdo->prepare(
            'SELECT id, booking_id, status
             FROM tbl_appointments
             WHERE tenant_id = ?
               AND id = ?
               AND patient_id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$tenant_id, $appointment_id, $patient_id]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, booking_id, status
             FROM tbl_appointments
             WHERE tenant_id = ?
               AND booking_id = ?
               AND patient_id = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$tenant_id, $booking_id, $patient_id]);
    }

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appointment) {
        throw new Exception('Appointment not found or not allowed to cancel');
    }

    $resolvedAppointmentId = (int) $appointment['id'];
    $resolvedBookingId = (string) $appointment['booking_id'];
    $dbStatusLower = strtolower((string) ($appointment['status'] ?? ''));

    if ($requestRefundOnly) {
        $allowedForRequest = ['pending', 'confirmed', 'scheduled'];
        if (!in_array($dbStatusLower, $allowedForRequest, true)) {
            throw new Exception('This booking cannot request cancel & refund in its current state.');
        }

        $dupStmt = $pdo->prepare(
            "SELECT id FROM tbl_refund_requests
             WHERE tenant_id = ?
               AND appointment_id = ?
               AND status = 'pending'
             LIMIT 1"
        );
        $dupStmt->execute([$tenant_id, $resolvedAppointmentId]);
        if ($dupStmt->fetchColumn()) {
            $pdo->commit();
            echo json_encode([
                'status' => 'success',
                'success' => true,
                'message' => 'Refund request already submitted.',
            ]);
            exit;
        }

        $reasonNote = $notes !== '' ? $notes : 'Patient requested cancel & refund.';
        $ins = $pdo->prepare(
            'INSERT INTO tbl_refund_requests (
                tenant_id, appointment_id, booking_id, patient_id, requester_user_id, reason, status
            ) VALUES (?, ?, ?, ?, ?, ?, \'pending\')'
        );
        $ins->execute([
            $tenant_id,
            $resolvedAppointmentId,
            $resolvedBookingId,
            $patient_id,
            $user_id,
            $reasonNote,
        ]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'success' => true,
            'message' => 'Refund request submitted. Clinic staff will review it shortly.',
        ]);
        exit;
    }

    $result = booking_perform_full_cancellation(
        $pdo,
        (string) $tenant_id,
        (string) $patient_id,
        $resolvedAppointmentId,
        $resolvedBookingId,
        (string) ($appointment['status'] ?? ''),
        $notes,
        (string) $user_id
    );

    if ($result['already_cancelled']) {
        $pdo->commit();
        echo json_encode([
            'status' => 'success',
            'success' => true,
            'message' => 'Booking already cancelled',
            'refunded_amount' => '0.00',
        ]);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'success' => true,
        'message' => 'Booking cancelled',
        'refunded_amount' => number_format((float) $result['refunded_amount'], 2, '.', ''),
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error',
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}
