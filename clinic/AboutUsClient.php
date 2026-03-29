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
$slug = $currentTenantSlug ?? ($_SESSION['public_tenant_slug'] ?? null);
if (!function_exists('clinic_link')) {
    function clinic_link(string $path, ?string $slug, string $fallback): string {
        if ($slug) {
            $slugPart = '/' . rawurlencode($slug);
            if ($path === '' || $path === '/') return $slugPart . '/';
            return $slugPart . '/' . ltrim($path, '/');
        }
        return $fallback;
    }
}
$servicesHref = htmlspecialchars(clinic_link('services', $slug, BASE_URL . 'PatientServices.php'), ENT_QUOTES, 'UTF-8');
$contactHref = htmlspecialchars(clinic_link('contact', $slug, BASE_URL . 'ContactUsClient.php'), ENT_QUOTES, 'UTF-8');
$aboutHeroImg = $cuImg('about_hero_image') ?: 'https://lh3.googleusercontent.com/aida-public/AB6AXuChrPelgrWNz5y22-jPblZ3xvf8IAD-hR1qnlkZRBw16FAQOI7uFlUaoioxaqF_x2Tr_73XIDwSMCHMnnGWH4FcoyVGYkRZzDOZXa4YC8CC70iVT00Sq15rLCNydE_JeNMoklsezLzYditmIY3X4rCb0sgfJXYcFV0Iz7CG1Jfy1qaunSseGzYkrRWRBHVDFFKosGZtshcOp3idiAppl8jDam5CVK0iOyXyyYqeeUb7D4355tXxps3A8-Oic9oPkbqwFMlxa5DVUm0';
?>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&amp;display=swap" rel="stylesheet"/>
<style>
.font-headline { font-family: Manrope, ui-sans-serif, system-ui, sans-serif; }
.font-body { font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
.font-editorial { font-family: "Playfair Display", Georgia, serif; }
.editorial-word {
    text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
    letter-spacing: -0.02em;
}
</style>
<div class="relative flex min-h-screen w-full flex-col bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100">
<?php include __DIR__ . '/includes/nav_client.php'; ?>
<main>
<!-- Hero Section -->
<section class="relative flex items-center justify-center pt-32 overflow-hidden bg-slate-50 dark:bg-slate-900 pb-12 min-h-[40vh]">
<div class="max-w-7xl mx-auto px-8 text-center relative z-10">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-8 font-headline">
                    <?php echo $cu('about_hero_caption_title') ?: 'Established Excellence'; ?>
                </div>
<h1 class="font-headline text-[clamp(2.5rem,6vw,5rem)] font-extrabold tracking-[-0.05em] mb-10 leading-[0.9] flex flex-col items-center justify-center text-slate-900 dark:text-white">
<span class="block"><?php echo $cu('about_intro_heading'); ?></span>
</h1>
<p class="font-body text-xl max-w-2xl mx-auto mb-12 leading-relaxed text-slate-600 dark:text-slate-400 font-medium">
                    <?php echo $cu('about_intro_text'); ?>
                </p>
</div>
<div class="absolute inset-0 z-0 opacity-20 pointer-events-none">
<div class="absolute top-1/4 -left-20 w-96 h-96 bg-primary/20 rounded-full blur-[100px]"></div>
<div class="absolute bottom-1/4 -right-20 w-96 h-96 bg-primary/10 rounded-full blur-[100px]"></div>
</div>
</section>
<!-- The Curator's Philosophy -->
<section class="bg-white dark:bg-slate-950 pt-16 pb-32" id="philosophy">
<div class="max-w-[1800px] mx-auto px-10">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
<div class="relative">
<div class="rounded-[2.5rem] overflow-hidden aspect-[4/5] shadow-2xl relative group">
<img alt="" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" src="<?php echo htmlspecialchars($aboutHeroImg, ENT_QUOTES, 'UTF-8'); ?>"/>
<div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
</div>
<div class="absolute -bottom-10 -right-10 bg-white dark:bg-slate-800 p-10 rounded-3xl max-w-sm shadow-2xl border border-slate-200/50 dark:border-slate-700 hidden md:block">
<p class="font-headline font-extrabold text-primary text-2xl mb-3"><?php echo $cu('about_trusted_title'); ?></p>
<p class="text-slate-600 dark:text-slate-400 font-medium italic font-body"><?php echo $cu('about_trusted_text'); ?></p>
</div>
</div>
<div class="space-y-10">
<div>
<div class="text-primary font-bold text-xs uppercase mb-6 flex gap-4 tracking-[0.3em] items-center font-headline">
<span class="w-12 h-[1.5px] bg-primary"></span> <?php echo $cu('about_why_heading'); ?>
                            </div>
<h2 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tighter leading-[0.9] text-slate-900 dark:text-white mb-8">Our <br/> <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Philosophy</span></h2>
</div>
<p class="text-slate-600 dark:text-slate-400 text-xl leading-relaxed font-medium font-body">
                            <?php echo $cu('about_team_doctor1_bio'); ?>
                        </p>
<p class="text-slate-600 dark:text-slate-400 text-xl leading-relaxed font-medium font-body">
                            <?php echo $cu('about_team_doctor2_bio'); ?>
                        </p>
<div class="pt-6">
<a href="<?php echo $servicesHref; ?>" class="group relative px-10 py-5 bg-primary/10 dark:bg-slate-800 text-primary font-bold rounded-full overflow-hidden transition-all hover:bg-primary hover:text-white active:scale-95 inline-flex items-center font-headline">
<span class="relative z-10 flex items-center gap-3">
                                    Explore our services
                                    <span class="material-symbols-outlined text-xl">arrow_right_alt</span>
</span>
</a>
</div>
</div>
</div>
</div>
</section>
<!-- Clinical Standards -->
<section class="py-32 bg-slate-50 dark:bg-slate-900/50">
<div class="max-w-[1800px] mx-auto px-10">
<div class="flex flex-col items-center text-center mb-20">
<h2 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tighter text-slate-900 dark:text-white mb-6">Clinical <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Standards</span></h2>
<p class="text-slate-600 dark:text-slate-400 text-xl font-medium max-w-2xl font-body"><?php echo $cu('about_intro_text'); ?></p>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-8">
<div class="group bg-white dark:bg-slate-800 p-12 rounded-[2.5rem] border border-slate-200/50 dark:border-slate-700 hover:border-primary/20 transition-all duration-700 hover:shadow-2xl">
<div class="w-16 h-16 bg-primary/10 dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl">biotech</span>
</div>
<h4 class="font-headline text-3xl font-extrabold mb-6 tracking-tight text-slate-900 dark:text-white"><?php echo $cu('about_why_1_title'); ?></h4>
<p class="text-slate-600 dark:text-slate-400 text-lg leading-relaxed font-medium font-body"><?php echo $cu('about_why_1_text'); ?></p>
</div>
<div class="group bg-primary p-12 rounded-[2.5rem] shadow-[0_40px_80px_-20px_rgba(43,139,235,0.3)] transition-all duration-700 relative overflow-hidden">
<div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-10 text-white border border-white/20">
<span class="material-symbols-outlined text-3xl">precision_manufacturing</span>
</div>
<h4 class="font-headline text-3xl font-extrabold mb-6 tracking-tight text-white"><?php echo $cu('about_why_2_title'); ?></h4>
<p class="text-white/80 text-lg leading-relaxed font-medium font-body"><?php echo $cu('about_why_2_text'); ?></p>
</div>
<div class="group bg-white dark:bg-slate-800 p-12 rounded-[2.5rem] border border-slate-200/50 dark:border-slate-700 hover:border-primary/20 transition-all duration-700 hover:shadow-2xl">
<div class="w-16 h-16 bg-primary/10 dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl">verified_user</span>
</div>
<h4 class="font-headline text-3xl font-extrabold mb-6 tracking-tight text-slate-900 dark:text-white"><?php echo $cu('about_why_3_title'); ?></h4>
<p class="text-slate-600 dark:text-slate-400 text-lg leading-relaxed font-medium font-body"><?php echo $cu('about_why_3_text'); ?></p>
</div>
</div>
</div>
</section>
<!-- Our Elite Team -->
<section class="py-32 bg-white dark:bg-slate-950">
<div class="max-w-[1800px] mx-auto px-10">
<div class="flex flex-col items-center text-center mb-20 gap-8">
<h2 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tighter text-slate-900 dark:text-white mb-6">Our <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block">Elite Team</span></h2>
<p class="text-slate-600 dark:text-slate-400 text-2xl font-medium font-body">Meet the experienced professionals dedicated to your smile.</p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 <?php echo trim($CLINIC['about_team_doctor3_name'] ?? '') !== '' ? 'lg:grid-cols-3' : 'lg:grid-cols-2'; ?> gap-10">
<?php
$teamCards = [
    ['about_team_doctor1_title', 'about_team_doctor1_name', 'about_team_doctor1_bio', 'about_team_doctor1_image', 'about_team_doctor1_tags'],
    ['about_team_doctor2_title', 'about_team_doctor2_name', 'about_team_doctor2_bio', 'about_team_doctor2_image', 'about_team_doctor2_tags'],
];
if (trim($CLINIC['about_team_doctor3_name'] ?? '') !== '') {
    $teamCards[] = ['about_team_doctor3_title', 'about_team_doctor3_name', 'about_team_doctor3_bio', 'about_team_doctor3_image', 'about_team_doctor3_tags'];
}
foreach ($teamCards as $keys) {
    list($tk, $nk, $bk, $ik, $tagk) = $keys;
    $imgUrl = $cuImg($ik);
    $tags = array_filter(array_map('trim', explode(',', $CLINIC[$tagk] ?? '')));
    ?>
<div class="group bg-white dark:bg-slate-800 p-6 rounded-[2.5rem] border border-slate-200/50 dark:border-slate-700 transition-all duration-500 hover:shadow-2xl">
<div class="rounded-3xl overflow-hidden h-[450px] mb-8 bg-slate-100 dark:bg-slate-700">
<?php if ($imgUrl !== ''): ?>
<img alt="<?php echo $cu($nk); ?>" class="w-full h-full object-cover grayscale transition-all duration-700 group-hover:grayscale-0 group-hover:scale-105" src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>"/>
<?php else: ?>
<div class="w-full h-full flex items-center justify-center text-slate-400"><span class="material-symbols-outlined text-6xl">person</span></div>
<?php endif; ?>
</div>
<div class="px-4 pb-4">
<p class="text-primary font-bold text-xs uppercase tracking-[0.3em] mb-4 font-headline"><?php echo $cu($tk); ?></p>
<h4 class="font-headline text-3xl font-extrabold mb-4 text-slate-900 dark:text-white"><?php echo $cu($nk); ?></h4>
<p class="text-slate-600 dark:text-slate-400 text-lg font-medium mb-8 font-body"><?php echo $cu($bk); ?></p>
<?php if ($tags): ?>
<div class="flex flex-wrap gap-2">
<?php foreach ($tags as $tag): ?>
<span class="bg-primary/5 text-primary text-[10px] font-black uppercase tracking-widest px-4 py-2 rounded-full font-headline"><?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
<?php } ?>
</div>
</div>
</section>
<!-- Final CTA -->
<section class="py-24 px-10">
<div class="mx-auto rounded-[4rem] bg-primary relative overflow-hidden flex flex-col items-center text-center shadow-[0_40px_100px_-20px_rgba(43,139,235,0.4)] max-w-6xl py-24 px-10 md:px-20">
<div class="relative z-10 max-w-3xl">
<div class="inline-block px-4 py-1 rounded-full bg-white/20 text-white text-[10px] font-black uppercase tracking-[0.3em] mb-10 font-headline">
                        Your next step
                    </div>
<h2 class="font-headline text-5xl font-extrabold text-white tracking-tighter leading-[0.85] md:text-6xl mb-8"><?php echo $cu('about_cta_heading'); ?></h2>
<p class="text-white/70 text-xl md:text-2xl max-w-xl mx-auto leading-relaxed mb-10 font-body"><?php echo $cu('about_cta_subtext'); ?></p>
<div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
<a href="<?php echo htmlspecialchars(BASE_URL . 'BookAppointmentClient.php', ENT_QUOTES, 'UTF-8'); ?>" class="inline-block bg-white text-primary px-16 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-2xl active:scale-95 font-headline">
                        <?php echo $cu('about_cta_book_text'); ?>
                    </a>
<a href="<?php echo $contactHref; ?>" class="inline-block border-2 border-white/40 text-white px-16 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] hover:bg-white/10 transition-all font-headline">
                        <?php echo $cu('about_cta_contact_text'); ?>
                    </a>
</div>
</div>
<div class="absolute top-0 right-0 w-1/3 h-full border-l border-white/10 pointer-events-none"></div>
<div class="absolute -right-20 -bottom-20 w-80 h-80 bg-white/5 rounded-full blur-3xl"></div>
</div>
</section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</div>
