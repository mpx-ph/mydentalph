<?php
// api/get_dentists.php
require_once '../db.php';
require_once __DIR__ . '/../clinic/includes/appointment_db_tables.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die(json_encode(['status' => 'error', 'message' => 'GET required']));
}

$tenant_id = $_GET['tenant_id'] ?? '';
$with_schedule_blocks = isset($_GET['with_schedule_blocks']) && $_GET['with_schedule_blocks'] === '1';

if ($tenant_id === '' || $tenant_id === null) {
    die(json_encode(['status' => 'error', 'message' => 'Missing tenant_id']));
}

$dTable = clinic_get_physical_table_name($pdo, 'tbl_dentists')
    ?? clinic_get_physical_table_name($pdo, 'dentists');
$sbTable = clinic_get_physical_table_name($pdo, 'tbl_schedule_blocks')
    ?? clinic_get_physical_table_name($pdo, 'schedule_blocks');
if ($dTable === null) {
    die(json_encode(['status' => 'error', 'message' => 'Dentists table not found']));
}
$qD = clinic_quote_identifier($dTable);

try {
    if ($with_schedule_blocks && $sbTable !== null) {
        $qSb = clinic_quote_identifier($sbTable);
        // Only dentists linked to tbl_schedule_blocks (published shift/work — mobile booking uses these windows).
        $sql = "
            SELECT DISTINCT d.dentist_id, d.first_name, d.last_name, d.specialization
            FROM {$qD} d
            INNER JOIN {$qSb} sb ON sb.tenant_id = d.tenant_id AND sb.user_id = d.user_id
                AND sb.is_active = 1
                AND LOWER(sb.block_type) IN ('shift', 'work')
            WHERE d.tenant_id = ?
        ";
        if (clinic_table_exists($pdo, $dTable) && in_array('status', clinic_table_columns($pdo, $dTable), true)) {
            $sql .= " AND LOWER(COALESCE(d.status, '')) = 'active' ";
        } elseif (clinic_table_exists($pdo, $dTable) && in_array('is_active', clinic_table_columns($pdo, $dTable), true)) {
            $sql .= ' AND d.is_active = 1 ';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenant_id]);
        $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'status' => 'success',
            'dentists' => $dentists,
            'filtered_by_schedule_blocks' => true,
        ]);

        return;
    }

    $stmt = $pdo->prepare(
        "SELECT dentist_id, first_name, last_name, specialization
         FROM {$qD}
         WHERE tenant_id = ? AND is_active = 1"
    );
    $stmt->execute([$tenant_id]);
    $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // In case there is no is_active column or the query fails due to it, let's gracefully fall back
    if ($dentists === false || empty($dentists)) {
        $stmt = $pdo->prepare(
            "SELECT dentist_id, first_name, last_name, specialization
             FROM {$qD}
             WHERE tenant_id = ?"
        );
        $stmt->execute([$tenant_id]);
        $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode([
        'status' => 'success',
        'dentists' => $dentists,
        'filtered_by_schedule_blocks' => false,
    ]);
} catch (Exception $e) {
    try {
        $stmt = $pdo->prepare(
            "SELECT dentist_id, first_name, last_name, specialization
             FROM {$qD}
             WHERE tenant_id = ?"
        );
        $stmt->execute([$tenant_id]);
        $dentists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'status' => 'success',
            'dentists' => $dentists,
            'filtered_by_schedule_blocks' => false,
        ]);
    } catch (Exception $ex) {
        echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $ex->getMessage()]);
    }
}

