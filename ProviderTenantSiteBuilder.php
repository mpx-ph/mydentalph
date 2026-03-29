<?php
declare(strict_types=1);

require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';
require_once __DIR__ . '/provider_tenant_canonical_context.inc.php';
require_once __DIR__ . '/provider_tenant_plan_and_site_context.inc.php';
require_once __DIR__ . '/provider_tenant_header_context.inc.php';
require_once __DIR__ . '/provider_tenant_site_customization_lib.php';

$provider_nav_active = 'customize';

$site_opts = provider_tenant_site_merged_options($pdo, (string) $tenant_id);
$cnTenant = trim((string) ($tenant['clinic_name'] ?? ''));
if ($cnTenant !== '') {
    $site_opts['clinic_name'] = $cnTenant;
}

$allowed_fonts = [
    'Manrope', 'Inter', 'Plus Jakarta Sans', 'DM Sans', 'Outfit', 'Source Sans 3',
    'Playfair Display', 'Lora', 'Merriweather', 'Nunito Sans', 'Work Sans',
];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
if ($host === '') {
    $host = 'localhost';
}
$slugForPreview = trim((string) ($clinic_slug ?? ($tenant['clinic_slug'] ?? '')));
$preview_base = rtrim((string) ($tenant_public_site_url ?? ''), '/');
if ($preview_base === '' && $slugForPreview !== '') {
    $preview_base = $scheme . '://' . $host . '/' . rawurlencode($slugForPreview);
}
$preview_urls = [
    'home' => $preview_base !== '' ? ($preview_base . '/') : '',
    'services' => $preview_base !== '' ? ($preview_base . '/services') : '',
    'about' => $preview_base !== '' ? ($preview_base . '/about') : '',
    'contact' => $preview_base !== '' ? ($preview_base . '/contact') : '',
];

$preview_urls_json = json_encode($preview_urls, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
$allowed_fonts_json = json_encode($allowed_fonts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

/**
 * @param array<string, string> $site_opts
 */
function sb_val(array $site_opts, string $key): string
{
    return htmlspecialchars((string) ($site_opts[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * @param list<string> $allowed_fonts
 * @param array<string, string> $site_opts
 */
function sb_font_select(string $key, string $label, array $allowed_fonts, array $site_opts, bool $is_owner): void
{
    $cur = trim((string) ($site_opts[$key] ?? ''));
    $fontsForSelect = $allowed_fonts;
    if ($cur !== '' && !in_array($cur, $fontsForSelect, true)) {
        array_unshift($fontsForSelect, $cur);
    }
    $dis = $is_owner ? '' : 'disabled';
    echo '<div class="space-y-2">';
    echo '<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="f_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
    echo '<select class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary ' . ($is_owner ? '' : 'opacity-70 cursor-not-allowed') . '" id="f_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" data-opt-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" ' . $dis . '>';
    foreach ($fontsForSelect as $f) {
        $sel = strcasecmp($cur, $f) === 0 ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($f, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>' . htmlspecialchars($f, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    echo '</select></div>';
}

/**
 * @param array<string, string> $site_opts
 */
function sb_text(string $key, string $label, array $site_opts, bool $is_owner, bool $textarea = false): void
{
    $dis = $is_owner ? '' : 'disabled readonly';
    echo '<div class="space-y-2">';
    echo '<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="t_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
    if ($textarea) {
        echo '<textarea class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-4 py-3 text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary min-h-[5rem] ' . ($is_owner ? '' : 'opacity-70') . '" id="t_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" data-opt-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" rows="3" ' . $dis . '>' . sb_val($site_opts, $key) . '</textarea>';
    } else {
        echo '<input type="text" class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary ' . ($is_owner ? '' : 'opacity-70') . '" id="t_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" data-opt-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="' . sb_val($site_opts, $key) . '" ' . $dis . '/>';
    }
    echo '</div>';
}

/**
 * @param array<string, string> $site_opts
 */
function sb_color(string $key, string $label, array $site_opts, bool $is_owner): void
{
    $raw = preg_replace('/^#/', '', trim((string) ($site_opts[$key] ?? '')));
    if (strlen($raw) !== 6 || !ctype_xdigit($raw)) {
        $raw = '2b8cee';
    }
    $dis = $is_owner ? '' : 'disabled';
    echo '<div class="space-y-2">';
    echo '<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="c_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
    echo '<div class="flex items-center gap-3">';
    echo '<input type="color" class="h-12 w-14 rounded-xl border border-slate-200 cursor-pointer shrink-0" id="c_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" data-opt-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="#' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . '" ' . $dis . '/>';
    echo '<input type="text" class="flex-1 bg-slate-50 border border-slate-300 rounded-2xl px-4 py-3 text-sm font-mono uppercase focus:ring-2 focus:ring-primary/20 focus:border-primary ' . ($is_owner ? '' : 'opacity-70') . '" data-opt-hex="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . '" maxlength="6" ' . $dis . '/>';
    echo '</div></div>';
}

/**
 * @param array<string, string> $site_opts
 */
function sb_range(string $key, string $label, array $site_opts, bool $is_owner, int $min, int $max, int $step = 1): void
{
    $v = (int) ($site_opts[$key] ?? $min);
    $v = max($min, min($max, $v));
    $dis = $is_owner ? '' : 'disabled';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between items-baseline">';
    echo '<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="r_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
    echo '<span class="text-xs font-bold text-primary tabular-nums" data-range-show="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">' . $v . '</span>';
    echo '</div>';
    echo '<input type="range" class="w-full accent-primary" id="r_' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" data-opt-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" min="' . $min . '" max="' . $max . '" step="' . $step . '" value="' . $v . '" ' . $dis . '/>';
    echo '</div>';
}

/**
 * @param array<string, string> $site_opts
 */
function sb_file(string $key, string $label, array $site_opts, bool $is_owner): void
{
    $hint = trim((string) ($site_opts[$key] ?? ''));
    $dis = $is_owner ? '' : 'disabled';
    echo '<div class="space-y-2">';
    echo '<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>';
    if ($hint !== '') {
        echo '<p class="text-[11px] text-on-surface-variant/80 truncate font-mono" title="' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '<input type="file" accept="image/jpeg,image/png,image/webp,image/svg+xml,.ico" class="block w-full text-xs text-on-surface-variant file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:bg-primary/10 file:text-primary file:font-bold" data-file-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" ' . $dis . '/>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Customize site</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-variant": "#f7f9ff",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#c0c7d4",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#404752",
                        "background": "#f1f5f9",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "error": "#ba1a1a",
                        "error-container": "#fff1f2"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        html { scrollbar-gutter: stable; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }
        .section-card { border: 1px solid rgba(0, 0, 0, 0.04); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03); }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .active-glow { box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4); }
        .provider-nav-link:not(.provider-nav-link--active):hover { transform: translateX(4px); }
        @keyframes provider-page-in { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        .provider-page-enter { animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards; }
        .builder-tab { transition: color 0.2s, background 0.2s, border-color 0.2s; }
        .builder-tab--active { background: rgba(43, 139, 235, 0.1); color: #2b8beb; border-color: rgba(43, 139, 235, 0.25); }
        .builder-panel { display: none; }
        .builder-panel--active { display: block; }
        .preview-frame-wrap { box-shadow: 0 24px 48px -12px rgba(15, 23, 42, 0.12); }
        .preview-viewport-tab { transition: color 0.2s, background 0.2s, border-color 0.2s; }
        .preview-viewport-tab--active { background: rgba(43, 139, 235, 0.12); color: #2b8beb; border-color: rgba(43, 139, 235, 0.3); }
        .preview-viewport-shell { transition: max-width 0.35s cubic-bezier(0.22, 1, 0.36, 1); }
        .preview-viewport-shell--desktop { max-width: 100%; }
        .preview-viewport-shell--tablet { max-width: 48rem; }
        .preview-viewport-shell--mobile { max-width: 24.375rem; }
    </style>
</head>
<body class="font-body text-on-background mesh-bg min-h-screen selection:bg-primary/10">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<?php include __DIR__ . '/provider_tenant_top_header.inc.php'; ?>
<main class="ml-64 pt-[4.5rem] sm:pt-24 min-h-screen provider-page-enter">
<div class="px-4 sm:px-6 lg:px-10 pb-16 max-w-[1920px] mx-auto w-full">
<section class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-8">
<div>
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> Patient website</div>
<h1 class="font-headline text-3xl sm:text-4xl font-extrabold tracking-tighter text-on-background mt-3">Customize <span class="font-editorial italic font-normal text-primary">your clinic site</span></h1>
<p class="text-on-surface-variant font-medium mt-3 max-w-xl text-sm sm:text-base">Adjust branding, colors, typography, and page copy. Changes apply to your public home, services, about, and contact pages for this clinic only.</p>
</div>
<div class="flex flex-wrap items-center gap-3">
<?php if ($tenant_public_site_url !== ''): ?>
<a href="<?php echo htmlspecialchars($tenant_public_site_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl border border-slate-200 bg-white text-on-background text-xs font-black uppercase tracking-widest hover:border-primary/30 hover:bg-primary/5 transition-all">
<span class="material-symbols-outlined text-lg">open_in_new</span> Open live site
</a>
<?php endif; ?>
<button type="button" id="btnRefreshPreview" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-primary text-white text-xs font-black uppercase tracking-widest hover:shadow-lg hover:shadow-primary/20 transition-all">
<span class="material-symbols-outlined text-lg">refresh</span> Refresh preview
</button>
</div>
</section>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
<div class="xl:col-span-5 space-y-4">
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
<div class="section-card rounded-2xl bg-white/90 p-4 border border-white/80">
<p class="text-[10px] font-black uppercase tracking-widest text-primary flex items-center gap-2"><span class="material-symbols-outlined text-base">sync</span> Autosave</p>
<p class="text-xs text-on-surface-variant font-medium mt-2">Edits sync to your clinic&rsquo;s site data automatically.</p>
</div>
<div class="section-card rounded-2xl bg-white/90 p-4 border border-white/80">
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant flex items-center gap-2"><span class="material-symbols-outlined text-base">preview</span> Preview</p>
<p class="text-xs text-on-surface-variant font-medium mt-2">Pick a page below to load it in the canvas.</p>
</div>
<div class="section-card rounded-2xl bg-white/90 p-4 border border-white/80">
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant flex items-center gap-2"><span class="material-symbols-outlined text-base">shield_lock</span> Scoped</p>
<p class="text-xs text-on-surface-variant font-medium mt-2">Only your <span class="font-bold text-on-background">tenant</span> record is updated.</p>
</div>
</div>

<?php if (!$is_owner): ?>
<div class="p-4 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-900 text-sm font-medium">Only the clinic owner can publish changes. You can still preview the live site.</div>
<?php endif; ?>

<?php if ($preview_urls['home'] === ''): ?>
<div class="p-4 rounded-2xl bg-slate-100 border border-slate-200 text-on-surface-variant text-sm font-medium">Set up your public clinic URL (slug) from subscription onboarding to enable the live preview canvas.</div>
<?php endif; ?>

<div class="section-card rounded-[2rem] bg-white p-6 sm:p-8 border border-white/90">
<div class="flex flex-wrap gap-2 mb-6">
<button type="button" class="builder-tab builder-tab--active px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-transparent" data-tab="branding">Branding</button>
<button type="button" class="builder-tab px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-transparent text-on-surface-variant" data-tab="colors">Colors</button>
<button type="button" class="builder-tab px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-transparent text-on-surface-variant" data-tab="type">Type</button>
<button type="button" class="builder-tab px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-transparent text-on-surface-variant" data-tab="layout">Layout</button>
<button type="button" class="builder-tab px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-transparent text-on-surface-variant" data-tab="pages">Pages</button>
</div>

<div class="mb-6 pb-6 border-b border-slate-100">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1 block mb-2">Preview page</label>
<select id="previewPageSelect" class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary">
<option value="home">Home</option>
<option value="services">Services</option>
<option value="about">About</option>
<option value="contact">Contact</option>
</select>
</div>

<div id="panel-branding" class="builder-panel builder-panel--active space-y-6 max-h-[52vh] overflow-y-auto pr-1">
<p class="text-xs text-on-surface-variant leading-relaxed">Logo and images are stored under your tenant in <code class="text-[11px] bg-slate-100 px-1 rounded">clinic_customization_tenant</code>. Clinic name also updates your tenant profile.</p>
<?php sb_text('clinic_name', 'Clinic display name', $site_opts, $is_owner); ?>
<?php sb_file('logo_nav', 'Navigation logo (PNG / JPG / WebP)', $site_opts, $is_owner); ?>
<?php sb_file('main_hero_image', 'Home hero background image', $site_opts, $is_owner); ?>
<?php sb_file('about_hero_image', 'About page hero image', $site_opts, $is_owner); ?>
<?php sb_file('site_favicon', 'Favicon', $site_opts, $is_owner); ?>
</div>

<div id="panel-colors" class="builder-panel space-y-6 max-h-[52vh] overflow-y-auto pr-1">
<?php sb_color('color_primary', 'Primary', $site_opts, $is_owner); ?>
<?php sb_color('color_primary_dark', 'Primary dark', $site_opts, $is_owner); ?>
<?php sb_color('color_primary_light', 'Primary light', $site_opts, $is_owner); ?>
</div>

<div id="panel-type" class="builder-panel space-y-6 max-h-[52vh] overflow-y-auto pr-1">
<?php sb_font_select('theme_font_headline', 'Headline font', $allowed_fonts, $site_opts, $is_owner); ?>
<?php sb_font_select('theme_font_display', 'Display font (services hero)', $allowed_fonts, $site_opts, $is_owner); ?>
<?php sb_font_select('theme_font_body', 'Body font', $allowed_fonts, $site_opts, $is_owner); ?>
<?php sb_font_select('theme_font_editorial', 'Editorial / accent font', $allowed_fonts, $site_opts, $is_owner); ?>
<?php sb_range('theme_base_font_px', 'Base font size (px)', $site_opts, $is_owner, 14, 22, 1); ?>
<div class="space-y-2">
<label class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70 ml-1" for="lh">Line height</label>
<input type="number" step="0.05" min="1.2" max="2" class="w-full bg-slate-50 border border-slate-300 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-primary/20 focus:border-primary <?php echo $is_owner ? '' : 'opacity-70'; ?>" id="lh" data-opt-key="theme_line_height" value="<?php echo sb_val($site_opts, 'theme_line_height'); ?>" <?php echo $is_owner ? '' : 'disabled'; ?>/>
</div>
<?php sb_range('theme_heading_weight', 'Heading weight', $site_opts, $is_owner, 500, 900, 50); ?>
</div>

<div id="panel-layout" class="builder-panel space-y-6 max-h-[52vh] overflow-y-auto pr-1">
<p class="text-xs text-on-surface-variant leading-relaxed">Controls corner rounding for Tailwind radius tokens on patient pages (buttons, cards).</p>
<?php sb_range('theme_radius_lg_px', 'Component rounding (px)', $site_opts, $is_owner, 6, 28, 1); ?>
</div>

<div id="panel-pages" class="builder-panel space-y-8 max-h-[52vh] overflow-y-auto pr-1">
<div class="space-y-4">
<h3 class="text-[10px] font-black uppercase tracking-widest text-primary">Home</h3>
<?php sb_text('main_hero_line1', 'Hero line 1', $site_opts, $is_owner); ?>
<?php sb_text('main_hero_line2', 'Hero line 2', $site_opts, $is_owner); ?>
<?php sb_text('main_hero_line3', 'Hero accent line', $site_opts, $is_owner); ?>
<?php sb_text('main_hero_subtext', 'Hero subtext', $site_opts, $is_owner, true); ?>
<?php sb_text('main_services_heading', 'Services section label', $site_opts, $is_owner); ?>
<?php sb_text('main_services_title', 'Services title', $site_opts, $is_owner); ?>
<?php sb_text('main_services_description', 'Services description', $site_opts, $is_owner, true); ?>
</div>
<div class="space-y-4 pt-4 border-t border-slate-100">
<h3 class="text-[10px] font-black uppercase tracking-widest text-primary">Services page</h3>
<?php sb_text('services_hero_badge', 'Badge', $site_opts, $is_owner); ?>
<?php sb_text('services_hero_title_before', 'Title (before accent)', $site_opts, $is_owner); ?>
<?php sb_text('services_hero_title_accent', 'Accent word', $site_opts, $is_owner); ?>
<?php sb_text('services_hero_subtitle', 'Subtitle', $site_opts, $is_owner, true); ?>
</div>
<div class="space-y-4 pt-4 border-t border-slate-100">
<h3 class="text-[10px] font-black uppercase tracking-widest text-primary">About</h3>
<?php sb_text('about_intro_heading', 'Intro heading', $site_opts, $is_owner); ?>
<?php sb_text('about_intro_text', 'Intro text', $site_opts, $is_owner, true); ?>
</div>
<div class="space-y-4 pt-4 border-t border-slate-100">
<h3 class="text-[10px] font-black uppercase tracking-widest text-primary">Contact</h3>
<?php sb_text('contact_hero_badge', 'Badge', $site_opts, $is_owner); ?>
<?php sb_text('contact_hero_title_before', 'Title (before accent)', $site_opts, $is_owner); ?>
<?php sb_text('contact_hero_title_accent', 'Accent word', $site_opts, $is_owner); ?>
<?php sb_text('contact_hero_subtext', 'Subtext', $site_opts, $is_owner, true); ?>
</div>
</div>

<div class="mt-8 pt-6 border-t border-slate-100 flex items-center justify-between gap-4">
<div class="flex items-center gap-2 text-xs font-bold text-on-surface-variant" id="saveStatus" data-state="idle">
<span class="material-symbols-outlined text-base text-emerald-600 hidden" id="saveIconOk">check_circle</span>
<span class="material-symbols-outlined text-base text-primary animate-spin hidden" id="saveIconBusy">progress_activity</span>
<span id="saveLabel"><?php echo $is_owner ? 'Saved' : 'View only'; ?></span>
</div>
</div>
</div>
</div>

<div class="xl:col-span-7 space-y-3">
<div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Preview viewport size">
<button type="button" role="tab" aria-selected="true" class="preview-viewport-tab preview-viewport-tab--active px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-transparent" data-preview-viewport="desktop">Desktop</button>
<button type="button" role="tab" aria-selected="false" class="preview-viewport-tab px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-transparent text-on-surface-variant" data-preview-viewport="tablet">Tablet</button>
<button type="button" role="tab" aria-selected="false" class="preview-viewport-tab px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border border-transparent text-on-surface-variant" data-preview-viewport="mobile">Mobile</button>
</div>
<div class="preview-frame-wrap rounded-[2rem] bg-slate-900/90 p-3 sm:p-4 border border-slate-800">
<div class="flex items-center gap-2 px-3 py-2 mb-2">
<span class="h-3 w-3 rounded-full bg-red-400/90"></span>
<span class="h-3 w-3 rounded-full bg-amber-400/90"></span>
<span class="h-3 w-3 rounded-full bg-emerald-400/90"></span>
<span class="text-[10px] font-mono text-slate-400 ml-3 truncate" id="previewUrlBar">—</span>
</div>
<div class="flex justify-center w-full">
<div id="previewViewportShell" class="preview-viewport-shell preview-viewport-shell--desktop w-full rounded-xl sm:rounded-2xl overflow-hidden bg-white aspect-[16/11] min-h-[380px]">
<?php if ($preview_urls['home'] !== ''): ?>
<iframe title="Site preview" class="w-full h-full min-h-[380px] border-0" id="sitePreviewFrame" src="<?php echo htmlspecialchars($preview_urls['home'], ENT_QUOTES, 'UTF-8'); ?>"></iframe>
<?php else: ?>
<div class="w-full h-full min-h-[380px] flex items-center justify-center text-slate-500 text-sm font-medium p-8 text-center">Preview unavailable until your clinic slug is active.</div>
<?php endif; ?>
</div>
</div>
</div>
</div>
</div>
</div>
</main>
<?php include __DIR__ . '/provider_tenant_profile_modal.inc.php'; ?>
<script>
(function () {
    var previewUrls = <?php echo $preview_urls_json !== false ? $preview_urls_json : '{}'; ?>;
    var canEdit = <?php echo $is_owner ? 'true' : 'false'; ?>;

    var frame = document.getElementById('sitePreviewFrame');
    var previewSelect = document.getElementById('previewPageSelect');
    var viewportShell = document.getElementById('previewViewportShell');
    var urlBar = document.getElementById('previewUrlBar');
    var saveLabel = document.getElementById('saveLabel');
    var saveIconOk = document.getElementById('saveIconOk');
    var saveIconBusy = document.getElementById('saveIconBusy');
    var debounceTimer = null;

    function setPreviewUrl(page) {
        var u = previewUrls[page] || '';
        if (urlBar) urlBar.textContent = u || '—';
        if (!frame || !u) return;
        frame.src = u + (u.indexOf('?') >= 0 ? '&' : '?') + 'cb=' + Date.now();
    }

    function setSaveState(state) {
        if (!saveLabel) return;
        saveIconOk.classList.toggle('hidden', state !== 'saved');
        saveIconBusy.classList.toggle('hidden', state !== 'saving');
        if (state === 'saving') saveLabel.textContent = 'Saving…';
        else if (state === 'saved') saveLabel.textContent = 'Saved';
        else if (state === 'error') saveLabel.textContent = 'Could not save';
        else saveLabel.textContent = canEdit ? 'Waiting…' : 'View only';
    }

    function collectPatch() {
        var patch = {};
        document.querySelectorAll('[data-opt-key]').forEach(function (el) {
            var k = el.getAttribute('data-opt-key');
            if (!k) return;
            if (el.type === 'color') {
                var hx = el.value.replace(/^#/, '');
                patch[k] = hx;
                var twin = document.querySelector('[data-opt-hex="' + k + '"]');
                if (twin) twin.value = hx;
            } else if (el.type === 'range') {
                patch[k] = String(el.value);
            } else {
                patch[k] = el.value;
            }
        });
        document.querySelectorAll('[data-opt-hex]').forEach(function (el) {
            var k = el.getAttribute('data-opt-hex');
            if (!k || patch[k] !== undefined) return;
            var v = el.value.replace(/^#/, '').replace(/[^a-fA-F0-9]/g, '').slice(0, 6);
            patch[k] = v;
        });
        return patch;
    }

    function scheduleSave() {
        if (!canEdit) return;
        var patch = collectPatch();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { savePatch(patch); }, 750);
    }

    function savePatch(patch) {
        var hasFile = false;
        var fd = new FormData();
        fd.append('data', JSON.stringify({ patch: patch }));
        document.querySelectorAll('input[type="file"][data-file-key]').forEach(function (inp) {
            var fk = inp.getAttribute('data-file-key');
            if (!fk || !inp.files || !inp.files.length) return;
            hasFile = true;
            fd.append('file_' + fk, inp.files[0]);
        });
        setSaveState('saving');
        var opt = { method: 'POST', body: hasFile ? fd : JSON.stringify({ patch: patch }), credentials: 'same-origin' };
        if (!hasFile) {
            opt.headers = { 'Content-Type': 'application/json' };
        }
        fetch('ProviderTenantSiteCustomizationApi.php', opt)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    setSaveState('saved');
                    if (frame && previewSelect) setPreviewUrl(previewSelect.value);
                } else {
                    setSaveState('error');
                }
            })
            .catch(function () { setSaveState('error'); });
    }

    function setPreviewViewport(mode) {
        if (!viewportShell) return;
        var m = mode === 'tablet' || mode === 'mobile' ? mode : 'desktop';
        viewportShell.classList.remove('preview-viewport-shell--desktop', 'preview-viewport-shell--tablet', 'preview-viewport-shell--mobile');
        viewportShell.classList.add('preview-viewport-shell--' + m);
        document.querySelectorAll('[data-preview-viewport]').forEach(function (b) {
            var on = b.getAttribute('data-preview-viewport') === m;
            b.classList.toggle('preview-viewport-tab--active', on);
            b.classList.toggle('text-on-surface-variant', !on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    document.querySelectorAll('[data-preview-viewport]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setPreviewViewport(btn.getAttribute('data-preview-viewport') || 'desktop');
        });
    });

    document.querySelectorAll('.builder-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tab = btn.getAttribute('data-tab');
            document.querySelectorAll('.builder-tab').forEach(function (b) {
                var on = b === btn;
                b.classList.toggle('builder-tab--active', on);
                b.classList.toggle('text-on-surface-variant', !on);
            });
            document.querySelectorAll('.builder-panel').forEach(function (p) {
                p.classList.toggle('builder-panel--active', p.id === 'panel-' + tab);
            });
        });
    });

    document.querySelectorAll('[data-opt-key], [data-opt-hex]').forEach(function (el) {
        el.addEventListener('input', scheduleSave);
        el.addEventListener('change', scheduleSave);
    });

    document.querySelectorAll('input[type="file"][data-file-key]').forEach(function (el) {
        el.addEventListener('change', function () {
            if (!canEdit) return;
            savePatch(collectPatch());
        });
    });

    document.querySelectorAll('input[type="range"][data-opt-key]').forEach(function (el) {
        var k = el.getAttribute('data-opt-key');
        var show = document.querySelector('[data-range-show="' + k + '"]');
        el.addEventListener('input', function () { if (show) show.textContent = el.value; });
    });

    if (previewSelect) {
        previewSelect.addEventListener('change', function () { setPreviewUrl(previewSelect.value); });
        setPreviewUrl(previewSelect.value);
    }

    var btnRef = document.getElementById('btnRefreshPreview');
    if (btnRef && previewSelect) {
        btnRef.addEventListener('click', function () { setPreviewUrl(previewSelect.value); });
    }

    document.querySelectorAll('[data-opt-hex]').forEach(function (hexInp) {
        var k = hexInp.getAttribute('data-opt-hex');
        var colorInp = document.querySelector('input[type="color"][data-opt-key="' + k + '"]');
        if (!colorInp) return;
        hexInp.addEventListener('change', function () {
            var v = hexInp.value.replace(/^#/, '').replace(/[^a-fA-F0-9]/g, '').slice(0, 6);
            if (v.length === 6) colorInp.value = '#' + v;
        });
    });

    colorInpSync();
    function colorInpSync() {
        document.querySelectorAll('input[type="color"][data-opt-key]').forEach(function (c) {
            var k = c.getAttribute('data-opt-key');
            var hexInp = document.querySelector('[data-opt-hex="' + k + '"]');
            if (hexInp) hexInp.value = c.value.replace(/^#/, '');
        });
    }
})();
</script>
</body></html>
