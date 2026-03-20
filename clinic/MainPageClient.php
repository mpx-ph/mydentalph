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
$cuImg = function($k) use ($CLINIC) {
    $v = isset($CLINIC[$k]) ? trim($CLINIC[$k]) : '';
    if ($v === '') return '';
    return (strpos($v, 'http') === 0) ? $v : (BASE_URL . ltrim($v, '/'));
};
?>
<body class="bg-background-light dark:bg-background-dark text-text-main dark:text-slate-200 font-display overflow-x-hidden selection:bg-primary selection:text-white">
<?php include __DIR__ . '/includes/nav_client.php'; ?>

<section class="relative w-full pt-32 pb-20 lg:pt-40 lg:pb-32 bg-gradient-hero overflow-hidden">
<div class="absolute top-0 right-0 -translate-y-1/4 translate-x-1/4 w-[600px] h-[600px] bg-blue-100 dark:bg-blue-900/20 rounded-full blur-[100px] opacity-40 pointer-events-none"></div>
<div class="absolute bottom-0 left-0 translate-y-1/4 -translate-x-1/4 w-[500px] h-[500px] bg-indigo-100 dark:bg-indigo-900/10 rounded-full blur-[100px] opacity-40 pointer-events-none"></div>
<div class="max-w-7xl mx-auto px-6 md:px-12 relative z-10">
<div class="flex flex-col lg:flex-row gap-16 items-center">
                <div class="flex-1 flex flex-col gap-6 items-start text-left">
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold leading-[1.2] tracking-tight text-slate-900 dark:text-white">
                        <span class="block font-display"><?php echo $cu('main_hero_line1'); ?></span>
                        <span class="block font-display"><?php echo $cu('main_hero_line2'); ?></span>
                        <span class="block italic font-serif mt-2"><?php echo $cu('main_hero_line3'); ?></span>
                       
                    </h1>
                    <p class="text-lg md:text-xl text-slate-600 dark:text-slate-400 max-w-lg leading-relaxed text-balance mt-2">
                        <?php echo $cu('main_hero_subtext'); ?>
                    </p>
                    <div class="flex flex-wrap gap-4 w-full sm:w-auto mt-4">
                        <a href="<?php echo htmlspecialchars($setAppointmentHref, ENT_QUOTES, 'UTF-8'); ?>" class="h-12 px-6 rounded-full bg-primary hover:bg-primary-dark text-white font-semibold transition-all shadow-lg shadow-primary/25 hover:shadow-xl hover:-translate-y-1 w-full sm:w-auto flex items-center justify-center gap-2">
                            Set Appointment
                            <span class="text-base">→</span>
                        </a>
                    </div>
                </div>
<div class="flex-1 w-full relative pl-0 lg:pl-10">
<div class="absolute -inset-4 bg-gradient-to-tr from-blue-100 to-indigo-100 dark:from-blue-900/30 dark:to-indigo-900/30 rounded-[2.5rem] blur-2xl opacity-60 -z-10"></div>
<div class="w-full aspect-square rounded-[2rem] bg-slate-100 dark:bg-slate-800 shadow-2xl shadow-primary/10 ring-1 ring-slate-900/5 overflow-hidden relative group cursor-pointer">
<img src="<?php echo $cuImg('main_hero_image') ?: (BASE_URL . 'Endorser1.png'); ?>" alt="Dental Clinic" class="w-full h-full object-cover transition-all duration-700 ease-out group-hover:scale-110 group-hover:brightness-110" fetchpriority="high" decoding="async" width="800" height="800">
<div class="absolute inset-0 bg-gradient-to-t from-slate-900/50 via-transparent to-transparent opacity-80 group-hover:opacity-60 transition-opacity duration-700"></div>
<div class="absolute inset-0 bg-primary/0 group-hover:bg-primary/5 transition-colors duration-700 rounded-[2rem]"></div>
</div>
</div>
</div>
</div>
</section>
<section class="py-24 bg-surface-light dark:bg-surface-dark" id="services">
<div class="max-w-7xl mx-auto px-6 md:px-12">
<div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6">
<div class="max-w-2xl">
<h3 class="text-primary font-bold tracking-widest uppercase text-xs mb-3"><?php echo $cu('main_services_heading'); ?></h3>
<h2 class="text-3xl md:text-4xl font-bold tracking-tight text-slate-900 dark:text-white mb-4"><?php echo $cu('main_services_title'); ?></h2>
<p class="text-slate-600 dark:text-slate-400 text-lg"><?php echo $cu('main_services_description'); ?></p>
</div>
<a class="group flex items-center gap-2 text-primary font-bold hover:text-primary-dark transition-colors bg-white dark:bg-slate-800 px-5 py-2.5 rounded-full shadow-sm border border-slate-200 dark:border-slate-700" href="<?php echo BASE_URL; ?>ServicesClient.php">
                    View All Services
                    <span class="material-symbols-outlined text-sm group-hover:translate-x-1 transition-transform">arrow_forward</span>
</a>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<div class="group p-8 rounded-2xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/50 hover:border-primary/30 hover:shadow-soft transition-all duration-300 relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-blue-50 to-transparent dark:from-blue-900/10 rounded-bl-[3rem] -mr-6 -mt-6 transition-transform group-hover:scale-125 duration-500"></div>
<div class="relative z-10">
<div class="size-12 rounded-xl bg-blue-50 dark:bg-slate-700 text-primary flex items-center justify-center mb-6 group-hover:bg-primary group-hover:text-white transition-colors duration-300">
<span class="material-symbols-outlined text-2xl">dentistry</span>
</div>
<h3 class="text-lg font-bold mb-3 text-slate-900 dark:text-white">General Dentistry</h3>
<p class="text-slate-500 dark:text-slate-400 text-sm mb-6 leading-relaxed">Preventative care, checkups, and hygiene to maintain optimal oral health.</p>
<a href="<?php echo BASE_URL; ?>ServicesClient.php#general" class="inline-flex items-center text-xs font-bold text-primary uppercase tracking-wide group-hover:underline decoration-2 underline-offset-4">
                            Learn More
                        </a>
</div>
</div>
<div class="group p-8 rounded-2xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/50 hover:border-primary/30 hover:shadow-soft transition-all duration-300 relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-sky-50 to-transparent dark:from-sky-900/10 rounded-bl-[3rem] -mr-6 -mt-6 transition-transform group-hover:scale-125 duration-500"></div>
<div class="relative z-10">
<div class="size-12 rounded-xl bg-sky-50 dark:bg-slate-700 text-secondary flex items-center justify-center mb-6 group-hover:bg-secondary group-hover:text-white transition-colors duration-300">
<span class="material-symbols-outlined text-2xl">clean_hands</span>
</div>
<h3 class="text-lg font-bold mb-3 text-slate-900 dark:text-white">Cosmetic &amp; Whitening</h3>
<p class="text-slate-500 dark:text-slate-400 text-sm mb-6 leading-relaxed">Professional aesthetic treatments for a brighter, more confident smile.</p>
<a href="<?php echo BASE_URL; ?>ServicesClient.php#cosmetic" class="inline-flex items-center text-xs font-bold text-secondary uppercase tracking-wide group-hover:underline decoration-2 underline-offset-4">
                            Learn More
                        </a>
</div>
</div>
<div class="group p-8 rounded-2xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/50 hover:border-primary/30 hover:shadow-soft transition-all duration-300 relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-indigo-50 to-transparent dark:from-indigo-900/10 rounded-bl-[3rem] -mr-6 -mt-6 transition-transform group-hover:scale-125 duration-500"></div>
<div class="relative z-10">
<div class="size-12 rounded-xl bg-indigo-50 dark:bg-slate-700 text-indigo-500 flex items-center justify-center mb-6 group-hover:bg-indigo-500 group-hover:text-white transition-colors duration-300">
<span class="material-symbols-outlined text-2xl">health_and_beauty</span>
</div>
<h3 class="text-lg font-bold mb-3 text-slate-900 dark:text-white">Orthodontics</h3>
<p class="text-slate-500 dark:text-slate-400 text-sm mb-6 leading-relaxed">Correction of irregularities using modern clear aligners and braces.</p>
<a href="<?php echo BASE_URL; ?>ServicesClient.php#orthodontics" class="inline-flex items-center text-xs font-bold text-indigo-500 uppercase tracking-wide group-hover:underline decoration-2 underline-offset-4">
                            Learn More
                        </a>
</div>
</div>
<div class="group p-8 rounded-2xl bg-white dark:bg-slate-800 border border-slate-100 dark:border-slate-700/50 hover:border-primary/30 hover:shadow-soft transition-all duration-300 relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-gradient-to-br from-teal-50 to-transparent dark:from-teal-900/10 rounded-bl-[3rem] -mr-6 -mt-6 transition-transform group-hover:scale-125 duration-500"></div>
<div class="relative z-10">
<div class="size-12 rounded-xl bg-teal-50 dark:bg-slate-700 text-teal-500 flex items-center justify-center mb-6 group-hover:bg-teal-500 group-hover:text-white transition-colors duration-300">
<span class="material-symbols-outlined text-2xl">face_3</span>
</div>
<h3 class="text-lg font-bold mb-3 text-slate-900 dark:text-white">Pediatric Care</h3>
<p class="text-slate-500 dark:text-slate-400 text-sm mb-6 leading-relaxed">Gentle, reassuring dental care specifically designed for children.</p>
<a href="<?php echo BASE_URL; ?>ServicesClient.php#pediatric" class="inline-flex items-center text-xs font-bold text-teal-500 uppercase tracking-wide group-hover:underline decoration-2 underline-offset-4">
                            Learn More
                        </a>
</div>
</div>
</div>
</div>
</section>
<section class="py-24 bg-white dark:bg-background-dark relative overflow-hidden" id="team">
<div class="absolute top-0 left-0 w-full h-1/2 bg-slate-50 dark:bg-slate-900/30 -z-10"></div>
<div class="max-w-7xl mx-auto px-6 md:px-12">
<div class="bg-white dark:bg-slate-800 rounded-[2rem] p-8 md:p-12 lg:p-16 shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-700/50 overflow-hidden relative">
<div class="absolute top-0 right-0 w-96 h-96 bg-blue-50 dark:bg-blue-900/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2 pointer-events-none opacity-60"></div>
<div class="flex flex-col lg:flex-row gap-12 lg:gap-20 items-center relative z-10">
<div class="w-full lg:w-1/3 relative group">
<div class="absolute inset-0 bg-primary/20 rounded-2xl rotate-2 group-hover:rotate-3 transition-transform duration-500"></div>
<img src="<?php echo $cuImg('main_doctor_image') ?: (BASE_URL . 'picturenidok1.jpg'); ?>" alt="<?php echo $cu('main_doctor_name'); ?>" class="aspect-[3/4] w-full rounded-2xl object-cover bg-slate-200 relative z-10 shadow-lg border-4 border-white dark:border-slate-700" loading="lazy" decoding="async" width="400" height="533">
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
<p>
                                <?php echo $cu('main_doctor_bio1'); ?>
                            </p>
<p>
                                <?php echo $cu('main_doctor_bio2'); ?>
                            </p>
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

<style>
@keyframes scroll {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(calc(-50% - 0.75rem));
    }
}

.auto-scroll {
    animation: scroll 40s linear infinite;
    display: flex;
    gap: 1.5rem;
    will-change: transform;
}

.auto-scroll:hover {
    animation-play-state: paused;
}

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

<script>
        // Replace 'YOUR_RECAPTCHA_SITE_KEY' with your actual reCAPTCHA v3 Site Key
        const RECAPTCHA_SITE_KEY = 'YOUR_RECAPTCHA_SITE_KEY';
        
        // Initialize EmailJS
        (function(){
            emailjs.init("yF8aTwk2JYrSOIn02");
        })();

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

        async function handleFormSubmit(event) {
            event.preventDefault();
            
            const form = document.getElementById('contactForm');
            const messageDiv = document.getElementById('formMessage');
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            
            // Get form values
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const concern = document.getElementById('concern').value;
            const message = document.getElementById('message').value.trim();
            
            // Hide previous messages
            messageDiv.classList.add('hidden');
            
            // Validation
            if (!name || !email || !phone || !concern || !message) {
                showMessage('Please fill in all required fields.', 'error');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }
            
            // Disable submit button and show loading state
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="flex items-center gap-2"><span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>Sending...</span>';
            
            try {
                // Execute reCAPTCHA v3
                let recaptchaToken = '';
                if (RECAPTCHA_SITE_KEY && RECAPTCHA_SITE_KEY !== 'YOUR_RECAPTCHA_SITE_KEY') {
                    try {
                        recaptchaToken = await grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'submit' });
                    } catch (recaptchaError) {
                        console.error('reCAPTCHA Error:', recaptchaError);
                        // Continue without reCAPTCHA if it fails (for development)
                    }
                }
                
                // Prepare email template parameters
                const templateParams = {
                    user_name: name,
                    user_email: email,
                    phone_number: phone,
                    concern_type: concern,
                    user_message: message,
                    recaptcha_token: recaptchaToken // Include reCAPTCHA token in email (optional)
                };
                
                // Send email via EmailJS
                const response = await emailjs.send(
                    'service_q99148g',
                    'template_1w5v9oe',
                    templateParams
                );
                
                // Success
                showMessage('Thank you! Your message has been sent successfully. We\'ll get back to you within 24 hours.', 'success');
                form.reset();
                
            } catch (error) {
                console.error('EmailJS Error:', error);
                showMessage('Sorry, there was an error sending your message. Please try again or contact us directly at hello@drcgdental.com', 'error');
            } finally {
                // Re-enable submit button
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
            
            // Scroll to message
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

