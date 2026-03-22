<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/superadmin_settings_lib.php';

$saveMessage = '';
$saveError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_superadmin_settings'])) {
    $data = [
        'system_name' => isset($_POST['system_name']) ? (string) $_POST['system_name'] : '',
        'brand_tagline' => isset($_POST['brand_tagline']) ? (string) $_POST['brand_tagline'] : '',
        'brand_logo_path' => isset($_POST['brand_logo_path']) ? (string) $_POST['brand_logo_path'] : '',
    ];

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
</style>
</head>
<body class="mesh-bg font-body text-on-surface antialiased min-h-screen">
<?php
$superadmin_nav = 'settings';
$superadmin_header_center = '<div class="text-sm font-semibold text-on-surface-variant">Super Admin · Settings</div>';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';
?>
<main class="ml-64 pt-20 min-h-screen">
<div class="pt-8 px-10 pb-16 space-y-10 relative">
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>

<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Settings</h2>
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

<section class="bg-white/60 backdrop-blur-md rounded-[2rem] editorial-shadow p-8 md:p-10 space-y-8">
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
<button class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow inline-flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all" type="submit">
<span class="material-symbols-outlined text-lg">save</span>
Save general settings
</button>
</div>
</form>
</section>

<section id="database-backup" class="bg-white/60 backdrop-blur-md rounded-[2rem] editorial-shadow p-8 md:p-10 space-y-6">
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
</body>
</html>
