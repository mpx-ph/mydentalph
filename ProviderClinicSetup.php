<?php
session_start();
require_once 'db.php';

$error = '';

if (empty($_SESSION['onboarding_user_id']) || empty($_SESSION['onboarding_tenant_id'])) {
    header('Location: ProviderOTP.php');
    exit;
}

$tenant_id = $_SESSION['onboarding_tenant_id'];

// Pre-fill clinic name from tenant
$stmt = $pdo->prepare("SELECT clinic_name, clinic_slug FROM tbl_tenants WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);
$current_clinic_name = $tenant['clinic_name'] ?? '';
$current_slug = $tenant['clinic_slug'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clinic_name = trim($_POST['clinic_name'] ?? '');
    $clinic_slug = trim($_POST['clinic_slug'] ?? '');
    $clinic_slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($clinic_slug));
    if (empty($clinic_name)) {
        $error = "Clinic name is required.";
    } elseif (empty($clinic_slug)) {
        $error = "Clinic URL slug is required.";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tbl_tenants WHERE clinic_slug = ? AND tenant_id != ?");
        $stmt->execute([$clinic_slug, $tenant_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "This clinic URL is already taken. Please choose another.";
        } else {
            $stmt = $pdo->prepare("UPDATE tbl_tenants SET clinic_name = ?, clinic_slug = ? WHERE tenant_id = ?");
            $stmt->execute([$clinic_name, $clinic_slug, $tenant_id]);
            header('Location: ProviderPurchase.php');
            exit;
        }
    }
} else {
    if ($current_slug === '' && $current_clinic_name !== '') {
        $current_slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '', $current_clinic_name)));
    }
}
?>
<!DOCTYPE html>

<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Set Up Your Clinic Workspace - MyDental</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "dark-blue": "#101922",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101022",
                    },
                    fontFamily: {
                        "display": ["Manrope", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
        body {
            font-family: 'Manrope', sans-serif;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark font-display min-h-screen flex flex-col">
<header class="w-full px-6 py-4 flex items-center justify-between bg-white border-b border-slate-200">
<div class="flex items-center gap-2">
<div class="bg-primary p-1.5 rounded-lg">
<span class="material-symbols-outlined text-white text-2xl">dentistry</span>
</div>
<span class="text-dark-blue font-800 text-xl tracking-tight">MyDental</span>
</div>
<div class="flex items-center gap-4">
<div class="text-right hidden sm:block">
<p class="text-sm font-bold text-dark-blue"><?php echo htmlspecialchars($_SESSION['onboarding_full_name'] ?? 'Account Owner'); ?></p>
<p class="text-xs text-slate-500">Account Owner</p>
</div>
<div class="h-10 w-10 rounded-full bg-slate-200 overflow-hidden border border-slate-200">
<img class="w-full h-full object-cover" data-alt="Professional portrait of a male dentist" src="https://lh3.googleusercontent.com/aida-public/AB6AXuB3h3yYa1XR0Zi50SZRq69QGo4-HK8E1tlmTStU0u6iG96JTa21-PiT3VgDWZMB8Z0UBgbOpCOQjUtKjBMGicxQFpCk6borrmdEbZ3yxYyZw5vdaWBpF8IF_H5B4CeUGyBGWeX6du5Gil_jGtH41NcqXQ0EgFzRjy6Y35ZB3qVZJGwERQxAvTMmVOdCsEKaA_oWrQGionpkJXkvexW92NOg9GpSbj7RzvDHAhvNGNBM1LK87AxKHFzhTPyC6j2gpsKno8dD3rLUZIw"/>
</div>
</div>
</header>
<main class="flex-1 flex items-center justify-center p-6">
<div class="max-w-xl w-full bg-white rounded-xl shadow-xl shadow-slate-200/50 border border-slate-100 overflow-hidden">
<div class="p-8 md:p-12">
<div class="mb-10 text-center">
<div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-6">
<span class="material-symbols-outlined text-primary text-3xl">settings_account_box</span>
</div>
<h1 class="text-3xl font-800 text-dark-blue mb-3">Set Up Your Clinic Workspace</h1>
<p class="text-slate-500 text-lg">Configure your professional environment in seconds to start managing your patients.</p>
</div>
<?php if ($error): ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm text-center"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<form method="POST" action="" class="space-y-8">
<div class="space-y-2">
<label class="block text-sm font-bold text-dark-blue uppercase tracking-wider">Clinic Name</label>
<div class="relative">
<input name="clinic_name" class="w-full px-4 py-4 rounded-lg border-slate-200 border bg-slate-50 focus:ring-2 focus:ring-primary focus:border-primary transition-all outline-none text-dark-blue" placeholder="e.g., JRL Dental Clinic" type="text" value="<?php echo htmlspecialchars($current_clinic_name); ?>" required/>
</div>
</div>
<div class="space-y-4">
<div class="flex justify-between items-end">
<label class="block text-sm font-bold text-dark-blue uppercase tracking-wider">Clinic URL / Domain</label>
<span class="text-xs font-semibold text-emerald-600 flex items-center gap-1">
<span class="material-symbols-outlined text-sm">check_circle</span>
                                Available
                            </span>
</div>
<div class="flex items-stretch shadow-sm">
<div class="flex items-center px-4 bg-slate-100 border border-slate-200 border-r-0 rounded-l-lg text-slate-500 font-semibold select-none">
                                mydental.ct.ws/
                            </div>
<input name="clinic_slug" class="flex-1 px-4 py-4 rounded-r-lg border-slate-200 border-l-0 border bg-white focus:ring-0 focus:border-primary transition-all outline-none text-dark-blue font-medium" type="text" value="<?php echo htmlspecialchars($current_slug); ?>" placeholder="e.g. jrldentalclinic" required pattern="[a-z0-9\-]+" title="Lowercase letters, numbers and hyphens only"/>
</div>
<div class="bg-slate-50 p-4 rounded-lg border border-slate-100">
<div class="flex gap-3">
<span class="material-symbols-outlined text-slate-400 text-xl">info</span>
<div class="space-y-2">
<p class="text-xs text-slate-600 leading-relaxed">
                                        Your team will use this URL to access your workspace. 
                                    </p>
<ul class="text-[11px] font-bold text-slate-400 flex flex-wrap gap-x-4 gap-y-1 uppercase tracking-tight">
<li class="flex items-center gap-1"><span class="w-1 h-1 rounded-full bg-slate-300"></span> Lowercase</li>
<li class="flex items-center gap-1"><span class="w-1 h-1 rounded-full bg-slate-300"></span> No Spaces</li>
<li class="flex items-center gap-1"><span class="w-1 h-1 rounded-full bg-slate-300"></span> Alphanumeric Only</li>
</ul>
</div>
</div>
</div>
</div>
<button class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 rounded-lg shadow-lg shadow-primary/20 transition-all transform active:scale-[0.98] flex items-center justify-center gap-2 text-lg" type="submit">
                        Complete Setup
                        <span class="material-symbols-outlined">arrow_forward</span>
</button>
</form>
</div>
<div class="bg-slate-50 px-8 py-4 border-t border-slate-100 flex justify-between items-center text-xs text-slate-400 font-medium">
<span>Step 1 of 3</span>
<div class="flex gap-1.5">
<div class="w-8 h-1.5 rounded-full bg-primary"></div>
<div class="w-8 h-1.5 rounded-full bg-slate-200"></div>
<div class="w-8 h-1.5 rounded-full bg-slate-200"></div>
</div>
</div>
</div>
</main>
<footer class="py-8 text-center text-slate-400 text-sm">
<p>© 2024 MyDental. All rights reserved.</p>
</footer>
</body></html>