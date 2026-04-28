<?php
// api/login.php - Mobile Login API for Dental App

header("Content-Type: application/json");

// Import database connection
require_once '../db.php';
require_once __DIR__ . '/profile_common.inc.php';

// Support both JSON input (from the app) and Form-Data input (standard PHP)
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? $input['login'] ?? $_POST['email'] ?? $_POST['login'] ?? null;
$password = $input['password'] ?? $_POST['password'] ?? null;

if (!$email || !$password) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit;
}

try {
    // 1. Find user by email and join with tenant info
    $sql = "SELECT 
                u.user_id, 
                u.tenant_id, 
                u.email, 
                u.password_hash, 
                u.full_name, 
                u.role, 
                u.phone,
                u.created_at AS registration_date,
                u.updated_at AS user_updated_at,
                t.clinic_name as tenant_name,
                p.patient_id AS patient_id,
                p.date_of_birth as birthdate,
                p.updated_at AS patient_updated_at,
                CONCAT_WS(', ', p.house_street, p.barangay, p.city_municipality) as address,
                p.profile_image AS patient_profile_image
            FROM tbl_users u
            LEFT JOIN tbl_tenants t ON u.tenant_id = t.tenant_id
            LEFT JOIN tbl_patients p
                ON p.tenant_id = u.tenant_id
                AND p.id = (
                    SELECT p2.id
                    FROM tbl_patients p2
                    WHERE p2.tenant_id = u.tenant_id
                        AND (p2.owner_user_id = u.user_id OR p2.linked_user_id = u.user_id)
                    ORDER BY (p2.linked_user_id = u.user_id) DESC, p2.id DESC
                    LIMIT 1
                )
            WHERE u.email = ? 
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // Success: Remove sensitive data
        unset($user['password_hash']);
        
        // Ensure strings match app expectations (null safety)
        $user['tenant_name'] = $user['tenant_name'] ?: "Your Clinic";
        $user['address'] = $user['address'] ?: "";
        $user['birthdate'] = $user['birthdate'] ?: "";
        $patImg = trim((string) ($user['patient_profile_image'] ?? ''));
        $user['patient_profile_image'] = $patImg;
        // Backward-compatible alias; now sourced from tbl_patients.profile_image only.
        $user['user_photo'] = $patImg;
        $user['profile_image'] = $patImg;

        $uU = (string) ($user['user_updated_at'] ?? '');
        $pU = (string) ($user['patient_updated_at'] ?? '');
        $user['last_profile_update'] = api_profile_last_update($uU, $pU !== '' ? $pU : null);
        $user['registration_date']   = (string) ($user['registration_date'] ?? '');
        unset($user['user_updated_at'], $user['patient_updated_at']);

        echo json_encode([
            "success" => true,
            "message" => "Login successful",
            "user" => $user
        ]);
    } else {
        // Failure
        echo json_encode([
            "success" => false,
            "message" => "Invalid email or password."
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
