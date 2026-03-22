<?php

/**
 * Super Admin branding (system name, logo path, sidebar tagline).
 * Table is created automatically on first use if missing.
 */

function superadmin_settings_ensure_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tbl_superadmin_settings (
            id INT NOT NULL PRIMARY KEY DEFAULT 1,
            system_name VARCHAR(255) NOT NULL DEFAULT 'MyDental',
            brand_logo_path VARCHAR(512) NOT NULL DEFAULT 'MyDental Logo.svg',
            brand_tagline VARCHAR(255) NOT NULL DEFAULT 'MANAGEMENT CONSOLE',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $n = (int) $pdo->query('SELECT COUNT(*) FROM tbl_superadmin_settings WHERE id = 1')->fetchColumn();
    if ($n === 0) {
        $pdo->exec("INSERT INTO tbl_superadmin_settings (id, system_name, brand_logo_path, brand_tagline)
            VALUES (1, 'MyDental', 'MyDental Logo.svg', 'MANAGEMENT CONSOLE')");
    }
}

/**
 * @return array{system_name: string, brand_logo_path: string, brand_tagline: string}
 */
function superadmin_get_settings(PDO $pdo): array
{
    superadmin_settings_ensure_table($pdo);
    $stmt = $pdo->query('SELECT system_name, brand_logo_path, brand_tagline FROM tbl_superadmin_settings WHERE id = 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [
            'system_name' => 'MyDental',
            'brand_logo_path' => 'MyDental Logo.svg',
            'brand_tagline' => 'MANAGEMENT CONSOLE',
        ];
    }
    return [
        'system_name' => (string) $row['system_name'],
        'brand_logo_path' => (string) $row['brand_logo_path'],
        'brand_tagline' => (string) $row['brand_tagline'],
    ];
}

function superadmin_sanitize_logo_relative_path(string $path): string
{
    $path = str_replace(["\0", "\r", "\n"], '', trim($path));
    if ($path === '') {
        return 'MyDental Logo.svg';
    }
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    $path = str_replace('\\', '/', $path);
    if (strpos($path, '..') !== false) {
        return 'MyDental Logo.svg';
    }
    return $path;
}

/**
 * @param array{system_name?: string, brand_logo_path?: string, brand_tagline?: string} $data
 */
function superadmin_save_settings(PDO $pdo, array $data): void
{
    superadmin_settings_ensure_table($pdo);
    $name = isset($data['system_name']) ? trim((string) $data['system_name']) : 'MyDental';
    if ($name === '') {
        $name = 'MyDental';
    }
    if (function_exists('mb_substr')) {
        $name = mb_substr($name, 0, 255);
    } else {
        $name = substr($name, 0, 255);
    }

    $logo = isset($data['brand_logo_path']) ? superadmin_sanitize_logo_relative_path((string) $data['brand_logo_path']) : 'MyDental Logo.svg';
    if (function_exists('mb_substr')) {
        $logo = mb_substr($logo, 0, 512);
    } else {
        $logo = substr($logo, 0, 512);
    }

    $tag = isset($data['brand_tagline']) ? trim((string) $data['brand_tagline']) : 'MANAGEMENT CONSOLE';
    if ($tag === '') {
        $tag = 'MANAGEMENT CONSOLE';
    }
    if (function_exists('mb_substr')) {
        $tag = mb_substr($tag, 0, 255);
    } else {
        $tag = substr($tag, 0, 255);
    }

    $stmt = $pdo->prepare('UPDATE tbl_superadmin_settings SET system_name = ?, brand_logo_path = ?, brand_tagline = ? WHERE id = 1');
    $stmt->execute([$name, $logo, $tag]);
}
