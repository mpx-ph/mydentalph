<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/db.php';

$error = '';
$success = '';
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug_mode) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

if (empty($_SESSION['onboarding_user_id']) || empty($_SESSION['onboarding_tenant_id'])) {
    header('Location: ProviderOTP.php');
    exit;
}

// VerifyBusiness is only available after super admin approval.
require_once __DIR__ . '/provider_auth.php';
provider_require_approved_for_provider_portal();

$tenant_id = $_SESSION['onboarding_tenant_id'];
$allowed_mimes = [
    'image/jpeg',
    'image/png',
    'application/pdf',
];

function normalize_spaces(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value));
}

function has_extracted_business_details(string $ocn_tin_branch, string $taxpayer_name, string $registered_address): bool
{
    return normalize_spaces($ocn_tin_branch) !== ''
        || normalize_spaces($taxpayer_name) !== ''
        || normalize_spaces($registered_address) !== '';
}

function extract_business_fields(string $text): array
{
    $result = [
        'ocn_tin_branch' => '',
        'taxpayer_name' => '',
        'registered_address' => '',
    ];

    $clean = str_replace("\r", "\n", $text);

    if (preg_match('/TIN\s*&?\s*BRANCH\s*CODE\s*[:\-]?\s*([A-Z0-9\-\s\/]{6,})/i', $clean, $m)) {
        $result['ocn_tin_branch'] = normalize_spaces($m[1]);
    } elseif (preg_match('/TIN\s*[:\-]?\s*([A-Z0-9\-]{6,})[^\n]{0,40}BRANCH\s*CODE\s*[:\-]?\s*([A-Z0-9\-]{1,})/i', $clean, $m)) {
        $result['ocn_tin_branch'] = normalize_spaces($m[1] . ' / ' . $m[2]);
    }
    if (preg_match('/NAME\s+OF\s+TAXPAYER\s*[:\-]?\s*([^\n]+)/i', $clean, $m)) {
        $result['taxpayer_name'] = normalize_spaces($m[1]);
    }
    if (preg_match('/REGISTERED\s+ADDRESS\s*[:\-]?\s*([^\n]+)/i', $clean, $m)) {
        $result['registered_address'] = normalize_spaces($m[1]);
    }

    return $result;
}

function run_ocr_space(string $file_path, string $mime): array
{
    $api_key = getenv('OCR_SPACE_API_KEY') ?: 'helloworld';
    $payload = [
        'apikey' => $api_key,
        'language' => 'eng',
        'isOverlayRequired' => 'false',
        'detectOrientation' => 'true',
        'scale' => 'true',
        'OCREngine' => '2',
        'file' => new CURLFile($file_path, $mime, basename($file_path)),
    ];

    $ch = curl_init('https://api.ocr.space/parse/image');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curl_error) {
        return ['ok' => false, 'error' => 'OCR service is unavailable right now. Please try again.'];
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['ok' => false, 'error' => 'Unexpected OCR response. Please try another file.'];
    }

    $parsed_text = '';
    if (!empty($json['ParsedResults']) && is_array($json['ParsedResults'])) {
        foreach ($json['ParsedResults'] as $item) {
            $parsed_text .= ($item['ParsedText'] ?? '') . "\n";
        }
    }

    if (trim($parsed_text) === '') {
        $api_error = $json['ErrorMessage'] ?? '';
        if (is_array($api_error)) {
            $api_error = implode(', ', $api_error);
        }
        return ['ok' => false, 'error' => $api_error !== '' ? $api_error : 'No readable text found in the document.'];
    }

    return ['ok' => true, 'text' => trim($parsed_text)];
}

$existing = [];
try {
    $stmt = $pdo->prepare("
        SELECT verification_id, uploaded_file_path, ocn_tin_branch, taxpayer_name, registered_address, verification_status, submitted_at
        FROM tbl_tenant_business_verifications
        WHERE tenant_id = ?
        ORDER BY verification_id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenant_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    error_log('[VerifyBusiness] Existing verification lookup failed: ' . $e->getMessage());
    $existing = [];
    $error = 'Could not load current business verification details. Please try again.';
    if ($debug_mode) {
        $error .= ' Debug: ' . $e->getMessage();
    }
}

$ocn_tin_branch = $existing['ocn_tin_branch'] ?? '';
$taxpayer_name = $existing['taxpayer_name'] ?? '';
$registered_address = $existing['registered_address'] ?? '';
$verification_status = $existing['verification_status'] ?? 'pending';
$submitted_at = $existing['submitted_at'] ?? null;
$uploaded_file_path = $existing['uploaded_file_path'] ?? '';
$has_extracted_details = has_extracted_business_details($ocn_tin_branch, $taxpayer_name, $registered_address);
$is_effectively_submitted = $verification_status === 'submitted' && $has_extracted_details;

if ($verification_status === 'submitted' && !$has_extracted_details) {
    $error = 'Business permit is not valid yet. Please upload the correct permit file so extracted details are filled.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'submit_verification';

    if ($action === 'continue' && $is_effectively_submitted) {
        header('Location: ProviderPurchase.php');
        exit;
    }

    if ($action === 'submit_verification') {
        if (!isset($_FILES['business_permit']) || !is_array($_FILES['business_permit']) || ($_FILES['business_permit']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Please upload a business permit document to continue.';
        } else {
            $tmp_name = $_FILES['business_permit']['tmp_name'];
            $original_name = $_FILES['business_permit']['name'] ?? 'business_permit';
            $file_size = (int) ($_FILES['business_permit']['size'] ?? 0);
            $mime = (string) (mime_content_type($tmp_name) ?: '');
            if ($mime === '' || stripos($mime, 'octet-stream') !== false) {
                $ext_guess = strtolower((string) pathinfo($original_name, PATHINFO_EXTENSION));
                if ($ext_guess === 'pdf') {
                    $mime = 'application/pdf';
                } elseif (in_array($ext_guess, ['jpg', 'jpeg'], true)) {
                    $mime = 'image/jpeg';
                } elseif ($ext_guess === 'png') {
                    $mime = 'image/png';
                }
            }

            if (!in_array($mime, $allowed_mimes, true)) {
                $error = 'Unsupported file type. Please upload PDF, JPG, or PNG only.';
            } elseif ($file_size <= 0) {
                $error = 'Uploaded file is empty. Please select a valid document.';
            } elseif ($file_size > 10 * 1024 * 1024) {
                $error = 'File is too large. Maximum size is 10MB.';
            } else {
                $upload_dir = __DIR__ . '/uploads/business_permits';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true)) {
                    $error = 'Could not prepare upload folder. Please contact support.';
                } else {
                    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                    if ($ext === '') {
                        $ext = $mime === 'application/pdf' ? 'pdf' : 'jpg';
                    }
                    $safe_name = $tenant_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $target_path = $upload_dir . '/' . $safe_name;
                    $stored_relative = 'uploads/business_permits/' . $safe_name;

                    if (!move_uploaded_file($tmp_name, $target_path)) {
                        $error = 'Upload failed. Please try again.';
                    } else {
                        $ocr = run_ocr_space($target_path, $mime);
                        if (!$ocr['ok']) {
                            $error = $ocr['error'];
                        } else {
                            $fields = extract_business_fields($ocr['text']);
                            $ocn_tin_branch = $fields['ocn_tin_branch'];
                            $taxpayer_name = $fields['taxpayer_name'];
                            $registered_address = $fields['registered_address'];
                            $has_extracted_details = has_extracted_business_details($ocn_tin_branch, $taxpayer_name, $registered_address);

                            if (!$has_extracted_details) {
                                $error = 'We could not extract any required details from this file. Please upload a valid business permit document.';
                            } else {

                                try {
                                    $stmt = $pdo->prepare("
                                        INSERT INTO tbl_tenant_business_verifications
                                        (tenant_id, uploaded_file_path, uploaded_file_name, ocr_raw_text, ocn_tin_branch, taxpayer_name, registered_address, verification_status, submitted_at)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, 'submitted', NOW())
                                    ");
                                    $stmt->execute([
                                        $tenant_id,
                                        $stored_relative,
                                        $original_name,
                                        $ocr['text'],
                                        $ocn_tin_branch,
                                        $taxpayer_name,
                                        $registered_address,
                                    ]);
                                    $verification_status = 'submitted';
                                    $submitted_at = date('Y-m-d H:i:s');
                                    $uploaded_file_path = $stored_relative;
                                    $success = 'Business permit verified and submitted. You can now continue to purchase.';
                                } catch (Throwable $e) {
                                    $error = 'Could not save verification details. Please try again.';
                                }
                            }
                            $is_effectively_submitted = $verification_status === 'submitted' && $has_extracted_details;
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Verify Business Permit - MyDental</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <style>
        body { font-family: 'Manrope', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">
    <div class="max-w-4xl mx-auto p-6 md:p-10">
        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight">Verify Business Permit</h1>
            <p class="text-slate-600 mt-2">Upload your clinic business permit so we can validate onboarding details through OCR.</p>
            <div class="mt-4 text-xs text-slate-500 bg-slate-100 border border-slate-200 rounded-lg p-3">
                Required fields from OCR: TIN &amp; BRANCH CODE, NAME OF TAXPAYER, and REGISTERED ADDRESS.
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mb-5 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-5 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <form method="POST" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-xl p-6 space-y-5">
                <input type="hidden" name="action" value="submit_verification"/>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Business Permit Document</label>
                    <input
                        type="file"
                        name="business_permit"
                        accept=".pdf,.jpg,.jpeg,.png"
                        class="w-full text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-blue-600 file:px-4 file:py-2.5 file:text-white hover:file:bg-blue-700"
                        <?php echo $is_effectively_submitted ? '' : 'required'; ?>
                    />
                    <p class="mt-2 text-xs text-slate-500">Allowed: PDF, JPG, PNG. Max size: 10MB.</p>
                    <?php if ($uploaded_file_path !== ''): ?>
                        <p class="mt-2 text-xs text-slate-500">Last uploaded: <?php echo htmlspecialchars($uploaded_file_path); ?></p>
                    <?php endif; ?>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg">
                    Run OCR and Submit Verification
                </button>
            </form>

            <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
                <h2 class="text-lg font-bold">Extracted Details</h2>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">TIN &amp; Branch Code</label>
                    <input type="text" readonly disabled value="<?php echo htmlspecialchars($ocn_tin_branch); ?>" class="w-full rounded-lg border-slate-200 bg-slate-50"/>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Name of Taxpayer</label>
                    <input type="text" readonly disabled value="<?php echo htmlspecialchars($taxpayer_name); ?>" class="w-full rounded-lg border-slate-200 bg-slate-50"/>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1">Registered Address</label>
                    <textarea readonly disabled rows="3" class="w-full rounded-lg border-slate-200 bg-slate-50"><?php echo htmlspecialchars($registered_address); ?></textarea>
                </div>
                <div class="pt-2 border-t border-slate-100 text-sm">
                    <span class="font-semibold">Status:</span>
                    <?php if ($is_effectively_submitted): ?>
                        <span class="text-emerald-700 font-semibold">Submitted</span>
                        <?php if ($submitted_at): ?>
                            <span class="text-slate-500">(<?php echo htmlspecialchars($submitted_at); ?>)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-amber-700 font-semibold">Pending Submission (missing extracted details)</span>
                    <?php endif; ?>
                </div>
                <?php if ($is_effectively_submitted): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="continue"/>
                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg">
                            Continue to Purchase
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-8 bg-white border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
            This verification step is required. You will not be able to create the account subscription without submitting a business permit.
        </div>
    </div>
</body>
</html>
