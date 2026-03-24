<?php
/**
 * Audit log created_at values use MySQL DEFAULT CURRENT_TIMESTAMP on clinic connections
 * (typically @@session.time_zone = SYSTEM). They are naive DATETIME in the server's wall clock,
 * not Philippines local. Infer offset once (before SET time_zone on this connection), then convert to Manila.
 */

/**
 * Effective offset of MySQL NOW() vs UTC for this connection (call before SET time_zone).
 */
function auditlogs_infer_mysql_storage_timezone(PDO $pdo): DateTimeZone
{
    try {
        $nowUtcRaw = $pdo->query('SELECT UTC_TIMESTAMP() AS t')->fetchColumn();
        $nowLocalRaw = $pdo->query('SELECT NOW() AS t')->fetchColumn();

        $nowUtcRaw = is_string($nowUtcRaw) ? trim($nowUtcRaw) : '';
        $nowLocalRaw = is_string($nowLocalRaw) ? trim($nowLocalRaw) : '';

        $nowUtcRaw = preg_replace('/\.\d+$/', '', $nowUtcRaw);
        $nowLocalRaw = preg_replace('/\.\d+$/', '', $nowLocalRaw);

        if ($nowUtcRaw !== '' && $nowLocalRaw !== '') {
            $dtUtc = new DateTime($nowUtcRaw, new DateTimeZone('+00:00'));
            $dtLocalAsUtc = new DateTime($nowLocalRaw, new DateTimeZone('+00:00'));
            $offsetSeconds = $dtLocalAsUtc->getTimestamp() - $dtUtc->getTimestamp();

            $abs = abs($offsetSeconds);
            $hours = (int) floor($abs / 3600);
            $mins = (int) floor(($abs % 3600) / 60);
            $sign = $offsetSeconds >= 0 ? '+' : '-';

            return new DateTimeZone($sign . sprintf('%02d:%02d', $hours, $mins));
        }
    } catch (Throwable $e) {
        // ignore
    }

    return new DateTimeZone('+00:00');
}

/**
 * @return array{date:string,time:string}
 */
function auditlogs_format_created_at_manila(string $createdAtRaw, DateTimeZone $storageTz): array
{
    $createdAtRaw = trim($createdAtRaw);
    if ($createdAtRaw === '') {
        return ['date' => '-', 'time' => ''];
    }
    $createdAtRaw = preg_replace('/\.\d+$/', '', $createdAtRaw);

    try {
        $displayTz = new DateTimeZone('Asia/Manila');
    } catch (Throwable $e) {
        $displayTz = new DateTimeZone('+08:00');
    }

    try {
        $dt = new DateTime($createdAtRaw, $storageTz);
        $dt->setTimezone($displayTz);

        return [
            'date' => $dt->format('M d, Y'),
            'time' => $dt->format('H:i:s'),
        ];
    } catch (Throwable $e) {
        $ts = strtotime($createdAtRaw);

        return [
            'date' => $ts ? date('M d, Y', $ts) : '-',
            'time' => $ts ? date('H:i:s', $ts) : '',
        ];
    }
}
