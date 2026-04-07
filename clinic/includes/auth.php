<?php
/**
 * Authentication helpers for clinic template
 * Requires config.php and functions.php (for getDBConnection via database.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/tenant.php';

/**
 * Write an audit log row without interrupting auth flow.
 * @param string $tenantId
 * @param string|null $userId
 * @param string $action
 * @param string|null $description
 */
/**
 * Update last_login and last_active on successful sign-in (tbl_users).
 *
 * Called from:
 * - loginUser() after password login (clinic api/login.php — patient + staff/admin forms)
 * - ProviderLogin.php (provider portal)
 * - AdminLoginPage.php MyDental SSO (after this fix)
 *
 * Not updated: users who have never logged in; failed logins; superadmin-only flows that bypass tbl_users.
 */
function auth_update_user_last_activity($pdo, $userId) {
    $userId = trim((string) $userId);
    if ($userId === '') {
        return;
    }
    try {
        $st = $pdo->prepare('UPDATE tbl_users SET last_active = CURRENT_TIMESTAMP, last_login = CURRENT_TIMESTAMP WHERE user_id = ?');
        $st->execute([$userId]);
    } catch (Throwable $e) {
        try {
            $st = $pdo->prepare('UPDATE tbl_users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?');
            $st->execute([$userId]);
        } catch (Throwable $e2) {
            error_log('auth_update_user_last_activity: ' . $e2->getMessage());
        }
    }
}

function writeAuditLog($tenantId, $userId, $action, $description = null) {
    if (!function_exists('getDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    try {
        $tenantId = trim((string) $tenantId);
        if ($tenantId === '' || trim((string) $action) === '') {
            return;
        }
        $ipAddress = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = trim((string) explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = trim((string) $_SERVER['REMOTE_ADDR']);
        }

        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO tbl_audit_logs (tenant_id, user_id, action, description, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenantId,
            $userId !== null && trim((string) $userId) !== '' ? (string) $userId : null,
            (string) $action,
            $description !== null && trim((string) $description) !== '' ? (string) $description : null,
            $ipAddress !== '' ? $ipAddress : null
        ]);
    } catch (Throwable $e) {
        // Never block login/logout because of audit logging.
        error_log('Audit log write failed: ' . $e->getMessage());
    }
}

/**
 * Log out the current user: clear session and destroy it.
 */
function logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Get current logged-in user identifier (schema: tbl_users PK is user_id, so this returns user_id string).
 * @return string|null
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null;
}

/**
 * Check if user is logged in as one of the given types
 * @param string $type e.g. 'client', 'manager', 'doctor', 'staff'
 * @return bool
 */
function isLoggedIn($type) {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_type'])) {
        return false;
    }
    $allowed = is_array($type) ? $type : [$type];
    return in_array($_SESSION['user_type'], $allowed);
}

/**
 * Require admin role (manager, doctor, or staff). Redirect to AdminLoginPage if not.
 */
function requireAdmin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . clinicPageUrl('AdminLoginPage.php'));
        exit;
    }
    // All clinic admin pages must be tenant-scoped
    requireClinicTenantId();
    $allowed = ['manager', 'doctor', 'staff', 'admin'];
    if (!in_array($_SESSION['user_type'], $allowed)) {
        header('Location: ' . clinicPageUrl('AdminLoginPage.php'));
        exit;
    }
}

/**
 * Require logged in as the given type. Redirect to appropriate login page if not.
 * @param string $type e.g. 'manager', 'client'
 */
function requireLogin($type) {
    if (empty($_SESSION['user_id'])) {
        if ($type === 'client') {
            header('Location: ' . BASE_URL . 'Login.php');
        } else {
            header('Location: ' . clinicPageUrl('AdminLoginPage.php'));
        }
        exit;
    }
    // All clinic-side requests must have tenant context
    requireClinicTenantId();
    $allowed = is_array($type) ? $type : [$type];
    if (!in_array($_SESSION['user_type'], $allowed)) {
        if ($type === 'client') {
            header('Location: ' . BASE_URL . 'Login.php');
        } else {
            header('Location: ' . clinicPageUrl('AdminLoginPage.php'));
        }
        exit;
    }
}

/**
 * Names and staff display id for admin portal; dentists may only exist in tbl_dentists (matched by tenant + email).
 *
 * @return array{first_name: string, last_name: string, staff_id: string|null, has_profile: bool}
 */
function _auth_resolve_staff_portal_profile(PDO $pdo, string $tenantId, array $user): array {
    $uid = trim((string) ($user['user_id'] ?? ''));
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    $roleLower = strtolower((string) ($user['role'] ?? ''));

    $stmt2 = $pdo->prepare('
        SELECT staff_id, first_name, last_name FROM tbl_staffs
        WHERE tenant_id = ? AND user_id = ?
        LIMIT 1
    ');
    $stmt2->execute([$tenantId, $uid]);
    $staff = $stmt2->fetch(PDO::FETCH_ASSOC);
    $staffId = null;
    if (is_array($staff) && trim((string) ($staff['staff_id'] ?? '')) !== '') {
        $staffId = (string) $staff['staff_id'];
        return [
            'first_name' => (string) ($staff['first_name'] ?? ''),
            'last_name' => (string) ($staff['last_name'] ?? ''),
            'staff_id' => $staffId,
            'has_profile' => true,
        ];
    }

    if ($roleLower === 'dentist' && $email !== '') {
        $st = $pdo->prepare('
            SELECT first_name, last_name FROM tbl_dentists
            WHERE tenant_id = ? AND LOWER(TRIM(COALESCE(email, \'\'))) = ?
            LIMIT 1
        ');
        $st->execute([$tenantId, $email]);
        $d = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($d)) {
            return [
                'first_name' => (string) ($d['first_name'] ?? ''),
                'last_name' => (string) ($d['last_name'] ?? ''),
                'staff_id' => null,
                'has_profile' => true,
            ];
        }
    }

    return [
        'first_name' => '',
        'last_name' => '',
        'staff_id' => null,
        'has_profile' => false,
    ];
}

/**
 * Unified clinic-portal login: same credentials resolve to patient (client) or staff portal
 * by tbl_users.role within the current tenant. Superadmin cannot use this endpoint.
 *
 * @return array success, message, user, portal keys
 */
function _loginPortalUnified(PDO $pdo, $tenantId, $login, $password) {
    $tenantId = trim((string) $tenantId);
    $loginNormalized = strtolower(trim((string) $login));
    $stmt = $pdo->prepare("
        SELECT user_id, tenant_id, email, username, full_name, password_hash, role, status
        FROM tbl_users
        WHERE (LOWER(TRIM(COALESCE(email, ''))) = ? OR LOWER(TRIM(COALESCE(username, ''))) = ?)
          AND status = 'active'
          AND tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$loginNormalized, $loginNormalized, $tenantId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $fail = ['success' => false, 'message' => 'Invalid credentials.', 'user' => null, 'portal' => null];
    if (!$user) {
        return $fail;
    }

    if (!password_verify($password, $user['password_hash'] ?? '')) {
        return $fail;
    }

    $rawRole = strtolower((string) ($user['role'] ?? ''));
    if ($rawRole === 'superadmin') {
        return $fail;
    }

    if (trim((string) ($user['tenant_id'] ?? '')) !== $tenantId) {
        return $fail;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $userDbType = _authRoleToUserType($user['role'] ?? 'client');
    $adminTypes = ['manager', 'doctor', 'staff', 'admin'];

    if ($userDbType === 'client') {
        unset($_SESSION['staff_id']);
        $_SESSION['account_kind'] = 'patient';

        $stmt2 = $pdo->prepare("
            SELECT first_name, last_name FROM tbl_patients
            WHERE tenant_id = ? AND linked_user_id = ?
            LIMIT 1
        ");
        $stmt2->execute([$tenantId, $user['user_id']]);
        $profile = $stmt2->fetch(PDO::FETCH_ASSOC);
        $first_name = $profile ? (string) ($profile['first_name'] ?? '') : '';
        $last_name = $profile ? (string) ($profile['last_name'] ?? '') : '';
        if ($first_name === '' && $last_name === '') {
            $fullName = $user['full_name'] ?? $user['username'] ?? 'User';
            $parts = explode(' ', trim($fullName), 2);
            $first_name = $parts[0] ?? 'User';
            $last_name = $parts[1] ?? '';
        }

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = trim($first_name . ' ' . $last_name) ?: ($user['full_name'] ?? $user['username']);
        $_SESSION['user_email'] = (string) ($user['email'] ?? '');
        $_SESSION['user_type'] = 'client';
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['clinic_id'] = $tenantId;
        $_SESSION['user_role'] = (string) ($user['role'] ?? 'client');

        writeAuditLog($tenantId, (string) $user['user_id'], 'LOGIN', 'Patient portal login');
        auth_update_user_last_activity($pdo, (string) $user['user_id']);

        return [
            'success' => true,
            'message' => 'Login successful.',
            'portal' => 'patient',
            'user' => [
                'id' => $user['user_id'],
                'user_id' => $user['user_id'],
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $user['email'],
                'user_type' => 'client',
            ],
        ];
    }

    if (!in_array($userDbType, $adminTypes, true)) {
        return $fail;
    }

    $prof = _auth_resolve_staff_portal_profile($pdo, $tenantId, $user);
    $roleLower = strtolower((string) ($user['role'] ?? ''));

    if ($roleLower === 'staff' && !$prof['has_profile']) {
        return $fail;
    }
    if ($roleLower === 'dentist' && !$prof['has_profile']) {
        return $fail;
    }

    $first_name = $prof['first_name'];
    $last_name = $prof['last_name'];
    if ($first_name === '' && $last_name === '') {
        $fullName = $user['full_name'] ?? $user['username'] ?? 'User';
        $parts = explode(' ', trim($fullName), 2);
        $first_name = $parts[0] ?? 'User';
        $last_name = $parts[1] ?? '';
    }

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = trim($first_name . ' ' . $last_name) ?: ($user['full_name'] ?? $user['username']);
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
    $_SESSION['user_type'] = $userDbType;
    $_SESSION['tenant_id'] = $tenantId;
    $_SESSION['clinic_id'] = $tenantId;
    $_SESSION['user_role'] = (string) ($user['role'] ?? '');
    $_SESSION['account_kind'] = 'staff';
    $_SESSION['staff_id'] = ($prof['staff_id'] !== null && trim((string) $prof['staff_id']) !== '')
        ? (string) $prof['staff_id']
        : null;

    writeAuditLog(
        $tenantId,
        (string) $user['user_id'],
        'LOGIN',
        'Staff portal login as ' . $userDbType
    );
    auth_update_user_last_activity($pdo, (string) $user['user_id']);

    return [
        'success' => true,
        'message' => 'Login successful.',
        'portal' => 'staff',
        'user' => [
            'id' => $user['user_id'],
            'user_id' => $user['user_id'],
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $user['email'],
            'user_type' => $userDbType,
            'staff_id' => $_SESSION['staff_id'],
            'role' => $_SESSION['user_role'],
        ],
    ];
}

/**
 * Attempt login: find user by email or username, verify password, set session, return result.
 * @param string $login Email or username
 * @param string $password Plain password
 * @param string $userType Requested type: 'client', 'manager', 'doctor', 'staff', 'portal' (patient + staff auto)
 * @return array ['success' => bool, 'message' => string, 'user' => array|null, 'portal' => string|null]
 */
function loginUser($login, $password, $userType) {
    if (!function_exists('getDBConnection')) {
        require_once __DIR__ . '/../config/database.php';
    }
    $pdo = getDBConnection();
    $tenantId = requireClinicTenantId();

    $requestedType = strtolower((string) $userType);
    if ($requestedType === 'portal' || $requestedType === 'unified') {
        return _loginPortalUnified($pdo, $tenantId, $login, $password);
    }

    // Find by email or username (case-insensitive and trimmed)
    $loginNormalized = strtolower(trim((string) $login));
    $stmt = $pdo->prepare("
        SELECT user_id, email, username, full_name, password_hash, role, status
        FROM tbl_users
        WHERE (LOWER(TRIM(COALESCE(email, ''))) = ? OR LOWER(TRIM(COALESCE(username, ''))) = ?)
          AND status = 'active'
          AND tenant_id = ?
        LIMIT 1
    ");
    $stmt->execute([$loginNormalized, $loginNormalized, $tenantId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid credentials.', 'user' => null, 'portal' => null];
    }

    if (!password_verify($password, $user['password_hash'] ?? '')) {
        return ['success' => false, 'message' => 'Invalid credentials.', 'user' => null, 'portal' => null];
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // Map DB role to app user_type: dentist→doctor, tenant_owner→manager
    $userDbType = _authRoleToUserType($user['role'] ?? 'client');
    $adminTypes = ['manager', 'doctor', 'staff', 'admin'];
    if ($requestedType === 'client') {
        if ($userDbType !== 'client') {
            return ['success' => false, 'message' => 'This account is not a patient account.', 'user' => null, 'portal' => null];
        }
    } else {
        if (!in_array($userDbType, $adminTypes)) {
            return ['success' => false, 'message' => 'Access denied. Admin, Doctor, or Staff credentials required.', 'user' => null, 'portal' => null];
        }
    }

    // Get first_name, last_name from tbl_staffs / tbl_dentists / tbl_patients (tenant-scoped)
    $first_name = '';
    $last_name = '';
    $resolvedStaffId = null;
    if (in_array($userDbType, $adminTypes)) {
        $prof = _auth_resolve_staff_portal_profile($pdo, $tenantId, $user);
        $roleLower = strtolower((string) ($user['role'] ?? ''));
        if ($roleLower === 'staff' && !$prof['has_profile']) {
            return ['success' => false, 'message' => 'Invalid credentials.', 'user' => null, 'portal' => null];
        }
        if ($roleLower === 'dentist' && !$prof['has_profile']) {
            return ['success' => false, 'message' => 'Invalid credentials.', 'user' => null, 'portal' => null];
        }
        $first_name = $prof['first_name'];
        $last_name = $prof['last_name'];
        $resolvedStaffId = $prof['staff_id'];
    } else {
        $stmt2 = $pdo->prepare("
            SELECT first_name, last_name FROM tbl_patients
            WHERE tenant_id = ? AND linked_user_id = ?
            LIMIT 1
        ");
        $stmt2->execute([$tenantId, $user['user_id']]);
        $profile = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($profile) {
            $first_name = isset($profile['first_name']) ? $profile['first_name'] : '';
            $last_name = isset($profile['last_name']) ? $profile['last_name'] : '';
        }
    }
    if ($first_name === '' && $last_name === '') {
        $fullName = $user['full_name'] ?? $user['username'] ?? 'User';
        $parts = explode(' ', trim($fullName), 2);
        $first_name = $parts[0] ?? 'User';
        $last_name = $parts[1] ?? '';
    }

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = trim($first_name . ' ' . $last_name) ?: ($user['full_name'] ?? $user['username']);
    $_SESSION['user_email'] = (string) ($user['email'] ?? '');
    $_SESSION['user_type'] = $userDbType;
    $_SESSION['tenant_id'] = $tenantId;
    $_SESSION['clinic_id'] = $tenantId;
    $_SESSION['user_role'] = (string) ($user['role'] ?? '');
    if (in_array($userDbType, $adminTypes, true)) {
        $_SESSION['account_kind'] = 'staff';
        $_SESSION['staff_id'] = ($resolvedStaffId !== null && trim((string) $resolvedStaffId) !== '')
            ? (string) $resolvedStaffId
            : null;
    } else {
        unset($_SESSION['staff_id']);
        $_SESSION['account_kind'] = 'patient';
    }

    writeAuditLog(
        $tenantId,
        (string) $user['user_id'],
        'LOGIN',
        'User logged in as ' . $userDbType
    );

    auth_update_user_last_activity($pdo, (string) $user['user_id']);

    return [
        'success' => true,
        'message' => 'Login successful.',
        'portal' => $userDbType === 'client' ? 'patient' : 'staff',
        'user' => [
            'id' => $user['user_id'],
            'user_id' => $user['user_id'],
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $user['email'],
            'user_type' => $userDbType,
        ],
    ];
}

/**
 * Map schema tbl_users.role to app user_type (dentist→doctor, tenant_owner→manager).
 * @param string $role DB role: tenant_owner|manager|staff|dentist|client
 * @return string user_type: manager|doctor|staff|admin|client
 */
function _authRoleToUserType($role) {
    $r = strtolower((string) $role);
    if ($r === 'dentist') return 'doctor';
    if ($r === 'tenant_owner') return 'manager';
    return $r;
}

/**
 * Map app user_type to schema tbl_users.role (doctor→dentist, admin→manager).
 * @param string $userType manager|doctor|staff|admin|client
 * @return string role for DB
 */
function _authUserTypeToRole($userType) {
    $t = strtolower((string) $userType);
    if ($t === 'doctor') return 'dentist';
    if ($t === 'admin') return 'manager';
    return $t;
}
