<?php
/**
 * Client Login Page
 */
$pageTitle = 'Patient Login';
require_once __DIR__ . '/config/config.php';

// Establish tenant context when opened via slug (e.g. /{slug}/login)
$clinic_slug = isset($_GET['clinic_slug']) ? trim((string) $_GET['clinic_slug']) : '';
if ($clinic_slug !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($clinic_slug))) {
    $_GET['clinic_slug'] = strtolower($clinic_slug);
    require_once __DIR__ . '/tenant_bootstrap.php';
}

// Load customization so logos/text can be tenant-aware (falls back to defaults)
require_once __DIR__ . '/includes/clinic_customization.php';

require_once __DIR__ . '/includes/auth.php';

// Redirect URL after login: back to MainPageClient with same clinic (tenant) context
$redirectAfterLogin = ($clinic_slug !== '') ? (BASE_URL . 'MainPageClient.php?clinic_slug=' . rawurlencode($clinic_slug)) : (BASE_URL . 'MainPageClient.php');

// URL for "Create new account" respecting clinic slug routing
// .htaccess maps /{slug}/register -> clinic/RegisterClient.php?clinic_slug={slug}
// For slug-based access (e.g. mydental.ct.ws/{slug}/login), we must build URLs from the domain root,
// not from /clinic, so we use PROVIDER_BASE_URL here.
$registerClientUrl = ($clinic_slug !== '')
    ? (PROVIDER_BASE_URL . rawurlencode(strtolower($clinic_slug)) . '/register')
    : (BASE_URL . 'RegisterClient.php');

// Redirect if already logged in
if (isLoggedIn('client')) {
    header('Location: ' . $redirectAfterLogin);
    exit;
}

$loginLogo = isset($CLINIC['logo_nav']) ? trim($CLINIC['logo_nav']) : 'DRCGLogo2.png';
$loginLogoUrl = (strpos($loginLogo, 'http') === 0) ? $loginLogo : (BASE_URL . ltrim($loginLogo, '/'));
$loginLogoLocalPath = (strpos($loginLogo, 'http') === 0) ? null : (defined('ROOT_PATH') ? (ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($loginLogo, '/\\'))) : null);
if (strpos($loginLogoUrl, '?') === false && $loginLogoLocalPath && is_file($loginLogoLocalPath)) {
    $loginLogoUrl .= '?v=' . @filemtime($loginLogoLocalPath);
}
$loginLogoAlt = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Dental Clinic';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "primary": "#2b8cee",
              "background-light": "#f6f7f8",
              "background-dark": "#101922",
            },
            fontFamily: {
              "display": ["Manrope", "sans-serif"]
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
          },
        },
      }
    </script>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-[#0d141b] dark:text-slate-50 antialiased overflow-x-hidden transition-colors duration-200">
<div class="min-h-screen w-full flex items-center justify-center relative bg-slate-900 overflow-hidden py-12 px-4 sm:px-6 lg:px-8">
<div class="absolute inset-0 z-0">
<img alt="Dental Clinic Interior" class="w-full h-full object-cover opacity-40" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBt7vTBGI-aA-eh6xO_wliPbB0c5UODiW_Z9sUqKmWO1uEpn2T8TUUMqCW9Z_1sTHVsOgS8SRr9dAG_Kekhno65F7UlqbKuIn3BNXnm6iB6hJEfaD3i8DZLd_p_0Six117fQz2fwwNmeIMgs57fz1t9wnHNfiNO2bA5v9ai0MgIhi63sQp3DFEZz4glZUWMqs_2jRvOWTL8Z_NtaO7Ch16Zw4O7QwFAFySbi07H5YGM8pyyCliMj_znrPlhD-UEEpmatW2qDmTJvfM"/>
<div class="absolute inset-0 bg-gradient-to-br from-blue-900/80 via-slate-900/80 to-slate-900/90 mix-blend-multiply"></div>
<div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary/20 rounded-full blur-[100px]"></div>
<div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[100px]"></div>
</div>
<div class="relative z-10 w-full max-w-[480px]">
<div class="bg-white dark:bg-[#151f2b] rounded-2xl shadow-2xl shadow-slate-900/50 overflow-hidden ring-1 ring-white/10 backdrop-blur-sm">
<div class="bg-slate-50 dark:bg-[#1a2634] px-8 py-6 border-b border-slate-100 dark:border-slate-700/50 flex flex-col items-center justify-center text-center">
<div class="mb-3">
<img alt="<?php echo $loginLogoAlt; ?>" class="h-12 w-auto drop-shadow-sm" src="<?php echo htmlspecialchars($loginLogoUrl, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
<p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Patient Access</p>
</div>
<div class="px-8 py-8 sm:px-10 sm:py-10">
<div class="mb-8 text-center">
<h1 class="text-[#0d141b] dark:text-white tracking-tight text-2xl font-bold leading-tight">Welcome Back</h1>
<p class="text-slate-500 dark:text-slate-400 text-sm font-normal mt-2">Enter your credentials to manage your dental care.</p>
</div>
<div id="errorMessage" class="hidden mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-400 text-sm"></div>
<div id="successMessage" class="hidden mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-400 text-sm"></div>
<form id="loginForm" action="#" class="flex flex-col gap-5" onsubmit="event.preventDefault()">
<div class="flex flex-col gap-1.5">
<label class="text-[#0d141b] dark:text-slate-200 text-sm font-semibold leading-normal">Email or Username</label>
<div class="group flex w-full items-stretch rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 overflow-hidden transition-all duration-200 shadow-sm">
<input id="loginEmail" class="flex w-full flex-1 border-none bg-transparent h-11 px-4 text-[#0d141b] dark:text-white placeholder:text-slate-400 focus:ring-0 text-base font-medium" placeholder="email@example.com or username" type="text" required/>
<div class="flex items-center justify-center px-3 text-slate-400 dark:text-slate-500 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">person</span>
</div>
</div>
</div>
<div class="flex flex-col gap-1.5">
<div class="flex justify-between items-center">
<label class="text-[#0d141b] dark:text-slate-200 text-sm font-semibold leading-normal">Password</label>
<a id="forgotPasswordLink" class="text-primary text-xs font-semibold hover:text-blue-600 transition-colors cursor-pointer" href="#">Forgot password?</a>
</div>
<div class="group flex w-full items-stretch rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 overflow-hidden transition-all duration-200 shadow-sm">
<input id="loginPassword" class="flex w-full flex-1 border-none bg-transparent h-11 px-4 text-[#0d141b] dark:text-white placeholder:text-slate-400 focus:ring-0 text-base font-medium" placeholder="Enter password" type="password" required/>
<div class="flex items-center justify-center px-3 text-slate-400 dark:text-slate-500 cursor-pointer hover:text-primary transition-colors" id="passwordToggle">
<span class="material-symbols-outlined text-[20px]">visibility</span>
</div>
</div>
</div>
<button id="loginBtn" type="submit" class="flex w-full cursor-pointer items-center justify-center rounded-lg h-11 bg-primary hover:bg-[#237cd6] active:scale-[0.98] transition-all text-white text-base font-bold leading-normal tracking-wide shadow-md shadow-primary/30 mt-2">
                            Sign In securely
                        </button>
</form>
<div class="text-center pt-8">
<p class="text-slate-600 dark:text-slate-400 text-sm font-medium leading-normal">
                            First time visiting? <a class="text-primary font-bold hover:underline hover:text-blue-600 ml-1" href="<?php echo htmlspecialchars($registerClientUrl, ENT_QUOTES, 'UTF-8'); ?>">Create new account</a>
</p>
</div>
</div>
</div>

<!-- Forgot / Reset Password Modal -->
<div id="forgotPasswordModal" class="hidden fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4">
<div class="relative bg-white dark:bg-[#151f2b] rounded-2xl shadow-2xl max-w-md w-full overflow-hidden ring-1 ring-white/10">
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
<button id="sendOtpBtn" type="button" class="w-full rounded-lg h-11 bg-primary hover:bg-[#237cd6] text-white text-base font-bold transition-all">Send OTP</button>
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
<button id="submitResetBtn" type="button" class="w-full rounded-lg h-11 bg-primary hover:bg-[#237cd6] text-white text-base font-bold transition-all">Reset Password</button>
<button id="forgotBackBtn" type="button" class="w-full rounded-lg h-11 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white text-base font-bold hover:opacity-90 transition-all">Back</button>
</div>
</div>
<button id="closeForgotPasswordModal" type="button" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
<span class="material-symbols-outlined">close</span>
</button>
</div>
</div>

<div class="mt-8 flex justify-center gap-6 text-xs text-slate-300 font-medium opacity-80">
<a class="hover:text-white transition-colors flex items-center gap-1.5" href="#">
<span class="material-symbols-outlined text-[14px]">shield</span>
                    Privacy Policy
                </a>
<a class="hover:text-white transition-colors" href="#">Terms of Service</a>
<a class="hover:text-white transition-colors flex items-center gap-1.5" href="#">
<span class="material-symbols-outlined text-[14px]">help</span>
                    Help Center
                </a>
</div>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const loginBtn = document.getElementById('loginBtn');
    const loginEmail = document.getElementById('loginEmail');
    const loginPassword = document.getElementById('loginPassword');
    const passwordToggle = document.getElementById('passwordToggle');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    
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
    
    // Password visibility toggle
    if (passwordToggle) {
        passwordToggle.addEventListener('click', function() {
            const type = loginPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            loginPassword.setAttribute('type', type);
            const icon = passwordToggle.querySelector('span');
            icon.textContent = type === 'password' ? 'visibility' : 'visibility_off';
        });
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
                const clinicSlug = '<?php echo isset($_GET['clinic_slug']) ? addslashes(trim((string)$_GET['clinic_slug'])) : ''; ?>';
                const debugLogin = <?php echo (isset($_GET['debug']) && $_GET['debug'] === '1') ? 'true' : 'false'; ?>;
                const response = await fetch('<?php echo BASE_URL; ?>api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email.trim(),
                        password: password,
                        user_type: 'client',
                        clinic_slug: clinicSlug || undefined,
                        debug: debugLogin
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Login successful! Redirecting...');
                    setTimeout(function() {
                        window.location.href = '<?php echo addslashes($redirectAfterLogin); ?>';
                    }, 500);
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
                        showError(data.message || 'Login failed. Please try again.');
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

