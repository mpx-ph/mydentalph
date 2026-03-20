<?php
/**
 * Blocked Schedules API
 * Handles reading and writing blocked schedule times without database
 */

header('Content-Type: application/json');

$dataDir = __DIR__ . '/../data';
require_once __DIR__ . '/../includes/tenant.php';
$tenantId = requireClinicTenantId();

// Tenant-scoped file (prevents clinics blocking each other's schedules)
$jsonFile = $dataDir . '/blocked_schedules_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $tenantId) . '.json';

// Ensure data directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Initialize empty array if file doesn't exist
if (!file_exists($jsonFile)) {
    file_put_contents($jsonFile, json_encode([]));
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Function to check if two time ranges overlap
function timeRangesOverlap($start1, $end1, $start2, $end2) {
    // Two ranges overlap if: start1 < end2 && start2 < end1
    return $start1 < $end2 && $start2 < $end1;
}

try {
    switch ($method) {
        case 'GET':
            // Read blocked schedules
            $blockedSchedules = json_decode(file_get_contents($jsonFile), true) ?? [];
            
            // Filter by date if provided
            $date = $_GET['date'] ?? null;
            if ($date) {
                $blockedSchedules = array_filter($blockedSchedules, function($block) use ($date) {
                    return $block['date'] === $date;
                });
            }
            
            echo json_encode([
                'success' => true,
                'data' => array_values($blockedSchedules)
            ]);
            break;
            
        case 'POST':
            // Add new blocked schedule
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['date']) || !isset($input['start_time']) || !isset($input['end_time'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing required fields: date, start_time, end_time'
                ]);
                exit;
            }
            
            $blockedSchedules = json_decode(file_get_contents($jsonFile), true) ?? [];
            
            // Validate time range
            if ($input['start_time'] >= $input['end_time']) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'End time must be after start time'
                ]);
                exit;
            }
            
            // Check for overlapping blocks on the same date
            $newStart = $input['start_time'];
            $newEnd = $input['end_time'];
            $newDate = $input['date'];
            
            foreach ($blockedSchedules as $existingBlock) {
                // Only check blocks on the same date
                if ($existingBlock['date'] === $newDate) {
                    $existingStart = substr($existingBlock['start_time'], 0, 5); // Get HH:MM format
                    $existingEnd = substr($existingBlock['end_time'], 0, 5);
                    
                    if (timeRangesOverlap($newStart, $newEnd, $existingStart, $existingEnd)) {
                        // Format time for display
                        $formatTime = function($time24) {
                            $parts = explode(':', $time24);
                            $hour = (int)$parts[0];
                            $minute = $parts[1];
                            $ampm = $hour >= 12 ? 'PM' : 'AM';
                            $hour12 = $hour % 12;
                            if ($hour12 == 0) $hour12 = 12;
                            return sprintf('%d:%s %s', $hour12, $minute, $ampm);
                        };
                        
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'message' => 'This time range overlaps with an existing block (' . 
                                        $formatTime($existingStart) . ' - ' . 
                                        $formatTime($existingEnd) . '). Please choose a different time range.'
                        ]);
                        exit;
                    }
                }
            }
            
            // Add new block
            $newBlock = [
                'id' => uniqid('block_', true),
                'date' => $input['date'],
                'start_time' => $input['start_time'],
                'end_time' => $input['end_time'],
                'reason' => $input['reason'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $blockedSchedules[] = $newBlock;
            
            // Save to file
            file_put_contents($jsonFile, json_encode($blockedSchedules, JSON_PRETTY_PRINT));
            
            echo json_encode([
                'success' => true,
                'data' => $newBlock,
                'message' => 'Schedule blocked successfully'
            ]);
            break;
            
        case 'DELETE':
            // Remove blocked schedule
            $blockId = $_GET['id'] ?? null;
            
            if (!$blockId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Missing block ID'
                ]);
                exit;
            }
            
            $blockedSchedules = json_decode(file_get_contents($jsonFile), true) ?? [];
            
            // Remove block
            $blockedSchedules = array_filter($blockedSchedules, function($block) use ($blockId) {
                return $block['id'] !== $blockId;
            });
            
            // Save to file
            file_put_contents($jsonFile, json_encode(array_values($blockedSchedules), JSON_PRETTY_PRINT));
            
            echo json_encode([
                'success' => true,
                'message' => 'Block removed successfully'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
