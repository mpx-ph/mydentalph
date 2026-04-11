<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/provider_tenant_lite_bootstrap.php';
require_once dirname(__DIR__) . '/provider_tenant_plan_and_site_context.inc.php';
require_once dirname(__DIR__) . '/provider_tenant_header_context.inc.php';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/tenant_public_services_lib.php';

$provider_nav_active = 'clinical_services';
$provider_portal_path_prefix = '../';

$services_initial = tenant_public_services_fetch_for_tenant($pdo, (string) $tenant_id);
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Clinical services</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-variant": "#f1f5f9",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#e2e8f0",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#475569",
                        "background": "#f8fafc",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "error": "#ba1a1a"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        html { scrollbar-gutter: stable; }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .active-glow { box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4); }
        .provider-nav-link:not(.provider-nav-link--active):hover { transform: translateX(4px); }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .provider-card-lift {
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .provider-card-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        body { font-family: 'Manrope', sans-serif; }
        #svc-modal[hidden] { display: none !important; }
    </style>
</head>
<body class="mesh-bg font-body text-on-background min-h-screen selection:bg-primary/10">
<?php include dirname(__DIR__) . '/provider_tenant_sidebar.inc.php'; ?>
<?php include dirname(__DIR__) . '/provider_tenant_top_header.inc.php'; ?>
<main class="ml-64 pt-[4.5rem] sm:pt-24 min-h-screen provider-page-enter">
<div class="pt-4 sm:pt-6 px-6 lg:px-10 pb-20 space-y-8">
<section class="p-8 sm:p-10 flex flex-col gap-6">
<div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-8">
<div>
<p class="text-primary font-bold text-xs uppercase tracking-[0.3em] mb-2">Catalog</p>
<h2 class="font-headline font-extrabold tracking-tighter text-on-background text-4xl sm:text-5xl">
                Clinical <span class="font-editorial italic font-normal text-primary">services</span>
</h2>
<p class="font-body text-sm font-semibold text-on-surface-variant uppercase tracking-[0.2em] mt-3">Configure and price your clinical treatment offerings</p>
</div>
<button type="button" id="btn-open-add-service" class="inline-flex items-center justify-center gap-3 rounded-full bg-[#0f172a] text-white px-8 py-4 text-[11px] font-black uppercase tracking-[0.15em] shadow-lg shadow-slate-900/20 hover:shadow-xl hover:scale-[1.02] active:scale-[0.98] transition-all shrink-0">
<span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-white/30">
<span class="material-symbols-outlined text-lg">add</span>
</span>
                New treatment
            </button>
</div>

<div id="services-empty" class="<?php echo count($services_initial) > 0 ? 'hidden' : ''; ?> py-24 text-center text-on-surface-variant/80 text-lg font-medium">
            No services yet. Click &ldquo;New treatment&rdquo; to get started.
        </div>

<div id="services-list" class="space-y-4 <?php echo count($services_initial) === 0 ? 'hidden' : ''; ?>">
<?php foreach ($services_initial as $s): ?>
<div class="service-row elevated-card rounded-2xl p-6 flex flex-col sm:flex-row sm:items-center gap-4 border border-slate-100" data-id="<?php echo (int) $s['id']; ?>">
<div class="flex-1 min-w-0">
<h3 class="font-headline font-bold text-lg text-on-background"><?php echo htmlspecialchars((string) $s['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
<?php if (trim((string) ($s['description'] ?? '')) !== '') { ?>
<p class="text-on-surface-variant text-sm mt-1 line-clamp-3"><?php echo nl2br(htmlspecialchars((string) $s['description'], ENT_QUOTES, 'UTF-8')); ?></p>
<?php } ?>
<?php if (trim((string) ($s['price_range'] ?? '')) !== '') { ?>
<p class="text-primary font-bold text-sm mt-2 tabular-nums"><?php echo htmlspecialchars((string) $s['price_range'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>
</div>
<button type="button" class="btn-delete-service shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 text-error text-xs font-black uppercase tracking-widest hover:bg-error/5 transition-colors" data-id="<?php echo (int) $s['id']; ?>">
<span class="material-symbols-outlined text-lg">delete</span> Remove
                </button>
</div>
<?php endforeach; ?>
</div>
</section>
</div>
</main>

<!-- Modal: add treatment -->
<div id="svc-modal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" hidden>
<div class="absolute inset-0" id="svc-modal-backdrop" aria-hidden="true"></div>
<div class="relative w-full max-w-lg elevated-card rounded-3xl shadow-2xl p-8 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="svc-modal-title">
<div class="flex justify-between items-start gap-4 mb-6">
<div>
<h2 id="svc-modal-title" class="font-headline font-extrabold text-xl uppercase tracking-tight text-on-background">Add treatment</h2>
<p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mt-1">Configure a registered clinical procedure</p>
</div>
<button type="button" id="svc-modal-close" class="rounded-full p-2 border border-slate-200 text-on-surface-variant hover:bg-slate-50 transition-colors" aria-label="Close">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form id="form-add-service" class="space-y-5">
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="f-title">Main title</label>
<input type="text" id="f-title" name="title" required maxlength="255" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="e.g. Dental crown"/>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="f-desc">Description</label>
<textarea id="f-desc" name="description" rows="4" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary min-h-[6rem]" placeholder="Briefly describe what patients can expect"></textarea>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2" for="f-price">Price range</label>
<input type="text" id="f-price" name="price_range" maxlength="255" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary tabular-nums" placeholder="e.g. ₱5,000 – ₱12,000"/>
</div>
<p id="form-add-err" class="text-sm text-error font-medium hidden"></p>
<div class="flex flex-col-reverse sm:flex-row gap-3 pt-2">
<button type="button" id="btn-cancel-modal" class="flex-1 rounded-2xl border border-slate-200 bg-white py-3.5 text-sm font-black uppercase tracking-widest text-primary hover:bg-slate-50 transition-colors">Cancel</button>
<button type="submit" id="btn-save-treatment" class="flex-1 rounded-2xl bg-primary text-white py-3.5 text-sm font-black uppercase tracking-widest shadow-lg shadow-primary/25 hover:opacity-95 disabled:opacity-50 disabled:cursor-not-allowed">Save treatment</button>
</div>
</form>
</div>
</div>

<script>
(function () {
    var apiUrl = <?php echo json_encode(BASE_URL . 'api/tenant_public_services.php', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
    var modal = document.getElementById('svc-modal');
    var openBtn = document.getElementById('btn-open-add-service');
    var closeBtn = document.getElementById('svc-modal-close');
    var backdrop = document.getElementById('svc-modal-backdrop');
    var cancelBtn = document.getElementById('btn-cancel-modal');
    var form = document.getElementById('form-add-service');
    var errEl = document.getElementById('form-add-err');
    var listEl = document.getElementById('services-list');
    var emptyEl = document.getElementById('services-empty');

    function openModal() {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        var t = document.getElementById('f-title');
        if (t) { t.focus(); }
    }
    function closeModal() {
        modal.hidden = true;
        document.body.style.overflow = '';
        errEl.classList.add('hidden');
        errEl.textContent = '';
        form.reset();
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    function rowHtml(s) {
        var id = s.id;
        var title = escapeHtml(s.title || '');
        var desc = (s.description || '').trim();
        var price = (s.price_range || '').trim();
        var descBlock = desc ? '<p class="text-on-surface-variant text-sm mt-1 line-clamp-3">' + nl2br(escapeHtml(desc)) + '</p>' : '';
        var priceBlock = price ? '<p class="text-primary font-bold text-sm mt-2 tabular-nums">' + escapeHtml(price) + '</p>' : '';
        return '<div class="service-row elevated-card rounded-2xl p-6 flex flex-col sm:flex-row sm:items-center gap-4 border border-slate-100" data-id="' + id + '">' +
            '<div class="flex-1 min-w-0">' +
            '<h3 class="font-headline font-bold text-lg text-on-background">' + title + '</h3>' +
            descBlock + priceBlock +
            '</div>' +
            '<button type="button" class="btn-delete-service shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 text-error text-xs font-black uppercase tracking-widest hover:bg-error/5 transition-colors" data-id="' + id + '">' +
            '<span class="material-symbols-outlined text-lg">delete</span> Remove</button></div>';
    }
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }
    function nl2br(str) {
        return escapeHtml(str).replace(/\n/g, '<br/>');
    }

    function refreshVisibility() {
        var has = listEl.querySelectorAll('.service-row').length > 0;
        emptyEl.classList.toggle('hidden', has);
        listEl.classList.toggle('hidden', !has);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        errEl.classList.add('hidden');
        errEl.textContent = '';
        var btn = document.getElementById('btn-save-treatment');
        btn.disabled = true;
        var payload = {
            title: (document.getElementById('f-title').value || '').trim(),
            description: (document.getElementById('f-desc').value || '').trim(),
            price_range: (document.getElementById('f-price').value || '').trim()
        };
        fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j.success) {
                errEl.textContent = j.message || 'Could not save.';
                errEl.classList.remove('hidden');
                return;
            }
            var svc = j.data && j.data.service ? j.data.service : null;
            if (svc) {
                listEl.insertAdjacentHTML('beforeend', rowHtml(svc));
            }
            closeModal();
            refreshVisibility();
        }).catch(function () {
            errEl.textContent = 'Network error. Try again.';
            errEl.classList.remove('hidden');
        }).finally(function () {
            btn.disabled = false;
        });
    });

    listEl.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-delete-service');
        if (!btn) return;
        var id = btn.getAttribute('data-id');
        if (!id) return;
        var ask = window.providerNotify
            ? window.providerNotify.confirm('This removes the service from your public clinic page. Patients will no longer see it.', {
                title: 'Remove service',
                variant: 'danger',
                confirmText: 'Remove',
                cancelText: 'Cancel'
            })
            : Promise.resolve(window.confirm('Remove this service from your public page?'));
        ask.then(function (ok) {
            if (!ok) return;
            fetch(apiUrl, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id, 10) }),
                credentials: 'same-origin'
            }).then(function (r) { return r.json(); }).then(function (j) {
                if (!j.success) {
                    if (window.providerNotify) {
                        window.providerNotify.alert(j.message || 'Could not remove.', { variant: 'error' });
                    } else {
                        window.alert(j.message || 'Could not remove.');
                    }
                    return;
                }
                var row = listEl.querySelector('.service-row[data-id="' + id + '"]');
                if (row) row.remove();
                refreshVisibility();
            }).catch(function () {
                if (window.providerNotify) {
                    window.providerNotify.alert('Network error.', { variant: 'error', title: 'Connection problem' });
                } else {
                    window.alert('Network error.');
                }
            });
        });
    });
})();
</script>
<?php include dirname(__DIR__) . '/provider_tenant_profile_modal.inc.php'; ?>
</body></html>
