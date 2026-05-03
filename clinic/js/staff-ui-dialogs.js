/**
 * Themed staff portal dialogs — replaces window.alert / confirm with UI aligned to Clinical Precision styling.
 * Depends on Tailwind (loaded on staff pages) and Material Symbols.
 */
(function () {
    'use strict';

    var VARIANTS = {
        info: {
            kicker: 'Notice',
            accent: 'bg-[#2b8beb]',
            iconWrap: 'bg-primary/10 text-primary',
            icon: 'info',
            kickerTone: 'text-primary/80'
        },
        success: {
            kicker: 'Success',
            accent: 'bg-emerald-500',
            iconWrap: 'bg-emerald-50 text-emerald-600',
            icon: 'check_circle',
            kickerTone: 'text-emerald-700/90'
        },
        error: {
            kicker: 'Something went wrong',
            accent: 'bg-red-500',
            iconWrap: 'bg-red-50 text-red-600',
            icon: 'error',
            kickerTone: 'text-red-700/90'
        },
        warning: {
            kicker: 'Attention',
            accent: 'bg-amber-500',
            iconWrap: 'bg-amber-50 text-amber-700',
            icon: 'warning',
            kickerTone: 'text-amber-800/90'
        },
        danger: {
            kicker: 'Confirm action',
            accent: 'bg-red-600',
            iconWrap: 'bg-red-50 text-red-600',
            icon: 'delete_forever',
            kickerTone: 'text-red-700/90'
        }
    };

    var root;
    var backdrop;
    var accentBar;
    var kickerEl;
    var titleEl;
    var bodyEl;
    var footerEl;
    var iconWrap;
    var iconEl;
    var btnCancel;
    var btnOk;
    var escapeHandler;
    var openCount = 0;

    function ensureDom() {
        if (root) return;
        root = document.createElement('div');
        root.id = 'staff-ui-dialog-root';
        // Inline position + z-index: Tailwind Play CDN doesn't reliably JIT-compile utilities that only appear in external .js; without them the overlay rendered under sidebar (z-40) / header (z-30) or flowed in normal layout.
        root.className = 'hidden';
        root.style.position = 'fixed';
        root.style.left = root.style.right = root.style.top = root.style.bottom = '0';
        root.style.zIndex = '100050';
        root.setAttribute('aria-hidden', 'true');
        root.innerHTML =
            '<div class="absolute inset-0 flex items-center justify-center p-4 sm:p-6">' +
            '<div id="staff-ui-dialog-backdrop" class="absolute inset-0 bg-slate-900/50 staff-ui-dialog-backdrop"></div>' +
            '<div class="relative w-full max-w-md staff-modal-panel rounded-3xl bg-white shadow-2xl border border-slate-100 overflow-hidden font-headline" role="dialog" aria-modal="true" aria-labelledby="staff-ui-dialog-title">' +
            '<div id="staff-ui-dialog-accent" class="h-1.5 bg-primary"></div>' +
            '<div class="px-6 pt-5 pb-2 flex items-start gap-3">' +
            '<div id="staff-ui-dialog-icon-wrap" class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 bg-primary/10 text-primary">' +
            '<span id="staff-ui-dialog-icon" class="material-symbols-outlined text-[26px]" style="font-variation-settings: \'FILL\' 1;">info</span>' +
            '</div>' +
            '<div class="min-w-0 flex-1 pt-0.5">' +
            '<p id="staff-ui-dialog-kicker" class="text-[11px] font-black uppercase tracking-[0.18em] text-primary/80">Notice</p>' +
            '<h2 id="staff-ui-dialog-title" class="text-lg font-bold text-slate-900 tracking-tight mt-1 leading-snug"></h2>' +
            '</div>' +
            '</div>' +
            '<div id="staff-ui-dialog-body" class="px-6 pb-6 text-sm text-slate-600 leading-relaxed whitespace-pre-line"></div>' +
            '<div id="staff-ui-dialog-footer" class="px-6 py-4 bg-slate-50/90 border-t border-slate-100 flex flex-wrap justify-end gap-2"></div>' +
            '</div>' +
            '</div>';
        document.body.appendChild(root);
        backdrop = root.querySelector('#staff-ui-dialog-backdrop');
        accentBar = root.querySelector('#staff-ui-dialog-accent');
        kickerEl = root.querySelector('#staff-ui-dialog-kicker');
        titleEl = root.querySelector('#staff-ui-dialog-title');
        bodyEl = root.querySelector('#staff-ui-dialog-body');
        footerEl = root.querySelector('#staff-ui-dialog-footer');
        iconWrap = root.querySelector('#staff-ui-dialog-icon-wrap');
        iconEl = root.querySelector('#staff-ui-dialog-icon');
    }

    function normalizeAlertArg(arg) {
        if (arg == null) return { title: '', message: '', variant: 'info' };
        if (typeof arg === 'string') {
            return { title: '', message: String(arg), variant: 'info' };
        }
        var o = arg;
        return {
            title: o.title != null ? String(o.title) : '',
            message: o.message != null ? String(o.message) : '',
            variant: o.variant && VARIANTS[o.variant] ? o.variant : 'info'
        };
    }

    function applyVariant(variantKey) {
        var v = VARIANTS[variantKey] || VARIANTS.info;
        accentBar.className = 'h-1.5 ' + v.accent;
        kickerEl.textContent = v.kicker;
        kickerEl.className = 'text-[11px] font-black uppercase tracking-[0.18em] ' + v.kickerTone;
        iconWrap.className = 'w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 ' + v.iconWrap;
        iconEl.textContent = v.icon;
        iconEl.style.fontVariationSettings = "'FILL' 1";
    }

    function openRoot() {
        root.classList.remove('hidden');
        root.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        openCount++;
        if (openCount === 1) {
            escapeHandler = function (e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    if (btnCancel && !btnCancel.classList.contains('hidden')) {
                        btnCancel.click();
                    } else if (btnOk) {
                        btnOk.click();
                    }
                }
            };
            document.addEventListener('keydown', escapeHandler);
        }
    }

    function closeRoot() {
        openCount = Math.max(0, openCount - 1);
        if (openCount === 0) {
            root.classList.add('hidden');
            root.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            if (escapeHandler) {
                document.removeEventListener('keydown', escapeHandler);
                escapeHandler = null;
            }
        }
    }

    /**
     * @param {string|{title?: string, message?: string, variant?: keyof VARIANTS}} arg
     * @returns {Promise<void>}
     */
    function staffUiAlert(arg) {
        ensureDom();
        var opts = normalizeAlertArg(arg);
        applyVariant(opts.variant);
        titleEl.textContent = opts.title || (
            opts.variant === 'success' ? 'Done' :
            opts.variant === 'error' ? 'Error' :
            opts.variant === 'warning' ? 'Attention' :
            'Notice'
        );
        bodyEl.textContent = opts.message;
        footerEl.innerHTML = '';
        btnCancel = null;
        btnOk = document.createElement('button');
        btnOk.type = 'button';
        btnOk.className =
            'inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-bold text-white bg-primary hover:bg-primary/90 shadow-md shadow-primary/25 transition-colors min-w-[6rem]';
        btnOk.textContent = 'OK';
        footerEl.appendChild(btnOk);

        return new Promise(function (resolve) {
            function done() {
                btnOk.removeEventListener('click', onOk);
                if (backdrop) backdrop.removeEventListener('click', onBackdrop);
                closeRoot();
                resolve();
            }
            function onOk() {
                done();
            }
            function onBackdrop() {
                done();
            }
            btnOk.addEventListener('click', onOk);
            if (backdrop) backdrop.addEventListener('click', onBackdrop);
            openRoot();
            btnOk.focus();
        });
    }

    /**
     * @param {{title?: string, message?: string, confirmLabel?: string, cancelLabel?: string, variant?: keyof VARIANTS}} opts
     * @returns {Promise<boolean>} true if confirmed
     */
    function staffUiConfirm(opts) {
        ensureDom();
        opts = opts || {};
        var variantKey = opts.variant && VARIANTS[opts.variant] ? opts.variant : 'danger';
        applyVariant(variantKey);
        titleEl.textContent = opts.title != null ? String(opts.title) : 'Please confirm';
        bodyEl.textContent = opts.message != null ? String(opts.message) : '';
        footerEl.innerHTML = '';

        btnOk = document.createElement('button');
        btnOk.type = 'button';
        btnOk.className =
            'inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-bold text-white bg-red-600 hover:bg-red-700 shadow-md shadow-red-900/10 transition-colors min-w-[6rem]';
        btnOk.textContent = opts.confirmLabel != null ? String(opts.confirmLabel) : 'Confirm';

        btnCancel = document.createElement('button');
        btnCancel.type = 'button';
        btnCancel.className =
            'inline-flex items-center justify-center rounded-xl px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 transition-colors min-w-[6rem]';
        btnCancel.textContent = opts.cancelLabel != null ? String(opts.cancelLabel) : 'Cancel';

        footerEl.appendChild(btnCancel);
        footerEl.appendChild(btnOk);

        return new Promise(function (resolve) {
            function cleanup() {
                btnOk.removeEventListener('click', onConfirm);
                btnCancel.removeEventListener('click', onCancel);
                if (backdrop) backdrop.removeEventListener('click', onBackdrop);
                closeRoot();
            }
            function onConfirm() {
                cleanup();
                resolve(true);
            }
            function onCancel() {
                cleanup();
                resolve(false);
            }
            function onBackdrop() {
                onCancel();
            }
            btnOk.addEventListener('click', onConfirm);
            btnCancel.addEventListener('click', onCancel);
            if (backdrop) backdrop.addEventListener('click', onBackdrop);
            openRoot();
            btnCancel.focus();
        });
    }

    window.staffUiAlert = staffUiAlert;
    window.staffUiConfirm = staffUiConfirm;
})();
