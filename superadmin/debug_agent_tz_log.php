<?php
/**
 * Debug session NDJSON logger (agent). Do not log secrets/PII.
 */
function agent_debug_tz_log(string $hypothesisId, string $location, string $message, array $data = []): void
{
    $line = json_encode([
        'sessionId' => '46b972',
        'timestamp' => (int) round(microtime(true) * 1000),
        'hypothesisId' => $hypothesisId,
        'location' => $location,
        'message' => $message,
        'data' => $data,
        'runId' => 'tz-debug-pre',
    ], JSON_UNESCAPED_UNICODE);
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'debug-46b972.log';
    @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
}
