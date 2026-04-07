<?php
/**
 * Contact Us Page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/tenant_bootstrap.php';
require_once __DIR__ . '/includes/clinic_customization.php';

$pageTitle = isset($CLINIC['contact_page_title']) ? trim((string) $CLINIC['contact_page_title']) : '';
if ($pageTitle === '') {
    $pageTitle = 'Contact Us';
}
require_once __DIR__ . '/includes/header.php';
$cu = function($k) use ($CLINIC) { return isset($CLINIC[$k]) ? htmlspecialchars($CLINIC[$k], ENT_QUOTES, 'UTF-8') : ''; };
?>
<style>
.material-symbols-outlined {
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}
/* Single page background so hero + cards share one continuous surface (no seam). */
.contact-page-shell {
    background-color: #eef5fb;
    background-image:
        radial-gradient(ellipse 140% 70% at 50% -30%, rgba(43, 139, 235, 0.11), transparent 55%),
        radial-gradient(ellipse 90% 50% at 100% 60%, rgba(43, 139, 235, 0.06), transparent 45%),
        radial-gradient(ellipse 80% 45% at 0% 80%, rgba(43, 139, 235, 0.05), transparent 40%),
        linear-gradient(180deg, #ffffff 0%, #f7fafd 22%, #f1f6fc 55%, #edf3fa 100%);
}
.dark .contact-page-shell {
    background-color: rgb(15 23 42);
    background-image:
        radial-gradient(ellipse 140% 70% at 50% -30%, rgba(43, 139, 235, 0.14), transparent 55%),
        radial-gradient(ellipse 90% 50% at 100% 60%, rgba(43, 139, 235, 0.08), transparent 45%),
        linear-gradient(180deg, rgb(15 23 42) 0%, rgb(17 24 39) 45%, rgb(15 23 42) 100%);
}
.editorial-word {
    text-shadow: 0 0 12px color-mix(in srgb, currentColor 12%, transparent);
    letter-spacing: -0.02em;
}
.contact-hero-badge {
    background-color: #e3f0fa;
    color: #5c9bd1;
    letter-spacing: 0.35em;
}
.dark .contact-hero-badge {
    background-color: rgba(92, 155, 209, 0.15);
    color: #7eb8e0;
}
.contact-hero-title {
    color: #1a1a1b;
}
.dark .contact-hero-title {
    color: #f8fafc;
}
.contact-hero-sub {
    color: #5c6670;
}
.dark .contact-hero-sub {
    color: #94a3b8;
}
</style>
<?php include __DIR__ . '/includes/nav_client.php'; ?>

<main class="contact-page-shell pt-24 flex-grow w-full min-h-screen">
<!-- Hero Section -->
<section class="py-20 md:py-28 lg:py-32 text-center px-4 sm:px-6 overflow-hidden reveal" data-reveal="section">
<div class="max-w-3xl mx-auto flex flex-col items-center">
<div class="inline-flex items-center justify-center px-4 py-2 rounded-full contact-hero-badge text-[10px] font-black uppercase mb-8 sm:mb-10 font-headline">
                    <?php echo $cu('contact_hero_badge'); ?>
                </div>
<h1 class="font-headline text-[clamp(2.75rem,7vw,4.75rem)] font-extrabold tracking-[-0.04em] mb-6 sm:mb-8 leading-[1.05]">
<span class="contact-hero-title"><?php echo $cu('contact_hero_title_before'); ?></span><span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block"><?php echo $cu('contact_hero_title_accent'); ?></span>
</h1>
<p class="font-body text-lg sm:text-xl max-w-2xl mx-auto leading-relaxed font-medium contact-hero-sub">
                    <?php echo $cu('contact_hero_subtext'); ?>
                </p>
</div>
</section>
<!-- Contact Information -->
<section class="max-w-[1800px] mx-auto px-10 mb-24 reveal" data-reveal="section">
<div class="max-w-3xl mx-auto space-y-6">
<div class="bg-white dark:bg-slate-800 p-12 rounded-[2.5rem] border border-slate-200/50 dark:border-slate-700 shadow-[0_20px_50px_-15px_rgba(43,139,235,0.05)] space-y-12 transition-all duration-500 hover:-translate-y-1 hover:shadow-[0_30px_60px_-20px_rgba(43,139,235,0.2)]">
<div>
<div class="text-primary font-bold text-xs uppercase mb-10 flex items-center gap-4 tracking-[0.3em] font-headline">
<span class="w-12 h-[1.5px] bg-primary"></span> <?php echo $cu('contact_info_section_label'); ?>
                            </div>
<div class="space-y-10">
<div class="flex items-start gap-6 group">
<div class="w-14 h-14 rounded-2xl bg-primary/10 dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">location_on</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 dark:text-white text-xl mb-1 tracking-tight"><?php echo $cu('contact_address_label'); ?></p>
<p class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed font-body"><?php echo nl2br($cu('contact_address')); ?></p>
<?php if (trim($CLINIC['contact_map_link'] ?? '')): ?>
<a class="text-primary text-sm font-bold mt-2 inline-flex items-center gap-1 hover:underline font-headline" href="<?php echo $cu('contact_map_link'); ?>" target="_blank" rel="noopener noreferrer"><?php echo $cu('contact_directions_link_text'); ?> <span class="material-symbols-outlined text-base">arrow_forward</span></a>
<?php endif; ?>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-14 h-14 rounded-2xl bg-primary/10 dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">call</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 dark:text-white text-xl mb-1 tracking-tight"><?php echo $cu('contact_phone_label'); ?></p>
<a class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed font-body hover:text-primary" href="tel:<?php echo preg_replace('/\s+/', '', $cu('contact_phone')); ?>"><?php echo $cu('contact_phone'); ?></a>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-14 h-14 rounded-2xl bg-primary/10 dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">mail</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 dark:text-white text-xl mb-1 tracking-tight"><?php echo $cu('contact_email_label'); ?></p>
<a class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed font-body hover:text-primary break-all" href="mailto:<?php echo $cu('contact_email'); ?>"><?php echo $cu('contact_email'); ?></a>
</div>
</div>
<div class="flex items-start gap-6 group">
<div class="w-14 h-14 rounded-2xl bg-primary/10 dark:bg-slate-700 flex items-center justify-center text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">schedule</span>
</div>
<div>
<p class="font-headline font-extrabold text-slate-900 dark:text-white text-xl mb-1 tracking-tight"><?php echo $cu('contact_hours_heading_label'); ?></p>
<p class="text-slate-600 dark:text-slate-400 font-medium leading-relaxed font-body">
<span class="block"><?php echo $cu('contact_hours_mon_fri_label'); ?> <?php echo $cu('contact_hours_mon_fri'); ?></span>
<span class="block"><?php echo $cu('contact_hours_sat_label'); ?> <?php echo $cu('contact_hours_sat'); ?></span>
<span class="block"><?php echo $cu('contact_hours_sun_label'); ?> <?php echo $cu('contact_hours_sun'); ?></span>
</p>
</div>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Map Section -->
<?php if (trim($CLINIC['contact_map_embed'] ?? '')): ?>
<section class="max-w-[1800px] mx-auto px-10 mb-24 reveal" data-reveal="section">
<div class="relative w-full h-[400px] md:h-[500px] lg:h-[600px] rounded-[4rem] overflow-hidden bg-slate-100 dark:bg-slate-800 shadow-2xl border border-slate-200 dark:border-slate-700">
<iframe
    class="w-full h-full border-0"
    src="<?php echo $cu('contact_map_embed'); ?>"
    allowfullscreen=""
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade"
    title="<?php echo $cu('contact_map_iframe_title'); ?>">
</iframe>
</div>
</section>
<?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
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

                const mobileMenuLinks = mobileMenu.querySelectorAll('a');
                mobileMenuLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        mobileMenu.classList.add('hidden');
                        menuIcon.textContent = 'menu';
                    });
                });

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
