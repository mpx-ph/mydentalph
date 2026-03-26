<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/db.php';

$error = '';
$success = '';
$field_errors = [];
$uploaded_files = [];

$tenant_id = $_SESSION['onboarding_tenant_id'] ?? '';
$user_id = $_SESSION['onboarding_user_id'] ?? '';
if ($tenant_id === '' || $user_id === '') {
    header('Location: ProviderOTP.php');
    exit;
}

// Step guard: user must have successfully verified their email first.
$email_verified = false;
try {
    $stmt = $pdo->prepare('SELECT 1 FROM tbl_email_verifications WHERE tenant_id = ? AND user_id = ? AND verified_at IS NOT NULL LIMIT 1');
    $stmt->execute([$tenant_id, $user_id]);
    $email_verified = (bool) $stmt->fetchColumn();
} catch (Throwable $e) {
    $email_verified = false;
}

// Allow onboarding to continue even if the optional email-verification row insert fails.
$session_email_verified_at = $_SESSION['onboarding_email_verified_at'] ?? 0;
$session_email_verified = is_numeric($session_email_verified_at) && (int) $session_email_verified_at > 0;
$email_verified = $email_verified || $session_email_verified;
if (!$email_verified) {
    header('Location: ProviderOTP.php');
    exit;
}

// Step guard: if docs already submitted using new request table, go forward.
$has_submitted_clinic_docs = false;
try {
    $stmt = $pdo->prepare('SELECT 1 FROM tbl_tenant_verification_requests WHERE tenant_id = ? AND submitted_at IS NOT NULL LIMIT 1');
    $stmt->execute([$tenant_id]);
    $has_submitted_clinic_docs = (bool) $stmt->fetchColumn();
} catch (Throwable $e) {
    $has_submitted_clinic_docs = false;
}

// Resilience: also accept a server-side flag set on successful upload.
$session_docs_submitted_at = $_SESSION['onboarding_clinic_docs_submitted_at'] ?? 0;
$session_docs_submitted = is_numeric($session_docs_submitted_at) && (int) $session_docs_submitted_at > 0;
$has_submitted_clinic_docs = $has_submitted_clinic_docs || $session_docs_submitted;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $has_submitted_clinic_docs) {
    header('Location: ProviderApplication.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'submit_clinic_verification';
    if ($action === 'submit_clinic_verification') {
        if ($has_submitted_clinic_docs) {
            header('Location: ProviderApplication.php');
            exit;
        }

        $allowed_mimes = [
            'image/jpeg',
            'image/png',
            'application/pdf',
        ];
        $max_bytes = 8 * 1024 * 1024; // 8MB
        $upload_dir = __DIR__ . '/uploads/clinic_verifications';
        $required_doc_types = ['business_permit', 'bir_certificate'];
        $doc_labels = [
            'business_permit' => 'Business Permit',
            'bir_certificate' => 'BIR Certificate / Form 2303',
            'sec_dti' => 'SEC/DTI Certificate',
            'other' => 'Other Supporting Document',
        ];

        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0775, true)) {
            $error = 'Could not prepare upload folder. Please contact support.';
        } else {
            $files_by_type = [
                'business_permit' => $_FILES['business_permit_docs'] ?? null,
                'bir_certificate' => $_FILES['bir_docs'] ?? null,
                'sec_dti' => $_FILES['sec_dti_docs'] ?? null,
                'other' => $_FILES['other_docs'] ?? null,
            ];

            foreach ($required_doc_types as $required_type) {
                $bucket = $files_by_type[$required_type] ?? null;
                $bucket_names = is_array($bucket['name'] ?? null) ? $bucket['name'] : [];
                $has_any_name = false;
                foreach ($bucket_names as $n) {
                    if (is_string($n) && trim($n) !== '') {
                        $has_any_name = true;
                        break;
                    }
                }
                if (!$has_any_name) {
                    $field_errors[] = ($doc_labels[$required_type] ?? $required_type) . ' is required.';
                }
            }

            if (empty($field_errors)) {
                $staged = [];
                $upload_error_found = '';

                foreach ($files_by_type as $doc_type => $docs) {
                    $names = is_array($docs['name'] ?? null) ? $docs['name'] : [];
                    $tmp_names = is_array($docs['tmp_name'] ?? null) ? $docs['tmp_name'] : [];
                    $sizes = is_array($docs['size'] ?? null) ? $docs['size'] : [];
                    $errors = is_array($docs['error'] ?? null) ? $docs['error'] : [];
                    $file_count = count($names);

                    for ($i = 0; $i < $file_count; $i++) {
                        $raw_name = is_string($names[$i] ?? null) ? trim((string) $names[$i]) : '';
                        if ($raw_name === '' && ($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                            $upload_error_found = 'Some files were not uploaded successfully. Please try again.';
                            break 2;
                        }
                        $tmp = $tmp_names[$i] ?? '';
                        if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
                            $upload_error_found = 'Invalid file upload detected.';
                            break 2;
                        }

                        $original_name = $raw_name !== '' ? $raw_name : 'clinic_doc';
                        $size = (int) ($sizes[$i] ?? 0);
                        if ($size <= 0 || $size > $max_bytes) {
                            $upload_error_found = 'Each file must be between 1 byte and 8MB.';
                            break 2;
                        }

                        $mime = (string) mime_content_type($tmp);
                        if (!in_array($mime, $allowed_mimes, true)) {
                            $upload_error_found = 'Unsupported file type. Upload PDF, JPG, or PNG only.';
                            break 2;
                        }

                        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                        if ($ext === '') {
                            $ext = ($mime === 'application/pdf') ? 'pdf' : (($mime === 'image/png') ? 'png' : 'jpg');
                        }

                        $safe_name = $tenant_id . '_' . $doc_type . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                        $target_path = $upload_dir . '/' . $safe_name;
                        $stored_relative = 'uploads/clinic_verifications/' . $safe_name;

                        if (!move_uploaded_file($tmp, $target_path)) {
                            $upload_error_found = 'Upload failed. Please try again.';
                            break 2;
                        }

                        $staged[] = [
                            'doc_type' => $doc_type,
                            'original_name' => $original_name,
                            'mime' => $mime,
                            'size' => $size,
                            'path' => $stored_relative,
                        ];
                    }
                }

                if ($upload_error_found !== '') {
                    $error = $upload_error_found;
                } elseif (empty($staged)) {
                    $error = 'Please upload at least one verification document to continue.';
                } else {
                    try {
                        $pdo->beginTransaction();

                        $tenant_stmt = $pdo->prepare("
                            SELECT t.clinic_name, u.full_name AS owner_name, u.email AS owner_email
                            FROM tbl_tenants t
                            LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
                            WHERE t.tenant_id = ?
                            LIMIT 1
                        ");
                        $tenant_stmt->execute([$tenant_id]);
                        $tenant = $tenant_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

                        $request_stmt = $pdo->prepare("
                            INSERT INTO tbl_tenant_verification_requests
                            (tenant_id, owner_user_id, clinic_name, owner_name, owner_email, status, submitted_at)
                            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                            ON DUPLICATE KEY UPDATE
                                owner_user_id = VALUES(owner_user_id),
                                clinic_name = VALUES(clinic_name),
                                owner_name = VALUES(owner_name),
                                owner_email = VALUES(owner_email),
                                status = 'pending',
                                submitted_at = NOW(),
                                reviewed_at = NULL,
                                reviewed_by = NULL,
                                reviewer_notes = NULL,
                                setup_token_hash = NULL,
                                setup_token_expires_at = NULL,
                                setup_token_used_at = NULL
                        ");
                        $request_stmt->execute([
                            $tenant_id,
                            $user_id,
                            (string) ($tenant['clinic_name'] ?? 'Clinic'),
                            (string) ($tenant['owner_name'] ?? ''),
                            (string) ($tenant['owner_email'] ?? ''),
                        ]);

                        $request_id_stmt = $pdo->prepare('SELECT request_id FROM tbl_tenant_verification_requests WHERE tenant_id = ? LIMIT 1');
                        $request_id_stmt->execute([$tenant_id]);
                        $request_id = (int) $request_id_stmt->fetchColumn();

                        if ($request_id <= 0) {
                            throw new RuntimeException('Could not resolve verification request.');
                        }

                        $pdo->prepare('DELETE FROM tbl_tenant_verification_files WHERE request_id = ?')->execute([$request_id]);

                        $file_stmt = $pdo->prepare("
                            INSERT INTO tbl_tenant_verification_files
                            (request_id, tenant_id, document_type, original_file_name, stored_file_path, mime_type, file_size_bytes)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        foreach ($staged as $f) {
                            $file_stmt->execute([
                                $request_id,
                                $tenant_id,
                                $f['doc_type'],
                                $f['original_name'],
                                $f['path'],
                                $f['mime'],
                                $f['size'],
                            ]);
                        }

                        $pdo->commit();
                        $_SESSION['onboarding_clinic_docs_submitted_at'] = time();
                        header('Location: ProviderApplication.php');
                        exit;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $error = 'Could not save verification details. Please try again.';
                    }
                }
            } else {
                $error = 'Please upload all required documents before submitting.';
            }
        }
    }
}
?>
<!DOCTYPE html>

<html class="light scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinic Verification | Aetheris OS</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "on-surface": "#131c25",
                        "surface": "#ffffff",
                        "surface-variant": "#f7f9ff",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "primary-fixed": "#d4e3ff",
                        "on-primary-fixed-variant": "#004883",
                        "surface-container-low": "#edf4ff",
                        "inverse-surface": "#131c25",
                        "error-container": "#ffdad6",
                        "on-error-container": "#93000a",
                        "surface-bright": "#f7f9ff",
                        "surface-container-lowest": "#ffffff",
                        "surface-container-high": "#e0e9f6",
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "3xl": "2.5rem", "full": "9999px" },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .mesh-gradient {
            background-color: #ffffff;
            background-image: 
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.05) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.03) 0px, transparent 50%);
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
    </style>
</head>
<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
<?php include 'ProviderNavbar.php'; ?>
<main class="min-h-screen flex items-center justify-center px-6 py-16">
<!-- Content Header (High-contrast, stylish font treatment) -->
<div class="max-w-xl text-center mb-10">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-6">
            Identity Assurance
        </div>
<h1 class="font-headline text-4xl md:text-5xl font-extrabold tracking-[-0.04em] text-on-surface mb-6 leading-[1]">
            Verify Your <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Clinic</span>
</h1>
<p class="text-on-surface-variant text-base md:text-lg leading-relaxed max-w-2xl mx-auto font-medium">
            Upload the required documents to confirm your clinic’s legitimacy. Your account will be activated after a precision architectural review.
        </p>
</div>
<!-- Verification Container -->
<div class="w-full max-w-xl bg-white rounded-3xl p-6 md:p-8 shadow-[0_40px_100px_-20px_rgba(43,139,235,0.08)] border border-on-surface/5">
<?php if (!empty($error)): ?>
<div class="mb-8 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm text-center font-semibold">
    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
<?php if (!empty($field_errors)): ?>
<div class="mb-8 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl text-sm">
    <p class="font-bold mb-2">Missing required files:</p>
    <ul class="list-disc list-inside">
        <?php foreach ($field_errors as $fe): ?>
            <li><?php echo htmlspecialchars($fe, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<!-- Upload Area -->
<form method="POST" enctype="multipart/form-data" class="w-full flex flex-col items-center">
    <input type="hidden" name="action" value="submit_clinic_verification"/>
<div class="relative group cursor-pointer mb-8 w-full">
<div class="border-2 border-dashed border-outline-variant group-hover:border-primary bg-surface-variant/50 group-hover:bg-primary/[0.02] transition-all duration-500 rounded-2xl p-8 text-center flex flex-col items-center justify-center gap-4">
<div class="w-16 h-16 rounded-3xl bg-primary-fixed flex items-center justify-center mb-1 transition-transform duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-primary text-4xl font-light" data-icon="cloud_upload">cloud_upload</span>
</div>
<div class="space-y-2">
<h3 class="font-headline font-extrabold text-xl text-on-surface tracking-tight">Upload Credentials</h3>
<p class="text-on-surface-variant font-medium text-sm">Drag and drop your required documents here</p>
</div>
<div class="flex gap-4">
<span class="px-4 py-1.5 bg-white border border-on-surface/5 rounded-full text-[10px] font-black text-on-surface-variant uppercase tracking-widest shadow-sm">PDF / Image</span>
<span class="px-4 py-1.5 bg-white border border-on-surface/5 rounded-full text-[10px] font-black text-on-surface-variant uppercase tracking-widest shadow-sm">8MB Limit</span>
</div>
</div>
<input class="absolute inset-0 opacity-0 cursor-pointer" type="file" name="business_permit_docs[]" multiple accept=".pdf,.jpg,.jpeg,.png"/>
</div>
<p class="text-xs text-on-surface-variant font-bold mb-6">Required: Business Permit (PDF/JPG/PNG, multiple files allowed)</p>

<div class="relative group cursor-pointer mb-8 w-full">
<div class="border-2 border-dashed border-outline-variant group-hover:border-primary bg-surface-variant/50 group-hover:bg-primary/[0.02] transition-all duration-500 rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3">
<h3 class="font-headline font-extrabold text-lg text-on-surface tracking-tight">Upload BIR Certificate / Form 2303</h3>
<p class="text-on-surface-variant font-medium text-xs">Required</p>
</div>
<input class="absolute inset-0 opacity-0 cursor-pointer" type="file" name="bir_docs[]" multiple accept=".pdf,.jpg,.jpeg,.png"/>
</div>

<div class="relative group cursor-pointer mb-8 w-full">
<div class="border-2 border-dashed border-outline-variant group-hover:border-primary bg-surface-variant/50 group-hover:bg-primary/[0.02] transition-all duration-500 rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3">
<h3 class="font-headline font-extrabold text-lg text-on-surface tracking-tight">Upload SEC/DTI Certificate (Optional)</h3>
</div>
<input class="absolute inset-0 opacity-0 cursor-pointer" type="file" name="sec_dti_docs[]" multiple accept=".pdf,.jpg,.jpeg,.png"/>
</div>

<div class="relative group cursor-pointer mb-8 w-full">
<div class="border-2 border-dashed border-outline-variant group-hover:border-primary bg-surface-variant/50 group-hover:bg-primary/[0.02] transition-all duration-500 rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3">
<h3 class="font-headline font-extrabold text-lg text-on-surface tracking-tight">Upload Other Supporting Documents (Optional)</h3>
</div>
<input class="absolute inset-0 opacity-0 cursor-pointer" type="file" name="other_docs[]" multiple accept=".pdf,.jpg,.jpeg,.png"/>
</div>
<!-- Note & CTA -->
<div class="space-y-6 w-full">
<div class="flex items-center justify-center gap-3 text-on-surface-variant">
<span class="material-symbols-outlined text-lg" data-icon="info">info</span>
<p class="text-sm font-semibold italic">Verification typically takes 24–48 hours.</p>
</div>
<button type="submit" class="w-full bg-primary hover:shadow-2xl hover:shadow-primary/30 text-white py-4 rounded-2xl font-headline font-black text-sm uppercase tracking-[0.2em] transition-all active:scale-[0.98]">
                Submit for Verification
            </button>
</div>
</div>
</form>
</main>
</body></html>