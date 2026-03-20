<?php
/**
 * Tenant/owner safeguards and helpers.
 * Use when updating tbl_tenants.owner_user_id to ensure:
 * - The user belongs to the same tenant.
 * - The user has role = 'tenant_owner'.
 */

/**
 * Validate that a user can be set as tenant owner, then update tbl_tenants.owner_user_id.
 * Returns true on success, false on validation failure (and sets $error message).
 *
 * @param PDO $pdo
 * @param string $tenant_id
 * @param string $user_id
 * @param string|null $error Set to error message if validation fails
 * @return bool
 */
function set_tenant_owner_user_id(PDO $pdo, $tenant_id, $user_id, &$error = null) {
    $error = null;
    if (empty($tenant_id) || empty($user_id)) {
        $error = 'Tenant and user are required.';
        return false;
    }
    $stmt = $pdo->prepare("SELECT user_id, tenant_id, role FROM tbl_users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $error = 'User not found.';
        return false;
    }
    if ($user['tenant_id'] !== $tenant_id) {
        $error = 'Cannot assign owner from another tenant.';
        return false;
    }
    if ($user['role'] !== 'tenant_owner') {
        $error = 'Assigned owner must have role tenant_owner.';
        return false;
    }
    $stmt = $pdo->prepare("UPDATE tbl_tenants SET owner_user_id = ? WHERE tenant_id = ?");
    $stmt->execute([$user_id, $tenant_id]);
    return true;
}
