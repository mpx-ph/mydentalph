<?php
if (session_id() === '') {
    session_start();
}
// user_id can be 0 for hardcoded superadmin; empty() treats 0 as empty
if (!isset($_SESSION['user_id'])) {
    $script = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'dashboard.php');
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? '?' . $_SERVER['QUERY_STRING']
        : '';
    header('Location: ../ProviderLogin.php?redirect=' . rawurlencode('superadmin/' . $script . $qs));
    exit;
}

$uid = $_SESSION['user_id'];
$sessionRole = isset($_SESSION['role']) ? (string) $_SESSION['role'] : '';
$dbRole = null;

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../db.php';
    }
    if (isset($pdo) && ($pdo instanceof PDO)) {
        $stmt = $pdo->prepare('SELECT role FROM tbl_users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$uid]);
        $role = $stmt->fetchColumn();
        if ($role !== false) {
            $dbRole = (string) $role;
        }
    }
} catch (Throwable $e) {
    // Fail closed: if we cannot verify role, deny access.
}

$effectiveRole = $dbRole !== null ? $dbRole : $sessionRole;
if ($effectiveRole === 'superadmin') {
    // Keep session role aligned with source of truth.
    $_SESSION['role'] = 'superadmin';
    return;
}

header('Location: ../denied.php');
exit;
