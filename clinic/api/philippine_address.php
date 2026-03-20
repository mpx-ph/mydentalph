<?php
/**
 * Philippine Address API
 * Provides cascading dropdown data for Province → City/Municipality → Barangay
 */

// Suppress warnings/notices for clean JSON output
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Path to the JSON data file
$jsonFile = __DIR__ . '/../philippine_provinces_cities_municipalities_and_barangays_2019v2.json';

// Check if JSON file exists
if (!file_exists($jsonFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Address data file not found at: ' . $jsonFile,
        'debug' => [
            'current_dir' => __DIR__,
            'file_exists' => file_exists($jsonFile),
            'parent_dir' => dirname(__DIR__)
        ]
    ]);
    exit;
}

// Load JSON data
$jsonContent = file_get_contents($jsonFile);
if ($jsonContent === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to read address data file'
    ]);
    exit;
}

$addressData = json_decode($jsonContent, true);

if (!$addressData) {
    $jsonError = json_last_error_msg();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to parse address data: ' . $jsonError
    ]);
    exit;
}

// Get action parameter
$action = $_GET['action'] ?? 'provinces';
$province = $_GET['province'] ?? '';
$city = $_GET['city'] ?? '';

// Test endpoint to verify API is working
if ($action === 'test') {
    echo json_encode([
        'success' => true,
        'message' => 'Philippine Address API is working',
        'json_file_exists' => file_exists($jsonFile),
        'json_file_path' => $jsonFile
    ]);
    exit;
}

try {
    switch ($action) {
        case 'provinces':
            // Get all unique provinces
            $provinces = [];
            foreach ($addressData as $regionCode => $region) {
                if (isset($region['province_list']) && is_array($region['province_list'])) {
                    foreach ($region['province_list'] as $provinceName => $provinceData) {
                        if (!in_array($provinceName, $provinces)) {
                            $provinces[] = $provinceName;
                        }
                    }
                }
            }
            // Sort provinces alphabetically
            sort($provinces);
            
            echo json_encode([
                'success' => true,
                'data' => $provinces
            ]);
            break;
            
        case 'cities':
            // Get cities/municipalities for a specific province
            if (empty($province)) {
                throw new Exception('Province parameter is required');
            }
            
            $cities = [];
            foreach ($addressData as $regionCode => $region) {
                if (isset($region['province_list'][$province]['municipality_list'])) {
                    $municipalities = $region['province_list'][$province]['municipality_list'];
                    foreach ($municipalities as $cityName => $cityData) {
                        if (!in_array($cityName, $cities)) {
                            $cities[] = $cityName;
                        }
                    }
                }
            }
            // Sort cities alphabetically
            sort($cities);
            
            echo json_encode([
                'success' => true,
                'data' => $cities
            ]);
            break;
            
        case 'barangays':
            // Get barangays for a specific city/municipality in a province
            if (empty($province) || empty($city)) {
                throw new Exception('Both province and city parameters are required');
            }
            
            $barangays = [];
            foreach ($addressData as $regionCode => $region) {
                if (isset($region['province_list'][$province]['municipality_list'][$city]['barangay_list'])) {
                    $barangays = $region['province_list'][$province]['municipality_list'][$city]['barangay_list'];
                    break; // Found it, no need to continue
                }
            }
            // Sort barangays alphabetically
            sort($barangays);
            
            echo json_encode([
                'success' => true,
                'data' => $barangays
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}