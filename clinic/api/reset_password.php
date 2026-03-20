<?php
/**
 * Password Reset API Endpoint
 * Handles password reset requests and password reset with verification code
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Handle CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Invalid JSON data: ' . json_last_error_msg());
    }
    
    $action = sanitize($input['action'] ?? '');
    
    if ($action === 'request') {
        // Request password reset code
        $email = sanitize($input['email'] ?? '');
        
        if (empty($email)) {
            jsonResponse(false, 'Email is required.');
        }
        
        $result = requestPasswordReset($email);
        jsonResponse($result['success'], $result['message']);
        
    } elseif ($action === 'reset') {
        // Reset password with verification code
        $email = sanitize($input['email'] ?? '');
        $code = sanitize($input['code'] ?? '');
        $newPassword = $input['new_password'] ?? '';
        
        if (empty($email) || empty($code) || empty($newPassword)) {
            jsonResponse(false, 'Email, verification code, and new password are required.');
        }
        
        $result = resetPasswordWithCode($email, $code, $newPassword);
        jsonResponse($result['success'], $result['message']);
        
    } else {
        jsonResponse(false, 'Invalid action. Use "request" or "reset".');
    }
    
} catch (PDOException $e) {
    error_log('Password Reset API PDO Error: ' . $e->getMessage());
    jsonResponse(false, 'Database error. Please try again.');
} catch (Exception $e) {
    error_log('Password Reset API Error: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again.');
}
