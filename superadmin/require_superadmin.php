<?php
if (session_id() === '') {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
if ($userId === null || $userId === '') {
    $script = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'dashboard.php');
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? '?' . $_SERVER['QUERY_STRING']
        : '';
    header('Location: ../ProviderLogin.php?redirect=' . rawurlencode('superadmin/' . $script . $qs));
    exit;
}

$dbRole = null;
try {
    require_once __DIR__ . '/../db.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        $stmt = $pdo->prepare('SELECT role FROM tbl_users WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && array_key_exists('role', $row)) {
            $dbRole = trim((string) $row['role']);
        }
    }
} catch (Throwable $e) {
    // Keep null role so access is denied safely below.
}

if ($dbRole === null || $dbRole === '') {
    // Stale/invalid session: treat as unauthenticated.
    $script = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'dashboard.php');
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? '?' . $_SERVER['QUERY_STRING']
        : '';
    header('Location: ../ProviderLogin.php?redirect=' . rawurlencode('superadmin/' . $script . $qs));
    exit;
}

$_SESSION['role'] = $dbRole;
if ($dbRole !== 'superadmin') {
    header('Location: ../denied.php');
    exit;
}
