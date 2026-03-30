<?php
/**
 * Login layout preview — same public navbar as MainPageClientRelayout (with clinic logo + uppercase labels).
 */
$pageTitle = 'Provider Portal Login';
require_once __DIR__ . '/config/config.php';

$clinic_slug = isset($_GET['clinic_slug']) ? trim((string) $_GET['clinic_slug']) : '';
if ($clinic_slug !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($clinic_slug))) {
    $_GET['clinic_slug'] = strtolower($clinic_slug);
    require_once __DIR__ . '/tenant_bootstrap.php';
}

require_once __DIR__ . '/includes/clinic_customization.php';

$slugLower = strtolower($clinic_slug);
$publicHomeUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/') : (BASE_URL . 'MainPageClient.php');
$publicServicesUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/services') : (BASE_URL . 'PatientServices.php');
$publicAboutUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/about') : (BASE_URL . 'AboutUsClient.php');
$publicContactUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/contact') : (BASE_URL . 'ContactUsClient.php');
$loginPageUrl = ($clinic_slug !== '') ? (PROVIDER_BASE_URL . rawurlencode($slugLower) . '/login') : (BASE_URL . 'Login.php');
$bookOnlineUrl = BASE_URL . 'BookAppointmentClient.php';

$loginLogo = isset($CLINIC['logo_nav']) ? trim($CLINIC['logo_nav']) : 'DRCGLogo2.png';
$loginLogoUrl = (strpos($loginLogo, 'http') === 0) ? $loginLogo : (BASE_URL . ltrim($loginLogo, '/'));
$loginLogoLocalPath = (strpos($loginLogo, 'http') === 0) ? null : (defined('ROOT_PATH') ? (ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($loginLogo, '/\\'))) : null);
if (strpos($loginLogoUrl, '?') === false && $loginLogoLocalPath && is_file($loginLogoLocalPath)) {
    $loginLogoUrl .= '?v=' . @filemtime($loginLogoLocalPath);
}
$loginLogoAlt = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Dental Clinic';
?>
<!DOCTYPE html>

<html class="scroll-smooth" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
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
        body { font-family: 'Inter', sans-serif; }
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
    </style>
</head>
<body class="bg-surface font-body text-on-surface mesh-gradient min-h-screen flex flex-col items-center selection:bg-primary/20">
<!-- Navigation -->
<nav class="fixed top-0 z-50 w-full bg-white/80 backdrop-blur-xl shadow-sm">
<div class="flex justify-between items-center h-20 px-8 max-w-screen-2xl mx-auto">
<div class="flex items-center font-headline">
<a href="<?php echo htmlspecialchars($publicHomeUrl, ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center shrink-0">
<img src="<?php echo htmlspecialchars($loginLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $loginLogoAlt; ?>" class="h-10 w-auto max-h-10 object-contain"/>
</a>
</div>
<div class="hidden md:flex items-center space-x-12 text-sm font-semibold tracking-tight text-on-surface/60 font-headline uppercase">
<a class="text-primary border-b-2 border-primary pb-1" href="<?php echo htmlspecialchars($publicHomeUrl, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
<a class="hover:text-primary transition-colors" href="<?php echo htmlspecialchars($publicServicesUrl, ENT_QUOTES, 'UTF-8'); ?>">Services</a>
<a class="hover:text-primary transition-colors" href="<?php echo htmlspecialchars($publicAboutUrl, ENT_QUOTES, 'UTF-8'); ?>">About Us</a>
<a class="hover:text-primary transition-colors" href="<?php echo htmlspecialchars($publicContactUrl, ENT_QUOTES, 'UTF-8'); ?>">Contact Us</a>
</div>
<div class="flex items-center gap-4">
<button type="button" class="text-on-surface font-semibold text-sm hover:text-primary transition-all font-headline uppercase" onclick="window.location.href=<?php echo json_encode($loginPageUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>">Login</button>
<button type="button" class="bg-primary text-white px-6 py-2.5 rounded-full font-semibold text-sm hover:shadow-lg hover:shadow-primary/30 transition-all active:scale-95 font-headline uppercase" onclick="window.location.href=<?php echo json_encode($bookOnlineUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>">
                Book Online
            </button>
</div>
</div>
</nav>
<main class="flex-grow flex items-center justify-center w-full px-4 sm:px-6 lg:px-8 relative pt-24 pb-12">
<div class="w-full max-w-lg">
<!-- Login Card -->
<div class="login-card rounded-[2.5rem] overflow-hidden p-12 space-y-10">
<!-- Header Content -->
<div class="text-center space-y-4">
<h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-[1.1] text-on-surface">
                    Log In to Your <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Account</span>
</h1>
<p class="text-on-surface-variant font-medium text-lg leading-relaxed max-w-xs mx-auto">Access dashboard to manage as staff or manager of the dental clinic</p>
</div>
<!-- Form -->
<form class="space-y-8">
<div class="space-y-6">
<!-- Email Field -->
<div class="space-y-2.5">
<label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em] ml-1" for="email">Email Address</label>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-primary/40 text-xl font-light">mail</span>
</div>
<input class="block w-full pl-12 pr-4 py-4 bg-surface-container-low/50 border rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:outline-none text-on-surface font-medium transition-all duration-200 placeholder:text-on-surface-variant/40 border-slate-200" id="email" name="email" placeholder="name@clinic.com" required="" type="email"/>
</div>
</div>
<!-- Password Field -->
<div class="space-y-2.5">
<div class="flex justify-between items-center px-1">
<label class="block text-[10px] font-black text-primary uppercase tracking-[0.2em]" for="password">Password</label>
<a class="text-[10px] font-black uppercase tracking-widest text-primary hover:opacity-70 transition-opacity" href="#">Forgot Password?</a>
</div>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-primary/40 text-xl font-light">lock</span>
</div>
<input class="block w-full pl-12 pr-4 py-4 bg-surface-container-low/50 border rounded-2xl focus:ring-2 focus:ring-primary/20 focus:border-primary focus:outline-none text-on-surface font-medium transition-all duration-200 placeholder:text-on-surface-variant/40 border-slate-200" id="password" name="password" placeholder="••••••••" required="" type="password"/>
</div>
</div>
</div>
<!-- Remember Me -->
<div class="flex items-center px-1">
<input class="h-5 w-5 text-primary focus:ring-primary border-on-surface/10 rounded-lg cursor-pointer transition-all" id="remember-me" name="remember-me" type="checkbox"/>
<label class="ml-3 block text-sm font-semibold text-on-surface-variant cursor-pointer" for="remember-me">
                        Remember Me
                    </label>
</div>
<!-- Action Button -->
<button class="w-full py-5 px-6 bg-primary text-white font-black text-sm uppercase tracking-[0.2em] rounded-2xl shadow-xl shadow-primary/20 hover:shadow-2xl hover:shadow-primary/30 active:scale-[0.98] transition-all duration-200" type="submit">
                    Login
                </button>
</form>
<!-- Footer Link -->
<div class="pt-2 text-center">
<p class="text-sm font-semibold text-on-surface-variant">
                    New tenant? 
                    <a class="font-black text-primary hover:underline underline-offset-4 transition-all" href="#">Create an Account</a>
</p>
</div>
</div>
<!-- Trust Badges / Security -->
<div class="mt-12 flex justify-center items-center space-x-10 opacity-30 hover:opacity-60 transition-all duration-500">
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
<footer class="w-full border-t border-slate-200 bg-slate-50/50 backdrop-blur-sm">
<div class="flex flex-col md:flex-row justify-between items-center py-12 px-10 max-w-screen-2xl mx-auto gap-8">
<div class="text-lg font-bold text-on-surface font-headline flex items-center gap-2"><div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center">
<span class="material-symbols-outlined text-white text-lg">select_check_box</span>
</div> 
            Your Logo Here</div>
<div class="flex flex-wrap justify-center gap-10 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">
<a class="hover:text-primary transition-colors" href="#">Privacy Policy</a>
<a class="hover:text-primary transition-colors" href="#">Terms of Service</a>
<a class="hover:text-primary transition-colors" href="#">Help Center</a>
</div>
<div class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/40">
            © 2024 Clinical Precision Framework.
        </div>
</div>
</footer>
<!-- Decorative Background Accents -->
<div class="fixed top-[-10%] right-[-5%] w-[40rem] h-[40rem] bg-primary/5 rounded-full blur-[120px] -z-10 pointer-events-none"></div>
<div class="fixed bottom-[-10%] left-[-5%] w-[30rem] h-[30rem] bg-primary/5 rounded-full blur-[100px] -z-10 pointer-events-none"></div>
</body></html>