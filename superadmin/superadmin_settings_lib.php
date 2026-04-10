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

    // Add new columns on existing installs without requiring manual migrations.
    $col = $pdo->query("SHOW COLUMNS FROM tbl_superadmin_settings LIKE 'provider_plans_json'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        $pdo->exec("ALTER TABLE tbl_superadmin_settings ADD COLUMN provider_plans_json LONGTEXT NULL AFTER brand_tagline");
    }
}

/**
 * @return array<string, array{name: string, price: string, description: string, cta: string, features: array<int, string>}>
 */
function superadmin_default_provider_plans(): array
{
    return [
        'monthly' => [
            'name' => 'MONTHLY',
            'price' => '₱4,999',
            'description' => 'Full platform access billed monthly with no annual commitment.',
            'cta' => 'Choose Monthly',
            'features' => [
                'Booking App',
                'Professional Clinic Management System',
                'Advanced Analytics Dashboards',
                '24/7 Priority Support',
                'Multi-user Access Control',
            ],
        ],
        'yearly' => [
            'name' => 'YEARLY',
            'price' => '₱47,998',
            'description' => 'Promo: 20% off annual billing. Regular ₱59,988/year, save ₱11,990.',
            'cta' => 'Choose Yearly',
            'features' => [
                'Booking App',
                'Professional Clinic Management System',
                'Advanced Analytics Dashboards',
                '24/7 Priority Support',
                'Multi-user Access Control',
            ],
        ],
    ];
}

function superadmin_trim(string $value, int $length): string
{
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $length);
    }
    return substr($value, 0, $length);
}

/**
 * @param mixed $raw
 * @return array<string, array{name: string, price: string, description: string, cta: string, features: array<int, string>}>
 */
function superadmin_sanitize_provider_plans($raw): array
{
    $defaults = superadmin_default_provider_plans();
    if (!is_array($raw)) {
        return $defaults;
    }

    $result = $defaults;
    foreach ($defaults as $key => $defPlan) {
        $plan = isset($raw[$key]) && is_array($raw[$key]) ? $raw[$key] : [];
        $name = isset($plan['name']) ? superadmin_trim((string) $plan['name'], 80) : $defPlan['name'];
        $price = isset($plan['price']) ? superadmin_trim((string) $plan['price'], 40) : $defPlan['price'];
        $desc = isset($plan['description']) ? superadmin_trim((string) $plan['description'], 255) : $defPlan['description'];
        $cta = isset($plan['cta']) ? superadmin_trim((string) $plan['cta'], 60) : $defPlan['cta'];

        if ($name === '') {
            $name = $defPlan['name'];
        }
        if ($price === '') {
            $price = $defPlan['price'];
        }
        if ($desc === '') {
            $desc = $defPlan['description'];
        }
        if ($cta === '') {
            $cta = $defPlan['cta'];
        }

        $features = $defPlan['features'];
        if (isset($plan['features']) && is_array($plan['features'])) {
            $tmp = [];
            foreach ($plan['features'] as $feature) {
                $feature = superadmin_trim((string) $feature, 120);
                if ($feature !== '') {
                    $tmp[] = $feature;
                }
            }
            if (!empty($tmp)) {
                $features = array_values(array_slice($tmp, 0, 8));
            }
        }

        $result[$key] = [
            'name' => $name,
            'price' => $price,
            'description' => $desc,
            'cta' => $cta,
            'features' => $features,
        ];
    }
    return $result;
}

/**
 * @return array{system_name: string, brand_logo_path: string, brand_tagline: string, provider_plans: array<string, array{name: string, price: string, description: string, cta: string, features: array<int, string>}>}
 */
function superadmin_get_settings(PDO $pdo): array
{
    superadmin_settings_ensure_table($pdo);
    $stmt = $pdo->query('SELECT system_name, brand_logo_path, brand_tagline, provider_plans_json FROM tbl_superadmin_settings WHERE id = 1');
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return [
            'system_name' => 'MyDental',
            'brand_logo_path' => 'MyDental Logo.svg',
            'brand_tagline' => 'MANAGEMENT CONSOLE',
            'provider_plans' => superadmin_default_provider_plans(),
        ];
    }

    $plansRaw = null;
    if (!empty($row['provider_plans_json'])) {
        $decoded = json_decode((string) $row['provider_plans_json'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $plansRaw = $decoded;
        }
    }

    return [
        'system_name' => (string) $row['system_name'],
        'brand_logo_path' => (string) $row['brand_logo_path'],
        'brand_tagline' => (string) $row['brand_tagline'],
        'provider_plans' => superadmin_sanitize_provider_plans($plansRaw),
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

function superadmin_extract_numeric_plan_price(string $priceLabel): ?float
{
    if (trim($priceLabel) === '') {
        return null;
    }
    $cleaned = preg_replace('/[^0-9.\-]/', '', $priceLabel);
    if (!is_string($cleaned) || $cleaned === '' || !is_numeric($cleaned)) {
        return null;
    }
    $value = (float) $cleaned;
    return $value > 0 ? $value : null;
}

/**
 * @param array<string, array{name: string, price: string, description: string, cta: string, features: array<int, string>}> $plans
 */
function superadmin_sync_subscription_plan_prices(PDO $pdo, array $plans): void
{
    $tableExists = (bool) $pdo->query("SHOW TABLES LIKE 'tbl_subscription_plans'")->fetchColumn();
    if (!$tableExists) {
        return;
    }

    $columnsStmt = $pdo->query("SHOW COLUMNS FROM tbl_subscription_plans");
    $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $columnNames = [];
    foreach ($columns as $col) {
        if (isset($col['Field'])) {
            $columnNames[] = (string) $col['Field'];
        }
    }
    if (!in_array('plan_name', $columnNames, true) || !in_array('price', $columnNames, true)) {
        return;
    }
    $hasSlug = in_array('plan_slug', $columnNames, true);

    $upsertWithSlug = null;
    $findByName = $pdo->prepare('SELECT plan_id FROM tbl_subscription_plans WHERE LOWER(plan_name) = ? LIMIT 1');
    $updateById = $pdo->prepare('UPDATE tbl_subscription_plans SET plan_name = ?, price = ? WHERE plan_id = ?');
    $insertWithoutSlug = $pdo->prepare('INSERT INTO tbl_subscription_plans (plan_name, price) VALUES (?, ?)');

    if ($hasSlug) {
        $upsertWithSlug = $pdo->prepare(
            'INSERT INTO tbl_subscription_plans (plan_name, price, plan_slug) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE plan_name = VALUES(plan_name), price = VALUES(price)'
        );
    }

    foreach ($plans as $slug => $plan) {
        if (!is_array($plan)) {
            continue;
        }
        $name = isset($plan['name']) ? trim((string) $plan['name']) : '';
        $priceLabel = isset($plan['price']) ? (string) $plan['price'] : '';
        $numericPrice = superadmin_extract_numeric_plan_price($priceLabel);
        if ($name === '' || $numericPrice === null) {
            continue;
        }

        if ($hasSlug && $upsertWithSlug) {
            $upsertWithSlug->execute([$name, $numericPrice, $slug]);
            continue;
        }

        $findByName->execute([strtolower($name)]);
        $existingPlanId = $findByName->fetchColumn();
        if ($existingPlanId !== false) {
            $updateById->execute([$name, $numericPrice, (int) $existingPlanId]);
        } else {
            $insertWithoutSlug->execute([$name, $numericPrice]);
        }
    }
}

/**
 * @param array{system_name?: string, brand_logo_path?: string, brand_tagline?: string, provider_plans?: array<string, array{name?: string, price?: string, description?: string, cta?: string, features?: array<int, string>}|mixed>} $data
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

    $plans = superadmin_default_provider_plans();
    if (isset($data['provider_plans'])) {
        $plans = superadmin_sanitize_provider_plans($data['provider_plans']);
    } else {
        $current = superadmin_get_settings($pdo);
        if (isset($current['provider_plans']) && is_array($current['provider_plans'])) {
            $plans = superadmin_sanitize_provider_plans($current['provider_plans']);
        }
    }
    $plansJson = json_encode($plans, JSON_UNESCAPED_UNICODE);
    if ($plansJson === false) {
        $plansJson = json_encode(superadmin_default_provider_plans(), JSON_UNESCAPED_UNICODE);
    }

    $stmt = $pdo->prepare('UPDATE tbl_superadmin_settings SET system_name = ?, brand_logo_path = ?, brand_tagline = ?, provider_plans_json = ? WHERE id = 1');
    $stmt->execute([$name, $logo, $tag, (string) $plansJson]);
    try {
        superadmin_sync_subscription_plan_prices($pdo, $plans);
    } catch (Throwable $e) {
        // Do not block settings save if plan table sync fails in legacy schemas.
        error_log('superadmin plan sync save warning: ' . $e->getMessage());
    }
}
