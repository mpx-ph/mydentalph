<?php
/**
 * Admin Manage Services & Pricing Page
 * Requires admin authentication
 */
$pageTitle = 'Manage Services & Pricing - Dental Clinic Admin';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // Require admin role
require_once __DIR__ . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-8 sticky top-0 z-10 shrink-0">
<div>
<h1 class="text-2xl font-bold">Manage Services & Pricing</h1>
<p class="text-sm text-slate-500 dark:text-slate-400">Set clinic rates and organize dental care categories for patient billing.</p>
</div>
</header>
<div class="flex-1 overflow-y-auto p-8">
<div class="mx-auto max-w-6xl space-y-8">
<!-- Search, Export, and New Service -->
<div class="flex items-center justify-between gap-4 flex-wrap">
<div class="relative flex-1 min-w-[200px]">
<span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input id="searchInput" class="w-full pl-10 pr-4 py-2 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all" placeholder="Search services..." type="text"/>
</div>
<div class="flex gap-2">
<button onclick="exportToCSV()" class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white text-sm font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
<span class="material-icons-outlined text-lg">download</span>
<span>Export CSV</span>
</button>
<button onclick="openNewServiceModal()" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-full font-semibold flex items-center gap-2 transition-all shadow-lg shadow-primary/20">
<span class="material-icons-outlined">add</span>
                    New Service
                </button>
</div>
</div>
<!-- Filter Chips -->
<div class="flex items-center justify-between bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
<div class="flex gap-2 flex-wrap items-center">
<span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider ml-2 mr-2">Filter By:</span>
<button onclick="filterByCategory(null)" class="filter-category-btn flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-lg bg-primary text-white px-4 text-sm font-semibold" data-category="">
<span>All Services</span>
</button>
<button onclick="filterByCategory('General Dentistry')" class="filter-category-btn flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-slate-200 px-4 text-sm font-medium hover:bg-slate-200 dark:hover:bg-slate-600" data-category="General Dentistry">
<span>General Dentistry</span>
</button>
<button onclick="filterByCategory('Oral Surgery')" class="filter-category-btn flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-slate-200 px-4 text-sm font-medium hover:bg-slate-200 dark:hover:bg-slate-600" data-category="Oral Surgery">
<span>Oral Surgery</span>
</button>
<button onclick="filterByCategory('Cosmetic Dentistry')" class="filter-category-btn flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-slate-200 px-4 text-sm font-medium hover:bg-slate-200 dark:hover:bg-slate-600" data-category="Cosmetic Dentistry">
<span>Cosmetic Dentistry</span>
</button>
<button onclick="filterByCategory('Orthodontics')" class="filter-category-btn flex h-9 shrink-0 items-center justify-center gap-x-2 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-900 dark:text-slate-200 px-4 text-sm font-medium hover:bg-slate-200 dark:hover:bg-slate-600" data-category="Orthodontics">
<span>Orthodontics</span>
</button>
</div>
<div class="flex items-center gap-2 pr-2">
<span class="material-symbols-outlined text-slate-500 dark:text-slate-400">sort</span>
<select id="sortSelect" onchange="sortServices()" class="bg-transparent border-none text-sm font-medium text-slate-500 dark:text-slate-400 focus:ring-0 cursor-pointer">
<option value="name">Sort by: Name</option>
<option value="price-high">Sort by: Price High-Low</option>
<option value="price-low">Sort by: Price Low-High</option>
</select>
</div>
</div>
<!-- Data Table -->
<div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
<th class="px-6 py-4 text-slate-900 dark:text-slate-300 text-xs font-bold uppercase tracking-wider">Service Name</th>
<th class="px-6 py-4 text-slate-900 dark:text-slate-300 text-xs font-bold uppercase tracking-wider">Category</th>
<th class="px-6 py-4 text-slate-900 dark:text-slate-300 text-xs font-bold uppercase tracking-wider">Current Price</th>
<th class="px-6 py-4 text-slate-900 dark:text-slate-300 text-xs font-bold uppercase tracking-wider">Status</th>
<th class="px-6 py-4 text-slate-900 dark:text-slate-300 text-xs font-bold uppercase tracking-wider">Last Updated</th>
<th class="px-6 py-4 text-right text-slate-900 dark:text-slate-300 text-xs font-bold uppercase tracking-wider">Actions</th>
</tr>
</thead>
<tbody id="servicesTableBody" class="divide-y divide-slate-100 dark:divide-slate-700">
<tr>
<td colspan="6" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
<span class="material-symbols-outlined text-4xl mb-2 opacity-50">hourglass_empty</span>
<p>Loading services...</p>
</td>
</tr>
</tbody>
</table>
<div id="paginationContainer" class="px-6 py-4 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between hidden">
<p id="paginationInfo" class="text-sm text-slate-500 dark:text-slate-400"></p>
<div id="paginationButtons" class="flex gap-2"></div>
</div>
</div>
<!-- Price Alert Info -->
<div class="flex items-center gap-4 p-4 bg-primary/5 dark:bg-primary/10 border border-primary/20 rounded-xl">
<span class="material-symbols-outlined text-primary text-3xl">info</span>
<div class="flex-1">
<p class="text-slate-900 dark:text-white text-sm font-bold">Price Update Policy</p>
<p class="text-slate-500 dark:text-slate-400 text-sm">Updated pricing will reflect immediately on patient invoices and the online booking portal. All changes are logged for administrative review.</p>
</div>
</div>
</div>
</div>
</div>

<!-- New Service Modal -->
<div id="newServiceModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto flex flex-col">
<div class="sticky top-0 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-6 py-4 flex items-center justify-between z-10">
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Add New Service</h3>
<button onclick="closeNewServiceModal()" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors">
<span class="material-symbols-outlined text-slate-500 dark:text-slate-400">close</span>
</button>
</div>
<div class="flex-1 overflow-y-auto p-6">
<div class="space-y-6">
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Service Name <span class="text-red-500">*</span></label>
<input type="text" id="newServiceName" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-primary/20 transition-all" placeholder="Enter service name" required/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Service Details</label>
<textarea id="newServiceDetails" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-primary/20 transition-all" placeholder="Enter service description"></textarea>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Category <span class="text-red-500">*</span></label>
<select id="newServiceCategory" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-primary/20 transition-all" required>
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
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Price (₱) <span class="text-red-500">*</span></label>
<input type="number" id="newServicePrice" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-primary/20 transition-all" placeholder="0.00" required/>
</div>
</div>
</div>
<div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 px-6 py-4 flex justify-end gap-3 z-10">
<button onclick="closeNewServiceModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">
Cancel
</button>
<button onclick="saveNewService()" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-semibold shadow-lg shadow-primary/30 transition-all">
Add Service
</button>
</div>
</div>
</div>

<!-- Edit Service Modal -->
<div id="editServiceModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto flex flex-col">
<div class="sticky top-0 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-6 py-4 flex items-center justify-between z-10">
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Edit Service</h3>
<button onclick="closeEditServiceModal()" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors">
<span class="material-symbols-outlined text-slate-500 dark:text-slate-400">close</span>
</button>
</div>
<div class="flex-1 overflow-y-auto p-6">
<div class="space-y-6">
<input type="hidden" id="editServiceId"/>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Service ID</label>
<input type="text" id="editServiceIdCode" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-400" readonly/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Service Name <span class="text-red-500">*</span></label>
<input type="text" id="editServiceName" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-primary/20 transition-all" placeholder="Enter service name" required/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Service Details</label>
<textarea id="editServiceDetails" rows="3" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-primary/20 transition-all" placeholder="Enter service description"></textarea>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Category <span class="text-red-500">*</span></label>
<select id="editServiceCategory" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-primary/20 transition-all" required>
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
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Price (₱) <span class="text-red-500">*</span></label>
<input type="number" id="editServicePrice" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white focus:border-primary focus:ring-primary/20 transition-all" placeholder="0.00" required/>
</div>
<div>
<label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Status</label>
<div class="flex items-center gap-4">
<label class="flex items-center gap-2 cursor-pointer">
<input type="radio" name="editServiceStatus" value="active" id="editServiceStatusActive" class="w-4 h-4 text-primary border-slate-300 focus:ring-primary"/>
<span class="text-slate-700 dark:text-slate-300">Active</span>
</label>
<label class="flex items-center gap-2 cursor-pointer">
<input type="radio" name="editServiceStatus" value="inactive" id="editServiceStatusInactive" class="w-4 h-4 text-primary border-slate-300 focus:ring-primary"/>
<span class="text-slate-700 dark:text-slate-300">Inactive</span>
</label>
</div>
</div>
</div>
</div>
<div class="sticky bottom-0 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 px-6 py-4 flex justify-end gap-3 z-10">
<button onclick="closeEditServiceModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-all">
Cancel
</button>
<button onclick="saveServiceChanges()" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-semibold shadow-lg shadow-primary/30 transition-all">
Save Changes
</button>
</div>
</div>
</div>

<script>
let allServices = [];
let filteredServices = [];
let currentPage = 1;
let itemsPerPage = 10;
let currentCategory = null;
let currentSearchTerm = '';

// Category color mapping
const categoryColors = {
    'General Dentistry': { bg: 'bg-blue-100', text: 'text-blue-800', darkBg: 'dark:bg-blue-900/30', darkText: 'dark:text-blue-300' },
    'Restorative Dentistry': { bg: 'bg-green-100', text: 'text-green-800', darkBg: 'dark:bg-green-900/30', darkText: 'dark:text-green-300' },
    'Oral Surgery': { bg: 'bg-red-100', text: 'text-red-800', darkBg: 'dark:bg-red-900/30', darkText: 'dark:text-red-300' },
    'Crowns and Bridges': { bg: 'bg-yellow-100', text: 'text-yellow-800', darkBg: 'dark:bg-yellow-900/30', darkText: 'dark:text-yellow-300' },
    'Cosmetic Dentistry': { bg: 'bg-purple-100', text: 'text-purple-800', darkBg: 'dark:bg-purple-900/30', darkText: 'dark:text-purple-300' },
    'Pediatric Dentistry': { bg: 'bg-pink-100', text: 'text-pink-800', darkBg: 'dark:bg-pink-900/30', darkText: 'dark:text-pink-300' },
    'Orthodontics': { bg: 'bg-orange-100', text: 'text-orange-800', darkBg: 'dark:bg-orange-900/30', darkText: 'dark:text-orange-300' },
    'Specialized and Others': { bg: 'bg-slate-100', text: 'text-slate-800', darkBg: 'dark:bg-slate-900/30', darkText: 'dark:text-slate-300' }
};

// Load services on page load
document.addEventListener('DOMContentLoaded', function() {
    loadServices();
    
    // Add search input event listener
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearchTerm = searchInput.value.trim().toLowerCase();
                currentPage = 1;
                applyFilters();
            }, 300); // Debounce search
        });
    }
});

function loadServices() {
    // Request a high limit to get all services
    fetch('<?php echo BASE_URL; ?>api/services.php?limit=10000')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allServices = data.data.services || [];
                currentPage = 1;
                applyFilters();
            } else {
                showError('Failed to load services: ' + (data.message || 'Unknown error'));
                document.getElementById('servicesTableBody').innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">No services found.</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading services:', error);
            showError('Network error: Failed to load services.');
            document.getElementById('servicesTableBody').innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-red-500">Error loading services. Please refresh the page.</td></tr>';
        });
}

function renderServices() {
    const tbody = document.getElementById('servicesTableBody');
    
    if (filteredServices.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">No services found.</td></tr>';
        document.getElementById('paginationContainer').classList.add('hidden');
        return;
    }
    
    // Sort services
    const sortValue = document.getElementById('sortSelect').value;
    let sorted = [...filteredServices];
    
    if (sortValue === 'price-high') {
        sorted.sort((a, b) => parseFloat(b.price) - parseFloat(a.price));
    } else if (sortValue === 'price-low') {
        sorted.sort((a, b) => parseFloat(a.price) - parseFloat(b.price));
    } else {
        sorted.sort((a, b) => a.service_name.localeCompare(b.service_name));
    }
    
    // Pagination
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const paginatedServices = sorted.slice(startIndex, endIndex);
    
    let html = '';
    paginatedServices.forEach(service => {
        const categoryColor = categoryColors[service.category] || categoryColors['Specialized and Others'];
        const formattedPrice = '₱' + parseFloat(service.price).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const updatedDate = service.updated_at ? new Date(service.updated_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
        const statusBadge = service.status === 'active' 
            ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">Active</span>'
            : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-800 dark:bg-slate-900/30 dark:text-slate-300">Inactive</span>';
        
        html += `
            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                <td class="px-6 py-4">
                    <div class="flex flex-col">
                        <span class="text-slate-900 dark:text-white font-bold">${escapeHtml(service.service_name)}</span>
                        ${service.service_details ? `<span class="text-xs text-slate-500 dark:text-slate-400">${escapeHtml(service.service_details)}</span>` : ''}
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${categoryColor.bg} ${categoryColor.text} ${categoryColor.darkBg} ${categoryColor.darkText}">
                        ${escapeHtml(service.category)}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="text-slate-900 dark:text-white font-semibold">${formattedPrice}</span>
                </td>
                <td class="px-6 py-4">
                    ${statusBadge}
                </td>
                <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">${updatedDate}</td>
                <td class="px-6 py-4 text-right">
                    <button onclick="openEditServiceModal(${service.id})" class="text-primary font-bold text-sm hover:underline flex items-center gap-1 ml-auto">
                        <span class="material-symbols-outlined text-sm">edit</span> Edit
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
    updatePagination();
}

function updatePagination() {
    const totalItems = filteredServices.length;
    const totalPages = Math.ceil(totalItems / itemsPerPage);
    
    if (totalPages <= 1) {
        document.getElementById('paginationContainer').classList.add('hidden');
        return;
    }
    
    document.getElementById('paginationContainer').classList.remove('hidden');
    document.getElementById('paginationInfo').textContent = `Showing ${((currentPage - 1) * itemsPerPage) + 1} to ${Math.min(currentPage * itemsPerPage, totalItems)} of ${totalItems} services`;
    
    const paginationButtons = document.getElementById('paginationButtons');
    let html = '';
    
    html += `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''} class="px-3 py-1 border border-slate-200 dark:border-slate-700 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white dark:hover:bg-slate-700">Previous</button>`;
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            html += `<button onclick="changePage(${i})" class="px-3 py-1 ${i === currentPage ? 'bg-primary text-white' : 'border border-slate-200 dark:border-slate-700'} rounded text-sm font-bold hover:bg-white dark:hover:bg-slate-700">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            html += `<span class="px-3 py-1 text-slate-500">...</span>`;
        }
    }
    
    html += `<button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''} class="px-3 py-1 border border-slate-200 dark:border-slate-700 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white dark:hover:bg-slate-700">Next</button>`;
    
    paginationButtons.innerHTML = html;
}

function changePage(page) {
    const totalPages = Math.ceil(filteredServices.length / itemsPerPage);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderServices();
}

function applyFilters() {
    // Apply category filter
    let filtered = [...allServices];
    
    if (currentCategory) {
        filtered = filtered.filter(s => s.category === currentCategory);
    }
    
    // Apply search filter
    if (currentSearchTerm) {
        filtered = filtered.filter(s => {
            const serviceName = (s.service_name || '').toLowerCase();
            const serviceDetails = (s.service_details || '').toLowerCase();
            const category = (s.category || '').toLowerCase();
            const serviceId = (s.service_id || '').toLowerCase();
            
            return serviceName.includes(currentSearchTerm) ||
                   serviceDetails.includes(currentSearchTerm) ||
                   category.includes(currentSearchTerm) ||
                   serviceId.includes(currentSearchTerm);
        });
    }
    
    filteredServices = filtered;
    renderServices();
}

function filterByCategory(category) {
    currentCategory = category;
    currentPage = 1;
    
    // Update filter button styles
    document.querySelectorAll('.filter-category-btn').forEach(btn => {
        const btnCategory = btn.getAttribute('data-category') || null;
        if (btnCategory === category) {
            btn.classList.remove('bg-slate-100', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-slate-200', 'font-medium');
            btn.classList.add('bg-primary', 'text-white', 'font-semibold');
        } else {
            btn.classList.remove('bg-primary', 'text-white', 'font-semibold');
            btn.classList.add('bg-slate-100', 'dark:bg-slate-700', 'text-slate-900', 'dark:text-slate-200', 'font-medium');
        }
    });
    
    applyFilters();
}

function sortServices() {
    renderServices();
}

function openNewServiceModal() {
    document.getElementById('newServiceModal').classList.remove('hidden');
    document.getElementById('newServiceModal').classList.add('flex');
    // Reset form
    document.getElementById('newServiceName').value = '';
    document.getElementById('newServiceDetails').value = '';
    document.getElementById('newServiceCategory').value = '';
    document.getElementById('newServicePrice').value = '';
}

function closeNewServiceModal() {
    document.getElementById('newServiceModal').classList.add('hidden');
    document.getElementById('newServiceModal').classList.remove('flex');
}

function saveNewService() {
    const serviceName = document.getElementById('newServiceName').value.trim();
    const serviceDetails = document.getElementById('newServiceDetails').value.trim();
    const category = document.getElementById('newServiceCategory').value;
    const price = parseFloat(document.getElementById('newServicePrice').value);
    
    // Validation
    if (!serviceName || !category || isNaN(price) || price < 0) {
        alert('Please fill in all required fields with valid values.');
        return;
    }
    
    const serviceData = {
        service_name: serviceName,
        service_details: serviceDetails,
        category: category,
        price: price
    };
    
    fetch('<?php echo BASE_URL; ?>api/services.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(serviceData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Service added successfully!');
            closeNewServiceModal();
            loadServices();
        } else {
            alert('Error: ' + (data.message || 'Failed to add service. Please try again.'));
        }
    })
    .catch(error => {
        console.error('Error saving service:', error);
        alert('Network error: Failed to save service. Please try again.');
    });
}

function openEditServiceModal(serviceId) {
    const service = allServices.find(s => s.id === serviceId);
    if (!service) {
        alert('Service not found.');
        return;
    }
    
    document.getElementById('editServiceId').value = service.id;
    document.getElementById('editServiceIdCode').value = service.service_id;
    document.getElementById('editServiceName').value = service.service_name;
    document.getElementById('editServiceDetails').value = service.service_details || '';
    document.getElementById('editServiceCategory').value = service.category;
    document.getElementById('editServicePrice').value = service.price;
    
    if (service.status === 'active') {
        document.getElementById('editServiceStatusActive').checked = true;
    } else {
        document.getElementById('editServiceStatusInactive').checked = true;
    }
    
    document.getElementById('editServiceModal').classList.remove('hidden');
    document.getElementById('editServiceModal').classList.add('flex');
}

function closeEditServiceModal() {
    document.getElementById('editServiceModal').classList.add('hidden');
    document.getElementById('editServiceModal').classList.remove('flex');
}

function saveServiceChanges() {
    const serviceId = document.getElementById('editServiceId').value;
    const serviceName = document.getElementById('editServiceName').value.trim();
    const serviceDetails = document.getElementById('editServiceDetails').value.trim();
    const category = document.getElementById('editServiceCategory').value;
    const price = parseFloat(document.getElementById('editServicePrice').value);
    const status = document.querySelector('input[name="editServiceStatus"]:checked').value;
    
    // Validation
    if (!serviceName || !category || isNaN(price) || price < 0) {
        alert('Please fill in all required fields with valid values.');
        return;
    }
    
    const serviceData = {
        id: parseInt(serviceId),
        service_name: serviceName,
        service_details: serviceDetails,
        category: category,
        price: price,
        status: status
    };
    
    fetch('<?php echo BASE_URL; ?>api/services.php', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(serviceData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Service updated successfully!');
            closeEditServiceModal();
            loadServices();
        } else {
            alert('Error: ' + (data.message || 'Failed to update service. Please try again.'));
        }
    })
    .catch(error => {
        console.error('Error updating service:', error);
        alert('Network error: Failed to update service. Please try again.');
    });
}

function exportToCSV() {
    const csv = [
        ['Service ID', 'Service Name', 'Service Details', 'Category', 'Price', 'Status'].join(','),
        ...filteredServices.map(s => [
            s.service_id,
            `"${s.service_name}"`,
            `"${(s.service_details || '').replace(/"/g, '""')}"`,
            s.category,
            s.price,
            s.status
        ].join(','))
    ].join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'services_' + new Date().toISOString().split('T')[0] + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    console.error(message);
    // You can implement a toast notification here if needed
}
</script>
