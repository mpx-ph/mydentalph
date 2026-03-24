<?php

/**
 * After email OTP succeeds: create tenant + owner user, optional verified email row, remove pending.
 *
 * @return array{user_id: string, tenant_id: string}
 */
function provider_signup_finalize_from_pending(PDO $pdo, int $pendingId): array
{
    $maxAttempts = 8;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $tenant_id = null;
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM tbl_provider_pending_signups WHERE id = ? FOR UPDATE');
            $stmt->execute([$pendingId]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$p) {
                $pdo->rollBack();
                throw new RuntimeException('Pending signup not found.');
            }

            $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(tenant_id, 5) AS UNSIGNED)), 0) FROM tbl_tenants WHERE tenant_id REGEXP '^TNT_[0-9]+$'");
            $num = (int) $stmt->fetchColumn() + 1;
            $tenant_id = 'TNT_' . str_pad((string) $num, 5, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare('INSERT INTO tbl_tenants (tenant_id, clinic_name, country_region) VALUES (?, ?, ?)');
            $stmt->execute([$tenant_id, $p['clinic_name'], $p['country_region']]);

            $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(user_id, 6) AS UNSIGNED)), 0) FROM tbl_users WHERE user_id REGEXP '^USER_[0-9]+$'");
            $unum = (int) $stmt->fetchColumn() + 1;
            $user_id = 'USER_' . str_pad((string) $unum, 5, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO tbl_users (user_id, tenant_id, username, email, full_name, password_hash, role) VALUES (?, ?, ?, ?, ?, ?, 'tenant_owner')");
            $stmt->execute([
                $user_id,
                $tenant_id,
                $p['username'],
                $p['email'],
                $p['full_name'],
                $p['password_hash'],
            ]);

            $stmt = $pdo->prepare('UPDATE tbl_tenants SET owner_user_id = ? WHERE tenant_id = ?');
            $stmt->execute([$user_id, $tenant_id]);

            // Backward-compatible: some environments may not have the latest
            // tbl_email_verifications schema yet. Do not block signup completion.
            try {
                $stmt = $pdo->prepare('INSERT INTO tbl_email_verifications (tenant_id, user_id, otp_hash, otp_expires_at, attempts, verified_at) VALUES (?, ?, ?, ?, 0, NOW())');
                $stmt->execute([
                    $tenant_id,
                    $user_id,
                    $p['otp_hash'],
                    $p['otp_expires_at'],
                ]);
            } catch (PDOException $e) {
                error_log('provider_signup_finalize_from_pending: email verification insert skipped: ' . $e->getMessage());
            }

            $stmt = $pdo->prepare('DELETE FROM tbl_provider_pending_signups WHERE id = ?');
            $stmt->execute([$pendingId]);

            $pdo->commit();

            return ['user_id' => $user_id, 'tenant_id' => $tenant_id];
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            provider_signup_cleanup_partial($pdo, $tenant_id);
            $driverCode = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
            if ($attempt < $maxAttempts && $driverCode === 1062) {
                continue;
            }
            throw $e;
        }
    }

    throw new RuntimeException('Could not allocate tenant or user id.');
}

function provider_signup_cleanup_partial(PDO $pdo, ?string $tenant_id): void
{
    if ($tenant_id === null || $tenant_id === '') {
        return;
    }
    try {
        $pdo->prepare('DELETE FROM tbl_email_verifications WHERE tenant_id = ?')->execute([$tenant_id]);
    } catch (PDOException $e) {
    }
    try {
        $pdo->prepare('DELETE FROM tbl_users WHERE tenant_id = ? AND role = ?')->execute([$tenant_id, 'tenant_owner']);
    } catch (PDOException $e) {
    }
    try {
        $pdo->prepare('DELETE FROM tbl_tenants WHERE tenant_id = ?')->execute([$tenant_id]);
    } catch (PDOException $e) {
    }
}
