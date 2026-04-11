<?php
/**
 * Footer Include
 * Use this for client-facing pages
 */
if (!isset($CLINIC)) { require_once __DIR__ . '/clinic_customization.php'; }
$footerLogo = isset($CLINIC['logo']) ? trim($CLINIC['logo']) : 'DRCGLogo.png';
$footerLogoUrl = (strpos($footerLogo, 'http') === 0) ? $footerLogo : (BASE_URL . ltrim($footerLogo, '/'));
$footerLogoLocalPath = (strpos($footerLogo, 'http') === 0) ? null : (defined('ROOT_PATH') ? (ROOT_PATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($footerLogo, '/\\'))) : null);
if (strpos($footerLogoUrl, '?') === false && $footerLogoLocalPath && is_file($footerLogoLocalPath)) {
    $footerLogoUrl .= '?v=' . @filemtime($footerLogoLocalPath);
}
$footerLogoAlt = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Dental Clinic';

$footerTagline = isset($CLINIC['footer_tagline']) ? trim((string) $CLINIC['footer_tagline']) : '';
$footerSocialLabel = isset($CLINIC['footer_social_label']) ? trim((string) $CLINIC['footer_social_label']) : 'Follow Us';
$footerFacebookRaw = isset($CLINIC['footer_facebook_url']) ? trim((string) $CLINIC['footer_facebook_url']) : '';
$footerFacebookHref = (preg_match('#^https?://#i', $footerFacebookRaw) === 1) ? $footerFacebookRaw : '';

$footerHoursHeading = isset($CLINIC['footer_hours_heading']) ? trim((string) $CLINIC['footer_hours_heading']) : 'Opening Hours';
$footerHMonLabel = isset($CLINIC['footer_hours_mon_fri_label']) ? trim((string) $CLINIC['footer_hours_mon_fri_label']) : 'Mon - Fri';
$footerHMon = isset($CLINIC['footer_hours_mon_fri']) ? trim((string) $CLINIC['footer_hours_mon_fri']) : '';
$footerHSatLabel = isset($CLINIC['footer_hours_sat_label']) ? trim((string) $CLINIC['footer_hours_sat_label']) : 'Saturday';
$footerHSat = isset($CLINIC['footer_hours_sat']) ? trim((string) $CLINIC['footer_hours_sat']) : '';
$footerHSunLabel = isset($CLINIC['footer_hours_sun_label']) ? trim((string) $CLINIC['footer_hours_sun_label']) : 'Sunday';
$footerHSun = isset($CLINIC['footer_hours_sun']) ? trim((string) $CLINIC['footer_hours_sun']) : '';
$sunClosed = $footerHSun !== '' && stripos($footerHSun, 'closed') !== false;

$clinicNamePlain = isset($CLINIC['clinic_name']) ? (string) $CLINIC['clinic_name'] : '';
$footerCopyrightTpl = isset($CLINIC['footer_copyright_line']) ? (string) $CLINIC['footer_copyright_line'] : '© {year} {clinic_name}. All rights reserved.';
$footerCopyrightOut = str_replace(
    ['{year}', '{clinic_name}'],
    [date('Y'), $clinicNamePlain],
    $footerCopyrightTpl
);

$footerPoweredText = isset($CLINIC['footer_powered_text']) ? trim((string) $CLINIC['footer_powered_text']) : 'Powered by MyDental Philippines';
$footerPoweredRaw = isset($CLINIC['footer_powered_url']) ? trim((string) $CLINIC['footer_powered_url']) : '';
$footerPoweredHref = (preg_match('#^https?://#i', $footerPoweredRaw) === 1) ? $footerPoweredRaw : '';
?>
<footer class="w-full border-t border-slate-200 bg-slate-50 reveal" data-reveal="section">
    <div class="max-w-7xl mx-auto px-6 md:px-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 py-16">
            <div class="space-y-6">
                <div class="flex items-center gap-2 text-slate-900">
                    <img src="<?php echo $footerLogoUrl; ?>" alt="<?php echo $footerLogoAlt; ?>" class="h-10 w-auto object-contain">
                </div>
                <?php if ($footerTagline !== ''): ?>
                <p class="text-slate-500 text-sm leading-relaxed max-w-xs">
                    <?php echo htmlspecialchars($footerTagline, ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <?php endif; ?>
                <?php if ($footerFacebookHref !== ''): ?>
                <div class="flex items-center gap-3">
                    <?php if ($footerSocialLabel !== ''): ?>
                    <span class="text-sm text-slate-500 font-medium"><?php echo htmlspecialchars($footerSocialLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <a class="size-9 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-primary hover:text-white hover:border-primary transition-all duration-300" href="<?php echo htmlspecialchars($footerFacebookHref, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined text-sm">thumb_up</span></a>
                </div>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($footerHoursHeading !== ''): ?>
                <h3 class="font-bold text-slate-900 mb-6 text-sm uppercase tracking-wider"><?php echo htmlspecialchars($footerHoursHeading, ENT_QUOTES, 'UTF-8'); ?></h3>
                <?php endif; ?>
                <ul class="space-y-3 text-sm text-slate-500">
                    <li class="flex justify-between items-center border-b border-slate-200 pb-2"><span><?php echo htmlspecialchars($footerHMonLabel, ENT_QUOTES, 'UTF-8'); ?></span> <span class="text-slate-900 font-medium"><?php echo htmlspecialchars($footerHMon, ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <li class="flex justify-between items-center border-b border-slate-200 pb-2"><span><?php echo htmlspecialchars($footerHSatLabel, ENT_QUOTES, 'UTF-8'); ?></span> <span class="text-slate-900 font-medium"><?php echo htmlspecialchars($footerHSat, ENT_QUOTES, 'UTF-8'); ?></span></li>
                    <li class="flex justify-between items-center border-b border-slate-200 pb-2"><span><?php echo htmlspecialchars($footerHSunLabel, ENT_QUOTES, 'UTF-8'); ?></span> <span class="<?php echo $sunClosed ? 'text-red-500 font-medium' : 'text-slate-900 font-medium'; ?>"><?php echo htmlspecialchars($footerHSun, ENT_QUOTES, 'UTF-8'); ?></span></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-200 pb-10 pt-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($footerCopyrightOut, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if ($footerPoweredText !== ''): ?>
            <?php if ($footerPoweredHref !== ''): ?>
            <a href="<?php echo htmlspecialchars($footerPoweredHref, ENT_QUOTES, 'UTF-8'); ?>" class="text-xs text-slate-500 hover:text-primary transition-colors" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($footerPoweredText, ENT_QUOTES, 'UTF-8'); ?></a>
            <?php else: ?>
            <span class="text-xs text-slate-500"><?php echo htmlspecialchars($footerPoweredText, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</footer>
</div>
<script>
    // Mobile Menu Toggle
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuButton = document.getElementById('mobileMenuButton');
        const mobileMenu = document.getElementById('mobileMenu');
        const menuIcon = document.getElementById('menuIcon');
        
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                const isHidden = mobileMenu.classList.contains('hidden');
                
                if (isHidden) {
                    mobileMenu.classList.remove('hidden');
                    menuIcon.textContent = 'close';
                } else {
                    mobileMenu.classList.add('hidden');
                    menuIcon.textContent = 'menu';
                }
            });
            
            // Close mobile menu when clicking on a link
            const mobileMenuLinks = mobileMenu.querySelectorAll('a');
            mobileMenuLinks.forEach(link => {
                link.addEventListener('click', function() {
                    mobileMenu.classList.add('hidden');
                    menuIcon.textContent = 'menu';
                });
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!mobileMenu.contains(event.target) && !mobileMenuButton.contains(event.target)) {
                    if (!mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                        menuIcon.textContent = 'menu';
                    }
                }
            });
        }
    });
</script>
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
        }, { threshold: 0.15, rootMargin: '0px 0px -10% 0px' });
        elements.forEach(function (el) { observer.observe(el); });
    })();
</script>
</body>
</html>

