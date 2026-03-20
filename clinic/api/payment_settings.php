<?php
/**
 * Payment Settings API Endpoint
 * Handles reading and updating payment settings from JSON config file
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$configFile = __DIR__ . '/../config/payment_settings.json';

// Route based on method
switch ($method) {
    case 'GET':
        getPaymentSettings();
        break;
    case 'POST':
    case 'PUT':
        updatePaymentSettings();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Get payment settings
 */
function getPaymentSettings() {
    global $configFile;
    
    // Require manager authentication
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'manager') {
        jsonResponse(false, 'Unauthorized. Manager access required.');
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        jsonResponse(false, 'Session expired. Please log in again.');
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Read config file
    if (!file_exists($configFile)) {
        // Create default config if it doesn't exist
        $defaultConfig = [
            'orthodontics_min_downpayment' => 5000,
            'non_orthodontics_downpayment_percentage' => 0.20
        ];
        file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
    }
    
    $config = json_decode(file_get_contents($configFile), true);
    
    if ($config === null) {
        jsonResponse(false, 'Error reading payment settings.');
    }
    
    jsonResponse(true, 'Payment settings retrieved successfully.', $config);
}

/**
 * Update payment settings
 */
function updatePaymentSettings() {
    global $configFile;
    
    // Require manager authentication
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'manager') {
        jsonResponse(false, 'Unauthorized. Manager access required.');
    }
    
    // Check session timeout
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        jsonResponse(false, 'Session expired. Please log in again.');
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
    
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input === null) {
        jsonResponse(false, 'Invalid JSON data.');
    }
    
    // Validate input
    $orthodonticsMin = isset($input['orthodontics_min_downpayment']) ? floatval($input['orthodontics_min_downpayment']) : null;
    $nonOrthoPercentage = isset($input['non_orthodontics_downpayment_percentage']) ? floatval($input['non_orthodontics_downpayment_percentage']) : null;
    
    // Read existing config
    $config = [];
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if ($config === null) {
            $config = [];
        }
    }
    
    // Update only provided values
    if ($orthodonticsMin !== null && $orthodonticsMin >= 0) {
        $config['orthodontics_min_downpayment'] = $orthodonticsMin;
    }
    
    if ($nonOrthoPercentage !== null && $nonOrthoPercentage >= 0 && $nonOrthoPercentage <= 1) {
        $config['non_orthodontics_downpayment_percentage'] = $nonOrthoPercentage;
    }
    
    // Ensure default values exist
    if (!isset($config['orthodontics_min_downpayment'])) {
        $config['orthodontics_min_downpayment'] = 5000;
    }
    if (!isset($config['non_orthodontics_downpayment_percentage'])) {
        $config['non_orthodontics_downpayment_percentage'] = 0.20;
    }
    
    // Write to file
    $result = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        jsonResponse(false, 'Error saving payment settings.');
    }
    
    jsonResponse(true, 'Payment settings updated successfully.', $config);
}
