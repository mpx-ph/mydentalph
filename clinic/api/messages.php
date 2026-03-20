<?php
/**
 * Messages API Endpoint
 * Handles messaging between users
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/tenant.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();
$tenantId = requireClinicTenantId();

// Route based on method
switch ($method) {
    case 'POST':
        sendMessage();
        break;
    case 'GET':
        getMessages();
        break;
    case 'PUT':
        markAsRead();
        break;
    default:
        jsonResponse(false, 'Invalid request method.');
}

/**
 * Send message
 */
function sendMessage() {
    global $pdo, $tenantId;
    
    // Require authentication
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Unauthorized. Please login.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Extract and sanitize data
    $data = [
        'receiver_id' => isset($input['receiver_id']) ? intval($input['receiver_id']) : null,
        'subject' => sanitize($input['subject'] ?? ''),
        'message' => sanitize($input['message'] ?? '')
    ];
    
    $senderIdInt = getCurrentUserId(); // This is users.id (integer)
    
    // Convert sender integer id to user_id (varchar)
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ? AND tenant_id = ?");
    $stmt->execute([$senderIdInt, $tenantId]);
    $senderUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$senderUser || !isset($senderUser['user_id'])) {
        error_log("Sender user not found. Sender ID: $senderIdInt");
        jsonResponse(false, 'User not found. Please login again.');
    }
    
    $senderId = $senderUser['user_id']; // This is users.user_id (varchar) for messages table
    
    // Validation
    if (empty($data['message'])) {
        jsonResponse(false, 'Message content is required.');
    }
    
    // If receiver_id is null, it's a message to admin (for clients) or broadcast (for admins)
    // For clients sending to admin, set receiver_id to null
    // For admins, receiver_id should be specified
    
    $userType = $_SESSION['user_type'] ?? 'client';
    
    if ($userType === 'client') {
        // Clients send to manager/doctor/staff - find manager, doctor, or staff user
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE role IN ('manager', 'dentist', 'staff', 'tenant_owner') AND status = 'active' AND tenant_id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin || !isset($admin['user_id'])) {
            error_log("No active manager/doctor/staff user found for client message. Sender ID: $senderId");
            jsonResponse(false, 'No active manager user found. Please contact support.');
        }
        
        $data['receiver_id'] = $admin['user_id']; // Use user_id (varchar) not id (integer)
        error_log("Client (user_id: $senderId) sending message to manager/doctor/staff (user_id: {$admin['user_id']})");
    } else {
        // Manager/staff/doctor sending to specific user
        if (empty($data['receiver_id'])) {
            jsonResponse(false, 'Receiver ID is required for manager messages.');
        }
        
        // Verify receiver exists and get user_id (varchar)
        // receiver_id can be either integer id or user_id (varchar)
        $receiverUser = null;
        // Schema: tbl_users PK is user_id (varchar); verify receiver exists
        $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$data['receiver_id']]);
        $receiverUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$receiverUser || !isset($receiverUser['user_id'])) {
            jsonResponse(false, 'Receiver not found.');
        }
        
        $data['receiver_id'] = $receiverUser['user_id']; // Convert to user_id (varchar)
    }
    
    try {
        // Check if status column exists
        $statusColumnExists = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'status'");
            $statusColumnExists = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
            // Column check failed, assume it doesn't exist
        }
        
        // Insert message with or without status column
        if ($statusColumnExists) {
            $stmt = $pdo->prepare("
                INSERT INTO messages (
                    sender_id, receiver_id, subject, message, is_read, status, created_at
                ) VALUES (?, ?, ?, ?, 0, 'sent', NOW())
            ");
        } else {
        $stmt = $pdo->prepare("
            INSERT INTO messages (
                sender_id, receiver_id, subject, message, is_read, created_at
            ) VALUES (?, ?, ?, ?, 0, NOW())
        ");
        }
        
        $stmt->execute([
            $senderId,
            $data['receiver_id'],
            $data['subject'] ?: null,
            $data['message']
        ]);
        
        $messageId = $pdo->lastInsertId();
        
        // Get the full message details to return
        // first_name, last_name from patients (for clients) or staffs (for admin/doctor/staff/manager)
        $stmt = $pdo->prepare("
            SELECT m.*,
                   COALESCE(ps.first_name, ss.first_name) as sender_first_name,
                   COALESCE(ps.last_name, ss.last_name) as sender_last_name,
                   s.email as sender_email, s.role as sender_type,
                   COALESCE(pr.first_name, sr.first_name) as receiver_first_name,
                   COALESCE(pr.last_name, sr.last_name) as receiver_last_name,
                   r.email as receiver_email, r.role as receiver_type
            FROM messages m
            LEFT JOIN tbl_users s ON m.sender_id = s.user_id
            LEFT JOIN patients ps ON ps.linked_user_id = s.user_id AND ps.owner_user_id = s.user_id AND s.role = 'client'
            LEFT JOIN staffs ss ON ss.user_id = s.user_id AND s.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
            LEFT JOIN tbl_users r ON m.receiver_id = r.user_id
            LEFT JOIN patients pr ON pr.linked_user_id = r.user_id AND pr.owner_user_id = r.user_id AND r.role = 'client'
            LEFT JOIN staffs sr ON sr.user_id = r.user_id AND r.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
            WHERE m.id = ?
        ");
        $stmt->execute([$messageId]);
        $message = $stmt->fetch();
        
        // Ensure status field exists (for backwards compatibility)
        if (!isset($message['status'])) {
            $message['status'] = 'sent';
        }
        
        // Format timestamp for JavaScript (ISO 8601 with timezone)
        if (isset($message['created_at'])) {
            $message['created_at'] = formatTimestampForJS($message['created_at']);
            $message['timestamp'] = $message['created_at']; // Also add as 'timestamp' for frontend compatibility
        }
        
        jsonResponse(true, 'Message sent successfully.', [
            'message_id' => $messageId,
            'message' => $message
        ]);
        
    } catch (Exception $e) {
        error_log('Send message error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to send message. Please try again.');
    }
}

/**
 * Get messages
 */
function getMessages() {
    global $pdo;
    
    // Require authentication
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Unauthorized. Please login.');
    }
    
    $userIdInt = getCurrentUserId(); // This is users.id (integer)
    $userType = $_SESSION['user_type'] ?? 'client';
    
    // Convert integer id to user_id (varchar) for messages table
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$userIdInt]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !isset($user['user_id'])) {
        error_log("User not found. User ID: $userIdInt");
        jsonResponse(false, 'User not found. Please login again.');
    }
    
    $userId = $user['user_id']; // This is users.user_id (varchar) for messages table
    
    // Debug: Log session info
    error_log("API Request - User ID (int): $userIdInt, User ID (varchar): $userId, User Type: $userType");
    error_log("Session data: " . print_r($_SESSION, true));
    
    // Get query parameters
    $messageId = isset($_GET['id']) ? intval($_GET['id']) : null;
    $conversationWith = isset($_GET['conversation_with']) ? $_GET['conversation_with'] : null;
    
    // Convert conversationWith from integer id to user_id (varchar) if needed
    // conversationWith can be either integer id or user_id (varchar)
    if ($conversationWith) {
        // Try to find as integer id first (if it's numeric and looks like an id)
        if (is_numeric($conversationWith) && intval($conversationWith) > 0 && intval($conversationWith) < 1000000) {
            // Likely an integer id, try to convert to user_id
            $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
            $stmt->execute([intval($conversationWith)]);
            $convUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($convUser && isset($convUser['user_id'])) {
                $conversationWith = $convUser['user_id'];
            }
            // If not found as integer id, it might be a numeric user_id, so use as-is
        }
        // If it's not numeric or conversion failed, use it as user_id (varchar) directly
    }
    $unreadOnly = isset($_GET['unread_only']) ? filter_var($_GET['unread_only'], FILTER_VALIDATE_BOOLEAN) : false;
    $conversationsOnly = isset($_GET['conversations_only']) ? filter_var($_GET['conversations_only'], FILTER_VALIDATE_BOOLEAN) : false;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 50;
    $offset = ($page - 1) * $limit;
    
    try {
        if ($messageId) {
            // Get single message
            // first_name, last_name from patients (for clients) or staffs (for admin/doctor/staff/manager)
            $stmt = $pdo->prepare("
                SELECT m.*,
                       COALESCE(ps.first_name, ss.first_name) as sender_first_name,
                       COALESCE(ps.last_name, ss.last_name) as sender_last_name,
                       COALESCE(ps.profile_image, ss.profile_image) as sender_profile_image,
                       s.email as sender_email, s.role as sender_type,
                       COALESCE(pr.first_name, sr.first_name) as receiver_first_name,
                       COALESCE(pr.last_name, sr.last_name) as receiver_last_name,
                       COALESCE(pr.profile_image, sr.profile_image) as receiver_profile_image,
                       r.email as receiver_email, r.role as receiver_type
            FROM messages m
            LEFT JOIN tbl_users s ON m.sender_id = s.user_id
            LEFT JOIN patients ps ON ps.linked_user_id = s.user_id AND ps.owner_user_id = s.user_id AND s.role = 'client'
            LEFT JOIN staffs ss ON ss.user_id = s.user_id AND s.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
            LEFT JOIN tbl_users r ON m.receiver_id = r.user_id
            LEFT JOIN patients pr ON pr.linked_user_id = r.user_id AND pr.owner_user_id = r.user_id AND r.role = 'client'
            LEFT JOIN staffs sr ON sr.user_id = r.user_id AND r.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)
            ");
            $stmt->execute([$messageId, $userId, $userId]);
            $message = $stmt->fetch();
            
            if (!$message) {
                jsonResponse(false, 'Message not found.');
            }
            
            // Ensure status field exists (for backwards compatibility)
            if (!isset($message['status'])) {
                $message['status'] = $message['is_read'] ? 'seen' : 'sent';
            }
            
            // Format timestamp for JavaScript (ISO 8601 with timezone)
            if (isset($message['created_at'])) {
                $message['created_at'] = formatTimestampForJS($message['created_at']);
                $message['timestamp'] = $message['created_at']; // Also add as 'timestamp' for frontend compatibility
            }
            
            // Update status to 'delivered' if receiver views message
            if ($message['receiver_id'] == $userId && $message['status'] == 'sent') {
                try {
                    $updateStmt = $pdo->prepare("UPDATE messages SET status = 'delivered' WHERE id = ?");
                    $updateStmt->execute([$messageId]);
                    $message['status'] = 'delivered';
                } catch (Exception $e) {
                    // Status column might not exist yet, ignore
                }
            }
            
            // Mark as read if viewing
            if (!$message['is_read'] && $message['receiver_id'] == $userId) {
                try {
                    $updateStmt = $pdo->prepare("UPDATE messages SET is_read = 1, status = 'seen' WHERE id = ?");
                    $updateStmt->execute([$messageId]);
                    $message['is_read'] = 1;
                    $message['status'] = 'seen';
                } catch (Exception $e) {
                    // Status column might not exist yet, try without it
                $updateStmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ?");
                $updateStmt->execute([$messageId]);
                    $message['is_read'] = 1;
                }
            }
            
            jsonResponse(true, 'Message retrieved successfully.', ['message' => $message]);
        } else {
            // Get list of messages or conversations
            if (($userType === 'manager' || $userType === 'doctor' || $userType === 'staff') && $conversationsOnly) {
                // FIX: Manager/Doctor/Staff can see messages sent to ANY manager/doctor/staff user, not just themselves
                // Get all manager/doctor/staff user_ids (varchar) for messages table
                $adminIdsStmt = $pdo->query("SELECT user_id FROM tbl_users WHERE role IN ('manager', 'dentist', 'staff', 'tenant_owner')");
                $adminIds = $adminIdsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($adminIds)) {
                    jsonResponse(true, 'Conversations retrieved successfully.', [
                        'conversations' => [],
                        'unread_count' => 0,
                        'debug' => ['error' => 'No admin users found']
                    ]);
                }
                
                // Create placeholders for IN clause
                $adminPlaceholders = implode(',', array_fill(0, count($adminIds), '?'));
                
                // Get all messages where:
                // 1. Admin is the sender (sender_id IN admin_ids)
                // 2. Admin is the receiver (receiver_id IN admin_ids)
                $msgStmt = $pdo->prepare("
                    SELECT DISTINCT 
                        sender_id, 
                        receiver_id 
                    FROM messages 
                    WHERE sender_id IN ($adminPlaceholders) OR receiver_id IN ($adminPlaceholders)
                ");
                $params = array_merge($adminIds, $adminIds);
                $msgStmt->execute($params);
                $allMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Extract unique partner IDs
                $conversationIds = [];
                foreach ($allMessages as $msg) {
                    $partnerId = ($msg['sender_id'] == $userId) ? $msg['receiver_id'] : $msg['sender_id'];
                    if ($partnerId && $partnerId != $userId && !in_array($partnerId, $conversationIds)) {
                        $conversationIds[] = $partnerId;
                    }
                }
                
                // Extract unique partner IDs (exclude admin IDs)
                $conversationIds = [];
                foreach ($allMessages as $msg) {
                    // Determine partner ID - if sender is admin, partner is receiver; if receiver is admin, partner is sender
                    $partnerId = in_array($msg['sender_id'], $adminIds) ? $msg['receiver_id'] : $msg['sender_id'];
                    
                    // Only add if partner is not an admin and not already in list
                    if ($partnerId && !in_array($partnerId, $adminIds) && !in_array($partnerId, $conversationIds)) {
                        $conversationIds[] = $partnerId;
                    }
                }
                
                $conversations = [];
                
                // For each conversation partner, get details
                foreach ($conversationIds as $partnerId) {
                    // $partnerId is user_id (varchar), not integer id
                    if (!$partnerId) continue;
                    
                    // Get partner user info (first_name, last_name from patients or staffs table)
                    $userStmt = $pdo->prepare("
                        SELECT u.id, u.user_id, 
                               COALESCE(p.first_name, s.first_name) as first_name,
                               COALESCE(p.last_name, s.last_name) as last_name,
                               COALESCE(p.profile_image, s.profile_image) as profile_image
                        FROM tbl_users u
                        LEFT JOIN patients p ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id AND u.user_type = 'client'
                        LEFT JOIN staffs s ON s.user_id = u.user_id AND u.user_type IN ('admin', 'staff', 'doctor', 'manager')
                        WHERE u.user_id = ?
                    ");
                    $userStmt->execute([$partnerId]);
                    $partner = $userStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$partner) {
                        // Create fallback entry if user doesn't exist
                        $partner = [
                            'id' => null,
                            'user_id' => $partnerId,
                            'first_name' => 'User',
                            'last_name' => "#$partnerId",
                            'profile_image' => null
                        ];
                    }
                    
                    // Get last message between this admin and partner
                    // Check messages where admin is sender/receiver and partner is the other
                    $lastMsgStmt = $pdo->prepare("
                        SELECT m.id, m.message, m.created_at, m.is_read
                        FROM messages m
                        WHERE ((m.sender_id IN ($adminPlaceholders) AND m.receiver_id = ?) 
                           OR (m.sender_id = ? AND m.receiver_id IN ($adminPlaceholders)))
                        ORDER BY m.created_at DESC, m.id DESC
                        LIMIT 1
                    ");
                    $lastMsgParams = array_merge($adminIds, [$partnerId], [$partnerId], $adminIds);
                    $lastMsgStmt->execute($lastMsgParams);
                    $lastMessage = $lastMsgStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Format timestamp for JavaScript if last message exists
                    if ($lastMessage && isset($lastMessage['created_at'])) {
                        $lastMessage['created_at'] = formatTimestampForJS($lastMessage['created_at']);
                    }
                    
                    // Get unread count for this specific admin (not all admins)
                    $unreadStmt = $pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM messages 
                        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
                    ");
                    $unreadStmt->execute([$partnerId, $userId]);
                    $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
                    $unreadCount = $unreadResult ? intval($unreadResult['count']) : 0;
                    
                    $conversationData = [
                        'conversation_with_id' => $partnerId, // Use user_id (varchar) for frontend - this is what frontend expects
                        'conversation_with_user_id' => $partnerId, // Also provide user_id (varchar) for API use
                        'conversation_with_integer_id' => isset($partner['id']) ? intval($partner['id']) : null, // Also provide integer id for API calls
                        'conversation_with_name' => trim($partner['first_name'] . ' ' . $partner['last_name']),
                        'conversation_with_photo' => $partner['profile_image'],
                        'last_message_time' => $lastMessage && isset($lastMessage['created_at']) ? formatTimestampForJS($lastMessage['created_at']) : null,
                        'last_message_id' => $lastMessage ? intval($lastMessage['id']) : null,
                        'last_message' => $lastMessage ? $lastMessage['message'] : null,
                        'unread_count' => intval($unreadCount)
                    ];
                    
                    error_log("Adding conversation: " . print_r($conversationData, true));
                    $conversations[] = $conversationData;
                }
                
                error_log("Total conversations found: " . count($conversations));
                
                // Sort by last message time (most recent first)
                usort($conversations, function($a, $b) {
                    if (!$a['last_message_time'] && !$b['last_message_time']) return 0;
                    if (!$a['last_message_time']) return 1;
                    if (!$b['last_message_time']) return -1;
                    return strtotime($b['last_message_time']) - strtotime($a['last_message_time']);
                });
                
                // Get total unread count
                $unreadStmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM messages 
                    WHERE receiver_id = ? AND is_read = 0
                ");
                $unreadStmt->execute([$userId]);
                $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
                $unreadCount = $unreadResult ? intval($unreadResult['count']) : 0;
                
                // Debug: Collect debug info to return in response
                $debugInfo = [
                    'admin_user_id' => $userId,
                    'admin_user_type' => $userType,
                    'total_messages_in_db' => 0,
                    'messages_for_admin' => 0,
                    'conversation_ids_found' => [],
                    'all_admin_users' => [],
                    'sample_messages' => []
                ];
                
                // Get debug info
                try {
                    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
                    $debugInfo['total_messages_in_db'] = intval($totalStmt->fetch(PDO::FETCH_ASSOC)['total']);
                    
                    $adminMsgStmt = $pdo->prepare("SELECT COUNT(*) as total FROM messages WHERE sender_id = ? OR receiver_id = ?");
                    $adminMsgStmt->execute([$userId, $userId]);
                    $debugInfo['messages_for_admin'] = intval($adminMsgStmt->fetch(PDO::FETCH_ASSOC)['total']);
                    
                    $adminUsersStmt = $pdo->query("
                        SELECT u.id, u.user_type, 
                               COALESCE(p.first_name, s.first_name) as first_name,
                               COALESCE(p.last_name, s.last_name) as last_name
                        FROM tbl_users u
                        LEFT JOIN patients p ON p.linked_user_id = u.user_id AND p.owner_user_id = u.user_id AND u.user_type = 'client'
                        LEFT JOIN staffs s ON s.user_id = u.user_id AND u.user_type IN ('admin', 'staff', 'doctor', 'manager')
                        WHERE u.user_type IN ('manager', 'doctor', 'staff', 'admin') LIMIT 5
                    ");
                    $debugInfo['all_admin_users'] = $adminUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $sampleStmt = $pdo->query("SELECT id, sender_id, receiver_id, message FROM messages ORDER BY created_at DESC LIMIT 3");
                    $debugInfo['sample_messages'] = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $debugInfo['conversation_ids_found'] = $conversationIds;
                } catch (Exception $e) {
                    $debugInfo['error'] = $e->getMessage();
                }
                
                jsonResponse(true, 'Conversations retrieved successfully.', [
                    'conversations' => $conversations,
                    'unread_count' => intval($unreadCount),
                    'debug' => $debugInfo  // Include debug info in response
                ]);
            } else if ($userType === 'manager' || $userType === 'doctor' || $userType === 'staff' || $userType === 'admin') {
                // Manager/Doctor/Staff/Admin sees all messages
                // Get all manager/doctor/staff/admin user_ids (varchar) to find messages to/from any manager/doctor/staff/admin
                $adminIdsStmt = $pdo->query("SELECT user_id FROM tbl_users WHERE role IN ('manager', 'dentist', 'staff', 'tenant_owner')");
                $adminUserIds = $adminIdsStmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($adminUserIds)) {
                    // No admin users found, return empty
                    jsonResponse(true, 'Messages retrieved successfully.', [
                        'messages' => [],
                        'unread_count' => 0
                    ]);
                    return;
                }
                
                $adminPlaceholders = implode(',', array_fill(0, count($adminUserIds), '?'));
                
                if ($conversationWith) {
                    // Get conversation with specific user (conversationWith is user_id varchar)
                    // Messages where: (admin is sender AND partner is receiver) OR (partner is sender AND admin is receiver)
                    // This ensures we get all messages between the conversation partner and ANY admin/staff/doctor
                    // first_name, last_name from patients (for clients) or staffs (for admin/doctor/staff/manager)
                    $sql = "
                        SELECT m.*,
                               COALESCE(ps.first_name, ss.first_name) as sender_first_name,
                               COALESCE(ps.last_name, ss.last_name) as sender_last_name,
                               COALESCE(ps.profile_image, ss.profile_image) as sender_profile_image,
                               s.email as sender_email, s.role as sender_type,
                               COALESCE(pr.first_name, sr.first_name) as receiver_first_name,
                               COALESCE(pr.last_name, sr.last_name) as receiver_last_name,
                               COALESCE(pr.profile_image, sr.profile_image) as receiver_profile_image,
                               r.email as receiver_email, r.role as receiver_type
                        FROM messages m
                        LEFT JOIN tbl_users s ON m.sender_id = s.user_id
                        LEFT JOIN patients ps ON ps.linked_user_id = s.user_id AND ps.owner_user_id = s.user_id AND s.role = 'client'
                        LEFT JOIN staffs ss ON ss.user_id = s.user_id AND s.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                        LEFT JOIN tbl_users r ON m.receiver_id = r.user_id
                        LEFT JOIN patients pr ON pr.linked_user_id = r.user_id AND pr.owner_user_id = r.user_id AND r.role = 'client'
                        LEFT JOIN staffs sr ON sr.user_id = r.user_id AND r.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                        WHERE ((m.sender_id IN ($adminPlaceholders) AND m.receiver_id = ?) 
                           OR (m.sender_id = ? AND m.receiver_id IN ($adminPlaceholders)))
                        ORDER BY m.created_at ASC
                    ";
                    // Parameters: adminUserIds (for sender IN), conversationWith (for receiver), conversationWith (for sender), adminUserIds (for receiver IN)
                    $params = array_merge($adminUserIds, [$conversationWith], [$conversationWith], $adminUserIds);
                    
                    // Debug logging
                    error_log("Fetching messages for conversation_with: $conversationWith");
                    error_log("Admin user IDs: " . implode(', ', $adminUserIds));
                    error_log("Query params count: " . count($params));
                    
                    // Test query to see all messages involving this conversation partner
                    $testStmt = $pdo->prepare("SELECT id, sender_id, receiver_id, message, created_at FROM messages WHERE sender_id = ? OR receiver_id = ? ORDER BY created_at DESC LIMIT 10");
                    $testStmt->execute([$conversationWith, $conversationWith]);
                    $testMessages = $testStmt->fetchAll(PDO::FETCH_ASSOC);
                    error_log("Test query found " . count($testMessages) . " total messages involving conversation_with: $conversationWith");
                    if (count($testMessages) > 0) {
                        error_log("Sample test message: id=" . $testMessages[0]['id'] . ", sender_id=" . $testMessages[0]['sender_id'] . ", receiver_id=" . $testMessages[0]['receiver_id']);
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $messages = $stmt->fetchAll();
                    
                    // Debug logging
                    error_log("Found " . count($messages) . " messages for conversation_with: $conversationWith (after filtering)");
                    if (count($messages) > 0) {
                        error_log("Sample message: sender_id=" . $messages[0]['sender_id'] . ", receiver_id=" . $messages[0]['receiver_id']);
                    } else if (count($testMessages) > 0) {
                        error_log("WARNING: Test query found messages but filtered query returned none! This suggests a query filtering issue.");
                    }
                    
                    // Ensure status field exists for all messages (backwards compatibility)
                    // Format timestamps for JavaScript
                    foreach ($messages as &$msg) {
                        if (!isset($msg['status'])) {
                            $msg['status'] = $msg['is_read'] ? 'seen' : 'sent';
                        }
                        // Format timestamp for JavaScript (ISO 8601 with timezone)
                        if (isset($msg['created_at'])) {
                            $msg['created_at'] = formatTimestampForJS($msg['created_at']);
                            $msg['timestamp'] = $msg['created_at']; // Also add as 'timestamp' for frontend compatibility
                        }
                    }
                    unset($msg);
                    
                    // Mark messages as delivered when viewing conversation (if status column exists)
                    try {
                        $updateStmt = $pdo->prepare("
                            UPDATE messages 
                            SET status = 'delivered' 
                            WHERE receiver_id = ? AND sender_id = ? AND status = 'sent'
                        ");
                        $updateStmt->execute([$userId, $conversationWith]);
                    } catch (Exception $e) {
                        // Status column doesn't exist yet, ignore
                    }
                    
                    // Mark all as read if viewing
                    try {
                        $updateStmt = $pdo->prepare("
                            UPDATE messages 
                            SET is_read = 1, status = 'seen' 
                            WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
                        ");
                        $updateStmt->execute([$userId, $conversationWith]);
                    } catch (Exception $e) {
                        // Status column doesn't exist yet, update without it
                        $updateStmt = $pdo->prepare("
                            UPDATE messages 
                            SET is_read = 1 
                            WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
                        ");
                        $updateStmt->execute([$userId, $conversationWith]);
                    }
                    
                    jsonResponse(true, 'Messages retrieved successfully.', [
                        'messages' => $messages,
                        'unread_count' => 0
                    ]);
                } else {
                    // $userId is already the varchar user_id (set on line 200)
                    $userUserId = $userId;
                    
                    // Get all messages (grouped by conversation)
                    // first_name, last_name from patients (for clients) or staffs (for admin/doctor/staff/manager)
                    $sql = "
                        SELECT m.*,
                               COALESCE(ps.first_name, ss.first_name) as sender_first_name,
                               COALESCE(ps.last_name, ss.last_name) as sender_last_name,
                               COALESCE(ps.profile_image, ss.profile_image) as sender_profile_image,
                               s.email as sender_email, s.role as sender_type,
                               COALESCE(pr.first_name, sr.first_name) as receiver_first_name,
                               COALESCE(pr.last_name, sr.last_name) as receiver_last_name,
                               COALESCE(pr.profile_image, sr.profile_image) as receiver_profile_image,
                               r.email as receiver_email, r.role as receiver_type,
                               CASE 
                                   WHEN m.sender_id = ? THEN m.receiver_id
                                   ELSE m.sender_id
                               END as conversation_with_id
                        FROM messages m
                        LEFT JOIN tbl_users s ON m.sender_id = s.user_id
                        LEFT JOIN patients ps ON ps.linked_user_id = s.user_id AND ps.owner_user_id = s.user_id AND s.role = 'client'
                        LEFT JOIN staffs ss ON ss.user_id = s.user_id AND s.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                        LEFT JOIN tbl_users r ON m.receiver_id = r.user_id
                        LEFT JOIN patients pr ON pr.linked_user_id = r.user_id AND pr.owner_user_id = r.user_id AND r.role = 'client'
                        LEFT JOIN staffs sr ON sr.user_id = r.user_id AND r.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                        WHERE m.sender_id = ? OR m.receiver_id = ?
                    ";
                    $params = [$userUserId, $userUserId, $userUserId];
                    
                    if ($unreadOnly) {
                        $sql .= " AND m.is_read = 0 AND m.receiver_id = ?";
                        $params[] = $userUserId;
                    }
                    
                    $sql .= " ORDER BY m.created_at DESC";
                    
                    // Get total count
                    $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_query";
                    $countStmt = $pdo->prepare($countSql);
                    $countStmt->execute($params);
                    $total = $countStmt->fetch()['total'];
                    
                    // Get messages with pagination
                    $sql .= " LIMIT ? OFFSET ?";
                    $params[] = $limit;
                    $params[] = $offset;
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $messages = $stmt->fetchAll();
                    
                    // Ensure status field exists for all messages (backwards compatibility)
                    foreach ($messages as &$msg) {
                        if (!isset($msg['status'])) {
                            $msg['status'] = $msg['is_read'] ? 'seen' : 'sent';
                        }
                    }
                    unset($msg);
                    
                    // Get unread count
                    $unreadStmt = $pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM messages 
                        WHERE receiver_id = ? AND is_read = 0
                    ");
                    $unreadStmt->execute([$userId]);
                    $unreadCount = $unreadStmt->fetch()['count'];
                    
                    jsonResponse(true, 'Messages retrieved successfully.', [
                        'messages' => $messages,
                        'unread_count' => intval($unreadCount),
                        'pagination' => [
                            'page' => $page,
                            'limit' => $limit,
                            'total' => $total,
                            'pages' => ceil($total / $limit)
                        ]
                    ]);
                }
            } else {
                // Client sees only their messages
                // $userId is already the varchar user_id (set on line 200)
                $userUserId = $userId;
                
                // first_name, last_name from patients (for clients) or staffs (for admin/doctor/staff/manager)
                $sql = "
                    SELECT m.*,
                           COALESCE(ps.first_name, ss.first_name) as sender_first_name,
                           COALESCE(ps.last_name, ss.last_name) as sender_last_name,
                           COALESCE(ps.profile_image, ss.profile_image) as sender_profile_image,
                           s.email as sender_email, s.role as sender_type,
                           COALESCE(pr.first_name, sr.first_name) as receiver_first_name,
                           COALESCE(pr.last_name, sr.last_name) as receiver_last_name,
                           COALESCE(pr.profile_image, sr.profile_image) as receiver_profile_image,
                           r.email as receiver_email, r.role as receiver_type
                    FROM messages m
                    LEFT JOIN tbl_users s ON m.sender_id = s.user_id
                    LEFT JOIN patients ps ON ps.linked_user_id = s.user_id AND ps.owner_user_id = s.user_id AND s.role = 'client'
                    LEFT JOIN staffs ss ON ss.user_id = s.user_id AND s.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                    LEFT JOIN tbl_users r ON m.receiver_id = r.user_id
                    LEFT JOIN patients pr ON pr.linked_user_id = r.user_id AND pr.owner_user_id = r.user_id AND r.role = 'client'
                    LEFT JOIN staffs sr ON sr.user_id = r.user_id AND r.role IN ('tenant_owner', 'staff', 'dentist', 'manager')
                    WHERE m.sender_id = ? OR m.receiver_id = ?
                ";
                $params = [$userUserId, $userUserId];
                
                if ($unreadOnly) {
                    $sql .= " AND m.is_read = 0 AND m.receiver_id = ?";
                    $params[] = $userUserId;
                }
                
                $sql .= " ORDER BY m.created_at ASC";
                
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $messages = $stmt->fetchAll();
            
                // Ensure status field exists for all messages (backwards compatibility)
                // Format timestamps for JavaScript
                foreach ($messages as &$msg) {
                    if (!isset($msg['status'])) {
                        $msg['status'] = $msg['is_read'] ? 'seen' : 'sent';
                    }
                    // Format timestamp for JavaScript (ISO 8601 with timezone)
                    if (isset($msg['created_at'])) {
                        $msg['created_at'] = formatTimestampForJS($msg['created_at']);
                        $msg['timestamp'] = $msg['created_at']; // Also add as 'timestamp' for frontend compatibility
                    }
                }
                unset($msg);
                
                // Mark messages as delivered when viewing (if status column exists)
                try {
                    $updateStmt = $pdo->prepare("
                        UPDATE messages 
                        SET status = 'delivered' 
                        WHERE receiver_id = ? AND status = 'sent'
                    ");
                    $updateStmt->execute([$userId]);
                } catch (Exception $e) {
                    // Status column doesn't exist yet, ignore
                }
                
                // Mark all as read if viewing
                try {
                    $updateStmt = $pdo->prepare("
                        UPDATE messages 
                        SET is_read = 1, status = 'seen' 
                        WHERE receiver_id = ? AND is_read = 0
                    ");
                    $updateStmt->execute([$userId]);
                } catch (Exception $e) {
                    // Status column doesn't exist yet, update without it
                    $updateStmt = $pdo->prepare("
                        UPDATE messages 
                        SET is_read = 1 
                        WHERE receiver_id = ? AND is_read = 0
                    ");
                    $updateStmt->execute([$userId]);
                }
                
                // Get unread count (before marking as read)
            $unreadStmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM messages 
                WHERE receiver_id = ? AND is_read = 0
            ");
            $unreadStmt->execute([$userId]);
            $unreadCount = $unreadStmt->fetch()['count'];
            
            jsonResponse(true, 'Messages retrieved successfully.', [
                'messages' => $messages,
                    'unread_count' => intval($unreadCount)
                ]);
            }
        }
        
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to retrieve messages.');
    }
}

/**
 * Mark message(s) as read
 */
function markAsRead() {
    global $pdo;
    
    // Require authentication
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(false, 'Unauthorized. Please login.');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $userIdInt = getCurrentUserId(); // This is users.id (integer)
    
    // Convert integer id to user_id (varchar) for messages table
    $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$userIdInt]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !isset($user['user_id'])) {
        error_log("User not found. User ID: $userIdInt");
        jsonResponse(false, 'User not found. Please login again.');
    }
    
    $userId = $user['user_id']; // This is users.user_id (varchar) for messages table
    
    $messageId = isset($input['id']) ? intval($input['id']) : null;
    $markAll = isset($input['mark_all']) ? filter_var($input['mark_all'], FILTER_VALIDATE_BOOLEAN) : false;
    
    try {
        $conversationWith = isset($input['conversation_with']) ? $input['conversation_with'] : null;
        
        // Convert conversationWith from integer id to user_id (varchar) if needed
        if ($conversationWith && is_numeric($conversationWith)) {
            $stmt = $pdo->prepare("SELECT user_id FROM tbl_users WHERE user_id = ?");
            $stmt->execute([intval($conversationWith)]);
            $convUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($convUser && isset($convUser['user_id'])) {
                $conversationWith = $convUser['user_id'];
            }
        }
        
        // Check if status column exists
        $statusColumnExists = false;
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM messages LIKE 'status'");
            $statusColumnExists = $checkStmt->rowCount() > 0;
        } catch (Exception $e) {
            // Column check failed, assume it doesn't exist
        }
        
        if ($markAll) {
            // Mark all messages as read
            if ($statusColumnExists) {
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, status = 'seen' WHERE receiver_id = ? AND is_read = 0");
            } else {
            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
            }
            $stmt->execute([$userId]);
            
            jsonResponse(true, 'All messages marked as read.');
        } elseif ($conversationWith) {
            // Mark all messages in a conversation as read
            if ($statusColumnExists) {
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, status = 'seen' WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
            } else {
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
            }
            $stmt->execute([$userId, $conversationWith]);
            
            jsonResponse(true, 'Conversation marked as read.');
        } elseif ($messageId) {
            // Mark single message as read
            if ($statusColumnExists) {
                $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, status = 'seen' WHERE id = ? AND receiver_id = ?");
            } else {
            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
            }
            $stmt->execute([$messageId, $userId]);
            
            jsonResponse(true, 'Message marked as read.');
        } else {
            jsonResponse(false, 'Message ID, conversation_with, or mark_all flag is required.');
        }
        
    } catch (Exception $e) {
        error_log('Mark as read error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to mark message as read.');
    }
}

