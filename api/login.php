<?php
// api/login.php - Mobile Login API for Dento App

header("Content-Type: application/json");

// Import database connection
require_once '../db.php';

// Check if email and password are provided
if (!isset($_POST['email']) || !isset($_POST['password'])) {
    echo json_encode([
        "success" => false,
        "message" => "Email and password are required."
    ]);
    exit;
}

$email = $_POST['email'];
$password = $_POST['password'];

try {
    // 1. Find user by email
    // Note: Since this is a multi-tenant system, usually you'd need tenant_id, 
    // but for mobile login we'll search across all users for simplicity first.
    $stmt = $pdo->prepare("SELECT user_id, email, password_hash, full_name, role FROM tbl_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        // Success: Don't return the hash, just user info
        unset($user['password_hash']);
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
