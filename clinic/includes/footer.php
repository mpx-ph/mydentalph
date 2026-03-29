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
?>
<footer class="bg-slate-900 text-white pt-20 pb-10 mt-10">
    <div class="max-w-7xl mx-auto px-6 md:px-12">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mb-16">
            <div class="space-y-6">
                <div class="flex items-center gap-2 text-white">
                    <img src="<?php echo $footerLogoUrl; ?>" alt="<?php echo $footerLogoAlt; ?>" class="h-10 w-auto object-contain">
                </div>
                <p class="text-slate-400 text-sm leading-relaxed max-w-xs">
                    Providing top-quality dental care with a gentle touch. Your health and comfort are our top priorities.
                </p>
                <div class="flex items-center gap-3">
                    <span class="text-sm text-slate-400 font-medium">Follow Us</span>
                    <a class="size-9 rounded-full bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-primary hover:text-white transition-all duration-300" href="https://www.facebook.com/DocRickGonzales/" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined text-sm">thumb_up</span></a>
                </div>
            </div>
            <div>
                <h3 class="font-bold text-white mb-6 text-sm uppercase tracking-wider">Opening Hours</h3>
                <ul class="space-y-3 text-sm text-slate-400">
                    <li class="flex justify-between items-center border-b border-slate-800 pb-2"><span>Mon - Fri</span> <span class="text-white font-medium">8:00 AM - 6:00 PM</span></li>
                    <li class="flex justify-between items-center border-b border-slate-800 pb-2"><span>Saturday</span> <span class="text-white font-medium">9:00 AM - 2:00 PM</span></li>
                    <li class="flex justify-between items-center border-b border-slate-800 pb-2"><span>Sunday</span> <span class="text-red-400 font-medium">Closed</span></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <p class="text-xs text-slate-500">© <?php echo date('Y'); ?> Dr. Romarico C. Gonzales Dental Clinic. All rights reserved.</p>
            <a href="https://mydentalph.ct.ws/" class="text-xs text-slate-500 hover:text-slate-300 transition-colors" target="_blank" rel="noopener noreferrer">Powered by MyDental Philippines</a>
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
</body>
</html>

