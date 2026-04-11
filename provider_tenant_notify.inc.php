<?php
declare(strict_types=1);
/**
 * Themed modal alerts and confirmations for the Provider tenant shell (replaces window.alert / window.confirm).
 * Include once per page — typically via provider_tenant_sidebar.inc.php.
 */
?>
<style>
  .provider-notify-overlay {
    background: rgba(15, 23, 42, 0.45);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
  }
  .provider-notify-panel {
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.2), 0 0 0 1px rgba(226, 232, 240, 0.9);
  }
  .provider-notify-enter {
    animation: provider-notify-in 0.28s cubic-bezier(0.22, 1, 0.36, 1) forwards;
  }
  @keyframes provider-notify-in {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
  }
</style>
<div id="provider-notify-root" class="fixed inset-0 z-[110] hidden pointer-events-none" role="alertdialog" aria-modal="true" aria-labelledby="provider-notify-title" aria-hidden="true">
  <div class="provider-notify-overlay absolute inset-0 pointer-events-auto" data-provider-notify-overlay="1"></div>
  <div class="absolute inset-0 flex items-end sm:items-center justify-center p-4 sm:p-6 pointer-events-none">
    <div id="provider-notify-panel" class="provider-notify-panel provider-notify-enter pointer-events-auto w-full max-w-md rounded-3xl bg-white border border-slate-100 overflow-hidden">
      <div class="px-6 pt-6 pb-4 flex gap-4">
        <span id="provider-notify-icon" class="material-symbols-outlined text-3xl shrink-0" aria-hidden="true">info</span>
        <div class="min-w-0 flex-1">
          <h2 id="provider-notify-title" class="text-lg font-extrabold font-headline text-on-background tracking-tight leading-snug"></h2>
          <p id="provider-notify-message" class="text-sm text-on-surface-variant mt-2 leading-relaxed whitespace-pre-wrap"></p>
        </div>
      </div>
      <div id="provider-notify-footer" class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 px-6 pb-6 pt-1 border-t border-slate-100 bg-slate-50/60"></div>
    </div>
  </div>
</div>
<script>
(function () {
  if (window.providerNotify) return;
  var root = document.getElementById('provider-notify-root');
  var panel = document.getElementById('provider-notify-panel');
  var iconEl = document.getElementById('provider-notify-icon');
  var titleEl = document.getElementById('provider-notify-title');
  var messageEl = document.getElementById('provider-notify-message');
  var footerEl = document.getElementById('provider-notify-footer');
  var overlay = root ? root.querySelector('[data-provider-notify-overlay]') : null;
  if (!root || !panel || !iconEl || !titleEl || !messageEl || !footerEl) return;

  var resolveFn = null;
  var mode = 'alert';

  var variantMap = {
    info: { icon: 'info', iconClass: 'text-primary', title: 'Notice' },
    success: { icon: 'check_circle', iconClass: 'text-emerald-600', title: 'Success' },
    error: { icon: 'error', iconClass: 'text-red-600', title: 'Something went wrong' },
    warning: { icon: 'warning', iconClass: 'text-amber-600', title: 'Please review' }
  };

  function setIcon(variant) {
    var v = variantMap[variant] || variantMap.info;
    iconEl.textContent = v.icon;
    iconEl.className = 'material-symbols-outlined text-3xl shrink-0 ' + v.iconClass;
  }

  function trapFocus() {
    var btns = footerEl.querySelectorAll('button');
    if (btns.length) btns[btns.length - 1].focus();
  }

  function openUi() {
    root.classList.remove('hidden');
    root.classList.remove('pointer-events-none');
    root.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    panel.classList.remove('provider-notify-enter');
    void panel.offsetWidth;
    panel.classList.add('provider-notify-enter');
    setTimeout(trapFocus, 30);
  }

  function finish(value) {
    var r = resolveFn;
    resolveFn = null;
    root.classList.add('hidden');
    root.classList.add('pointer-events-none');
    root.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (r) r(value);
  }

  function btnPrimary(label, destructive) {
    var b = document.createElement('button');
    b.type = 'button';
    b.textContent = label;
    b.className = destructive
      ? 'rounded-xl bg-red-700 text-white px-5 py-2.5 text-sm font-bold hover:bg-red-800 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50'
      : 'rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-bold shadow-sm shadow-primary/25 hover:brightness-105 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';
    return b;
  }

  function btnSecondary(label) {
    var b = document.createElement('button');
    b.type = 'button';
    b.textContent = label;
    b.className = 'rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-on-background hover:bg-slate-50 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300';
    return b;
  }

  function showAlert(message, opts) {
    opts = opts || {};
    var variant = opts.variant || 'info';
    var v = variantMap[variant] || variantMap.info;
    mode = 'alert';
    return new Promise(function (resolve) {
      resolveFn = resolve;
      setIcon(variant);
      titleEl.textContent = opts.title || v.title;
      messageEl.textContent = message || '';
      footerEl.innerHTML = '';
      var ok = btnPrimary(opts.confirmText || 'OK', false);
      ok.addEventListener('click', function () { finish(undefined); });
      footerEl.appendChild(ok);
      openUi();
    });
  }

  function showConfirm(message, opts) {
    opts = opts || {};
    var vBase = variantMap[opts.variant && opts.variant !== 'danger' ? opts.variant : 'info'] || variantMap.info;
    mode = 'confirm';
    var destructive = opts.variant === 'danger';
    return new Promise(function (resolve) {
      resolveFn = resolve;
      if (destructive) {
        iconEl.textContent = 'warning';
        iconEl.className = 'material-symbols-outlined text-3xl shrink-0 text-red-600';
      } else {
        setIcon(opts.variant || 'info');
      }
      titleEl.textContent = opts.title || (destructive ? 'Are you sure?' : vBase.title);
      messageEl.textContent = message || '';
      footerEl.innerHTML = '';
      var cancel = btnSecondary(opts.cancelText || 'Cancel');
      var ok = btnPrimary(opts.confirmText || 'Confirm', destructive);
      cancel.addEventListener('click', function () { finish(false); });
      ok.addEventListener('click', function () { finish(true); });
      footerEl.appendChild(cancel);
      footerEl.appendChild(ok);
      openUi();
    });
  }

  if (overlay) {
    overlay.addEventListener('click', function () {
      if (mode === 'confirm') finish(false);
      else finish(undefined);
    });
  }

  document.addEventListener('keydown', function (e) {
    if (root.classList.contains('hidden')) return;
    if (e.key !== 'Escape') return;
    e.preventDefault();
    e.stopPropagation();
    if (mode === 'confirm') finish(false);
    else finish(undefined);
  }, true);

  window.providerNotify = {
    alert: showAlert,
    confirm: showConfirm
  };
})();
</script>
