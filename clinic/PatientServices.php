<?php
/**
 * Patient-facing services page
 */
$pageTitle = 'Our Services';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/tenant_bootstrap.php';
require_once __DIR__ . '/includes/clinic_customization.php';
require_once __DIR__ . '/includes/header.php';

$cu = static function (string $k) use ($CLINIC): string {
    return isset($CLINIC[$k]) ? htmlspecialchars((string) $CLINIC[$k], ENT_QUOTES, 'UTF-8') : '';
};

$bookUrl = htmlspecialchars(BASE_URL . 'BookAppointmentClient.php', ENT_QUOTES, 'UTF-8');
?>
<style>
        .service-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(43, 140, 238, 0.15);
        }
    </style>
<div class="min-h-screen flex flex-col bg-white dark:bg-background-dark">
<?php include __DIR__ . '/includes/nav_client.php'; ?>
<main class="flex-grow w-full">
<!-- Hero Section -->
<section class="max-w-7xl mx-auto px-6 md:px-12 pt-28 lg:pt-32 pb-16 text-center">
<div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-primary/10 text-primary text-[11px] font-extrabold uppercase tracking-[0.2em] mb-8">
                <?php echo $cu('services_hero_badge'); ?>
            </div>
<h1 class="font-display text-5xl md:text-7xl font-extrabold tracking-tight text-slate-900 dark:text-white mb-6">
                <?php echo $cu('services_hero_title_before'); ?><span class="text-primary font-editorial italic font-normal"><?php echo $cu('services_hero_title_accent'); ?></span>
</h1>
<p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto text-xl font-medium leading-relaxed">
                <?php echo $cu('services_hero_subtitle'); ?>
            </p>
</section>
<!-- Vertical Services List -->
<section class="max-w-5xl mx-auto px-6 md:px-12 pb-20 space-y-6">
<!-- Service Card 1 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm gap-8">
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-display">General Dentistry</h3>
<p class="text-slate-600 dark:text-slate-400 font-medium text-lg leading-relaxed">Preventative care, professional cleanings, and precise digital diagnostics designed to maintain your peak oral health and wellness for a lifetime.</p>
</div>
<div class="shrink-0">
<a href="<?php echo $bookUrl; ?>" class="inline-flex px-8 py-4 bg-primary hover:bg-primary-dark text-white rounded-xl font-bold text-sm uppercase tracking-widest transition-all items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</a>
</div>
</div>
<!-- Service Card 2 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm gap-8">
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-display">Cosmetic Dentistry</h3>
<p class="text-slate-600 dark:text-slate-400 font-medium text-lg leading-relaxed">Artistic smile transformations using premium porcelain veneers, professional whitening, and digital smile design for natural-looking perfection.</p>
</div>
<div class="shrink-0">
<a href="<?php echo $bookUrl; ?>" class="inline-flex px-8 py-4 bg-primary hover:bg-primary-dark text-white rounded-xl font-bold text-sm uppercase tracking-widest transition-all items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</a>
</div>
</div>
<!-- Service Card 3 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm gap-8">
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-display">Orthodontics</h3>
<p class="text-slate-600 dark:text-slate-400 font-medium text-lg leading-relaxed">Advanced alignment solutions including clear aligners and modern braces tailored for both adults and teenagers to achieve a confident bite.</p>
</div>
<div class="shrink-0">
<a href="<?php echo $bookUrl; ?>" class="inline-flex px-8 py-4 bg-primary hover:bg-primary-dark text-white rounded-xl font-bold text-sm uppercase tracking-widest transition-all items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</a>
</div>
</div>
<!-- Service Card 4 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm gap-8">
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-display">Oral Surgery</h3>
<p class="text-slate-600 dark:text-slate-400 font-medium text-lg leading-relaxed">Specialized procedures including wisdom tooth extraction and dental implants performed with surgical precision and optimal patient comfort.</p>
</div>
<div class="shrink-0">
<a href="<?php echo $bookUrl; ?>" class="inline-flex px-8 py-4 bg-primary hover:bg-primary-dark text-white rounded-xl font-bold text-sm uppercase tracking-widest transition-all items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</a>
</div>
</div>
<!-- Service Card 5 -->
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm gap-8">
<div class="flex-grow">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-display">Pediatric Dentistry</h3>
<p class="text-slate-600 dark:text-slate-400 font-medium text-lg leading-relaxed">Gentle, fun-focused dental care for our youngest patients, building a foundation for healthy smiles that last a lifetime in a warm environment.</p>
</div>
<div class="shrink-0">
<a href="<?php echo $bookUrl; ?>" class="inline-flex px-8 py-4 bg-primary hover:bg-primary-dark text-white rounded-xl font-bold text-sm uppercase tracking-widest transition-all items-center gap-2">
                        Book Appointment
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</a>
</div>
</div>
</section>
<!-- Final CTA Section -->
<section class="max-w-7xl mx-auto px-6 md:px-12 pb-24">
<div class="rounded-3xl bg-primary p-12 md:p-20 text-center text-white shadow-xl shadow-primary/20">
<h2 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight mb-6">Ready to start your journey?</h2>
<p class="text-white/80 text-lg md:text-xl max-w-xl mx-auto mb-10 font-medium">Join thousands of happy patients who trust us with their oral health and aesthetic transformations.</p>
<a href="<?php echo $bookUrl; ?>" class="inline-block bg-white text-primary px-12 py-5 rounded-full font-extrabold text-sm uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-lg active:scale-95">
                    Book Your Consultation
                </a>
</div>
</section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</div>
