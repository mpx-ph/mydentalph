<?php
/**
 * Tenant-scoped public services catalog (patient-facing services page).
 */
declare(strict_types=1);

/**
 * Create table if missing (idempotent for shared hosting).
 */
function tenant_public_services_ensure_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_tenant_public_services (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            price_range VARCHAR(255) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tenant_sort (tenant_id, sort_order),
            KEY idx_tenant_created (tenant_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

/**
 * @return list<array{id:int,tenant_id:string,title:string,description:string,price_range:string,sort_order:int,created_at:string}>
 */
function tenant_public_services_fetch_for_tenant(PDO $pdo, string $tenantId): array
{
    $tenantId = trim($tenantId);
    if ($tenantId === '') {
        return [];
    }
    try {
        tenant_public_services_ensure_table($pdo);
        $st = $pdo->prepare('
            SELECT id, tenant_id, title, description, price_range, sort_order, created_at
            FROM tbl_tenant_public_services
            WHERE tenant_id = ?
            ORDER BY sort_order ASC, id ASC
        ');
        $st->execute([$tenantId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        error_log('tenant_public_services_fetch_for_tenant: ' . $e->getMessage());
        return [];
    }
}
