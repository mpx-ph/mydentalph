<?php
$staff_nav_active = 'payment_settings';
require_once __DIR__ . '/config/config.php';

// Dentist role restriction: redirect to dashboard
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['user_role']) && strtolower(trim((string) $_SESSION['user_role'])) === 'dentist') {
    header('Location: StaffDashboard.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$currentStaffUserId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : '';
$formMessage = '';
$formMessageType = 'success';
$paymentSettings = [
    'regular_downpayment_percentage' => 20.00,
    'long_term_min_downpayment' => 500.00,
];

try {
    $pdo = getDBConnection();

    if ($tenantId === '' && $currentTenantSlug !== '') {
        $tenantStmt = $pdo->prepare('SELECT tenant_id FROM tbl_tenants WHERE clinic_slug = ? LIMIT 1');
        $tenantStmt->execute([$currentTenantSlug]);
        $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenantRow && isset($tenantRow['tenant_id'])) {
            $tenantId = (string) $tenantRow['tenant_id'];
        }
    }

    if ($tenantId !== '') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $updateField = isset($_POST['update_field']) ? trim((string) $_POST['update_field']) : '';
            $newSettings = $paymentSettings;

            $existingStmt = $pdo->prepare('SELECT regular_downpayment_percentage, long_term_min_downpayment FROM tbl_payment_settings WHERE tenant_id = ? LIMIT 1');
            $existingStmt->execute([$tenantId]);
            $existingSettings = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingSettings) {
                $newSettings['regular_downpayment_percentage'] = (float) $existingSettings['regular_downpayment_percentage'];
                $newSettings['long_term_min_downpayment'] = (float) $existingSettings['long_term_min_downpayment'];
            }

            if ($updateField === 'regular') {
                $regularInput = isset($_POST['regular_downpayment_percentage']) ? (float) $_POST['regular_downpayment_percentage'] : -1;
                if ($regularInput < 0 || $regularInput > 100) {
                    $formMessage = 'Regular services down payment must be between 0 and 100.';
                    $formMessageType = 'error';
                } else {
                    $newSettings['regular_downpayment_percentage'] = round($regularInput, 2);
                }
            } elseif ($updateField === 'long_term') {
                $longTermInput = isset($_POST['long_term_min_downpayment']) ? (float) $_POST['long_term_min_downpayment'] : -1;
                if ($longTermInput < 0) {
                    $formMessage = 'Long-term services minimum down payment cannot be negative.';
                    $formMessageType = 'error';
                } else {
                    $newSettings['long_term_min_downpayment'] = round($longTermInput, 2);
                }
            } else {
                $formMessage = 'Unknown update action.';
                $formMessageType = 'error';
            }

            if ($formMessageType !== 'error') {
                $upsertSql = "
                    INSERT INTO tbl_payment_settings (
                        tenant_id,
                        regular_downpayment_percentage,
                        long_term_min_downpayment,
                        updated_by
                    ) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        regular_downpayment_percentage = VALUES(regular_downpayment_percentage),
                        long_term_min_downpayment = VALUES(long_term_min_downpayment),
                        updated_by = VALUES(updated_by),
                        updated_at = CURRENT_TIMESTAMP
                ";
                $upsertStmt = $pdo->prepare($upsertSql);
                $upsertStmt->execute([
                    $tenantId,
                    $newSettings['regular_downpayment_percentage'],
                    $newSettings['long_term_min_downpayment'],
                    $currentStaffUserId !== '' ? $currentStaffUserId : null,
                ]);

                $paymentSettings = $newSettings;
                $formMessage = 'Payment settings updated successfully.';
                $formMessageType = 'success';
            }
        }

        $settingsStmt = $pdo->prepare('SELECT regular_downpayment_percentage, long_term_min_downpayment FROM tbl_payment_settings WHERE tenant_id = ? LIMIT 1');
        $settingsStmt->execute([$tenantId]);
        $settingsRow = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        if ($settingsRow) {
            $paymentSettings['regular_downpayment_percentage'] = (float) $settingsRow['regular_downpayment_percentage'];
            $paymentSettings['long_term_min_downpayment'] = (float) $settingsRow['long_term_min_downpayment'];
        }
    } else {
        $formMessage = 'Unable to resolve clinic tenant. Payment settings are unavailable.';
        $formMessageType = 'error';
    }
} catch (Throwable $e) {
    error_log('Staff payment settings load/save error: ' . $e->getMessage());
    $formMessage = 'An unexpected error occurred while loading payment settings.';
    $formMessageType = 'error';
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision - Payment Settings</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background": "#f8fafc",
                        "surface": "#ffffff",
                        "on-background": "#101922",
                        "on-surface-variant": "#404752",
                        "surface-container-low": "#edf4ff",
                        "outline-variant": "#cbd5e1"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem"
                    },
                },
            },
        }
    </script>
<style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .elevated-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<!-- SideNavBar Component -->
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> FINANCE &amp; PAYMENTS
            </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Payment <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Settings</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Configure payment rules and down payment options for services
                    </p>
</div>
</div>
</section>
<?php if ($formMessage !== ''): ?>
<section class="rounded-2xl border px-6 py-4 <?php echo $formMessageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'; ?>">
<p class="text-sm font-semibold"><?php echo htmlspecialchars($formMessage, ENT_QUOTES, 'UTF-8'); ?></p>
</section>
<?php endif; ?>
<!-- Configuration Cards Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
<!-- Regular Services Down Payment -->
<div class="elevated-card rounded-3xl p-10 flex flex-col h-full hover:border-primary/30 transition-all group bg-slate-50/50">
<form method="post" class="h-full flex flex-col gap-6 payment-rule-form">
<input type="hidden" name="update_field" value="regular"/>
<div class="flex items-center gap-5">
<div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
</div>
<div>
<h3 class="font-headline font-bold text-2xl text-on-background tracking-tight">Regular Services Down Payment</h3>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-1">Standard clinic procedures &amp; checkups</p>
</div>
</div>
<div class="space-y-6 flex-1 flex flex-col">
<div class="space-y-3">
<label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Requirement Percentage</label>
<div class="relative">
<input class="w-full bg-white border-none rounded-xl px-6 py-5 text-2xl font-headline font-extrabold text-on-background focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" type="number" name="regular_downpayment_percentage" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars(number_format((float) $paymentSettings['regular_downpayment_percentage'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"/>
<div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none">
<span class="text-2xl font-extrabold text-primary">%</span>
</div>
</div>
</div>
<div class="p-5 rounded-2xl bg-white/60 border border-slate-100 text-sm text-slate-500 font-medium leading-relaxed min-h-[96px]">
                        Applied to all basic diagnostic, preventative, and minor restorative services.
                    </div>
<button type="submit" class="w-full py-4 bg-primary text-white font-bold text-xs uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:shadow-primary/40 active:scale-95 transition-all flex items-center justify-center gap-2 mt-auto">
<span class="material-symbols-outlined text-lg">published_with_changes</span>
                        Update Rule
                    </button>
</div>
</form>
</div>
<!-- Long-Term Services Down Payment -->
<div class="elevated-card rounded-3xl p-10 flex flex-col h-full hover:border-primary/30 transition-all group bg-slate-50/50">
<form method="post" class="h-full flex flex-col gap-6 payment-rule-form">
<input type="hidden" name="update_field" value="long_term"/>
<div class="flex items-center gap-5">
<div class="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center text-primary group-hover:bg-primary group-hover:text-white transition-colors">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">event_repeat</span>
</div>
<div>
<h3 class="font-headline font-bold text-2xl text-on-background tracking-tight">Long-Term Services Down Payment</h3>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-1">Orthodontics, implants, and multi-stage plans</p>
</div>
</div>
<div class="space-y-6 flex-1 flex flex-col">
<div class="space-y-3">
<label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Fixed Minimum Amount</label>
<div class="relative">
<input class="w-full bg-white border-none rounded-xl pl-14 pr-16 py-5 text-2xl font-headline font-extrabold text-on-background focus:ring-2 focus:ring-primary/20 transition-all shadow-sm" type="number" name="long_term_min_downpayment" min="0" step="0.01" value="<?php echo htmlspecialchars(number_format((float) $paymentSettings['long_term_min_downpayment'], 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"/>
<div class="absolute left-6 top-1/2 -translate-y-1/2 pointer-events-none">
<span class="material-symbols-outlined text-primary text-2xl" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none">
<span class="text-xs font-black text-slate-400 uppercase tracking-widest">USD</span>
</div>
</div>
</div>
<div class="p-5 rounded-2xl bg-white/60 border border-slate-100 text-sm text-slate-500 font-medium leading-relaxed min-h-[96px]">
                        Minimum deposit required to initiate multi-month treatment cycles and material orders.
                    </div>
<button type="submit" class="w-full py-4 bg-primary text-white font-bold text-xs uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:shadow-primary/40 active:scale-95 transition-all flex items-center justify-center gap-2 mt-auto">
<span class="material-symbols-outlined text-lg">published_with_changes</span>
                        Update Rule
                    </button>
</div>
</form>
</div>
</div>
</div>
</main>
<div id="update-rule-confirm-modal" class="fixed inset-0 z-50 hidden items-center justify-center px-4">
<div id="update-rule-confirm-overlay" class="absolute inset-0 bg-slate-900/50"></div>
<div class="relative w-full max-w-md rounded-3xl bg-white border border-slate-200 shadow-2xl p-8">
<div class="flex items-start gap-4">
<div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">help</span>
</div>
<div class="space-y-2">
<h4 class="text-xl font-headline font-extrabold text-on-background">Confirm Update</h4>
<p class="text-sm font-medium text-on-surface-variant leading-relaxed">Are you sure you want to update these payment rules?</p>
</div>
</div>
<div class="mt-8 flex justify-end gap-3">
<button type="button" id="update-rule-cancel-btn" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-xs font-bold uppercase tracking-widest hover:bg-slate-50 transition-colors">Cancel</button>
<button type="button" id="update-rule-confirm-btn" class="px-5 py-2.5 rounded-xl bg-primary text-white text-xs font-bold uppercase tracking-widest shadow-lg shadow-primary/25 hover:shadow-primary/40 transition-all">Confirm</button>
</div>
</div>
</div>
<script>
    (function () {
        var forms = document.querySelectorAll('.payment-rule-form');
        var modal = document.getElementById('update-rule-confirm-modal');
        var overlay = document.getElementById('update-rule-confirm-overlay');
        var cancelBtn = document.getElementById('update-rule-cancel-btn');
        var confirmBtn = document.getElementById('update-rule-confirm-btn');
        var pendingForm = null;
        var skipConfirmation = false;

        if (!forms.length || !modal || !overlay || !cancelBtn || !confirmBtn) {
            return;
        }

        function openModal(form) {
            pendingForm = form;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeModal() {
            pendingForm = null;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        forms.forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (skipConfirmation) {
                    return;
                }

                event.preventDefault();
                openModal(form);
            });
        });

        cancelBtn.addEventListener('click', function () {
            closeModal();
        });

        overlay.addEventListener('click', function () {
            closeModal();
        });

        confirmBtn.addEventListener('click', function () {
            if (!pendingForm) {
                closeModal();
                return;
            }

            var targetForm = pendingForm;
            skipConfirmation = true;
            closeModal();
            targetForm.submit();
        });
    })();
</script>
</body></html>