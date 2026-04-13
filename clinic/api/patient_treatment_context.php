<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/appointment_db_tables.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Use GET.']);
    exit;
}

$tenantId = requireClinicTenantId();
if (!isLoggedIn(['manager', 'doctor', 'staff', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff login required.']);
    exit;
}

$patientId = trim((string) ($_GET['patient_id'] ?? ''));
if ($patientId === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'patient_id is required.']);
    exit;
}

try {
    $pdo = getDBConnection();
    $dbTables = clinic_resolve_appointment_db_tables($pdo);
    $treatmentsTable = $dbTables['treatments'];
    $servicesTable = $dbTables['services'];
    if ($treatmentsTable === null || $servicesTable === null) {
        echo json_encode([
            'success' => true,
            'message' => 'No treatment table available.',
            'data' => ['has_active_treatment' => false],
        ]);
        exit;
    }

    $qt = clinic_quote_identifier($treatmentsTable);
    $qs = clinic_quote_identifier($servicesTable);

    $stmt = $pdo->prepare("
        SELECT
            t.treatment_id,
            t.patient_id,
            t.primary_service_id,
            t.primary_service_name,
            COALESCE(t.total_cost, 0) AS total_cost,
            COALESCE(t.amount_paid, 0) AS amount_paid,
            COALESCE(t.remaining_balance, 0) AS remaining_balance,
            COALESCE(t.duration_months, 0) AS duration_months,
            COALESCE(t.months_paid, 0) AS months_paid,
            COALESCE(t.months_left, 0) AS months_left,
            COALESCE(t.status, 'active') AS status,
            t.started_at
        FROM {$qt} t
        WHERE t.tenant_id = ?
          AND t.patient_id = ?
          AND LOWER(COALESCE(t.status, 'active')) = 'active'
        ORDER BY t.started_at DESC, t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $patientId]);
    $treatment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$treatment) {
        echo json_encode([
            'success' => true,
            'message' => 'No active installment treatment found.',
            'data' => ['has_active_treatment' => false],
        ]);
        exit;
    }

    $primaryServiceId = (string) ($treatment['primary_service_id'] ?? '');
    $serviceStmt = $pdo->prepare("
        SELECT service_id, service_name, category, price, enable_installment,
               installment_duration_months, installment_downpayment
        FROM {$qs}
        WHERE tenant_id = ? AND service_id = ?
        LIMIT 1
    ");
    $serviceStmt->execute([$tenantId, $primaryServiceId]);
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $totalCost = (float) ($treatment['total_cost'] ?? 0);
    $amountPaid = (float) ($treatment['amount_paid'] ?? 0);
    $remainingBalance = (float) ($treatment['remaining_balance'] ?? 0);
    if ($totalCost > 0 && $remainingBalance < 0) {
        $remainingBalance = 0.0;
    }
    $progressPct = $totalCost > 0 ? min(100.0, max(0.0, ($amountPaid / $totalCost) * 100.0)) : 0.0;

    echo json_encode([
        'success' => true,
        'message' => 'Treatment context loaded.',
        'data' => [
            'has_active_treatment' => true,
            'treatment' => [
                'treatment_id' => (string) ($treatment['treatment_id'] ?? ''),
                'patient_id' => (string) ($treatment['patient_id'] ?? ''),
                'status' => (string) ($treatment['status'] ?? 'active'),
                'total_cost' => round($totalCost, 2),
                'amount_paid' => round($amountPaid, 2),
                'remaining_balance' => round($remainingBalance, 2),
                'duration_months' => (int) ($treatment['duration_months'] ?? 0),
                'months_paid' => (int) ($treatment['months_paid'] ?? 0),
                'months_left' => (int) ($treatment['months_left'] ?? 0),
                'progress_percentage' => round($progressPct, 2),
                'started_at' => (string) ($treatment['started_at'] ?? ''),
                'primary_service' => $service,
            ],
        ],
    ]);
    exit;
} catch (Throwable $e) {
    error_log('patient_treatment_context error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load treatment context.',
    ]);
    exit;
}
