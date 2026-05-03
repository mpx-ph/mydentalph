<?php
// api/pay_outstanding_bill.php — consolidated balance or plan installment via PayMongo
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../paymongo_config.php';
require_once __DIR__ . '/../clinic/includes/appointment_db_tables.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['status' => 'error', 'message' => 'POST required']));
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$user_id = $input['user_id'] ?? null;
$tenant_id = trim((string) ($input['tenant_id'] ?? ''));
$booking_id = trim((string) ($input['booking_id'] ?? ''));
$payment_amount = isset($input['payment_amount']) ? (float) $input['payment_amount'] : 0.0;

$treatment_id = trim((string) ($input['treatment_id'] ?? ''));
$installment_number = (int) ($input['installment_number'] ?? 0);

/** Plan installments rarely appear as rows in get_outstanding_bills; use downpayment semantics for months_paid increments. */
$payment_type_row = ($installment_number >= 1) ? 'downpayment' : 'fullpayment';

if (!$user_id || $tenant_id === '' || $booking_id === '' || $payment_amount <= 0.0) {
    die(json_encode(['status' => 'error', 'message' => 'Missing required fields or invalid amount']));
}

$paymongo_secret = defined('PAYMONGO_SECRET_KEY') ? (string) PAYMONGO_SECRET_KEY : '';
if ($paymongo_secret === '') {
    die(json_encode(['status' => 'error', 'message' => 'PayMongo Secret Key not configured.']));
}

try {
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $paymentsPhys = $tables['payments'] ?? 'tbl_payments';
    $pq = clinic_quote_identifier((string) $paymentsPhys);
    $ppc = clinic_table_columns($pdo, (string) $paymentsPhys);

    // 1. Patient
    $stmt = $pdo->prepare(
        "SELECT p.patient_id, p.first_name, p.last_name, u.email, u.phone 
        FROM tbl_patients p
        LEFT JOIN tbl_users u ON u.user_id = ?
        WHERE (p.owner_user_id = ? OR p.linked_user_id = ?) AND p.tenant_id = ?
        LIMIT 1"
    );
    $stmt->execute([$user_id, $user_id, $user_id, $tenant_id]);
    $patRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patRow) {
        throw new Exception('Patient profile not found.');
    }

    $patient_id = trim((string) ($patRow['patient_id'] ?? ''));
    $patient_name = trim(trim((string) ($patRow['first_name'] ?? '')) . ' ' . trim((string) ($patRow['last_name'] ?? '')));
    $user_email = !empty($patRow['email']) ? trim((string) $patRow['email']) : 'patient@mydentalph.com';
    $user_phone = trim((string) ($patRow['phone'] ?? ''));

    // 2. Pending reuse
    $stmt = $pdo->prepare(
        "SELECT payment_id FROM {$pq}
         WHERE tenant_id = ? AND booking_id = ? AND LOWER(TRIM(COALESCE(status, ''))) = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$tenant_id, $booking_id]);
    $existingPending = $stmt->fetch(PDO::FETCH_ASSOC);

    $buildInsertSql = static function (
        PDO $pdo,
        string $pq,
        array $ppc,
        string $tenantId,
        string $payment_id,
        string $patient_id,
        string $booking_id,
        float $payment_amount,
        $user_id,
        string $payment_type_db,
        string $treatment_id,
        int $installment_number
    ): void {
        $map = [
            'tenant_id' => $tenantId,
            'payment_id' => $payment_id,
            'patient_id' => $patient_id,
            'booking_id' => $booking_id,
            'amount' => $payment_amount,
            'payment_method' => 'gcash',
            'status' => 'pending',
            'created_by' => (string) $user_id,
            'payment_type' => $payment_type_db,
        ];
        if ($treatment_id !== '' && in_array('treatment_id', $ppc, true)) {
            $map['treatment_id'] = $treatment_id;
        }
        if ($installment_number >= 1 && in_array('installment_number', $ppc, true)) {
            $map['installment_number'] = $installment_number;
        }
        $colParts = [];
        $ph = [];
        $bind = [];
        foreach ($map as $nm => $val) {
            $lc = strtolower((string) $nm);
            if (!in_array($lc, $ppc, true)) {
                continue;
            }
            $colParts[] = clinic_quote_identifier($lc);
            $ph[] = '?';
            $bind[] = $val;
        }
        if ($colParts === []) {
            throw new Exception('Payments table incompatible with mobile insert.');
        }
        $sql = 'INSERT INTO ' . $pq . ' (' . implode(', ', $colParts) . ') VALUES (' . implode(', ', $ph) . ')';
        $pst = $pdo->prepare($sql);
        $pst->execute($bind);
    };

    if ($existingPending && !empty($existingPending['payment_id'])) {
        $payment_id = (string) $existingPending['payment_id'];
        $set = ['amount = ?', "payment_method = 'gcash'", 'patient_id = ?', 'created_by = ?'];
        $params = [$payment_amount, $patient_id, $user_id];
        if (in_array('payment_type', $ppc, true)) {
            $set[] = 'payment_type = ?';
            $params[] = $payment_type_row;
        }
        if ($treatment_id !== '' && in_array('treatment_id', $ppc, true)) {
            $set[] = 'treatment_id = ?';
            $params[] = $treatment_id;
        }
        if ($installment_number >= 1 && in_array('installment_number', $ppc, true)) {
            $set[] = 'installment_number = ?';
            $params[] = $installment_number;
        }
        $params[] = $payment_id;
        $sql = 'UPDATE ' . $pq . ' SET ' . implode(', ', $set) . ' WHERE payment_id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmt = $pdo->prepare(
            "UPDATE {$pq} SET status = 'cancelled'
             WHERE tenant_id = ? AND booking_id = ?
               AND LOWER(TRIM(COALESCE(status, ''))) = 'pending' AND payment_id <> ?"
        );
        $stmt->execute([$tenant_id, $booking_id, $payment_id]);
    } else {
        $payment_id = 'PAY-' . strtoupper(substr(md5((string) microtime(true) . $booking_id . mt_rand()), 0, 10));
        $buildInsertSql($pdo, $pq, $ppc, $tenant_id, $payment_id, $patient_id, $booking_id, $payment_amount, $user_id, $payment_type_row, $treatment_id, $installment_number);
    }

    $scheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
    $baseApi = ($host !== '' ? "{$scheme}://{$host}" : 'http://mydentalph.ct.ws') . '/api';

    $description = ($installment_number >= 1)
        ? "Plan installment #$installment_number for $booking_id"
        : "Settling outstanding balance for $booking_id";

    $amount_in_cents = (int) round($payment_amount * 100);

    $paymongo_data = [
        'data' => [
            'attributes' => [
                'send_email_receipt' => false,
                'show_description' => true,
                'show_line_items' => true,
                'payment_method_types' => ['gcash', 'paymaya', 'card'],
                'description' => "{$description} - {$patient_name}",
                'success_url' => $baseApi . '/payment_success.php?pid=' . rawurlencode($payment_id),
                'billing' => [
                    'name' => $patient_name,
                    'email' => $user_email,
                    'phone' => $user_phone,
                ],
                'line_items' => [
                    [
                        'currency' => 'PHP',
                        'amount' => $amount_in_cents,
                        'description' => ($installment_number >= 1) ? 'Treatment plan installment' : 'Remaining balance',
                        'name' => ($installment_number >= 1) ? 'Plan installment' : 'Outstanding Bill',
                        'quantity' => 1,
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymongo_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode($paymongo_secret . ':'),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception('Network error connecting to payment gateway: ' . $curlError);
    }

    $responseData = json_decode((string) $response, true);

    if (
        $httpCode >= 200
        && $httpCode < 300
        && is_array($responseData)
        && isset($responseData['data']['attributes']['checkout_url'])
    ) {
        $checkout_url = $responseData['data']['attributes']['checkout_url'];
        $paymongo_reference_number = (string) $responseData['data']['id'];

        $stmt = $pdo->prepare("UPDATE {$pq} SET reference_number = ? WHERE payment_id = ?");
        $stmt->execute([$paymongo_reference_number, $payment_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Payment link generated',
            'checkout_url' => $checkout_url,
        ]);
        exit;
    }

    $paymongoErrorMsg = (is_array($responseData) && isset($responseData['errors'][0]['detail']))
        ? (string) $responseData['errors'][0]['detail']
        : 'Unknown Error';
    throw new Exception('PayMongo Error: ' . $paymongoErrorMsg);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
