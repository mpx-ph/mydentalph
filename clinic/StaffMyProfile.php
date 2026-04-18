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
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .elevated-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .cropper-modal-wrap .cropper-view-box,
        .cropper-modal-wrap .cropper-face { border-radius: 50%; }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="p-6 sm:p-10">
        <section class="max-w-5xl mx-auto space-y-8">
            <div id="profile-hero" class="text-center">
                <div class="relative mx-auto w-28 h-28 rounded-full bg-primary text-white flex items-center justify-center text-2xl font-bold shadow-lg shadow-primary/30 ring-4 ring-white overflow-hidden bg-cover bg-center" id="profileAvatar">
                    <span id="profileAvatarInitials" class="select-none">—</span>
                    <input type="file" id="profilePhotoInput" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"/>
                    <button type="button" id="profilePhotoEditBtn" class="absolute -right-1 -bottom-1 w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center border-2 border-white hover:bg-primary/90 transition-all" aria-label="Change profile photo">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                    </button>
                </div>
                <h1 class="mt-5 text-3xl sm:text-4xl font-extrabold tracking-tight text-on-background" id="profileDisplayName">Loading…</h1>
                <p class="mt-1 text-sm font-bold text-slate-500 uppercase tracking-wider" id="profileStaffIdLine">Staff ID: —</p>
            </div>

            <div id="toast" class="hidden fixed bottom-8 right-8 z-[60] max-w-sm rounded-2xl border px-4 py-3 shadow-xl text-sm font-semibold" role="status"></div>

            <section class="elevated-card rounded-3xl p-8 sm:p-10">
                <div class="flex items-center gap-2.5 mb-7">
                    <span class="material-symbols-outlined text-primary text-lg">description</span>
                    <h2 class="text-2xl font-bold font-headline text-on-background">Personal Details</h2>
                </div>
                <form id="personalForm" class="space-y-7">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="fieldUsername">Username</label>
                            <input id="fieldUsername" name="username" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="text" autocomplete="username" required/>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="fieldEmail">Email Address</label>
                            <input id="fieldEmail" name="email" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="email" autocomplete="email" required/>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="fieldFirst">First Name</label>
                            <input id="fieldFirst" name="first_name" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="text" autocomplete="given-name" required/>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="fieldLast">Last Name</label>
                            <input id="fieldLast" name="last_name" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="text" autocomplete="family-name" required/>
                        </div>
                    </div>
                    <p id="personalFormError" class="hidden text-sm text-rose-600 font-semibold"></p>
                    <div class="flex justify-end gap-3 pt-1">
                        <button class="px-6 py-3 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-black uppercase tracking-widest transition-all" type="button" id="personalCancelBtn">Cancel</button>
                        <button class="px-7 py-3 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-primary/20 disabled:opacity-50" type="submit" id="personalSaveBtn">Save Changes</button>
                    </div>
                </form>
            </section>

            <section class="elevated-card rounded-3xl p-8 sm:p-10">
                <div class="flex items-center gap-2.5 mb-2">
                    <span class="material-symbols-outlined text-primary text-lg">shield</span>
                    <h2 class="text-2xl font-bold font-headline text-on-background">Security Settings</h2>
                </div>
                <p class="text-sm text-on-surface-variant mb-7">Update your password to keep your account secure. We will email a 6-digit code to your registered address to confirm the change.</p>
                <form id="passwordForm" class="space-y-6">
                    <div>
                        <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="pwCurrent">Current Password</label>
                        <input id="pwCurrent" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="password" autocomplete="current-password"/>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="pwNew">New Password</label>
                            <input id="pwNew" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="password" autocomplete="new-password"/>
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mb-2" for="pwConfirm">Confirm New Password</label>
                            <input id="pwConfirm" class="w-full bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 text-sm font-semibold text-slate-700 outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary/30 transition-all" type="password" autocomplete="new-password"/>
                        </div>
                    </div>
                    <p class="text-xs text-on-surface-variant">Use at least 8 characters, including letters and numbers.</p>
                    <p id="passwordFormError" class="hidden text-sm text-rose-600 font-semibold"></p>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-1">
                        <a class="inline-flex items-center gap-2 text-rose-500 hover:text-rose-600 text-xs font-black uppercase tracking-wider transition-colors" href="<?php echo htmlspecialchars($findAccountUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                            <span class="material-symbols-outlined text-base">mark_email_unread</span>
                            Reset Password via Email
                        </a>
                        <button class="px-7 py-3 rounded-xl bg-slate-900 text-white hover:bg-slate-800 text-xs font-black uppercase tracking-widest transition-all disabled:opacity-50" type="submit" id="passwordSubmitBtn">Update Password</button>
                    </div>
                </form>
            </section>
        </section>
    </div>
</main>

<!-- OTP Modal -->
<div id="otpModal" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" aria-modal="true" role="dialog">
    <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 border border-slate-200">
        <h3 class="text-xl font-extrabold text-on-background font-headline">Enter verification code</h3>
        <p class="text-sm text-on-surface-variant mt-2">We sent a 6-digit code to your clinic email. Enter it below to finish updating your password.</p>
        <label class="block text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest mt-6 mb-2" for="otpInput">6-digit code</label>
        <input id="otpInput" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]*" class="w-full tracking-[0.4em] text-center text-2xl font-bold bg-slate-50 border border-slate-200 rounded-xl py-3 px-4 outline-none focus:ring-2 focus:ring-primary/30" placeholder="000000" autocomplete="one-time-code"/>
        <p id="otpError" class="hidden text-sm text-rose-600 font-semibold mt-3"></p>
        <div class="flex gap-3 mt-6">
            <button type="button" id="otpCancelBtn" class="flex-1 px-4 py-3 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-black uppercase tracking-widest">Cancel</button>
            <button type="button" id="otpConfirmBtn" class="flex-1 px-4 py-3 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-black uppercase tracking-widest shadow-lg shadow-primary/20">Verify</button>
        </div>
    </div>
</div>

<!-- Photo crop modal -->
<div id="cropModal" class="hidden fixed inset-0 z-[80] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 border border-slate-200">
        <h3 class="text-lg font-extrabold text-on-background font-headline">Adjust profile photo</h3>
        <p class="text-sm text-on-surface-variant mt-1">Drag to reposition, scroll to zoom.</p>
        <div class="mt-4 max-h-[60vh] cropper-modal-wrap">
            <img id="cropImage" src="" alt="" class="block max-w-full rounded-2xl"/>
        </div>
        <div class="flex gap-3 mt-6">
            <button type="button" id="cropCancelBtn" class="flex-1 px-4 py-3 rounded-xl bg-slate-100 text-slate-700 hover:bg-slate-200 text-xs font-black uppercase tracking-widest">Cancel</button>
            <button type="button" id="cropSaveBtn" class="flex-1 px-4 py-3 rounded-xl bg-primary text-white hover:bg-primary/90 text-xs font-black uppercase tracking-widest shadow-lg shadow-primary/20">Save Photo</button>
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
