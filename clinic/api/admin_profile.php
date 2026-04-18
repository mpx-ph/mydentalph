<?php
/**
 * Admin/Staff Profile API Endpoint
 * Handles profile data retrieval and updates for logged-in admin/staff/doctor users
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}

$__mailCfg = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'mail_config.php';
if (is_file($__mailCfg)) {
    require_once $__mailCfg;
}

header('Content-Type: application/json');

/**
 * @return string|null Error message or null if valid
 */
function admin_profile_validate_new_password(string $pw): ?string
{
    if (strlen($pw) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (!preg_match('/[A-Za-z]/', $pw)) {
        return 'Password must include at least one letter.';
    }
    if (!preg_match('/[0-9]/', $pw)) {
        return 'Password must include at least one number.';
    }
    return null;
}

function admin_profile_image_public_url(?string $relative): string
{
    $relative = trim((string) $relative);
    if ($relative === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $relative)) {
        return $relative;
    }
    return rtrim(BASE_URL, '/') . '/' . ltrim(str_replace('\\', '/', $relative), '/');
}

function admin_profile_is_dentist_session(): bool
{
    $role = strtolower(trim((string) ($_SESSION['user_role'] ?? '')));
    if ($role === 'dentist') {
        return true;
    }
    return ($_SESSION['user_type'] ?? '') === 'doctor';
}

/**
 * @return array<string, mixed>|null
 */
function admin_profile_resolve_dentist_row(PDO $pdo, string $tenantId, string $lookupEmail): ?array
{
    $lookupEmail = strtolower(trim($lookupEmail));
    if ($lookupEmail === '') {
        return null;
    }
    $stmt = $pdo->prepare('
        SELECT * FROM tbl_dentists
        WHERE tenant_id = ? AND LOWER(TRIM(COALESCE(email, \'\'))) = ?
        ORDER BY dentist_id ASC
        LIMIT 1
    ');
    $stmt->execute([$tenantId, $lookupEmail]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

/**
 * @param array<string, mixed> $dentistRow
 */
function admin_profile_ensure_dentist_display_id(PDO $pdo, string $tenantId, array $dentistRow): string
{
    $existing = trim((string) ($dentistRow['dentist_display_id'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }
    $pk = (int) ($dentistRow['dentist_id'] ?? 0);
    if ($pk < 1) {
        return '';
    }
    $newId = tenant_next_dentist_display_id($pdo, $tenantId);
    $stmt = $pdo->prepare('
        UPDATE tbl_dentists
        SET dentist_display_id = ?
        WHERE dentist_id = ? AND tenant_id = ?
          AND (dentist_display_id IS NULL OR dentist_display_id = \'\')
    ');
    $stmt->execute([$newId, $pk, $tenantId]);
    return $newId;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = getDBConnection();

$userType = $_SESSION['user_type'] ?? '';
if (!in_array($userType, ['admin', 'staff', 'doctor', 'manager'], true)) {
    jsonResponse(false, 'Unauthorized. Admin, Staff, Doctor, or Manager access required.');
}

$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

switch ($method) {
    case 'GET':
        getProfile();
        break;
    case 'PUT':
        updateProfile();
        break;
    case 'POST':
        if ($action === 'upload_photo') {
            uploadPhoto();
        } elseif ($action === 'request_password_otp') {
            requestPasswordChangeOtp();
        } elseif ($action === 'confirm_password_otp') {
            confirmPasswordChangeOtp();
        } else {
            jsonResponse(false, 'Invalid action.');
        }
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Get profile data for logged-in admin/staff/doctor
 */
function getProfile(): void
{
    global $pdo;

    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }

    $tenantId = trim((string) ($_SESSION['tenant_id'] ?? ''));
    if ($tenantId === '') {
        jsonResponse(false, 'Tenant context missing.');
    }

    try {
        $stmt = $pdo->prepare('SELECT user_id, username, email, full_name, photo FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }

        $isDentist = admin_profile_is_dentist_session();

        if ($isDentist) {
            $dentist = admin_profile_resolve_dentist_row($pdo, $tenantId, (string) ($user['email'] ?? ''));
            if (!$dentist) {
                jsonResponse(false, 'Dentist profile is not linked to this account. Contact your clinic administrator.');
            }
            $displayId = admin_profile_ensure_dentist_display_id($pdo, $tenantId, $dentist);
            if ($displayId !== '') {
                $dentist['dentist_display_id'] = $displayId;
            }

            $firstName = (string) ($dentist['first_name'] ?? '');
            $lastName = (string) ($dentist['last_name'] ?? '');
            if ($firstName === '' && $lastName === '') {
                $full = trim((string) ($user['full_name'] ?? ''));
                if ($full !== '') {
                    $parts = preg_split('/\s+/', $full, 2, PREG_SPLIT_NO_EMPTY);
                    $firstName = (string) ($parts[0] ?? '');
                    $lastName = (string) ($parts[1] ?? '');
                }
            }

            $dentRel = trim((string) ($dentist['profile_image'] ?? ''));
            $userPhoto = trim((string) ($user['photo'] ?? ''));
            $primaryImg = $dentRel !== '' ? $dentRel : $userPhoto;
            $photoUrl = admin_profile_image_public_url($primaryImg !== '' ? $primaryImg : null);

            $payload = [
                'profile_kind' => 'dentist',
                'dentist_table_pk' => (int) ($dentist['dentist_id'] ?? 0),
                'dentist_display_id' => trim((string) ($dentist['dentist_display_id'] ?? '')),
                'staff_table_id' => 0,
                'staff_display_id' => '',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'contact_number' => (string) ($dentist['contact_number'] ?? ''),
                'gender' => '',
                'house_street' => '',
                'barangay' => '',
                'city_municipality' => '',
                'province' => '',
                'profile_image' => $primaryImg,
                'profile_image_url' => $photoUrl,
                'username' => (string) ($user['username'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'full_name' => trim((string) ($user['full_name'] ?? '')),
                'user_photo' => $userPhoto,
                'source' => 'dentists',
            ];

            jsonResponse(true, 'Profile retrieved successfully.', $payload);
        }

        $stmt = $pdo->prepare('SELECT * FROM tbl_staffs WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        $firstName = '';
        $lastName = '';
        if (is_array($staff)) {
            $firstName = (string) ($staff['first_name'] ?? '');
            $lastName = (string) ($staff['last_name'] ?? '');
        }
        if ($firstName === '' && $lastName === '') {
            $full = trim((string) ($user['full_name'] ?? ''));
            if ($full !== '') {
                $parts = preg_split('/\s+/', $full, 2, PREG_SPLIT_NO_EMPTY);
                $firstName = (string) ($parts[0] ?? '');
                $lastName = (string) ($parts[1] ?? '');
            }
        }

        $staffRel = is_array($staff) ? (string) ($staff['profile_image'] ?? '') : '';
        $userPhoto = trim((string) ($user['photo'] ?? ''));
        $primaryImg = $staffRel !== '' ? $staffRel : $userPhoto;
        $photoUrl = admin_profile_image_public_url($primaryImg !== '' ? $primaryImg : null);

        $payload = [
            'profile_kind' => 'staff',
            'dentist_table_pk' => 0,
            'dentist_display_id' => '',
            'staff_table_id' => is_array($staff) ? (int) ($staff['id'] ?? 0) : 0,
            'staff_display_id' => is_array($staff) ? (string) ($staff['staff_id'] ?? '') : '',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'contact_number' => is_array($staff) ? (string) ($staff['contact_number'] ?? '') : '',
            'gender' => is_array($staff) ? (string) ($staff['gender'] ?? '') : '',
            'house_street' => is_array($staff) ? (string) ($staff['house_street'] ?? '') : '',
            'barangay' => is_array($staff) ? (string) ($staff['barangay'] ?? '') : '',
            'city_municipality' => is_array($staff) ? (string) ($staff['city_municipality'] ?? '') : '',
            'province' => is_array($staff) ? (string) ($staff['province'] ?? '') : '',
            'profile_image' => $primaryImg,
            'profile_image_url' => $photoUrl,
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'full_name' => trim((string) ($user['full_name'] ?? '')),
            'user_photo' => $userPhoto,
            'source' => is_array($staff) ? 'staffs' : 'users',
        ];

        jsonResponse(true, 'Profile retrieved successfully.', $payload);
    } catch (Throwable $e) {
        error_log('Get Admin Profile Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to retrieve profile.');
    }
}

/**
 * Update profile (personal details only — password uses email OTP flow)
 */
function updateProfile(): void
{
    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode((string) $raw, true);
    if (!is_array($input)) {
        $input = [];
    }

    if (isset($input['update_type']) && $input['update_type'] === 'password') {
        updatePasswordDirect($userId, $input);
        return;
    }

    updatePersonalDetails($userId, $input);
}

/**
 * Legacy direct password change (used by AdminMyProfile.php PUT).
 * Staff portal uses request_password_otp + confirm_password_otp instead.
 */
function updatePasswordDirect(string $userId, array $input): void
{
    global $pdo;

    $tenantId = trim((string) ($_SESSION['tenant_id'] ?? ''));
    if ($tenantId === '') {
        jsonResponse(false, 'Tenant context missing.');
    }

    $currentPassword = (string) ($input['current_password'] ?? '');
    $newPassword = (string) ($input['new_password'] ?? '');
    $confirmPassword = (string) ($input['confirm_password'] ?? '');

    if ($currentPassword === '') {
        jsonResponse(false, 'Current password is required.');
    }
    if ($newPassword === '') {
        jsonResponse(false, 'New password is required.');
    }
    $pwErr = admin_profile_validate_new_password($newPassword);
    if ($pwErr !== null) {
        jsonResponse(false, $pwErr);
    }
    if ($newPassword !== $confirmPassword) {
        jsonResponse(false, 'New password and confirm password do not match.');
    }

    try {
        $stmt = $pdo->prepare('SELECT password_hash FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            jsonResponse(false, 'Current password is incorrect.');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE tbl_users SET password_hash = ?, updated_at = NOW() WHERE user_id = ? AND tenant_id = ?');
        $stmt->execute([$hashedPassword, $userId, $tenantId]);

        jsonResponse(true, 'Password updated successfully.');
    } catch (Throwable $e) {
        error_log('updatePasswordDirect: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update password.');
    }
}

/**
 * Update personal details in tbl_staffs and tbl_users
 */
function updatePersonalDetails(string $userId, array $input): void
{
    global $pdo;

    $tenantId = trim((string) ($_SESSION['tenant_id'] ?? ''));
    if ($tenantId === '') {
        jsonResponse(false, 'Tenant context missing.');
    }

    try {
        $stmt = $pdo->prepare('SELECT user_id, username, email FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $existingUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingUserRow) {
            jsonResponse(false, 'User not found.');
        }

        $staffData = [
            'first_name' => sanitize($input['first_name'] ?? ''),
            'last_name' => sanitize($input['last_name'] ?? ''),
            'contact_number' => sanitize($input['contact_number'] ?? ''),
            'gender' => sanitize($input['gender'] ?? ''),
            'house_street' => sanitize($input['house_street'] ?? ''),
            'barangay' => sanitize($input['barangay'] ?? ''),
            'city_municipality' => sanitize($input['city_municipality'] ?? ''),
            'province' => sanitize($input['province'] ?? ''),
        ];

        $emailIn = isset($input['email']) ? trim(sanitize($input['email'])) : '';
        $usernameIn = isset($input['username']) ? trim(sanitize($input['username'])) : '';
        $accountData = [
            'email' => $emailIn !== '' ? $emailIn : trim((string) ($existingUserRow['email'] ?? '')),
            'username' => $usernameIn !== '' ? $usernameIn : trim((string) ($existingUserRow['username'] ?? '')),
        ];

        if ($staffData['first_name'] === '') {
            jsonResponse(false, 'First name is required.');
        }
        if ($staffData['last_name'] === '') {
            jsonResponse(false, 'Last name is required.');
        }
        if ($accountData['email'] === '' || !filter_var($accountData['email'], FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'A valid email address is required.');
        }
        if ($accountData['username'] === '') {
            jsonResponse(false, 'Username is required.');
        }

        $stmt = $pdo->prepare('SELECT user_id FROM tbl_users WHERE tenant_id = ? AND LOWER(TRIM(username)) = LOWER(TRIM(?)) AND user_id != ? LIMIT 1');
        $stmt->execute([$tenantId, $accountData['username'], $userId]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'That username is already taken in this clinic.');
        }

        $stmt = $pdo->prepare('SELECT user_id FROM tbl_users WHERE tenant_id = ? AND LOWER(TRIM(email)) = LOWER(TRIM(?)) AND user_id != ? LIMIT 1');
        $stmt->execute([$tenantId, $accountData['email'], $userId]);
        if ($stmt->fetch()) {
            jsonResponse(false, 'That email is already registered to another account in this clinic.');
        }

        $fullName = trim($staffData['first_name'] . ' ' . $staffData['last_name']);
        if (strlen($fullName) > 255) {
            jsonResponse(false, 'Full name is too long.');
        }

        if (admin_profile_is_dentist_session()) {
            $dentist = admin_profile_resolve_dentist_row($pdo, $tenantId, (string) ($existingUserRow['email'] ?? ''));
            if (!$dentist) {
                jsonResponse(false, 'Dentist profile is not linked to this account.');
            }
            $dentistPk = (int) ($dentist['dentist_id'] ?? 0);
            if ($dentistPk < 1) {
                jsonResponse(false, 'Invalid dentist profile.');
            }
            admin_profile_ensure_dentist_display_id($pdo, $tenantId, $dentist);

            $dentistEmail = trim($accountData['email']);
            $stmt = $pdo->prepare('
                UPDATE tbl_dentists SET
                    first_name = ?,
                    last_name = ?,
                    email = ?
                WHERE dentist_id = ? AND tenant_id = ?
            ');
            $stmt->execute([
                $staffData['first_name'],
                $staffData['last_name'],
                $dentistEmail,
                $dentistPk,
                $tenantId,
            ]);

            $stmt = $pdo->prepare('
                UPDATE tbl_users SET
                    email = ?,
                    username = ?,
                    full_name = ?,
                    updated_at = NOW()
                WHERE user_id = ? AND tenant_id = ?
            ');
            $stmt->execute([
                $accountData['email'],
                $accountData['username'],
                $fullName,
                $userId,
                $tenantId,
            ]);

            $_SESSION['user_name'] = $fullName;
            $_SESSION['user_email'] = $accountData['email'];

            jsonResponse(true, 'Profile updated successfully.');
        }

        $stmt = $pdo->prepare('SELECT id FROM tbl_staffs WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $existingStaff = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingStaff) {
            $stmt = $pdo->prepare('
                UPDATE tbl_staffs SET
                    first_name = ?,
                    last_name = ?,
                    contact_number = ?,
                    gender = ?,
                    house_street = ?,
                    barangay = ?,
                    city_municipality = ?,
                    province = ?,
                    updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ');
            $stmt->execute([
                $staffData['first_name'],
                $staffData['last_name'],
                $staffData['contact_number'] !== '' ? $staffData['contact_number'] : null,
                $staffData['gender'] !== '' ? $staffData['gender'] : null,
                $staffData['house_street'] !== '' ? $staffData['house_street'] : null,
                $staffData['barangay'] !== '' ? $staffData['barangay'] : null,
                $staffData['city_municipality'] !== '' ? $staffData['city_municipality'] : null,
                $staffData['province'] !== '' ? $staffData['province'] : null,
                (int) $existingStaff['id'],
                $tenantId,
            ]);
        } else {
            $year = date('Y');
            $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_staffs WHERE tenant_id = ? AND staff_id LIKE ?');
            $stmt->execute([$tenantId, 'S-' . $year . '-%']);
            $countRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $sequence = str_pad((string) (((int) ($countRow['c'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT);
            $staffDisplayId = 'S-' . $year . '-' . $sequence;

            $stmt = $pdo->prepare('
                INSERT INTO tbl_staffs (
                    tenant_id, staff_id, user_id, first_name, last_name, contact_number,
                    gender, house_street, barangay, city_municipality, province, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $tenantId,
                $staffDisplayId,
                $userId,
                $staffData['first_name'],
                $staffData['last_name'],
                $staffData['contact_number'] !== '' ? $staffData['contact_number'] : null,
                $staffData['gender'] !== '' ? $staffData['gender'] : null,
                $staffData['house_street'] !== '' ? $staffData['house_street'] : null,
                $staffData['barangay'] !== '' ? $staffData['barangay'] : null,
                $staffData['city_municipality'] !== '' ? $staffData['city_municipality'] : null,
                $staffData['province'] !== '' ? $staffData['province'] : null,
            ]);
        }

        $stmt = $pdo->prepare('
            UPDATE tbl_users SET
                email = ?,
                username = ?,
                full_name = ?,
                updated_at = NOW()
            WHERE user_id = ? AND tenant_id = ?
        ');
        $stmt->execute([
            $accountData['email'],
            $accountData['username'],
            $fullName,
            $userId,
            $tenantId,
        ]);

        $_SESSION['user_name'] = $fullName;
        $_SESSION['user_email'] = $accountData['email'];

        jsonResponse(true, 'Profile updated successfully.');
    } catch (Throwable $e) {
        error_log('Update Admin Profile Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update profile.');
    }
}

/**
 * Step 1: verify current password & new password rules, store new hash in session, email 6-digit OTP
 */
function requestPasswordChangeOtp(): void
{
    global $pdo;

    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }

    $tenantId = trim((string) ($_SESSION['tenant_id'] ?? ''));
    if ($tenantId === '') {
        jsonResponse(false, 'Tenant context missing.');
    }

    if (!function_exists('send_otp_email')) {
        jsonResponse(false, 'Email is not configured on this server.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode((string) $raw, true);
    if (!is_array($input)) {
        $input = [];
    }

    $currentPassword = (string) ($input['current_password'] ?? '');
    $newPassword = (string) ($input['new_password'] ?? '');
    $confirmPassword = (string) ($input['confirm_password'] ?? '');

    if ($currentPassword === '') {
        jsonResponse(false, 'Current password is required.');
    }
    if ($newPassword === '') {
        jsonResponse(false, 'New password is required.');
    }
    $pwErr = admin_profile_validate_new_password($newPassword);
    if ($pwErr !== null) {
        jsonResponse(false, $pwErr);
    }
    if ($newPassword !== $confirmPassword) {
        jsonResponse(false, 'New password and confirmation do not match.');
    }

    try {
        $stmt = $pdo->prepare('SELECT user_id, email, password_hash FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            jsonResponse(false, 'User not found.');
        }
        if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            jsonResponse(false, 'Current password is incorrect.');
        }

        $emailTo = trim((string) ($user['email'] ?? ''));
        if ($emailTo === '' || !filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(false, 'Your account does not have a valid email address for verification.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $_SESSION['staff_profile_pw_change'] = [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'new_hash' => $newHash,
            'expires_at' => time() + 900,
        ];

        $otp_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otp_hash = password_hash($otp_code, PASSWORD_DEFAULT);
        $otp_expires = date('Y-m-d H:i:s', time() + 900);

        $stmt = $pdo->prepare('SELECT id FROM tbl_email_verifications WHERE user_id = ? AND verified_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute([$userId]);
        $otp_row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($otp_row) {
            $stmt = $pdo->prepare('
                UPDATE tbl_email_verifications
                SET otp_hash = ?, otp_expires_at = ?, attempts = 0, last_sent_at = NOW(), token_hash = NULL, token_expires_at = NULL
                WHERE id = ?
            ');
            $stmt->execute([$otp_hash, $otp_expires, (int) $otp_row['id']]);
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO tbl_email_verifications (tenant_id, user_id, otp_hash, otp_expires_at, attempts, last_sent_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ');
            $stmt->execute([$tenantId, $userId, $otp_hash, $otp_expires]);
        }

        if (!send_otp_email($emailTo, $otp_code)) {
            unset($_SESSION['staff_profile_pw_change']);
            jsonResponse(false, 'Could not send the verification email. Please try again later.');
        }

        jsonResponse(true, 'We sent a 6-digit code to your registered email.', [
            'email_masked' => preg_replace('/(^.).*(@.*$)/', '$1***$2', $emailTo),
        ]);
    } catch (Throwable $e) {
        error_log('requestPasswordChangeOtp: ' . $e->getMessage());
        unset($_SESSION['staff_profile_pw_change']);
        jsonResponse(false, 'Could not start password verification. Please try again.');
    }
}

/**
 * Step 2: verify OTP and apply password hash from session
 */
function confirmPasswordChangeOtp(): void
{
    global $pdo;

    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode((string) $raw, true);
    if (!is_array($input)) {
        $input = [];
    }

    $otp_code = trim((string) ($input['otp_code'] ?? ''));
    if (strlen($otp_code) !== 6 || !ctype_digit($otp_code)) {
        jsonResponse(false, 'Enter the 6-digit code from your email.');
    }

    $pending = $_SESSION['staff_profile_pw_change'] ?? null;
    if (!is_array($pending)
        || ($pending['user_id'] ?? '') !== $userId
        || empty($pending['new_hash'])
        || (int) ($pending['expires_at'] ?? 0) < time()) {
        jsonResponse(false, 'Your verification session expired. Request a new code from Update Password.');
    }

    $matched_id = null;
    $matched = false;

    try {
        $stmt = $pdo->prepare('
            SELECT id, otp_hash, otp_expires_at FROM tbl_email_verifications
            WHERE user_id = ? AND verified_at IS NULL
            ORDER BY id DESC LIMIT 8
        ');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $exp = strtotime((string) ($row['otp_expires_at'] ?? ''));
            if ($exp !== false && $exp >= time() && password_verify($otp_code, (string) ($row['otp_hash'] ?? ''))) {
                $matched = true;
                $matched_id = (int) $row['id'];
                break;
            }
        }

        if (!$matched) {
            $pdo->prepare('UPDATE tbl_email_verifications SET attempts = attempts + 1 WHERE user_id = ? AND verified_at IS NULL')
                ->execute([$userId]);
            jsonResponse(false, 'Invalid or expired code.');
        }

        if ($matched_id !== null) {
            $pdo->prepare('UPDATE tbl_email_verifications SET verified_at = NOW() WHERE id = ?')->execute([$matched_id]);
        }

        $tenantId = trim((string) ($pending['tenant_id'] ?? ''));
        $newHash = (string) ($pending['new_hash'] ?? '');
        if ($tenantId === '' || $newHash === '') {
            unset($_SESSION['staff_profile_pw_change']);
            jsonResponse(false, 'Verification data was lost. Please start again.');
        }

        $stmt = $pdo->prepare('UPDATE tbl_users SET password_hash = ?, updated_at = NOW() WHERE user_id = ? AND tenant_id = ?');
        $stmt->execute([$newHash, $userId, $tenantId]);

        unset($_SESSION['staff_profile_pw_change']);

        jsonResponse(true, 'Your password was updated successfully.');
    } catch (Throwable $e) {
        error_log('confirmPasswordChangeOtp: ' . $e->getMessage());
        jsonResponse(false, 'Could not update your password. Please try again.');
    }
}

/**
 * Upload profile photo (also syncs tbl_users.photo)
 */
function uploadPhoto(): void
{
    global $pdo;

    $userId = getCurrentUserId();
    if (!$userId) {
        jsonResponse(false, 'User not logged in.');
    }

    $tenantId = trim((string) ($_SESSION['tenant_id'] ?? ''));
    if ($tenantId === '') {
        jsonResponse(false, 'Tenant context missing.');
    }

    $raw = file_get_contents('php://input');
    $input = json_decode((string) $raw, true);
    if (!is_array($input) || empty($input['photo'])) {
        jsonResponse(false, 'No photo data provided.');
    }

    try {
        $stmt = $pdo->prepare('SELECT email, photo FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$userId, $tenantId]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$userRow) {
            jsonResponse(false, 'User not found.');
        }

        $oldPath = '';
        $isDentist = admin_profile_is_dentist_session();

        if ($isDentist) {
            $dentist = admin_profile_resolve_dentist_row($pdo, $tenantId, (string) ($userRow['email'] ?? ''));
            if (!$dentist) {
                jsonResponse(false, 'Dentist profile is not linked to this account.');
            }
            if (!empty($dentist['profile_image'])) {
                $oldPath = (string) $dentist['profile_image'];
            }
        } else {
            $stmt = $pdo->prepare('SELECT id, profile_image FROM tbl_staffs WHERE user_id = ? AND tenant_id = ? LIMIT 1');
            $stmt->execute([$userId, $tenantId]);
            $existingStaff = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingStaff && !empty($existingStaff['profile_image'])) {
                $oldPath = (string) $existingStaff['profile_image'];
            }
        }

        if ($oldPath === '' && !empty($userRow['photo'])) {
            $oldPath = (string) $userRow['photo'];
        }

        $uploadDir = $isDentist ? 'uploads/dentists/' : 'uploads/staffs/';
        $uploadPrefix = $isDentist ? 'dentist_' : 'staff_';
        $photoResult = saveBase64Image($input['photo'], $uploadDir, $uploadPrefix);
        if (!$photoResult['success']) {
            jsonResponse(false, !empty($photoResult['message']) ? $photoResult['message'] : 'Failed to upload photo.');
        }

        $profileImagePath = (string) ($photoResult['filepath'] ?? '');
        if ($profileImagePath === '') {
            jsonResponse(false, 'Failed to save image.');
        }

        if ($oldPath !== '' && strpos($oldPath, 'http') !== 0) {
            $abs = ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($oldPath, '/\\'));
            if (is_file($abs)) {
                @unlink($abs);
            }
        }

        if ($isDentist) {
            $dentistPk = (int) ($dentist['dentist_id'] ?? 0);
            admin_profile_ensure_dentist_display_id($pdo, $tenantId, $dentist);
            $stmt = $pdo->prepare('UPDATE tbl_dentists SET profile_image = ? WHERE dentist_id = ? AND tenant_id = ?');
            $stmt->execute([$profileImagePath, $dentistPk, $tenantId]);
        } else {
            $stmt = $pdo->prepare('SELECT id, profile_image FROM tbl_staffs WHERE user_id = ? AND tenant_id = ? LIMIT 1');
            $stmt->execute([$userId, $tenantId]);
            $existingStaff = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingStaff) {
                $stmt = $pdo->prepare('UPDATE tbl_staffs SET profile_image = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?');
                $stmt->execute([$profileImagePath, (int) $existingStaff['id'], $tenantId]);
            } else {
                $year = date('Y');
                $stmt = $pdo->prepare('SELECT COUNT(*) AS c FROM tbl_staffs WHERE tenant_id = ? AND staff_id LIKE ?');
                $stmt->execute([$tenantId, 'S-' . $year . '-%']);
                $countRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $sequence = str_pad((string) (((int) ($countRow['c'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT);
                $staffDisplayId = 'S-' . $year . '-' . $sequence;

                $stmt = $pdo->prepare('
                    INSERT INTO tbl_staffs (tenant_id, staff_id, user_id, profile_image, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ');
                $stmt->execute([$tenantId, $staffDisplayId, $userId, $profileImagePath]);
            }
        }

        $stmt = $pdo->prepare('UPDATE tbl_users SET photo = ?, updated_at = NOW() WHERE user_id = ? AND tenant_id = ?');
        $stmt->execute([$profileImagePath, $userId, $tenantId]);

        jsonResponse(true, 'Photo uploaded successfully.', [
            'profile_image' => $profileImagePath,
            'profile_image_url' => admin_profile_image_public_url($profileImagePath),
        ]);
    } catch (Throwable $e) {
        error_log('Upload Photo Error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to upload photo.');
    }
}
