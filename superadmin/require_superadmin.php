<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// user_id can be 0 for hardcoded superadmin; empty() treats 0 as empty
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? 'dashboard.php');
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? '?' . $_SERVER['QUERY_STRING']
        : '';
    header('Location: ../ProviderLogin.php?redirect=' . rawurlencode('superadmin/' . $script . $qs));
    exit;
}
