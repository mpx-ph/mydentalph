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

// Step guard: if this tenant was already rejected, do not allow further onboarding progress.
try {
    $stmt = $pdo->prepare("
        SELECT status
        FROM tbl_tenant_verification_requests
        WHERE tenant_id = ? AND owner_user_id = ?
        ORDER BY request_id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenant_id, $user_id]);
    $reqStatus = $stmt->fetchColumn();
    if ($reqStatus === 'rejected') {
        header('Location: ProviderApprovalStatus.php');
        exit;
    }
} catch (Throwable $e) {
    // If verification request can't be read, continue normal onboarding checks.
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
                $bucket_names = [];
                if (is_array($bucket) && isset($bucket['name'])) {
                    $bucket_names = is_array($bucket['name']) ? $bucket['name'] : [$bucket['name']];
                }
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
                    if (!is_array($docs)) {
                        continue; // no files submitted for this doc type
                    }

                    $names = isset($docs['name']) ? (is_array($docs['name']) ? $docs['name'] : [$docs['name']]) : [];
                    $tmp_names = isset($docs['tmp_name']) ? (is_array($docs['tmp_name']) ? $docs['tmp_name'] : [$docs['tmp_name']]) : [];
                    $sizes = isset($docs['size']) ? (is_array($docs['size']) ? $docs['size'] : [$docs['size']]) : [];
                    $errors = isset($docs['error']) ? (is_array($docs['error']) ? $docs['error'] : [$docs['error']]) : [];
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
                        $original_name = basename($original_name); // prevent accidental paths from clients
                        $size = (int) ($sizes[$i] ?? 0);
                        if ($size <= 0 || $size > $max_bytes) {
                            $upload_error_found = 'Each file must be between 1 byte and 8MB.';
                            break 2;
                        }

                        // MIME detection varies by server and browser; validate using MIME "best effort"
                        // plus extension fallback so legitimate uploads don't get rejected.
                        $mime = (string) (mime_content_type($tmp) ?: '');

                        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                        if ($ext === '') {
                            if (preg_match('/pdf/i', $mime)) {
                                $ext = 'pdf';
                            } elseif (preg_match('/png/i', $mime)) {
                                $ext = 'png';
                            } else {
                                $ext = 'jpg';
                            }
                        }

                        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
                        if (!in_array($ext, $allowed_exts, true)) {
                            $upload_error_found = 'Unsupported file type. Upload PDF, JPG, or PNG only.';
                            break 2;
                        }

                        $mime_ok = in_array($mime, $allowed_mimes, true);
                        if (!$mime_ok && $mime !== '') {
                            $mime_ok = (
                                ($ext === 'pdf' && preg_match('/pdf/i', $mime)) ||
                                (in_array($ext, ['jpg', 'jpeg'], true) && preg_match('/jpe?g/i', $mime)) ||
                                ($ext === 'png' && preg_match('/png/i', $mime))
                            );
                        }

                        if (!$mime_ok) {
                            // Some environments return overly generic MIME like application/octet-stream.
                            // If we have a valid extension and the MIME is generic/empty, allow it.
                            if ($mime === '' || stripos($mime, 'octet-stream') !== false) {
                                $mime_ok = in_array($ext, $allowed_exts, true);
                            }
                        }

                        if (!$mime_ok) {
                            $upload_error_found = 'Unsupported file type. Upload PDF, JPG, or PNG only.';
                            break 2;
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
        .upload-thumb {
            width: 44px;
            height: 44px;
            border-radius: 0.75rem;
            object-fit: cover;
            border: 1px solid rgba(19, 28, 37, 0.08);
            background: #fff;
        }
    </style>
</head>
<body class="bg-background-light font-body text-on-surface dark:bg-background-dark dark:text-surface antialiased">
<?php include 'ProviderNavbar.php'; ?>
<main class="min-h-screen flex items-center justify-center px-6 py-16">
<!-- Verification Container -->
<div class="w-full max-w-xl bg-white rounded-3xl p-6 md:p-8 shadow-[0_40px_100px_-20px_rgba(43,139,235,0.08)] border border-on-surface/5">
    <!-- Page Header (top of form section) -->
    <div class="text-center mb-8">
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
<?php if (!empty($error)): ?>
<div class="mb-8 p-4 bg-red-50 border border-red-200 text-red-700 rounded-2xl text-sm text-center font-semibold">
    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
<?php if (!empty($field_errors)): ?>
<div class="mb-8 p-4 bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl text-sm">
    <ul class="list-disc list-inside">
        <?php foreach ($field_errors as $fe): ?>
            <li><?php echo htmlspecialchars($fe, ENT_QUOTES, 'UTF-8'); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<!-- Upload Area -->
<form method="POST" enctype="multipart/form-data" class="w-full flex flex-col items-center" id="clinic-verification-form">
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
<input class="absolute inset-0 opacity-0 cursor-pointer clinic-upload-input" type="file" name="business_permit_docs[]" multiple accept=".pdf,.jpg,.jpeg,.png" data-doc-label="Business Permit" data-preview-target="preview-business-permit" data-error-target="error-business-permit"/>
</div>
<p class="text-xs text-on-surface-variant font-bold mb-6">Required: Business Permit (PDF/JPG/PNG, multiple files allowed)</p>
<div id="error-business-permit" class="hidden w-full mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold"></div>
<div id="preview-business-permit" class="hidden w-full mb-8 rounded-2xl border border-outline-variant/60 bg-white p-4"></div>

<div class="relative group cursor-pointer mb-8 w-full">
<div class="border-2 border-dashed border-outline-variant group-hover:border-primary bg-surface-variant/50 group-hover:bg-primary/[0.02] transition-all duration-500 rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3">
<h3 class="font-headline font-extrabold text-lg text-on-surface tracking-tight">Upload BIR Certificate / Form 2303</h3>
<p class="text-on-surface-variant font-medium text-xs">Required</p>
</div>
<input class="absolute inset-0 opacity-0 cursor-pointer clinic-upload-input" type="file" name="bir_docs[]" multiple accept=".pdf,.jpg,.jpeg,.png" data-doc-label="BIR Certificate / Form 2303" data-preview-target="preview-bir" data-error-target="error-bir"/>
</div>
<div id="error-bir" class="hidden w-full mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold"></div>
<div id="preview-bir" class="hidden w-full mb-8 rounded-2xl border border-outline-variant/60 bg-white p-4"></div>

<div class="relative group cursor-pointer mb-8 w-full">
<div class="border-2 border-dashed border-outline-variant group-hover:border-primary bg-surface-variant/50 group-hover:bg-primary/[0.02] transition-all duration-500 rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3">
<h3 class="font-headline font-extrabold text-lg text-on-surface tracking-tight">Upload SEC/DTI Certificate (Optional)</h3>
</div>
<input class="absolute inset-0 opacity-0 cursor-pointer clinic-upload-input" type="file" name="sec_dti_docs[]" multiple accept=".pdf,.jpg,.jpeg,.png" data-doc-label="SEC/DTI Certificate" data-preview-target="preview-sec-dti" data-error-target="error-sec-dti"/>
</div>
<div id="error-sec-dti" class="hidden w-full mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold"></div>
<div id="preview-sec-dti" class="hidden w-full mb-8 rounded-2xl border border-outline-variant/60 bg-white p-4"></div>

<div class="relative group cursor-pointer mb-8 w-full">
<div class="border-2 border-dashed border-outline-variant group-hover:border-primary bg-surface-variant/50 group-hover:bg-primary/[0.02] transition-all duration-500 rounded-2xl p-6 text-center flex flex-col items-center justify-center gap-3">
<h3 class="font-headline font-extrabold text-lg text-on-surface tracking-tight">Upload Other Supporting Documents (Optional)</h3>
</div>
<input class="absolute inset-0 opacity-0 cursor-pointer clinic-upload-input" type="file" name="other_docs[]" multiple accept=".pdf,.jpg,.jpeg,.png" data-doc-label="Other Supporting Document" data-preview-target="preview-other" data-error-target="error-other"/>
</div>
<div id="error-other" class="hidden w-full mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-semibold"></div>
<div id="preview-other" class="hidden w-full mb-8 rounded-2xl border border-outline-variant/60 bg-white p-4"></div>
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
<script>
(() => {
    const MAX_BYTES = 8 * 1024 * 1024;
    const ALLOWED_EXTS = ['pdf', 'jpg', 'jpeg', 'png'];
    const ALLOWED_MIME_PREFIXES = ['image/', 'application/pdf'];
    const ICON_BY_EXT = {
        pdf: 'picture_as_pdf',
        jpg: 'image',
        jpeg: 'image',
        png: 'image'
    };

    function formatSize(bytes) {
        if (!Number.isFinite(bytes) || bytes <= 0) return '0 KB';
        const units = ['B', 'KB', 'MB', 'GB'];
        let value = bytes;
        let idx = 0;
        while (value >= 1024 && idx < units.length - 1) {
            value /= 1024;
            idx++;
        }
        return `${value.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
    }

    function extFromFile(file) {
        const name = (file.name || '').toLowerCase();
        const ext = name.includes('.') ? name.split('.').pop() : '';
        return ext || '';
    }

    function isAllowed(file) {
        const ext = extFromFile(file);
        const byExt = ALLOWED_EXTS.includes(ext);
        const mime = String(file.type || '').toLowerCase();
        const byMime = ALLOWED_MIME_PREFIXES.some((prefix) => mime.startsWith(prefix));
        return byExt || byMime;
    }

    function isImage(file) {
        return String(file.type || '').startsWith('image/');
    }

    function renderError(target, message) {
        if (!target) return;
        if (!message) {
            target.classList.add('hidden');
            target.textContent = '';
            return;
        }
        target.textContent = message;
        target.classList.remove('hidden');
    }

    function createFileRow(file, removeCb) {
        const row = document.createElement('div');
        row.className = 'flex items-center justify-between gap-3 rounded-xl border border-outline-variant/50 bg-surface-variant/40 px-3 py-2';

        const left = document.createElement('div');
        left.className = 'flex items-center gap-3 min-w-0';

        if (isImage(file)) {
            const thumb = document.createElement('img');
            thumb.className = 'upload-thumb';
            thumb.alt = file.name || 'Uploaded image';
            try {
                thumb.src = URL.createObjectURL(file);
                thumb.onload = () => URL.revokeObjectURL(thumb.src);
            } catch (e) {
                thumb.remove();
            }
            left.appendChild(thumb);
        } else {
            const iconWrap = document.createElement('div');
            iconWrap.className = 'w-11 h-11 rounded-xl bg-white border border-on-surface/10 flex items-center justify-center shrink-0';
            const icon = document.createElement('span');
            icon.className = 'material-symbols-outlined text-on-surface-variant text-xl';
            icon.textContent = ICON_BY_EXT[extFromFile(file)] || 'description';
            iconWrap.appendChild(icon);
            left.appendChild(iconWrap);
        }

        const meta = document.createElement('div');
        meta.className = 'min-w-0';
        const name = document.createElement('p');
        name.className = 'text-sm font-semibold text-on-surface truncate';
        name.textContent = file.name || 'Unnamed file';
        const details = document.createElement('p');
        details.className = 'text-xs text-on-surface-variant';
        details.textContent = `${(file.type || extFromFile(file) || 'Unknown type').toUpperCase()} • ${formatSize(file.size || 0)}`;
        meta.appendChild(name);
        meta.appendChild(details);
        left.appendChild(meta);

        const right = document.createElement('div');
        right.className = 'flex items-center gap-2 shrink-0';

        const uploaded = document.createElement('span');
        uploaded.className = 'inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-full';
        uploaded.innerHTML = '<span class="material-symbols-outlined text-sm">check_circle</span>Uploaded';

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'text-xs font-semibold text-red-700 hover:text-red-800 hover:underline';
        remove.textContent = 'Remove';
        remove.addEventListener('click', removeCb);

        right.appendChild(uploaded);
        right.appendChild(remove);

        row.appendChild(left);
        row.appendChild(right);
        return row;
    }

    function setupInput(input) {
        const previewId = input.dataset.previewTarget || '';
        const errorId = input.dataset.errorTarget || '';
        const docLabel = input.dataset.docLabel || 'Document';
        const preview = document.getElementById(previewId);
        const errorEl = document.getElementById(errorId);
        if (!preview) return;

        let selectedFiles = [];

        function syncInputFiles() {
            const dt = new DataTransfer();
            selectedFiles.forEach((f) => dt.items.add(f));
            input.files = dt.files;
        }

        function renderPreview() {
            preview.innerHTML = '';
            if (selectedFiles.length === 0) {
                preview.classList.add('hidden');
                return;
            }

            preview.classList.remove('hidden');

            const heading = document.createElement('div');
            heading.className = 'flex items-center justify-between mb-3';
            heading.innerHTML = `
                <p class="text-sm font-bold text-on-surface">${docLabel} files (${selectedFiles.length})</p>
                <span class="text-xs font-semibold text-emerald-700 inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">task_alt</span>
                    Ready to submit
                </span>
            `;
            preview.appendChild(heading);

            const list = document.createElement('div');
            list.className = 'space-y-2';
            selectedFiles.forEach((file, index) => {
                list.appendChild(createFileRow(file, () => {
                    selectedFiles.splice(index, 1);
                    syncInputFiles();
                    renderPreview();
                    renderError(errorEl, '');
                }));
            });
            preview.appendChild(list);
        }

        input.addEventListener('change', () => {
            const picked = Array.from(input.files || []);
            if (picked.length === 0) {
                selectedFiles = [];
                renderError(errorEl, '');
                renderPreview();
                return;
            }

            const invalidType = picked.find((file) => !isAllowed(file));
            if (invalidType) {
                selectedFiles = [];
                syncInputFiles();
                renderPreview();
                renderError(errorEl, 'Unsupported file detected. Please upload PDF, JPG, or PNG only.');
                return;
            }

            const invalidSize = picked.find((file) => Number(file.size || 0) <= 0 || Number(file.size || 0) > MAX_BYTES);
            if (invalidSize) {
                selectedFiles = [];
                syncInputFiles();
                renderPreview();
                renderError(errorEl, 'Each file must be between 1 byte and 8MB.');
                return;
            }

            selectedFiles = picked;
            syncInputFiles();
            renderError(errorEl, '');
            renderPreview();
        });
    }

    const inputs = document.querySelectorAll('.clinic-upload-input');
    inputs.forEach(setupInput);

    const form = document.getElementById('clinic-verification-form');
    if (form) {
        form.addEventListener('submit', () => {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('opacity-70', 'cursor-not-allowed');
                submitButton.innerHTML = 'Submitting...';
            }
        });
    }
})();
</script>
</body></html>