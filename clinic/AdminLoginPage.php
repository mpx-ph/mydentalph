<?php
/**
 * Legacy entrypoint retained for backward compatibility.
 * Admin/staff login has been consolidated to Login.php.
 */

require_once __DIR__ . '/config/config.php';

$qs = $_GET;
unset($qs['clinic_slug']);

$clinicSlug = isset($_GET['clinic_slug']) ? strtolower(trim((string) $_GET['clinic_slug'])) : '';
if ($clinicSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $clinicSlug)) {
    $target = rtrim(PROVIDER_BASE_URL, '/') . '/' . rawurlencode($clinicSlug) . '/login';
} else {
    $target = BASE_URL . 'Login.php';
}

if (!empty($qs)) {
    $target .= (strpos($target, '?') === false ? '?' : '&') . http_build_query($qs);
}

header('Location: ' . $target, true, 302);
exit;
