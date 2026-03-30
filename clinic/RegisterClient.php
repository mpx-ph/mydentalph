<?php
/**
 * Registration Page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/tenant_bootstrap.php';
require_once __DIR__ . '/includes/clinic_customization.php';
$clinicName = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Dental Clinic';
$logoRegister = isset($CLINIC['logo_register']) ? trim($CLINIC['logo_register']) : 'DRCGLogo.png';
$logoRegisterUrl = (strpos($logoRegister, 'http') === 0) ? $logoRegister : (BASE_URL . ltrim($logoRegister, '/'));
$logoRegisterLocalPath = (strpos($logoRegister, 'http') === 0) ? null : (defined('ROOT_PATH') ? (ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($logoRegister, '/\\'))) : null);
if (strpos($logoRegisterUrl, '?') === false && $logoRegisterLocalPath && is_file($logoRegisterLocalPath)) {
    $logoRegisterUrl .= '?v=' . @filemtime($logoRegisterLocalPath);
}
$registerHeading = isset($CLINIC['register_heading']) ? htmlspecialchars($CLINIC['register_heading'], ENT_QUOTES, 'UTF-8') : 'Create Patient Account';
$registerSubtext = isset($CLINIC['register_subtext']) ? htmlspecialchars($CLINIC['register_subtext'], ENT_QUOTES, 'UTF-8') : 'Join our clinic today.';
$registerFooterText = isset($CLINIC['register_footer_text']) ? htmlspecialchars($CLINIC['register_footer_text'], ENT_QUOTES, 'UTF-8') : '© 2026 Dental Clinic. All rights reserved.';
$regHex = function($k) use ($CLINIC) {
    $v = isset($CLINIC[$k]) ? preg_replace('/^#/', '', trim($CLINIC[$k])) : '';
    return (strlen($v) === 6 && ctype_xdigit($v)) ? '#' . $v : '';
};
$regPrimary = $regHex('color_primary') ?: '#2b8cee';
$regPrimaryDark = $regHex('color_primary_dark') ?: '#1a6cb6';
$regPrimaryLight = $regHex('color_primary_light') ?: '#eef7ff';
$h = ltrim($regPrimary, '#');
if (strlen($h) !== 6 || !ctype_xdigit($h)) {
    $h = '2b8cee';
}
$regPrimaryR = hexdec(substr($h, 0, 2));
$regPrimaryG = hexdec(substr($h, 2, 2));
$regPrimaryB = hexdec(substr($h, 4, 2));

$slugLower = strtolower($currentTenantSlug);
$publicHomeUrl = PROVIDER_BASE_URL . rawurlencode($slugLower) . '/';
$publicServicesUrl = PROVIDER_BASE_URL . rawurlencode($slugLower) . '/services';
$publicAboutUrl = PROVIDER_BASE_URL . rawurlencode($slugLower) . '/about';
$publicContactUrl = PROVIDER_BASE_URL . rawurlencode($slugLower) . '/contact';
$downloadAppUrl = PROVIDER_BASE_URL . rawurlencode($slugLower) . '/download';
$loginPageUrl = PROVIDER_BASE_URL . rawurlencode($slugLower) . '/login';
$pageTitle = 'Create Account';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<script id="tailwind-config">
      tailwind.config = {
        darkMode: "class",
        theme: {
          extend: {
            colors: {
              "primary": "<?php echo htmlspecialchars($regPrimary, ENT_QUOTES, 'UTF-8'); ?>",
              "primary-dark": "<?php echo htmlspecialchars($regPrimaryDark, ENT_QUOTES, 'UTF-8'); ?>",
              "primary-light": "<?php echo htmlspecialchars($regPrimaryLight, ENT_QUOTES, 'UTF-8'); ?>",
              "darkBlue": "<?php echo htmlspecialchars($regPrimaryDark, ENT_QUOTES, 'UTF-8'); ?>",
              "on-surface": "#131c25",
              "surface": "#ffffff",
              "surface-variant": "#f7f9ff",
              "on-surface-variant": "#404752",
              "outline-variant": "#c0c7d4",
              "primary-fixed": "#d4e3ff",
              "on-primary-fixed-variant": "#004883",
              "surface-container-low": "<?php echo htmlspecialchars($regPrimaryLight, ENT_QUOTES, 'UTF-8'); ?>",
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
        body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(<?php echo (int) $regPrimaryR; ?>, <?php echo (int) $regPrimaryG; ?>, <?php echo (int) $regPrimaryB; ?>, 0.1);
            letter-spacing: -0.02em;
        }
        html, body { height: 100%; }
        body { overflow: hidden; }
        @keyframes pulse-border {
            0%, 100% {
                border-color: rgb(43, 140, 238);
                box-shadow: 0 0 0 0 rgba(43, 140, 238, 0.4);
            }
            50% {
                border-color: rgb(30, 107, 181);
                box-shadow: 0 0 0 4px rgba(43, 140, 238, 0.1);
            }
        }
        .checking-username {
            animation: pulse-border 1.5s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-white flex flex-col min-h-0 h-full selection:bg-primary/20 text-on-surface font-body">
<!-- Navigation (aligned with MainPageClientRelayout.php) -->
<nav class="fixed top-0 z-50 w-full bg-white/80 backdrop-blur-xl shadow-sm shrink-0">
<div class="flex justify-between items-center h-20 px-8 max-w-screen-2xl mx-auto w-full">
<a href="<?php echo htmlspecialchars($publicHomeUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-2xl font-bold tracking-tighter font-headline flex items-center gap-2 text-inherit no-underline min-w-0">
<div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center shrink-0">
<span class="material-symbols-outlined text-white text-lg">select_check_box</span>
</div>
<span class="truncate"><?php echo $clinicName; ?></span>
</a>
<div class="hidden md:flex items-center space-x-12 text-sm font-semibold tracking-tight text-on-surface/60 font-headline">
<a class="hover:text-primary transition-colors no-underline text-inherit" href="<?php echo htmlspecialchars($publicHomeUrl, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
<a class="hover:text-primary transition-colors no-underline text-inherit" href="<?php echo htmlspecialchars($publicServicesUrl, ENT_QUOTES, 'UTF-8'); ?>">Services</a>
<a class="hover:text-primary transition-colors no-underline text-inherit" href="<?php echo htmlspecialchars($publicAboutUrl, ENT_QUOTES, 'UTF-8'); ?>">About Us</a>
<a class="hover:text-primary transition-colors no-underline text-inherit" href="<?php echo htmlspecialchars($publicContactUrl, ENT_QUOTES, 'UTF-8'); ?>">Contact Us</a>
</div>
<div class="flex items-center gap-4">
<a href="<?php echo htmlspecialchars($loginPageUrl, ENT_QUOTES, 'UTF-8'); ?>" class="text-on-surface font-semibold text-sm hover:text-primary transition-all font-headline no-underline">Login</a>
<a href="<?php echo htmlspecialchars($downloadAppUrl, ENT_QUOTES, 'UTF-8'); ?>" class="bg-primary text-white px-6 py-2.5 rounded-full font-semibold text-sm hover:shadow-lg hover:shadow-primary/30 transition-all active:scale-95 no-underline inline-flex items-center">
                Download App
            </a>
</div>
</div>
</nav>
<main class="flex-1 min-h-0 w-full flex flex-col items-center px-3 sm:px-6 pt-20 pb-2 z-10 overflow-hidden">
<div class="w-full max-w-5xl relative z-10 flex flex-col flex-1 min-h-0 py-1 sm:py-2">
<div class="rounded-2xl border-2 border-primary bg-white shadow-lg flex flex-col flex-1 min-h-0 overflow-hidden">
<div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 px-4 sm:px-5 pt-3 sm:pt-4 pb-2 border-b border-slate-100 shrink-0">
<img src="<?php echo htmlspecialchars($logoRegisterUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $clinicName; ?>" class="h-10 sm:h-11 w-auto object-contain shrink-0"/>
<div class="min-w-0 flex-1 text-center sm:text-left">
<h1 class="font-headline text-xl sm:text-2xl font-extrabold tracking-tighter leading-tight text-on-surface">
                    Create <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Account</span>
</h1>
<p class="text-on-surface-variant font-medium text-xs sm:text-sm leading-snug line-clamp-2 font-body"><?php echo $registerSubtext; ?></p>
</div>
</div>
<div class="px-4 sm:px-5 py-3 overflow-y-auto overscroll-contain min-h-0 flex-1">
<div id="formMessage" class="hidden mb-3"></div>
<form id="registerForm" class="space-y-3" action="<?php echo BASE_URL; ?>api/register.php" method="POST">
<input type="hidden" name="user_type" value="client"/>
<?php if (isset($currentTenantSlug)): ?>
<input type="hidden" name="clinic_slug" value="<?php echo htmlspecialchars($currentTenantSlug, ENT_QUOTES, 'UTF-8'); ?>"/>
<?php endif; ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
<div class="space-y-1">
<label class="block text-[9px] font-black text-primary uppercase tracking-[0.15em] ml-0.5 font-headline" for="first_name">First Name</label>
<div class="relative group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 text-lg group-focus-within:text-primary transition-colors">person</span>
<input autocomplete="given-name" class="w-full pl-10 pr-3 py-2 text-sm bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="first_name" name="first_name" placeholder="Jane" type="text" required/>
</div>
</div>
<div class="space-y-1">
<label class="block text-[9px] font-black text-primary uppercase tracking-[0.15em] ml-0.5 font-headline" for="last_name">Last Name</label>
<div class="relative group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 text-lg group-focus-within:text-primary transition-colors">person</span>
<input autocomplete="family-name" class="w-full pl-10 pr-3 py-2 text-sm bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="last_name" name="last_name" placeholder="Doe" type="text" required/>
</div>
</div>
<div class="space-y-1 sm:col-span-2 lg:col-span-1">
<label class="block text-[9px] font-black text-primary uppercase tracking-[0.15em] ml-0.5 font-headline" for="email">Email</label>
<div class="relative group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 text-lg group-focus-within:text-primary transition-colors">mail</span>
<input autocomplete="email" class="w-full pl-10 pr-3 py-2 text-sm bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="email" name="email" placeholder="jane@example.com" type="email" required/>
</div>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
<div class="space-y-1">
<label class="block text-[9px] font-black text-primary uppercase tracking-[0.15em] ml-0.5 font-headline" for="username">Username</label>
<div class="relative group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 text-lg group-focus-within:text-primary transition-colors">alternate_email</span>
<input autocomplete="username" class="w-full pl-10 pr-10 py-2 text-sm bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="username" name="username" placeholder="jane_doe" type="text" required/>
<span id="usernameStatusIcon" class="absolute right-3 top-1/2 -translate-y-1/2 hidden"></span>
</div>
<div id="usernameMessage" class="hidden mt-1 text-[11px]"></div>
</div>
<div class="space-y-1">
<label class="block text-[9px] font-black text-primary uppercase tracking-[0.15em] ml-0.5 font-headline" for="mobile">Mobile (+63)</label>
<div class="relative group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 text-lg group-focus-within:text-primary transition-colors">smartphone</span>
<span class="absolute left-10 top-1/2 -translate-y-1/2 text-slate-600 font-semibold text-xs">+63</span>
<input autocomplete="tel" class="w-full pl-[4.25rem] pr-3 py-2 text-sm bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="mobile" name="mobile" placeholder="9XX XXX XXXX" type="tel" maxlength="12"/>
</div>
<div id="mobileMessage" class="hidden mt-1 text-[11px]"></div>
</div>
<div class="space-y-1">
<label class="block text-[9px] font-black text-primary uppercase tracking-[0.15em] ml-0.5 font-headline" for="date_of_birth">Date of Birth <span class="text-red-500">*</span></label>
<div class="relative group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 text-lg group-focus-within:text-primary transition-colors pointer-events-none">calendar_today</span>
<input autocomplete="bday" class="w-full pl-10 pr-2 py-2 text-sm bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all min-h-[2.25rem]" id="date_of_birth" name="date_of_birth" type="date" required/>
</div>
<div id="ageWarning" class="hidden mt-1.5 p-2 bg-amber-50 border border-amber-200 rounded-lg text-[11px] text-amber-800 leading-snug">
<span class="material-symbols-outlined text-sm align-middle mr-0.5">warning</span>
<span>You must be 18 or older. Under 18? Use a parent/guardian account.</span>
</div>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
<div class="space-y-1">
<label class="block text-[9px] font-black text-primary uppercase tracking-[0.15em] ml-0.5 font-headline" for="password">Password</label>
<div class="relative group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 text-lg group-focus-within:text-primary transition-colors">lock</span>
<input class="w-full pl-10 pr-10 py-2 text-sm bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="password" name="password" placeholder="••••••••" type="password" required/>
<button id="togglePassword" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary cursor-pointer transition-colors p-0.5" type="button">
<span class="material-symbols-outlined text-lg">visibility</span>
</button>
</div>
</div>
<div class="space-y-1">
<label class="block text-[9px] font-black text-primary uppercase tracking-[0.15em] ml-0.5 font-headline" for="confirm_password">Confirm</label>
<div class="relative group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 text-lg group-focus-within:text-primary transition-colors">lock_reset</span>
<input class="w-full pl-10 pr-10 py-2 text-sm bg-slate-50 border border-slate-100 rounded-xl focus:ring-2 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="confirm_password" name="confirm_password" placeholder="••••••••" type="password" required/>
<button id="toggleConfirmPassword" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary cursor-pointer transition-colors p-0.5" type="button">
<span class="material-symbols-outlined text-lg">visibility</span>
</button>
</div>
</div>
</div>
<div class="bg-slate-50 p-2 rounded-lg border border-slate-100">
<div class="flex items-center justify-between mb-1">
<span class="text-[10px] font-semibold text-slate-500">Password strength</span>
<span id="passwordStrengthLabel" class="text-[10px] font-bold text-slate-400">Enter password</span>
</div>
<div class="flex gap-1 h-1 w-full">
<div id="strengthBar1" class="flex-1 bg-slate-200 rounded-full transition-all duration-300"></div>
<div id="strengthBar2" class="flex-1 bg-slate-200 rounded-full transition-all duration-300"></div>
<div id="strengthBar3" class="flex-1 bg-slate-200 rounded-full transition-all duration-300"></div>
<div id="strengthBar4" class="flex-1 bg-slate-200 rounded-full transition-all duration-300"></div>
</div>
<p class="mt-1 text-[9px] text-slate-400 leading-tight">Uppercase, lowercase, number, and special character required.</p>
</div>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pt-0.5">
<div class="flex items-start gap-2 min-w-0">
<div class="flex h-5 items-center shrink-0 pt-0.5">
<input class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/20 bg-slate-50 cursor-pointer" id="terms" name="terms" type="checkbox" required/>
</div>
<label class="text-[11px] sm:text-xs leading-snug text-on-surface-variant font-medium select-none font-body" for="terms">I agree to the <a class="text-primary hover:text-darkBlue hover:underline font-semibold transition-colors" href="<?php echo BASE_URL; ?>TermsofService.php">Terms</a> and <a class="text-primary hover:text-darkBlue hover:underline font-semibold transition-colors" href="<?php echo BASE_URL; ?>PrivacyPolicy.php">Privacy Policy</a></label>
</div>
<button id="submitBtn" class="w-full sm:w-auto shrink-0 bg-primary hover:bg-darkBlue text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-md shadow-primary/25 active:scale-[0.98] text-xs uppercase tracking-wider inline-flex items-center justify-center gap-2 sm:min-w-[11rem]" type="submit">
<span class="material-symbols-outlined text-lg">how_to_reg</span>
<span>Create Account</span>
</button>
</div>
</form>
</div>
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 px-4 sm:px-5 py-2 border-t border-slate-100 bg-slate-50/80 shrink-0 text-center sm:text-left">
<a class="group inline-flex items-center justify-center sm:justify-start gap-1 text-primary font-bold text-xs hover:text-darkBlue transition-colors no-underline font-headline" href="<?php echo htmlspecialchars($loginPageUrl, ENT_QUOTES, 'UTF-8'); ?>">
<span class="material-symbols-outlined text-base">login</span>
                Already a member? Log in
            </a>
<p class="text-[9px] text-on-surface-variant/70 uppercase tracking-[0.12em] font-headline truncate"><?php echo preg_replace('/\s+/u', ' ', trim(str_replace(["\r\n", "\n", "\r"], ' ', $registerFooterText))); ?></p>
</div>
</div>
</div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const strengthLabel = document.getElementById('passwordStrengthLabel');
    const strengthBars = [
        document.getElementById('strengthBar1'),
        document.getElementById('strengthBar2'),
        document.getElementById('strengthBar3'),
        document.getElementById('strengthBar4')
    ];
    
    // Username availability checker
    let usernameCheckTimeout;
    let isUsernameAvailable = false;
    let isCheckingUsername = false;
    
    const usernameInput = document.getElementById('username');
    const usernameStatusIcon = document.getElementById('usernameStatusIcon');
    const usernameMessage = document.getElementById('usernameMessage');
    
    function checkUsernameAvailability(username) {
        // Clear previous timeout
        clearTimeout(usernameCheckTimeout);
        
        // Reset state
        isUsernameAvailable = false;
        usernameStatusIcon.classList.add('hidden');
        usernameMessage.classList.add('hidden');
        usernameInput.classList.remove('border-red-500', 'border-green-500', 'border-primary', 'checking-username');
        
        // If username is empty or too short, don't check
        if (!username || username.length < 3) {
            return;
        }
        
        // Validate username format
        if (!/^[a-zA-Z0-9_-]{3,20}$/.test(username)) {
            usernameStatusIcon.classList.remove('hidden');
            usernameStatusIcon.innerHTML = '<span class="material-symbols-outlined text-red-500">error</span>';
            usernameMessage.classList.remove('hidden');
            usernameMessage.className = 'mt-2 text-xs text-red-600';
            usernameMessage.textContent = 'Username must be 3-20 characters and contain only letters, numbers, underscores, or hyphens.';
            usernameInput.classList.add('border-red-500');
            return;
        }
        
        // Show checking state with better loading indicator
        isCheckingUsername = true;
        usernameStatusIcon.classList.remove('hidden');
        usernameStatusIcon.innerHTML = '<span class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>';
        usernameMessage.classList.remove('hidden');
        usernameMessage.className = 'mt-2 text-xs text-primary flex items-center gap-2';
        usernameMessage.innerHTML = '<span class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span><span>Checking availability...</span>';
        usernameInput.classList.remove('border-red-500', 'border-green-500', 'checking-username');
        usernameInput.classList.add('border-primary', 'checking-username');
        
        // Debounce: wait 500ms after user stops typing
        usernameCheckTimeout = setTimeout(() => {
            fetch('<?php echo BASE_URL; ?>api/check_username.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ username: username })
            })
            .then(response => response.json())
            .then(data => {
                isCheckingUsername = false;
                
                if (data.success && data.data && data.data.available) {
                    // Username is available
                    isUsernameAvailable = true;
                    usernameStatusIcon.classList.remove('hidden');
                    usernameStatusIcon.innerHTML = '<span class="material-symbols-outlined text-green-500">check_circle</span>';
                    usernameMessage.classList.remove('hidden');
                    usernameMessage.className = 'mt-2 text-xs text-green-600';
                    usernameMessage.textContent = 'Username is available.';
                    usernameInput.classList.remove('border-red-500', 'border-primary', 'checking-username');
                    usernameInput.classList.add('border-green-500');
                } else {
                    // Username is taken
                    isUsernameAvailable = false;
                    usernameStatusIcon.classList.remove('hidden');
                    usernameStatusIcon.innerHTML = '<span class="material-symbols-outlined text-red-500">cancel</span>';
                    usernameMessage.classList.remove('hidden');
                    usernameMessage.className = 'mt-2 text-xs text-red-600';
                    usernameMessage.textContent = data.message || 'Username is already taken.';
                    usernameInput.classList.remove('border-green-500', 'border-primary', 'checking-username');
                    usernameInput.classList.add('border-red-500');
                }
            })
            .catch(error => {
                isCheckingUsername = false;
                console.error('Username check error:', error);
                // Show error message on network failure
                usernameStatusIcon.classList.remove('hidden');
                usernameStatusIcon.innerHTML = '<span class="material-symbols-outlined text-amber-500">warning</span>';
                usernameMessage.classList.remove('hidden');
                usernameMessage.className = 'mt-2 text-xs text-amber-600';
                usernameMessage.textContent = 'Unable to verify username. Please try again.';
                usernameInput.classList.remove('border-primary', 'border-green-500', 'checking-username');
                usernameInput.classList.add('border-amber-500');
            });
        }, 500);
    }
    
    // Add event listener for username input
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            const username = this.value.trim();
            checkUsernameAvailability(username);
        });
        
        usernameInput.addEventListener('blur', function() {
            const username = this.value.trim();
            if (username) {
                checkUsernameAvailability(username);
            }
        });
    }

    // Mobile number validation (Philippine numbers only)
    const mobileInput = document.getElementById('mobile');
    const mobileMessage = document.getElementById('mobileMessage');
    
    function validatePhilippineMobile(mobile) {
        // Remove all non-digit characters
        const digitsOnly = mobile.replace(/\D/g, '');
        
        // Philippine mobile numbers: +63 followed by 9 digits (without leading 0)
        // Or local format: 09 followed by 9 digits
        // Valid patterns: +63 9XX XXX XXXX or 09XX XXX XXXX
        // After removing non-digits, should be 10 digits starting with 9
        
        if (!mobile || mobile.trim() === '') {
            return { valid: true, message: '' }; // Mobile is optional
        }
        
        // Check if it starts with 0 (local format) or just digits
        let cleanNumber = digitsOnly;
        
        // If starts with 0, remove it (local format 09XX...)
        if (cleanNumber.startsWith('0')) {
            cleanNumber = cleanNumber.substring(1);
        }
        
        // Should be exactly 10 digits starting with 9
        if (cleanNumber.length === 10 && cleanNumber.startsWith('9')) {
            return { valid: true, message: '' };
        } else if (cleanNumber.length > 0) {
            return { 
                valid: false, 
                message: 'Please enter a valid Philippine mobile number (e.g., 9123456789)' 
            };
        }
        
        return { valid: true, message: '' };
    }
    
    function formatMobileInput(value, cursorPos) {
        // Remove all non-digit characters
        let digitsOnly = value.replace(/\D/g, '');
        
        // Remove leading 0 if present (local format)
        if (digitsOnly.startsWith('0')) {
            digitsOnly = digitsOnly.substring(1);
        }
        
        // Limit to 10 digits
        digitsOnly = digitsOnly.substring(0, 10);
        
        // Format: 9XX XXX XXXX
        let formatted = '';
        if (digitsOnly.length > 6) {
            formatted = digitsOnly.substring(0, 3) + ' ' + digitsOnly.substring(3, 6) + ' ' + digitsOnly.substring(6);
        } else if (digitsOnly.length > 3) {
            formatted = digitsOnly.substring(0, 3) + ' ' + digitsOnly.substring(3);
        } else {
            formatted = digitsOnly;
        }
        
        // Calculate cursor position
        // Count digits before cursor in original value
        const textBeforeCursor = value.substring(0, cursorPos);
        let digitsBeforeCursor = textBeforeCursor.replace(/\D/g, '').length;
        
        // Adjust if we removed a leading zero
        if (value.replace(/\D/g, '').startsWith('0') && digitsBeforeCursor > 0) {
            digitsBeforeCursor = Math.max(0, digitsBeforeCursor - 1);
        }
        
        // Calculate new cursor position
        let newCursorPos = formatted.length; // Default to end
        
        if (digitsBeforeCursor > 0 && digitsBeforeCursor <= digitsOnly.length) {
            // Find position in formatted string after the same number of digits
            if (digitsBeforeCursor <= 3) {
                newCursorPos = digitsBeforeCursor;
            } else if (digitsBeforeCursor <= 6) {
                newCursorPos = digitsBeforeCursor + 1; // +1 for space
            } else {
                newCursorPos = digitsBeforeCursor + 2; // +2 for two spaces
            }
            // Don't exceed formatted length
            newCursorPos = Math.min(newCursorPos, formatted.length);
        }
        
        return { formatted, cursorPos: newCursorPos };
    }
    
    if (mobileInput && mobileMessage) {
        let isFormatting = false; // Flag to prevent recursive calls
        
        mobileInput.addEventListener('input', function(e) {
            if (isFormatting) return; // Prevent recursive formatting
            isFormatting = true;
            
            const value = this.value;
            const cursorPos = this.selectionStart;
            const wasAtEnd = cursorPos >= value.length;
            
            const result = formatMobileInput(value, cursorPos);
            
            // Update the input value with formatted version
            if (value !== result.formatted) {
                this.value = result.formatted;
                
                // If user was typing at the end, keep cursor at end
                // Otherwise, use calculated position
                const newCursorPos = wasAtEnd ? result.formatted.length : result.cursorPos;
                
                // Use setTimeout to ensure cursor is set after DOM updates
                setTimeout(() => {
                    this.setSelectionRange(newCursorPos, newCursorPos);
                    isFormatting = false;
                }, 0);
            } else {
                isFormatting = false;
            }
            
            const validation = validatePhilippineMobile(result.formatted);
            
            if (validation.valid) {
                mobileInput.classList.remove('border-red-500');
                mobileInput.classList.add('border-green-500');
                mobileMessage.classList.add('hidden');
            } else {
                mobileInput.classList.remove('border-green-500');
                mobileInput.classList.add('border-red-500');
                mobileMessage.classList.remove('hidden');
                mobileMessage.className = 'mt-2 text-xs text-red-600';
                mobileMessage.textContent = validation.message;
            }
            
            // If empty, remove validation classes
            if (!value || value.trim() === '') {
                mobileInput.classList.remove('border-red-500', 'border-green-500');
                mobileMessage.classList.add('hidden');
            }
        });
        
        mobileInput.addEventListener('blur', function() {
            const value = this.value.trim();
            if (value) {
                const validation = validatePhilippineMobile(value);
                if (!validation.valid) {
                    mobileInput.classList.remove('border-green-500');
                    mobileInput.classList.add('border-red-500');
                    mobileMessage.classList.remove('hidden');
                    mobileMessage.className = 'mt-2 text-xs text-red-600';
                    mobileMessage.textContent = validation.message;
                }
            }
        });
    }

    // Password visibility toggle
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            const icon = togglePasswordBtn.querySelector('span');
            icon.textContent = type === 'password' ? 'visibility' : 'visibility_off';
        });
    }

    // Confirm password visibility toggle
    const toggleConfirmPasswordBtn = document.getElementById('toggleConfirmPassword');
    if (toggleConfirmPasswordBtn) {
        toggleConfirmPasswordBtn.addEventListener('click', function() {
            const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPasswordInput.setAttribute('type', type);
            const icon = toggleConfirmPasswordBtn.querySelector('span');
            icon.textContent = type === 'password' ? 'visibility' : 'visibility_off';
        });
    }

    // Password strength calculator
    function calculatePasswordStrength(password) {
        if (!password || password.length === 0) {
            return { strength: 0, label: 'Enter password', color: 'text-slate-400' };
        }

        let checks = {
            length: password.length >= 8,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^a-zA-Z0-9]/.test(password)
        };

        // Check if all requirements are met
        const allRequirementsMet = checks.length && checks.lowercase && checks.uppercase && checks.number && checks.special;
        
        // Count how many requirements are met
        const requirementsCount = [
            checks.length,
            checks.lowercase,
            checks.uppercase,
            checks.number,
            checks.special
        ].filter(Boolean).length;

        let strength = 0;
        let label, color;

        if (!allRequirementsMet) {
            // If not all requirements are met, show strength based on how many are met
            strength = Math.min(requirementsCount, 2); // Cap at 2 (Fair) if requirements not met
            if (strength === 0) {
                label = 'Too weak';
                color = 'text-red-500';
            } else if (strength === 1) {
                label = 'Weak';
                color = 'text-red-500';
            } else {
                label = 'Fair';
                color = 'text-yellow-500';
            }
        } else {
            // All requirements met - strength based on length
            if (password.length >= 16) {
                strength = 4;
                label = 'Strong';
                color = 'text-green-500';
            } else if (password.length >= 12) {
                strength = 4;
                label = 'Strong';
                color = 'text-green-500';
            } else {
                strength = 3;
                label = 'Good';
                color = 'text-blue-500';
            }
        }

        return { strength, label, color };
    }

    // Update password strength indicator
    function updatePasswordStrength() {
        const password = passwordInput.value;
        const result = calculatePasswordStrength(password);

        // Update label
        strengthLabel.textContent = result.label;
        strengthLabel.className = `text-xs font-bold ${result.color}`;

        // Update bars
        strengthBars.forEach((bar, index) => {
            if (index < result.strength) {
                // Set color based on strength
                if (result.strength <= 1) {
                    bar.className = 'flex-1 bg-red-400 rounded-full h-full transition-all duration-300 shadow-[0_0_10px_rgba(239,68,68,0.5)]';
                } else if (result.strength === 2) {
                    bar.className = 'flex-1 bg-yellow-400 rounded-full h-full transition-all duration-300 shadow-[0_0_10px_rgba(250,204,21,0.5)]';
                } else if (result.strength === 3) {
                    bar.className = 'flex-1 bg-blue-400 rounded-full h-full transition-all duration-300 shadow-[0_0_10px_rgba(59,130,246,0.5)]';
                } else {
                    bar.className = 'flex-1 bg-green-400 rounded-full h-full transition-all duration-300 shadow-[0_0_10px_rgba(34,197,94,0.5)]';
                }
            } else {
                bar.className = 'flex-1 bg-slate-200 rounded-full h-full transition-all duration-300';
            }
        });
    }

    // Add event listener to password input
    if (passwordInput) {
        passwordInput.addEventListener('input', updatePasswordStrength);
        passwordInput.addEventListener('focus', updatePasswordStrength);
    }

    // Age validation on date of birth change
    const dateOfBirthInput = document.getElementById('date_of_birth');
    const ageWarning = document.getElementById('ageWarning');
    if (dateOfBirthInput && ageWarning) {
        dateOfBirthInput.addEventListener('change', function() {
            const birthDate = new Date(this.value);
            if (isNaN(birthDate.getTime())) {
                ageWarning.classList.add('hidden');
                return;
            }
            
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            const dayDiff = today.getDate() - birthDate.getDate();
            
            // Calculate exact age
            let exactAge = age;
            if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                exactAge--;
            }
            
            if (exactAge < 18) {
                ageWarning.classList.remove('hidden');
                dateOfBirthInput.classList.add('border-red-500');
            } else {
                ageWarning.classList.add('hidden');
                dateOfBirthInput.classList.remove('border-red-500');
            }
        });
    }

    // Validate password confirmation
    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    confirmPasswordInput.classList.remove('border-red-500');
                    confirmPasswordInput.classList.add('border-green-500');
                } else {
                    confirmPasswordInput.classList.remove('border-green-500');
                    confirmPasswordInput.classList.add('border-red-500');
                }
            } else {
                confirmPasswordInput.classList.remove('border-red-500', 'border-green-500');
            }
        });
    }

    // Form submission handler - submit via AJAX to API
    const registerForm = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const formMessage = document.getElementById('formMessage');
    
    if (registerForm && submitBtn) {
        registerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form values
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const email = document.getElementById('email').value.trim();
            const username = document.getElementById('username').value.trim();
            let mobile = document.getElementById('mobile').value.trim();
            const dateOfBirth = document.getElementById('date_of_birth').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const termsAccepted = document.getElementById('terms').checked;
            
            // Validation
            if (!firstName || !lastName || !email || !username || !password || !confirmPassword || !dateOfBirth) {
                showMessage('Please fill in all required fields.', 'error');
                return;
            }
            
            // Check username availability before submitting
            if (isCheckingUsername) {
                showMessage('Please wait while we check username availability.', 'error');
                return;
            }
            
            if (!isUsernameAvailable && username) {
                showMessage('Please choose an available username.', 'error');
                usernameInput.focus();
                return;
            }
            
            // Age validation - must be 18 or older
            if (dateOfBirth) {
                const birthDate = new Date(dateOfBirth);
                const today = new Date();
                const age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                const dayDiff = today.getDate() - birthDate.getDate();
                
                // Calculate exact age
                let exactAge = age;
                if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
                    exactAge--;
                }
                
                if (exactAge < 18) {
                    showMessage('You must be 18 years or older to create an account. If you are under 18, please create an account under an adult/parental account instead.', 'error');
                    document.getElementById('ageWarning').classList.remove('hidden');
                    return;
                } else {
                    document.getElementById('ageWarning').classList.add('hidden');
                }
            }
            
            if (password !== confirmPassword) {
                showMessage('Passwords do not match.', 'error');
                return;
            }
            
            if (password.length < 8) {
                showMessage('Password must be at least 8 characters long.', 'error');
                return;
            }
            
            // Validate password requirements: uppercase, lowercase, number, special character
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecialChar = /[^a-zA-Z0-9]/.test(password);
            
            if (!hasUpperCase || !hasLowerCase || !hasNumber || !hasSpecialChar) {
                showMessage('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.', 'error');
                return;
            }
            
            if (!termsAccepted) {
                showMessage('Please accept the Terms of Service and Privacy Policy.', 'error');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }
            
            // Mobile number validation (Philippine numbers only)
            if (mobile && mobile.trim() !== '') {
                // Remove all formatting (spaces, etc.) for validation
                const digitsOnly = mobile.replace(/\D/g, '');
                let cleanMobile = digitsOnly;
                
                // Remove leading 0 if present (local format 09XX...)
                if (cleanMobile.startsWith('0')) {
                    cleanMobile = cleanMobile.substring(1);
                }
                
                // Remove +63 if present
                if (cleanMobile.startsWith('63')) {
                    cleanMobile = cleanMobile.substring(2);
                }
                
                // Validate: should be exactly 10 digits starting with 9
                if (cleanMobile.length !== 10 || !cleanMobile.startsWith('9')) {
                    showMessage('Please enter a valid Philippine mobile number (e.g., 9123456789).', 'error');
                    mobileInput.focus();
                    return;
                }
                
                // Format as +63XXXXXXXXXX for storage and update input
                mobile = '+63' + cleanMobile;
                mobileInput.value = mobile;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="flex items-center justify-center space-x-3"><span class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span><span>Creating Account...</span></span>';
            formMessage.classList.add('hidden');
            
            // Submit via AJAX
            const formData = new FormData(registerForm);
            
            fetch('<?php echo BASE_URL; ?>api/register.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message || 'Registration successful! Redirecting to home.', 'success');
                    setTimeout(function() {
                        const tenantSlug = '<?php echo isset($currentTenantSlug) && $currentTenantSlug ? rawurlencode($currentTenantSlug) : ''; ?>';
                        if (tenantSlug) {
                            // Redirect to tenant root, e.g., https://mydental.ct.ws/arman
                            window.location.href = window.location.origin.replace(/\/+$/, '') + '/' + tenantSlug;
                        } else {
                            // Fallback to clinic main page
                            window.location.href = '<?php echo BASE_URL . "MainPageClient.php"; ?>';
                        }
                    }, 1000);
                } else {
                    showMessage(data.message || 'Registration failed. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span class="material-symbols-outlined text-xl">how_to_reg</span><span>Create Account</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span class="material-symbols-outlined text-xl">how_to_reg</span><span>Create Account</span>';
            });
        });
    }
    
    function showMessage(text, type) {
        formMessage.classList.remove('hidden');
        
        if (type === 'success') {
            formMessage.className = 'mb-3 p-3 rounded-xl text-sm font-medium bg-green-50 text-green-800 border border-green-200';
            formMessage.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                    <span>${text}</span>
                </div>
            `;
        } else {
            formMessage.className = 'mb-3 p-3 rounded-xl text-sm font-medium bg-red-50 text-red-800 border border-red-200';
            formMessage.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <span>${text}</span>
                </div>
            `;
        }
        
        formMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
});
</script>
</body>
</html>

