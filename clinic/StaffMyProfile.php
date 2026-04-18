<?php
$pageTitle = 'My Profile';
$staff_nav_active = 'profile';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/tenant.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowedTypes = ['admin', 'staff', 'doctor', 'manager'];
if (empty($_SESSION['user_id']) || empty($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $allowedTypes, true)) {
    header('Location: ' . clinicPageUrl('Login.php'));
    exit;
}

if (empty($_GET['clinic_slug']) && !empty($_SESSION['public_tenant_slug'])) {
    $_GET['clinic_slug'] = $_SESSION['public_tenant_slug'];
}

if (empty($_GET['clinic_slug'])) {
    $reqUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $reqPath = $reqUri !== '' ? parse_url($reqUri, PHP_URL_PATH) : '';
    $scriptBase = isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : 'StaffMyProfile.php';
    if (is_string($reqPath) && $reqPath !== '') {
        $segments = array_values(array_filter(explode('/', trim($reqPath, '/')), 'strlen'));
        $scriptIdx = array_search($scriptBase, $segments, true);
        if ($scriptIdx !== false && $scriptIdx > 0) {
            $slugFromPath = strtolower(trim((string) $segments[$scriptIdx - 1]));
            if ($slugFromPath !== '' && preg_match('/^[a-z0-9\-]+$/', $slugFromPath)) {
                $_GET['clinic_slug'] = $slugFromPath;
            }
        }
    }
}

$clinicSlugBoot = isset($_GET['clinic_slug']) ? trim((string) $_GET['clinic_slug']) : '';
if ($clinicSlugBoot !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($clinicSlugBoot))) {
    $_GET['clinic_slug'] = strtolower($clinicSlugBoot);
    require_once __DIR__ . '/tenant_bootstrap.php';
    if (!isset($currentTenantSlug) || trim((string) $currentTenantSlug) === '') {
        $currentTenantSlug = strtolower($clinicSlugBoot);
    }
} else {
    $currentTenantSlug = '';
}

requireClinicTenantId();

$clinicWebRoot = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
$staffProfileApiUrl = $clinicWebRoot . '/api/admin_profile.php';
$sessionStaffDisplayId = trim((string) ($_SESSION['staff_id'] ?? ''));
$findAccountUrl = rtrim(PROVIDER_BASE_URL, '/') . '/ProviderFindAccount.php';

if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>My Profile | Staff Portal</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2b8beb",
                        "background": "#f8fafc",
                        "surface": "#ffffff",
                        "on-background": "#101922",
                        "on-surface-variant": "#404752",
                        "surface-container-low": "#edf4ff",
                        "outline-variant": "#cbd5e1"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem"
                    },
                },
            },
        }
    </script>
    <style>
        body { font-family: 'Manrope', sans-serif; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
        }
        .profile-hero-banner {
            background: linear-gradient(120deg, #1e3a5f 0%, #2b8beb 42%, #5ab0ff 100%);
            box-shadow: 0 20px 50px -24px rgba(43, 139, 235, 0.45);
        }
        .profile-section-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(165deg, rgba(255, 255, 255, 0.99) 0%, rgba(248, 250, 252, 0.96) 100%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.95), 0 10px 40px -12px rgba(15, 23, 42, 0.08);
        }
        .profile-section-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 3px;
            border-radius: 1.5rem 1.5rem 0 0;
            background: linear-gradient(90deg, #2b8beb, #60a5fa);
            opacity: 0.95;
            pointer-events: none;
        }
        .profile-section-card--security::before {
            background: linear-gradient(90deg, #1e3a5f, #2b8beb);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .cropper-modal-wrap .cropper-view-box,
        .cropper-modal-wrap .cropper-face { border-radius: 50%; }
        #confirmModal[aria-hidden="true"] { pointer-events: none; }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-[4.75rem] provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="px-4 sm:px-6 pb-6 sm:pb-8 pt-3 sm:pt-4 space-y-5 sm:space-y-6 max-w-7xl mx-auto w-full">
        <div id="toast" class="hidden fixed bottom-8 right-8 z-[60] max-w-sm rounded-2xl border px-4 py-3 shadow-xl text-sm font-semibold" role="status"></div>

        <section class="flex flex-col gap-2 sm:gap-3">
            <div class="text-primary font-bold text-xs uppercase flex items-center gap-3 tracking-[0.3em]">
                <span class="w-10 sm:w-12 h-[1.5px] bg-primary"></span> MY PROFILE
            </div>
            <div>
                <h2 class="font-headline text-3xl sm:text-4xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Your <span class="font-editorial italic font-normal text-primary">profile</span>
                </h2>
                <p class="font-body text-sm sm:text-base font-medium text-on-surface-variant max-w-xl leading-relaxed mt-2">
                    Keep your clinic identity accurate. Changes here sync with your staff account across the portal.
                </p>
            </div>
        </section>

        <section class="profile-hero-banner rounded-3xl text-white relative">
            <div class="absolute inset-0 rounded-3xl overflow-hidden pointer-events-none" aria-hidden="true">
                <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 20% 120%, #fff 0, transparent 55%), radial-gradient(circle at 90% -20%, #fff 0, transparent 45%);"></div>
            </div>
            <div class="relative px-5 sm:px-8 py-6 sm:py-7 flex flex-col sm:flex-row sm:items-center gap-5 sm:gap-8">
                <div class="shrink-0 flex justify-center sm:justify-start">
                    <input type="file" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"/>
                    <div class="relative h-[7.5rem] w-[7.5rem] sm:h-[7.25rem] sm:w-[7.25rem]">
                        <div id="profileAvatar" class="absolute left-0 top-0 h-[7.25rem] w-[7.25rem] rounded-full overflow-hidden bg-white/15 flex items-center justify-center text-2xl font-bold ring-4 ring-white/40 bg-cover bg-center shadow-lg">
                            <span id="profileAvatarInitials" class="select-none text-white">—</span>
                        </div>
                        <button type="button" id="profilePhotoEditBtn" class="absolute z-20 bottom-0 right-0 flex h-9 w-9 items-center justify-center rounded-full bg-white text-primary shadow-lg ring-2 ring-white hover:bg-surface-container-low transition-all" aria-label="Change profile photo">
                            <span class="material-symbols-outlined text-[20px]">photo_camera</span>
                        </button>
                    </div>
                </div>
                <div class="flex-1 min-w-0 text-center sm:text-left">
                    <p class="text-white/75 text-[11px] font-bold uppercase tracking-[0.22em] mb-2">Signed in as</p>
                    <h1 class="text-2xl sm:text-3xl font-extrabold font-headline tracking-tight text-white" id="profileDisplayName">Loading…</h1>
                    <p class="mt-2 text-sm font-bold text-white/85 uppercase tracking-wider" id="profileStaffIdLine">Staff ID: —</p>
                    <p class="mt-3 text-sm text-white/80 max-w-md mx-auto sm:mx-0 leading-relaxed">
                        Use the camera button to upload a new photo. It appears here and in your workspace header where supported.
                    </p>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            <div class="lg:col-span-7 order-1">
                <section class="profile-section-card rounded-3xl border border-slate-200/70 p-8 sm:p-9">
                    <header class="flex flex-col sm:flex-row sm:items-start gap-4 pb-6 mb-6 border-b border-slate-100">
                        <div class="w-12 h-12 rounded-2xl bg-surface-container-low border border-primary/10 flex items-center justify-center text-primary shrink-0">
                            <span class="material-symbols-outlined text-2xl">badge</span>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-xl sm:text-2xl font-bold font-headline text-on-background">Personal details</h2>
                            <p class="text-sm text-on-surface-variant mt-1.5 leading-relaxed">
                                Username, email, and name are stored for sign-in and how you appear to your team.
                            </p>
                        </div>
                    </header>
                    <form id="personalForm" class="space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="fieldUsername">Username</label>
                                <input id="fieldUsername" name="username" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 transition-all shadow-sm" type="text" autocomplete="username" required/>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="fieldEmail">Email address</label>
                                <input id="fieldEmail" name="email" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 transition-all shadow-sm" type="email" autocomplete="email" required/>
                            </div>
                            <div>
                                <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="fieldFirst">First name</label>
                                <input id="fieldFirst" name="first_name" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 transition-all shadow-sm" type="text" autocomplete="given-name" required/>
                            </div>
                            <div>
                                <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="fieldLast">Last name</label>
                                <input id="fieldLast" name="last_name" class="w-full bg-white border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 transition-all shadow-sm" type="text" autocomplete="family-name" required/>
                            </div>
                        </div>
                        <p id="personalFormError" class="hidden text-sm text-rose-600 font-semibold"></p>
                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                            <button class="w-full sm:w-auto px-6 py-3 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200/90 text-xs font-black uppercase tracking-widest transition-all border border-slate-200/80" type="button" id="personalCancelBtn">Cancel</button>
                            <button class="w-full sm:w-auto px-8 py-3 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-primary/25 disabled:opacity-50" type="submit" id="personalSaveBtn">Save changes</button>
                        </div>
                    </form>
                </section>
            </div>

            <div class="lg:col-span-5 order-2">
                <section class="profile-section-card profile-section-card--security rounded-3xl border border-slate-200/70 p-8 sm:p-9 lg:sticky lg:top-24">
                    <header class="flex flex-col sm:flex-row sm:items-start gap-4 pb-6 mb-6 border-b border-slate-100">
                        <div class="w-12 h-12 rounded-2xl bg-[#0b3463]/10 border border-[#0b3463]/15 flex items-center justify-center text-[#0b3463] shrink-0">
                            <span class="material-symbols-outlined text-2xl">shield_lock</span>
                        </div>
                        <div class="min-w-0">
                            <h2 class="text-xl sm:text-2xl font-bold font-headline text-on-background">Security</h2>
                            <p class="text-sm text-on-surface-variant mt-1.5 leading-relaxed">
                                Password updates require a 6-digit code sent to your registered email.
                            </p>
                        </div>
                    </header>
                    <form id="passwordForm" class="space-y-5">
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="pwCurrent">Current password</label>
                            <div class="relative">
                                <input id="pwCurrent" class="w-full bg-white border border-slate-200 rounded-xl py-3 pl-4 pr-11 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 transition-all shadow-sm" type="password" autocomplete="current-password"/>
                                <button type="button" class="profile-pw-toggle absolute right-1 top-1/2 -translate-y-1/2 p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors" data-pw-target="pwCurrent" aria-label="Show password" aria-pressed="false">
                                    <span class="material-symbols-outlined text-[20px] icon-visible">visibility</span>
                                    <span class="material-symbols-outlined text-[20px] icon-hidden hidden">visibility_off</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="pwNew">New password</label>
                            <div class="relative">
                                <input id="pwNew" class="w-full bg-white border border-slate-200 rounded-xl py-3 pl-4 pr-11 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 transition-all shadow-sm" type="password" autocomplete="new-password"/>
                                <button type="button" class="profile-pw-toggle absolute right-1 top-1/2 -translate-y-1/2 p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors" data-pw-target="pwNew" aria-label="Show password" aria-pressed="false">
                                    <span class="material-symbols-outlined text-[20px] icon-visible">visibility</span>
                                    <span class="material-symbols-outlined text-[20px] icon-hidden hidden">visibility_off</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="pwConfirm">Confirm new password</label>
                            <div class="relative">
                                <input id="pwConfirm" class="w-full bg-white border border-slate-200 rounded-xl py-3 pl-4 pr-11 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/25 focus:border-primary/40 transition-all shadow-sm" type="password" autocomplete="new-password"/>
                                <button type="button" class="profile-pw-toggle absolute right-1 top-1/2 -translate-y-1/2 p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition-colors" data-pw-target="pwConfirm" aria-label="Show password" aria-pressed="false">
                                    <span class="material-symbols-outlined text-[20px] icon-visible">visibility</span>
                                    <span class="material-symbols-outlined text-[20px] icon-hidden hidden">visibility_off</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-on-surface-variant leading-relaxed rounded-xl bg-slate-50 border border-slate-100 px-3 py-2.5">
                            Use at least 8 characters with both letters and numbers.
                        </p>
                        <p id="passwordFormError" class="hidden text-sm text-rose-600 font-semibold"></p>
                        <button class="w-full px-7 py-3.5 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-primary/25 disabled:opacity-50" type="submit" id="passwordSubmitBtn">Update password</button>
                        <a class="flex items-center justify-center gap-2 w-full py-2 text-rose-600 hover:text-rose-700 text-[11px] font-black uppercase tracking-wider transition-colors" href="<?php echo htmlspecialchars($findAccountUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="material-symbols-outlined text-base">mark_email_unread</span>
                            Reset password via email
                        </a>
                    </form>
                </section>
            </div>
        </div>
    </div>
</main>

<!-- Centered confirmation (profile / password success) -->
<div id="confirmModal" class="hidden fixed inset-0 z-[65] flex items-center justify-center p-4 bg-slate-900/45 backdrop-blur-sm" aria-modal="true" role="dialog" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200/90 text-center">
        <div class="h-1.5 w-full bg-gradient-to-r from-[#1e3a5f] via-primary to-[#5ab0ff]"></div>
        <div class="p-8 sm:p-10">
            <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                <span class="material-symbols-outlined text-3xl" id="confirmModalIcon">check_circle</span>
            </div>
            <h3 id="confirmModalTitle" class="text-lg sm:text-xl font-extrabold font-headline text-on-background">Success</h3>
            <p id="confirmModalMessage" class="mt-3 text-sm sm:text-base text-on-surface-variant leading-relaxed"></p>
            <button type="button" id="confirmModalOk" class="mt-8 w-full rounded-xl bg-primary px-6 py-3.5 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-primary/25 hover:bg-primary/90 transition-colors">OK</button>
        </div>
    </div>
</div>

<!-- OTP Modal -->
<div id="otpModal" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" aria-modal="true" role="dialog">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-200/90">
        <div class="h-1.5 w-full bg-gradient-to-r from-[#1e3a5f] via-primary to-[#5ab0ff]"></div>
        <div class="p-8">
        <h3 class="text-xl font-extrabold text-on-background font-headline">Enter verification code</h3>
        <p class="text-sm text-on-surface-variant mt-2">We sent a 6-digit code to your clinic email. Enter it below to finish updating your password.</p>
        <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mt-6 mb-2" for="otpInput">6-digit code</label>
        <input id="otpInput" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]*" class="w-full tracking-[0.4em] text-center text-2xl font-bold bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 outline-none focus:ring-2 focus:ring-primary/30" placeholder="000000" autocomplete="one-time-code"/>
        <p id="otpError" class="hidden text-sm text-rose-600 font-semibold mt-3"></p>
        <div class="flex gap-3 mt-6">
            <button type="button" id="otpCancelBtn" class="flex-1 px-4 py-3 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-black uppercase tracking-widest border border-slate-200/80">Cancel</button>
            <button type="button" id="otpConfirmBtn" class="flex-1 px-4 py-3 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-black uppercase tracking-widest shadow-lg shadow-primary/25">Verify</button>
        </div>
        </div>
    </div>
</div>

<!-- Photo crop modal -->
<div id="cropModal" class="hidden fixed inset-0 z-[80] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full overflow-hidden border border-slate-200/90">
        <div class="h-1.5 w-full bg-gradient-to-r from-primary to-[#5ab0ff]"></div>
        <div class="p-6">
        <h3 class="text-lg font-extrabold text-on-background font-headline">Adjust profile photo</h3>
        <p class="text-sm text-on-surface-variant mt-1">Drag to reposition, scroll to zoom.</p>
        <div class="mt-4 max-h-[60vh] cropper-modal-wrap">
            <img id="cropImage" src="" alt="" class="block max-w-full rounded-2xl"/>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="button" id="cropCancelBtn" class="flex-1 px-4 py-3 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-black uppercase tracking-widest border border-slate-200/80">Cancel</button>
            <button type="button" id="cropSaveBtn" class="flex-1 px-4 py-3 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-black uppercase tracking-widest shadow-lg shadow-primary/25">Save photo</button>
        </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function () {
  var API = <?php echo json_encode($staffProfileApiUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  var SESSION_STAFF_ID = <?php echo json_encode($sessionStaffDisplayId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

  var personalSnapshot = {};
  var cropper = null;

  function showToast(message, ok) {
    var el = document.getElementById('toast');
    if (!el) return;
    el.textContent = message;
    el.className = 'fixed bottom-8 right-8 z-[60] max-w-sm rounded-2xl border px-4 py-3 shadow-xl text-sm font-semibold ' +
      (ok ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900');
    el.classList.remove('hidden');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () { el.classList.add('hidden'); }, 4200);
  }

  var confirmModal = document.getElementById('confirmModal');
  var confirmModalMessage = document.getElementById('confirmModalMessage');

  function showConfirmModal(message) {
    if (!confirmModal || !confirmModalMessage) return;
    confirmModalMessage.textContent = message;
    confirmModal.classList.remove('hidden');
    confirmModal.setAttribute('aria-hidden', 'false');
    var okBtn = document.getElementById('confirmModalOk');
    if (okBtn) okBtn.focus();
  }

  function closeConfirmModal() {
    if (!confirmModal) return;
    confirmModal.classList.add('hidden');
    confirmModal.setAttribute('aria-hidden', 'true');
  }

  var confirmOk = document.getElementById('confirmModalOk');
  if (confirmOk) confirmOk.addEventListener('click', closeConfirmModal);
  if (confirmModal) {
    confirmModal.addEventListener('click', function (e) {
      if (e.target === confirmModal) closeConfirmModal();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && confirmModal && !confirmModal.classList.contains('hidden')) {
        closeConfirmModal();
      }
    });
  }

  function syncHeaderAvatar(imageUrl, firstName, lastName) {
    var box = document.getElementById('staff-header-avatar');
    var initialsEl = document.getElementById('staff-header-avatar-initials');
    if (!box || !initialsEl) return;
    var url = (imageUrl || '').trim();
    if (url) {
      box.style.backgroundImage = 'url("' + url.replace(/"/g, '%22') + '")';
      box.classList.remove('bg-primary/15');
      initialsEl.classList.add('sr-only');
    } else {
      box.style.backgroundImage = '';
      box.classList.add('bg-primary/15');
      initialsEl.classList.remove('sr-only');
      initialsEl.textContent = initialsFromName(firstName, lastName);
    }
  }

  function syncHeaderDisplayName(fullName) {
    var nameEl = document.getElementById('staff-header-user-name');
    if (nameEl && fullName) nameEl.textContent = fullName;
  }

  document.querySelectorAll('.profile-pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-pw-target');
      var input = id ? document.getElementById(id) : null;
      if (!input) return;
      var showPlain = input.type === 'password';
      input.type = showPlain ? 'text' : 'password';
      var isPlain = input.type === 'text';
      btn.setAttribute('aria-pressed', isPlain ? 'true' : 'false');
      btn.setAttribute('aria-label', isPlain ? 'Hide password' : 'Show password');
      var showEye = btn.querySelector('.icon-visible');
      var hideEye = btn.querySelector('.icon-hidden');
      if (showEye && hideEye) {
        showEye.classList.toggle('hidden', isPlain);
        hideEye.classList.toggle('hidden', !isPlain);
      }
    });
  });

  function initialsFromName(first, last) {
    var a = (first || '').trim().charAt(0);
    var b = (last || '').trim().charAt(0);
    if (!a && !b) return '•';
    if (!b) return (a + a).toUpperCase().slice(0, 2);
    return (a + b).toUpperCase();
  }

  function applyAvatar(url, first, last) {
    var wrap = document.getElementById('profileAvatar');
    var ini = document.getElementById('profileAvatarInitials');
    if (!wrap || !ini) return;
    if (url) {
      wrap.style.backgroundImage = 'url("' + url.replace(/"/g, '%22') + '")';
      ini.textContent = '';
      ini.classList.add('hidden');
    } else {
      wrap.style.backgroundImage = '';
      ini.classList.remove('hidden');
      ini.textContent = initialsFromName(first, last);
    }
    syncHeaderAvatar(url, first, last);
  }

  function fillForm(d) {
    document.getElementById('fieldUsername').value = d.username || '';
    document.getElementById('fieldEmail').value = d.email || '';
    document.getElementById('fieldFirst').value = d.first_name || '';
    document.getElementById('fieldLast').value = d.last_name || '';

    var displayName = ((d.first_name || '') + ' ' + (d.last_name || '')).trim() || (d.full_name || 'Staff');
    document.getElementById('profileDisplayName').textContent = displayName;

    var sid = d.staff_display_id || SESSION_STAFF_ID;
    document.getElementById('profileStaffIdLine').textContent = sid ? ('Staff ID: ' + sid) : 'Staff profile';

    var imgUrl = d.profile_image_url || '';
    applyAvatar(imgUrl, d.first_name, d.last_name);
    syncHeaderDisplayName(displayName);

    personalSnapshot = {
      username: d.username || '',
      email: d.email || '',
      first_name: d.first_name || '',
      last_name: d.last_name || ''
    };
  }

  function loadProfile() {
    return fetch(API, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) throw new Error(j.message || 'Could not load profile.');
        fillForm(j.data || {});
      });
  }

  document.getElementById('personalCancelBtn').addEventListener('click', function () {
    document.getElementById('fieldUsername').value = personalSnapshot.username;
    document.getElementById('fieldEmail').value = personalSnapshot.email;
    document.getElementById('fieldFirst').value = personalSnapshot.first_name;
    document.getElementById('fieldLast').value = personalSnapshot.last_name;
    document.getElementById('personalFormError').classList.add('hidden');
  });

  document.getElementById('personalForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var err = document.getElementById('personalFormError');
    err.classList.add('hidden');
    var btn = document.getElementById('personalSaveBtn');
    btn.disabled = true;
    var body = {
      username: document.getElementById('fieldUsername').value.trim(),
      email: document.getElementById('fieldEmail').value.trim(),
      first_name: document.getElementById('fieldFirst').value.trim(),
      last_name: document.getElementById('fieldLast').value.trim()
    };
    fetch(API, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) throw new Error(j.message || 'Save failed.');
        return loadProfile();
      })
      .then(function () {
        showConfirmModal('Your profile has been changed.');
      })
      .catch(function (ex) {
        err.textContent = ex.message || 'Save failed.';
        err.classList.remove('hidden');
      })
      .finally(function () { btn.disabled = false; });
  });

  var otpModal = document.getElementById('otpModal');
  var otpInput = document.getElementById('otpInput');
  var otpError = document.getElementById('otpError');

  function closeOtpModal() {
    otpModal.classList.add('hidden');
    otpInput.value = '';
    otpError.classList.add('hidden');
  }

  document.getElementById('otpCancelBtn').addEventListener('click', closeOtpModal);

  document.getElementById('passwordForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var err = document.getElementById('passwordFormError');
    err.classList.add('hidden');
    var cur = document.getElementById('pwCurrent').value;
    var nw = document.getElementById('pwNew').value;
    var cf = document.getElementById('pwConfirm').value;
    if (!cur || !nw || !cf) {
      err.textContent = 'Fill in all password fields.';
      err.classList.remove('hidden');
      return;
    }
    if (nw !== cf) {
      err.textContent = 'New password and confirmation do not match.';
      err.classList.remove('hidden');
      return;
    }
    var btn = document.getElementById('passwordSubmitBtn');
    btn.disabled = true;
    fetch(API + '?action=request_password_otp', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        current_password: cur,
        new_password: nw,
        confirm_password: cf
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) throw new Error(j.message || 'Could not send code.');
        otpModal.classList.remove('hidden');
        otpInput.focus();
      })
      .catch(function (ex) {
        err.textContent = ex.message || 'Request failed.';
        err.classList.remove('hidden');
      })
      .finally(function () { btn.disabled = false; });
  });

  function submitOtp() {
    otpError.classList.add('hidden');
    var code = otpInput.value.trim();
    if (code.length !== 6 || !/^\d+$/.test(code)) {
      otpError.textContent = 'Enter the 6-digit code.';
      otpError.classList.remove('hidden');
      return;
    }
    var btn = document.getElementById('otpConfirmBtn');
    btn.disabled = true;
    fetch(API + '?action=confirm_password_otp', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ otp_code: code })
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) throw new Error(j.message || 'Verification failed.');
        closeOtpModal();
        document.getElementById('pwCurrent').value = '';
        document.getElementById('pwNew').value = '';
        document.getElementById('pwConfirm').value = '';
        showConfirmModal('Your password has been changed.');
      })
      .catch(function (ex) {
        otpError.textContent = ex.message || 'Verification failed.';
        otpError.classList.remove('hidden');
      })
      .finally(function () { btn.disabled = false; });
  }

  document.getElementById('otpConfirmBtn').addEventListener('click', submitOtp);
  otpInput.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter') submitOtp();
  });

  /* Photo upload + crop */
  var cropModal = document.getElementById('cropModal');
  var cropImg = document.getElementById('cropImage');
  var fileInput = document.getElementById('profilePhotoInput');

  document.getElementById('profilePhotoEditBtn').addEventListener('click', function () {
    fileInput.click();
  });

  fileInput.addEventListener('change', function () {
    var f = fileInput.files && fileInput.files[0];
    if (!f) return;
    var url = URL.createObjectURL(f);
    cropImg.onload = function () {
      if (cropper) {
        cropper.destroy();
        cropper = null;
      }
      cropper = new Cropper(cropImg, {
        aspectRatio: 1,
        viewMode: 1,
        dragMode: 'move',
        background: false,
        guides: false,
        highlight: false,
        autoCropArea: 1
      });
      cropModal.classList.remove('hidden');
    };
    cropImg.src = url;
    fileInput.value = '';
  });

  document.getElementById('cropCancelBtn').addEventListener('click', function () {
    cropModal.classList.add('hidden');
    if (cropper) {
      cropper.destroy();
      cropper = null;
    }
    cropImg.src = '';
  });

  document.getElementById('cropSaveBtn').addEventListener('click', function () {
    if (!cropper) return;
    var canvas = cropper.getCroppedCanvas({ width: 512, height: 512, imageSmoothingQuality: 'high' });
    if (!canvas) return;
    var dataUrl = canvas.toDataURL('image/jpeg', 0.92);
    var btn = document.getElementById('cropSaveBtn');
    btn.disabled = true;
    fetch(API + '?action=upload_photo', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ photo: dataUrl })
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) throw new Error(j.message || 'Upload failed.');
        cropModal.classList.add('hidden');
        if (cropper) {
          cropper.destroy();
          cropper = null;
        }
        cropImg.src = '';
        var d = j.data || {};
        var url = d.profile_image_url || '';
        var first = document.getElementById('fieldFirst').value;
        var last = document.getElementById('fieldLast').value;
        applyAvatar(url, first, last);
      })
      .catch(function (ex) {
        showToast(ex.message || 'Upload failed.', false);
      })
      .finally(function () { btn.disabled = false; });
  });

  loadProfile().catch(function (ex) {
    showToast(ex.message || 'Could not load profile.', false);
    document.getElementById('profileDisplayName').textContent = 'Profile';
  });
})();
</script>
</body>
</html>
