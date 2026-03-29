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
$cu = function($k) use ($CLINIC) { return isset($CLINIC[$k]) ? htmlspecialchars($CLINIC[$k], ENT_QUOTES, 'UTF-8') : ''; };
$setAppointmentHref = isLoggedIn('client')
    ? clinic_link('download', $currentTenantSlug ?? null, BASE_URL . 'DownloadApp.php')
    : clinic_link('login', $currentTenantSlug ?? null, BASE_URL . 'Login.php');
$servicesHref = clinic_link('services', $currentTenantSlug ?? null, BASE_URL . 'ServicesClient.php');
$bookHref = BASE_URL . 'BookAppointmentClient.php';
if (!empty($currentTenantSlug)) {
    $bookHref .= (strpos($bookHref, '?') === false ? '?' : '&') . 'clinic_slug=' . rawurlencode((string) $currentTenantSlug);
}
$cuImg = function($k) use ($CLINIC) {
    $v = isset($CLINIC[$k]) ? trim($CLINIC[$k]) : '';
    if ($v === '') return '';
    return (strpos($v, 'http') === 0) ? $v : (BASE_URL . ltrim($v, '/'));
};
$heroBgUrl = htmlspecialchars($cuImg('main_hero_image') ?: (BASE_URL . 'Endorser1.png'), ENT_QUOTES, 'UTF-8');
?>
<style>
.mesh-gradient-hero {
    background-color: #ffffff;
    background-image: linear-gradient(to bottom, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0.85)), var(--hero-bg);
    background-size: cover;
    background-position: center;
}
.dark .mesh-gradient-hero {
    background-image: linear-gradient(to bottom, rgba(15, 23, 42, 0.92), rgba(15, 23, 42, 0.88)), var(--hero-bg);
}
.glass-card-main {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
.dark .glass-card-main {
    background: rgba(30, 41, 59, 0.75);
}
.editorial-word-main {
    text-shadow: 0 0 12px rgba(43, 140, 238, 0.12);
    letter-spacing: -0.02em;
}
.step-connector-main {
    height: 1px;
    background: linear-gradient(to right, transparent, rgba(43, 140, 238, 0.25), transparent);
}
</style>
<div class="relative flex min-h-screen w-full flex-col">
<?php include __DIR__ . '/includes/nav_client.php'; ?>

<main class="min-h-screen w-full flex-grow bg-white dark:bg-background-dark text-slate-900 dark:text-slate-100">
<section class="relative min-h-[90vh] flex items-center justify-center pt-24 lg:pt-28 pb-16 overflow-hidden mesh-gradient-hero" style="--hero-bg: url('<?php echo $heroBgUrl; ?>');">
<div class="max-w-7xl mx-auto w-full px-6 md:px-10 relative z-10 flex flex-col items-center text-center justify-center">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.2em] mb-8">
<?php echo $cu('main_services_heading') ?: 'Premium Patient Care'; ?>
</div>
<h1 class="font-display text-[clamp(2.5rem,7vw,5.5rem)] font-extrabold tracking-[-0.05em] mb-8 leading-[0.9] flex flex-col items-center justify-center">
<span class="block text-slate-900 dark:text-white"><?php echo $cu('main_hero_line1'); ?></span>
<span class="block text-slate-900 dark:text-white"><?php echo $cu('main_hero_line2'); ?></span>
<span class="relative block text-center mt-2">
<span class="font-serif italic font-normal text-primary editorial-word-main transform -skew-x-6 inline-block"><?php echo $cu('main_hero_line3'); ?></span>
</span>
</h1>
<p class="font-body text-lg md:text-xl max-w-2xl mb-10 leading-relaxed text-slate-600 dark:text-slate-400 font-medium text-balance">
<?php echo $cu('main_hero_subtext'); ?>
</p>
<div class="flex flex-col items-center justify-center">
<a href="<?php echo htmlspecialchars($setAppointmentHref, ENT_QUOTES, 'UTF-8'); ?>" class="group relative px-10 py-4 md:px-12 md:py-5 bg-primary hover:bg-primary-dark text-white font-bold rounded-full overflow-hidden transition-all hover:pr-14 md:hover:pr-16 active:scale-95 shadow-lg shadow-primary/25 inline-flex items-center justify-center gap-2">
<span class="relative z-10">Set Appointment</span>
<span class="material-symbols-outlined relative z-10 text-xl opacity-0 group-hover:opacity-100 transition-all absolute right-5 md:right-6">arrow_right_alt</span>
</a>
</div>
</div>
<div class="absolute inset-0 z-0 opacity-20 pointer-events-none">
<div class="absolute top-1/4 -left-20 w-96 h-96 bg-primary/20 rounded-full blur-[100px]"></div>
<div class="absolute bottom-1/4 -right-20 w-96 h-96 bg-primary/10 rounded-full blur-[100px]"></div>
</div>
</section>

<section class="py-24 px-6 md:px-10 bg-white dark:bg-slate-900 relative overflow-hidden" id="services">
<div class="max-w-[1800px] mx-auto">
<div class="flex flex-col justify-between items-start mb-16 md:mb-20 gap-10 md:gap-12 items-center text-center">
<div class="max-w-3xl">
<div class="text-primary font-bold text-xs uppercase mb-6 flex gap-4 tracking-[0.3em] justify-center items-center">
<span class="w-12 h-[1.5px] bg-primary"></span> <?php echo $cu('main_services_heading'); ?>
</div>
<h2 class="font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold tracking-tighter leading-[0.95] mb-6 md:mb-8 text-slate-900 dark:text-white">Our Specialized <br/> <span class="font-serif italic font-normal text-primary editorial-word-main transform -skew-x-6 inline-block">Care</span></h2>
<p class="text-slate-600 dark:text-slate-400 text-lg md:text-xl leading-relaxed max-w-xl font-medium mx-auto text-balance">
<?php echo $cu('main_services_description'); ?>
</p>
</div>
<div class="relative hidden lg:block">
<span class="text-[12rem] xl:text-[16rem] font-display font-black text-primary/[0.04] dark:text-primary/[0.08] leading-none tracking-tighter absolute -top-24 select-none left-1/2 -translate-x-1/2">CARE</span>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-12 gap-6 lg:gap-10">
<div class="md:col-span-5 lg:col-span-4 md:mt-16 lg:mt-24">
<div class="group h-full bg-white dark:bg-slate-800 p-8 md:p-12 rounded-[2.5rem] border border-slate-100 dark:border-slate-700/50 hover:border-primary/30 transition-all duration-700 hover:shadow-[0_40px_80px_-20px_rgba(43,140,238,0.12)] relative overflow-hidden">
<div class="absolute -right-8 -top-8 w-32 h-32 bg-primary/5 rounded-full blur-2xl group-hover:bg-primary/10 transition-colors"></div>
<div class="w-14 h-14 bg-primary-light dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-8 md:mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">align_horizontal_left</span>
</div>
<h3 class="font-display text-2xl md:text-3xl font-extrabold mb-4 md:mb-6 tracking-tight text-slate-900 dark:text-white">Orthodontics</h3>
<p class="text-slate-600 dark:text-slate-400 text-base md:text-lg leading-relaxed font-medium mb-6 md:mb-8">Correction of irregularities using modern clear aligners and braces.</p>
<a href="<?php echo htmlspecialchars($servicesHref, ENT_QUOTES, 'UTF-8'); ?>#orthodontics" class="inline-flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-[0.2em] hover:underline">
<span class="w-8 h-px bg-primary/30"></span> Learn more
</a>
</div>
</div>
<div class="md:col-span-7 lg:col-span-4">
<div class="group h-full bg-primary p-8 md:p-12 rounded-[2.5rem] shadow-[0_50px_100px_-20px_rgba(43,140,238,0.35)] transition-all duration-700 relative overflow-hidden flex flex-col justify-between">
<div class="absolute top-0 right-0 w-full h-full opacity-10 pointer-events-none text-white">
<svg class="w-full h-full stroke-current fill-none" viewBox="0 0 100 100" aria-hidden="true">
<circle cx="100" cy="0" r="80" stroke-width="0.5"></circle>
<circle cx="100" cy="0" r="60" stroke-width="0.5"></circle>
<circle cx="100" cy="0" r="40" stroke-width="0.5"></circle>
</svg>
</div>
<div>
<div class="w-14 h-14 bg-white/10 backdrop-blur-md rounded-2xl flex items-center justify-center mb-8 md:mb-10 text-white border border-white/20">
<span class="material-symbols-outlined text-3xl font-light">auto_awesome</span>
</div>
<h3 class="font-display text-3xl md:text-4xl font-extrabold mb-4 md:mb-6 tracking-tight text-white leading-tight">Cosmetic<br/>Dentistry</h3>
<p class="text-white/85 text-base md:text-xl leading-relaxed font-medium mb-8 md:mb-10">Professional whitening, veneers, and aesthetic treatments for a confident smile.</p>
</div>
<a href="<?php echo htmlspecialchars($servicesHref, ENT_QUOTES, 'UTF-8'); ?>#cosmetic" class="text-center bg-white text-primary w-full py-4 md:py-5 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-primary-light transition-colors">View all services</a>
</div>
</div>
<div class="md:col-span-12 lg:col-span-4 lg:mt-24 xl:mt-36">
<div class="group h-full glass-card-main p-8 md:p-12 rounded-[2.5rem] border border-slate-100 dark:border-slate-600/50 hover:border-primary/30 transition-all duration-700 hover:shadow-xl relative overflow-hidden">
<div class="w-14 h-14 bg-primary-light dark:bg-slate-700 rounded-2xl flex items-center justify-center mb-8 md:mb-10 text-primary transition-all duration-500 group-hover:scale-110">
<span class="material-symbols-outlined text-3xl font-light">health_and_safety</span>
</div>
<h3 class="font-display text-2xl md:text-3xl font-extrabold mb-4 md:mb-6 tracking-tight text-slate-900 dark:text-white">Preventative Care</h3>
<p class="text-slate-600 dark:text-slate-400 text-base md:text-lg leading-relaxed font-medium mb-6 md:mb-8">Checkups, hygiene, and diagnostics to maintain optimal oral health.</p>
<a href="<?php echo htmlspecialchars($servicesHref, ENT_QUOTES, 'UTF-8'); ?>#general" class="inline-flex items-center gap-2 text-primary font-bold text-xs uppercase tracking-[0.2em] hover:underline">
<span class="w-8 h-px bg-primary/30"></span> Learn more
</a>
</div>
</div>
</div>
</div>
</section>

<section class="py-24 md:py-32 bg-slate-50 dark:bg-slate-950/50 relative border-y border-slate-100 dark:border-slate-800" id="journey">
<div class="max-w-[1800px] mx-auto px-6 md:px-10">
<div class="flex flex-col items-center text-center mb-16 md:mb-24">
<div class="inline-flex items-center gap-4 px-4 py-2 rounded-full bg-primary/10 text-primary text-[10px] font-black uppercase tracking-[0.35em] mb-6">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span> Your Experience
</div>
<h2 class="font-display text-4xl md:text-5xl lg:text-7xl font-extrabold tracking-tighter text-slate-900 dark:text-white mb-6 leading-[1.05]">The Patient <span class="font-serif italic font-normal text-primary editorial-word-main transform -skew-x-6 inline-block">Journey</span></h2>
<p class="text-slate-600 dark:text-slate-400 text-lg md:text-xl font-medium max-w-2xl text-balance">From your first visit to lasting results—personalized care at every step.</p>
</div>
<div class="relative">
<div class="hidden lg:block absolute top-1/2 left-0 w-full step-connector-main -translate-y-1/2 z-0"></div>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-10 lg:gap-16 relative z-10">
<div class="relative group">
<div class="bg-white dark:bg-slate-800 rounded-[2rem] p-8 md:p-12 border border-slate-100 dark:border-slate-700 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-8 md:left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-display font-black shadow-lg shadow-primary/30 text-sm">01</div>
<div class="mb-8 md:mb-10 text-primary opacity-50 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">chat_bubble</span>
</div>
<h4 class="font-display font-extrabold text-xl md:text-2xl mb-4 text-slate-900 dark:text-white">Consultation</h4>
<p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium mb-6 md:mb-8">Discuss your goals in a relaxed, pressure-free environment.</p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/70">
<span class="material-symbols-outlined text-lg">forum</span> Goal alignment
</div>
</div>
</div>
<div class="relative group">
<div class="bg-white dark:bg-slate-800 rounded-[2rem] p-8 md:p-12 border border-slate-100 dark:border-slate-700 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-8 md:left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-display font-black shadow-lg shadow-primary/30 text-sm">02</div>
<div class="mb-8 md:mb-10 text-primary opacity-50 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">biotech</span>
</div>
<h4 class="font-display font-extrabold text-xl md:text-2xl mb-4 text-slate-900 dark:text-white">Treatment Planning</h4>
<p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium mb-6 md:mb-8">Digital imaging and a clear roadmap tailored to your smile.</p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/70">
<span class="material-symbols-outlined text-lg">map</span> Custom roadmap
</div>
</div>
</div>
<div class="relative group">
<div class="bg-white dark:bg-slate-800 rounded-[2rem] p-8 md:p-12 border border-slate-100 dark:border-slate-700 transition-all duration-500 group-hover:-translate-y-2 hover:shadow-2xl hover:shadow-primary/5">
<div class="absolute -top-6 left-8 md:left-12 w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center font-display font-black shadow-lg shadow-primary/30 text-sm">03</div>
<div class="mb-8 md:mb-10 text-primary opacity-50 group-hover:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-5xl font-light">face_6</span>
</div>
<h4 class="font-display font-extrabold text-xl md:text-2xl mb-4 text-slate-900 dark:text-white">Transformation</h4>
<p class="text-slate-600 dark:text-slate-400 leading-relaxed font-medium mb-6 md:mb-8">Precision care for a healthy, radiant smile you will love.</p>
<div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-[0.2em] text-primary/70">
<span class="material-symbols-outlined text-lg">verified</span> Lasting results
</div>
</div>
</div>
</div>
</div>
</div>
</section>

<section class="py-16 md:py-24 px-6 md:px-10">
<div class="mx-auto rounded-[3rem] md:rounded-[4rem] bg-primary relative overflow-hidden flex flex-col items-center text-center shadow-[0_40px_100px_-20px_rgba(43,140,238,0.45)] max-w-6xl py-16 md:py-24 px-8 md:px-16">
<div class="relative z-10 max-w-3xl">
<div class="inline-block px-4 py-1 rounded-full bg-white/20 text-white text-[10px] font-black uppercase tracking-[0.3em] mb-8 md:mb-10">Your smile awaits</div>
<h2 class="font-display text-3xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tighter leading-[0.9] md:leading-[0.85] mb-6 md:mb-8">Ready to rediscover your smile?</h2>
<p class="text-white/75 text-lg md:text-xl max-w-xl mx-auto leading-relaxed mb-8 md:mb-10">Book a visit or set an appointment—we are here when you are ready.</p>
<a href="<?php echo htmlspecialchars($bookHref, ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex bg-white text-primary px-10 md:px-16 py-5 md:py-6 rounded-full font-black text-sm uppercase tracking-[0.15em] hover:scale-[1.02] transition-transform shadow-2xl active:scale-95">Book a consultation</a>
</div>
<div class="absolute top-0 right-0 w-1/3 h-full border-l border-white/10 pointer-events-none hidden md:block"></div>
<div class="absolute bottom-0 left-0 w-full h-1/4 border-t border-white/10 pointer-events-none"></div>
<div class="absolute -right-20 -bottom-20 w-80 h-80 bg-white/5 rounded-full blur-3xl"></div>
</div>
</section>

<section class="py-24 bg-white dark:bg-background-dark relative overflow-hidden" id="team">
<div class="absolute top-0 left-0 w-full h-1/2 bg-slate-50 dark:bg-slate-900/30 -z-10"></div>
<div class="max-w-7xl mx-auto px-6 md:px-12">
<div class="bg-white dark:bg-slate-800 rounded-[2rem] p-8 md:p-12 lg:p-16 shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700/50 overflow-hidden relative">
<div class="absolute top-0 right-0 w-96 h-96 bg-primary/10 dark:bg-primary/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none opacity-60"></div>
<div class="flex flex-col lg:flex-row gap-12 lg:gap-20 items-center relative z-10">
<div class="w-full lg:w-1/3 relative group">
<div class="absolute inset-0 bg-primary/20 rounded-2xl rotate-2 group-hover:rotate-3 transition-transform duration-500"></div>
<img src="<?php echo htmlspecialchars($cuImg('main_doctor_image') ?: (BASE_URL . 'picturenidok1.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $cu('main_doctor_name'); ?>" class="aspect-[3/4] w-full rounded-2xl object-cover bg-slate-200 relative z-10 shadow-lg border-4 border-white dark:border-slate-700" loading="lazy" decoding="async" width="400" height="533">
</div>
<div class="flex-1 flex flex-col gap-8">
<div>
<div class="flex items-center gap-3 mb-3">
<span class="h-px w-10 bg-primary"></span>
<span class="text-primary font-bold text-xs tracking-widest uppercase"><?php echo $cu('main_doctor_title'); ?></span>
</div>
<h3 class="text-3xl md:text-4xl lg:text-5xl font-bold text-slate-900 dark:text-white mb-3"><?php echo $cu('main_doctor_name'); ?></h3>
</div>
<div class="space-y-4 text-slate-600 dark:text-slate-300 text-lg leading-relaxed font-light">
<p><?php echo $cu('main_doctor_bio1'); ?></p>
<p><?php echo $cu('main_doctor_bio2'); ?></p>
</div>
<div class="pt-6 border-t border-slate-100 dark:border-slate-700 flex flex-wrap gap-8 lg:gap-16">
<div class="flex flex-col">
<span class="text-3xl font-bold text-primary dark:text-white"><?php echo $cu('main_stats_years'); ?></span>
<span class="text-xs text-slate-500 uppercase tracking-wide font-semibold mt-1">Years Experience</span>
</div>
<div class="flex flex-col">
<span class="text-3xl font-bold text-primary dark:text-white"><?php echo $cu('main_stats_smiles'); ?></span>
<span class="text-xs text-slate-500 uppercase tracking-wide font-semibold mt-1">Happy Smiles</span>
</div>
<div class="flex flex-col">
<span class="text-3xl font-bold text-primary dark:text-white"><?php echo $cu('main_stats_focus'); ?></span>
<span class="text-xs text-slate-500 uppercase tracking-wide font-semibold mt-1">Patient Focus</span>
</div>
</div>
</div>
</div>
</div>
</div>
</section>

<section class="py-24 bg-surface-light dark:bg-background-dark overflow-hidden">
<div class="max-w-7xl mx-auto px-6 md:px-12 mb-12">
<div>
<h2 class="text-3xl md:text-4xl font-bold tracking-tight text-slate-900 dark:text-white mb-2"><?php echo $cu('main_facilities_title'); ?></h2>
<p class="text-slate-600 dark:text-slate-400"><?php echo $cu('main_facilities_description'); ?></p>
</div>
</div>
<div class="overflow-hidden pb-10 auto-scroll-wrapper">
<div class="flex gap-6 auto-scroll w-max">
<div class="shrink-0 w-[85vw] md:w-[500px] aspect-[16/10] rounded-2xl bg-slate-200 overflow-hidden relative group shadow-lg border border-slate-100 dark:border-slate-700">
<img src="https://lh3.googleusercontent.com/aida-public/AB6AXuB4gfNHHZtrrSWdyF1DbLkyINJaOpfJNz-U3LlQFyjOY9ocR-eH_3ftsqf6SzRybOm5gOTc3lp-E6ZG7U31VNzA6BVq1Q-EVMh3Ko0cDVU1nYMXx67kRvht5Uq0a8LQ3AXCDtczw3reaoCGg0y75i5WlWxCsmB_UxANtgD14hK1mjwNIUZs4RTddynF1cqOgKiFg7WqSsIoG_D2psXppUTN6a2zBOLeVK4NF8D4MPqzPDtYOf4FzRuLdnylrW-IAbA1KnP04dBDOyA" alt="Waiting Area" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy" decoding="async" width="500" height="313">
<div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-slate-900/80 to-transparent">
<p class="text-white font-bold text-lg">Reception &amp; Waiting Area</p>
</div>
</div>
<div class="shrink-0 w-[85vw] md:w-[500px] aspect-[16/10] rounded-2xl bg-slate-200 overflow-hidden relative group shadow-lg border border-slate-100 dark:border-slate-700">
<img src="https://lh3.googleusercontent.com/aida-public/AB6AXuAmXKtik5DbTpLuxqLQzU4mrLoZGTB7Ln-QAvcn3FyR2ho2VGoyXNscVdo03vAPgy6hOWRWScZhDjc0Ap6nAZW8jY_6CU6AuzrKJcAhC8I918x_i4fzM_tyAZvKcwjaRQjT-NtKt_eIgqm9DmUvaq-35KMj5ZcZ_7mKmTDqT9Fl2ErqSrMudRDUFDtTaqYY6z7GbwiW6TfDgG4-oWw1rNk2Gho1Wi_I32LFfVDcxfVhBL3-P341kJroWefROJIVA23C2uealG2vcuE" alt="Treatment Room" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy" decoding="async" width="500" height="313">
<div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-slate-900/80 to-transparent">
<p class="text-white font-bold text-lg">Modern Treatment Suites</p>
</div>
</div>
<div class="shrink-0 w-[85vw] md:w-[500px] aspect-[16/10] rounded-2xl bg-slate-200 overflow-hidden relative group shadow-lg border border-slate-100 dark:border-slate-700">
<img src="https://lh3.googleusercontent.com/aida-public/AB6AXuAxgk8CYFFwmURF0p5qz0wRUjiL31ArcTOFpXXiuPxlp2RJNna0HhtXN8bHThN3L5DtRbw_W6uqPZTRdoSaPAX-sqchitYvedmO9HozrWrhtUcTOE4X6KmKsVHC1OtV0D-MW_e3lqusAuOZq28JmYdgETkoXiqx2XeNW_vsAo_YBTyP47bjTn2w23uu-KaLHtYFb8g9EGy4FQhyX5XI7U3oQIo_wOxlw5x4zGkxJMrBuyb3M3ZdXtCWOoLinkz5-LXvhOxWFFmYxmk" alt="Hygiene Area" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy" decoding="async" width="500" height="313">
<div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-slate-900/80 to-transparent">
<p class="text-white font-bold text-lg">Sterilization &amp; Hygiene</p>
</div>
</div>
<div class="shrink-0 w-[85vw] md:w-[500px] aspect-[16/10] rounded-2xl bg-slate-200 overflow-hidden relative group shadow-lg border border-slate-100 dark:border-slate-700">
<img src="https://lh3.googleusercontent.com/aida-public/AB6AXuB4gfNHHZtrrSWdyF1DbLkyINJaOpfJNz-U3LlQFyjOY9ocR-eH_3ftsqf6SzRybOm5gOTc3lp-E6ZG7U31VNzA6BVq1Q-EVMh3Ko0cDVU1nYMXx67kRvht5Uq0a8LQ3AXCDtczw3reaoCGg0y75i5WlWxCsmB_UxANtgD14hK1mjwNIUZs4RTddynF1cqOgKiFg7WqSsIoG_D2psXppUTN6a2zBOLeVK4NF8D4MPqzPDtYOf4FzRuLdnylrW-IAbA1KnP04dBDOyA" alt="Waiting Area" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy" decoding="async" width="500" height="313">
<div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-slate-900/80 to-transparent">
<p class="text-white font-bold text-lg">Reception &amp; Waiting Area</p>
</div>
</div>
<div class="shrink-0 w-[85vw] md:w-[500px] aspect-[16/10] rounded-2xl bg-slate-200 overflow-hidden relative group shadow-lg border border-slate-100 dark:border-slate-700">
<img src="https://lh3.googleusercontent.com/aida-public/AB6AXuAmXKtik5DbTpLuxqLQzU4mrLoZGTB7Ln-QAvcn3FyR2ho2VGoyXNscVdo03vAPgy6hOWRWScZhDjc0Ap6nAZW8jY_6CU6AuzrKJcAhC8I918x_i4fzM_tyAZvKcwjaRQjT-NtKt_eIgqm9DmUvaq-35KMj5ZcZ_7mKmTDqT9Fl2ErqSrMudRDUFDtTaqYY6z7GbwiW6TfDgG4-oWw1rNk2Gho1Wi_I32LFfVDcxfVhBL3-P341kJroWefROJIVA23C2uealG2vcuE" alt="Treatment Room" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy" decoding="async" width="500" height="313">
<div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-slate-900/80 to-transparent">
<p class="text-white font-bold text-lg">Modern Treatment Suites</p>
</div>
</div>
<div class="shrink-0 w-[85vw] md:w-[500px] aspect-[16/10] rounded-2xl bg-slate-200 overflow-hidden relative group shadow-lg border border-slate-100 dark:border-slate-700">
<img src="https://lh3.googleusercontent.com/aida-public/AB6AXuAxgk8CYFFwmURF0p5qz0wRUjiL31ArcTOFpXXiuPxlp2RJNna0HhtXN8bHThN3L5DtRbw_W6uqPZTRdoSaPAX-sqchitYvedmO9HozrWrhtUcTOE4X6KmKsVHC1OtV0D-MW_e3lqusAuOZq28JmYdgETkoXiqx2XeNW_vsAo_YBTyP47bjTn2w23uu-KaLHtYFb8g9EGy4FQhyX5XI7U3oQIo_wOxlw5x4zGkxJMrBuyb3M3ZdXtCWOoLinkz5-LXvhOxWFFmYxmk" alt="Hygiene Area" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy" decoding="async" width="500" height="313">
<div class="absolute bottom-0 left-0 right-0 p-6 bg-gradient-to-t from-slate-900/80 to-transparent">
<p class="text-white font-bold text-lg">Sterilization &amp; Hygiene</p>
</div>
</div>
</div>
</div>
</section>

<section class="py-24 bg-accent/30 dark:bg-background-dark relative" id="contact">
<div class="absolute inset-0 bg-[radial-gradient(#2b8cee_1px,transparent_1px)] [background-size:24px_24px] opacity-[0.03] pointer-events-none"></div>
<div class="max-w-7xl mx-auto px-6 md:px-12 relative z-10">
<div class="max-w-3xl mx-auto">
<div class="text-center mb-10">
<h3 class="text-primary font-bold tracking-widest uppercase text-xs mb-2">CONTACT US</h3>
<h2 class="text-3xl md:text-4xl font-bold tracking-tight text-slate-900 dark:text-white mb-6">Let's Keep in Touch</h2>
<p class="text-slate-600 dark:text-slate-400 text-lg">Have a question or need to schedule an appointment? Fill out the form below and we'll get back to you shortly.</p>
</div>
<div class="bg-white dark:bg-slate-900 rounded-3xl p-6 md:p-10 shadow-xl border border-slate-100 dark:border-slate-800 relative overflow-hidden">
<div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-primary via-blue-400 to-primary"></div>
<div class="mb-8 relative z-10">
<h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white mb-2">Send us a Message</h2>
<p class="text-slate-500 dark:text-slate-400">Fill in the form below and our team will get back to you within 24 hours.</p>
</div>
<div id="formMessage" class="hidden mb-6 p-4 rounded-xl border font-medium text-sm"></div>
<form class="space-y-6 relative z-10" id="contactForm" onsubmit="handleFormSubmit(event);">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="name">Full Name</label>
<div class="relative">
<input class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none" id="name" placeholder="John Doe" type="text"/>
<div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">person</span>
</div>
</div>
</div>
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="email">Email Address</label>
<div class="relative">
<input class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none" id="email" placeholder="john@example.com" type="email"/>
<div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">mail</span>
</div>
</div>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="phone">Phone Number</label>
<div class="relative">
<input class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none" id="phone" placeholder="(555) 123-4567" type="tel"/>
<div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">call</span>
</div>
</div>
</div>
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="concern">Subject</label>
<div class="relative">
<select class="w-full h-12 px-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none appearance-none cursor-pointer" id="concern">
<option disabled="" selected="" value="">Select reason...</option>
<option value="appointment">New Appointment</option>
<option value="general">General Inquiry</option>
<option value="billing">Billing Question</option>
<option value="emergency">Emergency</option>
</select>
<div class="absolute inset-y-0 right-4 flex items-center pointer-events-none text-slate-500">
<span class="material-symbols-outlined text-[20px]">expand_more</span>
</div>
</div>
</div>
</div>
<div class="group">
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 ml-1" for="message">Your Message</label>
<textarea class="w-full p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 focus:bg-white dark:focus:bg-slate-800 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all duration-200 outline-none resize-none" id="message" placeholder="How can we help you today?" rows="4"></textarea>
</div>
<div class="pt-2 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 sm:gap-6">
<div class="text-xs text-slate-500 dark:text-slate-400 flex items-start gap-2 flex-1 sm:flex-initial">
<span class="material-symbols-outlined text-base shrink-0 mt-0.5">security</span>
<span class="leading-relaxed">This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Terms of Service</a> apply.</span>
</div>
<button class="w-full sm:w-auto px-8 h-12 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl shadow-lg shadow-primary/30 hover:shadow-primary/50 hover:-translate-y-0.5 transition-all duration-200 flex items-center justify-center gap-2 group shrink-0" type="submit">
<span>Send Message</span>
<span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">send</span>
</button>
</div>
</form>
</div>
</div>
</div>
</section>
</main>

<style>
@keyframes scroll {
    0% { transform: translateX(0); }
    100% { transform: translateX(calc(-50% - 0.75rem)); }
}
.auto-scroll {
    animation: scroll 40s linear infinite;
    display: flex;
    gap: 1.5rem;
    will-change: transform;
}
.auto-scroll:hover { animation-play-state: paused; }
.auto-scroll-wrapper {
    overflow: hidden;
    position: relative;
    mask-image: linear-gradient(to right, transparent, black 10%, black 90%, transparent);
    -webkit-mask-image: linear-gradient(to right, transparent, black 10%, black 90%, transparent);
}
.auto-scroll-wrapper::before,
.auto-scroll-wrapper::after {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    width: 150px;
    z-index: 10;
    pointer-events: none;
}
.auto-scroll-wrapper::before {
    left: 0;
    background: linear-gradient(to right, rgb(248 250 252), transparent);
}
.dark .auto-scroll-wrapper::before {
    background: linear-gradient(to right, rgb(15 23 42), transparent);
}
.auto-scroll-wrapper::after {
    right: 0;
    background: linear-gradient(to left, rgb(248 250 252), transparent);
}
.dark .auto-scroll-wrapper::after {
    background: linear-gradient(to left, rgb(15 23 42), transparent);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js"></script>
<script>
        const RECAPTCHA_SITE_KEY = 'YOUR_RECAPTCHA_SITE_KEY';
        (function(){
            emailjs.init("yF8aTwk2JYrSOIn02");
        })();

        async function handleFormSubmit(event) {
            event.preventDefault();
            const form = document.getElementById('contactForm');
            const messageDiv = document.getElementById('formMessage');
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const concern = document.getElementById('concern').value;
            const message = document.getElementById('message').value.trim();
            messageDiv.classList.add('hidden');
            if (!name || !email || !phone || !concern || !message) {
                showMessage('Please fill in all required fields.', 'error');
                return;
            }
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="flex items-center gap-2"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Sending...</span>';
            try {
                let recaptchaToken = '';
                if (RECAPTCHA_SITE_KEY && RECAPTCHA_SITE_KEY !== 'YOUR_RECAPTCHA_SITE_KEY') {
                    try {
                        recaptchaToken = await grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' });
                    } catch (recaptchaError) {
                        console.error('reCAPTCHA Error:', recaptchaError);
                    }
                }
                const templateParams = {
                    user_name: name,
                    user_email: email,
                    phone_number: phone,
                    concern_type: concern,
                    user_message: message,
                    recaptcha_token: recaptchaToken
                };
                await emailjs.send('service_q99148g', 'template_1w5v9oe', templateParams);
                showMessage('Thank you! Your message has been sent successfully. We\'ll get back to you within 24 hours.', 'success');
                form.reset();
            } catch (error) {
                console.error('EmailJS Error:', error);
                showMessage('Sorry, there was an error sending your message. Please try again or contact us directly at hello@drcgdental.com', 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            }
        }

        function showMessage(text, type) {
            const messageDiv = document.getElementById('formMessage');
            messageDiv.textContent = text;
            messageDiv.classList.remove('hidden');
            if (type === 'success') {
                messageDiv.className = 'mb-6 p-4 rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300 font-medium text-sm';
            } else {
                messageDiv.className = 'mb-6 p-4 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 font-medium text-sm';
            }
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
