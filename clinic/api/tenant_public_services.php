<?php
/**
 * JSON API: tenant public services catalog (provider portal session).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tenant.php';
require_once __DIR__ . '/../includes/tenant_public_services_lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = getDBConnection();
$tenantId = trim((string) ($_SESSION['tenant_id'] ?? ''));
$userId = trim((string) ($_SESSION['user_id'] ?? ''));

if ($tenantId === '' || $userId === '') {
    jsonResponse(false, 'Unauthorized.', ['code' => 'AUTH']);
}

tenant_public_services_ensure_table($pdo);

/**
 * @param array<string, mixed> $row
 */
function tenant_public_services_row_json(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'tenant_id' => (string) ($row['tenant_id'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'price_range' => (string) ($row['price_range'] ?? ''),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

try {
    if ($method === 'GET') {
        $st = $pdo->prepare('
            SELECT id, tenant_id, title, description, price_range, sort_order, created_at
            FROM tbl_tenant_public_services
            WHERE tenant_id = ?
            ORDER BY sort_order ASC, id ASC
        ');
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $r) {
            $out[] = tenant_public_services_row_json($r);
        }
        jsonResponse(true, 'OK', ['services' => $out]);
    }

    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $input = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($input)) {
            $input = $_POST;
        }

        $title = isset($input['title']) ? trim((string) $input['title']) : '';
        $description = isset($input['description']) ? trim((string) $input['description']) : '';
        $priceRange = isset($input['price_range']) ? trim((string) $input['price_range']) : '';

        if ($title === '') {
            jsonResponse(false, 'Main title is required.');
        }
        if (mb_strlen($title) > 255) {
            jsonResponse(false, 'Title is too long.');
        }
        if (mb_strlen($priceRange) > 255) {
            jsonResponse(false, 'Price range is too long.');
        }

        $mx = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM tbl_tenant_public_services WHERE tenant_id = ?');
        $mx->execute([$tenantId]);
        $nextSort = (int) $mx->fetchColumn() + 1;

        $ins = $pdo->prepare('
            INSERT INTO tbl_tenant_public_services (tenant_id, title, description, price_range, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ');
        $ins->execute([$tenantId, $title, $description, $priceRange, $nextSort]);
        $newId = (int) $pdo->lastInsertId();

        $st = $pdo->prepare('
            SELECT id, tenant_id, title, description, price_range, sort_order, created_at
            FROM tbl_tenant_public_services
            WHERE id = ? AND tenant_id = ?
        ');
        $st->execute([$newId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            jsonResponse(false, 'Failed to load new service.');
        }
        jsonResponse(true, 'Saved.', ['service' => tenant_public_services_row_json($row)]);
    }

    if ($method === 'DELETE') {
        $raw = file_get_contents('php://input');
        $input = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($input)) {
            $input = $_REQUEST;
        }
        $id = isset($input['id']) ? (int) $input['id'] : 0;
        if ($id <= 0) {
            jsonResponse(false, 'Invalid id.');
        }
        $del = $pdo->prepare('DELETE FROM tbl_tenant_public_services WHERE id = ? AND tenant_id = ?');
        $del->execute([$id, $tenantId]);
        if ($del->rowCount() === 0) {
            jsonResponse(false, 'Service not found.');
        }
        jsonResponse(true, 'Removed.');
    }

    jsonResponse(false, 'Method not allowed.');
} catch (Throwable $e) {
    error_log('tenant_public_services API: ' . $e->getMessage());
    jsonResponse(false, 'Server error.');
}
