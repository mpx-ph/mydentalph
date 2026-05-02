<?php
// api/upload_profile_photo.php — mobile app: save profile image to tbl_patients

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE);
ini_set('display_errors', 0);

require_once __DIR__ . '/../db.php';

$maxBytes = 5 * 1024 * 1024;
$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

/**
 * @return array{ok: bool, rel: string, err?: string}
 */
function api_profile_save_image_bytes(string $bytes, string $uploadDir, string $prefix, int $maxBytes, array $allowedMime): array
{
    if (strlen($bytes) > $maxBytes) {
        return ['ok' => false, 'rel' => '', 'err' => 'Image too large. Max 5MB.'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($bytes);
    if (!isset($allowedMime[$mime])) {
        return ['ok' => false, 'rel' => '', 'err' => 'Invalid image type. Use JPEG, PNG, GIF, or WebP.'];
    }
    $ext = $allowedMime[$mime];
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $name = $prefix . uniqid('', true) . '.' . $ext;
    $abs = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (file_put_contents($abs, $bytes) === false) {
        return ['ok' => false, 'rel' => '', 'err' => 'Failed to save image.'];
    }
    $rel = 'uploads/patients/' . $name;
    return ['ok' => true, 'rel' => $rel];
}

/**
 * @return array{ok: bool, rel: string, err?: string}
 */
function api_profile_save_base64(string $b64, string $uploadDir, string $prefix, int $maxBytes, array $allowedMime): array
{
    if (preg_match('/^data:image\/(\w+);base64,/', $b64, $m)) {
        $b64 = substr($b64, strpos($b64, ',') + 1);
    }
    $raw = base64_decode($b64, true);
    if ($raw === false) {
        return ['ok' => false, 'rel' => '', 'err' => 'Invalid base64 image data.'];
    }
    return api_profile_save_image_bytes($raw, $uploadDir, $prefix, $maxBytes, $allowedMime);
}

function api_profile_unlink_if_local(?string $relPath): void
{
    if ($relPath === null || $relPath === '' || strncmp($relPath, 'http://', 7) === 0 || strncmp($relPath, 'https://', 8) === 0) {
        return;
    }
    $base = realpath(__DIR__ . '/..') ?: __DIR__ . '/..';
    $full = $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relPath, '/\\'));
    if (is_file($full)) {
        @unlink($full);
    }
}

$uploadDir = __DIR__ . '/../uploads/patients/';

$input = null;
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ctype, 'application/json') !== false) {
    $input = json_decode((string) file_get_contents('php://input'), true);
}

$user_id    = $input['user_id']   ?? $_POST['user_id']   ?? null;
$tenant_id  = $input['tenant_id'] ?? $_POST['tenant_id'] ?? null;
$patient_id = isset($_POST['patient_id']) ? trim((string) $_POST['patient_id'])
    : (isset($input['patient_id']) ? trim((string) $input['patient_id']) : '');

if (!$user_id || !$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id or tenant_id.']);
    exit;
}

$saved = null;
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    if ($file['size'] > $maxBytes) {
        echo json_encode(['success' => false, 'message' => 'Image too large. Max 5MB.']);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
        exit;
    }
    $bytes = file_get_contents($file['tmp_name']);
    if ($bytes === false) {
        echo json_encode(['success' => false, 'message' => 'Could not read upload.']);
        exit;
    }
    $saved = api_profile_save_image_bytes($bytes, $uploadDir, 'mprofile_', $maxBytes, $allowedMime);
} elseif (is_array($input) && !empty($input['photo'])) {
    $saved = api_profile_save_base64($input['photo'], $uploadDir, 'mprofile_', $maxBytes, $allowedMime);
} else {
    echo json_encode(['success' => false, 'message' => 'Send multipart field "file" or JSON { "photo": "data:image/...;base64,..." }']);
    exit;
}

if (!($saved['ok'] ?? false)) {
    echo json_encode(['success' => false, 'message' => $saved['err'] ?? 'Failed to process image.']);
    exit;
}

$relPath = $saved['rel'];

try {
    $stmt = $pdo->prepare(
        "SELECT user_id, role FROM tbl_users WHERE user_id = ? AND tenant_id = ? LIMIT 1"
    );
    $stmt->execute([$user_id, $tenant_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        api_profile_unlink_if_local($relPath);
        echo json_encode(['success' => false, 'message' => 'User not found for this tenant.']);
        exit;
    }
    if (strtolower((string) ($user['role'] ?? '')) !== 'client') {
        api_profile_unlink_if_local($relPath);
        echo json_encode(['success' => false, 'message' => 'Only patient accounts can update this profile photo.']);
        exit;
    }

    if ($patient_id !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, profile_image FROM tbl_patients
             WHERE tenant_id = ?
               AND patient_id = ?
               AND (owner_user_id = ? OR linked_user_id = ?)
             LIMIT 1"
        );
        $stmt->execute([$tenant_id, $patient_id, $user_id, $user_id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, profile_image FROM tbl_patients
             WHERE tenant_id = ?
               AND (owner_user_id = ? OR linked_user_id = ?)
             ORDER BY (linked_user_id = ?) DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute([$tenant_id, $user_id, $user_id, $user_id]);
    }
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldPat = $patient ? trim((string) ($patient['profile_image'] ?? '')) : '';

    $pdo->beginTransaction();
    if ($patient) {
        $stmt = $pdo->prepare('UPDATE tbl_patients SET profile_image = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$relPath, $patient['id']]);
    } else {
        throw new RuntimeException('No linked patient record found for this account.');
    }
    $pdo->commit();

    if ($oldPat !== '' && $oldPat !== $relPath) {
        api_profile_unlink_if_local($oldPat);
    }

    echo json_encode([
        'success'         => true,
        'message'         => 'Profile photo updated.',
        'profile_image'  => $relPath,
        'user_photo'     => $relPath,
        'patient_profile_image' => $relPath,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    api_profile_unlink_if_local($relPath);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}
