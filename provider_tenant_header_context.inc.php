<?php
declare(strict_types=1);

/**
 * Resolves variables for provider_tenant_top_header.inc.php and profile modal.
 * Expects: $pdo (PDO), $tenant_id, $user_id. Optionally pre-set: $clinic_display_name,
 * $clinic_name, $user_email_display, $profile_first_name, $profile_last_name, $display_name.
 */
$pdoHdr = $GLOBALS['pdo'] ?? null;
if (!($pdoHdr instanceof PDO)) {
    return;
}
$tIdHdr = isset($tenant_id) ? trim((string) $tenant_id) : '';
$uIdHdr = isset($user_id) ? trim((string) $user_id) : '';
if ($tIdHdr === '' || $uIdHdr === '') {
    return;
}

if (!isset($clinic_display_name) || trim((string) $clinic_display_name) === '') {
    $clinic_display_name = '';
    if (isset($clinic_name) && trim((string) $clinic_name) !== '') {
        $clinic_display_name = trim((string) $clinic_name);
    } else {
        try {
            $st = $pdoHdr->prepare('SELECT clinic_name FROM tbl_tenants WHERE tenant_id = ? LIMIT 1');
            $st->execute([$tIdHdr]);
            $cn = $st->fetchColumn();
            if ($cn !== false && $cn !== null) {
                $clinic_display_name = trim((string) $cn);
            }
        } catch (Throwable $e) {
        }
    }
    if ($clinic_display_name === '') {
        $clinic_display_name = 'My Clinic';
    }
}

if (!isset($user_email_display)) {
    $user_email_display = '';
}
if (!isset($profile_first_name)) {
    $profile_first_name = '';
}
if (!isset($profile_last_name)) {
    $profile_last_name = '';
}

$needUserRow = (trim((string) $user_email_display) === '')
    || ($profile_first_name === '' && $profile_last_name === '');
if ($needUserRow) {
    try {
        $st = $pdoHdr->prepare('SELECT full_name, email FROM tbl_users WHERE user_id = ? LIMIT 1');
        $st->execute([$uIdHdr]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($u)) {
            if (trim((string) $user_email_display) === '') {
                $user_email_display = trim((string) ($u['email'] ?? ''));
            }
            if ($profile_first_name === '' && $profile_last_name === '') {
                $fn = trim((string) ($u['full_name'] ?? ''));
                if ($fn !== '') {
                    $parts = preg_split('/\s+/', $fn, 2, PREG_SPLIT_NO_EMPTY);
                    $profile_first_name = (string) ($parts[0] ?? '');
                    $profile_last_name = (string) ($parts[1] ?? '');
                }
            }
        }
    } catch (Throwable $e) {
    }
}
