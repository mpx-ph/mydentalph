<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/provider_auth.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/provider_tenant_site_customization_lib.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!($pdo instanceof PDO)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database is not available.']);
    exit;
}

provider_require_approved_for_provider_portal();

if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized.']);
    exit;
}

$user_id = (string) $_SESSION['user_id'];
require_once __DIR__ . '/provider_tenant_canonical_context.inc.php';

$tenant_id = trim((string) $tenant_id);
if ($tenant_id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid tenant.']);
    exit;
}

$defaults = require __DIR__ . '/clinic/config/clinic_customization_schema.php';
$allowedFonts = [
    'Manrope', 'Inter', 'Plus Jakarta Sans', 'DM Sans', 'Outfit', 'Source Sans 3',
    'Playfair Display', 'Lora', 'Merriweather', 'Nunito Sans', 'Work Sans',
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    echo json_encode([
        'ok' => true,
        'can_edit' => $is_owner,
        'options' => provider_tenant_site_merged_options($pdo, $tenant_id),
        'allowed_fonts' => $allowedFonts,
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    exit;
}

if (!$is_owner) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Only the clinic owner can update the public site.']);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/clinic/config/config.php';
require_once __DIR__ . '/clinic/includes/functions.php';

$input = [];
if (!empty($_POST['data'])) {
    $decoded = json_decode((string) $_POST['data'], true);
    $input = is_array($decoded) ? $decoded : [];
} elseif (strpos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string) $raw, true);
    $input = is_array($decoded) ? $decoded : [];
}

$patch = [];
if (isset($input['patch']) && is_array($input['patch'])) {
    $patch = $input['patch'];
} elseif ($input !== []) {
    $patch = $input;
}

foreach ($_POST as $k => $v) {
    if ($k === 'data' || strpos((string) $k, 'file_') === 0) {
        continue;
    }
    if (array_key_exists($k, $defaults)) {
        $patch[$k] = $v;
    }
}

$imageKeys = [
    'main_hero_image', 'main_doctor_image', 'about_hero_image', 'about_philosophy_image',
    'about_team_doctor1_image', 'about_team_doctor2_image', 'about_team_doctor3_image',
    'logo', 'logo_nav', 'logo_register', 'site_favicon',
];
$uploadDir = 'uploads/clinic/';
foreach ($imageKeys as $key) {
    $fileKey = 'file_' . $key;
    if (!empty($_FILES[$fileKey]['tmp_name']) && is_uploaded_file((string) $_FILES[$fileKey]['tmp_name'])) {
        $result = uploadFile($_FILES[$fileKey], $uploadDir);
        if (!empty($result['success']) && !empty($result['filename'])) {
            $patch[$key] = $uploadDir . $result['filename'];
        }
    }
}

if (array_key_exists('about_team_members_json', $patch)) {
    $rawTeam = (string) $patch['about_team_members_json'];
    $team = json_decode($rawTeam, true);
    if (is_array($team)) {
        $team = array_slice($team, 0, 30);
        $trimField = static function (string $v, int $max): string {
            $v = trim($v);
            if (strlen($v) <= $max) {
                return $v;
            }
            return substr($v, 0, $max);
        };
        $clean = [];
        foreach ($team as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean[] = [
                'title' => $trimField((string) ($row['title'] ?? ''), 200),
                'name' => $trimField((string) ($row['name'] ?? ''), 200),
                'bio' => $trimField((string) ($row['bio'] ?? ''), 8000),
                'tags' => $trimField((string) ($row['tags'] ?? ''), 500),
                'image' => $trimField((string) ($row['image'] ?? ''), 800),
            ];
        }
        foreach ($_FILES as $fieldName => $fileSlot) {
            if (!is_array($fileSlot) || !preg_match('/^file_about_team_m_(\d+)_img$/', (string) $fieldName, $mm)) {
                continue;
            }
            $idx = (int) $mm[1];
            if ($idx < 0 || $idx >= count($clean)) {
                continue;
            }
            $tmp = (string) ($fileSlot['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }
            $result = uploadFile($fileSlot, $uploadDir);
            if (!empty($result['success']) && !empty($result['filename'])) {
                $clean[$idx]['image'] = $uploadDir . $result['filename'];
            }
        }
        $patch['about_team_members_json'] = json_encode($clean, JSON_UNESCAPED_UNICODE);
    } else {
        unset($patch['about_team_members_json']);
    }
}

$sanitizeHex = static function (string $v): string {
    $v = strtolower(trim($v));
    $v = preg_replace('/^#/', '', $v);
    if (strlen($v) === 6 && ctype_xdigit($v)) {
        return $v;
    }
    return '';
};

$normalizeFont = static function (string $v) use ($allowedFonts): string {
    $v = trim($v);
    foreach ($allowedFonts as $f) {
        if (strcasecmp($v, $f) === 0) {
            return $f;
        }
    }
    if (preg_match('/^[a-zA-Z0-9 ][a-zA-Z0-9 ]{1,38}$/', $v) === 1) {
        return $v;
    }
    return '';
};

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS clinic_customization_tenant (
            tenant_id VARCHAR(50) NOT NULL,
            option_key VARCHAR(120) NOT NULL,
            option_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (tenant_id, option_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->prepare('
        INSERT INTO clinic_customization_tenant (tenant_id, option_key, option_value)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)
    ');

    if (isset($patch['clinic_name'])) {
        $cn = trim((string) $patch['clinic_name']);
        if ($cn !== '') {
            $ownUp = $pdo->prepare('UPDATE tbl_tenants SET clinic_name = ? WHERE tenant_id = ? AND owner_user_id = ?');
            $ownUp->execute([$cn, $tenant_id, $user_id]);
        }
    }

    foreach ($patch as $key => $value) {
        $key = (string) $key;
        if ($key === 'clinic_name') {
            $cnRow = trim((string) $value);
            if ($cnRow !== '') {
                $stmt->execute([$tenant_id, $key, $cnRow]);
            }
            continue;
        }
        if (!array_key_exists($key, $defaults)) {
            continue;
        }
        $strVal = trim((string) $value);
        if (strpos($key, 'color_') === 0) {
            $h = $sanitizeHex($strVal);
            if ($h === '') {
                continue;
            }
            $strVal = $h;
        }
        if (strpos($key, 'theme_font_') === 0) {
            $nf = $normalizeFont($strVal);
            if ($nf === '') {
                continue;
            }
            $strVal = $nf;
        }
        if ($key === 'theme_base_font_px') {
            $n = (int) $strVal;
            $strVal = (string) max(14, min(22, $n));
        }
        if ($key === 'theme_line_height') {
            $f = (float) $strVal;
            if ($f < 1.2 || $f > 2) {
                $f = 1.6;
            }
            $strVal = (string) $f;
        }
        if ($key === 'theme_heading_weight') {
            $n = (int) $strVal;
            $strVal = (string) max(500, min(900, $n));
        }
        if ($key === 'theme_radius_lg_px') {
            $n = (int) $strVal;
            $strVal = (string) max(6, min(28, $n));
        }
        if ($key === 'footer_hours_row3_style') {
            $lv = strtolower($strVal);
            $strVal = $lv === 'default' ? 'default' : 'danger';
        }
        if ($key === 'footer_social_url') {
            if ($strVal !== '' && preg_match('#^https?://#i', $strVal) !== 1) {
                continue;
            }
        }
        $stmt->execute([$tenant_id, $key, $strVal]);
    }

    echo json_encode([
        'ok' => true,
        'saved' => true,
        'options' => provider_tenant_site_merged_options($pdo, $tenant_id),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
} catch (Throwable $e) {
    error_log('ProviderTenantSiteCustomizationApi: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not save customization.']);
}
