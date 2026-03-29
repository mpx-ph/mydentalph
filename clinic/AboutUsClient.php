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
$aboutHeroUrl = htmlspecialchars($cuImg('about_hero_image') ?: (BASE_URL . 'Clinic2.jpg'), ENT_QUOTES, 'UTF-8');
$clinicNameDisplay = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : '';
?>
<style>
.about-editorial-word {
    text-shadow: 0 0 12px rgba(43, 140, 238, 0.12);
    letter-spacing: -0.02em;
}
</style>
<div class="relative flex min-h-screen w-full flex-col group/design-root">
<?php include __DIR__ . '/includes/nav_client.php'; ?>
<main class="flex-grow w-full bg-white dark:bg-background-dark text-slate-900 dark:text-slate-100">

<section class="relative flex items-center justify-center pt-28 lg:pt-32 overflow-hidden bg-slate-50 dark:bg-slate-900/40 pb-12 md:pb-16 min-h-[38vh]">
<div class="max-w-7xl mx-auto px-6 md:px-8 text-center relative z-10">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-8">
<?php echo $cu('about_hero_caption_title') ?: 'Established Excellence'; ?>
</div>
<h1 class="font-display text-[clamp(2.25rem,5.5vw,4.5rem)] font-extrabold tracking-[-0.05em] mb-8 leading-[0.95] flex flex-col items-center justify-center">
<span class="block text-slate-900 dark:text-white"><?php echo $cu('about_intro_heading'); ?></span>
<?php if ($clinicNameDisplay !== ''): ?>
<span class="relative block text-center mt-2">
<span class="font-serif italic font-normal text-primary about-editorial-word transform -skew-x-6 inline-block"><?php echo $clinicNameDisplay; ?></span>
</span>
<?php endif; ?>
</h1>
<p class="font-body text-lg md:text-xl max-w-2xl mx-auto mb-4 leading-relaxed text-slate-600 dark:text-slate-400 font-medium text-balance">
<?php echo $cu('about_intro_text'); ?>
</p>
</div>
<div class="absolute inset-0 z-0 opacity-25 pointer-events-none">
<div class="absolute top-1/4 -left-20 w-96 h-96 bg-primary/20 rounded-full blur-[100px]"></div>
<div class="absolute bottom-1/4 -right-20 w-96 h-96 bg-primary/10 rounded-full blur-[100px]"></div>
</div>
</section>

<section class="bg-white dark:bg-slate-900 pt-12 md:pt-16 pb-20 md:pb-28" id="philosophy">
<div class="max-w-[1800px] mx-auto px-6 md:px-10">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20 items-center">
<div class="relative">
<div class="rounded-[2.5rem] overflow-hidden aspect-[4/5] shadow-2xl relative group max-h-[560px] lg:max-h-none mx-auto lg:mx-0">
<img alt="<?php echo $cu('about_hero_caption_title'); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" src="<?php echo $aboutHeroUrl; ?>" loading="lazy" decoding="async" width="800" height="1000">
<div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
</div>
<div class="absolute -bottom-8 md:-bottom-10 -right-2 md:-right-10 bg-white dark:bg-slate-800 p-6 md:p-10 rounded-3xl max-w-sm shadow-2xl border border-slate-100 dark:border-slate-700 hidden md:block">
<p class="font-display font-extrabold text-primary text-xl mb-2"><?php echo $cu('about_hero_caption_title'); ?></p>
<p class="text-slate-600 dark:text-slate-400 font-medium italic text-sm leading-relaxed"><?php echo $cu('about_hero_caption_text'); ?></p>
</div>
</div>
<div class="space-y-8 md:space-y-10">
<div>
<div class="text-primary font-bold text-xs uppercase mb-6 flex gap-4 tracking-[0.3em] items-center">
<span class="w-12 h-[1.5px] bg-primary"></span> <?php echo $cu('about_trusted_title'); ?>
</div>
<h2 class="font-display text-4xl md:text-5xl lg:text-7xl font-extrabold tracking-tighter leading-[0.95] text-slate-900 dark:text-white mb-6 md:mb-8">Our Approach &amp; <br/> <span class="font-serif italic font-normal text-primary about-editorial-word transform -skew-x-6 inline-block">Philosophy</span></h2>
</div>
<p class="text-slate-600 dark:text-slate-400 text-lg md:text-xl leading-relaxed font-medium">
<?php echo $cu('about_why_subtext'); ?>
</p>
<p class="text-slate-600 dark:text-slate-400 text-lg md:text-xl leading-relaxed font-medium">
<?php echo $cu('about_trusted_text'); ?>
</p>
<div class="flex flex-wrap gap-4 items-center pt-4">
<div class="rounded-2xl bg-slate-900 dark:bg-slate-950 text-white px-8 py-6 shadow-lg">
<p class="text-4xl md:text-5xl font-bold tracking-tight"><?php echo $cu('about_years_number'); ?></p>
<p class="text-sm text-slate-300 font-medium mt-1"><?php echo nl2br($cu('about_years_text')); ?></p>
</div>
</div>
</div>
</div>
</div>
</section>

<section class="py-20 md:py-28 bg-slate-50 dark:bg-slate-950/50">
<div class="max-w-[1800px] mx-auto px-6 md:px-10">
<div class="flex flex-col items-center text-center mb-14 md:mb-20">
<h2 class="font-display text-4xl md:text-5xl lg:text-7xl font-extrabold tracking-tighter text-slate-900 dark:text-white mb-4 md:mb-6">Why Patients <span class="font-serif italic font-normal text-primary about-editorial-word transform -skew-x-6 inline-block">Choose Us</span></h2>
<p class="text-slate-600 dark:text-slate-400 text-lg md:text-xl font-medium max-w-2xl text-balance"><?php echo $cu('about_why_heading'); ?></p>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
<div class="group bg-white dark:bg-slate-800 p-8 md:p-12 rounded-[2.5rem] border border-slate-100 dark:border-slate-700 hover:border-primary/25 transition-all duration-700 hover:shadow-2xl">
<div class="w-16 h-16 bg-primary-light dark:bg-primary/20 rounded-2xl flex items-center justify-center mb-8 md:mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl">spa</span>
</div>
<h4 class="font-display text-2xl md:text-3xl font-extrabold mb-4 md:mb-6 tracking-tight text-slate-900 dark:text-white"><?php echo $cu('about_why_1_title'); ?></h4>
<p class="text-slate-600 dark:text-slate-400 text-base md:text-lg leading-relaxed font-medium"><?php echo $cu('about_why_1_text'); ?></p>
</div>
<div class="group bg-primary p-8 md:p-12 rounded-[2.5rem] shadow-[0_40px_80px_-20px_rgba(43,140,238,0.35)] transition-all duration-700 relative overflow-hidden">
<div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-8 md:mb-10 text-white border border-white/20">
<span class="material-symbols-outlined text-3xl">biotech</span>
</div>
<h4 class="font-display text-2xl md:text-3xl font-extrabold mb-4 md:mb-6 tracking-tight text-white"><?php echo $cu('about_why_2_title'); ?></h4>
<p class="text-white/85 text-base md:text-lg leading-relaxed font-medium"><?php echo $cu('about_why_2_text'); ?></p>
</div>
<div class="group bg-white dark:bg-slate-800 p-8 md:p-12 rounded-[2.5rem] border border-slate-100 dark:border-slate-700 hover:border-primary/25 transition-all duration-700 hover:shadow-2xl">
<div class="w-16 h-16 bg-primary-light dark:bg-primary/20 rounded-2xl flex items-center justify-center mb-8 md:mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl">verified_user</span>
</div>
<h4 class="font-display text-2xl md:text-3xl font-extrabold mb-4 md:mb-6 tracking-tight text-slate-900 dark:text-white"><?php echo $cu('about_why_3_title'); ?></h4>
<p class="text-slate-600 dark:text-slate-400 text-base md:text-lg leading-relaxed font-medium"><?php echo $cu('about_why_3_text'); ?></p>
</div>
</div>
</div>
</section>

<section class="py-20 md:py-28 bg-white dark:bg-background-dark">
<div class="max-w-[1800px] mx-auto px-6 md:px-10">
<div class="flex flex-col items-center text-center mb-14 md:mb-20 gap-6">
<span class="text-primary font-bold tracking-wider uppercase text-xs bg-slate-50 dark:bg-slate-800 px-3 py-1 rounded-full shadow-sm border border-slate-100 dark:border-slate-700">Our Team</span>
<h2 class="font-display text-4xl md:text-5xl lg:text-7xl font-extrabold tracking-tighter text-slate-900 dark:text-white">Meet the <span class="font-serif italic font-normal text-primary about-editorial-word transform -skew-x-6 inline-block">Experts</span></h2>
<p class="text-slate-600 dark:text-slate-400 text-lg md:text-2xl font-medium max-w-2xl text-balance">Experienced professionals dedicated to your smile.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-10 max-w-6xl mx-auto">
<div class="group bg-white dark:bg-slate-800 p-5 md:p-6 rounded-[2.5rem] border border-slate-100 dark:border-slate-700 transition-all duration-500 hover:shadow-2xl">
<div class="rounded-3xl overflow-hidden aspect-[4/3] md:h-[400px] mb-6 md:mb-8 bg-primary-light dark:bg-slate-700 relative">
<?php $t1 = $cuImg('about_team_doctor1_image'); ?>
<div class="absolute inset-0 bg-cover bg-center transition-transform duration-700 group-hover:scale-105<?php echo $t1 === '' ? ' bg-slate-200 dark:bg-slate-600' : ''; ?>"<?php echo $t1 !== '' ? ' style="background-image: url(\'' . htmlspecialchars($t1, ENT_QUOTES, 'UTF-8') . '\');"' : ''; ?>></div>
<div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
<div class="absolute bottom-0 left-0 w-full p-5 md:p-6 text-white">
<p class="text-primary font-bold text-xs uppercase tracking-[0.25em] mb-2"><?php echo $cu('about_team_doctor1_title'); ?></p>
<h4 class="font-display text-2xl md:text-3xl font-extrabold"><?php echo $cu('about_team_doctor1_name'); ?></h4>
</div>
</div>
<div class="px-2 md:px-4 pb-2">
<p class="text-slate-600 dark:text-slate-400 text-base leading-relaxed mb-6"><?php echo $cu('about_team_doctor1_bio'); ?></p>
<div class="flex flex-wrap gap-2">
<?php foreach (array_filter(array_map('trim', explode(',', $CLINIC['about_team_doctor1_tags'] ?? ''))) as $tag): ?>
<span class="bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-widest px-3 py-2 rounded-full"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
<?php endforeach; ?>
</div>
</div>
</div>
<div class="group bg-white dark:bg-slate-800 p-5 md:p-6 rounded-[2.5rem] border border-slate-100 dark:border-slate-700 transition-all duration-500 hover:shadow-2xl">
<div class="rounded-3xl overflow-hidden aspect-[4/3] md:h-[400px] mb-6 md:mb-8 bg-primary-light dark:bg-slate-700 relative">
<?php $t2 = $cuImg('about_team_doctor2_image'); ?>
<div class="absolute inset-0 bg-cover bg-center transition-transform duration-700 group-hover:scale-105<?php echo $t2 === '' ? ' bg-slate-200 dark:bg-slate-600' : ''; ?>"<?php echo $t2 !== '' ? ' style="background-image: url(\'' . htmlspecialchars($t2, ENT_QUOTES, 'UTF-8') . '\');"' : ''; ?>></div>
<div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent"></div>
<div class="absolute bottom-0 left-0 w-full p-5 md:p-6 text-white">
<p class="text-primary font-bold text-xs uppercase tracking-[0.25em] mb-2"><?php echo $cu('about_team_doctor2_title'); ?></p>
<h4 class="font-display text-2xl md:text-3xl font-extrabold"><?php echo $cu('about_team_doctor2_name'); ?></h4>
</div>
</div>
<div class="px-2 md:px-4 pb-2">
<p class="text-slate-600 dark:text-slate-400 text-base leading-relaxed mb-6"><?php echo $cu('about_team_doctor2_bio'); ?></p>
<div class="flex flex-wrap gap-2">
<?php foreach (array_filter(array_map('trim', explode(',', $CLINIC['about_team_doctor2_tags'] ?? ''))) as $tag): ?>
<span class="bg-primary/10 text-primary text-[10px] font-bold uppercase tracking-widest px-3 py-2 rounded-full"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
<?php endforeach; ?>
</div>
</div>
</div>
</div>
</div>
</section>

<section class="py-16 md:py-24 px-6 md:px-10">
<div class="mx-auto rounded-[3rem] md:rounded-[4rem] bg-primary relative overflow-hidden flex flex-col items-center text-center shadow-[0_40px_100px_-20px_rgba(43,140,238,0.45)] max-w-6xl py-16 md:py-24 px-8 md:px-16">
<div class="relative z-10 max-w-3xl">
<div class="inline-block px-4 py-1 rounded-full bg-white/20 text-white text-[10px] font-black uppercase tracking-[0.3em] mb-8 md:mb-10">Take the next step</div>
<h2 class="font-display text-3xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tighter leading-[0.9] md:leading-[0.85] mb-6 md:mb-8"><?php echo $cu('about_cta_heading'); ?></h2>
<p class="text-white/75 text-lg md:text-xl max-w-xl mx-auto leading-relaxed mb-8 md:mb-10"><?php echo $cu('about_cta_subtext'); ?></p>
<div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
<a href="<?php echo BASE_URL; ?>BookAppointmentClient.php" class="inline-flex items-center justify-center bg-white text-primary px-10 md:px-14 py-5 md:py-6 rounded-full font-black text-sm uppercase tracking-[0.15em] hover:scale-[1.02] transition-transform shadow-2xl active:scale-95"><?php echo $cu('about_cta_book_text'); ?></a>
<a href="<?php echo BASE_URL; ?>ContactUsClient.php" class="inline-flex items-center justify-center border-2 border-white/40 text-white px-10 md:px-14 py-5 md:py-6 rounded-full font-bold text-sm uppercase tracking-wide hover:bg-white/10 transition-colors"><?php echo $cu('about_cta_contact_text'); ?></a>
</div>
</div>
<div class="absolute top-0 right-0 w-1/3 h-full border-l border-white/10 pointer-events-none hidden md:block"></div>
<div class="absolute -right-20 -bottom-20 w-80 h-80 bg-white/5 rounded-full blur-3xl"></div>
</div>
</section>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
