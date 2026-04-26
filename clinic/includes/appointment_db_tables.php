<?php
/**
 * Resolve physical appointment-related table names: deployments may use tbl_* (schema.sql)
 * or legacy unprefixed names (users, patients, appointments, …).
 *
 * Staff UI and walk-in booking must resolve the same names so reads and writes hit one store.
 */

if (!function_exists('clinic_get_physical_table_name')) {
    /**
     * Return the exact table name as stored in MySQL (handles case / hosting quirks).
     *
     * @return string|null
     */
    function clinic_get_physical_table_name(PDO $pdo, string $preferredName)
    {
        static $cache = [];
        $key = strtolower($preferredName);
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND LOWER(TABLE_NAME) = LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$preferredName]);
        $found = $stmt->fetchColumn();
        if ($found) {
            $cache[$key] = (string) $found;
            return $cache[$key];
        }
        // Shared hosts sometimes restrict information_schema; fall back to SHOW TABLES.
        try {
            $tables = $pdo->query('SHOW TABLES');
            if ($tables) {
                while ($row = $tables->fetch(PDO::FETCH_NUM)) {
                    $name = isset($row[0]) ? (string) $row[0] : '';
                    if ($name !== '' && strcasecmp($name, $preferredName) === 0) {
                        $cache[$key] = $name;
                        return $cache[$key];
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
        $cache[$key] = null;
        return null;
    }
}

if (!function_exists('clinic_table_exists')) {
    /**
     * @return bool
     */
    function clinic_table_exists(PDO $pdo, string $tableName)
    {
        return clinic_get_physical_table_name($pdo, $tableName) !== null;
    }
}

if (!function_exists('clinic_resolve_appointment_db_tables')) {
    /**
     * @return array{
     *   appointments: ?string,
     *   appointment_services: ?string,
     *   services: ?string,
     *   patients: ?string,
     *   users: ?string,
     *   payments: ?string,
 *   treatments: ?string,
     *   tenants: ?string,
     *   dentists: ?string
     * }
     */
    function clinic_resolve_appointment_db_tables(PDO $pdo)
    {
        $pick = static function (PDO $pdo, array $candidates) {
            foreach ($candidates as $t) {
                $phys = clinic_get_physical_table_name($pdo, $t);
                if ($phys !== null) {
                    return $phys;
                }
            }
            return null;
        };

        return [
            'appointments' => $pick($pdo, ['tbl_appointments', 'appointments']),
            'appointment_services' => $pick($pdo, ['tbl_appointment_services', 'appointment_services']),
            'services' => $pick($pdo, ['tbl_services', 'services']),
            'patients' => $pick($pdo, ['tbl_patients', 'patients']),
            'users' => $pick($pdo, ['tbl_users', 'users']),
            'payments' => $pick($pdo, ['tbl_payments', 'payments']),
            'treatments' => $pick($pdo, ['tbl_treatments', 'treatments']),
            'tenants' => $pick($pdo, ['tbl_tenants', 'tenants']),
            'dentists' => $pick($pdo, ['tbl_dentists', 'dentists']),
        ];
    }
}

if (!function_exists('clinic_quote_identifier')) {
    function clinic_quote_identifier(string $ident)
    {
        return '`' . str_replace('`', '``', $ident) . '`';
    }
}

if (!function_exists('clinic_resolve_walkin_tenant_id')) {
    /**
     * Tenant for walk-in API: SSO session, public session, or clinic_slug lookup (no tenant_bootstrap required).
     *
     * @return string|null
     */
    function clinic_resolve_walkin_tenant_id(PDO $pdo)
    {
        if (function_exists('getClinicTenantId')) {
            $id = getClinicTenantId();
            if (!empty($id)) {
                return (string) $id;
            }
        }
        if (!empty($_SESSION['tenant_id'])) {
            return (string) $_SESSION['tenant_id'];
        }
        if (!empty($_SESSION['public_tenant_id'])) {
            return (string) $_SESSION['public_tenant_id'];
        }
        $slug = isset($_GET['clinic_slug']) ? strtolower(trim((string) $_GET['clinic_slug'])) : '';
        if ($slug === '' || !preg_match('/^[a-z0-9\-]+$/', $slug)) {
            return null;
        }
        $tenantsTable = clinic_get_physical_table_name($pdo, 'tbl_tenants')
            ?? clinic_get_physical_table_name($pdo, 'tenants');
        if ($tenantsTable === null) {
            return null;
        }
        $q = clinic_quote_identifier($tenantsTable);
        $stmt = $pdo->prepare("SELECT tenant_id FROM {$q} WHERE clinic_slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['tenant_id']) && (string) $row['tenant_id'] !== '') {
            return (string) $row['tenant_id'];
        }
        return null;
    }
}

if (!function_exists('clinic_table_columns')) {
    /**
     * Column names for a table (lowercase). Uses information_schema first; if that returns
     * nothing (common on some shared hosts with restricted metadata), falls back to SHOW COLUMNS.
     * Walk-in and other dynamic INSERTs need the full column list — an empty list caused inserts
     * to omit service_type, service_description, dentist_id, etc., so phpMyAdmin looked "empty".
     *
     * @return list<string>
     */
    function clinic_table_columns(PDO $pdo, string $tableName)
    {
        $phys = clinic_get_physical_table_name($pdo, $tableName) ?? $tableName;
        static $cache = [];
        if (isset($cache[$phys])) {
            return $cache[$phys];
        }
        $cols = [];
        try {
            $stmt = $pdo->prepare("
                SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
            ");
            $stmt->execute([$phys]);
            $cols = array_map('strtolower', array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        } catch (Throwable $e) {
            $cols = [];
        }
        if ($cols === []) {
            try {
                $q = clinic_quote_identifier($phys);
                $show = $pdo->query('SHOW COLUMNS FROM ' . $q);
                if ($show) {
                    while ($row = $show->fetch(PDO::FETCH_ASSOC)) {
                        if (!empty($row['Field'])) {
                            $cols[] = strtolower((string) $row['Field']);
                        }
                    }
                }
            } catch (Throwable $e) {
                $cols = [];
            }
        }
        $cache[$phys] = $cols;
        return $cache[$phys];
    }
}

if (!function_exists('clinic_appointments_ensure_in_progress_in_status_enum')) {
    /**
     * Older databases may have tbl_appointments.status as an ENUM that omits in_progress.
     * MySQL then stores '' for invalid values, which looks "blank" in clients.
     * This extends the ENUM idempotently when the app can ALTER the table.
     */
    function clinic_appointments_ensure_in_progress_in_status_enum(PDO $pdo, string $appointmentsTableName): void
    {
        if (!in_array('status', clinic_table_columns($pdo, $appointmentsTableName), true)) {
            return;
        }
        $phys = clinic_get_physical_table_name($pdo, $appointmentsTableName) ?? $appointmentsTableName;
        $q = clinic_quote_identifier($phys);

        $columnType = null;
        $isNullable = 'YES';
        $columnDefault = 'pending';
        try {
            $meta = $pdo->prepare("
                SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = 'status'
            ");
            $meta->execute([$phys]);
            $row = $meta->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row) {
                $columnType = isset($row['COLUMN_TYPE']) ? (string) $row['COLUMN_TYPE'] : null;
                $isNullable = isset($row['IS_NULLABLE']) ? strtoupper((string) $row['IS_NULLABLE']) : 'YES';
                if (array_key_exists('COLUMN_DEFAULT', $row)) {
                    if ($row['COLUMN_DEFAULT'] === null) {
                        $columnDefault = $isNullable === 'YES' ? '' : 'pending';
                    } else {
                        $columnDefault = (string) $row['COLUMN_DEFAULT'];
                    }
                }
            }
        } catch (Throwable $e) {
            $columnType = null;
        }
        if ($columnType === null) {
            try {
                $show = $pdo->query('SHOW COLUMNS FROM ' . $q . " LIKE 'status'");
                $r = $show ? $show->fetch(PDO::FETCH_ASSOC) : null;
                if ($r) {
                    $columnType = isset($r['Type']) ? (string) $r['Type'] : null;
                    $isNullable = isset($r['Null']) && strtoupper((string) $r['Null']) === 'NO' ? 'NO' : 'YES';
                    if (array_key_exists('Default', $r) && $r['Default'] !== null) {
                        $columnDefault = (string) $r['Default'];
                    } elseif (array_key_exists('Default', $r) && $r['Default'] === null) {
                        $columnDefault = '';
                    }
                }
            } catch (Throwable $e) {
                return;
            }
        }
        if ($columnType === null || stripos($columnType, 'enum(') !== 0) {
            return;
        }
        if (preg_match("/'in_progress'/", $columnType)) {
            return;
        }
        if (!preg_match_all("/'([a-z0-9_]+)'/i", $columnType, $enumParts) || empty($enumParts[1])) {
            return;
        }
        $values = $enumParts[1];
        if (in_array('in_progress', $values, true)) {
            return;
        }
        $idx = array_search('scheduled', $values, true);
        if ($idx !== false) {
            array_splice($values, (int) $idx + 1, 0, ['in_progress']);
        } else {
            $values[] = 'in_progress';
        }
        $enumList = implode(',', array_map(static function (string $v) {
            return "'" . str_replace("'", "''", $v) . "'";
        }, $values));
        $nullSql = $isNullable === 'NO' ? 'NOT NULL' : 'NULL';
        if ($isNullable === 'YES' && ($columnDefault === '' || $columnDefault === 'NULL')) {
            $defaultSql = 'DEFAULT NULL';
        } elseif ((string) $columnDefault === '') {
            $defaultSql = "DEFAULT 'pending'";
        } else {
            $d = (string) $columnDefault;
            $defaultSql = "DEFAULT '" . str_replace("'", "''", $d) . "'";
        }
        $sql = "ALTER TABLE {$q} MODIFY COLUMN `status` ENUM({$enumList}) {$nullSql} {$defaultSql}";
        $pdo->exec($sql);
    }
}
