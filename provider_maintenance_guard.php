<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin/superadmin_settings_lib.php';

if (!function_exists('provider_is_maintenance_mode_enabled')) {
    function provider_is_maintenance_mode_enabled(PDO $pdo): bool
    {
        try {
            $settings = superadmin_get_settings($pdo);
            return !empty($settings['provider_maintenance_mode']);
        } catch (Throwable $e) {
            error_log('provider maintenance guard read failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (provider_is_maintenance_mode_enabled($pdo)) {
    $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $currentFile = is_string($currentPath) ? basename($currentPath) : '';
    if (strtolower($currentFile) !== 'undermaintenance.php') {
        header('Location: /undermaintenance.php');
        exit;
    }
}
