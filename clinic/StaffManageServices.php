<?php
$staff_nav_active = 'services';
require_once __DIR__ . '/config/config.php';
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

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Manage Services &amp; Pricing | Precision Dental</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<div class="p-10 space-y-8">
<section class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> CLINICAL SERVICES
            </div>
<div class="flex items-end justify-between gap-4 flex-wrap">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Manage Services &amp; <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Pricing</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">Update clinic services, categories, and pricing for booking and billing.</p>
</div>
<button id="openNewServiceBtn" class="px-6 py-3.5 bg-primary text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-primary/30 hover:scale-[1.02] active:scale-95 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">add</span>
                    Add New Service
                </button>
</div>
</section>

<section class="elevated-card rounded-3xl overflow-hidden">
<div class="p-6 border-b border-slate-100 flex flex-col gap-4 bg-white">
<div class="flex items-center justify-between gap-3 flex-wrap">
<div class="relative flex-1 min-w-[280px] max-w-xl">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">search</span>
<input id="searchInput" class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Search services..." type="text"/>
</div>
<div class="flex items-center gap-3">
<button id="exportCsvBtn" class="px-4 py-2.5 border border-slate-200 text-slate-700 text-[11px] font-bold uppercase tracking-wider rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">download</span> Export CSV
                    </button>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">sort</span>
<select id="sortSelect" class="appearance-none pl-9 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[11px] font-bold uppercase tracking-wider focus:ring-2 focus:ring-primary/20 focus:border-primary cursor-pointer">
<option value="name">Sort: Name</option>
<option value="price-high">Price: High-Low</option>
<option value="price-low">Price: Low-High</option>
</select>
<span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">expand_more</span>
</div>
</div>
</div>
<div id="categoryFilters" class="flex flex-wrap items-center gap-2">
<button type="button" class="category-btn px-4 py-2 rounded-lg bg-primary text-white text-xs font-bold tracking-wide" data-category="">All Services</button>
</div>
</div>

<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/70 border-b border-slate-100">
<th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Service Name</th>
<th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Category</th>
<th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Current Price</th>
<th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Status</th>
<th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Last Updated</th>
<th class="px-6 py-4 text-[10px] font-black text-slate-500 uppercase tracking-widest text-right">Actions</th>
</tr>
</thead>
<tbody id="servicesTableBody" class="divide-y divide-slate-100">
<tr>
<td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">Loading services...</td>
</tr>
</tbody>
</table>
</div>

<div id="paginationContainer" class="hidden p-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between gap-4">
<p id="paginationInfo" class="text-[11px] font-bold text-slate-500 uppercase tracking-widest"></p>
<div id="paginationButtons" class="flex items-center gap-2"></div>
</div>
</section>
</div>
<div class="h-10"></div>
</main>

<div id="newServiceModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/45 p-4">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
<div class="sticky top-0 bg-white border-b border-slate-100 px-6 py-4 flex items-center justify-between">
<h3 class="text-2xl font-bold font-headline text-on-background">Add New Service</h3>
<button id="closeNewServiceBtn" class="p-2 rounded-lg hover:bg-slate-100 transition-colors"><span class="material-symbols-outlined text-slate-500">close</span></button>
</div>
<div class="p-6 space-y-5">
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Service Name <span class="text-red-500">*</span></label>
<input type="text" id="newServiceName" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:border-primary focus:ring-primary/20 transition-all" required/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Service Details</label>
<textarea id="newServiceDetails" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:border-primary focus:ring-primary/20 transition-all"></textarea>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Category <span class="text-red-500">*</span></label>
<select id="newServiceCategory" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:border-primary focus:ring-primary/20 transition-all" required>
<option value="">Select category</option>
<option value="General Dentistry">General Dentistry</option>
<option value="Restorative Dentistry">Restorative Dentistry</option>
<option value="Oral Surgery">Oral Surgery</option>
<option value="Crowns and Bridges">Crowns and Bridges</option>
<option value="Cosmetic Dentistry">Cosmetic Dentistry</option>
<option value="Pediatric Dentistry">Pediatric Dentistry</option>
<option value="Orthodontics">Orthodontics</option>
<option value="Specialized and Others">Specialized and Others</option>
</select>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Price (P) <span class="text-red-500">*</span></label>
<input type="number" id="newServicePrice" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:border-primary focus:ring-primary/20 transition-all" required/>
</div>
</div>
<div class="sticky bottom-0 bg-white border-t border-slate-100 px-6 py-4 flex justify-end gap-3">
<button id="cancelNewServiceBtn" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 font-semibold hover:bg-slate-50 transition-all">Cancel</button>
<button id="saveNewServiceBtn" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-semibold shadow-lg shadow-primary/30 transition-all">Add Service</button>
</div>
</div>
</div>

<div id="editServiceModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/45 p-4">
<div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
<div class="sticky top-0 bg-white border-b border-slate-100 px-6 py-4 flex items-center justify-between">
<h3 class="text-2xl font-bold font-headline text-on-background">Edit Service</h3>
<button id="closeEditServiceBtn" class="p-2 rounded-lg hover:bg-slate-100 transition-colors"><span class="material-symbols-outlined text-slate-500">close</span></button>
</div>
<div class="p-6 space-y-5">
<input type="hidden" id="editServiceId"/>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Service ID</label>
<input type="text" id="editServiceIdCode" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-slate-600" readonly/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Service Name <span class="text-red-500">*</span></label>
<input type="text" id="editServiceName" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:border-primary focus:ring-primary/20 transition-all" required/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Service Details</label>
<textarea id="editServiceDetails" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:border-primary focus:ring-primary/20 transition-all"></textarea>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Category <span class="text-red-500">*</span></label>
<select id="editServiceCategory" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:border-primary focus:ring-primary/20 transition-all" required>
<option value="">Select category</option>
<option value="General Dentistry">General Dentistry</option>
<option value="Restorative Dentistry">Restorative Dentistry</option>
<option value="Oral Surgery">Oral Surgery</option>
<option value="Crowns and Bridges">Crowns and Bridges</option>
<option value="Cosmetic Dentistry">Cosmetic Dentistry</option>
<option value="Pediatric Dentistry">Pediatric Dentistry</option>
<option value="Orthodontics">Orthodontics</option>
<option value="Specialized and Others">Specialized and Others</option>
</select>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Price (P) <span class="text-red-500">*</span></label>
<input type="number" id="editServicePrice" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 focus:border-primary focus:ring-primary/20 transition-all" required/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 mb-2">Status</label>
<div class="flex items-center gap-4">
<label class="flex items-center gap-2 cursor-pointer">
<input type="radio" name="editServiceStatus" value="active" id="editServiceStatusActive" class="w-4 h-4 text-primary border-slate-300 focus:ring-primary"/>
<span class="text-slate-700">Active</span>
</label>
<label class="flex items-center gap-2 cursor-pointer">
<input type="radio" name="editServiceStatus" value="inactive" id="editServiceStatusInactive" class="w-4 h-4 text-primary border-slate-300 focus:ring-primary"/>
<span class="text-slate-700">Inactive</span>
</label>
</div>
</div>
</div>
<div class="sticky bottom-0 bg-white border-t border-slate-100 px-6 py-4 flex justify-end gap-3">
<button id="cancelEditServiceBtn" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 font-semibold hover:bg-slate-50 transition-all">Cancel</button>
<button id="saveServiceChangesBtn" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-semibold shadow-lg shadow-primary/30 transition-all">Save Changes</button>
</div>
</div>
</div>

<script>
let allServices = [];
let filteredServices = [];
let currentPage = 1;
const itemsPerPage = 10;
let currentCategory = '';
let currentSearchTerm = '';

const apiUrl = <?php echo json_encode(BASE_URL . 'api/services.php', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
const categoryColors = {
    'General Dentistry': 'bg-blue-100 text-blue-700',
    'Restorative Dentistry': 'bg-green-100 text-green-700',
    'Oral Surgery': 'bg-rose-100 text-rose-700',
    'Crowns and Bridges': 'bg-amber-100 text-amber-700',
    'Cosmetic Dentistry': 'bg-violet-100 text-violet-700',
    'Pediatric Dentistry': 'bg-pink-100 text-pink-700',
    'Orthodontics': 'bg-orange-100 text-orange-700',
    'Specialized and Others': 'bg-slate-100 text-slate-700'
};

document.addEventListener('DOMContentLoaded', function () {
    bindEvents();
    loadServices();
});

function bindEvents() {
    document.getElementById('searchInput').addEventListener('input', debounce(function (e) {
        currentSearchTerm = (e.target.value || '').trim().toLowerCase();
        currentPage = 1;
        applyFilters();
    }, 250));

    document.getElementById('sortSelect').addEventListener('change', renderServices);
    document.getElementById('exportCsvBtn').addEventListener('click', exportToCSV);
    document.getElementById('openNewServiceBtn').addEventListener('click', openNewServiceModal);
    document.getElementById('closeNewServiceBtn').addEventListener('click', closeNewServiceModal);
    document.getElementById('cancelNewServiceBtn').addEventListener('click', closeNewServiceModal);
    document.getElementById('saveNewServiceBtn').addEventListener('click', saveNewService);
    document.getElementById('closeEditServiceBtn').addEventListener('click', closeEditServiceModal);
    document.getElementById('cancelEditServiceBtn').addEventListener('click', closeEditServiceModal);
    document.getElementById('saveServiceChangesBtn').addEventListener('click', saveServiceChanges);

    document.getElementById('servicesTableBody').addEventListener('click', function (e) {
        const btn = e.target.closest('[data-edit-id]');
        if (!btn) return;
        openEditServiceModal(parseInt(btn.getAttribute('data-edit-id'), 10));
    });

    document.getElementById('categoryFilters').addEventListener('click', function (e) {
        const btn = e.target.closest('.category-btn');
        if (!btn) return;
        currentCategory = btn.getAttribute('data-category') || '';
        currentPage = 1;
        renderCategoryButtons();
        applyFilters();
    });
}

function loadServices() {
    fetch(apiUrl + '?limit=10000', { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (!data.success) {
                throw new Error(data.message || 'Failed to load services.');
            }
            allServices = Array.isArray(data.data && data.data.services) ? data.data.services : [];
            renderCategoryButtons();
            applyFilters();
        })
        .catch(function (error) {
            console.error(error);
            document.getElementById('servicesTableBody').innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-sm text-red-500">Failed to load services.</td></tr>';
            document.getElementById('paginationContainer').classList.add('hidden');
        });
}

function renderCategoryButtons() {
    const container = document.getElementById('categoryFilters');
    const categories = Array.from(new Set(allServices.map(function (s) { return (s.category || '').trim(); }).filter(Boolean))).sort();
    const html = [
        '<button type="button" class="category-btn px-4 py-2 rounded-lg text-xs font-bold tracking-wide ' + (currentCategory === '' ? 'bg-primary text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200') + '" data-category="">All Services</button>'
    ];

    categories.forEach(function (category) {
        const active = category === currentCategory;
        html.push('<button type="button" class="category-btn px-4 py-2 rounded-lg text-xs font-bold tracking-wide ' + (active ? 'bg-primary text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200') + '" data-category="' + escapeHtmlAttr(category) + '">' + escapeHtml(category) + '</button>');
    });

    container.innerHTML = html.join('');
}

function applyFilters() {
    filteredServices = allServices.filter(function (service) {
        const categoryMatch = !currentCategory || service.category === currentCategory;
        if (!categoryMatch) {
            return false;
        }
        if (!currentSearchTerm) {
            return true;
        }
        const haystack = [
            service.service_name || '',
            service.service_details || '',
            service.category || '',
            service.service_id || ''
        ].join(' ').toLowerCase();
        return haystack.indexOf(currentSearchTerm) !== -1;
    });
    renderServices();
}

function renderServices() {
    const tbody = document.getElementById('servicesTableBody');
    if (filteredServices.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No services found.</td></tr>';
        document.getElementById('paginationContainer').classList.add('hidden');
        return;
    }

    const sortValue = document.getElementById('sortSelect').value;
    const sorted = filteredServices.slice().sort(function (a, b) {
        if (sortValue === 'price-high') {
            return parseFloat(b.price || 0) - parseFloat(a.price || 0);
        }
        if (sortValue === 'price-low') {
            return parseFloat(a.price || 0) - parseFloat(b.price || 0);
        }
        return String(a.service_name || '').localeCompare(String(b.service_name || ''));
    });

    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    const pageItems = sorted.slice(start, end);

    tbody.innerHTML = pageItems.map(function (service) {
        const serviceName = escapeHtml(service.service_name || '');
        const serviceDetails = escapeHtml(service.service_details || '');
        const serviceId = escapeHtml(service.service_id || '');
        const category = escapeHtml(service.category || 'Uncategorized');
        const colorClass = categoryColors[service.category] || 'bg-slate-100 text-slate-700';
        const price = Number(service.price || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const status = (service.status || '').toLowerCase() === 'active'
            ? '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700">Active</span>'
            : '<span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600">Inactive</span>';
        const updatedAt = service.updated_at ? new Date(service.updated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';

        return '<tr class="hover:bg-slate-50/60 transition-colors">' +
            '<td class="px-6 py-4"><div class="font-bold text-slate-900">' + serviceName + '</div>' +
            (serviceDetails ? '<div class="text-xs text-slate-500 mt-0.5">' + serviceDetails + '</div>' : '') +
            (serviceId ? '<div class="text-[10px] text-slate-400 mt-1 font-semibold uppercase tracking-wider">ID: ' + serviceId + '</div>' : '') +
            '</td>' +
            '<td class="px-6 py-4"><span class="px-2.5 py-1 rounded-full text-[11px] font-bold ' + colorClass + '">' + category + '</span></td>' +
            '<td class="px-6 py-4"><span class="font-extrabold text-slate-900">P' + price + '</span></td>' +
            '<td class="px-6 py-4">' + status + '</td>' +
            '<td class="px-6 py-4 text-sm text-slate-500">' + escapeHtml(updatedAt) + '</td>' +
            '<td class="px-6 py-4 text-right"><button class="text-primary font-bold text-sm hover:underline inline-flex items-center gap-1" data-edit-id="' + Number(service.id) + '"><span class="material-symbols-outlined text-sm">edit</span>Edit</button></td>' +
            '</tr>';
    }).join('');

    updatePagination(sorted.length);
}

function updatePagination(totalItems) {
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    const container = document.getElementById('paginationContainer');
    if (totalPages <= 1) {
        container.classList.add('hidden');
        return;
    }

    container.classList.remove('hidden');
    document.getElementById('paginationInfo').textContent =
        'Showing ' + (((currentPage - 1) * itemsPerPage) + 1) + ' to ' + Math.min(currentPage * itemsPerPage, totalItems) + ' of ' + totalItems + ' services';

    const buttons = [];
    buttons.push('<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 ' + (currentPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:text-primary') + '" ' + (currentPage === 1 ? 'disabled' : '') + ' onclick="changePage(' + (currentPage - 1) + ')"><span class="material-symbols-outlined text-sm">chevron_left</span></button>');
    for (let i = 1; i <= totalPages; i += 1) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            buttons.push('<button class="w-8 h-8 rounded-lg text-[11px] font-black ' + (i === currentPage ? 'bg-primary text-white' : 'border border-slate-200 text-slate-700 hover:text-primary') + '" onclick="changePage(' + i + ')">' + i + '</button>');
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            buttons.push('<span class="px-1 text-slate-400">...</span>');
        }
    }
    buttons.push('<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-500 ' + (currentPage === totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:text-primary') + '" ' + (currentPage === totalPages ? 'disabled' : '') + ' onclick="changePage(' + (currentPage + 1) + ')"><span class="material-symbols-outlined text-sm">chevron_right</span></button>');
    document.getElementById('paginationButtons').innerHTML = buttons.join('');
}

function changePage(page) {
    const totalPages = Math.ceil(filteredServices.length / itemsPerPage);
    if (page < 1 || page > totalPages) {
        return;
    }
    currentPage = page;
    renderServices();
}

function openNewServiceModal() {
    document.getElementById('newServiceModal').classList.remove('hidden');
    document.getElementById('newServiceModal').classList.add('flex');
    document.getElementById('newServiceName').focus();
}

function closeNewServiceModal() {
    document.getElementById('newServiceModal').classList.add('hidden');
    document.getElementById('newServiceModal').classList.remove('flex');
}

function openEditServiceModal(serviceId) {
    const service = allServices.find(function (s) { return Number(s.id) === Number(serviceId); });
    if (!service) {
        return;
    }
    document.getElementById('editServiceId').value = service.id;
    document.getElementById('editServiceIdCode').value = service.service_id || '';
    document.getElementById('editServiceName').value = service.service_name || '';
    document.getElementById('editServiceDetails').value = service.service_details || '';
    document.getElementById('editServiceCategory').value = service.category || '';
    document.getElementById('editServicePrice').value = service.price || '';
    document.getElementById((service.status || '').toLowerCase() === 'active' ? 'editServiceStatusActive' : 'editServiceStatusInactive').checked = true;
    document.getElementById('editServiceModal').classList.remove('hidden');
    document.getElementById('editServiceModal').classList.add('flex');
}

function closeEditServiceModal() {
    document.getElementById('editServiceModal').classList.add('hidden');
    document.getElementById('editServiceModal').classList.remove('flex');
}

function saveNewService() {
    const payload = {
        service_name: (document.getElementById('newServiceName').value || '').trim(),
        service_details: (document.getElementById('newServiceDetails').value || '').trim(),
        category: document.getElementById('newServiceCategory').value,
        price: parseFloat(document.getElementById('newServicePrice').value || '0')
    };

    if (!payload.service_name || !payload.category || Number.isNaN(payload.price) || payload.price < 0) {
        alert('Please complete required fields with a valid price.');
        return;
    }

    fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.success) {
            throw new Error(data.message || 'Failed to create service.');
        }
        closeNewServiceModal();
        loadServices();
    }).catch(function (err) {
        alert(err.message || 'Failed to create service.');
    });
}

function saveServiceChanges() {
    const selectedStatus = document.querySelector('input[name="editServiceStatus"]:checked');
    const payload = {
        id: parseInt(document.getElementById('editServiceId').value, 10),
        service_name: (document.getElementById('editServiceName').value || '').trim(),
        service_details: (document.getElementById('editServiceDetails').value || '').trim(),
        category: document.getElementById('editServiceCategory').value,
        price: parseFloat(document.getElementById('editServicePrice').value || '0'),
        status: selectedStatus ? selectedStatus.value : 'active'
    };

    if (!payload.id || !payload.service_name || !payload.category || Number.isNaN(payload.price) || payload.price < 0) {
        alert('Please complete required fields with a valid price.');
        return;
    }

    fetch(apiUrl, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'same-origin'
    }).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.success) {
            throw new Error(data.message || 'Failed to update service.');
        }
        closeEditServiceModal();
        loadServices();
    }).catch(function (err) {
        alert(err.message || 'Failed to update service.');
    });
}

function exportToCSV() {
    const rows = [
        ['Service ID', 'Service Name', 'Service Details', 'Category', 'Price', 'Status'].join(',')
    ];
    filteredServices.forEach(function (s) {
        rows.push([
            '"' + String(s.service_id || '').replace(/"/g, '""') + '"',
            '"' + String(s.service_name || '').replace(/"/g, '""') + '"',
            '"' + String(s.service_details || '').replace(/"/g, '""') + '"',
            '"' + String(s.category || '').replace(/"/g, '""') + '"',
            '"' + String(s.price || '').replace(/"/g, '""') + '"',
            '"' + String(s.status || '').replace(/"/g, '""') + '"'
        ].join(','));
    });
    const blob = new Blob([rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'services_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function debounce(fn, delay) {
    let timer;
    return function () {
        const args = arguments;
        clearTimeout(timer);
        timer = setTimeout(function () { fn.apply(null, args); }, delay);
    };
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
    });
}

function escapeHtmlAttr(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
}
</script>
</body></html>