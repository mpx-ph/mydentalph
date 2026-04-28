<?php
declare(strict_types=1);

/**
 * Resolve tenant_id from tbl_users when the client no longer has tenant_id in local storage.
 */
function api_resolve_tenant_id(PDO $pdo, string $userId, ?string $tenantId): ?string
{
    $uid = trim($userId);
    if ($uid === '') {
        return null;
    }

    $tid = trim((string) ($tenantId ?? ''));
    if ($tid !== '') {
        return $tid;
    }

    $st = $pdo->prepare("SELECT tenant_id FROM tbl_users WHERE user_id = ? LIMIT 1");
    $st->execute([$uid]);
    $found = $st->fetchColumn();
    if ($found === false || $found === null) {
        return null;
    }

    $resolved = trim((string) $found);
    return $resolved !== '' ? $resolved : null;
}

/**
 * Prevent stale/cached API payloads on mobile resume.
 */
function api_send_no_cache_headers(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
