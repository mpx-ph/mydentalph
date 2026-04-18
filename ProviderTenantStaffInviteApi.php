<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail_config.php';

header('Content-Type: application/json; charset=utf-8');

const TENANT_STAFF_INVITE_SESSION = 'tenant_staff_invite_v1';
const TENANT_STAFF_INVITE_TTL = 900;
const TENANT_STAFF_INVITE_MAX_ATTEMPTS = 10;

/**
 * @return string|null Error or null if allowed
 */
function tenant_staff_invite_require_portal(PDO $pdo): ?string
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

function tenant_staff_invite_validate_password(string $pw): ?string
{
    if (strlen($pw) < 12) {
        return 'Password must be at least 12 characters.';
    }
    if (!preg_match('/[A-Z]/', $pw)) {
        return 'Password must include an uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $pw)) {
        return 'Password must include a lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $pw)) {
        return 'Password must include a number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $pw)) {
        return 'Password must include a special character.';
    }
    return null;
}

function tenant_staff_invite_map_role(string $label): ?string
{
    $k = strtolower(trim($label));
    return match ($k) {
        'manager' => 'manager',
        'staff' => 'staff',
        'doctor' => 'dentist',
        default => null,
    };
}

function tenant_staff_invite_next_user_id(PDO $pdo): string
{
    $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(user_id, 6) AS UNSIGNED)), 0) FROM tbl_users WHERE user_id REGEXP '^USER_[0-9]+$'");
    $max_user_num = (int) $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(owner_user_id, 6) AS UNSIGNED)), 0) FROM tbl_tenants WHERE owner_user_id REGEXP '^USER_[0-9]+$'");
    $max_owner_num = (int) $stmt->fetchColumn();
    $unum = max($max_user_num, $max_owner_num) + 1;
    return 'USER_' . str_pad((string) $unum, 5, '0', STR_PAD_LEFT);
}

function tenant_staff_invite_next_staff_display_id(PDO $pdo, string $tenant_id): string
{
    $year = date('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_staffs WHERE tenant_id = ? AND staff_id LIKE ?');
    $stmt->execute([$tenant_id, 'S-' . $year . '-%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int) ($row['c'] ?? 0);
    $sequence = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    return 'S-' . $year . '-' . $sequence;
}

function tenant_staff_invite_next_dentist_display_id(PDO $pdo, string $tenant_id): string
{
    $year = date('Y');
    $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_dentists WHERE tenant_id = ? AND dentist_display_id LIKE ?');
    $stmt->execute([$tenant_id, 'D-' . $year . '-%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int) ($row['c'] ?? 0);
    $sequence = str_pad((string) ($count + 1), 5, '0', STR_PAD_LEFT);
    return 'D-' . $year . '-' . $sequence;
}

function tenant_staff_invite_insert_dentist_profile(
    PDO $pdo,
    string $tenant_id,
    string $first_name,
    string $last_name,
    string $email
): void {
    $display_id = tenant_staff_invite_next_dentist_display_id($pdo, $tenant_id);
    $stmt = $pdo->prepare('
        INSERT INTO tbl_dentists (tenant_id, dentist_display_id, first_name, last_name, email, status, created_at)
        VALUES (?, ?, ?, ?, ?, \'active\', NOW())
    ');
    $stmt->execute([$tenant_id, $display_id, $first_name, $last_name, strtolower(trim($email))]);
}

function tenant_staff_invite_read_session(): ?array
{
    $s = $_SESSION[TENANT_STAFF_INVITE_SESSION] ?? null;
    return is_array($s) ? $s : null;
}

function tenant_staff_invite_clear_session(): void
{
    unset($_SESSION[TENANT_STAFF_INVITE_SESSION]);
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

$portalErr = tenant_staff_invite_require_portal($pdo);
if ($portalErr !== null) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $portalErr]);
    exit;
}

$inviter_user_id = trim((string) ($_SESSION['user_id'] ?? ''));
$tenant_id = trim((string) ($_SESSION['tenant_id'] ?? ''));
if ($inviter_user_id === '' || $tenant_id === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not signed in.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = trim((string) ($data['action'] ?? ''));

if ($action === 'abandon') {
    tenant_staff_invite_clear_session();
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'send_code' || $action === 'resend') {
    $invite = tenant_staff_invite_read_session();

    if ($action === 'resend') {
        if ($invite === null || empty($invite['payload'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'No pending invitation. Send a new code from the form.']);
            exit;
        }
        $payload = $invite['payload'];
    } else {
        $owner_mode = !empty($data['owner_mode']);
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $role_label = trim((string) ($data['role'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $role_db = tenant_staff_invite_map_role($role_label);
        if ($role_db === null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Choose a valid clinic role (Manager, Staff, or Doctor).']);
            exit;
        }

        if ($first === '' || $last === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'First and last name are required.']);
            exit;
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'A valid professional email is required.']);
            exit;
        }

        $password_hash = null;
        if ($owner_mode) {
            $stmt = $pdo->prepare('SELECT email FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
            $stmt->execute([$inviter_user_id, $tenant_id]);
            $me = $stmt->fetch(PDO::FETCH_ASSOC);
            $my_email = strtolower(trim((string) ($me['email'] ?? '')));
            if ($my_email === '' || $my_email !== $email) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Owner credentials mode must use your account email.']);
                exit;
            }
        } else {
            $pwErr = tenant_staff_invite_validate_password($password);
            if ($pwErr !== null) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $pwErr]);
                exit;
            }
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
        }

        $full_name = trim($first . ' ' . $last);
        if ($full_name === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Name is required.']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT user_id FROM tbl_users WHERE tenant_id = ? AND email = ? LIMIT 1');
        $stmt->execute([$tenant_id, $email]);
        $existing_uid = $stmt->fetchColumn();
        if ($existing_uid) {
            $is_self = $owner_mode && (string) $existing_uid === $inviter_user_id;
            if (!$is_self) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'That email is already registered for this clinic.']);
                exit;
            }
        }

        if ($owner_mode) {
            if ($role_db === 'dentist') {
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM tbl_dentists WHERE tenant_id = ? AND LOWER(TRIM(COALESCE(email, \'\'))) = ? LIMIT 1'
                );
                $stmt->execute([$tenant_id, $email]);
                if ((bool) $stmt->fetchColumn()) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'error' => 'You already have a dentist profile for this clinic.']);
                    exit;
                }
            } else {
                $stmt = $pdo->prepare('SELECT 1 FROM tbl_staffs WHERE tenant_id = ? AND user_id = ? LIMIT 1');
                $stmt->execute([$tenant_id, $inviter_user_id]);
                if ((bool) $stmt->fetchColumn()) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'error' => 'You already have a staff profile for this clinic.']);
                    exit;
                }
            }
        }

        $payload = [
            'owner_mode' => $owner_mode,
            'first' => $first,
            'last' => $last,
            'email' => $email,
            'role' => $role_db,
            'password_hash' => $password_hash,
            'inviter_user_id' => $inviter_user_id,
        ];
    }

    $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
    $expires_at = time() + TENANT_STAFF_INVITE_TTL;

    $_SESSION[TENANT_STAFF_INVITE_SESSION] = [
        'otp_hash' => $otp_hash,
        'expires_at' => $expires_at,
        'attempts' => 0,
        'payload' => $payload,
    ];

    $to = (string) $payload['email'];
    $greet = (string) $payload['first'];
    if (!send_staff_invite_verification_email($to, $greet, $otp_code)) {
        tenant_staff_invite_clear_session();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not send email. Check mail settings and try again.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'email' => $to,
        'expires_in' => TENANT_STAFF_INVITE_TTL,
    ]);
    exit;
}

if ($action === 'verify') {
    $code = preg_replace('/\D/', '', (string) ($data['code'] ?? ''));
    $invite = tenant_staff_invite_read_session();

    if ($invite === null || empty($invite['payload'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No pending invitation. Request a new code.']);
        exit;
    }

    $expires_at = (int) ($invite['expires_at'] ?? 0);
    if ($expires_at < time()) {
        tenant_staff_invite_clear_session();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'This code has expired. Request a new one.']);
        exit;
    }

    $attempts = (int) ($invite['attempts'] ?? 0);
    if ($attempts >= TENANT_STAFF_INVITE_MAX_ATTEMPTS) {
        tenant_staff_invite_clear_session();
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Too many attempts. Request a new code.']);
        exit;
    }

    $dev_otp = defined('DEV_OTP') ? (string) constant('DEV_OTP') : '';
    $otp_ok = ($dev_otp !== '' && $code === $dev_otp)
        || (strlen($code) === 6 && password_verify($code, (string) ($invite['otp_hash'] ?? '')));

    if (!$otp_ok) {
        $invite['attempts'] = $attempts + 1;
        $_SESSION[TENANT_STAFF_INVITE_SESSION] = $invite;
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid verification code.']);
        exit;
    }

    $payload = $invite['payload'];
    $owner_mode = !empty($payload['owner_mode']);

    try {
        $pdo->beginTransaction();

        if ($owner_mode) {
            $uid = (string) $payload['inviter_user_id'];
            $invite_role = (string) ($payload['role'] ?? 'staff');

            if ($invite_role === 'dentist') {
                $owner_email = strtolower(trim((string) ($payload['email'] ?? '')));
                $stmt = $pdo->prepare(
                    'SELECT 1 FROM tbl_dentists WHERE tenant_id = ? AND LOWER(TRIM(COALESCE(email, \'\'))) = ? LIMIT 1'
                );
                $stmt->execute([$tenant_id, $owner_email]);
                if ((bool) $stmt->fetchColumn()) {
                    $pdo->rollBack();
                    tenant_staff_invite_clear_session();
                    echo json_encode(['ok' => true, 'message' => 'Your dentist profile was already active.']);
                    exit;
                }

                tenant_staff_invite_insert_dentist_profile(
                    $pdo,
                    $tenant_id,
                    (string) $payload['first'],
                    (string) $payload['last'],
                    $owner_email
                );
            } else {
                $stmt = $pdo->prepare('SELECT 1 FROM tbl_staffs WHERE tenant_id = ? AND user_id = ? LIMIT 1');
                $stmt->execute([$tenant_id, $uid]);
                if ((bool) $stmt->fetchColumn()) {
                    $pdo->rollBack();
                    tenant_staff_invite_clear_session();
                    echo json_encode(['ok' => true, 'message' => 'Your staff profile was already active.']);
                    exit;
                }

                $staff_display = tenant_staff_invite_next_staff_display_id($pdo, $tenant_id);
                $stmt = $pdo->prepare('
                    INSERT INTO tbl_staffs (tenant_id, staff_id, user_id, first_name, last_name, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $tenant_id,
                    $staff_display,
                    $uid,
                    $payload['first'],
                    $payload['last'],
                ]);
            }
        } else {
            $email = (string) $payload['email'];
            $stmt = $pdo->prepare('SELECT user_id FROM tbl_users WHERE tenant_id = ? AND email = ? LIMIT 1');
            $stmt->execute([$tenant_id, $email]);
            if ($stmt->fetchColumn()) {
                $pdo->rollBack();
                tenant_staff_invite_clear_session();
                http_response_code(409);
                echo json_encode(['ok' => false, 'error' => 'That email was registered while you were verifying.']);
                exit;
            }

            $new_user_id = tenant_staff_invite_next_user_id($pdo);
            $role = (string) $payload['role'];
            $hash = (string) $payload['password_hash'];
            $full_name = trim((string) $payload['first'] . ' ' . (string) $payload['last']);

            $stmt = $pdo->prepare('
                INSERT INTO tbl_users (user_id, tenant_id, username, email, full_name, password_hash, role, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, \'active\')
            ');
            $stmt->execute([
                $new_user_id,
                $tenant_id,
                $email,
                $email,
                $full_name,
                $hash,
                $role,
            ]);

            if ($role === 'dentist') {
                tenant_staff_invite_insert_dentist_profile(
                    $pdo,
                    $tenant_id,
                    (string) $payload['first'],
                    (string) $payload['last'],
                    $email
                );
            } else {
                $staff_display = tenant_staff_invite_next_staff_display_id($pdo, $tenant_id);
                $stmt = $pdo->prepare('
                    INSERT INTO tbl_staffs (tenant_id, staff_id, user_id, first_name, last_name, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $tenant_id,
                    $staff_display,
                    $new_user_id,
                    $payload['first'],
                    $payload['last'],
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('tenant_staff_invite verify: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not complete setup. Please try again.']);
        exit;
    }

    tenant_staff_invite_clear_session();
    $ownerMsg = 'Your staff profile is ready. You can use the clinic portal with your existing login.';
    if ($owner_mode && (string) ($payload['role'] ?? '') === 'dentist') {
        $ownerMsg = 'Your dentist profile is ready. You can use the clinic portal with your existing login.';
    }
    echo json_encode([
        'ok' => true,
        'message' => $owner_mode ? $ownerMsg : 'Team member account created. They can sign in with their email and password.',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
