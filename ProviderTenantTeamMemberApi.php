<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database is not available.']);
    exit;
}

/**
 * @return string|null Error message or null if allowed to call team member APIs
 */
function provider_tenant_team_api_require_portal(PDO $pdo): ?string
{
    $role = (string) ($_SESSION['role'] ?? '');
    if ($role === 'superadmin') {
        return 'Use clinic tools for this tenant.';
    }

    [$tenantId, $sessionUserId] = provider_get_authenticated_provider_identity_from_session();
    if ($tenantId === '' || $sessionUserId === '') {
        return 'Not signed in.';
    }

    $status = provider_get_verification_request_status($pdo, $tenantId, $sessionUserId);
    if ($status !== 'approved') {
        return 'Your clinic account is not approved for this action.';
    }

    $stmt = $pdo->prepare("SELECT 1 FROM tbl_users WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$sessionUserId, $tenantId]);
    if (!(bool) $stmt->fetchColumn()) {
        return 'Your account is not active.';
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$portalErr = provider_tenant_team_api_require_portal($pdo);
if ($portalErr !== null) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $portalErr]);
    exit;
}

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.']);
    exit;
}

$user_id = (string) $_SESSION['user_id'];
require_once __DIR__ . '/provider_tenant_canonical_context.inc.php';
$tenant_id = trim((string) $tenant_id);

if ($tenant_id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing tenant context.']);
    exit;
}

if (empty($_SESSION['is_owner'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Only the clinic owner can remove team members.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$targetId = trim((string) ($data['user_id'] ?? ''));
if ($targetId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'user_id is required.']);
    exit;
}

if ($targetId === $user_id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'You cannot remove your own account.']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT user_id, role, email FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$targetId, $tenant_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($target)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Team member not found.']);
        exit;
    }

    if ((string) ($target['role'] ?? '') === 'tenant_owner') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'The clinic owner account cannot be removed here.']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_patients WHERE (owner_user_id = ? OR linked_user_id = ?) AND tenant_id = ?');
    $stmt->execute([$targetId, $targetId, $tenant_id]);
    $patientCount = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_appointments WHERE created_by = ? AND tenant_id = ?');
    $stmt->execute([$targetId, $tenant_id]);
    $appointmentCount = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    if ($patientCount > 0 || $appointmentCount > 0) {
        $stmt = $pdo->prepare("UPDATE tbl_users SET status = 'inactive', updated_at = NOW() WHERE user_id = ? AND tenant_id = ?");
        $stmt->execute([$targetId, $tenant_id]);
        echo json_encode(['ok' => true, 'mode' => 'deactivated', 'message' => 'User was deactivated (linked patient or appointment history exists).']);
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM tbl_users WHERE user_id = ? AND tenant_id = ?');
    $stmt->execute([$targetId, $tenant_id]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Could not remove user.']);
        exit;
    }

    echo json_encode(['ok' => true, 'mode' => 'deleted', 'message' => 'Team member removed.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not remove team member.']);
}
