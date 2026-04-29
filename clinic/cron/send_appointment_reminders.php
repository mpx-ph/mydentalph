<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/appointment_reminder_service.php';

try {
    $pdo = getDBConnection();
    $tenantId = null;

    if (PHP_SAPI === 'cli' && !empty($argv[1])) {
        $tenantId = trim((string) $argv[1]);
    } elseif (!empty($_GET['tenant_id'])) {
        $tenantId = trim((string) $_GET['tenant_id']);
    }

    $result = send_scheduled_appointment_reminders($pdo, $tenantId !== '' ? $tenantId : null);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'checked' => 0,
        'sent' => ['3day' => 0, '1day' => 0, 'final' => 0],
        'errors' => ['Fatal reminder runner error: ' . $e->getMessage()],
        'schedule_mode' => '3h_1h_30m',
    ];
}

try {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $logLine = sprintf(
        "[%s] appointment_reminder_runner %s\n",
        date('Y-m-d H:i:s'),
        json_encode($result, JSON_UNESCAPED_SLASHES)
    );
    @file_put_contents($logDir . '/appointment_reminders.log', $logLine, FILE_APPEND);
} catch (Throwable $logError) {
    error_log('Appointment reminder log write failed: ' . $logError->getMessage());
}

if (PHP_SAPI === 'cli') {
    echo '[Appointment reminders] ';
    echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($result['ok'] ? 0 : 1);
}

header('Content-Type: application/json');
echo json_encode($result);
