<?php
/**
 * Resolves canonical tenant_id for the logged-in provider and sets $_SESSION['is_owner'].
 * Expects: $pdo (PDO), $user_id (string), $_SESSION['tenant_id'].
 * Sets: $tenant_id (string), $is_owner (bool).
 */
declare(strict_types=1);

if (!function_exists('provider_dashboard_tenant_has_billing_assets')) {
    function provider_dashboard_tenant_has_billing_assets(PDO $pdo, string $tid): bool
    {
        $tid = trim($tid);
        if ($tid === '') {
            return false;
        }
        try {
            $s = $pdo->prepare('SELECT 1 FROM tbl_tenant_subscriptions WHERE tenant_id = ? LIMIT 1');
            $s->execute([$tid]);
            if ($s->fetchColumn()) {
                return true;
            }
        } catch (Throwable $e) {
        }
        try {
            $s = $pdo->prepare("SELECT TRIM(COALESCE(clinic_slug, '')) AS s FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
            $s->execute([$tid]);
            $slug = $s->fetchColumn();
            return $slug !== false && trim((string) $slug) !== '';
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('provider_dashboard_resolve_tenant_id_for_user')) {
    /**
     * Tenant that actually has billing/website data for this login — does not depend on session tenant_id alone.
     * Matches subscription rows linked via tbl_tenants.owner_user_id OR tbl_users.tenant_id.
     */
    function provider_dashboard_resolve_tenant_id_for_user(PDO $pdo, string $user_id, string $session_tid): string
    {
        $session_tid = trim($session_tid);
        $user_id = trim($user_id);
        if ($user_id === '') {
            return $session_tid;
        }
        $best_tid = '';
        $best_sub_id = -1;
        try {
            $st = $pdo->prepare('
                SELECT ts.tenant_id, ts.id AS sid
                FROM tbl_tenant_subscriptions ts
                INNER JOIN tbl_tenants t ON t.tenant_id = ts.tenant_id AND t.owner_user_id = ?
                ORDER BY ts.id DESC
                LIMIT 1
            ');
            $st->execute([$user_id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($r) && isset($r['sid'], $r['tenant_id'])) {
                $sid = (int) $r['sid'];
                if ($sid > $best_sub_id) {
                    $best_sub_id = $sid;
                    $best_tid = trim((string) $r['tenant_id']);
                }
            }
        } catch (Throwable $e) {
        }
        try {
            $st = $pdo->prepare('
                SELECT ts.tenant_id, ts.id AS sid
                FROM tbl_tenant_subscriptions ts
                INNER JOIN tbl_users u ON u.user_id = ? AND u.tenant_id = ts.tenant_id
                ORDER BY ts.id DESC
                LIMIT 1
            ');
            $st->execute([$user_id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if (is_array($r) && isset($r['sid'], $r['tenant_id'])) {
                $sid = (int) $r['sid'];
                if ($sid > $best_sub_id) {
                    $best_sub_id = $sid;
                    $best_tid = trim((string) $r['tenant_id']);
                }
            }
        } catch (Throwable $e) {
        }
        if ($best_tid !== '') {
            return $best_tid;
        }

        try {
            $st = $pdo->prepare("
                SELECT t.tenant_id
                FROM tbl_tenants t
                WHERE t.owner_user_id = ?
                  AND TRIM(COALESCE(t.clinic_slug, '')) <> ''
                ORDER BY t.tenant_id DESC
                LIMIT 1
            ");
            $st->execute([$user_id]);
            $slugTenant = $st->fetchColumn();
            if ($slugTenant !== false && trim((string) $slugTenant) !== '') {
                return trim((string) $slugTenant);
            }
        } catch (Throwable $e) {
        }

        try {
            $st = $pdo->prepare('SELECT tenant_id FROM tbl_tenants WHERE owner_user_id = ? ORDER BY tenant_id DESC LIMIT 1');
            $st->execute([$user_id]);
            $ownerRow = $st->fetchColumn();
            if ($ownerRow !== false && trim((string) $ownerRow) !== '') {
                return trim((string) $ownerRow);
            }
        } catch (Throwable $e) {
        }

        try {
            $st = $pdo->prepare('SELECT tenant_id FROM tbl_users WHERE user_id = ? LIMIT 1');
            $st->execute([$user_id]);
            $ut = $st->fetchColumn();
            if ($ut !== false && trim((string) $ut) !== '') {
                return trim((string) $ut);
            }
        } catch (Throwable $e) {
        }

        return $session_tid;
    }
}

$user_role = (string) ($_SESSION['role'] ?? '');

// Canonical tenant_id: DB truth from subscriptions / ownership, then align session + tbl_users.
$session_tid = trim((string) $_SESSION['tenant_id']);
$tenant_id = provider_dashboard_resolve_tenant_id_for_user($pdo, $user_id, $session_tid);

if ($tenant_id !== $session_tid) {
    $_SESSION['tenant_id'] = $tenant_id;
    try {
        $repairStmt = $pdo->prepare('UPDATE tbl_users SET tenant_id = ? WHERE user_id = ?');
        $repairStmt->execute([$tenant_id, $user_id]);
    } catch (Throwable $e) {
    }
}

if ($user_role === 'tenant_owner') {
    try {
        $stmt = $pdo->prepare('SELECT tenant_id FROM tbl_tenants WHERE owner_user_id = ? ORDER BY tenant_id DESC LIMIT 1');
        $stmt->execute([$user_id]);
        $ownerTenantId = $stmt->fetchColumn();
        $ownerTenantId = ($ownerTenantId !== false) ? trim((string) $ownerTenantId) : '';

        if ($ownerTenantId !== '' && $ownerTenantId !== $tenant_id) {
            $current_assets = provider_dashboard_tenant_has_billing_assets($pdo, $tenant_id);
            $owner_assets = provider_dashboard_tenant_has_billing_assets($pdo, $ownerTenantId);
            if (!$current_assets && $owner_assets) {
                $tenant_id = $ownerTenantId;
                $_SESSION['tenant_id'] = $tenant_id;
                try {
                    $repairStmt = $pdo->prepare('UPDATE tbl_users SET tenant_id = ? WHERE user_id = ?');
                    $repairStmt->execute([$tenant_id, $user_id]);
                } catch (Throwable $e) {
                }
            }
        }
    } catch (Throwable $e) {
    }
}

if (!provider_dashboard_tenant_has_billing_assets($pdo, $tenant_id)) {
    $user_row_tid = '';
    try {
        $uStmt = $pdo->prepare('SELECT tenant_id FROM tbl_users WHERE user_id = ? LIMIT 1');
        $uStmt->execute([$user_id]);
        $c = $uStmt->fetchColumn();
        $user_row_tid = ($c !== false) ? trim((string) $c) : '';
    } catch (Throwable $e) {
    }
    foreach (array_unique(array_filter([$session_tid, $user_row_tid])) as $alt_tid) {
        if ($alt_tid === '' || $alt_tid === $tenant_id) {
            continue;
        }
        if (provider_dashboard_tenant_has_billing_assets($pdo, $alt_tid)) {
            $tenant_id = $alt_tid;
            $_SESSION['tenant_id'] = $tenant_id;
            try {
                $repairStmt = $pdo->prepare('UPDATE tbl_users SET tenant_id = ? WHERE user_id = ?');
                $repairStmt->execute([$tenant_id, $user_id]);
            } catch (Throwable $e) {
            }
            break;
        }
    }
}

try {
    $ownChk = $pdo->prepare('SELECT owner_user_id FROM tbl_tenants WHERE tenant_id = ? LIMIT 1');
    $ownChk->execute([(string) $tenant_id]);
    $resolvedOwner = $ownChk->fetchColumn();
    $_SESSION['is_owner'] = ($resolvedOwner !== false && (string) $resolvedOwner === (string) $user_id);
} catch (Throwable $e) {
}
$is_owner = !empty($_SESSION['is_owner']);
