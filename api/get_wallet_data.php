<?php
// api/get_wallet_data.php — household wallets: account holder + dependents share combined balance view
require_once '../db.php';
require_once __DIR__ . '/request_context.inc.php';
header('Content-Type: application/json');
api_send_no_cache_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode([
        "success" => false,
        "message" => "GET required"
    ]));
}

$user_id = trim((string) ($_GET['user_id'] ?? ''));
$tenant_id = trim((string) ($_GET['tenant_id'] ?? ''));

if ($user_id === '') {
    die(json_encode([
        "success" => false,
        "message" => "Missing user_id"
    ]));
}

try {
    $tenant_id = api_resolve_tenant_id($pdo, $user_id, $tenant_id);
    if ($tenant_id === null) {
        die(json_encode([
            "success" => false,
            "message" => "Missing tenant context for this user"
        ]));
    }

    // All patient rows for this login: holder + dependents (same scope as get_appointments.php).
    $stmt = $pdo->prepare("
        SELECT patient_id
        FROM tbl_patients
        WHERE tenant_id = ?
          AND (owner_user_id = ? OR linked_user_id = ?)
    ");
    $stmt->execute([$tenant_id, $user_id, $user_id]);
    $patient_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $patient_ids = array_values(array_unique(array_filter(array_map('strval', $patient_ids), static function ($v) {
        return $v !== '';
    })));

    if (empty($patient_ids)) {
        echo json_encode([
            "success" => true,
            "wallet_balance" => 0.0,
            "combined_balance" => 0.0,
            "wallet_account" => null,
            "wallet_accounts" => [],
            "wallet_transactions" => []
        ]);
        exit;
    }

    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));

    // Primary patient row: logged-in user's linked profile (holder), else any in set.
    $stmt = $pdo->prepare("
        SELECT patient_id
        FROM tbl_patients
        WHERE tenant_id = ?
          AND linked_user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $primary_patient_id = $stmt->fetchColumn();
    if (!$primary_patient_id) {
        $primary_patient_id = $patient_ids[0];
    }
    $primary_patient_id = (string) $primary_patient_id;

    $sql = "
        SELECT
            wa.id,
            wa.tenant_id,
            wa.wallet_id,
            wa.patient_id,
            wa.balance,
            wa.status,
            wa.created_at,
            wa.updated_at,
            TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))) AS patient_display_name
        FROM tbl_wallet_accounts wa
        LEFT JOIN tbl_patients p
            ON p.tenant_id = wa.tenant_id AND p.patient_id = wa.patient_id
        WHERE wa.tenant_id = ?
          AND wa.patient_id IN ($placeholders)
        ORDER BY (wa.patient_id = ?) DESC, wa.patient_id ASC
    ";
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$tenant_id], $patient_ids, [$primary_patient_id]);
    $stmt->execute($params);
    $wallet_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$wallet_rows) {
        echo json_encode([
            "success" => true,
            "wallet_balance" => 0.0,
            "combined_balance" => 0.0,
            "wallet_account" => null,
            "wallet_accounts" => [],
            "wallet_transactions" => []
        ]);
        exit;
    }

    $total = 0.0;
    $wallet_accounts_out = [];
    $wallet_id_list = [];

    foreach ($wallet_rows as $row) {
        $bal = isset($row['balance']) ? (float) $row['balance'] : 0.0;
        $total += $bal;
        $wid = (string) ($row['wallet_id'] ?? '');
        if ($wid !== '') {
            $wallet_id_list[] = $wid;
        }
        $pid = (string) ($row['patient_id'] ?? '');
        $name = trim((string) ($row['patient_display_name'] ?? ''));
        $wallet_accounts_out[] = [
            'wallet_id' => $wid,
            'patient_id' => $pid,
            'balance' => $bal,
            'patient_display_name' => $name,
            'is_primary_patient' => ($pid !== '' && $pid === $primary_patient_id),
        ];
    }

    $wallet_id_list = array_values(array_unique($wallet_id_list));

    $primary_wallet_row = null;
    foreach ($wallet_rows as $row) {
        if ((string) ($row['patient_id'] ?? '') === $primary_patient_id) {
            $primary_wallet_row = $row;
            break;
        }
    }
    if ($primary_wallet_row === null) {
        $primary_wallet_row = $wallet_rows[0];
    }

    // Strip join-only field from legacy wallet_account object
    unset($primary_wallet_row['patient_display_name']);

    $wallet_transactions = [];
    if ($wallet_id_list !== []) {
        $ph = implode(',', array_fill(0, count($wallet_id_list), '?'));
        $stmt = $pdo->prepare("
            SELECT
                id,
                tenant_id,
                wallet_id,
                wallet_transaction_id,
                transaction_type,
                direction,
                amount,
                balance_before,
                balance_after,
                source_payment_id,
                reference_number,
                notes,
                created_by,
                created_at
            FROM tbl_wallet_transactions
            WHERE tenant_id = ?
              AND wallet_id IN ($ph)
            ORDER BY created_at DESC, id DESC
            LIMIT 2000
        ");
        $stmt->execute(array_merge([$tenant_id], $wallet_id_list));
        $wallet_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        "success" => true,
        "wallet_balance" => $total,
        "combined_balance" => $total,
        "wallet_account" => $primary_wallet_row,
        "wallet_accounts" => $wallet_accounts_out,
        "wallet_transactions" => $wallet_transactions
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
