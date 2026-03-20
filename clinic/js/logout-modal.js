/**
 * Logout confirmation modal for admin panel.
 * showLogoutModal(logoutUrl, event) shows a confirm dialog, then calls the logout API and redirects.
 */
(function() {
    var modalEl = null;

    function getModal() {
        if (modalEl) return modalEl;
        modalEl = document.createElement('div');
        modalEl.id = 'logoutModal';
        modalEl.setAttribute('role', 'dialog');
        modalEl.setAttribute('aria-modal', 'true');
        modalEl.setAttribute('aria-labelledby', 'logoutModalTitle');
        modalEl.className = 'fixed inset-0 z-[100] hidden';
        modalEl.innerHTML =
            '<div class="absolute inset-0 bg-black/50 dark:bg-black/70 backdrop-blur-sm" id="logoutModalBackdrop"></div>' +
            '<div class="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-sm rounded-2xl bg-white dark:bg-slate-800 shadow-xl border border-slate-200 dark:border-slate-700 p-6 z-[101]">' +
            '<h3 id="logoutModalTitle" class="text-lg font-semibold text-slate-900 dark:text-white mb-2">Log out</h3>' +
            '<p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Are you sure you want to log out?</p>' +
            '<div class="flex gap-3 justify-end">' +
            '<button type="button" id="logoutModalCancel" class="px-4 py-2 rounded-lg text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">Cancel</button>' +
            '<button type="button" id="logoutModalConfirm" class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white hover:opacity-90 transition-opacity">Log out</button>' +
            '</div>' +
            '</div>';
        document.body.appendChild(modalEl);
        return modalEl;
    }

    function hideModal() {
        var m = document.getElementById('logoutModal');
        if (m) m.classList.add('hidden');
    }

    function showModal() {
        var m = getModal();
        m.classList.remove('hidden');
    }

    window.showLogoutModal = function(logoutUrl, event) {
        if (event) event.preventDefault();
        showModal();

        var modal = getModal();
        var cancelBtn = document.getElementById('logoutModalCancel');
        var confirmBtn = document.getElementById('logoutModalConfirm');
        var backdrop = document.getElementById('logoutModalBackdrop');

        function doLogout() {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Logging out…';
            fetch(logoutUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success && data.data && data.data.redirect) {
                        window.location.href = data.data.redirect;
                    } else {
                        window.location.href = logoutUrl;
                    }
                })
                .catch(function() {
                    window.location.href = logoutUrl;
                });
        }

        function cleanup() {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Log out';
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            backdrop.removeEventListener('click', onCancel);
            modal.removeEventListener('keydown', onKey);
            hideModal();
        }

        function onConfirm() {
            cleanup();
            doLogout();
        }

        function onCancel() {
            cleanup();
        }

        function onKey(e) {
            if (e.key === 'Escape') onCancel();
        }

        cancelBtn.addEventListener('click', onCancel);
        confirmBtn.addEventListener('click', onConfirm);
        backdrop.addEventListener('click', onCancel);
        modal.addEventListener('keydown', onKey);
    };
})();
