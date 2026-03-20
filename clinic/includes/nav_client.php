<?php
/**
 * Client Navigation Include
 */
if (!isset($CLINIC)) { require_once __DIR__ . '/clinic_customization.php'; }
if (!function_exists('isLoggedIn')) { require_once __DIR__ . '/auth.php'; }

$currentPage = basename($_SERVER['PHP_SELF']);
$clientLoggedIn = isLoggedIn('client');
$clientName = $clientLoggedIn && !empty($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$navLogo = isset($CLINIC['logo_nav']) ? trim($CLINIC['logo_nav']) : 'DRCGLogo2.png';
$navLogoUrl = (strpos($navLogo, 'http') === 0) ? $navLogo : (BASE_URL . ltrim($navLogo, '/'));
$navLogoLocalPath = (strpos($navLogo, 'http') === 0) ? null : (defined('ROOT_PATH') ? (ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($navLogo, '/\\'))) : null);
if (strpos($navLogoUrl, '?') === false && $navLogoLocalPath && is_file($navLogoLocalPath)) {
    $navLogoUrl .= '?v=' . @filemtime($navLogoLocalPath);
}
$navLogoAlt = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Dental Clinic';

// Current public clinic slug (set by tenant_bootstrap on public pages)
$currentSlug = $_SESSION['public_tenant_slug'] ?? null;

// Helper to build links that respect the pretty slug routes when available
if (!function_exists('clinic_link')) {
    function clinic_link(string $path, ?string $slug, string $fallback): string {
        if ($slug) {
            $slugPart = '/' . rawurlencode($slug);
            if ($path === '' || $path === '/') {
                return $slugPart . '/';
            }
            return $slugPart . '/' . ltrim($path, '/');
        }
        return $fallback;
    }
}
?>
<nav class="fixed top-0 z-50 w-full border-b border-slate-100 dark:border-slate-800 bg-white/90 dark:bg-background-dark/90 backdrop-blur-md transition-all duration-300">
    <div class="px-6 md:px-12 py-4 max-w-7xl mx-auto flex items-center justify-between">
        <div class="flex items-center gap-2.5 text-slate-900 dark:text-white group cursor-pointer">
            <a href="<?php echo clinic_link('', $currentSlug, BASE_URL . 'MainPageClient.php'); ?>">
                <img src="<?php echo $navLogoUrl; ?>" alt="<?php echo $navLogoAlt; ?>" class="h-12 w-auto object-contain">
            </a>
        </div>
        <div class="hidden lg:flex items-center gap-8">
            <a class="text-sm font-medium <?php echo ($currentPage == 'MainPageClient.php') ? 'text-primary dark:text-white border-b-2 border-primary pb-0.5' : 'text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-white transition-colors'; ?>" href="<?php echo clinic_link('', $currentSlug, BASE_URL . 'MainPageClient.php'); ?>">HOME</a>
            <a class="text-sm font-medium <?php echo ($currentPage == 'ServicesClient.php') ? 'text-primary dark:text-white border-b-2 border-primary pb-0.5' : 'text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-white transition-colors'; ?>" href="<?php echo clinic_link('services', $currentSlug, BASE_URL . 'ServicesClient.php'); ?>">SERVICES</a>
            <a class="text-sm font-medium <?php echo ($currentPage == 'AboutUsClient.php') ? 'text-primary dark:text-white border-b-2 border-primary pb-0.5' : 'text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-white transition-colors'; ?>" href="<?php echo clinic_link('about', $currentSlug, BASE_URL . 'AboutUsClient.php'); ?>">ABOUT US</a>
            <a class="text-sm font-medium <?php echo ($currentPage == 'ContactUsClient.php') ? 'text-primary dark:text-white border-b-2 border-primary pb-0.5' : 'text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-white transition-colors'; ?>" href="<?php echo clinic_link('contact', $currentSlug, BASE_URL . 'ContactUsClient.php'); ?>">CONTACT US</a>
        </div>
        <?php
        $loginUrl = clinic_link('login', $currentSlug, BASE_URL . 'Login.php');
        $logoutUrl = BASE_URL . 'api/logout.php';
        ?>
        <div class="flex items-center gap-4">
            <?php if ($clientLoggedIn && $clientName): ?>
            <div class="hidden md:block relative" id="userCardDropdownWrap">
                <button type="button" id="userCardButton" class="flex items-center gap-3 rounded-full pl-2 pr-4 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-primary/40 transition-colors cursor-pointer" aria-expanded="false" aria-haspopup="true">
                    <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-lg">person</span>
                    </div>
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate max-w-[140px]"><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="material-symbols-outlined text-slate-500 text-lg">expand_more</span>
                </button>
                <div id="userCardDropdown" class="hidden absolute right-0 mt-2 w-48 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-lg py-1 z-50">
                    <a href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        Logout
                    </a>
                </div>
            </div>
            <?php else: ?>
            <a class="hidden md:flex items-center justify-center rounded-full h-11 px-6 bg-primary hover:bg-primary-dark text-white text-sm font-semibold transition-all shadow-md shadow-primary/20 hover:shadow-lg hover:shadow-primary/30 hover:-translate-y-0.5 active:translate-y-0" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="truncate">LOGIN</span>
            </a>
            <?php endif; ?>
            <button id="mobileMenuButton" class="lg:hidden p-2 text-slate-600 dark:text-slate-300 hover:text-primary transition-colors" aria-label="Toggle menu">
                <span class="material-symbols-outlined" id="menuIcon">menu</span>
            </button>
        </div>
    </div>
    <!-- Mobile Menu -->
    <div id="mobileMenu" class="hidden lg:hidden fixed top-[73px] left-0 right-0 bg-white dark:bg-background-dark border-b border-slate-100 dark:border-slate-800 shadow-lg backdrop-blur-md">
        <div class="px-6 py-6 space-y-4">
            <?php if ($clientLoggedIn && $clientName): ?>
            <div class="flex items-center gap-3 rounded-xl p-3 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 mb-2">
                <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">person</span>
                </div>
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200"><?php echo htmlspecialchars($clientName, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <a class="block md:hidden flex items-center gap-2 py-2.5 px-3 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-primary transition-colors mb-2" href="<?php echo htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined text-[20px]">logout</span>
                Logout
            </a>
            <?php else: ?>
            <a class="block md:hidden mt-0 mb-4 w-full text-center rounded-full h-11 px-6 bg-primary hover:bg-primary-dark text-white text-sm font-semibold transition-all shadow-md shadow-primary/20 flex items-center justify-center" href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8'); ?>">
                LOGIN
            </a>
            <?php endif; ?>
            <a class="block text-base font-medium text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-white transition-colors py-2" href="<?php echo clinic_link('', $currentSlug, BASE_URL . 'MainPageClient.php'); ?>">HOME</a>
            <a class="block text-base font-medium text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-white transition-colors py-2" href="<?php echo clinic_link('services', $currentSlug, BASE_URL . 'ServicesClient.php'); ?>">SERVICES</a>
            <a class="block text-base font-medium text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-white transition-colors py-2" href="<?php echo clinic_link('about', $currentSlug, BASE_URL . 'AboutUsClient.php'); ?>">ABOUT US</a>
            <a class="block text-base font-medium text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-white transition-colors py-2" href="<?php echo clinic_link('contact', $currentSlug, BASE_URL . 'ContactUsClient.php'); ?>">CONTACT US</a>
        </div>
    </div>
</nav>
<script>
(function() {
    var wrap = document.getElementById('userCardDropdownWrap');
    var btn = document.getElementById('userCardButton');
    var menu = document.getElementById('userCardDropdown');
    if (!wrap || !btn || !menu) return;
    function close() { menu.classList.add('hidden'); btn.setAttribute('aria-expanded', 'false'); }
    function open() { menu.classList.remove('hidden'); btn.setAttribute('aria-expanded', 'true'); }
    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (menu.classList.contains('hidden')) open(); else close();
    });
    document.addEventListener('click', function() { close(); });
    menu.addEventListener('click', function(e) { e.stopPropagation(); });
})();
</script>

