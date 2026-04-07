<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../mail_config.php';

$error = '';
$success = '';

$selected_request_id = isset($_GET['request_id']) ? (int) $_GET['request_id'] : 0;
$status_filter = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : 'pending';
$allowed_filters = ['pending', 'approved', 'rejected'];
if (!in_array($status_filter, $allowed_filters, true)) {
    $status_filter = 'pending';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $request_id = (int) ($_POST['request_id'] ?? 0);
    $notes = trim((string) ($_POST['reviewer_notes'] ?? ''));
    $reviewer_id = (string) ($_SESSION['user_id'] ?? '');

    if ($request_id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $error = 'Invalid review action request.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT request_id, tenant_id, owner_user_id, owner_email, owner_name, clinic_name, status
                FROM tbl_tenant_verification_requests
                WHERE request_id = ?
                LIMIT 1
            ");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                $error = 'Verification request was not found.';
            } elseif (($request['status'] ?? '') !== 'pending') {
                $error = 'Only pending verification requests can be reviewed.';
            } else {
                if ($action === 'approve') {
                    $raw_token = bin2hex(random_bytes(32));
                    $token_hash = password_hash($raw_token, PASSWORD_DEFAULT);
                    $token_expires_at = date('Y-m-d H:i:s', time() + (60 * 60 * 24 * 7)); // 7 days

                    $update = $pdo->prepare("
                        UPDATE tbl_tenant_verification_requests
                        SET status = 'approved',
                            reviewed_at = NOW(),
                            reviewed_by = ?,
                            reviewer_notes = ?,
                            setup_token_hash = ?,
                            setup_token_expires_at = ?,
                            setup_token_used_at = NULL
                        WHERE request_id = ?
                    ");
                    $update->execute([$reviewer_id, $notes !== '' ? $notes : null, $token_hash, $token_expires_at, $request_id]);

                    // Activate the provider owner user now that the tenant is approved.
                    // This prevents pending/rejected tenants from having "active" provider accounts.
                    if (!empty($request['owner_user_id'])) {
                        $pdo->prepare("UPDATE tbl_users SET status = 'active' WHERE user_id = ? LIMIT 1")
                            ->execute([(string) $request['owner_user_id']]);
                    }

                    $to_email = trim((string) ($request['owner_email'] ?? ''));
                    if ($to_email !== '') {
                        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                        $scheme = $is_https ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? '';
                        $base = rtrim($scheme . '://' . $host, '/');
                        $setup_url = $base . '/ProviderClinicSetup.php?request_id=' . urlencode((string) $request_id) . '&setup_token=' . urlencode($raw_token);
                        $safe_owner = htmlspecialchars((string) ($request['owner_name'] ?? 'Clinic Owner'), ENT_QUOTES, 'UTF-8');
                        $safe_clinic = htmlspecialchars((string) ($request['clinic_name'] ?? 'Clinic'), ENT_QUOTES, 'UTF-8');
                        $safe_setup_url = htmlspecialchars($setup_url, ENT_QUOTES, 'UTF-8');
                        $body_text = "Hello {$request['owner_name']},\n\nYour clinic verification for {$request['clinic_name']} has been approved.\nUse this secure link to continue setup:\n{$setup_url}\n\nThis link expires in 7 days.";
                        $body_html = "<p>Hello {$safe_owner},</p><p>Your clinic verification for <strong>{$safe_clinic}</strong> has been approved.</p><p><a href=\"{$safe_setup_url}\">Continue clinic setup</a></p><p>This secure link expires in 7 days.</p>";
                        if (!send_smtp_gmail($to_email, 'Clinic Verification Approved - Continue Setup', $body_text, $body_html)) {
                            $success = 'Request approved. Email could not be sent; check SMTP settings.';
                        } else {
                            $success = 'Request approved and setup email was sent.';
                        }
                    } else {
                        $success = 'Request approved, but owner email is missing.';
                    }
                } else {
                    $update = $pdo->prepare("
                        UPDATE tbl_tenant_verification_requests
                        SET status = 'rejected',
                            reviewed_at = NOW(),
                            reviewed_by = ?,
                            reviewer_notes = ?,
                            setup_token_hash = NULL,
                            setup_token_expires_at = NULL,
                            setup_token_used_at = NULL
                        WHERE request_id = ?
                    ");
                    $update->execute([$reviewer_id, $notes !== '' ? $notes : null, $request_id]);

                    // Deactivate the provider owner user on rejection.
                    if (!empty($request['owner_user_id'])) {
                        $pdo->prepare("UPDATE tbl_users SET status = 'inactive' WHERE user_id = ? LIMIT 1")
                            ->execute([(string) $request['owner_user_id']]);
                    }

                    $success = 'Request has been rejected.';
                }
            }
        } catch (Throwable $e) {
            $error = 'Failed to process review action. Please try again.';
        }
    }
}

$list_stmt = $pdo->prepare("
    SELECT request_id, tenant_id, clinic_name, owner_name, owner_email, status, submitted_at, reviewed_at
    FROM tbl_tenant_verification_requests
    WHERE status = ?
    ORDER BY submitted_at DESC
");
$list_stmt->execute([$status_filter]);
$requests = $list_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

if ($selected_request_id <= 0 && !empty($requests)) {
    $selected_request_id = (int) $requests[0]['request_id'];
}

$selected = null;
$files = [];
if ($selected_request_id > 0) {
    $selected_stmt = $pdo->prepare("
        SELECT request_id, tenant_id, clinic_name, owner_name, owner_email, status, submitted_at, reviewed_at, reviewer_notes
        FROM tbl_tenant_verification_requests
        WHERE request_id = ?
        LIMIT 1
    ");
    $selected_stmt->execute([$selected_request_id]);
    $selected = $selected_stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($selected) {
        $files_stmt = $pdo->prepare("
            SELECT document_type, original_file_name, stored_file_path, mime_type, file_size_bytes, uploaded_at
            FROM tbl_tenant_verification_files
            WHERE request_id = ?
            ORDER BY file_id ASC
        ");
        $files_stmt->execute([$selected_request_id]);
        $files = $files_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

function sa_status_badge_class(string $status): string
{
    if ($status === 'approved') {
        return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    }
    if ($status === 'rejected') {
        return 'bg-rose-50 text-rose-700 border-rose-200';
    }
    return 'bg-amber-50 text-amber-700 border-amber-200';
}

function sa_document_label(string $type): string
{
    $map = [
        'business_permit' => 'Business Permit',
        'bir_certificate' => 'BIR Certificate',
        'sec_dti' => 'SEC/DTI',
        'other' => 'Other',
    ];
    return $map[$type] ?? ucfirst(str_replace('_', ' ', $type));
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinic Approvals | Clinical Precision</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "on-error": "#ffffff",
                        "on-tertiary-fixed-variant": "#6e3900",
                        "on-surface": "#131c25",
                        "primary-fixed-dim": "#a4c9ff",
                        "on-secondary-fixed": "#001c39",
                        "surface-container-high": "#e0e9f6",
                        "on-background": "#131c25",
                        "inverse-on-surface": "#e8f1ff",
                        "tertiary-container": "#b25f00",
                        "surface-bright": "#f7f9ff",
                        "secondary-fixed-dim": "#adc8f3",
                        "surface-variant": "#dae3f0",
                        "on-tertiary": "#ffffff",
                        "outline": "#717784",
                        "inverse-surface": "#28313b",
                        "on-primary-container": "#fdfcff",
                        "inverse-primary": "#a4c9ff",
                        "secondary-container": "#b8d3fe",
                        "error-container": "#ffdad6",
                        "primary-container": "#0076d2",
                        "on-secondary-container": "#405b80",
                        "surface": "#f7f9ff",
                        "on-secondary": "#ffffff",
                        "on-primary": "#ffffff",
                        "on-primary-fixed": "#001c39",
                        "on-primary-fixed-variant": "#004883",
                        "surface-container-lowest": "#ffffff",
                        "tertiary-fixed-dim": "#ffb77e",
                        "surface-dim": "#d2dbe8",
                        "on-tertiary-container": "#fffbff",
                        "on-error-container": "#93000a",
                        "background": "#f7f9ff",
                        "surface-tint": "#0060ac",
                        "surface-container": "#e6effc",
                        "tertiary": "#8e4a00",
                        "primary-fixed": "#d4e3ff",
                        "on-tertiary-fixed": "#2f1500",
                        "surface-container-low": "#edf4ff",
                        "tertiary-fixed": "#ffdcc3",
                        "primary": "#0066ff",
                        "surface-container-highest": "#dae3f0",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "error": "#ba1a1a",
                        "secondary": "#456085",
                        "secondary-fixed": "#d4e3ff",
                        "on-secondary-fixed-variant": "#2c486c"
                    },
                    fontFamily: {
                        "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .editorial-shadow {
            box-shadow: 0 12px 40px -10px rgba(19, 28, 37, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(0, 102, 255, 0.3);
        }
        .primary-glow {
            box-shadow: 0 8px 25px -5px rgba(0, 102, 255, 0.4);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image: 
                radial-gradient(at 0% 0%, hsla(210,100%,98%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(217,100%,94%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(210,100%,98%,1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .doc-preview-modal.hidden {
            display: none;
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface selection:bg-primary/10 min-h-screen">
<?php
$superadmin_nav = 'superadmin_approval';
$superadmin_header_center = '<h2 class="text-2xl font-headline font-extrabold text-[#131c25] tracking-tight">Clinic Approvals</h2>';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';
?>
<main class="ml-64 flex-grow flex flex-col min-h-screen">
<div class="pt-20 flex flex-grow overflow-hidden relative">
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<section class="w-3/5 p-10 overflow-y-auto space-y-8 no-scrollbar">
<?php if ($error !== ''): ?>
<div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-semibold"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<?php if ($success !== ''): ?>
<div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<div class="flex items-center justify-between mb-2">
<div class="flex gap-2 p-1.5 bg-white/40 backdrop-blur-md rounded-2xl border border-white/60">
<a href="?status=pending" class="px-5 py-2 rounded-xl text-xs font-bold transition-colors <?php echo $status_filter === 'pending' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'text-on-surface-variant hover:bg-white/50'; ?>">Pending Tenants</a>
<a href="?status=approved" class="px-5 py-2 rounded-xl text-xs font-bold <?php echo $status_filter === 'approved' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'text-on-surface-variant hover:bg-white/50'; ?>">Approved</a>
<a href="?status=rejected" class="px-5 py-2 rounded-xl text-xs font-bold <?php echo $status_filter === 'rejected' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'text-on-surface-variant hover:bg-white/50'; ?>">Rejected</a>
</div>
<span class="text-xs font-bold text-on-surface-variant/70 px-4 py-2 bg-white/60 border border-white rounded-xl"><?php echo count($requests); ?> result(s)</span>
</div>
<div class="space-y-6">
<?php if (empty($requests)): ?>
<div class="bg-white/60 backdrop-blur-md p-6 rounded-[2rem] editorial-shadow border border-white/60">
<p class="text-sm font-semibold text-on-surface-variant">No tenant requests in this status yet.</p>
</div>
<?php endif; ?>
<?php foreach ($requests as $r): ?>
<a href="?status=<?php echo urlencode($status_filter); ?>&request_id=<?php echo (int) $r['request_id']; ?>" class="block backdrop-blur-md p-6 rounded-[2rem] editorial-shadow border-l-[6px] <?php echo ((int) $r['request_id'] === $selected_request_id) ? 'bg-white/80 border-primary active-glow' : 'bg-white/45 border-transparent hover:bg-white/65'; ?> transition-all cursor-pointer group">
<div class="flex items-start justify-between gap-4">
<div class="flex gap-5">
<div class="w-14 h-14 rounded-2xl bg-blue-50 flex items-center justify-center text-primary group-hover:scale-105 transition-transform">
<span class="material-symbols-outlined text-3xl">dentistry</span>
</div>
<div>
<h3 class="font-headline font-extrabold text-on-surface text-lg"><?php echo htmlspecialchars((string) ($r['clinic_name'] ?? 'Clinic'), ENT_QUOTES, 'UTF-8'); ?></h3>
<p class="text-on-surface-variant text-xs font-medium mt-1">Owner: <?php echo htmlspecialchars((string) ($r['owner_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></p>
<div class="flex items-center gap-4 mt-4 text-[11px] text-on-surface-variant/70 font-bold uppercase tracking-widest">
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base">mail</span><?php echo htmlspecialchars((string) ($r['owner_email'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-base">calendar_today</span><?php echo htmlspecialchars((string) ($r['submitted_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</div>
</div>
<span class="px-3 py-1.5 rounded-xl text-[10px] font-extrabold uppercase tracking-widest border <?php echo sa_status_badge_class((string) ($r['status'] ?? 'pending')); ?>"><?php echo htmlspecialchars(strtoupper((string) ($r['status'] ?? 'pending')), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</a>
<?php endforeach; ?>
</div>
</section>
<aside class="w-2/5 border-l border-white/40 bg-white/30 backdrop-blur-md p-10 overflow-y-auto no-scrollbar">
<div class="space-y-10">
<?php if (!$selected): ?>
<div class="bg-white/70 border border-white rounded-[2rem] p-8 editorial-shadow">
<p class="text-sm font-semibold text-on-surface-variant">Select a tenant request to view details.</p>
</div>
<?php else: ?>
<div class="space-y-6">
<div class="flex items-center justify-between">
<h4 class="font-headline font-extrabold text-2xl text-on-surface">Review Details</h4>
<span class="text-[10px] font-extrabold text-primary px-3 py-1.5 bg-blue-50 rounded-xl border border-blue-100 uppercase tracking-widest">REF: #<?php echo (int) $selected['request_id']; ?></span>
</div>
<div class="p-8 bg-gradient-to-br from-primary via-[#1a80ff] to-[#0052cc] rounded-[2rem] text-white shadow-xl shadow-primary/20 relative overflow-hidden group">
<div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-[40px] group-hover:bg-white/20 transition-all duration-700"></div>
<h3 class="font-headline font-extrabold text-xl relative z-10"><?php echo htmlspecialchars((string) ($selected['clinic_name'] ?? 'Clinic'), ENT_QUOTES, 'UTF-8'); ?></h3>
<p class="text-blue-100 text-sm font-medium opacity-90 relative z-10 mt-1">Tenant ID: <?php echo htmlspecialchars((string) ($selected['tenant_id'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></p>
<div class="mt-8 space-y-4 relative z-10">
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-xl opacity-80">person</span>
<span class="text-xs font-medium leading-relaxed opacity-90"><?php echo htmlspecialchars((string) ($selected['owner_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-xl opacity-80">mail</span>
<span class="text-xs font-medium leading-relaxed opacity-90"><?php echo htmlspecialchars((string) ($selected['owner_email'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-xl opacity-80">calendar_today</span>
<span class="text-xs font-medium leading-relaxed opacity-90">Submitted: <?php echo htmlspecialchars((string) ($selected['submitted_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</div>
</div>
<div class="bg-white/60 backdrop-blur-md rounded-[2rem] p-6 editorial-shadow">
<div class="flex items-center justify-between border-b border-on-surface/5 pb-4">
<span class="text-[11px] font-extrabold text-on-surface-variant uppercase tracking-widest">Verification Status</span>
<span class="px-2 py-1 rounded-lg border text-[10px] uppercase tracking-widest font-extrabold <?php echo sa_status_badge_class((string) ($selected['status'] ?? 'pending')); ?>"><?php echo htmlspecialchars((string) ($selected['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<p class="text-xs font-semibold text-on-surface-variant mt-4">Reviewed at: <?php echo htmlspecialchars((string) ($selected['reviewed_at'] ?? 'Not reviewed yet'), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div class="space-y-3">
<h5 class="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-[0.2em] opacity-60">Submitted Documents</h5>
<?php if (empty($files)): ?>
<p class="text-sm font-semibold text-on-surface-variant">No files uploaded.</p>
<?php else: ?>
<div class="grid grid-cols-2 gap-4">
<?php foreach ($files as $f): ?>
<button type="button" data-file-url="../<?php echo htmlspecialchars((string) ($f['stored_file_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" data-file-name="<?php echo htmlspecialchars((string) ($f['original_file_name'] ?? 'file'), ENT_QUOTES, 'UTF-8'); ?>" class="doc-preview-trigger group relative aspect-[4/3] rounded-2xl overflow-hidden editorial-shadow bg-white/40 border border-white cursor-pointer text-left">
<div class="w-full h-full flex flex-col items-center justify-center px-3 text-center bg-gradient-to-br from-white to-blue-50/60">
<span class="material-symbols-outlined text-4xl text-primary/70">description</span>
<p class="mt-2 text-[10px] font-black uppercase tracking-widest text-on-surface-variant"><?php echo htmlspecialchars(sa_document_label((string) ($f['document_type'] ?? 'other')), ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[11px] font-bold text-on-surface line-clamp-2"><?php echo htmlspecialchars((string) ($f['original_file_name'] ?? 'file'), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
<div class="absolute inset-0 bg-primary/20 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2">
<span class="material-symbols-outlined text-white text-3xl">visibility</span>
<span class="text-white text-[10px] font-extrabold tracking-widest">VIEW FILE</span>
</div>
<div class="absolute bottom-3 left-3 px-2 py-1 bg-white/90 backdrop-blur-md rounded-lg text-[9px] font-bold text-on-surface border border-white">
<?php echo htmlspecialchars((string) ($f['mime_type'] ?? 'file'), ENT_QUOTES, 'UTF-8'); ?> • <?php echo number_format(((int) ($f['file_size_bytes'] ?? 0)) / 1024, 1); ?> KB
</div>
</button>
<?php endforeach; ?>
 </div>
<?php endif; ?>
</div>

<?php if (($selected['status'] ?? '') === 'pending'): ?>
<div class="pt-6 border-t border-white/60 space-y-4">
<form method="POST" class="space-y-4">
<input type="hidden" name="request_id" value="<?php echo (int) $selected['request_id']; ?>"/>
<textarea name="reviewer_notes" rows="3" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm bg-white/80 focus:border-primary/40 focus:ring-primary/20" placeholder="Optional notes for this review"></textarea>
<div class="flex gap-3">
<button type="submit" name="action" value="approve" class="flex-1 bg-primary text-white font-headline font-extrabold py-4 rounded-[2rem] primary-glow hover:brightness-110 transition-all">Approve Clinic Access</button>
<button type="submit" name="action" value="reject" class="flex-1 bg-white/80 border border-error/20 text-error font-headline font-extrabold py-4 rounded-[2rem] editorial-shadow hover:bg-error/5 transition-all">Reject Registration</button>
</div>
</form>
<p class="text-[10px] text-center text-on-surface-variant/60 mt-2 font-bold uppercase tracking-widest leading-relaxed">
Final approval will trigger automated onboarding email to clinic administrator.
</p>
</div>
<?php else: ?>
<div class="pt-6 border-t border-white/60">
<div class="bg-white/60 rounded-2xl p-4 border border-white">
<p class="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-[0.2em] opacity-60 mb-2">Review Notes</p>
<p class="text-sm font-semibold text-on-surface-variant"><?php echo htmlspecialchars((string) ($selected['reviewer_notes'] ?? 'No notes provided.'), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
</aside>
</div>
</main>
<div id="docPreviewModal" class="doc-preview-modal hidden fixed inset-0 z-50">
    <div id="docPreviewBackdrop" class="absolute inset-0 bg-slate-900/70"></div>
    <div class="relative h-full w-full p-4 md:p-8 flex items-center justify-center">
        <div class="w-full max-w-5xl h-[90vh] bg-white rounded-2xl shadow-2xl overflow-hidden border border-white/60">
            <div class="h-14 px-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                <h6 id="docPreviewTitle" class="text-sm font-bold text-slate-700 truncate pr-4">Document Preview</h6>
                <button id="docPreviewClose" type="button" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100">Close</button>
            </div>
            <div class="h-[calc(90vh-3.5rem)] bg-slate-100">
                <iframe id="docPreviewFrame" src="" class="w-full h-full border-0" title="Submitted document preview"></iframe>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        const modal = document.getElementById('docPreviewModal');
        const frame = document.getElementById('docPreviewFrame');
        const title = document.getElementById('docPreviewTitle');
        const closeBtn = document.getElementById('docPreviewClose');
        const backdrop = document.getElementById('docPreviewBackdrop');
        const triggers = document.querySelectorAll('.doc-preview-trigger');

        if (!modal || !frame || !title || !closeBtn || !backdrop || !triggers.length) {
            return;
        }

        function openModal(fileUrl, fileName) {
            frame.src = fileUrl;
            title.textContent = fileName || 'Document Preview';
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal() {
            modal.classList.add('hidden');
            frame.src = '';
            document.body.classList.remove('overflow-hidden');
        }

        triggers.forEach((trigger) => {
            trigger.addEventListener('click', function () {
                const fileUrl = this.getAttribute('data-file-url') || '';
                const fileName = this.getAttribute('data-file-name') || 'Document Preview';
                if (!fileUrl) {
                    return;
                }
                openModal(fileUrl, fileName);
            });
        });

        closeBtn.addEventListener('click', closeModal);
        backdrop.addEventListener('click', closeModal);
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeModal();
            }
        });
    })();
</script>
</body></html>