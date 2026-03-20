<?php
/**
 * Check Username Availability API Endpoint
 */

// Suppress error display for API (return JSON instead)
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any errors
ob_start();

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    // Clear any output that might have been generated
    ob_clean();
    
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
    
    $username = sanitize($input['username'] ?? '');
    
    if (empty($username)) {
        jsonResponse(false, 'Username is required.');
    }
    
    // Validate username format (alphanumeric, underscore, hyphen, 3-20 chars)
    if (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
        jsonResponse(false, 'Username must be 3-20 characters and contain only letters, numbers, underscores, or hyphens.');
    }
    
    // Check if username exists in provider tbl_users (global uniqueness)
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        jsonResponse(true, 'Username already taken.', ['available' => false]);
    } else {
        jsonResponse(true, 'Username is available.', ['available' => true]);
    }
    
} catch (Throwable $e) {
    // Clear any output
    ob_clean();
    
    // Log the error
    error_log('Check Username API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return JSON error
    header('Content-Type: application/json');
    jsonResponse(false, 'An error occurred while checking username availability. Please try again.');
}
