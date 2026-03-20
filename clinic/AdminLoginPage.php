<?php
/**
 * Admin Login Page
 */
$pageTitle = 'Dental Clinic Admin Login';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// If accessed via /{slug}/AdminLoginPage.php, bootstrap tenant context for branding + login scoping
$clinic_slug = isset($_GET['clinic_slug']) ? strtolower(trim((string) $_GET['clinic_slug'])) : '';
if ($clinic_slug !== '' && preg_match('/^[a-z0-9\-]+$/', $clinic_slug)) {
    $_GET['clinic_slug'] = $clinic_slug;
    require_once __DIR__ . '/tenant_bootstrap.php';
    // Ensure per-tenant customization loader sees tenant_id
    if (isset($currentTenantId) && $currentTenantId) {
        $_SESSION['public_tenant_id'] = $currentTenantId;
        $_SESSION['public_tenant_slug'] = $clinic_slug;
    }
}

require_once __DIR__ . '/includes/clinic_customization.php';
$loginLogo = isset($CLINIC['logo']) ? trim($CLINIC['logo']) : 'DRCGLogo.png';
$loginLogoUrl = (strpos($loginLogo, 'http') === 0) ? $loginLogo : (BASE_URL . ltrim($loginLogo, '/'));
$loginLogoAlt = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Dental Clinic';

$adminLoginUrl = clinicPageUrl('AdminLoginPage.php');
$adminCreateAccountUrl = clinicPageUrl('AdminCreateAccount.php');
$adminDashboardUrl = clinicPageUrl('AdminDashboard.php');
$dentistDashboardUrl = clinicPageUrl('Dentist_Dashboard.php');
$staffDashboardUrl = clinicPageUrl('Staff_Dashboard.php');

// MyDental SSO: validate from session (set by ProviderMyDentalSSO) or one-time token file
$ssoData = null;
$ttl = 120; // seconds

if (!empty($_GET['mydental_sso']) && !empty($_SESSION['mydental_sso_data'])) {
    $data = $_SESSION['mydental_sso_data'];
    if (!empty($data['email']) && isset($data['created']) && (time() - (int)$data['created']) <= $ttl) {
        $ssoData = $data;
    }
    unset($_SESSION['mydental_sso_data']);
} elseif (!empty($_GET['mydental_token'])) {
    $token = trim($_GET['mydental_token']);
    $projectRoot = dirname(ROOT_PATH);
    $tokenFile = $projectRoot . '/uploads/mydental_sso/' . $token . '.json';
    if (strlen($token) === 64 && ctype_xdigit($token) && file_exists($tokenFile)) {
        $data = @json_decode(file_get_contents($tokenFile), true);
        @unlink($tokenFile);
        if ($data && !empty($data['email']) && isset($data['created']) && (time() - (int)$data['created']) <= $ttl) {
            $ssoData = $data;
        }
    }
}

if ($ssoData !== null) {
    try {
        require_once __DIR__ . '/config/database.php';
        $pdo = getDBConnection();
        $email = $ssoData['email'];
        $user = null;
        $sessionId = null;
        $sessionName = null;
        $sessionType = null;

        // 1) Try clinic tbl_users (schema: user_id, role; role dentist→doctor, tenant_owner→manager)
        try {
            $stmt = $pdo->prepare("SELECT u.user_id, u.email, u.username, u.full_name, u.role FROM tbl_users u WHERE u.email = ? AND u.role IN ('manager', 'dentist', 'staff', 'tenant_owner') AND u.status = 'active' LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $sessionId = $user['user_id'];
                $stmt2 = $pdo->prepare("SELECT first_name, last_name FROM tbl_staffs WHERE user_id = ? LIMIT 1");
                $stmt2->execute([$user['user_id']]);
                $profile = $stmt2->fetch(PDO::FETCH_ASSOC);
                $sessionName = $profile ? trim((isset($profile['first_name']) ? $profile['first_name'] : '') . ' ' . (isset($profile['last_name']) ? $profile['last_name'] : '')) : ($user['full_name'] ?? $user['username']);
                $sessionType = ($user['role'] === 'dentist') ? 'doctor' : (($user['role'] === 'tenant_owner') ? 'manager' : $user['role']);
            }
        } catch (PDOException $e) {
            try {
                $stmt = $pdo->prepare("SELECT u.user_id, u.email, u.username, u.full_name, u.role FROM tbl_users u WHERE u.email = ? AND u.role IN ('manager', 'dentist', 'staff', 'tenant_owner') LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $sessionId = $user['user_id'];
                    $stmt2 = $pdo->prepare("SELECT first_name, last_name FROM tbl_staffs WHERE user_id = ? LIMIT 1");
                    $stmt2->execute([$user['user_id']]);
                    $profile = $stmt2->fetch(PDO::FETCH_ASSOC);
                    $sessionName = $profile ? trim((isset($profile['first_name']) ? $profile['first_name'] : '') . ' ' . (isset($profile['last_name']) ? $profile['last_name'] : '')) : ($user['full_name'] ?? $user['username']);
                    $sessionType = ($user['role'] === 'dentist') ? 'doctor' : (($user['role'] === 'tenant_owner') ? 'manager' : $user['role']);
                }
            } catch (PDOException $e2) {
                $user = null;
            }
        }

        // 2) Fallback: provider `tbl_users` (tenant_owner, manager, staff, dentist) – allow tenant_owner to log in as manager
        if ($user === null || $sessionId === null) {
            try {
                $stmt = $pdo->prepare("SELECT user_id, full_name, email, role FROM tbl_users WHERE email = ? AND role IN ('tenant_owner', 'manager', 'staff', 'dentist') AND status = 'active' LIMIT 1");
                $stmt->execute([$email]);
                $tblUser = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($tblUser) {
                    $sessionId = $tblUser['user_id'];
                    $sessionName = isset($tblUser['full_name']) ? $tblUser['full_name'] : $email;
                    $sessionType = ($tblUser['role'] === 'tenant_owner' || $tblUser['role'] === 'manager') ? 'manager' : (($tblUser['role'] === 'dentist') ? 'doctor' : 'staff');
                }
            } catch (PDOException $e) {
                try {
                    $stmt = $pdo->prepare("SELECT user_id, full_name, email, role FROM tbl_users WHERE email = ? AND role IN ('tenant_owner', 'manager', 'staff', 'dentist') LIMIT 1");
                    $stmt->execute([$email]);
                    $tblUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($tblUser) {
                        $sessionId = $tblUser['user_id'];
                        $sessionName = isset($tblUser['full_name']) ? $tblUser['full_name'] : $email;
                        $sessionType = ($tblUser['role'] === 'tenant_owner' || $tblUser['role'] === 'manager') ? 'manager' : (($tblUser['role'] === 'dentist') ? 'doctor' : 'staff');
                    }
                } catch (PDOException $e2) {
                    // ignore
                }
            }
        }

        if ($sessionId !== null && $sessionType !== null) {
            $_SESSION['user_id'] = $sessionId;
            $_SESSION['user_name'] = $sessionName ?: $email;
            $_SESSION['user_type'] = $sessionType;
            $dest = ($sessionType === 'manager') ? 'AdminDashboard.php' : (($sessionType === 'doctor') ? 'Dentist_Dashboard.php' : 'Staff_Dashboard.php');
            header('Location: ' . clinicPageUrl($dest));
            exit;
        }
    } catch (Exception $e) {
        error_log('AdminLoginPage MyDental SSO: ' . $e->getMessage());
    }
    header('Location: ' . $adminLoginUrl . '?mydental_error=1');
    exit;
} elseif (!empty($_GET['mydental_token']) || (!empty($_GET['mydental_sso']) && empty($ssoData))) {
    header('Location: ' . $adminLoginUrl . '?mydental_error=1');
    exit;
}

// Check if already logged in as manager, doctor, or staff
if (isLoggedIn('manager')) {
    header("Location: " . $adminDashboardUrl);
    exit();
}
if (isLoggedIn('doctor')) {
    header("Location: " . $dentistDashboardUrl);
    exit();
}
if (isLoggedIn('staff')) {
    header("Location: " . $staffDashboardUrl);
    exit();
}
require_once __DIR__ . '/includes/header.php';
?>
<body class="font-sans bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-50 antialiased h-screen overflow-hidden selection:bg-primary/20 selection:text-primary">
<div class="flex min-h-screen w-full">
<div class="hidden lg:flex lg:w-1/2 relative flex-col justify-between p-16 bg-blue-600 text-white overflow-hidden">
<div class="absolute inset-0 z-0 w-full h-full bg-cover bg-center" style='background-image: url("https://lh3.googleusercontent.com/aida-public/AB6AXuC9j3qxtJxBWSRodAlKKYXWXyU6_3mn31f-yfmt0y_VepXhFXTo66WbTnVjwvBfY5ZbVoshh8Gl7jWDFG6vkF_4ymSKUgcfl6Hqvu29ys3DvcNh3Z0v4qg5-FtLGolqYafBwuDsKDTq850vObFAOt6Z3bQfRvCHyTuVlyTAQmAwFaqMRZmtNzcSzA7m1hf_RIv9bXqRKITWrB_nmfHuotAmBH3CN7RD9cWME3ZBHNWADYexWpY265XGaIRWWItajxnlTtsXUjn9oQA");'></div>
<div class="absolute inset-0 z-0 bg-gradient-to-br from-blue-700/90 via-blue-600/85 to-indigo-900/90 mix-blend-multiply"></div>
<div class="absolute inset-0 z-0 bg-black/10"></div>
<div class="relative z-10">
<div class="flex items-center">
<img src="<?php echo $loginLogoUrl; ?>" alt="<?php echo $loginLogoAlt; ?>" class="h-16 object-contain"/>
</div>
</div>
<div class="relative z-10 max-w-xl">
<h2 class="font-display text-4xl lg:text-5xl font-bold leading-tight tracking-tight mb-6 text-white drop-shadow-sm">
                Excellence in <br/>Dental Management
            </h2>
<p class="text-blue-50 text-lg font-light leading-relaxed mb-8 opacity-90 border-l-2 border-white/30 pl-6">
                "Secure, efficient, and designed for professionals. Manage patient care with the tools you trust."
            </p>
<div class="flex items-center gap-3 text-sm font-medium text-blue-100/80 bg-black/20 w-fit px-4 py-2 rounded-full backdrop-blur-md border border-white/10">
<span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                System Operational v4.2.0
            </div>
</div>
</div>
<div class="w-full lg:w-1/2 flex flex-col items-center justify-center p-6 sm:p-12 lg:p-24 bg-white dark:bg-slate-900 relative overflow-y-auto">
<div class="lg:hidden absolute top-8 left-8 flex items-center">
<img src="<?php echo $loginLogoUrl; ?>" alt="<?php echo $loginLogoAlt; ?>" class="h-12 object-contain"/>
</div>
<div class="w-full max-w-md flex flex-col gap-8">
<div class="flex flex-col gap-2">
<a href="<?php echo BASE_URL; ?>ProviderMyDentalSSO.php" class="inline-flex items-center gap-2 text-primary text-sm font-semibold mb-1 hover:text-primary-dark transition-colors">
<span class="material-symbols-outlined text-[18px]">login</span>
<span>Login using MyDental</span>
</a>
<h1 class="text-slate-900 dark:text-white font-display text-3xl font-bold tracking-tight">Admin & Doctor Portal</h1>
<p class="text-slate-500 dark:text-slate-400 text-base">Enter your credentials to access the secure dashboard.</p>
</div>
<?php if (!empty($_GET['mydental_error'])): ?>
<div id="mydentalErrorMessage" class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl mb-4">
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-[20px]">info</span>
<div><p class="text-sm font-semibold text-amber-800 dark:text-amber-300">MyDental login</p><p class="text-sm text-amber-700 dark:text-amber-400 mt-1">Link expired or no clinic admin account with your MyDental email. Sign in with your clinic credentials below or create an admin account with the same email.</p></div>
</div>
</div>
<?php endif; ?>
<div id="errorMessage" class="hidden"></div>
<div id="successMessage" class="hidden"></div>
<form id="loginForm" class="flex flex-col gap-5" action="<?php echo BASE_URL; ?>api/login.php" method="POST">
<div class="space-y-1.5">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold" for="email">Username or Email</label>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">person</span>
</div>
<input autocomplete="username" class="form-input block w-full pl-11 pr-4 py-3 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder:text-slate-400 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white dark:focus:bg-slate-800 transition-all shadow-input" id="email" name="email" placeholder="username or email@example.com" type="text" required/>
</div>
</div>
<div class="space-y-1.5">
<label class="text-slate-700 dark:text-slate-300 text-sm font-semibold" for="password">Password</label>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">lock</span>
</div>
<input class="form-input block w-full pl-11 pr-11 py-3 rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-900 dark:text-white placeholder:text-slate-400 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:bg-white dark:focus:bg-slate-800 transition-all shadow-input" id="password" name="password" placeholder="••••••••" type="password" required/>
<div class="absolute inset-y-0 right-0 pr-3.5 flex items-center cursor-pointer text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 transition-colors" id="togglePassword">
<span class="material-symbols-outlined text-[20px]">visibility</span>
</div>
</div>
</div>
<div class="flex items-center justify-between mt-1">
<label class="flex items-center gap-2 cursor-pointer group">
<input class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary focus:ring-offset-0 cursor-pointer transition-colors" type="checkbox" name="remember"/>
<span class="text-sm font-medium text-slate-500 group-hover:text-slate-700 dark:text-slate-400 dark:group-hover:text-slate-300 transition-colors">Remember me</span>
</label>
<a id="forgotPasswordLink" class="text-sm font-semibold text-primary hover:text-primary-dark transition-colors cursor-pointer" href="#">Forgot password?</a>
</div>
<button class="flex w-full cursor-pointer items-center justify-center overflow-hidden rounded-lg h-12 bg-primary hover:bg-primary-dark text-white text-base font-semibold tracking-wide transition-all shadow-lg shadow-primary/20 active:scale-[0.99] active:shadow-none mt-2" type="submit">
                    Sign In
                </button>
</form>
<div class="flex flex-col items-center gap-6 mt-6">
<div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 text-xs font-medium bg-emerald-50 dark:bg-emerald-900/20 py-1.5 px-3 rounded-full border border-emerald-100 dark:border-emerald-900/30">
<span class="material-symbols-outlined text-[14px]">encrypted</span>
<span>256-bit SSL Encrypted Connection</span>
</div>
<div class="text-center space-y-2">
<p class="text-sm text-slate-500 dark:text-slate-400">
                        Need access or having trouble? <br/>
<a class="font-semibold text-slate-800 dark:text-white hover:text-primary transition-colors cursor-pointer" href="<?php echo BASE_URL; ?>ContactUsClient.php">Contact IT Support</a>
</p>
</div>
<div class="w-full border-t border-slate-100 dark:border-slate-800 pt-6 mt-2">
<div class="text-center">
<p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
    Need to create a new admin account?
</p>
<a class="inline-flex items-center justify-center gap-2 text-sm font-semibold text-primary hover:text-primary-dark transition-colors" href="<?php echo htmlspecialchars($adminCreateAccountUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <span class="material-symbols-outlined text-[18px]">person_add</span>
    Create Admin Account
</a>
</div>
<div class="text-center pt-6 mt-2">
<p class="text-xs text-slate-400 dark:text-slate-600">© 2026 Dr. Romarico C. Gonzales Dental Clinic Management System. <br>All rights reserved.</p>
</div>
</div>
</div>
</div>
</div>

<!-- Forgot / Reset Password Modal -->
<div id="forgotPasswordModal" class="hidden fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4">
<div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl max-w-md w-full p-6 relative">
<button id="closeForgotPasswordModal" type="button" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
<span class="material-symbols-outlined">close</span>
</button>
<h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Reset Password</h3>
<p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Enter the email on your account. We'll send an OTP to verify.</p>

<div id="forgotStep1" class="space-y-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="forgotEmail">Email Address</label>
<input id="forgotEmail" type="email" class="form-input block w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-primary focus:border-primary" placeholder="your@email.com" required/>
</div>
<button id="sendOtpBtn" type="button" class="w-full rounded-lg px-6 py-2.5 bg-primary hover:bg-primary-dark text-white font-bold transition-all">Send OTP</button>
</div>

<div id="forgotStep2" class="hidden space-y-4">
<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-2">
<p class="text-sm text-blue-900 dark:text-blue-200">
<span class="material-symbols-outlined align-middle text-base">info</span>
<span class="ml-2">If that email exists, we sent an OTP. Check your inbox.</span>
</p>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="forgotOtp">Verification Code</label>
<input id="forgotOtp" type="text" maxlength="6" class="form-input block w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-primary focus:border-primary text-center text-2xl tracking-widest font-mono" placeholder="000000" required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="forgotNewPass">New Password</label>
<input id="forgotNewPass" type="password" class="form-input block w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-primary focus:border-primary" placeholder="••••••••" required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300" for="forgotConfirmPass">Confirm New Password</label>
<input id="forgotConfirmPass" type="password" class="form-input block w-full rounded-lg border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-primary focus:border-primary" placeholder="••••••••" required/>
</div>
<button id="submitResetBtn" type="button" class="w-full rounded-lg px-6 py-2.5 bg-primary hover:bg-primary-dark text-white font-bold transition-all">Reset Password</button>
<button id="forgotBackBtn" type="button" class="w-full rounded-lg px-6 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white font-bold hover:opacity-90 transition-all">Back</button>
</div>
</div>
</div>

<script>
    // Show success message when redirected after reset
    (function() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('reset') === 'success') {
            const el = document.getElementById('successMessage');
            const errEl = document.getElementById('errorMessage');
            if (errEl) errEl.classList.add('hidden');
            if (el) {
                el.className = 'p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl';
                el.innerHTML = '<div class="flex items-start gap-3"><span class="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-[20px]">check_circle</span><div><p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Password reset successful</p><p class="text-sm text-emerald-700 dark:text-emerald-400 mt-1">You can now sign in with your new password.</p></div></div>';
                el.classList.remove('hidden');
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            try { history.replaceState(null, '', window.location.pathname); } catch (e) {}
        }
    })();

    // Handle form submission via AJAX
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const errorDiv = document.getElementById('errorMessage');
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Remove existing error
        errorDiv.classList.add('hidden');
        errorDiv.innerHTML = '';
        
        // Validation
        if (!email || !password) {
            showError('Please enter both email/username and password.');
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="flex items-center gap-2"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Signing in...</span>';
        
        // Try to login - first try as manager, then as doctor if that fails
        async function attemptLogin(userType) {
            const response = await fetch('<?php echo BASE_URL; ?>api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    password: password,
                    user_type: userType,
                    clinic_slug: '<?php echo addslashes($clinic_slug); ?>' || undefined
                })
            });
            
            if (!response.ok) {
                return { error: 'HTTP error! status: ' + response.status };
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                const text = await response.text();
                return { error: 'Server returned non-JSON response: ' + text.substring(0, 100) };
            }
        }
        
        // Try manager first, then doctor, then staff
        attemptLogin('manager').then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            if (data.success) {
                return data;
            } else {
                // If manager login failed, try doctor
                return attemptLogin('doctor');
            }
        }).then(data => {
            if (data && data.error) {
                throw new Error(data.error);
            }
            
            if (data && data.success) {
                return data;
            } else {
                // If doctor login also failed, try staff
                return attemptLogin('staff');
            }
        }).then(data => {
            if (data && data.error) {
                throw new Error(data.error);
            }
            
            console.log('Login response:', data); // Debug log
            
            if (!data) {
                showError('No response from server. Please check your connection.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                return;
            }
            
            if (data.success) {
                // Check user type - the response structure is: data.data.user.type
                const userType = data.data?.user?.type;
                console.log('User type from response:', userType);
                
                if (userType === 'manager') {
                    // Show success message, then redirect to admin dashboard
                    showSuccess('Login successful! Redirecting to admin dashboard...');
                    setTimeout(() => {
                        window.location.href = '<?php echo addslashes($adminDashboardUrl); ?>';
                    }, 1500);
                } else if (userType === 'doctor') {
                    // Show success message, then redirect to dentist dashboard
                    showSuccess('Login successful! Redirecting to dentist dashboard...');
                    setTimeout(() => {
                        window.location.href = '<?php echo addslashes($dentistDashboardUrl); ?>';
                    }, 1500);
                } else if (userType === 'staff') {
                    // Show success message, then redirect to staff dashboard
                    showSuccess('Login successful! Redirecting to staff dashboard...');
                    setTimeout(() => {
                        window.location.href = '<?php echo addslashes($staffDashboardUrl); ?>';
                    }, 1500);
                } else {
                    // Not an admin, doctor, or staff, show error
                    showError('Access denied. Admin, Doctor, or Staff credentials required. (User type: ' + (userType || 'unknown') + ')');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } else {
                // Login failed for all types
                showError(data.message || 'Invalid credentials. Please check your email/username and password and try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
                document.getElementById('password').value = '';
                document.getElementById('password').focus();
            }
        })
        .catch(error => {
            console.error('Login Error:', error);
            // Check if error is a JSON parse error
            let errorMessage = error.message;
            if (error.message.includes('JSON') || error.message.includes('Unexpected token')) {
                errorMessage = 'Server connection error. Please check your database configuration and try again.';
            }
            showError('An error occurred: ' + errorMessage + '. Please check the browser console (F12) for details.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });

    // Show error message
    function showError(message) {
        const errorDiv = document.getElementById('errorMessage');
        const successEl = document.getElementById('successMessage');
        if (successEl) successEl.classList.add('hidden');
        errorDiv.className = 'mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl';
        errorDiv.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-[20px]">error</span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-300">Access Denied</p>
                    <p class="text-sm text-red-700 dark:text-red-400 mt-1">${message}</p>
                </div>
            </div>
        `;
        errorDiv.classList.remove('hidden');
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Show success message
    function showSuccess(message) {
        const successDiv = document.getElementById('successMessage');
        const errorEl = document.getElementById('errorMessage');
        if (errorEl) errorEl.classList.add('hidden');
        successDiv.className = 'mt-4 p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl';
        successDiv.innerHTML = `
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-emerald-600 dark:text-emerald-400 text-[20px]">check_circle</span>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-300">Login Successful</p>
                    <p class="text-sm text-emerald-700 dark:text-emerald-400 mt-1">${message}</p>
                </div>
            </div>
        `;
        successDiv.classList.remove('hidden');
        successDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Password visibility toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = this.querySelector('span');
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            passwordInput.type = 'password';
            icon.textContent = 'visibility';
        }
    });

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
    const adminLoginUrl = '<?php echo addslashes($adminLoginUrl); ?>';
    const apiBase = '<?php echo BASE_URL; ?>api/reset_password.php';

    if (forgotLink) forgotLink.addEventListener('click', function(e) { e.preventDefault(); if (forgotModal) { forgotModal.classList.remove('hidden'); forgotStep1.classList.remove('hidden'); forgotStep2.classList.add('hidden'); forgotEmail.value = ''; forgotOtp.value = ''; forgotNewPass.value = ''; forgotConfirmPass.value = ''; } });
    if (closeForgotBtn) closeForgotBtn.addEventListener('click', function() { if (forgotModal) forgotModal.classList.add('hidden'); });
    if (forgotModal) forgotModal.addEventListener('click', function(e) { if (e.target === forgotModal) forgotModal.classList.add('hidden'); });

    if (forgotOtp) forgotOtp.addEventListener('input', function(e) { e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6); });

    if (sendOtpBtn) {
        sendOtpBtn.addEventListener('click', async function() {
            const email = forgotEmail.value.trim();
            if (!email) { alert('Please enter your email address.'); return; }
            sendOtpBtn.disabled = true;
            sendOtpBtn.textContent = 'Sending...';
            try {
                const res = await fetch(apiBase, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'request', email: email }) });
                const data = await res.json();
                if (data.success) {
                    forgotStep1.classList.add('hidden');
                    forgotStep2.classList.remove('hidden');
                } else {
                    alert(data.message || 'If that email exists, we sent an OTP.');
                }
            } catch (err) { alert('An error occurred. Please try again.'); }
            finally { sendOtpBtn.disabled = false; sendOtpBtn.textContent = 'Send OTP'; }
        });
    }

    if (submitResetBtn) {
        submitResetBtn.addEventListener('click', async function() {
            const email = forgotEmail.value.trim();
            const code = forgotOtp.value.trim();
            const newP = forgotNewPass.value;
            const confirmP = forgotConfirmPass.value;
            if (!code || code.length !== 6) { alert('Please enter a valid 6-digit verification code.'); return; }
            if (newP.length < 8) { alert('Password must be at least 8 characters.'); return; }
            if (newP !== confirmP) { alert('New password and confirm password do not match.'); return; }
            submitResetBtn.disabled = true;
            submitResetBtn.textContent = 'Resetting...';
            try {
                const res = await fetch(apiBase, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'reset', email: email, code: code, new_password: newP }) });
                const data = await res.json();
                if (data.success) {
                    forgotModal.classList.add('hidden');
                    window.location.href = adminLoginUrl + '?reset=success';
                    return;
                }
                alert(data.message || 'Failed to reset password. Please try again.');
            } catch (err) { alert('An error occurred. Please try again.'); }
            finally { submitResetBtn.disabled = false; submitResetBtn.textContent = 'Reset Password'; }
        });
    }

    if (forgotBackBtn) {
        forgotBackBtn.addEventListener('click', function() {
            forgotStep1.classList.remove('hidden');
            forgotStep2.classList.add('hidden');
            forgotOtp.value = ''; forgotNewPass.value = ''; forgotConfirmPass.value = '';
        });
    }

</script>
