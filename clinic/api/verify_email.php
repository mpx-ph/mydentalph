<?php
/**
 * Email Verification API Endpoint
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

// Handle both FormData and JSON
$input = [];
if (!empty($_POST)) {
    $input = $_POST;
} else {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $input = json_decode($rawInput, true) ?? [];
    }
}

$email = sanitize($input['email'] ?? '');
$code = sanitize($input['code'] ?? '');

if (empty($email) || empty($code)) {
    jsonResponse(false, 'Email and verification code are required.');
}

try {
    $pdo = getDBConnection();
    
    // Find user with matching email and verification code (schema: tbl_users PK is user_id)
    $stmt = $pdo->prepare("
        SELECT user_id, email, verification_code, verification_expires_at, email_verified_at
        FROM tbl_users
        WHERE email = ? AND verification_code = ? AND status = 'inactive'
    ");
    $stmt->execute([$email, $code]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(false, 'Invalid verification code or email address.');
    }
    
    // Check if already verified
    if (!empty($user['email_verified_at'])) {
        jsonResponse(false, 'This email has already been verified. You can now login.');
    }
    
    // Check if code has expired
    $now = new DateTime();
    $expiresAt = new DateTime($user['verification_expires_at']);
    
    if ($now > $expiresAt) {
        jsonResponse(false, 'Verification code has expired. Please request a new code.');
    }
    
    // Verify the email
    $stmt = $pdo->prepare("
        UPDATE tbl_users
        SET email_verified_at = NOW(),
            verification_code = NULL,
            verification_expires_at = NULL,
            status = 'active',
            updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    
    jsonResponse(true, 'Email verified successfully! You can now login.', [
        'email' => $user['email']
    ]);
    
} catch (PDOException $e) {
    error_log('Verify Email API PDO Error: ' . $e->getMessage());
    jsonResponse(false, 'Database error. Please try again.');
} catch (Exception $e) {
    error_log('Verify Email API Error: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again.');
}
