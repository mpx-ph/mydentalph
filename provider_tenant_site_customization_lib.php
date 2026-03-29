<?php
declare(strict_types=1);

/**
 * Merge clinic customization defaults + global table + per-tenant overrides.
 * Used by ProviderTenantSiteBuilder and ProviderTenantSiteCustomizationApi.
 */
function provider_tenant_site_merged_options(PDO $pdo, string $tenantId): array
{
    $defaults = require __DIR__ . '/clinic/config/clinic_customization_schema.php';
    $options = $defaults;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clinic_customization (
                option_key VARCHAR(120) NOT NULL PRIMARY KEY,
                option_value TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $stmtGlobal = $pdo->query('SELECT option_key, option_value FROM clinic_customization');
        if ($stmtGlobal) {
            while ($row = $stmtGlobal->fetch(PDO::FETCH_ASSOC)) {
                $options[$row['option_key']] = $row['option_value'];
            }
        }
        $tenantId = trim($tenantId);
        if ($tenantId !== '') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS clinic_customization_tenant (
                    tenant_id VARCHAR(50) NOT NULL,
                    option_key VARCHAR(120) NOT NULL,
                    option_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (tenant_id, option_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $stmtTenant = $pdo->prepare('
                SELECT option_key, option_value
                FROM clinic_customization_tenant
                WHERE tenant_id = ?
            ');
            $stmtTenant->execute([$tenantId]);
            while ($row = $stmtTenant->fetch(PDO::FETCH_ASSOC)) {
                $options[$row['option_key']] = $row['option_value'];
            }
        }
    } catch (Throwable $e) {
        error_log('provider_tenant_site_merged_options: ' . $e->getMessage());
    }
    return $options;
}
