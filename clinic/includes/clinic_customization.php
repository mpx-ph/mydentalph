<?php
/**
 * Load clinic website customization (images and text) from database.
 *
 * Multi-tenant behavior:
 * - Global defaults come from config/clinic_customization_schema.php
 * - Global overrides (all tenants) from clinic_customization
 * - Per-tenant overrides from clinic_customization_tenant (scoped by tenant_id)
 *
 * Usage:
 *   - Public pages should include tenant_bootstrap.php first to define $currentTenantId.
 *   - Then include this file and use $CLINIC['key'] in templates.
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

if (!function_exists('getDBConnection')) {
    require_once __DIR__ . '/../config/config.php';
}

if (!isset($CLINIC) || !is_array($CLINIC)) {
    $defaults = require __DIR__ . '/../config/clinic_customization_schema.php';
    $CLINIC = $defaults;

    try {
        $pdo = getDBConnection();

        // 1) Global overrides shared by all tenants
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clinic_customization (
                option_key VARCHAR(120) NOT NULL PRIMARY KEY,
                option_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $stmtGlobal = $pdo->query("SELECT option_key, option_value FROM clinic_customization");
        if ($stmtGlobal) {
            while ($row = $stmtGlobal->fetch(PDO::FETCH_ASSOC)) {
                $CLINIC[$row['option_key']] = $row['option_value'];
            }
        }

        // 2) Per-tenant overrides, if a current tenant is known
        if (!empty($currentTenantId)) {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS clinic_customization_tenant (
                    tenant_id VARCHAR(50) NOT NULL,
                    option_key VARCHAR(120) NOT NULL,
                    option_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (tenant_id, option_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $stmtTenant = $pdo->prepare("
                SELECT option_key, option_value
                FROM clinic_customization_tenant
                WHERE tenant_id = ?
            ");
            $stmtTenant->execute([$currentTenantId]);
            $tenantKeysLoaded = [];
            while ($row = $stmtTenant->fetch(PDO::FETCH_ASSOC)) {
                $CLINIC[$row['option_key']] = $row['option_value'];
                $tenantKeysLoaded[$row['option_key']] = true;
            }
            if (
                empty($tenantKeysLoaded['clinic_name'])
                && isset($currentTenantData) && is_array($currentTenantData)
            ) {
                $tn = trim((string) ($currentTenantData['clinic_name'] ?? ''));
                if ($tn !== '') {
                    $CLINIC['clinic_name'] = $tn;
                }
            }
        }
    } catch (Exception $e) {
        // Tables may not exist yet; use defaults only
        error_log('clinic_customization load: ' . $e->getMessage());
    }
}
