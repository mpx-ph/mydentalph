<?php
// change_password.php — signed-in user changes password (current + new), tbl_users

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/profile_common.inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json_exit(false, 'POST required');
}

$raw   = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$userId          = isset($input['user_id']) ? trim((string) $input['user_id']) : '';
$tenantId        = isset($input['tenant_id']) ? trim((string) $input['tenant_id']) : '';
$currentPassword = $input['current_password'] ?? $input['current'] ?? '';
$newPassword     = $input['new_password'] ?? $input['new'] ?? '';

if ($userId === '' || $tenantId === '' || $currentPassword === '' || $newPassword === '') {
    api_json_exit(false, 'Missing user_id, tenant_id, current_password, or new_password');
}

if (strlen((string) $newPassword) < 6) {
    api_json_exit(false, 'New password must be at least 6 characters');
}
if ((string) $newPassword === (string) $currentPassword) {
    api_json_exit(false, 'New password must be different from the current password');
}

try {
    $st = $pdo->prepare('SELECT user_id, password_hash, role FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $st->execute([$userId, $tenantId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) {
        api_json_exit(false, 'User not found for this tenant');
    }
    if (strtolower((string) ($u['role'] ?? '')) !== 'client') {
        api_json_exit(false, 'This endpoint is only for patient accounts');
    }
    $hash = (string) ($u['password_hash'] ?? '');
    if ($hash === '' || !password_verify((string) $currentPassword, $hash)) {
        api_json_exit(false, 'Current password is incorrect');
    }

    $newHash = password_hash((string) $newPassword, PASSWORD_DEFAULT);
    $stU = $pdo->prepare('UPDATE tbl_users SET password_hash = ?, updated_at = NOW() WHERE user_id = ? AND tenant_id = ?');
    $stU->execute([$newHash, $userId, $tenantId]);

    api_json_exit(true, 'Password updated successfully.');
} catch (Throwable $e) {
    api_json_exit(false, 'Error: ' . $e->getMessage());
}
