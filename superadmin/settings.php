<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/superadmin_settings_lib.php';

$saveMessage = '';
$saveError = '';

function superadmin_parse_price_number($raw): ?float
{
    if (is_numeric($raw)) {
        $n = (float) $raw;
        return $n >= 0 ? $n : null;
    }
    if (!is_string($raw)) {
        return null;
    }
    $cleaned = preg_replace('/[^0-9.\-]/', '', $raw);
    if (!is_string($cleaned) || $cleaned === '' || !is_numeric($cleaned)) {
        return null;
    }
    $n = (float) $cleaned;
    return $n >= 0 ? $n : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_superadmin_settings'])) {
    $settingsSection = isset($_POST['settings_section']) ? (string) $_POST['settings_section'] : '';
    $currentSettings = superadmin_get_settings($pdo);
    $data = [
        'system_name' => (string) ($currentSettings['system_name'] ?? 'MyDental'),
        'brand_tagline' => (string) ($currentSettings['brand_tagline'] ?? 'MANAGEMENT CONSOLE'),
        'brand_logo_path' => (string) ($currentSettings['brand_logo_path'] ?? 'MyDental Logo.svg'),
        'provider_plans' => isset($currentSettings['provider_plans']) && is_array($currentSettings['provider_plans'])
            ? $currentSettings['provider_plans']
            : superadmin_default_provider_plans(),
    ];

    if ($settingsSection === 'plans') {
        $providerPlans = [];
        $planKeys = ['monthly', 'yearly'];
        foreach ($planKeys as $planKey) {
            $featuresText = isset($_POST['provider_plan_' . $planKey . '_features'])
                ? (string) $_POST['provider_plan_' . $planKey . '_features']
                : '';
            $providerPlans[$planKey] = [
                'name' => isset($_POST['provider_plan_' . $planKey . '_name']) ? (string) $_POST['provider_plan_' . $planKey . '_name'] : '',
                'price' => isset($_POST['provider_plan_' . $planKey . '_price']) ? (string) $_POST['provider_plan_' . $planKey . '_price'] : '',
                'description' => isset($_POST['provider_plan_' . $planKey . '_description']) ? (string) $_POST['provider_plan_' . $planKey . '_description'] : '',
                'cta' => isset($_POST['provider_plan_' . $planKey . '_cta']) ? (string) $_POST['provider_plan_' . $planKey . '_cta'] : '',
                'features' => preg_split('/\r\n|\r|\n/', $featuresText) ?: [],
            ];
        }

        $monthlyAmount = superadmin_parse_price_number($providerPlans['monthly']['price'] ?? null);
        $baseAnnual = $monthlyAmount !== null ? ($monthlyAmount * 12) : null;
        if ($baseAnnual !== null && $baseAnnual > 0) {
            $yearlyEditSource = isset($_POST['yearly_pricing_edit_source'])
                ? (string) $_POST['yearly_pricing_edit_source']
                : 'discount_percent';
            $postedDiscountPercent = superadmin_parse_price_number($_POST['yearly_discount_percent'] ?? null);
            $postedDiscountedPrice = superadmin_parse_price_number($_POST['yearly_discounted_price'] ?? null);
            $existingYearlyAmount = superadmin_parse_price_number($providerPlans['yearly']['price'] ?? null);

            $effectiveDiscountPercent = 0.0;
            $effectiveDiscountedPrice = $baseAnnual;

            if ($yearlyEditSource === 'discount_price' && $postedDiscountedPrice !== null) {
                $effectiveDiscountedPrice = max(0.0, min($baseAnnual, $postedDiscountedPrice));
                $effectiveDiscountPercent = (($baseAnnual - $effectiveDiscountedPrice) / $baseAnnual) * 100;
            } else {
                if ($postedDiscountPercent === null && $existingYearlyAmount !== null) {
                    $postedDiscountPercent = (($baseAnnual - $existingYearlyAmount) / $baseAnnual) * 100;
                }
                $effectiveDiscountPercent = max(0.0, min(100.0, (float) ($postedDiscountPercent ?? 0.0)));
                $effectiveDiscountedPrice = $baseAnnual * (1 - ($effectiveDiscountPercent / 100));
            }

            $providerPlans['yearly']['price'] = '₱' . number_format((float) round($effectiveDiscountedPrice, 0), 0);
        }

        $data['provider_plans'] = $providerPlans;
    } else {
        $data['system_name'] = isset($_POST['system_name']) ? (string) $_POST['system_name'] : $data['system_name'];
        $data['brand_tagline'] = isset($_POST['brand_tagline']) ? (string) $_POST['brand_tagline'] : $data['brand_tagline'];
        $data['brand_logo_path'] = isset($_POST['brand_logo_path']) ? (string) $_POST['brand_logo_path'] : $data['brand_logo_path'];
    }

    if (!empty($_FILES['brand_logo_file']['name']) && isset($_FILES['brand_logo_file']['tmp_name'])
        && is_uploaded_file($_FILES['brand_logo_file']['tmp_name'])) {
        $f = $_FILES['brand_logo_file'];
        if ((int) $f['error'] !== UPLOAD_ERR_OK) {
            $saveError = 'Logo upload failed. Please try again.';
        } else {
            $maxBytes = 2 * 1024 * 1024;
            if ((int) $f['size'] > $maxBytes) {
                $saveError = 'Logo file must be 2 MB or smaller.';
            } else {
                $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                if (!in_array($ext, $allowedExt, true)) {
                    $saveError = 'Logo must be JPG, PNG, GIF, WebP, or SVG.';
                } else {
                    $mime = null;
                    if (function_exists('finfo_open')) {
                        $fi = finfo_open(FILEINFO_MIME_TYPE);
                        if ($fi) {
                            $mime = finfo_file($fi, $f['tmp_name']);
                            finfo_close($fi);
                        }
                    }
                    $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
                    $svgMimeOk = ['image/svg+xml', 'text/plain', 'application/xml', 'text/xml'];
                    $mimeOk = true;
                    if ($mime !== null) {
                        $mimeOk = $ext === 'svg'
                            ? in_array($mime, $svgMimeOk, true)
                            : in_array($mime, $allowedMime, true);
                    }
                    if (!$mimeOk) {
                        $saveError = 'Invalid image file type.';
                    } else {
                        $dir = __DIR__ . '/uploads/branding';
                        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                            $saveError = 'Could not create upload directory on the server.';
                        } else {
                            $base = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
                            $dest = $dir . '/' . $base;
                            if (!@move_uploaded_file($f['tmp_name'], $dest)) {
                                $saveError = 'Could not save uploaded logo.';
                            } else {
                                $data['brand_logo_path'] = 'uploads/branding/' . $base;
                            }
                        }
                    }
                }
            }
        }
    }

    if ($saveError === '') {
        try {
            superadmin_save_settings($pdo, $data);
            $saveMessage = 'Settings saved.';
        } catch (Throwable $e) {
            $saveError = 'Could not save settings.';
            error_log('superadmin settings save: ' . $e->getMessage());
        }
    }
}

$settings = superadmin_get_settings($pdo);
$providerPlans = isset($settings['provider_plans']) && is_array($settings['provider_plans'])
    ? $settings['provider_plans']
    : superadmin_default_provider_plans();
$monthlyPriceNumeric = superadmin_parse_price_number($providerPlans['monthly']['price'] ?? null);
$yearlyPriceNumeric = superadmin_parse_price_number($providerPlans['yearly']['price'] ?? null);
$baseAnnualPrice = ($monthlyPriceNumeric !== null && $monthlyPriceNumeric > 0) ? ($monthlyPriceNumeric * 12) : null;
$computedDiscountPercent = 0.0;
if ($baseAnnualPrice !== null && $yearlyPriceNumeric !== null && $baseAnnualPrice > 0) {
    $computedDiscountPercent = (($baseAnnualPrice - $yearlyPriceNumeric) / $baseAnnualPrice) * 100;
}
$computedDiscountPercent = max(0.0, min(100.0, $computedDiscountPercent));
$computedSavingsAmount = ($baseAnnualPrice !== null && $yearlyPriceNumeric !== null)
    ? max(0.0, $baseAnnualPrice - $yearlyPriceNumeric)
    : 0.0;
$pageTitle = htmlspecialchars($settings['system_name'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Settings | <?php echo $pageTitle; ?></title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0066ff",
                        "on-surface": "#131c25",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "error": "#ba1a1a",
                        "surface-container-low": "#edf4ff",
                        "surface-container-high": "#e0e9f6",
                        "surface-container-highest": "#dae3f0",
                        "background": "#f7f9ff",
                        "tertiary": "#8e4a00",
                        "error-container": "#ffdad6",
                    },
                    fontFamily: {
                        "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "3xl": "1.5rem", "full": "9999px" },
                },
            },
        }
</script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .editorial-shadow {
            box-shadow: 0 12px 40px -10px rgba(19, 28, 37, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(0, 102, 255, 0.3);
        }
        .primary-glow {
            box-shadow: 0 8px 25px -5px rgba(0, 102, 255, 0.4);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210,100%,98%,1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217,100%,94%,1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210,100%,98%,1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        @media (max-width: 1023px) {
            #superadmin-sidebar {
                transform: translateX(-100%);
                transition: transform 220ms ease;
                z-index: 60;
            }
            body.sa-mobile-sidebar-open #superadmin-sidebar {
                transform: translateX(0);
            }
            .sa-top-header {
                left: 0;
                width: 100% !important;
                padding-left: 5.5rem;
                padding-right: 1rem;
            }
            #sa-mobile-sidebar-toggle {
                top: 1rem;
                left: 0.75rem;
                width: 2.75rem;
                height: 2.75rem;
                transition: left 220ms ease, background-color 220ms ease, color 220ms ease;
            }
            body.sa-mobile-sidebar-open #sa-mobile-sidebar-toggle {
                left: calc(16rem - 3.25rem);
                background: rgba(255, 255, 255, 0.98);
                color: #0066ff;
            }
            #sa-mobile-sidebar-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(19, 28, 37, 0.45);
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
                z-index: 55;
                opacity: 0;
                pointer-events: none;
                transition: opacity 220ms ease;
            }
            body.sa-mobile-sidebar-open #sa-mobile-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
        }
</style>
</head>
<body class="mesh-bg font-body text-on-surface antialiased min-h-screen">
<?php
$superadmin_nav = 'settings';
$superadmin_header_center = '';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';
?>
<button id="sa-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="superadmin-sidebar" aria-expanded="false" aria-label="Open navigation menu">
<span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="sa-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<main class="ml-0 lg:ml-64 pt-20 min-h-screen">
<div class="pt-6 sm:pt-8 px-4 sm:px-6 lg:px-10 pb-12 sm:pb-16 space-y-8 sm:space-y-10 relative">
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>

<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-3xl sm:text-4xl font-extrabold font-headline tracking-tight text-on-surface">Settings</h2>
<p class="text-on-surface-variant mt-2 font-medium">General platform branding and database backup.</p>
</div>
</section>

<?php if ($saveMessage !== ''): ?>
<div class="rounded-2xl border border-emerald-200 bg-emerald-50/90 text-emerald-900 px-5 py-3 text-sm font-medium editorial-shadow">
<?php echo htmlspecialchars($saveMessage, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
<?php if ($saveError !== ''): ?>
<div class="rounded-2xl border border-error/30 bg-error-container/80 text-error px-5 py-3 text-sm font-medium editorial-shadow">
<?php echo htmlspecialchars($saveError, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<section class="bg-white/60 backdrop-blur-md rounded-[2rem] editorial-shadow p-5 sm:p-8 md:p-10 space-y-8">
<div class="flex items-start gap-4">
<div class="p-3 bg-blue-50 text-primary rounded-xl shadow-sm shrink-0">
<span class="material-symbols-outlined text-2xl">tune</span>
</div>
<div class="min-w-0 flex-1">
<h3 class="text-xl font-bold font-headline text-on-surface">General settings</h3>
<p class="text-on-surface-variant text-sm mt-1">System name and branding shown across Super Admin pages (sidebar logo and labels).</p>
</div>
</div>

<form method="post" action="" enctype="multipart/form-data" class="space-y-6 max-w-2xl">
<input type="hidden" name="save_superadmin_settings" value="1"/>
<input type="hidden" name="settings_section" value="general"/>

<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="system_name">System name</label>
<input class="w-full bg-surface-container-low/50 border border-white/80 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20" id="system_name" name="system_name" type="text" maxlength="255" value="<?php echo htmlspecialchars($settings['system_name'], ENT_QUOTES, 'UTF-8'); ?>" required/>
<p class="text-on-surface-variant/80 text-xs mt-1.5">Used for document titles and accessibility labels in the management console.</p>
</div>

<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="brand_tagline">Branding tagline</label>
<input class="w-full bg-surface-container-low/50 border border-white/80 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20" id="brand_tagline" name="brand_tagline" type="text" maxlength="255" value="<?php echo htmlspecialchars($settings['brand_tagline'], ENT_QUOTES, 'UTF-8'); ?>"/>
<p class="text-on-surface-variant/80 text-xs mt-1.5">Short line under the logo in the sidebar (e.g. “MANAGEMENT CONSOLE”).</p>
</div>

<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="brand_logo_path">Logo image</label>
<div class="flex flex-col sm:flex-row gap-4 items-start">
<?php $logoSrc = htmlspecialchars($settings['brand_logo_path'], ENT_QUOTES, 'UTF-8'); ?>
<div class="shrink-0 rounded-xl border border-white/80 bg-white/50 p-3">
<img src="<?php echo $logoSrc; ?>" alt="" class="h-12 w-auto max-w-[200px] object-contain object-left"/>
</div>
<div class="flex-1 min-w-0 space-y-3 w-full">
<input class="w-full bg-surface-container-low/50 border border-white/80 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20" id="brand_logo_path" name="brand_logo_path" type="text" maxlength="512" placeholder="MyDental Logo.svg or https://..." value="<?php echo htmlspecialchars($settings['brand_logo_path'], ENT_QUOTES, 'UTF-8'); ?>"/>
<p class="text-on-surface-variant/80 text-xs">Path relative to the Super Admin folder (e.g. <code class="text-on-surface/90">MyDental Logo.svg</code>) or a full image URL.</p>
<div>
<label class="block text-xs font-semibold text-on-surface-variant mb-1.5" for="brand_logo_file">Upload new logo</label>
<input class="block w-full text-sm text-on-surface-variant file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/15" id="brand_logo_file" name="brand_logo_file" type="file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,.svg"/>
<p class="text-on-surface-variant/80 text-xs mt-1">Optional. JPG, PNG, GIF, WebP, or SVG · max 2 MB. Uploading replaces the stored path.</p>
</div>
</div>
</div>
</div>

<div class="pt-2">
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow inline-flex w-full sm:w-auto justify-center items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all" type="submit">
<span class="material-symbols-outlined text-lg">save</span>
Save general settings
</button>
</div>
</form>
</section>

<section class="bg-white/60 backdrop-blur-md rounded-[2rem] editorial-shadow p-5 sm:p-8 md:p-10 space-y-8">
<div class="flex items-start gap-4">
<div class="p-3 bg-blue-50 text-primary rounded-xl shadow-sm shrink-0">
<span class="material-symbols-outlined text-2xl">sell</span>
</div>
<div class="min-w-0 flex-1">
<h3 class="text-xl font-bold font-headline text-on-surface">Provider plans</h3>
<p class="text-on-surface-variant text-sm mt-1">Update plan details shown in the public Provider Plans page.</p>
</div>
</div>

<form method="post" action="" class="space-y-6">
<input type="hidden" name="save_superadmin_settings" value="1"/>
<input type="hidden" name="settings_section" value="plans"/>
<input type="hidden" name="yearly_pricing_edit_source" id="yearly_pricing_edit_source" value="discount_percent"/>
<?php
$planLabels = ['monthly' => 'Monthly', 'yearly' => 'Yearly'];
foreach ($planLabels as $planKey => $planLabel):
    $plan = isset($providerPlans[$planKey]) && is_array($providerPlans[$planKey]) ? $providerPlans[$planKey] : [];
    $planName = isset($plan['name']) ? (string) $plan['name'] : $planLabel;
    $planPrice = isset($plan['price']) ? (string) $plan['price'] : '';
    $planDesc = isset($plan['description']) ? (string) $plan['description'] : '';
    $planCta = isset($plan['cta']) ? (string) $plan['cta'] : 'Choose ' . $planLabel;
    $planFeatures = isset($plan['features']) && is_array($plan['features']) ? implode("\n", $plan['features']) : '';
?>
<div class="rounded-2xl border border-white/80 bg-surface-container-low/30 p-5 space-y-4">
<h4 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant"><?php echo htmlspecialchars($planLabel, ENT_QUOTES, 'UTF-8'); ?> plan</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="provider_plan_<?php echo $planKey; ?>_name">Plan name</label>
<input class="w-full bg-white/80 border border-white rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20" id="provider_plan_<?php echo $planKey; ?>_name" name="provider_plan_<?php echo $planKey; ?>_name" type="text" maxlength="80" value="<?php echo htmlspecialchars($planName, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="provider_plan_<?php echo $planKey; ?>_price">Price label</label>
<input class="w-full bg-white/80 border border-white rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20" id="provider_plan_<?php echo $planKey; ?>_price" name="provider_plan_<?php echo $planKey; ?>_price" type="text" maxlength="40" value="<?php echo htmlspecialchars($planPrice, ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g. ₱999" <?php echo $planKey === 'yearly' ? 'readonly' : ''; ?>/>
</div>
</div>
<?php if ($planKey === 'yearly'): ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="yearly_discount_percent">Discount percentage</label>
<div class="relative">
<input class="w-full bg-white/80 border border-white rounded-2xl px-4 py-3 pr-10 text-sm focus:ring-2 focus:ring-primary/20" id="yearly_discount_percent" name="yearly_discount_percent" type="number" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars(number_format($computedDiscountPercent, 2, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"/>
<span class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-on-surface-variant">%</span>
</div>
</div>
<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="yearly_discounted_price">Discounted yearly price</label>
<input class="w-full bg-white/80 border border-white rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20" id="yearly_discounted_price" name="yearly_discounted_price" type="number" min="0" step="1" value="<?php echo htmlspecialchars((string) ((int) round((float) ($yearlyPriceNumeric ?? 0))), ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
</div>
<p class="text-on-surface-variant/80 text-xs">Auto-compute works both ways based on monthly price: update either discount percentage or discounted yearly price.</p>
<p class="text-on-surface-variant/80 text-xs">Base yearly amount: <span id="yearly_base_display">₱<?php echo number_format((float) ($baseAnnualPrice ?? 0), 0); ?></span> (monthly x 12)</p>
<p class="text-emerald-700 text-xs font-semibold">Savings preview: <span id="yearly_savings_display">Save ₱<?php echo number_format((float) round($computedSavingsAmount), 0); ?>!</span></p>
<?php endif; ?>
<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="provider_plan_<?php echo $planKey; ?>_description">Description</label>
<input class="w-full bg-white/80 border border-white rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20" id="provider_plan_<?php echo $planKey; ?>_description" name="provider_plan_<?php echo $planKey; ?>_description" type="text" maxlength="255" value="<?php echo htmlspecialchars($planDesc, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="provider_plan_<?php echo $planKey; ?>_cta">Button text</label>
<input class="w-full bg-white/80 border border-white rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20" id="provider_plan_<?php echo $planKey; ?>_cta" name="provider_plan_<?php echo $planKey; ?>_cta" type="text" maxlength="60" value="<?php echo htmlspecialchars($planCta, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
<div>
<label class="block text-xs font-bold uppercase tracking-widest text-on-surface-variant mb-2" for="provider_plan_<?php echo $planKey; ?>_features">Features (one per line)</label>
<textarea class="w-full bg-white/80 border border-white rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-primary/20 min-h-[140px]" id="provider_plan_<?php echo $planKey; ?>_features" name="provider_plan_<?php echo $planKey; ?>_features"><?php echo htmlspecialchars($planFeatures, ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>
</div>
</div>
<?php endforeach; ?>

<div class="pt-2">
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow inline-flex w-full sm:w-auto justify-center items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all" type="submit">
<span class="material-symbols-outlined text-lg">save</span>
Save provider plan details
</button>
</div>
</form>
</section>

<section id="database-backup" class="bg-white/60 backdrop-blur-md rounded-[2rem] editorial-shadow p-5 sm:p-8 md:p-10 space-y-6">
<div class="flex items-start gap-4">
<div class="p-3 bg-blue-50 text-primary rounded-xl shadow-sm shrink-0">
<span class="material-symbols-outlined text-2xl">database</span>
</div>
<div class="min-w-0 flex-1">
<h3 class="text-xl font-bold font-headline text-on-surface">Database backup</h3>
<p class="text-on-surface-variant text-sm mt-1">Download a complete SQL dump of the platform database (schema and data). Use for disaster recovery or offline copies.</p>
</div>
</div>
<div class="flex flex-col sm:flex-row sm:items-center gap-4 pt-2">
<a class="inline-flex items-center justify-center gap-2 bg-primary text-white px-7 py-3 rounded-2xl text-sm font-bold primary-glow hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all" href="backup_download.php">
<span class="material-symbols-outlined text-xl">download</span>
Download full system backup (.sql)
</a>
<p class="text-on-surface-variant text-xs max-w-md">Large databases may take a minute to generate. Keep this file secure; it contains all tenant and user data.</p>
</div>
</section>

</div>
</main>
<script>
    (function () {
        var toggleBtn = document.getElementById('sa-mobile-sidebar-toggle');
        var backdrop = document.getElementById('sa-mobile-sidebar-backdrop');
        var mqDesktop = window.matchMedia('(min-width: 1024px)');
        if (!toggleBtn || !backdrop) return;

        function setOpen(isOpen) {
            document.body.classList.toggle('sa-mobile-sidebar-open', isOpen);
            toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggleBtn.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
            var icon = toggleBtn.querySelector('.material-symbols-outlined');
            if (icon) icon.textContent = isOpen ? 'close' : 'menu';
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        toggleBtn.addEventListener('click', function () {
            setOpen(!document.body.classList.contains('sa-mobile-sidebar-open'));
        });
        backdrop.addEventListener('click', function () {
            setOpen(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.body.classList.contains('sa-mobile-sidebar-open')) {
                setOpen(false);
            }
        });

        function closeOnDesktop() {
            if (mqDesktop.matches) setOpen(false);
        }
        if (typeof mqDesktop.addEventListener === 'function') {
            mqDesktop.addEventListener('change', closeOnDesktop);
        } else if (typeof mqDesktop.addListener === 'function') {
            mqDesktop.addListener(closeOnDesktop);
        }
    })();
</script>
<script>
    (function () {
        var monthlyPriceInput = document.getElementById('provider_plan_monthly_price');
        var yearlyPriceLabelInput = document.getElementById('provider_plan_yearly_price');
        var discountPercentInput = document.getElementById('yearly_discount_percent');
        var discountedPriceInput = document.getElementById('yearly_discounted_price');
        var editSourceInput = document.getElementById('yearly_pricing_edit_source');
        var baseDisplay = document.getElementById('yearly_base_display');
        var savingsDisplay = document.getElementById('yearly_savings_display');

        if (!monthlyPriceInput || !yearlyPriceLabelInput || !discountPercentInput || !discountedPriceInput || !editSourceInput) {
            return;
        }

        function parseNumeric(value) {
            var cleaned = String(value || '').replace(/[^0-9.\-]/g, '');
            if (cleaned === '' || isNaN(Number(cleaned))) return null;
            return Number(cleaned);
        }

        function formatPesoWhole(value) {
            var n = Number(value || 0);
            return '₱' + n.toLocaleString('en-PH', { maximumFractionDigits: 0, minimumFractionDigits: 0 });
        }

        function getBaseAnnual() {
            var monthly = parseNumeric(monthlyPriceInput.value);
            if (monthly === null || monthly <= 0) return null;
            return monthly * 12;
        }

        function syncFromDiscountPercent() {
            var base = getBaseAnnual();
            if (base === null) return;
            var percent = parseNumeric(discountPercentInput.value);
            if (percent === null) percent = 0;
            percent = Math.max(0, Math.min(100, percent));
            var discounted = Math.round(base * (1 - (percent / 100)));
            discountPercentInput.value = percent.toFixed(2);
            discountedPriceInput.value = String(discounted);
            yearlyPriceLabelInput.value = formatPesoWhole(discounted);
            if (baseDisplay) baseDisplay.textContent = formatPesoWhole(Math.round(base));
            if (savingsDisplay) savingsDisplay.textContent = 'Save ' + formatPesoWhole(Math.round(base - discounted)) + '!';
        }

        function syncFromDiscountedPrice() {
            var base = getBaseAnnual();
            if (base === null || base <= 0) return;
            var discounted = parseNumeric(discountedPriceInput.value);
            if (discounted === null) discounted = Math.round(base);
            discounted = Math.max(0, Math.min(base, discounted));
            var percent = ((base - discounted) / base) * 100;
            discountPercentInput.value = percent.toFixed(2);
            discountedPriceInput.value = String(Math.round(discounted));
            yearlyPriceLabelInput.value = formatPesoWhole(Math.round(discounted));
            if (baseDisplay) baseDisplay.textContent = formatPesoWhole(Math.round(base));
            if (savingsDisplay) savingsDisplay.textContent = 'Save ' + formatPesoWhole(Math.round(base - discounted)) + '!';
        }

        discountPercentInput.addEventListener('input', function () {
            editSourceInput.value = 'discount_percent';
            syncFromDiscountPercent();
        });
        discountedPriceInput.addEventListener('input', function () {
            editSourceInput.value = 'discount_price';
            syncFromDiscountedPrice();
        });
        monthlyPriceInput.addEventListener('input', function () {
            if (editSourceInput.value === 'discount_price') {
                syncFromDiscountedPrice();
            } else {
                syncFromDiscountPercent();
            }
        });

        if (editSourceInput.value === 'discount_price') {
            syncFromDiscountedPrice();
        } else {
            syncFromDiscountPercent();
        }
    })();
</script>
</body>
</html>
