<?php
if (session_id() === '') {
    session_start();
}
// user_id can be 0 for hardcoded superadmin; empty() treats 0 as empty
$role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if (!isset($_SESSION['user_id']) || $role !== 'superadmin') {
    $script = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'dashboard.php');
    $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? '?' . $_SERVER['QUERY_STRING']
        : '';
    header('Location: ../ProviderLogin.php?redirect=' . rawurlencode('superadmin/' . $script . $qs));
    exit;
}
