<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
if ($tenantId !== '') {
    try {
        $ipAddress = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = trim((string) explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = trim((string) $_SERVER['REMOTE_ADDR']);
        }
        $stmt = $pdo->prepare("
            INSERT INTO tbl_audit_logs (tenant_id, user_id, action, description, ip_address)
            VALUES (?, ?, 'LOGOUT', 'Provider user logged out.', ?)
        ");
        $stmt->execute([
            $tenantId,
            $userId !== '' ? $userId : null,
            $ipAddress !== '' ? $ipAddress : null
        ]);
    } catch (Throwable $e) {
        error_log('Provider logout audit log write failed: ' . $e->getMessage());
    }
}

// Clear all session data (login + onboarding)
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

header('Location: ProviderMain.php');
exit;
