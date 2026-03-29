<?php
/**
 * JSON API: update logged-in provider account (name, email, optional password).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
require_once __DIR__ . '/db.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database is not available.']);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.']);
    exit;
}

$user_id = (string) $_SESSION['user_id'];
$tenant_id = (string) $_SESSION['tenant_id'];

try {
    $status = provider_get_verification_request_status($pdo, $tenant_id, $user_id);
} catch (Throwable $e) {
    $status = null;
}
if ($status !== 'approved') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

$st = $pdo->prepare("SELECT 1 FROM tbl_users WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1");
$st->execute([$user_id, $tenant_id]);
if (!(bool) $st->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request body.']);
    exit;
}

$full_name = trim((string) ($data['full_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$current_password = (string) ($data['current_password'] ?? '');
$new_password = (string) ($data['new_password'] ?? '');
$new_password_confirm = (string) ($data['new_password_confirm'] ?? '');

if ($full_name === '') {
    echo json_encode(['ok' => false, 'error' => 'Name is required.']);
    exit;
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'error' => 'A valid email is required.']);
    exit;
}

$dup = $pdo->prepare("SELECT user_id FROM tbl_users WHERE tenant_id = ? AND LOWER(TRIM(email)) = LOWER(TRIM(?)) AND user_id != ? LIMIT 1");
$dup->execute([$tenant_id, $email, $user_id]);
if ($dup->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'That email is already used by another user in your clinic.']);
    exit;
}

$want_pw_change = $new_password !== '' || $new_password_confirm !== '';
if ($want_pw_change) {
    if ($current_password === '') {
        echo json_encode(['ok' => false, 'error' => 'Enter your current password to set a new one.']);
        exit;
    }
    if ($new_password === '') {
        echo json_encode(['ok' => false, 'error' => 'Enter a new password.']);
        exit;
    }
    if (strlen($new_password) < 8) {
        echo json_encode(['ok' => false, 'error' => 'New password must be at least 8 characters.']);
        exit;
    }
    if ($new_password !== $new_password_confirm) {
        echo json_encode(['ok' => false, 'error' => 'New password and confirmation do not match.']);
        exit;
    }
}

$hashStmt = $pdo->prepare("SELECT password_hash FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1");
$hashStmt->execute([$user_id, $tenant_id]);
$row = $hashStmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Account not found.']);
    exit;
}

if ($want_pw_change) {
    $stored = (string) ($row['password_hash'] ?? '');
    if ($stored === '' || !password_verify($current_password, $stored)) {
        echo json_encode(['ok' => false, 'error' => 'Current password is incorrect.']);
        exit;
    }
}

try {
    if ($want_pw_change) {
        $newHash = password_hash($new_password, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE tbl_users SET full_name = ?, email = ?, password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND tenant_id = ? LIMIT 1");
        $up->execute([$full_name, $email, $newHash, $user_id, $tenant_id]);
    } else {
        $up = $pdo->prepare("UPDATE tbl_users SET full_name = ?, email = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ? AND tenant_id = ? LIMIT 1");
        $up->execute([$full_name, $email, $user_id, $tenant_id]);
    }
} catch (Throwable $e) {
    error_log('ProviderTenantAccountUpdate: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save changes. Please try again.']);
    exit;
}

$_SESSION['full_name'] = $full_name;
$_SESSION['email'] = $email;
$_SESSION['name'] = $full_name;

echo json_encode([
    'ok' => true,
    'message' => 'Your account was updated.',
    'full_name' => $full_name,
    'email' => $email,
]);
