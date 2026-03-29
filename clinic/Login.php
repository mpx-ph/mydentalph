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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "primary": "#2b8beb",
              "on-surface": "#131c25",
              "surface-container-low": "#edf4ff",
              "background-light": "#f6f7f8",
              "background-dark": "#101922",
            },
            fontFamily: {
              "display": ["Manrope", "sans-serif"],
              "serif": ["Playfair Display", "serif"],
            },
            borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1.5rem", "full": "9999px"},
          },
        },
      }
    </script>
    <style>
        .login-mesh-gradient {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.1) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.05) 0px, transparent 50%);
        }
        .dark .login-mesh-gradient {
            background-color: #0f172a;
            background-image:
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.15) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(43, 139, 235, 0.08) 0px, transparent 50%);
        }
        .login-editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.12);
            letter-spacing: -0.02em;
        }
        .login-card-shell {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 40px 80px -20px rgba(43, 139, 235, 0.12);
        }
        .dark .login-card-shell {
            background: rgba(21, 31, 43, 0.92);
            border-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 40px 80px -20px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body class="login-mesh-gradient font-display text-on-surface dark:text-slate-100 antialiased overflow-x-hidden min-h-screen flex flex-col selection:bg-primary/20">
<main class="flex-grow flex items-center justify-center w-full px-4 sm:px-6 lg:px-8 py-12 relative">
<div class="w-full max-w-lg relative z-10">
<div class="login-card-shell rounded-[2rem] md:rounded-[2.5rem] overflow-hidden p-8 md:p-12 space-y-8 md:space-y-10">
<div class="flex flex-col items-center text-center space-y-3">
<img alt="<?php echo $loginLogoAlt; ?>" class="h-11 md:h-12 w-auto object-contain" src="<?php echo htmlspecialchars($loginLogoUrl, ENT_QUOTES, 'UTF-8'); ?>"/>
<p class="text-xs font-semibold uppercase tracking-widest text-primary/80">Patient access</p>
<h1 class="font-display text-3xl md:text-4xl font-extrabold tracking-tight text-on-surface dark:text-white leading-tight">
Log in to your <span class="font-serif italic font-normal text-primary login-editorial-word transform -skew-x-6 inline-block">Account</span>
</h1>
<p class="text-slate-600 dark:text-slate-400 text-sm md:text-base font-medium leading-relaxed max-w-sm mx-auto">Enter your credentials to manage your dental care.</p>
</div>
<div id="errorMessage" class="hidden mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl text-red-700 dark:text-red-400 text-sm"></div>
<div id="successMessage" class="hidden mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl text-green-700 dark:text-green-400 text-sm"></div>
<form id="loginForm" action="#" class="space-y-6 md:space-y-8" onsubmit="event.preventDefault()">
<div class="space-y-2.5">
<label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em] ml-1" for="loginEmail">Email or username</label>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-primary/50 text-xl font-light">person</span>
</div>
<input id="loginEmail" class="block w-full pl-12 pr-4 py-3.5 md:py-4 bg-surface-container-low/60 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-600 rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:outline-none text-on-surface dark:text-white font-medium transition-all duration-200 placeholder:text-slate-400" placeholder="email@example.com or username" type="text" required/>
</div>
</div>
<div class="space-y-2.5">
<div class="flex justify-between items-center px-1 gap-2">
<label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em]" for="loginPassword">Password</label>
<a id="forgotPasswordLink" class="text-[10px] font-black uppercase tracking-wider text-primary hover:opacity-80 transition-opacity cursor-pointer shrink-0" href="#">Forgot password?</a>
</div>
<div class="relative group flex items-stretch">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none z-10">
<span class="material-symbols-outlined text-primary/50 text-xl font-light">lock</span>
</div>
<input id="loginPassword" class="block w-full pl-12 pr-12 py-3.5 md:py-4 bg-surface-container-low/60 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-600 rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:outline-none text-on-surface dark:text-white font-medium transition-all duration-200 placeholder:text-slate-400" placeholder="Enter password" type="password" required/>
<div class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-primary cursor-pointer transition-colors" id="passwordToggle">
<span class="material-symbols-outlined text-[22px]">visibility</span>
</div>
</div>
</div>
<button id="loginBtn" type="submit" class="w-full py-4 md:py-5 px-6 bg-primary hover:bg-[#2378d6] text-white font-black text-sm uppercase tracking-[0.2em] rounded-2xl shadow-xl shadow-primary/25 hover:shadow-2xl active:scale-[0.98] transition-all duration-200">
Sign In securely
</button>
</form>
<div class="text-center pt-2">
<p class="text-sm font-semibold text-slate-600 dark:text-slate-400">
First time visiting?
<a class="font-black text-primary hover:underline underline-offset-4 ml-1" href="<?php echo htmlspecialchars($registerClientUrl, ENT_QUOTES, 'UTF-8'); ?>">Create new account</a>
</p>
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

<div class="mt-10 md:mt-12 flex flex-wrap justify-center items-center gap-8 md:gap-10 text-slate-400 dark:text-slate-500 opacity-80 hover:opacity-100 transition-opacity">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-lg text-primary/60">verified_user</span>
<span class="text-[10px] uppercase font-black tracking-[0.2em]">Secure access</span>
</div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-lg text-primary/60">lock_person</span>
<span class="text-[10px] uppercase font-black tracking-[0.2em]">Encrypted</span>
</div>
</div>
</div>
<div class="fixed top-[-10%] right-[-5%] w-[28rem] md:w-[40rem] h-[28rem] md:h-[40rem] bg-primary/5 rounded-full blur-[100px] md:blur-[120px] -z-10 pointer-events-none"></div>
<div class="fixed bottom-[-10%] left-[-5%] w-[24rem] md:w-[30rem] h-[24rem] md:h-[30rem] bg-primary/5 rounded-full blur-[80px] md:blur-[100px] -z-10 pointer-events-none"></div>
</main>
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

