<?php
/**
 * PayMongo checkout return URL — marks staff-recorded pending payment completed and updates appointment.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/staff_installment_helpers.php';
require_once __DIR__ . '/includes/appointment_db_tables.php';

if (isset($_SESSION['user_role']) && strtolower(trim((string) $_SESSION['user_role'])) === 'dentist') {
    header('Location: StaffDashboard.php');
    exit;
}

$currentTenantSlug = '';
if (isset($_GET['clinic_slug'])) {
    $slug = strtolower(trim((string) $_GET['clinic_slug']));
    if ($slug !== '' && preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $currentTenantSlug = $slug;
    }
}

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$paymentId = trim((string) ($_GET['pid'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));

$redirectBack = 'StaffPaymentRecording.php'
    . ($currentTenantSlug !== '' ? '?clinic_slug=' . urlencode($currentTenantSlug) : '');

if ($tenantId === '' || $paymentId === '' || $token === '') {
    header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
    exit;
}

$stash = $_SESSION['staff_paymongo_checkout'] ?? null;
unset($_SESSION['staff_paymongo_checkout']);

if (
    !is_array($stash)
    || !isset($stash['token'], $stash['payment_id'], $stash['tenant_id'])
    || !hash_equals((string) $stash['token'], $token)
    || (string) $stash['payment_id'] !== $paymentId
    || (string) $stash['tenant_id'] !== $tenantId
) {
    header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
    exit;
}

try {
    $pdo = getDBConnection();

    $payStmt = $pdo->prepare("
        SELECT payment_id, patient_id, booking_id, amount, status
        FROM tbl_payments
        WHERE tenant_id = ?
          AND payment_id = ?
        LIMIT 1
    ");
    $payStmt->execute([$tenantId, $paymentId]);
    $payRow = $payStmt->fetch(PDO::FETCH_ASSOC);
    if (!$payRow || strtolower(trim((string) ($payRow['status'] ?? ''))) !== 'pending') {
        header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
        exit;
    }

    $bookingId = trim((string) ($payRow['booking_id'] ?? ''));
    $patientId = trim((string) ($payRow['patient_id'] ?? ''));
    $amount = (float) ($payRow['amount'] ?? 0);
    if ($bookingId === '' || $amount <= 0) {
        header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
        exit;
    }

    $bookingSql = "
        SELECT
            COALESCE(MAX(a.id), 0) AS appointment_id,
            a.booking_id,
            COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
            COALESCE(SUM(CASE WHEN py.status = 'completed' THEN py.amount ELSE 0 END), 0) AS total_paid
        FROM tbl_appointments a
        LEFT JOIN tbl_payments py
            ON py.tenant_id = a.tenant_id
           AND py.booking_id = a.booking_id
        WHERE a.tenant_id = ?
          AND a.booking_id = ?
        GROUP BY a.booking_id, a.total_treatment_cost
        LIMIT 1
    ";
    $bookingStmt = $pdo->prepare($bookingSql);
    $bookingStmt->execute([$tenantId, $bookingId]);
    $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    if (!$bookingRow) {
        header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
        exit;
    }

    $totalCost = (float) ($bookingRow['total_treatment_cost'] ?? 0);
    $totalPaid = (float) ($bookingRow['total_paid'] ?? 0);
    $pendingBalance = max(0, $totalCost - $totalPaid);
    $appointmentId = (int) ($bookingRow['appointment_id'] ?? 0);

    $storedDate = trim((string) ($stash['payment_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $storedDate)) {
        $storedDate = date('Y-m-d');
    }
    $paymentDateTime = $storedDate . ' ' . date('H:i:s');

    $pdo->beginTransaction();
    try {
        $updPay = $pdo->prepare("
            UPDATE tbl_payments
            SET status = 'completed',
                payment_date = ?
            WHERE tenant_id = ?
              AND payment_id = ?
              AND status = 'pending'
            LIMIT 1
        ");
        $updPay->execute([$paymentDateTime, $tenantId, $paymentId]);
        if ($updPay->rowCount() === 0) {
            throw new RuntimeException('Payment was already updated.');
        }

        $bookingStmt->execute([$tenantId, $bookingId]);
        $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        if ($bookingRow) {
            $totalCost = (float) ($bookingRow['total_treatment_cost'] ?? 0);
            $totalPaid = (float) ($bookingRow['total_paid'] ?? 0);
            $pendingBalance = max(0, $totalCost - $totalPaid);
        }

        $finalize = $stash['installment_finalize'] ?? null;
        if (is_array($finalize) && !empty($finalize['installments_table']) && !empty($finalize['paid_items']) && is_array($finalize['paid_items'])) {
            staff_installments_apply_paid_with_unlocks(
                $pdo,
                $tenantId,
                $bookingId,
                $paymentId,
                (string) $finalize['installments_table'],
                $finalize['paid_items']
            );
        }

        $appointmentUpdatedAtColumnStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_appointments'
              AND COLUMN_NAME = 'updated_at'
            LIMIT 1
        ");
        $appointmentUpdatedAtColumnStmt->execute();
        $supportsAppointmentUpdatedAtColumn = (bool) $appointmentUpdatedAtColumnStmt->fetchColumn();

        $tablesToUpdate = [];
        foreach (['tbl_appointments', 'appointments'] as $candidate) {
            $physical = clinic_get_physical_table_name($pdo, $candidate);
            if ($physical !== null && $physical !== '' && !in_array($physical, $tablesToUpdate, true)) {
                $tablesToUpdate[] = $physical;
            }
        }
        foreach ($tablesToUpdate as $tableName) {
            $updateById = $appointmentId > 0 && strtolower($tableName) === 'tbl_appointments';
            $quotedTable = clinic_quote_identifier($tableName);
            $updateAppointmentSql = "
                UPDATE {$quotedTable}
                SET status = 'completed'" . ($supportsAppointmentUpdatedAtColumn ? ", updated_at = NOW()" : "") . "
                WHERE tenant_id = ?
                  AND " . ($updateById ? "id = ?" : "booking_id = ?") . "
                  AND LOWER(COALESCE(status, 'pending')) NOT IN ('cancelled', 'no_show')
            ";
            $updateAppointmentStmt = $pdo->prepare($updateAppointmentSql);
            $updateAppointmentStmt->execute([$tenantId, $updateById ? $appointmentId : $bookingId]);
        }

        $pdo->commit();
    } catch (Throwable $inner) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $inner;
    }

    header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'payment_success=1');
    exit;
} catch (Throwable $e) {
    error_log('StaffPaymentPayMongoReturn: ' . $e->getMessage());
    header('Location: ' . $redirectBack . (strpos($redirectBack, '?') !== false ? '&' : '?') . 'paymongo_error=1');
    exit;
}
