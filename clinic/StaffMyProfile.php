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
        body { font-family: 'Manrope', sans-serif; -webkit-font-smoothing: antialiased; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .profile-page-bg {
            background-color: #f1f5f9;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(43, 139, 235, 0.14), transparent),
                radial-gradient(ellipse 60% 40% at 100% 0%, rgba(59, 130, 246, 0.06), transparent),
                linear-gradient(180deg, #f8fafc 0%, #f1f5f9 40%, #eef2f7 100%);
        }
        .profile-hero-gradient {
            background: linear-gradient(135deg, #0b3463 0%, #1e5a9e 42%, #2b8beb 100%);
        }
        .profile-glass {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.65);
            box-shadow:
                0 4px 24px -4px rgba(15, 23, 42, 0.12),
                0 0 0 1px rgba(15, 23, 42, 0.04);
        }
        .profile-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow:
                0 1px 2px rgba(15, 23, 42, 0.04),
                0 12px 40px -16px rgba(15, 23, 42, 0.12);
            border-radius: 1.25rem;
        }
        .profile-input {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: #fff;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #1e293b;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
            outline: none;
        }
        .profile-input:focus {
            border-color: rgba(43, 139, 235, 0.45);
            box-shadow: 0 0 0 3px rgba(43, 139, 235, 0.15);
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
    </style>
</head>
<body class="text-on-background profile-page-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="flex-1 pb-12">
        <div id="toast" class="hidden fixed bottom-8 right-8 z-[60] max-w-sm rounded-2xl border px-4 py-3.5 shadow-lg text-sm font-semibold" role="status"></div>

        <!-- Hero -->
        <div class="profile-hero-gradient relative overflow-hidden">
            <div class="absolute inset-0 opacity-30 pointer-events-none" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.07\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>
            <div class="relative max-w-6xl mx-auto px-5 sm:px-8 py-10 sm:py-12">
                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-white/70 mb-4">Account</p>
                <div class="profile-glass rounded-2xl p-6 sm:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-6 sm:gap-10">
                        <div class="relative shrink-0 mx-auto sm:mx-0">
                            <div class="relative w-28 h-28 sm:w-32 sm:h-32 rounded-full bg-gradient-to-br from-primary to-blue-600 text-white flex items-center justify-center text-2xl sm:text-3xl font-bold shadow-xl shadow-slate-900/20 ring-4 ring-white overflow-hidden bg-cover bg-center" id="profileAvatar">
                                <span id="profileAvatarInitials" class="select-none">—</span>
                                <input type="file" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"/>
                                <button type="button" id="profilePhotoEditBtn" class="absolute bottom-0 right-0 w-9 h-9 rounded-full bg-white text-primary flex items-center justify-center border-2 border-slate-100 shadow-md hover:bg-slate-50 transition-colors" aria-label="Change profile photo">
                                    <span class="material-symbols-outlined text-[20px]">photo_camera</span>
                                </button>
                            </div>
                        </div>
                        <div class="flex-1 text-center sm:text-left min-w-0">
                            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 font-headline" id="profileDisplayName">Loading…</h1>
                            <p class="mt-1 text-sm font-semibold text-primary" id="profileStaffIdLine">Staff ID: —</p>
                            <p class="mt-2 text-sm text-slate-500 truncate max-w-xl mx-auto sm:mx-0" id="profileHeroEmail"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Asymmetric layout: intro left (lg) · Personal + Security stacked on the right -->
        <div class="max-w-6xl mx-auto px-5 sm:px-8 -mt-6 relative z-10">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10 lg:items-start">
                <aside class="lg:col-span-5 space-y-4 pt-2 order-2 lg:order-1">
                    <h2 class="text-xl font-extrabold text-slate-900 font-headline tracking-tight">Your profile</h2>
                    <p class="text-sm text-slate-600 leading-relaxed">Update how you appear in the staff portal, keep your email current, and rotate your password when needed.</p>
                    <ul class="text-sm text-slate-500 space-y-2 pt-2">
                        <li class="flex gap-2"><span class="text-primary font-bold">·</span> Photo syncs across your session</li>
                        <li class="flex gap-2"><span class="text-primary font-bold">·</span> Password changes need email verification</li>
                    </ul>
                </aside>
                <div class="lg:col-span-7 space-y-6 order-1 lg:order-2">
                <!-- Personal Details -->
                <section class="profile-card p-6 sm:p-8">
                    <div class="flex items-start gap-4 mb-6">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined text-[22px]">badge</span>
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900 font-headline tracking-tight">Personal details</h2>
                            <p class="text-sm text-slate-500 mt-0.5">Name, username, and contact email for this clinic.</p>
                        </div>
                    </div>
                    <form id="personalForm" class="space-y-5">
                        <div class="grid grid-cols-1 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="fieldUsername">Username</label>
                                <input id="fieldUsername" name="username" class="profile-input" type="text" autocomplete="username" required/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="fieldEmail">Email address</label>
                                <input id="fieldEmail" name="email" class="profile-input" type="email" autocomplete="email" required/>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="fieldFirst">First name</label>
                                    <input id="fieldFirst" name="first_name" class="profile-input" type="text" autocomplete="given-name" required/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="fieldLast">Last name</label>
                                    <input id="fieldLast" name="last_name" class="profile-input" type="text" autocomplete="family-name" required/>
                                </div>
                            </div>
                        </div>
                        <p id="personalFormError" class="hidden text-sm text-rose-600 font-medium"></p>
                        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2">
                            <button class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50 transition-colors" type="button" id="personalCancelBtn">Cancel</button>
                            <button class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold shadow-md shadow-primary/25 hover:bg-primary/90 transition-colors disabled:opacity-50" type="submit" id="personalSaveBtn">Save changes</button>
                        </div>
                    </form>
                </section>

                <!-- Security — right -->
                <section class="profile-card p-6 sm:p-8">
                    <div class="flex items-start gap-4 mb-6">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                            <span class="material-symbols-outlined text-[22px]">shield_lock</span>
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900 font-headline tracking-tight">Security</h2>
                            <p class="text-sm text-slate-500 mt-0.5">Change your password. We will email a 6-digit code to confirm.</p>
                        </div>
                    </div>
                    <form id="passwordForm" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="pwCurrent">Current password</label>
                            <input id="pwCurrent" class="profile-input" type="password" autocomplete="current-password"/>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="pwNew">New password</label>
                            <input id="pwNew" class="profile-input" type="password" autocomplete="new-password"/>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1.5" for="pwConfirm">Confirm new password</label>
                            <input id="pwConfirm" class="profile-input" type="password" autocomplete="new-password"/>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed rounded-xl bg-slate-50 border border-slate-100 px-3 py-2">Use at least 8 characters with letters and numbers.</p>
                        <p id="passwordFormError" class="hidden text-sm text-rose-600 font-medium"></p>
                        <div class="flex flex-col gap-4 pt-1">
                            <button class="w-full sm:w-auto sm:ml-auto px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition-colors disabled:opacity-50" type="submit" id="passwordSubmitBtn">Update password</button>
                            <a class="inline-flex items-center justify-center sm:justify-start gap-2 text-sm font-semibold text-rose-600 hover:text-rose-700 transition-colors" href="<?php echo htmlspecialchars($findAccountUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                <span class="material-symbols-outlined text-[18px]">mark_email_unread</span>
                                Forgot password? Reset via email
                            </a>
                        </div>
                    </form>
                </section>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- OTP Modal -->
<div id="otpModal" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/45 backdrop-blur-md" aria-modal="true" role="dialog">
    <div class="profile-glass rounded-2xl max-w-md w-full p-7 sm:p-8 shadow-2xl">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/15 text-primary mb-4">
            <span class="material-symbols-outlined text-[26px]">mark_email_read</span>
        </div>
        <h3 class="text-xl font-extrabold text-slate-900 font-headline tracking-tight">Verification code</h3>
        <p class="text-sm text-slate-600 mt-2 leading-relaxed">We emailed a 6-digit code to your registered address. Enter it to finish updating your password.</p>
        <label class="block text-xs font-semibold text-slate-600 mt-6 mb-1.5" for="otpInput">Code</label>
        <input id="otpInput" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]*" class="profile-input tracking-[0.35em] text-center text-2xl font-bold py-3.5" placeholder="••••••" autocomplete="one-time-code"/>
        <p id="otpError" class="hidden text-sm text-rose-600 font-medium mt-3"></p>
        <div class="flex flex-col-reverse sm:flex-row gap-3 mt-6">
            <button type="button" id="otpCancelBtn" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">Cancel</button>
            <button type="button" id="otpConfirmBtn" class="flex-1 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold shadow-md shadow-primary/25 hover:bg-primary/90">Verify</button>
        </div>
    </div>
</div>

<!-- Photo crop modal -->
<div id="cropModal" class="hidden fixed inset-0 z-[80] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-md">
    <div class="profile-glass rounded-2xl max-w-lg w-full p-6 sm:p-7 shadow-2xl">
        <h3 class="text-lg font-extrabold text-slate-900 font-headline">Crop photo</h3>
        <p class="text-sm text-slate-600 mt-1">Drag to reposition. Pinch or scroll to zoom.</p>
        <div class="mt-4 max-h-[58vh] cropper-modal-wrap rounded-xl overflow-hidden bg-slate-100">
            <img id="cropImage" src="" alt="" class="block max-w-full"/>
        </div>
        <div class="flex flex-col-reverse sm:flex-row gap-3 mt-6">
            <button type="button" id="cropCancelBtn" class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50">Cancel</button>
            <button type="button" id="cropSaveBtn" class="flex-1 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold shadow-md shadow-primary/25 hover:bg-primary/90">Save photo</button>
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

    var heroEmail = document.getElementById('profileHeroEmail');
    if (heroEmail) {
      heroEmail.textContent = d.email ? d.email : '';
    }

    var imgUrl = d.profile_image_url || '';
    applyAvatar(imgUrl, d.first_name, d.last_name);

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
        showToast(j.message || 'Saved.', true);
        return loadProfile();
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
        showToast(j.message || 'Code sent.', true);
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
        showToast(j.message || 'Password updated.', true);
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
        showToast(j.message || 'Photo updated.', true);
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
