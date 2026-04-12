<?php
/**
 * Header Include
 * Use this for client-facing pages
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}
if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . '/');
}
if (!defined('SITE_NAME')) {
    define('SITE_NAME', 'Dental Clinic');
}
if (!isset($CLINIC)) {
    require_once __DIR__ . '/clinic_customization.php';
}
$pageTitle = isset($pageTitle) ? $pageTitle : 'Dental Clinic';
$hex = function($k) use ($CLINIC) {
    $v = isset($CLINIC[$k]) ? trim($CLINIC[$k]) : '';
    $v = preg_replace('/^#/', '', $v);
    if (strlen($v) === 6 && ctype_xdigit($v)) return '#' . $v;
    return '';
};
$cPrimary = $hex('color_primary') ?: '#2b8cee';
$cPrimaryDark = $hex('color_primary_dark') ?: '#1a6cb6';
$cPrimaryLight = $hex('color_primary_light') ?: '#eef7ff';

$tfHeadline = trim((string) ($CLINIC['theme_font_headline'] ?? '')) ?: 'Manrope';
$tfBody = trim((string) ($CLINIC['theme_font_body'] ?? '')) ?: 'Inter';
$tfEditorial = trim((string) ($CLINIC['theme_font_editorial'] ?? '')) ?: 'Playfair Display';
$tfDisplay = trim((string) ($CLINIC['theme_font_display'] ?? '')) ?: 'Plus Jakarta Sans';

$fontFamiliesUnique = [];
foreach ([$tfHeadline, $tfBody, $tfEditorial, $tfDisplay] as $fn) {
    if ($fn !== '' && !in_array($fn, $fontFamiliesUnique, true)) {
        $fontFamiliesUnique[] = $fn;
    }
}
$gfQueryParts = [];
foreach ($fontFamiliesUnique as $name) {
    $q = str_replace(' ', '+', $name);
    $lower = strtolower($name);
    if (strpos($lower, 'playfair') !== false || strpos($lower, 'lora') !== false) {
        $gfQueryParts[] = 'family=' . $q . ':ital,wght@0,400;0,600;0,700;1,400';
    } else {
        $gfQueryParts[] = 'family=' . $q . ':wght@400;500;600;700;800';
    }
}
$gfHref = $gfQueryParts !== [] ? ('https://fonts.googleapis.com/css2?' . implode('&', $gfQueryParts) . '&display=swap') : '';

$basePx = (int) ($CLINIC['theme_base_font_px'] ?? 16);
$basePx = max(14, min(22, $basePx));
$lineHeight = (float) ($CLINIC['theme_line_height'] ?? 1.6);
if ($lineHeight < 1.2 || $lineHeight > 2) {
    $lineHeight = 1.6;
}
$headingWt = (int) ($CLINIC['theme_heading_weight'] ?? 800);
$headingWt = max(500, min(900, $headingWt));

$radiusPx = (int) ($CLINIC['theme_radius_lg_px'] ?? 12);
$radiusPx = max(6, min(28, $radiusPx));
$r = $radiusPx / 16.0;
$brDefault = round($r * 0.45, 3) . 'rem';
$brLg = round($r * 0.85, 3) . 'rem';
$brXl = round($r * 1.15, 3) . 'rem';
$br2xl = round($r * 1.65, 3) . 'rem';
$br3xl = round($r * 2.15, 3) . 'rem';

$favRaw = trim((string) ($CLINIC['site_favicon'] ?? ''));
$favUrl = '';
if ($favRaw !== '') {
    $favUrl = (strpos($favRaw, 'http') === 0) ? $favRaw : (BASE_URL . ltrim($favRaw, '/'));
}
$favPathForMime = $favRaw !== '' ? $favRaw : 'favicon.jpg';
$favExt = strtolower((string) pathinfo(parse_url($favPathForMime, PHP_URL_PATH) ?: $favPathForMime, PATHINFO_EXTENSION));
$favMime = 'image/jpeg';
if ($favExt === 'png') {
    $favMime = 'image/png';
} elseif ($favExt === 'svg') {
    $favMime = 'image/svg+xml';
} elseif ($favExt === 'ico') {
    $favMime = 'image/x-icon';
} elseif ($favExt === 'webp') {
    $favMime = 'image/webp';
}
$favHref = $favUrl !== '' ? $favUrl : (BASE_URL . 'favicon.jpg');

$clinicBrandName = trim((string) ($CLINIC['clinic_name'] ?? ''));
if ($clinicBrandName === '') {
    $clinicBrandName = '(Business Name) Dental Clinic';
}
$metaDescription = $clinicBrandName . ' — Professional dentistry services including general, cosmetic, orthodontics, and pediatric care.';
$metaOgDescription = $clinicBrandName . ' — Professional dentistry services for your healthy smile.';
$metaKeywords = 'dental clinic, dentistry, dental care, dental services, ' . $clinicBrandName;

$ffHeadlineArr = [$tfHeadline, 'ui-sans-serif', 'system-ui', 'sans-serif'];
$ffBodyArr = [$tfBody, 'ui-sans-serif', 'system-ui', 'sans-serif'];
$ffDispArr = [$tfDisplay, 'ui-sans-serif', 'system-ui', 'sans-serif'];
$ffEdArr = [$tfEditorial, 'Georgia', 'serif'];
$jsonFfFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS;
$ffHeadlineJs = json_encode($ffHeadlineArr, $jsonFfFlags);
$ffBodyJs = json_encode($ffBodyArr, $jsonFfFlags);
$ffDispJs = json_encode($ffDispArr, $jsonFfFlags);
$ffEdJs = json_encode($ffEdArr, $jsonFfFlags);
?>
<!DOCTYPE html>
<html class="light clinic-patient-theme overflow-x-clip" lang="en" style="font-size: <?php echo $basePx; ?>px;">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="<?php echo htmlspecialchars($favMime, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($favHref, ENT_QUOTES, 'UTF-8'); ?>"/>
    <link rel="shortcut icon" type="<?php echo htmlspecialchars($favMime, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($favHref, ENT_QUOTES, 'UTF-8'); ?>"/>
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($favHref, ENT_QUOTES, 'UTF-8'); ?>"/>
    
    <!-- Meta Tags for SEO and Social Sharing -->
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords, ENT_QUOTES, 'UTF-8'); ?>"/>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website"/>
    <meta property="og:url" content="<?php echo BASE_URL; ?>"/>
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SITE_NAME; ?>"/>
    <meta property="og:description" content="<?php echo htmlspecialchars($metaOgDescription, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="og:image" content="<?php echo BASE_URL; ?>favicon.jpg"/>
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image"/>
    <meta property="twitter:url" content="<?php echo BASE_URL; ?>"/>
    <meta property="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SITE_NAME; ?>"/>
    <meta property="twitter:description" content="<?php echo htmlspecialchars($metaOgDescription, ENT_QUOTES, 'UTF-8'); ?>"/>
    <meta property="twitter:image" content="<?php echo BASE_URL; ?>favicon.jpg"/>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <?php if ($gfHref !== ''): ?>
    <link href="<?php echo htmlspecialchars($gfHref, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet"/>
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "<?php echo $cPrimary; ?>",
                        "primary-dark": "<?php echo $cPrimaryDark; ?>",
                        "primary-light": "<?php echo $cPrimaryLight; ?>",
                        "accent": "#F0F9FF",
                        "background-light": "#ffffff",
                        "background-dark": "#0F172A",
                        "surface-light": "#F8FAFC",
                    },
                    fontFamily: {
                        "display": <?php echo $ffDispJs; ?>,
                        "body": <?php echo $ffBodyJs; ?>,
                        "headline": <?php echo $ffHeadlineJs; ?>,
                        "editorial": <?php echo $ffEdJs; ?>,
                        "serif": <?php echo $ffEdJs; ?>
                    },
                    borderRadius: {
                        "DEFAULT": "<?php echo htmlspecialchars($brDefault, ENT_QUOTES, 'UTF-8'); ?>",
                        "lg": "<?php echo htmlspecialchars($brLg, ENT_QUOTES, 'UTF-8'); ?>",
                        "xl": "<?php echo htmlspecialchars($brXl, ENT_QUOTES, 'UTF-8'); ?>",
                        "2xl": "<?php echo htmlspecialchars($br2xl, ENT_QUOTES, 'UTF-8'); ?>",
                        "3xl": "<?php echo htmlspecialchars($br3xl, ENT_QUOTES, 'UTF-8'); ?>",
                        "full": "9999px"
                    },
                    boxShadow: {
                        "soft": "0 20px 40px -15px rgba(43, 140, 238, 0.08)",
                        "card": "0 0 0 1px rgba(0,0,0,0.03), 0 4px 12px rgba(0,0,0,0.03)",
                        "card-hover": "0 0 0 1px <?php echo $cPrimary; ?>33, 0 12px 32px -8px <?php echo $cPrimary; ?>26",
                    }
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .mesh-gradient {
            background-color: #ffffff;
            background-image:
                radial-gradient(at 100% 0%, color-mix(in srgb, <?php echo $cPrimary; ?> 12%, transparent) 0px, transparent 50%),
                radial-gradient(at 0% 100%, color-mix(in srgb, <?php echo $cPrimary; ?> 8%, transparent) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.35);
        }
        .editorial-word {
            text-shadow: 0 0 12px color-mix(in srgb, <?php echo $cPrimary; ?> 25%, transparent);
            letter-spacing: -0.02em;
        }
        .reveal {
            opacity: 0;
            transform: translateY(30px) scale(0.985);
            filter: blur(10px);
            transition:
                opacity 820ms cubic-bezier(0.22, 1, 0.36, 1),
                transform 820ms cubic-bezier(0.22, 1, 0.36, 1),
                filter 820ms cubic-bezier(0.22, 1, 0.36, 1);
            will-change: opacity, transform, filter;
        }
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            filter: blur(0);
        }
        @keyframes popIn {
            0% { transform: translateY(10px) scale(0.985); opacity: 0; }
            60% { transform: translateY(-2px) scale(1.01); opacity: 1; }
            100% { transform: translateY(0) scale(1); opacity: 1; }
        }
        .pop-up {
            animation: popIn 620ms cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .text-balance {
            text-wrap: balance;
        }
        .bg-grid-slate {
            background-image: radial-gradient(rgba(148, 163, 184, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .clinic-patient-theme.font-body,
        .clinic-patient-theme .font-body {
            font-family: <?php echo htmlspecialchars($tfBody, ENT_QUOTES, 'UTF-8'); ?>, ui-sans-serif, system-ui, sans-serif;
        }
        .clinic-patient-theme .font-headline {
            font-family: <?php echo htmlspecialchars($tfHeadline, ENT_QUOTES, 'UTF-8'); ?>, ui-sans-serif, system-ui, sans-serif;
            font-weight: <?php echo $headingWt; ?>;
        }
        .clinic-patient-theme .font-display {
            font-family: <?php echo htmlspecialchars($tfDisplay, ENT_QUOTES, 'UTF-8'); ?>, ui-sans-serif, system-ui, sans-serif;
            font-weight: <?php echo $headingWt; ?>;
        }
        .clinic-patient-theme .font-editorial {
            font-family: <?php echo htmlspecialchars($tfEditorial, ENT_QUOTES, 'UTF-8'); ?>, Georgia, serif;
        }
        html.clinic-patient-theme body {
            line-height: <?php echo htmlspecialchars((string) $lineHeight, ENT_QUOTES, 'UTF-8'); ?>;
        }
        @media (prefers-reduced-motion: reduce) {
            .reveal {
                opacity: 1;
                transform: none;
                filter: none;
                transition: none;
            }
            .pop-up {
                animation: none;
            }
        }
    </style>
</head>
<body class="font-body bg-white dark:bg-background-dark text-slate-900 dark:text-slate-100 overflow-x-clip antialiased selection:bg-primary/20">

