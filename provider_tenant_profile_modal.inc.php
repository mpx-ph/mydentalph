<?php
declare(strict_types=1);

$__prof_dn = trim((string) ($display_name ?? ''));
$__prof_json = json_encode([
    'first_name' => $profile_first_name ?? '',
    'last_name' => $profile_last_name ?? '',
    'email' => $user_email_display ?? '',
    'display_name' => $__prof_dn !== '' ? $__prof_dn : 'Signed in',
    'avatar_initials' => $avatar_initials ?? 'MD',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($__prof_json === false) {
    $__prof_json = '{}';
}
?>
<style>
      .primary-glow { box-shadow: 0 8px 25px -5px rgba(43, 139, 235, 0.4); }
      .profile-modal-overlay {
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
      }
      .profile-modal-panel {
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.2), 0 0 0 1px rgba(226, 232, 240, 0.9);
      }
    </style>
<script type="application/json" id="profile-modal-initial"><?php echo $__prof_json; ?></script>
<div id="profile-account-modal" class="fixed inset-0 z-[100] hidden" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title">
<div class="profile-modal-overlay absolute inset-0" data-profile-modal-dismiss="1"></div>
<div class="absolute inset-0 flex items-end sm:items-center justify-center p-0 sm:p-6 pointer-events-none">
<div class="profile-modal-panel pointer-events-auto w-full sm:max-w-3xl lg:max-w-4xl h-[100dvh] sm:h-auto max-h-[100dvh] sm:max-h-[min(92vh,52rem)] overflow-hidden rounded-none sm:rounded-3xl bg-white flex flex-col">
<div class="flex items-start justify-between gap-4 px-5 sm:px-8 pt-5 sm:pt-7 pb-4 border-b border-slate-100 shrink-0">
<div>
<p class="text-[10px] font-bold uppercase tracking-[0.2em] text-primary/80">Account</p>
<h2 id="profile-modal-title" class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight mt-1">Your profile</h2>
<p class="text-sm text-on-surface-variant mt-1 max-w-xl">Update how you appear on the portal, your sign-in email, or your password.</p>
</div>
<button type="button" class="rounded-xl p-2 text-on-surface-variant hover:bg-slate-100 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40" data-profile-modal-dismiss="1" aria-label="Close">
<span class="material-symbols-outlined text-2xl">close</span>
</button>
</div>
<div class="overflow-y-auto flex-1 min-h-0 px-4 sm:px-8 py-4 sm:py-6">
<form id="profile-account-form" class="grid grid-cols-1 lg:grid-cols-5 gap-6 lg:gap-8">
<div class="lg:col-span-3 space-y-4">
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-first-name">First name</label>
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="text" id="profile-first-name" name="first_name" autocomplete="given-name" required maxlength="120"/>
</div>
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-last-name">Last name</label>
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="text" id="profile-last-name" name="last_name" autocomplete="family-name" maxlength="120"/>
</div>
</div>
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-email">Email</label>
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="email" id="profile-email" name="email" autocomplete="email" required/>
<p class="text-[11px] text-on-surface-variant mt-2 leading-relaxed">If you change this address, we will send a verification code to the new inbox via email.</p>
</div>
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-current-password">Current password</label>
<div class="relative">
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-12 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="password" id="profile-current-password" name="current_password" autocomplete="current-password" placeholder="Required when changing email or password"/>
<button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-on-surface-variant hover:bg-slate-200/80" data-pw-toggle="profile-current-password" aria-label="Show password"><span class="material-symbols-outlined text-xl">visibility</span></button>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-new-password">New password</label>
<div class="relative">
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-12 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="password" id="profile-new-password" name="new_password" autocomplete="new-password" placeholder="Leave blank to keep"/>
<button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-on-surface-variant hover:bg-slate-200/80" data-pw-toggle="profile-new-password" aria-label="Show password"><span class="material-symbols-outlined text-xl">visibility</span></button>
</div>
<div class="mt-2">
<div class="flex items-center justify-between gap-2 mb-1">
<span class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant/80">Strength</span>
<span id="profile-pw-strength-label" class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Waiting</span>
</div>
<div class="h-1.5 rounded-full bg-slate-100 overflow-hidden flex gap-0.5" id="profile-pw-strength-bar" aria-hidden="true">
<span class="h-full flex-1 rounded-sm bg-slate-200 transition-colors profile-pw-seg"></span>
<span class="h-full flex-1 rounded-sm bg-slate-200 transition-colors profile-pw-seg"></span>
<span class="h-full flex-1 rounded-sm bg-slate-200 transition-colors profile-pw-seg"></span>
<span class="h-full flex-1 rounded-sm bg-slate-200 transition-colors profile-pw-seg"></span>
</div>
</div>
</div>
<div>
<label class="block text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-1.5" for="profile-confirm-password">Confirm new</label>
<div class="relative">
<input class="w-full rounded-xl border border-slate-200 bg-slate-50/80 px-4 py-3 pr-12 text-sm font-semibold text-on-background focus:border-primary focus:ring-2 focus:ring-primary/15" type="password" id="profile-confirm-password" name="confirm_password" autocomplete="new-password" placeholder="Repeat new password"/>
<button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-lg text-on-surface-variant hover:bg-slate-200/80" data-pw-toggle="profile-confirm-password" aria-label="Show password"><span class="material-symbols-outlined text-xl">visibility</span></button>
</div>
<p class="text-[11px] text-on-surface-variant mt-2">Only fill these in when you want a new password.</p>
</div>
</div>
<div id="profile-otp-block" class="hidden rounded-2xl border border-primary/20 bg-surface-container-low/80 p-4 sm:p-5">
<label class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1.5" for="profile-email-otp">Email verification code</label>
<div class="flex flex-col sm:flex-row gap-3">
<input class="flex-1 rounded-xl border border-primary/25 bg-white px-4 py-3 text-sm font-mono tracking-[0.25em] text-center focus:border-primary focus:ring-2 focus:ring-primary/15" type="text" id="profile-email-otp" inputmode="numeric" maxlength="6" placeholder="6 digits" autocomplete="one-time-code"/>
<button type="button" id="profile-verify-otp-btn" class="shrink-0 rounded-xl bg-primary text-white px-5 py-3 text-sm font-bold primary-glow hover:brightness-105 transition-all">Verify email</button>
</div>
</div>
</div>
<div class="lg:col-span-2 space-y-4">
<div class="rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 to-white p-5">
<div class="flex gap-3">
<span class="material-symbols-outlined text-primary text-3xl shrink-0">shield_lock</span>
<div>
<p class="text-xs font-extrabold font-headline text-on-background leading-snug">Sensitive updates need your current password</p>
<p class="text-sm text-on-surface-variant mt-2 leading-relaxed">Changing email or password requires confirming your current password. A fresh code is emailed when you change your address.</p>
</div>
</div>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5">
<p class="text-[10px] font-bold uppercase tracking-wider text-on-surface-variant mb-2">Password rules</p>
<p class="text-sm text-on-surface-variant leading-relaxed">At least 12 characters with uppercase, lowercase, a number, and a special character.</p>
</div>
</div>
<div class="lg:col-span-5 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] border-t border-slate-100 mt-2 bg-white">
<button type="button" class="rounded-xl border border-slate-200 bg-white px-6 py-3 text-sm font-bold text-on-background hover:bg-slate-50 transition-colors" data-profile-modal-dismiss="1">Cancel</button>
<button type="submit" id="profile-save-btn" class="rounded-xl bg-primary text-white px-8 py-3 text-sm font-bold primary-glow hover:brightness-105 transition-all disabled:opacity-50 disabled:pointer-events-none">Save profile</button>
</div>
</form>
<p id="profile-form-message" class="mt-4 text-sm font-semibold hidden px-1" role="status"></p>
</div>
</div>
</div>
</div>
<script data-purpose="profile-modal">
(function () {
  var modal = document.getElementById('profile-account-modal');
  var openBtn = document.getElementById('open-profile-modal');
  var form = document.getElementById('profile-account-form');
  if (!modal || !form) return;
  var msgEl = document.getElementById('profile-form-message');
  var initialEl = document.getElementById('profile-modal-initial');
  var otpBlock = document.getElementById('profile-otp-block');
  var otpInput = document.getElementById('profile-email-otp');
  var verifyOtpBtn = document.getElementById('profile-verify-otp-btn');
  var saveBtn = document.getElementById('profile-save-btn');
  var newPw = document.getElementById('profile-new-password');
  var strengthLabel = document.getElementById('profile-pw-strength-label');
  var strengthBar = document.getElementById('profile-pw-strength-bar');
  var segs = strengthBar ? strengthBar.querySelectorAll('.profile-pw-seg') : [];
  var apiUrl = 'ProviderTenantProfileApi.php';

  function initialData() {
    try {
      return JSON.parse(initialEl.textContent || '{}');
    } catch (e) {
      return {};
    }
  }

  function setMsg(text, kind) {
    msgEl.textContent = text || '';
    msgEl.classList.remove('hidden', 'text-emerald-700', 'text-red-700', 'text-primary');
    if (!text) {
      msgEl.classList.add('hidden');
      return;
    }
    msgEl.classList.remove('hidden');
    if (kind === 'ok') msgEl.classList.add('text-emerald-700');
    else if (kind === 'err') msgEl.classList.add('text-red-700');
    else msgEl.classList.add('text-primary');
  }

  function openModal() {
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    if (openBtn) openBtn.setAttribute('aria-expanded', 'true');
    var d = initialData();
    document.getElementById('profile-first-name').value = d.first_name || '';
    document.getElementById('profile-last-name').value = d.last_name || '';
    document.getElementById('profile-email').value = d.email || '';
    document.getElementById('profile-current-password').value = '';
    newPw.value = '';
    document.getElementById('profile-confirm-password').value = '';
    otpBlock.classList.add('hidden');
    if (otpInput) otpInput.value = '';
    setMsg('');
    updateStrength();
    setTimeout(function () {
      document.getElementById('profile-first-name').focus();
    }, 50);
  }

  function closeModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
  }

  function updateJsonInitial(partial) {
    var cur = initialData();
    Object.assign(cur, partial || {});
    initialEl.textContent = JSON.stringify(cur);
  }

  function initialsFromFullName(full) {
    var parts = (full || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'MD';
    var a = (parts[0].charAt(0) || '').toUpperCase();
    var b = parts[1] ? (parts[1].charAt(0) || '').toUpperCase() : (parts[0].charAt(1) || '').toUpperCase();
    return (a + b).slice(0, 2);
  }

  function scorePassword(pw) {
    var s = 0;
    if (pw.length >= 12) s++;
    if (/[A-Z]/.test(pw)) s++;
    if (/[a-z]/.test(pw)) s++;
    if (/[0-9]/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s;
  }

  function updateStrength() {
    var pw = newPw.value || '';
    if (!strengthLabel || !segs.length) return;
    if (!pw) {
      strengthLabel.textContent = 'Waiting';
      strengthLabel.className = 'text-[10px] font-bold uppercase tracking-wide text-slate-400';
      segs.forEach(function (el) {
        el.classList.remove('bg-primary', 'bg-amber-400', 'bg-emerald-500');
        el.classList.add('bg-slate-200');
      });
      return;
    }
    var sc = scorePassword(pw);
    var labels = ['Weak', 'Fair', 'Good', 'Strong', 'Excellent'];
    var label = labels[Math.max(0, sc - 1)] || 'Weak';
    strengthLabel.textContent = label;
    strengthLabel.className = 'text-[10px] font-bold uppercase tracking-wide ' + (sc >= 5 ? 'text-emerald-600' : sc >= 3 ? 'text-amber-600' : 'text-red-600');
    for (var i = 0; i < segs.length; i++) {
      segs[i].classList.remove('bg-slate-200', 'bg-primary', 'bg-amber-400', 'bg-emerald-500');
      if (i < sc) {
        if (sc >= 5) segs[i].classList.add('bg-emerald-500');
        else if (sc >= 3) segs[i].classList.add('bg-amber-400');
        else segs[i].classList.add('bg-primary');
      } else segs[i].classList.add('bg-slate-200');
    }
  }

  document.querySelectorAll('[data-pw-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-pw-toggle');
      var inp = document.getElementById(id);
      if (!inp) return;
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      var icon = btn.querySelector('.material-symbols-outlined');
      if (icon) icon.textContent = show ? 'visibility_off' : 'visibility';
    });
  });

  if (openBtn) openBtn.addEventListener('click', openModal);
  modal.querySelectorAll('[data-profile-modal-dismiss]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
  });

  newPw.addEventListener('input', updateStrength);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    setMsg('');
    var fd = new FormData(form);
    var body = {
      action: 'save_profile',
      first_name: (fd.get('first_name') || '').toString().trim(),
      last_name: (fd.get('last_name') || '').toString().trim(),
      email: (fd.get('email') || '').toString().trim(),
      current_password: (fd.get('current_password') || '').toString(),
      new_password: (fd.get('new_password') || '').toString(),
      confirm_password: (fd.get('confirm_password') || '').toString()
    };
    saveBtn.disabled = true;
    fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(body)
    })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, status: r.status, j: j }; }); })
      .then(function (res) {
        if (res.j && res.j.ok) {
          setMsg(res.j.message || 'Saved.', 'ok');
          updateJsonInitial(res.j.user || {});
          if (res.j.user) {
            var nm = document.getElementById('header-account-name');
            var em = document.getElementById('header-account-email');
            var av = document.getElementById('header-account-avatar');
            if (nm) nm.textContent = res.j.user.full_name || 'Signed in';
            if (em) {
              em.textContent = res.j.user.email || '';
              em.classList.toggle('hidden', !res.j.user.email);
            }
            if (av && res.j.user.full_name) av.textContent = initialsFromFullName(res.j.user.full_name);
          }
          if (res.j.email_verification_sent) {
            otpBlock.classList.remove('hidden');
            if (otpInput) otpInput.focus();
          } else {
            setTimeout(closeModal, 900);
          }
        } else {
          setMsg((res.j && res.j.error) || 'Something went wrong.', 'err');
        }
      })
      .catch(function () { setMsg('Network error. Try again.', 'err'); })
      .finally(function () { saveBtn.disabled = false; });
  });

  if (verifyOtpBtn) {
    verifyOtpBtn.addEventListener('click', function () {
      var code = (otpInput && otpInput.value) ? otpInput.value.trim() : '';
      setMsg('');
      verifyOtpBtn.disabled = true;
      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ action: 'verify_email_otp', otp_code: code })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (res.j && res.j.ok) {
            setMsg(res.j.message || 'Verified.', 'ok');
            otpBlock.classList.add('hidden');
            setTimeout(closeModal, 800);
          } else {
            setMsg((res.j && res.j.error) || 'Invalid code.', 'err');
          }
        })
        .catch(function () { setMsg('Network error.', 'err'); })
        .finally(function () { verifyOtpBtn.disabled = false; });
    });
  }
})();
</script>
