<?php
/**
 * Resend Verification Code API Endpoint
 * Includes rate limiting to prevent abuse
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

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

if (empty($email)) {
    jsonResponse(false, 'Email address is required.');
}

try {
    $pdo = getDBConnection();
    
    // Find user (schema: tbl_users PK is user_id; use tbl_patients for name)
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.email, u.full_name, u.verification_code, u.verification_expires_at, u.email_verified_at, u.status,
               p.first_name, p.last_name
        FROM tbl_users u
        LEFT JOIN tbl_patients p ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id
        WHERE u.email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal if email exists for security
        jsonResponse(false, 'If this email is registered, a verification code will be sent.');
    }
    
    // Check if already verified
    if ($user['email_verified_at'] !== null) {
        jsonResponse(false, 'This email has already been verified. You can now login.');
    }
    
    // Rate limiting: Check if code was sent recently
    if ($user['verification_expires_at'] !== null) {
        $expiresAt = new DateTime($user['verification_expires_at']);
        $now = new DateTime();
        $secondsUntilExpiry = $expiresAt->getTimestamp() - $now->getTimestamp();
        
        // Calculate when the code was created (expires_at - EXPIRY_TIME)
        $codeCreatedAt = $expiresAt->getTimestamp() - VERIFICATION_CODE_EXPIRY;
        $secondsSinceCodeCreated = $now->getTimestamp() - $codeCreatedAt;
        
        // If code was created less than COOLDOWN seconds ago, block resend
        if ($secondsSinceCodeCreated < RESEND_VERIFICATION_COOLDOWN) {
            $remainingSeconds = RESEND_VERIFICATION_COOLDOWN - $secondsSinceCodeCreated;
            jsonResponse(false, "Please wait {$remainingSeconds} seconds before requesting a new code.");
        }
    }
    
    // Generate new verification code
    $verificationCode = generateVerificationCode();
    $verificationExpires = date('Y-m-d H:i:s', time() + VERIFICATION_CODE_EXPIRY);
    
    // Update user with new code
    $stmt = $pdo->prepare("
        UPDATE tbl_users
        SET verification_code = ?,
            verification_expires_at = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->execute([$verificationCode, $verificationExpires, $user['user_id']]);
    
    // Send verification email
    $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: ($user['full_name'] ?? $user['email']);
    
    $emailResult = sendVerificationEmail($user['email'], $fullName, $verificationCode);
    
    if (!$emailResult['success']) {
        error_log('Failed to resend verification email: ' . $emailResult['message']);
        jsonResponse(false, 'Failed to send verification email. Please try again later.');
    }
    
    jsonResponse(true, 'Verification code has been sent to your email.');
    
} catch (PDOException $e) {
    error_log('Resend Verification API PDO Error: ' . $e->getMessage());
    jsonResponse(false, 'Database error. Please try again.');
} catch (Exception $e) {
    error_log('Resend Verification API Error: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again.');
}
