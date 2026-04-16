<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';
$success = '';
$postLoginRedirect = '';

function providerNormalizeStatus($status): string {
    return strtolower(trim((string) $status));
}

function providerWriteAuditLog($pdo, $tenantId, $userId, $action, $description = null) {
    try {
        $tenantId = trim((string) $tenantId);
        if ($tenantId === '' || trim((string) $action) === '') {
            return;
        }

        $ipAddress = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = trim((string) explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = trim((string) $_SERVER['REMOTE_ADDR']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO tbl_audit_logs (tenant_id, user_id, action, description, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenantId,
            $userId !== null && trim((string) $userId) !== '' ? (string) $userId : null,
            (string) $action,
            $description !== null && trim((string) $description) !== '' ? (string) $description : null,
            $ipAddress !== '' ? $ipAddress : null
        ]);
    } catch (Throwable $e) {
        error_log('Provider audit log write failed: ' . $e->getMessage());
    }
}

// Already logged in -> redirect to home or requested redirect
// user_id can be 0 for hardcoded superadmin; empty() treats 0 as empty
if (isset($_SESSION['user_id'])) {
    $redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
    if ($redirect !== '' && preg_match('#^([a-zA-Z0-9_\-\.]+/)?[a-zA-Z0-9_\-\.]+\.php(\?.*)?$#', $redirect)) {
        $is_superadmin_path = (strpos($redirect, 'superadmin/') === 0);
        if ($is_superadmin_path && ($_SESSION['role'] ?? '') !== 'superadmin') {
            header('Location: /');
        } else {
            header('Location: ' . $redirect);
        }
    } elseif (($_SESSION['role'] ?? '') === 'superadmin') {
        header('Location: superadmin/dashboard.php');
    } else {
        header('Location: /');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_identifier = trim($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_identifier) || empty($password)) {
        $error = 'Please enter your email/username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, tenant_id, username, email, full_name, role, status, password_hash FROM tbl_users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$login_identifier, $login_identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $userRole = (string) ($user['role'] ?? '');
                $tenantId = (string) ($user['tenant_id'] ?? '');
                $ownerUserId = (string) ($user['user_id'] ?? '');

                $allowed = false;

                // Super admin bypasses tenant approval checks.
                if ($userRole === 'superadmin') {
                    $allowed = true;
                } else {
                    // Provider portal login is reserved for tenant owners.
                    if ($userRole !== 'tenant_owner') {
                        $allowed = false;
                        $error = 'Only approved tenant owners can log in.';
                    } else {
                        // Start as allowed, then apply owner/approval/active guards below.
                        $allowed = true;
                    }

                    // Ensure this user is the tenant owner record.
                    if ($allowed !== false) {
                        $stmt = $pdo->prepare("SELECT owner_user_id FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
                        $stmt->execute([$tenantId]);
                        $actualOwnerId = (string) ($stmt->fetchColumn() ?: '');
                        if ($actualOwnerId === '' || $actualOwnerId !== $ownerUserId) {
                            $allowed = false;
                            $error = 'Only approved tenant owners can log in.';
                        }
                    }

                    // Only allow login if the clinic verification request is APPROVED.
                    // Rejected users should get a rejected-specific message.
                    if ($allowed !== false) {
                        $stmt = $pdo->prepare("
                            SELECT status
                            FROM tbl_tenant_verification_requests
                            WHERE tenant_id = ? AND owner_user_id = ?
                            ORDER BY request_id DESC
                            LIMIT 1
                        ");
                        $stmt->execute([$tenantId, $ownerUserId]);
                        $reqStatusRaw = $stmt->fetchColumn();
                        $reqStatus = providerNormalizeStatus($reqStatusRaw !== false ? (string) $reqStatusRaw : '');

                        if ($reqStatus !== 'approved') {
                            $allowed = false;
                            $error = $reqStatus === 'rejected'
                                ? 'Your account was rejected. You cannot log in.'
                                : 'Your account is pending approval. Please wait for an approval update.';
                        }
                    }

                    if ($allowed !== false) {
                        // Approved request exists; require active provider account status too.
                        $stmt = $pdo->prepare("SELECT 1 FROM tbl_users WHERE user_id = ? AND tenant_id = ? AND status = 'active' LIMIT 1");
                        $stmt->execute([$ownerUserId, $tenantId]);
                        $userActive = (bool) $stmt->fetchColumn();

                        if ($userActive) {
                            $allowed = true;
                        } else {
                            $allowed = false;
                            $error = 'Your account is not active yet. Please contact support.';
                        }
                    }
                }

                if (!$allowed) {
                    // Never create provider login session for pending/rejected accounts.
                    unset(
                        $_SESSION['user_id'],
                        $_SESSION['tenant_id'],
                        $_SESSION['name'],
                        $_SESSION['username'],
                        $_SESSION['email'],
                        $_SESSION['full_name'],
                        $_SESSION['role'],
                        $_SESSION['status'],
                        $_SESSION['is_owner']
                    );
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['tenant_id'] = $user['tenant_id'];
                    $_SESSION['name'] = $user['full_name'] ?: $user['username'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['status'] = $user['status'];

                    // Load tenant and set owner status (owner_user_id)
                    $is_owner = false;
                    if (!empty($user['tenant_id'])) {
                        $stmt = $pdo->prepare("SELECT owner_user_id FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
                        $stmt->execute([$user['tenant_id']]);
                        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
                        $is_owner = ($tenant && isset($tenant['owner_user_id']) && $tenant['owner_user_id'] === $user['user_id']);
                    }
                    $_SESSION['is_owner'] = $is_owner;
                    unset(
                        $_SESSION['tenant_clinic_slug'],
                        $_SESSION['tenant_clinic_link'],
                        $_SESSION['paymongo_checkout_return_token'],
                        $_SESSION['paymongo_checkout_session_id'],
                        $_SESSION['paymongo_payment_intent_id'],
                        $_SESSION['paymongo_client_key'],
                        $_SESSION['paymongo_payment_method'],
                        $_SESSION['paymongo_billing_email'],
                        $_SESSION['paymongo_tenant_id'],
                        $_SESSION['paymongo_user_id'],
                        $_SESSION['paymongo_plan_id'],
                        $_SESSION['paymongo_plan_name'],
                        $_SESSION['paymongo_plan_price'],
                        $_SESSION['paymongo_plan_slug'],
                        $_SESSION['paymongo_reference_number']
                    );
                    providerWriteAuditLog(
                        $pdo,
                        (string) $user['tenant_id'],
                        (string) $user['user_id'],
                        'LOGIN',
                        'Provider user logged in.'
                    );

                    try {
                        $st = $pdo->prepare('UPDATE tbl_users SET last_active = CURRENT_TIMESTAMP, last_login = CURRENT_TIMESTAMP WHERE user_id = ?');
                        $st->execute([(string) $user['user_id']]);
                    } catch (Throwable $e) {
                        try {
                            $st = $pdo->prepare('UPDATE tbl_users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?');
                            $st->execute([(string) $user['user_id']]);
                        } catch (Throwable $e2) {
                            error_log('ProviderLogin last_active: ' . $e2->getMessage());
                        }
                    }

                    $redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
                    if (($user['role'] ?? '') === 'superadmin') {
                        if ($redirect !== '' && preg_match('#^([a-zA-Z0-9_\-\.]+/)?[a-zA-Z0-9_\-\.]+\.php(\?.*)?$#', $redirect)
                            && strpos($redirect, 'superadmin/') === 0) {
                            $postLoginRedirect = $redirect;
                        } else {
                            $postLoginRedirect = 'superadmin/dashboard.php';
                        }
                    } elseif ($redirect !== '' && preg_match('#^([a-zA-Z0-9_\-\.]+/)?[a-zA-Z0-9_\-\.]+\.php(\?.*)?$#', $redirect)) {
                        $postLoginRedirect = $redirect;
                    } else {
                        $postLoginRedirect = '/';
                    }
                }
            }

            if ($error === '') {
                $error = 'Invalid email/username or password.';
            }
        } catch (PDOException $e) {
            $error = 'A temporary error occurred. Please try again.';
        }
    }
}

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = 'Password updated successfully. Please log in with your new password.';
}
?>
<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
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
                        "surface-container-low": "#edf4ff",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101922"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "1.5rem", "2xl": "2.5rem", "full": "9999px"},
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .mesh-gradient {
            background-color: #ffffff;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 40px 80px -20px rgba(43, 139, 235, 0.08);
        }
        /* Scroll-reveal "pop up" animation (matches ProviderMain.php) */
        .reveal {
            opacity: 0;
            transform: translateY(34px) scale(0.985);
            filter: blur(12px);
            transition:
                opacity 900ms cubic-bezier(0.22, 1, 0.36, 1),
                transform 900ms cubic-bezier(0.22, 1, 0.36, 1),
                filter 900ms cubic-bezier(0.22, 1, 0.36, 1);
            will-change: opacity, transform, filter;
        }
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
        }
        .login-success-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(3px);
            pointer-events: all;
        }
        .login-success-overlay.is-active {
            display: flex;
        }
        .login-success-panel {
            width: min(88vw, 440px);
            height: min(88vw, 440px);
            display: grid;
            place-items: center;
        }
        .login-success-animation {
            width: 100%;
            height: 100%;
        }
        @media (prefers-reduced-motion: reduce) {
            .reveal {
                opacity: 1;
                transform: none;
                filter: none;
                transition: none;
            }
        }
    </style>
    <title>Provider Portal Login - MyDental</title>
</head>

<body class="mesh-gradient min-h-screen overflow-x-hidden overflow-y-auto flex flex-col items-center selection:bg-primary/20 bg-background-light dark:bg-background-dark text-on-surface dark:text-slate-100 antialiased">
<?php include 'ProviderNavbar.php'; ?>

<main class="flex-1 w-full grid place-items-center px-4 sm:px-6 lg:px-8 relative py-5 sm:py-10 reveal" data-reveal="section">
    <div class="w-full max-w-lg">
        <!-- Login Card -->
        <div class="login-card rounded-[2.5rem] overflow-hidden p-6 sm:p-12 space-y-6 sm:space-y-10">
            <!-- Header Content -->
            <div class="text-center space-y-4">
                <h1 class="font-headline text-4xl sm:text-5xl font-extrabold tracking-tighter leading-[1.1] text-on-surface">
                    Log In to Your
                    <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block mt-1 sm:mt-0">Provider Account</span>
                </h1>
                <p class="text-on-surface-variant font-medium text-base sm:text-lg leading-relaxed max-w-xs mx-auto">
                    Access your dashboard to manage clinic settings and subscriptions
                </p>
            </div>

            <?php if ($error): ?>
                <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4 text-sm text-green-700">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form class="space-y-6 sm:space-y-8" method="post" action="">
                <div class="space-y-5 sm:space-y-6">
                    <!-- Login identifier Field -->
                    <div class="space-y-2.5">
                        <label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em] ml-1" for="login_identifier">Email Address</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="material-symbols-outlined text-primary/40 text-xl font-light">mail</span>
                            </div>
                            <input class="block w-full pl-12 pr-4 py-3.5 sm:py-4 bg-surface-container-low/50 border rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:outline-none text-on-surface font-medium transition-all duration-200 placeholder:text-on-surface-variant/40 border-slate-200"
                                   id="login_identifier"
                                   name="login_identifier"
                                   value="<?php echo isset($_POST['login_identifier']) ? htmlspecialchars($_POST['login_identifier']) : ''; ?>"
                                   placeholder="name@clinic.com"
                                   required
                                   type="text"
                                   autocomplete="username"/>
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div class="space-y-2.5">
                        <div class="flex justify-between items-center px-1">
                            <label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em]" for="password">Password</label>
                            <a class="text-[10px] font-black uppercase tracking-widest text-primary hover:opacity-70 transition-opacity" href="ProviderFindAccount.php">Forgot Password?</a>
                        </div>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <span class="material-symbols-outlined text-primary/40 text-xl font-light">lock</span>
                            </div>
                            <input class="block w-full pl-12 pr-16 py-3.5 sm:py-4 bg-surface-container-low/50 border rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:outline-none text-on-surface font-medium transition-all duration-200 placeholder:text-on-surface-variant/40 border-slate-200"
                                   id="password"
                                   name="password"
                                   placeholder="********"
                                   required
                                   type="password"
                                   autocomplete="current-password"/>
                            <button class="absolute inset-y-0 right-0 pr-4 flex items-center text-on-surface-variant hover:text-on-surface transition-colors"
                                    type="button"
                                    onclick="var p=document.getElementById('password'); p.type=p.type==='password'?'text':'password';">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Action Button -->
                <button class="w-full py-4 sm:py-5 px-6 bg-primary text-white font-black text-sm uppercase tracking-[0.2em] rounded-2xl shadow-xl shadow-primary/20 hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] transition-all duration-200" type="submit">
                    Login
                </button>
            </form>

            <!-- Footer Link -->
            <div class="pt-2 text-center">
                <p class="text-sm font-semibold text-on-surface-variant">
                    New tenant?
                    <a class="font-black text-primary hover:underline underline-offset-4 transition-all" href="ProviderCreate.php">Create an Account</a>
                </p>
            </div>
        </div>

        <!-- Trust Badges / Security -->
        <div class="mt-6 sm:mt-8 hidden sm:flex justify-center items-center space-x-10 opacity-30 hover:opacity-60 transition-all duration-500 reveal" data-reveal="section">
            <div class="flex items-center space-x-2">
                <span class="material-symbols-outlined text-lg">verified_user</span>
                <span class="text-[10px] uppercase font-black tracking-[0.2em]">HIPAA Compliant</span>
            </div>
            <div class="flex items-center space-x-2">
                <span class="material-symbols-outlined text-lg">lock_person</span>
                <span class="text-[10px] uppercase font-black tracking-[0.2em]">256-bit SSL</span>
            </div>
        </div>
    </div>
</main>

<!-- Security Badge (Floating) -->
<div class="fixed bottom-4 right-4 hidden md:flex items-center gap-2 bg-white dark:bg-slate-800 px-3 py-2 rounded-full shadow-lg border border-slate-100 dark:border-slate-700">
    <span class="material-symbols-outlined text-green-500 text-lg">verified_user</span>
    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Secure AES-256 Encryption</span>
</div>

<div id="loginSuccessOverlay" class="login-success-overlay" aria-hidden="true">
    <div class="login-success-panel" role="status" aria-live="polite" aria-label="Login successful">
        <div id="loginSuccessAnimation" class="login-success-animation"></div>
    </div>
</div>

<!-- Decorative Background Accents -->
<div class="fixed top-[-10%] right-[-5%] w-[40rem] h-[40rem] bg-primary/5 rounded-full blur-[120px] -z-10 pointer-events-none"></div>
<div class="fixed bottom-[-10%] left-[-5%] w-[30rem] h-[30rem] bg-primary/5 rounded-full blur-[100px] -z-10 pointer-events-none"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js" defer></script>
<script>
    (function () {
        var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var elements = document.querySelectorAll('[data-reveal="section"]');

        if (!elements || !elements.length) return;
        if (prefersReduced || !('IntersectionObserver' in window)) {
            elements.forEach(function (el) { el.classList.add('is-visible'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                } else {
                    entry.target.classList.remove('is-visible');
                }
            });
        }, { threshold: 0.18, rootMargin: '0px 0px -10% 0px' });

        elements.forEach(function (el) { observer.observe(el); });
    })();

    (function () {
        var redirectUrl = <?php echo json_encode($postLoginRedirect); ?>;
        if (!redirectUrl) {
            return;
        }

        var overlay = document.getElementById('loginSuccessOverlay');
        var animationContainer = document.getElementById('loginSuccessAnimation');
        if (!overlay || !animationContainer) {
            window.location.assign(redirectUrl);
            return;
        }

        overlay.classList.add('is-active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        var maxWaitTimer = null;
        var completed = false;
        var goNext = function () {
            if (completed) return;
            completed = true;
            if (maxWaitTimer) {
                clearTimeout(maxWaitTimer);
            }
            window.location.assign(redirectUrl);
        };

        var waitForLottie = function (attempt) {
            if (window.lottie && typeof window.lottie.loadAnimation === 'function') {
                var animation = window.lottie.loadAnimation({
                    container: animationContainer,
                    renderer: 'svg',
                    loop: false,
                    autoplay: true,
                    path: 'loginsuccess.json'
                });

                maxWaitTimer = window.setTimeout(goNext, 10000);
                animation.addEventListener('complete', goNext);
                return;
            }

            if (attempt >= 40) {
                window.setTimeout(goNext, 900);
                return;
            }

            window.setTimeout(function () {
                waitForLottie(attempt + 1);
            }, 50);
        };

        waitForLottie(0);
    })();
</script>

</body>
</html>