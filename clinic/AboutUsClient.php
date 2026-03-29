<?php
/**
 * About Us Page
 */
$pageTitle = 'About Us';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/tenant_bootstrap.php';
require_once __DIR__ . '/includes/clinic_customization.php';
require_once __DIR__ . '/includes/header.php';
$cu = function($k) use ($CLINIC) { return isset($CLINIC[$k]) ? htmlspecialchars($CLINIC[$k], ENT_QUOTES, 'UTF-8') : ''; };
$cuImg = function($k) use ($CLINIC) {
    $v = isset($CLINIC[$k]) ? trim($CLINIC[$k]) : '';
    if ($v === '') return '';
    return (strpos($v, 'http') === 0) ? $v : (BASE_URL . ltrim($v, '/'));
};
?>
<div class="relative flex min-h-screen w-full flex-col group/design-root">
<?php include __DIR__ . '/includes/nav_client.php'; ?>
<main class="flex-grow w-full bg-surface-light dark:bg-background-dark">
<section class="w-full relative py-20 lg:py-28 px-4 md:px-10 flex flex-col items-center text-center overflow-hidden bg-grid-slate mt-16 lg:mt-24">
<div class="absolute top-0 left-0 w-full h-full overflow-hidden -z-10 pointer-events-none">
<div class="absolute -top-[10%] left-1/4 w-[40%] h-[40%] bg-primary/20 dark:bg-primary/10 rounded-full blur-[100px] opacity-60"></div>
<div class="absolute top-[40%] right-0 w-[30%] h-[40%] bg-sky-200/40 dark:bg-sky-900/10 rounded-full blur-[100px] opacity-60"></div>
</div>
<div class="max-w-[800px] flex flex-col items-center gap-6 z-10">
<h1 class="text-slate-900 dark:text-white text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.1] text-balance">
                    <?php echo $cu('about_intro_heading'); ?>
</h1>
<p class="text-slate-600 dark:text-slate-400 text-lg md:text-xl leading-relaxed max-w-2xl text-balance">
                    <?php echo $cu('about_intro_text'); ?>
                </p>
</div>
<div class="mt-16 w-full max-w-[1100px] grid grid-cols-1 md:grid-cols-12 gap-5 h-auto md:h-[420px]">
<div class="md:col-span-8 h-64 md:h-full rounded-3xl overflow-hidden shadow-soft relative group border border-slate-100 dark:border-slate-800">
<div class="absolute inset-0 bg-gradient-to-t from-slate-900/70 to-transparent z-10"></div>
<div class="absolute bottom-8 left-8 z-20 text-white text-left max-w-md">
<div class="flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-primary text-[20px]">medical_services</span>
<p class="text-xs font-bold uppercase tracking-wider opacity-90"><?php echo $cu('about_hero_caption_title'); ?></p>
</div>
<p class="text-2xl font-bold leading-tight"><?php echo $cu('about_hero_caption_text'); ?></p>
</div>
<div class="w-full h-full bg-cover bg-center transition-transform duration-700 group-hover:scale-105" style='background-image: url("<?php echo $cuImg('about_hero_image') ?: (BASE_URL . 'Clinic2.jpg'); ?>");'></div>
</div>
<div class="md:col-span-4 flex flex-col gap-5 h-full">
<div class="flex-1 bg-white dark:bg-slate-800 rounded-3xl p-8 shadow-card border border-slate-100 dark:border-slate-700 flex flex-col justify-center items-start group hover:shadow-card-hover transition-all duration-300">
<div class="w-14 h-14 rounded-2xl bg-primary-light dark:bg-primary/20 text-primary flex items-center justify-center mb-5 group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-[32px]">verified_user</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2"><?php echo $cu('about_trusted_title'); ?></h3>
<p class="text-sm text-slate-500 dark:text-slate-400 leading-relaxed"><?php echo $cu('about_trusted_text'); ?></p>
</div>
<div class="flex-1 bg-slate-900 dark:bg-slate-950 text-white rounded-3xl p-8 shadow-lg flex flex-col justify-center items-start relative overflow-hidden group">
<div class="absolute top-0 right-0 w-40 h-40 bg-primary/20 rounded-full blur-3xl -mr-10 -mt-10 group-hover:bg-primary/30 transition-colors"></div>
<h3 class="text-5xl font-bold mb-2 tracking-tight"><?php echo $cu('about_years_number'); ?></h3>
<p class="text-base text-slate-300 font-medium"><?php echo nl2br($cu('about_years_text')); ?></p>
</div>
</div>
</div>
</section>
<section class="w-full py-24 bg-white dark:bg-slate-900 border-y border-slate-100 dark:border-slate-800">
<div class="max-w-[1200px] mx-auto px-4 md:px-10">
<div class="text-center mb-16">
<h2 class="text-3xl md:text-4xl font-bold text-slate-900 dark:text-white tracking-tight"><?php echo $cu('about_why_heading'); ?></h2>
<p class="mt-4 text-slate-600 dark:text-slate-400"><?php echo $cu('about_why_subtext'); ?></p>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-8 lg:gap-12">
<div class="group flex flex-col items-center text-center p-6 rounded-3xl hover:bg-surface-light dark:hover:bg-slate-800/50 transition-colors duration-300">
<div class="w-20 h-20 rounded-full bg-primary-light dark:bg-primary/10 text-primary flex items-center justify-center transition-transform group-hover:scale-110 duration-300 mb-6 shadow-sm">
<span class="material-symbols-outlined text-[36px]">spa</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3"><?php echo $cu('about_why_1_title'); ?></h3>
<p class="text-slate-500 dark:text-slate-400 leading-relaxed"><?php echo $cu('about_why_1_text'); ?></p>
</div>
<div class="group flex flex-col items-center text-center p-6 rounded-3xl hover:bg-surface-light dark:hover:bg-slate-800/50 transition-colors duration-300">
<div class="w-20 h-20 rounded-full bg-primary-light dark:bg-primary/10 text-primary flex items-center justify-center transition-transform group-hover:scale-110 duration-300 mb-6 shadow-sm">
<span class="material-symbols-outlined text-[36px]">biotech</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3"><?php echo $cu('about_why_2_title'); ?></h3>
<p class="text-slate-500 dark:text-slate-400 leading-relaxed"><?php echo $cu('about_why_2_text'); ?></p>
</div>
<div class="group flex flex-col items-center text-center p-6 rounded-3xl hover:bg-surface-light dark:hover:bg-slate-800/50 transition-colors duration-300">
<div class="w-20 h-20 rounded-full bg-primary-light dark:bg-primary/10 text-primary flex items-center justify-center transition-transform group-hover:scale-110 duration-300 mb-6 shadow-sm">
<span class="material-symbols-outlined text-[36px]">favorite</span>
</div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white mb-3"><?php echo $cu('about_why_3_title'); ?></h3>
<p class="text-slate-500 dark:text-slate-400 leading-relaxed"><?php echo $cu('about_why_3_text'); ?></p>
</div>
</div>
</div>
</section>
<section class="w-full py-24 bg-surface-light dark:bg-background-dark relative overflow-hidden">
<div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
<div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-primary/5 rounded-full blur-[120px]"></div>
</div>
<div class="max-w-[1100px] mx-auto px-4 md:px-10 relative z-10">
<div class="text-center mb-16">
<span class="text-primary font-bold tracking-wider uppercase text-xs bg-white dark:bg-slate-800 px-3 py-1 rounded-full shadow-sm border border-slate-100 dark:border-slate-700">Our Team</span>
<h2 class="mt-4 text-3xl md:text-5xl font-bold text-slate-900 dark:text-white tracking-tight">Meet the Experts</h2>
<p class="mt-5 text-slate-600 dark:text-slate-400 text-lg max-w-2xl mx-auto">Get to know the experienced and compassionate professionals dedicated to your smile.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">
<div class="group relative flex flex-col bg-white dark:bg-slate-800 rounded-[2.5rem] p-4 shadow-card hover:shadow-card-hover transition-all duration-300 border border-slate-100 dark:border-slate-700">
<div class="relative w-full aspect-[4/3] overflow-hidden rounded-[2rem] mb-6">
<div aria-hidden="true" class="absolute inset-0 bg-slate-100 dark:bg-slate-700 animate-pulse"></div>
<div class="absolute inset-0 bg-cover bg-center transition-transform duration-700 group-hover:scale-105" style='background-image: url("<?php echo $cuImg('about_team_doctor1_image'); ?>");'></div>
<div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-60"></div>
<div class="absolute bottom-0 left-0 w-full p-6 text-white translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
<h3 class="text-2xl font-bold drop-shadow-md"><?php echo $cu('about_team_doctor1_name'); ?></h3>
<p class="text-sm font-medium text-white/90 uppercase tracking-wide drop-shadow-md"><?php echo $cu('about_team_doctor1_title'); ?></p>
</div>
</div>
<div class="px-4 pb-4 flex flex-col gap-4">
<p class="text-slate-600 dark:text-slate-400 text-base leading-relaxed">
                                <?php echo $cu('about_team_doctor1_bio'); ?>
                            </p>
<div class="flex items-center mt-2 pt-4 border-t border-slate-100 dark:border-slate-700">
<div class="flex gap-2">
<?php foreach (array_filter(array_map('trim', explode(',', $CLINIC['about_team_doctor1_tags'] ?? ''))) as $tag): ?>
<span class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-slate-50 dark:bg-slate-700 text-xs font-medium text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
<?php endforeach; ?>
</div>
</div>
</div>
</div>
<div class="group relative flex flex-col bg-white dark:bg-slate-800 rounded-[2.5rem] p-4 shadow-card hover:shadow-card-hover transition-all duration-300 border border-slate-100 dark:border-slate-700">
<div class="relative w-full aspect-[4/3] overflow-hidden rounded-[2rem] mb-6">
<div aria-hidden="true" class="absolute inset-0 bg-slate-100 dark:bg-slate-700 animate-pulse"></div>
<div class="absolute inset-0 bg-cover bg-center transition-transform duration-700 group-hover:scale-105" style='background-image: url("<?php echo $cuImg('about_team_doctor2_image'); ?>");'></div>
<div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-60"></div>
<div class="absolute bottom-0 left-0 w-full p-6 text-white translate-y-2 group-hover:translate-y-0 transition-transform duration-300">
<h3 class="text-2xl font-bold drop-shadow-md"><?php echo $cu('about_team_doctor2_name'); ?></h3>
<p class="text-sm font-medium text-white/90 uppercase tracking-wide drop-shadow-md"><?php echo $cu('about_team_doctor2_title'); ?></p>
</div>
</div>
<div class="px-4 pb-4 flex flex-col gap-4">
<p class="text-slate-600 dark:text-slate-400 text-base leading-relaxed">
                                 <?php echo $cu('about_team_doctor2_bio'); ?>
                            </p>
<div class="flex items-center mt-2 pt-4 border-t border-slate-100 dark:border-slate-700">
<div class="flex gap-2">
<?php foreach (array_filter(array_map('trim', explode(',', $CLINIC['about_team_doctor2_tags'] ?? ''))) as $tag): ?>
<span class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-slate-50 dark:bg-slate-700 text-xs font-medium text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
<?php endforeach; ?>
</div>
</div>
</div>
</div>
</div>
</div>
</section>
<section class="w-full px-4 md:px-10 py-24 bg-white dark:bg-background-dark">
<div class="max-w-[1200px] mx-auto relative rounded-[2.5rem] bg-primary overflow-hidden shadow-2xl shadow-primary/25">
<div class="absolute top-0 right-0 -mr-20 -mt-20 w-96 h-96 rounded-full bg-white/10 blur-3xl"></div>
<div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full bg-white/10 blur-3xl"></div>
<div class="relative z-10 px-8 py-16 md:p-24 text-center flex flex-col items-center gap-8">
<div class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center text-white mb-2">
<span class="material-symbols-outlined text-[32px]">calendar_month</span>
</div>
<h2 class="text-3xl md:text-5xl font-bold text-white tracking-tight leading-tight max-w-3xl">
                        <?php echo $cu('about_cta_heading'); ?>
                    </h2>
<p class="text-white/90 text-lg md:text-xl max-w-2xl font-medium">
                        <?php echo $cu('about_cta_subtext'); ?>
                    </p>
<div class="flex flex-col sm:flex-row gap-4 w-full justify-center mt-4">
<a href="<?php echo BASE_URL; ?>BookAppointmentClient.php" class="flex items-center justify-center rounded-full h-14 px-10 bg-white text-primary hover:bg-slate-50 transition-all shadow-lg text-base font-bold tracking-wide transform hover:-translate-y-1">
                            <?php echo $cu('about_cta_book_text'); ?>
                        </a>
<a href="<?php echo BASE_URL; ?>ContactUsClient.php" class="flex items-center justify-center rounded-full h-14 px-10 bg-transparent border-2 border-white/30 text-white hover:bg-white/10 transition-colors text-base font-bold tracking-wide">
                            <?php echo $cu('about_cta_contact_text'); ?>
                        </a>
</div>
</div>
</div>
</section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>

