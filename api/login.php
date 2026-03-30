<?php
// api/login.php - Mobile Login API for Dental App

header("Content-Type: application/json");

// Import database connection
require_once '../db.php';

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
                t.clinic_name as tenant_name,
                p.date_of_birth as birthdate,
                CONCAT_WS(', ', p.house_street, p.barangay, p.city_municipality) as address
            FROM tbl_users u
            LEFT JOIN tbl_tenants t ON u.tenant_id = t.tenant_id
            LEFT JOIN tbl_patients p ON u.user_id = p.linked_user_id
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
