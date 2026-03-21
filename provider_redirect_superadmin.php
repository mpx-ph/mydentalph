<?php
/**
 * Include after session_start() on provider marketing / tenant pages.
 * Superadmin accounts use superadmin/* only.
 */
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'superadmin') {
    header('Location: superadmin/dashboard.php');
    exit;
}
