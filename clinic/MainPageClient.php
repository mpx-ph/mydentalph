<?php
/**
 * Main Page - Homepage
 */
$pageTitle = 'Dental Clinic - Patient Home';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/tenant_bootstrap.php';
require_once __DIR__ . '/includes/clinic_customization.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
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
$cu = function (string $k, string $default = '') use ($CLINIC): string {
    $v = isset($CLINIC[$k]) ? trim((string) $CLINIC[$k]) : '';
    if ($v === '' && $default !== '') {
        $v = $default;
    }
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
};
$cuMultiline = function (string $k, string $default = '') use ($CLINIC): string {
    $v = isset($CLINIC[$k]) ? trim((string) $CLINIC[$k]) : '';
    if ($v === '' && $default !== '') {
        $v = $default;
    }
    return nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), false);
};
$slug = $currentTenantSlug ?? ($_SESSION['public_tenant_slug'] ?? null);
$setAppointmentHref = isLoggedIn('client')
    ? clinic_link('download', $slug, BASE_URL . 'DownloadApp.php')
    : clinic_link('login', $slug, BASE_URL . 'Login.php');
$cuImg = function($k) use ($CLINIC) {
    $v = isset($CLINIC[$k]) ? trim($CLINIC[$k]) : '';
    if ($v === '') return '';
    return (strpos($v, 'http') === 0) ? $v : (BASE_URL . ltrim($v, '/'));
};
$servicesHref = htmlspecialchars(clinic_link('services', $slug, BASE_URL . 'PatientServices.php'), ENT_QUOTES, 'UTF-8');
$heroBg = $cuImg('main_hero_image');
if ($heroBg === '') {
    $heroBg = 'https://lh3.googleusercontent.com/aida-public/AB6AXuAKSToKSubciBNDHHIIhBLQWuwv70uupwQdixl7SdJZDmgnDrO7KwPH0nU9Tuyv8aNshhWTfTeP75EKGGbML5Ge0AweBfsy2V4AmVWId5nTGtpGe6_7fZcwoTag1cM1PJdBpkLGRE47XjINHeAHov0gmJegOGXOaY4Xsbphb11ypnokm_GnMy42Lk5byi_6B13so8CQ8mAtQE0e6twPfwumg6xkxXcDNMUMRCwqnTWdqYYK6EWku_TTChy4ON47ltF4FcaFeaL3nCw';
}
$heroBgEsc = htmlspecialchars($heroBg, ENT_QUOTES, 'UTF-8');
?>
<style>
.glass-card {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
.editorial-word {
    text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
    letter-spacing: -0.02em;
}
.step-connector {
    background: linear-gradient(90deg, transparent, rgba(43, 139, 235, 0.25) 20%, rgba(43, 139, 235, 0.25) 80%, transparent);
}
</style>
<?php include __DIR__ . '/includes/nav_client.php'; ?>

<main class="bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100">
<!-- Centered Hero Section -->
<section class="relative min-h-[90vh] flex items-center justify-center pt-20 overflow-hidden" style="background-image: linear-gradient(to bottom, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.8)), url('<?php echo $heroBgEsc; ?>'); background-size: cover; background-position: center;">
<div class="max-w-7xl mx-auto w-full px-10 relative z-10 flex flex-col items-center text-center justify-center">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-8 font-headline">
                <?php
                $heroBadge = trim((string) ($CLINIC['main_hero_badge'] ?? ''));
                if ($heroBadge === '') {
                    $heroBadge = trim((string) ($CLINIC['main_services_heading'] ?? ''));
                }
                echo htmlspecialchars($heroBadge !== '' ? $heroBadge : 'Premium Patient Care', ENT_QUOTES, 'UTF-8');
                ?>
            </div>
<h1 class="font-headline text-[clamp(3.5rem,8vw,8rem)] font-extrabold tracking-[-0.05em] mb-10 leading-[0.85] flex flex-col items-center justify-center text-slate-900 dark:text-white"><span class="block">Welcome to</span><span class="relative block text-center mt-2"><span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block"><?php echo $cu('clinic_name'); ?></span></span></h1>
<p class="font-body text-xl max-w-2xl mb-12 leading-relaxed text-slate-600 dark:text-slate-400 font-medium">
                <?php echo $cuMultiline('main_hero_subtext', 'Experience modern dentistry in a sterile, calming environment. We combine clinical excellence with a gentle touch to ensure your long-term oral health.'); ?>
            </p>
<div class="flex flex-col items-center justify-center">
<a href="<?php echo htmlspecialchars($setAppointmentHref, ENT_QUOTES, 'UTF-8'); ?>" class="group relative px-12 py-5 bg-primary text-white font-bold rounded-full overflow-hidden transition-all hover:pr-16 active:scale-95 font-headline inline-flex items-center">
<span class="relative z-10"><?php echo isLoggedIn('client') ? $cu('main_hero_cta_logged_in', 'Download Our App') : $cu('main_hero_cta_guest', 'Set Appointment'); ?></span>
<span class="material-symbols-outlined absolute right-6 opacity-0 group-hover:opacity-100 transition-all">arrow_right_alt</span>
</a>
</div>
</div>
<div class="absolute inset-0 z-0 opacity-20 pointer-events-none">
<div class="absolute top-1/4 -left-20 w-96 h-96 bg-primary/20 rounded-full blur-[100px]"></div>
<div class="absolute bottom-1/4 -right-20 w-96 h-96 bg-primary/10 rounded-full blur-[100px]"></div>
</div>
</section>
<!-- Our Specialized Care Section -->
<section class="py-24 px-10 bg-white dark:bg-slate-900 relative overflow-hidden" id="services">
<div class="max-w-[1800px] mx-auto">
<div class="flex flex-col justify-between items-start mb-20 gap-12 items-center text-center">
<div class="max-w-3xl">
<div class="text-primary font-bold text-xs uppercase mb-6 flex gap-4 tracking-[0.3em] justify-center items-center font-headline">
<span class="w-12 h-[1.5px] bg-primary"></span> <?php echo $cu('main_services_heading', 'Our Expertise'); ?>
                    </div>
<h2 class="font-headline text-6xl md:text-8xl font-extrabold tracking-tighter leading-[0.9] mb-8 text-slate-900 dark:text-white"><?php echo $cu('main_services_title', 'Comprehensive Dental Solutions'); ?></h2>
<p class="text-slate-600 dark:text-slate-400 text-2xl leading-relaxed max-w-xl font-medium font-body">
                        <?php echo $cuMultiline('main_services_description', 'From routine hygiene to complex restoration, our treatments are tailored to your specific oral health needs.'); ?>
                    </p>
</div>
<div class="relative hidden lg:block">
<span class="text-[16rem] font-headline font-black text-primary/[0.03] leading-none tracking-tighter absolute -top-24 select-none left-1/2 -translate-x-1/2"><?php echo $cu('main_services_watermark_word', 'CARE'); ?></span>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-10">
<div class="md:col-span-5 lg:col-span-4 md:mt-24">
<div class="group h-full bg-white dark:bg-slate-800 p-12 rounded-[2.5rem] border border-slate-200/50 dark:border-slate-700 hover:border-primary/20 transition-all duration-700 hover:shadow-[0_40px_80px_-20px_rgba(43,139,235,0.08)] relative overflow-hidden">
<div class="absolute -right-8 -top-8 w-32 h-32 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors"></div>
<div class="w-14 h-14 bg-primary/10 dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">align_items_stretch</span>
</div>
<h3 class="font-headline text-3xl font-extrabold mb-6 tracking-tight text-slate-900 dark:text-white"><?php echo $cu('main_service_card1_title', 'Orthodontics'); ?></h3>
<p class="text-slate-600 dark:text-slate-400 text-lg leading-relaxed font-medium mb-8 font-body"><?php echo $cuMultiline('main_service_card1_body', 'Achieve perfect alignment with clear aligners and modern braces. We specialize in discreet corrections for all ages.'); ?></p>
<a href="<?php echo $servicesHref; ?>#orthodontics" class="inline-flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-widest tracking-[0.3em] font-headline">
<span class="w-8 h-[1px] bg-primary/30"></span> <?php echo $cu('main_service_card1_cta', 'Learn more'); ?>
                        </a>
</div>
</div>
<div class="md:col-span-7 lg:col-span-4">
<div class="group h-full bg-primary p-12 rounded-[2.5rem] shadow-[0_50px_100px_-20px_rgba(43,139,235,0.3)] transition-all duration-700 relative overflow-hidden flex flex-col justify-between">
<div class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none">
<svg class="w-full h-full stroke-white fill-none" viewBox="0 0 100 100">
<circle cx="100" cy="0" r="80" stroke-width="0.5"></circle>
<circle cx="100" cy="0" r="60" stroke-width="0.5"></circle>
<circle cx="100" cy="0" r="40" stroke-width="0.5"></circle>
</svg>
</div>
<div>
<div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-10 text-white border border-white/20">
<span class="material-symbols-outlined text-3xl font-light">auto_awesome</span>
</div>
<h3 class="font-headline text-4xl font-extrabold mb-6 tracking-tight text-white leading-tight"><?php echo $cuMultiline('main_service_card2_title', "Cosmetic\nDentistry"); ?></h3>
<p class="text-white/80 text-xl leading-relaxed font-medium mb-12 font-body"><?php echo $cuMultiline('main_service_card2_body', 'Transform your smile with premium porcelain veneers, professional whitening, and aesthetic restoration techniques.'); ?></p>
</div>
<a href="<?php echo $servicesHref; ?>#cosmetic" class="bg-white text-primary w-full py-5 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-blue-50 transition-colors text-center font-headline inline-block">
                            <?php echo $cu('main_service_card2_cta', 'View Gallery'); ?>
                        </a>
</div>
</div>
<div class="md:col-span-12 lg:col-span-4 lg:mt-36">
<div class="group h-full glass-card dark:bg-slate-800/80 p-12 rounded-[2.5rem] border border-slate-200/50 dark:border-slate-700 hover:border-primary/30 transition-all duration-700 hover:shadow-xl relative overflow-hidden">
<div class="w-14 h-14 bg-primary/10 dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">health_and_safety</span>
</div>
<h3 class="font-headline text-3xl font-extrabold mb-6 tracking-tight text-slate-900 dark:text-white"><?php echo $cu('main_service_card3_title', 'Preventative Care'); ?></h3>
<p class="text-slate-600 dark:text-slate-400 text-lg leading-relaxed font-medium mb-8 font-body"><?php echo $cuMultiline('main_service_card3_body', 'Maintain lifelong oral health with comprehensive wellness exams, advanced cleanings, and digital diagnostics.'); ?></p>
<a href="<?php echo $servicesHref; ?>#general" class="inline-flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-widest tracking-[0.3em] font-headline">
<span class="w-8 h-[1px] bg-primary/30"></span> <?php echo $cu('main_service_card3_cta', 'Learn more'); ?>
                        </a>
</div>
</div>
</div>
</div>
</section>
<!-- The Patient Journey Section -->
<section class="py-32 bg-slate-50 dark:bg-slate-900/50 relative border-y border-slate-200/80 dark:border-slate-800" id="journey">
<div class="max-w-[1800px] mx-auto px-10">
<div class="flex flex-col items-center text-center mb-24">
<div class="inline-flex items-center gap-4 px-4 py-2 rounded-full bg-primary/5 text-primary text-[10px] font-black uppercase tracking-[0.4em] mb-6 font-headline">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span> <?php echo $cu('main_journey_badge', 'Your Experience'); ?>
                </div>
<h2 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tighter text-slate-900 dark:text-white mb-6 leading-[1.1]"><?php echo $cu('main_journey_title_before', 'The Patient'); ?> <span class="font-editorial italic font-normal text-primary editorial-word transform -skew-x-6 inline-block"><?php echo $cu('main_journey_title_accent', 'Journey'); ?></span></h2>
<p class="text-slate-600 dark:text-slate-400 text-xl font-medium max-w-2xl font-body"><?php echo $cuMultiline('main_journey_subtitle', 'A personalized pathway to your dream smile, from initial meeting to final reveal.'); ?></p>
</div>
<div class="relative">
<div class="hidden lg:block absolute top-1/2 left-0 w-full h-px step-connector -translate-y-1/2 z-0"></div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-24 relative z-10">
<div class="relative group">
<div class="bg-white dark:bg-slate-800 rounded-[2rem] p-12 border border-slate-200/80 dark:border-slate-700 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">01</div>
<div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">chat_bubble</span>
</div>
<h4 class="font-headline font-extrabold text-2xl mb-4 text-slate-900 dark:text-white"><?php echo $cu('main_journey_step1_title', 'Consultation'); ?></h4>
<p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium mb-8 font-body"><?php echo $cuMultiline('main_journey_step1_body', 'Discuss your dental goals and concerns with our experts in a relaxed, pressure-free environment.'); ?></p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60 font-headline">
<span class="material-symbols-outlined text-lg">forum</span>
                                <?php echo $cu('main_journey_step1_tag', 'Goal Alignment'); ?>
                            </div>
</div>
</div>
<div class="relative group">
<div class="bg-white dark:bg-slate-800 rounded-[2rem] p-12 border border-slate-200/80 dark:border-slate-700 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">02</div>
<div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">biotech</span>
</div>
<h4 class="font-headline font-extrabold text-2xl mb-4 text-slate-900 dark:text-white"><?php echo $cu('main_journey_step2_title', 'Treatment Planning'); ?></h4>
<p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium mb-8 font-body"><?php echo $cuMultiline('main_journey_step2_body', 'Utilizing 3D imaging to create a custom roadmap and digital preview of your future results.'); ?></p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60 font-headline">
<span class="material-symbols-outlined text-lg">map</span>
                                <?php echo $cu('main_journey_step2_tag', 'Custom Roadmap'); ?>
                            </div>
</div>
</div>
<div class="relative group">
<div class="bg-white dark:bg-slate-800 rounded-[2rem] p-12 border border-slate-200/80 dark:border-slate-700 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-headline font-black shadow-lg shadow-primary/30">03</div>
<div class="mb-10 text-primary opacity-40 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">face_6</span>
</div>
<h4 class="font-headline font-extrabold text-2xl mb-4 text-slate-900 dark:text-white"><?php echo $cu('main_journey_step3_title', 'Transformation'); ?></h4>
<p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium mb-8 font-body"><?php echo $cuMultiline('main_journey_step3_body', 'Executing your clinical plan with precision and care, revealing your healthy, radiant new smile.'); ?></p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/60 font-headline">
<span class="material-symbols-outlined text-lg">verified</span>
                                <?php echo $cu('main_journey_step3_tag', 'Reveal Success'); ?>
                            </div>
</div>
</div>
</div>
</div>
</div>
</section>
<!-- Final CTA Section -->
<section class="py-24 px-10">
<div class="mx-auto rounded-[4rem] bg-primary relative overflow-hidden flex flex-col items-center text-center shadow-[0_40px_100px_-20px_rgba(43,139,235,0.4)] max-w-6xl py-24 px-10 md:px-20">
<div class="relative z-10 max-w-3xl">
<div class="inline-block px-4 py-1 rounded-full bg-white/20 text-white text-[10px] font-black uppercase tracking-[0.3em] mb-10 font-headline">
                    <?php echo $cu('main_cta_badge', 'Your Smile Awaits'); ?>
                </div>
<h2 class="font-headline text-5xl font-extrabold text-white tracking-tighter leading-[0.85] md:text-6xl mb-8"><?php echo $cu('main_cta_title', 'Ready to rediscover your smile?'); ?></h2>
<p class="text-white/70 text-xl md:text-2xl max-w-xl mx-auto leading-relaxed mb-10 font-body"><?php echo $cuMultiline('main_cta_subtext', 'Join thousands of happy patients who trust us with their oral health and aesthetic transformations.'); ?></p>
<a href="<?php echo htmlspecialchars(BASE_URL . 'BookAppointmentClient.php', ENT_QUOTES, 'UTF-8'); ?>" class="inline-block bg-white text-primary px-16 py-6 rounded-full font-black text-sm uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-2xl active:scale-95 font-headline">
                    <?php echo $cu('main_cta_button', 'Book a Consultation'); ?>
                </a>
</div>
<div class="absolute top-0 right-0 w-1/3 h-full border-l border-white/10 pointer-events-none"></div>
<div class="absolute bottom-0 left-0 w-full h-1/4 border-t border-white/10 pointer-events-none"></div>
<div class="absolute -right-20 -bottom-20 w-80 h-80 bg-white/5 rounded-full blur-3xl"></div>
</div>
</section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
