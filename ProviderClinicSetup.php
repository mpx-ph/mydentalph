<?php
session_start();
require_once __DIR__ . '/provider_redirect_superadmin.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/provider_auth.php';

$error = '';
$setup_access_granted = false;
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';
if ($debug_mode) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

$setup_token = trim((string) ($_GET['setup_token'] ?? ''));
$setup_request_id = (int) ($_GET['request_id'] ?? 0);
$tenant_id = '';
$approved_request = null;
$user_id = '';
$token_attempted = ($setup_token !== '' || $setup_request_id > 0);
$chosen_plan = isset($_GET['plan']) ? strtolower(trim((string) $_GET['plan'])) : '';
if (in_array($chosen_plan, ['starter', 'professional', 'enterprise'], true)) {
    $_SESSION['onboarding_plan'] = $chosen_plan;
}

function setup_log_error(string $message, Throwable $e = null): void
{
    $line = '[ProviderClinicSetup] ' . $message;
    if ($e !== null) {
        $line .= ' | ' . $e->getMessage();
    }
    error_log($line);
}

if ($setup_token !== '' && $setup_request_id > 0) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                r.request_id,
                r.tenant_id,
                r.owner_user_id,
                r.status,
                r.setup_token_hash,
                r.setup_token_expires_at,
                r.setup_token_used_at,
                t.clinic_name,
                t.clinic_slug,
                u.user_id,
                u.tenant_id AS user_tenant_id,
                u.username,
                u.email,
                u.full_name,
                u.role,
                u.status AS user_status
            FROM tbl_tenant_verification_requests r
            INNER JOIN tbl_tenants t ON t.tenant_id = r.tenant_id
            INNER JOIN tbl_users u ON u.user_id = r.owner_user_id
            WHERE r.request_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$setup_request_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            $error = 'This setup link is invalid or already unavailable.';
            $pdo->rollBack();
        } else {
            $token_hash = (string) ($row['setup_token_hash'] ?? '');
            $expires_at = (string) ($row['setup_token_expires_at'] ?? '');
            $used_at = (string) ($row['setup_token_used_at'] ?? '');
            $expires_ts = strtotime($expires_at);

            $is_valid = true;
            if (strtolower(trim((string) ($row['status'] ?? ''))) !== 'approved') {
                $is_valid = false;
                $error = 'Your account is not approved for clinic setup yet.';
            } elseif ((string) ($row['role'] ?? '') !== 'tenant_owner' || (string) ($row['user_status'] ?? '') !== 'active') {
                $is_valid = false;
                $error = 'Your account is not active for clinic setup.';
            } elseif ((string) ($row['tenant_id'] ?? '') === '' || (string) ($row['tenant_id'] ?? '') !== (string) ($row['user_tenant_id'] ?? '')) {
                $is_valid = false;
                $error = 'Tenant identity validation failed for this setup link.';
            } elseif ($token_hash === '' || $expires_at === '' || $expires_ts === false || $expires_ts < time()) {
                $is_valid = false;
                $error = 'This setup link has expired. Please contact support for a new link.';
            } elseif ($used_at !== '') {
                $is_valid = false;
                $error = 'This setup link has already been used.';
            } elseif (!password_verify($setup_token, $token_hash)) {
                $is_valid = false;
                $error = 'This setup link is invalid.';
            }

            if ($is_valid) {
                $mark_used = $pdo->prepare("
                    UPDATE tbl_tenant_verification_requests
                    SET setup_token_used_at = NOW()
                    WHERE request_id = ? AND setup_token_used_at IS NULL
                    LIMIT 1
                ");
                $mark_used->execute([(int) $row['request_id']]);

                if ($mark_used->rowCount() !== 1) {
                    $is_valid = false;
                    $error = 'This setup link has already been used.';
                }
            }

            if ($is_valid) {
                $pdo->commit();

                $setup_access_granted = true;
                $approved_request = $row;
                $tenant_id = (string) ($row['tenant_id'] ?? '');
                $user_id = (string) ($row['owner_user_id'] ?? '');

                provider_establish_authenticated_session([
                    'user_id' => (string) ($row['user_id'] ?? ''),
                    'tenant_id' => (string) ($row['tenant_id'] ?? ''),
                    'username' => (string) ($row['username'] ?? ''),
                    'email' => (string) ($row['email'] ?? ''),
                    'full_name' => (string) ($row['full_name'] ?? ''),
                    'role' => (string) ($row['role'] ?? ''),
                    'is_owner' => true,
                ]);

                $_SESSION['onboarding_tenant_id'] = $tenant_id;
                $_SESSION['onboarding_user_id'] = $user_id;
                $_SESSION['onboarding_setup_autologin_at'] = time();

                $redirect = 'ProviderClinicSetup.php';
                if ($debug_mode) {
                    $redirect .= '?debug=1';
                }
                header('Location: ' . $redirect);
                exit;
            }

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setup_log_error('Token validation failed.', $e);
        $error = 'Could not validate setup link right now. Please try again.';
    }
} elseif ($setup_token !== '' || $setup_request_id > 0) {
    $error = 'This setup link is invalid. Please use the full link from your approval email.';
}

if (!$setup_access_granted && !$token_attempted) {
    $session_user_id = (string) ($_SESSION['user_id'] ?? '');
    $session_tenant_id = (string) ($_SESSION['tenant_id'] ?? '');
    if ($session_user_id !== '' && $session_tenant_id !== '') {
        $tenant_id = $session_tenant_id;
        $user_id = $session_user_id;
        try {
            $stmt = $pdo->prepare("
                SELECT r.request_id, r.tenant_id, r.owner_user_id, r.status
                FROM tbl_tenant_verification_requests r
                INNER JOIN tbl_users u ON u.user_id = r.owner_user_id AND u.tenant_id = r.tenant_id
                WHERE r.tenant_id = ? AND r.owner_user_id = ? AND u.status = 'active'
                ORDER BY r.request_id DESC
                LIMIT 1
            ");
            $stmt->execute([$tenant_id, $user_id]);
            $approved_request = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $setup_access_granted = (bool) $approved_request
                && strtolower(trim((string) ($approved_request['status'] ?? ''))) === 'approved';
        } catch (Throwable $e) {
            setup_log_error('Session approval lookup failed.', $e);
            $error = 'Could not verify your approval status right now. Please try again.';
        }
    }
}

if (!$setup_access_granted) {
    if ($error !== '') {
        $_SESSION['provider_setup_link_error'] = $error;
    }
    header('Location: ProviderApprovalStatus.php');
    exit;
}

// Clinic setup page is restricted to approved providers with an authenticated session.
if (!provider_has_authenticated_provider_session()) {
    $redirect = 'ProviderClinicSetup.php';
    if ($debug_mode) {
        $redirect .= '?debug=1';
    }
    header('Location: ProviderLogin.php?redirect=' . urlencode($redirect));
    exit;
}

[$session_tenant_id, $session_user_id] = provider_get_authenticated_provider_identity_from_session();
if ((string) $session_tenant_id !== (string) $tenant_id || (string) $session_user_id !== (string) $user_id) {
    $_SESSION['provider_setup_link_error'] = 'Your session does not match this clinic setup request. Please sign in again.';
    header('Location: ProviderApprovalStatus.php');
    exit;
}

$tenant = [];
if ($tenant_id !== '') {
    try {
        $stmt = $pdo->prepare("SELECT clinic_name, clinic_slug FROM tbl_tenants WHERE tenant_id = ?");
        $stmt->execute([$tenant_id]);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        setup_log_error('Tenant prefill failed.', $e);
        $error = 'Could not load clinic details right now. Please refresh and try again.';
    }
}
$current_clinic_name = (string) ($tenant['clinic_name'] ?? '');
$current_slug = (string) ($tenant['clinic_slug'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $setup_access_granted) {
    $clinic_name = trim((string) ($_POST['clinic_name'] ?? ''));
    $clinic_slug_raw = trim((string) ($_POST['clinic_slug'] ?? ''));
    $clinic_slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($clinic_slug_raw));
    $owner_user_id = (string) ($approved_request['owner_user_id'] ?? $user_id);

    if ($tenant_id === '' || $owner_user_id === '') {
        $error = 'Your onboarding session is incomplete. Please log in again to continue.';
    } elseif (!provider_has_authenticated_provider_session()) {
        $error = 'Your session expired. Please log in again.';
    } elseif ($clinic_name === '') {
        $error = 'Clinic name is required.';
    } elseif (strlen($clinic_name) > 255) {
        $error = 'Clinic name must not exceed 255 characters.';
    } elseif ($clinic_slug === '') {
        $error = 'Clinic URL slug is required.';
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $clinic_slug)) {
        $error = 'Clinic URL can only contain lowercase letters, numbers, and hyphens.';
    } elseif (strlen($clinic_slug) < 3 || strlen($clinic_slug) > 100) {
        $error = 'Clinic URL must be between 3 and 100 characters.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_tenants WHERE clinic_slug = ? AND tenant_id != ?");
            $stmt->execute([$clinic_slug, $tenant_id]);
            if ((int) $stmt->fetchColumn() > 0) {
                $error = 'This clinic URL is already taken. Please choose another.';
            } else {
                $stmt = $pdo->prepare("UPDATE tbl_tenants SET clinic_name = ?, clinic_slug = ? WHERE tenant_id = ?");
                $stmt->execute([$clinic_name, $clinic_slug, $tenant_id]);

                $_SESSION['onboarding_tenant_id'] = $tenant_id;
                $_SESSION['onboarding_user_id'] = $owner_user_id;
                $_SESSION['onboarding_setup_completed_at'] = time();
                $_SESSION['onboarding_clinic_name'] = $clinic_name;
                $_SESSION['onboarding_clinic_slug'] = $clinic_slug;
                $_SESSION['force_purchase_from_clinic_setup_once'] = 1;

                $next_plan = strtolower(trim((string) ($_SESSION['onboarding_plan'] ?? '')));
                $redirect = 'ProviderPurchase.php';
                if (in_array($next_plan, ['starter', 'professional', 'enterprise'], true)) {
                    $redirect .= '?plan=' . urlencode($next_plan);
                }
                header('Location: ' . $redirect);
                exit;
            }
        } catch (Throwable $e) {
            setup_log_error('Clinic setup save failed.', $e);
            $error = 'Could not save clinic setup right now. Please try again.';
            if ($debug_mode) {
                $error .= ' Debug: ' . $e->getMessage();
            }
        }
    }
}

if ($current_slug === '' && $current_clinic_name !== '') {
    $current_slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '', $current_clinic_name)));
}
?>
<!DOCTYPE html>

<html class="light scroll-smooth" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Set Up Your Clinic Workspace - MyDental</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@400;500;600&family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "error-container": "#ffdad6",
                        "secondary-fixed": "#d4e3ff",
                        "on-background": "#131c25",
                        "inverse-surface": "#28313b",
                        "on-tertiary": "#ffffff",
                        "primary": "#2b8beb",
                        "secondary": "#456085",
                        "on-tertiary-fixed-variant": "#6e3900",
                        "secondary-fixed-dim": "#adc8f3",
                        "primary-fixed": "#d4e3ff",
                        "tertiary-container": "#b25f00",
                        "on-tertiary-fixed": "#2f1500",
                        "surface-container-low": "#edf4ff",
                        "on-secondary-fixed": "#001c39",
                        "on-primary-fixed-variant": "#004883",
                        "tertiary-fixed": "#ffdcc3",
                        "outline-variant": "#c0c7d4",
                        "on-error-container": "#93000a",
                        "primary-container": "#2b8beb",
                        "error": "#ba1a1a",
                        "primary-fixed-dim": "#a4c9ff",
                        "background": "#f7f9ff",
                        "surface-container-lowest": "#ffffff",
                        "surface": "#f7f9ff",
                        "tertiary-fixed-dim": "#ffb77e",
                        "on-surface": "#131c25",
                        "surface-tint": "#2b8beb",
                        "surface-container": "#e6effc",
                        "on-primary": "#ffffff",
                        "inverse-primary": "#a4c9ff",
                        "surface-container-highest": "#dae3f0",
                        "on-secondary-fixed-variant": "#2c486c",
                        "secondary-container": "#b8d3fe",
                        "tertiary": "#8e4a00",
                        "surface-variant": "#dae3f0",
                        "outline": "#717784",
                        "on-surface-variant": "#404752",
                        "on-secondary": "#ffffff",
                        "surface-container-high": "#e0e9f6",
                        "on-tertiary-container": "#fffbff",
                        "on-primary-fixed-variant": "#004883",
                        "surface-dim": "#d2dbe8",
                        "surface-bright": "#f7f9ff",
                        "on-primary-container": "#fdfcff",
                        "inverse-on-surface": "#e8f1ff",
                        "on-secondary-container": "#405b80",
                        "on-error": "#ffffff",

                        /* Keep existing app colors used by Provider screens/components */
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Inter", "sans-serif"],
                        "label": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>

    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
    </style>
</head>

<body class="font-body text-on-surface bg-surface h-screen overflow-hidden flex flex-col dark:bg-background-dark dark:text-surface antialiased">
<?php include 'ProviderNavbar.php'; ?>

<main class="flex-1 flex items-center justify-center p-6 sm:p-12 relative overflow-hidden">
    <div class="relative z-10 w-full max-w-xl bg-surface-container-lowest rounded-3xl shadow-[0_32px_64px_-12px_rgba(43,139,235,0.08)] border border-on-surface/5 p-10 md:p-14">
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-surface-container-low rounded-2xl mb-8">
                <span class="material-symbols-outlined text-primary text-4xl">domain</span>
            </div>

            <h1 class="font-headline text-5xl md:text-6xl font-extrabold text-on-surface tracking-tighter leading-[0.9] mb-6">
                Set Up Your <br/>
                <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Clinic Workspace</span>
            </h1>

            <p class="text-on-surface-variant text-lg font-medium max-w-sm mx-auto">
                Enter your clinic details below to start managing your workspace.
            </p>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 bg-error-container border border-on-error-container/25 text-on-error-container rounded-2xl text-sm text-center font-semibold">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '')); ?>" class="space-y-8 max-w-md mx-auto">
            <div class="space-y-6">
                <!-- Clinic Name Field -->
                <div class="group">
                    <label class="block font-headline text-xs font-bold uppercase tracking-[0.2em] text-on-surface/60 mb-3 px-1" for="clinic-name">Clinic Name</label>
                    <div class="relative">
                        <input
                            class="w-full bg-surface-container-low border border-on-surface/5 focus:border-primary focus:ring-4 focus:ring-primary/10 rounded-2xl py-4.5 px-6 text-on-surface placeholder:text-on-surface-variant/40 transition-all font-body font-medium outline-none"
                            id="clinic-name"
                            name="clinic_name"
                            placeholder="e.g. North Star Dental"
                            type="text"
                            value="<?php echo htmlspecialchars($current_clinic_name); ?>"
                            required
                            <?php echo $setup_access_granted ? '' : 'disabled'; ?>
                        />
                    </div>
                </div>

                <!-- Clinic URL Field -->
                <div class="group">
                    <div class="flex justify-between items-end mb-3">
                        <label class="block font-headline text-xs font-bold uppercase tracking-[0.2em] text-on-surface/60 px-1" for="clinic-url">Clinic URL / Domain</label>
                        <span class="inline-flex items-center gap-1 text-[11px] font-black uppercase tracking-[0.2em] text-emerald-600">
                            <span class="material-symbols-outlined text-sm">check_circle</span>
                            Available
                        </span>
                    </div>

                    <div class="flex items-stretch shadow-sm">
                        <div class="flex items-center px-4 bg-surface-container-high border border-on-surface/5 border-r-0 rounded-l-2xl text-on-surface/70 font-semibold select-none">
                            mydental.ct.ws/
                        </div>
                        <input
                            class="flex-1 bg-surface-container-low border border-on-surface/5 border-l-0 focus:border-primary focus:ring-4 focus:ring-primary/10 rounded-r-2xl py-4.5 px-6 text-on-surface placeholder:text-on-surface-variant/40 transition-all font-body font-medium outline-none"
                            id="clinic-url"
                            name="clinic_slug"
                            placeholder="e.g. jrldentalclinic"
                            type="text"
                            value="<?php echo htmlspecialchars($current_slug); ?>"
                            required
                            pattern="[a-z0-9\-]+"
                            title="Lowercase letters, numbers and hyphens only"
                            <?php echo $setup_access_granted ? '' : 'disabled'; ?>
                        />
                    </div>

                    <div class="mt-4 bg-surface-container-lowest p-5 rounded-2xl border border-on-surface/5">
                        <div class="flex gap-3 items-start">
                            <span class="material-symbols-outlined text-on-surface/40 text-xl">info</span>

                            <div class="space-y-2">
                                <p class="text-xs text-on-surface-variant leading-relaxed font-medium">
                                    Your team will use this URL to access your workspace.
                                </p>

                                <ul class="text-[11px] font-bold text-on-surface/35 flex flex-wrap gap-x-4 gap-y-1 uppercase tracking-tight">
                                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-on-surface/20"></span> Lowercase</li>
                                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-on-surface/20"></span> No Spaces</li>
                                    <li class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-on-surface/20"></span> Alphanumeric Only</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col items-center pt-2">
                <button
                    class="w-full bg-primary text-white py-5 px-8 rounded-2xl font-black text-sm uppercase tracking-widest shadow-xl shadow-primary/20 transition-all hover:scale-[1.02] active:scale-95"
                    type="submit"
                    <?php echo $setup_access_granted ? '' : 'disabled'; ?>
                >
                    <?php echo $setup_access_granted ? 'Continue to Purchase' : 'Awaiting Super Admin Approval'; ?>
                    <span class="material-symbols-outlined ml-2" style="vertical-align: middle;">arrow_forward</span>
                </button>

                <div class="mt-8 flex items-center gap-4 text-[10px] font-black uppercase tracking-[0.4em] text-primary/60">
                    <span class="w-8 h-[1px] bg-primary/20"></span>
                    Step 1 of 4: Core Identity
                    <span class="w-8 h-[1px] bg-primary/20"></span>
                </div>
            </div>
        </form>
    </div>
</main>
</body>
</html>

