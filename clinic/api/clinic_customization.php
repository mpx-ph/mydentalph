<?php
/**
 * Clinic website customization API.
 * GET: return all options. POST: save options (and handle image uploads).
 * Requires admin authentication.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id']) || !in_array($_SESSION['user_type'] ?? '', ['admin', 'staff', 'doctor', 'manager'])) {
    jsonResponse(false, 'Unauthorized.');
}

// Determine current tenant for admin users. This should be set by your SSO / login flow.
$currentTenantId = $_SESSION['tenant_id'] ?? ($_SESSION['public_tenant_id'] ?? null);

if (empty($currentTenantId)) {
    // Fallback for legacy single-clinic setups – all changes are global.
    $currentTenantId = 'GLOBAL';
}

$pdo = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    getCustomization($currentTenantId);
} elseif ($method === 'POST') {
    saveCustomization($currentTenantId);
} else {
    jsonResponse(false, 'Invalid method.');
}

/**
 * Ensure customization tables exist and return merged options for the given tenant.
 */
function getCustomization(string $tenantId) {
    global $pdo;
    $defaults = require __DIR__ . '/../config/clinic_customization_schema.php';
    try {
        // Global table shared by all tenants
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clinic_customization (
                option_key VARCHAR(120) NOT NULL PRIMARY KEY,
                option_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Per-tenant overrides
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clinic_customization_tenant (
                tenant_id VARCHAR(50) NOT NULL,
                option_key VARCHAR(120) NOT NULL,
                option_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (tenant_id, option_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $options = $defaults;

        // Apply global overrides
        $stmtGlobal = $pdo->query("SELECT option_key, option_value FROM clinic_customization");
        if ($stmtGlobal) {
            while ($row = $stmtGlobal->fetch(PDO::FETCH_ASSOC)) {
                $options[$row['option_key']] = $row['option_value'];
            }
        }

        // Apply tenant-specific overrides
        if ($tenantId !== 'GLOBAL') {
            $stmtTenant = $pdo->prepare("
                SELECT option_key, option_value
                FROM clinic_customization_tenant
                WHERE tenant_id = ?
            ");
            $stmtTenant->execute([$tenantId]);
            while ($row = $stmtTenant->fetch(PDO::FETCH_ASSOC)) {
                $options[$row['option_key']] = $row['option_value'];
            }
        }

        jsonResponse(true, 'OK', $options);
    } catch (Exception $e) {
        error_log('clinic_customization get: ' . $e->getMessage());
        jsonResponse(false, 'Failed to load customization.', $defaults);
    }
}

/**
 * Save customization for a specific tenant.
 * Accepts JSON body with key-value pairs.
 * For keys that are image fields, value can be empty and file sent as multipart with key like "file_main_hero_image".
 */
function saveCustomization(string $tenantId) {
    global $pdo;
    $defaults = require __DIR__ . '/../config/clinic_customization_schema.php';
    $imageKeys = [
        'main_hero_image', 'main_doctor_image', 'about_hero_image',
        'about_team_doctor1_image', 'about_team_doctor2_image',
        'logo', 'logo_nav', 'logo_register', 'site_favicon',
    ];
    $uploadDir = 'uploads/clinic/';
    $input = [];

    if (!empty($_POST['data'])) {
        $input = json_decode($_POST['data'], true);
        if (!is_array($input)) {
            $input = [];
        }
    } elseif (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            $input = [];
        }
    }

    foreach ($_POST as $k => $v) {
        if ($k === 'data') continue;
        if (strpos($k, 'file_') === 0) {
            continue;
        }
        if (array_key_exists($k, $defaults)) {
            $input[$k] = $v;
        }
    }

    foreach ($imageKeys as $key) {
        $fileKey = 'file_' . $key;
        if (!empty($_FILES[$fileKey]['tmp_name']) && is_uploaded_file($_FILES[$fileKey]['tmp_name'])) {
            $result = uploadFile($_FILES[$fileKey], $uploadDir);
            if ($result['success'] && $result['filename']) {
                $input[$key] = $uploadDir . $result['filename'];
            }
        }
    }

    try {
        // Ensure tenant table exists
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clinic_customization_tenant (
                tenant_id VARCHAR(50) NOT NULL,
                option_key VARCHAR(120) NOT NULL,
                option_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (tenant_id, option_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Persist per-tenant values
        $stmt = $pdo->prepare("
            INSERT INTO clinic_customization_tenant (tenant_id, option_key, option_value)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)
        ");

        foreach ($input as $key => $value) {
            if (!array_key_exists($key, $defaults)) continue;
            $stmt->execute([$tenantId, $key, (string)$value]);
        }

        jsonResponse(true, 'Customization saved.');
    } catch (Exception $e) {
        error_log('clinic_customization save: ' . $e->getMessage());
        jsonResponse(false, 'Failed to save.');
    }
}
