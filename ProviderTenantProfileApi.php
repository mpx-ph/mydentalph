<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * @return string|null Error message or null if valid
 */
function provider_profile_api_validate_new_password(string $pw): ?string
{
    if (strlen($pw) < 12) {
        return 'New password must be at least 12 characters.';
    }
    if (!preg_match('/[A-Z]/', $pw)) {
        return 'New password must include an uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $pw)) {
        return 'New password must include a lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $pw)) {
        return 'New password must include a number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) {
        return 'New password must include a special character.';
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database is not available.']);
    exit;
}

/**
 * @return string|null Error message or null if allowed
 */
function provider_profile_api_require_portal(PDO $pdo): ?string
{
    $role = (string) ($_SESSION['role'] ?? '');
    if ($role === 'superadmin') {
        return null;
    }

    [$tenantId, $ownerUserId] = provider_get_authenticated_provider_identity_from_session();
    if ($tenantId === '' || $ownerUserId === '') {
        return 'Not signed in.';
    }

    $status = provider_get_verification_request_status($pdo, $tenantId, $ownerUserId);
    if ($status !== 'approved') {
        return 'Your clinic account is not approved for this action.';
    }

    $stmt = $pdo->prepare("SELECT 1 FROM tbl_users WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$ownerUserId, $tenantId]);
    if (!(bool) $stmt->fetchColumn()) {
        return 'Your account is not active.';
    }

    return null;
}

$portalErr = provider_profile_api_require_portal($pdo);
if ($portalErr !== null) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $portalErr]);
    exit;
}

$user_id = trim((string) ($_SESSION['user_id'] ?? ''));
$tenant_id = trim((string) ($_SESSION['tenant_id'] ?? ''));
if ($user_id === '' || $tenant_id === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = trim((string) ($data['action'] ?? 'save_profile'));

if ($action === 'verify_email_otp') {
    $otp_code = trim((string) ($data['otp_code'] ?? ''));
    if (strlen($otp_code) !== 6 || !ctype_digit($otp_code)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Enter the 6-digit code from your email.']);
        exit;
    }

    $dev_otp = defined('DEV_OTP') ? (string) DEV_OTP : '';
    $matched_id = null;
    $matched = false;

    try {
        $stmt = $pdo->prepare(
            'SELECT id, otp_hash, otp_expires_at FROM tbl_email_verifications
             WHERE user_id = ? AND verified_at IS NULL
             ORDER BY id DESC LIMIT 8'
        );
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $exp = strtotime((string) ($row['otp_expires_at'] ?? ''));
            if ($exp !== false && $exp >= time() && password_verify($otp_code, (string) ($row['otp_hash'] ?? ''))) {
                $matched = true;
                $matched_id = (int) $row['id'];
                break;
            }
        }
        if (!$matched && $dev_otp !== '' && $otp_code === $dev_otp) {
            $matched = true;
        }
        if (!$matched) {
            $pdo->prepare('UPDATE tbl_email_verifications SET attempts = attempts + 1 WHERE user_id = ? AND verified_at IS NULL')
                ->execute([$user_id]);
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid or expired code. Request a new one from your profile.']);
            exit;
        }
        if ($matched_id !== null) {
            $pdo->prepare('UPDATE tbl_email_verifications SET verified_at = NOW() WHERE id = ?')->execute([$matched_id]);
        } else {
            $pdo->prepare(
                'UPDATE tbl_email_verifications SET verified_at = NOW()
                 WHERE user_id = ? AND verified_at IS NULL ORDER BY id DESC LIMIT 1'
            )->execute([$user_id]);
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not verify the code. Try again.']);
        exit;
    }

    $_SESSION['onboarding_email_verified_at'] = time();
    echo json_encode(['ok' => true, 'message' => 'Email verified successfully.']);
    exit;
}

// --- save_profile ---

$first_name = trim((string) ($data['first_name'] ?? ''));
$last_name = trim((string) ($data['last_name'] ?? ''));
$email = trim((string) ($data['email'] ?? ''));
$current_password = (string) ($data['current_password'] ?? '');
$new_password = (string) ($data['new_password'] ?? '');
$confirm_password = (string) ($data['confirm_password'] ?? '');

if ($first_name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter your first name.']);
    exit;
}

$full_name = trim($first_name . ($last_name !== '' ? ' ' . $last_name : ''));
if (strlen($full_name) > 255) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Name is too long.']);
    exit;
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

$wants_password_change = $new_password !== '' || $confirm_password !== '';

try {
    $stmt = $pdo->prepare(
        'SELECT user_id, tenant_id, username, email, full_name, password_hash FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1'
    );
    $stmt->execute([$user_id, $tenant_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load your profile.']);
    exit;
}

if (!$row) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Profile not found for this session.']);
    exit;
}

$old_email = trim((string) ($row['email'] ?? ''));
$email_changed = strcasecmp($old_email, $email) !== 0;
$name_changed = ((string) ($row['full_name'] ?? '')) !== $full_name;

if ($wants_password_change) {
    if ($new_password !== $confirm_password) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'New password and confirmation do not match.']);
        exit;
    }
    $pwErr = provider_profile_api_validate_new_password($new_password);
    if ($pwErr !== null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $pwErr]);
        exit;
    }
}

$sensitive = $email_changed || $wants_password_change;
if ($sensitive) {
    $hash = (string) ($row['password_hash'] ?? '');
    if ($hash === '' || !password_verify($current_password, $hash)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Current password is required and must be correct to change email or password.']);
        exit;
    }
}

if ($email_changed) {
    try {
        $dup = $pdo->prepare(
            'SELECT 1 FROM tbl_users WHERE tenant_id = ? AND email = ? AND user_id <> ? LIMIT 1'
        );
        $dup->execute([$tenant_id, $email, $user_id]);
        if ((bool) $dup->fetchColumn()) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'That email is already used by another user in your clinic.']);
            exit;
        }
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not validate email.']);
        exit;
    }
}

$sync_username = strcasecmp(trim((string) ($row['username'] ?? '')), $old_email) === 0;
$new_username = $sync_username ? $email : null;

try {
    $pdo->beginTransaction();

    $sets = ['full_name = ?', 'email = ?', 'updated_at = CURRENT_TIMESTAMP'];
    $params = [$full_name, $email];

    if ($new_username !== null) {
        $sets[] = 'username = ?';
        $params[] = $email;
    }

    if ($wants_password_change) {
        $sets[] = 'password_hash = ?';
        $params[] = password_hash($new_password, PASSWORD_DEFAULT);
    }

    $params[] = $user_id;
    $params[] = $tenant_id;

    $sql = 'UPDATE tbl_users SET ' . implode(', ', $sets) . ' WHERE user_id = ? AND tenant_id = ? LIMIT 1';
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    if ($upd->rowCount() < 1) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Update did not apply. Please try again.']);
        exit;
    }

    $email_verification_sent = false;
    if ($email_changed) {
        $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
        $otp_expires = date('Y-m-d H:i:s', time() + 900);

        $stmt = $pdo->prepare(
            'SELECT id FROM tbl_email_verifications WHERE user_id = ? AND verified_at IS NULL ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$user_id]);
        $otp_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($otp_row) {
            $pdo->prepare(
                'UPDATE tbl_email_verifications
                 SET otp_hash = ?, otp_expires_at = ?, attempts = 0, last_sent_at = NOW(), token_hash = NULL, token_expires_at = NULL
                 WHERE id = ?'
            )->execute([$otp_hash, $otp_expires, (int) $otp_row['id']]);
        } else {
            $pdo->prepare(
                'INSERT INTO tbl_email_verifications (tenant_id, user_id, otp_hash, otp_expires_at, attempts, last_sent_at)
                 VALUES (?, ?, ?, ?, 0, NOW())'
            )->execute([$tenant_id, $user_id, $otp_hash, $otp_expires]);
        }

        $email_verification_sent = send_otp_email($email, $otp_code);
        if (!$email_verification_sent) {
            $pdo->rollBack();
            http_response_code(502);
            echo json_encode([
                'ok' => false,
                'error' => 'We could not send a verification email. Your email was not changed. Try again or contact support.',
            ]);
            exit;
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save your profile. Please try again.']);
    exit;
}

$_SESSION['full_name'] = $full_name;
$_SESSION['name'] = $full_name;
$_SESSION['email'] = $email;
if ($new_username !== null) {
    $_SESSION['username'] = $email;
}

$message = 'Profile saved.';
if ($email_changed) {
    $message = 'Profile saved. Enter the verification code we sent to your new email address.';
}

echo json_encode([
    'ok' => true,
    'message' => $message,
    'user' => [
        'full_name' => $full_name,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
    ],
    'email_verification_sent' => $email_changed,
    'name_updated' => $name_changed || $email_changed || $wants_password_change,
]);
exit;
