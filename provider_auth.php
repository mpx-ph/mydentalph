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

function provider_normalize_status(string $status): string
{
    return strtolower(trim($status));
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
    return $status !== false ? provider_normalize_status((string) $status) : null;
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
 *   name:string,
 *   username:string,
 *   email:string,
 *   full_name:string,
 *   role:string,
 *   status:string,
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
    $_SESSION['name'] = (string) ($user['name'] ?? ($user['full_name'] ?? $user['username'] ?? ''));
    $_SESSION['username'] = (string) ($user['username'] ?? '');
    $_SESSION['email'] = (string) ($user['email'] ?? '');
    $_SESSION['full_name'] = (string) ($user['full_name'] ?? '');
    $_SESSION['role'] = (string) ($user['role'] ?? '');
    $_SESSION['status'] = (string) ($user['status'] ?? '');
    $_SESSION['is_owner'] = (bool) ($user['is_owner'] ?? false);
}

/**
 * Resolve tenant subscription state with strict active checks.
 *
 * States:
 * - none: no subscription record exists
 * - active: paid and not expired (and tenant not suspended/inactive)
 * - expired: paid subscription exists but has ended
 * - inactive: subscription exists but not currently active (pending/failed/cancelled or tenant disabled)
 *
 * @return array{
 *   state:string,
 *   has_subscription:bool,
 *   has_active_subscription:bool,
 *   tenant_subscription_status:string,
 *   latest_subscription:?array<string,mixed>,
 *   active_subscription:?array<string,mixed>
 * }
 */
function provider_get_tenant_subscription_state(PDO $pdo, string $tenantId): array
{
    $result = [
        'state' => 'none',
        'has_subscription' => false,
        'has_active_subscription' => false,
        'tenant_subscription_status' => '',
        'latest_subscription' => null,
        'active_subscription' => null,
    ];

    $tenantId = trim($tenantId);
    if ($tenantId === '') {
        return $result;
    }

    $tenantStatusStmt = $pdo->prepare("SELECT subscription_status FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
    $tenantStatusStmt->execute([$tenantId]);
    $tenantSubscriptionStatus = (string) ($tenantStatusStmt->fetchColumn() ?: '');
    $result['tenant_subscription_status'] = strtolower(trim($tenantSubscriptionStatus));

    $latestStmt = $pdo->prepare("
        SELECT ts.id, ts.plan_id, ts.subscription_start, ts.subscription_end, ts.payment_status, ts.created_at,
               p.plan_slug, p.plan_name
        FROM tbl_tenant_subscriptions ts
        LEFT JOIN tbl_subscription_plans p ON p.plan_id = ts.plan_id
        WHERE ts.tenant_id = ?
        ORDER BY ts.id DESC
        LIMIT 1
    ");
    $latestStmt->execute([$tenantId]);
    $latest = $latestStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $result['latest_subscription'] = $latest;
    $result['has_subscription'] = $latest !== null;

    $activeStmt = $pdo->prepare("
        SELECT ts.id, ts.plan_id, ts.subscription_start, ts.subscription_end, ts.payment_status, ts.created_at,
               p.plan_slug, p.plan_name
        FROM tbl_tenant_subscriptions ts
        LEFT JOIN tbl_subscription_plans p ON p.plan_id = ts.plan_id
        WHERE ts.tenant_id = ?
          AND ts.payment_status = 'paid'
          AND (ts.subscription_start IS NULL OR ts.subscription_start <= CURDATE())
          AND (ts.subscription_end IS NULL OR ts.subscription_end >= CURDATE())
        ORDER BY ts.id DESC
        LIMIT 1
    ");
    $activeStmt->execute([$tenantId]);
    $active = $activeStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $result['active_subscription'] = $active;

    $tenantAllowsActive = ($result['tenant_subscription_status'] === '' || $result['tenant_subscription_status'] === 'active');
    $result['has_active_subscription'] = $active !== null && $tenantAllowsActive;

    if ($result['has_active_subscription']) {
        $result['state'] = 'active';
        return $result;
    }

    if (!$result['has_subscription']) {
        $result['state'] = 'none';
        return $result;
    }

    $latestPaymentStatus = strtolower(trim((string) ($latest['payment_status'] ?? '')));
    $latestEnd = (string) ($latest['subscription_end'] ?? '');
    $latestEndTs = $latestEnd !== '' ? strtotime($latestEnd . ' 23:59:59') : false;
    if ($latestPaymentStatus === 'paid' && $latestEndTs !== false && $latestEndTs < time()) {
        $result['state'] = 'expired';
        return $result;
    }

    $result['state'] = 'inactive';
    return $result;
}

