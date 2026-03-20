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
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpeg" href="<?php echo BASE_URL; ?>favicon.jpg"/>
    <link rel="shortcut icon" type="image/jpeg" href="<?php echo BASE_URL; ?>favicon.jpg"/>
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>favicon.jpg"/>
    
    <!-- Meta Tags for SEO and Social Sharing -->
    <meta name="description" content="Dr. Romarico C. Gonzales Dental Clinic - Advanced dental care in Baliwag. Professional dentistry services including general, cosmetic, orthodontics, and pediatric care."/>
    <meta name="keywords" content="dental clinic, dentistry, Baliwag, Dr. Romarico Gonzales, dental care, dental services"/>
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website"/>
    <meta property="og:url" content="<?php echo BASE_URL; ?>"/>
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SITE_NAME; ?>"/>
    <meta property="og:description" content="Dr. Romarico C. Gonzales Dental Clinic - Advanced dental care in Baliwag. Professional dentistry services for your healthy smile."/>
    <meta property="og:image" content="<?php echo BASE_URL; ?>favicon.jpg"/>
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image"/>
    <meta property="twitter:url" content="<?php echo BASE_URL; ?>"/>
    <meta property="twitter:title" content="<?php echo htmlspecialchars($pageTitle); ?> - <?php echo SITE_NAME; ?>"/>
    <meta property="twitter:description" content="Dr. Romarico C. Gonzales Dental Clinic - Advanced dental care in Baliwag. Professional dentistry services for your healthy smile."/>
    <meta property="twitter:image" content="<?php echo BASE_URL; ?>favicon.jpg"/>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet"/>
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
                        "display": ["Plus Jakarta Sans", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "sans-serif"],
                        "serif": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.375rem", 
                        "lg": "0.5rem", 
                        "xl": "1rem", 
                        "2xl": "1.5rem", 
                        "3xl": "2rem",
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
        .text-balance {
            text-wrap: balance;
        }
        .bg-grid-slate {
            background-image: radial-gradient(rgba(148, 163, 184, 0.1) 1px, transparent 1px);
            background-size: 40px 40px;
        }
    </style>
</head>
<body class="font-body bg-white dark:bg-background-dark text-slate-900 dark:text-slate-100 overflow-x-hidden antialiased selection:bg-primary/20">

