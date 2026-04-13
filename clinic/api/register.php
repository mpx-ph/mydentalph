<?php
/**
 * Registration API Endpoint
 */

// Suppress error display for API (return JSON instead)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any errors
ob_start();

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/tenant.php';
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Clear any output that might have been generated
    ob_clean();
    
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method.');
    }
    // Ensure request is scoped to a tenant (public slug or clinic session)
    $tenantId = requireClinicTenantId();
    
    // Handle both FormData (multipart/form-data) and JSON
    $input = [];
    if (!empty($_POST)) {
        // FormData was sent
        $input = $_POST;
    } else {
        // Try to read JSON from input
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput)) {
            $input = json_decode($rawInput, true) ?? [];
        }
    }
    
    $data = [
        'first_name' => sanitize($input['first_name'] ?? ''),
        'last_name' => sanitize($input['last_name'] ?? ''),
        'email' => sanitize($input['email'] ?? ''),
        'username' => sanitize($input['username'] ?? ''),
        'password' => $input['password'] ?? '',
        'mobile' => sanitize($input['mobile'] ?? ''),
        'date_of_birth' => sanitize($input['date_of_birth'] ?? ''),
        // Force client role for this public registration endpoint
        'user_type' => 'client',
        // Explicitly pass tenant context so registerUser can tenant-scope the account
        'tenant_id' => $tenantId,
    ];
    
    $result = registerPublicClient($data);
    
    if ($result['success']) {
        jsonResponse(true, $result['message'], [
            'user_id' => $result['user_id'],
            'email' => $result['email'] ?? $data['email']
        ]);
    } else {
        jsonResponse(false, $result['message']);
    }
    
} catch (Throwable $e) {
    // Clear any output
    ob_clean();
    
    // Log the error
    error_log('Registration API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return JSON error (include message to help diagnose during development)
    header('Content-Type: application/json');
    jsonResponse(false, 'Registration failed: ' . $e->getMessage());
}

/**
 * Public client registration used by RegisterClient.php
 * Creates a tenant-scoped user row and a matching patients row.
 *
 * @param array $data
 * @return array ['success' => bool, 'message' => string, 'user_id' => string|null, 'email' => string|null]
 */
function registerPublicClient(array $data) {
    $pdo = getDBConnection();
    $tenantId = $data['tenant_id'] ?? null;
    if (empty($tenantId)) {
        return [
            'success' => false,
            'message' => 'Tenant is required.',
            'user_id' => null,
            'email' => null,
        ];
    }

    // Basic required fields
    $required = ['first_name', 'last_name', 'email', 'username', 'password', 'date_of_birth'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return [
                'success' => false,
                'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.',
                'user_id' => null,
                'email' => null,
            ];
        }
    }

    // Age check (18+ as enforced by the frontend)
    if (!empty($data['date_of_birth'])) {
        try {
            $birthDate = new DateTime($data['date_of_birth']);
            $today = new DateTime();
            if ($birthDate > $today) {
                return [
                    'success' => false,
                    'message' => 'Date of birth cannot be in the future.',
                    'user_id' => null,
                    'email' => null,
                ];
            }
            $age = $today->diff($birthDate)->y;
            if ($age < 18) {
                return [
                    'success' => false,
                    'message' => 'You must be 18 years or older to create an account.',
                    'user_id' => null,
                    'email' => null,
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Invalid date of birth.',
                'user_id' => null,
                'email' => null,
            ];
        }
    }

    // Email validation
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid email format.',
            'user_id' => null,
            'email' => null,
        ];
    }

    // Password rules (mirror front-end)
    if (strlen($data['password']) < (defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8)) {
        return [
            'success' => false,
            'message' => 'Password must be at least ' . (defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8) . ' characters.',
            'user_id' => null,
            'email' => null,
        ];
    }
    $hasUpper = preg_match('/[A-Z]/', $data['password']);
    $hasLower = preg_match('/[a-z]/', $data['password']);
    $hasNumber = preg_match('/[0-9]/', $data['password']);
    $hasSpecial = preg_match('/[^a-zA-Z0-9]/', $data['password']);
    if (!$hasUpper || !$hasLower || !$hasNumber || !$hasSpecial) {
        return [
            'success' => false,
            'message' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'user_id' => null,
            'email' => null,
        ];
    }

    // Check uniqueness within tenant in provider tbl_users
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE email = ? AND tenant_id = ?");
    $stmt->execute([$data['email'], $tenantId]);
    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => 'Email already registered.',
            'user_id' => null,
            'email' => null,
        ];
    }
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ? AND tenant_id = ?");
    $stmt->execute([$data['username'], $tenantId]);
    if ($stmt->fetch()) {
        return [
            'success' => false,
            'message' => 'Username already taken.',
            'user_id' => null,
            'email' => null,
        ];
    }

    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

    // Generate provider-level user_id (numeric USER_* only; MAX(varchar) mixes P-/M- style ids)
    $stmt = $pdo->query("SELECT COALESCE(MAX(CAST(SUBSTRING(user_id, 6) AS UNSIGNED)), 0) FROM tbl_users WHERE user_id REGEXP '^USER_[0-9]+$'");
    $num = (int) $stmt->fetchColumn() + 1;
    $userId = 'USER_' . str_pad((string) $num, 5, '0', STR_PAD_LEFT);

    // Public signups in provider tbl_users: mark as active client for this tenant
    $status = 'active';
    $role = 'client';

    try {
        $pdo->beginTransaction();

        // Insert into provider-level tbl_users (tenant-scoped)
        $fullName = trim($data['first_name'] . ' ' . $data['last_name']);
        $stmt = $pdo->prepare("
            INSERT INTO tbl_users (
                user_id, tenant_id, username, email, full_name, phone, password_hash, role, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $tenantId,
            $data['username'],
            $data['email'],
            $fullName,
            !empty($data['mobile']) ? $data['mobile'] : null,
            $hashedPassword,
            $role,
            $status,
        ]);

        // Create minimal self patient profile for this client (tenant-scoped)
        // Generate patient_id based on existing tbl_patients IDs
        $year = date('Y');
        $stmt = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE patient_id LIKE ? ORDER BY patient_id DESC LIMIT 1");
        $stmt->execute(["P-{$year}-%"]);
        $lastPatientId = $stmt->fetchColumn();
        if ($lastPatientId) {
            $parts = explode('-', $lastPatientId);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        $patientDisplayId = 'P-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
        // Keep patient table synced using only applicable shared profile fields
        // from the same registration payload used for tbl_users.
        $syncedPatientFields = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'contact_number' => !empty($data['mobile']) ? $data['mobile'] : null, // tbl_users.phone equivalent
            'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
        ];

        $stmt = $pdo->prepare("
            INSERT INTO tbl_patients (
                tenant_id, patient_id, owner_user_id, linked_user_id, first_name, last_name, contact_number, date_of_birth, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $tenantId,
            $patientDisplayId,
            $userId, // owner_user_id
            $userId, // linked_user_id
            $syncedPatientFields['first_name'],
            $syncedPatientFields['last_name'],
            $syncedPatientFields['contact_number'],
            $syncedPatientFields['date_of_birth'],
        ]);

        $pdo->commit();

        // Insert into clinic users and patients so client can log in (no email verification)
        $clinicUserId = null;
        $clinicUserDbId = null;
        try {
            $clinicUserId = generateUserId('client');
            $clinicPatientId = generatePatientId();
            $stmt = $pdo->prepare("
                INSERT INTO tbl_users (
                    tenant_id, user_id, email, username, full_name, password_hash, role, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'client', 'active', NOW())
            ");
            $fullName = trim($data['first_name'] . ' ' . $data['last_name']) ?: $data['username'];
            $stmt->execute([
                $tenantId,
                $clinicUserId,
                $data['email'],
                $data['username'],
                $fullName,
                $hashedPassword,
            ]);
            $clinicUserDbId = $clinicUserId; // schema PK is user_id (string)

            $stmt = $pdo->prepare("
                INSERT INTO patients (
                    tenant_id, patient_id, owner_user_id, linked_user_id, first_name, last_name, contact_number, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $tenantId,
                $clinicPatientId,
                $clinicUserId,
                $clinicUserId,
                $data['first_name'],
                $data['last_name'],
                !empty($data['mobile']) ? $data['mobile'] : null,
            ]);
        } catch (Exception $e) {
            error_log('Clinic users/patients insert after registration: ' . $e->getMessage());
            // Continue without session; user can still log in manually
        }

        // Log the user in immediately (no email verification); session holds user_id (string)
        if ($clinicUserDbId) {
            $_SESSION['user_id'] = $clinicUserDbId;
            $_SESSION['user_name'] = trim($data['first_name'] . ' ' . $data['last_name']) ?: $data['username'];
            $_SESSION['user_type'] = 'client';
            $_SESSION['tenant_id'] = $tenantId;
        }

        return [
            'success' => true,
            'message' => 'Registration successful! Redirecting to home.',
            'user_id' => $userId,
            'email' => $data['email'],
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Public client registration error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to register: ' . $e->getMessage(),
            'user_id' => null,
            'email' => null,
        ];
    }
}

