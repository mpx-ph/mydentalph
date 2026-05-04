<?php
/**
 * Patient mobile JSON API for clinic staff messaging.
 * Uses tbl_messages + tenant-scoped users (same data path as clinic/StaffMessage.php).
 *
 * GET  ?user_id=&tenant_id=                     — contacts (staff/doctors + unread)
 * GET  ?user_id=&tenant_id=&with=STAFF_USER_ID   — thread messages (marks staff→patient as read)
 * POST JSON: user_id, tenant_id, receiver_id, message — send (patient → staff)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function psm_json(bool $ok, string $message, array $extra = []): void {
    echo json_encode(array_merge([
        'success' => $ok,
        'message' => $message,
    ], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** @param PDO $pdo */
function psm_verify_client(PDO $pdo, string $tenantId, string $userId): ?array {
    $st = $pdo->prepare("
        SELECT user_id, tenant_id, email, full_name, role, status
        FROM tbl_users
        WHERE user_id = ?
          AND tenant_id = ?
          AND LOWER(TRIM(role)) = 'client'
          AND LOWER(TRIM(status)) = 'active'
        LIMIT 1
    ");
    $st->execute([$userId, $tenantId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** @param PDO $pdo */
function psm_verify_staff_receiver(PDO $pdo, string $tenantId, string $receiverId): bool {
    $st = $pdo->prepare("
        SELECT user_id
        FROM tbl_users
        WHERE user_id = ?
          AND tenant_id = ?
          AND role IN ('tenant_owner', 'manager', 'staff', 'dentist')
          AND status = 'active'
        LIMIT 1
    ");
    $st->execute([$receiverId, $tenantId]);
    return (string) ($st->fetchColumn() ?: '') !== '';
}

function psm_role_label(string $role): string {
    $r = strtolower(trim($role));
    if ($r === 'dentist') {
        return 'DOCTOR';
    }
    if ($r === 'manager' || $r === 'tenant_owner' || $r === 'staff') {
        return 'STAFF';
    }
    return strtoupper($r !== '' ? $r : 'STAFF');
}

/** Uppercase first $len chars; works without mbstring. */
function psm_upper_slice(string $s, int $len): string {
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($s, 0, $len, 'UTF-8'), 'UTF-8');
    }
    $ascii = substr($s, 0, $len);
    return strtoupper($ascii);
}

function psm_initials(string $name, string $email): string {
    $name = trim($name);
    if ($name !== '') {
        $parts = preg_split('/\s+/u', $name) ?: [];
        if (count($parts) >= 2) {
            $a = psm_upper_slice((string) $parts[0], 1);
            $b = psm_upper_slice((string) $parts[count($parts) - 1], 1);
            return $a . $b;
        }
        return psm_upper_slice($name, 2);
    }
    $e = trim($email);
    if ($e !== '') {
        return psm_upper_slice($e, 2);
    }
    return '?';
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input') ?: '';
    $input = json_decode($raw, true);
    if (!is_array($input)) {
        $input = $_POST;
    }
    $userId = trim((string) ($input['user_id'] ?? ''));
    $tenantId = trim((string) ($input['tenant_id'] ?? ''));
    $receiverId = trim((string) ($input['receiver_id'] ?? ''));
    $content = trim((string) ($input['message'] ?? ''));

    if ($userId === '' || $tenantId === '') {
        psm_json(false, 'user_id and tenant_id are required.');
    }
    if ($content === '') {
        psm_json(false, 'Message cannot be empty.');
    }
    if ($receiverId === '') {
        psm_json(false, 'Choose a staff member to message.');
    }

    $client = psm_verify_client($pdo, $tenantId, $userId);
    if ($client === null) {
        psm_json(false, 'Invalid or inactive patient account.');
    }
    if (!psm_verify_staff_receiver($pdo, $tenantId, $receiverId)) {
        psm_json(false, 'That staff account is not available for messaging.');
    }

    try {
        $insert = $pdo->prepare("
            INSERT INTO tbl_messages (
                tenant_id, sender_id, receiver_id, subject, message, is_read, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 0, 'sent', NOW())
        ");
        $insert->execute([
            $tenantId,
            $userId,
            $receiverId,
            null,
            $content,
        ]);
        psm_json(true, 'Message sent.', ['message_id' => (int) $pdo->lastInsertId()]);
    } catch (Throwable $e) {
        psm_json(false, 'Unable to send message right now.');
    }
}

// GET
$userId = trim((string) ($_GET['user_id'] ?? ''));
$tenantId = trim((string) ($_GET['tenant_id'] ?? ''));
$with = trim((string) ($_GET['with'] ?? ''));

if ($userId === '' || $tenantId === '') {
    psm_json(false, 'user_id and tenant_id are required.');
}

$client = psm_verify_client($pdo, $tenantId, $userId);
if ($client === null) {
    psm_json(false, 'Invalid or inactive patient account.');
}

if ($with !== '') {
    if (!psm_verify_staff_receiver($pdo, $tenantId, $with)) {
        psm_json(false, 'Invalid conversation partner.');
    }
    try {
        $msgStmt = $pdo->prepare("
            SELECT id, sender_id, receiver_id, message, is_read, status, created_at
            FROM tbl_messages
            WHERE tenant_id = ?
              AND (
                (sender_id = ? AND receiver_id = ?)
                OR
                (sender_id = ? AND receiver_id = ?)
              )
            ORDER BY created_at ASC, id ASC
        ");
        $msgStmt->execute([$tenantId, $userId, $with, $with, $userId]);
        $rows = $msgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $markStmt = $pdo->prepare("
            UPDATE tbl_messages
            SET is_read = 1, status = 'seen'
            WHERE tenant_id = ?
              AND sender_id = ?
              AND receiver_id = ?
              AND is_read = 0
        ");
        $markStmt->execute([$tenantId, $with, $userId]);

        $out = [];
        foreach ($rows as $m) {
            $sid = (string) ($m['sender_id'] ?? '');
            $out[] = [
                'id' => (int) ($m['id'] ?? 0),
                'sender_id' => $sid,
                'receiver_id' => (string) ($m['receiver_id'] ?? ''),
                'message' => (string) ($m['message'] ?? ''),
                'created_at' => (string) ($m['created_at'] ?? ''),
                'mine' => $sid === $userId,
            ];
        }
        psm_json(true, 'Messages loaded.', ['messages' => $out]);
    } catch (Throwable $e) {
        psm_json(false, 'Could not load messages.');
    }
}

// Contacts list
try {
    $listStmt = $pdo->prepare("
        SELECT user_id, email, full_name, role
        FROM tbl_users
        WHERE tenant_id = ?
          AND role IN ('tenant_owner', 'manager', 'staff', 'dentist')
          AND status = 'active'
        ORDER BY
            CASE role
                WHEN 'tenant_owner' THEN 0
                WHEN 'manager' THEN 1
                WHEN 'dentist' THEN 2
                ELSE 3
            END,
            full_name ASC,
            email ASC
    ");
    $listStmt->execute([$tenantId]);
    $staffRows = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $staffRows = [];
}

try {
    $convStmt = $pdo->prepare("
        SELECT
            CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS partner_id,
            MAX(m.created_at) AS last_message_at
        FROM tbl_messages m
        INNER JOIN tbl_users su
            ON su.user_id = CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END
        WHERE m.tenant_id = ?
          AND (m.sender_id = ? OR m.receiver_id = ?)
          AND su.tenant_id = ?
          AND su.role IN ('tenant_owner', 'manager', 'staff', 'dentist')
        GROUP BY partner_id
    ");
    $convStmt->execute([$userId, $userId, $tenantId, $userId, $userId, $tenantId]);
    $conversations = $convStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $convMap = [];
    foreach ($conversations as $row) {
        $pid = (string) ($row['partner_id'] ?? '');
        if ($pid !== '') {
            $convMap[$pid] = $row;
        }
    }

    $staffById = [];
    foreach ($staffRows as $r) {
        $uid = (string) ($r['user_id'] ?? '');
        if ($uid !== '') {
            $staffById[$uid] = $r;
        }
    }

    foreach (array_keys($convMap) as $pid) {
        if ($pid !== '' && !isset($staffById[$pid])) {
            $extraStmt = $pdo->prepare("
                SELECT user_id, email, full_name, role
                FROM tbl_users
                WHERE tenant_id = ? AND user_id = ?
                  AND role IN ('tenant_owner', 'manager', 'staff', 'dentist')
                LIMIT 1
            ");
            $extraStmt->execute([$tenantId, $pid]);
            $ex = $extraStmt->fetch(PDO::FETCH_ASSOC);
            if ($ex && (string) ($ex['user_id'] ?? '') !== '') {
                $staffById[$pid] = $ex;
            }
        }
    }

    $contacts = [];
    foreach ($staffById as $sid => $person) {
        $lastAt = isset($convMap[$sid]['last_message_at']) ? (string) $convMap[$sid]['last_message_at'] : '';

        $unreadStmt = $pdo->prepare("
            SELECT COUNT(*) FROM tbl_messages
            WHERE tenant_id = ?
              AND sender_id = ?
              AND receiver_id = ?
              AND is_read = 0
        ");
        $unreadStmt->execute([$tenantId, $sid, $userId]);
        $unread = (int) $unreadStmt->fetchColumn();

        $fn = trim((string) ($person['full_name'] ?? ''));
        $em = trim((string) ($person['email'] ?? ''));
        $name = $fn !== '' ? $fn : ($em !== '' ? $em : 'Clinic');
        $role = psm_role_label((string) ($person['role'] ?? ''));

        $contacts[] = [
            'user_id' => $sid,
            'name' => $name,
            'role' => $role,
            'email' => $em,
            'initials' => psm_initials($fn, $em),
            'unread_count' => $unread,
            'last_message_at' => $lastAt,
        ];
    }

    usort($contacts, static function (array $a, array $b): int {
        $ta = (string) ($a['last_message_at'] ?? '');
        $tb = (string) ($b['last_message_at'] ?? '');
        if ($ta !== $tb) {
            return strcmp($tb, $ta);
        }
        return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
    });

    psm_json(true, 'Contacts loaded.', ['contacts' => $contacts]);
} catch (Throwable $e) {
    psm_json(false, 'Could not load contacts.');
}
