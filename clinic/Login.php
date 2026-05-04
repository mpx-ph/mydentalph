<?php
/**
 * Client Login Page
 */
$pageTitle = 'Sign In';
require_once __DIR__ . '/config/config.php';

// Establish tenant context when opened via slug (e.g. /{slug}/login)
$clinic_slug = isset($_GET['clinic_slug']) ? strtolower(trim((string) $_GET['clinic_slug'])) : '';
if ($clinic_slug !== '' && !preg_match('/^[a-z0-9\-]+$/', $clinic_slug)) {
    $clinic_slug = '';
}
if ($clinic_slug !== '') {
    $_GET['clinic_slug'] = $clinic_slug;
}

// Provider SSO stores payload on the default PHP session; slug pages use MDCLS{slug}. Copy once before opening clinic session.
$__mydental_sso_bridge = null;
if (!empty($_GET['mydental_sso']) && $clinic_slug !== '') {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $GLOBALS['_clinic_session_scope_configured'] = false;
    session_name('PHPSESSID');
    session_set_cookie_params(0, '/', '', $secure, true);
    session_start();
    if (!empty($_SESSION['mydental_sso_data']) && is_array($_SESSION['mydental_sso_data'])) {
        $__mydental_sso_bridge = $_SESSION['mydental_sso_data'];
    }
    session_write_close();
    $GLOBALS['_clinic_session_scope_configured'] = false;
}

if ($clinic_slug !== '') {
    require_once __DIR__ . '/tenant_bootstrap.php';
    if ($__mydental_sso_bridge !== null) {
        $_SESSION['mydental_sso_data'] = $__mydental_sso_bridge;
    }
}

// Load customization so logos/text can be tenant-aware (falls back to defaults)
require_once __DIR__ . '/includes/clinic_customization.php';

$loginHex = function (string $k) use ($CLINIC): string {
    $v = isset($CLINIC[$k]) ? trim((string) $CLINIC[$k]) : '';
    $v = preg_replace('/^#/', '', $v);
    if (strlen($v) === 6 && ctype_xdigit($v)) {
        return '#' . $v;
    }
    return '';
};
$cPrimary = $loginHex('color_primary') ?: '#2b8cee';
$cPrimaryDark = $loginHex('color_primary_dark') ?: '#1a6cb6';
$cPrimaryLight = $loginHex('color_primary_light') ?: '#eef7ff';
$h = ltrim($cPrimary, '#');
if (strlen($h) !== 6 || !ctype_xdigit($h)) {
    $h = '2b8cee';
}
$loginPrimaryR = hexdec(substr($h, 0, 2));
$loginPrimaryG = hexdec(substr($h, 2, 2));
$loginPrimaryB = hexdec(substr($h, 4, 2));

require_once __DIR__ . '/includes/auth.php';

// Ensure we can resolve tenant context even when `clinic_slug` isn't in the query string
// (e.g. navigation/redirects that keep session tenant_slug but drop URL params).
$clinicSlugForFetch = $clinic_slug !== ''
    ? strtolower((string) $clinic_slug)
    : (isset($_SESSION['public_tenant_slug']) ? strtolower(trim((string) $_SESSION['public_tenant_slug'])) : '');
if ($clinicSlugForFetch !== '' && !preg_match('/^[a-z0-9\-]+$/', $clinicSlugForFetch)) {
    $clinicSlugForFetch = '';
}

$slugLower = strtolower($clinicSlugForFetch);
// Redirect URL after login: back to MainPageClient with same clinic (tenant) context
$redirectAfterLogin = ($clinicSlugForFetch !== '') ? (BASE_URL . 'MainPageClient.php?clinic_slug=' . rawurlencode($clinicSlugForFetch)) : (BASE_URL . 'MainPageClient.php');
// Staff portal (slug-based URL when rewrite rules are active)
$staffRedirectAfterLogin = ($clinicSlugForFetch !== '' && preg_match('/^[a-z0-9\-]+$/', $slugLower))
    ? (rtrim(PROVIDER_BASE_URL, '/') . '/' . rawurlencode($slugLower) . '/StaffDashboard.php')
    : (($clinicSlugForFetch !== '') ? (BASE_URL . 'StaffDashboard.php?clinic_slug=' . rawurlencode($clinicSlugForFetch)) : (BASE_URL . 'StaffDashboard.php'));

// MyDental SSO compatibility (migrated from AdminLoginPage.php).
$ssoData = null;
$ttl = 120;
if (!empty($_GET['mydental_sso']) && !empty($_SESSION['mydental_sso_data'])) {
    $candidate = $_SESSION['mydental_sso_data'];
    if (!empty($candidate['email']) && isset($candidate['created']) && (time() - (int) $candidate['created']) <= $ttl) {
        $ssoData = $candidate;
    }
    unset($_SESSION['mydental_sso_data']);
}
if ($ssoData !== null) {
    try {
        $pdo = getDBConnection();
        $email = trim((string) ($ssoData['email'] ?? ''));
        $tenantId = trim((string) ($ssoData['tenant_id'] ?? ''));
        if ($email !== '' && $tenantId !== '') {
            $tenantStmt = $pdo->prepare("SELECT clinic_slug FROM tbl_tenants WHERE tenant_id = ? LIMIT 1");
            $tenantStmt->execute([$tenantId]);
            $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC);
            $tenantSlug = isset($tenantRow['clinic_slug']) ? strtolower(trim((string) $tenantRow['clinic_slug'])) : '';

            $_SESSION['tenant_id'] = $tenantId;
            if ($tenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $tenantSlug)) {
                $_SESSION['tenant_slug'] = $tenantSlug;
                $_SESSION['public_tenant_slug'] = $tenantSlug;
            }
            $_SESSION['public_tenant_id'] = $tenantId;

            $stmt = $pdo->prepare("
                SELECT user_id, email, username, full_name, role
                FROM tbl_users
                WHERE LOWER(TRIM(COALESCE(email, ''))) = LOWER(TRIM(?))
                  AND tenant_id = ?
                  AND role IN ('manager', 'dentist', 'staff', 'tenant_owner')
                  AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$email, $tenantId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $sessionType = _authRoleToUserType((string) ($user['role'] ?? ''));
                if (in_array($sessionType, ['manager', 'doctor', 'staff', 'admin'], true)) {
                    $_SESSION['user_id'] = (string) $user['user_id'];
                    $_SESSION['user_name'] = trim((string) ($user['full_name'] ?? $user['username'] ?? $email));
                    $_SESSION['user_email'] = (string) ($user['email'] ?? $email);
                    $_SESSION['user_type'] = $sessionType;
                    $_SESSION['user_role'] = (string) ($user['role'] ?? '');
                    $_SESSION['clinic_id'] = $tenantId;
                    $_SESSION['account_kind'] = 'staff';

                    auth_update_user_last_activity($pdo, (string) $user['user_id']);
                    header('Location: ' . clinicPageUrl('StaffDashboard.php'));
                    exit;
                }
            }
        }
        header('Location: ' . $loginPageUrl . '?mydental_error=1');
        exit;
    } catch (Throwable $e) {
        error_log('Login.php MyDental SSO: ' . $e->getMessage());
        header('Location: ' . $loginPageUrl . '?mydental_error=1');
        exit;
    }
}

// URL for "Create new account" respecting clinic slug routing
// .htaccess maps /{slug}/register -> clinic/RegisterClient.php?clinic_slug={slug}
// For slug-based access (e.g. mydental.ct.ws/{slug}/login), we must build URLs from the domain root,
// not from /clinic, so we use PROVIDER_BASE_URL here.
$registerClientUrl = ($clinic_slug !== '')
    ? (PROVIDER_BASE_URL . rawurlencode(strtolower($clinic_slug)) . '/register')
    : (BASE_URL . 'RegisterClient.php');

// Redirect if already logged in (patient vs staff)
if (isLoggedIn('client')) {
    header('Location: ' . $redirectAfterLogin);
    exit;
}
if (isLoggedIn('staff')) {
    header('Location: ' . $staffRedirectAfterLogin);
    exit;
}
if (isLoggedIn(['manager', 'doctor', 'admin'])) {
    header('Location: ' . $staffRedirectAfterLogin);
    exit;
}

$loginLogo = isset($CLINIC['logo_nav']) ? trim($CLINIC['logo_nav']) : 'DRCGLogo2.png';
$loginLogoUrl = (strpos($loginLogo, 'http') === 0) ? $loginLogo : (BASE_URL . ltrim($loginLogo, '/'));
$loginLogoLocalPath = (strpos($loginLogo, 'http') === 0) ? null : (defined('ROOT_PATH') ? (ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($loginLogo, '/\\'))) : null);
if (strpos($loginLogoUrl, '?') === false && $loginLogoLocalPath && is_file($loginLogoLocalPath)) {
    $loginLogoUrl .= '?v=' . @filemtime($loginLogoLocalPath);
}
$loginLogoAlt = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Dental Clinic';
$publicHomeUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/') : (BASE_URL . 'MainPageClient.php');
$publicServicesUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/services') : (BASE_URL . 'PatientServices.php');
$publicAboutUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/about') : (BASE_URL . 'AboutUsClient.php');
$publicContactUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/contact') : (BASE_URL . 'ContactUsClient.php');
$loginPageUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/login') : (BASE_URL . 'Login.php');
?>
<!DOCTYPE html>
<html class="scroll-smooth light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "primary": "<?php echo htmlspecialchars($cPrimary, ENT_QUOTES, 'UTF-8'); ?>",
              "primary-dark": "<?php echo htmlspecialchars($cPrimaryDark, ENT_QUOTES, 'UTF-8'); ?>",
              "primary-light": "<?php echo htmlspecialchars($cPrimaryLight, ENT_QUOTES, 'UTF-8'); ?>",
              "on-surface": "#131c25",
              "surface": "#ffffff",
              "surface-variant": "#f7f9ff",
              "on-surface-variant": "#404752",
              "outline-variant": "#c0c7d4",
              "primary-fixed": "#d4e3ff",
              "on-primary-fixed-variant": "#004883",
              "surface-container-low": "<?php echo htmlspecialchars($cPrimaryLight, ENT_QUOTES, 'UTF-8'); ?>",
              "inverse-surface": "#131c25",
            },
            fontFamily: {
              "headline": ["Manrope", "sans-serif"],
              "body": ["Inter", "sans-serif"],
              "editorial": ["Playfair Display", "serif"]
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "3xl": "2.5rem", "full": "9999px"},
          },
        },
      }
    </script>
    <style>
        .mesh-gradient {
            background-color: #ffffff;
            background-image:
                radial-gradient(at 100% 0%, rgba(<?php echo (int) $loginPrimaryR; ?>, <?php echo (int) $loginPrimaryG; ?>, <?php echo (int) $loginPrimaryB; ?>, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(<?php echo (int) $loginPrimaryR; ?>, <?php echo (int) $loginPrimaryG; ?>, <?php echo (int) $loginPrimaryB; ?>, 0.05) 0px, transparent 50%);
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(<?php echo (int) $loginPrimaryR; ?>, <?php echo (int) $loginPrimaryG; ?>, <?php echo (int) $loginPrimaryB; ?>, 0.1);
            letter-spacing: -0.02em;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 40px 80px -20px rgba(<?php echo (int) $loginPrimaryR; ?>, <?php echo (int) $loginPrimaryG; ?>, <?php echo (int) $loginPrimaryB; ?>, 0.08);
        }
        .reveal {
            opacity: 0;
            transform: translateY(28px) scale(0.985);
            filter: blur(10px);
            transition: opacity 820ms cubic-bezier(0.22, 1, 0.36, 1), transform 820ms cubic-bezier(0.22, 1, 0.36, 1), filter 820ms cubic-bezier(0.22, 1, 0.36, 1);
        }
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
        }
        @keyframes popIn {
            0% { transform: translateY(10px) scale(0.985); opacity: 0; }
            60% { transform: translateY(-2px) scale(1.01); opacity: 1; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        .pop-up { animation: popIn 620ms cubic-bezier(0.22, 1, 0.36, 1) both; }
        .login-success-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.72);
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
            pointer-events: all;
        }
        .login-success-overlay.is-active { display: flex; }
        .login-success-panel {
            width: min(88vw, 440px);
            height: min(88vw, 440px);
            display: grid;
            place-items: center;
        }
        .login-success-animation { width: 100%; height: 100%; }
    </style>
</head>
<body class="mesh-gradient min-h-screen flex flex-col items-center selection:bg-primary/20 text-on-surface font-body">
<?php include __DIR__ . '/includes/nav_client.php'; ?>
<main class="flex-grow flex items-center justify-center w-full px-4 sm:px-6 lg:px-8 relative pt-24 pb-12 reveal" data-reveal="section">
<div class="w-full max-w-lg">
<div class="login-card rounded-[2.5rem] overflow-hidden p-10 md:p-12 space-y-8 pop-up">
<div class="text-center space-y-4">
<h1 class="font-headline text-4xl sm:text-5xl font-extrabold tracking-tighter leading-[1.1] text-slate-900">
                    Sign <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">In</span>
</h1>
<p class="text-slate-600 font-medium text-base leading-relaxed max-w-sm mx-auto font-body">Patient or staff: use your clinic credentials. You will be routed to the right portal.</p>
</div>
<div id="errorMessage" class="hidden mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm"></div>
<div id="successMessage" class="hidden mb-4 p-3 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm"></div>
<form id="loginForm" action="#" class="space-y-8" onsubmit="event.preventDefault()">
<div class="space-y-6">
<div class="space-y-2.5">
<label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em] ml-1 font-headline" for="loginEmail">Email or Username</label>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-primary/40 text-xl font-light">person</span>
</div>
<input id="loginEmail" class="block w-full pl-12 pr-12 py-4 bg-surface-container-low/50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:outline-none text-slate-900 font-medium transition-all duration-200 placeholder:text-slate-400" placeholder="email@example.com or username" required="" type="text"/>
</div>
</div>
<div class="space-y-2.5">
<div class="flex justify-between items-center px-1">
<label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em] font-headline" for="loginPassword">Password</label>
<a id="forgotPasswordLink" class="text-[10px] font-black uppercase tracking-widest text-primary hover:opacity-70 transition-opacity cursor-pointer" href="#">Forgot Password?</a>
</div>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-primary/40 text-xl font-light">lock</span>
</div>
<input id="loginPassword" class="block w-full pl-12 pr-12 py-4 bg-surface-container-low/50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:outline-none text-slate-900 font-medium transition-all duration-200 placeholder:text-slate-400" placeholder="••••••••" required="" type="password"/>
<button type="button" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-primary transition-colors cursor-pointer bg-transparent border-0" id="passwordToggle" aria-label="Toggle password visibility">
<span class="material-symbols-outlined text-xl">visibility</span>
</button>
</div>
</div>
</div>
<button id="loginBtn" type="submit" class="w-full py-5 px-6 bg-primary text-white font-black text-sm uppercase tracking-[0.2em] rounded-2xl shadow-xl shadow-primary/20 hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] transition-all duration-200 font-headline">
                    Sign In securely
                </button>
</form>
<div class="pt-2 text-center">
<p class="text-sm font-semibold text-slate-600 font-body">
                    First time visiting?
                    <a class="font-black text-primary hover:underline underline-offset-4 transition-all" href="<?php echo htmlspecialchars($registerClientUrl, ENT_QUOTES, 'UTF-8'); ?>">Create new account</a>
</p>
</div>
</div>
<div class="mt-10 flex justify-center items-center space-x-10 opacity-40 hover:opacity-70 transition-all duration-500 text-slate-600">
<div class="flex items-center space-x-2">
<span class="material-symbols-outlined text-lg">verified_user</span>
<span class="text-[10px] uppercase font-black tracking-[0.2em] font-headline">Secure access</span>
</div>
<div class="flex items-center space-x-2">
<span class="material-symbols-outlined text-lg">lock_person</span>
<span class="text-[10px] uppercase font-black tracking-[0.2em] font-headline">Encrypted</span>
</div>
</div>
</div>
</main>
<footer class="w-full border-t border-slate-200 bg-slate-50/50 backdrop-blur-sm mt-auto reveal" data-reveal="section">
<div class="flex flex-col md:flex-row justify-between items-center py-12 px-10 max-w-screen-2xl mx-auto gap-8">
<a href="<?php echo htmlspecialchars($publicHomeUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-lg font-bold text-slate-900 font-headline flex items-center gap-2 no-underline text-inherit">
<img src="<?php echo htmlspecialchars($loginLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="h-8 w-auto object-contain"/>
</a>
<div class="flex flex-wrap justify-center gap-10 text-[10px] font-black uppercase tracking-widest text-slate-500 font-headline">
<a class="hover:text-primary transition-colors" href="#">Privacy Policy</a>
<a class="hover:text-primary transition-colors" href="#">Terms of Service</a>
<a class="hover:text-primary transition-colors" href="#">Help Center</a>
</div>
<div class="text-[10px] font-black uppercase tracking-widest text-slate-400 font-headline">
            © <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?>.
        </div>
</div>
</footer>
<div class="fixed top-[-10%] right-[-5%] w-[40rem] h-[40rem] bg-primary/5 rounded-full blur-[120px] -z-10 pointer-events-none"></div>
<div class="fixed bottom-[-10%] left-[-5%] w-[30rem] h-[30rem] bg-primary/5 rounded-full blur-[100px] -z-10 pointer-events-none"></div>

<div id="loginSuccessOverlay" class="login-success-overlay" aria-hidden="true">
<div class="login-success-panel" role="status" aria-live="polite" aria-label="Login successful">
<div id="loginSuccessAnimation" class="login-success-animation"></div>
</div>
</div>

<!-- Forgot / Reset Password Modal -->
<div id="forgotPasswordModal" class="hidden fixed inset-0 bg-black/50 z-[60] flex items-center justify-center p-4">
<div class="relative bg-white dark:bg-[#151f2b] rounded-2xl shadow-2xl max-w-md w-full overflow-hidden ring-1 ring-white/10 pop-up">
<div class="bg-slate-50 dark:bg-[#1a2634] px-8 py-5 border-b border-slate-100 dark:border-slate-700/50">
<h3 class="text-[#0d141b] dark:text-white text-lg font-bold">Reset Password</h3>
<p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Enter the email on your account. We'll send an OTP to verify.</p>
</div>
<div class="px-8 py-6">
<div id="forgotStep1" class="space-y-4">
<div class="flex flex-col gap-1.5">
<label class="text-[#0d141b] dark:text-slate-200 text-sm font-semibold">Email Address</label>
<input id="forgotEmail" type="email" class="w-full h-11 px-4 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-[#0d141b] dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="your@email.com" required/>
</div>
<button id="sendOtpBtn" type="button" class="w-full rounded-lg h-11 bg-primary hover:bg-primary-dark text-white text-base font-bold transition-all">Send OTP</button>
</div>
<div id="forgotStep2" class="hidden space-y-4">
<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-2">
<p class="text-sm text-blue-900 dark:text-blue-200">
<span class="material-symbols-outlined align-middle text-base">info</span>
<span class="ml-2">If that email exists, we sent an OTP. Check your inbox.</span>
</p>
</div>
<div class="flex flex-col gap-1.5">
<label class="text-[#0d141b] dark:text-slate-200 text-sm font-semibold">Verification Code</label>
<input id="forgotOtp" type="text" maxlength="6" class="w-full h-11 px-4 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-[#0d141b] dark:text-white text-center text-xl tracking-widest font-mono focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="000000" required/>
</div>
<div class="flex flex-col gap-1.5">
<label class="text-[#0d141b] dark:text-slate-200 text-sm font-semibold">New Password</label>
<input id="forgotNewPass" type="password" class="w-full h-11 px-4 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-[#0d141b] dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="••••••••" required/>
</div>
<div class="flex flex-col gap-1.5">
<label class="text-[#0d141b] dark:text-slate-200 text-sm font-semibold">Confirm New Password</label>
<input id="forgotConfirmPass" type="password" class="w-full h-11 px-4 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-[#0d141b] dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="••••••••" required/>
</div>
<button id="submitResetBtn" type="button" class="w-full rounded-lg h-11 bg-primary hover:bg-primary-dark text-white text-base font-bold transition-all">Reset Password</button>
<button id="forgotBackBtn" type="button" class="w-full rounded-lg h-11 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white text-base font-bold hover:opacity-90 transition-all">Back</button>
</div>
</div>
<button id="closeForgotPasswordModal" type="button" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
<span class="material-symbols-outlined">close</span>
</button>
</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
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
        }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' });
        elements.forEach(function (el) { observer.observe(el); });
    })();

    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const loginEmail = document.getElementById('loginEmail');
    const loginPassword = document.getElementById('loginPassword');
    const passwordToggle = document.getElementById('passwordToggle');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');

    function playLoginSuccessOverlayAndRedirect(redirectUrl) {
        if (!redirectUrl) return;

        var overlay = document.getElementById('loginSuccessOverlay');
        var animationContainer = document.getElementById('loginSuccessAnimation');
        if (!overlay || !animationContainer) {
            window.location.href = redirectUrl;
            return;
        }

        overlay.classList.add('is-active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        var finished = false;
        var maxWaitTimer = null;
        var goNext = function () {
            if (finished) return;
            finished = true;
            if (maxWaitTimer) clearTimeout(maxWaitTimer);
            window.location.href = redirectUrl;
        };

        var waitForLottie = function (attempt) {
            if (window.lottie && typeof window.lottie.loadAnimation === 'function') {
                var animation = window.lottie.loadAnimation({
                    container: animationContainer,
                    renderer: 'svg',
                    loop: false,
                    autoplay: true,
                    path: '../loginsuccess.json'
                });
                maxWaitTimer = window.setTimeout(goNext, 10000);
                animation.addEventListener('complete', goNext);
                return;
            }

            if (attempt >= 40) {
                window.setTimeout(goNext, 900);
                return;
            }
            window.setTimeout(function () { waitForLottie(attempt + 1); }, 50);
        };

        waitForLottie(0);
    }
    
    // Show success message if redirected from verification or password reset
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('verified') === '1') {
        showSuccess('Email verified successfully! You can now login.');
    }
    if (urlParams.get('reset') === 'success') {
        errorMessage.classList.add('hidden');
        showSuccess('Password reset successful. You can now sign in with your new password.');
        try { history.replaceState(null, '', window.location.pathname); } catch (e) {}
    }
    if (urlParams.get('mydental_error') === '1') {
        showError('MyDental auto-login was not completed. Please sign in with your clinic credentials.');
    }
    
    // Password visibility toggle
    if (passwordToggle) {
        passwordToggle.addEventListener('click', function() {
            const type = loginPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            loginPassword.setAttribute('type', type);
            const icon = passwordToggle.querySelector('span');
            icon.textContent = type === 'password' ? 'visibility' : 'visibility_off';
        });
    }
    
    function inferClinicSlugFromPath() {
        try {
            var path = String(window.location.pathname || '').replace(/\/+$/, '');
            if (!path) return '';
            // Supports slug route like /{slug}/login
            var match = path.match(/^\/([a-z0-9\-]+)\/login$/i);
            if (match && match[1]) {
                return String(match[1]).toLowerCase();
            }
        } catch (e) {}
        return '';
    }

    // Form submission handler
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = loginEmail.value.trim();
            const password = loginPassword.value;
            
            // Hide messages
            errorMessage.classList.add('hidden');
            successMessage.classList.add('hidden');
            
            // Validation
            if (!email || !password) {
                showError('Please enter both email/username and password.');
                return;
            }
            
            // Show loading state
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="flex items-center gap-2"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Signing in...</span>';
            
            try {
                const clinicSlug = <?php echo json_encode($clinicSlugForFetch, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
                const resolvedClinicSlug = clinicSlug || inferClinicSlugFromPath();
                const debugLogin = <?php echo (isset($_GET['debug']) && $_GET['debug'] === '1') ? 'true' : 'false'; ?>;
                const response = await fetch('<?php echo BASE_URL; ?>api/login.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        login: email.trim(),
                        email: email.trim(),
                        password: password,
                        user_type: 'portal',
                        clinic_slug: resolvedClinicSlug || undefined,
                        debug: debugLogin
                    })
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (jsonErr) {
                    throw new Error('Unexpected server response. Please refresh and try again.');
                }
                
                if (data.success) {
                    var nextUrl = (data.data && data.data.redirect_url) ? data.data.redirect_url : '<?php echo addslashes($redirectAfterLogin); ?>';
                    playLoginSuccessOverlayAndRedirect(nextUrl);
                } else {
                    // Check if user needs email verification
                    if (data.requires_verification && data.email) {
                        showError(data.message || 'Please verify your email first.');
                        // Add link to verification page
                        setTimeout(function() {
                            if (confirm('Would you like to go to the verification page?')) {
                                window.location.href = '<?php echo BASE_URL; ?>VerifyEmail.php?email=' + encodeURIComponent(data.email);
                            }
                        }, 2000);
                    } else {
                        const apiMessage = String(data.message || '');
                        if (apiMessage.toLowerCase().indexOf('tenant context missing') !== -1) {
                            showError('Clinic context expired. Please reopen the clinic login URL and try again.');
                        } else {
                            showError(apiMessage || 'Login failed. Please try again.');
                        }
                    }
                    loginBtn.disabled = false;
                    loginBtn.innerHTML = 'Sign In securely';
                }
            } catch (error) {
                showError('An error occurred. Please try again.');
                loginBtn.disabled = false;
                loginBtn.innerHTML = 'Sign In securely';
            }
        });
    }
    
    function showError(message) {
        successMessage.classList.add('hidden');
        errorMessage.textContent = message;
        errorMessage.classList.remove('hidden');
    }
    
    function showSuccess(message) {
        successMessage.textContent = message;
        successMessage.classList.remove('hidden');
    }

    // Forgot / Reset Password modal
    const forgotModal = document.getElementById('forgotPasswordModal');
    const forgotLink = document.getElementById('forgotPasswordLink');
    const closeForgotBtn = document.getElementById('closeForgotPasswordModal');
    const forgotStep1 = document.getElementById('forgotStep1');
    const forgotStep2 = document.getElementById('forgotStep2');
    const forgotEmail = document.getElementById('forgotEmail');
    const forgotOtp = document.getElementById('forgotOtp');
    const forgotNewPass = document.getElementById('forgotNewPass');
    const forgotConfirmPass = document.getElementById('forgotConfirmPass');
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const submitResetBtn = document.getElementById('submitResetBtn');
    const forgotBackBtn = document.getElementById('forgotBackBtn');
    const clientLoginUrl = '<?php echo BASE_URL; ?>LoginClient.php';
    const apiBase = '<?php echo BASE_URL; ?>api/reset_password.php';

    if (forgotLink) {
        forgotLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (forgotModal) {
                forgotModal.classList.remove('hidden');
                forgotStep1.classList.remove('hidden');
                forgotStep2.classList.add('hidden');
                forgotEmail.value = '';
                forgotOtp.value = '';
                forgotNewPass.value = '';
                forgotConfirmPass.value = '';
            }
        });
    }
    if (closeForgotBtn) {
        closeForgotBtn.addEventListener('click', function() {
            if (forgotModal) forgotModal.classList.add('hidden');
        });
    }
    if (forgotModal) {
        forgotModal.addEventListener('click', function(e) {
            if (e.target === forgotModal) forgotModal.classList.add('hidden');
        });
    }
    if (forgotOtp) {
        forgotOtp.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        });
    }
    if (sendOtpBtn) {
        sendOtpBtn.addEventListener('click', async function() {
            const email = forgotEmail.value.trim();
            if (!email) {
                alert('Please enter your email address.');
                return;
            }
            sendOtpBtn.disabled = true;
            sendOtpBtn.textContent = 'Sending...';
            try {
                const res = await fetch(apiBase, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'request', email: email })
                });
                const data = await res.json();
                if (data.success) {
                    forgotStep1.classList.add('hidden');
                    forgotStep2.classList.remove('hidden');
                } else {
                    alert(data.message || 'If that email exists, we sent an OTP.');
                }
            } catch (err) {
                alert('An error occurred. Please try again.');
            } finally {
                sendOtpBtn.disabled = false;
                sendOtpBtn.textContent = 'Send OTP';
            }
        });
    }
    if (submitResetBtn) {
        submitResetBtn.addEventListener('click', async function() {
            const email = forgotEmail.value.trim();
            const code = forgotOtp.value.trim();
            const newP = forgotNewPass.value;
            const confirmP = forgotConfirmPass.value;
            if (!code || code.length !== 6) {
                alert('Please enter a valid 6-digit verification code.');
                return;
            }
            if (newP.length < 8) {
                alert('Password must be at least 8 characters.');
                return;
            }
            if (newP !== confirmP) {
                alert('New password and confirm password do not match.');
                return;
            }
            submitResetBtn.disabled = true;
            submitResetBtn.textContent = 'Resetting...';
            try {
                const res = await fetch(apiBase, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset', email: email, code: code, new_password: newP })
                });
                const data = await res.json();
                if (data.success) {
                    forgotModal.classList.add('hidden');
                    window.location.href = clientLoginUrl + '?reset=success';
                    return;
                }
                alert(data.message || 'Failed to reset password. Please try again.');
            } catch (err) {
                alert('An error occurred. Please try again.');
            } finally {
                submitResetBtn.disabled = false;
                submitResetBtn.textContent = 'Reset Password';
            }
        });
    }
    if (forgotBackBtn) {
        forgotBackBtn.addEventListener('click', function() {
            forgotStep1.classList.remove('hidden');
            forgotStep2.classList.add('hidden');
            forgotOtp.value = '';
            forgotNewPass.value = '';
            forgotConfirmPass.value = '';
        });
    }
});
</script>
</body>
</html>

