<?php
/**
 * Provider-side authentication/authorization helpers.
 *
 * Goal:
 * - Only allow access to provider portal pages after the clinic verification is APPROVED.
 * - Never treat onboarding-only session variables as "authenticated/active".
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function provider_get_candidate_identity_from_session(): array
{
    // Order of precedence: real login session > onboarding session > payment session
    $tenantId = isset($_SESSION['tenant_id']) ? (string) $_SESSION['tenant_id'] : '';
    $ownerUserId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';

    if ($tenantId !== '' && $ownerUserId !== '') {
        return [$tenantId, $ownerUserId];
    }

    $tenantId = isset($_SESSION['onboarding_tenant_id']) ? (string) $_SESSION['onboarding_tenant_id'] : '';
    $ownerUserId = isset($_SESSION['onboarding_user_id']) ? (string) $_SESSION['onboarding_user_id'] : '';
    if ($tenantId !== '' && $ownerUserId !== '') {
        return [$tenantId, $ownerUserId];
    }

    $tenantId = isset($_SESSION['paymongo_tenant_id']) ? (string) $_SESSION['paymongo_tenant_id'] : '';
    $ownerUserId = isset($_SESSION['paymongo_user_id']) ? (string) $_SESSION['paymongo_user_id'] : '';
    if ($tenantId !== '' && $ownerUserId !== '') {
        return [$tenantId, $ownerUserId];
    }

    return ['', ''];
}

function provider_get_verification_request_status(PDO $pdo, string $tenantId, string $ownerUserId): ?string
{
    if ($tenantId === '' || $ownerUserId === '') {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT status
        FROM tbl_tenant_verification_requests
        WHERE tenant_id = ? AND owner_user_id = ?
        ORDER BY request_id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $ownerUserId]);
    $status = $stmt->fetchColumn();
    return $status !== false ? (string) $status : null;
}

function provider_is_email_verified(PDO $pdo, string $tenantId, string $ownerUserId): bool
{
    if ($tenantId === '' || $ownerUserId === '') {
        return false;
    }

    // Server-side flag set after OTP verification; allows pages to survive optional row insert failures.
    $sessionFlag = $_SESSION['onboarding_email_verified_at'] ?? 0;
    if (is_numeric($sessionFlag) && (int) $sessionFlag > 0) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM tbl_email_verifications
        WHERE tenant_id = ? AND user_id = ? AND verified_at IS NOT NULL
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $ownerUserId]);
    return (bool) $stmt->fetchColumn();
}

function provider_has_submitted_clinic_docs(PDO $pdo, string $tenantId, string $ownerUserId): bool
{
    if ($tenantId === '' || $ownerUserId === '') {
        return false;
    }

    // Server-side flag set after successful file upload.
    $sessionFlag = $_SESSION['onboarding_clinic_docs_submitted_at'] ?? 0;
    if (is_numeric($sessionFlag) && (int) $sessionFlag > 0) {
        return true;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM tbl_tenant_verification_requests
        WHERE tenant_id = ? AND submitted_at IS NOT NULL
        ORDER BY request_id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    return (bool) $stmt->fetchColumn();
}

function provider_require_approved_for_provider_portal(): void
{
    require_once __DIR__ . '/db.php';

    $role = $_SESSION['role'] ?? '';
    if (!empty($_SESSION['user_id']) && $role === 'superadmin') {
        return; // superadmin can access everything
    }

    [$tenantId, $ownerUserId] = provider_get_candidate_identity_from_session();
    // If the caller isn't an authenticated/onboarding/payment candidate, do not
    // force a redirect here. Some provider pages (e.g. marketing/landing) are public.
    if ($tenantId === '' || $ownerUserId === '') {
        $hasAnyIdentityHint = !empty($_SESSION['user_id'])
            || !empty($_SESSION['tenant_id'])
            || !empty($_SESSION['onboarding_user_id'])
            || !empty($_SESSION['onboarding_tenant_id'])
            || !empty($_SESSION['paymongo_user_id'])
            || !empty($_SESSION['paymongo_tenant_id']);

        if ($hasAnyIdentityHint) {
            header('Location: ProviderApprovalStatus.php');
            exit;
        }

        return; // truly unauthenticated visitor
    }

    $status = provider_get_verification_request_status($pdo, $tenantId, $ownerUserId);
    if ($status === 'approved') {
        // Also require the provider owner account to be active.
        $stmt = $pdo->prepare("SELECT 1 FROM tbl_users WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$ownerUserId, $tenantId]);
        if ((bool) $stmt->fetchColumn()) {
            return;
        }
    }

    header('Location: ProviderApprovalStatus.php');
    exit;
}

/**
 * Establish a standard authenticated provider session.
 * Use this after non-password auth flows (e.g., secure onboarding links).
 *
 * @param array{
 *   user_id:string,
 *   tenant_id:string,
 *   username:string,
 *   email:string,
 *   full_name:string,
 *   role:string,
 *   is_owner:bool
 * } $user
 */
function provider_establish_authenticated_session(array $user): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = (string) ($user['user_id'] ?? '');
    $_SESSION['tenant_id'] = (string) ($user['tenant_id'] ?? '');
    $_SESSION['username'] = (string) ($user['username'] ?? '');
    $_SESSION['email'] = (string) ($user['email'] ?? '');
    $_SESSION['full_name'] = (string) ($user['full_name'] ?? '');
    $_SESSION['role'] = (string) ($user['role'] ?? '');
    $_SESSION['is_owner'] = (bool) ($user['is_owner'] ?? false);
}

