<?php
/**
 * Patient-facing services page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/tenant_bootstrap.php';
require_once __DIR__ . '/includes/clinic_customization.php';
require_once __DIR__ . '/includes/tenant_public_services_lib.php';

$publicServices = tenant_public_services_fetch_for_tenant($pdo, (string) $currentTenantId);

$cu = static function (string $k, string $default = '') use ($CLINIC): string {
    $v = isset($CLINIC[$k]) ? trim((string) $CLINIC[$k]) : '';
    if ($v === '' && $default !== '') {
        $v = $default;
    }
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
};
$cuMultiline = static function (string $k, string $default = '') use ($CLINIC): string {
    $v = isset($CLINIC[$k]) ? trim((string) $CLINIC[$k]) : '';
    if ($v === '' && $default !== '') {
        $v = $default;
    }
    return nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), false);
};

$pageTitle = isset($CLINIC['services_page_title']) ? trim((string) $CLINIC['services_page_title']) : '';
if ($pageTitle === '') {
    $pageTitle = 'Our Services';
}
require_once __DIR__ . '/includes/header.php';

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
                <?php echo $cu('services_hero_badge', 'Clinically Proven Care'); ?>
            </div>
<h1 class="font-display text-5xl md:text-7xl font-extrabold tracking-tight text-slate-900 dark:text-white mb-6">
                <?php echo $cu('services_hero_title_before', 'Our Specialized '); ?><span class="text-primary font-editorial italic font-normal"><?php echo $cu('services_hero_title_accent', 'Services'); ?></span>
</h1>
<p class="text-slate-600 dark:text-slate-400 max-w-2xl mx-auto text-xl font-medium leading-relaxed">
                <?php echo $cuMultiline('services_hero_subtitle', 'Elevating dental wellness through clinical mastery and curated patient experiences. Discover our full spectrum of elite treatments.'); ?>
            </p>
</section>
<!-- Vertical Services List (from tenant catalog) -->
<section class="max-w-5xl mx-auto px-6 md:px-12 pb-20 space-y-6">
<?php if (count($publicServices) === 0) { ?>
<div class="text-center py-20 px-6 rounded-3xl border border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/20">
<p class="text-slate-500 dark:text-slate-400 font-medium text-lg">No services are listed yet. Please check back soon.</p>
</div>
<?php } else { ?>
<?php foreach ($publicServices as $svc) {
    $st = htmlspecialchars((string) ($svc['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sd = trim((string) ($svc['description'] ?? ''));
    $pr = trim((string) ($svc['price_range'] ?? ''));
?>
<div class="service-card flex flex-col md:flex-row items-center p-8 bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 rounded-3xl shadow-sm gap-8">
<div class="flex-grow text-center md:text-left">
<h3 class="text-2xl font-extrabold text-primary mb-2 font-display"><?php echo $st; ?></h3>
<?php if ($sd !== '') { ?>
<p class="text-slate-600 dark:text-slate-400 font-medium text-lg leading-relaxed"><?php echo nl2br(htmlspecialchars($sd, ENT_QUOTES, 'UTF-8'), false); ?></p>
<?php } ?>
<?php if ($pr !== '') { ?>
<p class="text-slate-700 dark:text-slate-300 font-semibold text-base mt-3 tabular-nums"><?php echo htmlspecialchars($pr, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>
</div>
<div class="shrink-0">
<a href="<?php echo $bookUrl; ?>" class="inline-flex px-8 py-4 bg-primary hover:bg-primary-dark text-white rounded-xl font-bold text-sm uppercase tracking-widest transition-all items-center gap-2">
                        <?php echo $cu('services_card_book_label', 'Book Appointment'); ?>
                        <span class="material-symbols-outlined text-sm">calendar_today</span>
</a>
</div>
</div>
<?php } ?>
<?php } ?>
</section>
<!-- Final CTA Section -->
<section class="max-w-7xl mx-auto px-6 md:px-12 pb-24">
<div class="rounded-3xl bg-primary p-12 md:p-20 text-center text-white shadow-xl shadow-primary/20">
<h2 class="font-display text-4xl md:text-5xl font-extrabold tracking-tight mb-6"><?php echo $cu('services_cta_title', 'Ready to start your journey?'); ?></h2>
<p class="text-white/80 text-lg md:text-xl max-w-xl mx-auto mb-10 font-medium"><?php echo $cuMultiline('services_cta_subtext', 'Join thousands of happy patients who trust us with their oral health and aesthetic transformations.'); ?></p>
<a href="<?php echo $bookUrl; ?>" class="inline-block bg-white text-primary px-12 py-5 rounded-full font-extrabold text-sm uppercase tracking-[0.2em] hover:scale-105 transition-all shadow-lg active:scale-95">
                    <?php echo $cu('services_cta_button', 'Book Your Consultation'); ?>
                </a>
</div>
</section>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</div>
