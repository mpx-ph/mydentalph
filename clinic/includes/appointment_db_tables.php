<?php
/**
 * Resolve physical appointment-related table names: deployments may use tbl_* (schema.sql)
 * or legacy unprefixed names (users, patients, appointments, …).
 *
 * Staff UI and walk-in booking must resolve the same names so reads and writes hit one store.
 */

if (!function_exists('clinic_table_exists')) {
    /**
     * @return bool
     */
    function clinic_table_exists(PDO $pdo, string $tableName)
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName]);
        $cache[$tableName] = (bool) $stmt->fetchColumn();
        return $cache[$tableName];
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
     *   tenants: ?string,
     *   dentists: ?string
     * }
     */
    function clinic_resolve_appointment_db_tables(PDO $pdo)
    {
        $pick = static function (PDO $pdo, array $candidates) {
            foreach ($candidates as $t) {
                if (clinic_table_exists($pdo, $t)) {
                    return $t;
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

if (!function_exists('clinic_table_columns')) {
    /**
     * @return list<string>
     */
    function clinic_table_columns(PDO $pdo, string $tableName)
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        $stmt->execute([$tableName]);
        $cache[$tableName] = array_map('strtolower', array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
        return $cache[$tableName];
    }
}
