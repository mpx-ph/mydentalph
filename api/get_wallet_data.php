<?php
// api/get_wallet_data.php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode([
        "success" => false,
        "message" => "GET required"
    ]));
}

$user_id = $_GET['user_id'] ?? null;
$tenant_id = $_GET['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    die(json_encode([
        "success" => false,
        "message" => "Missing user_id or tenant_id"
    ]));
}

try {
    // Resolve patient for this logged-in app user.
    $stmt = $pdo->prepare("
        SELECT patient_id
        FROM tbl_patients
        WHERE tenant_id = ?
          AND (owner_user_id = ? OR linked_user_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $user_id, $user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        echo json_encode([
            "success" => true,
            "wallet_account" => null,
            "wallet_transactions" => []
        ]);
        exit;
    }

    $patient_id = $patient['patient_id'];

    // Fetch wallet account for the patient.
    $stmt = $pdo->prepare("
        SELECT
            id,
            tenant_id,
            wallet_id,
            patient_id,
            balance,
            status,
            created_at,
            updated_at
        FROM tbl_wallet_accounts
        WHERE tenant_id = ?
          AND patient_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $patient_id]);
    $wallet_account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$wallet_account) {
        echo json_encode([
            "success" => true,
            "wallet_account" => null,
            "wallet_transactions" => []
        ]);
        exit;
    }

    $wallet_id = $wallet_account['wallet_id'];

    // Fetch latest wallet transactions for this account.
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
          AND wallet_id = ?
        ORDER BY created_at DESC, id DESC
    ");
    $stmt->execute([$tenant_id, $wallet_id]);
    $wallet_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "wallet_account" => $wallet_account,
        "wallet_transactions" => $wallet_transactions
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}

